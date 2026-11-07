<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Configuration\Models\SystemParameter;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAccountsAndConceptsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new FinanceMastersSeeder)->run();
        (new SystemParameterSeeder)->run();
    }

    private function authenticateAsAdmin(): User
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $adminRole->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    private function authenticateAsPlainUser(): User
    {
        $user = User::factory()->create();
        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    public function test_financial_account_hierarchy_and_type_inheritance(): void
    {
        $this->authenticateAsAdmin();

        // 1. Crear cuenta raíz (Nivel 1)
        $rootRes = $this->postJson('/api/v1/finance/financial-accounts', [
            'code' => '99',
            'name' => 'Cuentas de Prueba',
            'type' => FinancialAccountType::Asset->value,
            'is_postable' => true,
        ]);

        $rootRes->assertCreated()
            ->assertJsonPath('data.code', '99')
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.type', FinancialAccountType::Asset->value)
            ->assertJsonPath('data.is_postable', true);

        $rootId = (string) $rootRes->json('data.id');

        // 2. Crear subcuenta (Nivel 2) heredando el tipo
        $childRes = $this->postJson('/api/v1/finance/financial-accounts', [
            'code' => '991',
            'name' => 'Subcuenta 1',
            'parent_id' => $rootId,
            'is_postable' => true,
        ]);

        $childRes->assertCreated()
            ->assertJsonPath('data.code', '991')
            ->assertJsonPath('data.level', 2)
            ->assertJsonPath('data.type', FinancialAccountType::Asset->value);

        // La cuenta padre debió pasar automáticamente a is_postable = false
        $this->assertDatabaseHas('financial_accounts', [
            'id' => $rootId,
            'is_postable' => false,
        ]);

        // 3. Crear con tipo incompatible con el padre falla
        $this->postJson('/api/v1/finance/financial-accounts', [
            'code' => '992',
            'name' => 'Subcuenta Inválida',
            'parent_id' => $rootId,
            'type' => FinancialAccountType::Liability->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_cycle_prevention_in_financial_accounts(): void
    {
        $this->authenticateAsAdmin();

        $accountA = FinancialAccount::create([
            'code' => '91',
            'name' => 'Cuenta A',
            'type' => FinancialAccountType::Asset->value,
            'level' => 1,
            'is_postable' => false,
        ]);

        $accountB = FinancialAccount::create([
            'code' => '911',
            'name' => 'Cuenta B',
            'type' => FinancialAccountType::Asset->value,
            'parent_id' => $accountA->id,
            'level' => 2,
            'is_postable' => false,
        ]);

        $accountC = FinancialAccount::create([
            'code' => '91111',
            'name' => 'Cuenta C',
            'type' => FinancialAccountType::Asset->value,
            'parent_id' => $accountB->id,
            'level' => 3,
            'is_postable' => true,
        ]);

        // Intentar hacer a A hija de C (ciclo)
        $this->putJson("/api/v1/finance/financial-accounts/{$accountA->id}", [
            'code' => '91',
            'name' => 'Cuenta A',
            'parent_id' => $accountC->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        // Intentar hacer a A su propio padre
        $this->putJson("/api/v1/finance/financial-accounts/{$accountA->id}", [
            'code' => '91',
            'name' => 'Cuenta A',
            'parent_id' => $accountA->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_financial_accounts_tree_and_options(): void
    {
        $this->authenticateAsPlainUser();

        $this->getJson('/api/v1/finance/financial-accounts/tree')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'level', 'children'],
                ],
            ]);

        $this->getJson('/api/v1/finance/financial-accounts/options?postable_only=1')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'is_postable'],
                ],
            ]);
    }

    public function test_cannot_delete_account_with_children_or_concepts(): void
    {
        $this->authenticateAsAdmin();

        $parent = FinancialAccount::where('code', '10')->firstOrFail();
        $this->deleteJson("/api/v1/finance/financial-accounts/{$parent->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['financial_account']);

        $accountWithConcept = FinancialAccount::create([
            'code' => '98',
            'name' => 'Cuenta con Concepto',
            'type' => FinancialAccountType::Asset->value,
            'level' => 1,
            'is_postable' => true,
        ]);
        FinanceConcept::create([
            'code' => 'CONCEPT_LINKED',
            'name' => 'Concepto Vinculado',
            'direction' => ConceptDirection::Income->value,
            'financial_account_id' => $accountWithConcept->id,
        ]);
        $this->deleteJson("/api/v1/finance/financial-accounts/{$accountWithConcept->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['financial_account']);
    }

    public function test_concept_requires_postable_account(): void
    {
        $this->authenticateAsAdmin();

        $parentAccount = FinancialAccount::where('code', '10')->firstOrFail(); // is_postable = false

        $this->postJson('/api/v1/finance/concepts', [
            'code' => 'TEST_CONCEPT',
            'name' => 'Concepto No Imputable',
            'direction' => ConceptDirection::Income->value,
            'financial_account_id' => $parentAccount->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['financial_account_id']);
    }

    public function test_concept_direction_account_type_compatibility(): void
    {
        $this->authenticateAsAdmin();

        $incomeAccount = FinancialAccount::where('code', '7011')->firstOrFail(); // type: income
        $expenseAccount = FinancialAccount::where('code', '659')->firstOrFail(); // type: expense

        // Concepto Income con cuenta Expense -> 422
        $this->postJson('/api/v1/finance/concepts', [
            'code' => 'INVALID_INCOME',
            'name' => 'Ingreso Invalido',
            'direction' => ConceptDirection::Income->value,
            'financial_account_id' => $expenseAccount->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['financial_account_id']);

        // Concepto Expense con cuenta Income -> 422
        $this->postJson('/api/v1/finance/concepts', [
            'code' => 'INVALID_EXPENSE',
            'name' => 'Egreso Invalido',
            'direction' => ConceptDirection::Expense->value,
            'financial_account_id' => $incomeAccount->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['financial_account_id']);

        // Concepto Expense con cuenta Expense -> 201
        $this->postJson('/api/v1/finance/concepts', [
            'code' => 'VALID_EXPENSE',
            'name' => 'Egreso Valido',
            'direction' => ConceptDirection::Expense->value,
            'financial_account_id' => $expenseAccount->id,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'VALID_EXPENSE');
    }

    public function test_system_concepts_are_protected(): void
    {
        $this->authenticateAsAdmin();

        $systemConcept = FinanceConcept::where('code', 'SALE_COLLECTION')->firstOrFail();

        // No se puede modificar el código
        $this->putJson("/api/v1/finance/concepts/{$systemConcept->id}", [
            'code' => 'SALE_COLLECTION_MODIFIED',
            'name' => $systemConcept->name,
            'direction' => $systemConcept->direction->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // No se puede modificar la dirección
        $this->putJson("/api/v1/finance/concepts/{$systemConcept->id}", [
            'code' => 'SALE_COLLECTION',
            'name' => $systemConcept->name,
            'direction' => ConceptDirection::Expense->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['direction']);

        // No se puede eliminar
        $this->deleteJson("/api/v1/finance/concepts/{$systemConcept->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['finance_concept']);

        // Se puede actualizar nombre o cuenta contable
        $this->putJson("/api/v1/finance/concepts/{$systemConcept->id}", [
            'code' => 'SALE_COLLECTION',
            'name' => 'Cobro por venta comercial',
            'direction' => $systemConcept->direction->value,
            'financial_account_id' => $systemConcept->financial_account_id,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Cobro por venta comercial');
    }

    private const ACCOUNTS = '/api/v1/finance/financial-accounts';

    private const CONCEPTS = '/api/v1/finance/concepts';

    private function account(string $code): FinancialAccount
    {
        return FinancialAccount::where('code', $code)->firstOrFail();
    }

    private function requireConceptAccount(bool $value): void
    {
        app(SystemParameterService::class)->update(
            SystemParameter::where('group_key', 'finance')->where('item_key', 'require_concept_account')->firstOrFail(),
            $value
        );
    }

    public function test_account_code_must_follow_pcge_format_and_parent_prefix(): void
    {
        $this->authenticateAsAdmin();

        $this->postJson(self::ACCOUNTS, ['code' => '10.1', 'name' => 'Con punto', 'type' => 'asset'])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);

        $this->postJson(self::ACCOUNTS, ['code' => '101x', 'name' => 'Letras', 'type' => 'asset'])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);

        // Una raíz de 3 dígitos
        $this->postJson(self::ACCOUNTS, ['code' => '991', 'name' => 'Raíz larga', 'type' => 'asset'])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);

        // Hija que no empieza con el código del padre
        $this->postJson(self::ACCOUNTS, ['code' => '201', 'name' => 'Sin prefijo', 'parent_id' => $this->account('10')->id])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);

        // Hija válida bajo una cuenta sin conceptos
        $this->postJson(self::ACCOUNTS, ['code' => '6366', 'name' => 'Otros servicios básicos', 'parent_id' => $this->account('636')->id])
            ->assertCreated()
            ->assertJsonPath('data.level', 3);
    }

    public function test_level_sent_in_body_is_ignored(): void
    {
        $this->authenticateAsAdmin();

        $this->postJson(self::ACCOUNTS, ['code' => '97', 'name' => 'Nivel falso', 'type' => 'asset', 'level' => 7])
            ->assertCreated()
            ->assertJsonPath('data.level', 1);
    }

    public function test_cannot_create_subaccount_under_account_with_concepts(): void
    {
        $this->authenticateAsAdmin();
        $parent = $this->account('659');
        $this->assertNotNull(FinanceConcept::where('financial_account_id', $parent->id)->first());

        $this->postJson(self::ACCOUNTS, ['code' => '6591', 'name' => 'Subcuenta', 'parent_id' => $parent->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        $this->assertTrue($parent->fresh()->is_postable);
        $this->assertDatabaseMissing('financial_accounts', ['code' => '6591']);
    }

    public function test_cannot_change_code_of_account_with_children(): void
    {
        $this->authenticateAsAdmin();
        $account = $this->account('636');

        $this->putJson(self::ACCOUNTS."/{$account->id}", ['code' => '639', 'name' => $account->name, 'parent_id' => $account->parent_id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_cannot_deactivate_account_with_concepts_or_active_children(): void
    {
        $this->authenticateAsAdmin();

        $withConcepts = $this->account('659');
        $this->putJson(self::ACCOUNTS."/{$withConcepts->id}", [
            'code' => $withConcepts->code, 'name' => $withConcepts->name, 'parent_id' => $withConcepts->parent_id, 'is_active' => false,
        ])->assertStatus(422)->assertJsonValidationErrors(['is_active']);

        $withChildren = $this->account('636');
        $this->putJson(self::ACCOUNTS."/{$withChildren->id}", [
            'code' => $withChildren->code, 'name' => $withChildren->name, 'parent_id' => $withChildren->parent_id, 'is_active' => false,
        ])->assertStatus(422)->assertJsonValidationErrors(['is_active']);
    }

    public function test_cannot_mark_account_with_children_as_postable(): void
    {
        $this->authenticateAsAdmin();
        $account = $this->account('636');

        $this->putJson(self::ACCOUNTS."/{$account->id}", [
            'code' => $account->code, 'name' => $account->name, 'parent_id' => $account->parent_id, 'is_postable' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['is_postable']);
    }

    public function test_moving_the_only_child_restores_previous_parent_as_postable(): void
    {
        $this->authenticateAsAdmin();
        $a = FinancialAccount::create(['code' => '96', 'name' => 'Raíz', 'type' => 'asset', 'level' => 1, 'is_postable' => false]);
        $b = FinancialAccount::create(['code' => '961', 'name' => 'Intermedia', 'type' => 'asset', 'parent_id' => $a->id, 'level' => 2, 'is_postable' => false]);
        $c = FinancialAccount::create(['code' => '9611', 'name' => 'Hoja', 'type' => 'asset', 'parent_id' => $b->id, 'level' => 3, 'is_postable' => true]);

        $this->putJson(self::ACCOUNTS."/{$c->id}", ['code' => '9611', 'name' => 'Hoja', 'parent_id' => $a->id])
            ->assertOk()
            ->assertJsonPath('data.level', 2);

        $this->assertTrue($b->fresh()->is_postable);
        $this->assertFalse($a->fresh()->is_postable);
    }

    public function test_concept_direction_matrix_through_api(): void
    {
        $this->authenticateAsAdmin();

        $payload = fn (string $direction, string $accountCode, string $code) => [
            'code' => $code,
            'name' => 'Concepto '.$code,
            'direction' => $direction,
            'financial_account_id' => $this->account($accountCode)->id,
        ];

        // Ingreso hacia pasivo y patrimonio
        $this->postJson(self::CONCEPTS, $payload('income', '4511', 'LOAN_IN'))->assertCreated();
        $this->postJson(self::CONCEPTS, $payload('income', '501', 'CAPITAL_IN'))->assertCreated();

        // Egreso hacia patrimonio: rechazado
        $this->postJson(self::CONCEPTS, $payload('expense', '501', 'CAPITAL_OUT'))
            ->assertStatus(422)->assertJsonValidationErrors(['financial_account_id']);

        // Transferencia: solo bajo la cuenta 10
        $this->postJson(self::CONCEPTS, $payload('transfer', '1212', 'XFER_BAD'))
            ->assertStatus(422)->assertJsonValidationErrors(['financial_account_id']);
        $this->postJson(self::CONCEPTS, $payload('transfer', '103', 'XFER_OK'))->assertCreated();
    }

    public function test_concept_account_is_required_when_parameter_is_active(): void
    {
        $this->authenticateAsAdmin();

        $this->postJson(self::CONCEPTS, ['code' => 'NO_ACC', 'name' => 'Sin cuenta', 'direction' => 'income'])->assertCreated();

        $this->requireConceptAccount(true);

        $this->postJson(self::CONCEPTS, ['code' => 'NO_ACC_2', 'name' => 'Sin cuenta 2', 'direction' => 'income'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['financial_account_id']);
    }

    public function test_system_concept_account_can_be_reassigned(): void
    {
        $this->authenticateAsAdmin();
        $concept = FinanceConcept::where('code', 'CASH_SHORTAGE')->firstOrFail();

        $this->putJson(self::CONCEPTS."/{$concept->id}", [
            'code' => 'CASH_SHORTAGE',
            'name' => $concept->name,
            'direction' => 'expense',
            'financial_account_id' => $this->account('1419')->id,
        ])->assertOk()->assertJsonPath('data.financial_account.code', '1419');
    }

    public function test_concepts_can_be_filtered_by_account_presence_and_type(): void
    {
        $this->authenticateAsAdmin();
        FinanceConcept::factory()->expense()->create(['code' => 'ORPHAN', 'financial_account_id' => null]);

        $codes = collect($this->getJson(self::CONCEPTS.'?has_account=0&per_page=100')->assertOk()->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('ORPHAN'));
        $this->assertFalse($codes->contains('ELECTRICITY'));

        $codes = collect($this->getJson(self::CONCEPTS.'?has_account=1&per_page=100')->assertOk()->json('data'))->pluck('code');
        $this->assertFalse($codes->contains('ORPHAN'));

        $options = collect($this->getJson(self::CONCEPTS.'/options?direction=expense&account_type=expense')->assertOk()->json('data'))->pluck('code');
        $this->assertTrue($options->contains('ELECTRICITY'));
        $this->assertTrue($options->contains('ORPHAN'));
        $this->assertFalse($options->contains('LOAN_PAYMENT'));
        $this->assertFalse($options->contains('TAX_PAYMENT'));

        $this->requireConceptAccount(true);
        $options = collect($this->getJson(self::CONCEPTS.'/options?direction=expense&account_type=expense')->json('data'))->pluck('code');
        $this->assertFalse($options->contains('ORPHAN'));
    }

    public function test_concepts_options_filters_by_money_direction_in_and_out(): void
    {
        $this->authenticateAsPlainUser();

        $inOptions = collect($this->getJson(self::CONCEPTS.'/options?direction=in')->assertOk()->json('data'));
        $outOptions = collect($this->getJson(self::CONCEPTS.'/options?direction=out')->assertOk()->json('data'));

        $this->assertNotEmpty($inOptions);
        $this->assertNotEmpty($outOptions);

        $inCodes = $inOptions->pluck('code');
        $this->assertTrue($inCodes->contains('OTHER_INCOME'));
        $this->assertFalse($inCodes->contains('ELECTRICITY'));

        $outCodes = $outOptions->pluck('code');
        $this->assertTrue($outCodes->contains('ELECTRICITY'));
        $this->assertFalse($outCodes->contains('OTHER_INCOME'));

        // Por defecto no incluye conceptos de sistema
        $this->assertFalse($inCodes->contains('SALE_COLLECTION'));
        $this->assertFalse($outCodes->contains('SUPPLIER_PAYMENT'));
    }
}
