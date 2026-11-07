# 011 · branches-and-areas — TODO
> **Spec:** 011 · **Alcance:** fullstack · **Módulo:** Maintenance · **Prioridad:** alta · **Depende de specs:** 003, 008
> **Status:** Done
> **Última actualización:** 2026-10-20
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:**
> - 🔧 = pendiente · ✅ = hecho · ⚪ = opcional
> - **Subj** (subproyecto): `back` | `front` | `infra` | `docs`
> - **Complejidad:** S = ≤ 1 h · M = ≤ ½ día · L = > ½ día (evitar L: partir en varias M)
> - Cada tarea es **autocontenida**: un agente con `requirements.md`, `design.md` y los archivos indicados puede ejecutarla sin contexto adicional.
> - **Dependencias** explícitas entre tareas → preservar el orden indicado.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | ✅ | back | S | `database/migrations/2026_10_16_00000{3,4,5}_*.php` | — |
| T-2 | ✅ | back | M | `app/Modules/Maintenance/Models/{Branch,Area,UserBranch}.php`, `app/Models/User.php`, factories | T-1 |
| T-3 | ✅ | back | M | `Maintenance/Services/{BranchService,AreaService}.php`, `Requests/*`, `Resources/*`, `Controllers/{Branch,Area}Controller.php`, `routes/api.php` | T-2 |
| T-4 | ✅ | back | M | `Maintenance/Services/UserBranchService.php`, `Controllers/UserBranchController.php`, `UserResource.php`, `UserService.php`, `AuthController.php` | T-3 |
| T-5 | ✅ | back | M | `app/Support/Branch/{ActiveBranch,BranchAccessGuard}.php`, `app/Http/Middleware/ResolveActiveBranch.php`, `bootstrap/app.php`, `config/cors.php` | T-4 |
| T-6 | ✅ | back | S | `RolePermissionSeeder.php`, `BranchSeeder.php`, `DatabaseSeeder.php` | T-3 |
| T-7 | ✅ | back | M | `tests/Feature/Modules/Maintenance/*` | T-5, T-6 |
| T-8 | ✅ | front | M | `front-vue/src/modules/maintenance/**`, `pages/maintenance/{branches,areas}/index.vue`, `navigation/vertical/index.js` | T-3 |
| T-9 | ✅ | front | M | `modules/users/components/UserFormDrawer.vue`, `pages/users/index.vue`, `modules/auth/stores/authStore.js`, `utils/api.js`, `utils/download.js`, `components/common/BranchSwitcher.vue`, layout navbar | T-5, T-8 |

