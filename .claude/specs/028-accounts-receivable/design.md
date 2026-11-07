# 028 · accounts-receivable — Design
> **Spec:** 028 · **Alcance:** fullstack · **Módulo:** Sales · **Prioridad:** media · **Depende de specs:** 018, 020, 024, 025, 027
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
  - `Models/{ReceivableInstallment,Collection,CollectionAllocation}.php`
  - `Enums/{InstallmentStatus,CollectionStatus}.php` (`InstallmentStatus` puede compartirse con CxP si coincide; si no, propio)
  - `Services/{InstallmentScheduleService,CollectionService,ReceivableQueryService}.php`
  - `Hooks/CreateReceivableInstallments.php` (implementa `AfterSaleIssuedHook` del spec 027) y `Listeners/CancelReceivableInstallments.php` (de `SaleCancelled`)
  - `Controllers/{CollectionController,ReceivableController,ClientStatementController}.php`
  - `Requests/{StoreCollectionRequest,RescheduleInstallmentsRequest,ListReceivablesRequest,ClientStatementRequest}.php`
  - `Resources/{ReceivableInstallmentResource,CollectionResource,ReceivableAgingResource}.php`
  - Vistas `resources/views/pdf/sales/{collection-receipt,client-statement}.blade.php`
- **Modificados:** `StoreSaleRequest` (campos `installments`, `installments_count`, `installment_interval_days`), `SaleCancellationService::hasCollections()`, `CreditPolicyService` (bloqueo por vencidas según parámetro), `ClientResource.account` (cuotas vencidas), `pages/finance/cash/index.vue` (botón "Cobrar"), `pages/clients/[id].vue`, morph map (`collection`), `SystemParameterSeeder`.
- **Frontend:** `front-vue/src/pages/sales/receivables/index.vue`, `front-vue/src/modules/sales/{services/receivablesApi.js, stores/receivablesStore.js, components/receivables/*}`.

## 1. Backend — Modelo y Persistencia
- **`ReceivableInstallment`** (`HasUuids`): `sale_id`, `client_id`, `currency_id`, `number`, `due_date`, `amount`, `paid_amount`, `balance`, `status` (`pending`, `partially_paid`, `paid`, `cancelled`). Accessor `is_overdue`, `days_overdue`.
- **`Collection`** (`HasUuids`, `LogsActivity`): `number`, `branch_id`, `client_id`, `payment_date`, `currency_id`, `payment_method_id`, `cash_session_id`, `bank_account_id`, `amount`, `reference`, `notes`, `status`, `created_by`, `cancelled_*`. Relaciones `allocations`, movimientos por `source`.
- **`CollectionAllocation`** (`HasUuids`): `collection_id`, `receivable_installment_id`, `sale_id`, `amount`.

