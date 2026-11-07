# 030 · ubigeo-peru — Design
> **Spec:** 030 · **Alcance:** fullstack · **Módulo:** Ubigeo · **Prioridad:** alta · **Depende de specs:** 005, 008
> **Status:** Done
> **Última actualización:** 2026-10-16
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md): define el qué y el por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
**Backend:** nuevo módulo `back-laravel/app/Modules/Ubigeo/`:

```
Modules/Ubigeo/
├── Controllers/UbigeoController.php
├── Data/UbigeoData.php              # DTO readonly: code, department, province, district, isActive
├── Models/Ubigeo.php
├── Resources/UbigeoResource.php
├── Rules/ValidUbigeo.php            # ValidationRule reutilizable por todo módulo
├── Services/UbigeoService.php
└── Providers/UbigeoServiceProvider.php   # registrado en bootstrap/providers.php
```

Datos y seeding: `back-laravel/database/data/ubigeo_inei.csv` (versionado) y `database/seeders/UbigeoSeeder.php`.

**Frontend:**

```
front-vue/src/modules/ubigeo/
├── services/ubigeoApi.js
├── stores/ubigeoStore.js           # catálogo en memoria + índices
├── composables/useUbigeo.js        # describe(code), find(code), ensureLoaded()
└── utils/ubigeoSearch.js           # normalize(), buildSearchIndex(), searchUbigeo() (funciones puras)
front-vue/src/components/common/UbigeoSelect.vue   # auto-registrado (unplugin-vue-components, dir src/components)
```

**Se modifican:** `CompanySettingRequest`, `CompanySetting`, `CompanySettingResource`, `CompanyForm.vue` (y se retira la migración sin commitear `2026_10_16_000006_add_location_fields_to_company_settings_table.php`), `BranchRequest`, `BranchFormDrawer.vue`, la página `pages/maintenance/branches`, `DatabaseSeeder`, `routes/api.php`, `bootstrap/providers.php`.

## 1. Backend — Modelo y Persistencia
`App\Modules\Ubigeo\Models\Ubigeo`:
- `use HasUuids;` · `$table = 'ubigeos'` · sin `SoftDeletes` (catálogo de referencia; los retiros se marcan `is_active = false`).
- `$fillable`: `code`, `department_code`, `province_code`, `department`, `province`, `district`, `is_active`.
- `$casts`: `is_active => 'boolean'`.
- Sin `LogsActivity`: el catálogo cambia solo por seeder.
- Ninguna tabla de dominio declara FK a `ubigeos`: guardan `char(6)` y se validan con `ValidUbigeo` (ver §10).

`App\Modules\Ubigeo\Data\UbigeoData` (`final readonly class`): `code`, `department`, `province`, `district`, `isActive`; `toArray()` con claves snake_case; `label(): string` → `"LIMA, LIMA, MIRAFLORES"`.

## 2. Backend — Lógica de Negocio y Servicios
`App\Modules\Ubigeo\Services\UbigeoService` (singleton en el provider):

| Método | Descripción |
|---|---|
| `catalog(): Collection<string, UbigeoData>` | Todo el catálogo indexado por `code`, ordenado por `code`. `Cache::rememberForever('ubigeo:catalog', …)` + memo en propiedad para la petición. Una sola consulta. |
| `version(): string` | `sha1` del catálogo serializado, cacheado junto con él (`ubigeo:version`). Sirve de `ETag` y `meta.version`. |
| `find(?string $code): ?UbigeoData` | Busca en `catalog()`; `null` si no existe o es `null`. |
| `exists(?string $code, bool $activeOnly = true): bool` | Usado por `ValidUbigeo`. |
| `describe(?string $code): string` | `UbigeoData::label()` o `'—'`. Para PDFs y exportaciones (spec 027). |
| `flushCache(): void` | `Cache::forget` de ambas claves; lo llama el seeder al terminar. |

No hay `create/update/delete`: el catálogo es de solo lectura para la aplicación.

