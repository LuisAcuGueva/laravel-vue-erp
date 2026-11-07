# 032 · finance-accounting-setup — Design
> **Spec:** 032 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** alta · **Depende de specs:** 017, 018, 019, 020, 024, 028, 029
> **Status:** Done
> **Última actualización:** 2026-10-20
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
Este spec **no crea tablas ni modelos**. Completa los datos y endurece las reglas del módulo `Finance` creado en el spec 017.

**Backend** (`back-laravel/`)

| Acción | Archivo |
|---|---|
| nuevo | `database/data/financial_accounts_pcge.php` — catálogo del plan (§4.1 de requirements) |
| nuevo | `database/data/finance_concepts.php` — catálogo de conceptos operativos (§4.3) |
| nuevo | `database/seeders/FinancialAccountSeeder.php` |
| nuevo | `database/seeders/FinanceConceptSeeder.php` |
| modificar | `database/seeders/FinanceMastersSeeder.php` — delega en los dos seeders nuevos |
| modificar | `database/seeders/FinanceDemoSeeder.php` — elimina `seedAccountingPlan()` |
| modificar | `database/seeders/SystemParameterSeeder.php` — `finance.require_concept_account` |
| nuevo | `database/migrations/2026_10_21_000001_normalize_financial_account_codes.php` |
| modificar | `app/Modules/Finance/Enums/SystemConcept.php` — `defaultAccountCode()` |
| modificar | `app/Modules/Finance/Enums/FinancialAccountType.php` — `label()` |
| modificar | `app/Modules/Finance/Enums/ConceptDirection.php` — `allowsAccount()` (matriz §4.5) |
| nuevo | `app/Modules/Finance/Support/AccountCode.php` — validación y nivel del código PCGE |
| nuevo | `app/Modules/Finance/Support/AccountingPlanSynchronizer.php` — lógica compartida por seeder y migración |
| modificar | `app/Modules/Finance/Services/FinancialAccountService.php` |
| modificar | `app/Modules/Finance/Services/FinanceConceptService.php` |
| nuevo | `app/Modules/Finance/Services/AccountingSetupHealthService.php` |
| nuevo | `app/Modules/Finance/Data/AccountingSetupIssue.php` |
| modificar | `app/Modules/Finance/Requests/FinancialAccountRequest.php` |
| modificar | `app/Modules/Finance/Requests/FinanceConceptRequest.php` |
| nuevo | `app/Modules/Finance/Controllers/FinanceSettingsHealthController.php` |
| nuevo | `app/Modules/Finance/Console/CheckAccountingSetupCommand.php` |
| modificar | `app/Modules/Finance/Controllers/FinanceConceptController.php` — filtros `has_account`, `account_type` |
| modificar | `app/Modules/Finance/Providers/FinanceServiceProvider.php` — singleton y comando |
| modificar | `app/Modules/Purchases/Services/SupplierInvoiceService.php` — regla de US-8 |
| modificar | `routes/api.php` — `GET /finance/settings/health` |

**Frontend** (`front-vue/src/`)

| Acción | Archivo |
|---|---|
| modificar | `modules/finance/components/settings/ConceptsTab.vue` — columna, filtro y `VAlert` de diagnóstico |
| modificar | `modules/finance/components/settings/ConceptFormDrawer.vue` — matriz §4.5, obligatoriedad |
| modificar | `modules/finance/components/settings/FinancialAccountsTab.vue` — código delante del nombre, chip «Imputable» |
| modificar | `modules/finance/components/settings/FinancialAccountFormDrawer.vue` — placeholder y validación de prefijo |
| modificar | `modules/finance/services/financeSettingsApi.js` — `healthApi.get()` |
| nuevo | `modules/finance/utils/accountRules.js` — matriz §4.5 y validación de código (espejo del backend) |
| modificar | `modules/purchases/components/invoices/SupplierInvoiceForm.vue` — `account_type=expense` en el combo |

