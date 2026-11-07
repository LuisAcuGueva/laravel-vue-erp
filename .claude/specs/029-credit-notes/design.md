# 029 · credit-notes — Design
> **Spec:** 029 · **Alcance:** fullstack · **Módulo:** Sales · **Prioridad:** media · **Depende de specs:** 015, 018, 024, 027, 028
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend** (módulo `Sales`):
  - `Models/{CreditNote,CreditNoteItem}.php`
  - `Enums/{CreditNoteReason,CreditNoteStatus,CreditNoteSettlementMode}.php`
  - `Services/{CreditNoteIssuanceService,CreditNoteCancellationService,CreditNoteSettlementService}.php`
  - `Controllers/CreditNoteController.php`
  - `Requests/{StoreCreditNoteRequest,ListCreditNotesRequest}.php` (+ `CancelDocumentRequest`)
  - `Resources/{CreditNoteResource,CreditNoteItemResource}.php`
  - Vistas `resources/views/pdf/sales/{credit-note-a4,credit-note-ticket}.blade.php`
- **Reutilizados:** `DocumentSeriesService`, `DocumentTotalsCalculator`, `StockLedgerService`, `MoneySettlementService`, `CashSessionSummaryService::availableCash()`, `SendElectronicDocumentJob` + `ElectronicInvoicingDriver`, `PdfRenderer`, `NumberToWords`.
- **Modificados:** `SaleCancellationService::hasCreditNotes()`, `ReceivableQueryService::statement()` (abonos por notas), morph map (`credit_note`), `SystemReason`/`SystemConcept` ya incluyen `SALE_RETURN`/`SALE_REFUND`.
- **Frontend:** `front-vue/src/pages/sales/credit-notes/{index,new,[id]}.vue`, `front-vue/src/modules/sales/{services/creditNotesApi.js, stores/creditNotesStore.js, components/credit-notes/*}`, acción en `pages/sales/history/[id].vue`.

## 1. Backend — Modelo y Persistencia
- **`CreditNote`** (`HasUuids`, `LogsActivity` en `status`/`einvoice_status`): `branch_id`, `sale_id`, `document_series_id`, `series`, `number`, `full_number`, `issue_date`, `issued_at`, `reason_code` (`CreditNoteReason`: `01`, `04`, `05`, `06`, `07`, `09`), `reason_description`, `client_id` + datos congelados del cliente (copiados de la venta), `currency_id`, `exchange_rate`, `tax_rate`, `prices_include_tax`, `taxable_base`, `exempt_base`, `unaffected_base`, `tax_total`, `total`, `applied_to_balance`, `refunded_amount`, `settlement_mode`, `refund_payment_method_id`, `refund_cash_session_id`, `warehouse_id`, `status` (`issued`, `cancelled`), `einvoice_status`, `einvoice_attempts`, `einvoice_response`, `einvoice_sent_at`, `created_by`, `cancelled_*`. Relaciones `sale`, `items`, `kardexMovements()`, `cashMovements()`, `bankMovements()`, `installmentAdjustments` (ver abajo).
- **`CreditNoteItem`** (`HasUuids`): `credit_note_id`, `line`, `sale_item_id`, `catalog_item_id`, `item_presentation_id`, `description`, `quantity`, `base_quantity`, `unit_price`, `discount_percent`, `tax_type`, `line_subtotal`, `line_tax`, `line_total`, `return_to_stock`, `unit_cost` (de la línea de venta).
- **`CreditNoteInstallmentAdjustment`** (`HasUuids`): `credit_note_id`, `receivable_installment_id`, `amount` (para revertir con exactitud al anular).

## 2. Backend — Lógica de Negocio y Servicios
### `CreditNoteIssuanceService::issue(array $d, User $u): CreditNote` (transacción)
1. `lockForUpdate` de la venta; exige `issued`; serie `credit_note` activa de la sede activa con prefijo coherente (`invoice → F`, `receipt → B`, `sale_note → ` cualquier letra distinta de F/B).
2. Construye las líneas según el motivo:
   - `01` / `06`: todas las líneas con su cantidad pendiente (vendida − devuelta en notas vigentes); `01` exige que no haya notas vigentes.
   - `07`: líneas indicadas; valida pendientes por línea.
   - `05` / `09`: líneas indicadas con `amount` (monto con impuesto) → se convierte a `unit_price` sobre cantidad 1, sin stock.
   - `04`: una línea de descuento global con `amount` prorrateado por tipo de impuesto según las bases de la venta.
