# 021 · providers — TODO
> **Spec:** 021 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 012, 014, 017
> **Status:** Done
> **Última actualización:** 2026-10-19
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
| T-1 | ✅ | back | S | `migrations/2026_10_19_000001_create_providers_tables.php`, `Purchases/Models/*`, factories | — |
| T-2 | ✅ | back | M | `Purchases/{Services,Controllers,Requests,Resources}/Provider*` (CRUD, options, contactos, cuentas), `Providers/PurchasesServiceProvider.php`, `routes/api.php` | T-1 |
| T-3 | ✅ | back | M | `Purchases/{Services,Controllers,Requests,Resources}/ProviderPrice*` | T-2 |
| T-4 | ✅ | back | M | `RolePermissionSeeder.php`, `tests/Feature/Modules/Purchases/{ProvidersApiTest,ProviderPricesApiTest}.php` | T-3 |
| T-5 | ✅ | front | M | `pages/purchases/providers/index.vue`, `modules/purchases/{services/providersApi.js,stores/providersStore.js,components/providers/ProviderFormDrawer.vue}`, `components/common/ProviderPicker.vue`, menú | T-2 |
| T-6 | ✅ | front | M | `pages/purchases/providers/[id].vue`, `components/providers/{ProviderContactsTab,ProviderBankAccountsTab,ProviderPricesTab,ProviderPriceCompareDialog}.vue` | T-3, T-5 |

---
## Detalle de Tareas
### T-1 — Migración y modelos
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_19_000001_create_providers_tables.php`
- `back-laravel/app/Modules/Purchases/Models/{Provider,ProviderContact,ProviderBankAccount,ProviderPrice}.php`
- `back-laravel/database/factories/{Provider,ProviderPrice}Factory.php`
**Descripción:**
1. DDL de design §6; modelos y scopes de design §1.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `vendor/bin/pint --test` en verde.

---
### T-2 — Proveedores, contactos y cuentas
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/{ProviderService,ProviderContactService,ProviderBankAccountService}.php`
- `back-laravel/app/Modules/Purchases/Controllers/{ProviderController,ProviderContactController,ProviderBankAccountController}.php`
- `back-laravel/app/Modules/Purchases/Requests/{ProviderRequest,ListProvidersRequest,SyncProviderContactsRequest,SyncProviderBankAccountsRequest}.php`
- `back-laravel/app/Modules/Purchases/Resources/{ProviderResource,ProviderOptionResource,ProviderContactResource,ProviderBankAccountResource}.php`
- `back-laravel/app/Modules/Purchases/Exceptions/PurchasesRuleException.php`, `Providers/PurchasesServiceProvider.php`, `bootstrap/providers.php`, `routes/api.php`
**Descripción:**
1. Leer primero `Maintenance/Services/EmployeeService.php` (spec 012) y `app/Support/Identity/DocumentType.php`.
2. CRUD, options y `sync` de contactos y cuentas (design §2).
3. Rutas de design §3.
**Tests:**
- Cubiertos en T-4.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-3 — Precios por proveedor
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/ProviderPriceService.php`
- `back-laravel/app/Modules/Purchases/Controllers/ProviderPriceController.php`
- `back-laravel/app/Modules/Purchases/Requests/ProviderPriceRequest.php`
- `back-laravel/app/Modules/Purchases/Resources/ProviderPriceResource.php`
**Descripción:**
1. No superposición de vigencias (con `daterange` en PostgreSQL y alternativa en PHP para SQLite).
2. `compare()` con conversión de moneda para ordenar; `recordPurchase()` para el spec 023.
**Tests:**
- Cubiertos en T-4.
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Permisos y tests
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/database/seeders/RolePermissionSeeder.php`
- `back-laravel/tests/Feature/Modules/Purchases/{ProvidersApiTest,ProviderPricesApiTest}.php`
**Descripción:**
1. Permisos `providers.*`.
2. Casos de design §9.
**Tests:**
- Los dos archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Provider` en verde; `make test` completo en verde.

---
### T-5 — Front: listado y selector de proveedores
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `front-vue/src/pages/purchases/providers/index.vue`
- `front-vue/src/modules/purchases/services/providersApi.js`, `stores/providersStore.js`
- `front-vue/src/modules/purchases/components/providers/ProviderFormDrawer.vue`
- `front-vue/src/components/common/ProviderPicker.vue`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Listado y alta rápida; `ProviderPicker` reutilizable.
2. Menú "Compras" › "Proveedores".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-6 — Front: detalle con contactos, cuentas y precios
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-3, T-5
**Archivos clave:**
- `front-vue/src/pages/purchases/providers/[id].vue`
- `front-vue/src/modules/purchases/components/providers/{ProviderContactsTab,ProviderBankAccountsTab,ProviderPricesTab,ProviderPriceCompareDialog}.vue`
**Descripción:**
1. Pestañas con tablas editables y guardado en lote (design §8).
2. Comparador de precios desde el catálogo.
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
