# 016 · warehouse-notes — TODO
> **Spec:** 016 · **Alcance:** fullstack · **Módulo:** Inventory · **Prioridad:** alta · **Depende de specs:** 013, 014, 015
> **Status:** Completed
> **Última actualización:** 2026-10-19

---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | ✅ | back | S | `migrations/2026_10_18_000003_create_warehouse_notes_tables.php`, `Inventory/Models/{WarehouseNote,WarehouseNoteItem,InventoryCount,InventoryCountItem}.php`, `Enums/*` | — |
| T-2 | ✅ | back | M | `Inventory/Services/WarehouseNoteService.php`, `Requests/{Store,Update,List}WarehouseNote*Request.php`, `Requests/CancelDocumentRequest.php` | T-1 |
| T-3 | ✅ | back | M | `Inventory/Services/WarehouseNoteApprovalService.php` | T-2 |
| T-4 | ✅ | back | S | `Controllers/WarehouseNoteController.php`, `Resources/WarehouseNote*Resource.php`, `routes/api.php`, `RolePermissionSeeder.php` | T-3 |
| T-5 | ✅ | back | M | `tests/Feature/Modules/Inventory/WarehouseNotesApiTest.php` | T-4 |
| T-6 | ✅ | back | M | `Inventory/Services/InventoryCountService.php`, `Controllers/InventoryCountController.php`, requests/resources | T-3 |
| T-7 | ✅ | back | M | `tests/Feature/Modules/Inventory/InventoryCountsApiTest.php` | T-6 |
| T-8 | ✅ | front | M | `pages/inventory/warehouse-notes/index.vue`, `modules/inventory/{services/warehouseNotesApi.js,stores/warehouseNotesStore.js}` | T-4 |
| T-9 | ✅ | front | M | `pages/inventory/warehouse-notes/{new,[id]}.vue`, `components/notes/{WarehouseNoteForm,WarehouseNoteItemsTable,CancelDocumentDialog}.vue` | T-8 |
| T-10 | ✅ | front | M | `pages/inventory/counts/{index,[id]}.vue`, `services/inventoryCountsApi.js`, `components/counts/*` | T-6 |

---
## Detalle de Tareas
### T-1 — Migración, modelos y enums
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_18_000003_create_warehouse_notes_tables.php`
- `back-laravel/app/Modules/Inventory/Models/{WarehouseNote,WarehouseNoteItem,InventoryCount,InventoryCountItem}.php`
- `back-laravel/app/Modules/Inventory/Enums/{WarehouseNoteType,WarehouseNoteStatus,InventoryCountStatus}.php`
**Descripción:**
1. DDL de design §6 (tablas en orden: counts → count_items → notes → note_items).
2. Modelos y relaciones (design §1); `kardexMovements()` como `morphMany(KardexMovement::class, 'source')`.
3. Agregar `warehouse_note` e `inventory_count` al morph map.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-2 — Servicio de notas (crear y editar borradores)
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Services/WarehouseNoteService.php`
- `back-laravel/app/Modules/Inventory/Requests/{StoreWarehouseNoteRequest,UpdateWarehouseNoteRequest,ListWarehouseNotesRequest,CancelDocumentRequest}.php`
**Descripción:**
1. Validaciones de negocio de design §2 (sede activa, motivo vs tipo, ítems con stock, decimales).
2. Numeración con `SequentialCodeGenerator` por tipo.
3. `update` solo en `draft`, reemplazando líneas.
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-3 — Aprobación y anulación
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Services/WarehouseNoteApprovalService.php`
**Descripción:**
1. Leer primero `StockLedgerService.php` (spec 015).
2. `approve()` con `lockForUpdate`, construcción de `StockMovementData`, `applyBatch`/`transfer` y guardado de costos reales.
3. `cancel()` con reversión en orden inverso y protección de notas de toma de inventario.
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Controller, resources, rutas y permisos de notas
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Controllers/WarehouseNoteController.php`
- `back-laravel/app/Modules/Inventory/Resources/{WarehouseNoteResource,WarehouseNoteItemResource}.php`
- `back-laravel/routes/api.php`, `back-laravel/database/seeders/RolePermissionSeeder.php`
**Descripción:**
1. Rutas de design §3 dentro de `middleware('branch')`.
2. Filtro de visibilidad por sede de origen o destino.
3. `meta.approval_pending` cuando `approve: true` no procede.
4. Permisos `warehouse_notes.*` e `inventory_counts.*`.
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [x] `make artisan CMD="route:list --path=v1/inventory/warehouse-notes"` muestra 6 rutas.

