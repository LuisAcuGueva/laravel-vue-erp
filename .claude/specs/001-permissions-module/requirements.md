# 001 · permissions-module — Requirements
> **Spec:** 001 · **Alcance:** fullstack · **Módulo:** Permissions · **Prioridad:** alta · **Depende de specs:** —
> **Status:** Draft
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara
> **Origen:** Vistas de referencia `/home/luis/Template Multi Tenant/front-multi-tenancy` (`pages/*/permissions`, `modules/permissions`)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
>
---
## 1. Contexto de Negocio
El proyecto ya usa `spatie/permission` con modelos UUID (`App\Modules\Auth\Models\Permission`) y un `RolePermissionSeeder` que crea permisos con formato `modulo.accion` (`users.view`, `clients.create`, …). La estructura de tablas se alinea con la del proyecto `Template Multi Tenant` (`back-multi-tenancy`): permisos con `name`, `slug`, `module`, `description` y borrado lógico, sin `guard_name`. El frontend los consume vía `authStore.permissions` y `useAcl().can()`. Hoy **no existe** API ni UI para administrarlos: solo se crean por seeder.

Este spec habilita un catálogo administrable de permisos (listar, crear, editar, eliminar) para que un administrador pueda registrar los permisos de nuevos módulos sin tocar el seeder. Es la base de 002 (roles) y 003 (usuarios).

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Administrador | Usuario con rol `admin` | JWT (`JwtAuthenticate`); tiene todos los permisos |
| Gestor de accesos | Usuario con permisos `permissions.*` asignados | JWT; solo las acciones concedidas |

## 3. User Stories (formato EARS)
### US-1 — Listar permisos
**Como** administrador **quiero** ver el catálogo de permisos con búsqueda y paginación **para** conocer qué acciones existen.
**Cuando** se solicita `GET /api/v1/permissions`, **el sistema debe** devolver los permisos paginados, ordenados por `module` y `slug`, filtrables por `search` (name/slug/description) y `module`.
**Módulo:** `Permissions` (`/api/v1/permissions`)
**Permisos:** `permissions.view`
**Criterios de aceptación:**
- [ ] Responde 200 con `{ "data": [{ id, name, slug, module, description, roles_count, created_at, updated_at }], "meta": { current_page, per_page, total, last_page } }`.
- [ ] `per_page` acepta 1–100 (default 15); fuera de rango devuelve 422.
- [ ] Sin token devuelve 401; sin `permissions.view` devuelve 403.
- [ ] UI: `VDataTable` con estados de carga, tabla vacía ("No hay permisos registrados") y búsqueda con debounce de 350 ms.

### US-2 — Crear permiso
**Como** administrador **quiero** registrar un permiso **para** protegerlo en un módulo nuevo.
**Cuando** se envía `POST /api/v1/permissions` con datos válidos, **el sistema debe** persistirlo y devolver 201.
**Permisos:** `permissions.create`
**Criterios de aceptación:**
- [ ] `name` obligatorio (etiqueta legible, máx. 125).
- [ ] `slug` obligatorio, formato `modulo.accion` en minúsculas (`^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$`), máx. 125, único entre permisos no eliminados; si no, 422 con `{ "message": "...", "errors": { "slug": [...] } }`.
- [ ] `module` obligatorio, máx. 100; si se omite se deriva del prefijo del `slug`; si se envía debe coincidir con dicho prefijo.
- [ ] `description` opcional (texto).
- [ ] El `id` devuelto es UUID.
- [ ] UI: drawer con validación inline y mensaje de error del servidor.

### US-3 — Editar permiso
**Como** administrador **quiero** modificar nombre, módulo, slug y descripción de un permiso.
**Cuando** se envía `PUT|PATCH /api/v1/permissions/{id}`, **el sistema debe** actualizarlo y devolver 200.
**Permisos:** `permissions.update`
**Criterios de aceptación:**
- [ ] Mismas validaciones que US-2 (unicidad ignora el propio registro).
- [ ] `{id}` no UUID → 404; inexistente → 404 con `{ "message": "Permiso no encontrado: {id}" }`.
- [ ] Cambiar el `slug` invalida el caché de Spatie (`forgetCachedPermissions`); la UI advierte que el código que usa el slug anterior dejará de funcionar.

### US-4 — Eliminar permiso
**Como** administrador **quiero** eliminar un permiso que ya no se usa.
**Cuando** se envía `DELETE /api/v1/permissions/{id}`, **el sistema debe** eliminarlo lógicamente (soft delete) y devolver 204.
**Permisos:** `permissions.delete`
**Criterios de aceptación:**
- [ ] Si está asignado a algún rol devuelve 409 con `{ "message": "El permiso está asignado a N rol(es)." }` y no elimina.
- [ ] UI: diálogo de confirmación; muestra el mensaje 409 dentro del diálogo.

### US-5 — Visibilidad en la UI según permisos
**Como** usuario **quiero** ver solo las acciones que puedo ejecutar.
**Cuando** se renderiza `/permissions`, **el sistema debe** ocultar botones "Nuevo", editar y eliminar si `useAcl().can()` es falso, y no mostrar el ítem de menú sin `permissions.view`.
**Permisos:** `permissions.view` (ruta y menú)
**Criterios de aceptación:**
- [ ] Meta de página `action: 'permissions.view'` y entrada en `navigation/vertical/index.js` con `action: 'permissions.view'`.

## 4. Reglas de negocio e invariantes
- El `slug` (`modulo.accion`) es el identificador funcional: es lo que se evalúa en `permission:` / `useAcl().can()` y lo que `/auth/me` devuelve en `permissions`. `name` es solo etiqueta.
- Estructura igual a multi-tenant: `permissions` sin `guard_name`, con `slug`, `module`, `description`, `deleted_at`; unicidad de `slug` solo entre filas vivas.
- No se elimina un permiso asignado a roles.
- El rol `admin` recibe automáticamente todo permiso nuevo (el seeder lo sincroniza; en runtime, al crear un permiso se asigna al rol `admin`).

## 5. Requisitos no funcionales
- Validación estricta en FormRequests; mensajes en español.
- Gates: `vendor/bin/pint --test`, `make test`, `npm run lint`.
- Listado < 300 ms con 500 permisos (índice único parcial por `slug`).

## 6. Out of Scope
- Asignación de permisos a roles → spec 002.
- Asignación de roles a usuarios → spec 003.
- Permisos directos a usuario (`model_has_permissions`) → spec futuro.
- Protección con `permission:` de las rutas de Clients → spec propio del módulo Clients (decidido).

## 7. Open Questions
- [ ] ¿Se admite crear permisos desde la UI en producción o solo vía seeder? Propuesta: sí, con `permissions.create`. (Dueño: Luis Acuña)
- [x] Reemplazo del permiso `roles.manage` por `roles.view/create/update/delete`: **aprobado** (ver spec 002).

## 8. Métricas de éxito
- Feature tests de `PermissionsApiTest` en verde cubriendo US-1…US-4 (200/201/204/401/403/404/409/422).
- Un permiso nuevo creado desde la UI aparece en `/auth/me` de un usuario del rol `admin` tras re-login.
