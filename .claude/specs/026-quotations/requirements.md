# 026 · quotations — Requirements
> **Spec:** 026 · **Alcance:** fullstack · **Módulo:** Sales · **Prioridad:** media · **Depende de specs:** 004, 010, 012, 014, 015, 022, 025
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 5)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 022 — `DocumentTotalsCalculator`, `PdfRenderer`, `DocumentLinesTable`. Spec 025 — clientes. Spec 012 — empleados (vendedor). Spec 010 — `sales.max_line_discount_percent`, `sales.max_global_discount_percent`, `sales.quotation_validity_days`. Spec 004 — scheduler.
> - Origen Multinort: `create_quotations_table.php` (+ `add_discount_to_quotations`), `create_quotation_items_table.php`, `OrdenServicio/QuotationController.php`, `CotizadorTab.vue` (descuentos desde promociones y membresías).
>
---
## 1. Contexto de Negocio
Antes de vender, la empresa entrega **cotizaciones** (presupuestos) con precios, descuentos, validez y condiciones. El cliente las acepta o rechaza, y una cotización aceptada se convierte en venta (spec 027) sin volver a digitar.

En Multinort, la cotización depende de la orden de servicio del taller (vehículo, pintura, mano de obra, adicionales) y sus descuentos vienen del endpoint `descuentos-servicios`, que mezcla promociones y membresías. Por decisión del plan, **no hay promociones ni membresías**: los descuentos son **manuales por línea y global**, con topes configurables y un permiso para superarlos. El vendedor es un **empleado** (spec 012).

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Vendedor | `user` | JWT · `quotations.view|create|update` |
| Jefe de ventas | `manager` | JWT · `quotations.*`, `sales.discount_override` |

## 3. User Stories (formato EARS)
### US-1 — Crear y editar una cotización
**Como** vendedor **quiero** armar una cotización con ítems del catálogo o servicios con descripción libre **para** presentar una propuesta al cliente.
**Cuando** se envía `POST /api/v1/sales/quotations` o `PUT /api/v1/sales/quotations/{id}` (solo `draft`), **el sistema debe** guardar la cotización `COT-000001` con totales calculados en el backend.
**Módulo:** `Sales` (`/api/v1/sales/quotations`)
**Permisos:** `quotations.create` / `quotations.update`
**Criterios de aceptación:**
- [ ] Cabecera: `client_id` (no el cliente de sistema), `contact_id` opcional (del cliente), `seller_id` (empleado activo; por defecto el empleado vinculado al usuario), `issue_date`, `valid_until` (por defecto `issue_date + sales.quotation_validity_days`), `currency_id`, `exchange_rate`, `price_type` (por defecto el del cliente), `global_discount_percent`, `notes`, `terms`, `delivery_time`, `payment_terms`.
- [ ] Líneas: `item_presentation_id` (ítem `is_sellable`) o `description` libre; `quantity`; `unit_price` (por defecto el precio de lista de la presentación según `price_type`, convertido a la moneda de la cotización); `discount_percent`; `tax_type` (del ítem).
- [ ] Se guarda `list_price` por línea (precio de lista al cotizar) para auditar descuentos.
- [ ] Cada línea muestra el stock disponible en la sede activa (informativo, no bloquea).

### US-2 — Descuentos manuales con tope
**Como** jefe de ventas **quiero** que los descuentos por encima del tope requieran mi permiso **para** proteger el margen.
**Cuando** una cotización (o venta, spec 027) incluye una línea con descuento efectivo mayor que `sales.max_line_discount_percent` o un descuento global mayor que `sales.max_global_discount_percent`, **el sistema debe** rechazarla con 422 salvo que el usuario tenga `sales.discount_override`.
**Módulo:** `Sales`
**Permisos:** `sales.discount_override` (para superar el tope)
**Criterios de aceptación:**
- [ ] Descuento efectivo de línea = `1 − (unit_price × (1 − discount_percent/100)) / list_price` (bajar el precio unitario cuenta como descuento).
- [ ] Error: `{ "message": "El descuento supera el máximo permitido (10 %).", "errors": { "items.2.discount_percent": ["…"] } }`.
- [ ] Las líneas de texto libre (sin precio de lista) no se validan contra el tope.
- [ ] UI: el campo de descuento muestra el tope y marca en rojo la línea que lo supera; el aviso desaparece si el usuario tiene el permiso.

