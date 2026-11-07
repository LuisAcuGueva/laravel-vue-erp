# 015 · stock-and-kardex — Design
> **Spec:** 015 · **Alcance:** fullstack · **Módulo:** Inventory · **Prioridad:** alta · **Depende de specs:** 010, 011, 014
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
  - `Models/{Stock,KardexMovement}.php`
  - `Enums/{MovementDirection,MovementType}.php`
  - `Data/StockMovementData.php` (DTO inmutable `readonly` de entrada al ledger)
  - `Services/StockLedgerService.php` (núcleo), `Services/StockQueryService.php`, `Services/KardexQueryService.php`
  - `Exceptions/InsufficientStockException.php`
  - `Controllers/{StockController,KardexController}.php`
  - `Requests/{ListStockRequest,UpdateStockLimitsRequest,KardexRequest}.php`
  - `Resources/{StockResource,KardexMovementResource}.php`
  - `Console/VerifyStockCommand.php` (`inventory:verify-stock`)
- **Modificados:** `CatalogItemService::hasMovements()`, `ItemPresentationService` (factor con movimientos), `WarehouseService::delete()`, `CatalogLookupService::withStock()`.
- **Frontend:** `front-vue/src/pages/inventory/stock/index.vue`, `front-vue/src/pages/inventory/kardex/index.vue`, `front-vue/src/modules/inventory/{services/stockApi.js, stores/stockStore.js, components/stock/*}`.

## 1. Backend — Modelo y Persistencia
- **`Stock`** (`HasUuids`, sin `SoftDeletes`, sin `LogsActivity`: el kardex es su auditoría): `warehouse_id`, `catalog_item_id`, `quantity` (decimal:4), `avg_cost` (decimal:6), `min_stock`, `max_stock`, `last_movement_at`. Relaciones `warehouse`, `catalogItem`. Accessors `total_value`, `is_below_min`. **No** expone `quantity`/`avg_cost` en `$fillable` (solo `min_stock`, `max_stock`); el ledger usa `forceFill()`.
- **`KardexMovement`** (`HasUuids`, `public const UPDATED_AT = null`): ver DDL. `booted()`: `static::updating(fn () => throw new LogicException('Kardex inmutable'))` y lo mismo para `deleting`. Relaciones `warehouse`, `catalogItem`, `presentation`, `reason`, `user`, `source()` (`morphTo` con `source_type`/`source_id`), `reverses()`, `reversedBy()` (`hasOne` inversa).
- **`Enums/MovementType`**: `opening`, `purchase`, `purchase_return`, `sale`, `sale_return`, `transfer_in`, `transfer_out`, `adjustment_in`, `adjustment_out`, `note_in`, `note_out`, `reversal_in`, `reversal_out`; método `direction(): MovementDirection`.

