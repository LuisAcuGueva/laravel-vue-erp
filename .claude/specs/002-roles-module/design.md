# 002 · roles-module — Design
> **Spec:** 002 · **Alcance:** fullstack · **Módulo:** Roles · **Prioridad:** alta · **Depende de specs:** 001
> **Status:** Draft
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [001 design](../001-permissions-module/design.md)
>
---
## 0. Ubicación y alcance
Backend: `back-laravel/app/Modules/Roles/` reutilizando `App\Modules\Auth\Models\Role`. Frontend: `front-vue/src/modules/roles/`, `src/pages/roles/index.vue`, `src/modules/roles/components/{RoleFormDrawer,RolePermissionsDialog}.vue`. Referencias: `modules/roles/**`, `pages/tenant/roles/index.vue`, `components/dialogs/ManageRolePermissionsModal.vue` del proyecto multi-tenant.

## 1. Backend — Modelo y Persistencia
Estructura de `roles` y pivotes definida en spec 001 §1/§6 (sin `guard_name`, con `description` y `deleted_at`). El modelo `App\Modules\Auth\Models\Role` sigue el estilo de `RoleModel` de multi-tenant (`HasUuids`, `SoftDeletes`, sin guard). `Role::$fillable = ['name','description']`; `scopeSearch` por nombre/descripción; accessor `is_protected` (`in_array($name, config('permission.protected_roles', ['admin']))`).

## 2. Backend — Lógica de Negocio y Servicios
`App\Modules\Roles\Services\RoleService`:
- `list(array $filters)` — `with('permissions:id,name')`, `withCount('users')`, `search`, `orderBy('name')`.
- `findById(string $id): ?Role`.
- `create(array $attributes, array $permissions = []): Role` — transacción; `syncPermissions` (slugs resueltos a modelos).
  Al ser guard-less, `syncPermissions` debe recibir modelos `Permission` obtenidos con `whereIn('slug', …)`.
- `update(Role $role, array $attributes, ?array $permissions): Role` — rechaza protegido (`ProtectedRoleException` → 422); `syncPermissions` solo si `$permissions !== null`.
- `delete(Role $role): void` — protegido → 422; `users()->exists()` → `RoleInUseException` (409).
- Tras mutar: `app(PermissionRegistrar::class)->forgetCachedPermissions()`.

## 3. Backend — Presentation
- `RoleController` delgado (patrón `ClientController`).
- `RoleRequest`: `name` regex + `Rule::unique('roles','name')->whereNull('deleted_at')->ignore($id)`; `description` nullable string; `permissions` sometimes array; `permissions.*` `Rule::exists('permissions','slug')->whereNull('deleted_at')`. `toAttributes()` y `permissions()`.
- `ListRolesRequest`, `RoleResource`, `RolesServiceProvider` (+ `bootstrap/providers.php`).
- Rutas:

```php
Route::prefix('v1/roles')->middleware(JwtAuthenticate::class)->group(function () {
    Route::get('/', [RoleController::class, 'index'])->middleware('permission:roles.view');
    Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create');
    Route::get('/{id}', [RoleController::class, 'show'])->whereUuid('id')->middleware('permission:roles.view');
    Route::match(['put', 'patch'], '/{id}', [RoleController::class, 'update'])->whereUuid('id')->middleware('permission:roles.update');
    Route::delete('/{id}', [RoleController::class, 'destroy'])->whereUuid('id')->middleware('permission:roles.delete');
});
```

## 4. Contrato API
| Método | Ruta | Permiso | Body | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `/api/v1/roles` | `roles.view` | query `search, per_page, page` | 200 `{data[], meta}` | 401, 403, 422 |
| POST | `/api/v1/roles` | `roles.create` | `{ name, description?, permissions?[] }` | 201 `{data}` | 401, 403, 422 |
| GET | `/api/v1/roles/{id}` | `roles.view` | — | 200 `{data}` | 404 |
| PUT/PATCH | `/api/v1/roles/{id}` | `roles.update` | `{ name, description?, permissions?[] }` | 200 `{data}` | 404, 422 |
| DELETE | `/api/v1/roles/{id}` | `roles.delete` | — | 204 | 404, 409, 422 |

```json
{ "data": { "id": "…", "name": "supervisor", "description": "Supervisa ventas",
  "permissions": ["clients.view", "reports.view"], "users_count": 0, "is_protected": false,
  "created_at": "…", "updated_at": "…" } }
```

## 5. Permisos y seguridad
- Nuevos: `roles.view|create|update|delete` (seeder; se retira `roles.manage`, ver Open Questions).
- Un usuario con `roles.update` podría otorgarse permisos que no tiene: se valida que los permisos enviados estén contenidos en los del solicitante salvo `admin` (422 "No puede asignar permisos que no posee").
- Frontend: `definePage({ meta: { action: 'roles.view' } })`; botones con `can('roles.create'|'roles.update'|'roles.delete')`; ítem de menú "Roles".

## 6. Migraciones
Sin migración propia: usa la migración original `create_permission_tables` editada en spec 001 T-1. Actualizar `RolePermissionSeeder` (idempotente) para crear roles sin `guard_name`.

## 7. Configuración e infraestructura
`config/permission.php`: agregar `'protected_roles' => ['admin']` (sin env; constante de dominio).

## 8. Frontend
- `modules/roles/services/rolesApi.js` (`/v1/roles`), `stores/rolesStore.js` (referencia de `rolesStore.js`, meta con `current_page/last_page`).
- `components/RoleFormDrawer.vue` (nombre, descripción; nombre deshabilitado si `is_protected`).
- `components/RolePermissionsDialog.vue` — adaptación de `ManageRolePermissionsModal.vue`: **se simplifica** a lista agrupada por `module` con búsqueda por name/slug y "seleccionar módulo"; emite `save({ roleId, permissions })` con slugs (mismo contrato que la referencia).
- `pages/roles/index.vue`: tabla/tarjetas con chips de permisos (máx. 3 + "+N"), acciones editar/permisos/eliminar, rol protegido con candado y acciones deshabilitadas.

## 9. Testing
`back-laravel/tests/Feature/Modules/Roles/RolesApiTest.php`: listar (permisos y `users_count`), crear con permisos, 422 duplicado/permiso inexistente, actualizar y sincronizar, protegido 422, eliminar 204/409, 401/403, 404 id no UUID, escalación de privilegios 422. Front: `npm run lint`.

## 10. Decisiones Rechazadas
- **Multi-tenant (`pages/admin` vs `pages/tenant`)**: no aplica; una sola página `/roles`.
- **Endpoint separado `PUT /roles/{id}/permissions`**: se mantiene el contrato de la referencia (permisos dentro de `PUT /roles/{id}`) para menos superficie.
- **Referenciar permisos por UUID**: el frontend y `/auth/me` ya trabajan con slugs.
