# 003 · users-module — Design
> **Spec:** 003 · **Alcance:** fullstack · **Módulo:** Users · **Prioridad:** alta · **Depende de specs:** 001, 002
> **Status:** Draft
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md)
>
---
## 0. Ubicación y alcance
Backend: `back-laravel/app/Modules/Users/` reutilizando `App\Models\User` (no se mueve). Frontend: `front-vue/src/modules/users/`, `src/pages/users/index.vue`. Referencias: `modules/users/**`, `pages/tenant/users/index.vue`, `modules/users/components/UserFormDrawer.vue` del proyecto multi-tenant.

## 1. Backend — Modelo y Persistencia
`App\Models\User` ya tiene `HasUuids` y `HasRoles`. Agregar `scopeSearch` (nombre/email `ILIKE`) y `scopeRole(?string $role)` (`whereHas('roles', fn => where('name', $role))`). `password` con cast `hashed` (verificar `casts()`). Estructura objetivo igual a `users` de multi-tenant: `id` uuid, `name`, `email`, `password`, `status` string(20) indexado, `token_version` unsignedInteger default 1, `last_login_at` nullable, timestamps, `deleted_at`. Se agrega `use SoftDeletes;`, `$fillable` con `status`, y casts `last_login_at => datetime`, `password => hashed`. Constantes `STATUS_ACTIVE|STATUS_INACTIVE`. `scopeStatus`.

## 2. Backend — Lógica de Negocio y Servicios
`App\Modules\Users\Services\UserService`:
- `list(array $filters)` — `with('roles:id,name')`, `search`, `role`, `status`, `orderBy('name')`.
- `findById(string $id): ?User`.
- `create(array $attributes, array $roles): User` — transacción, `syncRoles`.
- `update(User $user, array $attributes, ?array $roles): User` — valida último admin (`LastAdminException` → 422); si cambian roles/password/status: `increment('token_version')` y `RefreshToken::where('user_id', …)->delete()`.
- `delete(User $user, User $actor): void` — rechaza auto-eliminación y último admin; elimina refresh tokens.

## 3. Backend — Presentation
- `UserController` delgado; `delete` pasa `$request->user()` como actor.
- `UserRequest` (`name`, `email` con `Rule::unique('users')->ignore($id)`, `password` `required` en POST / `nullable|min:8` en PUT, `status` `in:active,inactive`, `roles` `array|min:1`, `roles.*` `exists:roles,name`), `ListUsersRequest` (`search`, `role`, `status`, `per_page`, `page`), `UserResource`, `UsersServiceProvider`.
- Rutas:

```php
Route::prefix('v1/users')->middleware(JwtAuthenticate::class)->group(function () {
    Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::get('/{id}', [UserController::class, 'show'])->whereUuid('id')->middleware('permission:users.view');
    Route::match(['put', 'patch'], '/{id}', [UserController::class, 'update'])->whereUuid('id')->middleware('permission:users.update');
    Route::delete('/{id}', [UserController::class, 'destroy'])->whereUuid('id')->middleware('permission:users.delete');
});
```

## 4. Contrato API
| Método | Ruta | Permiso | Body | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `/api/v1/users` | `users.view` | query `search, role, status, per_page, page` | 200 `{data[], meta}` | 401, 403, 422 |
| POST | `/api/v1/users` | `users.create` | `{ name, email, password, status?, roles[] }` | 201 `{data}` | 401, 403, 422 |
| GET | `/api/v1/users/{id}` | `users.view` | — | 200 `{data}` | 404 |
| PUT/PATCH | `/api/v1/users/{id}` | `users.update` | `{ name, email, password?, status?, roles[] }` | 200 `{data}` | 404, 422 |
| DELETE | `/api/v1/users/{id}` | `users.delete` | — | 204 | 404, 422 |

```json
{ "data": { "id": "…", "name": "Ana Pérez", "email": "ana@example.com", "status": "active", "roles": ["manager"],
  "last_login_at": null, "created_at": "…", "updated_at": "…" } }
```

## 5. Permisos y seguridad
- Permisos `users.view|create|update|delete` ya existen en el seeder.
- Escalada: roles asignados no pueden contener permisos que el actor no posee (salvo `admin`) — reutiliza la regla del spec 002.
- Revocación de refresh tokens e incremento de `token_version` ante cambio de rol/contraseña/estado.
- `JwtService` incluye claim `tv`; `JwtAuthenticate` compara con `users.token_version` y exige `status = active`. `AuthController::login` rechaza inactivos y actualiza `last_login_at`; `refresh` valida lo mismo.
- Frontend: `definePage({ meta: { action: 'users.view' } })`, `can('users.create'|'users.update'|'users.delete')`, y comparación con `authStore.user.id` para ocultar eliminar.

## 6. Migraciones
Decisión: **se edita directamente la migración original** `back-laravel/database/migrations/0001_01_01_000000_create_users_table.php` (sin migración de alineación; reinicio con `migrate:fresh --seed`). Tabla `users` según `Template Multi Tenant/back-multi-tenancy/database/migrations/tenant/0001_01_01_000000_create_users_table.php`:

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('email');
    $table->string('password');
    $table->string('status', 20)->index();      // 'active' | 'inactive'
    $table->unsignedInteger('token_version')->default(1);
    $table->timestamp('last_login_at')->nullable();
    $table->rememberToken();                    // conservar lo ya existente en la original
    $table->timestamp('email_verified_at')->nullable(); // idem
    $table->timestamps();
    $table->softDeletes();
});

SoftDeleteUniqueIndex::make('users', ['email']); // reemplaza ->unique() en email
```
- Conservar `password_reset_tokens` y `sessions` de la migración original sin cambios.
- `status` sin default en BD: lo fija el servicio/factory (`active`).
- Reutiliza `SoftDeleteUniqueIndex` (spec 001 T-1).
- Verificación: `php artisan migrate:fresh --seed`.

## 7. Configuración e infraestructura
No aplica.

## 8. Frontend
- `modules/users/services/usersApi.js` (`/v1/users`), `stores/usersStore.js` (referencia).
- `components/UserFormDrawer.vue`: se conserva `status` (activo/inactivo); se eliminan los roles por defecto `['Owner','Admin','Member']` de la referencia; `availableRoles` viene de `rolesStore` (nombres); contraseña obligatoria solo al crear; mostrar error del servidor por campo.
- `pages/users/index.vue`: filtros búsqueda + rol + estado, tabla con chips de roles, paginación server-side, diálogo de borrado.
- Menú "Usuarios" (`tabler-user`, `action: 'users.view'`).

## 9. Testing
`back-laravel/tests/Feature/Modules/Users/UsersApiTest.php`: listar con filtros, no expone password, crear (hash, roles), 422 email duplicado/rol inexistente, actualizar sin password conserva hash, último admin 422, auto-eliminación 422, eliminar 204, 401/403/404, escalada 422, revocación de refresh tokens. Front: `npm run lint`.

## 10. Decisiones Rechazadas
- **Omitir `status`/`token_version`**: descartado; se pidió alinear con la estructura de multi-tenant.
- **Mover `User` a `app/Modules/Users/Models`**: rompe referencias de Auth/JWT/factories sin beneficio ahora.
- **Un solo rol por usuario**: la referencia y Spatie soportan múltiples.
