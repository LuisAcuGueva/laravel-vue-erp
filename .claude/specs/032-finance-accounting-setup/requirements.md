# 032 · finance-accounting-setup — Requirements
> **Spec:** 032 · **Alcance:** fullstack · **Módulo:** Finance · **Prioridad:** alta · **Depende de specs:** 017, 018, 019, 020, 024, 028, 029
> **Status:** Done
> **Última actualización:** 2026-10-20
> **Autores:** Luis Acuña Guevara
> **Origen:** Análisis de la configuración financiera — conceptos sin imputación al plan de cuentas
>
> **Referencias del proyecto:**
> - [README.md](../../../README.md) — arquitectura, entornos (dev, test, prod) y comandos make.
> - [.agents/rules/](../../../.agents/rules/) — reglas duras del repo (`finance.md`, `business-parameters.md`, `frontend-ui.md`).
> - [routes/api.php](../../../back-laravel/routes/api.php) — endpoints `/api/v1/finance/financial-accounts` y `/api/v1/finance/concepts`.
> - Spec 017 (US-5) — plan de cuentas y conceptos; dejó abierta la precarga del PCGE. Specs 018, 019, 020 — caja, transferencias y bancos (usan conceptos de sistema). Spec 024 — gastos con concepto de egreso. Specs 028, 029 — cobros y reembolsos.
> - Plan Contable General Empresarial (PCGE) 2019 — Consejo Normativo de Contabilidad.
>
---
## 1. Contexto de Negocio
El spec 017 creó el plan de cuentas (`financial_accounts`) y los conceptos de ingreso y egreso (`finance_concepts.financial_account_id` opcional) **para clasificar todo movimiento de dinero**. Hoy esa relación está vacía:

- `FinanceMastersSeeder::seedFinanceConcepts()` siembra los 12 conceptos de sistema (`SALE_COLLECTION`, `SUPPLIER_PAYMENT`, `CASH_OVERAGE`, …) y dos de ejemplo (`OTHER_INCOME`, `OTHER_EXPENSE`) **sin cuenta**. Ningún movimiento de caja (`cash_movements`) ni de banco (`bank_movements`) queda imputado.
- `seedFinancialAccounts()` usa códigos con punto (`10.1`, `10.4`) que no existen en el PCGE, y deja **imputables** las cuentas de primer nivel `12`, `42`, `65`, `70` y `75`.
- Las subcuentas están en `FinanceDemoSeeder::seedAccountingPlan()` (solo demo) y clasifican mal: «Servicios básicos» y «Alquileres» bajo `65` cuando el PCGE los ubica en `636` y `635`.
- Faltan cuentas que las operaciones ya existentes necesitan: efectivo en tránsito (`103`), anticipos (`122`, `422`), personal (`14`), tributos (`40`), préstamos (`45`), servicios de terceros (`63`), tributos como gasto (`64`) y gastos e ingresos financieros (`67`, `77`).
- `FinancialAccountService` vuelve no imputable al padre cuando se le crea una subcuenta **sin revisar si tiene conceptos**, y esos conceptos quedan imputados a una cuenta donde ya no se puede imputar.
- `FinanceConceptService::validateFinancialAccount()` no valida los conceptos `transfer` y no permite imputar un ingreso a pasivo o patrimonio (préstamo recibido, aporte de capital).
- Un gasto de proveedor (`supplier_invoices.kind = expense`, spec 024) acepta un concepto cuya cuenta es un pasivo, como el pago de un préstamo.
- Nada avisa al contador de qué le falta a la configuración.

Este spec deja la configuración financiera **completa y consistente desde la instalación**:
1. un plan de cuentas PCGE 2019 resumido en el seeder de producción;
2. cada concepto imputado a su **contrapartida** (la caja y el banco ya son las cuentas 101 y 104);
3. un catálogo de conceptos operativos frecuentes;
4. reglas que impiden degradar la configuración;
5. un diagnóstico visible en la interfaz.

