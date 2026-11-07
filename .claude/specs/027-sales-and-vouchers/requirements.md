# 027 · sales-and-vouchers — Requirements
> **Spec:** 027 · **Alcance:** fullstack · **Módulo:** Sales · **Prioridad:** media · **Depende de specs:** 004, 011, 015, 018, 020, 024, 025, 026
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 5)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 015 — `StockLedgerService`. Spec 018 — caja. Spec 024 — `MoneySettlementService`, `PaymentMethodFields`. Spec 026 — `DiscountPolicyService`, `SalePricingService`, cotizaciones. Spec 004 — colas.
> - Origen Multinort: `create_sales_table.php` (`service_order_id`, `type_receipt_id`, `document_number`, `payment_status`, `is_emitted`), `create_sale_details_table.php`, `create_type_receipts_table.php`, `create_correlatives_table.php`, `create_vouchers_table.php`, `Ingresos/SaleController.php`, `Facturacion/ElectronicInvoiceController.php`.
>
---
## 1. Contexto de Negocio
La venta es el corazón del ERP. El cajero o vendedor emite un **comprobante** (factura, boleta o nota de venta) con **serie y correlativo propios de la sede**. El sistema descuenta el stock al costo promedio, registra los **cobros** en caja o banco (uno o varios métodos de pago, con vuelto) y, si la venta es al **crédito**, deja un saldo pendiente (las cuotas y cobranzas se gestionan en el spec 028). El comprobante se imprime en A4 o en ticket. Más adelante puede enviarse a SUNAT mediante un **driver desacoplado** (por defecto local, sin envío).

En Multinort, `sales` está ligada a la orden de servicio del taller, tiene un único `payment_method_id`, el correlativo es `document_number` por sede con `correlatives` y `type_receipts` ligados a un área, y `vouchers` es un registro tributario aparte con todas las bases de SUNAT. Aquí se generaliza: series por sede (y opcionalmente por caja), varios pagos por venta, datos del cliente congelados en el comprobante y estado de facturación electrónica en la venta.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Cajero / Vendedor | `user` con caja | JWT · `sales.create`, `sales.view` |
| Jefe de ventas | `manager` | JWT · `sales.view_all`, `sales.cancel`, `sales.sell_on_credit`, `sales.discount_override` |
| Administrador | `admin` | JWT · `document_series.view|manage` |
| Proceso en cola | Worker (spec 004) | Envío al driver de facturación electrónica |

## 3. User Stories (formato EARS)
### US-1 — Series y correlativos por sede
**Como** administrador **quiero** configurar las series de cada tipo de comprobante por sede (y opcionalmente por caja) **para** numerar los documentos sin saltos ni duplicados.
**Cuando** se invoca el CRUD `/api/v1/sales/document-series`, **el sistema debe** gestionar series con `branch_id`, `box_id` opcional, `document_type` (`invoice`, `receipt`, `credit_note`, `sale_note`), `series` (4 caracteres), `last_number`, `is_electronic` e `is_active`.
**Módulo:** `Sales` (`/api/v1/sales/document-series`)
**Permisos:** `document_series.view|manage`
**Criterios de aceptación:**
- [ ] Formato de serie: factura `F` + 3 alfanuméricos, boleta `B` + 3, nota de crédito `F` o `B` + 3 (según el comprobante que afecta), nota de venta 4 alfanuméricos libres → 422 si no.
- [ ] `series` única por `document_type` en toda la empresa.
- [ ] `last_number` solo se edita mientras la serie no tiene documentos (para continuar una numeración previa).
- [ ] `GET /api/v1/sales/document-series/available?document_type=&box_id=` devuelve las series activas de la sede activa aplicables a la caja (las de la caja primero y luego las sin caja).
- [ ] Eliminar una sede con series → 422.

