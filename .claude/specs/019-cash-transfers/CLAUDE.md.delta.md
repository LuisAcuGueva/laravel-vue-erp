# 019 · cash-transfers — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/finance.md") Operaciones entre dos ledgers o sesiones
- Una operación que mueve dinero entre dos sesiones o cuentas (transferencias, depósitos, retiros) registra ambos lados en la **misma transacción** y bloquea los registros involucrados en orden determinista (por `id`).
- La validación de efectivo para nuevas salidas usa `CashSessionSummaryService::availableCash()` (esperado menos lo reservado por transferencias pendientes), no `expectedCash()`.
- Quien aprueba una operación de dinero no puede ser quien la creó (segregación de funciones); el Service lo valida y responde 422.
