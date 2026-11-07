# 020 · bank-sessions-and-transfers — Requirements
> **Spec:** 020 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** media · **Depende de specs:** 017, 018, 019
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 3)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 017 — `BankAccount`, `ExchangeRateService`, conceptos. Spec 018 — `CashLedgerService`.
> - Origen Multinort: `create_account_sessions_table.php` (+ `true_balance`), `create_deposits_table.php` (+ `state`, comisiones, liquidaciones), `Banco/AccountSessionController.php`, `Banco/DepositController.php`.
>
---
## 1. Contexto de Negocio
La empresa necesita conocer el **saldo de cada cuenta bancaria** y sus **movimientos**: cobros con tarjeta y transferencia (specs 027/028), pagos a proveedores (spec 024), **depósitos** de efectivo desde caja, **retiros** a caja, **transferencias entre cuentas** propias y cargos o abonos del banco (comisiones, ITF, intereses). Además, cada cierto tiempo se **concilia** con el estado de cuenta: se cierra un periodo comparando el saldo del sistema con el del banco.

En Multinort, `account_sessions` abre y cierra la cuenta con saldo inicial, esperado, real (`true_balance`) y diferencia. `deposits` registra entradas y salidas con número de operación, adjunto, estado de uso y comisiones de pasarela. Aquí se generaliza con un **ledger bancario** y **periodos de conciliación** que se encadenan: al cerrar uno se abre el siguiente.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Tesorero | `manager` | JWT · `bank_movements.view`, `bank_movements.create`, `bank_operations.create|cancel`, `bank_periods.close` |
| Cajero | `user` | JWT · `bank_operations.create` (depositar el efectivo de su caja) |
| Módulos internos | Ventas, CxC, CxP | `BankLedgerService` (sin HTTP) |

## 3. User Stories (formato EARS)
### US-1 — Ledger bancario (interno)
**Como** módulo de ventas, cobranzas o pagos **quiero** registrar abonos y cargos en una cuenta bancaria **para** mantener su saldo.
**Cuando** un módulo invoca `BankLedgerService::register|reverse`, **el sistema debe** bloquear la cuenta, registrar el movimiento en el periodo abierto con `balance_after` y actualizar `bank_accounts.balance`, en la transacción del documento de origen.
**Módulo:** `Finance` (servicio interno)
**Permisos:** —
**Criterios de aceptación:**
- [ ] Cuenta inactiva → 422; cuenta sin periodo abierto → 409 "La cuenta bancaria no tiene un periodo abierto".
- [ ] Los cargos pueden dejar saldo negativo solo si el parámetro `finance.allow_negative_bank_balance = true` (por defecto `false` → 422).
- [ ] El monto se expresa en la moneda de la cuenta.

### US-2 — Periodos de conciliación (apertura y cierre)
**Como** tesorero **quiero** abrir la cuenta con su saldo inicial y cerrar periodos comparando con el estado de cuenta **para** conciliar.
**Cuando** se envía `POST /api/v1/finance/bank-accounts/{id}/periods` con `{ opening_balance, opened_at }` (primera apertura) o `POST /api/v1/finance/bank-periods/{id}/close` con `{ statement_balance, closed_at, notes? }`, **el sistema debe** abrir el primer periodo, o cerrar el periodo con `expected_balance` (saldo del ledger), `statement_balance` y `difference`, y abrir automáticamente el siguiente con `opening_balance = expected_balance`.
**Módulo:** `Finance` (`/api/v1/finance/bank-periods`)
**Permisos:** `bank_periods.close`
**Criterios de aceptación:**
- [ ] Solo un periodo `open` por cuenta; la primera apertura solo procede si la cuenta no tiene periodos → 422 en otro caso.
- [ ] `difference ≠ 0` exige `notes` → 422 si faltan.
- [ ] `GET /api/v1/finance/bank-accounts/{id}/periods` lista el historial.
- [ ] La cuenta bancaria creada en el spec 017 muestra la acción "Abrir cuenta" mientras no tenga periodos.

### US-3 — Consulta de banco
**Como** tesorero **quiero** ver saldos y movimientos por cuenta y rango de fechas **para** controlar el banco.
**Cuando** se solicita `GET /api/v1/finance/bank-accounts/summary` o `GET /api/v1/finance/bank-accounts/{id}/movements?date_from=&date_to=&direction=&concept_id=&search=`, **el sistema debe** devolver el saldo actual de cada cuenta, o el saldo inicial del rango, los movimientos con saldo acumulado y el saldo final.
**Módulo:** `Finance`
**Permisos:** `bank_movements.view`
**Criterios de aceptación:**
- [ ] `summary`: por cuenta `{ bank, alias, currency, balance, current_period { number, opened_at } }`, agrupado por moneda con totales.
- [ ] Movimientos con `operation_number`, concepto, origen (`source_number`), contraparte, `voucher_url` y `balance_after`.
- [ ] UI: `/finance/banks` con tarjetas de saldo y `/finance/banks/[id]` con movimientos, filtros, periodos y acciones.

