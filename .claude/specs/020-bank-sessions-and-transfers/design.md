# 020 · bank-sessions-and-transfers — Design
> **Spec:** 020 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** media · **Depende de specs:** 017, 018, 019
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend** (módulo `Finance`):
  - `Models/{BankPeriod,BankMovement,BankOperation}.php`
  - `Enums/{BankPeriodStatus,BankOperationType,BankOperationStatus}.php`
  - `Data/BankMovementData.php`
  - `Services/{BankLedgerService,BankPeriodService,BankOperationService,BankQueryService}.php`
  - `Controllers/{BankPeriodController,BankMovementController,BankOperationController,BankAccountSummaryController}.php`
  - `Requests/{OpenBankPeriodRequest,CloseBankPeriodRequest,StoreBankMovementRequest,StoreBankOperationRequest,ListBankMovementsRequest}.php`
  - `Resources/{BankPeriodResource,BankMovementResource,BankOperationResource,BankAccountSummaryResource}.php`
  - `Exceptions/BankLedgerException.php`
  - `Console/VerifyBankBalancesCommand.php` (`finance:verify-bank-balances`)
- **Modificados:** `BankAccountService::hasMovements()`, `SystemParameterSeeder` (`finance.allow_negative_bank_balance`), morph map (`bank_operation`, `bank_movement`).
- **Frontend:** `front-vue/src/pages/finance/banks/{index,[id]}.vue`, `front-vue/src/modules/finance/{services/banksApi.js, stores/banksStore.js, components/banks/*}`.

## 1. Backend — Modelo y Persistencia
- **`BankPeriod`** (`HasUuids`, `LogsActivity`): `bank_account_id`, `number` (correlativo por cuenta: 1, 2, 3…), `status`, `opened_at` (date), `opening_balance`, `closed_at`, `closed_by`, `expected_balance`, `statement_balance`, `difference`, `notes`.
- **`BankMovement`** (`HasUuids`, `UPDATED_AT = null`, inmutable): `bank_account_id`, `bank_period_id`, `branch_id` (nullable), `direction`, `concept_id`, `payment_method_id` (nullable), `amount`, `balance_after`, `operation_number`, `movement_date`, `description`, `counterparty_name`, `voucher_path`, `source_type`, `source_id`, `source_number`, `reverses_id`, `is_manual`, `created_by`, `created_at`.
- **`BankOperation`** (`HasUuids`, `LogsActivity` en `status`): `number`, `type` (`deposit`, `withdrawal`, `transfer`), `status` (`completed`, `cancelled`), `bank_account_id`, `target_bank_account_id`, `cash_session_id`, `amount`, `target_amount`, `exchange_rate`, `operation_number`, `operation_date`, `notes`, `voucher_path`, `created_by`, `cancelled_by`, `cancelled_at`, `cancel_reason`. Relaciones `bankMovements()`, `cashMovements()` (`morphMany` por `source`).

## 2. Backend — Lógica de Negocio y Servicios
### `BankLedgerService` (exige transacción abierta)
- `register(BankMovementData $d): BankMovement` — `BankAccount::lockForUpdate()->find()`; exige `is_active` y `concept.direction->allows(direction)`; `period = BankPeriod::where(status open)->first()` → si no, `BankLedgerException` (409); `newBalance = balance ± amount` (bcmath); si `< 0` y `! allow_negative_bank_balance` → 422; inserta el movimiento con `balance_after`; `forceFill(['balance' => newBalance])`.
- `reverse(BankMovement $m, Model $source, string $sourceNumber, string $reason)` — exige que el periodo del original siga `open` y que no esté ya revertido; registra el opuesto.
- `BankMovementData`: `bankAccountId`, `direction`, `conceptCode|conceptId`, `amount`, `movementDate`, `source`, `sourceNumber`, `operationNumber`, `paymentMethodId`, `description`, `counterpartyName`, `voucherPath`, `branchId`, `isManual`.
### `BankPeriodService`
- `openInitial(BankAccount $a, string $openingBalance, Carbon $openedAt, User $u)` — exige que no existan periodos; crea el periodo 1 y fija `balance = openingBalance`.
- `close(BankPeriod $p, string $statementBalance, Carbon $closedAt, ?string $notes, User $u)` — `lockForUpdate` de la cuenta y el periodo; `expected = account.balance`; `difference = statement − expected`; si `≠ 0` exige notas; cierra y crea el periodo `number + 1` con `opening_balance = expected`, `opened_at = closedAt + 1 día`.
### `BankOperationService`
- `create(array $d, User $u)` — transacción; `number = SequentialCodeGenerator::next('bank_operation', 'BO')`; según el tipo:
  - `deposit`: valida la sesión (abierta, sede activa, `BoxAccessService::canOperate`), cuenta en moneda base y `availableCash`; `CashLedgerService::register(out, BANK_DEPOSIT)` + `BankLedgerService::register(in, BANK_DEPOSIT)`.
  - `withdrawal`: `BankLedgerService::register(out, BANK_WITHDRAWAL)` + `CashLedgerService::register(in, BANK_WITHDRAWAL)`.
  - `transfer`: cuentas distintas; si las monedas difieren, `rate = $d['exchange_rate'] ?? ExchangeRateService::convert(...)`; `target_amount`; `register(out, BANK_TRANSFER_OUT)` en origen y `register(in, BANK_TRANSFER_IN)` en destino (bloqueo de cuentas ordenado por `id`).
  - Adjunto: `Storage::disk('local')->putFile('bank-vouchers', $file)`.
