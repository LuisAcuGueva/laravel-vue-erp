<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Configuration\Models\SystemParameter;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceSettingsHealthApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/finance/settings/health';

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new SystemParameterSeeder)->run();
        (new FinanceMastersSeeder)->run();
    }

    private function actAsAdmin(): void
    {
        $role = Role::where('name', 'admin')->firstOrFail();
        $role->syncPermissions(Permission::all());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->withHeader('Authorization', 'Bearer '.app(JwtService::class)->createTokenPair($user)['access_token']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function issues(): array
    {
        return collect($this->getJson(self::URL)->assertOk()->json('data.issues'))->keyBy('code')->all();
    }

    private function account(string $code): FinancialAccount
    {
        return FinancialAccount::where('code', $code)->firstOrFail();
    }

    public function test_seeded_configuration_is_healthy(): void
    {
        $this->actAsAdmin();

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.require_concept_account', false)
            ->assertJsonPath('data.issues', []);
    }

    public function test_concept_without_account_is_warning_or_error_depending_on_parameter(): void
    {
        $this->actAsAdmin();
        FinanceConcept::where('code', 'ELECTRICITY')->update(['financial_account_id' => null]);

        $this->assertSame('warning', $this->issues()['concept_without_account']['severity']);
        $this->getJson(self::URL)->assertJsonPath('data.status', 'warning');

        app(SystemParameterService::class)->update(
            SystemParameter::where('group_key', 'finance')->where('item_key', 'require_concept_account')->firstOrFail(),
            true
        );

        $issue = $this->issues()['concept_without_account'];
        $this->assertSame('error', $issue['severity']);
        $this->assertSame(1, $issue['count']);
        $this->assertSame('ELECTRICITY', $issue['items'][0]['code']);
        $this->getJson(self::URL)->assertJsonPath('data.status', 'error');
    }

    public function test_detects_account_level_problems(): void
    {
        $this->actAsAdmin();

        // Concepto en cuenta no imputable, inactiva y con tipo incompatible
        FinanceConcept::where('code', 'WATER')->update(['financial_account_id' => $this->account('636')->id]);
        $inactive = $this->account('6364');
        DB::table('financial_accounts')->where('id', $inactive->id)->update(['is_active' => false]);
        FinanceConcept::where('code', 'LOAN_RECEIVED')->update(['financial_account_id' => $this->account('659')->id]);
        // Cuenta con hijas marcada como imputable
        DB::table('financial_accounts')->where('code', '10')->update(['is_postable' => true]);
        // Código fuera de formato
        DB::table('financial_accounts')->where('code', '6371')->update(['code' => '63.71']);
        // Concepto de sistema reasignado
        FinanceConcept::where('code', 'CASH_SHORTAGE')->update(['financial_account_id' => $this->account('1419')->id]);

        $issues = $this->issues();

        $this->assertSame('error', $issues['concept_account_not_postable']['severity']);
        $this->assertSame('error', $issues['concept_account_inactive']['severity']);
        $this->assertSame('error', $issues['concept_account_type_mismatch']['severity']);
        $this->assertSame('error', $issues['parent_account_postable']['severity']);
        $this->assertSame('error', $issues['account_code_format']['severity']);
        $this->assertSame('warning', $issues['system_concept_reassigned']['severity']);
        $this->assertSame('63.71', $issues['account_code_format']['items'][0]['code']);
    }

    public function test_requires_authentication_and_permission(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();

        $user = User::factory()->create();
        $this->withHeader('Authorization', 'Bearer '.app(JwtService::class)->createTokenPair($user)['access_token']);

        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_command_exit_code_reflects_status(): void
    {
        $this->artisan('finance:check-accounting-setup')->assertExitCode(0);

        FinanceConcept::where('code', 'WATER')->update(['financial_account_id' => $this->account('636')->id]);

        $this->artisan('finance:check-accounting-setup')->assertExitCode(1);
        $this->artisan('finance:check-accounting-setup', ['--json' => true])->assertExitCode(1);
    }
}