**Principio rector:** la cuenta de un concepto es la **contrapartida** del movimiento de dinero. Las transferencias internas (caja ↔ caja, caja ↔ banco, banco ↔ banco) usan la cuenta puente `103 Efectivo en tránsito`, cuyas dos patas se compensan.

## 2. Actores
| Actor | Quién | Cómo se autentica / Permisos base |
|---|---|---|
| Administrador / Contador | `admin`, `manager` | JWT · `finance_settings.view`, `finance_settings.manage` |
| Operador de despliegue | DevOps / instalador | `php artisan db:seed`, `php artisan migrate`, `php artisan finance:check-accounting-setup` |
| Sistema | Servicios de caja, bancos, compras y ventas | Usan conceptos de sistema por `code` (`SystemConcept`) |

## 3. User Stories (formato EARS)

### US-1 — Plan de cuentas PCGE resumido
**Como** contador **quiero** que la instalación traiga un plan de cuentas PCGE 2019 resumido **para** imputar los movimientos sin cargarlo a mano.
**Cuando** se ejecuta `php artisan db:seed` (en cualquier entorno, no solo demo), **el sistema debe** crear las cuentas del catálogo de §4.1 con `code`, `name`, `type`, `parent_id`, `level` e `is_postable` coherentes.
**Módulo:** `Finance` (seeders)
**Permisos:** — (despliegue)
**Criterios de aceptación:**
- [ ] Existen todas las cuentas del §4.1. Solo las hojas tienen `is_postable = true`.
- [ ] `level` = 1 para los códigos de 2 dígitos y aumenta en 1 por cada dígito adicional respecto del padre.
- [ ] El tipo de cada hija es igual al de su padre.
- [ ] Ejecutar el seeder dos veces no crea duplicados ni cambia `id`.
- [ ] El seeder **no pisa** `name` ni `is_active` de una cuenta existente. Solo crea las que faltan y corrige `parent_id`, `level` e `is_postable`.
- [ ] `FinanceDemoSeeder` ya no siembra subcuentas contables.

### US-2 — Normalización de códigos existentes
**Como** operador de despliegue **quiero** que las bases de datos que ya tienen los códigos `10.1`, `10.4`, `12.1`, etc. pasen al formato PCGE **sin perder la imputación de los conceptos**.
**Cuando** se ejecuta `php artisan migrate`, **el sistema debe** renombrar los códigos según §4.4 conservando el `id` de cada cuenta.
**Módulo:** `Finance` (migración de datos)
**Permisos:** — (despliegue)
**Criterios de aceptación:**
- [ ] Después de la migración no queda ningún `financial_accounts.code` con punto.
- [ ] Los `finance_concepts.financial_account_id` siguen apuntando a la misma fila.
- [ ] Si un código destino ya existe (creado a mano), la migración no duplica: reasigna los conceptos y los hijos del código viejo al existente y elimina lógicamente (soft delete) la fila vieja.
- [ ] Una base vacía (instalación nueva) migra sin error.

### US-3 — Imputación de los conceptos de sistema
**Como** contador **quiero** que cada concepto de sistema venga imputado a su contrapartida **para** que todo movimiento automático quede clasificado.
**Cuando** se ejecuta el seeder, **el sistema debe** asignar a cada concepto de sistema la cuenta del §4.2, solo si su `financial_account_id` es `null`.
**Módulo:** `Finance` (seeders)
**Permisos:** —
**Criterios de aceptación:**
- [ ] Los 12 conceptos de sistema quedan con cuenta imputable y activa.
- [ ] Si el contador reasignó un concepto de sistema a otra cuenta, volver a ejecutar el seeder no la revierte.
- [ ] La cuenta por defecto de cada concepto de sistema se define en un único lugar del código, el enum `SystemConcept`.
- [ ] `PUT /api/v1/finance/concepts/{id}` de un concepto de sistema permite cambiar `financial_account_id` y `name`; `code` y `direction` siguen siendo inmutables (spec 017).

