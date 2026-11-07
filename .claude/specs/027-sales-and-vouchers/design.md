# 027 · sales-and-vouchers — Design
> **Spec:** 027 · **Alcance:** fullstack · **Módulo:** Sales · **Prioridad:** media · **Depende de specs:** 004, 011, 015, 018, 020, 024, 025, 026
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
  - `Models/{DocumentSeries,Sale,SaleItem,SalePayment}.php`
  - `Enums/{SalesDocumentType,SaleStatus,EInvoiceStatus,PaymentCondition}.php` (`PaymentCondition` se reutiliza de `Purchases` si es idéntico; si no, se mueve a `app/Support/Commerce/PaymentCondition.php`)
  - `Services/{DocumentSeriesService,SaleService,SaleIssuanceService,SaleCancellationService,CreditPolicyService,SaleQueryService}.php`
  - `Events/SaleIssued.php`, `Events/SaleCancelled.php`, `Contracts/AfterSaleIssuedHook.php`
  - `EInvoicing/{ElectronicInvoicingDriver.php (interface), EInvoiceResult.php, Drivers/NullDriver.php, EInvoicingManager.php}`, `Jobs/SendElectronicDocumentJob.php`
  - `Controllers/{DocumentSeriesController,SaleController}.php`
  - `Requests/{DocumentSeriesRequest,StoreSaleRequest,ListSalesRequest}.php` (+ `CancelDocumentRequest`)
  - `Resources/{DocumentSeriesResource,SaleResource,SaleItemResource,SalePaymentResource}.php`
  - Vistas `resources/views/pdf/sales/{voucher-a4,voucher-ticket}.blade.php`
- **Transversal nuevo:** `app/Support/Text/NumberToWords.php` (montos en letras en español, `toInvoiceText(string $amount, Currency $c): string`).
- **Configuración nueva:** `config/einvoicing.php`.
- **Modificados:** `BranchService::delete()` (series), `ClientService::hasDocuments()` y `account.debt` del `ClientResource` (con `CreditPolicyService::debt()`), `EmployeeService::delete()` (vendedor), `QuotationWorkflowService::markConverted()` (uso), `CatalogItemService::delete()`, morph map (`sale`, `sale_payment`), `SystemParameterSeeder`.
- **Frontend:** `front-vue/src/pages/sales/pos/index.vue`, `front-vue/src/pages/sales/history/{index,[id]}.vue`, `front-vue/src/pages/sales/document-series/index.vue`, `front-vue/src/modules/sales/{services/salesApi.js, services/documentSeriesApi.js, stores/posStore.js, stores/salesStore.js, components/pos/*}`.

## 1. Backend — Modelo y Persistencia
- **`DocumentSeries`** (`HasUuids`, `SoftDeletes`, `LogsActivity`): `branch_id`, `box_id`, `document_type`, `series`, `last_number`, `is_electronic`, `is_active`. Método `format(int $n): string` → `"{$series}-".str_pad($n, 8, '0', STR_PAD_LEFT)`.
- **`Sale`** (`HasUuids`, `LogsActivity` en `status`/`einvoice_status`): `branch_id`, `warehouse_id`, `cash_session_id`, `document_series_id`, `document_type`, `series`, `number`, `full_number`, `issue_date`, `issued_at`, `due_date`, `client_id`, `client_document_type`, `client_document_number`, `client_name`, `client_address`, `seller_id`, `quotation_id`, `currency_id`, `exchange_rate`, `price_type`, `prices_include_tax`, `tax_rate`, `global_discount_percent`, `taxable_base`, `exempt_base`, `unaffected_base`, `discount_total`, `tax_total`, `total`, `total_base_currency`, `cost_total`, `payment_condition`, `paid_amount`, `balance`, `status` (`issued`, `cancelled`), `einvoice_status`, `einvoice_attempts`, `einvoice_response` (json), `einvoice_sent_at`, `discount_override_by`, `notes`, `created_by`, `cancelled_*`. Relaciones `items`, `payments`, `client`, `seller`, `series()`, `kardexMovements()`.
- **`SaleItem`** (`HasUuids`): `sale_id`, `line`, `catalog_item_id`, `item_presentation_id`, `description`, `unit_sunat_code`, `quantity`, `base_quantity`, `list_price`, `unit_price`, `discount_percent`, `effective_discount_percent`, `tax_type`, `line_subtotal`, `line_tax`, `line_total`, `unit_cost`, `cost_total`.
- **`SalePayment`** (`HasUuids`): `sale_id`, `payment_method_id`, `cash_session_id`, `amount`, `received_amount`, `change_amount`, `reference`, `status` (`completed`, `cancelled`). Relaciones a los movimientos de caja/banco por `source`.

