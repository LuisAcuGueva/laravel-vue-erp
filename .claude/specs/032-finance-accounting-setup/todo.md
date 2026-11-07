# 032 · finance-accounting-setup — TODO
> **Spec:** 032 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** alta · **Depende de specs:** 017, 018, 019, 020, 024, 028, 029
> **Status:** Done
> **Última actualización:** 2026-10-20
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md), [design.md](./design.md).
>
> **Convenciones:**
> - 🔧 = pendiente · ✅ = hecho · ⚪ = opcional
> - **Subj** (subproyecto): `back` | `front` | `infra` | `docs`
> - **Complejidad:** S = ≤ 1 h · M = ≤ ½ día · L = > ½ día (evitar L: partir en varias M)
> - Cada tarea es **autocontenida**: un agente con `requirements.md`, `design.md` y los archivos indicados puede ejecutarla sin contexto adicional.
> - **Dependencias** explícitas entre tareas → preservar el orden indicado.
> - Antes de explorar código, correr `graphify query "<pregunta>"` (regla del repo). Al terminar cada tarea, correr `graphify update .`.
>
---
## Mapa de Tareas
| ID | Estado | Subj | Compl | Archivos clave | Depende de |
|---|---|---|---|---|---|
| T-1 | ✅ | back | S | `Finance/Support/AccountCode.php`, `Enums/SystemConcept.php`, `Enums/ConceptDirection.php`, `Enums/FinancialAccountType.php` | — |
| T-2 | ✅ | back | M | `database/data/financial_accounts_pcge.php`, `database/data/finance_concepts.php`, `Finance/Support/AccountingPlanSynchronizer.php` | T-1 |
| T-3 | ✅ | back | M | `FinancialAccountSeeder.php`, `FinanceConceptSeeder.php`, `FinanceMastersSeeder.php`, `FinanceDemoSeeder.php`, `SystemParameterSeeder.php` | T-2 |
| T-4 | ✅ | back | M | `migrations/2026_10_21_000001_normalize_financial_account_codes.php` | T-2 |
| T-5 | ✅ | back | M | `Services/FinancialAccountService.php`, `Requests/FinancialAccountRequest.php` | T-1 |
| T-6 | ✅ | back | M | `Services/FinanceConceptService.php`, `Controllers/FinanceConceptController.php`, `Models/FinanceConcept.php`, `Resources/FinancialAccountResource.php` | T-1, T-3 |
| T-7 | ✅ | back | S | `Purchases/Services/SupplierInvoiceService.php` | T-6 |
| T-8 | ✅ | back | M | `Services/AccountingSetupHealthService.php`, `Data/AccountingSetupIssue.php`, `Controllers/FinanceSettingsHealthController.php`, `Console/CheckAccountingSetupCommand.php`, `routes/api.php` | T-3, T-6 |
| T-9 | ✅ | back | S | `tests/**` (fixtures heredados) | T-3, T-4, T-5, T-6, T-7, T-8 |
| T-10 | ✅ | front | S | `modules/finance/utils/accountRules.js`, `services/financeSettingsApi.js`, `stores/financeSettingsStore.js` | T-8 |
| T-11 | ✅ | front | M | `components/settings/ConceptFormDrawer.vue`, `components/settings/ConceptsTab.vue` | T-10 |
| T-12 | ✅ | front | S | `components/settings/FinancialAccountsTab.vue`, `components/settings/FinancialAccountFormDrawer.vue` | T-10 |
| T-13 | ✅ | front | S | `modules/purchases/components/invoices/SupplierInvoiceForm.vue` | T-6 |
| T-14 | ✅ | docs | S | `.agents/rules/finance.md`, `.claude/specs/README.md`, `README.md` | T-1 … T-13 |

---
## Detalle de Tareas

### T-1 — Value object de códigos y helpers de enums
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** —

**Archivos clave:**
- `back-laravel/app/Modules/Finance/Support/AccountCode.php` (nuevo)
- `back-laravel/app/Modules/Finance/Enums/SystemConcept.php`
- `back-laravel/app/Modules/Finance/Enums/ConceptDirection.php`
- `back-laravel/app/Modules/Finance/Enums/FinancialAccountType.php`
- `back-laravel/tests/Unit/Modules/Finance/AccountCodeTest.php` (nuevo)
- `back-laravel/tests/Unit/Modules/Finance/ConceptDirectionTest.php` (nuevo)

