# 018 · cash-registers-and-sessions — Design
> **Spec:** 018 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** media · **Depende de specs:** 002, 011, 014, 017
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend** (módulo `Finance`):
  - `Models/{Box,BoxRole,CashSession,CashCountDetail,CashMovement}.php`
  - `Enums/{CashSessionStatus,CashReviewStatus}.php` (reutiliza `MoneyDirection` del spec 017)
  - `Data/CashMovementData.php`
  - `Services/{BoxService,CashSessionService,CashLedgerService,CashSessionSummaryService,BoxAccessService}.php`
  - `Controllers/{BoxController,CashSessionController,CashMovementController}.php`
  - `Requests/{BoxRequest,SyncBoxRolesRequest,OpenCashSessionRequest,CloseCashSessionRequest,ReviewCashSessionRequest,StoreCashMovementRequest,ListCashSessionsRequest}.php` (+ `CancelDocumentRequest` del spec 016)
  - `Resources/{BoxResource,CashSessionResource,CashSessionSummaryResource,CashMovementResource}.php`
  - `Exceptions/{CashSessionException,InsufficientCashException}.php`
- **Modificados:** `Maintenance/Services/BranchService::delete()` (bloqueo por cajas), `SystemParameterSeeder` (`finance.cash_denominations`).
- **Frontend:** `front-vue/src/pages/finance/cash/index.vue` (operación), `front-vue/src/pages/finance/cash/sessions/{index,[id]}.vue` (historial), `front-vue/src/pages/finance/boxes/index.vue`, `front-vue/src/modules/finance/{services/cashApi.js, stores/cashStore.js, components/cash/*}`.

## 1. Backend — Modelo y Persistencia
- **`Box`** (`HasUuids`, `SoftDeletes`, `LogsActivity`): `branch_id`, `code`, `name`, `description`, `is_active`; `roles()` (`belongsToMany Role` vía `box_roles` con pivot UUID `BoxRole`), `sessions()`, `openSession()` (`hasOne` con `status = open`).
- **`CashSession`** (`HasUuids`, `LogsActivity` en estado/montos de cierre/revisión): `number`, `box_id`, `branch_id`, `status`, `opened_by`, `opened_at`, `opening_amount`, `opening_notes`, `closed_by`, `closed_at`, `expected_cash`, `counted_cash`, `difference`, `closing_notes`, `review_status`, `reviewed_by`, `reviewed_at`, `review_notes`. Relaciones `box`, `movements`, `countDetails`, `opener`, `closer`, `reviewer`.
- **`CashCountDetail`** (`HasUuids`): `cash_session_id`, `denomination` (decimal:2), `quantity`, `subtotal`.
- **`CashMovement`** (`HasUuids`, `UPDATED_AT = null`, inmutable como `KardexMovement`): `cash_session_id`, `box_id`, `branch_id`, `direction`, `concept_id`, `payment_method_id`, `affects_cash`, `amount`, `reference`, `description`, `counterparty_name`, `source_type`, `source_id`, `source_number`, `reverses_id`, `is_manual`, `created_by`, `moved_at`. Relaciones `session`, `concept`, `paymentMethod`, `source()` (morph), `reverses`, `reversedBy`.