---
## Detalle de Tareas
### T-1 — Migraciones de sedes, áreas y user_branches
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_16_000003_create_branches_table.php`
- `back-laravel/database/migrations/2026_10_16_000004_create_areas_table.php`
- `back-laravel/database/migrations/2026_10_16_000005_create_user_branches_table.php`
**Descripción:**
1. Copiar el DDL de design §6, incluidos `SoftDeleteUniqueIndex::make` y el índice parcial `user_branches_one_default`.
2. Aplicar el índice parcial solo si el driver es `pgsql` o `sqlite`, igual que `SoftDeleteUniqueIndex`.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] Tablas con PK UUID y FKs `foreignUuid`.
- [x] `cd back-laravel && vendor/bin/pint --test` en verde.

---
### T-2 — Modelos, pivot y relación en User
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Maintenance/Models/{Branch,Area,UserBranch}.php`
- `back-laravel/app/Models/User.php`
- `back-laravel/database/factories/{BranchFactory,AreaFactory}.php`
**Descripción:**
1. Leer primero `app/Modules/Clients/Models/Client.php` y `app/Models/User.php` y respetar sus convenciones.
2. Modelos según design §1; `UserBranch extends Pivot` con `HasUuids`.
3. `User::branches()` con `->using(UserBranch::class)->withPivot('is_default')->withTimestamps()`, más `defaultBranch()` e `isAdmin()`.
4. Factories con estados `main()` e `inactive()`.
**Tests:**
- Cubiertos en T-7.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-3 — CRUD de sedes y áreas (servicio, requests, resources, controllers, rutas)
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Maintenance/Services/{BranchService,AreaService}.php`
- `back-laravel/app/Modules/Maintenance/Requests/{BranchRequest,ListBranchesRequest,AreaRequest,ListAreasRequest}.php`
- `back-laravel/app/Modules/Maintenance/Resources/{BranchResource,BranchOptionResource,AreaResource}.php`
- `back-laravel/app/Modules/Maintenance/Controllers/{BranchController,AreaController}.php`
- `back-laravel/app/Modules/Maintenance/Exceptions/BranchRuleException.php`
- `back-laravel/app/Modules/Maintenance/Providers/MaintenanceServiceProvider.php`, `bootstrap/providers.php`, `routes/api.php`
**Descripción:**
1. Leer primero `ClientController.php`, `ClientService.php` y `routes/api.php`.
2. Implementar las reglas de sede principal en `BranchService` (design §2) con `DB::transaction`.
3. Registrar las rutas de design §3, con `/options` declarado antes de `/{id}`.
4. Registrar el provider.
**Tests:**
- Cubiertos en T-7 (`BranchesApiTest`, `AreasApiTest`).
**Criterios de done:**
- [x] `make artisan CMD="route:list --path=v1/branches"` y `--path=v1/areas` muestran 6 rutas cada uno.
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Asignación de sedes a usuarios y `/auth/me`
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Modules/Maintenance/Services/UserBranchService.php`
- `back-laravel/app/Modules/Maintenance/Controllers/UserBranchController.php`
- `back-laravel/app/Modules/Maintenance/Requests/SyncUserBranchesRequest.php`
- `back-laravel/app/Modules/Maintenance/Resources/UserBranchResource.php`
- `back-laravel/app/Modules/Users/Resources/UserResource.php`, `Users/Services/UserService.php`
- `back-laravel/app/Modules/Auth/Controllers/AuthController.php`
**Descripción:**
1. Leer primero `UserResource.php`, `UserService.php` y `AuthController::me`.
2. `UserBranchService::sync` y `accessibleBranches` (design §2), con log de actividad `branches_synced`.
3. `UserResource` agrega `branches` con `whenLoaded`; `UserService::list/findById` hacen `with('branches')`.
4. `AuthController::me` agrega `branches`.
5. Ruta `PUT /v1/users/{id}/branches`.
**Tests:**
- Cubiertos en T-7 (`UserBranchesApiTest`); `UsersApiTest` existente sigue en verde.
**Criterios de done:**
- [x] `make test-filter FILTER=UsersApiTest` en verde (sin regresiones).
- [x] `vendor/bin/pint --test` en verde.

---
### T-5 — Sede activa (ActiveBranch + middleware)
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/app/Support/Branch/ActiveBranch.php`, `back-laravel/app/Support/Branch/BranchAccessGuard.php`
- `back-laravel/app/Http/Middleware/ResolveActiveBranch.php`
- `back-laravel/bootstrap/app.php`, `back-laravel/config/cors.php`
- `back-laravel/app/Modules/Maintenance/Providers/MaintenanceServiceProvider.php`
**Descripción:**
1. `ActiveBranch` registrado con `$this->app->scoped(ActiveBranch::class)`; `get()` sin resolver lanza `LogicException`.
1b. `BranchAccessGuard::assertAccessible()` (404) y `scopeQuery()` (design §2); `branch_id` en las propiedades de auditoría.
2. Middleware con el algoritmo de design §2 (403 sin acceso, 409 sin sedes).
3. Alias `branch` en `bootstrap/app.php`; permitir `X-Branch-Id` en CORS.
**Tests:**
- Cubiertos en T-7 (`ActiveBranchMiddlewareTest`).
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-6 — Permisos y seeders
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/database/seeders/RolePermissionSeeder.php`
- `back-laravel/database/seeders/BranchSeeder.php`, `DatabaseSeeder.php`
**Descripción:**
1. Agregar `branches.*` y `areas.*` (design §5) con el mismo formato `name/slug/module/description`; asignar `view` a `manager`.
2. `BranchSeeder`: sede `PRINCIPAL` y asignación del admin como sede por defecto (idempotente con `firstOrCreate`).
**Tests:**
- `make seed` dos veces sin duplicados.
**Criterios de done:**
- [x] `make migrate-fresh` + `make seed` sin errores.

