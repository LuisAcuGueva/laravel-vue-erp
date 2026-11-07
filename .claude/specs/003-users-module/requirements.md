# 003 · users-module — Requirements
> **Spec:** 003 · **Alcance:** fullstack · **Módulo:** Users · **Prioridad:** alta · **Depende de specs:** 001, 002
> **Status:** Draft
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara
> **Origen:** Vistas de referencia `/home/luis/Template Multi Tenant/front-multi-tenancy` (`pages/*/users`, `modules/users`)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) · [.agents/rules/](../../../.agents/rules/) · [routes/api.php](../../../back-laravel/routes/api.php)
> - [002 roles-module](../002-roles-module/requirements.md)
>
---
## 1. Contexto de Negocio
`App\Models\User` (UUID, `HasRoles`) solo se crea vía `UserSeeder`. No hay administración de usuarios ni asignación de roles desde la aplicación. Este spec agrega el CRUD de usuarios con asignación de roles y alinea la tabla `users` con la de multi-tenant: `name`, `email`, `password`, `status` (20, indexado), `token_version`, `last_login_at`, timestamps y `deleted_at`.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Administrador | Rol `admin` | JWT; todos los permisos |
| Gestor de usuarios | Usuario con `users.*` | JWT; acciones concedidas |

## 3. User Stories (formato EARS)
### US-1 — Listar usuarios
**Cuando** se solicita `GET /api/v1/users`, **el sistema debe** devolver usuarios paginados con sus roles, filtrables por `search` (nombre/email), `role` y `status`.
**Módulo:** `Users` (`/api/v1/users`)
**Permisos:** `users.view`
**Criterios de aceptación:**
- [ ] 200 `{ "data": [{ id, name, email, status, roles: ["admin"], last_login_at, created_at, updated_at }], "meta": {…} }`; nunca expone `password` ni `remember_token`.
- [ ] `role` inexistente devuelve lista vacía (no error); `per_page` 1–100.
- [ ] UI: tabla con avatar/iniciales, chips de rol y de estado, filtros de rol y estado, búsqueda con debounce, estados loading/vacío.

### US-2 — Crear usuario
**Cuando** se envía `POST /api/v1/users`, **el sistema debe** crear el usuario con contraseña hasheada y roles asignados, y devolver 201.
**Permisos:** `users.create`
**Criterios de aceptación:**
- [ ] `name` requerido máx. 255; `email` requerido, válido, único; `password` requerido mín. 8; `status` en `active|inactive` (default `active`); `roles` array de nombres existentes (mín. 1).
- [ ] 422 con `{ "message", "errors": { "email": [...] } }` en incumplimiento.
- [ ] UI: drawer con selector múltiple de roles (desde `GET /api/v1/roles`).

### US-3 — Editar usuario
**Cuando** se envía `PUT|PATCH /api/v1/users/{id}`, **el sistema debe** actualizar datos y sincronizar roles; `password` solo se cambia si viene no vacío.
**Permisos:** `users.update`
**Criterios de aceptación:**
- [ ] 404 si no existe o id no UUID; unicidad de `email` ignora al propio usuario.
- [ ] No se puede quitar el rol `admin` al último administrador: 422.
- [ ] Un usuario sin rol `admin` no puede asignar roles con permisos que no posee: 422.
- [ ] Al cambiar roles, contraseña o `status` se incrementa `token_version` y se revocan los refresh tokens del usuario.
- [ ] Un usuario con `status = inactive` no puede iniciar sesión (401/403) y sus access tokens dejan de ser válidos.

### US-4 — Eliminar usuario
**Cuando** se envía `DELETE /api/v1/users/{id}`, **el sistema debe** eliminarlo lógicamente (soft delete) y devolver 204.
**Permisos:** `users.delete`
**Criterios de aceptación:**
- [ ] No se puede eliminar al propio usuario autenticado ni al último `admin`: 422.
- [ ] Se eliminan sus `refresh_tokens`.
- [ ] UI: diálogo de confirmación; botón oculto en la fila del propio usuario.

### US-5 — Visibilidad por permisos
**Cuando** se renderiza `/users`, **el sistema debe** exigir `users.view` en la ruta y ocultar acciones según `useAcl().can()`.
**Permisos:** `users.view`
**Criterios de aceptación:**
- [ ] Ítem de menú "Usuarios" con `action: 'users.view'`.
- [ ] Sin `roles.view` el selector de roles se muestra deshabilitado con aviso.

## 4. Reglas de negocio e invariantes
- Siempre debe existir al menos un usuario con rol `admin`.
- Un usuario no puede eliminarse a sí mismo ni quitarse su último rol administrador.
- Contraseña con `Hash::make` (cast `hashed`); nunca se devuelve.
- `email` único solo entre usuarios no eliminados (índice único parcial).
- `token_version` viaja en el JWT (claim `tv`); `JwtAuthenticate` rechaza (401) tokens con versión distinta o usuario inactivo.
- `last_login_at` se actualiza en cada login.
- Roles por nombre en el contrato.

## 5. Requisitos no funcionales
- Evitar N+1 (`with('roles:id,name')`); mensajes en español; Pint, `make test`, ESLint en verde.
- Rate limit estándar de la API.

## 6. Out of Scope
- Perfil propio / cambio de contraseña del usuario autenticado → spec futuro.
- Recuperación de contraseña y verificación de email → spec futuro.
- Permisos directos por usuario → spec futuro.

## 7. Open Questions
- [x] `status` de usuario incluido en este spec (`active|inactive`); `token_version` aplicado en JWT. Valores adicionales → spec futuro.
- [ ] ¿Un usuario puede tener múltiples roles (como en la referencia) o uno solo? Propuesta: múltiples. (Dueño: Luis Acuña)

## 8. Métricas de éxito
- `UsersApiTest` en verde (US-1…US-4).
- Un usuario creado desde la UI puede iniciar sesión y recibe los permisos de sus roles en `/auth/me`.
