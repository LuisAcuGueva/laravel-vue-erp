# 034 · purchase-receipt-installment-plan — Requirements
> **Spec:** 034 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 022, 023, 024
> **Status:** Done
> **Última actualización:** 2026-10-24
> **Autores:** Luis Acuña Guevara
> **Origen:** —
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias, reglas financieras).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 022 — Órdenes de compra (`PurchaseOrder`, botones "Recibir" / "Recibir todo").
> - Spec 023 — Recepciones de compra (`PurchaseReceipt`, `PurchaseReceiptConfirmationService::confirm()`/`receiveAll()`).
> - Spec 024 — Cuentas por pagar (`SupplierInvoice`, `PayableInstallment`, `SupplierInvoiceService::buildInstallments()`), ya extendido (fuera de spec, cambio directo) con `SupplierInvoiceService::createOrAttachFromConfirmedReceipt()`: al confirmar una recepción con `provider_document_type = invoice` y `provider_document_number`, se crea (o fusiona en una existente) automáticamente la `SupplierInvoice` correspondiente, con **una sola cuota** al vencimiento (`issue_date + provider.credit_days`).
>
---
## 1. Contexto de Negocio
Hoy, cuando se confirma una recepción de compra que trae la factura del proveedor (`provider_document_type = invoice`), el sistema genera automáticamente la `SupplierInvoice` con una **cuota única** a la fecha de vencimiento del proveedor (`PurchaseReceiptConfirmationService::confirm()` → `SupplierInvoiceService::createOrAttachFromConfirmedReceipt()`). Esto cubre el caso de pago al contado o a un solo vencimiento, pero no el caso — frecuente en compras a crédito — en que el proveedor acepta un **cronograma de varias cuotas** (ej. 30/60/90 días).

El formulario manual de factura de proveedor (`SupplierInvoiceForm.vue`, spec 024) ya resuelve esto con un bloque "Cuotas por pagar" (`splitCount`/`splitInterval`/"Dividir en cuotas" + filas editables, validado contra `buildInstallments()` en `SupplierInvoiceService`). Pero ese formulario se usa en una pantalla separada (`/purchases/supplier-invoices/new`), desconectada del momento real de recepción.

Este spec lleva esa misma capacidad de definir un cronograma de cuotas al **momento de recibir**, en los tres puntos donde el usuario interactúa con la recepción de una orden de compra:
1. `orders/[id].vue` → diálogo "Recibir todo" (recepción total, `receiveAll`).
2. `receipts/new.vue` → "Guardar y confirmar" (recepción parcial o total, flujo manual).
3. `receipts/[id].vue` → botón "Confirmar" de una recepción guardada antes como borrador con `provider_document_type = invoice`.

En los tres casos, el cronograma solo tiene sentido cuando la recepción trae una factura (`provider_document_type = invoice`) — es el mismo gating que ya usa la auto-creación de factura.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Encargado de almacén / compras | `user` o `manager` | JWT · `purchase_receipts.create` |
| Quien confirma la recepción | `manager` o `user` con el permiso | JWT · `purchase_receipts.confirm` |

No se introduce ningún permiso nuevo: el cronograma de cuotas viaja como dato opcional dentro de los mismos endpoints que ya exigen `purchase_receipts.create`/`purchase_receipts.confirm`, igual que hoy `pay_now` viaja dentro de `POST /purchases/supplier-invoices` sin exigir `supplier_payments.create` en un endpoint separado.

## 3. User Stories (formato EARS)

### US-1 — Definir un cronograma de cuotas al confirmar una recepción (recepción manual)
**Como** encargado de compras **quiero** indicar, al confirmar una recepción que trae la factura del proveedor, en cuántas cuotas y con qué vencimientos se pagará **para** no tener que entrar luego a la pantalla de facturas a repartir el cronograma.
**Cuando** se envía `POST /api/v1/purchases/receipts/{id}/confirm` con `provider_document_type = invoice` (ya guardado en la recepción) y un body `{ installments: [{ due_date, amount }] }`, **el sistema debe** crear la `SupplierInvoice` automática con esas cuotas en vez de la cuota única por defecto.
**Módulo:** `Purchases` (`/api/v1/purchases/receipts/{id}/confirm`)
**Permisos:** `purchase_receipts.confirm`
**Criterios de aceptación:**
- [ ] Si no se envía `installments` (o se envía vacío), el comportamiento es el actual: cuota única a `issue_date + provider.credit_days`.
- [ ] La suma de los montos de `installments` debe ser exactamente igual al total de la factura generada; si no coincide, la confirmación de la recepción falla con 422 y el mensaje ya existente de `buildInstallments()` ("La suma de las cuotas... debe ser igual al total de la factura...").
- [ ] Ninguna fecha de cuota puede ser anterior a `issue_date` (la fecha de la recepción); si lo es, 422 con el mensaje ya existente de `buildInstallments()`.
- [ ] Si `provider_document_type !== invoice` (p. ej. `guide`), un `installments` enviado en el body se ignora sin error — la recepción nunca se bloquea por datos de facturación.
- [ ] Máximo 60 cuotas (mismo límite que `StoreSupplierInvoiceRequest`).

