## Operaciones financieras y de caja

- Una operación que mueve dinero entre dos sesiones o cuentas (transferencias, depósitos, retiros) registra ambos lados en la **misma transacción** y bloquea los registros involucrados en orden determinista (por `id`) para prevenir interbloqueos.
- La validación de efectivo para nuevas salidas usa `CashSessionSummaryService::availableCash()` (esperado menos lo reservado por transferencias pendientes), no `expectedCash()`.
- Quien aprueba una operación de dinero no puede ser quien la creó (segregación de funciones); el Service lo valida y responde 422.
- Una transferencia aprobada no se anula; se corrige con una transferencia inversa.

## Ledger bancario

- Todo abono o cargo en una cuenta bancaria (cobros con tarjeta o transferencia, pagos, depósitos, retiros, transferencias, cargos del banco) se registra con `BankLedgerService::register()` dentro de la transacción del documento de origen. Prohibido insertar en `bank_movements` o escribir `bank_accounts.balance` desde otro lugar.
- `bank_movements` es inmutable; la anulación se hace con `BankLedgerService::reverse()` y solo dentro del periodo abierto.
- Los cobros y pagos con un método cuyo `settlement = bank_account` registran el movimiento bancario en la cuenta del método, no en una cuenta elegida por el usuario.
- `BankLedgerService::register()` rechaza con 422 un movimiento cuya `movement_date` sea anterior a la fecha de apertura (`opened_at`) del periodo abierto de la cuenta. Al cerrar un periodo, `BankPeriodService::close()` abre el siguiente al día siguiente del cierre (`closedAt + 1 día`); sin esta validación, un movimiento fechado el mismo día del cierre queda archivado en el periodo nuevo con una fecha anterior a su propia apertura y la vista de movimientos (que por defecto filtra desde `opened_at`) lo excluye en silencio, aunque el registro sí exista en la BD.

## Cobros, pagos y cuentas corrientes

- Los documentos que mueven dinero (pagos a proveedores, cobros de ventas y cuentas por cobrar, reembolsos) liquidan siempre con `Finance\Services\MoneySettlementService::settle()` y anulan con `unsettle()`; no llaman directamente a `CashLedgerService` ni a `BankLedgerService`.
- Deuda y pago son documentos distintos: la factura (o venta a crédito) genera cuotas; el pago o cobro se aplica a cuotas mediante asignaciones (`*_allocations`). Los saldos (`paid_amount`, `balance`) de cuotas y documentos se actualizan en la misma transacción del pago y con `lockForUpdate` sobre las cuotas, ordenadas por `id`.
- La moneda de un pago o cobro es la misma de todas las cuotas a las que se aplica; los reportes de saldos nunca suman montos de monedas distintas.
- En el frontend, los campos de método de pago, referencia y sesión de caja usan `components/common/PaymentMethodFields.vue`.

## Cronograma de cuotas al recibir una compra

- Un cronograma de cuotas (`installments: [{due_date, amount}]`) solo se acepta en el endpoint que **crea** la factura del proveedor (`POST /purchases/receipts/{id}/confirm` y `POST /purchases/orders/{id}/receive-all`, en la recepción que genera la `SupplierInvoice` por primera vez para ese número de documento). Nunca se aplica cuando la recepción se fusiona en una factura ya existente (mismo proveedor + mismo número de documento): esa recepción solo suma su monto a la cuota vigente y el cronograma enviado se ignora, devolviendo un aviso en `meta.warnings`.
- Si el usuario envía `installments` explícitamente y no cuadra con el total de la factura generada (o alguna fecha es anterior a la emisión), la confirmación de la recepción responde 422 y la recepción **no** queda confirmada — a diferencia de cualquier otro fallo de la generación automática de factura (p. ej. falta de tipo de cambio), que nunca bloquea la recepción. La distinción es: error del propio usuario → bloquea; condición ajena al usuario → nunca bloquea, la recepción queda confirmada sin factura/cuotas automáticas para registrarse manualmente después.

## Pago mixto (al contado + financiado) al recibir una compra

- `SupplierInvoiceService::create()` acepta `pay_now` + `down_payment_amount` (monto al contado, opcional; si se omite con `pay_now=true` paga el 100%, comportamiento sin cambios) + `payment` (método/caja/referencia, mismo shape que ya usa `pay_now`). El monto al contado se modela como la **primera cuota** (vencimiento = emisión, pagada en el acto vía `SupplierPaymentService::create()`); el resto del cronograma (`installments`, o la cuota única por defecto) se calcula sobre el saldo **restante**, nunca sobre el total completo.
- Igual que el cronograma de cuotas, el pago mixto (`down_payment` en `createOrAttachFromConfirmedReceipt()`) solo se aplica en la recepción que **crea** la factura; si la recepción se fusiona en una ya existente, se ignora con un aviso en `meta.warnings` y nunca se registra un pago.
- Pagar al contado al confirmar una recepción (`down_payment` en `POST /purchases/receipts/{id}/confirm` o `POST /purchases/orders/{id}/receive-all`) exige además el permiso `supplier_payments.create` (`ConfirmPurchaseReceiptRequest`/`ReceiveAllRequest::authorize()`), igual que `pay_now` ya exige ese permiso en `StoreSupplierInvoiceRequest`.
- Un monto al contado inválido (≤ 0, o mayor al total de la factura) responde 422 y bloquea la confirmación, igual que un cronograma de cuotas inválido — es un dato que el propio usuario ingresó mal.

## Plan de cuentas y conceptos

- Los códigos de `financial_accounts` siguen el PCGE: solo dígitos (`AccountCode::PATTERN`, de 2 a 8), las cuentas raíz tienen 2 dígitos y una hija empieza con el código de su padre. Prohibidos los puntos y los separadores.
- Una cuenta es imputable si y solo si no tiene hijas activas. Solo una cuenta activa e imputable se asigna a un concepto, y `FinancialAccountService` rechaza con 422 toda operación que deje un concepto en una cuenta no imputable o inactiva (crear subcuentas bajo un padre con conceptos, desactivar una cuenta en uso).
- La cuenta de un concepto es la **contrapartida** del movimiento de dinero. La dirección del concepto y el tipo de cuenta se validan con `ConceptDirection::allowsAccount()`. Los conceptos `transfer` se imputan a la cuenta puente `103 Efectivo en tránsito` (o a otra cuenta bajo `10`).
- La cuenta por defecto de un concepto de sistema se declara solo en `SystemConcept::defaultAccountCode()`. Todo caso nuevo de `SystemConcept` define allí su cuenta y se agrega al catálogo de `database/data/financial_accounts_pcge.php` si no existe.
- El plan de cuentas y los conceptos operativos iniciales se siembran desde `database/data/financial_accounts_pcge.php` y `database/data/finance_concepts.php`, siempre a través de `AccountingPlanSynchronizer`.
- Los seeders financieros no pisan decisiones del usuario: no cambian `name`, `is_active` ni un `financial_account_id` ya asignado, y no recrean ni restauran registros eliminados lógicamente (buscan con `withTrashed()`).
- Un gasto de proveedor (`supplier_invoices.kind = expense`) solo acepta conceptos imputados a una cuenta de tipo `expense`, o sin cuenta mientras `finance.require_concept_account` esté desactivado.
- Toda regla nueva de consistencia de la configuración financiera se agrega como un problema de `AccountingSetupHealthService`, con su `code` y su severidad, para que la reporten `GET /api/v1/finance/settings/health` y `php artisan finance:check-accounting-setup`.
