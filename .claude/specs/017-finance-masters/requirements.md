# 017 · finance-masters — Requirements
> **Spec:** 017 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** media · **Depende de specs:** 010, 011
> **Status:** Draft
> **Última actualización:** 2026-10-15
> **Autores:** Luis Acuña Guevara
> **Origen:** Migración Multinort — [docs/plan_migracion_modulos_multinort.md](../../../docs/plan_migracion_modulos_multinort.md) (Fase 3)
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (ej. UUIDs en claves primarias).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints de la API REST.
> - Spec 010 — `default_currency_code`. Spec 011 — sedes.
> - Origen Multinort: `create_currencies_table.php`, `create_exchange_rates_table.php`, `create_banks_table.php`, `create_bank_accounts_table.php`, `create_payment_methods_table.php` (+ `bank_account_id`, `code_sunat`, `reference_type`), `create_accounts_table.php` (+ `code`), `create_expense_categories_table.php`.
>
---
## 1. Contexto de Negocio
Caja (spec 018), bancos (spec 020), cuentas por pagar (spec 024) y ventas (specs 027/028) necesitan maestros financieros comunes:
- **monedas** y **tipos de cambio** diarios;
- **bancos** y **cuentas bancarias** de la empresa;
- **métodos de pago** (efectivo, tarjeta, transferencia, billeteras como Yape/Plin), cada uno vinculado a la caja o a una cuenta bancaria;
- **plan de cuentas** para imputación contable;
- **conceptos de ingreso y egreso** que clasifican cada movimiento de dinero.

En Multinort existen todos. Sin embargo, `banks` mezcla banco y cuenta (con saldo); `payment_methods.reference_type` es un entero mágico (1 = cuenta, 2 = celular); `accounts` es una lista plana con `type` libre; y `expense_categories` solo cubre egresos. Aquí se normalizan.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Administrador / Contador | `admin`, `manager` | JWT · `finance_settings.view`, `finance_settings.manage` |
| Cajero | `user` | JWT · `exchange_rates.manage` (registrar el tipo de cambio del día), lectura de opciones |

## 3. User Stories (formato EARS)
### US-1 — Monedas
**Como** contador **quiero** gestionar las monedas con las que opera la empresa **para** registrar cuentas y operaciones en ellas.
**Cuando** se invoca el CRUD `/api/v1/finance/currencies`, **el sistema debe** gestionar monedas con `code` ISO 4217 único, `name`, `symbol`, `sunat_code`, `decimals` (0–4), `is_base` e `is_active`.
**Módulo:** `Finance` (`/api/v1/finance/currencies`)
**Permisos:** `finance_settings.view|manage`
**Criterios de aceptación:**
- [ ] Exactamente una moneda base. Desde este spec, `currencies.is_base` es la **fuente de verdad** y `company_settings.default_currency_code` es una copia que solo escribe `CurrencyService` (en la misma transacción al cambiar la base).
- [ ] `PUT /api/v1/configuration/company` (spec 010, ya implementado) deja de aceptar `default_currency_code`: si llega → 422 "La moneda base se gestiona en Finanzas › Monedas" (regla `prohibited`, para que un cliente desactualizado lo note en vez de perder el cambio en silencio). La pantalla de Empresa la muestra de solo lectura con un enlace a Monedas.
- [ ] Cambiar la moneda base cuando ya existen tipos de cambio o movimientos de dinero → 422 "No se puede cambiar la moneda base con operaciones registradas" (verificación ampliable por los specs 018–029).
- [ ] La moneda base no se desactiva ni se elimina → 422.
- [ ] El seeder crea PEN (base, S/, "PEN") y USD ($, "USD").

### US-2 — Tipo de cambio diario
**Como** cajero **quiero** registrar el tipo de cambio de compra y venta del día **para** convertir operaciones en moneda extranjera.
**Cuando** se envía `PUT /api/v1/finance/exchange-rates` con `{ currency_id, date, buy_rate, sell_rate }`, **el sistema debe** crear o reemplazar el tipo de cambio de esa moneda y fecha.
**Módulo:** `Finance` (`/api/v1/finance/exchange-rates`)
**Permisos:** `exchange_rates.manage` (registrar); `finance_settings.view` (historial)
**Criterios de aceptación:**
- [ ] Solo para monedas distintas de la base; `buy_rate`, `sell_rate > 0` con 4 decimales; `sell_rate ≥ buy_rate` → si no, 422.
- [ ] `GET /api/v1/finance/exchange-rates/current?currency_id=&date=` devuelve el tipo del día o el último anterior (con `is_stale = true` si no es del día).
- [ ] `GET /api/v1/finance/exchange-rates?currency_id=&date_from=&date_to=` lista el historial.
- [ ] UI: aviso en el layout cuando no hay tipo de cambio del día para USD y el usuario puede registrarlo.

