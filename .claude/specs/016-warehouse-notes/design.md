# 016 · warehouse-notes — Design
> **Spec:** 016 · **Alcance:** fullstack · **Módulo:** Inventory · **Prioridad:** alta · **Depende de specs:** 013, 014, 015
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend** (módulo `Inventory`):
  - `Models/{WarehouseNote,WarehouseNoteItem,InventoryCount,InventoryCountItem}.php`
  - `Enums/{WarehouseNoteType,WarehouseNoteStatus,InventoryCountStatus}.php`
  - `Services/{WarehouseNoteService,WarehouseNoteApprovalService,InventoryCountService}.php`
  - `Controllers/{WarehouseNoteController,InventoryCountController}.php`
  - `Requests/{StoreWarehouseNoteRequest,UpdateWarehouseNoteRequest,ListWarehouseNotesRequest,CancelDocumentRequest,StoreInventoryCountRequest,UpdateInventoryCountItemsRequest,CloseInventoryCountRequest}.php`
  - `Resources/{WarehouseNoteResource,WarehouseNoteItemResource,InventoryCountResource,InventoryCountItemResource}.php`
- **Reutilizados:** `StockLedgerService`, `SequentialCodeGenerator`, `ActiveBranch`, `UserBranchService`.
- **Modificados:** morph map (`warehouse_note`, `inventory_count`); `CatalogItemService::delete()` (bloqueo por notas en borrador).
- **Frontend:** `front-vue/src/pages/inventory/warehouse-notes/{index,new,[id]}.vue`, `front-vue/src/pages/inventory/counts/{index,[id]}.vue`, `front-vue/src/modules/inventory/{services/warehouseNotesApi.js, services/inventoryCountsApi.js, stores/*, components/notes/*, components/counts/*}`.

## 1. Backend — Modelo y Persistencia
- **`WarehouseNote`** (`HasUuids`, `LogsActivity` en `status`/`observation`, sin `SoftDeletes`: se anula): `number`, `type` (`WarehouseNoteType`), `status` (`WarehouseNoteStatus`: `draft`, `approved`, `cancelled`, con `canTransitionTo(self $to): bool` que codifica las transiciones de requirements §4), `branch_id`, `warehouse_id`, `target_warehouse_id`, `reason_id`, `document_date` (date), `reference_document`, `observation`, `total_cost`, `inventory_count_id`, `created_by`, `approved_by`, `approved_at`, `cancelled_by`, `cancelled_at`, `cancel_reason`. Relaciones: `items`, `warehouse`, `targetWarehouse`, `reason`, `creator`, `approver`, `canceller`, `kardexMovements()` (`morphMany` por `source`). Métodos `isDraft()`, `isApproved()`.
- **`WarehouseNoteItem`** (`HasUuids`): `warehouse_note_id`, `catalog_item_id`, `item_presentation_id`, `quantity`, `base_quantity`, `unit_cost` (por presentación), `base_unit_cost` (por unidad base, real tras aprobar), `total_cost`, `notes`, `line`.
- **`InventoryCount`** (`HasUuids`, `LogsActivity`): `number`, `branch_id`, `warehouse_id`, `category_id`, `count_date`, `status` (`counting`, `closed`, `cancelled`), `notes`, `created_by`, `closed_by`, `closed_at`, `cancelled_by`, `cancelled_at`. Relaciones `items`, `notes()` (`hasMany WarehouseNote` por `inventory_count_id`).
- **`InventoryCountItem`** (`HasUuids`): `inventory_count_id`, `catalog_item_id`, `system_quantity`, `counted_quantity` (nullable), `closing_system_quantity`, `difference`, `unit_cost`, `difference_value`, `counted_by`, `counted_at`.

