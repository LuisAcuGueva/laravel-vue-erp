# 015 · stock-and-kardex — TODO
> **Spec:** 015 · **Alcance:** fullstack · **Módulo:** Inventory · **Prioridad:** alta · **Depende de specs:** 010, 011, 014
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
| T-1 | ✅ | back | S | `migrations/2026_10_18_000002_create_stock_and_kardex_tables.php`, `Inventory/Models/{Stock,KardexMovement}.php`, `Inventory/Enums/{MovementDirection,MovementType}.php` | — |
| T-2 | ✅ | back | M | `Inventory/Data/StockMovementData.php`, `Services/StockLedgerService.php`, `Exceptions/InsufficientStockException.php` | T-1 |
| T-3 | ✅ | back | M | `tests/Feature/Modules/Inventory/StockLedgerServiceTest.php` | T-2 |
| T-4 | ✅ | back | M | `Services/{StockQueryService,KardexQueryService}.php`, `Controllers/{StockController,KardexController}.php`, requests/resources, `routes/api.php` | T-2 |
| T-5 | ✅ | back | S | `CatalogItemService.php`, `ItemPresentationService.php`, `WarehouseService.php`, `CatalogLookupService.php`, `Console/VerifyStockCommand.php`, `RolePermissionSeeder.php` | T-2 |
| T-6 | ✅ | back | M | `tests/Feature/Modules/Inventory/{StockApiTest,KardexApiTest,VerifyStockCommandTest}.php` | T-4, T-5 |
| T-7 | ✅ | front | M | `pages/inventory/stock/index.vue`, `modules/inventory/{services/stockApi.js,stores/stockStore.js,components/stock/StockLimitsDialog.vue}` | T-4 |
| T-8 | ✅ | front | M | `pages/inventory/kardex/index.vue`, `components/common/CatalogItemPicker.vue`, menú | T-4, T-7 |

---
## Detalle de Tareas
### T-1 — Migración y modelos de stock y kardex
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_18_000002_create_stock_and_kardex_tables.php`
- `back-laravel/app/Modules/Inventory/Models/{Stock,KardexMovement}.php`
- `back-laravel/app/Modules/Inventory/Enums/{MovementDirection,MovementType}.php`
**Descripción:**
1. DDL de design §6.
2. `KardexMovement` inmutable (`UPDATED_AT = null`, eventos `updating`/`deleting` que lanzan excepción).
3. `Stock` con `$fillable` limitado a `min_stock`/`max_stock`.
4. Morph map en `InventoryServiceProvider` (`Relation::enforceMorphMap`), solo con los alias de modelos que ya existen.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-2 — StockLedgerService
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Data/StockMovementData.php`
- `back-laravel/app/Modules/Inventory/Services/StockLedgerService.php`
- `back-laravel/app/Modules/Inventory/Exceptions/InsufficientStockException.php`
**Descripción:**
1. Implementar `in`, `out`, `transfer`, `reverse`, `applyBatch` con el algoritmo de design §2 (bcmath, bloqueo ordenado, exigencia de transacción abierta).
2. Leer el parámetro `inventory.allow_negative_stock` con `SystemParameterService::boolean()`.
3. Verificar `ext-bcmath` en la imagen (`docker/`); si falta, agregarlo.
**Tests:**
- Cubiertos en T-3.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-3 — Tests del ledger
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Inventory/StockLedgerServiceTest.php`
**Descripción:**
1. Casos de design §9 (promedio ponderado con los valores del ejemplo, negativo, traslado, reversión, inmutabilidad, transacción obligatoria, servicio rechazado).
2. Usar un modelo de origen de prueba (p. ej. un `Warehouse` como `source`) hasta que existan las notas del spec 016.
**Tests:**
- `StockLedgerServiceTest`.
**Criterios de done:**
- [x] `make test-filter FILTER=StockLedgerServiceTest` en verde.

---
### T-4 — Consultas y endpoints de stock y kardex
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Services/{StockQueryService,KardexQueryService}.php`
- `back-laravel/app/Modules/Inventory/Controllers/{StockController,KardexController}.php`
- `back-laravel/app/Modules/Inventory/Requests/{ListStockRequest,UpdateStockLimitsRequest,KardexRequest}.php`
- `back-laravel/app/Modules/Inventory/Resources/{StockResource,KardexMovementResource}.php`
- `back-laravel/routes/api.php`
**Descripción:**
1. Resolver los almacenes permitidos con `ActiveBranch` + `UserBranchService::accessibleBranches()`.
2. Saldo inicial y movimientos del kardex; export CSV con `streamDownload`.
3. Rutas de design §3 dentro de `middleware('branch')`.
**Tests:**
- Cubiertos en T-6.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-5 — Hooks en servicios previos, comando de verificación y permisos
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Services/{CatalogItemService,ItemPresentationService,WarehouseService,CatalogLookupService}.php`
- `back-laravel/app/Modules/Inventory/Console/VerifyStockCommand.php`
- `back-laravel/database/seeders/RolePermissionSeeder.php`
**Descripción:**
1. Leer primero cada servicio; implementar `hasMovements()`, el bloqueo de `factor`, el bloqueo de borrado de almacén y `withStock()` (design §2).
2. Comando `inventory:verify-stock` registrado en el provider.
3. Permisos `stock.*` y `kardex.view`.
**Tests:**
- Cubiertos en T-6; los tests de los specs 013 y 014 siguen en verde.
**Criterios de done:**
- [x] `make test-filter FILTER=Inventory` en verde.

---
### T-6 — Feature tests de API y comando
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4, T-5
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Inventory/{StockApiTest,KardexApiTest,VerifyStockCommandTest}.php`
**Descripción:**
1. Casos de design §9; probar también que `lookup` con `warehouse_id` devuelve `stock.available` y que no se puede cambiar `base_unit_id` de un ítem con movimientos.
**Tests:**
- Los tres archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Stock` y `FILTER=Kardex` en verde.
- [x] `make test` completo y `vendor/bin/pint --test` en verde.

---
### T-7 — Front: consulta de stock
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `front-vue/src/pages/inventory/stock/index.vue`
- `front-vue/src/modules/inventory/services/stockApi.js`, `stores/stockStore.js`
- `front-vue/src/modules/inventory/components/stock/StockLimitsDialog.vue`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Filtros, tabla, pie con valor total y diálogo de límites (design §8).
2. Menú "Inventario" › "Stock".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-8 — Front: kardex valorizado y stock en el picker
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4, T-7
**Archivos clave:**
- `front-vue/src/pages/inventory/kardex/index.vue`
- `front-vue/src/components/common/CatalogItemPicker.vue`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Tabla con encabezado agrupado, saldo inicial, carga desde query params y export CSV (descarga vía `$api` con `responseType: 'blob'`).
2. `CatalogItemPicker` muestra `stock.available` cuando recibe `warehouseId`.
3. Menú "Inventario" › "Kardex".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
