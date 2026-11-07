<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seeders = [
            RolePermissionSeeder::class,
            UserSeeder::class,
            UbigeoSeeder::class,
            BranchSeeder::class,
            AreaSeeder::class,
            EmployeeSeeder::class,
            ClientSeeder::class,
            SystemParameterSeeder::class,
            InventoryMastersSeeder::class,
            FinanceMastersSeeder::class,
        ];

        if (app()->environment('local', 'testing')) {
            $seeders[] = CatalogSeeder::class;
            $seeders[] = ProviderSeeder::class;
            $seeders[] = PurchasesDemoSeeder::class;
            $seeders[] = PurchasesReceiptsDemoSeeder::class;
            $seeders[] = StockDemoSeeder::class;
            $seeders[] = InventoryCountDemoSeeder::class;
            $seeders[] = FinanceDemoSeeder::class;
            $seeders[] = SupplierInvoicesDemoSeeder::class;
            $seeders[] = SalesDemoSeeder::class;
            $seeders[] = BankPeriodsDemoSeeder::class;
            $seeders[] = QuotationsDemoSeeder::class;
            $seeders[] = CommercialOperationsDemoSeeder::class;
            $seeders[] = BankPeriodCloseDemoSeeder::class;
        }

        $this->call($seeders);
    }
}
