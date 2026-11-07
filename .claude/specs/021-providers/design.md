# 021 · providers — Design
> **Spec:** 021 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 012, 014, 017
> **Status:** Done
> **Última actualización:** 2026-10-19
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend:** módulo nuevo `back-laravel/app/Modules/Purchases/` (los specs 022–024 lo amplían):
  - `Models/{Provider,ProviderContact,ProviderBankAccount,ProviderPrice}.php`
  - `Services/{ProviderService,ProviderContactService,ProviderBankAccountService,ProviderPriceService}.php`
  - `Controllers/{ProviderController,ProviderContactController,ProviderBankAccountController,ProviderPriceController}.php`
  - `Requests/{ProviderRequest,ListProvidersRequest,SyncProviderContactsRequest,SyncProviderBankAccountsRequest,ProviderPriceRequest}.php`
  - `Resources/{ProviderResource,ProviderOptionResource,ProviderContactResource,ProviderBankAccountResource,ProviderPriceResource}.php`
  - `Exceptions/PurchasesRuleException.php`, `Providers/PurchasesServiceProvider.php`
- **Reutilizado:** `app/Support/Identity/DocumentType` (spec 012) con todos sus casos y `rulesFor()` (RUC: `digits:11` + prefijo 10/15/17/20).
- **Frontend:** `front-vue/src/pages/purchases/providers/{index,[id]}.vue`, `front-vue/src/modules/purchases/{services/providersApi.js, stores/providersStore.js, components/providers/*}`, `components/common/ProviderPicker.vue`.

## 1. Backend — Modelo y Persistencia
- **`Provider`** (`HasUuids`, `SoftDeletes`, `LogsActivity`): `document_type`, `document_number`, `business_name`, `trade_name`, `address`, `ubigeo`, `email`, `phone`, `website`, `default_currency_id`, `credit_days`, `notes`, `is_active`. Relaciones `contacts`, `bankAccounts`, `prices`, `defaultCurrency`, `primaryContact`. Scopes `search`, `active`.
- **`ProviderContact`** (`HasUuids`): `provider_id`, `name`, `position`, `phone`, `email`, `is_primary`.
- **`ProviderBankAccount`** (`HasUuids`, `LogsActivity`): `provider_id`, `bank_id`, `currency_id`, `account_number`, `cci`, `is_detraction_account`.
- **`ProviderPrice`** (`HasUuids`, `LogsActivity`): `provider_id`, `item_presentation_id`, `currency_id`, `price` (decimal:4), `valid_from`, `valid_until` (nullable = vigente indefinidamente), `notes`, `last_purchase_price`, `last_purchase_at` (los escribe el spec 023 vía `ProviderPriceService::recordPurchase()`). Scope `validOn(Carbon $d)`.

## 2. Backend — Lógica de Negocio y Servicios
- **`ProviderService`**: CRUD; `options(search)` con orden por coincidencia exacta de documento; `delete` con hook `hasDocuments()` (los specs 022 y 024 lo amplían).
- **`ProviderContactService::sync(Provider, array)`** y **`ProviderBankAccountService::sync(Provider, array)`**: transacción; actualiza por `id` (que debe pertenecer al proveedor → si no, 422), crea los nuevos y elimina los ausentes; valida un solo primario.
- **`ProviderPriceService`**:
  - `create/update`: valida la no superposición con `whereRaw('daterange(valid_from, COALESCE(valid_until, \'infinity\'), \'[]\') && daterange(?, ?, \'[]\')')` en PostgreSQL (fallback en PHP para SQLite).
  - `compare(ItemPresentation $p)`: precios vigentes hoy, convertidos con `ExchangeRateService::convert()` solo para ordenar; incluye `catalog_item.last_purchase_cost`.
  - `recordPurchase(Provider, ItemPresentation, Currency, string $price, Carbon $at)` (lo usa el spec 023): actualiza o crea el registro vigente con `last_purchase_price` y `last_purchase_at`.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `ProviderRequest`: `document_type` required|enum; `document_number` required + `DocumentType::rulesFor()` + unicidad compuesta ignorando eliminados; `business_name` required|max:200; `trade_name` nullable|max:200; `address` nullable|max:255; `ubigeo` nullable|string + `new ValidUbigeo($valorActual)` (spec 030); `email` nullable|email|max:150; `phone` nullable|max:30; `website` nullable|url|max:200; `default_currency_id` nullable|uuid|exists; `credit_days` required|integer|between:0,365; `notes` nullable|max:2000; `is_active` boolean.
