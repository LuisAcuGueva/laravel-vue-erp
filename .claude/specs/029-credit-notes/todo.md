# 029 · credit-notes — TODO
> **Spec:** 029 · **Alcance:** fullstack · **Módulo:** Sales · **Prioridad:** media · **Depende de specs:** 015, 018, 024, 027, 028
> **Status:** Complete
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
| T-1 | ✅ | back | S | `migrations/2026_10_20_000008_create_credit_notes_tables.php`, `Sales/Models/{CreditNote,CreditNoteItem,CreditNoteInstallmentAdjustment}.php`, `Enums/*` | — |
| T-2 | ✅ | back | M | `Sales/Services/CreditNoteSettlementService.php` | T-1 |
| T-3 | ✅ | back | M | `Sales/Services/CreditNoteIssuanceService.php`, `Requests/StoreCreditNoteRequest.php` | T-2 |
| T-4 | ✅ | back | M | `Sales/Services/CreditNoteCancellationService.php`, `Controllers/CreditNoteController.php`, `Resources/*`, vistas PDF, `routes/api.php`, hooks, seeders | T-3 |
| T-5 | ✅ | back | M | `tests/Feature/Modules/Sales/{CreditNotesApiTest,CreditNoteSettlementTest}.php` | T-4 |
| T-6 | ✅ | front | M | `pages/sales/credit-notes/new.vue`, `modules/sales/{services/creditNotesApi.js,stores/creditNotesStore.js,components/credit-notes/CreditNoteForm.vue}`, botón en `pages/sales/history/[id].vue` | T-4 |
| T-7 | ✅ | front | S | `pages/sales/credit-notes/{index,[id]}.vue`, menú | T-6 |

---
## Detalle de Tareas
### T-1 — Migración y modelos
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_20_000008_create_credit_notes_tables.php`
- `back-laravel/app/Modules/Sales/Models/{CreditNote,CreditNoteItem,CreditNoteInstallmentAdjustment}.php`
- `back-laravel/app/Modules/Sales/Enums/{CreditNoteReason,CreditNoteStatus,CreditNoteSettlementMode}.php`
**Descripción:**
1. DDL de design §6; morph map `credit_note`; `CreditNoteReason` con descripción del catálogo 09.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-2 — Aplicación del monto (saldo y reembolso)
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/CreditNoteSettlementService.php`
**Descripción:**
1. Leer primero `CollectionService.php` (spec 028) y `MoneySettlementService.php` (spec 024).
2. `apply()` (auto/refund, reducción de cuotas desde la última con ajustes registrados) y `revert()` (design §2).
**Tests:**
- Cubiertos en T-5 (`CreditNoteSettlementTest`).
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-3 — Emisión de notas de crédito
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/CreditNoteIssuanceService.php`
- `back-laravel/app/Modules/Sales/Requests/StoreCreditNoteRequest.php`
**Descripción:**
1. Leer primero `SaleIssuanceService.php` (spec 027).
2. Pasos 1–7 de design §2 por motivo (01, 04, 05, 06, 07, 09), con stock al costo original y job electrónico después del commit.
3. `prefill` (líneas pendientes, máximo acreditable, series).
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Anulación, API, PDF, permisos y hooks
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/CreditNoteCancellationService.php`
- `back-laravel/app/Modules/Sales/Controllers/CreditNoteController.php`
- `back-laravel/app/Modules/Sales/Resources/{CreditNoteResource,CreditNoteItemResource}.php`, `Requests/ListCreditNotesRequest.php`
- `back-laravel/resources/views/pdf/sales/{credit-note-a4,credit-note-ticket}.blade.php`
- `back-laravel/routes/api.php`, `back-laravel/database/seeders/RolePermissionSeeder.php`
- `back-laravel/app/Modules/Sales/Services/{SaleCancellationService,ReceivableQueryService}.php`, `Jobs/SendElectronicDocumentJob.php`
**Descripción:**
1. Anulación con reversión de stock y aplicación.
2. Rutas de design §3, PDF con comprobante modificado y motivo; permisos `credit_notes.*`.
3. Hooks: `hasCreditNotes()`, abonos en el estado de cuenta y soporte de `CreditNote` en el job.
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-5 — Tests
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Sales/{CreditNotesApiTest,CreditNoteSettlementTest}.php`
**Descripción:**
1. Casos de design §9 y verificación final de stock, caja y saldos.
**Tests:**
- Los dos archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=CreditNote` en verde; `make test` completo en verde.

---
### T-6 — Front: emisión desde la venta
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `front-vue/src/pages/sales/credit-notes/new.vue`
- `front-vue/src/modules/sales/{services/creditNotesApi.js,stores/creditNotesStore.js,components/credit-notes/CreditNoteForm.vue}`, botón en `pages/sales/history/[id].vue`
**Descripción:**
1. Formulario dinámico por motivo con vista previa de rebaja y reembolso (design §8).
2. Botón "Emitir nota de crédito" en el detalle de la venta.
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-7 — Front: listado y detalle
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-6
**Archivos clave:**
- `front-vue/src/pages/sales/credit-notes/{index,[id]}.vue`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Listado con filtros y detalle con PDF, anulación y reintento electrónico.
2. Menú "Ventas" › "Notas de crédito".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
