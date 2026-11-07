# 030 · ubigeo-peru — TODO
> **Spec:** 030 · **Alcance:** fullstack · **Módulo:** Ubigeo · **Prioridad:** alta · **Depende de specs:** 005, 008
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
| T-1 | ✅ | back | S | `database/data/{ubigeo_inei.csv,README.md}` | — |
| T-2 | ✅ | back | M | `migrations/2026_10_16_000007_create_ubigeos_table.php`, `Ubigeo/Models/Ubigeo.php`, `seeders/{UbigeoSeeder,DatabaseSeeder}.php`, `tests/Fixtures/ubigeo_sample.csv` | T-1 |
| T-3 | ✅ | back | M | `Ubigeo/{Data/UbigeoData,Services/UbigeoService,Controllers/UbigeoController,Resources/UbigeoResource,Providers/UbigeoServiceProvider}.php`, `routes/api.php`, `bootstrap/providers.php` | T-2 |
| T-4 | ✅ | back | S | `Ubigeo/Rules/ValidUbigeo.php` | T-3 |
| T-5 | ✅ | back | M | `Configuration/{Models/CompanySetting,Requests/CompanySettingRequest,Resources/CompanySettingResource}.php`, `Maintenance/Requests/BranchRequest.php`, migración `…000006…` (se elimina) | T-4 |
| T-6 | ✅ | front | M | `modules/ubigeo/{services/ubigeoApi.js,stores/ubigeoStore.js,composables/useUbigeo.js,utils/ubigeoSearch.js}` | T-3 |
| T-7 | ✅ | front | M | `components/common/UbigeoSelect.vue` | T-6 |
| T-8 | ✅ | front | S | `modules/configuration/components/CompanyForm.vue`, `modules/maintenance/components/BranchFormDrawer.vue`, `pages/maintenance/branches/` | T-5, T-7 |
| T-9 | ✅ | docs | S | `README.md`, specs `021`/`025` `design.md`, `.agents/rules/ubigeo.md`, `.claude/specs/README.md` | T-8 |

---
## Detalle de Tareas
### T-1 — Archivo fuente del catálogo INEI
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/data/ubigeo_inei.csv` (nuevo)
- `back-laravel/database/data/README.md` (nuevo)
**Descripción:**
1. Obtener el listado oficial de ubigeos INEI (resolver antes la Open Question de versión/fuente de requirements §7). Fuentes válidas: INEI o el catálogo de ubigeo que publica SUNAT para facturación electrónica (mismo código INEI). **No** usar ubigeo RENIEC.
2. Normalizar a CSV UTF-8 sin BOM, separador coma, cabecera exacta `code,department,province,district`, una fila por distrito, códigos con ceros a la izquierda (`010101`), nombres en MAYÚSCULAS con tildes y `Ñ` tal como los publica la fuente, ordenado por `code`.
3. En `README.md`: fuente (URL o documento), fecha de descarga, número de filas y cómo regenerarlo.
**Tests:**
- Script de verificación puntual (no se commitea): 25 prefijos de 2 dígitos distintos, 196 prefijos de 4 dígitos, todos los códigos `^\d{6}$` y únicos, ningún nombre vacío.
**Criterios de done:**
- [x] CSV versionado y verificado; `README.md` con fuente y fecha.
- [x] `150122` → `LIMA,LIMA,MIRAFLORES`; `070101` → `CALLAO,CALLAO,CALLAO`.
**Riesgos / notas:**
- Algunos datasets abiertos traen la provincia "Callao" con código de departamento `07` y otros mezclan RENIEC: comprobar con 3-4 códigos conocidos.
- Cuidado con la codificación: un CSV en Latin-1 rompe tildes y `Ñ`.

---
### T-2 — Migración, modelo y seeder
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_16_000007_create_ubigeos_table.php`
- `back-laravel/app/Modules/Ubigeo/Models/Ubigeo.php`
- `back-laravel/database/seeders/UbigeoSeeder.php`
- `back-laravel/database/seeders/DatabaseSeeder.php` (leer primero y respetar convenciones)
- `back-laravel/tests/Fixtures/ubigeo_sample.csv`
- `back-laravel/tests/Feature/Modules/Ubigeo/{UbigeoSeederTest,UbigeoCatalogCompletenessTest}.php`
**Descripción:**
1. Migración con el DDL de design §6 (PK `uuid('id')->primary()`, `code` único, índices en `department_code` y `province_code`).
2. Modelo `Ubigeo` según design §1 (`HasUuids`, `$fillable`, cast `is_active`; sin `SoftDeletes` ni `LogsActivity`).
3. `UbigeoSeeder` según design §2: propiedad pública `string $path` (default `database_path('data/ubigeo_inei.csv')`) para usar el fixture en tests; validación por fila con número de línea; `upsert` en lotes de 500 por `code` generando `id` solo para filas nuevas; códigos ausentes → `is_active = false`; al final `UbigeoService::flushCache()` (si T-3 aún no existe, dejar `Cache::forget('ubigeo:catalog')` y `Cache::forget('ubigeo:version')`, y reemplazarlo en T-3).
4. Registrar `UbigeoSeeder` en `DatabaseSeeder` antes de `BranchSeeder`.
5. Fixture de ~10 filas con al menos un nombre con tilde (`ANCÓN`) y uno con `Ñ`.
**Tests:**
- `UbigeoSeederTest` (US-1): cuenta de filas y `department_code`/`province_code` derivados; segunda corrida no duplica y conserva `id`; nombre modificado se actualiza; código quitado del CSV queda `is_active = false`; fila con `code = '15A122'` lanza excepción que menciona la línea.
- `UbigeoCatalogCompletenessTest` (US-1): con el CSV real, 25 departamentos, 196 provincias, filas = líneas − 1, toda provincia pertenece a un departamento existente.
**Criterios de done:**
- [x] `make migrate-fresh` siembra el catálogo sin errores.
- [x] `make test-filter FILTER=Ubigeo` en verde; `cd back-laravel && vendor/bin/pint --test` en verde.
**Riesgos / notas:**
- `upsert` de Postgres no dispara eventos de Eloquent: `HasUuids` no genera el `id`; hay que ponerlo a mano para las filas nuevas (consultar primero los `code` existentes).

