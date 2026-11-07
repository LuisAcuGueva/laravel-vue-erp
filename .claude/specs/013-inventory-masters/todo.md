# 013 · inventory-masters — TODO
> **Spec:** 013 · **Alcance:** fullstack · **Módulo:** Inventory · **Prioridad:** alta · **Depende de specs:** 011
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
| T-1 | ✅ | back | M | `database/migrations/2026_10_17_000001_create_inventory_masters_tables.php`, `Inventory/Models/*`, `Inventory/Enums/*`, factories | — |
| T-2 | ✅ | back | M | `Inventory/{Services,Controllers,Requests,Resources}/{Category,Brand}*`, `Providers/InventoryServiceProvider.php`, `routes/api.php` | T-1 |
| T-3 | ✅ | back | M | `Inventory/{Services,Controllers,Requests,Resources}/{UnitCategory,Unit}*` | T-2 |
| T-4 | ✅ | back | M | `Inventory/{Services,Controllers,Requests,Resources}/{Warehouse,WarehouseReason}*`, `Maintenance/Services/BranchService.php` | T-2 |
| T-5 | ✅ | back | S | `InventoryMastersSeeder.php`, `RolePermissionSeeder.php`, `DatabaseSeeder.php` | T-3, T-4 |
| T-6 | ✅ | back | M | `tests/Feature/Modules/Inventory/{Categories,Brands,Units,Warehouses,WarehouseReasons}ApiTest.php` | T-5 |
| T-7 | ✅ | front | M | `modules/inventory/services/inventorySettingsApi.js`, `stores/inventorySettingsStore.js`, `pages/inventory/settings/index.vue`, `components/settings/{CategoriesTab,BrandsTab}.vue`, `navigation/vertical/index.js` | T-2 |
| T-8 | ✅ | front | M | `components/settings/{UnitsTab,WarehousesTab,WarehouseReasonsTab}.vue` | T-4, T-7 |

---
## Detalle de Tareas
### T-1 — Migración, modelos y enums
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_17_000001_create_inventory_masters_tables.php`
- `back-laravel/app/Modules/Inventory/Models/{Category,Brand,UnitCategory,Unit,Warehouse,WarehouseReason}.php`
- `back-laravel/app/Modules/Inventory/Enums/{WarehouseReasonType,SystemReason}.php`
- `back-laravel/database/factories/{Category,Brand,UnitCategory,Unit,Warehouse,WarehouseReason}Factory.php`
**Descripción:**
1. DDL de design §6, con los índices parciales condicionados al driver.
2. Modelos según design §1 (leer primero `Maintenance/Models/Branch.php`).
3. `Branch::warehouses(): HasMany`.
**Tests:**
- `make migrate-fresh` sin errores.
**Criterios de done:**
- [x] `make phpstan` sin errores nuevos; `cd back-laravel && vendor/bin/pint --test` en verde.

---
### T-2 — Categorías y marcas
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Services/{CategoryService,BrandService}.php`
- `back-laravel/app/Modules/Inventory/Controllers/{CategoryController,BrandController}.php`
- `back-laravel/app/Modules/Inventory/Requests/{CategoryRequest,ListCategoriesRequest,BrandRequest,ListBrandsRequest}.php`
- `back-laravel/app/Modules/Inventory/Resources/{CategoryResource,CategoryTreeResource,BrandResource}.php`
- `back-laravel/app/Modules/Inventory/Exceptions/InventoryRuleException.php`
- `back-laravel/app/Modules/Inventory/Providers/InventoryServiceProvider.php`, `bootstrap/providers.php`, `routes/api.php`
**Descripción:**
1. Validación de profundidad y ciclos en `CategoryService` (design §2), con recálculo de `depth` de los descendientes.
2. `tree()` con una sola consulta.
3. Rutas del prefijo `inventory` (design §3): `/tree` y `/options` antes de las rutas con `{id}`.
**Tests:**
- Cubiertos en T-6.
**Criterios de done:**
- [x] `make artisan CMD="route:list --path=v1/inventory"` lista categorías y marcas.
- [x] `vendor/bin/pint --test` en verde.