## 2. Backend — Lógica de Negocio y Servicios
### `InstallmentScheduleService`
- `build(Sale $s, ?array $explicit, ?int $count, ?int $intervalDays): array` — explícitas (validadas), o `count` cuotas iguales (la última absorbe el redondeo) cada `intervalDays` desde `issue_date`, o una sola al `due_date`.
- `validate(...)` (usado por `StoreSaleRequest::withValidator` para responder 422 antes de emitir).
- `reschedule(Sale $s, array $new, User $u)` — transacción; `lockForUpdate` de cuotas; conserva las `paid`/`partially_paid`; elimina las `pending` sin asignaciones; crea las nuevas con numeración continua; log de actividad en la venta con el cronograma anterior y el nuevo.
### Generación de cuotas (atómica con la venta)
- `InstallmentScheduleService::createFor(Sale $s, array $schedule)` se ejecuta **dentro** de la transacción de emisión a través del punto de extensión `SaleIssuanceService::afterIssue()` (spec 027): `SalesServiceProvider` registra un `AfterSaleIssuedHook` que, si `payment_condition = credit`, crea las cuotas. Así una venta al crédito nunca queda sin cronograma.
- El evento `SaleIssued` (posterior al commit) queda solo para efectos no críticos.
- Listener `CancelReceivableInstallments` (de `SaleCancelled`): pasa las cuotas de la venta a `cancelled`; la anulación ya validó que no hay cobranzas.
### `CollectionService`
- `create(array $d, User $u)`: transacción; si `auto_allocate` → cuotas del cliente con saldo, en la moneda, ordenadas por `due_date, number`, asignando hasta agotar `amount`; bloquea las cuotas y las ventas (`lockForUpdate`, ordenadas por `id`); valida cliente, estado de las ventas, moneda y saldos; `number = next('collection', 'CB')`; `MoneySettlementService::settle(direction: in, concept: RECEIVABLE_COLLECTION, source: $collection)`; actualiza `installment.paid_amount/balance/status` y `sale.paid_amount/balance`.
- `cancel(Collection $c, string $reason, User $u)`: `unsettle()`; revierte asignaciones en cuotas y ventas.
### `ReceivableQueryService`
- `openInstallments(filters)`, `aging(asOf)` (mismo esquema que CxP, agrupado por cliente y moneda), `statement(Client, from, to, currency)`: cargos = ventas al crédito (`total − pagos iniciales`), abonos = cobranzas y notas de crédito aplicadas (spec 029, vía consulta unida cuando exista la tabla), saldo inicial y acumulado.
### Política de crédito
- `CreditPolicyService::assertAvailable()` agrega: si `sales.block_credit_when_overdue = true` y el cliente tiene cuotas vencidas → 422 "El cliente tiene cuotas vencidas".
### Hook
- `SaleCancellationService::hasCollections(Sale $s)` → existen `CollectionAllocation` de la venta con cobranza `completed`.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `StoreSaleRequest` (spec 027) amplía: `installments` nullable|array|required_without_all…; `installments.*.due_date` required|date|after_or_equal:issue_date; `installments.*.amount` required|numeric|gt:0|decimal:0,2; `installments_count` nullable|integer|between:1,60; `installment_interval_days` nullable|integer|between:1,365; solo con `payment_condition = credit`.
- `StoreCollectionRequest`: `client_id` required|uuid; `payment_date` required|date|before_or_equal:today; `currency_id` required|uuid; `payment_method_id` required|uuid; `cash_session_id` nullable|uuid; `reference` nullable|max:60; `notes` nullable|max:500; `auto_allocate` boolean; `amount` required_if:auto_allocate,true|numeric|gt:0; `allocations` required_unless:auto_allocate,true|array; `allocations.*.installment_id` required|uuid|distinct; `allocations.*.amount` required|numeric|gt:0|decimal:0,2.
- `RescheduleInstallmentsRequest`: `installments` required|array|min:1; `.due_date` required|date; `.amount` required|numeric|gt:0.
- Rutas (`v1/sales` con `branch`, y `v1/clients`):
```php
// Cuentas por cobrar (Spec 028)
Route::get('/receivables', [ReceivableController::class, 'index'])->middleware('permission:receivables.view');
Route::get('/receivables/aging', [ReceivableController::class, 'aging'])->middleware('permission:receivables.view');
Route::put('/sales/{id}/installments', [ReceivableController::class, 'reschedule'])->whereUuid('id')->middleware('permission:receivables.reschedule');
Route::get('/collections', [CollectionController::class, 'index'])->middleware('permission:receivables.view');
Route::post('/collections', [CollectionController::class, 'store'])->middleware('permission:collections.create');
Route::get('/collections/{id}', [CollectionController::class, 'show'])->whereUuid('id')->middleware('permission:receivables.view');
Route::get('/collections/{id}/pdf', [CollectionController::class, 'pdf'])->whereUuid('id')->middleware('permission:receivables.view');
Route::post('/collections/{id}/cancel', [CollectionController::class, 'cancel'])->whereUuid('id')->middleware('permission:collections.cancel');
// en el bloque clients:
Route::get('/{id}/statement', [ClientStatementController::class, 'show'])->whereUuid('id')->middleware('permission:receivables.view');
Route::get('/{id}/statement/pdf', [ClientStatementController::class, 'pdf'])->whereUuid('id')->middleware('permission:receivables.view');
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/sales/receivables` | `branch`, `receivables.view` | filtros §US-5 | 200 `data: Installment[]`, `meta.totals_by_currency` | 401, 403 |
| GET | `/api/v1/sales/receivables/aging` | `receivables.view` | query `as_of?` | 200 `data: [{ client, currency, current, d1_30, d31_60, d61_90, over_90, total }]` | 401, 403 |
| PUT | `/api/v1/sales/sales/{id}/installments` | `receivables.reschedule` | `{ installments: [...] }` | 200 | 401, 403, 404, 422 |
| GET/POST | `/api/v1/sales/collections` | `receivables.view` / `collections.create` | §3 | 200 / 201 | 401, 403, 409, 422 |
| GET | `/api/v1/sales/collections/{id}` (`/pdf`) | `receivables.view` | query `format?` | 200 / PDF | 401, 403, 404 |
| POST | `/api/v1/sales/collections/{id}/cancel` | `collections.cancel` | `{ reason }` | 200 | 401, 403, 404, 422 |
| GET | `/api/v1/clients/{id}/statement` (`/pdf`) | `receivables.view` | query `date_from, date_to, currency_id` | 200 `data: { opening, entries[], closing }` / PDF | 401, 403, 404, 422 |