## 2. Backend — Lógica de Negocio y Servicios
### `WarehouseNoteService`
- `create(array $data, User $actor): WarehouseNote` — transacción: valida reglas de tipo/motivo/almacenes (ver abajo); `number = SequentialCodeGenerator::next("warehouse_note_{$type}", ['in'=>'NI','out'=>'NS','transfer'=>'NT'][$type])`; `branch_id = warehouse.branch_id`; crea líneas con `base_quantity = presentation->toBaseQuantity(quantity)`, `total_cost` estimado (`in`: `quantity × unit_cost`; `out`/`transfer`: `base_quantity × stock.avg_cost` actual, solo informativo). Si `approve = true` y `! reason.requires_approval` → `WarehouseNoteApprovalService::approve()`.
- `update(WarehouseNote $n, array $data)` — exige `draft`; reemplaza las líneas (delete + insert) y recalcula.
- Reglas de validación de negocio (lanzan `InventoryRuleException`):
  - `warehouse_id` debe pertenecer a la sede activa; `target_warehouse_id` accesible, activo y distinto de `warehouse_id`.
  - `reason.type === type` o (`reason.type === adjustment` y `type ∈ {in,out}`); motivos `is_system` prohibidos salvo `OPENING` en `in`.
  - Ítem `track_stock = true` y activo; `quantity` entera si `baseUnit.allow_decimals = false` y el `factor` es entero.
### `WarehouseNoteApprovalService`
- `approve(WarehouseNote $n, User $actor)` — `DB::transaction` + `lockForUpdate` sobre la nota; exige `draft`; construye `StockMovementData[]` por línea:
  - `in` → `type = reason.code === 'OPENING' ? opening : note_in` (o `adjustment_in` si el motivo es de ajuste); `unitCost = unit_cost / factor`.
  - `out` → `note_out`/`adjustment_out`.
  - `transfer` → `StockLedgerService::transfer()` por línea (los bloqueos siguen el orden de `applyBatch`: ordenar las líneas antes).
  - Guarda en cada línea `base_unit_cost = movimiento.unit_cost` y `total_cost = movimiento.total_cost`; `total_cost` de la nota = suma; `status = approved`, `approved_by`, `approved_at`.
- `cancel(WarehouseNote $n, string $reason, User $actor, bool $fromCount = false)` — exige no `cancelled`; si `inventory_count_id` y `! $fromCount` → excepción; si `approved` → `reverse()` de cada `kardexMovements` (orden inverso de creación) con `source = $n`; `status = cancelled`.
### `InventoryCountService`
- `create(array $data, User $actor)` — valida almacén en la sede activa y sin otro conteo `counting` (bloqueo con `lockForUpdate` sobre el almacén); `number = next('inventory_count', 'TI')`; inserta líneas desde `stocks` del almacén (filtrando por `CategoryService::descendantIds()` si hay categoría) con `system_quantity = stock.quantity`, `unit_cost = stock.avg_cost`.
- `updateItems(InventoryCount $c, array $rows, User $actor)` — exige `counting`; `upsert` por `catalog_item_id` (permite ítems nuevos con `system_quantity = 0`); registra `counted_by`/`counted_at`.
- `close(InventoryCount $c, bool $uncountedAsZero, User $actor)` — transacción con `lockForUpdate`:
  1. Si hay líneas `counted_quantity IS NULL` y `! $uncountedAsZero` → excepción con el número de pendientes.
  2. Por línea: `closing_system_quantity = availableFor()` actual; `difference = counted − closing`; `unit_cost = avg_cost > 0 ? avg_cost : item.last_purchase_cost ?? 0`; `difference_value`.
  3. Crea (si aplica) una nota `in` con motivo `COUNT_ADJUSTMENT_IN` para las diferencias positivas (`unit_cost` por unidad base, presentación base) y una `out` con `COUNT_ADJUSTMENT_OUT`; ambas con `inventory_count_id` y aprobadas con `WarehouseNoteApprovalService`.
  4. `status = closed`, `closed_by`, `closed_at`.