**Descripción:**
1. Leer primero los tres enums y respetar sus convenciones (`declare(strict_types=1)`, `match`).
2. Crear `AccountCode` según design §1.4:
   - `PATTERN = '/^\d{2,8}$/'`;
   - `isValid()`, `isValidChildOf()`, `levelFor()` y `parentOf()`.
3. `SystemConcept::defaultAccountCode(): string` con el mapeo de design §2.1.
4. `ConceptDirection::allowsAccount(FinancialAccount $account): bool` con la matriz de design §2.2.
5. `label(): string` en español en `ConceptDirection` (Ingreso, Egreso, Transferencia) y en `FinancialAccountType` (Activo, Pasivo, Patrimonio, Ingreso, Gasto).

**Tests:**
- `AccountCodeTest`:
  - `10` válido; `10.1`, `1`, `123456789` y `1a` inválidos;
  - `101` es hija válida de `10` y `20` no lo es;
  - una raíz con 3 dígitos es inválida;
  - `parentOf('6361', [...])` devuelve `636` (requirements US-5).
- `ConceptDirectionTest`: los 15 casos de la matriz §4.5, incluido `transfer` con `1212` (inválido) y con `103` (válido) (US-7).

**Criterios de done:**
- [x] `make test-filter FILTER=AccountCodeTest` y `make test-filter FILTER=ConceptDirectionTest` en verde.
- [x] `cd back-laravel && vendor/bin/pint --test` en verde.
- [x] `SystemConcept::cases()` tiene un código por defecto para cada caso; el `match` es exhaustivo.

**Riesgos / notas:** `allowsAccount` recibe el modelo y no solo el tipo, porque `transfer` necesita el `code`.

---

### T-2 — Catálogos de datos y `AccountingPlanSynchronizer`
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1

**Archivos clave:**
- `back-laravel/database/data/financial_accounts_pcge.php` (nuevo)
- `back-laravel/database/data/finance_concepts.php` (nuevo)
- `back-laravel/app/Modules/Finance/Support/AccountingPlanSynchronizer.php` (nuevo)
- `back-laravel/tests/Feature/Modules/Finance/AccountingPlanSynchronizerTest.php` (nuevo)

**Descripción:**
1. Transcribir el §4.1 de requirements a `financial_accounts_pcge.php` como lista ordenada por código, con `code`, `name` y `type`. **Sin** `parent` ni `is_postable` (design §1.3).
2. Transcribir el §4.3 a `finance_concepts.php`, con `code`, `name`, `direction` y `account_code`.
3. Implementar `AccountingPlanSynchronizer` según design §2.3 y §6:
   - `syncAccounts(array $catalog)`: usa `withTrashed()`, no restaura cuentas eliminadas, no pisa `name` ni `is_active`, y al final recalcula `is_postable` con un único `UPDATE`;
   - `assignConceptAccounts()`: cubre los conceptos de sistema y los operativos (`withTrashed()` y asignación solo si es `null`);
   - `relocateConceptsFromParents(array $fallbacks): int`;
   - `renameOrMerge(string $old, string $new, ?string $parentCode): void`;
   - `public const DEFAULT_LEAVES`.
4. Todas las operaciones públicas corren en `DB::transaction`. Cada cambio sobre datos existentes se registra con `Log::info|warning` en el canal por defecto.

**Tests:**
- `AccountingPlanSynchronizerTest`:
  - `syncAccounts` crea la jerarquía, con hojas imputables y niveles correctos;
  - al ejecutarse dos veces conserva los ids;
  - respeta un `name` editado;
  - no restaura una cuenta eliminada (US-1).
- `renameOrMerge`:
  - con el destino libre, renombra conservando el `id`;
  - con el destino ocupado, fusiona y mueve los conceptos y las hijas (US-2).
- `relocateConceptsFromParents` mueve un concepto de `65` a `659`.
- `assignConceptAccounts`:
  - no pisa una cuenta ya asignada;
  - no recrea un concepto operativo eliminado (US-3, US-4).

