# 022 · purchase-requests-and-orders — Requirements
> **Spec:** 022 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 010, 011, 014, 015, 021
> **Status:** Done
> **Última actualización:** 2026-10-19
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 4)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 021 — proveedores. Spec 014 — catálogo. Spec 015 — stock mínimo. Spec 010 — impuesto y parámetros.
> - Origen Multinort: `create_purchase_requests_table.php` (ligado a `quotation_id` y `task_id`), `create_purchase_request_items_table.php`, `create_purchase_orders_table.php` (+ tipo mixto, `custom_description`), `create_purchase_order_details_table.php`, `PurchaseRequestController.php`, `PurchaseOrderController.php`.
>
---
## 1. Contexto de Negocio
Las áreas piden lo que necesitan mediante un **requerimiento de compra**; compras lo atiende emitiendo **órdenes de compra (OC)** a proveedores, con impuestos, condición de pago y aprobación según el monto. La OC es el documento contra el que luego se recibe la mercadería (spec 023) y se registra la factura del proveedor (spec 024).

En Multinort, el requerimiento nace obligatoriamente de una cotización del taller (`quotation_id`) y de tareas de la ODS. La OC tiene estados separados de aprobación, recepción y pago, el tipo productos/servicios/mixta y líneas con descripción libre para servicios. Aquí el requerimiento es **genérico**: lo crea cualquier área, o se genera desde el stock bajo mínimo. La OC conserva la aprobación por monto, las líneas de producto o servicio, la recepción parcial y el PDF.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Solicitante | Cualquier usuario de un área | JWT · `purchase_requests.create` |
| Comprador | Rol de compras | JWT · `purchase_requests.view|approve`, `purchase_orders.view|create|update` |
| Aprobador | `manager` | JWT · `purchase_orders.approve`, `purchase_orders.cancel` |

## 3. User Stories (formato EARS)
### US-1 — Crear y enviar un requerimiento de compra
**Como** solicitante **quiero** pedir productos o servicios indicando cantidad, fecha requerida y almacén de destino **para** que compras los adquiera.
**Cuando** se envía `POST /api/v1/purchases/requests` y luego `POST /api/v1/purchases/requests/{id}/submit`, **el sistema debe** crear el requerimiento `RQ-000001` en `draft` y pasarlo a `submitted`.
**Módulo:** `Purchases` (`/api/v1/purchases/requests`)
**Permisos:** `purchase_requests.create`
**Criterios de aceptación:**
- [ ] Cabecera: `area_id` (obligatoria), `warehouse_id` (de la sede activa), `required_date ≥ hoy`, `priority` (`normal`|`urgent`), `notes`.
- [ ] Líneas: `item_presentation_id` **o** `description` libre (ítem no catalogado), `quantity > 0`, `notes`.
- [ ] Solo el creador edita mientras está en `draft`.

### US-2 — Generar requerimiento desde stock bajo mínimo
**Como** jefe de almacén **quiero** generar un requerimiento con los ítems por debajo del mínimo **para** reponer sin digitar.
**Cuando** se envía `POST /api/v1/purchases/requests/from-low-stock` con `{ warehouse_id, area_id, category_id? }`, **el sistema debe** crear un requerimiento `draft` con una línea por ítem con `quantity < min_stock`, sugiriendo `max(max_stock, min_stock) − quantity` en la presentación por defecto.
**Módulo:** `Purchases`
**Permisos:** `purchase_requests.create`
**Criterios de aceptación:**
- [ ] Sin ítems bajo mínimo → 422 "No hay ítems por debajo del stock mínimo".

### US-3 — Aprobar o rechazar requerimientos
**Como** comprador **quiero** aprobar o rechazar requerimientos enviados **para** decidir qué se compra.
**Cuando** se envía `POST /api/v1/purchases/requests/{id}/approve` o `/reject` con `{ reason }`, **el sistema debe** pasar el requerimiento a `approved` o `rejected`.
**Módulo:** `Purchases`
**Permisos:** `purchase_requests.approve`
**Criterios de aceptación:**
- [ ] Solo desde `submitted`; `reason` obligatorio al rechazar.
- [ ] El estado pasa a `partially_ordered` u `ordered` automáticamente según las cantidades incluidas en OC; `cancelled` mediante `/cancel` solo si no tiene OC.