## 2. Backend — Lógica de Negocio y Servicios
### `StockMovementData` (DTO)
```php
final readonly class StockMovementData {
    public function __construct(
        public string $warehouseId,
        public string $catalogItemId,
        public float $baseQuantity,          // > 0, en unidad base
        public MovementType $type,
        public Model $source,                // documento de origen (nota, compra, venta…)
        public string $sourceNumber,
        public ?float $unitCost = null,      // obligatorio en entradas (costo por unidad base)
        public ?string $presentationId = null,
        public ?float $presentationQuantity = null,
        public ?string $reasonCode = null,   // SystemReason o código de motivo
        public ?string $notes = null,
    ) {}
}
```
### `StockLedgerService`
- `in(StockMovementData $d): KardexMovement`
- `out(StockMovementData $d): KardexMovement`
- `transfer(StockMovementData $out, string $toWarehouseId): array{0: KardexMovement, 1: KardexMovement}` — `transfer_group_id = Str::uuid()`; entrada con `unitCost = salida.unit_cost`.
- `reverse(KardexMovement $m, Model $source, string $sourceNumber): KardexMovement` — falla si `$m->reversedBy()->exists()`; tipo `reversal_in|out` opuesto; usa `$m->unit_cost`.
- `applyBatch(array $movements): array` — ordena por (`warehouse_id`, `catalog_item_id`) y aplica en ese orden (para documentos multilínea).
- Algoritmo común (`apply()` privado), **requiere transacción abierta** (`DB::transactionLevel() > 0`, si no `LogicException`):
  1. Valida ítem `track_stock` y `baseQuantity > 0`.
  2. `Stock::query()->firstOrCreate([...])` y luego `->lockForUpdate()->find($id)` (re-lectura con bloqueo).
  3. Entrada: `newQty = q₀ + q`; `newAvg = q₀ > 0 ? (q₀·c₀ + q·c) / newQty : c` (redondeo 6).
     Salida: `newQty = q₀ − q`; si `newQty < 0` y `! SystemParameterService::boolean('inventory.allow_negative_stock')` → `InsufficientStockException`; `unitCost = c₀`; `newAvg = c₀`.
  4. `stock->forceFill(['quantity' => newQty, 'avg_cost' => newAvg, 'last_movement_at' => now()])->save()`.
  5. Crea `KardexMovement` con `quantity`, `unit_cost`, `total_cost = round(q × unitCost, 4)`, `balance_quantity`, `balance_unit_cost`, `balance_total_cost`, `branch_id = warehouse.branch_id`, `user_id = auth()->id()`, `moved_at = now()`.
- Precisión: cálculos con `bcmath` (`bcadd`, `bcmul`, `bcdiv` con escala 8) y redondeo final; evitar `float` acumulado.

### `StockQueryService`
- `list(array $filters, Collection $warehouseIds)`: `join` a ítems y almacenes, filtros, `selectRaw('SUM(quantity*avg_cost)')` para `meta.total_value`.
- `byItem(string $itemId, Collection $warehouseIds)`.
- `availableFor(string $warehouseId, array $itemIds): array<string,float>` (usado por `CatalogLookupService::withStock()` y por las validaciones previas de ventas).
- `upsertLimits(...)`.

### `KardexQueryService`
- `openingBalance(warehouseId, itemId, Carbon $from)`: último movimiento con `moved_at < from` → sus `balance_*`; si no hay → ceros.
- `movements(warehouseId, itemId, from, to)`: `orderBy('moved_at')->orderBy('created_at')` con `with('reason:id,code,name','user:id,name')`.
- `streamCsv(...)`: `response()->streamDownload` con `fputcsv` y BOM.

### Hooks activados en servicios previos
- `CatalogItemService::hasMovements(CatalogItem $i): bool` → `KardexMovement::where('catalog_item_id', $i->id)->exists()`.
- `ItemPresentationService::update/delete` → bloquea el cambio de `factor` o el borrado si `KardexMovement::where('item_presentation_id', …)->exists()`.
- `WarehouseService::delete` → bloquea si hay `Stock` con `quantity <> 0` o `KardexMovement` del almacén.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- Las rutas de stock y kardex usan el middleware `branch`; los almacenes aceptados son los de `UserBranchService::accessibleBranches()`; por defecto, los de la sede activa.
- `ListStockRequest`: `warehouse_id` nullable|uuid; `category_id` nullable|uuid; `search` nullable|max:100; `below_min`, `include_zero` boolean; `page`, `per_page`.
- `UpdateStockLimitsRequest`: `warehouse_id`, `catalog_item_id` required|uuid|exists; `min_stock` required|numeric|min:0; `max_stock` nullable|numeric|gte:min_stock.
- `KardexRequest`: `warehouse_id`, `catalog_item_id` required|uuid|exists; `date_from`, `date_to` required|date|after_or_equal:date_from; validación de rango ≤ 366 días en `withValidator`.
- `InsufficientStockException` → 422 `{ "message": "Stock insuficiente…", "errors": { "items": ["…"] }, "meta": { "catalog_item_id", "warehouse_id", "available", "required" } }`.
- Rutas (`v1/inventory`):
```php
// Stock y Kardex (Spec 015)
Route::middleware('branch')->group(function () {
    Route::get('/stock', [StockController::class, 'index'])->middleware('permission:stock.view');
    Route::get('/stock/by-item/{id}', [StockController::class, 'byItem'])->whereUuid('id')->middleware('permission:stock.view');
    Route::put('/stock/limits', [StockController::class, 'updateLimits'])->middleware('permission:stock.update_limits');
    Route::get('/kardex', [KardexController::class, 'index'])->middleware('permission:kardex.view');
    Route::get('/kardex/export', [KardexController::class, 'export'])->middleware('permission:kardex.view');
});
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/inventory/stock` | `branch`, `stock.view` | query de §3 | 200 `data: Stock[]`, `meta { …paginación, total_value }` | 401, 403, 409, 422 |
| GET | `/api/v1/inventory/stock/by-item/{id}` | `branch`, `stock.view` | — | 200 `data: [{ warehouse, quantity, presentation_quantity }]` | 401, 403, 404 |
| PUT | `/api/v1/inventory/stock/limits` | `branch`, `stock.update_limits` | `{ warehouse_id, catalog_item_id, min_stock, max_stock? }` | 200 `data: Stock` | 401, 403, 422 |
| GET | `/api/v1/inventory/kardex` | `branch`, `kardex.view` | query `warehouse_id, catalog_item_id, date_from, date_to` | 200 `data: { opening_balance, movements[], closing_balance }` | 401, 403, 422 |
| GET | `/api/v1/inventory/kardex/export` | `branch`, `kardex.view` | ídem + `format=csv` | 200 `text/csv` | 401, 403, 422 |