### US-3 — Bancos y cuentas bancarias
**Como** contador **quiero** registrar bancos y las cuentas de la empresa **para** recibir depósitos, transferencias y pagos con tarjeta.
**Cuando** se invoca el CRUD `/api/v1/finance/banks` y `/api/v1/finance/bank-accounts`, **el sistema debe** gestionar bancos (`name`, `short_name`, `is_active`) y cuentas (`bank_id`, `currency_id`, `branch_id` opcional, `alias`, `account_number`, `cci`, `account_type` = `checking`|`savings`|`other`, `holder_name`, `is_active`).
**Módulo:** `Finance`
**Permisos:** `finance_settings.view|manage`
**Criterios de aceptación:**
- [ ] `account_number` único por banco; `cci` de 20 dígitos si se informa → 422 si no.
- [ ] La moneda de una cuenta no cambia si la cuenta tiene movimientos (se activa con el spec 020).
- [ ] El saldo de la cuenta es de solo lectura (lo mantiene el spec 020).
- [ ] El seeder carga los bancos peruanos principales (BCP, BBVA, Interbank, Scotiabank, Banco de la Nación, BanBif, Pichincha).

### US-4 — Métodos de pago
**Como** administrador **quiero** definir los métodos de pago aceptados y adónde va el dinero de cada uno **para** que las cobranzas se registren en la caja o en la cuenta correcta.
**Cuando** se invoca el CRUD `/api/v1/finance/payment-methods`, **el sistema debe** gestionar métodos con `code` único, `name`, `kind` (`cash`, `card`, `transfer`, `wallet`, `check`, `other`), `settlement` (`cash_box` o `bank_account`), `bank_account_id` (obligatorio si `settlement = bank_account`), `requires_reference`, `sunat_code`, `sort_order` e `is_active`.
**Módulo:** `Finance`
**Permisos:** `finance_settings.view|manage`
**Criterios de aceptación:**
- [ ] `kind = cash` implica `settlement = cash_box`.
- [ ] El método de sistema `CASH` (efectivo) no se elimina ni cambia de `kind`.
- [ ] `GET /api/v1/finance/payment-methods/options` devuelve los activos ordenados por `sort_order`.

### US-5 — Plan de cuentas y conceptos de ingreso/egreso
**Como** contador **quiero** un plan de cuentas jerárquico y conceptos de ingreso y egreso imputados a una cuenta **para** clasificar todo movimiento de dinero.
**Cuando** se invoca el CRUD `/api/v1/finance/financial-accounts` y `/api/v1/finance/concepts`, **el sistema debe** gestionar cuentas (`code` único, `name`, `type` = `asset`|`liability`|`equity`|`income`|`expense`, `parent_id`, `is_postable`, `is_active`) y conceptos (`code` único, `name`, `direction` = `income`|`expense`|`transfer`, `financial_account_id` opcional, `is_system`, `is_active`).
**Módulo:** `Finance`
**Permisos:** `finance_settings.view|manage`
**Criterios de aceptación:**
- [ ] Una cuenta hija hereda el `type` del padre (enviar otro → 422); solo las cuentas `is_postable` se asignan a conceptos.
- [ ] `transfer` se reserva a conceptos de sistema que mueven dinero entre caja y banco o entre cuentas propias, válidos para entradas y salidas.
- [ ] Conceptos de sistema sembrados (no editables en `code`/`direction`, no eliminables): `SALE_COLLECTION`, `RECEIVABLE_COLLECTION`, `SUPPLIER_PAYMENT`, `CASH_TRANSFER_IN`, `CASH_TRANSFER_OUT`, `BANK_DEPOSIT`, `BANK_WITHDRAWAL`, `BANK_TRANSFER_IN`, `BANK_TRANSFER_OUT`, `SALE_REFUND`, `CASH_OVERAGE`, `CASH_SHORTAGE`.
- [ ] `GET /api/v1/finance/concepts/options?direction=expense` excluye los de sistema salvo `include_system=1`.

## 4. Reglas de negocio e invariantes
- Una sola moneda base (`currencies.is_base`), replicada en `company_settings.default_currency_code` solo por `CurrencyService`.
- Un tipo de cambio por (moneda, fecha).
- Todo método de pago liquida en caja o en una cuenta bancaria concreta.
- Los conceptos y métodos de sistema se referencian por `code` (enum), nunca por id.
- Ningún maestro referenciado por movimientos se elimina; se desactiva.

## 5. Requisitos no funcionales
- Auditoría de todos los maestros (spec 007).
- Endpoints `/options` en < 100 ms.
- Pint, Larastan, `make test` y `npm run lint` en verde.

## 6. Out of Scope
- Saldos y movimientos de cuentas bancarias → spec 020.
- Cajas y sesiones → spec 018.
- Consulta automática del tipo de cambio SUNAT/SBS → spec futuro.
- Asientos contables y libros electrónicos (PLE) → spec futuro; aquí solo se imputa el concepto a una cuenta.
- Pasarelas de pago, comisiones de POS y liquidaciones (Multinort `deposits.payment_gateway`) → spec futuro.

## 7. Open Questions
- [ ] ¿Se debe precargar un plan de cuentas PCGE resumido? Propuesta: solo las cuentas de primer nivel. — Responsable: Luis Acuña.
- [ ] ¿Las cajas pueden operar en USD? Propuesta: no en esta entrega (solo moneda base). — Responsable: Luis Acuña.

## 8. Métricas de éxito
- `CurrenciesApiTest`, `ExchangeRatesApiTest`, `BankAccountsApiTest`, `PaymentMethodsApiTest` y `FinancialAccountsAndConceptsApiTest` en verde.
- Seeder idempotente con monedas, bancos, método `CASH` y conceptos de sistema.
