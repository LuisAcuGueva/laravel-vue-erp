# 026 · quotations — Design
> **Spec:** 026 · **Alcance:** fullstack · **Módulo:** Sales · **Prioridad:** media · **Depende de specs:** 004, 010, 012, 014, 015, 022, 025
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend:** módulo nuevo `back-laravel/app/Modules/Sales/` (los specs 027–029 lo amplían):
  - `Models/{Quotation,QuotationItem}.php`, `Enums/QuotationStatus.php` (con `canTransitionTo()`)
  - `Services/{QuotationService,QuotationWorkflowService,DiscountPolicyService,SalePricingService}.php`
  - `Controllers/QuotationController.php`
  - `Requests/{StoreQuotationRequest,ListQuotationsRequest}.php` (+ `CancelDocumentRequest`)
  - `Resources/{QuotationResource,QuotationItemResource}.php`
  - `Console/ExpireQuotationsCommand.php` (`sales:expire-quotations`)
  - `Exceptions/{SalesRuleException,DiscountLimitExceededException}.php`
  - `Providers/SalesServiceProvider.php`
  - Vista PDF `resources/views/pdf/sales/quotation.blade.php`
- **Modificados:** `routes/console.php` (programar `sales:expire-quotations` a diario), `ClientService::hasDocuments()`, `EmployeeService::delete()` (bloqueo por `seller_id`), `CatalogItemService::delete()`, `pages/clients/[id].vue` (pestaña Cotizaciones).
- **Frontend:** `front-vue/src/pages/sales/quotations/{index,new,[id]}.vue`, `front-vue/src/modules/sales/{services/quotationsApi.js, stores/quotationsStore.js, components/quotations/*}`; reutiliza `DocumentLinesTable`, `ClientPicker`, `CatalogItemPicker`.

## 1. Backend — Modelo y Persistencia
- **`Quotation`** (`HasUuids`, `LogsActivity` en estado y totales): `number`, `branch_id`, `client_id`, `contact_id`, `seller_id`, `issue_date`, `valid_until`, `currency_id`, `exchange_rate`, `price_type`, `prices_include_tax`, `tax_rate`, `global_discount_percent`, `taxable_base`, `exempt_base`, `unaffected_base`, `discount_total`, `tax_total`, `total`, `total_base_currency`, `status`, `notes`, `terms`, `delivery_time`, `payment_terms`, `discount_override_by` (usuario que superó el tope, nullable), `created_by`, `sent_at`, `accepted_at`, `rejected_*`, `cancelled_*`, `converted_sale_id` (nullable; FK la agrega el spec 027). Relaciones `client`, `contact`, `seller`, `items`.
- **`QuotationItem`** (`HasUuids`): `quotation_id`, `line`, `catalog_item_id`, `item_presentation_id`, `description`, `quantity`, `list_price`, `unit_price`, `discount_percent`, `effective_discount_percent`, `tax_type`, `line_subtotal`, `line_tax`, `line_total`.

