# 001 · permissions-module — Delta de reglas
> **Instrucciones:** solo reglas duras nuevas; se mergean en [.agents/rules/](../../../.agents/rules/) al cerrar el spec.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ nuevo ".agents/rules/api-authorization.md", trigger: always_on) Autorización de endpoints
`description`: Convención de permisos Spatie y protección de rutas de la API.
- Todo permiso se identifica por `slug` con formato `modulo.accion` en minúsculas (`^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$`); `name` es solo etiqueta. Las tablas `permissions` y `roles` no tienen `guard_name` y usan soft deletes (igual que multi-tenant).
- Toda ruta de `routes/api.php` fuera de `/auth` debe llevar `JwtAuthenticate` y, si es de gestión, `->middleware('permission:<modulo.accion>')` por endpoint.
- Todo permiso nuevo debe agregarse a `RolePermissionSeeder` y el rol `admin` debe recibirlo.
- La API y `/auth/me` exponen permisos por `slug`.
- Los permisos se leen en el frontend solo con `useAcl().can()`; no comparar nombres de rol en componentes.