### US-2 — Emitir una venta al contado
**Como** cajero **quiero** registrar una venta con sus líneas y cobrarla con uno o varios métodos de pago **para** entregar el comprobante al cliente.
**Cuando** se envía `POST /api/v1/sales/sales` con `payment_condition = cash`, **el sistema debe**, en una transacción: obtener el siguiente correlativo de la serie, congelar los datos del cliente, calcular totales, validar descuentos, descontar el stock de los ítems con stock (motivo `SALE`) guardando el costo, registrar cada pago con `MoneySettlementService` (concepto `SALE_COLLECTION`) y dejar la venta `issued` con `balance = 0`.
**Módulo:** `Sales` (`/api/v1/sales/sales`)
**Permisos:** `sales.create`
**Criterios de aceptación:**
- [ ] Cabecera: `document_series_id`, `cash_session_id` (sesión abierta y operable de la sede activa), `warehouse_id` (por defecto el almacén por defecto de la sede), `client_id`, `seller_id`, `issue_date` (hoy; con `sales.backdate` hasta `sales.max_backdate_days` días atrás), `currency_id` (base en esta entrega), `price_type`, `global_discount_percent`, `quotation_id` opcional, `notes`; líneas como en cotizaciones; `payments: [{ payment_method_id, amount, reference?, received_amount? }]`.
- [ ] Factura: el cliente debe tener RUC → 422. Boleta con total ≥ `sales.receipt_identity_threshold` (700): cliente identificado (no genérico) → 422 si no.
- [ ] Σ `amount` de pagos = total → 422 si no; en efectivo, `received_amount ≥ amount` y `change = received − amount` (informativo).
- [ ] Stock insuficiente → 422 con el ítem (`InsufficientStockException`) y nada se registra; el correlativo no se consume.
- [ ] Si `quotation_id` viene, la cotización debe estar `accepted` o `sent` (vigente) y del mismo cliente, y queda `converted`.
- [ ] Respuesta 201 con `full_number` (`B001-00000123`), totales, pagos y `change`.

### US-3 — Venta al crédito
**Como** jefe de ventas o cajero autorizado **quiero** vender al crédito a clientes con línea aprobada **para** cobrar después.
**Cuando** se envía `POST /api/v1/sales/sales` con `payment_condition = credit` y pagos parciales opcionales (adelanto), **el sistema debe** validar el crédito disponible del cliente y registrar la venta con `balance = total − Σ pagos` y `due_date = issue_date + credit_days`.
**Módulo:** `Sales`
**Permisos:** `sales.create` + `sales.sell_on_credit`
**Criterios de aceptación:**
- [ ] Cliente sin crédito (`credit_limit = 0`) o genérico → 422.
- [ ] `balance > credit_limit − deuda vigente` (deuda = Σ `balance` de sus ventas emitidas) → 422 "Crédito insuficiente: disponible X".
- [ ] Se emite el evento `SaleIssued` (el spec 028 genera las cuotas al escucharlo).

### US-4 — Punto de venta (POS)
**Como** cajero **quiero** una pantalla rápida de venta con lector de código de barras **para** atender en mostrador.
**Cuando** abro `/sales/pos`, **el sistema debe** mostrar la caja y la sesión actuales, la serie por tipo de comprobante, el cliente (genérico por defecto), la búsqueda de ítems con stock y precio, el carrito con descuentos según el tope, y el diálogo de cobro con varios métodos y vuelto.
**Módulo:** `Sales`
**Permisos:** `sales.create`
**Criterios de aceptación:**
- [ ] Sin sesión de caja abierta → la pantalla bloquea la venta y ofrece "Abrir caja" (spec 018).
- [ ] Atajos: F2 buscar ítem, F4 cliente, F9 cobrar; Enter con un código de barras agrega una unidad.
- [ ] Tras emitir, abre la vista previa del ticket y limpia el carrito.
- [ ] Desde una cotización (`/sales/pos?quotation_id=`) el carrito se precarga con `GET /api/v1/sales/sales/prefill?quotation_id=`.

### US-5 — Comprobante impreso
**Como** cajero **quiero** imprimir el comprobante en ticket de 80 mm o en A4 **para** entregarlo al cliente.
**Cuando** se solicita `GET /api/v1/sales/sales/{id}/pdf?format=ticket|a4`, **el sistema debe** generar el PDF con los datos de la empresa y la sede, el tipo y número del comprobante, los datos congelados del cliente, las líneas, las bases por tipo de impuesto, el IGV, el total, el monto en letras ("SON: CIENTO VEINTE CON 50/100 SOLES"), la condición de pago, el vendedor y el cajero.
**Módulo:** `Sales`
**Permisos:** `sales.view`
**Criterios de aceptación:**
- [ ] Una venta anulada se imprime con la marca de agua "ANULADO".
- [ ] El ticket usa un ancho de 80 mm y un alto dinámico.

### US-6 — Anular una venta
**Como** jefe de ventas **quiero** anular una venta mal emitida **para** revertir stock y cobros.
**Cuando** se envía `POST /api/v1/sales/sales/{id}/cancel` con `{ reason }`, **el sistema debe** anular la venta revirtiendo los movimientos de kardex y de caja o banco, siempre que el comprobante no haya sido aceptado por SUNAT.
**Módulo:** `Sales`
**Permisos:** `sales.cancel`
**Criterios de aceptación:**
- [ ] `einvoice_status = accepted` → 422 "El comprobante fue aceptado por SUNAT; emita una nota de crédito" (spec 029).
- [ ] La sesión de caja del cobro ya cerrada → 422 "Use una nota de crédito".
- [ ] Una venta con cobranzas posteriores (spec 028) → 422.
- [ ] El correlativo no se reutiliza.