3. Totales con `DocumentTotalsCalculator` y la tasa de la venta; valida `total ≤ sale.total − Σ notas vigentes`.
4. Correlativo con `DocumentSeriesService::next()`; crea la nota y las líneas.
5. Stock: líneas con `return_to_stock` → `StockLedgerService::applyBatch()` con `type = sale_return`, `reasonCode = SALE_RETURN`, `unitCost = sale_item.unit_cost`, almacén `warehouse_id ?? sale.warehouse_id` (de la misma sede).
6. `CreditNoteSettlementService::apply($note, $d['settlement'])`.
7. `einvoice_status` según la serie; job después del commit (reutiliza `SendElectronicDocumentJob`, que acepta `Sale|CreditNote`).
### `CreditNoteSettlementService`
- `apply(CreditNote $n, array $s)`: `mode = auto` → `toBalance = min(total, sale.balance)`; reduce las cuotas pendientes de la venta desde la última (`lockForUpdate`, registra `CreditNoteInstallmentAdjustment`), y `sale.balance −= toBalance`; `refund = total − toBalance`. `mode = refund` → `toBalance = 0`, `refund = total`. Si `refund > 0`: exige método (y sesión si es de caja); valida `availableCash` en caja; `MoneySettlementService::settle(direction: out, concept: SALE_REFUND, source: $n)`. Guarda `applied_to_balance`, `refunded_amount`.
- `revert(CreditNote $n)`: `unsettle($n)` (si hubo reembolso); restaura cuotas desde los ajustes y `sale.balance += applied_to_balance`.
### `CreditNoteCancellationService::cancel(CreditNote $n, string $reason, User $u)`
- Exige `issued` y `einvoice_status ≠ accepted`; revierte kardex (`reverse` de cada movimiento: requiere stock suficiente) y `CreditNoteSettlementService::revert()`; `status = cancelled`.
### Hooks
- `SaleCancellationService::hasCreditNotes(Sale $s)` → notas `issued` de la venta.
- `ReceivableQueryService::statement()` agrega las notas (`applied_to_balance`) como abonos.
- `SendElectronicDocumentJob` y `ElectronicInvoicingDriver::send()` aceptan `CreditNote` (el spec 027 ya tipa `Model`).

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `StoreCreditNoteRequest`: `sale_id` required|uuid; `document_series_id` required|uuid; `reason_code` required|in:01,04,05,06,07,09; `reason_description` required|string|min:5|max:500; `issue_date` nullable|date|before_or_equal:today|after_or_equal:sale.issue_date; `warehouse_id` nullable|uuid; `items` required_if:reason_code,05,07,09|array; `items.*.sale_item_id` required|uuid|distinct; `items.*.quantity` required_if:reason_code,07|numeric|gt:0; `items.*.amount` required_if:reason_code,05,09|numeric|gt:0|decimal:0,2; `items.*.return_to_stock` boolean; `amount` required_if:reason_code,04|numeric|gt:0|decimal:0,2; `settlement.mode` required|in:auto,refund; `settlement.payment_method_id` nullable|uuid; `settlement.cash_session_id` nullable|uuid; `settlement.reference` nullable|max:60.
- `CreditNoteResource` incluye `sale { id, full_number, document_type, issue_date }`, líneas, `applied_to_balance`, `refunded_amount`, movimientos y estado electrónico.
- Rutas (`v1/sales`, `branch`):
```php
// Notas de crédito (Spec 029)
Route::get('/credit-notes', [CreditNoteController::class, 'index'])->middleware('permission:credit_notes.view');
Route::get('/credit-notes/prefill', [CreditNoteController::class, 'prefill'])->middleware('permission:credit_notes.create');
Route::post('/credit-notes', [CreditNoteController::class, 'store'])->middleware('permission:credit_notes.create');
Route::get('/credit-notes/{id}', [CreditNoteController::class, 'show'])->whereUuid('id')->middleware('permission:credit_notes.view');
Route::get('/credit-notes/{id}/pdf', [CreditNoteController::class, 'pdf'])->whereUuid('id')->middleware('permission:credit_notes.view');
Route::post('/credit-notes/{id}/cancel', [CreditNoteController::class, 'cancel'])->whereUuid('id')->middleware('permission:credit_notes.cancel');
Route::post('/credit-notes/{id}/einvoice/retry', [CreditNoteController::class, 'retryEInvoice'])->whereUuid('id')->middleware('permission:document_series.manage');
```
- `prefill?sale_id=` devuelve las líneas con cantidad pendiente de devolución, el saldo de la venta, las series aplicables y el total máximo acreditable.

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/sales/credit-notes` | `credit_notes.view` | filtros `sale_id, client_id, reason_code, status, date_from, date_to, search` | 200 `data[]`, `meta.totals` | 401, 403 |
| GET | `/api/v1/sales/credit-notes/prefill` | `credit_notes.create` | query `sale_id` | 200 `data: { sale, lines[], max_creditable, balance, series[] }` | 401, 403, 404, 422 |
| POST | `/api/v1/sales/credit-notes` | `credit_notes.create` | §3 | 201 `data: CreditNote` | 401, 403, 409, 422 |
| GET | `/api/v1/sales/credit-notes/{id}` | `credit_notes.view` | — | 200 | 401, 403, 404 |
| GET | `/api/v1/sales/credit-notes/{id}/pdf` | `credit_notes.view` | query `format=ticket|a4`, `inline?` | 200 PDF | 401, 403, 404 |
| POST | `/api/v1/sales/credit-notes/{id}/cancel` | `credit_notes.cancel` | `{ reason }` | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/sales/credit-notes/{id}/einvoice/retry` | `document_series.manage` | — | 202 | 401, 403, 404, 422 |

