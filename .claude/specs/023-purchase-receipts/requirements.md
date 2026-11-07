# 023 · purchase-receipts — Requirements
> **Spec:** 023 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 013, 015, 021, 022
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 4)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 022 — `PurchaseOrder`, `PurchaseOrderWorkflowService::markReceived()`. Spec 015 — `StockLedgerService`. Spec 021 — `ProviderPriceService::recordPurchase()`.
> - Origen Multinort: `create_entry_notes_table.php`, `create_entry_note_details_table.php` (lote y vencimiento), `create_purchase_conformities_table.php`, `create_purchase_conformity_details_table.php`, `Services/Tenant/Logistica/EntryNoteService.php`.
>
---
## 1. Contexto de Negocio
Cuando llega la mercadería, el almacén registra una **recepción** contra la OC: qué llegó, cuánto y con qué guía del proveedor. Al confirmarla, el stock aumenta **al costo de compra** (sin impuesto y en moneda base), la OC actualiza su avance (parcial o completa) y se registra el último costo del ítem y el último precio pagado al proveedor. Las líneas de **servicio** se reciben como **conformidad**, sin stock.

En Multinort esto está repartido entre `entry_notes` (con lote y vencimiento por línea) y `purchase_conformities` (conformidad de servicios por área). Aquí se unifica en un solo documento de recepción.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Almacenero | `user` con permisos de recepción | JWT · `purchase_receipts.view|create` |
| Jefe de almacén | `manager` | JWT · `purchase_receipts.confirm|cancel` |

## 3. User Stories (formato EARS)
### US-1 — Registrar una recepción en borrador
**Como** almacenero **quiero** registrar lo recibido de una OC **para** documentar la llegada de mercadería o la conformidad de un servicio.
**Cuando** se envía `POST /api/v1/purchases/receipts` con `{ purchase_order_id, warehouse_id, receipt_date, provider_document_type, provider_document_number, notes, items: [{ purchase_order_item_id, quantity, notes }] }`, **el sistema debe** crear la recepción `RC-000001` en `draft` sin afectar el stock.
**Módulo:** `Purchases` (`/api/v1/purchases/receipts`)
**Permisos:** `purchase_receipts.create`
**Criterios de aceptación:**
- [ ] La OC está `approved` o `partially_received` y es de la sede activa → si no, 422.
- [ ] `warehouse_id` por defecto el de la OC; debe ser de la misma sede.
- [ ] `quantity > 0` y `≤ pendiente × (1 + purchases.over_receipt_tolerance_percent / 100)` por línea → 422 en otro caso.
- [ ] `provider_document_type` ∈ `guide` (guía de remisión), `invoice`, `other`.
- [ ] `GET /api/v1/purchases/orders/{id}/pending-items` devuelve las líneas con saldo por recibir para precargar el formulario.

### US-2 — Confirmar la recepción
**Como** jefe de almacén **quiero** confirmar la recepción **para** ingresar el stock y actualizar la OC.
**Cuando** se envía `POST /api/v1/purchases/receipts/{id}/confirm`, **el sistema debe**, en una transacción:
1. registrar en el ledger una entrada `purchase` (motivo `PURCHASE`) por cada línea de producto con `track_stock`, con costo unitario = precio neto de la línea de la OC (después del descuento y sin impuesto) × tipo de cambio de la OC ÷ factor de la presentación;
2. aumentar `received_quantity` de las líneas de la OC y actualizar su estado (`partially_received`/`received`);
3. actualizar `catalog_items.last_purchase_cost` y el último precio del proveedor;
4. marcar la recepción `confirmed`.
**Módulo:** `Purchases`
**Permisos:** `purchase_receipts.confirm`
**Criterios de aceptación:**
- [ ] Las líneas de servicio (o ítems sin `track_stock`) solo actualizan `received_quantity` (conformidad).
- [ ] Si otra recepción confirmada redujo el pendiente y ya no alcanza → 422 sin cambios.
- [ ] Recepción no `draft` → 422.

### US-3 — Recepción rápida del total pendiente
**Como** almacenero **quiero** recibir de un solo paso todo lo pendiente de una OC **para** agilizar entregas completas.
**Cuando** se envía `POST /api/v1/purchases/orders/{id}/receive-all` con `{ warehouse_id?, receipt_date, provider_document_type, provider_document_number }`, **el sistema debe** crear y confirmar una recepción con todas las líneas pendientes.
**Módulo:** `Purchases`
**Permisos:** `purchase_receipts.create` **y** `purchase_receipts.confirm`

### US-4 — Anular una recepción
**Como** jefe de almacén **quiero** anular una recepción registrada por error **para** revertir su efecto.
**Cuando** se envía `POST /api/v1/purchases/receipts/{id}/cancel` con `{ reason }`, **el sistema debe** marcarla `cancelled`; si estaba confirmada, revertir sus movimientos de kardex, restar `received_quantity` de la OC y recalcular su estado.
**Módulo:** `Purchases`
**Permisos:** `purchase_receipts.cancel`
**Criterios de aceptación:**
- [ ] Si la reversión deja stock negativo (con el parámetro en `false`) → 422 sin cambios.
- [ ] Una recepción vinculada a una factura de proveedor registrada (spec 024) → 422 "Anule primero la factura del proveedor".
- [ ] Una OC `closed` no vuelve a abrirse; una OC `received` vuelve a `partially_received` o `approved`.

### US-5 — Consultar recepciones
**Como** almacenero **quiero** listar recepciones y verlas en el detalle de la OC **para** hacer seguimiento.
**Cuando** se solicita `GET /api/v1/purchases/receipts` (filtros `status`, `provider_id`, `purchase_order_id`, `warehouse_id`, `date_from`, `date_to`, `search`) o `GET /api/v1/purchases/receipts/{id}`, **el sistema debe** listar las recepciones de la sede activa o devolver el detalle con líneas y movimientos de kardex.
**Módulo:** `Purchases`
**Permisos:** `purchase_receipts.view`
**Criterios de aceptación:**
- [ ] El detalle de la OC (spec 022) incluye sus recepciones.
- [ ] UI: `/purchases/receipts` (listado), `/purchases/receipts/new?order_id=` (formulario precargado) y `/purchases/receipts/[id]`.

## 4. Reglas de negocio e invariantes
- El stock de compras entra solo mediante recepciones confirmadas y a través de `StockLedgerService`.
- El costo de entrada está en moneda base, sin impuesto y neto de descuentos.
- `received_quantity` de cada línea de OC = Σ cantidades de recepciones `confirmed` que la referencian.
- Transiciones: `draft → confirmed | cancelled`, `confirmed → cancelled`.

## 5. Requisitos no funcionales
- Confirmar una recepción de 100 líneas en < 1 s.
- Auditoría de estado; `inventory:verify-stock` sin diferencias tras confirmar y anular.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Lotes, series y fechas de vencimiento por línea → spec futuro (junto con el control de lotes en stock).
- Devoluciones a proveedor → spec futuro (con nota de crédito de proveedor en CxP).
- Costos adicionales prorrateados (flete, aduanas) → spec futuro.
- Facturas del proveedor y pagos → spec 024.
- Conformidad de servicios por área con flujo propio (Multinort) → simplificada como recepción de líneas de servicio.

## 7. Open Questions
- [ ] ¿Se permite recibir sin OC (compra directa)? Propuesta: no; crear una OC rápida y usar "recibir todo". — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `PurchaseReceiptsApiTest` en verde, incluido el ciclo OC → recepción parcial → recepción final → anulación.
