# 019 · cash-transfers — Design
> **Spec:** 019 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** media · **Depende de specs:** 018
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
>
> **Prerequisito de lectura:** [requirements.md](./requirements.md) — define el qué/por qué; este documento define el cómo.
> **Reglas:** [.agents/rules/](../../../.agents/rules/) · **Makefile:** [Makefile](../../../Makefile)
>
---
## 0. Ubicación y alcance
- **Backend** (módulo `Finance`): `Models/CashTransfer.php`, `Enums/CashTransferStatus.php`, `Services/CashTransferService.php`, `Controllers/CashTransferController.php`, `Requests/{StoreCashTransferRequest,ListCashTransfersRequest}.php` (+ `CancelDocumentRequest`), `Resources/CashTransferResource.php`, `Exceptions/CashTransferException.php`.
- **Modificados:** `CashSessionSummaryService` → nuevo método `availableCash($s) = expectedCash − Σ pending transfers out`; `BoxController::available` (spec 018) acepta `scope=all` para devolver las cajas activas de todas las sedes accesibles, sin filtrar por rol, para elegir el destino.
- **Frontend:** `front-vue/src/pages/finance/cash/transfers/index.vue`, `front-vue/src/modules/finance/components/cash/CashTransferDialog.vue`, `services/cashTransfersApi.js`, `stores/cashTransfersStore.js`.

## 1. Backend — Modelo y Persistencia
- **`CashTransfer`** (`HasUuids`, `LogsActivity` en `status`): `number`, `status` (`CashTransferStatus`: `pending`, `approved`, `rejected`, `cancelled`, con `canTransitionTo()`), `source_session_id`, `source_box_id`, `source_branch_id`, `target_box_id`, `target_branch_id`, `target_session_id` (al aprobar), `amount`, `reason`, `created_by`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `rejection_reason`, `cancelled_by`, `cancelled_at`, `cancel_reason`. Relaciones a sesiones, cajas, usuarios y `cashMovements()` (`morphMany` por `source`).

## 2. Backend — Lógica de Negocio y Servicios
`CashTransferService`:
- `create(array $d, User $u)`: transacción; `lockForUpdate` de la sesión de origen; valida estado `open`, `BoxAccessService::canOperate`, sede activa = sede de la sesión; caja destino ≠ caja origen, activa y en una sede accesible; `amount ≤ availableCash(source)` → si no, `InsufficientCashException`; `number = SequentialCodeGenerator::next('cash_transfer', 'TC')`.
- `approve(CashTransfer $t, User $u)`: transacción; `lockForUpdate` sobre la transferencia; exige `pending`, `approved_by ≠ created_by`; `target = CashLedgerService::currentSession(targetBox)` → si null, `CashTransferException`; bloquea ambas sesiones ordenadas por `id`; exige origen `open`; registra:
  - `CashLedgerService::register(new CashMovementData(sessionId: source, direction: out, conceptCode: CASH_TRANSFER_OUT, paymentMethodId: CASH, amount, source: $t, sourceNumber: $t->number, description: "Transferencia a {targetBox}"))`
  - ídem `in` en `target` con `CASH_TRANSFER_IN`.
  - `status = approved`, `target_session_id`, `approved_by`, `approved_at`.
- `reject(CashTransfer $t, string $reason, User $u)` y `cancel(CashTransfer $t, string $reason, User $u)` (este último solo si `created_by = $u`).
- Morph map: `cash_transfer`.

## 3. Backend — Presentation (Controllers, Requests, Resources)
- `StoreCashTransferRequest`: `source_session_id` required|uuid|exists; `target_box_id` required|uuid|exists; `amount` required|numeric|gt:0|decimal:0,2; `reason` required|string|min:5|max:255.
- `ListCashTransfersRequest`: `status`, `box_id`, `direction` in:sent,received; `date_from`, `date_to`; paginación.
- Rutas (`v1/finance`, `branch`):
```php
// Transferencias entre cajas (Spec 019)
Route::middleware('branch')->group(function () {
    Route::get('/cash-transfers', [CashTransferController::class, 'index'])->middleware('permission:cash_transfers.view|cash_transfers.create');
    Route::get('/cash-transfers/pending-count', [CashTransferController::class, 'pendingCount'])->middleware('permission:cash_transfers.approve');
    Route::post('/cash-transfers', [CashTransferController::class, 'store'])->middleware('permission:cash_transfers.create');
    Route::get('/cash-transfers/{id}', [CashTransferController::class, 'show'])->whereUuid('id')->middleware('permission:cash_transfers.view|cash_transfers.create');
    Route::post('/cash-transfers/{id}/approve', [CashTransferController::class, 'approve'])->whereUuid('id')->middleware('permission:cash_transfers.approve');
    Route::post('/cash-transfers/{id}/reject', [CashTransferController::class, 'reject'])->whereUuid('id')->middleware('permission:cash_transfers.approve');
    Route::post('/cash-transfers/{id}/cancel', [CashTransferController::class, 'cancel'])->whereUuid('id')->middleware('permission:cash_transfers.create');
});
```
- Visibilidad: sede activa como origen o destino; con solo `create`, transferencias cuyas cajas (origen o destino) el usuario puede operar.

