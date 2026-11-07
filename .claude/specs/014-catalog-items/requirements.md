# 014 · catalog-items — Requirements
> **Spec:** 014 · **Alcance:** fullstack · **Módulo:** Inventory · **Prioridad:** alta · **Depende de specs:** 010, 013
> **Status:** Done
> **Última actualización:** 2026-10-19
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 2)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 013 — categorías, marcas, unidades. Spec 010 — impuesto por defecto.
> - Origen Multinort: `create_catalog_items_table.php`, `create_item_presentations_table.php`, `create_product_prices_table.php` (`catalog_item_prices`), `CatalogItemController.php`, `CatalogItemPriceController.php`.
>
---
## 1. Contexto de Negocio
El **catálogo maestro** es la lista única de lo que la empresa compra, almacena y vende: **bienes** (con stock) y **servicios** (sin stock). Cada bien se maneja en una **unidad base** y puede tener varias **presentaciones** (unidad, caja x12, saco de 50 kg), cada una con código de barras y **precios** (minorista y mayorista).

En Multinort, `catalog_items` mezcla conceptos del taller: `type` producto/servicio/kit, `is_tool`, `is_additional`, `estimated_time`, `quantity_liters`, componentes de kit, tarifas de mano de obra y presentaciones fraccionables (litros de aceite). Los precios viven en `catalog_item_prices` con tipo, moneda y vigencia. Aquí se conserva el núcleo genérico: ítem, presentaciones con factor hacia la unidad base y precios por presentación.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Administrador / Jefe de almacén | `admin`, `manager` | JWT · `catalog.view|create|update|delete` |
| Responsable de precios | `admin`, `manager` | JWT · `catalog.update_prices` |
| Vendedor / Comprador | `user` | JWT · `catalog.view` (búsqueda en POS y compras) |

## 3. User Stories (formato EARS)
### US-1 — CRUD de ítems del catálogo
**Como** jefe de almacén **quiero** registrar bienes y servicios **para** usarlos en compras, inventario y ventas.
**Cuando** se invoca `GET|POST /api/v1/inventory/catalog-items` o `GET|PUT|DELETE /api/v1/inventory/catalog-items/{id}`, **el sistema debe** gestionar el ítem con `code` (SKU, único), `name`, `type` (`product`|`service`), `category_id`, `brand_id` opcional, `base_unit_id`, `description`, `tax_type` (`taxable`|`exempt`|`unaffected`), `track_stock`, `is_sellable`, `is_purchasable` e `is_active`.
**Módulo:** `Inventory` (`/api/v1/inventory/catalog-items`)
**Permisos:** `catalog.view|create|update|delete`
**Criterios de aceptación:**
- [ ] Si `code` no se envía, se genera con el formato `ITM-000001`.
- [ ] `type = service` fuerza `track_stock = false` y `is_purchasable` es opcional.
- [ ] Al crear el ítem se crea automáticamente su presentación base (`factor = 1`, unidad = `base_unit_id`, `is_default = true`).
- [ ] Listado con `search` (código, nombre, código de barras de presentaciones), filtros `type`, `category_id` (incluye subcategorías), `brand_id`, `is_active`, `is_sellable`, `is_purchasable`; paginado.
- [ ] `base_unit_id` no se puede cambiar si el ítem ya tiene movimientos de stock → 422 (la verificación se activa con el spec 015).
- [ ] Eliminar un ítem con movimientos o usado en documentos → 422; se ofrece desactivar.
- [ ] UI: `/inventory/catalog` con `ServerDataTable`, filtros y una página de detalle `/inventory/catalog/[id]` con pestañas General · Presentaciones y precios.

### US-2 — Presentaciones
**Como** jefe de almacén **quiero** definir presentaciones con su factor y código de barras **para** comprar y vender en distintos empaques.
**Cuando** se invoca `GET|POST /api/v1/inventory/catalog-items/{id}/presentations` o `PUT|DELETE /api/v1/inventory/catalog-items/{id}/presentations/{presentationId}`, **el sistema debe** gestionar presentaciones con `name`, `unit_id`, `factor` (cantidad de unidad base contenida), `barcode` (único global), `is_default`, `is_active`.
**Módulo:** `Inventory`
**Permisos:** `catalog.update`
**Criterios de aceptación:**
- [ ] `factor > 0` con hasta 4 decimales; si la unidad base no permite decimales, `factor` debe ser entero → 422.
- [ ] Exactamente una presentación `is_default` por ítem.
- [ ] La presentación base (`factor = 1` creada automáticamente) no se elimina.
- [ ] `barcode` duplicado en cualquier ítem → 422.