Ejemplo `POST /credit-notes` (devolución parcial con reembolso en efectivo):
```json
{ "sale_id": "…", "document_series_id": "<BC01>", "reason_code": "07", "reason_description": "Devolución de 1 unidad por falla de fábrica",
  "items": [ { "sale_item_id": "…", "quantity": 1, "return_to_stock": true } ],
  "settlement": { "mode": "auto", "payment_method_id": "<CASH>", "cash_session_id": "…" } }
```

## 5. Permisos y seguridad
- Permisos nuevos: `credit_notes.view|create|cancel`. `admin` y `manager` todos; `user` `credit_notes.view`.

## 6. Migraciones
`2026_10_20_000005_create_credit_notes_tables.php`:
```php
Schema::create('credit_notes', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
    $t->foreignUuid('document_series_id')->constrained('document_series')->restrictOnDelete();
    $t->char('series', 4); $t->unsignedInteger('number'); $t->string('full_number', 13);
    $t->date('issue_date'); $t->timestamp('issued_at');
    $t->char('reason_code', 2); $t->string('reason_description', 500);
    $t->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
    $t->string('client_document_type', 10)->nullable(); $t->string('client_document_number', 20)->nullable();
    $t->string('client_name', 200); $t->string('client_address')->nullable();
    $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete(); $t->decimal('exchange_rate', 12, 4)->default(1);
    $t->decimal('tax_rate', 5, 2); $t->boolean('prices_include_tax')->default(true);
    $t->decimal('taxable_base', 18, 2)->default(0); $t->decimal('exempt_base', 18, 2)->default(0);
    $t->decimal('unaffected_base', 18, 2)->default(0); $t->decimal('tax_total', 18, 2)->default(0); $t->decimal('total', 18, 2);
    $t->decimal('applied_to_balance', 18, 2)->default(0); $t->decimal('refunded_amount', 18, 2)->default(0);
    $t->string('settlement_mode', 10);
    $t->foreignUuid('refund_payment_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
    $t->foreignUuid('refund_cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
    $t->foreignUuid('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
    $t->string('status', 10)->default('issued');
    $t->string('einvoice_status', 16)->default('not_applicable'); $t->unsignedTinyInteger('einvoice_attempts')->default(0);
    $t->json('einvoice_response')->nullable(); $t->timestamp('einvoice_sent_at')->nullable();
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('cancelled_at')->nullable();
    $t->string('cancel_reason', 500)->nullable(); $t->timestamps();
    $t->unique(['document_series_id', 'number']);
    $t->index(['sale_id', 'status']); $t->index(['branch_id', 'issue_date']);
});
Schema::create('credit_note_items', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
    $t->unsignedSmallInteger('line');
    $t->foreignUuid('sale_item_id')->nullable()->constrained('sale_items')->restrictOnDelete();
    $t->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
    $t->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
    $t->string('description', 255);
    $t->decimal('quantity', 18, 4); $t->decimal('base_quantity', 18, 4)->nullable();
    $t->decimal('unit_price', 18, 4); $t->decimal('discount_percent', 5, 2)->default(0); $t->string('tax_type', 20)->default('taxable');
    $t->decimal('line_subtotal', 18, 2); $t->decimal('line_tax', 18, 2); $t->decimal('line_total', 18, 2);
    $t->boolean('return_to_stock')->default(false); $t->decimal('unit_cost', 18, 6)->default(0);
    $t->timestamps();
});
Schema::create('credit_note_installment_adjustments', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
    $t->foreignUuid('receivable_installment_id')->constrained('receivable_installments')->restrictOnDelete();
    $t->decimal('amount', 18, 2); $t->timestamps();
});
```

