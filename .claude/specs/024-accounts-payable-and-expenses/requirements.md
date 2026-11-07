# 024 · accounts-payable-and-expenses — Requirements
> **Spec:** 024 · **Alcance:** fullstack · **Módulo:** Purchases · **Prioridad:** media · **Depende de specs:** 017, 018, 020, 021, 022, 023
> **Status:** Done
> **Última actualización:** 2026-10-20
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 4)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Specs 018/020 — ledgers de caja y banco. Spec 022 — `DocumentTotalsCalculator`, OC. Spec 023 — recepciones.
> - Origen Multinort: `create_purchase_invoices_table.php` (`paid`, `pending`, `status`), `create_expenses_table.php` (+ `expense_category_id`, `beneficiary_*`, `purchase_invoice_id`, `account_id`, `payment_method_id`, `cash_sessions_id`), `Egreso/ExpenseController.php`.
>
---
## 1. Contexto de Negocio
La empresa registra las **facturas de sus proveedores** (por compras con OC o por **gastos directos**: alquiler, servicios, movilidad), controla lo que debe y cuándo vence (**cuentas por pagar** con cuotas) y registra los **pagos** desde caja o desde banco, total o parcialmente, aplicándolos a una o varias facturas.

En Multinort, `purchase_invoices` lleva `paid`/`pending` y estado, y `expenses` funciona a la vez como pago y como gasto: tiene beneficiario polimórfico, categoría de gasto, sesión de caja, método de pago y vínculo a la factura. Aquí se separan **documento (factura)** y **pago**, con cuotas y asignaciones explícitas.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Asistente contable | `manager` / rol contable | JWT · `supplier_invoices.view|create` |
| Tesorero / Cajero | `manager` / `user` con caja | JWT · `supplier_payments.view|create` |
| Supervisor | `manager` | JWT · `supplier_invoices.cancel`, `supplier_payments.cancel` |

## 3. User Stories (formato EARS)
### US-1 — Registrar la factura de un proveedor por compras
**Como** asistente contable **quiero** registrar la factura del proveedor vinculándola a la OC y sus recepciones **para** reconocer la deuda.
**Cuando** se envía `POST /api/v1/purchases/supplier-invoices` con `kind = purchase`, **el sistema debe** crear la factura `FP-000001` en estado `pending`, con líneas, totales calculados en el backend, cuotas y vínculos.
**Módulo:** `Purchases` (`/api/v1/purchases/supplier-invoices`)
**Permisos:** `supplier_invoices.create`
**Criterios de aceptación:**
- [ ] Cabecera: `provider_id`, `document_type` (`invoice`, `receipt`, `fee_receipt`, `sales_note`, `other`), `series`, `document_number`, `issue_date`, `due_date` (por defecto `issue_date + credit_days` del proveedor), `currency_id`, `exchange_rate`, `prices_include_tax`, `purchase_order_id` opcional, `receipt_ids[]` opcional, `notes`, `attachment` opcional.
- [ ] `(provider_id, document_type, series, document_number)` único entre facturas no anuladas → 422.
- [ ] Las líneas se pueden precargar desde las recepciones con `GET /api/v1/purchases/supplier-invoices/prefill?receipt_ids[]=`.
- [ ] Si el total difiere del valor de las recepciones vinculadas en más de `purchases.invoice_match_tolerance_percent`, la respuesta incluye `meta.warnings` (no bloquea).
- [ ] Una recepción ya vinculada a otra factura no anulada → 422.
- [ ] `installments` opcional (`[{ due_date, amount }]` cuya suma = total); si no se envía, se crea una cuota única al `due_date`.

### US-2 — Registrar un gasto directo
**Como** asistente contable o cajero **quiero** registrar un gasto (con o sin proveedor) y, opcionalmente, pagarlo en el acto **para** no crear una OC por gastos menores.
**Cuando** se envía `POST /api/v1/purchases/supplier-invoices` con `kind = expense`, `concept_id` (concepto de egreso) y, si `pay_now = true`, los datos del pago, **el sistema debe** crear la factura y, en la misma transacción, el pago completo.
**Módulo:** `Purchases`
**Permisos:** `supplier_invoices.create` (+ `supplier_payments.create` si `pay_now`)
**Criterios de aceptación:**
- [ ] `provider_id` opcional en gastos; si falta, `counterparty_name` es obligatorio y `document_type = other`.
- [ ] `concept_id` de dirección `expense`, no de sistema.
- [ ] UI: botón "Registrar gasto" en la pantalla de caja (spec 018), con `pay_now` y la sesión actual preseleccionadas.