**Criterios de done:**
- [x] `make test-filter FILTER=AccountingPlanSynchronizerTest` en verde.
- [x] `vendor/bin/pint --test` y Larastan sin errores nuevos.
- [x] Cada `account_code` de `finance_concepts.php` existe en el catálogo y es una hoja (lo verifica un test).

**Riesgos / notas:**
- El índice único de `code` excluye las filas eliminadas (`SoftDeleteUniqueIndex`), así que **hay que** buscar con `withTrashed()` para no duplicar.
- `122` y `422` tienen el `type` de su elemento PCGE (nota del §4.1).

---

### T-3 — Seeders de producción y parámetro de negocio
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2

**Archivos clave:**
- `back-laravel/database/seeders/FinancialAccountSeeder.php` (nuevo)
- `back-laravel/database/seeders/FinanceConceptSeeder.php` (nuevo)
- `back-laravel/database/seeders/FinanceMastersSeeder.php`
- `back-laravel/database/seeders/FinanceDemoSeeder.php`
- `back-laravel/database/seeders/SystemParameterSeeder.php`
- `back-laravel/tests/Feature/Modules/Finance/FinanceAccountingSeedersTest.php` (nuevo)

**Descripción:**
1. Leer primero los tres seeders existentes y respetar sus convenciones.
2. `FinancialAccountSeeder::run()`: `app(AccountingPlanSynchronizer::class)->syncAccounts(require database_path('data/financial_accounts_pcge.php'))` y luego `relocateConceptsFromParents(DEFAULT_LEAVES)`.
3. `FinanceConceptSeeder::run()`:
   - `updateOrCreate` de los conceptos de sistema (`code`, `name` solo al crear, `direction`, `is_system`). Conserva el comportamiento actual de `seedFinanceConcepts()`;
   - luego `assignConceptAccounts()`.
4. `FinanceMastersSeeder`:
   - eliminar `seedFinancialAccounts()` y `seedFinanceConcepts()`;
   - al final de `run()`, `$this->call([FinancialAccountSeeder::class, FinanceConceptSeeder::class])`.
5. `FinanceDemoSeeder`: eliminar `seedAccountingPlan()` y su llamada.
6. `SystemParameterSeeder`: agregar `finance.require_concept_account` (design §7), sin pisar el `value` existente.

**Tests:**
- `FinanceAccountingSeedersTest`, después de `$this->seed(DatabaseSeeder::class)` en entorno no demo:
  - existen todas las cuentas del §4.1;
  - ninguna cuenta tiene punto en el código;
  - ningún padre es imputable;
  - los 12 conceptos de sistema tienen la cuenta de `defaultAccountCode()`;
  - los 30 conceptos operativos existen con su cuenta;
  - ejecutar el seeder de nuevo no cambia el snapshot `(id, code, financial_account_id)`;
  - un concepto de sistema reasignado a `1419` se conserva;
  - el parámetro existe con `false` (US-1, US-3, US-4, US-9).

**Criterios de done:**
- [x] `make test-filter FILTER=FinanceAccountingSeedersTest` en verde.
- [x] `php artisan migrate:fresh --seed` sin errores, tanto en demo como en no demo.
- [x] `vendor/bin/pint --test` en verde.

**Riesgos / notas:** `FinanceDemoSeeder` puede usar cuentas `12.1` o `65.1` en otros métodos; buscar con `grep -n "'[0-9]*\.[0-9]'" database/seeders` y migrarlas a los códigos PCGE.

---

### T-4 — Migración de normalización de códigos heredados
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-2

**Archivos clave:**
- `back-laravel/database/migrations/2026_10_21_000001_normalize_financial_account_codes.php` (nuevo)
- `back-laravel/tests/Feature/Modules/Finance/NormalizeFinancialAccountCodesMigrationTest.php` (nuevo)

**Descripción:**
1. Crear la migración de datos de design §6:
   - `RENAMES`;
   - guard con `Schema::hasTable`;
   - `syncAccounts` → `renameOrMerge` → `syncAccounts` → `relocateConceptsFromParents`;
   - `down()` vacío con un comentario.