### US-2 — Definir el cronograma desde "Recibir todo" (recepción total de la OC)
**Como** encargado de compras **quiero** la misma calculadora de cuotas en el diálogo "Recibir todo" de la orden de compra **para** resolver en un solo paso la recepción completa, su factura y su cronograma de pago.
**Cuando** se envía `POST /api/v1/purchases/orders/{id}/receive-all` con `provider_document_type = invoice` y `installments`, **el sistema debe** aplicar las mismas reglas de US-1 a la factura generada por `receiveAll()`.
**Módulo:** `Purchases` (`/api/v1/purchases/orders/{id}/receive-all`)
**Permisos:** `purchase_receipts.create`, `purchase_receipts.confirm`
**Criterios de aceptación:**
- [ ] Mismos criterios de aceptación que US-1 (`receiveAll()` internamente llama a `confirm()` con el mismo payload de `installments`).

### US-3 — Calculadora de cuotas visible en las 3 pantallas de recepción
**Como** encargado de compras **quiero** ver y ajustar el cronograma de cuotas (número de cuotas, frecuencia en días, monto y vencimiento por cuota) en la misma pantalla donde recibo la mercadería, sin saltar a otra pantalla **para** no duplicar trabajo ni perder contexto.
**Cuando** el usuario marca `provider_document_type = invoice` y hay un `provider_document_number` informado, en `orders/[id].vue` (diálogo "Recibir todo"), `receipts/new.vue` y `receipts/[id].vue` (al confirmar un borrador), **el sistema debe** mostrar un bloque de cuotas con "Dividir en N cuotas cada M días" (prellenando filas editables) y una validación visual de que la suma coincide con el total previsto, igual a la UX que ya existe en `SupplierInvoiceForm.vue`.
**Módulo:** `Purchases` (frontend)
**Permisos:** los de cada pantalla (sin cambio).
**Criterios de aceptación:**
- [ ] El bloque de cuotas no aparece si `provider_document_type = guide` (no hay factura que calendarizar todavía).
- [ ] El total mostrado en la calculadora se calcula en el cliente con `calculateDocumentTotals()` (`front-vue/src/utils/documentTotals.js`) a partir de las cantidades e ítems que se están recibiendo, usando la tasa de impuesto de la empresa (`CompanySettingService::current()->default_tax_rate`, no la tasa de la OC) y `prices_include_tax: false` (porque `unit_cost_preview` ya viene neto de impuesto). Es solo una previsualización: el backend recalcula el total real al confirmar.
- [ ] Si la suma de las cuotas editadas no coincide con el total previsualizado, el botón de confirmar no se bloquea en el cliente (el backend es quien valida), pero se muestra una advertencia visual igual a la de `SupplierInvoiceForm.vue`.

### US-4 — No personalizar el cronograma cuando la recepción se fusiona en una factura ya existente
**Como** encargado de compras **quiero** que, si la recepción que estoy confirmando corresponde a una factura del proveedor que ya fue creada por una recepción anterior (mismo proveedor + mismo número de documento), el sistema no me deje definir un cronograma nuevo **para** no generar un cronograma inconsistente con el ya vigente.
**Cuando** `SupplierInvoiceService::createOrAttachFromConfirmedReceipt()` detecta una factura existente no anulada con el mismo `provider_id` + `document_type` + `document_number` (caso de fusión ya implementado), **el sistema debe** ignorar cualquier `installments` recibido en el payload y seguir fusionando la recepción en la cuota única existente, tal como ya funciona hoy.
**Módulo:** `Purchases`
**Permisos:** `purchase_receipts.confirm`
**Criterios de aceptación:**
- [ ] La respuesta de `confirm()`/`receiveAll()` incluye en `meta.warnings` un aviso legible cuando esto ocurre (p. ej. "La factura {número} ya existe; el cronograma que definiste no se aplicó, se repartió en su cuota existente.") para que el frontend lo muestre.
- [ ] El frontend, antes de enviar, puede detectar este caso (si conoce de antemano que ya existe una recepción/factura con el mismo número de proveedor) y ocultar o deshabilitar la calculadora con un mensaje equivalente — mejora de UX, no bloqueante: la fuente de verdad es el aviso del backend.