- `SyncProviderContactsRequest`: `contacts` present|array|max:20; `contacts.*.id` nullable|uuid; `.name` required|max:150; `.position` nullable|max:100; `.phone` nullable|max:30; `.email` nullable|email; `.is_primary` boolean.
- `SyncProviderBankAccountsRequest`: `accounts` present|array|max:10; `.bank_id`, `.currency_id` required|uuid|exists; `.account_number` required|max:30; `.cci` nullable|digits:20; `.is_detraction_account` boolean.
- `ProviderPriceRequest`: `item_presentation_id` required|uuid|exists; `currency_id` required|uuid|exists; `price` required|numeric|min:0|decimal:0,4; `valid_from` required|date; `valid_until` nullable|date|after_or_equal:valid_from; `notes` nullable|max:500.
- Rutas (`v1/purchases`):
```php
// Módulo Purchases — Proveedores (Spec 021)
Route::prefix('purchases')->group(function () {
    Route::get('/providers/options', [ProviderController::class, 'options'])->middleware('permission:providers.view');
    Route::get('/providers', [ProviderController::class, 'index'])->middleware('permission:providers.view');
    Route::post('/providers', [ProviderController::class, 'store'])->middleware('permission:providers.create');
    Route::get('/providers/{id}', [ProviderController::class, 'show'])->whereUuid('id')->middleware('permission:providers.view');
    Route::match(['put', 'patch'], '/providers/{id}', [ProviderController::class, 'update'])->whereUuid('id')->middleware('permission:providers.update');
    Route::delete('/providers/{id}', [ProviderController::class, 'destroy'])->whereUuid('id')->middleware('permission:providers.delete');
    Route::put('/providers/{id}/contacts', [ProviderContactController::class, 'sync'])->whereUuid('id')->middleware('permission:providers.update');
    Route::put('/providers/{id}/bank-accounts', [ProviderBankAccountController::class, 'sync'])->whereUuid('id')->middleware('permission:providers.update');
    Route::get('/providers/{id}/prices', [ProviderPriceController::class, 'index'])->whereUuid('id')->middleware('permission:providers.view');
    Route::post('/providers/{id}/prices', [ProviderPriceController::class, 'store'])->whereUuid('id')->middleware('permission:providers.update');
    Route::get('/provider-prices/compare', [ProviderPriceController::class, 'compare'])->middleware('permission:providers.view');
    Route::put('/provider-prices/{id}', [ProviderPriceController::class, 'update'])->whereUuid('id')->middleware('permission:providers.update');
    Route::delete('/provider-prices/{id}', [ProviderPriceController::class, 'destroy'])->whereUuid('id')->middleware('permission:providers.update');
});
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| CRUD | `/api/v1/purchases/providers[/{id}]` | `providers.*` | §3 | 200/201/204 | 401, 403, 404, 422 |
| GET | `/api/v1/purchases/providers/options` | `providers.view` | query `search` | 200 `data: ProviderOption[]` | 401, 403 |
| PUT | `/api/v1/purchases/providers/{id}/contacts` | `providers.update` | `{ contacts: [...] }` | 200 `data: Contact[]` | 401, 403, 404, 422 |
| PUT | `/api/v1/purchases/providers/{id}/bank-accounts` | `providers.update` | `{ accounts: [...] }` | 200 `data: BankAccount[]` | 401, 403, 404, 422 |
| GET/POST | `/api/v1/purchases/providers/{id}/prices` | `view` / `update` | §3 | 200 / 201 | 401, 403, 404, 422 |
| PUT/DELETE | `/api/v1/purchases/provider-prices/{id}` | `providers.update` | §3 | 200 / 204 | 401, 403, 404, 422 |
| GET | `/api/v1/purchases/provider-prices/compare` | `providers.view` | query `item_presentation_id` | 200 `data: { prices[], last_purchase_cost }` | 401, 403, 422 |

## 5. Permisos y seguridad
- Permisos nuevos (módulo `providers`): `providers.view|create|update|delete`. `admin` todos; `manager` todos excepto `delete`.

## 6. Migraciones
`2026_10_19_000001_create_providers_tables.php`:
```php
Schema::create('providers', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('document_type', 10); $t->string('document_number', 20);
    $t->string('business_name', 200); $t->string('trade_name', 200)->nullable();
    $t->string('address')->nullable(); $t->char('ubigeo', 6)->nullable();
    $t->string('email', 150)->nullable(); $t->string('phone', 30)->nullable(); $t->string('website', 200)->nullable();
    $t->foreignUuid('default_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
    $t->unsignedSmallInteger('credit_days')->default(0); $t->text('notes')->nullable();
    $t->boolean('is_active')->default(true)->index(); $t->timestamps(); $t->softDeletes();
});
SoftDeleteUniqueIndex::make('providers', ['document_type', 'document_number']);

