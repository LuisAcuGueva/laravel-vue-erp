# 010 · company-configuration — TODO
> **Spec:** 010 · **Alcance:** fullstack · **Módulo:** Configuration · **Prioridad:** alta · **Depende de specs:** 005, 007, 008
> **Status:** Done
> **Última actualización:** 2026-10-15
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
| T-1 | ✅ | back | S | `database/migrations/2026_10_16_00000{1,2}_*.php` | — |
| T-2 | ✅ | back | M | `app/Modules/Configuration/{Models,Enums}/*`, `database/factories/SystemParameterFactory.php` | T-1 |
| T-3 | ✅ | back | M | `app/Modules/Configuration/Services/*`, `database/seeders/SystemParameterSeeder.php` | T-2 |
| T-4 | ✅ | back | M | `Controllers/*`, `Requests/*`, `Resources/*`, `Providers/*`, `routes/api.php`, `RolePermissionSeeder.php` | T-3 |
| T-5 | ✅ | back | M | `tests/Feature/Modules/Configuration/*`, `tests/Unit/Modules/Configuration/*` | T-4 |
| T-6 | ✅ | front | S | `front-vue/src/modules/configuration/{services,stores}/*` | T-4 |
| T-7 | ✅ | front | M | `front-vue/src/pages/configuration/index.vue`, `modules/configuration/components/*`, `navigation/vertical/index.js` | T-6 |

---
## Detalle de Tareas
### T-1 — Migraciones de empresa y parámetros
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_16_000001_create_company_settings_table.php`
- `back-laravel/database/migrations/2026_10_16_000002_create_system_parameters_table.php`
**Descripción:**
1. Crear ambas migraciones copiando el DDL de design §6 (PK `uuid`, `unique(['group_key','item_key'])`).
2. Implementar `down()` con `Schema::dropIfExists`.
**Tests:**
- `make artisan CMD="migrate:fresh --seed"` sin errores.
**Criterios de done:**
- [x] Las tablas existen en PostgreSQL con PK UUID.
- [x] Backend: `make test` y `cd back-laravel && vendor/bin/pint --test` en verde.

---
### T-2 — Modelos y enum de tipo de valor
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Configuration/Models/CompanySetting.php`
- `back-laravel/app/Modules/Configuration/Models/SystemParameter.php`
- `back-laravel/app/Modules/Configuration/Enums/ParameterValueType.php`
- `back-laravel/database/factories/SystemParameterFactory.php`
**Descripción:**
1. Leer primero `app/Modules/Clients/Models/Client.php` y respetar su estructura (PHPDoc `@property`, `HasUuids`, `LogsActivity`).
2. Implementar los modelos según design §1 (casts, accessor `logoUrl`, `key`, `scopeGroup`).
3. Implementar el enum con `rules()` y `cast()`.
4. Factory de `SystemParameter` con estado `boolean()`, `decimal()` y `notEditable()`.
**Tests:**
- Se cubren en T-5 (`SystemParameterServiceTest`).
**Criterios de done:**
- [x] `cd back-laravel && vendor/bin/phpstan analyse` sin errores nuevos.
- [x] `vendor/bin/pint --test` en verde.