## 1. Backend — Modelo y Persistencia
No hay modelos nuevos. Se usan `FinancialAccount` y `FinanceConcept` (spec 017), con dos adiciones.

### 1.1 `FinancialAccount`
- Scope `scopePostable(Builder $q)`: `where('is_postable', true)`.
- `ancestorIds()`, `children()` y `concepts()` ya existen.

### 1.2 `FinanceConcept`
- Scope `scopeWithoutAccount(Builder $q)`: `whereNull('financial_account_id')`.
- Scope `scopeAccountType(Builder $q, FinancialAccountType|string|null $type)`:
  ```php
  $type === null ? $q : $q->where(fn ($w) => $w
      ->whereNull('financial_account_id')
      ->orWhereHas('financialAccount', fn ($a) => $a->where('type', $value)));
  ```
  Incluye los conceptos sin cuenta, porque US-8 los acepta mientras el parámetro está apagado. Con el parámetro activo, el servicio quita la rama `whereNull`.

### 1.3 Catálogos de datos
`database/data/financial_accounts_pcge.php` devuelve una lista ordenada (padres antes que hijas):

```php
return [
    ['code' => '10',   'name' => 'Efectivo y equivalentes de efectivo', 'type' => 'asset'],
    ['code' => '101',  'name' => 'Caja',                                'type' => 'asset'],
    ['code' => '102',  'name' => 'Fondos fijos',                        'type' => 'asset'],
    ['code' => '103',  'name' => 'Efectivo en tránsito',                'type' => 'asset'],
    ['code' => '104',  'name' => 'Cuentas corrientes en instituciones financieras', 'type' => 'asset'],
    ['code' => '1041', 'name' => 'Cuentas corrientes operativas',       'type' => 'asset'],
    // … resto del §4.1
];
```

- El padre **no se escribe**: se deriva como el código más largo de la lista que sea prefijo propio del código (`AccountCode::parentOf()`).
- `is_postable` tampoco: una cuenta es imputable si ningún otro código del catálogo la tiene como padre.
- Así el archivo no puede contradecirse.

`database/data/finance_concepts.php` devuelve `[['code', 'name', 'direction', 'account_code'], …]` con el §4.3.

### 1.4 `AccountCode` (value object estático)
```php
final class AccountCode
{
    public const PATTERN = '/^\d{2,8}$/';

    public static function isValid(string $code): bool;
    public static function isValidChildOf(string $code, ?string $parentCode): bool; // raíz: strlen === 2; hija: str_starts_with && strlen > padre
    public static function levelFor(?FinancialAccount $parent): int;              // padre?->level + 1 ?? 1
    /** @param list<string> $codes */
    public static function parentOf(string $code, array $codes): ?string;
}
```

## 2. Backend — Lógica de Negocio y Servicios

### 2.1 `SystemConcept::defaultAccountCode(): string`
```php
return match ($this) {
    self::SaleCollection, self::ReceivableCollection, self::SaleRefund => '1212',
    self::SupplierPayment => '4212',
    self::CashTransferIn, self::CashTransferOut,
    self::BankDeposit, self::BankWithdrawal,
    self::BankTransferIn, self::BankTransferOut => '103',
    self::CashOverage => '759',
    self::CashShortage => '659',
};
```
Es la única fuente del mapeo (invariante §4.7).

### 2.2 `ConceptDirection::allowsAccount(FinancialAccount $account): bool`
```php
return match ($this) {
    self::Income => $account->type !== FinancialAccountType::Expense,
    self::Expense => in_array($account->type, [FinancialAccountType::Asset, FinancialAccountType::Liability, FinancialAccountType::Expense], true),
    self::Transfer => $account->type === FinancialAccountType::Asset && str_starts_with($account->code, '10'),
};
```
Lo usan `FinanceConceptService` y el diagnóstico.

### 2.3 `AccountingPlanSynchronizer`
Clase sin estado, usada por `FinancialAccountSeeder` y por la migración. Corre dentro de `DB::transaction` y tiene tres métodos públicos.

