# 018 · cash-registers-and-sessions — Requirements
> **Spec:** 018 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** media · **Depende de specs:** 002, 011, 014, 017
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 3)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 017 — métodos de pago y conceptos. Spec 011 — sedes. Spec 010 — `finance.cash_difference_tolerance`.
> - Origen Multinort: `create_boxes_table.php`, `create_role_box_table.php`, `create_cash_sessions_table.php`, `create_transactions_table.php`, `create_cash_count_details_table.php`, `create_cash_session_payment_summaries_table.php`, `Caja/CashSessionController.php`.
>
---
## 1. Contexto de Negocio
Cada sede tiene una o más **cajas** (puntos de cobro). El cajero **abre** una sesión con un fondo inicial, registra cobros y pagos, y al final del turno **cierra** contando el efectivo por denominación (**arqueo**). El sistema calcula lo esperado y la diferencia (sobrante o faltante); si supera la tolerancia, un supervisor debe **revisar** el cierre. El supervisor también **consulta** cualquier sesión con su detalle por método de pago y por concepto.

En Multinort, una caja pertenece a una sede, el acceso se da por rol (`role_box`) y la sesión guarda montos de apertura, ingresos, egresos, esperado, contado, diferencia y un estado de autorización. Los movimientos están en `transactions` (y también en `incomes`/`expenses`, que están acoplados a ventas y cuotas). Aquí se unifica todo en un **ledger de caja** (`cash_movements`) por el que pasan cobros de ventas (specs 027/028), pagos a proveedores (spec 024), transferencias (spec 019) y movimientos manuales.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Cajero | Usuario con un rol asignado a la caja | JWT · `cash_sessions.operate` |
| Supervisor | `manager` | JWT · `cash_sessions.view`, `cash_sessions.review`, `cash_movements.cancel` |
| Administrador | `admin` | JWT · `boxes.view|manage` + todos los anteriores; opera cualquier caja |

## 3. User Stories (formato EARS)
### US-1 — Cajas por sede y acceso por rol
**Como** administrador **quiero** registrar las cajas de cada sede y qué roles pueden operarlas **para** controlar quién maneja dinero.
**Cuando** se invoca el CRUD `/api/v1/finance/boxes` o `PUT /api/v1/finance/boxes/{id}/roles` con `{ role_ids }`, **el sistema debe** gestionar cajas (`branch_id`, `code` único, `name`, `description`, `is_active`) y sincronizar sus roles.
**Módulo:** `Finance` (`/api/v1/finance/boxes`)
**Permisos:** `boxes.view|manage`
**Criterios de aceptación:**
- [ ] No se desactiva ni se elimina una caja con sesión abierta → 422; no se elimina una caja con sesiones → 422.
- [ ] `GET /api/v1/finance/boxes/available` devuelve las cajas activas de la sede activa que el usuario puede operar (admin: todas), con `current_session { id, number, opened_by, opened_at } | null`.
- [ ] Eliminar una sede con cajas → 422 (se agrega a `BranchService::delete()`).

### US-2 — Abrir una sesión de caja
**Como** cajero **quiero** abrir la caja con un fondo inicial **para** empezar a cobrar.
**Cuando** se envía `POST /api/v1/finance/cash-sessions` con `{ box_id, opening_amount, opening_notes? }`, **el sistema debe** crear una sesión `open` con número `CJ-000001`, `opened_by` y `opened_at`.
**Módulo:** `Finance` (`/api/v1/finance/cash-sessions`)
**Permisos:** `cash_sessions.operate`
**Criterios de aceptación:**
- [ ] La caja ya tiene una sesión abierta → 409 "La caja ya tiene una sesión abierta (CJ-…)".
- [ ] Usuario sin rol asignado a la caja (no admin) → 403.
- [ ] Caja de una sede distinta de la activa → 403.
- [ ] `opening_amount ≥ 0`; se sugiere el monto contado en el último cierre de la caja (`GET /boxes/{id}/suggested-opening`).

### US-3 — Registrar movimientos manuales
**Como** cajero **quiero** registrar ingresos y egresos varios (pago de un servicio, ingreso por otro concepto) **para** que la caja refleje todo el dinero.
**Cuando** se envía `POST /api/v1/finance/cash-sessions/{id}/movements` con `{ direction, concept_id, payment_method_id, amount, reference?, description, counterparty_name? }`, **el sistema debe** registrar el movimiento en el ledger de caja.
**Módulo:** `Finance`
**Permisos:** `cash_sessions.operate`
**Criterios de aceptación:**
- [ ] Sesión no abierta → 422; concepto de sistema o de dirección distinta → 422; método con `settlement = bank_account` → 422 (los movimientos manuales de caja son en efectivo).
- [ ] Un egreso que deja el efectivo esperado en negativo → 422 "Efectivo insuficiente en caja".
- [ ] `amount > 0` con 2 decimales; `reference` obligatoria si el método la requiere.
- [ ] `POST /api/v1/finance/cash-movements/{id}/cancel` con `{ reason }` anula un movimiento **manual** de una sesión abierta registrando su reversión (`cash_movements.cancel`); los movimientos generados por otros documentos se anulan desde su documento → 422.