---
### T-3 — Servicios y seeder de parámetros
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Configuration/Services/CompanySettingService.php`
- `back-laravel/app/Modules/Configuration/Services/SystemParameterService.php`
- `back-laravel/app/Modules/Configuration/Exceptions/ParameterNotEditableException.php`
- `back-laravel/database/seeders/SystemParameterSeeder.php`, `DatabaseSeeder.php`
**Descripción:**
1. `CompanySettingService::current()` con creación perezosa en transacción + `lockForUpdate`; `update()`; `replaceLogo()` (disco `public`, carpeta `company/`, borra el anterior).
2. `SystemParameterService` según design §2 con caché `system_parameters.all` y atajos tipados.
3. `ParameterNotEditableException` con `render()` 422 (`{ message, errors: { value: [...] } }`), siguiendo `Users/Exceptions/LastAdminException.php`.
4. Seeder idempotente con los 5 parámetros de requirements §4; no pisar `value` existente. Registrar en `DatabaseSeeder`.
**Tests:**
- Se cubren en T-5.
**Criterios de done:**
- [x] `make artisan CMD="db:seed --class=SystemParameterSeeder"` dos veces seguidas no duplica filas.
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Controllers, requests, resources, rutas y permisos
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Modules/Configuration/Controllers/{CompanySettingController,SystemParameterController}.php`
- `back-laravel/app/Modules/Configuration/Requests/*.php`
- `back-laravel/app/Modules/Configuration/Resources/*.php`
- `back-laravel/app/Modules/Configuration/Providers/ConfigurationServiceProvider.php`, `bootstrap/providers.php`
- `back-laravel/routes/api.php`, `back-laravel/database/seeders/RolePermissionSeeder.php`
**Descripción:**
1. Leer primero `ClientController.php`, `ClientRequest.php` y `routes/api.php` y respetar sus convenciones.
2. Requests con las reglas de design §3 (`Rule::when` para el RUC; `UpdateSystemParameterRequest` con reglas dinámicas por `value_type`).
3. Resources: `CompanySettingResource` (sin `logo_path`), `CompanySummaryResource`, `SystemParameterResource` (con `key`).
4. Rutas del bloque de design §3 con `permission:` y `whereUuid`.
5. Provider con `singleton(SystemParameterService::class)`; registrarlo en `bootstrap/providers.php`.
6. Agregar `configuration.view` y `configuration.update` a `RolePermissionSeeder`.
**Tests:**
- Se cubren en T-5.
**Criterios de done:**
- [x] `make artisan CMD="route:list --path=v1/configuration"` muestra 6 rutas.
- [x] `vendor/bin/pint --test` en verde.

---
### T-5 — Feature y unit tests
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Configuration/CompanySettingsApiTest.php`
- `back-laravel/tests/Feature/Modules/Configuration/SystemParametersApiTest.php`
- `back-laravel/tests/Unit/Modules/Configuration/SystemParameterServiceTest.php`
**Descripción:**
1. Leer primero `tests/Feature/Modules/Clients/ClientsApiTest.php` para reutilizar el helper de autenticación JWT y el seeding de permisos.
2. Implementar los casos de design §9.
**Tests:**
- US-1: show crea el registro por defecto · US-2: update válido / RUC inválido · US-3: logo válido e inválido · US-4: decimal, tipo incorrecto, no editable, 404 · US-5: summary sin `tax_id`.
**Criterios de done:**
- [x] `make test-filter FILTER=Configuration` en verde.
- [x] `make test` completo en verde y `vendor/bin/pint --test` en verde.

---
### T-6 — Servicio API y store Pinia
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-4
**Archivos clave:**
- `front-vue/src/modules/configuration/services/configurationApi.js`
- `front-vue/src/modules/configuration/stores/configurationStore.js`
- `front-vue/src/modules/auth/stores/authStore.js` (solo para llamar `fetchSummary`)
**Descripción:**
1. Leer primero `modules/clients/services/clientsApi.js` y `stores/clientsStore.js` y seguir su estilo.
2. Métodos `getCompany`, `updateCompany`, `uploadLogo` (con `FormData`), `getSummary`, `listParameters`, `updateParameter`.
3. Store con el estado y las acciones de design §8; llamar a `fetchSummary()` después de cargar `/auth/me`.
**Tests:**
- `cd front-vue && npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-7 — Página de configuración
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-6
**Archivos clave:**
- `front-vue/src/pages/configuration/index.vue`
- `front-vue/src/modules/configuration/components/{CompanyForm,ParametersTable}.vue`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Página con `definePage({ meta: { action: 'configuration.view' } })` y `VTabs` Empresa/Parámetros.
2. `CompanyForm` con `useApiFormErrors`, vista previa del logo y botón deshabilitado sin `can('configuration.update')`.
3. `ParametersTable` agrupada por `group_key`, con un control por tipo y `loading` por fila.
4. Agregar la entrada de menú con `action: 'configuration.view'`.
**Tests:**
- `npm run lint`; verificación manual: editar empresa, subir logo y cambiar un parámetro booleano y uno decimal.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
- [x] Un usuario sin `configuration.view` no ve el menú ni accede a la ruta.
