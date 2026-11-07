# 032 · finance-accounting-setup — Delta de reglas
> **Instrucciones:** este archivo contiene **solo las reglas duras nuevas** que este spec agrega al repo. Al cerrar el spec se mergean en [.agents/rules/](../../../.agents/rules/) (archivo existente o uno nuevo con frontmatter `trigger` / `description`), según el destino indicado en cada sección.
>
> **Origen:** [requirements.md](./requirements.md), [design.md](./design.md), [todo.md](./todo.md).
>
---
## (+ en ".agents/rules/finance.md") Plan de cuentas y conceptos
- Los códigos de `financial_accounts` siguen el PCGE: solo dígitos (`AccountCode::PATTERN`, de 2 a 8), las cuentas raíz tienen 2 dígitos y una hija empieza con el código de su padre. Prohibidos los puntos y los separadores.
- Una cuenta es imputable si y solo si no tiene hijas activas. Solo una cuenta activa e imputable se asigna a un concepto, y `FinancialAccountService` rechaza con 422 toda operación que deje un concepto en una cuenta no imputable o inactiva (crear subcuentas bajo un padre con conceptos, desactivar una cuenta en uso).
- La cuenta de un concepto es la **contrapartida** del movimiento de dinero. La dirección del concepto y el tipo de cuenta se validan con `ConceptDirection::allowsAccount()`. Los conceptos `transfer` se imputan a la cuenta puente `103 Efectivo en tránsito` (o a otra cuenta bajo `10`).
- La cuenta por defecto de un concepto de sistema se declara solo en `SystemConcept::defaultAccountCode()`. Todo caso nuevo de `SystemConcept` define allí su cuenta y se agrega al catálogo de `database/data/financial_accounts_pcge.php` si no existe.
- El plan de cuentas y los conceptos operativos iniciales se siembran desde `database/data/financial_accounts_pcge.php` y `database/data/finance_concepts.php`, siempre a través de `AccountingPlanSynchronizer`.
- Los seeders financieros no pisan decisiones del usuario: no cambian `name`, `is_active` ni un `financial_account_id` ya asignado, y no recrean ni restauran registros eliminados lógicamente (buscan con `withTrashed()`).
- Un gasto de proveedor (`supplier_invoices.kind = expense`) solo acepta conceptos imputados a una cuenta de tipo `expense`, o sin cuenta mientras `finance.require_concept_account` esté desactivado.
- Toda regla nueva de consistencia de la configuración financiera se agrega como un problema de `AccountingSetupHealthService`, con su `code` y su severidad, para que la reporten `GET /api/v1/finance/settings/health` y `php artisan finance:check-accounting-setup`.
