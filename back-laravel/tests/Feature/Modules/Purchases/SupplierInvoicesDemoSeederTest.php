<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Purchases;

use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Purchases\Enums\SupplierInvoiceStatus;
use App\Modules\Purchases\Models\PurchaseReceipt;
use App\Modules\Purchases\Models\SupplierInvoice;
use App\Modules\Purchases\Models\SupplierPayment;
use App\Support\Pricing\Decimal;
use Database\Seeders\AreaSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\FinanceDemoSeeder;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\InventoryMastersSeeder;
use Database\Seeders\ProviderSeeder;
use Database\Seeders\PurchasesDemoSeeder;
use Database\Seeders\PurchasesReceiptsDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SupplierInvoicesDemoSeeder;
use Database\Seeders\SystemParameterSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierInvoicesDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SystemParameterSeeder::class,
            UserSeeder::class,
            BranchSeeder::class,
            AreaSeeder::class,
            InventoryMastersSeeder::class,
            CatalogSeeder::class,
            FinanceMastersSeeder::class,
            FinanceDemoSeeder::class,
            ProviderSeeder::class,
            PurchasesDemoSeeder::class,
            PurchasesReceiptsDemoSeeder::class,
        ]);
    }

    public function test_it_registers_the_invoices_cited_by_the_demo_receipts(): void
    {
        $this->seed(SupplierInvoicesDemoSeeder::class);

        foreach (['RC-000001' => ['F001', '00214'], 'RC-000002' => ['F002', '00088']] as $receiptNumber => [$series, $number]) {
            $receipt = PurchaseReceipt::where('number', $receiptNumber)->firstOrFail();

            /** @var SupplierInvoice $invoice */
            $invoice = $receipt->supplierInvoices()->with('installments')->firstOrFail();

            $this->assertSame($series, $invoice->series);
            $this->assertSame($number, $invoice->document_number);
            $this->assertSame($receipt->provider_id, $invoice->provider_id);
            $this->assertSame($receipt->purchase_order_id, $invoice->purchase_order_id);
            $this->assertCount(1, $invoice->installments);
        }
    }

    public function test_it_pays_part_of_the_first_invoice_through_the_bank_ledger(): void
    {
        $this->seed(SupplierInvoicesDemoSeeder::class);

        $paid = PurchaseReceipt::where('number', 'RC-000001')->firstOrFail()->supplierInvoices()->firstOrFail();
        $this->assertSame(SupplierInvoiceStatus::PartiallyPaid, $paid->status);
        $this->assertSame('15000.00', $paid->paid_amount);
        $this->assertSame(Decimal::sub($paid->total, '15000.00', 2), $paid->balance);

        $pending = PurchaseReceipt::where('number', 'RC-000002')->firstOrFail()->supplierInvoices()->firstOrFail();
        $this->assertSame(SupplierInvoiceStatus::Pending, $pending->status);
        $this->assertSame($pending->total, $pending->balance);

        $account = BankAccount::where('alias', 'BCP Soles')->firstOrFail();
        $this->assertSame('35000.00', $account->balance);
        $this->assertSame(1, BankMovement::where('bank_account_id', $account->id)->count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(SupplierInvoicesDemoSeeder::class);
        $this->seed(SupplierInvoicesDemoSeeder::class);

        $this->assertSame(2, SupplierInvoice::count());
        $this->assertSame(1, SupplierPayment::count());
    }
}
