<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Purchases\Enums\ProviderDocumentType;
use App\Modules\Purchases\Enums\PurchaseReceiptStatus;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseReceipt;
use App\Modules\Purchases\Models\PurchaseReceiptItem;
use App\Modules\Purchases\Services\PurchaseOrderWorkflowService;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea las recepciones de compra demo alineadas a OC-000001 y OC-000002.
 * Debe ejecutarse ANTES de StockDemoSeeder para que el kardex pueda usar
 * la PurchaseReceipt como source de los movimientos de tipo Purchase.
 *
 * Depende de: CatalogSeeder, ProviderSeeder, PurchasesDemoSeeder.
 */
class PurchasesReceiptsDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotencia: si ya existen recepciones demo, salir
        if (PurchaseReceipt::where('number', 'like', 'RC-%')->exists()) {
            return;
        }

        $admin = User::where('email', 'admin@example.com')->first() ?? User::first();
        if ($admin === null) {
            return;
        }

        $oc1 = PurchaseOrder::where('number', 'OC-000001')
            ->with(['items' => fn ($q) => $q->with(['catalogItem', 'presentation'])])
            ->first();

        $oc2 = PurchaseOrder::where('number', 'OC-000002')
            ->with(['items' => fn ($q) => $q->with(['catalogItem', 'presentation'])])
            ->first();

        if ($oc1 === null && $oc2 === null) {
            return;
        }

        $workflow = app(PurchaseOrderWorkflowService::class);
        $startOfMonth = Carbon::now()->startOfMonth();

        DB::transaction(function () use ($oc1, $oc2, $admin, $workflow, $startOfMonth): void {

            // -------------------------------------------------------------------------
            // RC-000001 — OC-000001 (Mayorista Tecnológico)
            // Laptops ×5 | Monitores ×10 | SSD ×15
            // -------------------------------------------------------------------------
            if ($oc1 !== null) {
                $receiptDate1 = $startOfMonth->copy()->addDays(3)->toDateString();
                $confirmedAt1 = $startOfMonth->copy()->addDays(3)->setTime(10, 30);

                $rc1 = PurchaseReceipt::updateOrCreate(
                    ['number' => 'RC-000001'],
                    [
                        'branch_id' => $oc1->branch_id,
                        'purchase_order_id' => $oc1->id,
                        'provider_id' => $oc1->provider_id,
                        'warehouse_id' => $oc1->warehouse_id,
                        'receipt_date' => $receiptDate1,
                        'provider_document_type' => ProviderDocumentType::Invoice,
                        'provider_document_number' => 'F001-00214',
                        'notes' => 'Recepción total según factura F001-00214. Mercadería conforme.',
                        'status' => PurchaseReceiptStatus::Confirmed,
                        'total_cost' => 0,
                        'created_by' => $admin->id,
                        'confirmed_by' => $admin->id,
                        'confirmed_at' => $confirmedAt1,
                    ]
                );
                SequentialCodeGenerator::reserve('purchase_receipt', $rc1->number);

                $totalCost1 = 0.0;

                foreach ($oc1->items as $idx => $orderItem) {
                    $unitCost = $this->resolveUnitCost($oc1, $orderItem);
                    $qty = (float) $orderItem->quantity;
                    $lineTotal = round($unitCost * $qty, 2);
                    $totalCost1 += $lineTotal;

                    [$baseQty, $baseUnitCost] = $this->resolveBaseValues($orderItem, $qty, $unitCost);

                    PurchaseReceiptItem::updateOrCreate(
                        ['purchase_receipt_id' => $rc1->id, 'line' => $idx + 1],
                        [
                            'purchase_order_item_id' => $orderItem->id,
                            'catalog_item_id' => $orderItem->catalog_item_id,
                            'item_presentation_id' => $orderItem->item_presentation_id,
                            'is_service' => false,
                            'quantity' => number_format($qty, 4, '.', ''),
                            'base_quantity' => number_format($baseQty, 4, '.', ''),
                            'unit_cost' => number_format($unitCost, 4, '.', ''),
                            'base_unit_cost' => number_format($baseUnitCost, 6, '.', ''),
                            'total_cost' => number_format($lineTotal, 2, '.', ''),
                            'notes' => null,
                        ]
                    );

                    // Actualizar received_quantity en la línea de la OC
                    $orderItem->update(['received_quantity' => number_format($qty, 4, '.', '')]);
                }

                $rc1->update(['total_cost' => number_format($totalCost1, 2, '.', '')]);

                // Marcar OC-000001 como recibida
                $workflow->markReceived($oc1->fresh() ?? $oc1);
            }

            // -------------------------------------------------------------------------
            // RC-000002 — OC-000002 (Distribuidora Papelera)
            // Papel A4 ×50
            // -------------------------------------------------------------------------
            if ($oc2 !== null) {
                $receiptDate2 = $startOfMonth->copy()->addDays(4)->toDateString();
                $confirmedAt2 = $startOfMonth->copy()->addDays(4)->setTime(14, 0);

                $rc2 = PurchaseReceipt::updateOrCreate(
                    ['number' => 'RC-000002'],
                    [
                        'branch_id' => $oc2->branch_id,
                        'purchase_order_id' => $oc2->id,
                        'provider_id' => $oc2->provider_id,
                        'warehouse_id' => $oc2->warehouse_id,
                        'receipt_date' => $receiptDate2,
                        'provider_document_type' => ProviderDocumentType::Invoice,
                        'provider_document_number' => 'F002-00088',
                        'notes' => 'Recepción total según factura F002-00088. Papel A4 conforme.',
                        'status' => PurchaseReceiptStatus::Confirmed,
                        'total_cost' => 0,
                        'created_by' => $admin->id,
                        'confirmed_by' => $admin->id,
                        'confirmed_at' => $confirmedAt2,
                    ]
                );
                SequentialCodeGenerator::reserve('purchase_receipt', $rc2->number);

                $totalCost2 = 0.0;

                foreach ($oc2->items as $idx => $orderItem) {
                    $unitCost = $this->resolveUnitCost($oc2, $orderItem);
                    $qty = (float) $orderItem->quantity;
                    $lineTotal = round($unitCost * $qty, 2);
                    $totalCost2 += $lineTotal;

                    [$baseQty, $baseUnitCost] = $this->resolveBaseValues($orderItem, $qty, $unitCost);

                    PurchaseReceiptItem::updateOrCreate(
                        ['purchase_receipt_id' => $rc2->id, 'line' => $idx + 1],
                        [
                            'purchase_order_item_id' => $orderItem->id,
                            'catalog_item_id' => $orderItem->catalog_item_id,
                            'item_presentation_id' => $orderItem->item_presentation_id,
                            'is_service' => false,
                            'quantity' => number_format($qty, 4, '.', ''),
                            'base_quantity' => number_format($baseQty, 4, '.', ''),
                            'unit_cost' => number_format($unitCost, 4, '.', ''),
                            'base_unit_cost' => number_format($baseUnitCost, 6, '.', ''),
                            'total_cost' => number_format($lineTotal, 2, '.', ''),
                            'notes' => null,
                        ]
                    );

                    $orderItem->update(['received_quantity' => number_format($qty, 4, '.', '')]);
                }

                $rc2->update(['total_cost' => number_format($totalCost2, 2, '.', '')]);

                // Marcar OC-000002 como recibida
                $workflow->markReceived($oc2->fresh() ?? $oc2);
            }
        });
    }

    /**
     * Calcula el costo unitario en moneda base sin impuesto,
     * usando la misma lógica que PurchaseReceiptService::calculateUnitCost().
     */
    private function resolveUnitCost(PurchaseOrder $order, PurchaseOrderItem $item): float
    {
        $unitPrice = (float) $item->unit_price;
        $discountPct = (float) $item->discount_percent;
        $netUnit = $unitPrice * (1 - ($discountPct / 100));

        if ($order->prices_include_tax && $item->tax_type === TaxType::Taxable) {
            $taxRate = (float) $order->tax_rate;
            $netUnit = $taxRate > 0 ? $netUnit / (1 + ($taxRate / 100)) : $netUnit;
        }

        return round($netUnit * (float) $order->exchange_rate, 4);
    }

    /**
     * Calcula base_quantity y base_unit_cost aplicando el factor de presentación.
     *
     * @return array{0: float, 1: float}
     */
    private function resolveBaseValues(
        PurchaseOrderItem $orderItem,
        float $qty,
        float $unitCost
    ): array {
        if ($orderItem->item_presentation_id === null || $orderItem->presentation === null) {
            return [$qty, $unitCost];
        }

        $factor = (float) $orderItem->presentation->factor;
        $baseQty = $factor > 0 ? round($qty * $factor, 4) : $qty;
        $baseUnitCost = $factor > 0 ? round($unitCost / $factor, 6) : $unitCost;

        return [$baseQty, $baseUnitCost];
    }
}
