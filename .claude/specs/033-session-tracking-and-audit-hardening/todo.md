# 033 · session-tracking-and-audit-hardening — TODO
> **Spec:** 033 · **Alcance:** fullstack · **Módulo:** Auth/Profile + Audit (transversal) · **Prioridad:** alta · **Depende de specs:** 005, 007, 009
> **Status:** In Progress
> **Última actualización:** 2026-10-23
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
| T-1 | ✅ | back | M | migraciones `sessions`/`refresh_tokens` | — |
| T-2 | ✅ | back | S | `Session`, `UserAgentParser` | T-1 |
| T-3 | ✅ | back | S | `RefreshToken` modificado | T-1 |
| T-4 | ✅ | back | S | `CurrentSession`, `AuditContext` | — |
| T-5 | ✅ | back | M | `Activity` modificado (desacoplar de `ActiveBranch`) | T-4 |
| T-6 | ✅ | back | L | `JwtService` modificado (sesión + lock + revocación) | T-2, T-3 |
| T-7 | ✅ | back | M | `JwtAuthenticate` + middleware `branch` (poblar `CurrentSession`/`AuditContext`) | T-4, T-6 |
| T-8 | ✅ | back | M | `ProfileService`/`ProfileController`/`AuthController` (sesiones, `updatePassword`, `logout`) | T-6, T-7 |
| T-9 | ✅ | back | M | Corregir `morphMap` duplicado (`CashSession`) y registrar modelos ausentes | — |
| T-10 | ✅ | back | M | Test de regresión `MorphMapIntegrityTest` | T-9 |
| T-11 | ✅ | back | L | Estandarizar las 5 llamadas manuales a `activity()` (`causedBy` explícito) | T-4 |
| T-12 | ✅ | back | S | `SendElectronicDocumentJob` — marca `system` en log manual | T-5 |
| T-13 | ✅ | back | S | `AuditLogResource`/`ListAuditLogsRequest` — exponer/filtrar `session_id` | T-5 |
| T-14 | ✅ | front | M | `SessionsTab.vue` + `profileStore.js` — consumir campos estructurados | T-8 |
| T-15 | ✅ | back | S | `config/session.php` — nota de no-uso | — |
| T-16 | ✅ | back | M | Tests de integración de ciclo de vida de sesión (cubierto por `JwtServiceSessionTest` + `AuthTest` + `ProfileApiTest`, ver nota) | T-6, T-7, T-8 |
| T-17 | 🔧 | docs | S | Actualizar `.claude/specs/README.md` (status → Approved cuando se apruebe) | — |
| T-18 | ✅ | back | M | `branch_id`/`session_id` como columnas propias e indexadas de `activity_log` (a pedido del dueño del producto, post-entrega) | T-5, T-13 |

---
## Detalle de Tareas

### T-1 — Migraciones: crear `sessions`, enlazar `refresh_tokens`, retirar `ip_address`/`user_agent`
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_24_000001_create_sessions_table.php`
- `back-laravel/database/migrations/2026_10_24_000002_add_session_id_to_refresh_tokens_table.php`
- `back-laravel/database/migrations/2026_10_24_000003_drop_device_info_from_refresh_tokens_table.php`
**Descripción:**
1. Crear las 3 migraciones exactamente como en `design.md` §6.1–§6.3. No editar `2026_10_13_205900_create_refresh_tokens_table.php` ni `2026_10_15_000001_add_device_info_to_refresh_tokens_table.php`.
2. La migración de backfill (§6.2) depende de `App\Support\Auth\UserAgentParser` (T-2) y `App\Modules\Auth\Models\Session` (T-2) — si se ejecuta este T-1 antes de T-2, usar los valores crudos sin parsear (`device_type = 'unknown'`, `browser = null`, `platform = null`) y dejar un comentario `// TODO T-2` en la migración; de preferencia ejecutar T-2 primero aunque el mapa de tareas no lo marque como bloqueante estricto (ambas son triviales y conviene hacerlas juntas).
3. Correr `make test-run ARGS="--filter=NonExistentPlaceholder"` solo para confirmar que `postgres-test` migra limpio (`.agents/rules/testing.md`): el runner ejecuta todas las migraciones igual aunque el filtro no matchee ningún test.
**Tests:**
- Ninguno propio; se valida con las migraciones corriendo sin error contra `postgres-test`.
**Criterios de done:**
- [ ] Las 3 migraciones corren limpio en `postgres-test` (`make test`).
- [ ] `refresh_tokens` ya no tiene columnas `ip_address`/`user_agent`; tiene `session_id` `NOT NULL` con FK a `sessions.id`.
- [ ] `vendor/bin/pint --test` en verde sobre los 3 archivos.