## 2. Backend — Lógica de Negocio y Servicios
### `SalePricingService` (compartido con 027)
- `listPrice(ItemPresentation $p, PriceType $type, float $quantity, Currency $currency, string $rate): ?string` — `wholesale` aplica solo si `quantity ≥ min_quantity`, si no cae a `retail`; convierte de moneda base a la del documento (`/ rate`).
### `DiscountPolicyService` (compartido con 027)
- `validate(array $lines, string $globalDiscountPercent, User $u): ?User` — calcula `effective = 1 − (unit_price × (1 − d/100)) / list_price` por línea con precio de lista; compara con `SystemParameterService::decimal('sales.max_line_discount_percent')` y el global con `sales.max_global_discount_percent`; si alguno excede y `! $u->can('sales.discount_override')` → `DiscountLimitExceededException` (422, con la clave `items.{i}.discount_percent` o `global_discount_percent`); si excede y tiene permiso, devuelve `$u` para guardarlo en `discount_override_by`.
### `QuotationService`
- `create/update(array $d, User $u)`: transacción; valida cliente activo y no de sistema, `contact_id` del cliente, `seller_id` activo (por defecto `$u->employee?->id`); completa `list_price`/`unit_price` con `SalePricingService`; ítems `is_sellable`; `DiscountPolicyService::validate()`; `DocumentTotalsCalculator` con `globalDiscount` en porcentaje; `number = next('quotation', 'COT')`; `valid_until` por defecto.
- `duplicate(Quotation $q, User $u)`: copia cabecera y líneas con precios de lista actuales; omite ítems inactivos.
- `availability(Quotation $q)`: stock por línea en los almacenes de la sede activa (`StockQueryService::availableFor`).
### `QuotationWorkflowService`
- `send`, `accept` (valida vigencia), `reject`, `cancel`, `markConverted(Quotation $q, Model $sale)` (lo usa el spec 027), `expireOverdue(): int` (usado por el comando; actualización masiva de `sent` con `valid_until < today`).

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `StoreQuotationRequest`: `client_id` required|uuid; `contact_id` nullable|uuid; `seller_id` nullable|uuid; `issue_date` required|date; `valid_until` nullable|date|after_or_equal:issue_date; `currency_id` required|uuid; `exchange_rate` nullable|numeric|gt:0; `price_type` nullable|in:retail,wholesale; `global_discount_percent` nullable|numeric|between:0,100; `notes`, `terms`, `delivery_time`, `payment_terms` nullable|max:2000; `items` required|array|min:1|max:300; `items.*.item_presentation_id` nullable|uuid; `items.*.description` required_without:items.*.item_presentation_id|max:255; `items.*.quantity` required|numeric|gt:0|decimal:0,4; `items.*.unit_price` nullable|numeric|min:0|decimal:0,4 (requerido si es texto libre); `items.*.discount_percent` nullable|numeric|between:0,100.
- `ListQuotationsRequest`: `status`, `client_id`, `seller_id`, `date_from`, `date_to`, `search`, paginación.
- Visibilidad: sin `quotations.view_all` → `where(created_by = u) or seller_id = u.employee.id`.
- Rutas (`v1/sales`, `branch`):
```php
// Módulo Sales — Cotizaciones (Spec 026)
Route::prefix('sales')->middleware('branch')->group(function () {
    Route::get('/quotations', [QuotationController::class, 'index'])->middleware('permission:quotations.view');
    Route::post('/quotations', [QuotationController::class, 'store'])->middleware('permission:quotations.create');
    Route::get('/quotations/{id}', [QuotationController::class, 'show'])->whereUuid('id')->middleware('permission:quotations.view');
    Route::put('/quotations/{id}', [QuotationController::class, 'update'])->whereUuid('id')->middleware('permission:quotations.update');
    Route::get('/quotations/{id}/availability', [QuotationController::class, 'availability'])->whereUuid('id')->middleware('permission:quotations.view');
    Route::get('/quotations/{id}/pdf', [QuotationController::class, 'pdf'])->whereUuid('id')->middleware('permission:quotations.view');
    Route::post('/quotations/{id}/duplicate', [QuotationController::class, 'duplicate'])->whereUuid('id')->middleware('permission:quotations.create');
    Route::post('/quotations/{id}/send', [QuotationController::class, 'send'])->whereUuid('id')->middleware('permission:quotations.update');
    Route::post('/quotations/{id}/accept', [QuotationController::class, 'accept'])->whereUuid('id')->middleware('permission:quotations.update');
    Route::post('/quotations/{id}/reject', [QuotationController::class, 'reject'])->whereUuid('id')->middleware('permission:quotations.update');
    Route::post('/quotations/{id}/cancel', [QuotationController::class, 'cancel'])->whereUuid('id')->middleware('permission:quotations.cancel');
});
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET/POST | `/api/v1/sales/quotations` | `branch`, `quotations.view` / `.create` | §3 | 200 (`meta.totals_by_status`) / 201 | 401, 403, 409, 422 |
| GET/PUT | `/api/v1/sales/quotations/{id}` | `view` / `update` | §3 | 200 | 401, 403, 404, 422 |
| GET | `/api/v1/sales/quotations/{id}/availability` | `view` | — | 200 `data: [{ line, available }]` | 401, 403, 404 |
| GET | `/api/v1/sales/quotations/{id}/pdf` | `view` | query `inline?` | 200 PDF | 401, 403, 404 |
| POST | `/api/v1/sales/quotations/{id}/duplicate` | `create` | — | 201 (`meta.skipped_lines`) | 401, 403, 404 |
| POST | `/api/v1/sales/quotations/{id}/send|accept|reject` | `update` | `{ reason }` (reject) | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/sales/quotations/{id}/cancel` | `quotations.cancel` | `{ reason }` | 200 | 401, 403, 404, 422 |

Error de descuento:
```json
{ "message": "El descuento supera el máximo permitido (10.00 %).", "errors": { "items.1.discount_percent": ["El descuento efectivo de la línea 2 es 15.00 % y el máximo es 10.00 %."] } }
```

## 5. Permisos y seguridad
- Permisos nuevos: `quotations.view|view_all|create|update|cancel` (módulo `quotations`) y `sales.discount_override` (módulo `sales`). `admin` y `manager` todos; `user` `quotations.view|create|update`.

