# 017 · finance-masters — TODO
> **Spec:** 017 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** media · **Depende de specs:** 010, 011
> **Status:** Done
> **Última actualización:** 2026-10-20
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:**
> - 🔧 = pendiente · ✅ = hecho · ⚪ = opcional
> - **Subj** (subproyecto): `back` | `front` | `infra` | `docs`
> - **Complejidad:** S = ≤ 1 h · M = ≤ ½ día · L = > ½ día (evitar L: partir en varias M)
> - Cada tarea es **autocontenida**: un agente con `requirements.md`, `design.md` y los archivos indicados puede ejecutarla sin contexto adicional.
> - **Dependencias** explícitas entre tareas → preservar el orden indicado.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | ✅ | back | M | `migrations/2026_10_18_000001_create_finance_masters_tables.php`, `Finance/Models/*`, `Finance/Enums/*`, factories | — |
| T-2 | ✅ | back | M | `Finance/{Services,Controllers,Requests,Resources}/{Currency,ExchangeRate}*`, `CompanySettingService.php`, `Providers/FinanceServiceProvider.php`, `routes/api.php` | T-1 |
| T-3 | ✅ | back | M | `Finance/{Services,Controllers,Requests,Resources}/{Bank,BankAccount,PaymentMethod}*` | T-2 |
| T-4 | ✅ | back | M | `Finance/{Services,Controllers,Requests,Resources}/{FinancialAccount,FinanceConcept}*` | T-2 |
| T-5 | ✅ | back | S | `FinanceMastersSeeder.php`, `RolePermissionSeeder.php`, `DatabaseSeeder.php` | T-3, T-4 |
| T-6 | ✅ | back | M | `tests/Feature/Modules/Finance/*ApiTest.php` | T-5 |
| T-7 | ✅ | front | M | `pages/finance/settings/index.vue`, `modules/finance/{services/financeSettingsApi.js,stores/financeSettingsStore.js}`, `components/settings/{Currencies,ExchangeRates,Banks,BankAccounts}Tab.vue`, menú | T-3 |
| T-8 | ✅ | front | M | `components/settings/{PaymentMethods,FinancialAccounts,Concepts}Tab.vue`, `components/common/ExchangeRateBanner.vue`, layout | T-4, T-7 |