## 2. Backend — Lógica de Negocio y Servicios
### `DocumentSeriesService`
- CRUD con validación de formato por tipo; `available(Branch, ?Box, type)`.
- `next(DocumentSeries $s): array{number:int, full:string}` — exige transacción; `DocumentSeries::whereKey($s->id)->lockForUpdate()->first()`; `last_number + 1`; guarda; devuelve.
### `CreditPolicyService`
- `debt(Client $c): string` → `SUM(balance)` de ventas `issued` del cliente (el spec 029 descuenta notas de crédito aplicadas al balance, por lo que la fórmula no cambia).
- `assertAvailable(Client $c, string $amount)` → 422 si `amount > credit_limit − debt` o si el cliente es de sistema o sin crédito.
### `SaleIssuanceService::issue(array $d, User $u): Sale` (transacción única)
1. Valida sede activa, sesión de caja (abierta, sede activa, `BoxAccessService::canOperate`), serie activa de la sede y aplicable a la caja, almacén de la sede.
2. Valida reglas del comprobante: factura ⇒ RUC; boleta ⇒ identificación si `total_base ≥ sales.receipt_identity_threshold`; nota de venta sin restricción.
3. `issue_date`: hoy, o anterior con `sales.backdate` (≤ `sales.max_backdate_days`).
4. Líneas: `SalePricingService::listPrice()`, `DiscountPolicyService::validate()`, `DocumentTotalsCalculator`.
5. Pagos: `Σ amount = total` (contado) o `≤ total` y `CreditPolicyService::assertAvailable(balance)` (crédito, con `sales.sell_on_credit`).
6. Correlativo: `DocumentSeriesService::next()`.
7. Crea `Sale` (con cliente congelado) e ítems.
8. Stock: `StockLedgerService::applyBatch()` con `type = sale`, `reasonCode = SALE`, `source = $sale`; copia `unit_cost`/`cost_total` del kardex en cada línea; `cost_total` de la venta.
9. Pagos: por cada uno crea `SalePayment` y llama `MoneySettlementService::settle(direction: in, concept: SALE_COLLECTION, source: $salePayment, sourceNumber: $sale->full_number, cashSessionId)`.
10. `paid_amount`, `balance`, `due_date` (crédito); si `quotation_id` → `QuotationWorkflowService::markConverted()`; ejecuta `afterIssue($sale, $request)`: recorre los servicios etiquetados `sales.after_issue` que implementan `Sales\Contracts\AfterSaleIssuedHook` (`handle(Sale $sale, array $input): void`), dentro de la misma transacción. El spec 028 registra ahí la generación de cuotas.
11. `einvoice_status = series.is_electronic ? pending : not_applicable`.
12. `DB::afterCommit` → `event(new SaleIssued($sale))` y, si `pending`, `SendElectronicDocumentJob::dispatch($sale->id)->afterCommit()`.
### `SaleCancellationService::cancel(Sale $s, string $reason, User $u)`
- `lockForUpdate`; exige `issued`; rechaza si `einvoice_status = accepted`, si hay cobranzas posteriores (hook `hasCollections()` que implementa el spec 028; aquí `false`) o notas de crédito (hook del spec 029); revierte el kardex (`reverse` de cada movimiento) y cada `SalePayment` con `MoneySettlementService::unsettle()` (falla si la sesión cerró); `status = cancelled`; `event(new SaleCancelled)`; si venía de cotización, la cotización vuelve a `accepted`.
### E-invoicing
- `interface ElectronicInvoicingDriver { public function send(Model $document): EInvoiceResult; }` (`Model` = `Sale` o `CreditNote` del spec 029).
- `EInvoiceResult` (`readonly`): `status` (`sent`|`accepted`|`rejected`|`error`), `code`, `message`, `hash`, `raw` (array).
- Los drivers concretos (spec futuro, uno por proveedor) implementan `send()` llamando a la API REST del PSE/OSE con `Http::` (timeouts y reintentos configurables en `config/einvoicing.php` → `drivers.<proveedor>.{base_url, token, timeout}`), mapean el documento a su JSON y traducen la respuesta (aceptado/rechazado, hash, enlaces a XML/CDR/PDF del proveedor) a `EInvoiceResult`. El sistema no genera XML UBL ni firma digital.
- `NullDriver::send()` → `EInvoiceResult(status: 'not_applicable')`.
- `EInvoicingManager` resuelve el driver desde `config('einvoicing.driver')` (registro en el provider: `null` → `NullDriver`; otros drivers se registran en specs futuros).
- `SendElectronicDocumentJob` (cola `default`, `tries = 1`, `ShouldBeUnique` por id): incrementa `einvoice_attempts`, llama al driver y guarda el resultado; captura excepciones → `error` con el mensaje. El reintento manual vuelve a encolar si `attempts < 5`.
### `NumberToWords`
- Convierte la parte entera en letras en español (hasta 999 999 999 999) y agrega `CON NN/100 {NOMBRE MONEDA EN PLURAL}` (`SOLES`, `DÓLARES AMERICANOS`).

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `DocumentSeriesRequest`: `branch_id` required|uuid accesible; `box_id` nullable|uuid de la misma sede; `document_type` required|enum; `series` required|size:4|regex según el tipo (`withValidator`)|único por tipo; `last_number` integer|min:0; `is_electronic`, `is_active` boolean.
- `StoreSaleRequest`: `document_series_id` required|uuid; `cash_session_id` required_if:payments not empty|uuid; `warehouse_id` nullable|uuid; `client_id` required|uuid; `seller_id` nullable|uuid; `issue_date` nullable|date; `currency_id` required|uuid; `price_type` nullable|in:retail,wholesale; `global_discount_percent` nullable|numeric|between:0,100; `quotation_id` nullable|uuid; `payment_condition` required|in:cash,credit; `notes` nullable|max:500; `items` (como `StoreQuotationRequest`, sin texto libre para productos con stock); `payments` array (`required` si contado); `payments.*.payment_method_id` required|uuid; `payments.*.amount` required|numeric|gt:0|decimal:0,2; `payments.*.received_amount` nullable|numeric|gte:payments.*.amount; `payments.*.reference` nullable|max:60.
- `SaleResource` oculta `unit_cost`, `cost_total` y márgenes sin `sales.view_costs`.
- Rutas (`v1/sales`, `branch`):
```php
// Ventas y comprobantes (Spec 027)
Route::get('/document-series/available', [DocumentSeriesController::class, 'available'])->middleware('permission:sales.create');
Route::get('/document-series', [DocumentSeriesController::class, 'index'])->middleware('permission:document_series.view');
Route::post('/document-series', [DocumentSeriesController::class, 'store'])->middleware('permission:document_series.manage');
Route::match(['put', 'patch'], '/document-series/{id}', [DocumentSeriesController::class, 'update'])->whereUuid('id')->middleware('permission:document_series.manage');
Route::delete('/document-series/{id}', [DocumentSeriesController::class, 'destroy'])->whereUuid('id')->middleware('permission:document_series.manage');

Route::get('/sales/prefill', [SaleController::class, 'prefill'])->middleware('permission:sales.create');
Route::get('/sales', [SaleController::class, 'index'])->middleware('permission:sales.view');
Route::post('/sales', [SaleController::class, 'store'])->middleware('permission:sales.create');
Route::get('/sales/{id}', [SaleController::class, 'show'])->whereUuid('id')->middleware('permission:sales.view');
Route::get('/sales/{id}/pdf', [SaleController::class, 'pdf'])->whereUuid('id')->middleware('permission:sales.view');
Route::post('/sales/{id}/cancel', [SaleController::class, 'cancel'])->whereUuid('id')->middleware('permission:sales.cancel');
Route::post('/sales/{id}/einvoice/retry', [SaleController::class, 'retryEInvoice'])->whereUuid('id')->middleware('permission:document_series.manage');
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| CRUD | `/api/v1/sales/document-series[/{id}]` | `document_series.view|manage` | §3 | 200/201/204 | 401, 403, 404, 422 |
| GET | `/api/v1/sales/document-series/available` | `sales.create` | query `document_type, box_id?` | 200 `data[]` | 401, 403 |
| GET | `/api/v1/sales/sales/prefill` | `sales.create` | query `quotation_id` | 200 `data: { client, seller, items[], global_discount_percent }` | 401, 403, 404, 422 |
| POST | `/api/v1/sales/sales` | `sales.create` (+ `sell_on_credit`, `discount_override`, `backdate` según el caso) | §3 | 201 `data: Sale` (`full_number`, `payments`, `change`) | 401, 403, 409, 422 |
| GET | `/api/v1/sales/sales` | `sales.view` | filtros §US-8 | 200 `data[]`, `meta.totals` | 401, 403 |
| GET | `/api/v1/sales/sales/{id}` | `sales.view` | — | 200 | 401, 403, 404 |
| GET | `/api/v1/sales/sales/{id}/pdf` | `sales.view` | query `format=ticket|a4`, `inline?` | 200 PDF | 401, 403, 404 |
| POST | `/api/v1/sales/sales/{id}/cancel` | `sales.cancel` | `{ reason }` | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/sales/sales/{id}/einvoice/retry` | `document_series.manage` | — | 202 | 401, 403, 404, 422 |