## 6. Migraciones
`2026_10_20_000002_create_quotations_tables.php`:
```php
Schema::create('quotations', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('number', 20)->unique();
    $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
    $t->foreignUuid('contact_id')->nullable()->constrained('client_contacts')->nullOnDelete();
    $t->foreignUuid('seller_id')->nullable()->constrained('employees')->restrictOnDelete();
    $t->date('issue_date'); $t->date('valid_until');
    $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete(); $t->decimal('exchange_rate', 12, 4)->default(1);
    $t->string('price_type', 10)->default('retail'); $t->boolean('prices_include_tax')->default(true); $t->decimal('tax_rate', 5, 2);
    $t->decimal('global_discount_percent', 5, 2)->default(0);
    $t->decimal('taxable_base', 18, 2)->default(0); $t->decimal('exempt_base', 18, 2)->default(0);
    $t->decimal('unaffected_base', 18, 2)->default(0); $t->decimal('discount_total', 18, 2)->default(0);
    $t->decimal('tax_total', 18, 2)->default(0); $t->decimal('total', 18, 2)->default(0); $t->decimal('total_base_currency', 18, 2)->default(0);
    $t->string('status', 12)->default('draft');
    $t->text('notes')->nullable(); $t->text('terms')->nullable(); $t->string('delivery_time', 255)->nullable(); $t->string('payment_terms', 255)->nullable();
    $t->foreignUuid('discount_override_by')->nullable()->constrained('users')->nullOnDelete();
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->timestamp('sent_at')->nullable(); $t->timestamp('accepted_at')->nullable();
    $t->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('rejected_at')->nullable(); $t->string('rejection_reason', 500)->nullable();
    $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('cancelled_at')->nullable(); $t->string('cancel_reason', 500)->nullable();
    $t->uuid('converted_sale_id')->nullable();
    $t->timestamps();
    $t->index(['branch_id', 'status', 'issue_date']); $t->index('client_id'); $t->index('seller_id');
});
Schema::create('quotation_items', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('quotation_id')->constrained('quotations')->cascadeOnDelete();
    $t->unsignedSmallInteger('line');
    $t->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
    $t->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
    $t->string('description', 255);
    $t->decimal('quantity', 18, 4); $t->decimal('list_price', 18, 4)->nullable(); $t->decimal('unit_price', 18, 4);
    $t->decimal('discount_percent', 5, 2)->default(0); $t->decimal('effective_discount_percent', 5, 2)->default(0);
    $t->string('tax_type', 20)->default('taxable');
    $t->decimal('line_subtotal', 18, 2); $t->decimal('line_tax', 18, 2); $t->decimal('line_total', 18, 2);
    $t->timestamps();
});
```

## 7. Configuración e infraestructura
- Parámetros ya sembrados en el spec 010: `sales.max_line_discount_percent`, `sales.max_global_discount_percent`, `sales.quotation_validity_days`.
- `routes/console.php`: `Schedule::command('sales:expire-quotations')->dailyAt('00:10')->withoutOverlapping();` (scheduler del spec 004).
- Morph map: `quotation`.

## 8. Frontend
- **Listado** `pages/sales/quotations/index.vue` (`quotations.view`): filtros (estado, cliente, vendedor, fechas, búsqueda), tarjetas con totales por estado, tabla con número, fecha, cliente, vendedor, validez (chip de vencida), total y estado.
- **Formulario** `new.vue` / `[id].vue`: cabecera (`ClientPicker`, contacto, vendedor con `/employees/options`, fechas, moneda y tipo de cambio, tipo de precio) y `DocumentLinesTable` (`context=sale`, texto libre permitido) con columnas de precio de lista, descuento (con tope visible y validación en vivo según `can('sales.discount_override')`) y stock disponible. Panel de descuento global, términos y condiciones. Botones por estado: Guardar, Enviar, Aceptar, Rechazar, Anular, Duplicar, PDF y (spec 027) "Convertir en venta".
- **Detalle de cliente**: pestaña "Cotizaciones".
- **Menú:** grupo "Ventas" (`tabler-receipt`) › "Cotizaciones" (`quotations.view`).

## 9. Testing
- `tests/Unit/Modules/Sales/DiscountPolicyServiceTest.php`: descuento por porcentaje y por precio rebajado; tope de línea y global; con permiso devuelve el usuario; texto libre ignorado.
- `tests/Feature/Modules/Sales/QuotationsApiTest.php`: crear con precios de lista por tipo (mayorista con cantidad mínima); totales del backend; descuento sobre el tope sin permiso → 422 y con permiso → 201 con `discount_override_by`; cliente de sistema → 422; vendedor por defecto del usuario; transiciones válidas e inválidas; aceptar vencida → 422; duplicar omite ítems inactivos; PDF; visibilidad sin `view_all`; 403 por permiso.
- `tests/Feature/Modules/Sales/ExpireQuotationsCommandTest.php`.
- Ejecutar: `make test-filter FILTER=Quotation` y `FILTER=DiscountPolicy`.

## 10. Decisiones Rechazadas
- **Descuentos desde promociones y membresías (Multinort `descuentos-servicios`):** promociones y membresías quedan fuera del alcance; solo descuentos manuales con tope.
- **Tipos de línea del taller (repuesto, insumo, mano de obra, adicional):** reemplazados por ítems del catálogo (producto/servicio) o texto libre.
- **Aprobación de cada descuento por un supervisor (flujo):** se resuelve con permiso y registro de quién lo superó; un flujo de aprobación queda para un spec futuro.
