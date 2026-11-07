# 028 · accounts-receivable — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/sales.md") Extensiones de la emisión y cuentas por cobrar
- Los efectos que deben ser **atómicos** con la emisión de una venta (p. ej. el cronograma de cuotas) se implementan como `Sales\Contracts\AfterSaleIssuedHook` etiquetados `sales.after_issue`; los efectos no críticos (notificaciones, integraciones) escuchan el evento `SaleIssued` posterior al commit.
- `sales.balance` y los saldos de `receivable_installments` solo cambian dentro de las transacciones de emisión, cobranza, anulación o nota de crédito; Σ saldos de cuotas vigentes = `sale.balance`.
- La deuda y el crédito disponible de un cliente se obtienen solo con `CreditPolicyService` (`debt()`, `assertAvailable()`).
