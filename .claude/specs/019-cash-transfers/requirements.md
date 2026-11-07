# 019 · cash-transfers — Requirements
> **Spec:** 019 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** media · **Depende de specs:** 018
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 3)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 018 — `CashLedgerService`, `BoxAccessService`.
> - Origen Multinort: `create_cash_transfers_table.php`, `Caja/CashTransferController.php` (`store` → `approve`/`reject`, voucher PDF).
>
---
## 1. Contexto de Negocio
Durante el día el efectivo se mueve entre cajas: una caja principal entrega sencillo a otra, o una caja con mucho efectivo lo envía a la caja principal o a la bóveda. Cada traslado debe quedar registrado en ambas cajas y requerir **aprobación**, para que el dinero no "desaparezca" entre sesiones.

En Multinort, `cash_transfers` usa morfismos de sesión de origen y destino (caja o cuenta bancaria), `motive` entero con `other_motive`, estados numéricos (registrado, aprobado, rechazado) y voucher PDF. El dinero se mueve al aprobar. Este spec cubre **caja → caja**; los movimientos caja ↔ banco se tratan en el spec 020.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Cajero de origen | Opera la caja de origen | JWT · `cash_transfers.create` |
| Aprobador | Supervisor o cajero de destino | JWT · `cash_transfers.approve` |
| Supervisor | `manager` | JWT · `cash_transfers.view` |

## 3. User Stories (formato EARS)
### US-1 — Solicitar una transferencia
**Como** cajero **quiero** registrar el envío de efectivo de mi caja a otra **para** documentar el traslado.
**Cuando** se envía `POST /api/v1/finance/cash-transfers` con `{ source_session_id, target_box_id, amount, reason }`, **el sistema debe** crear la transferencia `pending` con número `TC-000001`, sin mover dinero todavía.
**Módulo:** `Finance` (`/api/v1/finance/cash-transfers`)
**Permisos:** `cash_transfers.create`
**Criterios de aceptación:**
- [ ] La sesión de origen está abierta y el usuario opera su caja → si no, 403/422.
- [ ] La caja de destino es otra, está activa y pertenece a una sede accesible (puede ser otra sede).
- [ ] `amount > 0` y `≤` efectivo esperado de la sesión de origen menos las transferencias `pending` que ya salen de ella → 422 "Efectivo insuficiente".
- [ ] `reason` obligatorio (5–255).
- [ ] UI: botón "Transferir" en la pantalla de caja (spec 018) con diálogo.

### US-2 — Aprobar la transferencia
**Como** aprobador **quiero** confirmar la recepción **para** que el dinero salga de una caja y entre en la otra.
**Cuando** se envía `POST /api/v1/finance/cash-transfers/{id}/approve`, **el sistema debe** registrar en una transacción la salida (`CASH_TRANSFER_OUT`) en la sesión de origen y la entrada (`CASH_TRANSFER_IN`) en la sesión abierta de la caja de destino, y marcar la transferencia `approved` con `approved_by`, `approved_at` y `target_session_id`.
**Módulo:** `Finance`
**Permisos:** `cash_transfers.approve`
**Criterios de aceptación:**
- [ ] La caja de destino no tiene sesión abierta → 422 "La caja de destino no tiene sesión abierta".
- [ ] La sesión de origen se cerró antes de aprobar → 422; la transferencia queda `pending` y solo puede rechazarse o cancelarse.
- [ ] El aprobador no puede ser quien la creó → 422.
- [ ] El efectivo de origen se valida de nuevo al aprobar (`InsufficientCashException`).

### US-3 — Rechazar o cancelar
**Como** aprobador o cajero de origen **quiero** rechazar o cancelar una transferencia pendiente **para** anular un traslado que no se concretó.
**Cuando** se envía `POST /api/v1/finance/cash-transfers/{id}/reject` con `{ reason }` (aprobador) o `POST /api/v1/finance/cash-transfers/{id}/cancel` con `{ reason }` (creador), **el sistema debe** cambiar el estado a `rejected` o `cancelled` sin movimientos de dinero.
**Módulo:** `Finance`
**Permisos:** `cash_transfers.approve` (rechazar), `cash_transfers.create` (cancelar la propia)
**Criterios de aceptación:**
- [ ] Solo desde `pending` → 422 en otro estado.
- [ ] Cancelar una transferencia de otro usuario → 403.

### US-4 — Consultar transferencias
**Como** supervisor o cajero **quiero** ver las transferencias enviadas y recibidas **para** hacer seguimiento.
**Cuando** se solicita `GET /api/v1/finance/cash-transfers?status=&box_id=&direction=sent|received&date_from=&date_to=`, **el sistema debe** listar las transferencias de la sede activa (como origen o destino).
**Módulo:** `Finance`
**Permisos:** `cash_transfers.view` (todas) o `cash_transfers.create` (las de cajas que opera)
**Criterios de aceptación:**
- [ ] Cada fila: número, origen (caja y sesión), destino (caja y sesión), monto, motivo, estado, creado por, aprobado/rechazado por y fechas.
- [ ] UI: `/finance/cash/transfers` con pestañas Enviadas · Recibidas · Pendientes de aprobar; badge con el número de pendientes en el menú.

## 4. Reglas de negocio e invariantes
- Una transferencia aprobada tiene exactamente dos movimientos de caja (salida y entrada) por el mismo monto, en una sola transacción.
- Transiciones: `pending → approved | rejected | cancelled`; ninguna otra.
- Las transferencias `pending` reservan efectivo de la sesión de origen para validar nuevas salidas.
- Una transferencia aprobada no se anula; se corrige con una transferencia inversa.

## 5. Requisitos no funcionales
- Bloqueos sobre ambas sesiones en orden determinista (por `id`) para evitar deadlocks.
- Auditoría de cambios de estado.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Depósitos de caja a banco y retiros de banco a caja → spec 020.
- Transferencias entre cuentas bancarias → spec 020.
- Voucher PDF de la transferencia → spec futuro (infraestructura PDF del spec 022).
- Transferencias en moneda extranjera → spec futuro.

## 7. Open Questions
- [ ] ¿Debe aprobar solo alguien que opere la caja de destino (y no cualquier supervisor)? Propuesta: el permiso basta. — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `CashTransfersApiTest` en verde, incluido el escenario de sesión de origen cerrada antes de aprobar.
- El efectivo esperado de ambas sesiones cuadra tras aprobar.
