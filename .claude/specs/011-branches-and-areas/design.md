# 011 · branches-and-areas — Design
> **Spec:** 011 · **Alcance:** fullstack · **Módulo:** Maintenance · **Prioridad:** alta · **Depende de specs:** 003, 008
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend:** módulo nuevo `back-laravel/app/Modules/Maintenance/` (agrupa los maestros organizacionales; el spec 012 agrega `Employee`):
  - `Models/Branch.php`, `Models/Area.php`
  - `Services/BranchService.php`, `Services/AreaService.php`, `Services/UserBranchService.php`
  - `Controllers/BranchController.php`, `Controllers/AreaController.php`, `Controllers/UserBranchController.php`
  - `Requests/{BranchRequest,ListBranchesRequest,AreaRequest,ListAreasRequest,SyncUserBranchesRequest}.php`
  - `Resources/{BranchResource,BranchOptionResource,AreaResource,UserBranchResource}.php`
  - `Exceptions/BranchRuleException.php` (422 con mensaje de negocio)
  - `Providers/MaintenanceServiceProvider.php`
- **Transversal:** `app/Support/Branch/ActiveBranch.php` (contenedor por petición), `app/Http/Middleware/ResolveActiveBranch.php` (alias `branch` en `bootstrap/app.php`) y `app/Support/Branch/BranchAccessGuard.php` (autorización por registro).
- **Modificados:** `app/Models/User.php` (relación `branches()`, `defaultBranch()`), `Users/Resources/UserResource.php` (campo `branches`), `UserService::list/findById` (eager load `branches`), `Auth/Controllers/AuthController::me` (campo `branches`).
- **Frontend:** `front-vue/src/pages/maintenance/{branches,areas}/index.vue`, `front-vue/src/modules/maintenance/{services,stores,components}/`, `components/common/BranchSwitcher.vue` en el navbar, `utils/api.js` (cabecera `X-Branch-Id`), drawer de usuarios.

## 1. Backend — Modelo y Persistencia
### `Branch`
- `HasUuids`, `SoftDeletes`, `LogsActivity` (`logAll()->logOnlyDirty()`), `HasFactory`.
- `$fillable`: `code`, `name`, `address`, `ubigeo`, `phone`, `email`, `is_main`, `is_active`.
- Casts: `is_main`, `is_active` → `boolean`.
- Relaciones: `users(): BelongsToMany` (`user_branches`, `withPivot('is_default')`, `withTimestamps()`).
- Scopes: `scopeSearch($term)` (code/name con `ilike` como en `Client`), `scopeActive()`.
- Mutator: `code` en mayúsculas.

### `Area`
- `HasUuids`, `SoftDeletes`, `LogsActivity`, `HasFactory`. `$fillable`: `name`, `description`, `is_active`. Scopes `search`, `active`.

### `User` (modificado)
- `branches(): BelongsToMany` → `Branch::class`, `user_branches`, `withPivot('is_default')->withTimestamps()`.
- `defaultBranch(): ?Branch` → la sede con pivot `is_default = true`.
- `isAdmin(): bool` → `hasRole('admin')` (hoy el chequeo está repetido en `UserService`/`UserRequest`; reemplazarlo es opcional).

## 2. Backend — Lógica de Negocio y Servicios
### `BranchService`
- `list(array $filters)`: `withCount('users')`, `search`, filtro `is_active`, `orderBy('code')`, `paginate`.
- `options()`: activas, `orderBy('name')`, columnas `id, code, name`.
- `create(array $attrs)`: en `DB::transaction`; si no existen sedes → fuerza `is_main = true`; si `is_main` → `Branch::where('is_main', true)->update(['is_main' => false])` antes de crear.
- `update(Branch $b, array $attrs)`: si `$b->is_main` y llega `is_main = false` → `BranchRuleException('Debe existir una sede principal; marque otra sede como principal.')`; si `$b->is_main` y `is_active = false` → excepción; si llega `is_main = true` → desmarca la anterior.
- `delete(Branch $b)`: excepción si `is_main` o `users()->exists()`. Los specs 013/018/027 agregan verificaciones de almacenes, cajas y series aquí mismo.

### `AreaService`
- `list`, `options`, `findById`, `create`, `update`, `delete` (igual que `ClientService`).