---
## Detalle de Tareas
### T-1 — Migración, modelos y enums
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_18_000001_create_finance_masters_tables.php`
- `back-laravel/app/Modules/Finance/Models/{Currency,ExchangeRate,Bank,BankAccount,PaymentMethod,FinancialAccount,FinanceConcept}.php`
- `back-laravel/app/Modules/Finance/Enums/*.php`
- `back-laravel/database/factories/*Factory.php` (uno por modelo)
**Descripción:**
1. DDL de design §6 (índices parciales solo en `pgsql`/`sqlite`).
2. Modelos con relaciones y casts (design §1); `BankAccount::balance` fuera de `$fillable`.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-2 — Monedas y tipo de cambio
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Services/{CurrencyService,ExchangeRateService}.php`
- `back-laravel/app/Modules/Finance/Controllers/{CurrencyController,ExchangeRateController}.php`
- `back-laravel/app/Modules/Finance/Requests/{CurrencyRequest,UpsertExchangeRateRequest,ListExchangeRatesRequest}.php`
- `back-laravel/app/Modules/Finance/Resources/{CurrencyResource,ExchangeRateResource}.php`
- `back-laravel/app/Modules/Configuration/Services/CompanySettingService.php`, `Requests/CompanySettingRequest.php`
- `back-laravel/tests/Feature/Modules/Configuration/CompanySettingsApiTest.php`
- `back-laravel/app/Modules/Finance/Providers/FinanceServiceProvider.php`, `bootstrap/providers.php`, `routes/api.php`
**Descripción:**
1. Sincronización de la moneda base con `company_settings` (leer primero `CompanySettingService`): `setDefaultCurrency()` y `BaseCurrencyGuard`.
2. Quitar `default_currency_code` de `CompanySettingRequest` (rules y `toAttributes()`) y ajustar `CompanySettingsApiTest` (spec 010 ya implementado).
3. `ExchangeRateService::current()` y `convert()`.
4. Rutas de monedas y tipo de cambio (design §3).
**Tests:**
- Cubiertos en T-6.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-3 — Bancos, cuentas bancarias y métodos de pago
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Services/{BankService,BankAccountService,PaymentMethodService}.php`
- `back-laravel/app/Modules/Finance/Controllers/{BankController,BankAccountController,PaymentMethodController}.php`
- `back-laravel/app/Modules/Finance/Requests/{BankRequest,BankAccountRequest,PaymentMethodRequest,List*}.php`
- `back-laravel/app/Modules/Finance/Resources/{BankResource,BankAccountResource,PaymentMethodResource}.php`
**Descripción:**
1. Reglas de design §2 (hook `hasMovements()` en cuentas, coherencia `kind`/`settlement`, protección de `CASH`).
2. Rutas CRUD + `/options`.
**Tests:**
- Cubiertos en T-6.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Plan de cuentas y conceptos
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Services/{FinancialAccountService,FinanceConceptService}.php`
- `back-laravel/app/Modules/Finance/Controllers/{FinancialAccountController,FinanceConceptController}.php`
- `back-laravel/app/Modules/Finance/Requests/{FinancialAccountRequest,FinanceConceptRequest,List*}.php`
- `back-laravel/app/Modules/Finance/Resources/{FinancialAccountResource,FinanceConceptResource}.php`
**Descripción:**
1. Jerarquía con herencia de tipo y bloqueo de ciclos (reutilizar el enfoque de `CategoryService`, spec 013).
2. Protección de conceptos de sistema y validación de cuenta imputable.
3. `SystemConcept::model()` con caché por petición.
**Tests:**
- Cubiertos en T-6.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-5 — Seeder y permisos
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-3, T-4
**Archivos clave:**
- `back-laravel/database/seeders/{FinanceMastersSeeder,RolePermissionSeeder,DatabaseSeeder}.php`
**Descripción:**
1. Datos de design §7, idempotentes.
2. Permisos `finance_settings.*` y `exchange_rates.manage`.
**Tests:**
- `make seed` dos veces sin duplicados.
**Criterios de done:**
- [x] `make migrate-fresh` + `make seed` sin errores.

---
### T-6 — Feature tests
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-5
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Finance/{CurrenciesApiTest,ExchangeRatesApiTest,BankAccountsApiTest,PaymentMethodsApiTest,FinancialAccountsAndConceptsApiTest}.php`
**Descripción:**
1. Casos de design §9.
**Tests:**
- Los cinco archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Finance` en verde; `make test` completo en verde.

---
### T-7 — Front: configuración financiera (monedas, TC, bancos, cuentas)
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `front-vue/src/pages/finance/settings/index.vue`
- `front-vue/src/modules/finance/services/financeSettingsApi.js`, `stores/financeSettingsStore.js`
- `front-vue/src/modules/finance/components/settings/{CurrenciesTab,ExchangeRatesTab,BanksTab,BankAccountsTab}.vue`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Leer primero `pages/inventory/settings/index.vue` (spec 013) y replicar el patrón de pestañas.
2. Menú "Finanzas" › "Configuración".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-8 — Front: métodos de pago, plan de cuentas, conceptos y aviso de TC
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4, T-7
**Archivos clave:**
- `front-vue/src/modules/finance/components/settings/{PaymentMethodsTab,FinancialAccountsTab,ConceptsTab}.vue`
- `front-vue/src/components/common/ExchangeRateBanner.vue`
- `front-vue/src/layouts/components/DefaultLayoutWithVerticalNav.vue`
- `front-vue/src/modules/configuration/components/CompanyForm.vue`
**Descripción:**
1. Formulario de método de pago con `settlement` dinámico (muestra la cuenta bancaria solo si corresponde).
2. Árbol del plan de cuentas; conceptos con candado para los de sistema.
3. Banner de tipo de cambio pendiente (design §8).
4. `modules/configuration/components/CompanyForm.vue`: quitar `currencyOptions` fijo y mostrar la moneda base de solo lectura con enlace a Monedas.
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