`Database\Seeders\UbigeoSeeder`:
1. Lee `database_path('data/ubigeo_inei.csv')` (cabecera `code,department,province,district`, UTF-8).
2. Valida cada fila: `code` `^\d{6}$`, nombres no vacíos; aborta con excepción y número de línea si falla.
3. Deriva `department_code = substr(code,0,2)`, `province_code = substr(code,0,4)`.
4. `Ubigeo::upsert($rows, uniqueBy: ['code'], update: ['department_code','province_code','department','province','district','is_active','updated_at'])` en lotes de 500. El `id` se genera en PHP (`Str::uuid()`) solo para filas nuevas; `upsert` no lo actualiza.
5. Códigos presentes en BD y ausentes del CSV → `is_active = false` (nunca `delete`).
6. `app(UbigeoService::class)->flushCache()`.

Se agrega a `DatabaseSeeder::run()` **antes** de `BranchSeeder`. En producción: `php artisan db:seed --class=UbigeoSeeder --force` en cada despliegue que cambie el CSV (idempotente).

## 3. Backend — Presentation (Controllers, Requests, Resources)
**`UbigeoController::index(Request $request)`** (usa `ApiResponse`):

```php
$catalog = $this->ubigeoService->catalog();
$version = $this->ubigeoService->version();

$response = $this->successResponse(
    data: UbigeoResource::collection($catalog->values()),
    meta: ['version' => $version],
);
$response->setEtag($version)->setPrivate()->setMaxAge(86400);
$response->isNotModified($request); // convierte en 304 sin cuerpo si If-None-Match coincide

return $response;
```

**`UbigeoResource`:** `code`, `department`, `province`, `district`, `is_active`. Sin `id`, sin timestamps (reduce el payload).

**`App\Modules\Ubigeo\Rules\ValidUbigeo implements ValidationRule`:**
- Constructor `__construct(private ?string $current = null)`.
- `null` / `''` → pasa (la obligatoriedad la decide `required` / `nullable` del request).
- No string o no `^\d{6}$` → falla.
- Existe y activo → pasa. Existe inactivo y `=== $current` → pasa (registros antiguos se pueden volver a guardar sin cambiar su ubigeo). En otro caso → `"El ubigeo seleccionado no es válido."`.

Uso en requests:
```php
'ubigeo' => ['nullable', 'string', new ValidUbigeo($this->route('id') ? Branch::find($this->route('id'))?->ubigeo : null)],
```
(`CompanySettingRequest` pasa `CompanySettingService::current()->ubigeo`.)

**Rutas** (`routes/api.php`, dentro del grupo `v1` con `throttle:api` + `JwtAuthenticate`):
```php
// Ubigeo (catálogo INEI, solo lectura)
Route::get('/ubigeo', [UbigeoController::class, 'index']);
```
Sin `{id}`: no aplica `whereUuid`.

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/ubigeo` | `throttle:api`, `JwtAuthenticate` · autenticado | — (cabecera opcional `If-None-Match`) | 200 envelope + `ETag` · 304 sin cuerpo | 401, 429 |
| PUT | `/api/v1/configuration/company` | `configuration.update` | `…, ubigeo?` | 200 | 422 `ubigeo` |
| POST/PUT | `/api/v1/branches[/{id}]` | `branches.create` / `branches.update` | `…, ubigeo?` | 201 / 200 | 422 `ubigeo` |

```json
// 200 GET /api/v1/ubigeo
// ETag: "3f1c…"   Cache-Control: max-age=86400, private
{
  "success": true,
  "message": "Operación exitosa",
  "data": [
    { "code": "010101", "department": "AMAZONAS", "province": "CHACHAPOYAS", "district": "CHACHAPOYAS", "is_active": true },
    { "code": "150122", "department": "LIMA", "province": "LIMA", "district": "MIRAFLORES", "is_active": true }
  ],
  "meta": { "version": "3f1c…" }
}
```

```json
// 422 PUT /api/v1/branches/{id} con "ubigeo": "999999"
{
  "success": false,
  "message": "El ubigeo seleccionado no es válido.",
  "errors": { "ubigeo": ["El ubigeo seleccionado no es válido."] }
}
```

## 5. Permisos y seguridad
- Sin permiso Spatie nuevo: el catálogo es público dentro de la sesión (dato abierto del INEI). Solo requiere JWT.
- Sin endpoints de escritura: no hay superficie de modificación desde la API.
- `throttle:api` aplica; con `max-age=86400` y `ETag` las recargas no consumen el límite en la práctica.
- Ningún cambio en `RolePermissionSeeder` ni en CASL.

## 6. Migraciones
`back-laravel/database/migrations/2026_10_16_000007_create_ubigeos_table.php`:

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubigeos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('code', 6)->unique();
            $table->char('department_code', 2)->index();
            $table->char('province_code', 4)->index();
            $table->string('department', 60);
            $table->string('province', 60);
            $table->string('district', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubigeos');
    }
};
```