- `cancel(BankOperation $o, string $reason, User $u)` — `lockForUpdate`; exige `completed`; revierte todos los `bankMovements` y `cashMovements` de la operación (cada ledger valida su periodo/sesión abiertos; cualquier fallo aborta la transacción completa).
### `BankQueryService`
- `summary(Collection $accounts)`; `movements(BankAccount, from, to, filters)` → `opening` = `balance_after` del último movimiento anterior a `from` (o `opening_balance` del primer periodo), movimientos ordenados por `movement_date, created_at`, `closing`.
### Hook
- `BankAccountService::hasMovements()` → `BankMovement::where('bank_account_id', …)->exists()`.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `OpenBankPeriodRequest`: `opening_balance` required|numeric|decimal:0,2; `opened_at` required|date.
- `CloseBankPeriodRequest`: `statement_balance` required|numeric|decimal:0,2; `closed_at` required|date|after_or_equal:periodo.opened_at; `notes` nullable|max:1000.
- `StoreBankMovementRequest`: `direction` required|in:in,out; `concept_id` required|uuid; `amount` required|numeric|gt:0|decimal:0,2; `operation_number` nullable|max:40; `movement_date` required|date|before_or_equal:today; `description` required|max:255; `counterparty_name` nullable|max:150; `voucher` nullable|file|mimes:pdf,jpg,jpeg,png|max:5120.
- `StoreBankOperationRequest`: `type` required|enum; `bank_account_id` required|uuid; `cash_session_id` required_if:type,deposit,withdrawal|uuid; `target_bank_account_id` required_if:type,transfer|uuid|different:bank_account_id; `amount` required|numeric|gt:0|decimal:0,2; `exchange_rate` nullable|numeric|gt:0|decimal:0,4; `operation_number` required_if:type,deposit|nullable|max:40; `operation_date` required|date; `notes` nullable|max:500; `voucher` nullable|file|mimes:pdf,jpg,jpeg,png|max:5120.
- `BankMovementResource` expone `voucher_url` = ruta autenticada (`/api/v1/finance/bank-movements/{id}/voucher`), nunca la ruta física.
- Rutas (`v1/finance`, `branch`):
```php
// Bancos (Spec 020)
Route::middleware('branch')->group(function () {
    Route::get('/bank-accounts/summary', [BankAccountSummaryController::class, 'index'])->middleware('permission:bank_movements.view');
    Route::get('/bank-accounts/{id}/movements', [BankMovementController::class, 'index'])->whereUuid('id')->middleware('permission:bank_movements.view');
    Route::post('/bank-accounts/{id}/movements', [BankMovementController::class, 'store'])->whereUuid('id')->middleware('permission:bank_movements.create');
    Route::get('/bank-accounts/{id}/periods', [BankPeriodController::class, 'index'])->whereUuid('id')->middleware('permission:bank_movements.view');
    Route::post('/bank-accounts/{id}/periods', [BankPeriodController::class, 'openInitial'])->whereUuid('id')->middleware('permission:bank_periods.close');
    Route::post('/bank-periods/{id}/close', [BankPeriodController::class, 'close'])->whereUuid('id')->middleware('permission:bank_periods.close');
    Route::get('/bank-movements/{id}/voucher', [BankMovementController::class, 'voucher'])->whereUuid('id')->middleware('permission:bank_movements.view');
    Route::post('/bank-movements/{id}/cancel', [BankMovementController::class, 'cancel'])->whereUuid('id')->middleware('permission:bank_operations.cancel');
    Route::get('/bank-operations', [BankOperationController::class, 'index'])->middleware('permission:bank_movements.view|bank_operations.create');
    Route::post('/bank-operations', [BankOperationController::class, 'store'])->middleware('permission:bank_operations.create');
    Route::get('/bank-operations/{id}', [BankOperationController::class, 'show'])->whereUuid('id')->middleware('permission:bank_movements.view|bank_operations.create');
    Route::post('/bank-operations/{id}/cancel', [BankOperationController::class, 'cancel'])->whereUuid('id')->middleware('permission:bank_operations.cancel');
});
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/finance/bank-accounts/summary` | `bank_movements.view` | — | 200 `data: { by_currency: [{ currency, total, accounts[] }] }` | 401, 403 |
| GET | `/api/v1/finance/bank-accounts/{id}/movements` | `bank_movements.view` | query `date_from, date_to, direction?, concept_id?, search?, page` | 200 `data: { opening, movements[], closing }`, `meta` | 401, 403, 404, 422 |
| POST | `/api/v1/finance/bank-accounts/{id}/movements` | `bank_movements.create` | multipart §3 | 201 | 401, 403, 404, 409, 422 |
| GET/POST | `/api/v1/finance/bank-accounts/{id}/periods` | `view` / `bank_periods.close` | `{ opening_balance, opened_at }` | 200 / 201 | 401, 403, 404, 422 |
| POST | `/api/v1/finance/bank-periods/{id}/close` | `bank_periods.close` | `{ statement_balance, closed_at, notes? }` | 200 `data: { closed, next }` | 401, 403, 404, 422 |
| GET | `/api/v1/finance/bank-movements/{id}/voucher` | `bank_movements.view` | — | 200 archivo | 401, 403, 404 |
| POST | `/api/v1/finance/bank-movements/{id}/cancel` | `bank_operations.cancel` | `{ reason }` | 200 | 401, 403, 404, 422 |
| GET/POST | `/api/v1/finance/bank-operations` | `view|create` / `create` | multipart §3 | 200 / 201 | 401, 403, 409, 422 |
| POST | `/api/v1/finance/bank-operations/{id}/cancel` | `bank_operations.cancel` | `{ reason }` | 200 | 401, 403, 404, 422 |