2. Confirmar que no existe otra migración con el prefijo `2026_10_21_000001`; si existe, usar el siguiente correlativo libre.

**Tests:**
- `NormalizeFinancialAccountCodesMigrationTest`:
  - siembra a mano (con `DB::table`) los códigos `10`, `10.1`, `10.4`, `65`, `65.1`, `65.2`, `70`, `70.1` y conceptos apuntando a `10.4`, `65.1` y `65`;
  - ejecuta `(require <migración>)->up()`.
- Verifica:
  - mismos `id` con los nuevos códigos;
  - `65.1` → `636` con padre `63`;
  - el concepto de `10.4` termina en `1041` y el de `65` en `659`;
  - sin códigos con punto;
  - una segunda ejecución no cambia nada;
  - con `636` ya existente, se fusiona y `65.1` queda eliminado lógicamente;
  - sobre una base vacía no falla (US-2).

**Criterios de done:**
- [x] `make test-filter FILTER=NormalizeFinancialAccountCodesMigrationTest` en verde.
- [ ] `php artisan migrate` sobre una base con los datos del seeder anterior (checkout de `HEAD~` + seed, luego volver a la rama) deja `finance:check-accounting-setup` en `0` una vez terminado T-8.

**Riesgos / notas:** la migración usa el catálogo y el synchronizer de la versión actual del código. Si en el futuro cambia el catálogo, la migración seguirá siendo correcta porque es idempotente.

---

### T-5 — Reglas de jerarquía y protección en `FinancialAccountService`
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1

**Archivos clave:**
- `back-laravel/app/Modules/Finance/Services/FinancialAccountService.php`
- `back-laravel/app/Modules/Finance/Requests/FinancialAccountRequest.php`
- `back-laravel/app/Modules/Finance/Models/FinancialAccount.php`
- `back-laravel/tests/Feature/Modules/Finance/FinancialAccountsAndConceptsApiTest.php`

**Descripción:**
1. Leer primero el servicio y el request y respetar sus convenciones (`FinanceRuleException($msg, $field)`).
2. Request: `code` con `regex:` + `AccountCode::PATTERN` y su mensaje; quitar `level` de `rules()` y `toAttributes()` (design §3.1).
3. Servicio: aplicar todas las reglas de la tabla de design §2.4, con los mensajes exactos:
   - prefijo;
   - raíz de 2 dígitos;
   - padre con conceptos (sin tocar `is_postable`);
   - `lockForUpdate` del padre;
   - restaurar `is_postable` del padre anterior al mover;
   - cambio de código con hijas;
   - desactivar con conceptos o con hijas;
   - `is_postable` con hijas;
   - `level` calculado.
4. Modelo: `scopePostable`.

**Tests (en `FinancialAccountsAndConceptsApiTest`):**
- 422 en `code` para `10.1`, para una raíz `101` y para una hija `201` bajo `10` (US-5).
- 422 en `parent_id` al crear una hija de `659` si tiene un concepto, y `659` sigue imputable (US-6).
- 422 al cambiar el código de una cuenta con hijas.
- 422 en `is_active`, con conceptos y con hijas activas.
- 422 en `is_postable` con hijas.
- Al mover la única hija de una cuenta, el padre anterior vuelve a ser imputable.
- `level` enviado en el body se ignora.

**Criterios de done:**
- [x] `make test-filter FILTER=FinancialAccountsAndConceptsApiTest` en verde.
- [x] `vendor/bin/pint --test` en verde.

**Riesgos / notas:** actualizar en el mismo test los fixtures que usan `10.1`/`10.4` (ahora `101`/`104`) para que el archivo quede en verde en esta tarea.

---

### T-6 — Validación de imputación y filtros en conceptos
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-1, T-3

**Archivos clave:**
- `back-laravel/app/Modules/Finance/Services/FinanceConceptService.php`
- `back-laravel/app/Modules/Finance/Controllers/FinanceConceptController.php`
- `back-laravel/app/Modules/Finance/Models/FinanceConcept.php`
- `back-laravel/app/Modules/Finance/Resources/FinancialAccountResource.php`
- `back-laravel/tests/Feature/Modules/Finance/FinancialAccountsAndConceptsApiTest.php`

