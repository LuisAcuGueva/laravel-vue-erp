# 028 · accounts-receivable — TODO
> **Spec:** 028 · **Alcance:** fullstack · **Módulo:** Sales · **Prioridad:** media · **Depende de specs:** 018, 020, 024, 025, 027
> **Status:** Draft
> **Última actualización:** 2026-10-15
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
| T-1 | 🔧 | back | S | `migrations/2026_10_20_000004_create_receivables_tables.php`, `Sales/Models/{ReceivableInstallment,Collection,CollectionAllocation}.php`, `Enums/*` | — |
| T-2 | 🔧 | back | M | `Sales/Services/InstallmentScheduleService.php`, `Sales/Hooks/CreateReceivableInstallments.php`, `Listeners/CancelReceivableInstallments.php`, `StoreSaleRequest.php`, `SalesServiceProvider.php` | T-1 |
| T-3 | 🔧 | back | M | `Sales/Services/CollectionService.php`, `Requests/StoreCollectionRequest.php`, `Resources/CollectionResource.php`, `SaleCancellationService.php`, `CreditPolicyService.php` | T-2 |
| T-4 | 🔧 | back | M | `Sales/Services/ReceivableQueryService.php`, `Controllers/{CollectionController,ReceivableController,ClientStatementController}.php`, vistas PDF, `routes/api.php`, seeders | T-3 |
| T-5 | 🔧 | back | M | `tests/Feature/Modules/Sales/{ReceivableInstallmentsTest,CollectionsApiTest,ReceivablesReportsApiTest}.php` | T-4 |
| T-6 | 🔧 | front | M | `modules/sales/components/receivables/{InstallmentPlanEditor,CollectionDialog}.vue`, `components/pos/PosPaymentDialog.vue`, `pages/finance/cash/index.vue` | T-4 |
| T-7 | 🔧 | front | M | `pages/sales/receivables/index.vue`, `modules/sales/{services/receivablesApi.js,stores/receivablesStore.js,components/receivables/RescheduleDialog.vue}`, `pages/sales/history/[id].vue`, `pages/clients/[id].vue`, menú | T-6 |

---
## Detalle de Tareas
### T-1 — Migración y modelos
**Estado:** 🔧 · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_20_000004_create_receivables_tables.php`
- `back-laravel/app/Modules/Sales/Models/{ReceivableInstallment,Collection,CollectionAllocation}.php`
- `back-laravel/app/Modules/Sales/Enums/{InstallmentStatus,CollectionStatus}.php`
**Descripción:**
1. DDL de design §6; relación `Sale::installments()`; morph map `collection`.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [ ] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-2 — Cronograma de cuotas atómico con la venta
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/InstallmentScheduleService.php`
- `back-laravel/app/Modules/Sales/Hooks/CreateReceivableInstallments.php`
- `back-laravel/app/Modules/Sales/Listeners/CancelReceivableInstallments.php`
- `back-laravel/app/Modules/Sales/Requests/StoreSaleRequest.php`
- `back-laravel/app/Modules/Sales/Providers/SalesServiceProvider.php`
**Descripción:**
1. Leer primero `SaleIssuanceService::afterIssue()` y `Contracts/AfterSaleIssuedHook.php` (spec 027).
2. `build`, `validate`, `createFor`, `reschedule` (design §2); hook etiquetado `sales.after_issue`; listener de `SaleCancelled`.
3. Ampliar `StoreSaleRequest` con los campos de cronograma.
**Tests:**
- Cubiertos en T-5 (`ReceivableInstallmentsTest`); `SalesApiTest` sigue en verde.
**Criterios de done:**
- [ ] `make test-filter FILTER=SalesApiTest` en verde.

---
### T-3 — Cobranzas, hook de anulación y bloqueo por vencidas
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/CollectionService.php`
- `back-laravel/app/Modules/Sales/Requests/StoreCollectionRequest.php`
- `back-laravel/app/Modules/Sales/Resources/CollectionResource.php`
- `back-laravel/app/Modules/Sales/Services/{SaleCancellationService,CreditPolicyService}.php`
**Descripción:**
1. `create` (manual y `auto_allocate`) y `cancel` con `MoneySettlementService` (design §2); leer primero `SupplierPaymentService.php` (spec 024).
2. `hasCollections()` y el bloqueo de crédito por cuotas vencidas.
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [ ] `vendor/bin/pint --test` en verde.

---
### T-4 — Consultas, PDFs, rutas, permisos y parámetro
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/ReceivableQueryService.php`
- `back-laravel/app/Modules/Sales/Controllers/{CollectionController,ReceivableController,ClientStatementController}.php`
- `back-laravel/app/Modules/Sales/Requests/{RescheduleInstallmentsRequest,ListReceivablesRequest,ClientStatementRequest}.php`
- `back-laravel/app/Modules/Sales/Resources/{ReceivableInstallmentResource,ReceivableAgingResource}.php`
- `back-laravel/resources/views/pdf/sales/{collection-receipt,client-statement}.blade.php`
- `back-laravel/routes/api.php`, `back-laravel/database/seeders/{RolePermissionSeeder,SystemParameterSeeder}.php`
- `back-laravel/app/Modules/Clients/Resources/ClientResource.php`
**Descripción:**
1. Aging, cuotas abiertas, estado de cuenta (JSON y PDF), recibo de cobranza.
2. Rutas de design §3; permisos y parámetro de §5; `ClientResource.account` con cuotas vencidas.
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [ ] `vendor/bin/pint --test` en verde.

---
### T-5 — Tests
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Sales/{ReceivableInstallmentsTest,CollectionsApiTest,ReceivablesReportsApiTest}.php`
**Descripción:**
1. Casos de design §9.
**Tests:**
- Los tres archivos listados.
**Criterios de done:**
- [ ] `make test-filter FILTER=Receivable` y `FILTER=Collections` en verde; `make test` completo en verde.

---
### T-6 — Front: plan de cuotas en el POS y cobranza desde caja
**Estado:** 🔧 · **Subj:** front · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `front-vue/src/modules/sales/components/receivables/{InstallmentPlanEditor,CollectionDialog}.vue`
- `front-vue/src/modules/sales/components/pos/PosPaymentDialog.vue`
- `front-vue/src/pages/finance/cash/index.vue`
**Descripción:**
1. Editor de cuotas (única, N cuotas cada X días, manual) en el cobro al crédito.
2. Diálogo de cobranza con distribución manual o automática y `PaymentMethodFields`; botón "Cobrar" en la caja.
**Tests:**
- `npm run lint`; Vitest del reparto de cuotas iguales con redondeo.
**Criterios de done:**
- [ ] Front: `cd front-vue && npm run lint` y `make test-front` en verde.

---
### T-7 — Front: cuentas por cobrar, reprogramación y estado de cuenta
**Estado:** 🔧 · **Subj:** front · **Complejidad:** M · **Depende de:** T-6
**Archivos clave:**
- `front-vue/src/pages/sales/receivables/index.vue`
- `front-vue/src/modules/sales/services/receivablesApi.js`, `stores/receivablesStore.js`
- `front-vue/src/modules/sales/components/receivables/RescheduleDialog.vue`
- `front-vue/src/pages/sales/history/[id].vue`, `front-vue/src/pages/clients/[id].vue`, `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Pestañas Cuotas · Antigüedad · Cobranzas (design §8).
2. Pestaña "Cuotas" en el detalle de venta con reprogramación; "Estado de cuenta" en el cliente.
3. Menú "Ventas" › "Cuentas por cobrar".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [ ] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
