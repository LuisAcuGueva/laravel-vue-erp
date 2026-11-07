# 013 · inventory-masters — Requirements
> **Spec:** 013 · **Alcance:** fullstack · **Módulo:** Inventory · **Prioridad:** alta · **Depende de specs:** 011
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 2)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 011 — `Branch`, middleware `branch`.
> - Origen Multinort: `create_categories_table.php` (+ `parent_id`), `create_brands_table.php`, `create_unit_categories_table.php`, `create_units_table.php`, `create_warehouses_table.php` (+ `branch_id`), `create_warehouse_note_reasons_table.php`.
>
---
## 1. Contexto de Negocio
El catálogo (spec 014), el stock (spec 015) y las notas de almacén (spec 016) necesitan maestros estables: **categorías jerárquicas**, **marcas**, **unidades de medida con conversión**, **almacenes por sede** y **motivos de movimiento**.

En Multinort estos maestros existen pero están acoplados al taller: `brands.type` distingue marcas de producto y de vehículo, los almacenes no tenían sede al inicio (`branch_id` se agregó después y es nullable), y los motivos solo tienen tipo `entrada`/`salida`. Aquí se generalizan:
- marcas solo de producto;
- almacén siempre ligado a una sede, con un almacén por defecto por sede;
- unidades agrupadas por categoría (masa, volumen, cantidad) con factor hacia la unidad base y código SUNAT para comprobantes;
- motivos con tipo `in`/`out`/`transfer`/`adjustment` y motivos de sistema que usan compras, ventas y notas de crédito.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Administrador / Jefe de almacén | Rol `admin` o `manager` | JWT · `categories.*`, `brands.*`, `units.*`, `warehouses.*`, `warehouse_reasons.*` |
| Usuario operativo | Vendedor, almacenero | JWT · lectura de opciones para selects |

## 3. User Stories (formato EARS)
### US-1 — Categorías jerárquicas
**Como** jefe de almacén **quiero** organizar productos y servicios en categorías y subcategorías **para** filtrar el catálogo y los reportes.
**Cuando** se invoca `GET|POST /api/v1/inventory/categories` o `GET|PUT|DELETE /api/v1/inventory/categories/{id}`, **el sistema debe** gestionar categorías con `name`, `code` opcional, `parent_id` opcional, `description` e `is_active`.
**Módulo:** `Inventory` (`/api/v1/inventory/categories`)
**Permisos:** `categories.view|create|update|delete`
**Criterios de aceptación:**
- [ ] Profundidad máxima de 3 niveles → 422 si se excede.
- [ ] Una categoría no puede ser su propio ancestro (ciclo) → 422.
- [ ] `name` único entre hermanos (mismo `parent_id`) no eliminados → 422.
- [ ] `GET /inventory/categories/tree` devuelve el árbol completo de categorías activas `[{ id, name, children: [...] }]`.
- [ ] No se elimina una categoría con subcategorías o con ítems del catálogo → 422.
- [ ] UI: pestaña "Categorías" con `VTreeview` (o tabla con indentación) y drawer.

### US-2 — Marcas
**Como** jefe de almacén **quiero** registrar marcas **para** clasificar mercadería.
**Cuando** se invoca el CRUD `/api/v1/inventory/brands`, **el sistema debe** gestionar marcas con `name` único, `description` e `is_active`.
**Módulo:** `Inventory`
**Permisos:** `brands.view|create|update|delete`
**Criterios de aceptación:**
- [ ] Nombre duplicado → 422; no se elimina una marca usada en el catálogo → 422.

### US-3 — Unidades de medida y conversiones
**Como** jefe de almacén **quiero** definir unidades agrupadas por tipo con su factor de conversión **para** comprar en cajas y vender por unidad.
**Cuando** se invoca el CRUD `/api/v1/inventory/unit-categories` y `/api/v1/inventory/units`, **el sistema debe** gestionar las unidades con `name`, `symbol`, `unit_category_id`, `is_base`, `conversion_factor` (cantidad de la unidad base que equivale a 1 de esta unidad), `allow_decimals`, `sunat_code` e `is_active`.
**Módulo:** `Inventory`
**Permisos:** `units.view|create|update|delete`
**Criterios de aceptación:**
- [ ] Cada categoría de unidad tiene exactamente una unidad base con `conversion_factor = 1`.
- [ ] `conversion_factor > 0` con hasta 6 decimales.
- [ ] `POST /api/v1/inventory/units/convert` con `{ quantity, from_unit_id, to_unit_id }` devuelve la cantidad convertida; unidades de distinta categoría → 422.
- [ ] El seeder crea: Cantidad (Unidad NIU base, Docena, Ciento), Masa (Kilogramo KGM base, Gramo GRM), Volumen (Litro LTR base, Mililitro MLT, Galón GLL) y Servicio (Servicio ZZ base).
- [ ] No se elimina una unidad usada por ítems o presentaciones → 422.