Ejemplo de movimiento de kardex:
```json
{ "id": "…", "moved_at": "2026-11-03T10:15:00-05:00", "movement_type": "purchase", "direction": "in", "reason": { "code": "PURCHASE", "name": "Compra" }, "source_type": "purchase_receipt", "source_number": "RC-000012",
  "quantity": 24, "unit_cost": 4.5, "total_cost": 108, "balance_quantity": 60, "balance_unit_cost": 4.3, "balance_total_cost": 258, "user": { "id": "…", "name": "Ana" } }
```

## 5. Permisos y seguridad
- Permisos nuevos: `stock.view`, `stock.update_limits` (módulo `stock`), `kardex.view` (módulo `kardex`). `admin` todos; `manager` todos; `user` `stock.view`.
- `source_type` usa el *morph map* (`Relation::enforceMorphMap` en `InventoryServiceProvider`) para no exponer nombres de clase: `warehouse_note`, `purchase_receipt`, `sale`, `credit_note`, `inventory_count`. Cada spec agrega su alias.

## 6. Migraciones
`2026_10_17_000004_create_stock_and_kardex_tables.php`:
```php
Schema::create('stocks', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $table->foreignUuid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
    $table->decimal('quantity', 18, 4)->default(0);
    $table->decimal('avg_cost', 18, 6)->default(0);
    $table->decimal('min_stock', 18, 4)->default(0);
    $table->decimal('max_stock', 18, 4)->nullable();
    $table->timestamp('last_movement_at')->nullable();
    $table->timestamps();
    $table->unique(['warehouse_id', 'catalog_item_id']);
    $table->index('catalog_item_id');
});

Schema::create('kardex_movements', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $table->foreignUuid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
    $table->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
    $table->foreignUuid('reason_id')->nullable()->constrained('warehouse_reasons')->restrictOnDelete();
    $table->string('direction', 3);           // in | out
    $table->string('movement_type', 20);
    $table->decimal('quantity', 18, 4);       // unidad base, siempre > 0
    $table->decimal('presentation_quantity', 18, 4)->nullable();
    $table->decimal('unit_cost', 18, 6);
    $table->decimal('total_cost', 18, 4);
    $table->decimal('balance_quantity', 18, 4);
    $table->decimal('balance_unit_cost', 18, 6);
    $table->decimal('balance_total_cost', 18, 4);
    $table->string('source_type', 40);
    $table->uuid('source_id');
    $table->string('source_number', 40);
    $table->uuid('transfer_group_id')->nullable();
    $table->foreignUuid('reverses_id')->nullable()->unique()->constrained('kardex_movements')->restrictOnDelete();
    $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('notes', 500)->nullable();
    $table->timestamp('moved_at');
    $table->timestamp('created_at')->useCurrent();
    $table->index(['warehouse_id', 'catalog_item_id', 'moved_at']);
    $table->index(['source_type', 'source_id']);
    $table->index('transfer_group_id');
});
```