Ejemplo `POST /sales/sales`:
```json
{ "document_series_id": "…", "cash_session_id": "…", "client_id": "…", "currency_id": "…", "payment_condition": "cash",
  "items": [ { "item_presentation_id": "…", "quantity": 2, "discount_percent": 5 } ],
  "payments": [ { "payment_method_id": "<CASH>", "amount": 80.00, "received_amount": 100.00 }, { "payment_method_id": "<YAPE>", "amount": 38.00, "reference": "123456" } ] }
```

## 5. Permisos y seguridad
- Permisos nuevos: `sales.view|view_all|view_costs|create|cancel|sell_on_credit|backdate`, `document_series.view|manage`. `admin` todos; `manager` todos; `user` `sales.view|create`.
- Parámetros nuevos: `sales.receipt_identity_threshold` (decimal, 700.00), `sales.max_backdate_days` (integer, 3).
- El driver de facturación electrónica nunca recibe credenciales desde la API; vienen de `.env`.

## 6. Migraciones
`2026_10_20_000003_create_sales_tables.php`:
```php
Schema::create('document_series', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->foreignUuid('box_id')->nullable()->constrained('boxes')->restrictOnDelete();
    $t->string('document_type', 15); $t->char('series', 4); $t->unsignedInteger('last_number')->default(0);
    $t->boolean('is_electronic')->default(false); $t->boolean('is_active')->default(true);
    $t->timestamps(); $t->softDeletes();
});
SoftDeleteUniqueIndex::make('document_series', ['document_type', 'series']);

Schema::create('sales', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $t->foreignUuid('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
    $t->foreignUuid('document_series_id')->constrained('document_series')->restrictOnDelete();
    $t->string('document_type', 15); $t->char('series', 4); $t->unsignedInteger('number'); $t->string('full_number', 13);
    $t->date('issue_date'); $t->timestamp('issued_at'); $t->date('due_date')->nullable();
    $t->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
    $t->string('client_document_type', 10)->nullable(); $t->string('client_document_number', 20)->nullable();
    $t->string('client_name', 200); $t->string('client_address')->nullable();
    $t->foreignUuid('seller_id')->nullable()->constrained('employees')->restrictOnDelete();
    $t->foreignUuid('quotation_id')->nullable()->constrained('quotations')->restrictOnDelete();
    $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete(); $t->decimal('exchange_rate', 12, 4)->default(1);
    $t->string('price_type', 10)->default('retail'); $t->boolean('prices_include_tax')->default(true); $t->decimal('tax_rate', 5, 2);
    $t->decimal('global_discount_percent', 5, 2)->default(0);
    $t->decimal('taxable_base', 18, 2)->default(0); $t->decimal('exempt_base', 18, 2)->default(0);
    $t->decimal('unaffected_base', 18, 2)->default(0); $t->decimal('discount_total', 18, 2)->default(0);
    $t->decimal('tax_total', 18, 2)->default(0); $t->decimal('total', 18, 2); $t->decimal('total_base_currency', 18, 2);
    $t->decimal('cost_total', 18, 4)->default(0);
    $t->string('payment_condition', 10); $t->decimal('paid_amount', 18, 2)->default(0); $t->decimal('balance', 18, 2)->default(0);
    $t->string('status', 10)->default('issued');
    $t->string('einvoice_status', 16)->default('not_applicable'); $t->unsignedTinyInteger('einvoice_attempts')->default(0);
    $t->json('einvoice_response')->nullable(); $t->timestamp('einvoice_sent_at')->nullable();
    $t->foreignUuid('discount_override_by')->nullable()->constrained('users')->nullOnDelete();
    $t->string('notes', 500)->nullable();
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('cancelled_at')->nullable();
    $t->string('cancel_reason', 500)->nullable(); $t->timestamps();
    $t->unique(['document_series_id', 'number']);
    $t->index(['branch_id', 'issue_date', 'status']); $t->index(['client_id', 'status']); $t->index('cash_session_id');
});

Schema::create('sale_items', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
    $t->unsignedSmallInteger('line');
    $t->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
    $t->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
    $t->string('description', 255); $t->string('unit_sunat_code', 5)->nullable();
    $t->decimal('quantity', 18, 4); $t->decimal('base_quantity', 18, 4)->nullable();
    $t->decimal('list_price', 18, 4)->nullable(); $t->decimal('unit_price', 18, 4);
    $t->decimal('discount_percent', 5, 2)->default(0); $t->decimal('effective_discount_percent', 5, 2)->default(0);
    $t->string('tax_type', 20)->default('taxable');
    $t->decimal('line_subtotal', 18, 2); $t->decimal('line_tax', 18, 2); $t->decimal('line_total', 18, 2);
    $t->decimal('unit_cost', 18, 6)->default(0); $t->decimal('cost_total', 18, 4)->default(0);
    $t->timestamps();
});

Schema::create('sale_payments', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
    $t->foreignUuid('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
    $t->foreignUuid('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
    $t->decimal('amount', 18, 2); $t->decimal('received_amount', 18, 2)->nullable(); $t->decimal('change_amount', 18, 2)->default(0);
    $t->string('reference', 60)->nullable(); $t->string('status', 12)->default('completed'); $t->timestamps();
});
Schema::table('quotations', fn (Blueprint $t) => $t->foreign('converted_sale_id')->references('id')->on('sales')->nullOnDelete());
```

