# 017 · finance-masters — Design
> **Spec:** 017 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** media · **Depende de specs:** 010, 011
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend:** módulo nuevo `back-laravel/app/Modules/Finance/` (los specs 018–020 lo amplían):
  - `Models/{Currency,ExchangeRate,Bank,BankAccount,PaymentMethod,FinancialAccount,FinanceConcept}.php`
  - `Enums/{MoneyDirection,PaymentMethodKind,Settlement,BankAccountType,FinancialAccountType,ConceptDirection,SystemConcept,SystemPaymentMethod}.php` (`ConceptDirection`: `income`, `expense`, `transfer`; método `allows(MoneyDirection $d): bool` → `income ⇒ in`, `expense ⇒ out`, `transfer ⇒ ambos`)
  - `Services/{CurrencyService,BaseCurrencyGuard,ExchangeRateService,BankService,BankAccountService,PaymentMethodService,FinancialAccountService,FinanceConceptService}.php`
  - `Controllers/*Controller.php` (uno por recurso), `Requests/*`, `Resources/*`
  - `Exceptions/FinanceRuleException.php` (422)
  - `Providers/FinanceServiceProvider.php`
- **Seeder:** `database/seeders/FinanceMastersSeeder.php`.
- **Modificados (código ya implementado en el spec 010):**
  - `Configuration/Services/CompanySettingService`: nuevo `setDefaultCurrency(string $code)`, único punto que escribe `default_currency_code`.
  - `Configuration/Requests/CompanySettingRequest`: `default_currency_code` pasa a `['prohibited']` con mensaje en español y sale de `toAttributes()`.
  - `front-vue/src/modules/configuration/components/CompanyForm.vue`: eliminar la lista fija `currencyOptions` (PEN/USD/EUR) y mostrar la moneda base de solo lectura (desde `configurationStore.summary`), con enlace a `/finance/settings?tab=currencies`.
  - `tests/Feature/Modules/Configuration/CompanySettingsApiTest.php`: ajustar los casos que envían `default_currency_code`.
- **Frontend:** `front-vue/src/pages/finance/settings/index.vue` (pestañas), `front-vue/src/modules/finance/{services,stores,components/settings}/`, `components/common/ExchangeRateBanner.vue`.

## 1. Backend — Modelo y Persistencia
Todos con `HasUuids`, `LogsActivity` (`logAll()->logOnlyDirty()`), `HasFactory`; `SoftDeletes` en todos excepto `ExchangeRate`.
- **`Currency`**: `code`, `name`, `symbol`, `sunat_code`, `decimals`, `is_base`, `is_active`. `scopeBase()`; `Currency::base(): Currency` estático cacheado por petición.
- **`ExchangeRate`**: `currency_id`, `date`, `buy_rate` (decimal:4), `sell_rate` (decimal:4), `created_by`.
- **`Bank`**: `name`, `short_name`, `is_active`; `accounts()`.
- **`BankAccount`**: `bank_id`, `currency_id`, `branch_id`, `alias`, `account_number`, `cci`, `account_type`, `holder_name`, `balance` (decimal:2, **fuera de `$fillable`**, lo escribe solo el ledger bancario del spec 020), `is_active`. Accessor `display_name` → `"{$bank->short_name} {$currency->code} ···{últimos 4}"`.
- **`PaymentMethod`**: `code`, `name`, `kind`, `settlement`, `bank_account_id`, `requires_reference`, `sunat_code`, `sort_order`, `is_system`, `is_active`.
- **`FinancialAccount`**: `code`, `name`, `type`, `parent_id`, `level`, `is_postable`, `is_active`; `parent()`, `children()`.
- **`FinanceConcept`**: `code`, `name`, `direction`, `financial_account_id`, `is_system`, `is_active`.

