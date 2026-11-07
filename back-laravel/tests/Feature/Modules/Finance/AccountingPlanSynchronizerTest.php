<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Support\AccountCode;
use App\Modules\Finance\Support\AccountingPlanSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingPlanSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{code: string, name: string, type: string}>
     */
    private function catalog(): array
    {
        return require database_path('data/financial_accounts_pcge.php');
    }

    private function sync(): AccountingPlanSynchronizer
    {
        return app(AccountingPlanSynchronizer::class);
    }

    private function account(string $code): FinancialAccount
    {
        return FinancialAccount::where('code', $code)->firstOrFail();
    }

    public function test_sync_accounts_builds_hierarchy_with_postable_leaves(): void
    {
        $this->sync()->syncAccounts($this->catalog());

        $this->assertSame(count($this->catalog()), FinancialAccount::count());

        $leaf = $this->account('6361');
        $this->assertSame($this->account('636')->id, $leaf->parent_id);
        $this->assertSame(3, $leaf->level);
        $this->assertTrue($leaf->is_postable);
        $this->assertFalse($this->account('636')->is_postable);
        $this->assertFalse($this->account('10')->is_postable);
        $this->assertSame(1, $this->account('10')->level);
        $this->assertSame(FinancialAccountType::Liability, $this->account('4212')->type);
        $this->assertSame(FinancialAccountType::Asset, $this->account('122')->type);
        $this->assertSame(FinancialAccountType::Liability, $this->account('422')->type);
    }

    public function test_sync_accounts_is_idempotent_and_keeps_user_edits(): void
    {
        $this->sync()->syncAccounts($this->catalog());
        $before = FinancialAccount::orderBy('code')->pluck('id', 'code')->all();

        $this->account('6361')->update(['name' => 'Luz', 'is_active' => false]);
        $this->sync()->syncAccounts($this->catalog());

        $this->assertSame($before, FinancialAccount::orderBy('code')->pluck('id', 'code')->all());
        $this->assertSame('Luz', $this->account('6361')->name);
        $this->assertFalse($this->account('6361')->is_active);
    }

    public function test_sync_accounts_does_not_restore_deleted_accounts(): void
    {
        $this->sync()->syncAccounts($this->catalog());
        $this->account('6365')->delete();

        $this->sync()->syncAccounts($this->catalog());

        $this->assertNull(FinancialAccount::where('code', '6365')->first());
        $this->assertSame(1, FinancialAccount::withTrashed()->where('code', '6365')->count());
    }

    public function test_every_catalog_concept_points_to_an_existing_leaf(): void
    {
        $codes = array_column($this->catalog(), 'code');

        /** @var list<array{code: string, name: string, direction: string, account_code: string}> $concepts */
        $concepts = require database_path('data/finance_concepts.php');

        $this->assertCount(30, $concepts);
        foreach ($concepts as $concept) {
            $this->assertContains($concept['account_code'], $codes, $concept['code']);
            $isParent = collect($codes)->contains(fn (string $c) => AccountCode::parentOf($c, $codes) === $concept['account_code']);
            $this->assertFalse($isParent, "{$concept['code']} apunta a una cuenta no imputable");
        }
    }

    public function test_rename_keeps_id_when_target_is_free(): void
    {
        $old = FinancialAccount::create(['code' => '10.1', 'name' => 'Caja', 'type' => 'asset', 'level' => 2, 'is_postable' => true]);

        $this->sync()->renameOrMerge('10.1', '101', null);

        $this->assertSame('101', $old->fresh()->code);
    }

    public function test_rename_merges_into_existing_target(): void
    {
        $this->sync()->syncAccounts($this->catalog());
        $old = FinancialAccount::create(['code' => '65.1', 'name' => 'Servicios', 'type' => 'expense', 'level' => 2, 'is_postable' => true]);
        $concept = FinanceConcept::factory()->expense()->create(['financial_account_id' => $old->id]);
        $child = FinancialAccount::create(['code' => '65.1.1', 'name' => 'Hija', 'type' => 'expense', 'parent_id' => $old->id, 'level' => 3]);

        $this->sync()->renameOrMerge('65.1', '636', '63');

        $target = $this->account('636');
        $this->assertSame($target->id, $concept->fresh()->financial_account_id);
        $this->assertSame($target->id, $child->fresh()->parent_id);
        $this->assertSoftDeleted('financial_accounts', ['id' => $old->id]);
    }

    public function test_relocate_moves_concepts_from_parent_accounts_to_default_leaf(): void
    {
        $this->sync()->syncAccounts($this->catalog());
        $concept = FinanceConcept::factory()->expense()->create(['financial_account_id' => $this->account('65')->id]);

        $moved = $this->sync()->relocateConceptsFromParents(AccountingPlanSynchronizer::DEFAULT_LEAVES);

        $this->assertSame(1, $moved);
        $this->assertSame($this->account('659')->id, $concept->fresh()->financial_account_id);
    }

    public function test_assign_concept_accounts_respects_existing_assignments_and_deleted_concepts(): void
    {
        $this->sync()->syncAccounts($this->catalog());

        $custom = FinanceConcept::factory()->system()->expense()->create(['code' => 'CASH_SHORTAGE', 'financial_account_id' => $this->account('1419')->id]);
        FinanceConcept::factory()->create(['code' => 'OTHER_INCOME', 'direction' => ConceptDirection::Income])->delete();

        $this->sync()->assignConceptAccounts();

        $this->assertSame($this->account('1419')->id, $custom->fresh()->financial_account_id);
        $this->assertNull(FinanceConcept::where('code', 'OTHER_INCOME')->first());
        $this->assertSame(1, FinanceConcept::withTrashed()->where('code', 'OTHER_INCOME')->count());
        $this->assertNotNull(FinanceConcept::where('code', 'ELECTRICITY')->first()?->financial_account_id);
    }
}
