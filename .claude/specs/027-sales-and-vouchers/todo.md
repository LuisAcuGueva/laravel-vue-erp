# 027 · sales-and-vouchers — TODO
> **Spec:** 027 · **Alcance:** fullstack · **Módulo:** Sales · **Prioridad:** media · **Depende de specs:** 004, 011, 015, 018, 020, 024, 025, 026
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
| T-1 | ✅ | back | S | `app/Support/Text/NumberToWords.php`, `tests/Unit/Support/NumberToWordsTest.php` | — |
| T-2 | ✅ | back | S | `migrations/2026_10_20_000006_create_sales_tables.php`, `Sales/Models/{DocumentSeries,Sale,SaleItem,SalePayment}.php`, `Sales/Enums/*` | — |
| T-3 | ✅ | back | M | `Sales/Services/DocumentSeriesService.php`, `Controllers/DocumentSeriesController.php`, `Requests/DocumentSeriesRequest.php`, `Resources/DocumentSeriesResource.php`, `BranchService.php`, `tests/Feature/Modules/Sales/DocumentSeriesApiTest.php` | T-2 |
| T-4 | ✅ | back | M | `Sales/Services/{CreditPolicyService,SaleIssuanceService}.php`, `Events/SaleIssued.php` | T-3 |
| T-5 | ✅ | back | M | `Sales/Services/{SaleService,SaleQueryService}.php`, `Controllers/SaleController.php`, `Requests/{StoreSaleRequest,ListSalesRequest}.php`, `Resources/*`, `routes/api.php`, seeders | T-4 |
| T-6 | ✅ | back | M | `Sales/Services/SaleCancellationService.php`, `Events/SaleCancelled.php`, hooks en `ClientService`/`EmployeeService`/`CatalogItemService`/`ClientResource` | T-5 |
| T-7 | ✅ | back | M | `config/einvoicing.php`, `Sales/EInvoicing/*`, `Sales/Jobs/SendElectronicDocumentJob.php`, `.env.example` | T-5 |
| T-8 | ✅ | back | M | `resources/views/pdf/sales/{voucher-a4,voucher-ticket}.blade.php`, `SaleController::pdf` | T-1, T-5 |
| T-9 | ✅ | back | M | `tests/Feature/Modules/Sales/SalesApiTest.php` | T-5 |
| T-10 | ✅ | back | M | `tests/Feature/Modules/Sales/{SaleCancellationApiTest,SendElectronicDocumentJobTest}.php` | T-6, T-7 |
| T-11 | ✅ | front | S | `pages/sales/document-series/index.vue`, `modules/sales/services/documentSeriesApi.js` | T-3 |
| T-12 | ✅ | front | M | `pages/sales/pos/index.vue`, `modules/sales/{stores/posStore.js,services/salesApi.js,components/pos/PosCartTable.vue}` | T-5 |
| T-13 | ✅ | front | M | `modules/sales/components/pos/{PosPaymentDialog,VoucherPreviewDialog}.vue`, botón "Convertir en venta" en cotización | T-8, T-12 |
| T-14 | ✅ | front | M | `pages/sales/history/{index,[id]}.vue`, `stores/salesStore.js`, pestaña Ventas en cliente, menú | T-6, T-8 |

