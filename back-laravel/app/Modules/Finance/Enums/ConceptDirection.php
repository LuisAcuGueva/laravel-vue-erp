<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

use App\Modules\Finance\Models\FinancialAccount;

enum ConceptDirection: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';

    public function allows(MoneyDirection $direction): bool
    {
        return match ($this) {
            self::Income => $direction === MoneyDirection::In,
            self::Expense => $direction === MoneyDirection::Out,
            self::Transfer => true,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Ingreso',
            self::Expense => 'Egreso',
            self::Transfer => 'Transferencia',
        };
    }

    /**
     * Matriz dirección × tipo de cuenta del spec 032.
     */
    public function allowsAccount(FinancialAccount $account): bool
    {
        return match ($this) {
            self::Income => $account->type !== FinancialAccountType::Expense,
            self::Expense => in_array($account->type, [
                FinancialAccountType::Asset,
                FinancialAccountType::Liability,
                FinancialAccountType::Expense,
            ], true),
            self::Transfer => $account->type === FinancialAccountType::Asset
                && str_starts_with($account->code, '10'),
        };
    }
}
