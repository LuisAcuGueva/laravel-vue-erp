# 023 · purchase-receipts — TODO
> **Spec:** 023 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 013, 015, 021, 022
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
| T-1 | ✅ | back | S | `migrations/2026_10_20_000003_create_purchase_receipts_tables.php`, `Purchases/Models/{PurchaseReceipt,PurchaseReceiptItem}.php`, `Enums/*` | — |
| T-2 | ✅ | back | M | `Purchases/Services/PurchaseReceiptService.php`, `Requests/*`, `Resources/*` | T-1 |
| T-3 | ✅ | back | M | `Purchases/Services/PurchaseReceiptConfirmationService.php` | T-2 |
| T-4 | ✅ | back | S | `Controllers/PurchaseReceiptController.php`, `routes/api.php`, `RolePermissionSeeder.php`, `SystemParameterSeeder.php` | T-3 |
| T-5 | ✅ | back | M | `tests/Feature/Modules/Purchases/PurchaseReceiptsApiTest.php` | T-4 |
| T-6 | ✅ | front | M | `pages/purchases/receipts/{index,new,[id]}.vue`, `modules/purchases/{services/purchaseReceiptsApi.js,stores/purchaseReceiptsStore.js,components/receipts/ReceiptItemsTable.vue}`, detalle de OC, menú | T-4 |

---
## Detalle de Tareas
### T-1 — Migración y modelos
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_20_000003_create_purchase_receipts_tables.php`
- `back-laravel/app/Modules/Purchases/Models/{PurchaseReceipt,PurchaseReceiptItem}.php`
- `back-laravel/app/Modules/Purchases/Enums/{PurchaseReceiptStatus,ProviderDocumentType}.php`
**Descripción:**
1. DDL de design §6; morph map `purchase_receipt`; relación `PurchaseOrder::receipts()`.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-2 — Creación y edición de recepciones
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/PurchaseReceiptService.php`
- `back-laravel/app/Modules/Purchases/Requests/{StorePurchaseReceiptRequest,ReceiveAllRequest,ListPurchaseReceiptsRequest}.php`
- `back-laravel/app/Modules/Purchases/Resources/{PurchaseReceiptResource,PurchaseReceiptItemResource}.php`
**Descripción:**
1. Validación de pendientes con tolerancia y cálculo de costo en moneda base sin impuesto (design §2).
2. `pendingItems()`.
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-3 — Confirmación, recepción rápida y anulación
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/PurchaseReceiptConfirmationService.php`
**Descripción:**
1. Leer primero `StockLedgerService.php`, `PurchaseOrderWorkflowService.php` y `ProviderPriceService.php`.
2. `confirm`, `receiveAll`, `cancel` (design §2), con hook `isInvoiced()`.
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Controller, rutas, permisos y parámetro
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Controllers/PurchaseReceiptController.php`
- `back-laravel/routes/api.php`
- `back-laravel/database/seeders/{RolePermissionSeeder,SystemParameterSeeder}.php`
**Descripción:**
1. Rutas de design §3 (dentro del grupo `branch` de `purchases`).
2. Permisos `purchase_receipts.*` y parámetro de tolerancia.
3. Incluir las recepciones en `PurchaseOrderResource` del detalle.
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-5 — Tests
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Purchases/PurchaseReceiptsApiTest.php`
**Descripción:**
1. Casos de design §9.
**Tests:**
- `PurchaseReceiptsApiTest`.
**Criterios de done:**
- [x] `make test-filter FILTER=PurchaseReceipts` en verde; `make test` completo en verde.

---
### T-6 — Front: recepciones
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `front-vue/src/pages/purchases/receipts/{index,new,[id]}.vue`
- `front-vue/src/modules/purchases/services/purchaseReceiptsApi.js`, `stores/purchaseReceiptsStore.js`
- `front-vue/src/modules/purchases/components/receipts/{ReceiptItemsTable.vue,ReceiptKardexTable.vue}`
- `front-vue/src/modules/purchases/components/orders/OrderReceiptsCard.vue`
- `front-vue/src/pages/purchases/orders/[id].vue`, `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Listado, formulario precargado desde la OC y detalle (design §8).
2. Pestaña / sección "Recepciones" y botón "Recibir todo" en el detalle de la OC.
3. Menú "Compras" › "Recepciones".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
