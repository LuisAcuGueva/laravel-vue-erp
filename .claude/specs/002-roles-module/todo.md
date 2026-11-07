# 002 · roles-module — TODO
> **Spec:** 002 · **Alcance:** fullstack · **Módulo:** Roles · **Prioridad:** alta · **Depende de specs:** 001
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
| T-1 | ✅ | back | M | `app/Modules/Roles/{Services,Exceptions}`, `config/permission.php`, `Auth/Models/Role.php` | spec 001 T-1 |
| T-2 | ✅ | back | M | `app/Modules/Roles/{Requests,Resources,Controllers,Providers}`, `bootstrap/providers.php`, `routes/api.php`, `RolePermissionSeeder.php` | T-1 |
| T-3 | ✅ | back | M | `tests/Feature/Modules/Roles/RolesApiTest.php` | T-2 |
| T-4 | ✅ | front | S | `front-vue/src/modules/roles/{services,stores}` | T-2 |
| T-5 | ✅ | front | M | `modules/roles/components/{RoleFormDrawer,RolePermissionsDialog}.vue`, `pages/roles/index.vue`, `navigation/vertical/index.js` | T-4, spec 001 T-4 |

---
## Detalle de Tareas
### T-1 — Servicio y reglas de dominio de roles
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** spec 001 T-1
**Archivos clave:** `back-laravel/app/Modules/Roles/Services/RoleService.php`, `Exceptions/{ProtectedRoleException,RoleInUseException}.php`, `back-laravel/config/permission.php`, `back-laravel/app/Modules/Auth/Models/Role.php`.
**Descripción:**
1. Leer `Role.php`, `config/permission.php` y `Modules/Clients/Services/ClientService.php`.
2. Agregar `protected_roles` a la config y accessor `is_protected` al modelo (modelo ya sin guard por spec 001 T-1).
3. Implementar `RoleService` (design §2) con transacciones e invalidación de caché.
4. Excepciones con `render()` → 422 / 409 y mensaje en español.
**Tests:** T-3.
**Criterios de done:** [x] `make test` y `vendor/bin/pint --test` en verde.

### T-2 — Controller, requests, resource, rutas y seeder
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:** `app/Modules/Roles/{Controllers,Requests,Resources,Providers}/**`, `bootstrap/providers.php`, `routes/api.php`, `database/seeders/RolePermissionSeeder.php`.
**Descripción:**
1. Crear `RoleController`, `RoleRequest` (con `permissions()`), `ListRolesRequest`, `RoleResource`, `RolesServiceProvider`.
2. Registrar provider y rutas con `permission:roles.*` y `->whereUuid('id')`.
3. Seeder: agregar `roles.create|update|delete`, retirar `roles.manage`, mantener idempotencia; permisos referenciados por `slug`.
4. Validar que los permisos enviados estén contenidos en los del solicitante (excepto `admin`).
**Tests:** T-3.
**Criterios de done:** [x] `php artisan route:list --path=v1/roles` muestra 5 rutas; [x] pint y `make test` en verde.

### T-3 — Feature tests Roles
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:** `back-laravel/tests/Feature/Modules/Roles/RolesApiTest.php`.
**Descripción:** casos del design §9 (patrón `ClientsApiTest`).
**Tests:** `make test-filter FILTER=RolesApiTest`.
**Criterios de done:** [x] cubre US-1…US-5; [x] `make test` y pint en verde.

### T-4 — Frontend: API y store
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-2
**Archivos clave:** `front-vue/src/modules/roles/services/rolesApi.js`, `stores/rolesStore.js`.
**Descripción:** adaptar desde `Template Multi Tenant/front-multi-tenancy/src/modules/roles/**` cambiando la ruta a `/v1/roles`; leer antes `modules/clients/**`.
**Tests:** `npm run lint`. **Criterios de done:** [x] lint en verde.

### T-5 — Frontend: página, drawer, diálogo de permisos y menú
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:** `front-vue/src/pages/roles/index.vue`, `modules/roles/components/RoleFormDrawer.vue`, `modules/roles/components/RolePermissionsDialog.vue`, `navigation/vertical/index.js`.
**Descripción:**
1. Adaptar `pages/tenant/roles/index.vue`; `definePage` con `action: 'roles.view'`; quitar lógica `owner` → usar `is_protected`.
2. Adaptar `ManageRolePermissionsModal.vue` → `RolePermissionsDialog.vue` (design §8).
3. Cargar catálogo con `usePermissionsStore().fetchPermissions({ per_page: 100 })` (spec 001).
4. Menú "Roles" con `action: 'roles.view'`.
**Tests:** manual (crear, editar, asignar permisos, eliminar con 409) + `npm run lint`.
**Criterios de done:** [x] estados loading/vacío/error; [x] lint en verde.