**Descripción:**
1. Leer primero el servicio, el controller y el resource.
2. Reescribir `validateFinancialAccount()` según design §2.5:
   - obligatoriedad según `SystemParameterService::boolean('finance.require_concept_account')`, inyectado por constructor;
   - cuenta activa e imputable;
   - `ConceptDirection::allowsAccount()` con el mensaje que usa `label()`.
3. Scopes `scopeWithoutAccount` y `scopeAccountType` en `FinanceConcept` (design §1.2).
4. `paginate()` y `options()` aceptan `hasAccount` y `accountType`; el controller lee `has_account` y `account_type` (design §3.3).
5. `FinancialAccountResource` expone `type` (valor del enum) si no lo hace ya.

**Tests:**
- Matriz §4.5 vía API: un caso aceptado y uno rechazado por dirección; ingreso con `4511` y `501` aceptados; `transfer` con `1212` rechazado (US-7).
- Con el parámetro activo (actualizado con `SystemParameterService::update`), un concepto sin cuenta → 422 (US-9).
- Un concepto de sistema permite cambiar `financial_account_id` (US-3).
- `GET /concepts?has_account=0` y `GET /concepts/options?direction=expense&account_type=expense` filtran bien (US-11, US-8).

**Criterios de done:**
- [x] `make test-filter FILTER=FinancialAccountsAndConceptsApiTest` en verde.
- [x] `vendor/bin/pint --test` en verde.

**Riesgos / notas:** si `SystemParameterService` cachea los valores, limpiar el caché en el test después de cambiar el parámetro.

---

### T-7 — Gastos de proveedor imputados a gasto
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-6

**Archivos clave:**
- `back-laravel/app/Modules/Purchases/Services/SupplierInvoiceService.php`
- `back-laravel/tests/Feature/Modules/Purchases/SupplierInvoicesApiTest.php`

**Descripción:**
1. Leer primero `SupplierInvoiceService.php:425-450` (validación del concepto).
2. Agregar la regla de design §2.6. Inyectar `SystemParameterService` si no está.

**Tests:**
- Gasto con un concepto imputado a `4511` → 422 en `concept_id`.
- Gasto con `ELECTRICITY` (`6361`) → 201.
- Con el parámetro activo, un concepto sin cuenta → 422 (US-8, US-9).

**Criterios de done:**
- [x] `make test-filter FILTER=SupplierInvoicesApiTest` en verde.
- [x] `vendor/bin/pint --test` en verde.

**Riesgos / notas:** los fixtures del test que usan `OTHER_EXPENSE` siguen siendo válidos (`659` es de gasto).

---

### T-8 — Diagnóstico: servicio, endpoint y comando
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-3, T-6

**Archivos clave:**
- `back-laravel/app/Modules/Finance/Services/AccountingSetupHealthService.php` (nuevo)
- `back-laravel/app/Modules/Finance/Data/AccountingSetupIssue.php` (nuevo)
- `back-laravel/app/Modules/Finance/Controllers/FinanceSettingsHealthController.php` (nuevo)
- `back-laravel/app/Modules/Finance/Console/CheckAccountingSetupCommand.php` (nuevo)
- `back-laravel/app/Modules/Finance/Providers/FinanceServiceProvider.php`
- `back-laravel/routes/api.php`
- `back-laravel/tests/Feature/Modules/Finance/FinanceSettingsHealthApiTest.php` (nuevo)

**Descripción:**
1. Leer primero `VerifyBankBalancesCommand.php` y `FinanceServiceProvider.php` para seguir su patrón.
2. Implementar el servicio y el DTO según design §2.7: siete problemas, una consulta por problema y `items` limitado a 50.
3. Controller invocable según design §3.4: incluye `require_concept_account` en `data`.
4. Ruta `GET /settings/health` en el grupo `finance` con `permission:finance_settings.view` (design §3.5).
5. Comando `finance:check-accounting-setup {--json}` (design §2.8). Registrar el servicio como singleton y el comando en el provider.