## 2. Backend — Lógica de Negocio y Servicios
- **`CurrencyService`**: `create/update` → si `is_base = true`: transacción que desmarca la base anterior, fija `decimals` y llama `CompanySettingService::setDefaultCurrency($code)`; desmarcar o desactivar la base → `FinanceRuleException`; cambiar la base si `BaseCurrencyGuard::hasOperations()` (existen `exchange_rates`, y luego movimientos de caja/banco y documentos con importes: cada spec agrega su verificación) → `FinanceRuleException`.
- **`ExchangeRateService`**: `upsert(currencyId, date, buy, sell, User)` (rechaza la moneda base); `current(currencyId, ?date): array{rate: ExchangeRate|null, is_stale: bool}`; `convert(float $amount, Currency $from, Currency $to, Carbon $date, string $side = 'sell'): float` (usa la base como pivote; lanza `FinanceRuleException('No hay tipo de cambio registrado…')` si falta).
- **`BankAccountService`**: CRUD; `update` rechaza cambiar `currency_id` si `hasMovements()` (hook que el spec 020 implementa; aquí devuelve `false`).
- **`PaymentMethodService`**: valida `kind = cash ⇒ settlement = cash_box`; `settlement = bank_account ⇒ bank_account_id` activo; protege `is_system`.
- **`FinancialAccountService`**: `level = parent.level + 1`; `type` heredado; ciclos prohibidos (mismo algoritmo que `CategoryService` del spec 013); una cuenta con hijas pasa a `is_postable = false`.
- **`FinanceConceptService`**: protege `is_system` (no cambiar `code`/`direction`, no eliminar); `financial_account_id` debe ser `is_postable` y de tipo coherente (`income` → `income|asset`, `expense` → `expense|asset|liability`).
- Dirección de los conceptos de sistema: `SALE_COLLECTION`, `RECEIVABLE_COLLECTION`, `CASH_OVERAGE` → `income`; `SUPPLIER_PAYMENT`, `SALE_REFUND`, `CASH_SHORTAGE` → `expense`; `CASH_TRANSFER_IN/OUT`, `BANK_DEPOSIT`, `BANK_WITHDRAWAL`, `BANK_TRANSFER_IN/OUT` → `transfer`.
- **`SystemConcept`** y **`SystemPaymentMethod`** (enums): `SystemConcept::SaleCollection->model(): FinanceConcept` resuelve por `code` con caché por petición.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- Reglas clave:
  - `CurrencyRequest`: `code` required|size:3|uppercase|unique; `name` required|max:40; `symbol` required|max:5; `sunat_code` nullable|max:5; `decimals` required|integer|between:0,4; `is_base`, `is_active` boolean.
  - `UpsertExchangeRateRequest`: `currency_id` required|uuid|exists; `date` required|date|before_or_equal:today; `buy_rate`, `sell_rate` required|numeric|gt:0|decimal:0,4; `sell_rate` gte:buy_rate.
  - `BankAccountRequest`: `bank_id`, `currency_id` required|uuid|exists activo; `branch_id` nullable|uuid|exists; `alias` required|max:60; `account_number` required|max:30|unique por banco (ignorando eliminados); `cci` nullable|digits:20; `account_type` required|enum; `holder_name` nullable|max:150.
  - `PaymentMethodRequest`: `code` required|max:20|regex `^[A-Z0-9_]+$`|unique; `name` required|max:60; `kind`, `settlement` required|enum; `bank_account_id` required_if:settlement,bank_account|nullable|uuid|exists; `requires_reference` boolean; `sunat_code` nullable|max:5; `sort_order` integer|min:0.
  - `FinancialAccountRequest`: `code` required|max:20|regex `^[0-9.]+$`|unique; `name` required|max:150; `type` required_without:parent_id|enum; `parent_id` nullable|uuid|exists; `is_postable` boolean.
  - `FinanceConceptRequest`: `code` required|max:40|regex|unique; `name` required|max:120; `direction` required|enum; `financial_account_id` nullable|uuid|exists.