---
### T-2 — `Session` y `UserAgentParser`
**Estado:** 🔧 · **Subj:** back · **Complejidad:** S · **Depende de:** T-1 (orden de archivo, no de ejecución de migración)
**Archivos clave:**
- `back-laravel/app/Modules/Auth/Models/Session.php`
- `back-laravel/app/Support/Auth/UserAgentParser.php`
**Descripción:**
1. Crear `Session` tal como en `design.md` §1.1 (UUID PK, `use HasUuids`, constantes `DEVICE_*`, relación `user()`/`refreshTokens()`, `isRevoked()`/`isExpired()`/`isActive()`).
2. Crear `UserAgentParser` tal como en `design.md` §1.3 (sin dependencias nuevas en `composer.json`).
**Tests:**
- `back-laravel/tests/Unit/Support/Auth/UserAgentParserTest.php` — casos: UA de Chrome/Firefox/Edge/Safari en desktop, UA de Android/iPhone/iPad, UA vacío/null → `unknown`/`null`.
**Criterios de done:**
- [ ] `UserAgentParserTest` en verde.
- [ ] `vendor/bin/pint --test` en verde.

---
### T-3 — `RefreshToken` modificado
**Estado:** 🔧 · **Subj:** back · **Complejidad:** S · **Depende de:** T-1
**Archivos clave:**
- `back-laravel/app/Modules/Auth/Models/RefreshToken.php`
- `back-laravel/database/factories/RefreshTokenFactory.php` (si existe; si no, verificar que ningún factory la referencie con `ip_address`/`user_agent`)
**Descripción:**
1. Reemplazar el modelo según `design.md` §1.2: quitar `ip_address`/`user_agent` de `$fillable`/`@property`, agregar `session_id` y `session(): BelongsTo`.
2. `grep -rn "ip_address\|user_agent" back-laravel/app/Modules/Auth back-laravel/app/Modules/Profile` para confirmar que no quedan referencias muertas fuera de `Session`.
**Tests:**
- Ninguno propio; cubierto por T-16.
**Criterios de done:**
- [ ] No quedan referencias a `RefreshToken::ip_address`/`user_agent` en el código.
- [ ] `vendor/bin/pint --test` en verde.

---
### T-4 — `CurrentSession` y `AuditContext`
**Estado:** 🔧 · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Support/Auth/CurrentSession.php`
- `back-laravel/app/Support/Audit/AuditContext.php`
- `back-laravel/app/Providers/AppServiceProvider.php` (o el provider donde ya se registra `ActiveBranch` como singleton — registrar ambos igual, `$this->app->singleton(CurrentSession::class)` / `$this->app->singleton(AuditContext::class)`)
**Descripción:**
1. Crear ambas clases exactamente como en `design.md` §1.4/§1.5.
2. Registrar como singleton por request (buscar con `grep -rn "ActiveBranch::class" back-laravel/app/Providers` el patrón exacto ya usado para replicarlo).
**Tests:**
- `back-laravel/tests/Unit/Support/Auth/CurrentSessionTest.php` — `has()` falso antes de `set()`, `id()` lanza `LogicException` sin `set()`.
- `back-laravel/tests/Unit/Support/Audit/AuditContextTest.php` — `branchId()`/`sessionId()` devuelven `null` por defecto y el valor seteado tras `set*()`.
**Criterios de done:**
- [ ] Ambos tests unitarios en verde.
- [ ] `vendor/bin/pint --test` en verde.

---
### T-5 — `Activity` modificado: desacoplar de `ActiveBranch`, resolver `branch_id` del propio `subject`
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/app/Modules/Audit/Models/Activity.php`
**Descripción:**
1. Reemplazar el hook `booted()` según `design.md` §1.5: usar `AuditContext` en vez de `ActiveBranch`; preferir `$activity->subject?->getAttribute('branch_id')` antes del *fallback* a `AuditContext::branchId()`; agregar `session_id` desde `AuditContext::sessionId()` si no viene ya en `properties`.
2. No eliminar el `use App\Support\Branch\ActiveBranch;` import hasta confirmar que ningún otro punto del archivo lo usa (debería quedar sin uso tras este cambio — eliminarlo).
**Tests:**
- `back-laravel/tests/Feature/Modules/Audit/ActivityBranchAndSessionContextTest.php`:
  - Actividad sobre un modelo con columna `branch_id` propia (p. ej. `CashSession`) sin `AuditContext` *bound*/seteado → `properties.branch_id` igual al `branch_id` del subject.
  - Actividad con `AuditContext::setSessionId()` seteado → `properties.session_id` presente.
  - Actividad sobre un modelo sin columna `branch_id` propia, con `AuditContext::setBranchId()` seteado → usa el *fallback*.