**`syncAccounts(array $catalog): void`**
1. Ordena por `strlen(code)` y luego por `code`.
2. Para cada fila busca la cuenta por `code`, **incluidas las eliminadas lógicamente** (`withTrashed()`):
   - **Eliminada:** no la recrea ni la restaura. Respeta la decisión del usuario y la registra en log `info`.
   - **No existe:** `create([code, name, type, parent_id, level, is_postable, is_active => true])`.
   - **Existe:** `update([parent_id, level, type, is_postable])`. **No toca** `name` ni `is_active`.
3. Recalcula `is_postable` de todas las cuentas: `NOT EXISTS (hija activa no eliminada)`. Es un único `UPDATE … SET is_postable = …` con subconsulta.

**`relocateConceptsFromParents(array $fallbacks): int`**
Mueve los conceptos imputados a una cuenta no imputable a la hija por defecto (`['104' => '1041', '121' => '1212', …]`, del §4.4). Devuelve cuántos movió y registra cada uno en log `warning`.

**`assignConceptAccounts(): void`**
- Conceptos de sistema: `financial_account_id = id(SystemConcept::defaultAccountCode())` **solo** si es `null`.
- Conceptos operativos del catálogo:
  - el concepto existe, aunque esté eliminado (`withTrashed()->where('code')`): no se recrea y solo se completa `financial_account_id` si es `null` y no está eliminado;
  - no existe: se crea con `is_system = false`.

### 2.4 `FinancialAccountService` (cambios)
Las reglas nuevas lanzan `FinanceRuleException($mensaje, $campo)`, que ya se traduce a 422.

| Método | Regla nueva |
|---|---|
| `create` | Valida `AccountCode::isValidChildOf($code, $parent?->code)` → 422 en `code`. |
| `create` | Si el padre tiene conceptos (`$parent->concepts()->count() > 0`) → 422 en `parent_id` (US-6). **No** cambia `is_postable` del padre en ese caso. |
| `create` | Aplica `lockForUpdate()` sobre el padre. |
| `update` | Cambio de `parent_id` → misma validación de conceptos sobre el nuevo padre y validación de prefijo del `code` resultante. |
| `update` | Cambio de `parent_id` → el padre anterior vuelve a `is_postable = true` si se queda sin hijas activas. |
| `update` | Cambio de `code` con hijas → 422 en `code` («No se puede cambiar el código de una cuenta con subcuentas»). Evita romper el prefijo de los descendientes. |
| `update` | `is_active = false` con conceptos activos → 422 en `is_active`. |
| `update` | `is_active = false` con hijas activas → 422 en `is_active`. |
| `update` | `is_postable = true` con hijas → 422 en `is_postable`. Hoy se sobrescribe en silencio; ahora se informa. |
| `create` / `update` | `level` siempre lo calcula `AccountCode::levelFor()`. Se ignora si llega en `$attributes`. |

Mensajes:
- `"El código debe comenzar con el código de la cuenta padre ({code})."`
- `"Una cuenta principal debe tener 2 dígitos."`
- `"La cuenta {code} tiene {n} conceptos asociados. Reasígnalos antes de crear subcuentas."`
- `"No se puede desactivar la cuenta {code}: tiene {n} conceptos activos asociados."`
- `"No se puede desactivar la cuenta {code}: tiene subcuentas activas."`
- `"Una cuenta con subcuentas no puede ser imputable."`

### 2.5 `FinanceConceptService` (cambios)
`validateFinancialAccount(array $attributes)` se reescribe:
1. Si `financial_account_id` está vacío y `SystemParameterService::boolean('finance.require_concept_account')` es `true` → 422 `"La cuenta contable es obligatoria."`.
2. La cuenta debe existir, estar activa, no eliminada y ser imputable (reglas actuales).
3. `ConceptDirection::allowsAccount($account)`; si falla → 422 `"Una cuenta de tipo {type.label} no es válida para un concepto de {direction.label}."`.