- Rutas (`v1`, prefijo `finance`), patrón explícito como en `clients`:
```php
// Módulo Finance — Maestros (Spec 017)
Route::prefix('finance')->group(function () {
    Route::get('/exchange-rates/current', [ExchangeRateController::class, 'current']);
    Route::put('/exchange-rates', [ExchangeRateController::class, 'upsert'])->middleware('permission:exchange_rates.manage');
    Route::get('/exchange-rates', [ExchangeRateController::class, 'index'])->middleware('permission:finance_settings.view');
    // Para cada recurso R ∈ {currencies, banks, bank-accounts, payment-methods, financial-accounts, concepts}:
    Route::get('/currencies/options', [CurrencyController::class, 'options']); // solo autenticado; declarar antes de /{id}
    Route::get('/currencies', [CurrencyController::class, 'index'])->middleware('permission:finance_settings.view');
    Route::post('/currencies', [CurrencyController::class, 'store'])->middleware('permission:finance_settings.manage');
    Route::get('/currencies/{id}', [CurrencyController::class, 'show'])->whereUuid('id')->middleware('permission:finance_settings.view');
    Route::match(['put', 'patch'], '/currencies/{id}', [CurrencyController::class, 'update'])->whereUuid('id')->middleware('permission:finance_settings.manage');
    Route::delete('/currencies/{id}', [CurrencyController::class, 'destroy'])->whereUuid('id')->middleware('permission:finance_settings.manage');
    // … repetir el bloque para los demás recursos; financial-accounts agrega GET /financial-accounts/tree (autenticado)
});
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| CRUD | `/api/v1/finance/currencies[/{id}]` | `finance_settings.view|manage` | §3 | 200/201/204 | 401, 403, 404, 422 |
| PUT | `/api/v1/finance/exchange-rates` | `exchange_rates.manage` | `{ currency_id, date, buy_rate, sell_rate }` | 200 `data: ExchangeRate` | 401, 403, 422 |
| GET | `/api/v1/finance/exchange-rates/current` | autenticado | query `currency_id, date?` | 200 `data: { rate, is_stale }` | 401, 422 |
| GET | `/api/v1/finance/exchange-rates` | `finance_settings.view` | query `currency_id, date_from, date_to` | 200 `data[]` | 401, 403, 422 |
| CRUD | `/api/v1/finance/banks[/{id}]` | `finance_settings.*` | `name, short_name, is_active` | 200/201/204 | 401, 403, 404, 422 |
| CRUD | `/api/v1/finance/bank-accounts[/{id}]` | `finance_settings.*` | §3 | 200/201/204 (con `balance` de solo lectura) | 401, 403, 404, 422 |
| CRUD | `/api/v1/finance/payment-methods[/{id}]` | `finance_settings.*` | §3 | 200/201/204 | 401, 403, 404, 422 |
| CRUD | `/api/v1/finance/financial-accounts[/{id}]` (+ `/tree`) | `finance_settings.*` | §3 | 200/201/204 | 401, 403, 404, 422 |
| CRUD | `/api/v1/finance/concepts[/{id}]` | `finance_settings.*` | §3 | 200/201/204 | 401, 403, 404, 422 |
| GET | `/api/v1/finance/{recurso}/options` | autenticado | filtros simples | 200 | 401 |

## 5. Permisos y seguridad
- Permisos nuevos: `finance_settings.view`, `finance_settings.manage` (módulo `finance_settings`) y `exchange_rates.manage` (módulo `exchange_rates`). `admin` todos; `manager` todos; `user` `exchange_rates.manage`.
- Se agrupan los maestros financieros en dos permisos (y no cuatro por recurso) porque los administra un único rol (contabilidad) y son de baja rotación; queda documentado en §10.

## 6. Migraciones
`2026_10_18_000001_create_finance_masters_tables.php`:
```php
Schema::create('currencies', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->char('code', 3); $t->string('name', 40); $t->string('symbol', 5);
    $t->string('sunat_code', 5)->nullable(); $t->unsignedTinyInteger('decimals')->default(2);
    $t->boolean('is_base')->default(false); $t->boolean('is_active')->default(true);
    $t->timestamps(); $t->softDeletes();
});
SoftDeleteUniqueIndex::make('currencies', ['code']);
DB::statement('CREATE UNIQUE INDEX currencies_one_base ON currencies ((true)) WHERE is_base = true AND deleted_at IS NULL');

Schema::create('exchange_rates', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
    $t->date('date'); $t->decimal('buy_rate', 12, 4); $t->decimal('sell_rate', 12, 4);
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->timestamps(); $t->unique(['currency_id', 'date']);
});

Schema::create('banks', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('name', 120); $t->string('short_name', 20);
    $t->boolean('is_active')->default(true); $t->timestamps(); $t->softDeletes();
});
SoftDeleteUniqueIndex::make('banks', ['name']);

Schema::create('bank_accounts', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->foreignUuid('bank_id')->constrained('banks')->restrictOnDelete();
    $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
    $t->foreignUuid('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
    $t->string('alias', 60); $t->string('account_number', 30); $t->char('cci', 20)->nullable();
    $t->string('account_type', 20); $t->string('holder_name', 150)->nullable();
    $t->decimal('balance', 18, 2)->default(0);
    $t->boolean('is_active')->default(true); $t->timestamps(); $t->softDeletes();
});
SoftDeleteUniqueIndex::make('bank_accounts', ['bank_id', 'account_number']);

Schema::create('payment_methods', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('code', 20); $t->string('name', 60);
    $t->string('kind', 20); $t->string('settlement', 20);
    $t->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
    $t->boolean('requires_reference')->default(false); $t->string('sunat_code', 5)->nullable();
    $t->unsignedSmallInteger('sort_order')->default(0); $t->boolean('is_system')->default(false);
    $t->boolean('is_active')->default(true); $t->timestamps(); $t->softDeletes();
});
SoftDeleteUniqueIndex::make('payment_methods', ['code']);

