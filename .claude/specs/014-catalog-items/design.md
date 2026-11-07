# 014 · catalog-items — Design
> **Spec:** 014 · **Alcance:** fullstack · **Módulo:** Inventory · **Prioridad:** alta · **Depende de specs:** 010, 013
> **Status:** Done
> **Última actualización:** 2026-10-19
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend** (módulo `Inventory`):
  - `Models/{CatalogItem,ItemPresentation,ItemPrice}.php`
  - `Enums/{CatalogItemType,TaxType,PriceType}.php`
  - `Services/{CatalogItemService,ItemPresentationService,ItemPriceService,CatalogLookupService}.php`
  - `Controllers/{CatalogItemController,ItemPresentationController,ItemPriceController}.php`
  - `Requests/{CatalogItemRequest,ListCatalogItemsRequest,ItemPresentationRequest,SyncItemPricesRequest,CatalogLookupRequest}.php`
  - `Resources/{CatalogItemResource,ItemPresentationResource,ItemPriceResource,CatalogLookupResource}.php`
- **Transversal nuevo:** `app/Support/Numbering/SequentialCodeGenerator.php` — genera códigos `PREFIX-000001` a partir de la tabla `code_sequences` con `lockForUpdate` (lo reutilizan los specs 016, 021–029 para números internos).
- **Modificados:** `Inventory/Services/{CategoryService,BrandService,UnitService}::delete()` (bloqueo por ítems).
- **Frontend:** `front-vue/src/pages/inventory/catalog/index.vue`, `front-vue/src/pages/inventory/catalog/[id].vue`, `front-vue/src/modules/inventory/{services/catalogApi.js, stores/catalogStore.js, components/catalog/*}`, componente reutilizable `components/common/CatalogItemPicker.vue`.

## 1. Backend — Modelo y Persistencia
- **`CatalogItem`** (`HasUuids`, `SoftDeletes`, `LogsActivity`, `HasFactory`): `code`, `name`, `type` (`CatalogItemType`: `product`, `service`), `category_id`, `brand_id`, `base_unit_id`, `description`, `tax_type` (`TaxType`: `taxable`, `exempt`, `unaffected`), `track_stock`, `is_sellable`, `is_purchasable`, `is_active`, `last_purchase_cost` (decimal:6, lo actualiza el spec 023). Relaciones `category`, `brand`, `baseUnit`, `presentations`, `defaultPresentation` (`hasOne` con `is_default`), `prices` (`hasManyThrough` vía presentaciones).
- **`ItemPresentation`** (`HasUuids`, `SoftDeletes`, `LogsActivity`): `catalog_item_id`, `unit_id`, `name`, `factor` (decimal:4), `barcode`, `is_base`, `is_default`, `is_active`. Relaciones `item`, `unit`, `prices`. Helper `toBaseQuantity(float $qty): float` → `round($qty * factor, 4)`.
- **`ItemPrice`** (`HasUuids`, `LogsActivity` con `logOnly(['price','min_quantity'])`): `item_presentation_id`, `price_type` (`PriceType`: `retail`, `wholesale`), `price` (decimal:4), `min_quantity` (decimal:4).
- Scopes en `CatalogItem`: `search($q)` (code, name y `whereHas('presentations', barcode)`), `ofType`, `inCategoryTree($categoryId)` (ids del nodo y descendientes vía `CategoryService::descendantIds()`), `sellable`, `purchasable`, `stockable`.

## 2. Backend — Lógica de Negocio y Servicios
- **`CatalogItemService`**
  - `create(array $attrs, array $prices = [])`: transacción → `code ??= SequentialCodeGenerator::next('catalog_item', 'ITM')`; si `type = service` → `track_stock = false`; crea el ítem y su presentación base (`name = baseUnit.name`, `unit_id = base_unit_id`, `factor = 1`, `is_base = is_default = true`).
  - `update(CatalogItem $i, array $attrs)`: si cambia `base_unit_id` → valida `! $this->hasMovements($i)` (método que el spec 015 implementa; aquí devuelve `false`), actualiza la presentación base a la nueva unidad; `type` no cambia de `product` a `service` si hay movimientos.
  - `delete`: bloquea si `hasMovements` o si está referenciado en documentos (los specs 016, 022, 026, 027 amplían esta verificación).
