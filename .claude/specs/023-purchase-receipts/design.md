# 023 · purchase-receipts — Design
> **Spec:** 023 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 013, 015, 021, 022
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend** (módulo `Purchases`): `Models/{PurchaseReceipt,PurchaseReceiptItem}.php`, `Enums/{PurchaseReceiptStatus,ProviderDocumentType}.php`, `Services/{PurchaseReceiptService,PurchaseReceiptConfirmationService}.php`, `Controllers/PurchaseReceiptController.php`, `Requests/{StorePurchaseReceiptRequest,ReceiveAllRequest,ListPurchaseReceiptsRequest}.php`, `Resources/{PurchaseReceiptResource,PurchaseReceiptItemResource}.php`.
- **Reutilizados:** `StockLedgerService::applyBatch()`/`reverse()`, `PurchaseOrderWorkflowService::markReceived()`, `ProviderPriceService::recordPurchase()`, `SequentialCodeGenerator`.
- **Modificados:** `PurchaseOrderController` (`pending-items`, `receive-all`, recepciones en `show`), morph map (`purchase_receipt`), `SystemParameterSeeder` (`purchases.over_receipt_tolerance_percent`).
- **Frontend:** `front-vue/src/pages/purchases/receipts/{index,new,[id]}.vue`, `front-vue/src/modules/purchases/{services/purchaseReceiptsApi.js, stores/purchaseReceiptsStore.js, components/receipts/ReceiptItemsTable.vue}`.

## 1. Backend — Modelo y Persistencia
- **`PurchaseReceipt`** (`HasUuids`, `LogsActivity` en `status`): `number`, `branch_id`, `purchase_order_id`, `provider_id` (copiado de la OC), `warehouse_id`, `receipt_date`, `provider_document_type`, `provider_document_number`, `notes`, `status`, `total_cost`, `created_by`, `confirmed_by`, `confirmed_at`, `cancelled_*`. Relaciones `order`, `items`, `kardexMovements()` (`morphMany` `source`).
- **`PurchaseReceiptItem`** (`HasUuids`): `purchase_receipt_id`, `line`, `purchase_order_item_id`, `catalog_item_id`, `item_presentation_id`, `is_service`, `quantity`, `base_quantity`, `unit_cost` (por presentación, moneda base, sin IGV), `base_unit_cost`, `total_cost`, `notes`.

## 2. Backend — Lógica de Negocio y Servicios
### `PurchaseReceiptService`
- `create(array $d, User $u)`: transacción; `lockForUpdate` de la OC; valida estado y sede; por línea: la línea pertenece a la OC; `pending = quantity − received_quantity`; `max = pending × (1 + tolerance/100)`; calcula el costo:
  `netUnit = unit_price × (1 − discount_percent/100)`; si `order.prices_include_tax` y la línea es `taxable` → `netUnit = netUnit / (1 + tax_rate/100)`; `unit_cost = netUnit × order.exchange_rate` (4 decimales); `base_quantity = presentation.toBaseQuantity(quantity)`; `base_unit_cost = unit_cost / factor` (6 decimales). Número `RC-` con `SequentialCodeGenerator`.
- `update` (solo `draft`), `pendingItems(PurchaseOrder)`.
### `PurchaseReceiptConfirmationService`
- `confirm(PurchaseReceipt $r, User $u)`: transacción; `lockForUpdate` de la recepción, la OC y sus líneas; exige `draft`; revalida los pendientes; `StockLedgerService::applyBatch()` con `StockMovementData(type: purchase, reasonCode: PURCHASE, unitCost: base_unit_cost, presentationId, presentationQuantity, source: $r, sourceNumber: $r->number)` para las líneas con `track_stock`; incrementa `received_quantity`; `PurchaseOrderWorkflowService::markReceived($order)`; `catalog_item.last_purchase_cost = base_unit_cost`; `ProviderPriceService::recordPurchase(provider, presentation, order.currency, unit_price neto en moneda de la OC, receipt_date)`; `status = confirmed`.
- `receiveAll(PurchaseOrder $o, array $header, User $u)`: arma las líneas con todo el pendiente y llama `create()` + `confirm()` en la misma transacción.
- `cancel(PurchaseReceipt $r, string $reason, User $u)`: exige no `cancelled`; hook `isInvoiced()` (lo implementa el spec 024, aquí `false`); si `confirmed` → `reverse()` de cada movimiento de kardex, decrementa `received_quantity` y recalcula el estado de la OC (sin reabrir `closed`).

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `StorePurchaseReceiptRequest`: `purchase_order_id` required|uuid|exists; `warehouse_id` nullable|uuid; `receipt_date` required|date|before_or_equal:today; `provider_document_type` required|enum; `provider_document_number` nullable|max:30; `notes` nullable|max:1000; `items` required|array|min:1; `items.*.purchase_order_item_id` required|uuid|distinct; `items.*.quantity` required|numeric|gt:0|decimal:0,4; `items.*.notes` nullable|max:255.
- `ReceiveAllRequest`: `warehouse_id` nullable|uuid; `receipt_date` required|date; `provider_document_type` required|enum; `provider_document_number` nullable|max:30.
- Rutas (`v1/purchases`, `branch`):
```php
// Recepciones de compra (Spec 023)
Route::get('/orders/{id}/pending-items', [PurchaseReceiptController::class, 'pendingItems'])->whereUuid('id')->middleware('permission:purchase_receipts.create');
Route::post('/orders/{id}/receive-all', [PurchaseReceiptController::class, 'receiveAll'])->whereUuid('id')->middleware(['permission:purchase_receipts.create', 'permission:purchase_receipts.confirm']);
Route::get('/receipts', [PurchaseReceiptController::class, 'index'])->middleware('permission:purchase_receipts.view');
Route::post('/receipts', [PurchaseReceiptController::class, 'store'])->middleware('permission:purchase_receipts.create');
Route::get('/receipts/{id}', [PurchaseReceiptController::class, 'show'])->whereUuid('id')->middleware('permission:purchase_receipts.view');
Route::put('/receipts/{id}', [PurchaseReceiptController::class, 'update'])->whereUuid('id')->middleware('permission:purchase_receipts.create');
Route::post('/receipts/{id}/confirm', [PurchaseReceiptController::class, 'confirm'])->whereUuid('id')->middleware('permission:purchase_receipts.confirm');
Route::post('/receipts/{id}/cancel', [PurchaseReceiptController::class, 'cancel'])->whereUuid('id')->middleware('permission:purchase_receipts.cancel');
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/purchases/orders/{id}/pending-items` | `purchase_receipts.create` | — | 200 `data: [{ purchase_order_item_id, item, ordered, received, pending, unit_cost_preview }]` | 401, 403, 404 |
| POST | `/api/v1/purchases/orders/{id}/receive-all` | `create` + `confirm` | §3 | 201 `data: PurchaseReceipt` (confirmada) | 401, 403, 404, 422 |
| GET/POST | `/api/v1/purchases/receipts` | `view` / `create` | §3 | 200 / 201 | 401, 403, 422 |
| GET/PUT | `/api/v1/purchases/receipts/{id}` | `view` / `create` | §3 | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/purchases/receipts/{id}/confirm` | `purchase_receipts.confirm` | — | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/purchases/receipts/{id}/cancel` | `purchase_receipts.cancel` | `{ reason }` | 200 | 401, 403, 404, 422 |