### US-7 — Facturación electrónica desacoplada
**Como** administrador **quiero** que la emisión electrónica dependa de un driver configurable **para** consumir más adelante la API REST de cualquier proveedor de facturación electrónica (PSE/OSE) sin cambiar las ventas.
**Cuando** se emite una venta con una serie `is_electronic = true`, **el sistema debe** encolar `SendElectronicDocumentJob` después del commit; el job invoca el driver configurado (`EINVOICE_DRIVER`, por defecto `null`) y actualiza `einvoice_status` (`pending → sent → accepted | rejected | error`) y la respuesta.
**Módulo:** `Sales`
**Permisos:** `sales.view` (consulta), `document_series.manage` (reintentar)
**Criterios de aceptación:**
- [ ] Con el driver `null`, el estado queda `not_applicable` y no se realiza ninguna llamada externa.
- [ ] `POST /api/v1/sales/sales/{id}/einvoice/retry` reintenta los documentos en `error` o `rejected` (máximo 5 intentos registrados).
- [ ] La venta nunca falla por un error del driver: la emisión local es independiente.

### US-8 — Historial de ventas
**Como** jefe de ventas **quiero** consultar las ventas con filtros y totales **para** controlar la facturación.
**Cuando** se solicita `GET /api/v1/sales/sales?document_type=&status=&client_id=&seller_id=&cash_session_id=&payment_condition=&date_from=&date_to=&search=`, **el sistema debe** listar las ventas de la sede activa con `meta.totals` (total, IGV, costo, margen) en moneda base.
**Módulo:** `Sales`
**Permisos:** `sales.view` (las propias o las de su sesión) / `sales.view_all`
**Criterios de aceptación:**
- [ ] El detalle muestra líneas con costo y margen (solo con `sales.view_costs`), pagos, movimientos de kardex y estado electrónico.
- [ ] UI: `/sales/history` y `/sales/history/[id]`, más una pestaña "Ventas" en el detalle del cliente.

## 4. Reglas de negocio e invariantes
- Correlativos sin duplicados ni saltos por serie: se obtienen con bloqueo dentro de la transacción de la venta; si la venta falla, la transacción revierte el correlativo.
- Los datos del cliente en el comprobante (`client_document_*`, `client_name`, `client_address`) se congelan al emitir y no cambian aunque cambie el cliente.
- Todo ítem con `track_stock` descuenta stock mediante el ledger al costo promedio; `cost_total` de la venta = Σ costos de kardex.
- Σ pagos = total (contado) o ≤ total (crédito); `balance = total − Σ pagos vigentes − Σ cobranzas (spec 028) − Σ notas de crédito aplicadas (spec 029)`.
- Una venta emitida no se edita; se anula (si procede) o se corrige con nota de crédito.

## 5. Requisitos no funcionales
- Emitir una venta de 30 líneas con 2 pagos en < 800 ms.
- La emisión no depende de servicios externos (el driver va en cola).
- Auditoría de emisión y anulación.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Drivers concretos por proveedor (cada uno consume la API REST del PSE/OSE elegido, que se encarga del XML UBL 2.1, la firma y el CDR), QR y hash en el PDF → spec futuro. Aquí solo el contrato y el driver nulo. El sistema **no** genera XML ni firma digital propios.
- Comunicación de baja y resumen diario de boletas → spec futuro.
- Ventas en moneda extranjera, guías de remisión, anticipos, detracciones y percepciones → spec futuro.
- Cuotas y cobranzas de ventas al crédito → spec 028; notas de crédito y devoluciones → spec 029.
- Venta vinculada a orden de servicio de taller → descartada.

## 7. Open Questions
- [ ] ¿Las series deben asignarse siempre a una caja o pueden compartirse en la sede? Propuesta: ambas opciones (caja opcional). — Responsable: Luis Acuña.
- [ ] ¿Se permite emitir con fecha pasada? Propuesta: solo con `sales.backdate` y máximo `sales.max_backdate_days` (3). — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `DocumentSeriesApiTest`, `SalesApiTest`, `SaleCancellationApiTest`, `SendElectronicDocumentJobTest` y `NumberToWordsTest` en verde.
- `inventory:verify-stock` y los saldos de caja cuadran tras emitir y anular en los tests.