## 2. Backend — Lógica de Negocio y Servicios
### `BoxAccessService`
- `canOperate(User $u, Box $b): bool` → `$u->isAdmin() || $b->roles()->whereIn('roles.id', $u->roles->pluck('id'))->exists()`.
- `availableBoxes(User $u, Branch $active): Collection` (con `openSession.opener`).
### `CashSessionService`
- `open(Box $box, float $amount, ?string $notes, User $u)`: valida sede activa, acceso y caja activa; transacción con `lockForUpdate` sobre la caja; si existe sesión abierta → `CashSessionException` (409); `number = SequentialCodeGenerator::next('cash_session', 'CJ')`.
- `close(CashSession $s, array $count|null, ?float $countedCash, ?string $notes, User $u)`: `lockForUpdate`; exige `open`; valida denominaciones contra `SystemParameterService::get('finance.cash_denominations')`; `counted = Σ denomination × quantity` (o `countedCash`); `expected = CashSessionSummaryService::expectedCash($s)`; `difference`; `review_status = abs(diff) <= tolerance ? approved : pending` (y exige notas si `pending`); guarda detalles y cierra.
- `review(CashSession $s, string $decision, ?string $notes, User $u)`: exige `closed` + `pending`, `reviewer ≠ closed_by`; si `approved` y `difference ≠ 0` → registra con `CashLedgerService::registerInformative()` un movimiento `CASH_OVERAGE`/`CASH_SHORTAGE` con `affects_cash = false` (no cambia `expected_cash`, deja rastro contable).
### `CashLedgerService` (núcleo, exige transacción abierta)
- `register(CashMovementData $d): CashMovement` — `lockForUpdate` sobre la sesión; exige `open`; valida `concept.direction->allows($d->direction)` (si no, `LogicException`: es un error de programación); `affects_cash = paymentMethod.settlement === cash_box`; si `direction = out` y `affects_cash` → `expectedCash($s) - amount < 0` ⇒ `InsufficientCashException` (422).
- `reverse(CashMovement $m, Model $source, string $sourceNumber, string $reason): CashMovement` — la sesión del original debe seguir abierta (si no, 422 "La sesión del movimiento ya está cerrada"); crea el movimiento opuesto enlazado por `reverses_id`.
- `registerInformative(CashSession $s, SystemConcept $concept, string $amount, string $description): CashMovement` — único caso permitido sobre una sesión **cerrada**: `affects_cash = false`, `payment_method = CASH`, usado solo por `CashSessionService::review()`.
- `currentSession(Box $b): ?CashSession`.
- `CashMovementData`: `sessionId`, `direction`, `conceptCode|conceptId`, `paymentMethodId`, `amount`, `source` (`?Model`), `sourceNumber`, `reference`, `description`, `counterpartyName`, `isManual`.
### `CashSessionSummaryService`
- `expectedCash(CashSession $s): string` → `opening + SUM(in, affects_cash) − SUM(out, affects_cash)` con una consulta agregada.
- `summary(CashSession $s): array` → totales de efectivo y agrupaciones por método y por concepto (dos consultas `GROUP BY`).

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `OpenCashSessionRequest`: `box_id` required|uuid|exists; `opening_amount` required|numeric|min:0|decimal:0,2; `opening_notes` nullable|max:500.
- `CloseCashSessionRequest`: `count` required_without:counted_cash|array; `count.*.denomination` required|numeric; `count.*.quantity` required|integer|min:0; `counted_cash` required_without:count|numeric|min:0; `closing_notes` nullable|max:1000.
- `ReviewCashSessionRequest`: `decision` required|in:approved,rejected; `notes` required_if:decision,rejected|nullable|max:1000.
- `StoreCashMovementRequest`: `direction` required|in:in,out; `concept_id` required|uuid|exists activo; `payment_method_id` required|uuid|exists activo; `amount` required|numeric|gt:0|decimal:0,2; `reference` nullable|max:60 (obligatoria si el método `requires_reference`, en `withValidator`); `description` required|max:255; `counterparty_name` nullable|max:150.
- Autorización de sesión concreta (en el controller, delegando a `BoxAccessService`): con `cash_sessions.view` se ve cualquier sesión de sedes accesibles; con solo `operate`, únicamente sesiones de cajas que puede operar → si no, 403.
- Rutas (`v1/finance`, middleware `branch`):
```php
// Cajas y sesiones (Spec 018)
Route::middleware('branch')->group(function () {
    Route::get('/boxes/available', [BoxController::class, 'available'])->middleware('permission:cash_sessions.operate');
    Route::get('/boxes/{id}/suggested-opening', [BoxController::class, 'suggestedOpening'])->whereUuid('id')->middleware('permission:cash_sessions.operate');
    Route::get('/boxes', [BoxController::class, 'index'])->middleware('permission:boxes.view');
    Route::post('/boxes', [BoxController::class, 'store'])->middleware('permission:boxes.manage');
    Route::get('/boxes/{id}', [BoxController::class, 'show'])->whereUuid('id')->middleware('permission:boxes.view');
    Route::match(['put', 'patch'], '/boxes/{id}', [BoxController::class, 'update'])->whereUuid('id')->middleware('permission:boxes.manage');
    Route::delete('/boxes/{id}', [BoxController::class, 'destroy'])->whereUuid('id')->middleware('permission:boxes.manage');
    Route::put('/boxes/{id}/roles', [BoxController::class, 'syncRoles'])->whereUuid('id')->middleware('permission:boxes.manage');

    Route::get('/cash-sessions', [CashSessionController::class, 'index'])->middleware('permission:cash_sessions.view|cash_sessions.operate');
    Route::post('/cash-sessions', [CashSessionController::class, 'open'])->middleware('permission:cash_sessions.operate');
    Route::get('/cash-sessions/{id}', [CashSessionController::class, 'show'])->whereUuid('id')->middleware('permission:cash_sessions.view|cash_sessions.operate');
    Route::get('/cash-sessions/{id}/movements', [CashMovementController::class, 'index'])->whereUuid('id')->middleware('permission:cash_sessions.view|cash_sessions.operate');
    Route::post('/cash-sessions/{id}/movements', [CashMovementController::class, 'store'])->whereUuid('id')->middleware('permission:cash_sessions.operate');
    Route::post('/cash-sessions/{id}/close', [CashSessionController::class, 'close'])->whereUuid('id')->middleware('permission:cash_sessions.operate');
    Route::post('/cash-sessions/{id}/review', [CashSessionController::class, 'review'])->whereUuid('id')->middleware('permission:cash_sessions.review');
    Route::post('/cash-movements/{id}/cancel', [CashMovementController::class, 'cancel'])->whereUuid('id')->middleware('permission:cash_movements.cancel');
});
```

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| CRUD | `/api/v1/finance/boxes[/{id}]` | `branch`, `boxes.view|manage` | `branch_id, code, name, description?, is_active?` | 200/201/204 | 401, 403, 404, 422 |
| PUT | `/api/v1/finance/boxes/{id}/roles` | `boxes.manage` | `{ role_ids: uuid[] }` | 200 `data: Box` | 401, 403, 404, 422 |
| GET | `/api/v1/finance/boxes/available` | `cash_sessions.operate` | — | 200 `data: [{ box, current_session }]` | 401, 403, 409 |
| POST | `/api/v1/finance/cash-sessions` | `cash_sessions.operate` | `{ box_id, opening_amount, opening_notes? }` | 201 `data: CashSession` | 401, 403, 409, 422 |
| GET | `/api/v1/finance/cash-sessions` | `view` u `operate` | filtros §US-4 | 200 `data[]`, `meta` | 401, 403 |
| GET | `/api/v1/finance/cash-sessions/{id}` | `view` u `operate` | — | 200 `data: { …session, summary }` | 401, 403, 404 |
| GET/POST | `/api/v1/finance/cash-sessions/{id}/movements` | `view|operate` / `operate` | §3 | 200 / 201 | 401, 403, 404, 422 |
| POST | `/api/v1/finance/cash-sessions/{id}/close` | `operate` | `{ count[] | counted_cash, closing_notes? }` | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/finance/cash-sessions/{id}/review` | `cash_sessions.review` | `{ decision, notes? }` | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/finance/cash-movements/{id}/cancel` | `cash_movements.cancel` | `{ reason }` | 200 `data: CashMovement` (reversión) | 401, 403, 404, 422 |

