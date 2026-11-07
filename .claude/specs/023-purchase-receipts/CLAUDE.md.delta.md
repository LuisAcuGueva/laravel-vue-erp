# 023 · purchase-receipts — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/inventory.md") Costo de entrada por compras
- El stock comprado entra solo por recepciones confirmadas (`PurchaseReceiptConfirmationService`), nunca por notas de almacén ni por la factura del proveedor.
- El costo unitario de entrada al kardex se expresa en moneda base, sin impuesto y neto de descuentos, por unidad base: `precio neto de la línea de OC (sin impuesto) × tipo de cambio de la OC ÷ factor de la presentación`.
- `catalog_items.last_purchase_cost` solo lo actualiza la confirmación de recepciones.
