# 013 · inventory-masters — Design
> **Spec:** 013 · **Alcance:** fullstack · **Módulo:** Inventory · **Prioridad:** alta · **Depende de specs:** 011
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend:** módulo nuevo `back-laravel/app/Modules/Inventory/` (los specs 014–016 lo amplían):
  - `Models/{Category,Brand,UnitCategory,Unit,Warehouse,WarehouseReason}.php`
  - `Enums/WarehouseReasonType.php` (`in`, `out`, `transfer`, `adjustment`), `Enums/SystemReason.php` (códigos de sistema)
  - `Services/{CategoryService,BrandService,UnitService,WarehouseService,WarehouseReasonService}.php`
  - `Controllers/{CategoryController,BrandController,UnitCategoryController,UnitController,WarehouseController,WarehouseReasonController}.php`
  - `Requests/*` (uno por recurso + `List*`), `Resources/*`, `Exceptions/InventoryRuleException.php`
  - `Providers/InventoryServiceProvider.php`
- **Seeders:** `database/seeders/InventoryMastersSeeder.php` (unidades + motivos de sistema), permisos en `RolePermissionSeeder`.
- **Modificado:** `Maintenance/Services/BranchService::delete()` (bloqueo por almacenes).
- **Frontend:** `front-vue/src/pages/inventory/settings/index.vue` con pestañas; `front-vue/src/modules/inventory/{services,stores,components}/`.

## 1. Backend — Modelo y Persistencia
Todos los modelos: `HasUuids`, `SoftDeletes`, `LogsActivity` (`logAll()->logOnlyDirty()`), `HasFactory`, scopes `search` y `active`.
- **`Category`**: `name`, `code`, `parent_id`, `description`, `depth` (0–2, calculado), `is_active`. Relaciones `parent()`, `children()`. Método `ancestorIds(): array` (sube por `parent_id`; máx. 3 niveles).
- **`Brand`**: `name`, `description`, `is_active`.
- **`UnitCategory`**: `name`, `description`, `is_active`; `units()`, `baseUnit()` (`hasOne` con `is_base = true`).
- **`Unit`**: `unit_category_id`, `name`, `symbol`, `is_base`, `conversion_factor` (cast `decimal:6`), `allow_decimals`, `sunat_code`, `is_active`.
- **`Warehouse`**: `branch_id`, `code` (mayúsculas), `name`, `address`, `is_default`, `is_active`; `branch()`.
- **`WarehouseReason`**: `code`, `name`, `type` (cast `WarehouseReasonType`), `requires_approval`, `is_system`, `is_active`.

## 2. Backend — Lógica de Negocio y Servicios
- **`CategoryService`**: `list`, `tree()` (una consulta de activas + armado en memoria), `create`/`update` validan: `parent` existe y activo; `depth = parent.depth + 1 ≤ 2`; en update, `parent_id` no puede ser el propio id ni un descendiente (recorriendo `children` del nodo); al mover, recalcula `depth` de los descendientes en transacción. `delete` bloquea si tiene `children`; la verificación de ítems del catálogo la agrega el spec 014 en este mismo método.
- **`BrandService`**: CRUD; `delete` bloquea si hay ítems (lo agrega el spec 014).
- **`UnitService`**:
  - `create`: si `is_base` → exige que la categoría no tenga base y fuerza `conversion_factor = 1`; si no es base → exige que exista base.
  - `update`: no permite cambiar `unit_category_id` ni `is_base` si la unidad está en uso (lo verifica el spec 014).
  - `convert(float $qty, Unit $from, Unit $to): float` → `round($qty * $from->conversion_factor / $to->conversion_factor, 6)`; distinta categoría → `InventoryRuleException`.
- **`WarehouseService`**: `create`/`update` con `is_default` exclusivo por sede (transacción); `options(Branch|null $branch, Collection $accessibleBranches)`; `delete` bloquea si hay stock/movimientos (lo agrega el spec 015).
- **`WarehouseReasonService`**: `update` rechaza cambios de `code`/`type`/`is_system` si `is_system`; `delete` rechaza `is_system`.
- **`BranchService::delete()`** (spec 011): agregar `if ($branch->warehouses()->exists()) throw new BranchRuleException('La sede tiene almacenes registrados.')` y la relación `Branch::warehouses()`.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- Controllers delgados (patrón `ClientController`). Excepción `InventoryRuleException` → 422 `{ message, errors: { <campo>: [...] } }`.
- Reglas clave:
  - `CategoryRequest`: `name` required|max:120 + unicidad por (`parent_id`, `name`) ignorando eliminados (closure rule); `code` nullable|max:20|unique; `parent_id` nullable|uuid|exists activo; `description` nullable|max:500; `is_active` boolean.
  - `BrandRequest`: `name` required|max:120|unique; `description` nullable|max:500.
  - `UnitRequest`: `unit_category_id` required|uuid|exists; `name` required|max:60|unique por categoría; `symbol` required|max:10; `is_base` boolean; `conversion_factor` required_unless:is_base,true|numeric|gt:0|decimal:0,6; `allow_decimals` boolean; `sunat_code` nullable|max:5|alpha_num.
  - `ConvertUnitRequest`: `quantity` required|numeric; `from_unit_id`, `to_unit_id` required|uuid|exists.
  - `WarehouseRequest`: `code` required|max:10|regex alfanumérico|unique; `name` required|max:120; `branch_id` required|uuid|exists activo y accesible para el usuario; `address` nullable|max:255; `is_default`, `is_active` boolean.
  - `WarehouseReasonRequest`: `code` required|max:30|regex `^[A-Z0-9_]+$`|unique; `name` required|max:120; `type` required|enum; `requires_approval` boolean.