### `UserBranchService`
- `sync(User $user, array $branchIds, string $defaultBranchId): User` — en transacción: `$user->branches()->sync(array_fill_keys($branchIds, ['is_default' => false]))` y luego `updateExistingPivot($defaultBranchId, ['is_default' => true])`. Registra la actividad manualmente: `activity()->performedOn($user)->withProperties(['branches' => $branchIds, 'default' => $defaultBranchId])->log('branches_synced')`.
- `accessibleBranches(User $user): Collection` — `admin` → todas las activas (con `is_default` según su pivot si lo tiene); resto → `$user->branches()->where('is_active', true)`.

### `ActiveBranch` + `ResolveActiveBranch`
- `ActiveBranch` (registrado con `scoped()` en `MaintenanceServiceProvider`): `set(Branch)`, `get(): Branch`, `id(): string`, `has(): bool`. `get()` sin resolver lanza `LogicException` (uso indebido fuera del middleware).
- `ResolveActiveBranch::handle()`:
  1. `$id = $request->header('X-Branch-Id')`.
  2. Si `$id` no es UUID válido → 403. Si viene, busca entre `accessibleBranches($user)`; si no está → 403 `{ "message": "No tiene acceso a la sede seleccionada." }`.
  3. Si no viene → `defaultBranch()` o, para `admin`, la sede principal; si no hay ninguna → 409 `{ "message": "El usuario no tiene sedes asignadas." }`.
  4. `app(ActiveBranch::class)->set($branch)`.
- **Uso restringido de `ActiveBranch`:** solo controllers y FormRequests lo leen. Los servicios reciben la sede como parámetro (`create(array $data, Branch $branch, User $actor)`); `ActiveBranch::get()` llamado sin petición HTTP (jobs, comandos) lanza `LogicException`, lo que hace visible el uso indebido en tests.
- La actividad de auditoría de las peticiones con sede agrega `branch_id` a `properties` (tap en `LogOptions` o `activity()->withProperties`), para trazar desde qué sede se operó.

