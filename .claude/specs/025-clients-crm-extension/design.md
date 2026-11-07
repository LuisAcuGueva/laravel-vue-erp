# 025 · clients-crm-extension — Design
> **Spec:** 025 · **Alcance:** fullstack · **Módulo:** Clients · **Prioridad:** media · **Depende de specs:** 003, 012
> **Status:** Done
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend (modificado):** módulo existente `back-laravel/app/Modules/Clients/`:
  - `Models/Client.php` (nuevos campos, casts, scopes), nuevo `Models/ClientContact.php`
  - `Enums/{ClientType,PriceType}.php` (`PriceType` se reutiliza de `Inventory/Enums/PriceType` del spec 014 si existe; no duplicar)
  - `Services/ClientService.php` (options, crédito, protección del cliente de sistema), nuevo `Services/ClientContactService.php`
  - `Requests/ClientRequest.php` (reglas nuevas), `ListClientsRequest.php` (filtros), nuevos `UpdateClientCreditRequest.php`, `SyncClientContactsRequest.php`
  - `Resources/ClientResource.php` (campos nuevos), nuevos `ClientOptionResource.php`, `ClientContactResource.php`
  - `Exceptions/ClientRuleException.php`
- **Rutas:** bloque `clients` de `routes/api.php` con `permission:` por acción y endpoints nuevos.
- **Seeders:** `ClientSeeder.php` (cliente de sistema + datos de ejemplo con documento), `RolePermissionSeeder.php` (`clients.manage_credit`).
- **Frontend (modificado):** `front-vue/src/pages/clients/index.vue`, nuevo `front-vue/src/pages/clients/[id].vue`, `front-vue/src/modules/clients/{services/clientsApi.js, stores/clientsStore.js, components/ClientFormDrawer.vue}`, nuevos `components/ClientContactsTab.vue`, `components/ClientCreditCard.vue`, `front-vue/src/components/common/ClientPicker.vue`.

## 1. Backend — Modelo y Persistencia
- `Client` (`HasUuids`, `SoftDeletes`, `LogsActivity` ya presentes): `$fillable` agrega `client_type`, `document_type`, `document_number`, `trade_name`, `representative_name`, `representative_document`, `email`, `phone`, `address`, `ubigeo`, `default_price_type`, `is_active`. **Fuera de `$fillable`:** `credit_limit`, `credit_days`, `is_system` (se asignan con `forceFill` en el servicio).
- Casts: `client_type => ClientType::class`, `document_type => DocumentType::class`, `credit_limit => 'decimal:2'`, `is_active`, `is_system => 'boolean'`.
- `scopeSearch` amplía las columnas: `document_number` (prefijo exacto), `name`, `trade_name`, `email`.
- Relación `contacts()`; `Client::system(): Client` estático (por `is_system = true`, cacheado por petición).
- `ClientContact` (`HasUuids`): `client_id`, `name`, `position`, `phone`, `email`, `is_primary`.

## 2. Backend — Lógica de Negocio y Servicios
- `ClientService::create/update`: rechaza modificar el cliente de sistema (`ClientRuleException('El cliente genérico no se puede modificar.')`); `client_type = company ⇒ document_type = RUC`.
- `ClientService::delete`: rechaza el de sistema; hook `hasDocuments()` (los specs 026–028 lo amplían).
- `ClientService::updateCredit(Client $c, string $limit, int $days)`: rechaza el cliente de sistema (crédito fijo 0).
- `ClientService::options(?string $search)`: activos; sin búsqueda → el de sistema primero y luego los más recientes; con búsqueda → coincidencia exacta de documento primero.
- `ClientContactService::sync()`: mismo patrón que `ProviderContactService` (spec 021).

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `ClientRequest::rules()` (leer primero el actual y mantener `name`/`description`):
  - `client_type` required|enum; `document_type` required|enum `DocumentType`; `document_number` required + `DocumentType::rulesFor()` + unicidad compuesta ignorando eliminados y el propio id.
  - `name` required|max:200; `trade_name` nullable|max:200; `representative_name` nullable|max:150; `representative_document` nullable|max:15; `email` nullable|email|max:150; `phone` nullable|max:30; `address` nullable|max:255; `ubigeo` nullable|string + `new ValidUbigeo($valorActual)` (spec 030); `default_price_type` required|in:retail,wholesale; `description` nullable|max:1000; `is_active` boolean.
  - `toAttributes()` extendido (mayúsculas en `name` para empresas, `trim`, vacíos → `null`).
