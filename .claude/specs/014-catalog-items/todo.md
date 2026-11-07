# 014 · catalog-items — TODO
> **Spec:** 014 · **Alcance:** fullstack · **Módulo:** Inventory · **Prioridad:** alta · **Depende de specs:** 010, 013
> **Status:** Done
> **Última actualización:** 2026-10-19
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:**
> - 🔧 = pendiente · ✅ = hecho · ⚪ = opcional
> - **Subj** (subproyecto): `back` | `front` | `infra` | `docs`
> - **Complejidad:** S = ≤ 1 h · M = ≤ ½ día · L = > ½ día (evitar L: partir en varias M)
> - Cada tarea es **autocontenida**: un agente con `requirements.md`, `design.md` y los archivos indicados puede ejecutarla sin contexto adicional.
> - **Dependencias** explícitas entre tareas → preservar el orden indicado.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | ✅ | back | S | `migrations/2026_10_17_000002_create_code_sequences_table.php`, `app/Support/Numbering/SequentialCodeGenerator.php`, test | — |
| T-2 | ✅ | back | M | `migrations/2026_10_17_000003_create_catalog_items_tables.php`, `Inventory/Models/{CatalogItem,ItemPresentation,ItemPrice}.php`, `Inventory/Enums/*`, factories | T-1 |
| T-3 | ✅ | back | M | `Inventory/Services/CatalogItemService.php`, `Controllers/CatalogItemController.php`, `Requests/{CatalogItemRequest,ListCatalogItemsRequest}.php`, `Resources/CatalogItemResource.php`, `routes/api.php` | T-2 |
| T-4 | ✅ | back | M | `Inventory/Services/{ItemPresentationService,ItemPriceService}.php`, `Controllers/{ItemPresentationController,ItemPriceController}.php`, requests/resources | T-3 |
| T-5 | ✅ | back | S | `Inventory/Services/CatalogLookupService.php`, `Requests/CatalogLookupRequest.php`, `Resources/CatalogLookupResource.php` | T-4 |
| T-6 | ✅ | back | S | `RolePermissionSeeder.php`, `CatalogSeeder.php`, `Inventory/Services/{CategoryService,BrandService,UnitService}.php` | T-3 |
| T-7 | ✅ | back | M | `tests/Feature/Modules/Inventory/{CatalogItems,ItemPresentations,ItemPrices,CatalogLookup}ApiTest.php` | T-5, T-6 |
| T-8 | ✅ | front | M | `modules/inventory/{services/catalogApi.js,stores/catalogStore.js,components/catalog/CatalogItemFormDrawer.vue}`, `pages/inventory/catalog/index.vue`, menú | T-3 |
| T-9 | ✅ | front | M | `pages/inventory/catalog/[id].vue`, `components/catalog/PresentationsPricesTab.vue` | T-4, T-8 |
| T-10 | ✅ | front | S | `front-vue/src/components/common/CatalogItemPicker.vue` | T-5 |

