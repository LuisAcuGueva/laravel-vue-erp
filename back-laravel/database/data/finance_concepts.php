<?php

declare(strict_types=1);

/**
 * Conceptos operativos iniciales (is_system = false) con su cuenta contrapartida.
 *
 * @return list<array{code: string, name: string, direction: string, account_code: string}>
 */
return [
    ['code' => 'OTHER_INCOME', 'name' => 'Otros ingresos varios', 'direction' => 'income', 'account_code' => '759'],
    ['code' => 'BANK_INTEREST', 'name' => 'Intereses bancarios ganados', 'direction' => 'income', 'account_code' => '7721'],
    ['code' => 'FX_GAIN', 'name' => 'Ganancia por diferencia de cambio', 'direction' => 'income', 'account_code' => '776'],
    ['code' => 'LOAN_RECEIVED', 'name' => 'Préstamo recibido', 'direction' => 'income', 'account_code' => '4511'],
    ['code' => 'CAPITAL_CONTRIBUTION', 'name' => 'Aporte de capital', 'direction' => 'income', 'account_code' => '501'],
    ['code' => 'ADVANCE_RETURN', 'name' => 'Devolución de entrega a rendir', 'direction' => 'income', 'account_code' => '1413'],
    ['code' => 'CUSTOMER_ADVANCE', 'name' => 'Anticipo de cliente', 'direction' => 'income', 'account_code' => '122'],
    ['code' => 'OTHER_EXPENSE', 'name' => 'Otros gastos operativos', 'direction' => 'expense', 'account_code' => '659'],
    ['code' => 'BANK_FEES', 'name' => 'Comisiones y gastos bancarios', 'direction' => 'expense', 'account_code' => '6391'],
    ['code' => 'ITF', 'name' => 'Impuesto a las transacciones financieras', 'direction' => 'expense', 'account_code' => '6412'],
    ['code' => 'ELECTRICITY', 'name' => 'Energía eléctrica', 'direction' => 'expense', 'account_code' => '6361'],
    ['code' => 'WATER', 'name' => 'Agua', 'direction' => 'expense', 'account_code' => '6363'],
    ['code' => 'PHONE', 'name' => 'Telefonía', 'direction' => 'expense', 'account_code' => '6364'],
    ['code' => 'INTERNET', 'name' => 'Internet', 'direction' => 'expense', 'account_code' => '6365'],
    ['code' => 'RENT', 'name' => 'Alquiler de local', 'direction' => 'expense', 'account_code' => '6352'],
    ['code' => 'MAINTENANCE', 'name' => 'Mantenimiento y reparaciones', 'direction' => 'expense', 'account_code' => '634'],
    ['code' => 'TRANSPORT', 'name' => 'Transporte y movilidad', 'direction' => 'expense', 'account_code' => '6311'],
    ['code' => 'TRAVEL_MEALS', 'name' => 'Alimentación y viáticos', 'direction' => 'expense', 'account_code' => '6314'],
    ['code' => 'PROFESSIONAL_FEES', 'name' => 'Asesoría y honorarios profesionales', 'direction' => 'expense', 'account_code' => '632'],
    ['code' => 'ADVERTISING', 'name' => 'Publicidad', 'direction' => 'expense', 'account_code' => '6371'],
    ['code' => 'OFFICE_SUPPLIES', 'name' => 'Útiles y suministros de oficina', 'direction' => 'expense', 'account_code' => '656'],
    ['code' => 'MUNICIPAL_TAXES', 'name' => 'Arbitrios e impuesto predial', 'direction' => 'expense', 'account_code' => '643'],
    ['code' => 'LOAN_INTEREST', 'name' => 'Intereses de préstamos', 'direction' => 'expense', 'account_code' => '6731'],
    ['code' => 'FX_LOSS', 'name' => 'Pérdida por diferencia de cambio', 'direction' => 'expense', 'account_code' => '676'],
    ['code' => 'LOAN_PAYMENT', 'name' => 'Amortización de préstamo', 'direction' => 'expense', 'account_code' => '4511'],
    ['code' => 'TAX_PAYMENT', 'name' => 'Pago de IGV', 'direction' => 'expense', 'account_code' => '40111'],
    ['code' => 'PAYROLL_PAYMENT', 'name' => 'Pago de remuneraciones', 'direction' => 'expense', 'account_code' => '4111'],
    ['code' => 'EMPLOYEE_ADVANCE', 'name' => 'Entrega a rendir cuenta', 'direction' => 'expense', 'account_code' => '1413'],
    ['code' => 'SALARY_ADVANCE', 'name' => 'Adelanto de remuneraciones', 'direction' => 'expense', 'account_code' => '1412'],
    ['code' => 'SUPPLIER_ADVANCE', 'name' => 'Anticipo a proveedor', 'direction' => 'expense', 'account_code' => '422'],
];