## 7. Configuración e infraestructura
- Sin variables nuevas (reutiliza `config/einvoicing.php`). Morph map: `credit_note`.
- Seeder de dev: series `FC01` y `BC01` (spec 027) y `NC01` para notas de venta.

## 8. Frontend
- **Desde la venta** (`pages/sales/history/[id].vue`): botón "Emitir nota de crédito" (`can('credit_notes.create')`, visible si la venta está `issued`) → `pages/sales/credit-notes/new.vue?sale_id=`.
- **Formulario** `new.vue`: resumen de la venta (comprobante, cliente, total, saldo, máximo acreditable), serie (de `prefill`), motivo (`VSelect` con descripción del catálogo 09), sustento; según el motivo: tabla de líneas con cantidad a devolver y "Devolver al stock" (06/07, con 06 todo marcado y no editable), montos por línea (05/09) o monto global (04); almacén de devolución; sección de aplicación (`auto`/`refund`) con `PaymentMethodFields` cuando hay reembolso y vista previa "Rebaja de deuda: X · Reembolso: Y".
- **Listado** `index.vue` y **detalle** `[id].vue`: número, venta afectada, motivo, total, aplicado/reembolsado, estado y estado electrónico; PDF A4/ticket, anulación y reintento.
- **Menú:** "Ventas" › "Notas de crédito" (`credit_notes.view`).

## 9. Testing
- `tests/Feature/Modules/Sales/CreditNotesApiTest.php`: `07` parcial devuelve stock al costo original (kardex `sale_return`) y no excede lo vendido; segunda nota sobre la misma línea respeta lo pendiente; `06` total; `01` con nota previa → 422; `04` prorratea bases; `05`/`09` sin stock; serie incoherente (B para factura) → 422; total sobre el máximo → 422; venta anulada → 422; PDF; job electrónico con driver nulo; anular revierte stock y aplicación; anular aceptada → 422; venta con nota vigente no se anula (spec 027).
- `tests/Feature/Modules/Sales/CreditNoteSettlementTest.php`: venta al crédito con saldo: `auto` aplica al saldo y reduce cuotas desde la última; remanente reembolsado en caja (movimiento `SALE_REFUND`); `refund` total en banco; caja sin efectivo suficiente → 422; anular restaura cuotas exactas; estado de cuenta con la nota como abono.
- Al final: `inventory:verify-stock` sin diferencias.
- Ejecutar: `make test-filter FILTER=CreditNote`.

## 10. Decisiones Rechazadas
- **Estados pendiente/aplicado con aprobación (Multinort):** la nota se aplica al emitirse; el control es el permiso.
- **Valorizar la devolución al costo promedio actual:** distorsiona el margen de la venta original; se usa el costo con que salió.
- **Saldo a favor automático cuando no hay deuda:** requiere un módulo de anticipos; se reembolsa o queda fuera de alcance.
- **`affects_inventory` ligado al flujo de la ODS:** reemplazado por `return_to_stock` por línea.
