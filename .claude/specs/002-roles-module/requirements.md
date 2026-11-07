# 002 · roles-module — Requirements
> **Spec:** 002 · **Alcance:** fullstack · **Módulo:** Roles · **Prioridad:** alta · **Depende de specs:** 001
> **Status:** Draft
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara
> **Origen:** Vistas de referencia `/home/luis/Template Multi Tenant/front-multi-tenancy` (`pages/*/roles`, `modules/roles`, `components/dialogs/ManageRolePermissionsModal.vue`)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) · [.agents/rules/](../../../.agents/rules/) · [routes/api.php](../../../back-laravel/routes/api.php)
> - [001 permissions-module](../001-permissions-module/requirements.md)
>
---
## 1. Contexto de Negocio
Existen tres roles sembrados (`admin`, `manager`, `user`) con permisos fijos por `RolePermissionSeeder`; no hay forma de crear roles ni cambiar sus permisos sin deploy. La tabla `roles` se alinea con multi-tenant: `id` uuid, `name`, `description`, timestamps y `deleted_at`, sin `guard_name`. Este spec agrega la gestión de roles y la asignación de permisos (catálogo del spec 001) desde API y UI.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Administrador | Rol `admin` | JWT; todos los permisos |
| Gestor de accesos | Usuario con `roles.*` | JWT; acciones concedidas |

## 3. User Stories (formato EARS)
### US-1 — Listar roles
**Como** administrador **quiero** ver los roles con sus permisos y cantidad de usuarios.
**Cuando** se solicita `GET /api/v1/roles`, **el sistema debe** devolver los roles paginados con `permissions` (lista de slugs) y `users_count`, filtrables por `search`.
**Módulo:** `Roles` (`/api/v1/roles`)
**Permisos:** `roles.view`
**Criterios de aceptación:**
- [ ] 200 `{ "data": [{ id, name, description, permissions: ["clients.view"], users_count, is_protected, created_at, updated_at }], "meta": {…} }`.
- [ ] `per_page` 1–100; 401 sin token; 403 sin permiso.
- [ ] UI: tarjetas o tabla con estados loading/vacío y búsqueda con debounce.

### US-2 — Crear rol
**Cuando** se envía `POST /api/v1/roles` con datos válidos, **el sistema debe** crear el rol, sincronizar sus permisos y devolver 201.
**Permisos:** `roles.create`
**Criterios de aceptación:**
- [ ] `name` obligatorio, `^[a-z][a-z0-9_-]*$`, máx. 125, único entre roles no eliminados (422 si no).
- [ ] `description` opcional máx. 255; `permissions` opcional, array de `slug` de permisos existentes (422 si alguno no existe).
- [ ] UI: drawer con nombre, descripción.

### US-3 — Editar rol
**Cuando** se envía `PUT|PATCH /api/v1/roles/{id}`, **el sistema debe** actualizar nombre, descripción y, si viene `permissions`, sincronizarlos.
**Permisos:** `roles.update`
**Criterios de aceptación:**
- [ ] 404 si no existe o `{id}` no es UUID.
- [ ] El rol protegido `admin` no puede renombrarse ni perder permisos: 422 con mensaje "El rol admin es de sistema y no puede modificarse".
- [ ] El caché de Spatie se invalida.

### US-4 — Asignar permisos a un rol
**Como** administrador **quiero** un modal con permisos agrupados por módulo, buscador, y selección por módulo o individual (equivalente a `ManageRolePermissionsModal`).
**Cuando** se guarda, **el sistema debe** enviar `permissions: [slugs]` vía `PUT /api/v1/roles/{id}`.
**Permisos:** `roles.update`
**Criterios de aceptación:**
- [ ] El modal carga el catálogo desde `GET /api/v1/permissions?per_page=100` (requiere `permissions.view`; sin él se muestra aviso y el botón queda deshabilitado).
- [ ] Tras guardar, la tabla refleja los permisos sin recargar la página.

### US-5 — Eliminar rol
**Cuando** se envía `DELETE /api/v1/roles/{id}`, **el sistema debe** eliminarlo lógicamente (soft delete) y devolver 204.
**Permisos:** `roles.delete`
**Criterios de aceptación:**
- [ ] Rol protegido → 422; rol con usuarios asignados → 409 `{ "message": "El rol tiene N usuario(s) asignado(s)." }`.
- [ ] UI: diálogo de confirmación con el mensaje de error.

## 4. Reglas de negocio e invariantes
- Rol protegido: `admin` (configurable en `config/permission.php` → `protected_roles`). No se renombra ni elimina; siempre posee todos los permisos.
- No se elimina un rol con usuarios.
- Un nombre de rol es único entre roles no eliminados (índice único parcial).
- Los permisos se referencian por `slug`, no por id, en el contrato.

## 5. Requisitos no funcionales
- Sincronización de permisos en transacción; mensajes en español; Pint, `make test`, ESLint en verde.
- Evitar N+1 (`with('permissions')`, `withCount('users')`).

## 6. Out of Scope
- CRUD del catálogo de permisos → spec 001.
- Asignación de roles a usuarios → spec 003.
- Roles por tenant / multi-tenancy → spec futuro (la referencia lo incluye; este repo es single-tenant).
- Jerarquía/herencia de roles → spec futuro.

## 7. Open Questions
- [x] Reemplazo de `roles.manage` por `roles.view|create|update|delete`: **aprobado**; el seeder elimina `roles.manage` y concede los 4 nuevos a `admin`.
- [ ] ¿Roles `manager`/`user` también son protegidos? Propuesta: no. (Dueño: Luis Acuña)

## 8. Métricas de éxito
- `RolesApiTest` en verde (US-1…US-5).
- Cambiar permisos de un rol desde la UI se refleja en `/auth/me` del usuario tras refresh de token.