- **`ItemPresentationService`**: `create`/`update` validan la categoría de unidad (design §4 de requirements), `factor` entero si `baseUnit.allow_decimals = false`, exclusividad de `is_default` (transacción); `delete` rechaza `is_base` y presentaciones con movimientos.
- **`ItemPriceService::sync(CatalogItem $i, array $rows)`**: valida que cada `presentation_id` pertenezca al ítem; `updateOrCreate` por (`item_presentation_id`, `price_type`) y elimina los pares no enviados. El activity log de `ItemPrice` registra `old`/`attributes`.
- **`CatalogLookupService::search(string $q, string $context, int $limit, ?string $warehouseId)`**: consulta sobre `item_presentations` con `join` a `catalog_items` (activos), filtro por contexto, orden `CASE WHEN barcode = :q THEN 0 WHEN code = :q THEN 1 ELSE 2 END, name`, `with('prices', 'unit')`. Punto de extensión `withStock()` para el spec 015.
- **`SequentialCodeGenerator::next(string $key, string $prefix, int $pad = 6, ?string $scope = null): string`** — `DB::transaction` + `lockForUpdate` sobre `code_sequences (key, scope)`; crea la fila si no existe; devuelve `"{$prefix}-".str_pad(++$last, $pad, '0', STR_PAD_LEFT)`.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `CatalogItemRequest`: `code` nullable|max:30|regex `^[A-Za-z0-9._-]+$`|unique ignorando eliminados; `name` required|max:200; `type` required|enum; `category_id` required|uuid|exists activo; `brand_id` nullable|uuid|exists activo; `base_unit_id` required|uuid|exists activo; `description` nullable|max:2000; `tax_type` required|enum; `track_stock`, `is_sellable`, `is_purchasable`, `is_active` boolean. En create acepta `prices` opcional (mismo formato que `SyncItemPricesRequest`, aplicado a la presentación base).
- `ItemPresentationRequest`: `name` required|max:80; `unit_id` required|uuid|exists; `factor` required|numeric|gt:0|decimal:0,4; `barcode` nullable|max:50|unique en `item_presentations` ignorando eliminados; `is_default`, `is_active` boolean.
- `SyncItemPricesRequest`: `prices` present|array; `prices.*.presentation_id` required|uuid; `prices.*.price_type` required|enum|distinct por presentación (validación en `withValidator`); `prices.*.price` required|numeric|min:0|decimal:0,4; `prices.*.min_quantity` nullable|numeric|min:1.
- `CatalogLookupRequest`: `q` required|string|min:1|max:100; `context` required|in:sale,purchase,stock; `limit` nullable|integer|max:50; `warehouse_id` nullable|uuid.
- `CatalogItemResource`: campos del modelo + `category {id,name}`, `brand {id,name}`, `base_unit {id,name,symbol}`, `presentations` (whenLoaded, con `prices`).
- Rutas (`v1/inventory`):
```php
// Catálogo maestro (Spec 014)
Route::get('/catalog-items/lookup', [CatalogItemController::class, 'lookup'])->middleware('permission:catalog.view');
Route::get('/catalog-items', [CatalogItemController::class, 'index'])->middleware('permission:catalog.view');
Route::post('/catalog-items', [CatalogItemController::class, 'store'])->middleware('permission:catalog.create');
Route::get('/catalog-items/{id}', [CatalogItemController::class, 'show'])->whereUuid('id')->middleware('permission:catalog.view');
Route::match(['put', 'patch'], '/catalog-items/{id}', [CatalogItemController::class, 'update'])->whereUuid('id')->middleware('permission:catalog.update');
Route::delete('/catalog-items/{id}', [CatalogItemController::class, 'destroy'])->whereUuid('id')->middleware('permission:catalog.delete');
Route::get('/catalog-items/{id}/presentations', [ItemPresentationController::class, 'index'])->whereUuid('id')->middleware('permission:catalog.view');
Route::post('/catalog-items/{id}/presentations', [ItemPresentationController::class, 'store'])->whereUuid('id')->middleware('permission:catalog.update');
Route::put('/catalog-items/{id}/presentations/{presentationId}', [ItemPresentationController::class, 'update'])->whereUuid(['id', 'presentationId'])->middleware('permission:catalog.update');
Route::delete('/catalog-items/{id}/presentations/{presentationId}', [ItemPresentationController::class, 'destroy'])->whereUuid(['id', 'presentationId'])->middleware('permission:catalog.update');
Route::put('/catalog-items/{id}/prices', [ItemPriceController::class, 'sync'])->whereUuid('id')->middleware('permission:catalog.update_prices');
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/inventory/catalog-items` | `catalog.view` | query `search, type, category_id, brand_id, is_active, is_sellable, is_purchasable, page, per_page` | 200 `data: CatalogItem[]`, `meta` | 401, 403, 422 |
| POST | `/api/v1/inventory/catalog-items` | `catalog.create` | campos §3 + `prices?` | 201 `data: CatalogItem` (con presentación base) | 401, 403, 422 |
| GET | `/api/v1/inventory/catalog-items/{id}` | `catalog.view` | — | 200 (con `presentations.prices`) | 401, 403, 404 |
| PUT | `/api/v1/inventory/catalog-items/{id}` | `catalog.update` | campos §3 | 200 | 401, 403, 404, 422 |
| DELETE | `/api/v1/inventory/catalog-items/{id}` | `catalog.delete` | — | 204 | 401, 403, 404, 422 |
| GET/POST/PUT/DELETE | `/api/v1/inventory/catalog-items/{id}/presentations[/{presentationId}]` | `catalog.view` / `catalog.update` | `name, unit_id, factor, barcode?, is_default?, is_active?` | 200/201/204 | 401, 403, 404, 422 |
| PUT | `/api/v1/inventory/catalog-items/{id}/prices` | `catalog.update_prices` | `{ prices: [{ presentation_id, price_type, price, min_quantity? }] }` | 200 `data: ItemPrice[]` | 401, 403, 404, 422 |
| GET | `/api/v1/inventory/catalog-items/lookup` | `catalog.view` | query `q, context, limit?, warehouse_id?` | 200 `data: CatalogLookup[]` | 401, 403, 422 |

