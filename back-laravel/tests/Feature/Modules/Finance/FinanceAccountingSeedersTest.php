<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\FinancialAccount;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceAccountingSeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new SystemParameterSeeder)->run();
        (new FinanceMastersSeeder)->run();
    }

    /**
     * @return array<string, string|null>
     */
    private function snapshot(): array
    {
        return FinanceConcept::all()
            ->mapWithKeys(fn (FinanceConcept $c) => [$c->id.'|'.$c->code => $c->financial_account_id])
            ->all();
    }

    public function test_plan_is_seeded_with_pcge_codes_and_postable_leaves_only(): void
    {
        /** @var list<array{code: string}> $catalog */
        $catalog = require database_path('data/financial_accounts_pcge.php');

        foreach ($catalog as $row) {
            $this->assertNotNull(FinancialAccount::where('code', $row['code'])->first(), $row['code']);
        }

        $this->assertSame(0, FinancialAccount::where('code', 'like', '%.%')->count());

        foreach (FinancialAccount::with('children')->get() as $account) {
            $this->assertSame($account->children->isEmpty(), $account->is_postable, $account->code);
        }
    }

    public function test_system_and_operational_concepts_have_their_account(): void
    {
        foreach (SystemConcept::cases() as $system) {
            $concept = FinanceConcept::with('financialAccount')->where('code', $system->value)->firstOrFail();
            $this->assertTrue($concept->is_system);
            $this->assertSame($system->defaultAccountCode(), $concept->financialAccount?->code, $system->value);
        }

        $operational = FinanceConcept::where('is_system', false)->get();
        $this->assertCount(30, $operational);
        $this->assertSame(0, $operational->whereNull('financial_account_id')->count());
    }

    public function test_seeding_again_changes_nothing_and_keeps_reassigned_accounts(): void
    {
        $concept = FinanceConcept::where('code', 'CASH_SHORTAGE')->firstOrFail();
        $concept->update(['financial_account_id' => FinancialAccount::where('code', '1419')->firstOrFail()->id]);
        $accounts = FinancialAccount::orderBy('code')->pluck('id', 'code')->all();
        $snapshot = $this->snapshot();

        (new FinanceMastersSeeder)->run();

        $this->assertSame($accounts, FinancialAccount::orderBy('code')->pluck('id', 'code')->all());
        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame('1419', $concept->fresh('financialAccount')->financialAccount?->code);
    }

    public function test_require_concept_account_parameter_defaults_to_false(): void
    {
        $this->assertFalse(app(SystemParameterService::class)->boolean('finance.require_concept_account', true));
    }
}