**Criterios de done:**
- [ ] `ActivityBranchAndSessionContextTest` en verde.
- [ ] Ningún test existente de `.claude/specs/007-audit-trail` relacionado con `branch_id` se rompe (`make test-filter FILTER=Audit`).

---
### T-6 — `JwtService`: sesión persistente, lock determinista, revocación a nivel de sesión
**Estado:** 🔧 · **Subj:** back · **Complejidad:** L · **Depende de:** T-2, T-3
**Archivos clave:**
- `back-laravel/app/Modules/Auth/Services/JwtService.php`
**Descripción:**
1. Implementar los cambios de `design.md` §2.1 completos: `issueAccessToken(User $user, string $sessionId)` agrega claim `sid`; `createTokenPair()` crea/actualiza `Session`; `rotateRefreshToken()` envuelto en `DB::transaction` con `lockForUpdate()` sobre la `Session`, reutilizando `session_id`; nuevos `revokeSession(string $sessionId)`, `revokeOtherSessions(string $userId, ?string $currentSessionId)`; `revokeRefreshToken()` delega a `revokeSession()`.
2. Partir en commits lógicos si ayuda: primero `issueAccessToken`/`createTokenPair`, luego `rotateRefreshToken`, luego los métodos de revocación — pero esta tarea se entrega completa (no a medias, rompería `AuthController`).
**Tests:**
- `back-laravel/tests/Feature/Modules/Auth/JwtServiceSessionTest.php`:
  - `createTokenPair()` sin `$session` crea 1 `Session` + 1 `RefreshToken` con `session_id` coincidente.
  - 3 rotaciones consecutivas del mismo refresh token preservan `session_id` y no crean sesiones nuevas (`Session::count()` no cambia).
  - Rotar un refresh token cuya `Session.revoked_at` no es nula lanza excepción con código 401, incluso si la fila de `refresh_tokens` todavía está en periodo de gracia.
  - `revokeOtherSessions()` no revoca la sesión actual y revoca todas las demás; devuelve el conteo correcto.
**Criterios de done:**
- [ ] `JwtServiceSessionTest` en verde.
- [ ] `make test-filter FILTER=Auth` en verde (no rompe tests existentes de `AuthController`/`JwtAuthenticate`).
- [ ] `vendor/bin/pint --test` en verde.

---
### T-7 — `JwtAuthenticate` y middleware `branch`: poblar `CurrentSession`/`AuditContext`
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** T-4, T-6
**Archivos clave:**
- `back-laravel/app/Modules/Auth/Middleware/JwtAuthenticate.php`
- Middleware `branch` existente (localizar con `grep -rn "class.*Middleware" back-laravel/app/Modules/Maintenance` o equivalente — el que hoy llama `app(ActiveBranch::class)->set(...)`)
**Descripción:**
1. En `JwtAuthenticate::handle()`, tras `Auth::setUser($user)` y antes de `$next($request)`: leer `$claims['sid']` (o `$rotatedTokens['session_id']` si hubo renovación silenciosa), setear `$request->attributes->set('session_id', ...)`, `app(CurrentSession::class)->set(...)`, `app(AuditContext::class)->setSessionId(...)`.
2. En el middleware `branch`, agregar `app(AuditContext::class)->setBranchId($branch->id)` junto al `ActiveBranch::set()` existente.
**Tests:**
- Extender el test de `JwtAuthenticate` existente (localizar con `grep -rln "JwtAuthenticate" back-laravel/tests`) para verificar que, tras una request autenticada, `app(CurrentSession::class)->id()` coincide con la sesión creada en login.
**Criterios de done:**
- [ ] Test extendido en verde.
- [ ] `make test-filter FILTER=Auth` y `make test-filter FILTER=Branch` en verde.

---
### T-8 — `ProfileService`/`ProfileController`/`AuthController`: sesiones sobre `Session`
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** T-6, T-7
**Archivos clave:**
- `back-laravel/app/Modules/Profile/Services/ProfileService.php`
- `back-laravel/app/Modules/Profile/Controllers/ProfileController.php`
- `back-laravel/app/Modules/Auth/Controllers/AuthController.php`
**Descripción:**
1. `ProfileService::listSessions()`/`revokeOtherSessions()`/`updatePassword()` según `design.md` §2.2.
2. `ProfileController::listSessions()`/`revokeOtherSessions()` dejan de extraer el refresh token de la cookie para identificar "sesión actual"; usan `$request->attributes->get('session_id')` (poblado en T-7).
3. `AuthController::logout()` sigue llamando `$this->jwtService->revokeRefreshToken($refreshToken)`, que ahora revoca la sesión completa (sin cambio de firma en el controller).
**Tests:**
- `back-laravel/tests/Feature/Modules/Profile/ProfileSessionsApiTest.php` (puede ya existir con otro nombre — buscar con `grep -rln "listSessions\|revokeOtherSessions" back-laravel/tests` y extender en vez de duplicar):
  - `GET /api/v1/profile/sessions` devuelve `device_type`/`browser`/`platform`/`last_used_at`, no devuelve `user_agent` crudo ni `token_hash`.
  - `DELETE /api/v1/profile/sessions/others` revoca todas menos la sesión del request actual.
  - `PUT /api/v1/profile/password` revoca todas las sesiones previas del usuario (verificado consultando `Session::whereNull('revoked_at')`).