### US-4 — Consultar la caja
**Como** cajero o supervisor **quiero** ver el estado de una sesión con totales por método de pago y por concepto **para** saber cuánto debería haber.
**Cuando** se solicita `GET /api/v1/finance/cash-sessions/{id}` o `GET /api/v1/finance/cash-sessions/{id}/movements`, **el sistema debe** devolver la cabecera, el resumen y los movimientos paginados.
**Módulo:** `Finance`
**Permisos:** `cash_sessions.operate` (sesiones de sus cajas) o `cash_sessions.view` (todas)
**Criterios de aceptación:**
- [ ] `summary`: `opening_amount`, `cash_in`, `cash_out`, `expected_cash`, `by_payment_method[] { method, in, out, net }`, `by_concept[] { concept, direction, total }`.
- [ ] `GET /api/v1/finance/cash-sessions` lista el historial con filtros `box_id`, `status`, `review_status`, `opened_by`, `date_from`, `date_to` (sede activa).
- [ ] UI: `/finance/cash` (operación diaria: selección de caja, apertura, tarjetas de resumen, movimientos, botón cerrar) y `/finance/cash/sessions` (historial y detalle).

### US-5 — Cerrar con arqueo
**Como** cajero **quiero** cerrar la caja contando el efectivo por denominación **para** cuadrar el turno.
**Cuando** se envía `POST /api/v1/finance/cash-sessions/{id}/close` con `{ count: [{ denomination, quantity }], closing_notes? }` (o `{ counted_cash }` sin detalle), **el sistema debe** calcular `counted_cash`, `expected_cash` y `difference = counted − expected`, cerrar la sesión y fijar `review_status`.
**Módulo:** `Finance`
**Permisos:** `cash_sessions.operate`
**Criterios de aceptación:**
- [ ] Las denominaciones válidas vienen del parámetro `finance.cash_denominations`; otra → 422.
- [ ] `|difference| ≤ finance.cash_difference_tolerance` → `review_status = approved` automáticamente; si no → `pending` y `closing_notes` obligatorio.
- [ ] Tras cerrar, ningún movimiento se registra en la sesión → 422.
- [ ] UI: diálogo de arqueo con grilla de denominaciones, totales en vivo y diferencia en color.

### US-6 — Revisar cierres con diferencia
**Como** supervisor **quiero** aprobar o rechazar cierres con diferencia **para** dejar constancia de sobrantes y faltantes.
**Cuando** se envía `POST /api/v1/finance/cash-sessions/{id}/review` con `{ decision: approved|rejected, notes }`, **el sistema debe** registrar la revisión (`reviewed_by`, `reviewed_at`) de una sesión cerrada en `pending`.
**Módulo:** `Finance`
**Permisos:** `cash_sessions.review`
**Criterios de aceptación:**
- [ ] Sesión no `pending` → 422; el revisor no puede ser quien cerró la sesión → 422.
- [ ] `notes` obligatorio al rechazar.
- [ ] UI: bandeja "Cierres por revisar" en el historial (filtro `review_status = pending`).

## 4. Reglas de negocio e invariantes
- Una caja tiene como máximo una sesión `open`.
- Todo movimiento de dinero en caja pasa por `CashLedgerService` y pertenece a una sesión abierta.
- `expected_cash = opening_amount + Σ entradas en efectivo − Σ salidas en efectivo` (solo métodos con `settlement = cash_box`).
- Los movimientos de caja son inmutables; la anulación se registra como reversión.
- El efectivo esperado nunca queda negativo por un egreso.
- Las diferencias de arqueo se registran en la sesión; su contabilización como movimiento (`CASH_OVERAGE`/`CASH_SHORTAGE`) se hace al aprobar la revisión con un movimiento informativo que no altera el esperado de la sesión cerrada.

## 5. Requisitos no funcionales
- Registrar un movimiento en < 100 ms; resumen de una sesión con 2 000 movimientos en < 300 ms.
- Auditoría de cajas, sesiones (apertura, cierre, revisión) y anulaciones.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Transferencias entre cajas → spec 019.
- Depósitos de caja a banco y retiros → spec 020.
- Cobros de ventas y cuentas por cobrar → specs 027/028; pagos a proveedores → spec 024.
- Cajas en moneda extranjera → spec futuro.
- Autorización previa de egresos por monto (`transactional_permissions` de Multinort) → spec futuro.
- Reporte PDF del cierre → spec futuro (reutilizará la infraestructura PDF del spec 022).

## 7. Open Questions
- [ ] ¿Un mismo usuario puede tener sesiones abiertas en dos cajas a la vez? Propuesta: sí, la regla es por caja. — Responsable: Luis Acuña.
- [ ] ¿Se permite reabrir una sesión cerrada por error? Propuesta: no en esta entrega. — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `BoxesApiTest`, `CashSessionsApiTest`, `CashMovementsApiTest` y `CashLedgerServiceTest` en verde.
- Ninguna escritura a `cash_movements` fuera de `CashLedgerService` (revisión de código).
