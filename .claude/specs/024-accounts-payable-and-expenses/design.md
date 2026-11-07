# 024 · accounts-payable-and-expenses — Design
> **Spec:** 024 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 017, 018, 020, 021, 022, 023
> **Status:** Done
> **Última actualización:** 2026-10-20
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend** (módulo `Purchases`):
  - `Models/{SupplierInvoice,SupplierInvoiceItem,PayableInstallment,SupplierPayment,SupplierPaymentAllocation}.php`
  - `Enums/{SupplierInvoiceKind,SupplierDocumentType,SupplierInvoiceStatus,SupplierPaymentStatus}.php`
  - `Services/{SupplierInvoiceService,SupplierPaymentService,AccountsPayableQueryService}.php`
  - `app/Modules/Finance/Services/MoneySettlementService.php` + `app/Modules/Finance/Data/SettlementData.php` — **servicio transversal de Finance creado en este spec**: dado un método de pago, sesión de caja, monto, dirección, concepto y documento de origen, registra el movimiento en el ledger que corresponda (caja o banco) y devuelve el movimiento. Lo reutilizan los specs 027, 028 y 029.
  - `Controllers/{SupplierInvoiceController,SupplierPaymentController,AccountsPayableController}.php`
  - `Requests/{StoreSupplierInvoiceRequest,StoreSupplierPaymentRequest,ListPayablesRequest,ProviderStatementRequest}.php`
  - `Resources/{SupplierInvoiceResource,PayableInstallmentResource,SupplierPaymentResource,AgingResource}.php`
- **Modificados:** `PurchaseReceiptConfirmationService::isInvoiced()`, `ProviderService::hasDocuments()`, `pages/finance/cash/index.vue` (botón "Registrar gasto"), morph map (`supplier_invoice`, `supplier_payment`), `SystemParameterSeeder` (`purchases.invoice_match_tolerance_percent`).
- **Frontend:** `front-vue/src/pages/purchases/supplier-invoices/{index,new,[id]}.vue`, `front-vue/src/pages/purchases/accounts-payable/index.vue`, `front-vue/src/modules/purchases/{services,stores,components/payables/*}`, `components/common/PaymentMethodFields.vue` (reutilizable).

## 1. Backend — Modelo y Persistencia
- **`SupplierInvoice`** (`HasUuids`, `LogsActivity` en estado/saldo): `number`, `branch_id`, `kind`, `provider_id` (nullable en gastos), `counterparty_name`, `document_type`, `series`, `document_number`, `issue_date`, `due_date`, `currency_id`, `exchange_rate`, `prices_include_tax`, `tax_rate`, `taxable_base`, `exempt_base`, `unaffected_base`, `discount_total`, `tax_total`, `total`, `paid_amount`, `balance`, `status`, `purchase_order_id`, `concept_id`, `notes`, `attachment_path`, `created_by`, `cancelled_*`. Relaciones `items`, `installments`, `receipts` (`belongsToMany` vía `supplier_invoice_receipts` con pivot UUID), `allocations`.
- **`SupplierInvoiceItem`** (`HasUuids`): `line`, `description`, `catalog_item_id`, `item_presentation_id`, `purchase_receipt_item_id`, `quantity`, `unit_price`, `discount_percent`, `tax_type`, `line_subtotal`, `line_tax`, `line_total`.
- **`PayableInstallment`** (`HasUuids`): `supplier_invoice_id`, `provider_id`, `currency_id`, `number`, `due_date`, `amount`, `paid_amount`, `balance`, `status` (`pending`, `partially_paid`, `paid`, `cancelled`). Accessor `is_overdue`.
- **`SupplierPayment`** (`HasUuids`, `LogsActivity`): `number`, `branch_id`, `provider_id`, `counterparty_name`, `payment_date`, `currency_id`, `exchange_rate`, `payment_method_id`, `cash_session_id`, `bank_account_id` (derivado del método), `amount`, `reference`, `notes`, `status` (`completed`, `cancelled`), `created_by`, `cancelled_*`. Relaciones `allocations`, `cashMovements()`, `bankMovements()`.
- **`SupplierPaymentAllocation`** (`HasUuids`): `supplier_payment_id`, `payable_installment_id`, `supplier_invoice_id`, `amount`.