**`company_settings`:** la migración `2026_10_16_000006_add_location_fields_to_company_settings_table.php` **no está commiteada ni aplicada fuera del entorno local**: se elimina el archivo (y en local `make migrate-fresh`). Si para cuando se implemente ya estuviera en `master`, se crea en su lugar `…_drop_location_fields_from_company_settings_table.php` con `dropColumn(['department','province','district'])` (nunca se edita una migración aplicada).

Las columnas `ubigeo char(6)` existentes (`company_settings`, `branches`) no cambian.

## 7. Configuración e infraestructura
- Sin variables de entorno nuevas.
- Archivo de datos: `back-laravel/database/data/ubigeo_inei.csv` (~1 890 filas, < 100 KB). Fuente y fecha en la primera línea de `back-laravel/database/data/README.md`.
- Producción ya comprime `application/json` (`docker/nginx/default.prod.conf`, `gzip on`): la respuesta queda en ~25 KB.
- Documentar en el README raíz (sección de despliegue): `php artisan db:seed --class=UbigeoSeeder --force`.

## 8. Frontend
### 8.1 `modules/ubigeo/services/ubigeoApi.js`
```js
export const ubigeoApi = {
  catalog() {
    return $api('/v1/ubigeo', { method: 'GET' })
  },
}
```
El navegador gestiona `ETag` / `If-None-Match` y `max-age`; el código no maneja 304.

### 8.2 `modules/ubigeo/stores/ubigeoStore.js` (Pinia, setup store)
- Estado: `items` (`shallowRef([])`), `byCode` (`Map`), `searchIndex`, `isLoading`, `error`, `version`.
- `ensureLoaded()`: si ya hay datos, retorna; si hay una petición en curso, retorna **la misma promesa** (varios componentes montados a la vez → una sola petición); si no, la crea. En error deja `error = 'No se pudo cargar el catálogo de ubigeo'` y limpia la promesa para permitir reintentar.
- Al cargar construye el mapa y el índice de búsqueda una sola vez (`markRaw`, sin reactividad profunda sobre ~1 890 objetos).
- `find(code)` → `{ code, department, province, district, isActive } | null`.

### 8.3 `modules/ubigeo/utils/ubigeoSearch.js` (puro, testeable sin Vue)
- `normalize(text)`: NFD + quita marcas combinantes (`\p{M}`), minúsculas y `trim` (`ñ` → `n`).
- `formatName(name)`: formato título (`"SAN JUAN DE LURIGANCHO"` → `"San Juan de Lurigancho"`, preposiciones `de`, `del`, `la`, `las`, `los`, `y`, `el` en minúscula). `formatUbigeo(u)` → `"Lima, Lima, Miraflores"`.
- `buildSearchIndex(items)`: una entrada por **distrito activo** con `code`, nombres, `label` y claves normalizadas (`districtKey`, `provinceKey`, `departmentKey`, `haystack`).
- `searchUbigeo(index, query, limit = 50)`:
  - `q = normalize(query)`; `< 2` caracteres → `[]`.
  - `^\d+$` → distritos cuyo `code` empieza con `q`, en orden de código.
  - Texto → cada palabra de `q` debe estar en `haystack`; rango: distrito empieza (0) → distrito contiene (1) → provincia empieza (2) → departamento empieza (3) → resto (4); luego alfabético por distrito.
  - Corta en `limit`.

