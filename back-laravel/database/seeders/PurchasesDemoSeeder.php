<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Finance\Models\Currency;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Area;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\PaymentCondition;
use App\Modules\Purchases\Enums\Priority;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Enums\PurchaseRequestStatus;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Purchases\Models\PurchaseRequestItem;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use App\Support\Pricing\DocumentTotalsCalculator;
use App\Support\Pricing\LineInput;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchasesDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Si ya existen órdenes demo, no duplicar
        if (PurchaseOrder::where('number', 'like', 'OC-%')->exists()) {
            return;
        }

        $branch = Branch::where('is_main', true)->first() ?? Branch::first();
        $warehouse = Warehouse::where('code', 'ALM01')->first()
            ?? Warehouse::where('is_default', true)->first();
        $admin = User::where('email', 'admin@example.com')->first() ?? User::first();
        $pen = Currency::where('code', 'PEN')->first();

        if ($branch === null || $warehouse === null || $admin === null || $pen === null) {
            return;
        }

        $areas = Area::get()->keyBy('name');
        $providers = Provider::get()->keyBy('document_number');
        $catalog = CatalogItem::with('defaultPresentation')->get()->keyBy('code');

        $calculator = new DocumentTotalsCalculator;

        DB::transaction(function () use (
            $branch,
            $warehouse,
            $admin,
            $pen,
            $areas,
            $providers,
            $catalog,
            $calculator
        ) {
            // =========================================================================
            // REQUERIMIENTOS DE COMPRA
            // =========================================================================

            // RQ-000001: TI - Renovación tecnológica (Aprobado y vinculado a OC-000001)
            $rq1 = PurchaseRequest::updateOrCreate(
                ['number' => 'RQ-000001'],
                [
                    'branch_id' => $branch->id,
                    'area_id' => $areas['Tecnología y Sistemas (TI)']?->id ?? $areas->first()->id,
                    'warehouse_id' => $warehouse->id,
                    'requested_by' => $admin->id,
                    'required_date' => Carbon::now()->addDays(5)->toDateString(),
                    'priority' => Priority::Normal,
                    'status' => PurchaseRequestStatus::Approved,
                    'notes' => 'Requerimiento de renovación tecnológica para puestos de desarrollo y diseño',
                    'approved_by' => $admin->id,
                    'approved_at' => Carbon::now()->subDays(10),
                ]
            );
            SequentialCodeGenerator::reserve('purchase_request', $rq1->number);

            $rq1Items = [
                ['code' => 'ITM-000001', 'qty' => 5.0,  'notes' => 'Laptops Lenovo Core i7'],
                ['code' => 'ITM-000002', 'qty' => 10.0, 'notes' => 'Monitores Samsung 27 4K'],
                ['code' => 'ITM-000005', 'qty' => 15.0, 'notes' => 'Discos SSD NVMe 1TB'],
            ];

            $rq1ModelItems = [];
            foreach ($rq1Items as $idx => $row) {
                $item = $catalog->get($row['code']);
                if ($item !== null && $item->defaultPresentation !== null) {
                    $rq1ModelItems[$row['code']] = PurchaseRequestItem::updateOrCreate(
                        ['purchase_request_id' => $rq1->id, 'line' => $idx + 1],
                        [
                            'catalog_item_id' => $item->id,
                            'item_presentation_id' => $item->defaultPresentation->id,
                            'description' => $item->name,
                            'quantity' => $row['qty'],
                            'ordered_quantity' => $row['qty'], // Ya enlazadas a la OC
                            'notes' => $row['notes'],
                        ]
                    );
                }
            }

            // RQ-000002: Administración - Papelería y economato (Aprobado y vinculado a OC-000002)
            $rq2 = PurchaseRequest::updateOrCreate(
                ['number' => 'RQ-000002'],
                [
                    'branch_id' => $branch->id,
                    'area_id' => $areas['Administración y Finanzas']?->id ?? $areas->first()->id,
                    'warehouse_id' => $warehouse->id,
                    'requested_by' => $admin->id,
                    'required_date' => Carbon::now()->addDays(3)->toDateString(),
                    'priority' => Priority::Normal,
                    'status' => PurchaseRequestStatus::Approved,
                    'notes' => 'Abastecimiento mensual de papel fotocopia y suministros para administración',
                    'approved_by' => $admin->id,
                    'approved_at' => Carbon::now()->subDays(8),
                ]
            );
            SequentialCodeGenerator::reserve('purchase_request', $rq2->number);

            $rq2Items = [
                ['code' => 'ITM-000008', 'qty' => 50.0, 'notes' => 'Papel A4 paquete x 500 hojas'],
                ['code' => 'ITM-000011', 'qty' => 60.0, 'notes' => 'Aceite vegetal economato'],
            ];

            $rq2ModelItems = [];
            foreach ($rq2Items as $idx => $row) {
                $item = $catalog->get($row['code']);
                if ($item !== null && $item->defaultPresentation !== null) {
                    $rq2ModelItems[$row['code']] = PurchaseRequestItem::updateOrCreate(
                        ['purchase_request_id' => $rq2->id, 'line' => $idx + 1],
                        [
                            'catalog_item_id' => $item->id,
                            'item_presentation_id' => $item->defaultPresentation->id,
                            'description' => $item->name,
                            'quantity' => $row['qty'],
                            'ordered_quantity' => $row['qty'],
                            'notes' => $row['notes'],
                        ]
                    );
                }
            }

            // RQ-000003: Operaciones - Reposición urgente (Enviado / Pendiente de aprobación)
            $rq3 = PurchaseRequest::updateOrCreate(
                ['number' => 'RQ-000003'],
                [
                    'branch_id' => $branch->id,
                    'area_id' => $areas['Operaciones y Logística']?->id ?? $areas->first()->id,
                    'warehouse_id' => $warehouse->id,
                    'requested_by' => $admin->id,
                    'required_date' => Carbon::now()->addDays(2)->toDateString(),
                    'priority' => Priority::Urgent,
                    'status' => PurchaseRequestStatus::Submitted,
                    'notes' => 'Herramientas de reposición inmediata para equipo de operaciones de sede',
                ]
            );
            SequentialCodeGenerator::reserve('purchase_request', $rq3->number);

            $rq3Items = [
                ['code' => 'ITM-000016', 'qty' => 3.0,  'notes' => 'Taladro Percutor 20V'],
                ['code' => 'ITM-000009', 'qty' => 10.0, 'notes' => 'Cables HDMI 2m'],
            ];

            foreach ($rq3Items as $idx => $row) {
                $item = $catalog->get($row['code']);
                if ($item !== null && $item->defaultPresentation !== null) {
                    PurchaseRequestItem::updateOrCreate(
                        ['purchase_request_id' => $rq3->id, 'line' => $idx + 1],
                        [
                            'catalog_item_id' => $item->id,
                            'item_presentation_id' => $item->defaultPresentation->id,
                            'description' => $item->name,
                            'quantity' => $row['qty'],
                            'ordered_quantity' => 0.0,
                            'notes' => $row['notes'],
                        ]
                    );
                }
            }

            // RQ-000004: TI - Periféricos adicionales (Borrador para edición en UI)
            $rq4 = PurchaseRequest::updateOrCreate(
                ['number' => 'RQ-000004'],
                [
                    'branch_id' => $branch->id,
                    'area_id' => $areas['Tecnología y Sistemas (TI)']?->id ?? $areas->first()->id,
                    'warehouse_id' => $warehouse->id,
                    'requested_by' => $admin->id,
                    'required_date' => Carbon::now()->addDays(10)->toDateString(),
                    'priority' => Priority::Normal,
                    'status' => PurchaseRequestStatus::Draft,
                    'notes' => 'Borrador preliminar de teclados y mouses para nuevos ingresos',
                ]
            );
            SequentialCodeGenerator::reserve('purchase_request', $rq4->number);

            $rq4Items = [
                ['code' => 'ITM-000003', 'qty' => 10.0, 'notes' => 'Teclados mecánicos RGB'],
                ['code' => 'ITM-000004', 'qty' => 15.0, 'notes' => 'Mouse bluetooth'],
            ];

            foreach ($rq4Items as $idx => $row) {
                $item = $catalog->get($row['code']);
                if ($item !== null && $item->defaultPresentation !== null) {
                    PurchaseRequestItem::updateOrCreate(
                        ['purchase_request_id' => $rq4->id, 'line' => $idx + 1],
                        [
                            'catalog_item_id' => $item->id,
                            'item_presentation_id' => $item->defaultPresentation->id,
                            'description' => $item->name,
                            'quantity' => $row['qty'],
                            'ordered_quantity' => 0.0,
                            'notes' => $row['notes'],
                        ]
                    );
                }
            }

            // =========================================================================
            // ÓRDENES DE COMPRA
            // =========================================================================

            $techProvider = $providers['20512345671'] ?? null;
            $paperProvider = $providers['20601234565'] ?? null;
            $foodProvider = $providers['20498765433'] ?? null;
            $hardwareProvider = $providers['20555444339'] ?? null;

            // -------------------------------------------------------------------------
            // OC-000001: Mayorista Tecnológico (Aprobada / lista para ser recibida por almacén)
            // -------------------------------------------------------------------------
            if ($techProvider !== null) {
                $oc1LinesConfig = [
                    ['code' => 'ITM-000001', 'qty' => 5.0,  'price' => 2950.00, 'reqItem' => $rq1ModelItems['ITM-000001'] ?? null],
                    ['code' => 'ITM-000002', 'qty' => 10.0, 'price' => 860.00,  'reqItem' => $rq1ModelItems['ITM-000002'] ?? null],
                    ['code' => 'ITM-000005', 'qty' => 15.0, 'price' => 245.00,  'reqItem' => $rq1ModelItems['ITM-000005'] ?? null],
                ];

                $lineInputs = [];
                foreach ($oc1LinesConfig as $c) {
                    $lineInputs[] = new LineInput((string) $c['qty'], (string) $c['price'], '0', null, TaxType::Taxable);
                }

                $totals = $calculator->calculate($lineInputs, '18.00', false);

                $oc1 = PurchaseOrder::updateOrCreate(
                    ['number' => 'OC-000001'],
                    [
                        'branch_id' => $branch->id,
                        'provider_id' => $techProvider->id,
                        'warehouse_id' => $warehouse->id,
                        'order_date' => Carbon::now()->startOfMonth()->addDays(1)->toDateString(),
                        'expected_date' => Carbon::now()->startOfMonth()->addDays(4)->toDateString(),
                        'currency_id' => $pen->id,
                        'exchange_rate' => 1.0000,
                        'payment_condition' => PaymentCondition::Credit,
                        'credit_days' => 30,
                        'prices_include_tax' => false,
                        'tax_rate' => 18.00,
                        'taxable_base' => (float) $totals->taxableBase,
                        'exempt_base' => (float) $totals->exemptBase,
                        'unaffected_base' => (float) $totals->unaffectedBase,
                        'discount_total' => (float) $totals->discountTotal,
                        'tax_total' => (float) $totals->taxTotal,
                        'total' => (float) $totals->total,
                        'total_base_currency' => (float) $totals->total,
                        'status' => PurchaseOrderStatus::Approved,
                        'notes' => 'Orden de compra según contrato corporativo TI',
                        'terms' => 'Entrega en Almacén Principal. Pago a 30 días contra recepción conforme y factura electrónica.',
                        'created_by' => $admin->id,
                        'approved_by' => $admin->id,
                        'approved_at' => Carbon::now()->startOfMonth()->addDays(1)->setTime(10, 0),
                    ]
                );
                SequentialCodeGenerator::reserve('purchase_order', $oc1->number);

                foreach ($oc1LinesConfig as $idx => $c) {
                    $item = $catalog->get($c['code']);
                    $calcLine = $totals->lines[$idx];

                    PurchaseOrderItem::updateOrCreate(
                        ['purchase_order_id' => $oc1->id, 'line' => $idx + 1],
                        [
                            'purchase_request_item_id' => $c['reqItem']?->id,
                            'catalog_item_id' => $item?->id,
                            'item_presentation_id' => $item?->defaultPresentation?->id,
                            'description' => $item?->name,
                            'is_service' => false,
                            'quantity' => $c['qty'],
                            'received_quantity' => 0.0, // Se actualizará al recibir en StockDemoSeeder
                            'unit_price' => $c['price'],
                            'discount_percent' => 0.0,
                            'tax_type' => TaxType::Taxable->value,
                            'line_subtotal' => (float) $calcLine->subtotal,
                            'line_tax' => (float) $calcLine->tax,
                            'line_total' => (float) $calcLine->total,
                        ]
                    );
                }
            }

            // -------------------------------------------------------------------------
            // OC-000002: Distribuidora Papelera (Aprobada / reposición de papel)
            // -------------------------------------------------------------------------
            if ($paperProvider !== null) {
                $oc2LinesConfig = [
                    ['code' => 'ITM-000008', 'qty' => 50.0, 'price' => 12.20, 'reqItem' => $rq2ModelItems['ITM-000008'] ?? null],
                ];

                $lineInputs = [
                    new LineInput('50.0', '12.20', '0', null, TaxType::Taxable),
                ];

                $totals = $calculator->calculate($lineInputs, '18.00', false);

                $oc2 = PurchaseOrder::updateOrCreate(
                    ['number' => 'OC-000002'],
                    [
                        'branch_id' => $branch->id,
                        'provider_id' => $paperProvider->id,
                        'warehouse_id' => $warehouse->id,
                        'order_date' => Carbon::now()->startOfMonth()->addDays(2)->toDateString(),
                        'expected_date' => Carbon::now()->startOfMonth()->addDays(4)->toDateString(),
                        'currency_id' => $pen->id,
                        'exchange_rate' => 1.0000,
                        'payment_condition' => PaymentCondition::Credit,
                        'credit_days' => 15,
                        'prices_include_tax' => false,
                        'tax_rate' => 18.00,
                        'taxable_base' => (float) $totals->taxableBase,
                        'exempt_base' => (float) $totals->exemptBase,
                        'unaffected_base' => (float) $totals->unaffectedBase,
                        'discount_total' => (float) $totals->discountTotal,
                        'tax_total' => (float) $totals->taxTotal,
                        'total' => (float) $totals->total,
                        'total_base_currency' => (float) $totals->total,
                        'status' => PurchaseOrderStatus::Approved,
                        'notes' => 'Reposición mensual de papel bond A4',
                        'terms' => 'Crédito a 15 días.',
                        'created_by' => $admin->id,
                        'approved_by' => $admin->id,
                        'approved_at' => Carbon::now()->startOfMonth()->addDays(2)->setTime(11, 0),
                    ]
                );
                SequentialCodeGenerator::reserve('purchase_order', $oc2->number);

                foreach ($oc2LinesConfig as $idx => $c) {
                    $item = $catalog->get($c['code']);
                    $calcLine = $totals->lines[$idx];

                    PurchaseOrderItem::updateOrCreate(
                        ['purchase_order_id' => $oc2->id, 'line' => $idx + 1],
                        [
                            'purchase_request_item_id' => $c['reqItem']?->id,
                            'catalog_item_id' => $item?->id,
                            'item_presentation_id' => $item?->defaultPresentation?->id,
                            'description' => $item?->name,
                            'is_service' => false,
                            'quantity' => $c['qty'],
                            'received_quantity' => 0.0,
                            'unit_price' => $c['price'],
                            'discount_percent' => 0.0,
                            'tax_type' => TaxType::Taxable->value,
                            'line_subtotal' => (float) $calcLine->subtotal,
                            'line_tax' => (float) $calcLine->tax,
                            'line_total' => (float) $calcLine->total,
                        ]
                    );
                }
            }

            // -------------------------------------------------------------------------
            // OC-000003: Consorcio Alimentos (Pendiente de aprobación)
            // -------------------------------------------------------------------------
            if ($foodProvider !== null) {
                $oc3LinesConfig = [
                    ['code' => 'ITM-000010', 'qty' => 100.0, 'price' => 3.10, 'tax' => TaxType::Unaffected], // Arroz exonerado/inafecto
                    ['code' => 'ITM-000011', 'qty' => 60.0,  'price' => 6.40, 'tax' => TaxType::Taxable],
                    ['code' => 'ITM-000012', 'qty' => 80.0,  'price' => 3.20, 'tax' => TaxType::Taxable],
                ];

                $lineInputs = [];
                foreach ($oc3LinesConfig as $c) {
                    $lineInputs[] = new LineInput((string) $c['qty'], (string) $c['price'], '0', null, $c['tax']);
                }

                $totals = $calculator->calculate($lineInputs, '18.00', false);

                $oc3 = PurchaseOrder::updateOrCreate(
                    ['number' => 'OC-000003'],
                    [
                        'branch_id' => $branch->id,
                        'provider_id' => $foodProvider->id,
                        'warehouse_id' => $warehouse->id,
                        'order_date' => Carbon::now()->subDays(2)->toDateString(),
                        'expected_date' => Carbon::now()->addDays(2)->toDateString(),
                        'currency_id' => $pen->id,
                        'exchange_rate' => 1.0000,
                        'payment_condition' => PaymentCondition::Cash,
                        'credit_days' => 0,
                        'prices_include_tax' => false,
                        'tax_rate' => 18.00,
                        'taxable_base' => (float) $totals->taxableBase,
                        'exempt_base' => (float) $totals->exemptBase,
                        'unaffected_base' => (float) $totals->unaffectedBase,
                        'discount_total' => (float) $totals->discountTotal,
                        'tax_total' => (float) $totals->taxTotal,
                        'total' => (float) $totals->total,
                        'total_base_currency' => (float) $totals->total,
                        'status' => PurchaseOrderStatus::PendingApproval,
                        'notes' => 'Pedido de economato mensual de alimentos',
                        'terms' => 'Contado contra entrega.',
                        'created_by' => $admin->id,
                    ]
                );
                SequentialCodeGenerator::reserve('purchase_order', $oc3->number);

                foreach ($oc3LinesConfig as $idx => $c) {
                    $item = $catalog->get($c['code']);
                    $calcLine = $totals->lines[$idx];

                    PurchaseOrderItem::updateOrCreate(
                        ['purchase_order_id' => $oc3->id, 'line' => $idx + 1],
                        [
                            'catalog_item_id' => $item?->id,
                            'item_presentation_id' => $item?->defaultPresentation?->id,
                            'description' => $item?->name,
                            'is_service' => false,
                            'quantity' => $c['qty'],
                            'received_quantity' => 0.0,
                            'unit_price' => $c['price'],
                            'discount_percent' => 0.0,
                            'tax_type' => $c['tax']->value,
                            'line_subtotal' => (float) $calcLine->subtotal,
                            'line_tax' => (float) $calcLine->tax,
                            'line_total' => (float) $calcLine->total,
                        ]
                    );
                }
            }

            // -------------------------------------------------------------------------
            // OC-000004: Comercializadora Ferretera (Borrador para interacción en la UI)
            // -------------------------------------------------------------------------
            if ($hardwareProvider !== null) {
                $oc4LinesConfig = [
                    ['code' => 'ITM-000016', 'qty' => 2.0, 'price' => 280.00],
                    ['code' => 'ITM-000017', 'qty' => 5.0, 'price' => 60.00],
                ];

                $lineInputs = [];
                foreach ($oc4LinesConfig as $c) {
                    $lineInputs[] = new LineInput((string) $c['qty'], (string) $c['price'], '0', null, TaxType::Taxable);
                }

                $totals = $calculator->calculate($lineInputs, '18.00', false);

                $oc4 = PurchaseOrder::updateOrCreate(
                    ['number' => 'OC-000004'],
                    [
                        'branch_id' => $branch->id,
                        'provider_id' => $hardwareProvider->id,
                        'warehouse_id' => $warehouse->id,
                        'order_date' => Carbon::now()->subDay()->toDateString(),
                        'expected_date' => Carbon::now()->addDays(3)->toDateString(),
                        'currency_id' => $pen->id,
                        'exchange_rate' => 1.0000,
                        'payment_condition' => PaymentCondition::Cash,
                        'credit_days' => 0,
                        'prices_include_tax' => false,
                        'tax_rate' => 18.00,
                        'taxable_base' => (float) $totals->taxableBase,
                        'exempt_base' => (float) $totals->exemptBase,
                        'unaffected_base' => (float) $totals->unaffectedBase,
                        'discount_total' => (float) $totals->discountTotal,
                        'tax_total' => (float) $totals->taxTotal,
                        'total' => (float) $totals->total,
                        'total_base_currency' => (float) $totals->total,
                        'status' => PurchaseOrderStatus::Draft,
                        'notes' => 'Borrador de orden de reposición ferretería',
                        'terms' => 'Contado.',
                        'created_by' => $admin->id,
                    ]
                );
                SequentialCodeGenerator::reserve('purchase_order', $oc4->number);

                foreach ($oc4LinesConfig as $idx => $c) {
                    $item = $catalog->get($c['code']);
                    $calcLine = $totals->lines[$idx];

                    PurchaseOrderItem::updateOrCreate(
                        ['purchase_order_id' => $oc4->id, 'line' => $idx + 1],
                        [
                            'catalog_item_id' => $item?->id,
                            'item_presentation_id' => $item?->defaultPresentation?->id,
                            'description' => $item?->name,
                            'is_service' => false,
                            'quantity' => $c['qty'],
                            'received_quantity' => 0.0,
                            'unit_price' => $c['price'],
                            'discount_percent' => 0.0,
                            'tax_type' => TaxType::Taxable->value,
                            'line_subtotal' => (float) $calcLine->subtotal,
                            'line_tax' => (float) $calcLine->tax,
                            'line_total' => (float) $calcLine->total,
                        ]
                    );
                }
            }
        });
    }
}
