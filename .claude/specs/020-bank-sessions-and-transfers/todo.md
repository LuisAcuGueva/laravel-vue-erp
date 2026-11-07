# 020 · bank-sessions-and-transfers — TODO
> **Spec:** 020 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** media · **Depende de specs:** 017, 018, 019
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
| T-1 | ✅ | back | S | `migrations/2026_10_20_000001_create_bank_ledger_tables.php`, `Finance/Models/{BankPeriod,BankMovement,BankOperation}.php`, `Enums/*` | — |
| T-2 | ✅ | back | M | `Finance/Data/BankMovementData.php`, `Services/{BankLedgerService,BankPeriodService}.php`, `Exceptions/BankLedgerException.php`, `BankAccountService.php` | T-1 |
| T-3 | ✅ | back | M | `Services/BankQueryService.php`, `Controllers/{BankAccountSummaryController,BankMovementController,BankPeriodController}.php`, requests/resources, `routes/api.php` | T-2 |
| T-4 | ✅ | back | M | `Services/BankOperationService.php`, `Controllers/BankOperationController.php`, `Requests/StoreBankOperationRequest.php`, `Resources/BankOperationResource.php` | T-2 |
| T-5 | ✅ | back | S | `Console/VerifyBankBalancesCommand.php`, `RolePermissionSeeder.php`, `SystemParameterSeeder.php` | T-3, T-4 |
| T-6 | ✅ | back | M | `tests/Feature/Modules/Finance/{BankLedgerServiceTest,BankPeriodsApiTest,BankMovementsApiTest}.php` | T-5 |
| T-7 | ✅ | back | M | `tests/Feature/Modules/Finance/{BankOperationsApiTest,VerifyBankBalancesCommandTest}.php` | T-5 |
| T-8 | ✅ | front | M | `pages/finance/banks/index.vue`, `modules/finance/{services/banksApi.js,stores/banksStore.js,components/banks/BankOperationDialog.vue}`, menú, botón en caja | T-4 |
| T-9 | ✅ | front | M | `pages/finance/banks/[id].vue`, `components/banks/{BankMovementDialog,CloseBankPeriodDialog}.vue` | T-3, T-8 |

---
## Detalle de Tareas
### T-1 — Migración y modelos
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_20_000001_create_bank_ledger_tables.php`
- `back-laravel/app/Modules/Finance/Models/{BankPeriod,BankMovement,BankOperation}.php`
- `back-laravel/app/Modules/Finance/Enums/{BankPeriodStatus,BankOperationType,BankOperationStatus}.php`
**Descripción:**
1. DDL de design §6; `BankMovement` inmutable; morph map `bank_operation`, `bank_movement`.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-2 — Ledger bancario y periodos
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Data/BankMovementData.php`
- `back-laravel/app/Modules/Finance/Services/{BankLedgerService,BankPeriodService}.php`
- `back-laravel/app/Modules/Finance/Exceptions/BankLedgerException.php`
- `back-laravel/app/Modules/Finance/Services/BankAccountService.php`
**Descripción:**
1. Leer primero `CashLedgerService.php` (spec 018) y seguir el mismo patrón (transacción obligatoria, bloqueo, inmutabilidad).
2. `register`, `reverse`, `openInitial`, `close` (design §2).
3. Implementar `BankAccountService::hasMovements()`.
**Tests:**
- Cubiertos en T-6.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-3 — Consulta, movimientos manuales y periodos (API)
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Services/BankQueryService.php`
- `back-laravel/app/Modules/Finance/Controllers/{BankAccountSummaryController,BankMovementController,BankPeriodController}.php`
- `back-laravel/app/Modules/Finance/Requests/{OpenBankPeriodRequest,CloseBankPeriodRequest,StoreBankMovementRequest,ListBankMovementsRequest}.php`
- `back-laravel/app/Modules/Finance/Resources/{BankAccountSummaryResource,BankMovementResource,BankPeriodResource}.php`
- `back-laravel/routes/api.php`
**Descripción:**
1. Summary, movimientos con saldo inicial/final, alta manual con comprobante privado, descarga autenticada y anulación.
2. Rutas de design §3.
**Tests:**
- Cubiertos en T-6.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Operaciones: depósito, retiro y transferencia
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Services/BankOperationService.php`
- `back-laravel/app/Modules/Finance/Controllers/BankOperationController.php`
- `back-laravel/app/Modules/Finance/Requests/StoreBankOperationRequest.php`
- `back-laravel/app/Modules/Finance/Resources/BankOperationResource.php`
**Descripción:**
1. Leer primero `CashTransferService.php` (spec 019).
2. Tres tipos de operación y anulación atómica (design §2).
**Tests:**
- Cubiertos en T-7.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-5 — Comando de verificación, permisos y parámetro
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-3, T-4
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Console/VerifyBankBalancesCommand.php`
- `back-laravel/database/seeders/{RolePermissionSeeder,SystemParameterSeeder}.php`
**Descripción:**
1. Comando registrado en `FinanceServiceProvider`.
2. Permisos de design §5 y parámetro `finance.allow_negative_bank_balance`.
**Tests:**
- Cubiertos en T-7.
**Criterios de done:**
- [x] `make seed` sin errores.

---
### T-6 — Tests del ledger, periodos y movimientos
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-5
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Finance/{BankLedgerServiceTest,BankPeriodsApiTest,BankMovementsApiTest}.php`
**Descripción:**
1. Casos de design §9 (ledger, periodos, movimientos).
**Tests:**
- Los tres archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Bank` en verde.

---
### T-7 — Tests de operaciones y verificación
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-5
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Finance/{BankOperationsApiTest,VerifyBankBalancesCommandTest}.php`
**Descripción:**
1. Casos de design §9 (operaciones y comando), incluida la atomicidad de la anulación.
**Tests:**
- Los dos archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=BankOperations` en verde; `make test` completo en verde.

---
### T-8 — Front: resumen de bancos y operaciones
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `front-vue/src/pages/finance/banks/index.vue`
- `front-vue/src/modules/finance/services/banksApi.js`, `stores/banksStore.js`
- `front-vue/src/modules/finance/components/banks/BankOperationDialog.vue`
- `front-vue/src/pages/finance/cash/index.vue`, `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Tarjetas por cuenta y diálogo de operación con campos dinámicos (design §8).
2. Botón "Depositar en banco" en la pantalla de caja.
3. Menú "Finanzas" › "Bancos".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-9 — Front: detalle de cuenta, movimientos y periodos
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-3, T-8
**Archivos clave:**
- `front-vue/src/pages/finance/banks/[id].vue`
- `front-vue/src/modules/finance/components/banks/{BankMovementDialog,CloseBankPeriodDialog}.vue`
**Descripción:**
1. Pestañas Movimientos · Periodos · Operaciones; subida de comprobante con `FormData`; descarga autenticada vía `$api` con `blob`.
2. "Abrir cuenta" cuando no hay periodos.
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