## 2. Backend — Lógica de Negocio y Servicios
### `Finance\Services\MoneySettlementService` (transversal)
- `settle(SettlementData $d): Model` — `$method = PaymentMethod::findOrFail($d->paymentMethodId)`; si `settlement = cash_box` → exige `cashSessionId`, moneda base, y llama `CashLedgerService::register(...)`; si `bank_account` → exige `method.bank_account.currency_id = $d->currencyId` y llama `BankLedgerService::register(...)` en la cuenta del método. Devuelve el movimiento.
- `unsettle(Model $source, string $sourceNumber, string $reason)` — revierte todos los movimientos de caja/banco cuyo `source` sea el documento (cada ledger valida sesión/periodo abiertos).
### `SupplierInvoiceService`
- `create(array $d, User $u)`: transacción; valida unicidad del documento del proveedor (consulta con `status <> cancelled`); valida que las recepciones sean del proveedor, `confirmed` y no facturadas (`lockForUpdate`); calcula totales con `DocumentTotalsCalculator`; `number = next('supplier_invoice', 'FP')`; genera cuotas (validando suma = total con bcmath); `kind = expense` exige `concept_id` y permite `provider_id` nulo; compara con el valor de las recepciones y agrega advertencias; adjunto al disco `local`; si `pay_now` → `SupplierPaymentService::create()` con una asignación por cuota.
- `prefill(array $receiptIds)`: líneas desde `purchase_receipt_items` con el precio de la línea de OC.
- `cancel(SupplierInvoice $i, string $reason, User $u)`: exige sin asignaciones de pagos `completed`; cuotas → `cancelled`; desvincula las recepciones (el pivot se conserva con la factura anulada; `isInvoiced()` considera solo facturas no anuladas).
### `SupplierPaymentService`
- `create(array $d, User $u)`: transacción; bloquea las cuotas (`lockForUpdate`, ordenadas por `id`); valida proveedor, moneda y saldo de cada una; `amount = Σ asignaciones`; `number = next('supplier_payment', 'PP')`; `MoneySettlementService::settle(direction: out, concept: SUPPLIER_PAYMENT, source: $payment)`; actualiza cuotas e invoices (`paid_amount`, `balance`, `status`).
- `cancel(SupplierPayment $p, string $reason, User $u)`: `MoneySettlementService::unsettle($p)`; revierte asignaciones en cuotas y facturas; `status = cancelled`.
### `AccountsPayableQueryService`
- `openInstallments(filters)`, `aging(asOf = today)` (`CASE WHEN due_date >= today THEN 'current' WHEN today - due_date <= 30 …` agrupado por proveedor y moneda), `statement(Provider, from, to)` (facturas como cargo y pagos como abono, con saldo inicial y acumulado).
### Hooks
- `PurchaseReceiptConfirmationService::isInvoiced(PurchaseReceipt $r)` → existe vínculo con factura no anulada.
- `ProviderService::hasDocuments()` → también facturas.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `StoreSupplierInvoiceRequest`: `kind` required|enum; `provider_id` required_if:kind,purchase|nullable|uuid; `counterparty_name` required_without:provider_id|max:150; `document_type` required|enum; `series` nullable|max:10; `document_number` required|max:20; `issue_date` required|date|before_or_equal:today; `due_date` nullable|date|after_or_equal:issue_date; `currency_id` required|uuid; `exchange_rate` nullable|numeric|gt:0; `prices_include_tax` boolean; `purchase_order_id` nullable|uuid; `receipt_ids` nullable|array; `receipt_ids.*` uuid|distinct; `concept_id` required_if:kind,expense|uuid; `items` required|array|min:1 (mismo formato de líneas que `StorePurchaseOrderRequest`, con `description` obligatoria); `installments` nullable|array; `installments.*.due_date` required|date; `installments.*.amount` required|numeric|gt:0; `notes` nullable|max:1000; `attachment` nullable|file|mimes:pdf,jpg,jpeg,png,xml|max:5120; `pay_now` boolean; `payment` required_if:pay_now,true|array (`payment_method_id`, `cash_session_id`, `payment_date`, `reference`).
- `StoreSupplierPaymentRequest`: `provider_id` nullable|uuid; `payment_date` required|date|before_or_equal:today; `currency_id` required|uuid; `payment_method_id` required|uuid|exists activo; `cash_session_id` nullable|uuid; `reference` nullable|max:60; `notes` nullable|max:500; `allocations` required|array|min:1; `allocations.*.installment_id` required|uuid|distinct; `allocations.*.amount` required|numeric|gt:0|decimal:0,2.
- Rutas (`v1/purchases`, `branch`): `supplier-invoices` (index, `prefill`, store, show, `{id}/cancel`, `{id}/attachment`), `supplier-payments` (index, `open-installments`, store, show, `{id}/cancel`), `accounts-payable` (index, `aging`), `providers/{id}/statement`; con los permisos de §5 y `whereUuid`.

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/purchases/supplier-invoices` | `supplier_invoices.view` | filtros `kind, provider_id, status, date_from, date_to, search` | 200 `data[]`, `meta` | 401, 403 |
| GET | `/api/v1/purchases/supplier-invoices/prefill` | `supplier_invoices.create` | query `receipt_ids[]` | 200 `data: { provider, currency, items[] }` | 401, 403, 422 |
| POST | `/api/v1/purchases/supplier-invoices` | `supplier_invoices.create` (+ `supplier_payments.create` si `pay_now`) | multipart §3 | 201 `data`, `meta.warnings?` | 401, 403, 422 |
| GET | `/api/v1/purchases/supplier-invoices/{id}` | `view` | — | 200 (con cuotas, pagos, recepciones) | 401, 403, 404 |
| GET | `/api/v1/purchases/supplier-invoices/{id}/attachment` | `view` | — | 200 archivo | 401, 403, 404 |
| POST | `/api/v1/purchases/supplier-invoices/{id}/cancel` | `supplier_invoices.cancel` | `{ reason }` | 200 | 401, 403, 404, 422 |
| GET | `/api/v1/purchases/supplier-payments/open-installments` | `supplier_payments.create` | query `provider_id, currency_id` | 200 `data: Installment[]` | 401, 403 |
| GET/POST | `/api/v1/purchases/supplier-payments` | `view` / `create` | §3 | 200 / 201 | 401, 403, 409, 422 |
| POST | `/api/v1/purchases/supplier-payments/{id}/cancel` | `supplier_payments.cancel` | `{ reason }` | 200 | 401, 403, 404, 422 |
| GET | `/api/v1/purchases/accounts-payable` | `supplier_invoices.view` | filtros §US-5 | 200 `data: Installment[]`, `meta.totals_by_currency` | 401, 403 |
| GET | `/api/v1/purchases/accounts-payable/aging` | `supplier_invoices.view` | query `as_of?` | 200 `data: [{ provider, currency, current, d1_30, d31_60, d61_90, over_90, total }]` | 401, 403 |
| GET | `/api/v1/purchases/providers/{id}/statement` | `supplier_invoices.view` | query `date_from, date_to, currency_id` | 200 `data: { opening, entries[], closing }` | 401, 403, 404 |

## 5. Permisos y seguridad
- Permisos nuevos: `supplier_invoices.view|create|cancel`, `supplier_payments.view|create|cancel`. `admin` y `manager` todos; `user` `supplier_invoices.create` y `supplier_payments.create` (gastos de caja).
- Parámetro: `purchases.invoice_match_tolerance_percent` (decimal, 1.00).

## 6. Migraciones
`2026_10_20_000005_create_accounts_payable_tables.php`:
```php
Schema::create('supplier_invoices', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('number', 20)->unique();
    $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->string('kind', 10);
    $t->foreignUuid('provider_id')->nullable()->constrained('providers')->restrictOnDelete();
    $t->string('counterparty_name', 150)->nullable();
    $t->string('document_type', 15); $t->string('series', 10)->nullable(); $t->string('document_number', 20);
    $t->date('issue_date'); $t->date('due_date');
    $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete(); $t->decimal('exchange_rate', 12, 4)->default(1);
    $t->boolean('prices_include_tax')->default(true); $t->decimal('tax_rate', 5, 2);
    $t->decimal('taxable_base', 18, 2)->default(0); $t->decimal('exempt_base', 18, 2)->default(0);
    $t->decimal('unaffected_base', 18, 2)->default(0); $t->decimal('discount_total', 18, 2)->default(0);
    $t->decimal('tax_total', 18, 2)->default(0); $t->decimal('total', 18, 2);
    $t->decimal('paid_amount', 18, 2)->default(0); $t->decimal('balance', 18, 2);
    $t->string('status', 16)->default('pending');
    $t->foreignUuid('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
    $t->foreignUuid('concept_id')->nullable()->constrained('finance_concepts')->restrictOnDelete();
    $t->string('notes', 1000)->nullable(); $t->string('attachment_path')->nullable();
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('cancelled_at')->nullable();
    $t->string('cancel_reason', 500)->nullable(); $t->timestamps();
    $t->index(['provider_id', 'status']); $t->index(['branch_id', 'issue_date']);
});
DB::statement("CREATE UNIQUE INDEX supplier_invoices_doc_unique ON supplier_invoices (provider_id, document_type, COALESCE(series, ''), document_number) WHERE status <> 'cancelled' AND provider_id IS NOT NULL");