- `UpdateClientCreditRequest`: `credit_limit` required|numeric|min:0|decimal:0,2; `credit_days` required|integer|between:0,365.
- `ClientResource`: agrega los campos nuevos, `credit_limit`, `credit_days`, `is_system`, `contacts` (whenLoaded) y `account { debt, available_credit }` (por ahora `debt = 0`; el spec 028 lo implementa con `ReceivableQueryService`).
- Rutas (reemplazan el bloque actual):
```php
// Módulo Clients (Spec 025 — autorización por permiso)
Route::prefix('clients')->group(function () {
    Route::get('/options', [ClientController::class, 'options'])->middleware('permission:clients.view');
    Route::get('/', [ClientController::class, 'index'])->middleware('permission:clients.view');
    Route::post('/', [ClientController::class, 'store'])->middleware('permission:clients.create');
    Route::get('/{id}', [ClientController::class, 'show'])->whereUuid('id')->middleware('permission:clients.view');
    Route::match(['put', 'patch'], '/{id}', [ClientController::class, 'update'])->whereUuid('id')->middleware('permission:clients.update');
    Route::delete('/{id}', [ClientController::class, 'destroy'])->whereUuid('id')->middleware('permission:clients.delete');
    Route::put('/{id}/credit', [ClientController::class, 'updateCredit'])->whereUuid('id')->middleware('permission:clients.manage_credit');
    Route::put('/{id}/contacts', [ClientContactController::class, 'sync'])->whereUuid('id')->middleware('permission:clients.update');
});
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/clients` | `clients.view` | query `search, client_type, is_active, page, per_page` | 200 `data: Client[]`, `meta` | 401, 403, 422 |
| GET | `/api/v1/clients/options` | `clients.view` | query `search` | 200 `data: ClientOption[]` | 401, 403 |
| POST | `/api/v1/clients` | `clients.create` | §3 | 201 | 401, 403, 422 |
| GET | `/api/v1/clients/{id}` | `clients.view` | — | 200 (con `contacts`, `account`) | 401, 403, 404 |
| PUT | `/api/v1/clients/{id}` | `clients.update` | §3 | 200 | 401, 403, 404, 422 |
| DELETE | `/api/v1/clients/{id}` | `clients.delete` | — | 204 | 401, 403, 404, 422 |
| PUT | `/api/v1/clients/{id}/credit` | `clients.manage_credit` | `{ credit_limit, credit_days }` | 200 | 401, 403, 404, 422 |
| PUT | `/api/v1/clients/{id}/contacts` | `clients.update` | `{ contacts: [...] }` | 200 | 401, 403, 404, 422 |

Ejemplo `POST /clients`:
```json
{ "client_type": "company", "document_type": "RUC", "document_number": "20601234567", "name": "COMERCIAL ANDINA S.A.C.", "trade_name": "Andina", "address": "Av. Los Próceres 123, Lima", "email": "compras@andina.pe", "default_price_type": "wholesale" }
```

## 5. Permisos y seguridad
- Permiso nuevo: `clients.manage_credit` ("Gestionar crédito de clientes"). `admin` y `manager` lo reciben; los roles existentes conservan sus permisos `clients.*` actuales.
- Corrección de seguridad: los cinco endpoints existentes pasan a exigir `permission:` (regla de requirements US-1).