Ejemplo de respuesta de `lookup`:
```json
{ "success": true, "message": "Operación exitosa", "data": [
  { "presentation_id": "…", "catalog_item_id": "…", "code": "ITM-000045", "name": "Arroz extra", "presentation_name": "Saco 50 kg", "factor": 50, "barcode": "7751234567890", "unit": { "id": "…", "symbol": "kg", "sunat_code": "KGM" }, "type": "product", "tax_type": "taxable", "track_stock": true, "prices": { "retail": 189.9, "wholesale": 180, "wholesale_min_quantity": 5 } }
] }
```

## 5. Permisos y seguridad
- Permisos nuevos (módulo `catalog`): `catalog.view`, `catalog.create`, `catalog.update`, `catalog.delete`, `catalog.update_prices`. `admin` todos; `manager` todos excepto `delete`; `user` `catalog.view`.

## 6. Migraciones
`2026_10_17_000002_create_code_sequences_table.php`:
```php
Schema::create('code_sequences', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('key', 60);
    $table->string('scope', 60)->default('global'); // p. ej. branch_id para numeración por sede
    $table->unsignedBigInteger('last_number')->default(0);
    $table->timestamps();
    $table->unique(['key', 'scope']);
});
```
`2026_10_17_000003_create_catalog_items_tables.php`:
```php
Schema::create('catalog_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('code', 30);
    $table->string('name', 200);
    $table->string('type', 20); // product | service
    $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
    $table->foreignUuid('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
    $table->foreignUuid('base_unit_id')->constrained('units')->restrictOnDelete();
    $table->text('description')->nullable();
    $table->string('tax_type', 20)->default('taxable');
    $table->boolean('track_stock')->default(true);
    $table->boolean('is_sellable')->default(true);
    $table->boolean('is_purchasable')->default(true);
    $table->boolean('is_active')->default(true)->index();
    $table->decimal('last_purchase_cost', 18, 6)->nullable();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['type', 'is_active']);
    $table->index('category_id');
});
SoftDeleteUniqueIndex::make('catalog_items', ['code']);
DB::statement('CREATE INDEX catalog_items_name_lower_idx ON catalog_items (lower(name))');

Schema::create('item_presentations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();
    $table->foreignUuid('unit_id')->constrained('units')->restrictOnDelete();
    $table->string('name', 80);
    $table->decimal('factor', 18, 4)->default(1);
    $table->string('barcode', 50)->nullable();
    $table->boolean('is_base')->default(false);
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
    $table->index('catalog_item_id');
});
SoftDeleteUniqueIndex::make('item_presentations', ['barcode']);
DB::statement('CREATE UNIQUE INDEX item_presentations_one_default ON item_presentations (catalog_item_id) WHERE is_default = true AND deleted_at IS NULL');

Schema::create('item_prices', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('item_presentation_id')->constrained('item_presentations')->cascadeOnDelete();
    $table->string('price_type', 20); // retail | wholesale
    $table->decimal('price', 18, 4);
    $table->decimal('min_quantity', 18, 4)->default(1);
    $table->timestamps();
    $table->unique(['item_presentation_id', 'price_type']);
});
```
> En `pgsql`/`sqlite`, `SoftDeleteUniqueIndex` sobre `barcode` permite varios `NULL`.