## 5. Permisos y seguridad
- Permisos nuevos: `purchase_receipts.view|create|confirm|cancel`. `admin` y `manager` todos; `user` `view|create`.

## 6. Migraciones
`2026_10_19_000003_create_purchase_receipts_tables.php`:
```php
Schema::create('purchase_receipts', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('number', 20)->unique();
    $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->foreignUuid('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
    $t->foreignUuid('provider_id')->constrained('providers')->restrictOnDelete();
    $t->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $t->date('receipt_date'); $t->string('provider_document_type', 10); $t->string('provider_document_number', 30)->nullable();
    $t->string('notes', 1000)->nullable(); $t->string('status', 12)->default('draft');
    $t->decimal('total_cost', 18, 2)->default(0);
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('confirmed_at')->nullable();
    $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('cancelled_at')->nullable();
    $t->string('cancel_reason', 500)->nullable(); $t->timestamps();
    $t->index(['branch_id', 'status', 'receipt_date']); $t->index('purchase_order_id');
});
Schema::create('purchase_receipt_items', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('purchase_receipt_id')->constrained('purchase_receipts')->cascadeOnDelete();
    $t->unsignedSmallInteger('line');
    $t->foreignUuid('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
    $t->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
    $t->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
    $t->boolean('is_service')->default(false);
    $t->decimal('quantity', 18, 4); $t->decimal('base_quantity', 18, 4)->nullable();
    $t->decimal('unit_cost', 18, 4); $t->decimal('base_unit_cost', 18, 6)->nullable(); $t->decimal('total_cost', 18, 2);
    $t->string('notes', 255)->nullable(); $t->timestamps();
});
```

## 7. Configuración e infraestructura
- Parámetro `purchases.over_receipt_tolerance_percent` (decimal, 0).
- Morph map: `purchase_receipt`.

## 8. Frontend
- **Listado** `pages/purchases/receipts/index.vue` (`purchase_receipts.view`): número, fecha, OC, proveedor, almacén, documento del proveedor, costo total, estado.
- **Formulario** `new.vue?order_id=`: cabecera (OC con `VAutocomplete` sobre OC `approved|partially_received`, almacén, fecha, documento del proveedor) y `ReceiptItemsTable.vue` precargada con `pending-items` (ordenado, recibido, pendiente, a recibir editable, costo unitario de referencia). Botones "Guardar borrador" y "Guardar y confirmar" (este último si `can('purchase_receipts.confirm')`).
- **Detalle** `[id].vue`: líneas, movimientos de kardex y acciones Confirmar/Anular (`CancelDocumentDialog`).
- En el detalle de la OC (spec 022): pestaña "Recepciones" y botón "Recibir todo".
- **Menú:** "Compras" › "Recepciones" (`purchase_receipts.view`).

## 9. Testing
- `tests/Feature/Modules/Purchases/PurchaseReceiptsApiTest.php`:
  - Recepción parcial: stock y costo promedio correctos (OC en USD con tipo de cambio y precios con impuesto → costo en moneda base sin impuesto); OC pasa a `partially_received`; `last_purchase_cost` y precio del proveedor actualizados.
  - Recepción final → OC `received`; exceder el pendiente → 422; tolerancia del 10 % permite el 110 %.
  - Línea de servicio: sin movimientos de kardex, `received_quantity` actualizado.
  - Confirmación concurrente simulada: segunda recepción por encima del pendiente → 422 sin cambios.
  - `receive-all`; anular confirmada (revierte stock y OC); anular con stock ya consumido → 422; OC de otra sede → 403/422.
  - `inventory:verify-stock` sin diferencias al final.
- Ejecutar: `make test-filter FILTER=PurchaseReceipts`.

## 10. Decisiones Rechazadas
- **Notas de ingreso y conformidades separadas (Multinort):** se unifican en recepciones con líneas de producto o servicio.
- **Costo de entrada con impuesto:** el impuesto de compra es crédito fiscal, no costo.
- **Reusar las notas de almacén del spec 016 para compras:** las recepciones necesitan vínculo con líneas de OC, tolerancias y actualización de costos de proveedor.