- Rutas (`v1`, prefijo `inventory`):
```php
// Módulo Inventory — Maestros (Spec 013)
Route::prefix('inventory')->group(function () {
    Route::get('/categories/tree', [CategoryController::class, 'tree']);
    Route::get('/categories/options', [CategoryController::class, 'options']);
    Route::apiResource('categories', CategoryController::class)->parameters(['categories' => 'id'])->whereUuid('id')
        ->middlewareFor('index', 'permission:categories.view')->middlewareFor('show', 'permission:categories.view')
        ->middlewareFor('store', 'permission:categories.create')->middlewareFor('update', 'permission:categories.update')
        ->middlewareFor('destroy', 'permission:categories.delete');
    // mismo patrón para brands, unit-categories (permiso units.*), units, warehouse-reasons
    Route::post('/units/convert', [UnitController::class, 'convert']);
    Route::get('/warehouses/options', [WarehouseController::class, 'options'])->middleware('branch');
    // warehouses CRUD con permission:warehouses.*
});
```
> Si `middlewareFor` no está disponible en la versión de Laravel instalada, declarar las 5 rutas explícitas como en el bloque de `clients`.

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| CRUD | `/api/v1/inventory/categories[/{id}]` | `categories.*` | `name, code?, parent_id?, description?, is_active?` | 200/201/204 | 401, 403, 404, 422 |
| GET | `/api/v1/inventory/categories/tree` | autenticado | — | 200 `data: [{id, name, code, children[]}]` | 401 |
| CRUD | `/api/v1/inventory/brands[/{id}]` | `brands.*` | `name, description?, is_active?` | 200/201/204 | 401, 403, 404, 422 |
| CRUD | `/api/v1/inventory/unit-categories[/{id}]` | `units.*` | `name, description?` | 200/201/204 | 401, 403, 404, 422 |
| CRUD | `/api/v1/inventory/units[/{id}]` | `units.*` | `unit_category_id, name, symbol, is_base, conversion_factor, allow_decimals, sunat_code?` | 200/201/204 | 401, 403, 404, 422 |
| POST | `/api/v1/inventory/units/convert` | autenticado | `{ quantity, from_unit_id, to_unit_id }` | 200 `data: { quantity }` | 401, 422 |
| CRUD | `/api/v1/inventory/warehouses[/{id}]` | `warehouses.*` | `code, name, branch_id, address?, is_default?, is_active?` | 200/201/204 | 401, 403, 404, 422 |
| GET | `/api/v1/inventory/warehouses/options` | autenticado + `branch` | query `scope=active_branch|all` | 200 `data: [{id, code, name, branch_id, is_default}]` | 401, 403, 409 |
| CRUD | `/api/v1/inventory/warehouse-reasons[/{id}]` | `warehouse_reasons.*` | `code, name, type, requires_approval?` | 200/201/204 | 401, 403, 404, 422 |
| GET | `/api/v1/inventory/{brands,units,warehouse-reasons}/options` | autenticado | query de filtro | 200 | 401 |

## 5. Permisos y seguridad
- Permisos nuevos (`RolePermissionSeeder`), cada uno con `view|create|update|delete`: `categories`, `brands`, `units`, `warehouses`, `warehouse_reasons` (módulo `inventory`). `admin` todos; `manager` todos excepto `delete`.
- `WarehouseRequest` valida que `branch_id` esté entre `UserBranchService::accessibleBranches()`.