---
### T-3 — Unidades y conversión
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Services/UnitService.php`
- `back-laravel/app/Modules/Inventory/Controllers/{UnitCategoryController,UnitController}.php`
- `back-laravel/app/Modules/Inventory/Requests/{UnitCategoryRequest,UnitRequest,ConvertUnitRequest}.php`
- `back-laravel/app/Modules/Inventory/Resources/{UnitCategoryResource,UnitResource}.php`
**Descripción:**
1. Reglas de unidad base y `convert()` (design §2).
2. Rutas CRUD + `POST /inventory/units/convert`.
**Tests:**
- Cubiertos en T-6 (`UnitsApiTest`).
**Criterios de done:**
- [x] `vendor/bin/pint --test` en verde.

---
### T-4 — Almacenes y motivos
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Inventory/Services/{WarehouseService,WarehouseReasonService}.php`
- `back-laravel/app/Modules/Inventory/Controllers/{WarehouseController,WarehouseReasonController}.php`
- `back-laravel/app/Modules/Inventory/Requests/{WarehouseRequest,WarehouseReasonRequest,List*}.php`
- `back-laravel/app/Modules/Inventory/Resources/{WarehouseResource,WarehouseReasonResource}.php`
- `back-laravel/app/Modules/Maintenance/Services/BranchService.php`
**Descripción:**
1. `is_default` exclusivo por sede; `options()` con middleware `branch` (leer primero `app/Support/Branch/ActiveBranch.php`).
2. Protección de motivos de sistema.
3. Leer primero `BranchService::delete()` y agregar el bloqueo por almacenes.
**Tests:**
- Cubiertos en T-6; `BranchesApiTest` sigue en verde.
**Criterios de done:**
- [x] `make test-filter FILTER=BranchesApiTest` en verde.
- [x] `vendor/bin/pint --test` en verde.

---
### T-5 — Seeders y permisos
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-3, T-4
**Archivos clave:**
- `back-laravel/database/seeders/InventoryMastersSeeder.php`
- `back-laravel/database/seeders/RolePermissionSeeder.php`, `DatabaseSeeder.php`
**Descripción:**
1. Unidades y motivos de sistema (design §7), de forma idempotente.
2. Permisos de design §5.
**Tests:**
- `make seed` dos veces sin duplicados.
**Criterios de done:**
- [x] `make migrate-fresh` + `make seed` sin errores.

---
### T-6 — Feature tests
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-5
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Inventory/{CategoriesApiTest,BrandsApiTest,UnitsApiTest,WarehousesApiTest,WarehouseReasonsApiTest}.php`
**Descripción:**
1. Casos de design §9.
**Tests:**
- Los cinco archivos listados.
**Criterios de done:**
- [x] `make test-filter FILTER=Inventory` en verde.
- [x] `make test` completo y `vendor/bin/pint --test` en verde.

---
### T-7 — Front: página de configuración de inventario (categorías y marcas)
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `front-vue/src/modules/inventory/services/inventorySettingsApi.js`
- `front-vue/src/modules/inventory/stores/inventorySettingsStore.js`
- `front-vue/src/pages/inventory/settings/index.vue`
- `front-vue/src/modules/inventory/components/settings/{CategoriesTab,BrandsTab}.vue`
- `front-vue/src/navigation/vertical/index.js`
**Descripción:**
1. Leer primero `pages/maintenance/branches/index.vue` (spec 011).
2. Página con `VTabs`, cada pestaña visible según `can('<recurso>.view')`.
3. Árbol de categorías con selector de padre; marcas con `ServerDataTable`.
4. Grupo de menú "Inventario" › "Configuración".
**Tests:**
- `npm run lint`.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` en verde.

---
### T-8 — Front: unidades, almacenes y motivos
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-4, T-7
**Archivos clave:**
- `front-vue/src/modules/inventory/components/settings/{UnitsTab,WarehousesTab,WarehouseReasonsTab}.vue`
**Descripción:**
1. Unidades agrupadas por categoría con chip "Base" y diálogo de conversión.
2. Almacenes con filtro por sede y estrella para el almacén por defecto.
3. Motivos con candado y acciones deshabilitadas para `is_system`.
**Tests:**
- `npm run lint`; verificación manual de los errores 422 de cada regla.
**Criterios de done:**
- [x] Front: `cd front-vue && npm run lint` y `npm run build` en verde.
