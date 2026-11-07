<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\ExchangeRate;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\InventoryCount;
use App\Modules\Inventory\Models\InventoryCountItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\ItemPrice;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\ProviderContact;
use App\Modules\Purchases\Models\ProviderPrice;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseRequest;
use Database\Seeders\AreaSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\FinanceDemoSeeder;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\InventoryCountDemoSeeder;
use Database\Seeders\InventoryMastersSeeder;
use Database\Seeders\ProviderSeeder;
use Database\Seeders\PurchasesDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StockDemoSeeder;
use Database\Seeders\SystemParameterSeeder;
use Database\Seeders\UbigeoSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new BranchSeeder)->run();
        $this->seed([InventoryMastersSeeder::class, FinanceMastersSeeder::class]);
    }

    public function test_catalog_seeder_is_idempotent_and_assigns_distinct_categories(): void
    {
        $this->seed(CatalogSeeder::class);
        $counts = [CatalogItem::count(), ItemPresentation::count(), ItemPrice::count()];

        $this->seed(CatalogSeeder::class);

        $this->assertSame($counts, [CatalogItem::count(), ItemPresentation::count(), ItemPrice::count()]);
        $this->assertGreaterThan(1, CatalogItem::query()->distinct('category_id')->count('category_id'));
        $this->assertSame(1, ItemPresentation::query()->where('is_base', true)->where('catalog_item_id', CatalogItem::where('code', 'ITM-000001')->value('id'))->count());
    }

    public function test_finance_demo_seeder_is_idempotent(): void
    {
        $this->seed(FinanceDemoSeeder::class);
        $counts = [BankAccount::count(), PaymentMethod::count(), ExchangeRate::count()];

        $this->seed(FinanceDemoSeeder::class);

        $this->assertSame($counts, [BankAccount::count(), PaymentMethod::count(), ExchangeRate::count()]);
        $this->assertGreaterThan(0, min($counts));
    }

    public function test_provider_seeder_is_idempotent(): void
    {
        $this->seed([RolePermissionSeeder::class, UserSeeder::class, UbigeoSeeder::class, CatalogSeeder::class, ProviderSeeder::class]);
        $counts = [Provider::count(), ProviderContact::count(), ProviderPrice::count()];

        $this->seed(ProviderSeeder::class);

        $this->assertSame($counts, [Provider::count(), ProviderContact::count(), ProviderPrice::count()]);
        $this->assertGreaterThan(0, min($counts));
    }

    public function test_purchases_demo_seeder_is_idempotent_and_links_requests_to_orders(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            UserSeeder::class,
            UbigeoSeeder::class,
            AreaSeeder::class,
            SystemParameterSeeder::class,
            CatalogSeeder::class,
            ProviderSeeder::class,
            PurchasesDemoSeeder::class,
        ]);

        $requestsCount = PurchaseRequest::count();
        $ordersCount = PurchaseOrder::count();

        $this->assertGreaterThan(0, $requestsCount);
        $this->assertGreaterThan(0, $ordersCount);

        // Verifica que OC-000001 tenga líneas vinculadas a RQ-000001
        $oc1 = PurchaseOrder::where('number', 'OC-000001')->firstOrFail();
        $this->assertTrue($oc1->items()->whereNotNull('purchase_request_item_id')->exists());

        // Idempotencia
        $this->seed(PurchasesDemoSeeder::class);

        $this->assertSame($requestsCount, PurchaseRequest::count());
        $this->assertSame($ordersCount, PurchaseOrder::count());
    }

    public function test_inventory_count_demo_seeder_is_idempotent_and_links_opening_note(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            UserSeeder::class,
            UbigeoSeeder::class,
            AreaSeeder::class,
            SystemParameterSeeder::class,
            CatalogSeeder::class,
            ProviderSeeder::class,
            PurchasesDemoSeeder::class,
            StockDemoSeeder::class,
            InventoryCountDemoSeeder::class,
        ]);

        $countsTotal = InventoryCount::count();
        $this->assertGreaterThanOrEqual(3, $countsTotal);

        // Verifica que la toma de apertura TI-000001 esté cerrada y vinculada a NI-000001
        $ti1 = InventoryCount::where('number', 'TI-000001')->firstOrFail();
        $this->assertTrue($ti1->isClosed());
        $this->assertTrue($ti1->warehouseNotes()->where('number', 'NI-000001')->exists());

        // Verifica que la toma cíclica TI-000002 en tienda tenga notas de ajuste generadas (ingreso y salida)
        $ti2 = InventoryCount::where('number', 'TI-000002')->firstOrFail();
        $this->assertTrue($ti2->isClosed());
        $this->assertTrue($ti2->warehouseNotes()->where('number', 'NI-000004')->exists());
        $this->assertTrue($ti2->warehouseNotes()->where('number', 'NS-000005')->exists());

        // Verifica que exista una toma en proceso y una cancelada
        $this->assertTrue(InventoryCount::where('status', 'counting')->exists());
        $this->assertTrue(InventoryCount::where('status', 'cancelled')->exists());

        // Idempotencia
        $itemsTotal = InventoryCountItem::count();
        $this->seed(InventoryCountDemoSeeder::class);

        $this->assertSame($countsTotal, InventoryCount::count());
        $this->assertSame($itemsTotal, InventoryCountItem::count());
    }

    public function test_branch_and_employee_seeders_are_idempotent(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            UserSeeder::class,
            UbigeoSeeder::class,
            BranchSeeder::class,
            AreaSeeder::class,
            EmployeeSeeder::class,
        ]);

        $branchesCount = Branch::count();
        $employeesCount = Employee::count();

        $this->assertGreaterThanOrEqual(4, $branchesCount);
        $this->assertGreaterThanOrEqual(8, $employeesCount);

        // Verifica que existan empleados con usuario y sin usuario
        $this->assertTrue(Employee::whereNotNull('user_id')->exists());
        $this->assertTrue(Employee::whereNull('user_id')->exists());

        // Idempotencia
        $this->seed([BranchSeeder::class, EmployeeSeeder::class]);

        $this->assertSame($branchesCount, Branch::count());
        $this->assertSame($employeesCount, Employee::count());
    }
}
