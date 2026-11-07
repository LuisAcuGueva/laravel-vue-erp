# 022 · purchase-requests-and-orders — TODO
> **Spec:** 022 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 010, 011, 014, 015, 021
> **Status:** Done
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
| T-1 | ✅ | back | M | `app/Support/Pricing/*`, `tests/Unit/Support/DocumentTotalsCalculatorTest.php` | — |
| T-2 | ✅ | infra | S | `composer.json`, `config/dompdf.php`, `app/Support/Pdf/PdfRenderer.php`, `resources/views/pdf/layouts/*`, `tests/Feature/Support/PdfRendererTest.php` | — |
| T-3 | ✅ | back | S | `migrations/2026_10_19_000002_*.php`, `Purchases/Models/{PurchaseRequest,PurchaseRequestItem,PurchaseOrder,PurchaseOrderItem}.php`, `Enums/*` | — |
| T-4 | ✅ | back | M | `Purchases/Services/PurchaseRequestService.php`, `Controllers/PurchaseRequestController.php`, requests/resources, `routes/api.php` | T-3 |
| T-5 | ✅ | back | M | `Purchases/Services/PurchaseOrderService.php`, `Controllers/PurchaseOrderController.php`, requests/resources | T-1, T-4 |
| T-6 | ✅ | back | M | `Purchases/Services/PurchaseOrderWorkflowService.php`, `resources/views/pdf/purchases/order.blade.php`, seeders | T-2, T-5 |
| T-7 | ✅ | back | M | `tests/Feature/Modules/Purchases/{PurchaseRequestsApiTest,PurchaseOrdersApiTest}.php` | T-6 |
| T-8 | ✅ | front | M | `components/common/DocumentLinesTable.vue`, `utils/documentTotals.js` | — |
| T-9 | ✅ | front | M | `pages/purchases/requests/{index,new,[id]}.vue`, `modules/purchases/{services/purchaseRequestsApi.js,stores/purchaseRequestsStore.js}` | T-4, T-8 |
| T-10 | ✅ | front | M | `pages/purchases/orders/{index,new,[id]}.vue`, `modules/purchases/{services/purchaseOrdersApi.js,stores/purchaseOrdersStore.js,components/orders/PendingRequestItemsDialog.vue}`, menú | T-6, T-8 |

---
## Detalle de Tareas
### T-1 — Calculadora de totales de documentos
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Support/Pricing/{DocumentTotalsCalculator,LineInput,LineResult,DocumentTotals}.php`
- `back-laravel/tests/Unit/Support/DocumentTotalsCalculatorTest.php`
**Descripción:**
1. Implementar el algoritmo de design §2 con `bcmath` (precios con/sin impuesto, tipos de impuesto, descuento por línea y global prorrateado, cuadre de redondeo).
2. Clase pura sin dependencias de Eloquent.
**Tests:**
- `DocumentTotalsCalculatorTest` con los casos de design §9.
**Criterios de done:**
- [x] `make test-filter FILTER=DocumentTotals` en verde; `vendor/bin/pint --test` en verde.

---
### T-2 — Infraestructura PDF
**Estado:** ✅ · **Subj:** infra · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/composer.json`, `back-laravel/config/dompdf.php`
- `back-laravel/app/Support/Pdf/PdfRenderer.php`
- `back-laravel/resources/views/pdf/layouts/document.blade.php`, `resources/views/pdf/partials/{items,totals}.blade.php`
- `back-laravel/tests/Feature/Support/PdfRendererTest.php`
**Descripción:**
1. `make composer CMD="require barryvdh/laravel-dompdf"` y publicar la configuración (design §7).
2. `PdfRenderer` con datos de empresa y logo en base64.
**Tests:**
- `PdfRendererTest`.
**Criterios de done:**
- [x] `make test-filter FILTER=PdfRenderer` en verde.