## 4. Contrato API
| Método | Ruta | Middleware / Permiso | Request Body | Respuesta exitosa | Errores posibles |
|---|---|---|---|---|---|
| GET | `/api/v1/finance/cash-transfers` | `branch`, `view|create` | filtros §3 | 200 `data[]`, `meta` | 401, 403 |
| GET | `/api/v1/finance/cash-transfers/pending-count` | `approve` | — | 200 `data: { count }` | 401, 403 |
| POST | `/api/v1/finance/cash-transfers` | `create` | `{ source_session_id, target_box_id, amount, reason }` | 201 | 401, 403, 422 |
| GET | `/api/v1/finance/cash-transfers/{id}` | `view|create` | — | 200 (con movimientos) | 401, 403, 404 |
| POST | `/api/v1/finance/cash-transfers/{id}/approve` | `approve` | — | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/finance/cash-transfers/{id}/reject` | `approve` | `{ reason }` | 200 | 401, 403, 404, 422 |
| POST | `/api/v1/finance/cash-transfers/{id}/cancel` | `create` | `{ reason }` | 200 | 401, 403, 404, 422 |

## 5. Permisos y seguridad
- Permisos nuevos: `cash_transfers.view|create|approve`. `admin` y `manager` todos; `user` `create`.

## 6. Migraciones
`2026_10_18_000003_create_cash_transfers_table.php`:
```php
Schema::create('cash_transfers', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('number', 20)->unique(); $t->string('status', 10)->default('pending');
    $t->foreignUuid('source_session_id')->constrained('cash_sessions')->restrictOnDelete();
    $t->foreignUuid('source_box_id')->constrained('boxes')->restrictOnDelete();
    $t->foreignUuid('source_branch_id')->constrained('branches')->restrictOnDelete();
    $t->foreignUuid('target_box_id')->constrained('boxes')->restrictOnDelete();
    $t->foreignUuid('target_branch_id')->constrained('branches')->restrictOnDelete();
    $t->foreignUuid('target_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
    $t->decimal('amount', 18, 2); $t->string('reason', 255);
    $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $t->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('approved_at')->nullable();
    $t->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('rejected_at')->nullable();
    $t->string('rejection_reason', 500)->nullable();
    $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('cancelled_at')->nullable();
    $t->string('cancel_reason', 500)->nullable();
    $t->timestamps();
    $t->index(['source_branch_id', 'status']); $t->index(['target_branch_id', 'status']);
    $t->index(['source_session_id', 'status']);
});
```

## 7. Configuración e infraestructura
- No aplica (sin variables ni parámetros nuevos).

## 8. Frontend
- **Diálogo** `CashTransferDialog.vue` (abierto desde `pages/finance/cash/index.vue`): caja de destino (`/boxes/available?scope=all` de sedes accesibles, excluye la propia), monto (muestra el efectivo disponible), motivo.
- **Página** `src/pages/finance/cash/transfers/index.vue` (`cash_transfers.view` o `create`): pestañas Enviadas · Recibidas · Pendientes de aprobar (esta solo con `can('cash_transfers.approve')`); acciones Aprobar, Rechazar (con `CancelDocumentDialog` reutilizado como diálogo de motivo) y Cancelar.
- **Badge** en el menú "Finanzas" › "Transferencias de caja" con `pending-count` (refresco cada 60 s mientras la vista de caja está abierta).
- Tras aprobar o recibir, `useCashSession()` refresca el resumen de la caja.

## 9. Testing
- `tests/Feature/Modules/Finance/CashTransfersApiTest.php`: crear (sin movimientos); monto mayor al disponible (considerando otras pendientes) → 422; misma caja → 422; aprobar mueve el dinero en ambas sesiones y el esperado cuadra; aprobar con destino sin sesión → 422; aprobar después de cerrar el origen → 422; el creador no aprueba → 422; rechazar y cancelar sin movimientos; cancelar la de otro → 403; transiciones inválidas → 422; listado por dirección.
- Ejecutar: `make test-filter FILTER=CashTransfers`.

## 10. Decisiones Rechazadas
- **Morfismos de sesión origen/destino (caja o banco) de Multinort:** se separa caja ↔ caja (aquí) de caja ↔ banco (spec 020) para mantener FKs reales.
- **Descontar el dinero de origen al registrar:** si se rechaza habría que revertir; se reserva el disponible y se mueve al aprobar.
- **`motive` como entero:** reemplazado por texto libre; la clasificación contable la dan los conceptos de sistema.
