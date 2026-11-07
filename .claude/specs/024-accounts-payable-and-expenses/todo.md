# 024 · accounts-payable-and-expenses — TODO
> **Spec:** 024 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 017, 018, 020, 021, 022, 023
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
| T-1 | ✅ | back | M | `Finance/Services/MoneySettlementService.php`, `Finance/Data/SettlementData.php`, `tests/Feature/Modules/Finance/MoneySettlementServiceTest.php` | — |
| T-2 | ✅ | back | S | `migrations/2026_10_20_000005_create_accounts_payable_tables.php`, `Purchases/Models/{SupplierInvoice,SupplierInvoiceItem,PayableInstallment,SupplierPayment,SupplierPaymentAllocation}.php`, `Enums/*` | — |
| T-3 | ✅ | back | M | `Purchases/Services/SupplierInvoiceService.php`, `Requests/StoreSupplierInvoiceRequest.php`, `Resources/{SupplierInvoiceResource,PayableInstallmentResource}.php`, hooks | T-2 |
| T-4 | ✅ | back | M | `Purchases/Services/SupplierPaymentService.php`, `Requests/StoreSupplierPaymentRequest.php`, `Resources/SupplierPaymentResource.php` | T-1, T-3 |
| T-5 | ✅ | back | M | `Purchases/Services/AccountsPayableQueryService.php`, `Controllers/{SupplierInvoiceController,SupplierPaymentController,AccountsPayableController}.php`, `routes/api.php`, seeders | T-4 |
| T-6 | ✅ | back | M | `tests/Feature/Modules/Purchases/{SupplierInvoicesApiTest,SupplierPaymentsApiTest,AccountsPayableApiTest}.php` | T-5 |
| T-7 | ✅ | front | M | `components/common/PaymentMethodFields.vue`, `pages/purchases/supplier-invoices/{index,new,[id]}.vue`, `modules/purchases/{services/supplierInvoicesApi.js,stores/supplierInvoicesStore.js}` | T-5 |
| T-8 | ✅ | front | M | `pages/purchases/accounts-payable/index.vue`, `components/payables/{SupplierPaymentDialog,AgingTable}.vue`, `pages/finance/cash/index.vue`, menú | T-7 |

---
## Detalle de Tareas
### T-1 — Servicio de liquidación de dinero (caja o banco)
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Services/MoneySettlementService.php`
- `back-laravel/app/Modules/Finance/Data/SettlementData.php`
- `back-laravel/tests/Feature/Modules/Finance/MoneySettlementServiceTest.php`
**Descripción:**
1. Leer primero `CashLedgerService.php`, `BankLedgerService.php` y `BoxAccessService.php`.
2. `settle()` y `unsettle()` según design §2 (validaciones de moneda, sesión y acceso a caja).
**Tests:**
- `MoneySettlementServiceTest`.
**Criterios de done:**
- [x] `make test-filter FILTER=MoneySettlement` en verde; `vendor/bin/pint --test` en verde.

---
### T-2 — Migración y modelos
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_20_000005_create_accounts_payable_tables.php`
- `back-laravel/app/Modules/Purchases/Models/{SupplierInvoice,SupplierInvoiceItem,PayableInstallment,SupplierPayment,SupplierPaymentAllocation}.php`
- `back-laravel/app/Modules/Purchases/Enums/{SupplierInvoiceKind,SupplierDocumentType,SupplierInvoiceStatus,SupplierPaymentStatus}.php`
**Descripción:**
1. DDL de design §6 (índice único parcial de documento del proveedor).
2. Morph map `supplier_invoice`, `supplier_payment`.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-3 — Facturas de proveedor y gastos
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/SupplierInvoiceService.php`
- `back-laravel/app/Modules/Purchases/Requests/StoreSupplierInvoiceRequest.php`
- `back-laravel/app/Modules/Purchases/Resources/{SupplierInvoiceResource,PayableInstallmentResource}.php`
- `back-laravel/app/Modules/Purchases/Services/{PurchaseReceiptConfirmationService,ProviderService}.php` (hooks)
**Descripción:**
1. `create` (totales, cuotas, recepciones, advertencias, adjunto), `prefill`, `cancel` (design §2). `pay_now` se conecta en T-4.
2. Implementar `isInvoiced()` y ampliar `hasDocuments()`.
**Tests:**
- Cubiertos en T-6.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Pagos a proveedores
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1, T-3
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/SupplierPaymentService.php`
- `back-laravel/app/Modules/Purchases/Requests/StoreSupplierPaymentRequest.php`
- `back-laravel/app/Modules/Purchases/Resources/SupplierPaymentResource.php`
**Descripción:**
1. `create` con bloqueo de cuotas y `MoneySettlementService::settle()`; `cancel` con `unsettle()` (design §2).
2. Conectar `pay_now` de `SupplierInvoiceService`.
**Tests:**
- Cubiertos en T-6.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-5 — Consultas, controllers, rutas, permisos y parámetro
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/AccountsPayableQueryService.php`
- `back-laravel/app/Modules/Purchases/Controllers/{SupplierInvoiceController,SupplierPaymentController,AccountsPayableController}.php`
- `back-laravel/app/Modules/Purchases/Requests/{ListPayablesRequest,ProviderStatementRequest}.php`, `Resources/AgingResource.php`
- `back-laravel/routes/api.php`, `back-laravel/database/seeders/{RolePermissionSeeder,SystemParameterSeeder}.php`
**Descripción:**
1. Aging, cuotas abiertas y estado de cuenta (design §2).
2. Rutas de design §3; descarga autenticada del adjunto.
3. Permisos y parámetro de design §5.
**Tests:**
- Cubiertos en T-6.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-6 — Tests
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-5
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Purchases/{SupplierInvoicesApiTest,SupplierPaymentsApiTest,AccountsPayableApiTest}.php`
**Descripción:**
1. Casos de design §9 y el ciclo completo OC → recepción → factura → pagos.
**Tests:**
- Los tres archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Supplier` y `FILTER=AccountsPayable` en verde; `make test` completo en verde.

---
### T-7 — Front: facturas y gastos
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-5
**Archivos clave:**
- `front-vue/src/components/common/PaymentMethodFields.vue`
- `front-vue/src/pages/purchases/supplier-invoices/{index,new,[id]}.vue`
- `front-vue/src/modules/purchases/services/supplierInvoicesApi.js`, `stores/supplierInvoicesStore.js`
**Descripción:**
1. Formulario compra/gasto con carga desde recepciones, cuotas, adjunto y "Pagar ahora" (design §8).
2. Detalle con cuotas, pagos y anulación.
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-8 — Front: cuentas por pagar y gasto desde caja
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-7
**Archivos clave:**
- `front-vue/src/pages/purchases/accounts-payable/index.vue`
- `front-vue/src/modules/purchases/components/payables/{SupplierPaymentDialog,AgingTable}.vue`
- `front-vue/src/pages/finance/cash/index.vue`, `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Pestañas Cuotas · Antigüedad · Pagos y pago múltiple (design §8).
2. Botón "Registrar gasto" en la pantalla de caja.
3. Menú "Compras" › "Facturas de proveedor" y "Cuentas por pagar".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