## 5. Permisos y seguridad
- Permisos nuevos: `bank_movements.view|create`, `bank_operations.create|cancel`, `bank_periods.close`. `admin` y `manager` todos; `user` `bank_operations.create` (depósitos de su caja).
- Los comprobantes se guardan en el disco `local` (no público) y se sirven con `Storage::download` tras verificar el permiso.

## 6. Migraciones
`2026_10_18_000004_create_bank_ledger_tables.php`:
```php
Schema::create('bank_periods', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
    $t->unsignedInteger('number'); $t->string('status', 10)->default('open');
    $t->date('opened_at'); $t->decimal('opening_balance', 18, 2);
    $t->date('closed_at')->nullable(); $t->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
    $t->decimal('expected_balance', 18, 2)->nullable(); $t->decimal('statement_balance', 18, 2)->nullable();
    $t->decimal('difference', 18, 2)->nullable(); $t->string('notes', 1000)->nullable();
    $t->timestamps(); $t->unique(['bank_account_id', 'number']);
});
DB::statement("CREATE UNIQUE INDEX bank_periods_one_open ON bank_periods (bank_account_id) WHERE status = 'open'");

Schema::create('bank_operations', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('number', 20)->unique(); $t->string('type', 12); $t->string('status', 10)->default('completed');
    $t->foreignUuid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
    $t->foreignUuid('target_bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
    $t->foreignUuid('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
    $t->decimal('amount', 18, 2); $t->decimal('target_amount', 18, 2)->nullable(); $t->decimal('exchange_rate', 12, 4)->nullable();
    $t->string('operation_number', 40)->nullable(); $t->date('operation_date'); $t->string('notes', 500)->nullable();
    $t->string('voucher_path')->nullable();
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('cancelled_at')->nullable();
    $t->string('cancel_reason', 500)->nullable(); $t->timestamps();
    $t->index(['bank_account_id', 'operation_date']);
});

Schema::create('bank_movements', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->foreignUuid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
    $t->foreignUuid('bank_period_id')->constrained('bank_periods')->restrictOnDelete();
    $t->foreignUuid('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
    $t->string('direction', 3);
    $t->foreignUuid('concept_id')->constrained('finance_concepts')->restrictOnDelete();
    $t->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
    $t->decimal('amount', 18, 2); $t->decimal('balance_after', 18, 2);
    $t->string('operation_number', 40)->nullable(); $t->date('movement_date');
    $t->string('description', 255)->nullable(); $t->string('counterparty_name', 150)->nullable();
    $t->string('voucher_path')->nullable();
    $t->string('source_type', 40)->nullable(); $t->uuid('source_id')->nullable(); $t->string('source_number', 40)->nullable();
    $t->foreignUuid('reverses_id')->nullable()->unique()->constrained('bank_movements')->restrictOnDelete();
    $t->boolean('is_manual')->default(false);
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->timestamp('created_at')->useCurrent();
    $t->index(['bank_account_id', 'movement_date']); $t->index(['source_type', 'source_id']);
});
```