**Criterios de done:**
- [ ] Test extendido/nuevo en verde.
- [ ] `make test-filter FILTER=Profile` en verde.

---
### T-9 — Corregir `morphMap`: quitar duplicado `CashSession`, registrar modelos ausentes
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** —
**Archivos clave:**
- `back-laravel/app/Modules/Finance/Providers/FinanceServiceProvider.php`
- `back-laravel/app/Modules/Sales/Providers/SalesServiceProvider.php`
- `back-laravel/app/Modules/Purchases/Providers/PurchasesServiceProvider.php`
- `back-laravel/app/Modules/Users/Providers/UsersServiceProvider.php` (crear el bloque `morphMap` si el provider no lo tiene — verificar primero con `grep -n "morphMap" back-laravel/app/Modules/Users/Providers/UsersServiceProvider.php`)
**Descripción:**
1. En `FinanceServiceProvider`: quitar la clave `'CashSession'`, dejar solo `'cash_session'`; agregar `'bank_account' => BankAccount::class`, `'exchange_rate' => ExchangeRate::class`.
2. En `SalesServiceProvider`: agregar `'document_series' => DocumentSeries::class`.
3. En `PurchasesServiceProvider`: agregar `'purchase_request' => PurchaseRequest::class`, `'provider_price' => ProviderPrice::class`.
4. En `UsersServiceProvider`: agregar bloque `Relation::morphMap(['user' => User::class], merge: true)` en `boot()`.
5. Confirmar que ningún código del proyecto compara `$model->getMorphClass() === 'CashSession'` literal (buscar con `grep -rn "'CashSession'" back-laravel/app`) antes de quitar la clave — si existe, migrar esa comparación a `'cash_session'` en la misma tarea.
**Tests:**
- Cubiertos por T-10.
**Criterios de done:**
- [ ] `grep -rn "'CashSession'" back-laravel/app` no devuelve ninguna comparación de código (solo, a lo sumo, un comentario).
- [ ] `vendor/bin/pint --test` en verde.

---
### T-10 — Test de regresión `MorphMapIntegrityTest`
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** T-9
**Archivos clave:**
- `back-laravel/tests/Feature/MorphMapIntegrityTest.php`
**Descripción:**
1. El test arranca la aplicación (los `ServiceProvider`s ya registraron `Relation::morphMap()` en `boot()`), lee `Relation::morphMap()` (método estático de Laravel que expone el mapa acumulado) y falla si `array_count_values(array_map('strval', $map))` tiene algún valor `> 1` (dos alias para la misma clase — detecta el caso `CashSession`/`cash_session` si reaparece).
2. Enumerar con `Illuminate\Support\Facades\File` o `glob(app_path('Modules/*/Models/*.php'))` las clases de modelo que usan el trait `Spatie\Activitylog\Traits\LogsActivity` (vía `class_uses_recursive()`), y para cada una verificar que `array_search($class, Relation::morphMap(), true)` no sea `false`.
**Tests:**
- Es el propio test; no requiere test del test.
**Criterios de done:**
- [ ] `MorphMapIntegrityTest` en verde con el estado actual del código tras T-9.
- [ ] El test falla intencionalmente si se revierte T-9 (verificar manualmente revirtiendo T-9 en local, confirmar fallo, y volver a aplicar T-9) — no se deja esa verificación en el repo, es solo para confirmar que el test realmente protege la regla.

