# 016 · warehouse-notes — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ nuevo ".agents/rules/document-lifecycle.md", trigger: always_on) Ciclo de vida de documentos transaccionales
**description:** Estados, transiciones y anulación de documentos que afectan stock o dinero (notas, recepciones, ventas, cobros, pagos).
- Los documentos transaccionales no se eliminan ni usan `SoftDeletes`: se **anulan** (`status = cancelled`, con `cancelled_by`, `cancelled_at` y `cancel_reason` obligatorio de 5 a 500 caracteres, validado con `CancelDocumentRequest`).
- Cada documento define sus transiciones válidas en un enum de estado con un método `canTransitionTo()`; el Service la valida antes de cambiar el estado y lanza una excepción de dominio (422) si no procede.
- Cambiar el estado de un documento usa `lockForUpdate()` sobre la cabecera dentro de la transacción, para evitar dobles aprobaciones o anulaciones.
- Solo los documentos en `draft` se editan; al editar se reemplazan las líneas completas.
- Anular un documento que ya afectó stock o saldos genera movimientos compensatorios (reversión); nunca borra ni edita movimientos previos.
- Las acciones de estado se exponen como `POST /<recurso>/{id}/<accion>` (`approve`, `cancel`, `close`), no como `PUT` del campo `status`.
- En el frontend, la anulación usa `components/common/CancelDocumentDialog.vue`.
