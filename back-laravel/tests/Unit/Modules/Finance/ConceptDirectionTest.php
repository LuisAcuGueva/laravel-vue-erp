<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance;

use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Models\FinancialAccount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConceptDirectionTest extends TestCase
{
    /**
     * @return array<string, array{ConceptDirection, FinancialAccountType, string, bool}>
     */
    public static function matrix(): array
    {
        return [
            'income asset' => [ConceptDirection::Income, FinancialAccountType::Asset, '1212', true],
            'income liability' => [ConceptDirection::Income, FinancialAccountType::Liability, '4511', true],
            'income equity' => [ConceptDirection::Income, FinancialAccountType::Equity, '501', true],
            'income income' => [ConceptDirection::Income, FinancialAccountType::Income, '759', true],
            'income expense' => [ConceptDirection::Income, FinancialAccountType::Expense, '659', false],
            'expense asset' => [ConceptDirection::Expense, FinancialAccountType::Asset, '1413', true],
            'expense liability' => [ConceptDirection::Expense, FinancialAccountType::Liability, '4511', true],
            'expense equity' => [ConceptDirection::Expense, FinancialAccountType::Equity, '501', false],
            'expense income' => [ConceptDirection::Expense, FinancialAccountType::Income, '759', false],
            'expense expense' => [ConceptDirection::Expense, FinancialAccountType::Expense, '6361', true],
            'transfer asset 10' => [ConceptDirection::Transfer, FinancialAccountType::Asset, '103', true],
            'transfer asset 12' => [ConceptDirection::Transfer, FinancialAccountType::Asset, '1212', false],
            'transfer liability' => [ConceptDirection::Transfer, FinancialAccountType::Liability, '4511', false],
            'transfer equity' => [ConceptDirection::Transfer, FinancialAccountType::Equity, '501', false],
            'transfer expense' => [ConceptDirection::Transfer, FinancialAccountType::Expense, '659', false],
        ];
    }

    #[DataProvider('matrix')]
    public function test_allows_account_follows_matrix(ConceptDirection $direction, FinancialAccountType $type, string $code, bool $expected): void
    {
        $account = new FinancialAccount(['code' => $code, 'type' => $type]);

        $this->assertSame($expected, $direction->allowsAccount($account));
    }

    public function test_every_system_concept_has_a_default_account_code(): void
    {
        foreach (SystemConcept::cases() as $concept) {
            $this->assertMatchesRegularExpression('/^\d{2,8}$/', $concept->defaultAccountCode());
        }
    }
}