Otros cambios:
- `paginate()` y `options()` reciben `?bool $hasAccount` y `FinancialAccountType|string|null $accountType`.
- Con `accountType` y el parámetro activo, se excluyen los conceptos sin cuenta.
- `ConceptDirection` y `FinancialAccountType` agregan `label()` en español para los mensajes.

### 2.6 `SupplierInvoiceService` (US-8)
En la validación de concepto actual (`SupplierInvoiceService.php:432-444`), después de comprobar que el concepto es de egreso, activo y no de sistema:

```php
$account = $concept->financialAccount;
if ($account === null && $this->parameters->boolean('finance.require_concept_account')) {
    throw new PurchasesRuleException('El concepto no tiene cuenta contable asignada.', 'concept_id');
}
if ($account !== null && $account->type !== FinancialAccountType::Expense) {
    throw new PurchasesRuleException('El concepto debe estar imputado a una cuenta de gasto.', 'concept_id');
}
```

### 2.7 `AccountingSetupHealthService`
```php
final class AccountingSetupHealthService
{
    /** @return array{status: 'ok'|'warning'|'error', issues: list<AccountingSetupIssue>} */
    public function check(): array;
}
```

`AccountingSetupIssue` es un DTO `readonly` con `code`, `severity`, `message`, `count` e `items` (`list<array{id, code, name}>`, máximo 50).

Cada problema del §4.6 sale de **una sola consulta**:

| Código | Consulta |
|---|---|
| `concept_without_account` | `FinanceConcept::active()->withoutAccount()` |
| `concept_account_not_postable` / `inactive` | join `finance_concepts` ↔ `financial_accounts` (incluye `deleted_at IS NOT NULL`) |
| `concept_account_type_mismatch` | carga los conceptos activos con cuenta (`with('financialAccount')`) y filtra en PHP con `allowsAccount()` |
| `parent_account_postable` | `is_postable = true AND EXISTS (hija activa)` |
| `account_code_format` | todas las cuentas con su `parent.code`, validadas con `AccountCode` |
| `system_concept_reassigned` | conceptos `is_system` comparados con `defaultAccountCode()` |

`status` es `error` si existe algún problema con severidad `error`, `warning` si solo hay advertencias, y `ok` si no hay problemas.

### 2.8 `CheckAccountingSetupCommand`
- Firma: `finance:check-accounting-setup {--json}`.
- Usa `AccountingSetupHealthService`. Imprime una tabla `Severidad | Código | Cantidad | Mensaje` y debajo los primeros 10 ítems de cada problema.
- Devuelve `self::FAILURE` si `status = error`; si no, `self::SUCCESS`.
- Con `--json` imprime el mismo payload del endpoint. Sirve para la CI de despliegue.
- Se registra en `FinanceServiceProvider::boot()` junto a `VerifyBankBalancesCommand`.

## 3. Backend — Presentation

### 3.1 `FinancialAccountRequest`
- `code`: `['required', 'string', 'regex:'.AccountCode::PATTERN, Rule::unique(...)->ignore($id)->whereNull('deleted_at')]`.
- Mensaje de `code.regex`: `"El código debe tener entre 2 y 8 dígitos, sin puntos ni espacios."`.
- `level` se elimina de `rules()` y de `toAttributes()`.
- La validación de prefijo vive en el servicio, porque necesita el padre cargado y bloqueado.

### 3.2 `FinanceConceptRequest`
Sin cambios de reglas. La obligatoriedad de la cuenta depende del parámetro y se valida en el servicio, igual que el resto de reglas de negocio de Finance.

### 3.3 `FinanceConceptController`
- `index` y `options` leen `has_account` (`'0'|'1'` → `?bool` con `$request->has()` + `boolean()`) y `account_type` (validado con `Rule::enum(FinancialAccountType::class)` en línea; si es inválido, se ignora).
- `FinanceConceptResource`: ya expone `financial_account`. Se agrega `financial_account.type` al `FinancialAccountResource` si no está, para que el frontend lo vea.

