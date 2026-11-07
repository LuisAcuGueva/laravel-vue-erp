# 022 · purchase-requests-and-orders — Design
> **Spec:** 022 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 010, 011, 014, 015, 021
> **Status:** Done
> **Última actualización:** 2026-10-19
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Transversal nuevo (reutilizado por los specs 023, 024, 026, 027 y 029):**
  - `app/Support/Pricing/DocumentTotalsCalculator.php` + `app/Support/Pricing/LineInput.php` / `LineResult.php` / `DocumentTotals.php` (DTOs `readonly`).
  - `app/Support/Pdf/PdfRenderer.php` (envoltura de `barryvdh/laravel-dompdf`) y vistas `resources/views/pdf/layouts/document.blade.php` (cabecera con logo y datos de empresa desde `CompanySettingService`, pie y marca de agua opcional) y `resources/views/pdf/partials/{totals,items}.blade.php`.
- **Backend** (módulo `Purchases`):
  - `Models/{PurchaseRequest,PurchaseRequestItem,PurchaseOrder,PurchaseOrderItem}.php`
  - `Enums/{PurchaseRequestStatus,PurchaseOrderStatus,PaymentCondition,Priority}.php` (con `canTransitionTo()`)
  - `Services/{PurchaseRequestService,PurchaseOrderService,PurchaseOrderWorkflowService}.php`
  - `Controllers/{PurchaseRequestController,PurchaseOrderController}.php`
  - `Requests/{StorePurchaseRequestRequest,ListPurchaseRequestsRequest,LowStockRequestRequest,StorePurchaseOrderRequest,ListPurchaseOrdersRequest}.php` (+ `CancelDocumentRequest`)
  - `Resources/{PurchaseRequestResource,PurchaseRequestItemResource,PurchaseOrderResource,PurchaseOrderItemResource}.php`
  - `resources/views/pdf/purchases/order.blade.php`
- **Frontend:** `front-vue/src/pages/purchases/{requests,orders}/{index,new,[id]}.vue`, `front-vue/src/modules/purchases/{services,stores,components/requests,components/orders}/`, `components/common/DocumentLinesTable.vue` (grilla de líneas reutilizable con totales).

## 1. Backend — Modelo y Persistencia
- **`PurchaseRequest`** (`HasUuids`, `LogsActivity` en `status`): `number`, `branch_id`, `area_id`, `warehouse_id`, `requested_by`, `required_date`, `priority`, `status` (`draft`, `submitted`, `approved`, `rejected`, `partially_ordered`, `ordered`, `cancelled`), `notes`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `rejection_reason`, `cancelled_*`.
- **`PurchaseRequestItem`** (`HasUuids`): `purchase_request_id`, `line`, `catalog_item_id` (nullable), `item_presentation_id` (nullable), `description`, `quantity`, `ordered_quantity`, `notes`. Accessor `pending_quantity`.
- **`PurchaseOrder`** (`HasUuids`, `LogsActivity` en `status`/totales): `number`, `branch_id`, `provider_id`, `warehouse_id`, `order_date`, `expected_date`, `currency_id`, `exchange_rate`, `payment_condition`, `credit_days`, `prices_include_tax`, `tax_rate`, `taxable_base`, `exempt_base`, `unaffected_base`, `discount_total`, `tax_total`, `total`, `total_base_currency`, `status`, `notes`, `terms`, `created_by`, `approved_by`, `approved_at`, `rejected_*`, `closed_by`, `closed_at`, `cancelled_*`.
- **`PurchaseOrderItem`** (`HasUuids`): `purchase_order_id`, `line`, `purchase_request_item_id`, `catalog_item_id`, `item_presentation_id`, `description`, `is_service`, `quantity`, `received_quantity`, `unit_price`, `discount_percent`, `tax_type`, `line_subtotal`, `line_tax`, `line_total`.