- `cancel(InventoryCount $c, User $actor)` — `counting` → `cancelled`; `closed` → anula sus notas con `cancel(..., fromCount: true)` y luego `cancelled`.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `StoreWarehouseNoteRequest`: `type` required|enum; `warehouse_id` required|uuid; `target_warehouse_id` required_if:type,transfer|nullable|uuid|different:warehouse_id; `reason_id` required|uuid|exists; `document_date` required|date|before_or_equal:today; `reference_document` nullable|max:60; `observation` nullable|max:1000; `approve` boolean; `items` required|array|min:1|max:500; `items.*.item_presentation_id` required|uuid|exists; `items.*.quantity` required|numeric|gt:0|decimal:0,4; `items.*.unit_cost` required_if:type,in|nullable|numeric|min:0|decimal:0,4; `items.*.notes` nullable|max:255. `withValidator`: presentaciones distintas (sin duplicar líneas del mismo ítem y presentación).
- `UpdateWarehouseNoteRequest`: igual sin `type` (el tipo no cambia).
- `CancelDocumentRequest`: `reason` required|string|min:5|max:500 (reutilizable por otros specs).
- `StoreInventoryCountRequest`: `warehouse_id` required|uuid; `category_id` nullable|uuid|exists; `count_date` required|date; `notes` nullable|max:1000.
- `UpdateInventoryCountItemsRequest`: `items` required|array|max:1000; `items.*.catalog_item_id` required|uuid|exists; `items.*.counted_quantity` required|numeric|min:0.
- `CloseInventoryCountRequest`: `treat_uncounted_as_zero` boolean.
- Rutas (`v1/inventory`, todas con middleware `branch`):
```php
// Notas de almacén y toma de inventario (Spec 016)
Route::middleware('branch')->group(function () {
    Route::get('/warehouse-notes', [WarehouseNoteController::class, 'index'])->middleware('permission:warehouse_notes.view');
    Route::post('/warehouse-notes', [WarehouseNoteController::class, 'store'])->middleware('permission:warehouse_notes.create');
    Route::get('/warehouse-notes/{id}', [WarehouseNoteController::class, 'show'])->whereUuid('id')->middleware('permission:warehouse_notes.view');
    Route::put('/warehouse-notes/{id}', [WarehouseNoteController::class, 'update'])->whereUuid('id')->middleware('permission:warehouse_notes.update');
    Route::post('/warehouse-notes/{id}/approve', [WarehouseNoteController::class, 'approve'])->whereUuid('id')->middleware('permission:warehouse_notes.approve');
    Route::post('/warehouse-notes/{id}/cancel', [WarehouseNoteController::class, 'cancel'])->whereUuid('id')->middleware('permission:warehouse_notes.cancel');

    Route::get('/counts', [InventoryCountController::class, 'index'])->middleware('permission:inventory_counts.view');
    Route::post('/counts', [InventoryCountController::class, 'store'])->middleware('permission:inventory_counts.manage');
    Route::get('/counts/{id}', [InventoryCountController::class, 'show'])->whereUuid('id')->middleware('permission:inventory_counts.view');
    Route::put('/counts/{id}/items', [InventoryCountController::class, 'updateItems'])->whereUuid('id')->middleware('permission:inventory_counts.manage');
    Route::post('/counts/{id}/close', [InventoryCountController::class, 'close'])->whereUuid('id')->middleware('permission:inventory_counts.close');
    Route::post('/counts/{id}/cancel', [InventoryCountController::class, 'cancel'])->whereUuid('id')->middleware('permission:inventory_counts.close');
});
```
- Visibilidad: `index`/`show` filtran por `branch_id = activa` **o** `targetWarehouse.branch_id = activa` (traslados entrantes).

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/inventory/warehouse-notes` | `branch`, `warehouse_notes.view` | query `type, status, warehouse_id, reason_id, date_from, date_to, search, page, per_page` | 200 `data[]`, `meta` | 401, 403, 409, 422 |
| POST | `/api/v1/inventory/warehouse-notes` | `branch`, `warehouse_notes.create` | §3 | 201 `data: WarehouseNote` | 401, 403, 422 |
| GET | `/api/v1/inventory/warehouse-notes/{id}` | `branch`, `warehouse_notes.view` | — | 200 (con `items`, `kardex_movements`) | 401, 403, 404 |
| PUT | `/api/v1/inventory/warehouse-notes/{id}` | `branch`, `warehouse_notes.update` | §3 sin `type` | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/inventory/warehouse-notes/{id}/approve` | `branch`, `warehouse_notes.approve` | — | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/inventory/warehouse-notes/{id}/cancel` | `branch`, `warehouse_notes.cancel` | `{ reason }` | 200 | 401, 403, 404, 422 |
| GET/POST | `/api/v1/inventory/counts` | `inventory_counts.view` / `.manage` | `{ warehouse_id, category_id?, count_date, notes? }` | 200 / 201 | 401, 403, 422 |
| GET | `/api/v1/inventory/counts/{id}` | `inventory_counts.view` | — | 200 (con `items`, `summary { total, counted, positive_value, negative_value }`) | 401, 403, 404 |
| PUT | `/api/v1/inventory/counts/{id}/items` | `inventory_counts.manage` | `{ items: [{ catalog_item_id, counted_quantity }] }` | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/inventory/counts/{id}/close` | `inventory_counts.close` | `{ treat_uncounted_as_zero? }` | 200 (con `notes`) | 401, 403, 404, 422 |
| POST | `/api/v1/inventory/counts/{id}/cancel` | `inventory_counts.close` | — | 200 | 401, 403, 404, 422 |

