<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Enums\BankAccountType;
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

class BankAccountsApiTest extends TestCase
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

    public function test_banks_can_be_created_updated_and_deleted(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/v1/finance/banks', [
            'name' => 'Banco de Comercio',
            'short_name' => 'BDC',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Banco de Comercio');

        $bankId = (string) $response->json('data.id');

        $this->putJson("/api/v1/finance/banks/{$bankId}", [
            'name' => 'Banco de Comercio Editado',
            'short_name' => 'BDC',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Banco de Comercio Editado');

        $this->deleteJson("/api/v1/finance/banks/{$bankId}")
            ->assertNoContent();
    }

    public function test_bank_accounts_crud_and_invariants(): void
    {
        $this->authenticateAsAdmin();

        $bank = Bank::where('short_name', 'BCP')->firstOrFail();
        $pen = Currency::where('code', 'PEN')->firstOrFail();

        // 1. Create with balance payload (should be ignored, balance stays 0)
        $response = $this->postJson('/api/v1/finance/bank-accounts', [
            'bank_id' => $bank->id,
            'currency_id' => $pen->id,
            'alias' => 'BCP Soles Operativa',
            'account_number' => '191-12345678-0-99',
            'cci' => '00219100123456780099',
            'account_type' => BankAccountType::Checking->value,
            'balance' => 50000.00, // No editable
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.alias', 'BCP Soles Operativa')
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.display_name', 'BCP PEN ···0-99');

        $accountId = (string) $response->json('data.id');

        // 2. Duplicate account_number for same bank fails
        $this->postJson('/api/v1/finance/bank-accounts', [
            'bank_id' => $bank->id,
            'currency_id' => $pen->id,
            'alias' => 'BCP Soles Duplicada',
            'account_number' => '191-12345678-0-99',
            'account_type' => BankAccountType::Checking->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['account_number']);

        // 3. Invalid CCI (not 20 digits)
        $this->postJson('/api/v1/finance/bank-accounts', [
            'bank_id' => $bank->id,
            'currency_id' => $pen->id,
            'alias' => 'Cuenta CCI Invalido',
            'account_number' => '191-99999999-0-99',
            'cci' => '12345', // solo 5 dígitos
            'account_type' => BankAccountType::Checking->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['cci']);

        // 4. Update
        $this->putJson("/api/v1/finance/bank-accounts/{$accountId}", [
            'bank_id' => $bank->id,
            'currency_id' => $pen->id,
            'alias' => 'BCP Soles Modificada',
            'account_number' => '191-12345678-0-99',
            'account_type' => BankAccountType::Savings->value,
        ])->assertOk()
            ->assertJsonPath('data.alias', 'BCP Soles Modificada')
            ->assertJsonPath('data.account_type', BankAccountType::Savings->value);

        // 5. Delete
        $this->deleteJson("/api/v1/finance/bank-accounts/{$accountId}")
            ->assertNoContent();
    }

    public function test_cannot_delete_bank_with_accounts(): void
    {
        $this->authenticateAsAdmin();

        $bank = Bank::where('short_name', 'BCP')->firstOrFail();
        $pen = Currency::where('code', 'PEN')->firstOrFail();

        BankAccount::factory()->create([
            'bank_id' => $bank->id,
            'currency_id' => $pen->id,
        ]);

        $this->deleteJson("/api/v1/finance/banks/{$bank->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.bank.0', 'No se puede eliminar un banco con cuentas asociadas');
    }

    public function test_cannot_delete_bank_account_associated_with_payment_methods(): void
    {
        $this->authenticateAsAdmin();

        $account = BankAccount::factory()->create();

        PaymentMethod::factory()->create([
            'bank_account_id' => $account->id,
        ]);

        $this->deleteJson("/api/v1/finance/bank-accounts/{$account->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.bank_account.0', 'No se puede eliminar una cuenta bancaria asociada a métodos de pago');
    }
}