### `BranchAccessGuard` (autorización por registro)
- `assertAccessible(User $u, ?string $branchId): void` → si `$branchId` no está en `UserBranchService::accessibleBranches($u)` (admin: todas las activas e inactivas) lanza `ModelNotFoundException` (404, para no revelar existencia).
- `scopeQuery(Builder $q, User $u, string $column = 'branch_id')` → filtra por sedes accesibles (para búsquedas globales o enlaces).
- Patrón en controllers de documentos con sede: `index` filtra por `ActiveBranch::id()`; `show`/`update`/acciones cargan el registro por id y llaman `assertAccessible($user, $model->branch_id)`; `store` usa `ActiveBranch::get()`.
- Los specs 013–029 aplican este patrón (regla delta).
- Se aplica con `->middleware('branch')` solo en rutas que lo necesiten (specs 013+). En este spec se prueba con una ruta registrada solo en tests.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `BranchRequest`: `code` required|string|max:10|regex:/^[A-Za-z0-9-]+$/ + `Rule::unique('branches','code')->ignore($id)->whereNull('deleted_at')`; `name` required|max:150|unique (ídem); `address` nullable|max:255; `ubigeo` nullable|digits:6; `phone` nullable|max:30; `email` nullable|email|max:150; `is_main` boolean; `is_active` boolean. `toAttributes()` con `trim` y `strtoupper(code)`.
- `ListBranchesRequest`: `page`, `per_page` (≤ 100), `search` (≤ 100), `is_active` nullable|boolean.
- `AreaRequest`: `name` required|max:100|unique ignorando eliminados; `description` nullable|max:500; `is_active` boolean.
- `SyncUserBranchesRequest`: `branch_ids` required|array|min:1; `branch_ids.*` uuid|distinct|`Rule::exists('branches','id')->whereNull('deleted_at')->where('is_active', true)`; `default_branch_id` required|uuid|`in:` (valores de `branch_ids`, vía `Rule::in($this->input('branch_ids', []))`).
- `BranchResource`: `id, code, name, address, ubigeo, phone, email, is_main, is_active, users_count (whenCounted), created_at, updated_at`.
- `UserBranchResource`: `id, code, name, is_default` (desde `pivot`).
- `BranchRuleException` → 422 `{ "message": "...", "errors": { "branch": ["..."] } }`.
- Rutas (`routes/api.php`, grupo `v1`):
```php
// Módulo Maintenance — Sedes y Áreas (Spec 011)
Route::prefix('branches')->group(function () {
    Route::get('/options', [BranchController::class, 'options']);
    Route::get('/', [BranchController::class, 'index'])->middleware('permission:branches.view');
    Route::post('/', [BranchController::class, 'store'])->middleware('permission:branches.create');
    Route::get('/{id}', [BranchController::class, 'show'])->whereUuid('id')->middleware('permission:branches.view');
    Route::match(['put', 'patch'], '/{id}', [BranchController::class, 'update'])->whereUuid('id')->middleware('permission:branches.update');
    Route::delete('/{id}', [BranchController::class, 'destroy'])->whereUuid('id')->middleware('permission:branches.delete');
});
Route::prefix('areas')->group(function () { /* mismo patrón con areas.* y /options */ });
Route::put('/users/{id}/branches', [UserBranchController::class, 'update'])->whereUuid('id')->middleware('permission:users.update');
```
- `AuthController::me` agrega `'branches' => UserBranchResource::collection($userBranchService->accessibleBranches($user))`.

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/branches` | `branches.view` | query `search, is_active, page, per_page` | 200 `data: Branch[]`, `meta` | 401, 403, 422 |
| GET | `/api/v1/branches/options` | autenticado | — | 200 `data: [{id, code, name}]` | 401 |
| POST | `/api/v1/branches` | `branches.create` | `code, name, address?, ubigeo?, phone?, email?, is_main?, is_active?` | 201 `data: Branch` | 401, 403, 422 |
| GET | `/api/v1/branches/{id}` | `branches.view` | — | 200 `data: Branch` | 401, 403, 404 |
| PUT | `/api/v1/branches/{id}` | `branches.update` | ídem POST | 200 `data: Branch` | 401, 403, 404, 422 |
| DELETE | `/api/v1/branches/{id}` | `branches.delete` | — | 204 | 401, 403, 404, 422 |
| GET/POST/PUT/DELETE | `/api/v1/areas[/{id}]`, `/areas/options` | `areas.*` / autenticado | `name, description?, is_active?` | 200/201/204 | 401, 403, 404, 422 |
| PUT | `/api/v1/users/{id}/branches` | `users.update` | `{ branch_ids: uuid[], default_branch_id: uuid }` | 200 `data: User` (con `branches`) | 401, 403, 404, 422 |
| GET | `/auth/me` | autenticado | — | 200 + `branches: [{id, code, name, is_default}]` | 401 |

Ejemplo de error de regla de sede:
```json
{ "message": "Debe existir una sede principal; marque otra sede como principal.", "errors": { "branch": ["Debe existir una sede principal; marque otra sede como principal."] } }
```

## 5. Permisos y seguridad
- Permisos nuevos (`RolePermissionSeeder`): módulo `branches` → `branches.view|create|update|delete`; módulo `areas` → `areas.view|create|update|delete`. `admin` recibe todos; `manager` recibe `branches.view` y `areas.view`.
- La sede activa nunca se toma del body en endpoints con contexto de sede: siempre de `ActiveBranch` (regla delta).
- `X-Branch-Id` se agrega a `allowed_headers` de `config/cors.php` si no se permiten todos.

## 6. Migraciones
`back-laravel/database/migrations/2026_10_16_000003_create_branches_table.php`:
```php
Schema::create('branches', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('code', 10);
    $table->string('name', 150);
    $table->string('address')->nullable();
    $table->char('ubigeo', 6)->nullable();
    $table->string('phone', 30)->nullable();
    $table->string('email', 150)->nullable();
    $table->boolean('is_main')->default(false);
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
});
SoftDeleteUniqueIndex::make('branches', ['code']);
SoftDeleteUniqueIndex::make('branches', ['name']);
```
`2026_10_16_000004_create_areas_table.php`:
```php
Schema::create('areas', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name', 100);
    $table->string('description', 500)->nullable();
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
});
SoftDeleteUniqueIndex::make('areas', ['name']);
```
`2026_10_16_000005_create_user_branches_table.php`:
```php
Schema::create('user_branches', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
    $table->boolean('is_default')->default(false);
    $table->timestamps();
    $table->unique(['user_id', 'branch_id']);
});
// Una sola sede por defecto por usuario (índice parcial en PostgreSQL)
DB::statement('CREATE UNIQUE INDEX user_branches_one_default ON user_branches (user_id) WHERE is_default = true');
```
> El pivot tiene PK `uuid`: el `BelongsToMany` usa un modelo pivot `UserBranch extends Pivot` con `HasUuids` e `$incrementing = false` (`->using(UserBranch::class)`).

## 7. Configuración e infraestructura
- `bootstrap/app.php`: alias `'branch' => ResolveActiveBranch::class`.
- `config/cors.php`: permitir `X-Branch-Id`.
- `BranchSeeder` (dev): sede `PRINCIPAL` (`is_main = true`) y asignación del usuario admin del `UserSeeder` como sede por defecto.

## 8. Frontend
- **Páginas:** `src/pages/maintenance/branches/index.vue` (`definePage({ meta: { action: 'branches.view' } })`) y `src/pages/maintenance/areas/index.vue`. Mismo patrón que `pages/clients/index.vue`: `ServerDataTable`, búsqueda con debounce, `VChip` de estado, `VIcon` estrella para la principal, drawer de formulario, `useConfirm` al eliminar, `useApiFormErrors` en el drawer.
- **Módulo:** `src/modules/maintenance/services/{branchesApi,areasApi}.js`, `stores/{branchesStore,areasStore}.js`, `components/{BranchFormDrawer,AreaFormDrawer}.vue`.
- **Usuarios:** en `modules/users/components/UserFormDrawer.vue`, sección "Sedes" con `VAutocomplete multiple chips` (desde `/branches/options`) y `VSelect` "Sede por defecto" filtrado a las elegidas. Tras guardar el usuario, llamar `PUT /users/{id}/branches`. Columna "Sedes" en la tabla de usuarios.
- **Sede activa por pestaña:** `authStore` guarda `branches` de `/auth/me` y `activeBranchId`. Al iniciar la pestaña: `sessionStorage['active_branch_id']` → si no existe, `localStorage['last_branch_id']` → si no, la `is_default`; siempre validado contra `branches`. Al cambiar de sede se escriben ambos (`sessionStorage` para la pestaña, `localStorage` solo como preferencia para pestañas nuevas). No se escucha el evento `storage`, para que una pestaña no cambie a otra.
- **Descargas:** nuevo `utils/download.js` con `downloadFile(url, params, filename)` que usa `$api` con `responseType: 'blob'` (lleva JWT y `X-Branch-Id`) y crea el enlace temporal con `URL.createObjectURL`. Prohibido `<a href>` o `window.open` directos a la API. `components/common/BranchSwitcher.vue` (`VMenu` con la lista) en el navbar del layout, visible si hay más de una sede. `utils/api.js` agrega `X-Branch-Id` desde `authStore.activeBranchId` en `onRequest`. Cambiar de sede recarga la ruta actual (`router.go(0)` o re-fetch).
- **Menú:** grupo `{ title: 'Mantenimiento', icon: { icon: 'tabler-building' }, children: [Sedes (branches.view), Áreas (areas.view)] }` en `navigation/vertical/index.js` (el spec 012 agrega Empleados).

## 9. Testing
- `tests/Feature/Modules/Maintenance/BranchesApiTest.php`: CRUD; código duplicado → 422; reutilizar el código de una sede eliminada → 201; primera sede queda principal; marcar otra como principal desmarca la anterior; desmarcar la principal → 422; eliminar la principal → 422; eliminar con usuarios → 422; 403 sin permiso; options solo activas.
- `tests/Feature/Modules/Maintenance/AreasApiTest.php`: CRUD, duplicado → 422, options.
- `tests/Feature/Modules/Maintenance/UserBranchesApiTest.php`: sync válido; default fuera de la lista → 422; sede inactiva → 422; `/auth/me` incluye `branches`; admin ve todas.
- `tests/Feature/Modules/Maintenance/ActiveBranchMiddlewareTest.php`: registra en `setUp` una ruta de prueba `Route::middleware(['jwt','branch'])->get('/api/test/active-branch', fn () => ['id' => app(ActiveBranch::class)->id()])`; casos: con cabecera válida, sin cabecera (usa default), sede no asignada → 403, usuario sin sedes → 409, admin con cualquier sede activa; `ActiveBranch::get()` fuera de petición → `LogicException`.
- `tests/Feature/Support/BranchAccessGuardTest.php`: registro de sede accesible distinta de la activa → permitido; sede no accesible → 404; admin accede a todas; `scopeQuery` filtra.
- Ejecutar: `make test-filter FILTER=Maintenance`.

## 10. Decisiones Rechazadas
- **Sede activa en el JWT (claim):** cambiar de sede obligaría a reemitir el token; la cabecera validada en cada petición es más simple y segura.
- **`branch_id` directo en `users`:** no soporta usuarios multi-sede (cajeros rotativos, supervisores).
- **Multi-tenancy (`stancl/tenancy`) por sede:** descartado en el plan; complica reportes consolidados.
- **`branch_settings` de Multinort (prefijos de documentos por sede):** se reemplaza por series en el spec 027.
