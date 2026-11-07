# 009 · user-profile-and-sessions — TODO
> **Spec:** 009 · **Alcance:** fullstack · **Módulo:** Profile · **Prioridad:** media · **Depende de specs:** 001, 002, 003, 005
> **Status:** Done
> **Última actualización:** 2026-10-15
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:** 🔧 pendiente · ✅ hecho · ⚪ opcional · Subj: `back` | `front` | `infra` | `docs` · Compl: S ≤ 1 h, M ≤ ½ día.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | ✅ | back | S | `back-laravel/app/Modules/Profile/Requests/{UpdateProfileRequest,UpdatePasswordRequest}.php` | — |
| T-2 | ✅ | back | M | `back-laravel/app/Modules/Profile/Services/ProfileService.php`, `Controllers/ProfileController.php`, `routes/api.php` | T-1 |
| T-3 | ✅ | back | M | `back-laravel/tests/Feature/Modules/Profile/ProfileApiTest.php` | T-2 |
| T-4 | ✅ | front | S | `front-vue/src/modules/profile/stores/profileStore.js` | T-2 |
| T-5 | ✅ | front | M | `front-vue/src/pages/profile/index.vue`, componentes de pestañas | T-4 |

---
## Detalle de Tareas

### T-1 — Crear Form Requests de perfil
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:** `UpdateProfileRequest.php`, `UpdatePasswordRequest.php`
**Descripción:** Validar unicidad de email excluyendo el ID actual y validación de contraseña actual.

### T-2 — Implementar controlador y servicio de perfil
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:** `ProfileService.php`, `ProfileController.php`, `routes/api.php`
**Descripción:** Actualizar perfil, contraseña con incremento de `token_version` y listado/revocación de sesiones en `refresh_tokens`.

### T-3 — Tests de Feature para endpoints de perfil
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:** `back-laravel/tests/Feature/Modules/Profile/ProfileApiTest.php`
**Descripción:** Probar actualización de datos, cambio de contraseña erróneo y exitoso, y revocación de otros dispositivos.

### T-4 — Crear store de perfil en frontend
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-2
**Archivos clave:** `front-vue/src/modules/profile/stores/profileStore.js`
**Descripción:** Métodos Pinia para interactuar con `/api/v1/profile`.

### T-5 — Construir vista de Perfil y Sesiones en Vue 3
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:** `front-vue/src/pages/profile/index.vue`
**Descripción:** Maquetar pestañas de Cuenta, Seguridad y Dispositivos Activos, vinculando con el menú de usuario del Navbar.
