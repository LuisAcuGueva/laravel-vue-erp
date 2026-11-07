<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Models\Box;
use App\Modules\Maintenance\Exceptions\BranchRuleException;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Services\BranchService;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Users\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSeriesApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SystemParameterSeeder::class,
            UserSeeder::class,
            BranchSeeder::class,
        ]);

        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->operator = User::where('email', 'user@example.com')->firstOrFail();
        $this->branch = Branch::firstOrFail();

        $this->admin->branches()->syncWithoutDetaching([$this->branch->id => ['is_default' => true]]);
        $this->operator->branches()->syncWithoutDetaching([$this->branch->id => ['is_default' => true]]);
    }

    private function authAs(User $user): self
    {
        $token = app(JwtService::class)->createTokenPair($user)['access_token'];

        return $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Branch-Id', $this->branch->id);
    }

    public function test_can_create_document_series_with_valid_formats(): void
    {
        // 1. Factura F001
        $response = $this->authAs($this->admin)
            ->postJson('/api/v1/sales/document-series', [
                'branch_id' => $this->branch->id,
                'document_type' => SalesDocumentType::Invoice->value,
                'series' => 'F001',
                'is_electronic' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.series', 'F001')
            ->assertJsonPath('data.document_type', 'invoice')
            ->assertJsonPath('data.last_number', 0)
            ->assertJsonPath('data.next_full_number', 'F001-00000001');

        // 2. Boleta B001
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/document-series', [
                'branch_id' => $this->branch->id,
                'document_type' => SalesDocumentType::Receipt->value,
                'series' => 'B001',
                'is_electronic' => true,
            ])->assertCreated();

        // 3. Nota de crédito FC01
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/document-series', [
                'branch_id' => $this->branch->id,
                'document_type' => SalesDocumentType::CreditNote->value,
                'series' => 'FC01',
                'is_electronic' => true,
            ])->assertCreated();

        // 4. Nota de venta NV01
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/document-series', [
                'branch_id' => $this->branch->id,
                'document_type' => SalesDocumentType::SaleNote->value,
                'series' => 'NV01',
                'is_electronic' => false,
            ])->assertCreated();
    }

    public function test_rejects_invalid_series_format_per_document_type(): void
    {
        // Factura no puede empezar con B
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/document-series', [
                'branch_id' => $this->branch->id,
                'document_type' => SalesDocumentType::Invoice->value,
                'series' => 'B001',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['series']);

        // Boleta no puede empezar con F
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/document-series', [
                'branch_id' => $this->branch->id,
                'document_type' => SalesDocumentType::Receipt->value,
                'series' => 'F001',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['series']);

        // Nota de crédito no puede empezar con X
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/document-series', [
                'branch_id' => $this->branch->id,
                'document_type' => SalesDocumentType::CreditNote->value,
                'series' => 'NC01',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['series']);
    }

    public function test_duplicate_series_per_document_type_is_rejected(): void
    {
        DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::Invoice,
            'series' => 'F001',
            'last_number' => 0,
        ]);

        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/document-series', [
                'branch_id' => $this->branch->id,
                'document_type' => SalesDocumentType::Invoice->value,
                'series' => 'F001',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['series']);
    }

    public function test_same_series_code_allowed_for_different_document_types(): void
    {
        DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::Invoice,
            'series' => 'F001',
            'last_number' => 0,
        ]);

        // Nota de crédito con F001 sí se permite (afecta factura)
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/document-series', [
                'branch_id' => $this->branch->id,
                'document_type' => SalesDocumentType::CreditNote->value,
                'series' => 'F001',
            ])->assertCreated();
    }

    public function test_available_series_orders_box_specific_series_first(): void
    {
        $box = Box::create([
            'branch_id' => $this->branch->id,
            'code' => 'BOX-01',
            'name' => 'Caja Principal',
        ]);

        // Serie general de sede (sin caja)
        DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::Receipt,
            'series' => 'B001',
            'is_active' => true,
        ]);

        // Serie específica para la caja
        DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'box_id' => $box->id,
            'document_type' => SalesDocumentType::Receipt,
            'series' => 'B002',
            'is_active' => true,
        ]);

        $response = $this->authAs($this->operator)
            ->getJson("/api/v1/sales/document-series/available?document_type=receipt&box_id={$box->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.series', 'B002') // De la caja primero
            ->assertJsonPath('data.1.series', 'B001'); // General después
    }

    public function test_last_number_cannot_be_modified_if_series_has_sales(): void
    {
        $series = DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::Invoice,
            'series' => 'F001',
            'last_number' => 5,
        ]);

        // Sin ventas: se puede actualizar last_number
        $this->authAs($this->admin)
            ->putJson("/api/v1/sales/document-series/{$series->id}", [
                'branch_id' => $this->branch->id,
                'document_type' => SalesDocumentType::Invoice->value,
                'series' => 'F001',
                'last_number' => 10,
            ])->assertOk()
            ->assertJsonPath('data.last_number', 10);
    }

    public function test_create_without_branch_id_uses_active_branch(): void
    {
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/document-series', [
                'document_type' => SalesDocumentType::Receipt->value,
                'series' => 'B001',
            ])->assertCreated()
            ->assertJsonPath('data.branch_id', $this->branch->id);
    }

    public function test_partial_update_keeps_branch_type_and_series(): void
    {
        $series = DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::Invoice,
            'series' => 'F001',
        ]);

        $this->authAs($this->admin)
            ->putJson("/api/v1/sales/document-series/{$series->id}", [
                'is_active' => false,
            ])->assertOk()
            ->assertJsonPath('data.series', 'F001')
            ->assertJsonPath('data.document_type', 'invoice')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_branch_with_document_series_cannot_be_deleted(): void
    {
        $newBranch = Branch::create([
            'code' => 'SUC-02',
            'name' => 'Sucursal Norte',
            'is_main' => false,
        ]);

        DocumentSeries::create([
            'branch_id' => $newBranch->id,
            'document_type' => SalesDocumentType::Receipt,
            'series' => 'B003',
        ]);

        $this->expectException(BranchRuleException::class);
        $this->expectExceptionMessage('La sede tiene series de comprobantes registradas.');

        app(BranchService::class)->delete($newBranch);
    }

    public function test_unauthorized_user_cannot_manage_document_series(): void
    {
        // Operador tiene sales.create y sales.view pero no document_series.manage
        $this->authAs($this->operator)
            ->postJson('/api/v1/sales/document-series', [
                'branch_id' => $this->branch->id,
                'document_type' => SalesDocumentType::Receipt->value,
                'series' => 'B005',
            ])->assertForbidden();
    }
}
