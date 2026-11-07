# 019 · cash-transfers — TODO
> **Spec:** 019 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** media · **Depende de specs:** 018
> **Status:** Completed
> **Última actualización:** 2026-10-19
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
| T-1 | ✅ | back | S | `migrations/2026_10_19_000003_create_cash_transfers_table.php`, `Finance/Models/CashTransfer.php`, `Enums/CashTransferStatus.php` | — |
| T-2 | ✅ | back | M | `Finance/Services/CashTransferService.php`, `CashSessionSummaryService.php`, `Exceptions/CashTransferException.php` | T-1 |
| T-3 | ✅ | back | S | `Controllers/CashTransferController.php`, requests/resources, `routes/api.php`, `RolePermissionSeeder.php` | T-2 |
| T-4 | ✅ | back | M | `tests/Feature/Modules/Finance/CashTransfersApiTest.php` | T-3 |
| T-5 | ✅ | front | M | `pages/finance/cash/transfers/index.vue`, `components/cash/CashTransferDialog.vue`, `services/cashTransfersApi.js`, `stores/cashTransfersStore.js`, menú | T-3 |

---
## Detalle de Tareas
### T-1 — Migración, modelo y enum
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_19_000003_create_cash_transfers_table.php`
- `back-laravel/app/Modules/Finance/Models/CashTransfer.php`
- `back-laravel/app/Modules/Finance/Enums/CashTransferStatus.php`
**Descripción:**
1. DDL de design §6; enum con `canTransitionTo()`; morph map `cash_transfer`.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-2 — Servicio de transferencias
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Services/CashTransferService.php`
- `back-laravel/app/Modules/Finance/Services/CashSessionSummaryService.php`
- `back-laravel/app/Modules/Finance/Exceptions/CashTransferException.php`
**Descripción:**
1. Leer primero `CashLedgerService.php` y `CashSessionSummaryService.php` (spec 018).
2. `availableCash()` y `create/approve/reject/cancel` según design §2 (bloqueo de ambas sesiones ordenado por `id`).
**Tests:**
- Cubiertos en T-4.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-3 — Controller, rutas y permisos
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Controllers/CashTransferController.php`
- `back-laravel/app/Modules/Finance/Requests/{StoreCashTransferRequest,ListCashTransfersRequest}.php`
- `back-laravel/app/Modules/Finance/Resources/CashTransferResource.php`
- `back-laravel/routes/api.php`, `back-laravel/database/seeders/RolePermissionSeeder.php`
**Descripción:**
1. Rutas y visibilidad de design §3; `pendingCount`.
2. Permisos `cash_transfers.*`.
**Tests:**
- Cubiertos en T-4.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Tests
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Finance/CashTransfersApiTest.php`
**Descripción:**
1. Casos de design §9.
**Tests:**
- `CashTransfersApiTest`.
**Criterios de done:**
- [x] `make test-filter FILTER=CashTransfers` en verde; `make test` completo en verde.

---
### T-5 — Front: transferencias
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `front-vue/src/pages/finance/cash/transfers/index.vue`
- `front-vue/src/modules/finance/components/cash/CashTransferDialog.vue`
- `front-vue/src/modules/finance/services/cashTransfersApi.js`, `stores/cashTransfersStore.js`
- `front-vue/src/pages/finance/cash/index.vue`, `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Diálogo desde la pantalla de caja; página con pestañas y acciones (design §8).
2. Badge de pendientes en el menú.
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