### 3.4 `FinanceSettingsHealthController`
```php
final class FinanceSettingsHealthController extends Controller
{
    use ApiResponse;

    public function __invoke(AccountingSetupHealthService $health, SystemParameterService $parameters): JsonResponse
    {
        return $this->successResponse(data: $health->check() + ['require_concept_account' => $parameters->boolean('finance.require_concept_account')]);
    }
}
```

### 3.5 Rutas (`routes/api.php`, grupo `finance`)
```php
Route::get('/settings/health', FinanceSettingsHealthController::class)
    ->middleware('permission:finance_settings.view');
```
Se declara junto a las rutas de `financial-accounts` y `concepts`.

## 4. Contrato API

| Método | Ruta | Permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `/api/v1/finance/settings/health` | `finance_settings.view` | — | 200 `{ data: { status, issues[] } }` | 401, 403 |
| GET | `/api/v1/finance/concepts` | `finance_settings.view` | `?search&direction&is_active&has_account=0\|1&account_type=expense&page&per_page` | 200 paginado (sin cambios de forma) | 401, 403 |
| GET | `/api/v1/finance/concepts/options` | autenticado | `?direction&include_system&account_type` | 200 `{ data: [...] }` | 401 |
| POST/PUT | `/api/v1/finance/concepts[/{id}]` | `finance_settings.manage` | sin cambios | 200/201 | 422 `financial_account_id` (obligatoria, tipo incompatible) |
| POST/PUT | `/api/v1/finance/financial-accounts[/{id}]` | `finance_settings.manage` | `level` ya no se acepta | 200/201 | 422 `code` (formato, prefijo, código con hijas), `parent_id` (padre con conceptos), `is_active`, `is_postable` |
| POST/PUT | `/api/v1/purchases/supplier-invoices[/{id}]` | spec 024 | sin cambios | sin cambios | 422 `concept_id` (cuenta no gasto, sin cuenta con parámetro) |

**Ejemplo de diagnóstico**
```json
{
  "success": true,
  "data": {
    "status": "warning",
    "require_concept_account": false,
    "issues": [
      {
        "code": "concept_without_account",
        "severity": "warning",
        "message": "Hay 2 conceptos activos sin cuenta contable.",
        "count": 2,
        "items": [
          { "id": "9b1c…", "code": "COURIER", "name": "Courier" },
          { "id": "9b1d…", "code": "DONATION", "name": "Donaciones" }
        ]
      }
    ]
  }
}
```

**Ejemplo de 422 (subcuenta bajo un padre con conceptos)**
```json
{
  "message": "La cuenta 659 tiene 2 conceptos asociados. Reasígnalos antes de crear subcuentas.",
  "errors": { "parent_id": ["La cuenta 659 tiene 2 conceptos asociados. Reasígnalos antes de crear subcuentas."] }
}
```

## 5. Permisos y seguridad
- **No se crean permisos nuevos.** El diagnóstico usa `finance_settings.view`, que ya tienen `admin` y `manager` (`RolePermissionSeeder`).
- En el frontend, el `VAlert` de diagnóstico se muestra solo con `can('finance_settings.view')`. La pestaña ya lo exige.
- El diagnóstico solo expone `id`, `code` y `name` de conceptos y cuentas, sin montos.
- Tests 403: `FinanceSettingsHealthApiTest` con un usuario sin `finance_settings.view` (regla `api-authorization.md`).

## 6. Migraciones
**No hay cambios de esquema.** Se agrega una migración **de datos**, idempotente y sin `down()` destructivo.

