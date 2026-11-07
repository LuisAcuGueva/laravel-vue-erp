# 001 · permissions-module — TODO
> **Spec:** 001 · **Alcance:** fullstack · **Módulo:** Permissions · **Prioridad:** alta · **Depende de specs:** —
> **Status:** Done
> **Última actualización:** 2026-10-14
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:** 🔧 pendiente · ✅ hecho · ⚪ opcional · Subj: `back` | `front` | `infra` | `docs` · Compl: S ≤ 1 h, M ≤ ½ día.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | ✅ | back | M | `database/migrations/2026_10_13_205451_create_permission_tables.php` (editar), `app/Support/Database/SoftDeleteUniqueIndex.php`, `Auth/Models/Permission.php`, `Auth/Models/Role.php` | — |
| T-2 | ✅ | back | M | `app/Modules/Permissions/{Services,Requests,Resources,Controllers,Providers}`, `bootstrap/providers.php`, `routes/api.php`, `RolePermissionSeeder.php` | T-1 |
| T-3 | ✅ | back | M | `tests/Feature/Modules/Permissions/PermissionsApiTest.php` | T-2 |
| T-4 | ✅ | front | S | `front-vue/src/modules/permissions/{services,stores}` | T-2 |
| T-5 | ✅ | front | M | `front-vue/src/modules/permissions/components/PermissionFormDrawer.vue`, `src/pages/permissions/index.vue`, `src/navigation/vertical/index.js` | T-4 |

---
## Detalle de Tareas
### T-1 — Alinear migración original y modelos con multi-tenant
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_13_205451_create_permission_tables.php` (**editar la original**)
- `back-laravel/app/Support/Database/SoftDeleteUniqueIndex.php` (copia desde `Template Multi Tenant/back-multi-tenancy/app/Core/Shared/Infrastructure/Persistence/`)
- `back-laravel/app/Modules/Auth/Models/Permission.php`, `Role.php`
**Descripción:**
1. Leer `Permission.php` y `Role.php` y respetar su estilo.
2. Leer `database/migrations/2026_10_13_205451_create_permission_tables.php`.
3. Editar esa migración original según design §6 (slug, module, description, soft deletes, sin guard_name, únicos parciales); no crear migración nueva.
4. Adaptar `Permission` y `Role` al estilo de `PermissionModel`/`RoleModel` de multi-tenant (sin guard, `SoftDeletes`, búsqueda por `slug`/`name`).
5. Actualizar `RolePermissionSeeder` (`slug`, `module`, sin `guard_name`) y `AuthController` (`pluck('slug')`).
**Tests:** cubiertos por T-3 (columna usada en creación).
**Criterios de done:**
- [x] `php artisan migrate:fresh --seed` y `migrate:rollback` sin error.
- [x] `/auth/me` devuelve slugs en `permissions`.
- [x] `make test` y `cd back-laravel && vendor/bin/pint --test` en verde.

### T-2 — Módulo backend Permissions
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Permissions/**`
- `back-laravel/bootstrap/providers.php`, `back-laravel/routes/api.php`
- `back-laravel/database/seeders/RolePermissionSeeder.php`
**Descripción:**
1. Replicar la estructura de `app/Modules/Clients/`; implementar `PermissionService`, `PermissionRequest`, `ListPermissionsRequest`, `PermissionResource`, `PermissionController`, `PermissionInUseException` (render 409) según design §2–§3.
2. Registrar `PermissionsServiceProvider` y rutas con `permission:` y `->whereUuid('id')`.
3. Agregar `permissions.view|create|update|delete` al seeder.
**Tests:** T-3.
**Criterios de done:**
- [x] `php artisan route:list --path=v1/permissions` muestra 5 rutas con middleware `permission:*`.
- [x] `make test` y pint en verde.

### T-3 — Feature tests Permissions
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:** `back-laravel/tests/Feature/Modules/Permissions/PermissionsApiTest.php` (patrón `ClientsApiTest.php`).
**Descripción:** casos del design §9; helper para crear usuario con rol `admin` o con un permiso puntual y token vía `JwtService::createTokenPair`.
**Tests:** `make test-filter FILTER=PermissionsApiTest`.
**Criterios de done:**
- [x] Cubre US-1…US-4 (200/201/204/401/403/404/409/422).
- [x] `make test` y pint en verde.

### T-4 — Frontend: API y store
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-2
**Archivos clave:** `front-vue/src/modules/permissions/services/permissionsApi.js`, `stores/permissionsStore.js`.
**Descripción:** leer `modules/clients/services/clientsApi.js` y `stores/clientsStore.js`; adaptar desde `Template Multi Tenant/front-multi-tenancy/src/modules/permissions/**` con rutas `/v1/permissions` y respuesta `{data, meta}` (`meta.current_page`, `last_page`).
**Tests:** `npm run lint`.
**Criterios de done:** [x] lint en verde.

### T-5 — Frontend: página, drawer y menú
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:** `front-vue/src/pages/permissions/index.vue`, `modules/permissions/components/PermissionFormDrawer.vue`, `navigation/vertical/index.js`.
**Descripción:**
1. Adaptar `pages/tenant/permissions/index.vue` (referencia) a la estructura de `pages/clients/index.vue`; `definePage` con `action: 'permissions.view'`.
2. Formulario con `name`, `slug`, `module` y `description`; mostrar errores 422 por campo y 409 en el diálogo de borrado.
3. Botones condicionados con `useAcl().can()`.
4. Agregar ítem de menú "Permisos".
**Tests:** verificación manual con `make front-dev` (carga, vacío, crear, editar, borrar con 409) + `npm run lint`.
**Criterios de done:**
- [x] Estados loading/vacío/error visibles.
- [x] `cd front-vue && npm run lint` en verde.
