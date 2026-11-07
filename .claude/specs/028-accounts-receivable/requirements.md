# 028 · accounts-receivable — Requirements
> **Spec:** 028 · **Alcance:** fullstack · **Módulo:** Sales · **Prioridad:** media · **Depende de specs:** 018, 020, 024, 025, 027
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 5)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 027 — `Sale` (`payment_condition = credit`, `balance`, `due_date`), evento `SaleIssued`, `CreditPolicyService`. Spec 024 — `MoneySettlementService`, patrón de cuotas y asignaciones. Spec 025 — crédito del cliente.
> - Origen Multinort: `create_quotes_table.php` (+ `client_id`, `frequency`), `create_incomes_table.php` (`quote_id`, `sale_id`), `Caja/IncomeController.php`.
>
---
## 1. Contexto de Negocio
Las ventas al crédito (spec 027) dejan un saldo que el cliente paga en **cuotas**. Cobranzas registra los **cobros** (en caja o banco), que pueden cubrir una o varias cuotas de una o varias ventas del mismo cliente. Además, necesita ver **qué está vencido**, la **antigüedad** de la deuda y el **estado de cuenta** de cada cliente.

En Multinort, `quotes` guarda cuotas por venta con número, monto, vencimiento, estado y frecuencia, y los cobros son `incomes` con `quote_id`/`sale_id` y método de pago. Aquí se sigue el mismo patrón que cuentas por pagar (spec 024): cuotas, cobranzas y asignaciones, liquidadas con `MoneySettlementService`.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Cajero / Cobrador | `user` con caja | JWT · `collections.create`, `receivables.view` |
| Jefe de créditos | `manager` | JWT · `receivables.view`, `collections.cancel`, `receivables.reschedule` |

## 3. User Stories (formato EARS)
### US-1 — Generar cuotas al vender al crédito
**Como** vendedor **quiero** definir en cuántas cuotas paga el cliente **para** que cobranzas sepa qué cobrar y cuándo.
**Cuando** se emite una venta al crédito (evento `SaleIssued`), **el sistema debe** generar sus cuotas por el saldo de la venta: las indicadas en `installments: [{ due_date, amount }]` del request de venta, o según `installments_count` + `installment_interval_days`, o una cuota única al `due_date`.
**Módulo:** `Sales` (listener de `SaleIssued`)
**Permisos:** — (se autoriza con la venta)
**Criterios de aceptación:**
- [ ] Σ cuotas = `balance` de la venta (validado en `StoreSaleRequest`, ampliado por este spec) → 422 si no cuadra.
- [ ] Ninguna cuota vence antes de `issue_date` ni después de `issue_date + credit_days` del cliente (salvo `receivables.reschedule`).
- [ ] Las cuotas se numeran 1..N.

### US-2 — Registrar una cobranza
**Como** cobrador **quiero** registrar un pago del cliente y aplicarlo a sus cuotas **para** reducir su deuda.
**Cuando** se envía `POST /api/v1/sales/collections` con `{ client_id, payment_date, payment_method_id, cash_session_id?, reference?, notes?, allocations: [{ installment_id, amount }] }`, **el sistema debe** crear la cobranza `CB-000001`, registrar el ingreso en caja o banco (concepto `RECEIVABLE_COLLECTION`) y actualizar cuotas y ventas (`paid_amount`, `balance`).
**Módulo:** `Sales` (`/api/v1/sales/collections`)
**Permisos:** `collections.create`
**Criterios de aceptación:**
- [ ] Las cuotas son del cliente, de ventas `issued` y en la moneda del cobro; `amount ≤ saldo` de cada cuota → 422 en otro caso.
- [ ] Con `auto_allocate: true` y `amount`, el sistema asigna a las cuotas más antiguas primero.
- [ ] Recibo PDF: `GET /api/v1/sales/collections/{id}/pdf` (A4 o ticket) con las cuotas pagadas y el saldo restante.
- [ ] UI: botón "Cobrar" en la pantalla de caja y en cuentas por cobrar, con `PaymentMethodFields`.