### US-4 — Almacenes por sede
**Como** administrador **quiero** registrar los almacenes de cada sede **para** controlar el stock por ubicación.
**Cuando** se invoca el CRUD `/api/v1/inventory/warehouses`, **el sistema debe** gestionar almacenes con `code` único, `name`, `branch_id` obligatorio, `address`, `is_default` e `is_active`.
**Módulo:** `Inventory`
**Permisos:** `warehouses.view|create|update|delete`
**Criterios de aceptación:**
- [ ] Como máximo un almacén `is_default` por sede (marcar uno desmarca el anterior).
- [ ] Listado filtrable por `branch_id`; `GET /inventory/warehouses/options` devuelve los almacenes activos de la **sede activa** (middleware `branch`) o, con `scope=all`, los de todas las sedes accesibles para el usuario.
- [ ] No se elimina un almacén con stock distinto de cero o con movimientos → 422 (la verificación de stock se activa con el spec 015).
- [ ] Eliminar una sede con almacenes → 422 (se agrega a `BranchService::delete()`).

### US-5 — Motivos de movimiento de almacén
**Como** jefe de almacén **quiero** definir motivos de ingreso, salida, traslado y ajuste **para** clasificar las notas de almacén.
**Cuando** se invoca el CRUD `/api/v1/inventory/warehouse-reasons`, **el sistema debe** gestionar motivos con `code` único, `name`, `type` (`in`, `out`, `transfer`, `adjustment`), `requires_approval` e `is_active`.
**Módulo:** `Inventory`
**Permisos:** `warehouse_reasons.view|create|update|delete`
**Criterios de aceptación:**
- [ ] Los motivos de sistema (`is_system = true`, creados por seeder: `OPENING`, `PURCHASE`, `SALE`, `SALE_RETURN`, `PURCHASE_RETURN`, `TRANSFER`, `COUNT_ADJUSTMENT_IN`, `COUNT_ADJUSTMENT_OUT`) no se editan en `code`/`type` ni se eliminan → 422.
- [ ] `GET /inventory/warehouse-reasons/options?type=in` devuelve solo motivos activos y no de sistema, salvo `include_system=1`.

## 4. Reglas de negocio e invariantes
- Toda categoría de unidad tiene una sola unidad base; cambiar la base recalcula los factores de las demás (o se bloquea si hay ítems que la usan: se bloquea, con 422).
- Todo almacén pertenece a una sede; una sede tiene como máximo un almacén por defecto.
- Los motivos de sistema son inmutables en `code` y `type`.
- Ningún maestro referenciado por el catálogo o por movimientos puede eliminarse; se desactiva.

## 5. Requisitos no funcionales
- Endpoints `/options` y `/tree` responden en < 100 ms con 1 000 categorías.
- Auditoría (spec 007) en todos los maestros.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Catálogo maestro de bienes y servicios y sus presentaciones → spec 014.
- Stock y kardex → spec 015.
- Notas de almacén y toma de inventario → spec 016.
- Modelos de vehículo y marcas de vehículo → descartados (rubro automotriz).
- Ubicaciones internas del almacén (pasillo/estante) → spec futuro.

## 7. Open Questions
- [ ] ¿Se necesitan unidades de longitud y área en el seeder inicial? — Responsable: Luis Acuña.
- [ ] ¿Los motivos deben tener una cuenta contable asociada (spec 017)? — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `CategoriesApiTest`, `BrandsApiTest`, `UnitsApiTest`, `WarehousesApiTest` y `WarehouseReasonsApiTest` en verde.
- Seeder idempotente con unidades y motivos de sistema.