## 7. Configuración e infraestructura
- `config/einvoicing.php`: `'driver' => env('EINVOICE_DRIVER', 'null')`, `'drivers' => ['null' => [...]]`; `.env.example`: `EINVOICE_DRIVER=null`.
- Parámetros en `SystemParameterSeeder` (§5).
- Seeder de dev: series `F001`, `B001`, `NV01`, `FC01`, `BC01` en la sede principal.
- `routes/console.php` no cambia (los reintentos son manuales en esta entrega).

## 8. Frontend
- **POS** `pages/sales/pos/index.vue` (`sales.create`), en layout de pantalla completa:
  - Barra superior: caja y sesión (`useCashSession()`; si no hay, botón "Abrir caja"), `VBtnToggle` Boleta · Factura · Nota de venta con la serie (`/document-series/available`), `ClientPicker` (genérico por defecto, `requireRuc` en factura), vendedor.
  - Izquierda: búsqueda (`CatalogItemPicker` `context=sale` con `warehouseId`, atajo F2, Enter con código de barras).
  - Derecha: carrito (`PosCart.vue`: cantidad, precio, descuento con tope, quitar), totales y "Cobrar (F9)".
  - `PosPaymentDialog.vue`: contado/crédito (con `can('sales.sell_on_credit')`), varios pagos con `PaymentMethodFields` (efectivo con monto recibido y vuelto), validación de Σ = total.
  - Tras emitir: `VoucherPreviewDialog.vue` con el PDF ticket (`?format=ticket&inline=1`) y "Nueva venta".
  - `posStore.js` (Pinia) mantiene el carrito y lo persiste en `sessionStorage` para no perderlo al recargar.
