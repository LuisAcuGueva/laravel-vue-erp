# 025 · clients-crm-extension — TODO
> **Spec:** 025 · **Alcance:** fullstack · **Módulo:** Clients · **Prioridad:** media · **Depende de specs:** 003, 012
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
| T-1 | ✅ | back | S | `routes/api.php` (bloque `clients`), `tests/Feature/Modules/Clients/ClientsApiTest.php` | — |
| T-2 | ✅ | back | M | `migrations/2026_10_20_000002_extend_clients_table.php`, `Clients/Models/{Client,ClientContact}.php`, `Clients/Enums/ClientType.php`, `ClientFactory.php` | T-1 |
| T-3 | ✅ | back | M | `Clients/Services/{ClientService,ClientContactService}.php`, `Requests/*`, `Resources/*`, `Controllers/*`, `Exceptions/ClientRuleException.php` | T-2 |
| T-4 | ✅ | back | S | `ClientSeeder.php`, `DatabaseSeeder.php`, `RolePermissionSeeder.php` | T-3 |
| T-5 | ✅ | back | M | `tests/Feature/Modules/Clients/ClientsApiTest.php` | T-4 |
| T-6 | ✅ | front | M | `pages/clients/index.vue`, `modules/clients/{services/clientsApi.js,stores/clientsStore.js,components/ClientFormDrawer.vue}` | T-3 |
| T-7 | ✅ | front | M | `pages/clients/[id].vue`, `modules/clients/components/{ClientContactsTab,ClientCreditCard}.vue`, `components/common/ClientPicker.vue` | T-6 |

---
## Detalle de Tareas
### T-1 — Corregir la autorización de la API de clientes
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/routes/api.php`
- `back-laravel/tests/Feature/Modules/Clients/ClientsApiTest.php`
**Descripción:**
1. Leer primero el bloque `clients` y el de `users` en `routes/api.php`.
2. Agregar `->middleware('permission:clients.<accion>')` a las cinco rutas existentes (design §3).
3. Agregar al test un caso 403 por acción con un usuario sin permisos; ajustar el `setUp` si los casos actuales usan un usuario sin permisos.
**Tests:**
- `ClientsApiTest` (casos existentes + 403).
**Criterios de done:**
- [x] `make test-filter FILTER=ClientsApiTest` en verde; `vendor/bin/pint --test` en verde.

---
### T-2 — Migración de extensión y modelos
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_20_000002_extend_clients_table.php`
- `back-laravel/app/Modules/Clients/Models/{Client,ClientContact}.php`
- `back-laravel/app/Modules/Clients/Enums/ClientType.php`
- `back-laravel/database/factories/ClientFactory.php`
**Descripción:**
1. Migración nueva compatible con datos existentes (design §6), con `down()` completo.
2. Leer primero `Client.php`; agregar campos, casts, scopes, `contacts()` y `system()`; `credit_*` e `is_system` fuera de `$fillable`.
3. Factory con estados `company()`, `person()`, `withCredit()`.
**Tests:**
- `make migrate-fresh` sin errores; `ClientsApiTest` existente en verde.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-3 — Servicio, requests, resources y endpoints nuevos
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Clients/Services/{ClientService,ClientContactService}.php`
- `back-laravel/app/Modules/Clients/Requests/{ClientRequest,ListClientsRequest,UpdateClientCreditRequest,SyncClientContactsRequest}.php`
- `back-laravel/app/Modules/Clients/Resources/{ClientResource,ClientOptionResource,ClientContactResource}.php`
- `back-laravel/app/Modules/Clients/Controllers/{ClientController,ClientContactController}.php`
- `back-laravel/app/Modules/Clients/Exceptions/ClientRuleException.php`
- `back-laravel/routes/api.php`
**Descripción:**
1. Reglas de validación y protección del cliente de sistema (design §2–§3).
2. Endpoints `options`, `credit`, `contacts`.
**Tests:**
- Cubiertos en T-5.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Seeders y permiso de crédito
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/database/seeders/{ClientSeeder,DatabaseSeeder,RolePermissionSeeder}.php`
**Descripción:**
1. Cliente de sistema en todos los entornos; ejemplos solo en local.
2. Permiso `clients.manage_credit`.
**Tests:**
- `make seed` dos veces sin duplicar el cliente de sistema.
**Criterios de done:**
- [x] `make migrate-fresh` + `make seed` sin errores.

---
### T-5 — Tests ampliados
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Clients/ClientsApiTest.php`
**Descripción:**
1. Casos de design §9.
**Tests:**
- `ClientsApiTest`.
**Criterios de done:**
- [x] `make test-filter FILTER=Clients` en verde; `make test` completo en verde.

---
### T-6 — Front: listado y formulario
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `front-vue/src/pages/clients/index.vue`
- `front-vue/src/modules/clients/services/clientsApi.js`, `stores/clientsStore.js`
- `front-vue/src/modules/clients/components/ClientFormDrawer.vue`
**Descripción:**
1. Leer primero cada archivo; ampliar columnas, filtros y formulario (design §8) sin romper el patrón existente.
**Tests:**
- `npm run lint`; `make test-front` si hay tests del store de clientes.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-7 — Front: detalle, contactos, crédito y selector
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-6
**Archivos clave:**
- `front-vue/src/pages/clients/[id].vue`
- `front-vue/src/modules/clients/components/{ClientContactsTab,ClientCreditCard}.vue`
- `front-vue/src/components/common/ClientPicker.vue`
**Descripción:**
1. Detalle con pestañas; crédito editable con `can('clients.manage_credit')`.
2. `ClientPicker` con alta rápida y prop `requireRuc`.
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