Schema::create('provider_contacts', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
    $t->string('name', 150); $t->string('position', 100)->nullable(); $t->string('phone', 30)->nullable();
    $t->string('email', 150)->nullable(); $t->boolean('is_primary')->default(false); $t->timestamps();
});

Schema::create('provider_bank_accounts', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
    $t->foreignUuid('bank_id')->constrained('banks')->restrictOnDelete();
    $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
    $t->string('account_number', 30); $t->char('cci', 20)->nullable();
    $t->boolean('is_detraction_account')->default(false); $t->timestamps();
    $t->unique(['provider_id', 'bank_id', 'account_number']);
});

Schema::create('provider_prices', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
    $t->foreignUuid('item_presentation_id')->constrained('item_presentations')->restrictOnDelete();
    $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
    $t->decimal('price', 18, 4); $t->date('valid_from'); $t->date('valid_until')->nullable();
    $t->string('notes', 500)->nullable();
    $t->decimal('last_purchase_price', 18, 4)->nullable(); $t->timestamp('last_purchase_at')->nullable();
    $t->timestamps();
    $t->index(['item_presentation_id', 'valid_from']);
    $t->index(['provider_id', 'item_presentation_id']);
});
```

## 7. Configuración e infraestructura
- No aplica (sin variables nuevas). Seeder de dev: 3 proveedores con contactos y precios.

## 8. Frontend
- La ubicación del proveedor se captura con `<UbigeoSelect v-model="form.ubigeo" :error-messages="errors.ubigeo" />` y se muestra con `useUbigeo().describe()` (spec 030).
- **Listado** `src/pages/purchases/providers/index.vue` (`providers.view`): documento, razón social, contacto principal, teléfono, condición (contado / crédito N días), estado; alta rápida en drawer.
- **Detalle** `src/pages/purchases/providers/[id].vue`: pestañas General (formulario) · Contactos (tabla editable con guardado en lote) · Cuentas bancarias (tabla editable) · Precios (tabla con `CatalogItemPicker` `context=purchase`, vigencias y moneda).
- **`components/common/ProviderPicker.vue`**: `VAutocomplete` remoto sobre `/providers/options` (reutilizado por los specs 022–024).
- **Comparador:** en `CatalogItemPicker` con `context=purchase`, acción "Comparar precios" que abre `ProviderPriceCompareDialog.vue`.
- **Menú:** grupo "Compras" (`tabler-shopping-cart`) › "Proveedores" (`providers.view`).

## 9. Testing
- `tests/Feature/Modules/Purchases/ProvidersApiTest.php`: CRUD; documento duplicado → 422 (y reutilizable tras eliminar); RUC inválido → 422; options con coincidencia exacta primero; sync de contactos (dos primarios → 422; id de otro proveedor → 422); sync de cuentas (CCI inválido → 422); 403 sin permiso.
- `ProviderPricesApiTest.php`: alta; vigencias superpuestas → 422; vigencia abierta; `compare` ordena por precio convertido; `recordPurchase` actualiza el último precio.
- Ejecutar: `make test-filter FILTER=Provider`.

## 10. Decisiones Rechazadas
- **Email obligatorio y único (Multinort):** muchos proveedores pequeños no tienen email o comparten uno.
- **Una sola cuenta en el proveedor (`number_count`):** se normaliza en `provider_bank_accounts`.
- **Proveedor y cliente como una sola entidad "persona":** simplifica poco y complica permisos y validaciones; queda como Open Question.