### US-4 — Catálogo de conceptos operativos
**Como** contador **quiero** una lista inicial de conceptos de ingreso y egreso frecuentes, ya imputados **para** registrar movimientos manuales y gastos sin configurar cada uno.
**Cuando** se ejecuta el seeder, **el sistema debe** crear los conceptos del §4.3 con `is_system = false` y su cuenta.
**Módulo:** `Finance` (seeders)
**Permisos:** —
**Criterios de aceptación:**
- [ ] Se crean todos los conceptos del §4.3, editables y eliminables.
- [ ] El seeder no recrea un concepto operativo que el usuario eliminó (soft delete), ni pisa su `name`, `is_active` o `financial_account_id`.
- [ ] `OTHER_INCOME` y `OTHER_EXPENSE` se conservan con su `id` y reciben cuenta (759 y 659).

### US-5 — Formato y jerarquía de códigos de cuenta
**Como** contador **quiero** que el sistema exija códigos con formato PCGE **para** que la jerarquía sea legible y exportable.
**Cuando** se envía `POST|PUT /api/v1/finance/financial-accounts`, **el sistema debe** validar el `code` y su relación con el padre.
**Módulo:** `Finance` (`/api/v1/finance/financial-accounts`)
**Permisos:** `finance_settings.manage`
**Criterios de aceptación:**
- [ ] `code` con un carácter que no sea dígito, o con menos de 2 o más de 8 dígitos → 422 `{ "errors": { "code": ["El código debe tener entre 2 y 8 dígitos, sin puntos ni espacios."] } }`.
- [ ] Una cuenta sin padre tiene exactamente 2 dígitos; si no → 422 en `code`.
- [ ] Una cuenta con padre empieza con el código del padre y es más larga; si no → 422 `"El código debe comenzar con el código de la cuenta padre ({padre})."`.
- [ ] `level` lo calcula el servidor; si llega en el body se ignora.

### US-6 — Proteger la imputación existente
**Como** contador **quiero** que el sistema no deje conceptos imputados a cuentas inválidas **para** no perder la clasificación.
**Cuando** una operación sobre `financial_accounts` dejaría un concepto imputado a una cuenta no imputable o inactiva, **el sistema debe** rechazarla con 422.
**Módulo:** `Finance` (`/api/v1/finance/financial-accounts`)
**Permisos:** `finance_settings.manage`
**Criterios de aceptación:**
- [ ] Crear o mover una subcuenta bajo un padre con conceptos asociados → 422 en `parent_id`: `"La cuenta {code} tiene {n} conceptos asociados. Reasígnalos antes de crear subcuentas."`. No se modifica nada.
- [ ] Desactivar (`is_active = false`) una cuenta con conceptos activos asociados → 422 en `is_active`.
- [ ] Desactivar una cuenta padre con hijas activas → 422 en `is_active`.
- [ ] Marcar `is_postable = true` una cuenta con hijas → 422 en `is_postable`.
- [ ] La eliminación sigue bloqueada si hay hijas o conceptos (spec 017).

### US-7 — Coherencia entre dirección del concepto y tipo de cuenta
**Como** contador **quiero** que el sistema valide que la cuenta del concepto tenga sentido para su dirección **para** evitar imputaciones absurdas.
**Cuando** se envía `POST|PUT /api/v1/finance/concepts` con `financial_account_id`, **el sistema debe** aplicar la matriz de §4.5.
**Módulo:** `Finance` (`/api/v1/finance/concepts`)
**Permisos:** `finance_settings.manage`
**Criterios de aceptación:**
- [ ] Una combinación no permitida → 422 en `financial_account_id` con el mensaje `"Una cuenta de tipo {tipo} no es válida para un concepto de {dirección}."`.
- [ ] Un concepto `transfer` solo acepta una cuenta imputable bajo la cuenta `10`.
- [ ] Un ingreso imputado a pasivo (`4511`) o patrimonio (`501`) se acepta.
- [ ] La lista de cuentas del formulario de concepto muestra solo las combinaciones permitidas para la dirección elegida.

