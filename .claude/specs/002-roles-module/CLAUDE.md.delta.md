# 002 · roles-module — Delta de reglas
> **Instrucciones:** solo reglas duras nuevas; se mergean en [.agents/rules/](../../../.agents/rules/) al cerrar el spec.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/api-authorization.md") Roles protegidos y escalada de privilegios
- Los roles listados en `config('permission.protected_roles')` no se renombran, eliminan ni pierden permisos desde la API.
- Todo endpoint que asigne permisos o roles debe rechazar (422) los que el solicitante no posee, salvo que sea `admin`.
- Todo cambio de roles/permisos debe invalidar el caché con `PermissionRegistrar::forgetCachedPermissions()`.
