<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Finance\Enums\BankAccountType;
use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Enums\PaymentMethodKind;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;

/**
 * Datos de prueba de cuentas por pagar: maestros de finanzas, caja con sesión abierta, cuenta bancaria con método
 * de transferencia, concepto de egreso y atajos para crear OC con recepción y facturas de proveedor.
 */
trait SetsUpPayables
{
    use SetsUpPurchasing;

    protected Box $box;

    protected CashSession $session;

    protected PaymentMethod $cashMethod;

    protected BankAccount $bankAccount;

    protected PaymentMethod $transferMethod;

    protected FinanceConcept $expenseConcept;

    protected function setUpPayables(): void
    {
        $this->setUpPurchasing();
        (new FinanceMastersSeeder)->run();
        (new SystemParameterSeeder)->run();
        Currency::clearBaseCache();

        $this->pen->refresh();
        $this->usd->refresh();

        $userRole = Role::where('name', 'user')->firstOrFail();
        $this->box = Box::create([
            'branch_id' => $this->branch->id,
            'code' => 'CJ01',
            'name' => 'Caja principal',
            'is_active' => true,
        ]);
        $this->box->roles()->sync([$userRole->id]);

        /** @var User $opener */
        $opener = User::factory()->create();
        $this->session = CashSession::create([
            'number' => 'CJ-000001',
            'box_id' => $this->box->id,
            'branch_id' => $this->branch->id,
            'status' => CashSessionStatus::Open->value,
            'opened_by' => $opener->id,
            'opened_at' => now(),
            'opening_amount' => 2000.00,
        ]);

        $this->cashMethod = PaymentMethod::where('code', 'CASH')->firstOrFail();

        $this->bankAccount = BankAccount::create([
            'bank_id' => (Bank::first() ?? Bank::factory()->create())->id,
            'currency_id' => $this->pen->id,
            'alias' => 'BCP Pagos',
            'account_number' => '191-99999999-0-01',
            'account_type' => BankAccountType::Checking,
            'balance' => '10000.00',
            'is_active' => true,
        ]);

        BankPeriod::create([
            'bank_account_id' => $this->bankAccount->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => now()->startOfMonth()->toDateString(),
            'opening_balance' => '10000.00',
        ]);

        $this->transferMethod = PaymentMethod::create([
            'code' => 'TRF_PAGOS',
            'name' => 'Transferencia BCP Pagos',
            'kind' => PaymentMethodKind::Transfer,
            'settlement' => Settlement::BankAccount,
            'bank_account_id' => $this->bankAccount->id,
            'requires_reference' => false,
            'is_active' => true,
        ]);

        $this->expenseConcept = FinanceConcept::create([
            'code' => 'EGR_SERVICIOS',
            'name' => 'Servicios básicos',
            'direction' => ConceptDirection::Expense,
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Autentica a un usuario con los permisos dados y con un rol que puede operar la caja.
     *
     * @param  list<string>  $permissions
     */
    protected function actAsCashier(array $permissions): User
    {
        $user = $this->actAs($permissions);
        $user->assignRole(Role::where('name', 'user')->firstOrFail());

        return $user;
    }

    /**
     * OC aprobada (10 × 50 + 2 × 100, sin IGV incluido = 826.00) recibida por completo con una recepción confirmada.
     *
     * @return array{order: PurchaseOrder, receipt_id: string}
     */
    protected function receivedOrder(string $guide = 'GR-001'): array
    {
        $this->actAs(['purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update']);

        $res = $this->postJson('/api/v1/purchases/orders', [
            'provider_id' => $this->provider->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'currency_id' => $this->pen->id,
            'payment_condition' => 'cash',
            'prices_include_tax' => false,
            'items' => [
                ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '10', 'unit_price' => '50', 'tax_type' => 'taxable'],
                ['item_presentation_id' => $this->servicePresentation->id, 'quantity' => '2', 'unit_price' => '100', 'tax_type' => 'taxable'],
            ],
        ])->assertCreated();
        $orderId = (string) $res->json('data.id');

        $this->postJson("/api/v1/purchases/orders/{$orderId}/submit")->assertOk();

        /** @var PurchaseOrder $order */
        $order = PurchaseOrder::findOrFail($orderId);
        if ($order->status !== PurchaseOrderStatus::Approved) {
            $this->actAs(['purchase_orders.approve']);
            $this->postJson("/api/v1/purchases/orders/{$orderId}/approve")->assertOk();
        }

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm']);
        $receipt = $this->postJson("/api/v1/purchases/orders/{$orderId}/receive-all", [
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'provider_document_number' => $guide,
        ])->assertCreated();

        return ['order' => $order->fresh() ?? $order, 'receipt_id' => (string) $receipt->json('data.id')];
    }

    /**
     * Payload de una factura de compra de texto libre con una sola línea gravada.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function invoicePayload(array $overrides = [], string $amount = '100.00'): array
    {
        return array_merge([
            'kind' => 'purchase',
            'provider_id' => $this->provider->id,
            'document_type' => 'invoice',
            'series' => 'F001',
            'document_number' => (string) random_int(1000, 999999),
            'issue_date' => now()->toDateString(),
            'currency_id' => $this->pen->id,
            'prices_include_tax' => true,
            'items' => [
                ['description' => 'Servicio de mantenimiento', 'quantity' => '1', 'unit_price' => $amount, 'tax_type' => 'taxable'],
            ],
        ], $overrides);
    }

    /**
     * Registra una factura con un usuario que tiene permiso de crearla y devuelve la respuesta.
     *
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    protected function storeInvoice(array $payload): TestResponse
    {
        $this->actAs(['supplier_invoices.create', 'supplier_invoices.view']);

        return $this->postJson('/api/v1/purchases/supplier-invoices', $payload);
    }
}
