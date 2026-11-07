# 001 · permissions-module — Design
> **Spec:** 001 · **Alcance:** fullstack · **Módulo:** Permissions · **Prioridad:** alta · **Depende de specs:** —
> **Status:** Draft
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md)
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
Backend: nuevo módulo `back-laravel/app/Modules/Permissions/` (Controllers, Requests, Resources, Services, Providers) generado con `php artisan make:module Permissions` y refinado. **No** crea modelo propio: reutiliza `App\Modules\Auth\Models\Permission` (se adapta al estilo de `PermissionModel` de `Template Multi Tenant/back-multi-tenancy/app/Core/Identity/Infrastructure/Persistence/Models/`).
Frontend: `front-vue/src/modules/permissions/{services,stores,components}` y `front-vue/src/pages/permissions/index.vue`, tomando como referencia `Template Multi Tenant/front-multi-tenancy/src/modules/permissions` y `pages/tenant/permissions/index.vue`.

## 1. Backend — Modelo y Persistencia
Estructura objetivo (idéntica a `create_permission_tables` de multi-tenant):

| Tabla | Columnas |
|---|---|
| `permissions` | `id` uuid PK, `name` string, `slug` string, `module` string, `description` text null, timestamps, `deleted_at`; **sin** `guard_name`; único parcial `slug` (`deleted_at IS NULL`) |
| `roles` | `id` uuid PK, `name` string, `description` text null, timestamps, `deleted_at`; **sin** `guard_name`; único parcial `name` |
| `model_has_permissions`, `model_has_roles`, `role_has_permissions` | pivotes con `uuid` y FK `cascadeOnDelete` (como multi-tenant) |

Modelo `App\Modules\Auth\Models\Permission` (ajustar, tomando de `PermissionModel`):
- `use HasUuids, SoftDeletes;` `$fillable = ['name','slug','module','description']`.
- Quitar `guard_name`: constructor hace `unset($this->attributes['guard_name'])`, `setGuardNameAttribute()` no-op, `getGuardNameAttribute()` → `'default'`, `getGuardNames()` → `collect(['web','api','default'])`, `ensureModelSharesGuard()` no-op.
- `create()`, `findByName()`, `findById()`, `findOrCreate()` resuelven por `slug` (derivan `module` del prefijo si falta).
- `scopeSearch` (`name|slug|description ILIKE`), `scopeModule`.

## 2. Backend — Lógica de Negocio y Servicios
`App\Modules\Permissions\Services\PermissionService`:
- `list(array $filters): LengthAwarePaginator` — `search`, `module`, `per_page`, `page`; `withCount('roles')`, `orderBy('module')->orderBy('slug')`.
- `findById(string $id): ?Permission`.
- `create(array $attributes): Permission` — deriva `module` si falta, asigna al rol `admin` si existe, `forgetCachedPermissions()`.
- `update(Permission $p, array $attributes): Permission` — `forgetCachedPermissions()`.
- `delete(Permission $p): void` — lanza `PermissionInUseException` (→ 409) si `roles()->exists()`; luego soft delete.

## 3. Backend — Presentation
- `PermissionController` (delgado, patrón de `ClientController`, ids tipados `string`).
- `PermissionRequest` (`name` required max 125; `slug` con regex y `Rule::unique('permissions','slug')->whereNull('deleted_at')->ignore($id)`; `module` nullable max 100 y coincidente con el prefijo del slug; `description` nullable string) con `toAttributes()`.
- `ListPermissionsRequest` (`search`, `module`, `per_page` 1–100, `page`).
- `PermissionResource` → `{ id, name, slug, module, description, roles_count, created_at, updated_at }`.
- `PermissionsServiceProvider` registrado en `bootstrap/providers.php`.
- Rutas (en `routes/api.php`):

```php
Route::prefix('v1/permissions')->middleware(JwtAuthenticate::class)->group(function () {
    Route::get('/', [PermissionController::class, 'index'])->middleware('permission:permissions.view');
    Route::post('/', [PermissionController::class, 'store'])->middleware('permission:permissions.create');
    Route::get('/{id}', [PermissionController::class, 'show'])->whereUuid('id')->middleware('permission:permissions.view');
    Route::match(['put', 'patch'], '/{id}', [PermissionController::class, 'update'])->whereUuid('id')->middleware('permission:permissions.update');
    Route::delete('/{id}', [PermissionController::class, 'destroy'])->whereUuid('id')->middleware('permission:permissions.delete');
});
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/permissions` | jwt + `permissions.view` | query: `search`, `module`, `per_page`, `page` | 200 `{data[], meta}` | 401, 403, 422 |
| POST | `/api/v1/permissions` | jwt + `permissions.create` | `{ name, slug, module?, description? }` | 201 `{data}` | 401, 403, 422 |
| GET | `/api/v1/permissions/{id}` | jwt + `permissions.view` | — | 200 `{data}` | 401, 403, 404 |
| PUT/PATCH | `/api/v1/permissions/{id}` | jwt + `permissions.update` | `{ name, slug, module?, description? }` | 200 `{data}` | 401, 403, 404, 422 |
| DELETE | `/api/v1/permissions/{id}` | jwt + `permissions.delete` | — | 204 | 401, 403, 404, 409 |