Schema::create('financial_accounts', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('code', 20); $t->string('name', 150); $t->string('type', 20);
    $t->foreignUuid('parent_id')->nullable()->constrained('financial_accounts')->restrictOnDelete();
    $t->unsignedTinyInteger('level')->default(1); $t->boolean('is_postable')->default(true);
    $t->boolean('is_active')->default(true); $t->timestamps(); $t->softDeletes();
});
SoftDeleteUniqueIndex::make('financial_accounts', ['code']);

Schema::create('finance_concepts', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('code', 40); $t->string('name', 120); $t->string('direction', 10);
    $t->foreignUuid('financial_account_id')->nullable()->constrained('financial_accounts')->restrictOnDelete();
    $t->boolean('is_system')->default(false); $t->boolean('is_active')->default(true);
    $t->timestamps(); $t->softDeletes(); $t->index('direction');
});
SoftDeleteUniqueIndex::make('finance_concepts', ['code']);
```

## 7. Configuración e infraestructura
- `FinanceMastersSeeder` (idempotente): PEN (base) y USD; bancos de requirements US-3; método `CASH` (`is_system`, `kind = cash`, `settlement = cash_box`, `sunat_code = '008'`); conceptos de sistema de US-5 más dos no sistema de ejemplo (`OTHER_INCOME`, `OTHER_EXPENSE`).
- La moneda base inicial se toma de `company_settings.default_currency_code` (hoy puede ser PEN, USD o EUR por la lista fija del spec 010): si no es PEN ni USD, el seeder crea esa moneda con nombre y símbolo de un mapa ISO mínimo (`EUR → Euro, €`) y la marca como base.

## 8. Frontend
- **Página** `src/pages/finance/settings/index.vue` (`action: 'finance_settings.view'`): `VTabs` Monedas · Tipo de cambio · Bancos · Cuentas bancarias · Métodos de pago · Plan de cuentas · Conceptos. Componentes en `src/modules/finance/components/settings/*Tab.vue` con `ServerDataTable`, drawers, `useConfirm` y `useApiFormErrors`. Botones de edición solo con `can('finance_settings.manage')`.
- **Tipo de cambio:** pestaña con historial y formulario rápido; `components/common/ExchangeRateBanner.vue` en el layout: si `GET /exchange-rates/current?currency_id=<USD>` devuelve `is_stale` y el usuario tiene `exchange_rates.manage`, muestra un `VAlert` con un formulario en línea.
- **Plan de cuentas:** árbol con código y nombre (mismo componente de árbol del spec 013 si se extrajo; si no, lista indentada).
- **Servicios/stores:** `src/modules/finance/services/financeSettingsApi.js`, `stores/financeSettingsStore.js` (caché de options compartida con los specs 018–028).
- **Menú:** grupo "Finanzas" (`tabler-cash`) › "Configuración" (`finance_settings.view`).

## 9. Testing
- `tests/Feature/Modules/Finance/CurrenciesApiTest.php`: una sola base; cambiar la base actualiza `company_settings`; desactivar la base → 422.
- `ExchangeRatesApiTest.php`: upsert del día; moneda base → 422; `sell < buy` → 422; `current` con `is_stale`; `convert` PEN↔USD.
- `BankAccountsApiTest.php`: CRUD; cuenta duplicada por banco → 422; CCI inválido → 422; `balance` no editable vía API.
- `PaymentMethodsApiTest.php`: `cash` con `bank_account` → 422; `bank_account` sin cuenta → 422; `CASH` de sistema protegido.
- `FinancialAccountsAndConceptsApiTest.php`: herencia de tipo; ciclo → 422; concepto con cuenta no imputable → 422; conceptos de sistema protegidos.
- Ejecutar: `make test-filter FILTER=Finance`.

## 10. Decisiones Rechazadas
- **`banks` con número de cuenta y saldo (Multinort):** mezcla entidad y cuenta; se separan `banks` y `bank_accounts`.
- **`reference_type` entero en métodos de pago:** reemplazado por `kind`/`settlement` explícitos.
- **Eliminar `company_settings.default_currency_code` y leer siempre de `currencies`:** sería una sola fuente física, pero obligaría a `Configuration` (spec 010) a depender de `Finance` (spec 017), invirtiendo la dependencia entre módulos. Se mantiene la columna como copia desnormalizada que solo escribe `CurrencyService`, con la dependencia en una sola dirección (Finance → Configuration).
- **Ignorar en silencio `default_currency_code` en el PUT de empresa:** oculta clientes desactualizados; se responde 422.
- **Permisos CRUD por cada maestro financiero:** 28 permisos para un solo rol administrador; se usa `finance_settings.view|manage`.
- **Cajas multimoneda:** fuera de esta entrega (ver Open Questions).
