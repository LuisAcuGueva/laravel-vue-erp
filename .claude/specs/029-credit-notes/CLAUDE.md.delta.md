# 029 · credit-notes — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/sales.md") Notas de crédito
- Toda corrección económica de una venta emitida (devolución, descuento posterior, anulación de un comprobante aceptado) se hace con una nota de crédito emitida por `CreditNoteIssuanceService`; nunca editando la venta ni sus líneas.
- La devolución de mercadería entra al stock con el costo unitario de la línea de venta original (`sale_items.unit_cost`), con tipo `sale_return` y motivo `SALE_RETURN`.
- El monto de una nota se aplica primero al saldo pendiente de la venta (cuotas desde la última, con registro en `credit_note_installment_adjustments`) y el remanente se reembolsa con `MoneySettlementService` (concepto `SALE_REFUND`); `applied_to_balance + refunded_amount = total`.
- La serie de una nota de crédito debe ser coherente con el comprobante que modifica: factura → serie que empieza con `F`, boleta → `B`, nota de venta → otra letra.