## 2. Backend — Lógica de Negocio y Servicios
### `DocumentTotalsCalculator` (transversal, puro, sin BD)
- `calculate(array $lines, string $taxRate, bool $pricesIncludeTax, string $globalDiscount = '0', string $globalDiscountType = 'amount'): DocumentTotals`.
- Por línea (`LineInput`: `quantity`, `unitPrice`, `discountPercent|discountAmount`, `taxType`): `gross = qty × price`; `discount`; `net = gross − discount`; si `pricesIncludeTax` y `taxable` → `subtotal = net / (1 + rate)`, `tax = net − subtotal`; si no → `subtotal = net`, `tax = net × rate` (solo `taxable`). `exempt`/`unaffected` → `tax = 0`.
- El descuento global se prorratea por bases antes del impuesto.
- Todo en `bcmath` con escala 6 y redondeo `ROUND_HALF_UP` a 2 decimales en totales de línea y documento; la suma de líneas cuadra con el total (ajuste de redondeo en la última línea).
### `PdfRenderer`
- `render(string $view, array $data, array $options = ['paper' => 'a4']): Response` (`download`/`stream` según `?inline=1`); inyecta `company` (de `CompanySettingService::current()`) y `logo_data_uri` (base64 del logo del disco `public`).
### `PurchaseRequestService`
- `create/update` (solo `draft`, solo el creador), `submit`, `approve`, `reject`, `cancel` (sin OC), `fromLowStock(warehouseId, areaId, ?categoryId)` usando `StockQueryService` con `below_min`.
- `refreshOrderedQuantities(array $requestItemIds)`: recalcula `ordered_quantity` desde líneas de OC no `cancelled`/`rejected` y ajusta el estado del requerimiento (`approved` / `partially_ordered` / `ordered`). La llaman `PurchaseOrderService` y `PurchaseOrderWorkflowService`.
- `pendingItems(warehouseId)`.
### `PurchaseOrderService`
- `create/update(array $d, User $u)`: transacción; valida proveedor activo, almacén de la sede activa, ítems `is_purchasable`, líneas con descripción solo si `is_service`; `exchange_rate` por defecto con `ExchangeRateService`; `tax_rate` de `company_settings.default_tax_rate`; calcula con `DocumentTotalsCalculator`; `total_base_currency = total × exchange_rate`; valida y bloquea (`lockForUpdate`) las líneas de requerimiento vinculadas y que no se exceda el pendiente; `number = SequentialCodeGenerator::next('purchase_order', 'OC')`; llama `refreshOrderedQuantities`.
### `PurchaseOrderWorkflowService`
- `submit` (umbral `SystemParameterService::decimal('purchases.order_approval_threshold')`), `approve`, `reject`, `close`, `cancel`; todos con `lockForUpdate` y `canTransitionTo()`. `cancel` exige Σ `received_quantity = 0`. `reject`/`cancel` → `refreshOrderedQuantities`.
- `markReceived(PurchaseOrder $o)` (lo usa el spec 023): fija `partially_received`/`received` según las cantidades.
### Hooks
- `ProviderService::hasDocuments()` → existe alguna `PurchaseOrder`; `CatalogItemService::delete()` → bloquea si hay líneas de OC; `EmployeeService` no aplica.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `StorePurchaseRequestRequest`: `area_id` required|uuid|exists activo; `warehouse_id` required|uuid; `required_date` required|date|after_or_equal:today; `priority` required|enum; `notes` nullable|max:1000; `items` required|array|min:1|max:200; `items.*.item_presentation_id` nullable|uuid|exists|required_without:items.*.description; `items.*.description` nullable|max:255; `items.*.quantity` required|numeric|gt:0; `items.*.notes` nullable|max:255.
- `StorePurchaseOrderRequest`: `provider_id` required|uuid; `warehouse_id` required|uuid; `order_date` required|date; `expected_date` nullable|date|after_or_equal:order_date; `currency_id` required|uuid; `exchange_rate` nullable|numeric|gt:0|decimal:0,4; `payment_condition` required|enum; `credit_days` required_if:payment_condition,credit|integer|between:1,365; `prices_include_tax` boolean; `notes`, `terms` nullable|max:2000; `items` required|array|min:1|max:300; `items.*.item_presentation_id` nullable|uuid; `items.*.description` required_without:items.*.item_presentation_id|max:255; `items.*.quantity` required|numeric|gt:0|decimal:0,4; `items.*.unit_price` required|numeric|min:0|decimal:0,4; `items.*.discount_percent` nullable|numeric|between:0,100; `items.*.tax_type` nullable|enum `TaxType`; `items.*.purchase_request_item_id` nullable|uuid.
- Rutas (`v1/purchases`, `branch`): `requests` (index, store, show, update, `pending-items`, `from-low-stock`, `{id}/submit|approve|reject|cancel`) y `orders` (index, store, show, update, `{id}/submit|approve|reject|close|cancel`, `{id}/pdf`), cada una con su `permission:` (§5) y `whereUuid`. Las rutas estáticas (`pending-items`, `from-low-stock`) se declaran antes de `/{id}`.

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET/POST | `/api/v1/purchases/requests` | `branch`, `purchase_requests.view` / `.create` | §3 | 200 / 201 | 401, 403, 422 |
| GET/PUT | `/api/v1/purchases/requests/{id}` | `view` / `create` (creador, `draft`) | §3 | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/purchases/requests/{id}/submit` | `purchase_requests.create` | — | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/purchases/requests/{id}/approve|reject|cancel` | `purchase_requests.approve` | `{ reason }` (reject/cancel) | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/purchases/requests/from-low-stock` | `purchase_requests.create` | `{ warehouse_id, area_id, category_id? }` | 201 | 401, 403, 422 |
| GET | `/api/v1/purchases/requests/pending-items` | `purchase_orders.create` | query `warehouse_id` | 200 `data: RequestItem[]` | 401, 403 |
| GET/POST | `/api/v1/purchases/orders` | `purchase_orders.view` / `.create` | §3 | 200 / 201 | 401, 403, 422 |
| GET/PUT | `/api/v1/purchases/orders/{id}` | `view` / `purchase_orders.update` | §3 | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/purchases/orders/{id}/submit` | `purchase_orders.create` | — | 200 `data.status` | 401, 403, 404, 422 |
| POST | `/api/v1/purchases/orders/{id}/approve|reject` | `purchase_orders.approve` | `{ reason }` (reject) | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/purchases/orders/{id}/close|cancel` | `purchase_orders.cancel` | `{ reason }` | 200 | 401, 403, 404, 422 |
| GET | `/api/v1/purchases/orders/{id}/pdf` | `purchase_orders.view` | query `inline?` | 200 `application/pdf` | 401, 403, 404 |

Ejemplo de totales en la respuesta de la OC:
```json
{ "currency": { "code": "PEN" }, "exchange_rate": 1, "prices_include_tax": false, "tax_rate": 18,
  "taxable_base": 1000.00, "exempt_base": 0, "unaffected_base": 0, "discount_total": 50.00, "tax_total": 180.00, "total": 1180.00 }
