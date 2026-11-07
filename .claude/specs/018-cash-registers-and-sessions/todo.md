# 018 · cash-registers-and-sessions — TODO
> **Spec:** 018 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** media · **Depende de specs:** 002, 011, 014, 017
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
| T-1 | ✅ | back | S | `migrations/2026_10_19_000001_create_cash_tables.php`, `Finance/Models/{Box,BoxRole,CashSession,CashCountDetail,CashMovement}.php`, `Enums/*` | — |
| T-2 | ✅ | back | M | `Finance/Services/{BoxService,BoxAccessService}.php`, `Controllers/BoxController.php`, requests/resources, `BranchService.php`, `routes/api.php` | T-1 |
| T-3 | ✅ | back | M | `Finance/Data/CashMovementData.php`, `Services/{CashLedgerService,CashSessionSummaryService}.php`, `Exceptions/*` | T-1 |
| T-4 | ✅ | back | M | `Finance/Services/CashSessionService.php`, `Controllers/{CashSessionController,CashMovementController}.php`, requests/resources | T-2, T-3 |
| T-5 | ✅ | back | S | `RolePermissionSeeder.php`, `SystemParameterSeeder.php` | T-4 |
| T-6 | ✅ | back | M | `tests/Feature/Modules/Finance/{BoxesApiTest,CashSessionsApiTest,CashMovementsApiTest,CashLedgerServiceTest}.php` | T-5 |
| T-7 | ✅ | front | M | `pages/finance/boxes/index.vue`, `modules/finance/{services/cashApi.js,stores/cashStore.js,composables/useCashSession.js}` | T-2 |
| T-8 | ✅ | front | M | `pages/finance/cash/index.vue`, `components/cash/{CashMovementDialog,CashCountDialog,CashSummaryCards}.vue` | T-4, T-7 |
| T-9 | ✅ | front | S | `pages/finance/cash/sessions/{index,[id]}.vue`, `components/cash/CashReviewDialog.vue`, menú | T-8 |

---
## Detalle de Tareas
### T-1 — Migración y modelos de caja
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_19_000001_create_cash_tables.php`
- `back-laravel/app/Modules/Finance/Models/{Box,BoxRole,CashSession,CashCountDetail,CashMovement}.php`
- `back-laravel/app/Modules/Finance/Enums/{CashSessionStatus,CashReviewStatus}.php`
**Descripción:**
1. DDL de design §6 (índice parcial de sesión abierta por caja).
2. `CashMovement` inmutable (mismo patrón que `KardexMovement`, spec 015).
3. Morph map `cash_session`, `cash_movement`; relación `Branch::boxes()`.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-2 — Cajas y acceso por rol
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Services/{BoxService,BoxAccessService}.php`
- `back-laravel/app/Modules/Finance/Controllers/BoxController.php`
- `back-laravel/app/Modules/Finance/Requests/{BoxRequest,SyncBoxRolesRequest,ListBoxesRequest}.php`
- `back-laravel/app/Modules/Finance/Resources/BoxResource.php`
- `back-laravel/app/Modules/Maintenance/Services/BranchService.php`, `back-laravel/routes/api.php`
**Descripción:**
1. CRUD, sincronización de roles, `available` y `suggestedOpening` (último `counted_cash` de la caja).
2. Bloqueos de desactivación/borrado (design §2) y de borrado de sede con cajas.
**Tests:**
- Cubiertos en T-6 (`BoxesApiTest`).
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-3 — Ledger de caja y resumen
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Data/CashMovementData.php`
- `back-laravel/app/Modules/Finance/Services/{CashLedgerService,CashSessionSummaryService}.php`
- `back-laravel/app/Modules/Finance/Exceptions/{CashSessionException,InsufficientCashException}.php`
**Descripción:**
1. `register`, `registerInformative`, `reverse`, `currentSession` con bloqueo de la sesión y exigencia de transacción (design §2).
2. `expectedCash` y `summary` con consultas agregadas.
**Tests:**
- Cubiertos en T-6 (`CashLedgerServiceTest`).
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Sesiones: apertura, movimientos, cierre y revisión
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2, T-3
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Services/CashSessionService.php`
- `back-laravel/app/Modules/Finance/Controllers/{CashSessionController,CashMovementController}.php`
- `back-laravel/app/Modules/Finance/Requests/{OpenCashSessionRequest,CloseCashSessionRequest,ReviewCashSessionRequest,StoreCashMovementRequest,ListCashSessionsRequest}.php`
- `back-laravel/app/Modules/Finance/Resources/{CashSessionResource,CashSessionSummaryResource,CashMovementResource}.php`
- `back-laravel/routes/api.php`
**Descripción:**
1. Implementar `open`, `close`, `review` (design §2) y los endpoints de movimientos manuales y anulación.
2. Autorización por sesión concreta con `BoxAccessService`.
3. Rutas de design §3.
**Tests:**
- Cubiertos en T-6.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-5 — Permisos y parámetros
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/database/seeders/{RolePermissionSeeder,SystemParameterSeeder}.php`
**Descripción:**
1. Permisos de design §5.
2. Parámetro `finance.cash_denominations`.
3. Seeder de dev: caja `CAJA01` en la sede principal con el rol `user` asignado.
**Tests:**
- `make seed` sin errores ni duplicados.
**Criterios de done:**
- [x] `make migrate-fresh` + `make seed` en verde.

---
### T-6 — Tests de caja
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-5
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Finance/{BoxesApiTest,CashSessionsApiTest,CashMovementsApiTest,CashLedgerServiceTest}.php`
**Descripción:**
1. Casos de design §9.
**Tests:**
- Los cuatro archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Cash` y `FILTER=Boxes` en verde; `make test` completo en verde.

---
### T-7 — Front: cajas y store de caja
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `front-vue/src/pages/finance/boxes/index.vue`
- `front-vue/src/modules/finance/services/cashApi.js`, `stores/cashStore.js`
- `front-vue/src/modules/finance/composables/useCashSession.js`
**Descripción:**
1. CRUD de cajas con asignación de roles.
2. `cashStore` con `selectedBoxId` persistido y `useCashSession()` (design §8).
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-8 — Front: operación diaria de caja
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4, T-7
**Archivos clave:**
- `front-vue/src/pages/finance/cash/index.vue`
- `front-vue/src/modules/finance/components/cash/{CashMovementDialog,CashCountDialog,CashSummaryCards}.vue`
**Descripción:**
1. Apertura, tarjetas de resumen, movimientos, ingreso/egreso manual y arqueo con denominaciones (design §8).
2. Errores 409/422 con `useNotifications` y `useApiFormErrors`.
**Tests:**
- `npm run lint`; verificación manual del ciclo apertura → movimientos → cierre.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-9 — Front: historial, detalle y revisión
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-8
**Archivos clave:**
- `front-vue/src/pages/finance/cash/sessions/{index,[id]}.vue`
- `front-vue/src/modules/finance/components/cash/CashReviewDialog.vue`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Historial con filtros y bandeja de pendientes; detalle con resumen y arqueo.
2. Menú "Finanzas" › "Caja", "Historial de caja", "Cajas".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
