<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Punto de partida para pruebas manuales de caja, bancos, compras y ventas:
 * mantenimiento + maestros + catálogo/proveedores + compras y movimientos
 * que cargan el inventario inicial, más las series de documentos de venta
 * (sin emitir ningún documento). Deliberadamente NO incluye SalesDemoSeeder,
 * QuotationsDemoSeeder, CommercialOperationsDemoSeeder, BankPeriodsDemoSeeder
 * ni BankPeriodCloseDemoSeeder: esas transacciones las crea el usuario a mano.
 *
 * Mismo orden relativo que DatabaseSeeder para respetar sus dependencias.
 */
class InventoryBaselineSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
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
            CatalogSeeder::class,
            ProviderSeeder::class,
            PurchasesDemoSeeder::class,
            PurchasesReceiptsDemoSeeder::class,
            StockDemoSeeder::class,
            InventoryCountDemoSeeder::class,
            FinanceDemoSeeder::class,
            SupplierInvoicesDemoSeeder::class,
            SalesDocumentSeriesSeeder::class,
        ]);
    }
}