`database/migrations/2026_10_21_000001_normalize_financial_account_codes.php`
```php
return new class extends Migration
{
    /** @var array<string, array{code: string, parent: ?string}> */
    private const RENAMES = [
        '10.1' => ['code' => '101', 'parent' => '10'],
        '10.4' => ['code' => '104', 'parent' => '10'],
        '12.1' => ['code' => '121', 'parent' => '12'],
        '42.1' => ['code' => '421', 'parent' => '42'],
        '65.1' => ['code' => '636', 'parent' => '63'],
        '65.2' => ['code' => '635', 'parent' => '63'],
        '70.1' => ['code' => '701', 'parent' => '70'],
        '70.4' => ['code' => '704', 'parent' => '70'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('financial_accounts')) {
            return;
        }

        DB::transaction(function () {
            $sync = app(AccountingPlanSynchronizer::class);

            // 1. Asegura las raíces destino (p. ej. 63) antes de re-parentar.
            $sync->syncAccounts(require database_path('data/financial_accounts_pcge.php'));

            // 2. Renombra conservando id; si el destino ya existe, fusiona.
            foreach (self::RENAMES as $old => $target) {
                $sync->renameOrMerge($old, $target['code'], $target['parent']);
            }

            // 3. Recalcula jerarquía y mueve conceptos que quedaron en cuentas padre.
            $sync->syncAccounts(require database_path('data/financial_accounts_pcge.php'));
            $sync->relocateConceptsFromParents(AccountingPlanSynchronizer::DEFAULT_LEAVES);
        });
    }

    public function down(): void
    {
        // Migración de datos irreversible: los códigos con punto no son válidos desde este spec.
    }
};
```

**`AccountingPlanSynchronizer::renameOrMerge($old, $new, $parentCode)`**
- Si no existe `$old`, no hace nada.
- Si existe `$old` y no existe `$new`: `update(['code' => $new, 'parent_id' => id($parentCode)])`.
- Si existen ambos: mueve `finance_concepts.financial_account_id` y `financial_accounts.parent_id` de `$old` a `$new`, y aplica soft delete a `$old`.

**`DEFAULT_LEAVES`** es una constante pública con el mapeo padre → hoja del §4.4:
```php
['10' => '101', '104' => '1041', '12' => '1212', '121' => '1212', '42' => '4212', '421' => '4212',
 '63' => '6391', '635' => '6352', '636' => '6361', '65' => '659', '70' => '7011', '701' => '7011', '75' => '759']
```

Usar la misma clase en la migración y en el seeder garantiza que ambos caminos dejen el mismo estado.

## 7. Configuración e infraestructura
- **Parámetro nuevo** en `SystemParameterSeeder` (regla `business-parameters.md`):
  ```php
  [
      'group_key' => 'finance',
      'item_key' => 'require_concept_account',
      'label' => 'Exigir cuenta contable en conceptos',
      'description' => 'Obliga a imputar cada concepto de ingreso o egreso a una cuenta imputable del plan de cuentas.',
      'value_type' => ParameterValueType::Boolean,
      'default_value' => false,
      'is_editable' => true,
      'is_active' => true,
  ],
  ```
- **Orden de seeders:** `FinanceMastersSeeder::run()` llama a `seedCurrencies`, `seedBanks` y `seedPaymentMethods`, y luego a `$this->call([FinancialAccountSeeder::class, FinanceConceptSeeder::class])`. `DatabaseSeeder` no cambia.
- **Sin variables de entorno nuevas.**
- **Opcional (CI):** paso `php artisan finance:check-accounting-setup` después de `db:seed` en el pipeline de despliegue. Solo se documenta en `README.md`; no se agrega a `Makefile`.

## 8. Frontend