### US-8 — Gastos de proveedor imputados a gasto
**Como** contador **quiero** que un gasto de proveedor solo use conceptos imputados a cuentas de gasto **para** que el registro de compras no mezcle pagos de deuda.
**Cuando** se registra o actualiza una factura con `kind = expense` (spec 024) cuyo concepto tiene cuenta, **el sistema debe** exigir que la cuenta sea de tipo `expense`.
**Módulo:** `Purchases` (`/api/v1/purchases/supplier-invoices`)
**Permisos:** los de spec 024
**Criterios de aceptación:**
- [ ] Concepto con cuenta de tipo distinto de `expense` → 422 en `concept_id`: `"El concepto debe estar imputado a una cuenta de gasto."`.
- [ ] Un concepto sin cuenta se acepta mientras `finance.require_concept_account = false`.
- [ ] El combo de concepto del formulario de gasto lista solo los conceptos de egreso cuya cuenta es de gasto o que no tienen cuenta.

### US-9 — Parámetro de imputación obligatoria
**Como** contador **quiero** activar la imputación obligatoria cuando el plan esté listo **para** que ningún concepto nuevo quede sin cuenta.
**Cuando** `finance.require_concept_account = true`, **el sistema debe** exigir `financial_account_id` al crear o actualizar un concepto.
**Módulo:** `Finance` (`/api/v1/finance/concepts`) · `SystemParameterSeeder`
**Permisos:** `finance_settings.manage` (concepto); los de spec 010 (parámetro)
**Criterios de aceptación:**
- [ ] El parámetro existe en `SystemParameterSeeder` (`group_key = finance`, `item_key = require_concept_account`, `boolean`) con valor por defecto `false`.
- [ ] Con el parámetro activo, un concepto sin cuenta → 422 `"La cuenta contable es obligatoria."` en `financial_account_id`.
- [ ] Con el parámetro activo, un gasto de proveedor con un concepto sin cuenta → 422 en `concept_id`.
- [ ] Activar el parámetro no modifica los conceptos existentes; el diagnóstico (US-10) los reporta.

### US-10 — Diagnóstico de la configuración contable
**Como** contador **quiero** ver qué le falta a la configuración financiera **para** corregirlo antes de operar.
**Cuando** se invoca `GET /api/v1/finance/settings/health` o `php artisan finance:check-accounting-setup`, **el sistema debe** devolver la lista de problemas detectados según §4.6.
**Módulo:** `Finance` (`/api/v1/finance/settings/health`)
**Permisos:** `finance_settings.view`
**Criterios de aceptación:**
- [ ] Respuesta `200` `{ "data": { "status": "ok"|"warning"|"error", "issues": [ { "code": "...", "severity": "warning"|"error", "message": "...", "count": n, "items": [ { "id", "code", "name" } ] } ] } }`.
- [ ] Sin `finance_settings.view` → 403.
- [ ] El comando termina con código `0` sin errores y con `1` si hay al menos un `error`. Imprime una tabla con los problemas.
- [ ] Con el parámetro activo, «concepto activo sin cuenta» se reporta como `error`; con el parámetro inactivo, como `warning`.
- [ ] La pestaña Conceptos de Configuración financiera muestra un `VAlert` con el resumen cuando `status ≠ ok`, y un filtro «Sin cuenta».

### US-11 — Visualización de la imputación
**Como** contador **quiero** ver la cuenta de cada concepto en el listado **para** revisar la imputación de un vistazo.
**Cuando** se abre Finanzas › Configuración › Conceptos, **el sistema debe** mostrar la columna «Cuenta» con `código - nombre`, o «Sin cuenta» en color de advertencia.
**Módulo:** `Finance` (frontend)
**Permisos:** `finance_settings.view`
**Criterios de aceptación:**
- [ ] `GET /api/v1/finance/concepts` admite `has_account=0|1` y devuelve `financial_account: { id, code, name, type }`.
- [ ] La pestaña Plan de cuentas muestra el código delante del nombre y un chip «Imputable» en las hojas.
- [ ] El placeholder del código de cuenta es un ejemplo PCGE (`6361`).