## 7. Configuración e infraestructura
- Parámetro `inventory.allow_negative_stock` ya sembrado en el spec 010.
- Comando `inventory:verify-stock {--fix=false}`: compara `stocks.quantity` con `SUM(in) − SUM(out)` de `kardex_movements` y lista diferencias (no corrige automáticamente; `--fix` solo reporta en esta entrega).
- `ext-bcmath` disponible en la imagen PHP (verificar en `docker/`; si no, agregarlo al Dockerfile).

## 8. Frontend
- **Stock** `src/pages/inventory/stock/index.vue` (`action: 'stock.view'`): selector de almacén (opciones de la sede activa, con "Todos"), categoría, búsqueda, switches "Bajo mínimo" e "Incluir sin stock". `ServerDataTable` con código, ítem, almacén, cantidad + unidad, costo promedio, valor, mínimo/máximo (edición inline en diálogo `StockLimitsDialog.vue` con `can('stock.update_limits')`), último movimiento. Pie con `meta.total_value`. Acción "Ver kardex" → `/inventory/kardex?warehouse_id=&catalog_item_id=`.
- **Kardex** `src/pages/inventory/kardex/index.vue` (`action: 'kardex.view'`): filtros (almacén, `CatalogItemPicker` con `context=stock`, rango de fechas con valor por defecto en el mes actual); `VTable` con encabezado agrupado (Fecha · Documento · Motivo | Entrada: Cant, C.U., Total | Salida: Cant, C.U., Total | Saldo: Cant, C.P., Total); primera fila "Saldo inicial"; botón "Exportar CSV".
- **Stock en el picker:** `CatalogItemPicker` muestra `stock.available` cuando recibe `warehouseId`.
- **Menú:** "Inventario" › "Stock" (`tabler-stack-2`, `stock.view`) y "Kardex" (`tabler-report-analytics`, `kardex.view`).

## 9. Testing
- `tests/Feature/Modules/Inventory/StockLedgerServiceTest.php` (con BD):
  - Entrada 10 @ 5 → stock 10, promedio 5; entrada 10 @ 7 → promedio 6; salida 5 → costo 6, saldo 15 @ 6, total 90.
  - Salida mayor al stock con parámetro en `false` → `InsufficientStockException`; con `true` → saldo negativo permitido.
  - Traslado conserva el costo y comparte `transfer_group_id`.
  - Reversión de una entrada restaura cantidad; segunda reversión → excepción.
  - `update`/`delete` de `KardexMovement` lanza `LogicException`.
  - Llamar al ledger sin transacción → `LogicException`.
  - Ítem servicio → `InventoryRuleException`.
- `StockApiTest.php`: listado por sede activa; almacén de sede no accesible → 403; `below_min`; `meta.total_value`; límites inválidos → 422.
- `KardexApiTest.php`: saldo inicial correcto; rango > 366 días → 422; export CSV con cabeceras.
- `VerifyStockCommandTest.php`: sin diferencias tras movimientos.
- Ejecutar: `make test-filter FILTER=Stock` y `make test-filter FILTER=Kardex`.

## 10. Decisiones Rechazadas
- **Stock por presentación (Multinort):** obliga a conversiones entre filas de stock del mismo ítem.
- **Stock abierto/cerrado para fraccionar envases:** caso del rubro (aceites); se resuelve con presentaciones y unidad base.
- **Kardex con `softDeletes`:** rompe la trazabilidad; se usa reversión.
- **Recalcular el promedio en un job asíncrono:** introduce ventanas de inconsistencia; el cálculo es síncrono con bloqueo de fila.