## 6. Migraciones
`2026_10_17_000001_create_inventory_masters_tables.php` (una migración por tabla si se prefiere; mismo contenido):
```php
Schema::create('categories', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
    $table->string('code', 20)->nullable();
    $table->string('name', 120);
    $table->string('description', 500)->nullable();
    $table->unsignedTinyInteger('depth')->default(0);
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
});
SoftDeleteUniqueIndex::make('categories', ['code']);

Schema::create('brands', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name', 120);
    $table->string('description', 500)->nullable();
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
});
SoftDeleteUniqueIndex::make('brands', ['name']);

Schema::create('unit_categories', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name', 60);
    $table->string('description', 255)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
});
SoftDeleteUniqueIndex::make('unit_categories', ['name']);

Schema::create('units', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('unit_category_id')->constrained('unit_categories')->restrictOnDelete();
    $table->string('name', 60);
    $table->string('symbol', 10);
    $table->boolean('is_base')->default(false);
    $table->decimal('conversion_factor', 18, 6)->default(1);
    $table->boolean('allow_decimals')->default(true);
    $table->string('sunat_code', 5)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
});
SoftDeleteUniqueIndex::make('units', ['unit_category_id', 'name']);
DB::statement('CREATE UNIQUE INDEX units_one_base_per_category ON units (unit_category_id) WHERE is_base = true AND deleted_at IS NULL');

Schema::create('warehouses', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $table->string('code', 10);
    $table->string('name', 120);
    $table->string('address')->nullable();
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
    $table->index('branch_id');
});
SoftDeleteUniqueIndex::make('warehouses', ['code']);
DB::statement('CREATE UNIQUE INDEX warehouses_one_default_per_branch ON warehouses (branch_id) WHERE is_default = true AND deleted_at IS NULL');

Schema::create('warehouse_reasons', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('code', 30);
    $table->string('name', 120);
    $table->string('type', 20); // in | out | transfer | adjustment
    $table->boolean('requires_approval')->default(true);
    $table->boolean('is_system')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
    $table->index('type');
});
SoftDeleteUniqueIndex::make('warehouse_reasons', ['code']);
```
> Los índices parciales con `DB::statement` se ejecutan solo con `pgsql`/`sqlite` (mismo criterio que `SoftDeleteUniqueIndex`).

## 7. Configuración e infraestructura
- `InventoryMastersSeeder` (idempotente con `updateOrCreate` por `code`/`name`): unidades de requirements US-3 y motivos de sistema de US-5 (`is_system = true`, `requires_approval = false`). En entorno dev, un almacén `ALM01` por defecto en la sede principal.

## 8. Frontend
- **Página** `src/pages/inventory/settings/index.vue` (`definePage({ meta: { action: 'categories.view' } })`; cada pestaña se muestra con su propio `can('<recurso>.view')`): `VTabs` Categorías · Marcas · Unidades · Almacenes · Motivos.
- **Componentes** en `src/modules/inventory/components/settings/`: `CategoriesTab.vue` (árbol con `VTreeview` de Vuetify Labs o lista indentada + drawer con selector de padre), `BrandsTab.vue`, `UnitsTab.vue` (agrupado por categoría de unidad; chip "Base"; diálogo de prueba de conversión), `WarehousesTab.vue` (filtro por sede; estrella para el almacén por defecto), `WarehouseReasonsTab.vue` (candado en los de sistema). Cada pestaña usa `ServerDataTable`, `useConfirm` y `useApiFormErrors`.
- **Servicios/stores:** `src/modules/inventory/services/inventorySettingsApi.js` (un objeto por recurso) y `stores/inventorySettingsStore.js` con caché de `options` reutilizable por los specs 014–016.
- **Menú:** grupo "Inventario" (`tabler-packages`) con hijo "Configuración" (`action: 'categories.view'`). Los specs 014–016 agregan Catálogo, Stock, Kardex y Notas.

## 9. Testing
- `tests/Feature/Modules/Inventory/CategoriesApiTest.php`: CRUD; cuarto nivel → 422; ciclo → 422; duplicado entre hermanos → 422 y permitido en ramas distintas; `tree`; borrar con hijos → 422.
- `BrandsApiTest.php`: CRUD y duplicado.
- `UnitsApiTest.php`: segunda base en la misma categoría → 422; no base sin base existente → 422; `convert` (12 unidades → 1 docena); categorías distintas → 422.
- `WarehousesApiTest.php`: `is_default` exclusivo por sede; sede no accesible → 422; `options` filtra por `X-Branch-Id`; borrar una sede con almacenes → 422.
- `WarehouseReasonsApiTest.php`: motivo de sistema no editable ni eliminable; `options` excluye los de sistema por defecto.
- Ejecutar: `make test-filter FILTER=Inventory`.

## 10. Decisiones Rechazadas
- **`brands.type` (producto/vehículo/ambas):** rubro automotriz.
- **Almacén sin sede (`branch_id` nullable en Multinort):** genera stock "huérfano" fuera del control multi-sede.
- **Unidades sin categoría y conversión por ítem (`catalog_item_units`):** la conversión se resuelve con categoría + factor; las presentaciones del spec 014 cubren empaques específicos.
- **Árbol con nested sets o ltree:** con 3 niveles basta `parent_id` + `depth`.
