# 017 · finance-masters — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ nuevo ".agents/rules/finance.md", trigger: always_on) Dinero, monedas y maestros financieros
**description:** Reglas para montos, monedas, tipos de cambio, métodos de pago y conceptos en todos los módulos.
- Montos de dinero en documentos y saldos: `decimal(18,2)`; precios y costos unitarios: `decimal(18,4)`; tipos de cambio: `decimal(12,4)`. Los cálculos de totales usan `bcmath` o enteros en céntimos, nunca `float` acumulado.
- Todo documento con importes guarda `currency_id` y, si la moneda no es la base, `exchange_rate` (el tipo vigente al registrar), obtenido con `ExchangeRateService`. Ningún módulo consulta `exchange_rates` directamente.
- El destino del dinero de un cobro o pago se determina por el `settlement` del `PaymentMethod` (`cash_box` → ledger de caja; `bank_account` → ledger bancario de su cuenta); nunca por el nombre o código del método.
- Los movimientos de dinero de sistema usan conceptos resueltos por el enum `SystemConcept` (y el efectivo por `SystemPaymentMethod::Cash`), nunca por id ni por nombre. Un concepto de sistema nuevo se agrega al enum y a `FinanceMastersSeeder`.
- `bank_accounts.balance` solo lo modifica el ledger bancario (spec 020).