## 6. Migraciones
`2026_10_20_000001_extend_clients_table.php` (nueva migración; no se edita `2026_10_13_234353_create_clients_table.php`):
```php
Schema::table('clients', function (Blueprint $t) {
    $t->string('client_type', 10)->default('person')->after('id');
    $t->string('document_type', 10)->nullable()->after('client_type');
    $t->string('document_number', 20)->nullable()->after('document_type');
    $t->string('trade_name', 200)->nullable()->after('name');
    $t->string('representative_name', 150)->nullable();
    $t->string('representative_document', 15)->nullable();
    $t->string('email', 150)->nullable(); $t->string('phone', 30)->nullable();
    $t->string('address')->nullable(); $t->char('ubigeo', 6)->nullable();
    $t->string('default_price_type', 10)->default('retail');
    $t->decimal('credit_limit', 18, 2)->default(0); $t->unsignedSmallInteger('credit_days')->default(0);
    $t->boolean('is_system')->default(false); $t->boolean('is_active')->default(true)->index();
    $t->string('name', 200)->change();
});
DB::statement('CREATE UNIQUE INDEX clients_document_unique ON clients (document_type, document_number) WHERE deleted_at IS NULL AND document_number IS NOT NULL');
DB::statement('CREATE UNIQUE INDEX clients_one_system ON clients ((true)) WHERE is_system = true');
DB::statement('CREATE INDEX clients_name_lower_idx ON clients (lower(name))');

Schema::create('client_contacts', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
    $t->string('name', 150); $t->string('position', 100)->nullable(); $t->string('phone', 30)->nullable();
    $t->string('email', 150)->nullable(); $t->boolean('is_primary')->default(false); $t->timestamps();
});
```
> `down()` elimina los índices, la tabla `client_contacts` y las columnas agregadas.

## 7. Configuración e infraestructura
- `ClientSeeder`: `firstOrCreate` del cliente de sistema (`forceFill(['is_system' => true])`) en todos los entornos; datos de ejemplo con RUC/DNI solo en local.
- `DatabaseSeeder`: el cliente de sistema se siembra también en producción (llamar a `ClientSeeder::seedSystemClient()` desde un seeder de datos base).

## 8. Frontend
- **Listado** `pages/clients/index.vue` (leer primero y mantener el patrón): columnas tipo (chip), documento, nombre, teléfono, email, crédito, estado; filtros por tipo y estado; filas del cliente de sistema sin acciones de edición/borrado.
- **`ClientFormDrawer.vue`**: secciones Identificación (tipo de cliente, tipo y número de documento con máscara), Datos (nombre, nombre comercial, representante), Contacto, Dirección (dirección + `<UbigeoSelect v-model="form.ubigeo" :error-messages="errors.ubigeo" />`, spec 030), Comercial (tipo de precio por defecto) y Observaciones.
- **Detalle** `pages/clients/[id].vue`: pestañas General · Contactos (`ClientContactsTab.vue`) · Crédito (`ClientCreditCard.vue`, editable con `can('clients.manage_credit')`, muestra deuda y disponible). Los specs 026–028 agregan pestañas de cotizaciones, ventas y estado de cuenta.
- **`components/common/ClientPicker.vue`**: `VAutocomplete` remoto sobre `/clients/options` con botón "+" (alta rápida en diálogo) y retorno del cliente creado; prop `requireRuc` (para facturas, spec 027).

## 9. Testing
- `tests/Feature/Modules/Clients/ClientsApiTest.php` (ampliar el existente): 403 por cada acción sin permiso; alta con RUC/DNI válidos e inválidos; duplicado → 422 (y reutilizable tras eliminar); `company` sin RUC → 422; cliente existente sin documento sigue listándose; `options` (sistema primero, documento exacto primero); cliente de sistema no editable/eliminable → 422; `credit` con y sin `manage_credit`; crédito enviado en el `PUT` general se ignora; sync de contactos.
- Ejecutar: `make test-filter FILTER=Clients`.

## 10. Decisiones Rechazadas
- **Nombres y apellidos separados (Multinort):** para comprobantes basta un `name` completo; se evita duplicar lógica de concatenación.
- **Mover `Clients` dentro de un módulo `Sales`:** el módulo existe, es consumido por el frontend y otros módulos lo referencian; se conserva.
- **Contraseña de cliente para app móvil:** fuera del alcance del ERP.
