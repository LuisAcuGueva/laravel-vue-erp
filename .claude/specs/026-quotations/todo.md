# 026 · quotations — TODO
> **Spec:** 026 · **Alcance:** fullstack · **Módulo:** Sales · **Prioridad:** media · **Depende de specs:** 004, 010, 012, 014, 015, 022, 025
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
| T-1 | ✅ | back | M | `Sales/Services/{SalePricingService,DiscountPolicyService}.php`, `Exceptions/DiscountLimitExceededException.php`, `tests/Unit/Modules/Sales/DiscountPolicyServiceTest.php` | — |
| T-2 | ✅ | back | S | `migrations/2026_10_20_000004_create_quotations_tables.php`, `Sales/Models/{Quotation,QuotationItem}.php`, `Enums/QuotationStatus.php`, `Providers/SalesServiceProvider.php` | — |
| T-3 | ✅ | back | M | `Sales/Services/QuotationService.php`, `Requests/*`, `Resources/*`, `Controllers/QuotationController.php`, `routes/api.php` | T-1, T-2 |
| T-4 | ✅ | back | M | `Sales/Services/QuotationWorkflowService.php`, `Console/ExpireQuotationsCommand.php`, `routes/console.php`, `resources/views/pdf/sales/quotation.blade.php`, hooks, seeders | T-3 |
| T-5 | ✅ | back | M | `tests/Feature/Modules/Sales/{QuotationsApiTest,ExpireQuotationsCommandTest}.php` | T-4 |
| T-6 | ✅ | front | M | `pages/sales/quotations/index.vue`, `modules/sales/{services/quotationsApi.js,stores/quotationsStore.js}`, menú | T-3 |
| T-7 | ✅ | front | M | `pages/sales/quotations/{new,[id]}.vue`, `modules/sales/components/quotations/QuotationForm.vue`, `pages/clients/[id].vue` | T-4, T-6 |

---
## Detalle de Tareas
### T-1 — Política de descuentos y precios de lista
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/{SalePricingService,DiscountPolicyService}.php`
- `back-laravel/app/Modules/Sales/Exceptions/{SalesRuleException,DiscountLimitExceededException}.php`
- `back-laravel/tests/Unit/Modules/Sales/DiscountPolicyServiceTest.php`
**Descripción:**
1. `listPrice()` con mayorista por cantidad mínima y conversión de moneda.
2. `validate()` con descuento efectivo y topes leídos de `SystemParameterService` (design §2).
**Tests:**
- `DiscountPolicyServiceTest`.
**Criterios de done:**
- [x] `make test-filter FILTER=DiscountPolicy` en verde; `vendor/bin/pint --test` en verde.

---
### T-2 — Migración, modelos y provider del módulo Sales
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_20_000004_create_quotations_tables.php`
- `back-laravel/app/Modules/Sales/Models/{Quotation,QuotationItem}.php`
- `back-laravel/app/Modules/Sales/Enums/QuotationStatus.php`
- `back-laravel/app/Modules/Sales/Providers/SalesServiceProvider.php`, `bootstrap/providers.php`
**Descripción:**
1. DDL de design §6; enum con `canTransitionTo()`; morph map `quotation`.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-3 — Servicio, controller y rutas de cotizaciones
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1, T-2
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/QuotationService.php`
- `back-laravel/app/Modules/Sales/Requests/{StoreQuotationRequest,ListQuotationsRequest}.php`
- `back-laravel/app/Modules/Sales/Resources/{QuotationResource,QuotationItemResource}.php`
- `back-laravel/app/Modules/Sales/Controllers/QuotationController.php`
- `back-laravel/routes/api.php`
**Descripción:**
1. `create/update/duplicate/availability` (design §2) con `DocumentTotalsCalculator` y `DiscountPolicyService`.
2. Visibilidad sin `view_all`; `meta.totals_by_status`.
3. Rutas de design §3.
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Flujo, vencimiento, PDF, permisos y hooks
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/QuotationWorkflowService.php`
- `back-laravel/app/Modules/Sales/Console/ExpireQuotationsCommand.php`, `back-laravel/routes/console.php`
- `back-laravel/resources/views/pdf/sales/quotation.blade.php`
- `back-laravel/database/seeders/RolePermissionSeeder.php`
- `back-laravel/app/Modules/Clients/Services/ClientService.php`, `Maintenance/Services/EmployeeService.php`, `Inventory/Services/CatalogItemService.php`
**Descripción:**
1. Transiciones, `markConverted()` y `expireOverdue()`; comando programado (leer primero `routes/console.php`).
2. Vista PDF que extiende el layout del spec 022.
3. Permisos de design §5 y hooks de borrado (cliente, empleado vendedor, ítem).
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [x] `make artisan CMD="schedule:list"` muestra `sales:expire-quotations`.

---
### T-5 — Tests de cotizaciones
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Sales/{QuotationsApiTest,ExpireQuotationsCommandTest}.php`
**Descripción:**
1. Casos de design §9.
**Tests:**
- Los dos archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Quotation` en verde; `make test` completo en verde.

---
### T-6 — Front: listado de cotizaciones
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `front-vue/src/pages/sales/quotations/index.vue`
- `front-vue/src/modules/sales/services/quotationsApi.js`, `stores/quotationsStore.js`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Filtros, tarjetas por estado y tabla (design §8).
2. Grupo de menú "Ventas" › "Cotizaciones".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-7 — Front: formulario y detalle
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4, T-6
**Archivos clave:**
- `front-vue/src/pages/sales/quotations/{new,[id]}.vue`
- `front-vue/src/modules/sales/components/quotations/QuotationForm.vue`
- `front-vue/src/pages/clients/[id].vue`
**Descripción:**
1. Cabecera y `DocumentLinesTable` con precio de lista, descuento con tope en vivo y stock (design §8).
2. Acciones por estado, duplicar y PDF.
3. Pestaña "Cotizaciones" en el detalle del cliente.
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
