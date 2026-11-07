# 009 · user-profile-and-sessions — Design
> **Spec:** 009 · **Alcance:** fullstack · **Módulo:** Profile · **Prioridad:** media · **Depende de specs:** 001, 002, 003, 005
> **Status:** Done
> **Última actualización:** 2026-10-14
> **Autores:** Luis Acuña Guevara

---
## 1. Endpoints de la API Backend
En `back-laravel/routes/api.php`:

```php
Route::prefix('v1/profile')->middleware(JwtAuthenticate::class)->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::put('/', [ProfileController::class, 'update']);
    Route::put('/password', [ProfileController::class, 'updatePassword']);
    Route::get('/sessions', [ProfileController::class, 'listSessions']);
    Route::delete('/sessions/others', [ProfileController::class, 'revokeOtherSessions']);
});
```

### 1.1 `ProfileController`
Ubicación: `back-laravel/app/Modules/Profile/Controllers/ProfileController.php`

- `show()`: Devuelve `UserResource` del usuario autenticado (`auth()->user()`).
- `update(UpdateProfileRequest $request)`: Actualiza `name` y `email`.
- `updatePassword(UpdatePasswordRequest $request)`:
  - Valida `current_password` con `Hash::check()`.
  - Actualiza `password` hasheado.
  - Incrementa `$user->token_version++` y revoca refresh tokens previos en base de datos.
- `listSessions()`:
  - Lista los tokens en `refresh_tokens` asociados al `user_id`, mostrando `ip_address`, `user_agent`, `created_at` y una bandera `is_current`.
- `revokeOtherSessions(Request $request)`:
  - Elimina o marca `revoked_at = now()` para todos los tokens del usuario excepto el token actual presente en la cookie `refresh_token`.

## 2. DTOs y Requests
- `UpdateProfileRequest`: `name` (required, string, max:255), `email` (required, email, unique:users,email,{id}).
- `UpdatePasswordRequest`: `current_password` (required, current_password), `password` (required, min:8, confirmed).

## 3. Frontend en Vue 3
Ubicación: `front-vue/src/pages/profile/index.vue` y `front-vue/src/modules/profile/`

Estructura de la pantalla con pestañas (`v-tabs`):
1. **Pestaña 1: Información de la Cuenta**: Formulario de nombre, email, roles asignados (solo lectura) con botón guardar.
2. **Pestaña 2: Seguridad**: Formulario de contraseña actual, nueva contraseña y confirmación.
3. **Pestaña 3: Sesiones y Dispositivos**: Lista de dispositivos con icono de laptop/móvil, navegador detectado, IP y botón para *"Cerrar sesión en todos los demás dispositivos"*.
