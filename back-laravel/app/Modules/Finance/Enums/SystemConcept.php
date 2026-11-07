<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

use App\Modules\Finance\Models\FinanceConcept;

enum SystemConcept: string
{
    case SaleCollection = 'SALE_COLLECTION';
    case ReceivableCollection = 'RECEIVABLE_COLLECTION';
    case SupplierPayment = 'SUPPLIER_PAYMENT';
    case CashTransferIn = 'CASH_TRANSFER_IN';
    case CashTransferOut = 'CASH_TRANSFER_OUT';
    case BankDeposit = 'BANK_DEPOSIT';
    case BankWithdrawal = 'BANK_WITHDRAWAL';
    case BankTransferIn = 'BANK_TRANSFER_IN';
    case BankTransferOut = 'BANK_TRANSFER_OUT';
    case SaleRefund = 'SALE_REFUND';
    case CashOverage = 'CASH_OVERAGE';
    case CashShortage = 'CASH_SHORTAGE';

    public function model(): ?FinanceConcept
    {
        /** @var array<string, FinanceConcept|null> $cache */
        static $cache = [];

        return $cache[$this->value] ??= FinanceConcept::where('code', $this->value)->first();
    }

    public function direction(): ConceptDirection
    {
        return match ($this) {
            self::SaleCollection,
            self::ReceivableCollection,
            self::CashOverage => ConceptDirection::Income,

            self::SupplierPayment,
            self::SaleRefund,
            self::CashShortage => ConceptDirection::Expense,

            self::CashTransferIn,
            self::CashTransferOut,
            self::BankDeposit,
            self::BankWithdrawal,
            self::BankTransferIn,
            self::BankTransferOut => ConceptDirection::Transfer,
        };
    }

    public function defaultName(): string
    {
        return match ($this) {
            self::SaleCollection => 'Cobro de venta',
            self::ReceivableCollection => 'Cobro de cuenta por cobrar',
            self::SupplierPayment => 'Pago a proveedor',
            self::CashTransferIn => 'Transferencia de caja recibida',
            self::CashTransferOut => 'Transferencia de caja enviada',
            self::BankDeposit => 'Depósito bancario',
            self::BankWithdrawal => 'Retiro bancario',
            self::BankTransferIn => 'Transferencia bancaria recibida',
            self::BankTransferOut => 'Transferencia bancaria enviada',
            self::SaleRefund => 'Devolución de venta',
            self::CashOverage => 'Sobrante de caja',
            self::CashShortage => 'Faltante de caja',
        };
    }

    /**
     * Cuenta contable contrapartida por defecto. Única fuente del mapeo.
     */
    public function defaultAccountCode(): string
    {
        return match ($this) {
            self::SaleCollection,
            self::ReceivableCollection,
            self::SaleRefund => '1212',

            self::SupplierPayment => '4212',

            self::CashTransferIn,
            self::CashTransferOut,
            self::BankDeposit,
            self::BankWithdrawal,
            self::BankTransferIn,
            self::BankTransferOut => '103',

            self::CashOverage => '759',
            self::CashShortage => '659',
        };
    }
}
