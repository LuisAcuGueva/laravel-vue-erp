# 024 · accounts-payable-and-expenses — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/finance.md") Cobros, pagos y cuentas corrientes
- Los documentos que mueven dinero (pagos a proveedores, cobros de ventas y cuentas por cobrar, reembolsos) liquidan siempre con `Finance\Services\MoneySettlementService::settle()` y anulan con `unsettle()`; no llaman directamente a `CashLedgerService` ni a `BankLedgerService`.
- Deuda y pago son documentos distintos: la factura (o venta a crédito) genera cuotas; el pago o cobro se aplica a cuotas mediante asignaciones (`*_allocations`). Los saldos (`paid_amount`, `balance`) de cuotas y documentos se actualizan en la misma transacción del pago y con `lockForUpdate` sobre las cuotas, ordenadas por `id`.
- La moneda de un pago o cobro es la misma de todas las cuotas a las que se aplica; los reportes de saldos nunca suman montos de monedas distintas.
- En el frontend, los campos de método de pago, referencia y sesión de caja usan `components/common/PaymentMethodFields.vue`.