---
## Detalle de Tareas
### T-1 — Generador de códigos correlativos
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_17_000002_create_code_sequences_table.php`
- `back-laravel/app/Support/Numbering/SequentialCodeGenerator.php`
- `back-laravel/tests/Feature/Support/SequentialCodeGeneratorTest.php`
**Descripción:**
1. Tabla `code_sequences` (design §6).
2. `next(key, prefix, pad, scope)` con `lockForUpdate` dentro de `DB::transaction` (design §2). Usar `insertOrIgnore` + `lockForUpdate` para la primera fila.
**Tests:**
- `SequentialCodeGeneratorTest`: consecutivos, scopes independientes y formato con relleno.
**Criterios de done:**
- [x] `make test-filter FILTER=SequentialCodeGenerator` en verde; `vendor/bin/pint --test` en verde.

---
### T-2 — Migración, modelos y enums del catálogo
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_17_000003_create_catalog_items_tables.php`
- `back-laravel/app/Modules/Inventory/Models/{CatalogItem,ItemPresentation,ItemPrice}.php`
- `back-laravel/app/Modules/Inventory/Enums/{CatalogItemType,TaxType,PriceType}.php`
- `back-laravel/database/factories/{CatalogItem,ItemPresentation,ItemPrice}Factory.php`
**Descripción:**
1. DDL de design §6.
2. Modelos con relaciones, casts y scopes de design §1; `CategoryService::descendantIds()` si no existe (leer primero `CategoryService`).
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-3 — CRUD de ítems
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Services/CatalogItemService.php`
- `back-laravel/app/Modules/Inventory/Controllers/CatalogItemController.php`
- `back-laravel/app/Modules/Inventory/Requests/{CatalogItemRequest,ListCatalogItemsRequest}.php`
- `back-laravel/app/Modules/Inventory/Resources/CatalogItemResource.php`
- `back-laravel/routes/api.php`
**Descripción:**
1. `create` con código autogenerado y presentación base; `update` con protección de `base_unit_id` (hook `hasMovements()` que devuelve `false` hasta el spec 015).
2. Rutas de design §3 (`/lookup` antes de `/{id}`).
**Tests:**
- Cubiertos en T-7.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Presentaciones y precios
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Services/{ItemPresentationService,ItemPriceService}.php`
- `back-laravel/app/Modules/Inventory/Controllers/{ItemPresentationController,ItemPriceController}.php`
- `back-laravel/app/Modules/Inventory/Requests/{ItemPresentationRequest,SyncItemPricesRequest}.php`
- `back-laravel/app/Modules/Inventory/Resources/{ItemPresentationResource,ItemPriceResource}.php`
**Descripción:**
1. Validaciones de factor, unidad y `is_default` (design §2).
2. `sync` de precios en transacción con eliminación de los pares no enviados.
**Tests:**
- Cubiertos en T-7.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-5 — Búsqueda rápida (lookup)
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Services/CatalogLookupService.php`
- `back-laravel/app/Modules/Inventory/Requests/CatalogLookupRequest.php`
- `back-laravel/app/Modules/Inventory/Resources/CatalogLookupResource.php`
**Descripción:**
1. Consulta sobre presentaciones con orden por coincidencia exacta (design §2).
2. Método `withStock()` vacío, documentado como punto de extensión del spec 015.
**Tests:**
- Cubiertos en T-7 (`CatalogLookupApiTest`).
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-6 — Permisos, seeder y bloqueo de borrado de maestros
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/database/seeders/{RolePermissionSeeder,CatalogSeeder,DatabaseSeeder}.php`
- `back-laravel/app/Modules/Inventory/Services/{CategoryService,BrandService,UnitService}.php`
**Descripción:**
1. Permisos `catalog.*` (design §5).
2. `CatalogSeeder` solo en entorno local.
3. Leer primero cada `Service::delete()` y agregar el bloqueo por ítems/presentaciones que los usen.
**Tests:**
- Cubiertos en T-7; tests del spec 013 siguen en verde.
**Criterios de done:**
- [x] `make test-filter FILTER=Inventory` en verde.

---
### T-7 — Feature tests del catálogo
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-5, T-6
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Inventory/{CatalogItemsApiTest,ItemPresentationsApiTest,ItemPricesApiTest,CatalogLookupApiTest}.php`
**Descripción:**
1. Casos de design §9.
**Tests:**
- Los cuatro archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Catalog` en verde.
- [x] `make test` completo y `vendor/bin/pint --test` en verde.

---
### T-8 — Front: listado y alta rápida
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `front-vue/src/modules/inventory/services/catalogApi.js`, `stores/catalogStore.js`
- `front-vue/src/modules/inventory/components/catalog/CatalogItemFormDrawer.vue`
- `front-vue/src/pages/inventory/catalog/index.vue`, `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Leer primero `pages/clients/index.vue` y `pages/inventory/settings/index.vue`.
2. Tabla con filtros; drawer con precio inicial.
3. Menú "Inventario" › "Catálogo".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-9 — Front: detalle con presentaciones y precios
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4, T-8
**Archivos clave:**
- `front-vue/src/pages/inventory/catalog/[id].vue`
- `front-vue/src/modules/inventory/components/catalog/PresentationsPricesTab.vue`
**Descripción:**
1. Pestañas General y Presentaciones y precios (design §8).
2. Grilla de precios editable con guardado en lote; deshabilitada sin `can('catalog.update_prices')`.
**Tests:**
- `npm run lint`; verificación manual de factor inválido y barcode duplicado.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-10 — Front: selector reutilizable de ítems
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-5
**Archivos clave:**
- `front-vue/src/components/common/CatalogItemPicker.vue`
**Descripción:**
1. `VAutocomplete` remoto con debounce, Enter para lector de códigos y props `context`/`warehouseId` (design §8).
2. Documentar las props con JSDoc en el componente.
**Tests:**
- `npm run lint`; `make test-front` con un test Vitest del debounce si existe infraestructura de componentes.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
