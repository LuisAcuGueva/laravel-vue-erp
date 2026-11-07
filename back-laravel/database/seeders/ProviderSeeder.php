<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\Currency;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\ProviderBankAccount;
use App\Modules\Purchases\Models\ProviderContact;
use App\Modules\Purchases\Models\ProviderPrice;
use App\Support\Identity\DocumentType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        $pen = Currency::where('code', 'PEN')->first();
        $usd = Currency::where('code', 'USD')->first();

        $bcp = Bank::where('short_name', 'BCP')->first();
        $bbva = Bank::where('short_name', 'BBVA')->first();
        $interbank = Bank::where('short_name', 'INTERBANK')->first();

        $catalog = CatalogItem::with('defaultPresentation')->get()->keyBy('code');

        // 1. Proveedor Tecnológico
        $techProvider = Provider::updateOrCreate(
            [
                'document_type' => DocumentType::Ruc,
                'document_number' => '20512345671',
            ],
            [
                'business_name' => 'Mayorista Tecnológico S.A.C.',
                'trade_name' => 'TecnoMayorista Perú',
                'address' => 'Av. Javier Prado Este 4200, Edificio Cómputo Piso 8',
                'ubigeo' => '150131', // San Isidro
                'email' => 'ventas@tecnomayorista.pe',
                'phone' => '01-4458900',
                'website' => 'https://tecnomayorista.pe',
                'default_currency_id' => $pen?->id,
                'credit_days' => 30,
                'notes' => 'Distribuidor mayorista oficial de Lenovo, Samsung, Xiaomi y componentes.',
                'is_active' => true,
            ]
        );

        ProviderContact::updateOrCreate(
            ['provider_id' => $techProvider->id, 'name' => 'Carlos Mendoza Paredes'],
            [
                'position' => 'Gerente de Cuentas Corporativas',
                'phone' => '998112233',
                'email' => 'cmendoza@tecnomayorista.pe',
                'is_primary' => true,
            ]
        );

        if ($bcp !== null && $pen !== null) {
            ProviderBankAccount::updateOrCreate(
                [
                    'provider_id' => $techProvider->id,
                    'bank_id' => $bcp->id,
                    'account_number' => '191-2345678-0-12',
                ],
                [
                    'currency_id' => $pen->id,
                    'cci' => '00219100234567801254',
                    'is_detraction_account' => false,
                ]
            );
        }

        if ($bcp !== null && $usd !== null) {
            ProviderBankAccount::updateOrCreate(
                [
                    'provider_id' => $techProvider->id,
                    'bank_id' => $bcp->id,
                    'account_number' => '191-9876543-1-45',
                ],
                [
                    'currency_id' => $usd->id,
                    'cci' => '00219100987654314589',
                    'is_detraction_account' => false,
                ]
            );
        }

        // Precios pactados de tecnología
        $techPrices = [
            'ITM-000001' => 2850.00, // Laptop Lenovo
            'ITM-000002' => 820.00,  // Monitor Samsung
            'ITM-000003' => 160.00,  // Teclado Xiaomi
            'ITM-000004' => 65.00,   // Mouse Xiaomi
            'ITM-000005' => 230.00,  // SSD 1TB
            'ITM-000006' => 150.00,  // RAM 16GB
            'ITM-000007' => 620.00,  // Impresora Epson
            'ITM-000009' => 18.00,   // Cable HDMI
        ];

        foreach ($techPrices as $code => $price) {
            $item = $catalog->get($code);
            if ($item?->defaultPresentation !== null && $pen !== null) {
                ProviderPrice::updateOrCreate(
                    [
                        'provider_id' => $techProvider->id,
                        'item_presentation_id' => $item->defaultPresentation->id,
                    ],
                    [
                        'currency_id' => $pen->id,
                        'price' => $price,
                        'valid_from' => Carbon::now()->startOfYear()->toDateString(),
                        'valid_until' => null,
                        'notes' => 'Tarifa de contrato anual mayorista',
                        'last_purchase_price' => $price,
                        'last_purchase_at' => Carbon::now()->subDays(10),
                    ]
                );
            }
        }

        // 2. Proveedor Papelería y Útiles
        $paperProvider = Provider::updateOrCreate(
            [
                'document_type' => DocumentType::Ruc,
                'document_number' => '20601234565',
            ],
            [
                'business_name' => 'Distribuidora Papelera & Suministros S.A.',
                'trade_name' => 'Papelera Central',
                'address' => 'Av. Los Frutales 850, Urbanización El Artesano',
                'ubigeo' => '150115', // La Molina
                'email' => 'ventas@papeleracentral.com.pe',
                'phone' => '01-3489200',
                'website' => 'https://papeleracentral.com.pe',
                'default_currency_id' => $pen?->id,
                'credit_days' => 15,
                'notes' => 'Proveedor de resmas de papel, útiles de oficina y material de imprenta.',
                'is_active' => true,
            ]
        );

        ProviderContact::updateOrCreate(
            ['provider_id' => $paperProvider->id, 'name' => 'Lucía Alarcón Soto'],
            [
                'position' => 'Ejecutiva Comercial Corporativa',
                'phone' => '977223344',
                'email' => 'lalarcon@papeleracentral.com.pe',
                'is_primary' => true,
            ]
        );

        if ($bbva !== null && $pen !== null) {
            ProviderBankAccount::updateOrCreate(
                [
                    'provider_id' => $paperProvider->id,
                    'bank_id' => $bbva->id,
                    'account_number' => '0011-0182-0100034567',
                ],
                [
                    'currency_id' => $pen->id,
                    'cci' => '01118200010003456743',
                    'is_detraction_account' => false,
                ]
            );
        }

        $paperPrices = [
            'ITM-000008' => 11.50, // Papel A4 paquete
            'ITM-000015' => 0.50,  // Lapicero Azul
        ];

        foreach ($paperPrices as $code => $price) {
            $item = $catalog->get($code);
            if ($item?->defaultPresentation !== null && $pen !== null) {
                ProviderPrice::updateOrCreate(
                    [
                        'provider_id' => $paperProvider->id,
                        'item_presentation_id' => $item->defaultPresentation->id,
                    ],
                    [
                        'currency_id' => $pen->id,
                        'price' => $price,
                        'valid_from' => Carbon::now()->startOfYear()->toDateString(),
                        'valid_until' => null,
                        'notes' => 'Precio corporativo suministros de oficina',
                        'last_purchase_price' => $price,
                        'last_purchase_at' => Carbon::now()->subDays(15),
                    ]
                );
            }
        }

        // 3. Proveedor Alimentos y Abarrotes
        $foodProvider = Provider::updateOrCreate(
            [
                'document_type' => DocumentType::Ruc,
                'document_number' => '20498765433',
            ],
            [
                'business_name' => 'Consorcio Alimentos & Abarrotes del Perú S.A.C.',
                'trade_name' => 'Abarrotes del Perú',
                'address' => 'Jr. Huancavelica 1050, Cercado de Lima',
                'ubigeo' => '150101', // Lima
                'email' => 'pedidos@abarrotesdelperu.pe',
                'phone' => '01-4287654',
                'website' => null,
                'default_currency_id' => $pen?->id,
                'credit_days' => 15,
                'notes' => 'Distribución de productos de consumo masivo, granos y lácteos.',
                'is_active' => true,
            ]
        );

        ProviderContact::updateOrCreate(
            ['provider_id' => $foodProvider->id, 'name' => 'Roberto Sánchez Vega'],
            [
                'position' => 'Jefe de Distribución',
                'phone' => '966334455',
                'email' => 'rsanchez@abarrotesdelperu.pe',
                'is_primary' => true,
            ]
        );

        if ($interbank !== null && $pen !== null) {
            ProviderBankAccount::updateOrCreate(
                [
                    'provider_id' => $foodProvider->id,
                    'bank_id' => $interbank->id,
                    'account_number' => '200-3001234567',
                ],
                [
                    'currency_id' => $pen->id,
                    'cci' => '00320000300123456788',
                    'is_detraction_account' => false,
                ]
            );
        }

        $foodPrices = [
            'ITM-000010' => 3.10,  // Arroz Extra kg
            'ITM-000011' => 6.20,  // Aceite Vegetal L
            'ITM-000012' => 3.20,  // Leche Evaporada
            'ITM-000013' => 14.00, // Detergente 2kg
            'ITM-000014' => 4.50,  // Pasta Dental
        ];

        foreach ($foodPrices as $code => $price) {
            $item = $catalog->get($code);
            if ($item?->defaultPresentation !== null && $pen !== null) {
                ProviderPrice::updateOrCreate(
                    [
                        'provider_id' => $foodProvider->id,
                        'item_presentation_id' => $item->defaultPresentation->id,
                    ],
                    [
                        'currency_id' => $pen->id,
                        'price' => $price,
                        'valid_from' => Carbon::now()->startOfYear()->toDateString(),
                        'valid_until' => null,
                        'notes' => 'Tarifa mayorista abarrotes',
                        'last_purchase_price' => $price,
                        'last_purchase_at' => Carbon::now()->subDays(5),
                    ]
                );
            }
        }

        // 4. Proveedor Ferretería e Industrial
        $hardwareProvider = Provider::updateOrCreate(
            [
                'document_type' => DocumentType::Ruc,
                'document_number' => '20555444339',
            ],
            [
                'business_name' => 'Comercializadora Industrial & Ferretera E.I.R.L.',
                'trade_name' => 'Ferretería Industrial',
                'address' => 'Av. Nicolás Ayllón 2450, Ate Vitarte',
                'ubigeo' => '150103', // Ate
                'email' => 'contacto@industrialferretera.pe',
                'phone' => '01-3519800',
                'website' => null,
                'default_currency_id' => $pen?->id,
                'credit_days' => 0,
                'notes' => 'Herramientas eléctricas Bosch, lubricantes y suministros de mantenimiento.',
                'is_active' => true,
            ]
        );

        ProviderContact::updateOrCreate(
            ['provider_id' => $hardwareProvider->id, 'name' => 'Jorge Huamán Quispe'],
            [
                'position' => 'Asesor Técnico de Ventas',
                'phone' => '955443322',
                'email' => 'jhuaman@industrialferretera.pe',
                'is_primary' => true,
            ]
        );

        if ($bcp !== null && $pen !== null) {
            ProviderBankAccount::updateOrCreate(
                [
                    'provider_id' => $hardwareProvider->id,
                    'bank_id' => $bcp->id,
                    'account_number' => '193-4567890-0-23',
                ],
                [
                    'currency_id' => $pen->id,
                    'cci' => '00219300456789002361',
                    'is_detraction_account' => false,
                ]
            );
        }

        $hardwarePrices = [
            'ITM-000016' => 280.00, // Taladro Bosch
            'ITM-000017' => 60.00,  // Aceite Motor Galón
        ];

        foreach ($hardwarePrices as $code => $price) {
            $item = $catalog->get($code);
            if ($item?->defaultPresentation !== null && $pen !== null) {
                ProviderPrice::updateOrCreate(
                    [
                        'provider_id' => $hardwareProvider->id,
                        'item_presentation_id' => $item->defaultPresentation->id,
                    ],
                    [
                        'currency_id' => $pen->id,
                        'price' => $price,
                        'valid_from' => Carbon::now()->startOfYear()->toDateString(),
                        'valid_until' => null,
                        'notes' => 'Tarifa distribuidor herramientas',
                        'last_purchase_price' => $price,
                        'last_purchase_at' => Carbon::now()->subDays(20),
                    ]
                );
            }
        }
    }
}