Schema::create('supplier_invoice_items', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
    $t->unsignedSmallInteger('line'); $t->string('description', 255);
    $t->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
    $t->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
    $t->foreignUuid('purchase_receipt_item_id')->nullable()->constrained('purchase_receipt_items')->restrictOnDelete();
    $t->decimal('quantity', 18, 4); $t->decimal('unit_price', 18, 4); $t->decimal('discount_percent', 5, 2)->default(0);
    $t->string('tax_type', 20)->default('taxable');
    $t->decimal('line_subtotal', 18, 2); $t->decimal('line_tax', 18, 2); $t->decimal('line_total', 18, 2); $t->timestamps();
});

Schema::create('supplier_invoice_receipts', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
    $t->foreignUuid('purchase_receipt_id')->constrained('purchase_receipts')->restrictOnDelete();
    $t->timestamps(); $t->unique(['supplier_invoice_id', 'purchase_receipt_id']);
});

Schema::create('payable_installments', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
    $t->foreignUuid('provider_id')->nullable()->constrained('providers')->restrictOnDelete();
    $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
    $t->unsignedSmallInteger('number'); $t->date('due_date');
    $t->decimal('amount', 18, 2); $t->decimal('paid_amount', 18, 2)->default(0); $t->decimal('balance', 18, 2);
    $t->string('status', 16)->default('pending'); $t->timestamps();
    $t->index(['provider_id', 'status', 'due_date']);
});