Ejemplo de `summary`:
```json
{ "opening_amount": 200.00, "cash_in": 1530.50, "cash_out": 120.00, "expected_cash": 1610.50,
  "by_payment_method": [ { "method": { "code": "CASH", "name": "Efectivo" }, "in": 1530.50, "out": 120.00, "net": 1410.50 },
                         { "method": { "code": "VISA", "name": "Tarjeta Visa" }, "in": 845.00, "out": 0, "net": 845.00 } ],
  "by_concept": [ { "concept": { "code": "SALE_COLLECTION", "name": "Cobro de venta" }, "direction": "income", "total": 2375.50 } ] }
```

## 5. Permisos y seguridad
- Permisos nuevos: `boxes.view|manage`; `cash_sessions.view|operate|review`; `cash_movements.cancel`. `admin` todos; `manager` todos; `user` `cash_sessions.operate`.
- El middleware `permission:a|b` de Spatie acepta cualquiera de los dos (OR).
- Ninguna ruta acepta `cash_session_id` de otra sede: se valida contra `ActiveBranch`.

## 6. Migraciones
`2026_10_18_000002_create_cash_tables.php`:
```php
Schema::create('boxes', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->string('code', 10); $t->string('name', 80); $t->string('description', 255)->nullable();
    $t->boolean('is_active')->default(true); $t->timestamps(); $t->softDeletes(); $t->index('branch_id');
});
SoftDeleteUniqueIndex::make('boxes', ['code']);

Schema::create('box_roles', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('box_id')->constrained('boxes')->cascadeOnDelete();
    $t->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete(); $t->timestamps();
    $t->unique(['box_id', 'role_id']);
});

Schema::create('cash_sessions', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('number', 20)->unique();
    $t->foreignUuid('box_id')->constrained('boxes')->restrictOnDelete();
    $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->string('status', 10)->default('open');
    $t->foreignUuid('opened_by')->constrained('users')->restrictOnDelete(); $t->timestamp('opened_at');
    $t->decimal('opening_amount', 18, 2)->default(0); $t->string('opening_notes', 500)->nullable();
    $t->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('closed_at')->nullable();
    $t->decimal('expected_cash', 18, 2)->nullable(); $t->decimal('counted_cash', 18, 2)->nullable();
    $t->decimal('difference', 18, 2)->nullable(); $t->string('closing_notes', 1000)->nullable();
    $t->string('review_status', 10)->nullable();
    $t->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('reviewed_at')->nullable();
    $t->string('review_notes', 1000)->nullable();
    $t->timestamps();
    $t->index(['branch_id', 'status']); $t->index(['box_id', 'opened_at']);
});
DB::statement("CREATE UNIQUE INDEX cash_sessions_one_open_per_box ON cash_sessions (box_id) WHERE status = 'open'");

Schema::create('cash_count_details', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->foreignUuid('cash_session_id')->constrained('cash_sessions')->cascadeOnDelete();
    $t->decimal('denomination', 10, 2); $t->unsignedInteger('quantity'); $t->decimal('subtotal', 18, 2);
    $t->timestamps();
});

Schema::create('cash_movements', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->foreignUuid('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();
    $t->foreignUuid('box_id')->constrained('boxes')->restrictOnDelete();
    $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
    $t->string('direction', 3);
    $t->foreignUuid('concept_id')->constrained('finance_concepts')->restrictOnDelete();
    $t->foreignUuid('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
    $t->boolean('affects_cash')->default(true);
    $t->decimal('amount', 18, 2);
    $t->string('reference', 60)->nullable(); $t->string('description', 255)->nullable();
    $t->string('counterparty_name', 150)->nullable();
    $t->string('source_type', 40)->nullable(); $t->uuid('source_id')->nullable(); $t->string('source_number', 40)->nullable();
    $t->foreignUuid('reverses_id')->nullable()->unique()->constrained('cash_movements')->restrictOnDelete();
    $t->boolean('is_manual')->default(false);
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->timestamp('moved_at'); $t->timestamp('created_at')->useCurrent();
    $t->index(['cash_session_id', 'moved_at']); $t->index(['source_type', 'source_id']);
});
```