---
### T-5 — Tests de notas
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Inventory/WarehouseNotesApiTest.php`
**Descripción:**
1. Casos de design §9 para notas, incluida la verificación final con `inventory:verify-stock`.
**Tests:**
- `WarehouseNotesApiTest`.
**Criterios de done:**
- [x] `make test-filter FILTER=WarehouseNotes` en verde.
- [x] `make test` completo y `vendor/bin/pint --test` en verde.

---
### T-6 — Toma de inventario (backend)
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Services/InventoryCountService.php`
- `back-laravel/app/Modules/Inventory/Controllers/InventoryCountController.php`
- `back-laravel/app/Modules/Inventory/Requests/{StoreInventoryCountRequest,UpdateInventoryCountItemsRequest,CloseInventoryCountRequest}.php`
- `back-laravel/app/Modules/Inventory/Resources/{InventoryCountResource,InventoryCountItemResource}.php`
- `back-laravel/routes/api.php`
**Descripción:**
1. `create`, `updateItems`, `close`, `cancel` según design §2; reutilizar `WarehouseNoteService` + `WarehouseNoteApprovalService` para las notas de ajuste.
2. `summary` en el resource de detalle.
**Tests:**
- Cubiertos en T-7.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-7 — Tests de toma de inventario
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-6
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Inventory/InventoryCountsApiTest.php`
**Descripción:**
1. Casos de design §9 para tomas.
**Tests:**
- `InventoryCountsApiTest`.
**Criterios de done:**
- [x] `make test-filter FILTER=InventoryCounts` en verde; `make test` completo en verde.

---
### T-8 — Front: listado de notas
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `front-vue/src/pages/inventory/warehouse-notes/index.vue`
- `front-vue/src/modules/inventory/services/warehouseNotesApi.js`, `stores/warehouseNotesStore.js`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Filtros y columnas de design §8; acciones según estado y `can()`.
2. Menú "Inventario" › "Notas de almacén".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-9 — Front: formulario y detalle de nota
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-8
**Archivos clave:**
- `front-vue/src/pages/inventory/warehouse-notes/{new,[id]}.vue`
- `front-vue/src/modules/inventory/components/notes/{WarehouseNoteForm,WarehouseNoteItemsTable,WarehouseNoteKardexTable}.vue`
- `front-vue/src/components/common/CancelDocumentDialog.vue` (reutilizable por compras y ventas)
**Descripción:**
1. Cabecera dinámica por tipo, grilla con `CatalogItemPicker` y totales.
2. Botones según estado; errores 422 de stock mostrados en la línea correspondiente (usar `meta.catalog_item_id`).
3. Pestaña de movimientos de kardex en el detalle aprobado.
**Tests:**
- `npm run lint`; verificación manual de los flujos aprobar/anular.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-10 — Front: toma de inventario
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-6
**Archivos clave:**
- `front-vue/src/pages/inventory/counts/{index,[id]}.vue`
- `front-vue/src/modules/inventory/services/inventoryCountsApi.js`, `stores/inventoryCountsStore.js`
- `front-vue/src/modules/inventory/components/counts/{CountItemsGrid,CloseCountDialog}.vue`
**Descripción:**
1. Listado, creación (almacén + categoría), grilla con escaneo y guardado por lotes, resumen y cierre (design §8).
2. Menú "Inventario" › "Toma de inventario".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