### 8.1 `modules/finance/utils/accountRules.js`
```js
export const ACCOUNT_CODE_PATTERN = /^\d{2,8}$/

export const isValidAccountCode = (code, parentCode = null) => {
  if (!ACCOUNT_CODE_PATTERN.test(code ?? '')) return false
  return parentCode ? code.startsWith(parentCode) && code.length > parentCode.length : code.length === 2
}

// Matriz §4.5 — espejo de ConceptDirection::allowsAccount()
export const directionAllowsAccount = (direction, account) => {
  const type = account?.type?.value ?? account?.type
  if (direction === 'income') return type !== 'expense'
  if (direction === 'expense') return ['asset', 'liability', 'expense'].includes(type)
  if (direction === 'transfer') return type === 'asset' && String(account.code).startsWith('10')
  return false
}
```
Tiene una prueba unitaria `src/tests/unit/accountRules.spec.js`.

### 8.2 `ConceptFormDrawer.vue`
- Mantiene la carga local de cuentas imputables que ya está en curso en el working tree (`loadPostableAccounts`).
- `filteredAccountOptions` = `postableAccounts.filter(acc => directionAllowsAccount(form.direction, acc))`, con `title: \`${acc.code} - ${acc.name}\``.
- Si cambia la dirección y la cuenta elegida deja de ser válida, `form.financial_account_id = null`.
- Label: `Cuenta contable${requireAccount ? ' *' : ''}`. `requireAccount` sale de la respuesta de diagnóstico, cacheada en el store (ver 8.5). `clearable` solo si no es obligatoria.

### 8.3 `ConceptsTab.vue`
- Columna `CUENTA CONTABLE` con slot `item.financial_account`:
  - con cuenta: `{{ code }} - {{ name }}`;
  - sin cuenta: `VChip color="warning" size="small"` «Sin cuenta».
- Filtro en `#filters`: `VSelect` «Cuenta contable» (`density="compact"`, `clearable`) con `[{ title: 'Con cuenta', value: 1 }, { title: 'Sin cuenta', value: 0 }]` → `has_account`.
- Encima de la tabla, un `VAlert` (`type` = `warning` | `error` según `status`, `variant="tonal"`) cuando `health.status !== 'ok'`:
  - una línea por problema;
  - botón «Ver sin cuenta» que aplica el filtro `has_account=0`.
- La alerta se refresca después de cada crear, actualizar o eliminar.

### 8.4 `FinancialAccountsTab.vue` y `FinancialAccountFormDrawer.vue`
- El árbol muestra `code - name`, y `VChip` «Imputable» (`color="success"`, `size="x-small"`) en las hojas.
- Formulario:
  - `VTextField` «Código *» con `placeholder="6361"`;
  - regla local `isValidAccountCode(code, parent?.code)` con el mensaje del backend;
  - el campo `level` se elimina si existía.

### 8.5 Store y API
- `financeSettingsApi.js`: `export const healthApi = { get: () => $api('/v1/finance/settings/health') }`.
- `financeSettingsStore`:
  - `state.health = null`;
  - acción `fetchHealth(force = false)`;
  - getter `requireConceptAccount` = `health?.require_concept_account ?? false`.
- El endpoint de diagnóstico incluye `data.require_concept_account: bool` para que el frontend no dependa del módulo de Configuración.

### 8.6 `SupplierInvoiceForm.vue`
`conceptsApi.options({ direction: 'expense', account_type: 'expense' })`.

### 8.7 MSW
Se agrega un handler de `GET /v1/finance/settings/health` en los mocks de finanzas, si el módulo los tiene. Si no, no se crean.

## 9. Testing
Todos en `back-laravel/tests/Feature/Modules/Finance/` salvo indicación. Se ejecutan con `make test-filter FILTER=<Test>`.