---
### T-3 — Servicio, endpoint y provider
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2
**Archivos clave:**
- `back-laravel/app/Modules/Ubigeo/Data/UbigeoData.php`
- `back-laravel/app/Modules/Ubigeo/Services/UbigeoService.php`
- `back-laravel/app/Modules/Ubigeo/Controllers/UbigeoController.php`
- `back-laravel/app/Modules/Ubigeo/Resources/UbigeoResource.php`
- `back-laravel/app/Modules/Ubigeo/Providers/UbigeoServiceProvider.php`
- `back-laravel/bootstrap/providers.php`, `back-laravel/routes/api.php` (leer primero y respetar convenciones)
- `back-laravel/tests/Feature/Modules/Ubigeo/UbigeoApiTest.php`
**Descripción:**
1. `UbigeoData` (`final readonly`) con `toArray()` y `label()` (design §1).
2. `UbigeoService` con `catalog`, `version`, `find`, `exists`, `describe`, `flushCache` (design §2); registrado como singleton en `UbigeoServiceProvider`; provider agregado a `bootstrap/providers.php`.
3. `UbigeoResource` (sin `id` ni timestamps) y `UbigeoController::index` con `ApiResponse`, `setEtag`, `setPrivate`, `setMaxAge(86400)` e `isNotModified` (design §3).
4. Ruta `GET /ubigeo` dentro del grupo `v1` (design §3). Reemplazar en `UbigeoSeeder` los `Cache::forget` por `flushCache()`.
**Tests:**
- `UbigeoApiTest` (US-2): 401 sin token; 200 con envelope, `data` ordenada por `code`, `meta.version` y cabecera `ETag`; `If-None-Match` con ese ETag → 304 sin cuerpo; tras cambiar un nombre y llamar `flushCache()` el ETag cambia.
- Unit dentro del mismo archivo o `UbigeoServiceTest`: `describe('150122')`, `describe(null) === '—'`, `describe('999999') === '—'`; `catalog()` hace una sola consulta en dos llamadas (`DB::enableQueryLog`).
**Criterios de done:**
- [x] `make test-filter FILTER=Ubigeo` en verde; `vendor/bin/pint --test` y `make phpstan` sin errores nuevos.
**Riesgos / notas:**
- En tests el driver de caché es `array`: limpiar la caché en `setUp` para no arrastrar catálogos entre tests.
- `ETag` debe ir entre comillas; `setEtag()` ya lo hace.

---
### T-4 — Regla `ValidUbigeo`
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-3
**Archivos clave:**
- `back-laravel/app/Modules/Ubigeo/Rules/ValidUbigeo.php`
- `back-laravel/tests/Feature/Modules/Ubigeo/UbigeoRuleTest.php`
**Descripción:**
1. `ValidUbigeo implements ValidationRule` con `__construct(private ?string $current = null)` y el comportamiento de design §3, usando `UbigeoService::exists()` / `find()`.
2. Mensaje único: `"El ubigeo seleccionado no es válido."`.
**Tests:**
- `UbigeoRuleTest` (US-5): `null` y `''` pasan; `'15012'`, `'abc123'`, `150122` (int), `'999999'` fallan con el mensaje; código activo pasa; inactivo falla; inactivo igual a `$current` pasa.
**Criterios de done:**
- [x] `make test-filter FILTER=UbigeoRuleTest` en verde; `vendor/bin/pint --test` en verde.