**Tests:**
- `FinanceSettingsHealthApiTest`:
  - después del seeder: 200 con `status=ok`;
  - cada problema del §4.6, provocado con `DB::table()->update()`, aparece con su `code` y su severidad;
  - `concept_without_account` es `warning` con el parámetro en `false` y `error` con el parámetro en `true`;
  - 403 sin permiso y 401 sin token (US-10, regla `api-authorization.md`).
- Comando: `$this->artisan('finance:check-accounting-setup')->assertExitCode(0)` con datos sanos y `assertExitCode(1)` con un concepto en una cuenta no imputable.

**Criterios de done:**
- [x] `make test-filter FILTER=FinanceSettingsHealthApiTest` en verde.
- [ ] El tiempo de respuesta del endpoint con el seeder completo es < 300 ms en local.
- [x] `vendor/bin/pint --test` en verde.

**Riesgos / notas:** declarar la ruta antes de cualquier `/{id}` del mismo prefijo, para que no sea capturada por otra ruta.

---

### T-9 — Regresión de la suite completa
**Estado:** ✅ · **Subj:** back · **Complejidad:** S · **Depende de:** T-3, T-4, T-5, T-6, T-7, T-8

**Archivos clave:**
- `back-laravel/tests/**` (los que referencian códigos con punto)
- `back-laravel/database/factories/FinancialAccountFactory.php`, si existe

**Descripción:**
1. `grep -rnE "'(10|12|42|65|70|75)\.[0-9]'" back-laravel/tests back-laravel/database` y reemplazar por los códigos PCGE.
2. Revisar que la factory de cuentas genere códigos válidos: `fake()->unique()->numerify('9#')` para raíces y prefijo del padre para hijas.
3. Ejecutar la suite completa.

**Tests:**
- `make test` completo.

**Criterios de done:**
- [x] `make test` en verde.
- [x] `vendor/bin/pint --test` y Larastan (`make stan` o el comando del Makefile) en verde.

**Riesgos / notas:** los tests que crean cuentas de primer nivel imputables (`is_postable = true` con hijas) dependían del comportamiento anterior; ajustarlos a la regla nueva.

---

### T-10 — Reglas compartidas, API y store de diagnóstico (frontend)
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-8

**Archivos clave:**
- `front-vue/src/modules/finance/utils/accountRules.js` (nuevo)
- `front-vue/src/modules/finance/services/financeSettingsApi.js`
- `front-vue/src/modules/finance/stores/financeSettingsStore.js`
- `front-vue/src/tests/unit/accountRules.spec.js` (nuevo)

**Descripción:**
1. Crear `accountRules.js` según design §8.1.
2. Agregar `healthApi.get()` y exportarlo en el objeto por defecto, siguiendo el patrón de `conceptsApi`.
3. Store: `health` en el state, acción `fetchHealth(force)`, getter `requireConceptAccount`, y limpieza en el `reset` existente (design §8.5).

**Tests:**
- `accountRules.spec.js`: matriz §4.5 completa y casos de `isValidAccountCode` (raíz, hija, punto, longitud).

**Criterios de done:**
- [x] `cd front-vue && npm run lint` en verde.
- [x] `cd front-vue && npm run test:unit -- accountRules` en verde.

---

### T-11 — Conceptos: formulario, columna, filtro y alerta de diagnóstico
**Estado:** ✅ · **Subj:** front · **Complejidad:** M · **Depende de:** T-10

**Archivos clave:**
- `front-vue/src/modules/finance/components/settings/ConceptFormDrawer.vue`
- `front-vue/src/modules/finance/components/settings/ConceptsTab.vue`

**Descripción:**
1. Leer primero ambos componentes. **Conservar** el cambio en curso de `ConceptFormDrawer.vue`, la carga local `loadPostableAccounts` con `VAutocomplete`.
2. `ConceptFormDrawer` (design §8.2):
   - filtrar con `directionAllowsAccount`;
   - título `code - name`;
   - limpiar la cuenta si deja de ser válida al cambiar la dirección;
   - label con ` *` y sin `clearable` cuando `requireConceptAccount`.
