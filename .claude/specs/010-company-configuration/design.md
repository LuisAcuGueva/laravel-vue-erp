# 010 · company-configuration — Design
> **Spec:** 010 · **Alcance:** fullstack · **Módulo:** Configuration · **Prioridad:** alta · **Depende de specs:** 005, 007, 008
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend:** módulo nuevo `back-laravel/app/Modules/Configuration/`:
  - `Models/CompanySetting.php`, `Models/SystemParameter.php`
  - `Services/CompanySettingService.php`, `Services/SystemParameterService.php`
  - `Controllers/CompanySettingController.php`, `Controllers/SystemParameterController.php`
  - `Requests/CompanySettingRequest.php`, `Requests/CompanyLogoRequest.php`, `Requests/UpdateSystemParameterRequest.php`, `Requests/ListSystemParametersRequest.php`
  - `Resources/CompanySettingResource.php`, `Resources/CompanySummaryResource.php`, `Resources/SystemParameterResource.php`
  - `Enums/ParameterValueType.php`
  - `Exceptions/ParameterNotEditableException.php`
  - `Providers/ConfigurationServiceProvider.php` (registrado en `bootstrap/providers.php`; registra `SystemParameterService` como singleton)
- **Seeders:** `database/seeders/SystemParameterSeeder.php` (llamado desde `DatabaseSeeder`) y los permisos nuevos en `RolePermissionSeeder.php`.
- **Frontend:** `front-vue/src/pages/configuration/index.vue` y `front-vue/src/modules/configuration/{services/configurationApi.js, stores/configurationStore.js, components/CompanyForm.vue, components/ParametersTable.vue}`.
- Se toma como referencia el módulo `Clients` (controller delgado + `ApiResponse`, `toAttributes()`, `LogsActivity`).

## 1. Backend — Modelo y Persistencia
### `CompanySetting`
- `HasUuids`, `LogsActivity` (`logAll()->logOnlyDirty()->dontSubmitEmptyLogs()`), `$keyType = 'string'`, `$incrementing = false`. Sin `SoftDeletes` (registro único).
- `$fillable`: `legal_name`, `trade_name`, `legal_representative`, `ruc`, `address`, `ubigeo`, `phone`, `email`, `website`, `logo_path`, `default_currency_code`, `default_tax_rate`, `prices_include_tax`, `terms_conditions`, `warranty_policy`, `social_links`.
- Casts: `default_tax_rate => 'decimal:2'`, `prices_include_tax => 'boolean'`, `social_links => 'array'`.
- Accessor `logoUrl(): ?string` → `Storage::disk('public')->url($this->logo_path)`.

### `SystemParameter`
- `HasUuids`, `LogsActivity` (solo `value`, `is_active`).
- `$fillable`: `group_key`, `item_key`, `label`, `description`, `value_type`, `value`, `is_editable`, `is_active`.
- Casts: `value => 'json'`, `value_type => ParameterValueType::class`, `is_editable`/`is_active => 'boolean'`.
- Accessor `key(): string` → `"{$group_key}.{$item_key}"`.
- `scopeGroup(Builder $q, ?string $group)`.

### `Enums/ParameterValueType` (string backed)
`String = 'string'`, `Integer = 'integer'`, `Decimal = 'decimal'`, `Boolean = 'boolean'`, `Json = 'json'`. Métodos:
- `rules(): array` — reglas de validación de `value` (`['string','max:1000']`, `['integer']`, `['numeric']`, `['boolean']`, `['array']`).
- `cast(mixed $raw): mixed` — `int`, `round((float), 4)`, `bool`, `array`, `string`.

## 2. Backend — Lógica de Negocio y Servicios
### `CompanySettingService`
- `current(): CompanySetting` — `CompanySetting::query()->firstOrCreate([], DEFAULTS)`. La unicidad la garantiza el índice único de la columna `singleton` (siempre `true`): ante una creación concurrente, `firstOrCreate` (vía `createOrFirst`) captura la violación y devuelve la fila existente. Sin transacción ni bloqueo en las lecturas. `DEFAULTS`: `legal_name = config('app.name')`, `ruc = null` (sin placeholder; se exige al guardar), `default_currency_code = 'PEN'`, `default_tax_rate = 18.00`, `prices_include_tax = true`.
- `update(array $attributes): CompanySetting`.
- `replaceLogo(UploadedFile $file): CompanySetting` — guarda en `company/` del disco `public` con `store()`, borra el `logo_path` anterior si existe y actualiza.