## 4. Reglas de negocio e invariantes

### 4.1 Plan de cuentas PCGE 2019 resumido
Solo las cuentas en **negrita** son imputables. La columna «Padre» indica de qué cuenta cuelga cada una.

| Código | Nombre | Tipo | Padre |
|---|---|---|---|
| 10 | Efectivo y equivalentes de efectivo | asset | — |
| **101** | Caja | asset | 10 |
| **102** | Fondos fijos | asset | 10 |
| **103** | Efectivo en tránsito | asset | 10 |
| 104 | Cuentas corrientes en instituciones financieras | asset | 10 |
| **1041** | Cuentas corrientes operativas | asset | 104 |
| **106** | Depósitos en instituciones financieras | asset | 10 |
| 12 | Cuentas por cobrar comerciales – Terceros | asset | — |
| 121 | Facturas, boletas y otros comprobantes por cobrar | asset | 12 |
| **1212** | Emitidas en cartera | asset | 121 |
| **122** | Anticipos de clientes | asset | 12 |
| 14 | Cuentas por cobrar al personal, a los accionistas, directores y gerentes | asset | — |
| 141 | Personal | asset | 14 |
| **1411** | Préstamos | asset | 141 |
| **1412** | Adelanto de remuneraciones | asset | 141 |
| **1413** | Entregas a rendir cuenta | asset | 141 |
| **1419** | Otras cuentas por cobrar al personal | asset | 141 |
| 16 | Cuentas por cobrar diversas – Terceros | asset | — |
| **1689** | Otras cuentas por cobrar diversas | asset | 16 |
| 40 | Tributos, contraprestaciones y aportes al sistema de pensiones y de salud por pagar | liability | — |
| 401 | Gobierno central | liability | 40 |
| 4011 | Impuesto general a las ventas | liability | 401 |
| **40111** | IGV – Cuenta propia | liability | 4011 |
| **4017** | Impuesto a la renta | liability | 401 |
| 403 | Instituciones públicas | liability | 40 |
| **4031** | ESSALUD | liability | 403 |
| 41 | Remuneraciones y participaciones por pagar | liability | — |
| 411 | Remuneraciones por pagar | liability | 41 |
| **4111** | Sueldos y salarios por pagar | liability | 411 |
| 42 | Cuentas por pagar comerciales – Terceros | liability | — |
| 421 | Facturas, boletas y otros comprobantes por pagar | liability | 42 |
| **4212** | Emitidas | liability | 421 |
| **422** | Anticipos a proveedores | liability | 42 |
| **424** | Honorarios por pagar | liability | 42 |
| 45 | Obligaciones financieras | liability | — |
| 451 | Préstamos de instituciones financieras y otras entidades | liability | 45 |
| **4511** | Instituciones financieras | liability | 451 |
| 46 | Cuentas por pagar diversas – Terceros | liability | — |
| **4699** | Otras cuentas por pagar diversas | liability | 46 |
| 50 | Capital | equity | — |
| **501** | Capital social | equity | 50 |
| 60 | Compras | expense | — |
| 601 | Mercaderías | expense | 60 |
| **6011** | Mercaderías manufacturadas | expense | 601 |
| 62 | Gastos de personal, directores y gerentes | expense | — |
| 621 | Remuneraciones | expense | 62 |
| **6211** | Sueldos y salarios | expense | 621 |
| 63 | Gastos de servicios prestados por terceros | expense | — |
| 631 | Transporte, correos y gastos de viaje | expense | 63 |
| **6311** | Transporte | expense | 631 |
| **6314** | Alimentación | expense | 631 |
| **632** | Asesoría y consultoría | expense | 63 |
| **634** | Mantenimiento y reparaciones | expense | 63 |
| 635 | Alquileres | expense | 63 |
| **6352** | Edificaciones | expense | 635 |
| 636 | Servicios básicos | expense | 63 |
| **6361** | Energía eléctrica | expense | 636 |
| **6363** | Agua | expense | 636 |
| **6364** | Teléfono | expense | 636 |
| **6365** | Internet | expense | 636 |
| 637 | Publicidad, publicaciones, relaciones públicas | expense | 63 |
| **6371** | Publicidad | expense | 637 |
| 639 | Otros servicios prestados por terceros | expense | 63 |
| **6391** | Gastos bancarios | expense | 639 |
| 64 | Gastos por tributos | expense | — |
| 641 | Gobierno central | expense | 64 |
| **6412** | Impuesto a las transacciones financieras | expense | 641 |
| **643** | Gobierno local | expense | 64 |
| 65 | Otros gastos de gestión | expense | — |
| **656** | Suministros | expense | 65 |
| **659** | Otros gastos de gestión | expense | 65 |
| 67 | Gastos financieros | expense | — |
| 673 | Intereses por préstamos y otras obligaciones | expense | 67 |
| **6731** | Préstamos de instituciones financieras y otras entidades | expense | 673 |
| **676** | Diferencia de cambio | expense | 67 |
| 70 | Ventas | income | — |
| 701 | Mercaderías | income | 70 |
| **7011** | Mercaderías manufacturadas | income | 701 |
| **704** | Prestación de servicios | income | 70 |
| 75 | Otros ingresos de gestión | income | — |
| **759** | Otros ingresos de gestión | income | 75 |
| 77 | Ingresos financieros | income | — |
| 772 | Rendimientos ganados | income | 77 |
| **7721** | Depósitos en instituciones financieras | income | 772 |
| **776** | Diferencia en cambio | income | 77 |