## 7. Configuración e infraestructura
- `CatalogSeeder` (solo dev): 10 productos y 3 servicios de ejemplo con presentaciones y precios.
- Sin variables de entorno nuevas.

## 8. Frontend
- **Listado** `src/pages/inventory/catalog/index.vue` (`action: 'catalog.view'`): `ServerDataTable` con código, nombre, tipo (chip), categoría, marca, unidad base, precio minorista de la presentación por defecto, estado y acciones. Filtros: búsqueda, tipo, categoría (`VTreeview` en menú o `VAutocomplete` sobre `/categories/options`), marca, estado.
- **Alta rápida:** `CatalogItemFormDrawer.vue` (campos generales + precio minorista/mayorista inicial de la presentación base).
- **Detalle** `src/pages/inventory/catalog/[id].vue`: pestaña General (mismo formulario) y pestaña "Presentaciones y precios" (`PresentationsPricesTab.vue`): tabla de presentaciones (nombre, unidad, factor, código de barras, por defecto) con alta/edición en diálogo, y columnas editables de precio minorista, mayorista y mínimo mayorista; botón "Guardar precios" (`can('catalog.update_prices')`).
- **`components/common/CatalogItemPicker.vue`** (reutilizable en specs 016, 022, 026, 027): `VAutocomplete` con búsqueda remota a `/lookup` (debounce 250 ms, soporte de lector de código de barras con Enter), props `context`, `warehouseId`; emite la presentación seleccionada completa.
- **Store/servicio:** `catalogStore.js`, `catalogApi.js`.
- **Menú:** "Inventario" › "Catálogo" (`tabler-box`, `action: 'catalog.view'`).

## 9. Testing
- `tests/Feature/Modules/Inventory/CatalogItemsApiTest.php`: crear con código autogenerado y presentación base; código duplicado → 422; servicio fuerza `track_stock = false`; filtro por categoría incluye subcategorías; 403 sin permiso; borrar categoría/marca/unidad en uso → 422.
- `ItemPresentationsApiTest.php`: alta con factor 12; factor decimal con unidad base sin decimales → 422; barcode duplicado → 422; `is_default` exclusivo; borrar la base → 422.
- `ItemPricesApiTest.php`: sync crea, actualiza y elimina; presentación de otro ítem → 422; sin `catalog.update_prices` → 403; actividad registrada.
- `CatalogLookupApiTest.php`: barcode exacto primero; contexto `sale` excluye no vendibles; inactivos excluidos.
- `tests/Feature/Support/SequentialCodeGeneratorTest.php`: correlativo por clave y por scope; dos llamadas devuelven números consecutivos.
- Ejecutar: `make test-filter FILTER=Catalog` y `make test-filter FILTER=SequentialCodeGenerator`.

## 10. Decisiones Rechazadas
- **Stock por presentación (Multinort):** obliga a convertir entre presentaciones del mismo ítem al vender; se lleva el stock en unidad base (spec 015) y la presentación solo aporta el factor.
- **`catalog_item_prices` con moneda y vigencia:** se simplifica a un precio vigente por tipo; el historial queda en el activity log.
- **Kits, herramientas, adicionales y tiempo estimado:** propios del taller.
- **Tabla de unidades por ítem (`catalog_item_units`):** reemplazada por presentaciones.