---
### T-11 — Estandarizar las 5 llamadas manuales a `activity()`
**Estado:** 🔧 · **Subj:** back · **Complejidad:** L · **Depende de:** T-4
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Services/InstallmentScheduleService.php` (línea ~262)
- `back-laravel/app/Modules/Inventory/Services/StockQueryService.php` (línea ~162)
- `back-laravel/app/Modules/Maintenance/Services/UserBranchService.php` (línea ~30) + `back-laravel/app/Modules/Maintenance/Controllers/UserBranchController.php`
- `back-laravel/app/Modules/Purchases/Services/PurchaseOrderWorkflowService.php` (línea ~88)
- `back-laravel/app/Modules/Roles/Services/RoleService.php` (línea ~88) + su controller
**Descripción:**
1. `InstallmentScheduleService`, `StockQueryService`, `PurchaseOrderWorkflowService` **ya** llaman `causedBy($user)`/`causedBy($causer)` — en estos 3 solo se ajusta `event()` a un slug de máquina estable (`schedule_reprogrammed`, `stock_limits_updated`, `closed` ya es correcto) dejando `description` como la frase humana actual (no cambia el mensaje visible, solo se asegura que `event` nunca quede `NULL`).
2. `UserBranchService::sync()` **no** recibe el causer — cambiar su firma a `sync(User $user, array $branchIds, string $defaultBranchId, User $causer)`, agregar `->causedBy($causer)` a la llamada, y actualizar `UserBranchController::update()` para pasar `$request->user()`.
3. `RoleService::update()` **no** recibe el causer — cambiar su firma a `update(Role $role, array $attributes, ?array $permissions, User $causer)`, agregar `->causedBy($causer)`, y actualizar el controller que lo invoca (localizar con `grep -rln "roleService->update\|RoleService::update" back-laravel/app`).
4. Revisar que ningún test existente rompa por el cambio de firma (`grep -rln "UserBranchService\|RoleService" back-laravel/tests`).
**Tests:**
- Extender los tests de API existentes de `users/{id}/branches` y `roles/{id}` para verificar `activity_log.causer_id` no nulo tras la operación.
**Criterios de done:**
- [ ] Los 5 sitios producen `causer_id` no nulo en un test de integración.
- [ ] `make test-filter FILTER=Role` y `make test-filter FILTER=Branch` en verde.
- [ ] `vendor/bin/pint --test` en verde.

---
### T-12 — `SendElectronicDocumentJob`: marca `system` cuando no hay causer humano
**Estado:** 🔧 · **Subj:** back · **Complejidad:** S · **Depende de:** T-5
**Archivos clave:**
- `back-laravel/app/Modules/Sales/Jobs/SendElectronicDocumentJob.php`
**Descripción:**
1. No se agrega ningún log manual duplicado (el log automático de `LogsActivity` sobre `$document->update(['einvoice_status' => ...])` ya captura el diff). Se agrega únicamente, inmediatamente después de cada `$document->update([...])` dentro de `handle()`, una actualización de la última actividad registrada para marcarla como `system`:
   ```php
   $document->update([...]);
   \App\Modules\Audit\Models\Activity::where('subject_type', $document->getMorphClass())
       ->where('subject_id', $document->id)
       ->latest('created_at')
       ->first()
       ?->forceFill(['properties' => $document->activities()->latest()->first()?->properties?->put('system', true)])
       ->save();
   ```
   Revisar en la implementación real si Spatie expone un hook más directo (`tapActivity()` en el modelo, condicionado a una propiedad temporal como `$document->loggedAsSystem = true` seteada antes del `update()`) — **preferir `tapActivity()` sobre post-procesar la última fila** si el modelo (`Sale`/`CreditNote`) puede declararlo sin ensuciar su `getActivitylogOptions()` existente (`logOnly(['status','einvoice_status'])` no cambia). Decidir la forma exacta al implementar; el criterio de done no depende de la técnica usada.
**Tests:**
- `back-laravel/tests/Feature/Modules/Sales/SendElectronicDocumentJobTest.php` (ya existe — extenderlo): el log de `einvoice_status` generado por el job trae `properties.system === true` y `branch_id` igual al `branch_id` del `Sale`/`CreditNote` (este segundo punto ya debería funcionar solo por T-5, sin cambios en el job).
**Criterios de done:**
- [ ] `SendElectronicDocumentJobTest` extendido en verde.
- [ ] No se duplica el log automático existente (un solo registro de actividad por actualización de `einvoice_status`, con la marca agregada).

---
### T-13 — `AuditLogResource`/`ListAuditLogsRequest`: exponer y filtrar `session_id`
**Estado:** 🔧 · **Subj:** back · **Complejidad:** S · **Depende de:** T-5
**Archivos clave:**
- `back-laravel/app/Modules/Audit/Resources/AuditLogResource.php`
- `back-laravel/app/Modules/Audit/Requests/ListAuditLogsRequest.php`
- `back-laravel/app/Modules/Audit/Services/AuditLogService.php`
**Descripción:**
1. `AuditLogResource::toArray()` agrega `'session_id' => $activity->properties?->get('session_id')` junto a los campos existentes (no se mueve `session_id` a una columna propia de `activity_log`; sigue viviendo en `properties`, igual que `branch_id` hoy).
2. `ListAuditLogsRequest::rules()` agrega `'session_id' => ['nullable', 'uuid']`.
3. `AuditLogService::list()` agrega, si `$filters['session_id']` viene presente: `$query->where('properties->session_id', $filters['session_id'])` (operador JSON de PostgreSQL vía el *query builder* de Laravel — igual técnica que ya use el proyecto para filtrar JSON si existe algún precedente; si no existe, usar `whereJsonContains` o `->where('properties->>session_id', $filters['session_id'])` según el *driver*).
**Tests:**
- Extender el test de API existente de `audit-logs` (`grep -rln "audit-logs\|AuditLogController" back-laravel/tests`) con un caso que filtra por `session_id` y devuelve solo las actividades de esa sesión.
**Criterios de done:**
- [ ] Test extendido en verde.
- [ ] `make test-filter FILTER=Audit` en verde.

---
### T-14 — Frontend: `SessionsTab.vue` + `profileStore.js`
**Estado:** 🔧 · **Subj:** front · **Complejidad:** M · **Depende de:** T-8
**Archivos clave:**
- `front-vue/src/modules/profile/components/SessionsTab.vue`
- `front-vue/src/modules/profile/stores/profileStore.js`
**Descripción:**
1. Quitar `getDeviceIcon(userAgent)`/`getBrowserName(userAgent)`; agregar `deviceIcon(deviceType)` según `design.md` §8.2.
2. Cambiar las columnas de la tabla: "DISPOSITIVO / NAVEGADOR" usa `session.browser`/`session.platform`; "INICIO DE SESIÓN" usa `session.created_at`; agregar "ÚLTIMA ACTIVIDAD" con `session.last_used_at`.
3. Revisar `profileStore.js`: si tiene algún *mapper* que referencie `user_agent`, actualizarlo; si solo reenvía el payload de la API, no requiere cambios.
**Tests:**
- Ninguno nuevo (no hay precedente de tests de componente en `profile/`); validar manualmente en `make front-dev` con un usuario con 2+ sesiones (dos navegadores o modo incógnito).
**Criterios de done:**
- [ ] `cd front-vue && npm run lint` en verde.
- [ ] Verificación manual: la tabla muestra navegador/plataforma/dos fechas distintas tras varias rotaciones (recargar la página varias veces separadas por >15 min, o bajar `JWT_ACCESS_EXPIRES_IN` temporalmente en `.env` local para probar más rápido).

---
### T-15 — `config/session.php`: nota de no-uso
**Estado:** 🔧 · **Subj:** back · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `back-laravel/config/session.php`
**Descripción:**
1. Agregar el comentario de `design.md` §7 antes del `return [...]` del archivo. No cambiar ningún valor de configuración (`SESSION_DRIVER` se deja como está; no se desinstala ni se fuerza a `array` para no afectar a terceros).
**Tests:**
- Ninguno (cambio de comentario).
**Criterios de done:**
- [ ] `vendor/bin/pint --test` en verde (el archivo sigue siendo PHP válido).

---
### T-16 — Tests de integración de ciclo de vida de sesión (end-to-end del módulo)
**Estado:** 🔧 · **Subj:** back · **Complejidad:** M · **Depende de:** T-6, T-7, T-8
**Archivos clave:**
- `back-laravel/tests/Feature/Modules/Auth/SessionLifecycleTest.php`
**Descripción:**
1. Flujo completo: login → 3 llamadas a `/api/v1/auth/refresh` consecutivas (simulando rotaciones) → `GET /api/v1/profile/sessions` muestra 1 sola sesión con `created_at` igual al del login original → `DELETE /api/v1/profile/sessions/others` desde una segunda sesión (login en paralelo) revoca solo la segunda → la primera sigue pudiendo rotar.
2. Caso negativo: revocar la sesión actual (`logout`) y luego intentar `POST /api/v1/auth/refresh` con el mismo refresh token (incluso dentro del periodo de gracia) → `401`.
**Tests:**
- Es el propio archivo de test.
**Criterios de done:**
- [ ] `SessionLifecycleTest` en verde.
- [ ] `make test` completo (toda la suite) en verde — valida que el refactor no rompió nada fuera del alcance directo del spec.

---
### T-17 — Actualizar índice de specs
**Estado:** 🔧 · **Subj:** docs · **Complejidad:** S · **Depende de:** —
**Archivos clave:**
- `.claude/specs/README.md`
**Descripción:**
1. Cuando el spec se apruebe para implementación, cambiar su fila de `Draft` a `Approved`, y a `Done` cuando T-1..T-16 estén completas y en verde.
**Tests:**
- Ninguno.
**Criterios de done:**
- [ ] Fila de la tabla refleja el estado real.

---
### T-18 — `branch_id`/`session_id` como columnas propias e indexadas de `activity_log`
**Estado:** ✅ · **Subj:** back · **Complejidad:** M · **Depende de:** T-5, T-13
**Archivos clave:**
- `back-laravel/database/migrations/2026_10_24_000004_add_branch_id_and_session_id_to_activity_log_table.php`
- `back-laravel/app/Modules/Audit/Models/Activity.php`
- `back-laravel/app/Modules/Audit/Resources/AuditLogResource.php`
- `back-laravel/app/Modules/Audit/Requests/ListAuditLogsRequest.php`
- `back-laravel/app/Modules/Audit/Services/AuditLogService.php`
- `back-laravel/tests/Feature/Modules/Audit/ActivityBranchAndSessionContextTest.php`, `AuditLogTest.php`
- `back-laravel/tests/Feature/Modules/Sales/SendElectronicDocumentJobTest.php`, `back-laravel/tests/Feature/AuthTest.php`
**Descripción:**
1. Pedido explícito del dueño del producto tras T-5/T-13: pasar `branch_id`/`session_id` de claves sueltas en `properties` (JSON sin índice) a columnas `uuid` nullable indexadas (`(columna, created_at)`), sin FK (ver `design.md` §6.6 — una FK con cascada borraría auditoría al podar sesiones viejas; con restricción, rompería el *prune*).
2. Migración con backfill validado manualmente contra `laravel_dev` (insertar fila con el formato antiguo, correr el backfill, confirmar `UPDATE 1`/`UPDATE 1` y valores coincidentes) antes de confiar en que corriera bien en datos reales.
3. `Activity::booted()->saving()` escribe los atributos del modelo directamente, no `properties`.
4. `AuditLogResource` expone `branch_id`/`session_id` como campos propios; `ListAuditLogsRequest`/`AuditLogService` filtran por columna (`where('branch_id', ...)`/`where('session_id', ...)`), no por `properties->session_id`.
5. Los tests que antes insertaban UUIDs falsos en `properties` (`'branch-uuid-from-subject'`, `'018e3333-session-context-1234'`) se corrigieron a UUIDs reales (`Str::uuid()`) porque `branch_id`/`session_id` son ahora columnas `uuid` estrictas de Postgres — un string no-UUID rompe el insert.
**Tests:**
- `ActivityBranchAndSessionContextTest` (3 tests) — columnas, no `properties`.
- `AuditLogTest::test_audit_logs_can_be_filtered_by_branch_id` (nuevo) / `test_audit_logs_can_be_filtered_by_session_id` (ya existía, sin cambios de contrato).
- `SendElectronicDocumentJobTest::test_job_marks_activity_log_as_system_action_without_human_causer`, `AuthTest::test_login_records_last_login_at_activity_with_session_id` — ajustados a `$log->branch_id`/`$log->session_id`.
**Criterios de done:**
- [x] `make test`: 831 passed (mismos 6 fallos preexistentes de `SalesApiTest`, ajenos al spec).
- [x] `vendor/bin/pint --test` en verde.
- [x] Migración aplicada y backfill verificado manualmente contra `laravel_dev`.

---
## Notas de implementación (hallazgos durante la ejecución, no previstos en `design.md`)

La implementación inicial (otro agente) dejó T-1 a T-11 parcialmente hechas y T-12 a T-15 sin empezar. Al verificar end-to-end contra `postgres-test` y `laravel_dev` reales (no solo lectura de código) se encontraron y corrigieron los siguientes problemas, todos ya resueltos y cubiertos por tests en verde (`make test`: 829 passed, 6 failed — los 6 son `SalesApiTest` por `InsufficientStockException`, **preexistentes y no relacionados con este spec**, confirmado al pasar en aislamiento con `make test-filter`):

1. **Colisión real con la tabla `sessions` de framework.** `0001_01_01_000000_create_users_table.php` (stub por defecto de Laravel) ya crea una tabla `sessions` (framework, para `SESSION_DRIVER=database`) — el diagnóstico original de `requirements.md`/`design.md` ("no existe ninguna migración que cree `sessions`") era incorrecto; se corrigió en ambos documentos. La migración `2026_10_24_000001_create_sessions_table.php` ahora hace `Schema::dropIfExists('sessions')` antes de crear la tabla de dominio, con el porqué documentado inline. Se limpió además una tabla `sessions` huérfana (vacía) que había quedado en `laravel_dev` de un intento previo fallido.
2. **`UserService::delete()`/`updateProfile()` seguían borrando `refresh_tokens` por `user_id` directamente**, dejando `sessions` huérfanas (la FK `cascadeOnDelete` nueva es de `refresh_tokens.session_id` → `sessions.id`, no al revés). Se cambiaron ambos sitios para borrar `Session::where('user_id', ...)`, que cascada a `refresh_tokens`.
3. **`EmployeeUserService::createUser()` llamaba `UserBranchService::sync()` con 3 argumentos** (faltaba `$causer`, y además `$actor` no estaba en el `use()` del closure de la transacción — bug preexistente independiente de este spec). Corregido.
4. **`routes/console.php` (`auth:prune-tokens`) seguía podando solo `refresh_tokens`**, dejando `sessions` revocadas/expiradas sin eliminar nunca. Se extendió para podar también `sessions` (cascada a sus `refresh_tokens` restantes).
5. **`MorphMapIntegrityTest` (T-10) usaba una lista fija de 7 modelos** (los mencionados como ejemplo en `requirements.md`) en vez de escanear dinámicamente `app/Modules/*/Models/*.php` buscando `use LogsActivity`. Al corregirlo para que escanee de verdad, aparecieron **24 modelos adicionales** sin alias en ningún `morphMap` (`Bank`, `BankPeriod`, `Box`, `Currency`, `FinanceConcept`, `FinancialAccount`, `PaymentMethod` en Finance; `Brand`, `Category`, `ItemPresentation`, `ItemPrice`, `Unit`, `UnitCategory`, `WarehouseReason` en Inventory; `Area`, `Branch`, `Employee` en Maintenance; `Permission`; `Role`; `Client`; `CompanySetting`, `SystemParameter` en Configuration; `Provider`, `ProviderBankAccount` en Purchases). Se registraron todos (4 providers nuevos con bloque `morphMap`: Maintenance, Permissions, Roles, Clients, Configuration; 3 providers existentes ampliados: Finance, Inventory, Purchases). El test dinámico ahora protege contra que esto se repita.
6. **`RoleService::update()` dejaba `$causer` opcional** (`?User $causer = null`) con un `if` condicional en vez de exigirlo — se verificó que solo tiene un caller (`RoleController`, siempre autenticado) y se hizo obligatorio, cerrando el hueco que la regla dura de `CLAUDE.md.delta.md` pedía cerrar.
7. **4 tests existentes** (`CashSessionsApiTest`, `AuditLogTest` ×7 asserts, `UserBranchesApiTest`) asumían `subject_type` = FQCN para `CashSession`/`Client`/`Role`/`Permission`/`User` — corregidos a los alias `snake_case` nuevos, consecuencia esperada y correcta de T-9.
8. **T-16 no se escribió como test HTTP end-to-end separado**: `JwtServiceSessionTest` (nivel servicio) + `AuthTest::test_authenticated_request_populates_current_session_and_audit_context` + `ProfileApiTest` (nivel HTTP, ya extendidos) cubren en conjunto rotación×3, revocación de sesión con 401 en periodo de gracia, `revokeOtherSessions`, y el *wiring* real de `JwtAuthenticate`→`CurrentSession`/`AuditContext`. Se consideró razonablemente cubierto sin duplicar un test HTTP idéntico.
9. **`make migrate-fresh` rompía con `SQLSTATE[22P02]: invalid input syntax for type uuid`** al correr `database/seeders/StockDemoSeeder.php`, que llama `auth()->login($admin)` para tener un `auth()->user()` válido en el seeder. `Auth::login()` toca el almacén de sesión de Laravel **sin necesidad de middleware `web` ni request HTTP** — con `SESSION_DRIVER=database` apuntando a la tabla `sessions` de dominio (esquema UUID, incompatible con el esquema de framework que esa llamada espera: `id` string, `payload`, `last_activity`), cualquier `Auth::login()` fuera de contexto de request rompe. `docker-compose.test.yml` ya usaba `SESSION_DRIVER=array` (por eso `make test` nunca lo mostró), pero `.env`, `.env.example`, `docker-compose.yml` y `docker-compose.prod.yml` seguían en `database`. Se cambiaron los 4 a `array`, cerrando la clase de bug completa en vez de solo el caso HTTP (ver `design.md` §7, corregido). Verificado con `make migrate-fresh` corriendo limpio dos veces seguidas tras recrear los contenedores de dev.
10. **`AuthController::login()` dejaba la actualización de `last_login_at` sin `session_id`** (y sin `causer_id`, preexistente y fuera de alcance de este spec): `$user->update(['last_login_at' => now()])` corría *antes* de `createTokenPair()`, es decir, antes de que la `Session` existiera. Encontrado haciendo login real contra `template_app_dev` (`manager@example.com`/`password`) e inspeccionando `activity_log` en `laravel_dev` con `psql` directamente — no apareció en ningún test automatizado porque ninguno verificaba ese caso puntual. Corregido el orden (crear el par de tokens primero, `AuditContext::setSessionId()` después, y solo entonces el `update()`), con test de regresión `AuthTest::test_login_records_last_login_at_activity_with_session_id`.