```

## 5. Permisos y seguridad
- Permisos nuevos: `purchase_requests.view|create|approve`; `purchase_orders.view|create|update|approve|cancel`. `admin` y `manager` todos; `user` `purchase_requests.view|create`.
- Parámetro nuevo: `purchases.order_approval_threshold` (decimal, 5000.00, moneda base).

## 6. Migraciones
`2026_10_19_000002_create_purchase_requests_and_orders_tables.php`:
```php
Schema::create('purchase_requests', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('number', 20)->unique();
    $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->foreignUuid('area_id')->constrained('areas')->restrictOnDelete();
    $t->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $t->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
    $t->date('required_date'); $t->string('priority', 10)->default('normal'); $t->string('status', 20)->default('draft');
    $t->string('notes', 1000)->nullable();
    $t->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('approved_at')->nullable();
    $t->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('rejected_at')->nullable();
    $t->string('rejection_reason', 500)->nullable();
    $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('cancelled_at')->nullable();
    $t->string('cancel_reason', 500)->nullable(); $t->timestamps();
    $t->index(['branch_id', 'status']);
});
Schema::create('purchase_request_items', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
    $t->unsignedSmallInteger('line');
    $t->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
    $t->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
    $t->string('description', 255)->nullable();
    $t->decimal('quantity', 18, 4); $t->decimal('ordered_quantity', 18, 4)->default(0);
    $t->string('notes', 255)->nullable(); $t->timestamps();
});
Schema::create('purchase_orders', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('number', 20)->unique();
    $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->foreignUuid('provider_id')->constrained('providers')->restrictOnDelete();
    $t->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $t->date('order_date'); $t->date('expected_date')->nullable();
    $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
    $t->decimal('exchange_rate', 12, 4)->default(1);
    $t->string('payment_condition', 10); $t->unsignedSmallInteger('credit_days')->default(0);
    $t->boolean('prices_include_tax')->default(false); $t->decimal('tax_rate', 5, 2);
    $t->decimal('taxable_base', 18, 2)->default(0); $t->decimal('exempt_base', 18, 2)->default(0);
    $t->decimal('unaffected_base', 18, 2)->default(0); $t->decimal('discount_total', 18, 2)->default(0);
    $t->decimal('tax_total', 18, 2)->default(0); $t->decimal('total', 18, 2)->default(0);
    $t->decimal('total_base_currency', 18, 2)->default(0);
    $t->string('status', 20)->default('draft'); $t->text('notes')->nullable(); $t->text('terms')->nullable();
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('approved_at')->nullable();
    $t->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('rejected_at')->nullable();
    $t->string('rejection_reason', 500)->nullable();
    $t->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('closed_at')->nullable();
    $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('cancelled_at')->nullable();
    $t->string('cancel_reason', 500)->nullable(); $t->timestamps();
    $t->index(['branch_id', 'status', 'order_date']); $t->index('provider_id');
});
Schema::create('purchase_order_items', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
    $t->unsignedSmallInteger('line');
    $t->foreignUuid('purchase_request_item_id')->nullable()->constrained('purchase_request_items')->restrictOnDelete();
    $t->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
    $t->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
    $t->string('description', 255)->nullable(); $t->boolean('is_service')->default(false);
    $t->decimal('quantity', 18, 4); $t->decimal('received_quantity', 18, 4)->default(0);
    $t->decimal('unit_price', 18, 4); $t->decimal('discount_percent', 5, 2)->default(0);
    $t->string('tax_type', 20)->default('taxable');
    $t->decimal('line_subtotal', 18, 2); $t->decimal('line_tax', 18, 2); $t->decimal('line_total', 18, 2);
    $t->timestamps();
});
```

## 7. Configuración e infraestructura
- `composer require barryvdh/laravel-dompdf` (versión compatible con Laravel 12) vía `make composer CMD="require barryvdh/laravel-dompdf"`; publicar `config/dompdf.php` con `default_paper_size = a4`, `default_font = DejaVu Sans` (soporta tildes y ñ) y `enable_remote = false`.
- Parámetro `purchases.order_approval_threshold` en `SystemParameterSeeder`.
- Morph map: `purchase_order`.

## 8. Frontend
- **`components/common/DocumentLinesTable.vue`** (reutilizable en 022, 024, 026, 027, 029): grilla editable de líneas (picker o descripción libre, cantidad, precio, % de descuento, tipo de impuesto, subtotal) con props `context`, `pricesIncludeTax`, `taxRate`, `readonly`, `allowFreeText`; muestra la vista previa de totales calculada en el cliente con la misma fórmula (`utils/documentTotals.js`), pero lo que se guarda es lo que devuelve el backend.
- **Requerimientos:** `pages/purchases/requests/index.vue` (pestañas Mis requerimientos · Por aprobar), `new.vue` (incluye "Generar desde stock bajo mínimo") y `[id].vue` (detalle con acciones según estado y avance de ordenado).
- **Órdenes:** `pages/purchases/orders/index.vue` (filtros, chips de estado), `new.vue` (cabecera con `ProviderPicker`, moneda y tipo de cambio, condición de pago; botón "Agregar desde requerimientos" que abre `PendingRequestItemsDialog.vue`), `[id].vue` (detalle, avance de recepción por línea, botones Enviar · Aprobar · Rechazar · Cerrar · Anular · Descargar PDF).
- **Menú:** "Compras" › "Requerimientos" (`purchase_requests.view`), "Órdenes de compra" (`purchase_orders.view`).

## 9. Testing
- `tests/Unit/Support/DocumentTotalsCalculatorTest.php`: precios sin y con impuesto, línea exonerada, descuento por línea, descuento global prorrateado y cuadre de redondeo (casos con 3 líneas de 0.333…).
- `tests/Feature/Support/PdfRendererTest.php`: devuelve `application/pdf` con la razón social.
- `tests/Feature/Modules/Purchases/PurchaseRequestsApiTest.php`: crear, enviar, aprobar, rechazar; editar por otro usuario → 403; `from-low-stock` (con y sin ítems); `pending-items`.
- `tests/Feature/Modules/Purchases/PurchaseOrdersApiTest.php`: crear con totales del backend (ignora totales enviados); línea libre en producto → 422; exceder el pendiente del requerimiento → 422; submit bajo y sobre el umbral; el creador no aprueba; rechazar libera cantidades; cancelar con recepciones → 422 (simulando `received_quantity`); PDF.
- Ejecutar: `make test-filter FILTER=Purchase` y `make test-filter FILTER=DocumentTotals`.

## 10. Decisiones Rechazadas
- **Requerimiento ligado a cotización/tarea (Multinort):** específico del taller.
- **Estados de recepción y pago en columnas separadas de la OC:** la recepción se refleja en el estado (`partially_received`/`received`) y el pago pertenece a la factura del proveedor (spec 024).
- **Cálculo de totales en el frontend:** los totales del cliente no son confiables; el backend es la fuente de verdad.
- **Generar PDF con Browsershot/Chrome:** requiere Chromium en la imagen; dompdf basta para documentos tabulares.