---
### T-3 — Migración y modelos de compras
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_19_000002_create_purchase_requests_and_orders_tables.php`
- `back-laravel/app/Modules/Purchases/Models/{PurchaseRequest,PurchaseRequestItem,PurchaseOrder,PurchaseOrderItem}.php`
- `back-laravel/app/Modules/Purchases/Enums/{PurchaseRequestStatus,PurchaseOrderStatus,PaymentCondition,Priority}.php`
**Descripción:**
1. DDL de design §6; enums con `canTransitionTo()` (transiciones de requirements US-3 y US-5).
2. Morph map `purchase_order`.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-4 — Requerimientos de compra (backend)
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/PurchaseRequestService.php`
- `back-laravel/app/Modules/Purchases/Controllers/PurchaseRequestController.php`
- `back-laravel/app/Modules/Purchases/Requests/{StorePurchaseRequestRequest,ListPurchaseRequestsRequest,LowStockRequestRequest}.php`
- `back-laravel/app/Modules/Purchases/Resources/{PurchaseRequestResource,PurchaseRequestItemResource}.php`
- `back-laravel/routes/api.php`
**Descripción:**
1. Flujo completo de design §2 (incluido `fromLowStock` con `StockQueryService` y `refreshOrderedQuantities`).
2. Rutas con middleware `branch`.
**Tests:**
- Cubiertos en T-7.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-5 — Órdenes de compra: creación y edición
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1, T-4
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/PurchaseOrderService.php`
- `back-laravel/app/Modules/Purchases/Controllers/PurchaseOrderController.php`
- `back-laravel/app/Modules/Purchases/Requests/{StorePurchaseOrderRequest,ListPurchaseOrdersRequest}.php`
- `back-laravel/app/Modules/Purchases/Resources/{PurchaseOrderResource,PurchaseOrderItemResource}.php`
**Descripción:**
1. Validaciones, tipo de cambio, cálculo con `DocumentTotalsCalculator` y vínculo con requerimientos (design §2).
2. Hooks de borrado en `ProviderService` y `CatalogItemService`.
**Tests:**
- Cubiertos en T-7.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-6 — Flujo de aprobación, PDF, permisos y parámetro
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2, T-5
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/PurchaseOrderWorkflowService.php`
- `back-laravel/resources/views/pdf/purchases/order.blade.php`
- `back-laravel/database/seeders/{RolePermissionSeeder,SystemParameterSeeder}.php`
**Descripción:**
1. `submit/approve/reject/close/cancel` y `markReceived()` (para el spec 023).
2. Vista PDF de la OC con marca de agua en `draft`.
3. Permisos de design §5 y parámetro de umbral.
**Tests:**
- Cubiertos en T-7.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-7 — Tests de requerimientos y OC
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-6
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Purchases/{PurchaseRequestsApiTest,PurchaseOrdersApiTest}.php`
**Descripción:**
1. Casos de design §9.
**Tests:**
- Los dos archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Purchase` en verde; `make test` completo en verde.

---
### T-8 — Front: grilla de líneas de documento reutilizable
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `front-vue/src/components/common/DocumentLinesTable.vue`
- `front-vue/src/utils/documentTotals.js`
**Descripción:**
1. Grilla con `CatalogItemPicker` o texto libre, cálculo de vista previa con la misma fórmula del backend y props de design §8.
2. Test Vitest de `documentTotals.js` con los mismos casos que `DocumentTotalsCalculatorTest`.
**Tests:**
- `make test-front` (Vitest de `documentTotals.js`); `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `make test-front` en verde.

---
### T-9 — Front: requerimientos
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4, T-8
**Archivos clave:**
- `front-vue/src/pages/purchases/requests/{index,new,[id]}.vue`
- `front-vue/src/modules/purchases/services/purchaseRequestsApi.js`, `stores/purchaseRequestsStore.js`
**Descripción:**
1. Listado con pestañas, formulario con generación desde stock bajo mínimo, detalle con acciones (design §8).
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-10 — Front: órdenes de compra
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-6, T-8
**Archivos clave:**
- `front-vue/src/pages/purchases/orders/{index,new,[id]}.vue`
- `front-vue/src/modules/purchases/services/purchaseOrdersApi.js`, `stores/purchaseOrdersStore.js`
- `front-vue/src/modules/purchases/components/orders/PendingRequestItemsDialog.vue`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Formulario con `ProviderPicker`, moneda y tipo de cambio y `DocumentLinesTable`; detalle con acciones de flujo y descarga del PDF (`blob`).
2. Menú "Compras" › "Requerimientos" y "Órdenes de compra".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