## 5. Permisos y seguridad
- Permisos nuevos: `receivables.view|reschedule` y `collections.create|cancel`. `admin` y `manager` todos; `user` `receivables.view` y `collections.create`.
- Parámetro nuevo: `sales.block_credit_when_overdue` (boolean, `true`).

## 6. Migraciones
`2026_10_20_000004_create_receivables_tables.php`:
```php
Schema::create('receivable_installments', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
    $t->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
    $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
    $t->unsignedSmallInteger('number'); $t->date('due_date');
    $t->decimal('amount', 18, 2); $t->decimal('paid_amount', 18, 2)->default(0); $t->decimal('balance', 18, 2);
    $t->string('status', 16)->default('pending'); $t->timestamps();
    $t->index(['client_id', 'status', 'due_date']); $t->index('sale_id');
});
Schema::create('collections', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('number', 20)->unique();
    $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
    $t->date('payment_date');
    $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
    $t->foreignUuid('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
    $t->foreignUuid('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
    $t->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
    $t->decimal('amount', 18, 2); $t->string('reference', 60)->nullable(); $t->string('notes', 500)->nullable();
    $t->string('status', 12)->default('completed');
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('cancelled_at')->nullable();
    $t->string('cancel_reason', 500)->nullable(); $t->timestamps();
    $t->index(['client_id', 'payment_date']);
});
Schema::create('collection_allocations', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('collection_id')->constrained('collections')->cascadeOnDelete();
    $t->foreignUuid('receivable_installment_id')->constrained('receivable_installments')->restrictOnDelete();
    $t->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
    $t->decimal('amount', 18, 2); $t->timestamps();
    $t->index('sale_id');
});
```

## 7. Configuración e infraestructura
- Parámetro `sales.block_credit_when_overdue` en `SystemParameterSeeder`.
- Hook y listener registrados en `SalesServiceProvider` (`tag` del hook y `Event::listen`).
- Morph map: `collection`.

## 8. Frontend
- **POS** (spec 027): en el diálogo de cobro con "Crédito", sección "Cuotas" (`InstallmentPlanEditor.vue`: única, N cuotas cada X días, o manual con tabla editable y suma validada).
- **Cuentas por cobrar** `pages/sales/receivables/index.vue` (`receivables.view`): pestañas Cuotas (filtros, chips de vencida, selección múltiple de un mismo cliente → "Cobrar" abre `CollectionDialog.vue` con `PaymentMethodFields` y montos por cuota o "Distribuir automáticamente") · Antigüedad · Cobranzas (historial, recibo PDF, anulación).
- **Detalle de venta** (spec 027): pestaña "Cuotas" con reprogramación (`RescheduleDialog.vue`, `can('receivables.reschedule')`).
- **Cliente** (spec 025): tarjeta de crédito con deuda y vencidas; pestaña "Estado de cuenta" con rango de fechas y PDF.
- **Caja:** botón "Cobrar" en `pages/finance/cash/index.vue` que abre `CollectionDialog` con la sesión actual.
- **Menú:** "Ventas" › "Cuentas por cobrar" (`receivables.view`).

## 9. Testing
- `tests/Feature/Modules/Sales/ReceivableInstallmentsTest.php`: venta al crédito sin plan → una cuota; con `installments_count = 3` cada 30 días (redondeo en la última); plan explícito que no suma → 422; cuota fuera de `credit_days` → 422; anular venta sin cobranzas → cuotas `cancelled`; reprogramar conservando las pagadas.
- `tests/Feature/Modules/Sales/CollectionsApiTest.php`: cobranza a varias cuotas de dos ventas (saldos de cuotas y ventas); `auto_allocate` (más antiguas primero); exceder el saldo → 422; moneda distinta → 422; cobro en caja y en banco (movimiento correcto); anular revierte; anular con sesión cerrada → 422; venta con cobranza no se anula (spec 027) → 422; cliente con vencidas bloquea nueva venta al crédito; recibo PDF.
- `tests/Feature/Modules/Sales/ReceivablesReportsApiTest.php`: aging por buckets y moneda; estado de cuenta con saldo acumulado; PDF.
- Ejecutar: `make test-filter FILTER=Receivable` y `make test-filter FILTER=Collections`.

## 10. Decisiones Rechazadas
- **`incomes` con `quote_id` (Multinort):** el ingreso se modela como cobranza con asignaciones a cuotas, simétrico a CxP.
- **Generar las cuotas con un listener asíncrono posterior al commit:** una venta al crédito podría quedar sin cuotas si el listener falla; se generan dentro de la transacción de emisión.
- **Recalcular `sale.balance` en cada consulta:** se mantiene persistido y actualizado transaccionalmente para el control de crédito.