| Test | Casos |
|---|---|
| `FinanceAccountingSeedersTest` (nuevo) | El seeder crea todas las cuentas del catálogo. Solo las hojas son imputables. Niveles correctos. Doble ejecución sin cambios (snapshot de ids). No pisa `name` ni `is_active` editados. No restaura una cuenta eliminada. Los 12 conceptos de sistema tienen la cuenta de `defaultAccountCode()`. Un concepto de sistema reasignado no se revierte. Un concepto operativo eliminado no se recrea. `finance:check-accounting-setup` sale con 0. |
| `NormalizeFinancialAccountCodesMigrationTest` (nuevo) | Siembra los códigos heredados (`10.1`, `65.1`, …) con conceptos, ejecuta la migración (`$this->artisan('migrate', ['--path' => ...])` o instancia la clase anónima con `require`) y verifica: mismo `id`, nuevo `code`, re-parent `65.1→636` bajo `63`, conceptos movidos de padres a hojas, fusión cuando el destino ya existía, ejecución sobre base vacía. |
| `FinancialAccountsAndConceptsApiTest` (ampliado) | Actualiza los fixtures `10.1`/`10.4` → `101`/`104`. 422: código con punto, raíz de 3 dígitos, hija sin prefijo, subcuenta bajo padre con conceptos (y el padre sigue imputable), cambio de código con hijas, desactivar con conceptos, desactivar con hijas activas, `is_postable` con hijas. Matriz §4.5 completa: un caso válido y uno inválido por dirección, incluido `transfer` fuera de `10`. Ingreso → `4511` y `501` aceptados. Parámetro activo: concepto sin cuenta → 422. Filtros `has_account` y `account_type`. |
| `FinanceSettingsHealthApiTest` (nuevo) | 200 con `status=ok` tras el seeder. Detecta cada problema del §4.6 (preparando datos inconsistentes con `DB::table()` para saltar el servicio). Severidad de `concept_without_account` según el parámetro. 403 sin permiso. 401 sin token. |
| `tests/Feature/Modules/Purchases/SupplierInvoicesApiTest` (ampliado) | Gasto con concepto imputado a `4511` → 422 `concept_id`. Con concepto imputado a `6361` → 201. Concepto sin cuenta con parámetro activo → 422. |
| `tests/Unit/Modules/Finance/AccountCodeTest` (nuevo) | `isValid`, `isValidChildOf`, `parentOf`. |
| Frontend `src/tests/unit/accountRules.spec.js` (nuevo) | Matriz y validación de códigos. |
| Frontend `uiGuardrails.spec.js` y `routeMeta.spec.js` | Sin excepciones nuevas. |

Fixtures: los tests que hoy dependen de `FinanceMastersSeeder` (`SetsUpPayables`, `MoneySettlementServiceTest`, …) siguen funcionando, porque los conceptos de sistema conservan su `code`. Hay que revisar los que buscan cuentas por `10.1`: `grep -rn "'10\.\|'65\.\|'70\.\|'12\.\|'42\." back-laravel/tests`.

## 10. Decisiones Rechazadas
| Alternativa | Motivo del rechazo |
|---|---|
| Columna `nature` (deudora/acreedora) en `financial_accounts` | Sin asientos no aporta nada. Se decide en el spec de Contabilidad. |
| Cargar el PCGE 2019 completo (~2 000 cuentas) | Satura el combo y el árbol, y la mayoría no se usa. El resumido cubre todos los conceptos y se amplía a mano. |
| Guardar `parent_code` e `is_postable` en el archivo de catálogo | Duplica información derivable y permite inconsistencias. Se derivan del prefijo. |
| Campo `applies_to_invoices` en `finance_concepts` para filtrar gastos | Equivale a «cuenta de tipo gasto». Una regla derivada evita otra columna que mantener. |
| Hacer la cuenta obligatoria con `required` en el FormRequest | Rompería instalaciones sin plan validado. Se controla con un parámetro de negocio (`business-parameters.md`). |
| Cuentas puente distintas para cada tipo de transferencia | Complica la conciliación sin beneficio. La `103` única permite el control «saldo de transferencias aprobadas = 0». |
| Que el seeder sobrescriba la cuenta de los conceptos de sistema | Revertiría decisiones del contador en cada despliegue. Se asigna solo si es `null` y la diferencia se informa como `warning` en el diagnóstico. |
| Migración con `down()` que restaure los códigos con punto | Esos códigos dejan de ser válidos; volver atrás produciría datos que el propio sistema rechaza. |