---
## Detalle de Tareas
### T-1 — Montos en letras
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Support/Text/NumberToWords.php`
- `back-laravel/tests/Unit/Support/NumberToWordsTest.php`
**Descripción:**
1. Conversión en español con casos especiales (uno/un, veintiún, cien/ciento, mil, millón/millones) y formato `SON: … CON NN/100 SOLES`.
**Tests:**
- `NumberToWordsTest` (design §9).
**Criterios de done:**
- [x] `make test-filter FILTER=NumberToWords` en verde; `vendor/bin/pint --test` en verde.

---
### T-2 — Migración, modelos y enums de ventas
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_20_000006_create_sales_tables.php`
- `back-laravel/app/Modules/Sales/Models/{DocumentSeries,Sale,SaleItem,SalePayment}.php`
- `back-laravel/app/Modules/Sales/Enums/{SalesDocumentType,SaleStatus,EInvoiceStatus}.php`
**Descripción:**
1. DDL de design §6 (incluida la FK `quotations.converted_sale_id`).
2. Morph map `sale`, `sale_payment`.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-3 — Series y correlativos
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/DocumentSeriesService.php`
- `back-laravel/app/Modules/Sales/Controllers/DocumentSeriesController.php`
- `back-laravel/app/Modules/Sales/Requests/DocumentSeriesRequest.php`
- `back-laravel/app/Modules/Sales/Resources/DocumentSeriesResource.php`
- `back-laravel/app/Modules/Maintenance/Services/BranchService.php`
- `back-laravel/tests/Feature/Modules/Sales/DocumentSeriesApiTest.php`
**Descripción:**
1. CRUD con formato por tipo, `available()` y `next()` con bloqueo (design §2).
2. Bloqueo de borrado de sede con series.
**Tests:**
- `DocumentSeriesApiTest`.
**Criterios de done:**
- [x] `make test-filter FILTER=DocumentSeries` en verde.

---
### T-4 — Emisión de ventas y política de crédito
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/{CreditPolicyService,SaleIssuanceService}.php`
- `back-laravel/app/Modules/Sales/Events/SaleIssued.php`
**Descripción:**
1. Leer primero `MoneySettlementService.php`, `StockLedgerService.php`, `DiscountPolicyService.php`, `SalePricingService.php` y `QuotationWorkflowService.php`.
2. Implementar los 12 pasos de `issue()` (design §2) en una sola transacción, con eventos y job después del commit.
**Tests:**
- Cubiertos en T-9.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-5 — API de ventas, consultas, permisos y parámetros
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/{SaleService,SaleQueryService}.php`
- `back-laravel/app/Modules/Sales/Controllers/SaleController.php`
- `back-laravel/app/Modules/Sales/Requests/{StoreSaleRequest,ListSalesRequest}.php`
- `back-laravel/app/Modules/Sales/Resources/{SaleResource,SaleItemResource,SalePaymentResource}.php`
- `back-laravel/routes/api.php`, `back-laravel/database/seeders/{RolePermissionSeeder,SystemParameterSeeder}.php`
**Descripción:**
1. `prefill` desde cotización, listado con `meta.totals`, detalle con costos condicionados a `sales.view_costs`.
2. Rutas de design §3; permisos y parámetros de §5; series de ejemplo en dev.
**Tests:**
- Cubiertos en T-9.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-6 — Anulación y hooks
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-5
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/SaleCancellationService.php`
- `back-laravel/app/Modules/Sales/Events/SaleCancelled.php`
- `back-laravel/app/Modules/Clients/Services/ClientService.php`, `back-laravel/app/Modules/Clients/Resources/ClientResource.php`
- `back-laravel/app/Modules/Maintenance/Services/EmployeeService.php`, `back-laravel/app/Modules/Inventory/Services/CatalogItemService.php`
**Descripción:**
1. `cancel()` con reversión de kardex y pagos (design §2) y hooks `hasCollections()`/`hasCreditNotes()` en `false`.
2. `ClientResource.account.debt` con `CreditPolicyService::debt()`; bloqueos de borrado por ventas.
**Tests:**
- Cubiertos en T-10.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-7 — Facturación electrónica desacoplada
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-5
**Archivos clave:**
- `back-laravel/config/einvoicing.php`, `back-laravel/.env.example`
- `back-laravel/app/Modules/Sales/EInvoicing/{ElectronicInvoicingDriver,EInvoiceResult,EInvoicingManager}.php`, `EInvoicing/Drivers/NullDriver.php`
- `back-laravel/app/Modules/Sales/Jobs/SendElectronicDocumentJob.php`
**Descripción:**
1. Contrato, driver nulo, manager y job en cola (design §2); endpoint de reintento.
2. Leer primero la configuración de colas del spec 004 (`config/queue.php`).
**Tests:**
- Cubiertos en T-10.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-8 — Comprobantes PDF (A4 y ticket)
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1, T-5
**Archivos clave:**
- `back-laravel/resources/views/pdf/sales/{voucher-a4,voucher-ticket}.blade.php`
- `back-laravel/app/Modules/Sales/Controllers/SaleController.php`
**Descripción:**
1. Vistas que extienden el layout del spec 022 (A4) y una vista de ticket de 80 mm con alto dinámico (`setPaper([0, 0, 226.77, $height])`).
2. Monto en letras, bases por tipo de impuesto y marca de agua "ANULADO".
**Tests:**
- Caso de PDF en `SalesApiTest` (T-9).
**Criterios de done:**
- [x] El PDF abre en el navegador con `?inline=1`.