```json
// POST request
{ "name": "Ver facturas", "slug": "invoices.view", "module": "invoices", "description": "Permite listar facturas" }
// 201
{ "data": { "id": "9d3f…", "name": "Ver facturas", "slug": "invoices.view", "module": "invoices", "description": "Permite listar facturas", "roles_count": 1, "created_at": "…", "updated_at": "…" } }
// 422
{ "message": "El slug del permiso ya existe.", "errors": { "slug": ["El slug del permiso ya existe."] } }
// 409
{ "message": "El permiso está asignado a 2 rol(es)." }
```

## 5. Permisos y seguridad
- Nuevos permisos del sistema: `permissions.view|create|update|delete` (agregar a `RolePermissionSeeder`; `admin` los recibe vía `Permission::all()`).
- Middleware `permission:` (alias ya registrado en `bootstrap/app.php`) evalúa por `slug`; `JwtAuthenticate` hace `Auth::setUser`, por lo que Spatie resuelve el usuario.
- `AuthController` (`login`, `refresh`, `me`) debe devolver `getAllPermissions()->pluck('slug')` en lugar de `pluck('name')`, para que `useAcl().can()` use el slug.
- Frontend: `definePage({ meta: { action: 'permissions.view' } })` y `useAcl().can('permissions.create' | 'permissions.update' | 'permissions.delete')` para botones.

## 6. Migraciones
Decisión: **se editan directamente las migraciones originales** (proyecto plantilla sin datos productivos; se reinicia con `RESET-PLATFORM.md` / `php artisan migrate:fresh --seed`). No se crea migración de alineación.

**Editar `back-laravel/database/migrations/2026_10_13_205451_create_permission_tables.php`** para replicar `Template Multi Tenant/back-multi-tenancy/database/migrations/tenant/2026_10_06_234920_create_permission_tables.php`:

```php
Schema::create($tableNames['permissions'], static function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug');
    $table->string('module');
    $table->text('description')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

Schema::create($tableNames['roles'], static function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->text('description')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
// pivotes model_has_permissions / model_has_roles / role_has_permissions:
// columnas uuid + FK cascadeOnDelete + PKs compuestas (igual que multi-tenant).

SoftDeleteUniqueIndex::make($tableNames['roles'], ['name']);
SoftDeleteUniqueIndex::make($tableNames['permissions'], ['slug']);
```
- Se quita `guard_name` y los únicos `(name, guard_name)`; `teams` permanece deshabilitado.
- Copiar `SoftDeleteUniqueIndex` a `app/Support/Database/SoftDeleteUniqueIndex.php` desde `Template Multi Tenant/back-multi-tenancy/app/Core/Shared/Infrastructure/Persistence/`.
- Leer primero la migración existente y conservar el estilo/`config('permission.*')` que ya usa; solo cambiar las columnas indicadas.
- Verificación: `php artisan migrate:fresh --seed` y `migrate:rollback`.

## 7. Configuración e infraestructura
Sin variables nuevas. `config/permission.php` ya apunta a los modelos UUID; mantener `models.permission` / `models.role`.

## 8. Frontend
- `modules/permissions/services/permissionsApi.js` — `list/get/create/update/delete` sobre `/v1/permissions` (patrón `clientsApi.js`).
- `modules/permissions/stores/permissionsStore.js` — Pinia options store como `rolesStore` de referencia (`items`, `meta`, `isLoading`, `error`, `fetchPermissions`, `createPermission`, `updatePermission`, `deletePermission`).
- `modules/permissions/components/PermissionFormDrawer.vue` — copia de la referencia: campos `name`, `slug` (hint `modulo.accion`), `module`, `description`.
- `pages/permissions/index.vue` — `VDataTable` (Nombre, Slug, Módulo como `VChip`, Descripción, Roles, Acciones), filtro por módulo, búsqueda con debounce, diálogo de borrado con error 409.
- `navigation/vertical/index.js` — ítem "Permisos" (`tabler-key`, `action: 'permissions.view'`).
- Mocks MSW: handlers solo si el repo ya los usa para clients (verificar `public/mockServiceWorker.js`).

## 9. Testing
`back-laravel/tests/Feature/Modules/Permissions/PermissionsApiTest.php` (`RefreshDatabase`, usuario con rol `admin` y token vía `JwtService`):
- listar con paginación/búsqueda/filtro por módulo; 401 sin token; 403 sin permiso.
- crear válido (UUID, `slug`/`module` persistidos, sin `guard_name`, asignado a `admin`); 422 por formato y duplicado.
- actualizar; 404 por uuid inexistente y por id no UUID.
- eliminar libre (204) y asignado (409).
Frontend: `npm run lint`.

## 10. Decisiones Rechazadas
- **Tabla estándar de Spatie (`name` + `guard_name`)**: se descarta por pedido explícito de alinear con la estructura de multi-tenant (`slug`, `module`, soft deletes, sin guard).
- **Permisos solo por seeder**: impide a un administrador registrar permisos de módulos nuevos sin deploy.
- **Eliminación en cascada** de la asignación a roles: oculta impacto; se prefiere 409 explícito.
- **Borrado físico**: se usa soft delete como en multi-tenant, con índice único parcial para reutilizar slugs.