### `SystemParameterService` (singleton)
- `get(string $key, mixed $default = null): mixed` — resuelve desde `all()` y aplica `value_type->cast()`; devuelve `$default` si no existe o `is_active = false`.
- `decimal(string $key, float $default = 0.0): float`, `integer(...)`, `boolean(...)` — atajos tipados.
- `all(): array<string, SystemParameter>` — `Cache::rememberForever('system_parameters.all', ...)` indexado por `key`.
- `list(?string $group): Collection` — para el controller, ordenado por `group_key`, `item_key`.
- `update(SystemParameter $param, mixed $value): SystemParameter` — lanza `ParameterNotEditableException` si `! is_editable`; guarda y ejecuta `Cache::forget('system_parameters.all')`.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `CompanySettingController`: `show()`, `update(CompanySettingRequest)`, `uploadLogo(CompanyLogoRequest)`, `summary()`. Usa `ApiResponse::successResponse`.
- `SystemParameterController`: `index(ListSystemParametersRequest)`, `update(string $id, UpdateSystemParameterRequest)`. Devuelve 404 con `errorResponse('Parámetro no encontrado: {id}', 404)`.
- `CompanySettingRequest::rules()`:
  - `legal_name` required|string|max:200 · `trade_name` nullable|string|max:200
  - `ruc` required|string + `App\Support\Validation\ValidRuc` (11 dígitos, prefijo 10/15/16/17/20 y dígito verificador módulo 11).
  - `address` nullable|string|max:255 · `ubigeo` nullable|digits:6 · `phone` nullable|string|max:30 · `email` nullable|email|max:150 · `website` nullable|url|max:200
  - `default_currency_code` required|string|size:3|uppercase · `default_tax_rate` required|numeric|between:0,100|decimal:0,2 · `prices_include_tax` required|boolean
  - `terms_conditions`, `warranty_policy` nullable|string|max:5000 · `social_links` nullable|array · `social_links.*` nullable|url|max:200
  - `toAttributes()` hace `trim` y convierte strings vacíos en `null`.
- `CompanyLogoRequest`: `logo` required|file|mimes:png,jpg,jpeg,webp,svg|max:2048.
- `UpdateSystemParameterRequest`: en `rules()` resuelve el parámetro por `route('id')` y devuelve `['value' => ['present', ...$param->value_type->rules()]]`; `toValue()` aplica `cast()`.
- `ListSystemParametersRequest`: `group` nullable|string|max:50.
- `SystemParameterResource` expone `key`; `CompanySettingResource` expone `logo_url` (nunca `logo_path`).
- Rutas en `routes/api.php` dentro del grupo `v1`:
```php
// Módulo Configuration (Spec 010)
Route::prefix('configuration')->group(function () {
    Route::get('/company/summary', [CompanySettingController::class, 'summary']);
    Route::get('/company', [CompanySettingController::class, 'show'])->middleware('permission:configuration.view');
    Route::put('/company', [CompanySettingController::class, 'update'])->middleware('permission:configuration.update');
    Route::post('/company/logo', [CompanySettingController::class, 'uploadLogo'])->middleware('permission:configuration.update');
    Route::get('/parameters', [SystemParameterController::class, 'index'])->middleware('permission:configuration.view');
    Route::put('/parameters/{id}', [SystemParameterController::class, 'update'])->whereUuid('id')->middleware('permission:configuration.update');
});
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/configuration/company` | `configuration.view` | — | 200 `data: CompanySetting` | 401, 403 |
| PUT | `/api/v1/configuration/company` | `configuration.update` | campos de §3 | 200 `data: CompanySetting` | 401, 403, 422 |
| POST | `/api/v1/configuration/company/logo` | `configuration.update` | multipart `logo` | 200 `data: CompanySetting` | 401, 403, 422 |
| GET | `/api/v1/configuration/company/summary` | autenticado | — | 200 `data: CompanySummary` | 401 |
| GET | `/api/v1/configuration/parameters` | `configuration.view` | query `group?` | 200 `data: SystemParameter[]` | 401, 403 |
| PUT | `/api/v1/configuration/parameters/{id}` | `configuration.update` | `{ "value": <tipado> }` | 200 `data: SystemParameter` | 401, 403, 404, 422 |

Ejemplo `PUT /parameters/{id}`:
```json
// request
{ "value": 15 }
// 200
{ "success": true, "message": "Operación exitosa", "data": { "id": "9f2c…", "key": "sales.max_line_discount_percent", "group_key": "sales", "item_key": "max_line_discount_percent", "label": "Descuento máximo por línea (%)", "value_type": "decimal", "value": 15, "is_editable": true, "is_active": true } }
// 422
{ "message": "El parámetro no es editable.", "errors": { "value": ["El parámetro no es editable."] } }
```

## 5. Permisos y seguridad
- Nuevos permisos en `RolePermissionSeeder` (módulo `configuration`): `configuration.view` ("Ver configuración"), `configuration.update` ("Editar configuración"). Solo `admin` los recibe por defecto.
- El resumen (`/company/summary`) solo requiere JWT: no expone documento fiscal ni políticas.
- La subida de logo valida MIME en el servidor; los SVG se sirven desde el disco público sin ejecutarse en el contexto de la app (se muestran con `<img>`).