## 7. Configuración e infraestructura
- `SystemParameterSeeder`: `finance.cash_denominations` (json, `[200,100,50,20,10,5,2,1,0.5,0.2,0.1]`).
- Morph map de Finance (`FinanceServiceProvider`): `cash_session`, `cash_movement` (los specs 019–028 agregan los suyos).
- `BranchService::delete()`: bloquear si `Branch::boxes()->exists()`.

## 8. Frontend
- **Operación diaria** `src/pages/finance/cash/index.vue` (`action: 'cash_sessions.operate'`):
  - Selector de caja (de `/boxes/available`), persistido en `cashStore.selectedBoxId` (`localStorage`).
  - Sin sesión: tarjeta "Abrir caja" con monto sugerido.
  - Con sesión: tarjetas (Apertura, Ingresos efectivo, Egresos efectivo, Esperado), tabla de movimientos (`ServerDataTable`), botones "Nuevo ingreso", "Nuevo egreso" (`CashMovementDialog.vue`), "Cerrar caja" (`CashCountDialog.vue`, grilla de denominaciones con totales en vivo y diferencia en verde/rojo) y la pestaña "Resumen por método/concepto".
- **Historial** `src/pages/finance/cash/sessions/index.vue` (`cash_sessions.view` u `operate`): filtros y chip de `review_status`; acción "Revisar" con `CashReviewDialog.vue` (`can('cash_sessions.review')`). **Detalle** `[id].vue` con resumen, arqueo y movimientos.
- **Cajas** `src/pages/finance/boxes/index.vue` (`boxes.view`): CRUD + asignación de roles (`VAutocomplete` múltiple sobre `/roles`).
- **Composable** `useCashSession()` (en `src/modules/finance/composables/`): expone la sesión actual de la caja seleccionada; lo reutilizan POS (027) y cobranzas (028).
- **Menú:** "Finanzas" › "Caja" (`tabler-cash-register`, `cash_sessions.operate`), "Historial de caja" (`cash_sessions.view`), "Cajas" (`boxes.view`).