## 7. Configuración e infraestructura
- `SystemParameterSeeder`: `finance.allow_negative_bank_balance` (boolean, `false`).
- Disco `local` de `config/filesystems.php` para comprobantes (carpeta `bank-vouchers/`).
- Comando `finance:verify-bank-balances`: compara `bank_accounts.balance` con el primer `opening_balance` + Σ movimientos.

## 8. Frontend
- **Resumen** `src/pages/finance/banks/index.vue` (`bank_movements.view`): tarjetas por cuenta (banco, alias, moneda, saldo, periodo abierto) agrupadas por moneda; botón "Nueva operación" (`BankOperationDialog.vue`, tipo con `VBtnToggle` y campos dinámicos; caja desde `useCashSession()` para depósitos y retiros).
- **Detalle** `src/pages/finance/banks/[id].vue`: pestañas Movimientos (filtros, tabla con saldo acumulado, enlace al comprobante, "Registrar movimiento" con `BankMovementDialog.vue` y anulación) · Periodos (historial y "Cerrar periodo" con `CloseBankPeriodDialog.vue`, que muestra el esperado y pide el saldo del estado de cuenta) · Operaciones.
- "Abrir cuenta" (primer periodo) visible cuando la cuenta no tiene periodos.
- En la pantalla de caja (spec 018) se agrega el botón "Depositar en banco", que abre `BankOperationDialog` con `type = deposit`.
- **Menú:** "Finanzas" › "Bancos" (`tabler-building-bank`, `bank_movements.view`).

## 9. Testing
- `tests/Feature/Modules/Finance/BankLedgerServiceTest.php`: abono/cargo actualizan `balance` y `balance_after`; cargo con saldo insuficiente → 422 (y permitido con el parámetro); sin periodo → 409; reversión; inmutabilidad.
- `BankPeriodsApiTest.php`: apertura inicial; segunda apertura → 422; cierre con diferencia sin notas → 422; cierre abre el siguiente con el saldo esperado.
- `BankMovementsApiTest.php`: movimiento manual con comprobante (`Storage::fake('local')`); concepto de sistema → 422; descarga autenticada; anulación en periodo cerrado → 422.
- `BankOperationsApiTest.php`: depósito mueve caja y banco; depósito mayor que el disponible → 422; cuenta en USD para depósito → 422; retiro; transferencia PEN→USD con tipo de cambio; anular el depósito revierte ambos lados; anular con la sesión de caja cerrada → 422 sin cambios parciales.
- `VerifyBankBalancesCommandTest.php`.
- Ejecutar: `make test-filter FILTER=Bank`.

## 10. Decisiones Rechazadas
- **Sesiones bancarias abiertas y cerradas manualmente como la caja (Multinort):** bloquean cobros con tarjeta cuando nadie abrió la sesión; se usan periodos encadenados siempre abiertos.
- **`deposits` con estado de uso y vínculo a ingresos/egresos:** reemplazado por movimientos con `source` del documento que los origina.
- **Comprobantes en disco público:** contienen datos bancarios sensibles.