> El PCGE clasifica `122 Anticipos de clientes` en el elemento 1 (activo) y `422 Anticipos a proveedores` en el elemento 4 (pasivo). Aquí se respeta el elemento del PCGE para el `type`, aunque su saldo natural sea el contrario.

### 4.2 Contrapartida de los conceptos de sistema
| Concepto | Cuenta | Motivo |
|---|---|---|
| `SALE_COLLECTION` | 1212 | El cobro cancela la cuenta por cobrar de la venta |
| `RECEIVABLE_COLLECTION` | 1212 | Igual |
| `SALE_REFUND` | 1212 | La nota de crédito abona la 12 y el reembolso la carga |
| `SUPPLIER_PAYMENT` | 4212 | El pago cancela la cuenta por pagar |
| `CASH_TRANSFER_IN`, `CASH_TRANSFER_OUT` | 103 | Cuenta puente |
| `BANK_DEPOSIT`, `BANK_WITHDRAWAL` | 103 | Cuenta puente (caja ↔ banco) |
| `BANK_TRANSFER_IN`, `BANK_TRANSFER_OUT` | 103 | Cuenta puente (banco ↔ banco) |
| `CASH_OVERAGE` | 759 | Otros ingresos de gestión |
| `CASH_SHORTAGE` | 659 | Otros gastos de gestión (reasignable a 1419 si se cobra al cajero) |