### US-3 — Flujo de estados
**Como** vendedor **quiero** marcar la cotización como enviada, aceptada o rechazada **para** hacer seguimiento comercial.
**Cuando** se envía `POST /api/v1/sales/quotations/{id}/send|accept|reject|cancel` (`reason` en reject/cancel), **el sistema debe** cambiar el estado según las transiciones válidas.
**Módulo:** `Sales`
**Permisos:** `quotations.update` (send/accept/reject), `quotations.cancel` (cancel)
**Criterios de aceptación:**
- [ ] Transiciones: `draft → sent | cancelled`, `sent → accepted | rejected | expired | cancelled`, `accepted → converted | cancelled`; `rejected`, `expired`, `converted` y `cancelled` son finales.
- [ ] No se acepta una cotización vencida (`valid_until < hoy`) → 422.
- [ ] Un comando diario `sales:expire-quotations` (scheduler del spec 004) pasa a `expired` las `sent` vencidas.
- [ ] `converted` lo fija el spec 027 al generar la venta.

### US-4 — Duplicar y descargar en PDF
**Como** vendedor **quiero** duplicar una cotización anterior y descargarla en PDF **para** reutilizarla y enviarla al cliente.
**Cuando** se envía `POST /api/v1/sales/quotations/{id}/duplicate` o se solicita `GET /api/v1/sales/quotations/{id}/pdf`, **el sistema debe** crear un borrador nuevo con las mismas líneas (precios de lista actualizados) o generar el PDF con logo, datos de empresa y cliente, vendedor, líneas, descuentos, totales, validez y términos.
**Módulo:** `Sales`
**Permisos:** `quotations.create` (duplicar), `quotations.view` (PDF)
**Criterios de aceptación:**
- [ ] Al duplicar, `issue_date = hoy` y `valid_until` se recalcula; la línea de un ítem inactivo se omite y se reporta en `meta.skipped_lines`.

### US-5 — Consultar cotizaciones
**Como** vendedor o jefe de ventas **quiero** listar cotizaciones con filtros **para** gestionar el embudo.
**Cuando** se solicita `GET /api/v1/sales/quotations?status=&client_id=&seller_id=&date_from=&date_to=&search=`, **el sistema debe** listar las cotizaciones de la sede activa con totales.
**Módulo:** `Sales`
**Permisos:** `quotations.view`
**Criterios de aceptación:**
- [ ] Un vendedor sin `quotations.view_all` ve solo las que creó o en las que es vendedor.
- [ ] `meta.totals_by_status` con el monto por estado (en moneda base).
- [ ] UI: `/sales/quotations`, `/sales/quotations/new`, `/sales/quotations/[id]`, y pestaña "Cotizaciones" en el detalle del cliente.

## 4. Reglas de negocio e invariantes
- Los totales los calcula el backend con `DocumentTotalsCalculator` y la tasa de impuesto guardada en el documento.
- Ningún descuento efectivo supera los topes sin el permiso `sales.discount_override`; la validación vive en un servicio compartido con el spec 027.
- Solo `draft` se edita; una cotización `converted` está vinculada a exactamente una venta.
- No existen promociones ni membresías: el único mecanismo de descuento es manual.

## 5. Requisitos no funcionales
- PDF de 50 líneas en < 2 s.
- Auditoría de estado y de descuentos que superan el tope (quién los aprobó por permiso).
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Envío por correo o WhatsApp → spec futuro.
- Versionado de cotizaciones (revisiones v1, v2) → spec futuro; se usa "duplicar".
- Promociones, cupones y membresías → descartados por decisión del plan.
- Reserva de stock al aceptar → spec futuro.
- Conversión a venta → spec 027.

## 7. Open Questions
- [ ] ¿Un vendedor puede ver cotizaciones de otros? Propuesta: no, salvo `quotations.view_all`. — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `QuotationsApiTest`, `DiscountPolicyServiceTest` y `ExpireQuotationsCommandTest` en verde.
