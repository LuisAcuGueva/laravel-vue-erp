<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\FinancialAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NormalizeFinancialAccountCodesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migrate(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_10_21_000001_normalize_financial_account_codes.php');
        $migration->up();
    }

    private function legacy(string $code, string $type, ?string $parentCode = null): FinancialAccount
    {
        return FinancialAccount::create([
            'code' => $code,
            'name' => 'Cuenta '.$code,
            'type' => $type,
            'parent_id' => $parentCode !== null ? FinancialAccount::where('code', $parentCode)->value('id') : null,
            'level' => $parentCode !== null ? 2 : 1,
            'is_postable' => $parentCode === null ? false : true,
        ]);
    }

    /**
     * @return array<string, FinancialAccount>
     */
    private function seedLegacy(): array
    {
        $a = [];
        $a['10'] = $this->legacy('10', 'asset');
        $a['10.1'] = $this->legacy('10.1', 'asset', '10');
        $a['10.4'] = $this->legacy('10.4', 'asset', '10');
        $a['65'] = $this->legacy('65', 'expense');
        $a['65.1'] = $this->legacy('65.1', 'expense', '65');
        $a['65.2'] = $this->legacy('65.2', 'expense', '65');
        $a['70'] = $this->legacy('70', 'income');
        $a['70.1'] = $this->legacy('70.1', 'income', '70');

        return $a;
    }

    public function test_legacy_codes_are_renamed_keeping_ids_and_concepts(): void
    {
        $a = $this->seedLegacy();
        $bank = FinanceConcept::factory()->create(['financial_account_id' => $a['10.4']->id]);
        $services = FinanceConcept::factory()->expense()->create(['financial_account_id' => $a['65.1']->id]);
        $other = FinanceConcept::factory()->expense()->create(['financial_account_id' => $a['65']->id]);

        $this->migrate();

        $this->assertSame('104', $a['10.4']->fresh()->code);
        $this->assertSame('101', $a['10.1']->fresh()->code);
        $this->assertSame('636', $a['65.1']->fresh()->code);
        $this->assertSame('635', $a['65.2']->fresh()->code);
        $this->assertSame(FinancialAccount::where('code', '63')->value('id'), $a['65.1']->fresh()->parent_id);

        $this->assertSame(FinancialAccount::where('code', '1041')->value('id'), $bank->fresh()->financial_account_id);
        $this->assertSame(FinancialAccount::where('code', '6361')->value('id'), $services->fresh()->financial_account_id);
        $this->assertSame(FinancialAccount::where('code', '659')->value('id'), $other->fresh()->financial_account_id);

        $this->assertSame(0, FinancialAccount::where('code', 'like', '%.%')->count());
        $this->assertFalse($a['65']->fresh()->is_postable);
    }

    public function test_second_run_changes_nothing(): void
    {
        $this->seedLegacy();
        $this->migrate();
        $first = FinancialAccount::withTrashed()->orderBy('id')->pluck('code', 'id')->all();

        $this->migrate();

        $this->assertSame($first, FinancialAccount::withTrashed()->orderBy('id')->pluck('code', 'id')->all());
    }

    public function test_existing_target_is_merged_and_old_account_soft_deleted(): void
    {
        $a = $this->seedLegacy();
        $existing = FinancialAccount::create(['code' => '636', 'name' => 'Servicios básicos', 'type' => 'expense', 'level' => 1, 'is_postable' => true]);
        $concept = FinanceConcept::factory()->expense()->create(['financial_account_id' => $a['65.1']->id]);

        $this->migrate();

        $this->assertSoftDeleted('financial_accounts', ['id' => $a['65.1']->id]);
        $this->assertSame(1, FinancialAccount::where('code', '636')->count());
        $this->assertSame($existing->id, FinancialAccount::where('code', '636')->value('id'));
        $this->assertSame(FinancialAccount::where('code', '6361')->value('id'), $concept->fresh()->financial_account_id);
    }

    public function test_empty_database_migrates_without_error(): void
    {
        $this->migrate();

        $this->assertSame(0, FinancialAccount::count());
    }
}