Ejemplo `POST /warehouse-notes` (carga inicial):
```json
{ "type": "in", "warehouse_id": "…", "reason_id": "<OPENING>", "document_date": "2026-11-01", "approve": true,
  "items": [ { "item_presentation_id": "…", "quantity": 20, "unit_cost": 54.00 }, { "item_presentation_id": "…", "quantity": 150, "unit_cost": 2.35 } ] }
```

## 5. Permisos y seguridad
- Permisos nuevos: `warehouse_notes.view|create|update|approve|cancel` y `inventory_counts.view|manage|close`. `admin` todos; `manager` todos; `user` `warehouse_notes.view|create`.
- La aprobación directa (`approve: true`) solo procede si el motivo no requiere aprobación; en otro caso se ignora y la nota queda en `draft`, y la respuesta lo indica con `meta.approval_pending = true`.

## 6. Migraciones
`2026_10_17_000005_create_warehouse_notes_tables.php`:
```php
Schema::create('inventory_counts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('number', 20)->unique();
    $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $table->foreignUuid('category_id')->nullable()->constrained('categories')->restrictOnDelete();
    $table->date('count_date');
    $table->string('status', 20)->default('counting');
    $table->string('notes', 1000)->nullable();
    $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('closed_at')->nullable();
    $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('cancelled_at')->nullable();
    $table->timestamps();
});
DB::statement("CREATE UNIQUE INDEX inventory_counts_one_open_per_warehouse ON inventory_counts (warehouse_id) WHERE status = 'counting'");

Schema::create('inventory_count_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('inventory_count_id')->constrained('inventory_counts')->cascadeOnDelete();
    $table->foreignUuid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
    $table->decimal('system_quantity', 18, 4)->default(0);
    $table->decimal('counted_quantity', 18, 4)->nullable();
    $table->decimal('closing_system_quantity', 18, 4)->nullable();
    $table->decimal('difference', 18, 4)->nullable();
    $table->decimal('unit_cost', 18, 6)->default(0);
    $table->decimal('difference_value', 18, 4)->nullable();
    $table->foreignUuid('counted_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('counted_at')->nullable();
    $table->timestamps();
    $table->unique(['inventory_count_id', 'catalog_item_id']);
});

Schema::create('warehouse_notes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('number', 20);
    $table->string('type', 10);    // in | out | transfer
    $table->string('status', 20)->default('draft');
    $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $table->foreignUuid('target_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
    $table->foreignUuid('reason_id')->constrained('warehouse_reasons')->restrictOnDelete();
    $table->foreignUuid('inventory_count_id')->nullable()->constrained('inventory_counts')->restrictOnDelete();
    $table->date('document_date');
    $table->string('reference_document', 60)->nullable();
    $table->string('observation', 1000)->nullable();
    $table->decimal('total_cost', 18, 4)->default(0);
    $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();
    $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('cancelled_at')->nullable();
    $table->string('cancel_reason', 500)->nullable();
    $table->timestamps();
    $table->unique(['type', 'number']);
    $table->index(['branch_id', 'status', 'document_date']);
});

Schema::create('warehouse_note_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('warehouse_note_id')->constrained('warehouse_notes')->cascadeOnDelete();
    $table->unsignedSmallInteger('line');
    $table->foreignUuid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
    $table->foreignUuid('item_presentation_id')->constrained('item_presentations')->restrictOnDelete();
    $table->decimal('quantity', 18, 4);
    $table->decimal('base_quantity', 18, 4);
    $table->decimal('unit_cost', 18, 4)->nullable();
    $table->decimal('base_unit_cost', 18, 6)->nullable();
    $table->decimal('total_cost', 18, 4)->default(0);
    $table->string('notes', 255)->nullable();
    $table->timestamps();
});
```

