# 018 · cash-registers-and-sessions — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/finance.md") Ledger de caja
- Todo ingreso o egreso de dinero por caja (cobros, pagos, transferencias, movimientos manuales) se registra con `CashLedgerService::register()` dentro de la transacción del documento de origen y contra una sesión abierta. Prohibido insertar en `cash_movements` desde otro lugar.
- `cash_movements` es inmutable: la anulación se hace con `CashLedgerService::reverse()` mientras la sesión siga abierta; un documento cuya sesión ya cerró no se anula por caja (su spec define la alternativa).
- El acceso a una caja se decide solo con `BoxAccessService::canOperate()` (rol asignado a la caja o admin); ningún controller reimplementa esa regla.
- Los documentos que cobran o pagan en caja guardan `cash_session_id` y obtienen la sesión del frontend vía `useCashSession()`; el backend valida que la sesión esté abierta, sea de la sede activa y que el usuario pueda operar su caja.