### US-3 — Anular una cobranza
**Como** jefe de créditos **quiero** anular una cobranza registrada por error **para** restaurar la deuda.
**Cuando** se envía `POST /api/v1/sales/collections/{id}/cancel` con `{ reason }`, **el sistema debe** revertir el movimiento de caja o banco y restaurar los saldos de cuotas y ventas.
**Módulo:** `Sales`
**Permisos:** `collections.cancel`
**Criterios de aceptación:**
- [ ] Sesión de caja o periodo bancario cerrado → 422.

### US-4 — Reprogramar cuotas
**Como** jefe de créditos **quiero** cambiar fechas y montos de las cuotas pendientes de una venta **para** acordar un nuevo cronograma.
**Cuando** se envía `PUT /api/v1/sales/sales/{id}/installments` con la nueva lista de cuotas pendientes, **el sistema debe** reemplazar las cuotas sin pagos manteniendo las pagadas o parcialmente pagadas.
**Módulo:** `Sales`
**Permisos:** `receivables.reschedule`
**Criterios de aceptación:**
- [ ] Σ nuevas cuotas = saldo pendiente no asignado a cuotas pagadas o parciales → 422 si no.
- [ ] Queda registro en el activity log de la venta (cronograma anterior y nuevo).

### US-5 — Cuentas por cobrar, antigüedad y estado de cuenta
**Como** jefe de créditos **quiero** ver la deuda por cliente, lo vencido y el historial **para** gestionar la cobranza.
**Cuando** se solicita `GET /api/v1/sales/receivables?client_id=&overdue=&due_from=&due_to=&seller_id=`, `GET /api/v1/sales/receivables/aging` o `GET /api/v1/clients/{id}/statement?date_from=&date_to=`, **el sistema debe** listar cuotas con saldo, el resumen por antigüedad (corriente, 1–30, 31–60, 61–90, > 90) por cliente y moneda, o el estado de cuenta (ventas al crédito como cargo; cobranzas y notas de crédito como abono; saldo acumulado).
**Módulo:** `Sales`
**Permisos:** `receivables.view`
**Criterios de aceptación:**
- [ ] `GET /api/v1/clients/{id}/statement/pdf` descarga el estado de cuenta en PDF.
- [ ] El detalle del cliente (spec 025) muestra deuda, crédito disponible, cuotas vencidas y la pestaña "Estado de cuenta".
- [ ] UI: `/sales/receivables` con pestañas Cuotas · Antigüedad · Cobranzas.

## 4. Reglas de negocio e invariantes
- `sale.balance = total − Σ pagos de la venta − Σ cobranzas asignadas no anuladas − Σ notas de crédito aplicadas (spec 029)`; Σ saldos de cuotas = `sale.balance`.
- Toda cobranza registra exactamente un movimiento en caja o banco, en su transacción.
- Una venta con cobranzas vigentes no se anula (activa el hook `hasCollections()` del spec 027).
- Al anular una venta al crédito sin cobranzas, sus cuotas pasan a `cancelled`.

## 5. Requisitos no funcionales
- Aging de 10 000 cuotas en < 500 ms.
- Auditoría de cobranzas y reprogramaciones.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Intereses moratorios y penalidades → spec futuro.
- Letras y protestos → spec futuro.
- Recordatorios automáticos (correo/WhatsApp) de cuotas por vencer → spec futuro.
- Saldo a favor del cliente (anticipos) → spec futuro.
- Notas de crédito aplicadas a la deuda → spec 029.

## 7. Open Questions
- [ ] ¿Se bloquea la venta al crédito a clientes con cuotas vencidas? Propuesta: parámetro `sales.block_credit_when_overdue` (por defecto `true`). — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `ReceivableInstallmentsTest`, `CollectionsApiTest` y `ReceivablesReportsApiTest` en verde.
- Ciclo venta al crédito → cobranza parcial → cobranza final sin descuadres entre `sale.balance` y cuotas.