### 8.4 `modules/ubigeo/composables/useUbigeo.js`
```js
export function useUbigeo() {
  const store = useUbigeoStore()
  store.ensureLoaded()
  return {
    isReady: computed(() => store.items.length > 0),
    find: store.find,
    describe: (code, { withCode = false } = {}) => /* "Lima, Lima, Miraflores" | "—" */,
  }
}
```
Es la **única** forma de convertir un código en texto en el frontend (listados, detalles, chips).

### 8.5 `components/common/UbigeoSelect.vue`
Un **solo campo** (`AppAutocomplete`, `no-filter`, `clearable`, icono `tabler-map-pin`); no hay selects de departamento, provincia y distrito.

| Prop / evento | Tipo | Default | Descripción |
|---|---|---|---|
| `modelValue` (`v-model`) | `String \| null` | `null` | Código de distrito de 6 dígitos |
| `label` | `String` | `'Ubigeo'` | Label del campo (`*` si `required`) |
| `placeholder` | `String` | `'Busque por departamento, provincia, distrito o código'` | |
| `required` | `Boolean` | `false` | Regla "Seleccione el ubigeo" |
| `disabled` / `readonly` | `Boolean` | `false` | |
| `errorMessages` | `String \| String[]` | `[]` | Error 422 (`errors.ubigeo`) |
| `@update:modelValue` | `String \| null` | | Al elegir un distrito o limpiar |
| `@change` | `{ code, department, province, district } \| null` | | Detalle completo del ubigeo elegido |
| `selected` (expuesto) | `{ code, department, province, district } \| null` | | Estado interno derivado del `v-model` |

**Comportamiento:**
- `onMounted` → `store.ensureLoaded()`. Mientras carga: `loading` y `disabled`. Si falla: mensaje "No se pudo cargar el catálogo de ubigeo" con botón **Reintentar** bajo el campo.
- `items` = `searchUbigeo(store.searchIndex, search)`; el ubigeo seleccionado se agrega siempre para que el campo muestre su texto. `item-title` = "Lima, Lima, Miraflores · 150122"; en la lista, título "Lima, Lima, Miraflores" y subtítulo el código.
- Valor inicial inexistente → "Ubigeo no válido".
- Código exacto: `watch(search)` con `/^\d{6}$/`; si existe y está activo, se selecciona directo; si no, `no-data-text` = "No se encontró el ubigeo 999999".
- El valor emitido siempre es el código; los nombres se derivan del catálogo (no se guardan en tablas de dominio).

**Uso estándar:**
```vue
<UbigeoSelect
  v-model="form.ubigeo"
  :error-messages="errors.ubigeo"
  @update:model-value="clearFieldError('ubigeo')"
/>
```

### 8.6 Adopción
- `CompanyForm.vue` (sección Contacto y Ubicación): reemplaza `AppTextField` de `ubigeo` y los de `department` / `province` / `district` por un `UbigeoSelect` junto a la dirección fiscal; quita esos 3 campos de `form`, del llenado inicial y del payload.
- `BranchFormDrawer.vue`: reemplaza el `AppTextField` de `ubigeo` (líneas ~164-169) por `UbigeoSelect`; el payload envía `form.ubigeo || null`.
- `pages/maintenance/branches`: nueva columna **Ubicación** con `useUbigeo().describe(item.ubigeo)`.
- Specs 021 y 025 (`design.md`): anotar "campo `ubigeo` con `ValidUbigeo` y `UbigeoSelect` (spec 030)" en lugar de `digits:6` y texto libre; en 025 se quita la línea "Ubigeo por tablas … pendiente".

### 8.7 MSW
No aplica: el proyecto no registra handlers MSW para módulos de negocio; las pruebas de frontend mockean `$api` con Vitest.