- **Historial** `pages/sales/history/index.vue` (`sales.view`): filtros, totales, chips de estado y de estado electrónico; detalle `[id].vue` con líneas (costo y margen con `can('sales.view_costs')`), pagos, kardex, PDF A4/ticket, Anular (`CancelDocumentDialog`) y Reintentar envío.
- **Series** `pages/sales/document-series/index.vue` (`document_series.view`).
- **Cotización → venta:** botón "Convertir en venta" en la cotización (spec 026) que navega a `/sales/pos?quotation_id=`.
- **Cliente:** pestaña "Ventas" en `pages/clients/[id].vue`.
- **Menú:** "Ventas" › "Punto de venta" (`sales.create`), "Ventas" (`sales.view`), "Series" (`document_series.view`).

## 9. Testing
- `tests/Unit/Support/NumberToWordsTest.php`: 0, 1, 15, 100, 101, 1 000, 1 000 000, 120.50 → "CIENTO VEINTE CON 50/100 SOLES".
- `tests/Feature/Modules/Sales/DocumentSeriesApiTest.php`: formatos por tipo; serie duplicada → 422; `last_number` bloqueado con documentos; `available` por caja.
- `tests/Feature/Modules/Sales/SalesApiTest.php`: boleta contado con dos pagos (caja y banco), stock descontado y costo guardado, correlativo `B001-00000001` y luego `…02`; factura sin RUC → 422; boleta ≥ 700 con cliente genérico → 422; pagos que no suman → 422; stock insuficiente → 422 sin consumir correlativo; descuento sobre el tope → 422; crédito sin permiso → 403/422; crédito excediendo la línea → 422; crédito con adelanto (balance correcto, `SaleIssued` despachado con `Event::fake`); desde cotización (queda `converted`); sesión cerrada → 422; serie de otra sede → 422; `meta.totals`; costos ocultos sin `view_costs`.
- `tests/Feature/Modules/Sales/SaleCancellationApiTest.php`: anula y revierte stock y caja; sesión cerrada → 422; `einvoice_status = accepted` → 422; cotización vuelve a `accepted`.
- `tests/Feature/Modules/Sales/SendElectronicDocumentJobTest.php`: driver nulo → `not_applicable`; driver falso (binding en el test) → `accepted` y `error`; reintento con límite.
- Al final de las clases: `inventory:verify-stock` sin diferencias.
- Ejecutar: `make test-filter FILTER=Sale` y `make test-filter FILTER=NumberToWords`.

## 10. Decisiones Rechazadas
- **`vouchers` como registro tributario separado (Multinort):** duplicaba bases y totales; la venta contiene los datos del comprobante y el driver los envía en el formato de la API del proveedor.
- **Un solo método de pago por venta:** los pagos mixtos son habituales en mostrador.
- **Correlativo por `MAX(number)+1`:** propenso a duplicados; se usa `last_number` con bloqueo.
- **Enviar a SUNAT de forma síncrona en la emisión:** una caída del PSE bloquearía la caja; se usa una cola.
- **Generar y firmar el XML UBL 2.1 en el sistema (envío directo a SUNAT):** exige certificado digital, mantenimiento de esquemas y homologación; se delega en la API del proveedor PSE/OSE.
- **Venta vinculada a orden de servicio:** rubro automotriz.