### US-3 — Pagar facturas (total o parcial)
**Como** tesorero **quiero** registrar un pago a un proveedor y aplicarlo a una o varias cuotas **para** reducir la deuda.
**Cuando** se envía `POST /api/v1/purchases/supplier-payments` con `{ provider_id, payment_date, currency_id, payment_method_id, cash_session_id?, reference?, notes?, allocations: [{ installment_id, amount }] }`, **el sistema debe** crear el pago `PP-000001`, registrar la salida en caja o banco según el `settlement` del método (concepto `SUPPLIER_PAYMENT`) y actualizar cuotas y facturas.
**Módulo:** `Purchases` (`/api/v1/purchases/supplier-payments`)
**Permisos:** `supplier_payments.create`
**Criterios de aceptación:**
- [ ] Todas las cuotas son del mismo proveedor y moneda que el pago; `amount ≤ saldo` de cada cuota → 422 en otro caso.
- [ ] Método de caja: `cash_session_id` obligatorio (sesión abierta y operable) y moneda base; método bancario: la cuenta del método debe ser de la moneda del pago.
- [ ] Facturas: `paid_amount`, `balance` y estado (`pending`, `partially_paid`, `paid`) se actualizan.
- [ ] `GET /api/v1/purchases/supplier-payments/open-installments?provider_id=&currency_id=` lista las cuotas con saldo ordenadas por vencimiento.

### US-4 — Anular facturas y pagos
**Como** supervisor **quiero** anular un pago o una factura registrados por error **para** corregir la deuda.
**Cuando** se envía `POST /api/v1/purchases/supplier-payments/{id}/cancel` o `POST /api/v1/purchases/supplier-invoices/{id}/cancel` con `{ reason }`, **el sistema debe** anular el pago revirtiendo el movimiento de caja o banco y restaurando saldos; o anular la factura si no tiene pagos vigentes.
**Módulo:** `Purchases`
**Permisos:** `supplier_payments.cancel`, `supplier_invoices.cancel`
**Criterios de aceptación:**
- [ ] Pago cuya sesión de caja o periodo bancario ya cerró → 422.
- [ ] Factura con pagos no anulados → 422 "Anule primero los pagos".
- [ ] Anular la factura libera sus recepciones (vuelven a poder anularse en el spec 023).

### US-5 — Cuentas por pagar: consulta, vencimientos y antigüedad
**Como** tesorero **quiero** ver qué debo, a quién y cuándo vence **para** planificar pagos.
**Cuando** se solicita `GET /api/v1/purchases/accounts-payable?provider_id=&status=&overdue=&due_from=&due_to=`, `GET /api/v1/purchases/accounts-payable/aging` o `GET /api/v1/purchases/providers/{id}/statement?date_from=&date_to=`, **el sistema debe** listar cuotas con saldo, el resumen de antigüedad por proveedor (corriente, 1–30, 31–60, 61–90, > 90 días) o el estado de cuenta (facturas, pagos y saldo acumulado).
**Módulo:** `Purchases`
**Permisos:** `supplier_invoices.view`
**Criterios de aceptación:**
- [ ] Montos por moneda (no se suman monedas distintas).
- [ ] `is_overdue` = `due_date < hoy` y saldo > 0.
- [ ] UI: `/purchases/accounts-payable` con pestañas Cuotas por pagar · Antigüedad · Pagos y botón "Pagar seleccionadas".

## 4. Reglas de negocio e invariantes
- `invoice.balance = total − Σ asignaciones de pagos no anulados`; `installment.balance` análogo; Σ cuotas = total.
- Todo pago registra exactamente un movimiento en caja **o** en banco por su monto, en la transacción del pago.
- Moneda del pago = moneda de todas las cuotas asignadas.
- Facturas y pagos no se eliminan; se anulan.

## 5. Requisitos no funcionales
- Aging de 5 000 cuotas en < 500 ms.
- Adjuntos en disco privado (regla de `file-uploads`).
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Notas de crédito y débito de proveedores → spec futuro.
- Detracciones, retenciones y percepciones → spec futuro.
- Pagos en moneda distinta de la factura (con diferencia de cambio) → spec futuro.
- Programación de pagos y aprobación de lotes de pago → spec futuro.
- Registro de compras (PLE) → spec futuro.

## 7. Open Questions
- [ ] ¿Se debe bloquear el registro de una factura sin recepción cuando la OC es de productos? Propuesta: solo advertencia. — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `SupplierInvoicesApiTest`, `SupplierPaymentsApiTest` y `AccountsPayableApiTest` en verde.
- Ciclo completo OC → recepción → factura → pago parcial → pago final sin descuadres.