## 7. Configuración e infraestructura
- Sin variables nuevas. Morph map: `warehouse_note => WarehouseNote::class`, `inventory_count => InventoryCount::class`.

## 8. Frontend
- **Listado** `src/pages/inventory/warehouse-notes/index.vue` (`warehouse_notes.view`): filtros (tipo, estado, almacén, motivo, rango de fechas, búsqueda); columnas número, fecha, tipo (chip de color), almacén → destino, motivo, total, estado, creado por, acciones según estado y `can()`.
- **Formulario** `new.vue` y `[id].vue` (el detalle muestra el formulario en modo lectura si no es `draft`): cabecera (tipo con `VBtnToggle`, almacén, destino si es traslado, motivo filtrado por tipo, fecha, referencia, observación) y grilla de líneas (`WarehouseNoteItemsTable.vue`) con `CatalogItemPicker` (`context=stock`, `warehouseId` de origen), cantidad, costo (solo `in`), subtotal y stock disponible. Botones "Guardar borrador", "Guardar y aprobar" (si el motivo no requiere aprobación o el usuario puede aprobar), "Aprobar" y "Anular" (diálogo con motivo). El detalle aprobado muestra la pestaña "Movimientos de kardex".
- **Toma de inventario** `src/pages/inventory/counts/index.vue` y `[id].vue`: cabecera con progreso (`VProgressLinear` contado/total); grilla con búsqueda y campo de escaneo (Enter busca el código de barras y enfoca la cantidad); guardado en lotes cada 20 cambios o al pulsar "Guardar"; botón "Cerrar toma" abre un resumen (sobrantes y faltantes valorizados) con la opción "Tratar no contados como cero".
- **Menú:** "Inventario" › "Notas de almacén" (`tabler-file-invoice`, `warehouse_notes.view`) y "Toma de inventario" (`tabler-clipboard-check`, `inventory_counts.view`).

## 9. Testing
- `tests/Feature/Modules/Inventory/WarehouseNotesApiTest.php`: crear borrador sin afectar stock; numeración por tipo; motivo de otro tipo → 422; motivo de sistema → 422 (salvo OPENING); servicio → 422; traslado al mismo almacén → 422; editar aprobada → 422; aprobar ingreso (stock y costo promedio); aprobar salida sin stock → 422 y sigue en draft; aprobar traslado entre sedes; `approve: true` con motivo sin aprobación; anular aprobada revierte el stock; anular una entrada ya consumida → 422; visibilidad del traslado en la sede destino; 403 sin permisos.
- `tests/Feature/Modules/Inventory/InventoryCountsApiTest.php`: crear genera líneas filtradas por categoría; segundo conteo abierto → 422; conteo de ítem nuevo; cerrar con pendientes → 422; cerrar genera notas IN/OUT aprobadas y el stock queda igual a lo contado; anular la nota de una toma → 422; anular toma cerrada revierte.
- Al final de cada clase: `artisan('inventory:verify-stock')` sin diferencias.
- Ejecutar: `make test-filter FILTER=WarehouseNotes` y `make test-filter FILTER=InventoryCounts`.

## 10. Decisiones Rechazadas
- **Prohibir la anulación de notas aprobadas (Multinort):** obliga a crear notas inversas a mano; la reversión automática es trazable y más simple para el usuario.
- **Aplicar stock al crear (sin borrador):** elimina el control del jefe de almacén; se permite solo con motivos sin aprobación.
- **Toma de inventario como módulo independiente con tablas propias de ajuste:** se reutilizan las notas de almacén para no duplicar la lógica de aplicación de stock.