Schema::create('supplier_payments', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('number', 20)->unique();
    $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->foreignUuid('provider_id')->nullable()->constrained('providers')->restrictOnDelete();
    $t->string('counterparty_name', 150)->nullable(); $t->date('payment_date');
    $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete(); $t->decimal('exchange_rate', 12, 4)->default(1);
    $t->foreignUuid('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
    $t->foreignUuid('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
    $t->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
    $t->decimal('amount', 18, 2); $t->string('reference', 60)->nullable(); $t->string('notes', 500)->nullable();
    $t->string('status', 12)->default('completed');
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('cancelled_at')->nullable();
    $t->string('cancel_reason', 500)->nullable(); $t->timestamps();
    $t->index(['provider_id', 'payment_date']);
});

Schema::create('supplier_payment_allocations', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('supplier_payment_id')->constrained('supplier_payments')->cascadeOnDelete();
    $t->foreignUuid('payable_installment_id')->constrained('payable_installments')->restrictOnDelete();
    $t->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->restrictOnDelete();
    $t->decimal('amount', 18, 2); $t->timestamps();
});
```

## 7. Configuración e infraestructura
- Parámetro `purchases.invoice_match_tolerance_percent`. Adjuntos en `local` (`supplier-invoices/`).
- Morph map: `supplier_invoice`, `supplier_payment`.

## 8. Frontend
- **Facturas** `pages/purchases/supplier-invoices/index.vue` (`supplier_invoices.view`): filtros por tipo (compra/gasto), proveedor, estado y fechas; columnas documento del proveedor, número interno, emisión, vencimiento, total, saldo, estado (chip rojo si vencida).
- **Formulario** `new.vue`: selector Compra/Gasto; en compra, `ProviderPicker` + "Cargar desde recepciones" (diálogo con las recepciones confirmadas no facturadas → `prefill`); en gasto, proveedor opcional o nombre libre + concepto. `DocumentLinesTable` con texto libre permitido, panel de cuotas (una por defecto, "Dividir en N cuotas"), adjunto y sección "Pagar ahora" con `PaymentMethodFields.vue`. Muestra `meta.warnings` en un `VAlert`.
- **`components/common/PaymentMethodFields.vue`** (reutilizable en 027, 028 y 029): método de pago (opciones activas), referencia si se requiere y, si el método es de caja, la sesión actual (`useCashSession()`), con aviso si no hay caja abierta.
- **Cuentas por pagar** `pages/purchases/accounts-payable/index.vue`: pestañas Cuotas por pagar (selección múltiple de un mismo proveedor y moneda → "Pagar seleccionadas" abre `SupplierPaymentDialog.vue` con montos editables por cuota) · Antigüedad (tabla por proveedor con buckets) · Pagos (historial con anulación). Enlace "Estado de cuenta" por proveedor.
- **Caja:** botón "Registrar gasto" en `pages/finance/cash/index.vue` que abre el formulario de gasto en un diálogo con `pay_now` y la sesión actual.
- **Menú:** "Compras" › "Facturas de proveedor" (`supplier_invoices.view`), "Cuentas por pagar" (`supplier_invoices.view`).

## 9. Testing
- `tests/Feature/Modules/Purchases/SupplierInvoicesApiTest.php`: factura con recepciones (prefill, advertencia por diferencia); documento duplicado → 422 (permitido si el anterior está anulado); recepción ya facturada → 422; cuotas que no suman → 422; gasto sin proveedor con `pay_now` en caja (movimiento de caja creado); anular con pagos → 422; anular libera recepciones (y la recepción ya puede anularse).
- `SupplierPaymentsApiTest.php`: pago parcial y total a varias cuotas (saldos y estados); exceder el saldo → 422; moneda distinta → 422; caja sin sesión → 422; método bancario crea un movimiento bancario en la cuenta del método; anular revierte el ledger y restaura saldos; anular con sesión cerrada → 422.
- `AccountsPayableApiTest.php`: listado con vencidas, aging por buckets y por moneda, estado de cuenta con saldo acumulado.
- `tests/Feature/Modules/Finance/MoneySettlementServiceTest.php`: caja vs banco, validaciones de moneda y sesión, `unsettle`.
- Ejecutar: `make test-filter FILTER=Supplier`, `FILTER=AccountsPayable`, `FILTER=MoneySettlement`.

## 10. Decisiones Rechazadas
- **`expenses` como pago y gasto a la vez (Multinort):** mezcla documento y pago; se separan factura (deuda) y pago (salida de dinero).
- **`paid`/`pending` sin cuotas:** no permite cronogramas de pago ni aging por vencimiento.
- **Beneficiario polimórfico (`beneficiary_type`):** reemplazado por proveedor o `counterparty_name`.
- **Decidir caja o banco en cada módulo:** centralizado en `MoneySettlementService`.