## 6. Migraciones
`back-laravel/database/migrations/2026_10_16_000001_create_company_settings_table.php`:
```php
Schema::create('company_settings', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->boolean('singleton')->default(true)->unique(); // una sola fila
    $table->string('legal_name', 200);
    $table->string('trade_name', 200)->nullable();
    $table->string('legal_representative', 150)->nullable(); // migración 000010
    $table->char('ruc', 11)->nullable(); // migración 000009: antes tax_id_type + tax_id
    $table->string('address')->nullable();
    $table->char('ubigeo', 6)->nullable();
    $table->string('phone', 30)->nullable();
    $table->string('email', 150)->nullable();
    $table->string('website', 200)->nullable();
    $table->string('logo_path')->nullable();
    $table->char('default_currency_code', 3)->default('PEN');
    $table->decimal('default_tax_rate', 5, 2)->default(18.00);
    $table->boolean('prices_include_tax')->default(true);
    $table->text('terms_conditions')->nullable();
    $table->text('warranty_policy')->nullable();
    $table->json('social_links')->nullable();
    $table->timestamps();
});
```
`back-laravel/database/migrations/2026_10_16_000002_create_system_parameters_table.php`:
```php
Schema::create('system_parameters', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('group_key', 50);
    $table->string('item_key', 80);
    $table->string('label', 150);
    $table->string('description', 500)->nullable();
    $table->string('value_type', 20);
    $table->json('value')->nullable();
    $table->boolean('is_editable')->default(true);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->unique(['group_key', 'item_key']);
    $table->index('group_key');
});
```

## 7. Configuración e infraestructura
- Disco `public` existente en `config/filesystems.php`; requiere `php artisan storage:link` (agregar al target de setup del `Makefile` si no está).
- `SystemParameterSeeder` usa `SystemParameter::updateOrCreate(['group_key'=>…, 'item_key'=>…], [...])` sin pisar `value` si la fila ya existe (solo actualiza `label`, `description`, `value_type`).
- Sin variables de entorno nuevas.

## 8. Frontend
- `src/pages/configuration/index.vue` (`definePage({ meta: { action: 'configuration.view' } })`, igual que `clients/index.vue`): `VTabs` con "Empresa" y "Parámetros".
- `components/CompanyForm.vue`: `VForm` en dos columnas (`VRow`/`VCol`) con datos fiscales, contacto, impuesto/moneda (`VSwitch` "Precios incluyen impuesto"), políticas (`VTextarea`) y redes sociales. Logo: `VFileInput` + `VImg` de vista previa. Errores 422 con `useApiFormErrors`; aviso de éxito con `useNotifications`.
- `components/ParametersTable.vue`: lista agrupada por `group_key` (`VExpansionPanels`), con un control por tipo: `VSwitch` (boolean), `VTextField type=number` (integer/decimal), `VTextField` (string) y `VTextarea` JSON (json). Guarda al confirmar; `loading` por fila.
- `stores/configurationStore.js` (Pinia, options API como `clientsStore`): `summary`, `company`, `parameters`, `isLoading`, `error`; acciones `fetchSummary`, `fetchCompany`, `updateCompany`, `uploadLogo`, `fetchParameters`, `updateParameter`.
- `fetchSummary()` se llama tras el login y al recargar con sesión (donde `authStore` carga `/auth/me`).
- Menú `navigation/vertical/index.js`: `{ title: 'Configuración', to: { name: 'configuration' }, icon: { icon: 'tabler-settings' }, action: 'configuration.view' }`.

## 9. Testing
- `back-laravel/tests/Feature/Modules/Configuration/CompanySettingsApiTest.php`: show crea el registro por defecto; update válido; RUC inválido → 422; 403 sin permiso; logo válido reemplaza el anterior (`Storage::fake('public')`); tipo inválido → 422; summary accesible para un usuario sin permisos y sin `tax_id`.
- `back-laravel/tests/Feature/Modules/Configuration/SystemParametersApiTest.php`: listado por grupo; update de decimal; tipo incorrecto → 422; no editable → 422; id inexistente → 404; id no UUID → 404.
- `back-laravel/tests/Unit/Modules/Configuration/SystemParameterServiceTest.php` (o Feature si requiere BD): `get()` castea por tipo, devuelve el default si falta o está inactivo y la caché se invalida tras `update()`.
- Ejecutar: `make test-filter FILTER=Configuration`.

## 10. Decisiones Rechazadas
- **Key-value genérico sin tipo (como `system_parameters` de Multinort con `category_id` y vigencias):** se descarta la vigencia y la categoría porque servían a campañas y promociones, que no se migran; el tipo explícito evita casts dispersos.
- **Configuración en `.env`/`config/*.php`:** no es editable por el cliente final sin despliegue.
- **Tablas de ubigeo:** costo de datos y mantenimiento alto para la Fase 1; se deja el código de 6 dígitos.
- **Columnas de redes sociales separadas (`url_facebook`, …):** se reemplazan por `social_links` JSON para no migrar el esquema cada vez que aparece una red nueva.
