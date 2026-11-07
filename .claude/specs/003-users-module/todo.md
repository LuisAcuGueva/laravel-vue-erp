# 003 · users-module — TODO
> **Spec:** 003 · **Alcance:** fullstack · **Módulo:** Users · **Prioridad:** alta · **Depende de specs:** 001, 002
> **Status:** Completed
> **Última actualización:** 2026-10-14
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:** 🔧 pendiente · ✅ hecho · ⚪ opcional · Subj: `back` | `front` | `infra` | `docs` · Compl: S ≤ 1 h, M ≤ ½ día.

---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-0 | ✅ | back | M | `database/migrations/0001_01_01_000000_create_users_table.php` (editar), `Auth/Services/JwtService.php`, `Auth/Middleware/JwtAuthenticate.php`, `Auth/Controllers/AuthController.php` | spec 001 T-1 |
| T-1 | ✅ | back | M | `app/Models/User.php`, `app/Modules/Users/{Services,Exceptions}` | T-0, spec 002 T-2 |
| T-2 | ✅ | back | M | `app/Modules/Users/{Requests,Resources,Controllers,Providers}`, `bootstrap/providers.php`, `routes/api.php` | T-1 |
| T-3 | ✅ | back | M | `tests/Feature/Modules/Users/UsersApiTest.php` | T-2 |
| T-4 | ✅ | front | S | `front-vue/src/modules/users/{services,stores}` | T-2 |
| T-5 | ✅ | front | M | `modules/users/components/UserFormDrawer.vue`, `pages/users/index.vue`, `navigation/vertical/index.js` | T-4, spec 002 T-4 |

---
## Detalle de Tareas
### T-0 — Editar migración de `users` y aplicar `status` / `token_version` en la sesión
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** spec 001 T-1
**Archivos clave:** `back-laravel/database/migrations/0001_01_01_000000_create_users_table.php` (**editar la original**), `back-laravel/app/Modules/Auth/Services/JwtService.php`, `Middleware/JwtAuthenticate.php`, `Controllers/AuthController.php`, `back-laravel/database/factories/UserFactory.php`.
**Descripción:**
1. Leer los archivos de Auth y la migración de users; editar la migración original según design §6 (sin migración nueva).
2. Agregar claim `tv` al access token y validarlo (y `status = active`) en `JwtAuthenticate` y `refresh`.
3. En `login`: rechazar inactivos y actualizar `last_login_at`.
4. Actualizar `UserFactory`/`UserSeeder` con `status = active`.
**Tests:** tests de Auth existentes + nuevos casos (usuario inactivo, `token_version` obsoleto).
**Criterios de done:** [x] `migrate:fresh --seed` OK; [x] `make test` y pint en verde.

### T-1 — Servicio de usuarios y reglas de dominio
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-0, spec 002 T-2
**Archivos clave:** `back-laravel/app/Models/User.php`, `back-laravel/app/Modules/Users/Services/UserService.php`, `Exceptions/LastAdminException.php`, `back-laravel/app/Modules/Auth/Models/RefreshToken.php`.
**Descripción:**
1. Leer `User.php`, `RefreshToken.php` y `Modules/Clients/Services/ClientService.php`.
2. Agregar `SoftDeletes`, `scopeSearch`, `scopeRole`, `scopeStatus` al modelo; confirmar cast `hashed` en `password`.
3. Implementar `UserService` (design §2) con transacciones, incremento de `token_version` y revocación de refresh tokens.
**Tests:** T-3. **Criterios de done:** [x] `make test` y `vendor/bin/pint --test` en verde.

### T-2 — Controller, requests, resource y rutas
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:** `app/Modules/Users/{Controllers,Requests,Resources,Providers}/**`, `bootstrap/providers.php`, `routes/api.php`.
**Descripción:** implementar design §3; `UserResource` sin `password`/`remember_token`; rutas con `permission:users.*` y `->whereUuid('id')`; reutilizar validación de escalada del spec 002.
**Tests:** T-3.
**Criterios de done:** [x] `php artisan route:list --path=v1/users` muestra 5 rutas; [x] pint y `make test` en verde.

### T-3 — Feature tests Users
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:** `back-laravel/tests/Feature/Modules/Users/UsersApiTest.php`.
**Descripción:** casos del design §9 (patrón `ClientsApiTest`).
**Tests:** `make test-filter FILTER=UsersApiTest`.
**Criterios de done:** [x] cubre US-1…US-4; [x] `make test` y pint en verde.

### T-4 — Frontend: API y store
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-2
**Archivos clave:** `front-vue/src/modules/users/services/usersApi.js`, `stores/usersStore.js`.
**Descripción:** adaptar de `Template Multi Tenant/front-multi-tenancy/src/modules/users/**` con ruta `/v1/users`.
**Tests:** `npm run lint`. **Criterios de done:** [x] lint en verde.

### T-5 — Frontend: página, drawer y menú
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:** `front-vue/src/pages/users/index.vue`, `modules/users/components/UserFormDrawer.vue`, `navigation/vertical/index.js`.
**Descripción:**
1. Adaptar `pages/tenant/users/index.vue` y `UserFormDrawer.vue` (design §8): con `status`, roles desde `rolesStore`.
2. Ocultar eliminar para el usuario autenticado (`useAuthStore().user.id`).
3. Menú "Usuarios".
**Tests:** manual (crear, editar sin password, filtrar por rol, eliminar) + `npm run lint`.
**Criterios de done:** [x] estados loading/vacío/error; [x] lint en verde.
