<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Enums\BankAccountType;
use App\Modules\Finance\Enums\PaymentMethodKind;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new FinanceMastersSeeder)->run();
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

    public function test_payment_methods_can_be_listed_and_filtered(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/v1/finance/payment-methods');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'kind', 'settlement', 'is_system', 'is_active'],
                ],
                'meta' => ['total', 'current_page'],
            ]);

        $this->getJson('/api/v1/finance/payment-methods?kind=cash')
            ->assertOk()
            ->assertJsonFragment(['code' => 'CASH']);
    }

    public function test_payment_method_can_be_created_with_cash_box(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/v1/finance/payment-methods', [
            'code' => 'YAPE_POS',
            'name' => 'Yape Caja',
            'kind' => PaymentMethodKind::Wallet->value,
            'settlement' => Settlement::CashBox->value,
            'requires_reference' => true,
            'sunat_code' => '008',
            'sort_order' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'YAPE_POS')
            ->assertJsonPath('data.name', 'Yape Caja')
            ->assertJsonPath('data.settlement', Settlement::CashBox->value);

        $this->assertDatabaseHas('payment_methods', [
            'code' => 'YAPE_POS',
            'settlement' => Settlement::CashBox->value,
        ]);
    }

    public function test_payment_method_with_bank_account_settlement_requires_valid_bank_account(): void
    {
        $this->authenticateAsAdmin();

        // Falta bank_account_id
        $this->postJson('/api/v1/finance/payment-methods', [
            'code' => 'BCP_TRANS',
            'name' => 'Transferencia BCP',
            'kind' => PaymentMethodKind::Transfer->value,
            'settlement' => Settlement::BankAccount->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['bank_account_id']);

        // Cuenta inactiva
        $bank = Bank::firstOrFail();
        $currency = Currency::where('code', 'PEN')->firstOrFail();
        $inactiveAccount = BankAccount::create([
            'bank_id' => $bank->id,
            'currency_id' => $currency->id,
            'alias' => 'BCP Inactiva',
            'account_number' => '999-999-999',
            'account_type' => BankAccountType::Checking->value,
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/finance/payment-methods', [
            'code' => 'BCP_TRANS',
            'name' => 'Transferencia BCP',
            'kind' => PaymentMethodKind::Transfer->value,
            'settlement' => Settlement::BankAccount->value,
            'bank_account_id' => $inactiveAccount->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['bank_account_id']);

        // Cuenta activa
        $inactiveAccount->update(['is_active' => true]);

        $this->postJson('/api/v1/finance/payment-methods', [
            'code' => 'BCP_TRANS',
            'name' => 'Transferencia BCP',
            'kind' => PaymentMethodKind::Transfer->value,
            'settlement' => Settlement::BankAccount->value,
            'bank_account_id' => $inactiveAccount->id,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'BCP_TRANS')
            ->assertJsonPath('data.bank_account.id', $inactiveAccount->id);
    }

    public function test_cash_kind_must_have_cash_box_settlement(): void
    {
        $this->authenticateAsAdmin();

        $bank = Bank::firstOrFail();
        $currency = Currency::where('code', 'PEN')->firstOrFail();
        $account = BankAccount::create([
            'bank_id' => $bank->id,
            'currency_id' => $currency->id,
            'alias' => 'Cuenta BCP',
            'account_number' => '111-222-333',
            'account_type' => BankAccountType::Checking->value,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/finance/payment-methods', [
            'code' => 'CASH_INVALID',
            'name' => 'Efectivo en Banco',
            'kind' => PaymentMethodKind::Cash->value,
            'settlement' => Settlement::BankAccount->value,
            'bank_account_id' => $account->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['settlement']);
    }

    public function test_system_payment_method_is_protected(): void
    {
        $this->authenticateAsAdmin();

        $cash = PaymentMethod::where('code', 'CASH')->firstOrFail();

        // No se puede modificar el código
        $this->putJson("/api/v1/finance/payment-methods/{$cash->id}", [
            'code' => 'CASH_RENAMED',
            'name' => $cash->name,
            'kind' => $cash->kind->value,
            'settlement' => $cash->settlement->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // No se puede modificar el tipo
        $this->putJson("/api/v1/finance/payment-methods/{$cash->id}", [
            'code' => 'CASH',
            'name' => $cash->name,
            'kind' => PaymentMethodKind::Transfer->value,
            'settlement' => $cash->settlement->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['kind']);

        // No se puede eliminar
        $this->deleteJson("/api/v1/finance/payment-methods/{$cash->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);

        // Se puede actualizar nombre o estado
        $this->putJson("/api/v1/finance/payment-methods/{$cash->id}", [
            'code' => 'CASH',
            'name' => 'Efectivo Caja Principal',
            'kind' => $cash->kind->value,
            'settlement' => $cash->settlement->value,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Efectivo Caja Principal');
    }

    public function test_payment_methods_options_returns_active_methods(): void
    {
        $this->authenticateAsPlainUser();

        $response = $this->getJson('/api/v1/finance/payment-methods/options');
        $response->assertOk()
            ->assertJsonFragment(['code' => 'CASH']);
    }

    public function test_unauthorized_user_cannot_manage_payment_methods(): void
    {
        $this->authenticateAsPlainUser();

        $this->postJson('/api/v1/finance/payment-methods', [
            'code' => 'NEW_METHOD',
            'name' => 'Nuevo Método',
            'kind' => PaymentMethodKind::Card->value,
            'settlement' => Settlement::CashBox->value,
        ])->assertForbidden();
    }
}