### US-4 — Emitir una orden de compra
**Como** comprador **quiero** emitir una OC a un proveedor, opcionalmente a partir de líneas de requerimientos aprobados **para** formalizar la compra.
**Cuando** se envía `POST /api/v1/purchases/orders`, **el sistema debe** crear la OC `OC-000001` en `draft` con totales calculados en el backend.
**Módulo:** `Purchases` (`/api/v1/purchases/orders`)
**Permisos:** `purchase_orders.create`
**Criterios de aceptación:**
- [ ] Cabecera: `provider_id`, `warehouse_id` de entrega (sede activa), `order_date`, `expected_date`, `currency_id` (por defecto la del proveedor o la base), `exchange_rate` (obligatorio si no es la base; por defecto el vigente), `payment_condition` (`cash`|`credit`), `credit_days` (por defecto los del proveedor), `prices_include_tax`, `notes`, `terms`.
- [ ] Líneas: `item_presentation_id` (producto o servicio del catálogo con `is_purchasable`) **o** `description` libre (solo servicios), `quantity`, `unit_price`, `discount_percent` (0–100), `tax_type` (por defecto el del ítem), `purchase_request_item_id` opcional.
- [ ] Totales: `taxable_base`, `exempt_base`, `unaffected_base`, `discount_total`, `tax_total`, `total`, calculados con la tasa de impuesto de la empresa y redondeo a 2 decimales.
- [ ] Una línea vinculada a un requerimiento no puede superar la cantidad pendiente de esa línea → 422.
- [ ] `GET /api/v1/purchases/requests/pending-items?warehouse_id=` lista líneas aprobadas con saldo por ordenar, para armar la OC.

### US-5 — Aprobar, enviar, cerrar o anular la OC
**Como** aprobador **quiero** que las OC que superan un monto requieran mi aprobación **para** controlar el gasto.
**Cuando** se envía `POST /api/v1/purchases/orders/{id}/submit`, **el sistema debe** pasar la OC a `approved` si su total en moneda base es ≤ `purchases.order_approval_threshold`, o a `pending_approval` en caso contrario; con `POST /approve` o `/reject` el aprobador resuelve; `POST /close` cierra una OC parcialmente recibida; `POST /cancel` la anula si no tiene recepciones.
**Módulo:** `Purchases`
**Permisos:** `purchase_orders.create` (submit), `purchase_orders.approve` (approve/reject), `purchase_orders.cancel` (cancel/close)
**Criterios de aceptación:**
- [ ] Estados: `draft → pending_approval | approved`, `pending_approval → approved | rejected`, `approved → partially_received → received`, `approved | partially_received → closed`, `draft | pending_approval | approved (sin recepciones) → cancelled`.
- [ ] El aprobador no puede ser el creador → 422.
- [ ] Editar solo en `draft` o `rejected` (al editar una rechazada vuelve a `draft`).
- [ ] Anular o rechazar libera las cantidades de los requerimientos vinculados.

### US-6 — Consultar y descargar la OC
**Como** comprador **quiero** listar OC y descargarlas en PDF **para** enviarlas al proveedor.
**Cuando** se solicita `GET /api/v1/purchases/orders` (filtros `status`, `provider_id`, `date_from`, `date_to`, `search`) o `GET /api/v1/purchases/orders/{id}/pdf`, **el sistema debe** listar las OC de la sede activa o generar el PDF A4 con los datos de la empresa (spec 010), el proveedor, las líneas, los totales y los términos.
**Módulo:** `Purchases`
**Permisos:** `purchase_orders.view`
**Criterios de aceptación:**
- [ ] El PDF se genera solo para OC `approved` o posteriores; en `draft` se marca con la marca de agua "BORRADOR".
- [ ] UI: `/purchases/orders` (listado), `/purchases/orders/new` y `/purchases/orders/[id]` (formulario y detalle con avance de recepción por línea).

## 4. Reglas de negocio e invariantes
- Los totales de la OC los calcula siempre el backend con `DocumentTotalsCalculator`; los montos enviados por el cliente se ignoran.
- `ordered_quantity` de una línea de requerimiento = Σ cantidades en líneas de OC no anuladas que la referencian, y nunca supera `quantity`.
- `received_quantity` de una línea de OC la actualiza solo el spec 023 y nunca supera `quantity`.
- Una OC con recepciones no se anula (se cierra).

## 5. Requisitos no funcionales
- Generación del PDF de una OC de 50 líneas en < 2 s.
- Auditoría de cambios de estado de requerimientos y OC.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Recepción de mercadería y conformidad de servicios → spec 023.
- Facturas de proveedor, cuentas por pagar y pagos → spec 024.
- Envío de la OC por correo al proveedor → spec futuro.
- Solicitud de cotizaciones a varios proveedores (RFQ) → spec futuro.
- Vínculo con cotizaciones de venta o tareas del taller → descartado.

## 7. Open Questions
- [ ] ¿El umbral de aprobación debe ser por sede o por rol? Propuesta: global (parámetro). — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `PurchaseRequestsApiTest`, `PurchaseOrdersApiTest`, `DocumentTotalsCalculatorTest` y `PdfRendererTest` en verde.