### US-4 — Movimientos manuales del banco
**Como** tesorero **quiero** registrar comisiones, ITF, intereses u otros abonos y cargos con su constancia **para** reflejar el estado de cuenta.
**Cuando** se envía `POST /api/v1/finance/bank-accounts/{id}/movements` (multipart) con `{ direction, concept_id, amount, operation_number?, movement_date, description, counterparty_name?, voucher? }`, **el sistema debe** registrar el movimiento manual en el ledger.
**Módulo:** `Finance`
**Permisos:** `bank_movements.create`
**Criterios de aceptación:**
- [ ] Concepto no de sistema y de la misma dirección → si no, 422.
- [ ] `voucher` opcional: pdf/jpg/png hasta 5 MB, en el disco privado `local` (descarga autenticada).
- [ ] `POST /api/v1/finance/bank-movements/{id}/cancel` con `{ reason }` revierte un movimiento manual del periodo abierto (`bank_operations.cancel`); en un periodo cerrado → 422.

### US-5 — Depósito de caja a banco y retiro de banco a caja
**Como** cajero o tesorero **quiero** depositar el efectivo de una caja en el banco, o retirar efectivo del banco a una caja **para** que ambos saldos queden registrados.
**Cuando** se envía `POST /api/v1/finance/bank-operations` con `{ type: deposit|withdrawal, cash_session_id, bank_account_id, amount, operation_number, operation_date, notes?, voucher? }`, **el sistema debe** crear la operación `BO-000001` y registrar en una transacción la salida (o entrada) en caja (`BANK_DEPOSIT`/`BANK_WITHDRAWAL`) y la entrada (o salida) en el banco.
**Módulo:** `Finance` (`/api/v1/finance/bank-operations`)
**Permisos:** `bank_operations.create` (+ operar la caja)
**Criterios de aceptación:**
- [ ] La cuenta debe estar en la moneda base (las cajas operan solo en moneda base) → 422 en otro caso.
- [ ] El depósito valida el efectivo disponible de la sesión (`availableCash`).
- [ ] `operation_number` obligatorio para depósitos.

### US-6 — Transferencia entre cuentas propias
**Como** tesorero **quiero** transferir entre cuentas de la empresa, incluso de distinta moneda **para** reflejar el movimiento en ambas.
**Cuando** se envía `POST /api/v1/finance/bank-operations` con `{ type: transfer, bank_account_id, target_bank_account_id, amount, exchange_rate?, operation_number, operation_date, notes? }`, **el sistema debe** registrar la salida en la cuenta de origen y la entrada en la de destino; si las monedas difieren, `target_amount = amount × exchange_rate` (o la conversión con `ExchangeRateService` si no se envía).
**Módulo:** `Finance`
**Permisos:** `bank_operations.create`
**Criterios de aceptación:**
- [ ] Misma cuenta → 422; monedas distintas sin tipo de cambio disponible → 422.
- [ ] `POST /api/v1/finance/bank-operations/{id}/cancel` revierte ambos lados (y la caja, si es depósito o retiro con la sesión aún abierta) → si alguna parte ya está en un periodo o sesión cerrada, 422.
- [ ] UI: diálogo "Nueva operación" con tipo (Depósito · Retiro · Transferencia) y campos dinámicos.

## 4. Reglas de negocio e invariantes
- `bank_accounts.balance` = `opening_balance` del primer periodo + Σ abonos − Σ cargos; solo lo actualiza `BankLedgerService`.
- Una cuenta tiene como máximo un periodo `open`; los periodos se encadenan sin huecos.
- Los movimientos bancarios son inmutables; se anulan por reversión dentro del periodo abierto.
- Una operación (depósito, retiro o transferencia) registra todos sus lados en una sola transacción.
- La moneda de una cuenta con movimientos no cambia (activa el hook del spec 017).

## 5. Requisitos no funcionales
- Consulta de un año de movimientos de una cuenta en < 300 ms (índice `bank_account_id, movement_date`).
- Los comprobantes adjuntos no son públicos: se descargan con `GET /api/v1/finance/bank-movements/{id}/voucher` autenticado.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Importación del estado de cuenta (CSV/OFX) y conciliación partida por partida → spec futuro.
- Comisiones de pasarela, liquidaciones de POS y "estado de uso" de depósitos (Multinort) → spec futuro.
- Cheques y cartas fianza → spec futuro.
- Cobros con tarjeta/transferencia → specs 027/028; pagos a proveedores → spec 024.

## 7. Open Questions
- [ ] ¿Los depósitos de caja deben requerir aprobación del tesorero? Propuesta: no; se anulan si hay error. — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `BankLedgerServiceTest`, `BankPeriodsApiTest`, `BankMovementsApiTest` y `BankOperationsApiTest` en verde.
- `balance` de cada cuenta igual al recalculado desde el ledger (comando `finance:verify-bank-balances`).