---
### T-5 — Adoptar la regla en empresa y sedes
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave (leer primero cada uno y respetar convenciones):**
- `back-laravel/database/migrations/2026_10_16_000006_add_location_fields_to_company_settings_table.php` (se elimina)
- `back-laravel/app/Modules/Configuration/Models/CompanySetting.php`
- `back-laravel/app/Modules/Configuration/Requests/CompanySettingRequest.php`
- `back-laravel/app/Modules/Configuration/Resources/CompanySettingResource.php`
- `back-laravel/app/Modules/Maintenance/Requests/BranchRequest.php`
- `back-laravel/tests/Feature/Modules/Configuration/CompanySettingsApiTest.php`
- `back-laravel/tests/Feature/Modules/Maintenance/BranchesApiTest.php`
**Descripción:**
1. Comprobar con `git log --oneline -- <migración 000006>` que no está commiteada. Si no lo está: borrar el archivo y quitar `department`, `province`, `district` de `$fillable`, reglas y resource. Si ya está en `master`: crear `…_drop_location_fields_from_company_settings_table.php` (design §6) y hacer lo mismo en modelo, request y resource.
2. `CompanySettingRequest`: `ubigeo` → `['nullable', 'string', new ValidUbigeo(CompanySettingService::current()->ubigeo)]` (sustituye `digits:6`).
3. `BranchRequest`: `ubigeo` → `['nullable', 'string', new ValidUbigeo(<ubigeo actual de la sede si es update>)]`; mantener el `trim` de `toAttributes()`.
4. En los tests que guardan ubigeo, sembrar el fixture con `UbigeoSeeder` (`$path` del fixture) en `setUp`.
**Tests:**
- `CompanySettingsApiTest` (US-5, US-7): 422 con `ubigeo: "999999"` y mensaje en español; 200 con un código del fixture; la respuesta no contiene `department`/`province`/`district`.
- `BranchesApiTest` (US-5): 422 al crear y al actualizar con `999999`; 201 con código válido.
**Criterios de done:**
- [x] `make migrate-fresh` sin errores; `make test` en verde; `vendor/bin/pint --test` en verde.
- [x] `grep -rn "'department'\|'province'\|'district'" back-laravel/app/Modules/Configuration` sin resultados.
**Riesgos / notas:**
- La migración 000006 y los cambios de `CompanySetting*` son trabajo en curso sin commitear: coordinar con el autor antes de descartarlos.

---
### T-6 — Store, servicio y búsqueda en el frontend
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-3
**Archivos clave:**
- `front-vue/src/modules/ubigeo/services/ubigeoApi.js`
- `front-vue/src/modules/ubigeo/stores/ubigeoStore.js`
- `front-vue/src/modules/ubigeo/composables/useUbigeo.js`
- `front-vue/src/modules/ubigeo/utils/ubigeoSearch.js`
- `front-vue/src/tests/unit/{ubigeoSearch,ubigeoStore}.spec.js`
**Descripción:**
1. `ubigeoApi.catalog()` con `$api` (design §8.1); ver `front-vue/src/modules/maintenance/services/branchesApi.js` como referencia de estilo.
2. `ubigeoStore` (design §8.2): `ensureLoaded()` con promesa compartida, mapas e índice construidos una vez con `markRaw`, `find(code)`, `error` y reintento.
3. `ubigeoSearch.js` (design §8.3): `normalize`, `buildSearchIndex`, `searchUbigeo`, `formatName`, sin dependencias de Vue.
4. `useUbigeo()` (design §8.4) con `describe(code, { withCode })`.
**Tests:**
- `ubigeoSearch.spec.js` (US-4): `normalize('Ancón') === 'ancon'`, `'Güeppí'`, `'Ñahuimpuquio'`; `"san"` devuelve primero los que empiezan con "san"; dep < prov < dist dentro del mismo rango; `"15"`, `"1501"`, `"15012"`; `"a"` → `[]`; máximo 50; `formatName('SAN JUAN DE LURIGANCHO') === 'San Juan de Lurigancho'`.
- `ubigeoStore.spec.js` (US-2, US-3, US-6): dos `ensureLoaded()` simultáneos → `ubigeoApi.catalog` llamado 1 vez; fallo → `error` y segundo `ensureLoaded()` vuelve a pedir; `provincesByDepartment.get('15')` y `districtsByProvince.get('1501')` correctos; `describe('150122')` → `'Lima, Lima, Miraflores'`, `describe(null)` → `'—'`.
**Criterios de done:**
- [x] `cd front-vue && npm run test` y `npm run lint` en verde.