---
### T-7 — Feature tests del módulo
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-5, T-6
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Maintenance/{BranchesApiTest,AreasApiTest,UserBranchesApiTest,ActiveBranchMiddlewareTest}.php`
- `back-laravel/tests/Feature/Support/BranchAccessGuardTest.php`
**Descripción:**
1. Leer primero `tests/Feature/Modules/Users/UsersApiTest.php` para reutilizar los helpers de autenticación.
2. Implementar los casos de design §9 (US-1 a US-6).
**Tests:**
- Los cuatro archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Maintenance` en verde.
- [x] `make test` completo en verde y `vendor/bin/pint --test` en verde.

---
### T-8 — Front: páginas de sedes y áreas
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `front-vue/src/modules/maintenance/services/{branchesApi,areasApi}.js`
- `front-vue/src/modules/maintenance/stores/{branchesStore,areasStore}.js`
- `front-vue/src/modules/maintenance/components/{BranchFormDrawer,AreaFormDrawer}.vue`
- `front-vue/src/pages/maintenance/branches/index.vue`, `front-vue/src/pages/maintenance/areas/index.vue`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Leer primero `pages/clients/index.vue`, `modules/clients/**` y replicar el patrón (`ServerDataTable`, `useConfirm`, `useApiFormErrors`, `can()`).
2. Sedes: columnas código, nombre, dirección, teléfono, principal (estrella), usuarios, estado y acciones.
3. Áreas: columnas nombre, descripción, estado y acciones.
4. Grupo de menú "Mantenimiento" con los hijos Sedes y Áreas.
**Tests:**
- `npm run lint`; verificación manual del CRUD y del error 422 al desmarcar la principal.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-9 — Front: sedes en usuarios y selector de sede activa
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-5, T-8
**Archivos clave:**
- `front-vue/src/modules/users/components/UserFormDrawer.vue`, `front-vue/src/pages/users/index.vue`, `front-vue/src/modules/users/services/*`
- `front-vue/src/modules/auth/stores/authStore.js`
- `front-vue/src/utils/api.js`, `front-vue/src/utils/download.js`
- `front-vue/src/components/common/BranchSwitcher.vue`, `front-vue/src/layouts/components/DefaultLayoutWithVerticalNav.vue` (y horizontal)
**Descripción:**
1. Leer primero cada archivo y respetar sus convenciones.
2. Drawer de usuario: sedes (múltiple) + sede por defecto; guardar con `PUT /v1/users/{id}/branches` después del create/update.
3. `authStore`: guardar `branches` y `activeBranchId` **por pestaña** (`sessionStorage`, con `localStorage['last_branch_id']` solo como valor inicial de pestañas nuevas), validado contra `branches` (design §8).
3b. `utils/download.js` con `downloadFile()` vía `$api` blob; reemplazar cualquier descarga existente por enlace directo.
4. `api.js`: cabecera `X-Branch-Id` cuando `activeBranchId` existe.
5. `BranchSwitcher` en el navbar (solo si hay más de una sede); al cambiar, recargar los datos de la vista.
**Tests:**
- `npm run lint`; `make test-front` si existen tests de `authStore`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.
- [x] Las peticiones llevan `X-Branch-Id` (verificar en DevTools).
- [x] Dos pestañas pueden operar en sedes distintas sin interferir.
- [x] Vitest de la resolución inicial de la sede (`sessionStorage` → `localStorage` → default) con `make test-front`.