## 9. Testing
- `tests/Feature/Modules/Finance/BoxesApiTest.php`: CRUD; roles; `available` respeta rol y sede; eliminar sede con cajas → 422.
- `CashSessionsApiTest.php`: abrir; segunda apertura → 409; sin rol → 403; cerrar dentro de la tolerancia → `approved`; fuera de la tolerancia sin notas → 422; con notas → `pending`; denominación inválida → 422; revisar (autor del cierre → 422; aprobar registra el movimiento informativo de sobrante); movimiento en sesión cerrada → 422.
- `CashMovementsApiTest.php`: ingreso y egreso manual; egreso que deja negativo → 422; concepto de sistema → 422; método bancario → 422; anular un manual crea la reversión; anular un no manual → 422.
- `CashLedgerServiceTest.php`: `expectedCash` solo suma efectivo; sin transacción → `LogicException`; inmutabilidad.
- Ejecutar: `make test-filter FILTER=Cash` y `make test-filter FILTER=Boxes`.

## 10. Decisiones Rechazadas
- **`incomes`/`expenses`/`transactions` separados (Multinort):** se unifican en `cash_movements` con dirección y concepto.
- **`cash_session_payment_summaries` persistidos:** el resumen se calcula con agregaciones indexadas; evita inconsistencias.
- **Acceso a caja por usuario:** se mantiene el acceso por rol de Multinort (`role_box`), más simple de administrar.
- **Registrar la diferencia de arqueo como movimiento que altera el esperado:** la sesión ya está cerrada; se deja un movimiento informativo.