## 4. Reglas de negocio e invariantes
- El cronograma de cuotas personalizado solo se aplica en la recepción que **crea** la `SupplierInvoice` (primera recepción de ese número de documento). Nunca se aplica en una fusión posterior (US-4).
- La validación de que `Σ cuotas = total` y de que ninguna cuota vence antes de la emisión ya existe en `SupplierInvoiceService::buildInstallments()` y se reutiliza sin duplicar lógica.
- La recepción de mercadería **nunca** se bloquea por un problema ajeno al usuario (falta de tipo de cambio registrado, etc. — comportamiento ya existente de `createOrAttachFromConfirmedReceipt()`, que atrapa cualquier `Throwable` y continúa). Este spec introduce una excepción deliberada a esa regla: si el **usuario** definió explícitamente un `installments` inválido (no cuadra con el total, o tiene una fecha anterior a la emisión), la confirmación de la recepción **debe fallar visiblemente con 422**, no tragarse el error en silencio — porque en ese caso el problema es un dato que el propio usuario ingresó mal, no una condición externa. Ver Open Questions.
- No se agrega ningún permiso nuevo ni regla de segregación de funciones adicional a las de `.agents/rules/finance.md`: el cronograma es una cuota por pagar, no un movimiento de dinero.

## 5. Requisitos no funcionales
- Reutilizar `buildInstallments()` y el componente de UI ya existente en `SupplierInvoiceForm.vue` (extraído a un componente compartido) — no se reimplementa la lógica de reparto de cuotas.
- Pint, Larastan y `make test` en verde en back-laravel; `npm run lint` en verde en front-vue.
- Los endpoints afectados (`confirm`, `receive-all`) siguen bajo los mismos permisos (`.agents/rules/api-authorization.md`); no se agregan rutas nuevas.

## 6. Out of Scope
- Reprogramar o editar el cronograma de una factura ya creada (fuera de la recepción) — ya es posible indirectamente anulando la factura (spec 024); no se agrega una pantalla de edición de cuotas post-creación.
- Validar el cronograma contra un plazo máximo de crédito del proveedor (como sí existe para clientes en `InstallmentPlanEditor.vue` del módulo de ventas, vía `maxCreditDays`) — no hay ese concepto para proveedores hoy; se deja para un spec futuro si se decide agregarlo.
- Cambiar el comportamiento de fusión de recepciones en una factura existente (spec ya implementado) más allá de ignorar el cronograma personalizado.
- Intereses, moras o recargos por cuota — el cronograma es solo de montos y fechas, igual que en facturas manuales.

## 7. Open Questions
- [ ] **¿Un `installments` inválido enviado explícitamente por el usuario debe romper la confirmación de la recepción (422, recepción no confirmada), o debe tragarse en silencio como los demás fallos de `createOrAttachFromConfirmedReceipt()` (recepción confirmada sin factura ni cuotas)?** Propuesta en este spec: debe romper la confirmación (ver Reglas de negocio) porque es un error del propio usuario, no una condición externa — pero requiere decidir explícitamente antes de implementar, porque cambia el comportamiento de "la recepción nunca se bloquea" que rige para todos los demás fallos de ese método. — Responsable: Luis Acuña.
- [ ] **¿El bloque de cuotas en `receipts/[id].vue` (confirmar un borrador) debe ser un diálogo aparte o un panel inline antes del botón "Confirmar"?** Propuesta: diálogo aparte (`VDialog`, igual estilo que `CancelDocumentDialog`) para no sobrecargar la vista de detalle con un formulario completo. — Responsable: Luis Acuña.

## 8. Métricas de éxito
- Tests backend nuevos en `PurchaseReceiptsApiTest` (o un test file dedicado) en verde: cronograma válido, cronograma inválido, cronograma ignorado en fusión, cronograma ignorado con `provider_document_type = guide`.
- Flujo manual end-to-end verificado con `/run`: recibir una OC completa, definir 3 cuotas en "Recibir todo", y ver esas 3 cuotas en el detalle de la factura generada.
