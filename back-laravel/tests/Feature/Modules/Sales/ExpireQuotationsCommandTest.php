<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Models\Currency;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Sales\Enums\QuotationStatus;
use App\Modules\Sales\Models\Quotation;
use Database\Seeders\ClientSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpireQuotationsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemParameterSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ClientSeeder::class);

        Currency::clearBaseCache();
    }

    protected function tearDown(): void
    {
        Currency::clearBaseCache();
        parent::tearDown();
    }

    public function test_sales_expire_quotations_command_expires_only_overdue_sent_quotations(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $client = Client::factory()->create(['is_active' => true]);
        $currency = Currency::factory()->base()->create(['code' => 'PEN', 'decimals' => 2]);

        // 1. Enviada y vencida (debe expirar)
        $overdueSent = Quotation::factory()->create([
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => QuotationStatus::Sent,
            'valid_until' => Carbon::yesterday()->toDateString(),
        ]);

        // 2. Enviada pero vigente (NO debe expirar)
        $activeSent = Quotation::factory()->create([
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => QuotationStatus::Sent,
            'valid_until' => Carbon::tomorrow()->toDateString(),
        ]);

        // 3. Borrador vencido (NO debe expirar porque no ha sido enviada)
        $draftOverdue = Quotation::factory()->create([
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => QuotationStatus::Draft,
            'valid_until' => Carbon::yesterday()->toDateString(),
        ]);

        // 4. Aceptada con fecha vencida (NO debe expirar porque ya fue aceptada)
        $acceptedOverdue = Quotation::factory()->create([
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => QuotationStatus::Accepted,
            'valid_until' => Carbon::yesterday()->toDateString(),
        ]);

        $this->artisan('sales:expire-quotations')
            ->expectsOutputToContain('Se marcaron 1 cotizaciones como vencidas.')
            ->assertExitCode(0);

        $this->assertEquals(QuotationStatus::Expired, $overdueSent->fresh()?->status);
        $this->assertEquals(QuotationStatus::Sent, $activeSent->fresh()?->status);
        $this->assertEquals(QuotationStatus::Draft, $draftOverdue->fresh()?->status);
        $this->assertEquals(QuotationStatus::Accepted, $acceptedOverdue->fresh()?->status);
    }
}
