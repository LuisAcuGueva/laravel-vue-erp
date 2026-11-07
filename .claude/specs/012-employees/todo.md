# 012 · employees — TODO
> **Spec:** 012 · **Alcance:** fullstack · **Módulo:** Maintenance · **Prioridad:** alta · **Depende de specs:** 003, 011
> **Status:** Done
> **Última actualización:** 2026-10-16
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
| T-1 | ✅ | back | S | `database/migrations/2026_10_16_000011_create_employees_table.php`, `Maintenance/Models/Employee.php`, `app/Support/Identity/DocumentType.php`, `EmployeeFactory.php`, `User.php` | — |
| T-2 | ✅ | back | M | `Maintenance/Services/EmployeeService.php`, `Requests/{EmployeeRequest,ListEmployeesRequest}.php`, `Resources/*`, `Controllers/EmployeeController.php`, `routes/api.php`, `RolePermissionSeeder.php` | T-1 |
| T-3 | ✅ | back | M | `Maintenance/Services/EmployeeUserService.php`, `Controllers/EmployeeUserController.php`, `Requests/{CreateEmployeeUserRequest,LinkEmployeeUserRequest}.php`, `Exceptions/EmployeeRuleException.php` | T-2 |
| T-4 | ✅ | back | M | `tests/Feature/Modules/Maintenance/{EmployeesApiTest,EmployeeUserLinkApiTest}.php` | T-3 |
| T-5 | ✅ | front | M | `modules/maintenance/{services/employeesApi.js,stores/employeesStore.js,components/EmployeeFormDrawer.vue}`, `pages/maintenance/employees/index.vue`, `navigation/vertical/index.js` | T-2 |
| T-6 | ✅ | front | M | `modules/maintenance/components/EmployeeAccessCard.vue` | T-3, T-5 |

---
## Detalle de Tareas
### T-1 — Migración, modelo y enum de documento
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_16_000011_create_employees_table.php`
- `back-laravel/app/Modules/Maintenance/Models/Employee.php`
- `back-laravel/app/Support/Identity/DocumentType.php`
- `back-laravel/database/factories/EmployeeFactory.php`
- `back-laravel/app/Models/User.php`
**Descripción:**
1. DDL de design §6 con los tres `SoftDeleteUniqueIndex`.
2. Modelo con relaciones, accessor `full_name` y scopes (design §1). Leer primero `Maintenance/Models/Branch.php` (spec 011).
3. Enum transversal `DocumentType` con `rulesFor()` y `forPersons()` (design §1).
4. `User::employee(): HasOne`.
5. Factory con estados `withUser()` e `inactive()`.
**Tests:**
- `make migrate-fresh` sin errores; casos funcionales en T-4.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `cd back-laravel && vendor/bin/pint --test` en verde.

---
### T-2 — CRUD y opciones de empleados
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Maintenance/Services/EmployeeService.php`
- `back-laravel/app/Modules/Maintenance/Requests/{EmployeeRequest,ListEmployeesRequest}.php`
- `back-laravel/app/Modules/Maintenance/Resources/{EmployeeResource,EmployeeOptionResource}.php`
- `back-laravel/app/Modules/Maintenance/Controllers/EmployeeController.php`
- `back-laravel/routes/api.php`, `back-laravel/database/seeders/RolePermissionSeeder.php`
**Descripción:**
1. Leer primero `BranchController.php` y `BranchService.php` (spec 011) y seguir el mismo estilo.
2. Servicio con la sincronización de `users.name` en `update()` y la liberación de `user_id` en `delete()`.
3. Requests con las reglas de design §3 (unicidad compuesta por tipo + número).
4. Rutas CRUD + `/options` (antes de `/{id}`).
5. Permisos `employees.*` en el seeder.
**Tests:**
- Cubiertos en T-4 (`EmployeesApiTest`).
**Criterios de done:**
- [x] `make artisan CMD="route:list --path=v1/employees"` muestra las rutas CRUD y `/options`.
- [x] `vendor/bin/pint --test` en verde.

---
### T-3 — Acceso al sistema desde la ficha
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Maintenance/Services/EmployeeUserService.php`
- `back-laravel/app/Modules/Maintenance/Controllers/EmployeeUserController.php`
- `back-laravel/app/Modules/Maintenance/Requests/{CreateEmployeeUserRequest,LinkEmployeeUserRequest}.php`
- `back-laravel/app/Modules/Maintenance/Exceptions/EmployeeRuleException.php`
- `back-laravel/app/Modules/Users/Requests/UserRequest.php` (solo si se extrae el trait `UserCredentialRules`)
**Descripción:**
1. Leer primero `UserRequest.php` y `UserService::create()`; reutilizar sus reglas sin duplicarlas (trait si hace falta, sin cambiar el comportamiento de `UserRequest`).
2. `createUser`, `link`, `unlink` según design §2, en transacciones.
3. Rutas `POST|PUT|DELETE /employees/{id}/user` con los permisos de design §3.
**Tests:**
- Cubiertos en T-4 (`EmployeeUserLinkApiTest`); `UsersApiTest` sigue en verde.
**Criterios de done:**
- [x] `make test-filter FILTER=UsersApiTest` en verde (sin regresiones).
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Feature tests
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Maintenance/EmployeesApiTest.php`
- `back-laravel/tests/Feature/Modules/Maintenance/EmployeeUserLinkApiTest.php`
**Descripción:**
1. Implementar los casos de design §9 (US-1 a US-5).
**Tests:**
- Los dos archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Employee` en verde.
- [x] `make test` completo en verde y `vendor/bin/pint --test` en verde.

---
### T-5 — Front: listado y formulario de empleados
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `front-vue/src/modules/maintenance/services/employeesApi.js`
- `front-vue/src/modules/maintenance/stores/employeesStore.js`
- `front-vue/src/modules/maintenance/components/EmployeeFormDrawer.vue`
- `front-vue/src/pages/maintenance/employees/index.vue`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Leer primero `pages/maintenance/branches/index.vue` (spec 011) y replicar su patrón.
2. Tabla con filtros por sede, área, estado y acceso; drawer con validación de documento según tipo.
3. Entrada "Empleados" en el grupo "Mantenimiento".
**Tests:**
- `npm run lint`; verificación manual del CRUD y del 422 por documento duplicado.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-6 — Front: tarjeta de acceso al sistema
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-3, T-5
**Archivos clave:**
- `front-vue/src/modules/maintenance/components/EmployeeAccessCard.vue`
- `front-vue/src/modules/maintenance/components/EmployeeFormDrawer.vue`
**Descripción:**
1. Tres estados (sin usuario, crear, con usuario) según design §8.
2. "Crear acceso" visible solo con `can('users.create')`; roles desde el servicio de roles existente (`modules/roles/services`) y sedes desde `/branches/options`.
3. "Desvincular" con `useConfirm`.
**Tests:**
- `npm run lint`; verificación manual: crear acceso e iniciar sesión con el usuario nuevo.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