---
### T-9 — Tests de emisión
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-5
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Sales/SalesApiTest.php`
**Descripción:**
1. Casos de emisión de design §9 (contado, crédito, reglas del comprobante, stock, correlativos, descuentos, cotización, sesión).
**Tests:**
- `SalesApiTest`.
**Criterios de done:**
- [x] `make test-filter FILTER=SalesApiTest` en verde.

---
### T-10 — Tests de anulación y facturación electrónica
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-6, T-7
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Sales/{SaleCancellationApiTest,SendElectronicDocumentJobTest}.php`
**Descripción:**
1. Casos de design §9 (anulación y job con driver nulo y driver falso).
**Tests:**
- Los dos archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Sale` en verde; `make test` completo en verde.

---
### T-11 — Front: series
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-3
**Archivos clave:**
- `front-vue/src/pages/sales/document-series/index.vue`
- `front-vue/src/modules/sales/services/documentSeriesApi.js`
**Descripción:**
1. CRUD con validación de formato por tipo y asignación opcional de caja.
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-12 — Front: POS (carrito y búsqueda)
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-5
**Archivos clave:**
- `front-vue/src/pages/sales/pos/index.vue`
- `front-vue/src/modules/sales/stores/posStore.js`, `services/salesApi.js`
- `front-vue/src/modules/sales/components/pos/PosCartTable.vue`
**Descripción:**
1. Barra superior (caja, comprobante y serie, cliente, vendedor), búsqueda con lector y carrito con descuentos y tope (design §8).
2. Atajos de teclado y persistencia del carrito en `sessionStorage`.
**Tests:**
- `npm run lint`; Vitest de `posStore` (totales y persistencia) con `make test-front`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `make test-front` en verde.

---
### T-13 — Front: cobro, comprobante y conversión desde cotización
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-8, T-12
**Archivos clave:**
- `front-vue/src/modules/sales/components/pos/{PosPaymentDialog,VoucherPreviewDialog}.vue`
- `front-vue/src/pages/sales/quotations/[id].vue`
**Descripción:**
1. Diálogo de cobro con varios pagos, vuelto y crédito (design §8).
2. Vista previa del ticket tras emitir.
3. Botón "Convertir en venta" en la cotización (`/sales/pos?quotation_id=`).
**Tests:**
- `npm run lint`; verificación manual del ciclo completo en el POS.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-14 — Front: historial de ventas
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-6, T-8
**Archivos clave:**
- `front-vue/src/pages/sales/history/{index,[id]}.vue`
- `front-vue/src/modules/sales/stores/salesStore.js`
- `front-vue/src/pages/clients/[id].vue`, `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Listado con totales, detalle con costos condicionados, PDF, anulación y reintento electrónico.
2. Pestaña "Ventas" en el cliente; menú "Ventas" › "Punto de venta", "Ventas", "Series".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
