<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Configuration\Services\CompanySettingService;
use App\Modules\Finance\Enums\PaymentMethodKind;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class FinanceMastersSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCurrencies();
        $this->seedBanks();
        $this->seedPaymentMethods();

        $this->call([
            FinancialAccountSeeder::class,
            FinanceConceptSeeder::class,
        ]);
    }

    protected function seedCurrencies(): void
    {
        $company = app(CompanySettingService::class)->current();
        $baseCode = $company->default_currency_code ?: 'PEN';

        $currencies = [
            'PEN' => [
                'name' => 'Sol Peruano',
                'symbol' => 'S/',
                'sunat_code' => 'PEN',
                'decimals' => 2,
            ],
            'USD' => [
                'name' => 'Dólar Estadounidense',
                'symbol' => '$',
                'sunat_code' => 'USD',
                'decimals' => 2,
            ],
            'EUR' => [
                'name' => 'Euro',
                'symbol' => '€',
                'sunat_code' => 'EUR',
                'decimals' => 2,
            ],
        ];

        // Si la moneda configurada no está en la lista estándar, la agregamos
        if (! isset($currencies[$baseCode])) {
            $currencies[$baseCode] = [
                'name' => 'Moneda '.$baseCode,
                'symbol' => $baseCode,
                'sunat_code' => $baseCode,
                'decimals' => 2,
            ];
        }

        foreach ($currencies as $code => $data) {
            $isBase = ($code === $baseCode);

            Currency::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'symbol' => $data['symbol'],
                    'sunat_code' => $data['sunat_code'],
                    'decimals' => $data['decimals'],
                    'is_base' => $isBase,
                    'is_active' => true,
                ]
            );
        }

        Currency::clearBaseCache();
    }

    protected function seedBanks(): void
    {
        $banks = [
            ['name' => 'Banco de Crédito del Perú', 'short_name' => 'BCP'],
            ['name' => 'BBVA Perú', 'short_name' => 'BBVA'],
            ['name' => 'Interbank', 'short_name' => 'INTERBANK'],
            ['name' => 'Scotiabank Perú', 'short_name' => 'SCOTIABANK'],
            ['name' => 'Banco de la Nación', 'short_name' => 'BN'],
            ['name' => 'BanBif', 'short_name' => 'BANBIF'],
            ['name' => 'Banco Pichincha', 'short_name' => 'PICHINCHA'],
        ];

        foreach ($banks as $data) {
            Bank::updateOrCreate(
                ['name' => $data['name']],
                [
                    'short_name' => $data['short_name'],
                    'is_active' => true,
                ]
            );
        }
    }

    protected function seedPaymentMethods(): void
    {
        PaymentMethod::updateOrCreate(
            ['code' => 'CASH'],
            [
                'name' => 'Efectivo',
                'kind' => PaymentMethodKind::Cash,
                'settlement' => Settlement::CashBox,
                'bank_account_id' => null,
                'requires_reference' => false,
                'sunat_code' => '008',
                'sort_order' => 1,
                'is_system' => true,
                'is_active' => true,
            ]
        );
    }
}