### US-3 — Precios por presentación
**Como** responsable de precios **quiero** fijar el precio minorista y mayorista de cada presentación **para** que POS y cotizaciones los usen.
**Cuando** se envía `PUT /api/v1/inventory/catalog-items/{id}/prices` con `[{ presentation_id, price_type, price, min_quantity }]`, **el sistema debe** reemplazar los precios del ítem en una transacción.
**Módulo:** `Inventory`
**Permisos:** `catalog.update_prices`
**Criterios de aceptación:**
- [ ] `price_type` ∈ `retail`, `wholesale`; `price ≥ 0` con 4 decimales; `min_quantity ≥ 1` (solo para mayorista).
- [ ] Los precios se expresan en la moneda por defecto de la empresa (spec 010) e incluyen o no impuesto según `prices_include_tax`.
- [ ] Cada cambio queda en el activity log del ítem con precio anterior y nuevo.
- [ ] UI: grilla editable de presentaciones × tipos de precio, deshabilitada sin `can('catalog.update_prices')`.

### US-4 — Búsqueda rápida para documentos
**Como** vendedor o comprador **quiero** buscar por nombre, código o código de barras **para** agregar ítems a cotizaciones, ventas, compras y notas.
**Cuando** se solicita `GET /api/v1/inventory/catalog-items/lookup?q=&context=sale|purchase|stock&limit=20`, **el sistema debe** devolver presentaciones activas `[{ presentation_id, catalog_item_id, code, name, presentation_name, factor, barcode, unit, tax_type, type, track_stock, prices: { retail, wholesale, wholesale_min_quantity } }]`.
**Módulo:** `Inventory`
**Permisos:** `catalog.view`
**Criterios de aceptación:**
- [ ] `context=sale` → solo `is_sellable`; `context=purchase` → solo `is_purchasable`; `context=stock` → solo `track_stock`.
- [ ] Coincidencia exacta de `barcode` primero; luego por código y nombre (`ilike`).
- [ ] Responde en < 150 ms con 20 000 ítems (índices por `code`, `barcode` y nombre).
- [ ] El spec 015 agrega `stock` disponible del almacén indicado (`warehouse_id`).

## 4. Reglas de negocio e invariantes
- Todo ítem tiene al menos una presentación y exactamente una por defecto; la presentación base (`factor = 1`) existe siempre.
- La unidad de una presentación es de la misma categoría de unidad que la unidad base del ítem, salvo las unidades de categorías de empaque (`unit_categories.is_packaging = true`: Caja, Saco, Paquete…), que se permiten para cualquier ítem; su factor es siempre el de la presentación.
- Los servicios nunca generan stock (`track_stock = false`).
- `factor` de una presentación con movimientos no se puede cambiar → 422 (se activa con el spec 015).
- El precio vigente es único por (presentación, tipo de precio).

## 5. Requisitos no funcionales
- Índices: `catalog_items.code`, `item_presentations.barcode`, trigram o `lower(name)` para búsqueda.
- Auditoría de ítems, presentaciones y precios.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Kits y componentes (`catalog_item_components`) → spec futuro.
- Fraccionamiento de envases abiertos (aceites por litro) → spec futuro.
- Precios con vigencia, por sede, por cliente o en otra moneda → spec futuro.
- Importación masiva por Excel → spec futuro.
- Stock por almacén → spec 015.
- Disponibilidad por sede (`branch_catalog_items`) → descartada: todo ítem activo está disponible en todas las sedes.

## 7. Open Questions
- [ ] ¿Se requiere imagen del ítem en esta entrega? Propuesta: un campo `image_path` opcional con subida simple. — Responsable: Luis Acuña.
- [ ] ¿Se necesitan más tipos de precio (p. ej. "distribuidor")? — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `CatalogItemsApiTest`, `ItemPresentationsApiTest`, `ItemPricesApiTest` y `CatalogLookupApiTest` en verde.
- Búsqueda por código de barras en POS en < 150 ms.