3. `ConceptsTab` (design §8.3):
   - slot de columna con `code - name` o chip «Sin cuenta»;
   - filtro `has_account` en `#filters` (`VSelect`, `density="compact"`, `clearable`);
   - `VAlert` con los problemas y el botón «Ver sin cuenta»;
   - llamar a `financeStore.fetchHealth(true)` en `onMounted` y después de cada guardar o eliminar.

**Tests:**
- `src/tests/unit/uiGuardrails.spec.js` en verde, sin agregar excepciones.

**Criterios de done:**
- [x] `cd front-vue && npm run lint` y `npm run test:unit` en verde.
- [ ] Verificación manual (`make front-dev`):
  - un concepto `transfer` solo ofrece cuentas `10x`;
  - un concepto sin cuenta muestra el chip y la alerta;
  - el filtro «Sin cuenta» funciona (US-7, US-10, US-11).

**Riesgos / notas:** no usar `AppSelect`/`AppAutocomplete` (regla `frontend-ui.md`).

---

### T-12 — Plan de cuentas: árbol y formulario
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-10

**Archivos clave:**
- `front-vue/src/modules/finance/components/settings/FinancialAccountsTab.vue`
- `front-vue/src/modules/finance/components/settings/FinancialAccountFormDrawer.vue`

**Descripción:**
1. Leer primero ambos componentes.
2. Árbol o listado: mostrar `code - name` y el chip «Imputable» en las hojas (design §8.4).
3. Formulario:
   - `placeholder="6361"`;
   - regla `isValidAccountCode(code, parent?.code)` con el mensaje del backend;
   - quitar el campo `level` si existe;
   - mostrar los errores 422 de `parent_id`, `is_active` e `is_postable` con `useApiFormErrors`.

**Tests:**
- `uiGuardrails.spec.js` en verde.

**Criterios de done:**
- [x] `cd front-vue && npm run lint` y `npm run test:unit` en verde.
- [ ] Verificación manual:
  - crear la cuenta `6366` bajo `636` funciona;
  - crear la cuenta `701` bajo `636` muestra el error de prefijo;
  - crear una subcuenta bajo `659` muestra el error de conceptos (US-5, US-6).

---

### T-13 — Combo de concepto en gastos de proveedor
**Estado:** ✅ · **Subj:** front · **Complejidad:** S · **Depende de:** T-6

**Archivos clave:**
- `front-vue/src/modules/purchases/components/invoices/SupplierInvoiceForm.vue`

**Descripción:**
1. Leer primero `SupplierInvoiceForm.vue:100-120`.
2. Cambiar la carga a `conceptsApi.options({ direction: 'expense', account_type: 'expense' })` (design §8.6).

**Tests:**
- `cd front-vue && npm run test:unit` (los tests existentes del módulo de compras, si existen).

**Criterios de done:**
- [x] `cd front-vue && npm run lint` en verde.
- [ ] Verificación manual: el combo de gasto no muestra `LOAN_PAYMENT` ni `TAX_PAYMENT`, y sí muestra `ELECTRICITY` (US-8).

---

### T-14 — Reglas del repo, documentación y cierre
**Estado:** ✅ · **Subj:** docs · **Complejidad:** S · **Depende de:** T-1 … T-13

**Archivos clave:**
- `.agents/rules/finance.md`
- `.claude/specs/032-finance-accounting-setup/CLAUDE.md.delta.md`
- `.claude/specs/README.md`
- `README.md`

**Descripción:**
1. Mergear el contenido de `CLAUDE.md.delta.md` en `.agents/rules/finance.md`, en la sección «Plan de cuentas y conceptos».
2. `README.md`: documentar `php artisan finance:check-accounting-setup` en la sección de comandos.
3. Marcar el spec 032 como `Done` en `.claude/specs/README.md` y en las cabeceras de los cuatro archivos.
4. Correr `graphify update .`.

**Tests:**
- `make test` y `cd front-vue && npm run lint` finales.

**Criterios de done:**
- [x] Las reglas nuevas aparecen en `.agents/rules/finance.md`.
- [x] Después de `php artisan migrate:fresh --seed`, `php artisan finance:check-accounting-setup` sale con `0` y sin `warning`.
- [x] Grafo de graphify actualizado.