---
### T-7 — Componente `UbigeoSelect`
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-6
**Archivos clave:**
- `front-vue/src/components/common/UbigeoSelect.vue`
- `front-vue/src/tests/unit/UbigeoSelect.spec.js`
**Descripción:**
1. Props, eventos y comportamiento de design §8.5, con `<script setup>` y un solo `AppAutocomplete` de `@core/components/app-form-elements` (`no-filter`, `clearable`).
2. Estados: `loading` y `disabled` mientras carga; mensaje con **Reintentar** si falla; "Ubigeo no válido" con valor inicial inexistente; "No se encontró el ubigeo NNNNNN" en búsqueda por código inexistente.
3. Búsqueda por código exacto de 6 dígitos selecciona sin elegir de la lista; cada resultado muestra "Departamento, Provincia, Distrito" y el código.
4. Label asociado al campo y navegación por teclado nativa de `VAutocomplete`.
**Tests:**
- `UbigeoSelect.spec.js` (US-3, US-4), store mockeado con un catálogo pequeño: es un solo campo; valor inicial `'150122'` muestra "Lima, Lima, Miraflores · 150122"; valor inexistente → "Ubigeo no válido"; buscar y elegir emite el código y `change` con el detalle; código exacto selecciona directo; limpiar emite `null`.
**Criterios de done:**
- [x] `npm run test` y `npm run lint` en verde.
- [ ] Probado a mano en `make front-dev`: búsqueda con y sin tildes, por código y por selects; tema claro y oscuro; ancho de móvil.
**Riesgos / notas:**
- `VAutocomplete` filtra por su cuenta: usar `no-filter` en el buscador porque el filtrado y el orden los hace `searchUbigeo`.
- Evitar reactividad profunda sobre las listas grandes (`shallowRef` / `markRaw`), o el primer render con ~1 890 distritos se nota.

---
### T-8 — Usar `UbigeoSelect` en empresa y sedes
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-5, T-7
**Archivos clave (leer primero cada uno y respetar convenciones):**
- `front-vue/src/modules/configuration/components/CompanyForm.vue`
- `front-vue/src/modules/maintenance/components/BranchFormDrawer.vue`
- `front-vue/src/pages/maintenance/branches/` (página del listado)
**Descripción:**
1. `CompanyForm.vue`: quitar `department`, `province`, `district` del `form`, del llenado inicial y del payload; reemplazar sus `AppTextField` y el de `ubigeo` por `<UbigeoSelect v-model="form.ubigeo" :error-messages="errors.ubigeo" @update:model-value="clearFieldError('ubigeo')" />`.
2. `BranchFormDrawer.vue`: reemplazar el `AppTextField` de `ubigeo` por `UbigeoSelect`; payload `ubigeo: form.value.ubigeo || null`.
3. Listado de sedes: columna **Ubicación** con `useUbigeo().describe(item.ubigeo)`.
**Tests:**
- Manual en `make front-dev`: editar empresa y una sede, guardar, recargar y ver el ubigeo como "Departamento, Provincia, Distrito · código"; enviar con ubigeo inválido forzado desde devtools y ver el error 422 bajo el componente; en Network, una sola petición a `/v1/ubigeo` aunque se abra el drawer varias veces.
**Criterios de done:**
- [x] `cd front-vue && npm run lint` y `npm run test` en verde.
- [x] `grep -rn "department\|province\|district" front-vue/src/modules/configuration` sin resultados.

---
### T-9 — Documentación, regla y anotación de specs
**Estado:** ✅ · **Subj:** docs · **Complejidad:** S · **Depende de:** T-8
**Archivos clave:**
- `README.md` (sección de despliegue)
- `.claude/specs/021-providers/design.md`, `.claude/specs/025-clients-crm-extension/design.md`
- `.agents/rules/ubigeo.md` (nuevo, desde [CLAUDE.md.delta.md](./CLAUDE.md.delta.md))
- `.claude/specs/README.md`, encabezados de este spec
**Descripción:**
1. README: comando `php artisan db:seed --class=UbigeoSeeder --force` y cuándo correrlo.
2. Specs 021 y 025: cambiar `ubigeo nullable|digits:6` por `ubigeo nullable + ValidUbigeo` y anotar el uso de `UbigeoSelect`; en 025 quitar "Ubigeo por tablas … pendiente".
3. Mergear `CLAUDE.md.delta.md` en `.agents/rules/ubigeo.md`.
4. Marcar el spec como `Done` en `.claude/specs/README.md` y en los encabezados.
5. `graphify update .`
**Tests:**
- `grep -rn "digits:6" .claude/specs/021-providers .claude/specs/025-clients-crm-extension` sin resultados.
**Criterios de done:**
- [x] Regla publicada en `.agents/rules/ubigeo.md`; índice de specs actualizado; grafo actualizado.
