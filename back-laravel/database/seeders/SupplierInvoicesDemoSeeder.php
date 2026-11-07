<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Services\BankPeriodService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\SupplierDocumentType;
use App\Modules\Purchases\Enums\SupplierInvoiceKind;
use App\Modules\Purchases\Models\PurchaseReceipt;
use App\Modules\Purchases\Models\SupplierInvoice;
use App\Modules\Purchases\Models\SupplierPayment;
use App\Modules\Purchases\Services\SupplierInvoiceService;
use App\Modules\Purchases\Services\SupplierPaymentService;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Registra las facturas de proveedor que citan las recepciones demo
 * (F001-00214 → RC-000001, F002-00088 → RC-000002) usando SupplierInvoiceService,
 * de modo que generen sus cuotas por pagar y queden vinculadas a la recepción.
 * Además paga parcialmente F001-00214 por transferencia BCP (abre el periodo
 * inicial de "BCP Soles" si no tiene). Es idempotente.
 *
 * Depende de: PurchasesReceiptsDemoSeeder, FinanceMastersSeeder, FinanceDemoSeeder.
 */
class SupplierInvoicesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first() ?? User::first();
        if ($admin === null) {
            return;
        }

        $this->seedInvoices($admin);
        $this->seedPartialPayment($admin);
    }

    private function seedInvoices(User $admin): void
    {
        $service = app(SupplierInvoiceService::class);

        foreach (['RC-000001', 'RC-000002'] as $receiptNumber) {
            $receipt = PurchaseReceipt::where('number', $receiptNumber)->with(['order', 'branch'])->first();

            if ($receipt === null || $receipt->provider_document_number === null || $receipt->supplierInvoices()->exists()) {
                continue;
            }

            [$series, $number] = array_pad(explode('-', $receipt->provider_document_number, 2), 2, null);
            if ($number === null) {
                [$series, $number] = [null, $series];
            }

            $alreadyRegistered = SupplierInvoice::where('provider_id', $receipt->provider_id)
                ->where('series', $series)
                ->where('document_number', $number)
                ->exists();

            if ($alreadyRegistered) {
                continue;
            }

            /** @var Branch $branch */
            $branch = $receipt->branch;
            $prefill = $service->prefill([$receipt->id], $branch->id);

            $service->create([
                'kind' => SupplierInvoiceKind::Purchase->value,
                'provider_id' => $receipt->provider_id,
                'document_type' => SupplierDocumentType::Invoice->value,
                'series' => $series,
                'document_number' => $number,
                'issue_date' => $receipt->receipt_date->toDateString(),
                'currency_id' => $receipt->order->currency_id,
                'prices_include_tax' => $prefill['prices_include_tax'],
                'purchase_order_id' => $receipt->purchase_order_id,
                'receipt_ids' => [$receipt->id],
                'notes' => "Factura de la recepción {$receipt->number}.",
                'items' => array_map(fn (array $item): array => [
                    'purchase_receipt_item_id' => $item['purchase_receipt_item_id'],
                    'item_presentation_id' => $item['item_presentation_id'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'],
                    'tax_type' => $item['tax_type'],
                ], $prefill['items']),
            ], $admin, $branch);
        }
    }

    /**
     * Abona S/ 15,000.00 a la factura de RC-000001 desde "BCP Soles", dejándola con saldo pendiente.
     */
    private function seedPartialPayment(User $admin): void
    {
        $invoice = PurchaseReceipt::where('number', 'RC-000001')->first()?->supplierInvoices()->with(['installments', 'branch'])->first();
        $method = PaymentMethod::where('code', 'TRANSFER_BCP')->first();

        if ($invoice === null || $method === null || $method->bank_account_id === null || $invoice->installments->isEmpty()) {
            return;
        }

        if (SupplierPayment::whereHas('allocations', fn ($q) => $q->where('supplier_invoice_id', $invoice->id))->exists()) {
            return;
        }

        /** @var BankAccount $account */
        $account = BankAccount::findOrFail($method->bank_account_id);
        if (! $account->periods()->exists()) {
            app(BankPeriodService::class)->openInitial($account, '50000.00', Carbon::now()->startOfMonth(), $admin);
        }

        $paymentDate = Carbon::parse($invoice->issue_date)->addDays(10)->min(Carbon::today());

        app(SupplierPaymentService::class)->create([
            'provider_id' => $invoice->provider_id,
            'currency_id' => $invoice->currency_id,
            'payment_method_id' => $method->id,
            'payment_date' => $paymentDate->toDateString(),
            'reference' => 'OP-778812',
            'notes' => "Pago a cuenta de {$invoice->series}-{$invoice->document_number}.",
            'allocations' => [
                ['installment_id' => $invoice->installments->first()->id, 'amount' => '15000.00'],
            ],
        ], $admin, $invoice->branch);
    }
}