## 9. Testing
**Backend** (`back-laravel/tests/Feature/Modules/Ubigeo/`, `make test-filter FILTER=Ubigeo`):
- `UbigeoSeederTest`: siembra un CSV de fixture (`tests/Fixtures/ubigeo_sample.csv`, ~10 filas, con tildes) → filas y códigos derivados correctos; resembrar no duplica y conserva `id`; cambio de nombre se actualiza; código ausente queda `is_active = false`; fila con `code` inválido lanza excepción con número de línea. El seeder acepta la ruta del CSV por propiedad para usar el fixture.
- `UbigeoCatalogCompletenessTest`: siembra el CSV real → 25 `department_code`, 196 `province_code`, filas = líneas del CSV − 1; todo `province_code` empieza con un `department_code` existente.
- `UbigeoApiTest`: 401 sin token; 200 con envelope, orden por `code`, `meta.version`, cabecera `ETag`; con `If-None-Match` igual → 304 sin cuerpo; tras `flushCache()` y cambio de datos el `ETag` cambia.
- `UbigeoRuleTest`: `null` pasa; `'15012'`, `'abc123'`, `'999999'` fallan con el mensaje en español; activo pasa; inactivo falla salvo que sea `$current`.
- `CompanySettingsApiTest` y `BranchesApiTest`: 422 con `ubigeo: "999999"`; 200 con código del fixture; la respuesta de empresa ya no trae `department` / `province` / `district`.

**Frontend** (`front-vue/src/tests/unit/`, `npm run test`):
- `ubigeoSearch.spec.js`: `normalize` (tildes, diéresis, ñ); ranking `startsWith` antes que `includes`; tipos en orden; prefijos `15`, `1501`, `15012`; menos de 2 caracteres → `[]`; límite 50; `formatName`.
- `ubigeoStore.spec.js`: dos `ensureLoaded()` simultáneos → una sola llamada a `ubigeoApi.catalog`; error → `error` y reintento posible; mapas de provincias y distritos correctos.
- `UbigeoSelect.spec.js` (`@vue/test-utils` + Vuetify): valor inicial llena los 3 selects; cambiar departamento emite `null`; elegir distrito emite el código; código exacto de 6 dígitos selecciona directo.

**Gates:** `vendor/bin/pint --test`, Larastan, `make test`, `npm run lint`, `npm run test`.

## 10. Decisiones Rechazadas
- **Tres tablas normalizadas (`departments`, `provinces`, `districts`) con FKs UUID:** el código INEI ya codifica la jerarquía; tres tablas obligan a joins y a mapear UUIDs ↔ códigos sin ganar integridad. Una tabla plana de ~1 890 filas con `department_code` y `province_code` indexados basta.
- **Código como PK (`char(6) primary`):** contradice `.agents/rules/database-primary-keys.md`. Se usa `uuid id` + `code` único.
- **FK `ubigeo → ubigeos.code` en tablas de dominio:** obligaría a ordenar seeders en tests de todos los módulos y fallaría con datos existentes sin validar; la validación `ValidUbigeo` + catálogo de solo lectura dan la misma garantía en la API.
- **Búsqueda en el servidor (`GET /ubigeo/search?q=`):** una petición por tecla, latencia visible y carga innecesaria para un dataset que cabe en ~25 KB comprimidos. La búsqueda local es instantánea.
- **Endpoints encadenados (`/departments`, `/departments/{code}/provinces`, …):** 3 peticiones por formulario y estados de carga intermedios; el catálogo completo cacheado los vuelve innecesarios.
- **Guardar `department` / `province` / `district` como texto en cada tabla:** duplica datos y permite inconsistencias con el código; se derivan con `useUbigeo().describe()` y `UbigeoService::describe()`.
- **Paquete npm o API externa de ubigeo:** dependencia de terceros, sin control de versión del dato; una API externa además rompe el seeding sin conexión y agrega un punto de falla en cada formulario.
- **Cachear el catálogo en `localStorage`:** el caché HTTP con `ETag` + `max-age` ya evita la descarga; `localStorage` agrega invalidación manual y otra fuente de verdad.
