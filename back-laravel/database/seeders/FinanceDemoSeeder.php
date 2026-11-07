<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Finance\Enums\BankAccountType;
use App\Modules\Finance\Enums\PaymentMethodKind;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\ExchangeRate;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Roles\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Datos de muestra de finanzas (solo local/testing). Depende de FinanceMastersSeeder y BranchSeeder.
 * Es idempotente.
 */
class FinanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedExchangeRates();
        $bankAccounts = $this->seedBankAccounts();
        $this->seedPaymentMethods($bankAccounts);
        $this->seedBoxes();
    }

    protected function seedExchangeRates(): void
    {
        $rates = [
            'USD' => [3.7400, 3.7700],
            'EUR' => [4.0600, 4.1200],
        ];

        foreach ($rates as $code => [$buy, $sell]) {
            $currency = Currency::where('code', $code)->first();

            if ($currency === null || $currency->is_base) {
                continue;
            }

            ExchangeRate::updateOrCreate(
                ['currency_id' => $currency->id, 'date' => today()->toDateString()],
                ['buy_rate' => $buy, 'sell_rate' => $sell]
            );
        }
    }

    /**
     * @return array<string, BankAccount> alias => cuenta
     */
    protected function seedBankAccounts(): array
    {
        $branchId = Branch::where('is_main', true)->value('id');

        $accounts = [
            ['bank' => 'Banco de Crédito del Perú', 'currency' => 'PEN', 'alias' => 'BCP Soles', 'number' => '19412345678012', 'cci' => '00219400123456780123', 'type' => BankAccountType::Checking],
            ['bank' => 'Banco de Crédito del Perú', 'currency' => 'USD', 'alias' => 'BCP Dólares', 'number' => '19422345678123', 'cci' => '00219400223456781234', 'type' => BankAccountType::Checking],
            ['bank' => 'BBVA Perú', 'currency' => 'PEN', 'alias' => 'BBVA Soles', 'number' => '001108140200123456', 'cci' => '01181400020012345601', 'type' => BankAccountType::Savings],
            ['bank' => 'Interbank', 'currency' => 'PEN', 'alias' => 'Interbank Soles', 'number' => '2003001234567', 'cci' => '00320000300123456722', 'type' => BankAccountType::Checking],
        ];

        $created = [];

        foreach ($accounts as $data) {
            $bank = Bank::where('name', $data['bank'])->first();
            $currency = Currency::where('code', $data['currency'])->first();

            if ($bank === null || $currency === null) {
                continue;
            }

            $created[$data['alias']] = BankAccount::updateOrCreate(
                ['bank_id' => $bank->id, 'account_number' => $data['number']],
                [
                    'currency_id' => $currency->id,
                    'branch_id' => $branchId,
                    'alias' => $data['alias'],
                    'cci' => $data['cci'],
                    'account_type' => $data['type'],
                    'holder_name' => 'Empresa Demo S.A.C.',
                    'is_active' => true,
                ]
            );
        }

        return $created;
    }

    /**
     * @param  array<string, BankAccount>  $bankAccounts
     */
    protected function seedPaymentMethods(array $bankAccounts): void
    {
        $methods = [
            ['code' => 'TRANSFER_BCP', 'name' => 'Transferencia BCP', 'kind' => PaymentMethodKind::Transfer, 'account' => 'BCP Soles', 'reference' => true, 'sunat' => '003', 'order' => 2],
            ['code' => 'TRANSFER_BBVA', 'name' => 'Transferencia BBVA', 'kind' => PaymentMethodKind::Transfer, 'account' => 'BBVA Soles', 'reference' => true, 'sunat' => '003', 'order' => 3],
            ['code' => 'YAPE', 'name' => 'Yape', 'kind' => PaymentMethodKind::Wallet, 'account' => 'BCP Soles', 'reference' => true, 'sunat' => '008', 'order' => 4],
            ['code' => 'PLIN', 'name' => 'Plin', 'kind' => PaymentMethodKind::Wallet, 'account' => 'Interbank Soles', 'reference' => true, 'sunat' => '008', 'order' => 5],
            ['code' => 'CARD_POS', 'name' => 'Tarjeta (POS)', 'kind' => PaymentMethodKind::Card, 'account' => 'Interbank Soles', 'reference' => true, 'sunat' => '006', 'order' => 6],
        ];

        foreach ($methods as $data) {
            $account = $bankAccounts[$data['account']] ?? null;

            if ($account === null) {
                continue;
            }

            PaymentMethod::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'kind' => $data['kind'],
                    'settlement' => Settlement::BankAccount,
                    'bank_account_id' => $account->id,
                    'requires_reference' => $data['reference'],
                    'sunat_code' => $data['sunat'],
                    'sort_order' => $data['order'],
                    'is_system' => false,
                    'is_active' => true,
                ]
            );
        }
    }

    protected function seedBoxes(): void
    {
        /** @var Branch|null $mainBranch */
        $mainBranch = Branch::where('is_main', true)->first();

        if ($mainBranch === null) {
            return;
        }

        /** @var Box $box */
        $box = Box::updateOrCreate(
            [
                'code' => 'CAJA01',
            ],
            [
                'branch_id' => $mainBranch->id,
                'name' => 'Caja Principal 01',
                'description' => 'Caja de cobro principal de la sede central.',
                'is_active' => true,
            ]
        );

        $userRole = Role::where('name', 'user')->first();
        $managerRoleForCaja01 = Role::where('name', 'manager')->first();
        $box->roles()->syncWithoutDetaching(array_filter([
            $userRole?->id,
            $managerRoleForCaja01?->id,
        ]));

        /** @var Branch|null $miraflores */
        $miraflores = Branch::where('code', 'MIRAFLORES')->first();

        if ($miraflores === null) {
            return;
        }

        /** @var Box $box2 */
        $box2 = Box::updateOrCreate(
            [
                'code' => 'CAJA02',
            ],
            [
                'branch_id' => $miraflores->id,
                'name' => 'Caja Miraflores 01',
                'description' => 'Caja de cobro de la sucursal Miraflores.',
                'is_active' => true,
            ]
        );

        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole !== null) {
            $box2->roles()->syncWithoutDetaching([$managerRole->id]);
        }
    }
}