### 4.3 Conceptos operativos iniciales (`is_system = false`)
| Código | Nombre | Dirección | Cuenta |
|---|---|---|---|
| `OTHER_INCOME` | Otros ingresos varios | income | 759 |
| `BANK_INTEREST` | Intereses bancarios ganados | income | 7721 |
| `FX_GAIN` | Ganancia por diferencia de cambio | income | 776 |
| `LOAN_RECEIVED` | Préstamo recibido | income | 4511 |
| `CAPITAL_CONTRIBUTION` | Aporte de capital | income | 501 |
| `ADVANCE_RETURN` | Devolución de entrega a rendir | income | 1413 |
| `CUSTOMER_ADVANCE` | Anticipo de cliente | income | 122 |
| `OTHER_EXPENSE` | Otros gastos operativos | expense | 659 |
| `BANK_FEES` | Comisiones y gastos bancarios | expense | 6391 |
| `ITF` | Impuesto a las transacciones financieras | expense | 6412 |
| `ELECTRICITY` | Energía eléctrica | expense | 6361 |
| `WATER` | Agua | expense | 6363 |
| `PHONE` | Telefonía | expense | 6364 |
| `INTERNET` | Internet | expense | 6365 |
| `RENT` | Alquiler de local | expense | 6352 |
| `MAINTENANCE` | Mantenimiento y reparaciones | expense | 634 |
| `TRANSPORT` | Transporte y movilidad | expense | 6311 |
| `TRAVEL_MEALS` | Alimentación y viáticos | expense | 6314 |
| `PROFESSIONAL_FEES` | Asesoría y honorarios profesionales | expense | 632 |
| `ADVERTISING` | Publicidad | expense | 6371 |
| `OFFICE_SUPPLIES` | Útiles y suministros de oficina | expense | 656 |
| `MUNICIPAL_TAXES` | Arbitrios e impuesto predial | expense | 643 |
| `LOAN_INTEREST` | Intereses de préstamos | expense | 6731 |
| `FX_LOSS` | Pérdida por diferencia de cambio | expense | 676 |
| `LOAN_PAYMENT` | Amortización de préstamo | expense | 4511 |
| `TAX_PAYMENT` | Pago de IGV | expense | 40111 |
| `PAYROLL_PAYMENT` | Pago de remuneraciones | expense | 4111 |
| `EMPLOYEE_ADVANCE` | Entrega a rendir cuenta | expense | 1413 |
| `SALARY_ADVANCE` | Adelanto de remuneraciones | expense | 1412 |
| `SUPPLIER_ADVANCE` | Anticipo a proveedor | expense | 422 |

### 4.4 Normalización de códigos heredados
| Código actual | Código PCGE | Origen |
|---|---|---|
| `10.1` | `101` | `FinanceMastersSeeder` |
| `10.4` | `104` | `FinanceMastersSeeder` |
| `12.1` | `121` | `FinanceDemoSeeder` |
| `42.1` | `421` | `FinanceDemoSeeder` |
| `65.1` | `636` (re-parent a `63`) | `FinanceDemoSeeder` |
| `65.2` | `635` (re-parent a `63`) | `FinanceDemoSeeder` |
| `70.1` | `701` | `FinanceDemoSeeder` |
| `70.4` | `704` | `FinanceDemoSeeder` |

Después de la migración, el seeder del §4.1 completa la jerarquía. Un concepto que quede en una cuenta padre se mueve a la hija imputable por defecto: `104`→`1041`, `121`→`1212`, `421`→`4212`, `636`→`6361`, `635`→`6352`, `701`→`7011`. Los raíces que pierden la imputabilidad (`12`, `42`, `65`, `70`, `75`) siguen el mismo criterio: `12`→`1212`, `42`→`4212`, `65`→`659`, `70`→`7011`, `75`→`759`.

### 4.5 Matriz dirección × tipo de cuenta
| Dirección | asset | liability | equity | income | expense |
|---|---|---|---|---|---|
| `income` | ✔ | ✔ | ✔ | ✔ | ✘ |
| `expense` | ✔ | ✔ | ✘ | ✘ | ✔ |
| `transfer` | ✔ solo bajo `10` | ✘ | ✘ | ✘ | ✘ |

### 4.6 Problemas que detecta el diagnóstico
| Código | Severidad | Condición |
|---|---|---|
| `concept_without_account` | warning (error si `require_concept_account`) | Concepto activo sin `financial_account_id` |
| `concept_account_not_postable` | error | Concepto imputado a cuenta no imputable |
| `concept_account_inactive` | error | Concepto imputado a cuenta inactiva o eliminada |
| `concept_account_type_mismatch` | error | Combinación prohibida por §4.5 |
| `parent_account_postable` | error | Cuenta con hijas marcada como imputable |
| `account_code_format` | error | `code` fuera de formato PCGE o sin prefijo del padre |
| `system_concept_reassigned` | warning | Concepto de sistema con cuenta distinta a la de §4.2 (informativo) |

