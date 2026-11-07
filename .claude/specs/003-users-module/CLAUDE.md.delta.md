# 003 · users-module — Delta de reglas
> **Instrucciones:** solo reglas duras nuevas; se mergean en [.agents/rules/](../../../.agents/rules/) al cerrar el spec.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/api-authorization.md") Usuarios y sesiones
- Debe existir siempre al menos un usuario con rol `admin`; ningún endpoint puede dejar el sistema sin uno.
- Todo cambio de roles, contraseña o `status` de un usuario debe incrementar `token_version` y revocar sus `refresh_tokens`.
- Solo usuarios con `status = active` y `token_version` vigente pueden autenticarse.
- Los Resources de usuario nunca exponen `password` ni `remember_token`.