### 4.7 Invariantes
- Un concepto solo se imputa a una cuenta **activa, imputable y no eliminada**.
- Una cuenta es imputable si y solo si no tiene hijas activas.
- El `code` de una hija empieza con el `code` de su padre; las cuentas raíz tienen 2 dígitos.
- Los seeders financieros **no pisan** decisiones del usuario: `name`, `is_active` ni `financial_account_id` ya asignados.
- La cuenta por defecto de un concepto de sistema se lee de `SystemConcept`; ningún otro archivo repite el mapeo.

## 5. Requisitos no funcionales
- **Idempotencia:** los seeders y la migración de datos se pueden ejecutar N veces con el mismo resultado.
- **Transaccionalidad:** la migración de normalización y cada operación de `FinancialAccountService` corren en una sola transacción.
- **Rendimiento:** el diagnóstico resuelve con un número fijo de consultas (sin N+1) y responde en < 300 ms con 500 cuentas y 500 conceptos.
- **Mensajes:** en español, con el código de la cuenta afectada.
- **Calidad:** `make test`, `vendor/bin/pint --test`, Larastan, `npm run lint` y `npm run test:unit` (incluidos `uiGuardrails.spec.js` y `routeMeta.spec.js`) en verde.
- **Reglas del repo:** el parámetro nuevo se lee con `SystemParameterService` (`business-parameters.md`); la interfaz sigue `frontend-ui.md`.

## 6. Out of Scope
- Asientos contables (libro diario y mayor), cierre contable y libros electrónicos PLE → spec futuro de Contabilidad (ya excluido en spec 017).
- Vincular cada cuenta bancaria (`bank_accounts`) y cada caja (`boxes`) a una subcuenta propia (`104101`, `1011`, …) → spec futuro de Contabilidad.
- Cuentas contables en métodos de pago (por ejemplo, tarjetas por liquidar) → spec futuro de Contabilidad.
- Importar o exportar un plan de cuentas completo desde CSV o Excel → spec futuro.
- Plan de cuentas por empresa o sede (multiempresa contable) → fuera del alcance del template.
- Reimputar movimientos históricos (`cash_movements`, `bank_movements`): se imputan por su concepto, así que el cambio de cuenta de un concepto afecta los reportes futuros sin tocar los movimientos.

## 7. Open Questions
- [x] ¿Faltante de caja a gasto (659) o a cobrar al cajero (1419)? → **659 por defecto**, reasignable por el contador. — Responsable: Luis Acuña.
- [x] ¿Precargar el PCGE resumido (pregunta abierta en spec 017)? → **Sí**, en el seeder de producción (§4.1). — Responsable: Luis Acuña.
- [ ] ¿El parámetro `finance.require_concept_account` debe venir activo en instalaciones nuevas? Propuesta: `false` hasta que el contador valide el plan. — Responsable: Luis Acuña.
- [ ] ¿Existen bases de datos productivas con códigos con punto? Si no, la migración de US-2 solo corre sobre bases demo, pero se mantiene por seguridad. — Responsable: Luis Acuña.

## 8. Métricas de éxito
- Tras `migrate:fresh --seed`, `php artisan finance:check-accounting-setup` termina con código `0` y sin `warning`.
- 100 % de los conceptos de sistema y operativos sembrados tienen cuenta imputable.
- 0 cuentas con punto en el código y 0 cuentas padre imputables.
- Tests nuevos en verde: `FinancialAccountsAndConceptsApiTest` (ampliado), `FinanceAccountingSeedersTest`, `NormalizeFinancialAccountCodesMigrationTest`, `FinanceSettingsHealthApiTest`, `SupplierInvoicesApiTest` (caso de cuenta no gasto).
