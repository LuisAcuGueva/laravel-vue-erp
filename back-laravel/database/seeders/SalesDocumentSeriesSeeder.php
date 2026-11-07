<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Maintenance\Models\Branch;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Models\DocumentSeries;
use Illuminate\Database\Seeder;

/**
 * Series de documentos de venta (factura, boleta, nota de venta, notas de crédito)
 * de la sede principal. Es idempotente y no emite ningún documento de venta.
 */
class SalesDocumentSeriesSeeder extends Seeder
{
    public function run(): void
    {
        $mainBranch = Branch::where('is_main', true)->first() ?? Branch::first();

        if ($mainBranch === null) {
            return;
        }

        $series = [
            [
                'branch_id' => $mainBranch->id,
                'document_type' => SalesDocumentType::Invoice,
                'series' => 'F001',
                'is_electronic' => true,
                'is_active' => true,
            ],
            [
                'branch_id' => $mainBranch->id,
                'document_type' => SalesDocumentType::Receipt,
                'series' => 'B001',
                'is_electronic' => true,
                'is_active' => true,
            ],
            [
                'branch_id' => $mainBranch->id,
                'document_type' => SalesDocumentType::SaleNote,
                'series' => 'NV01',
                'is_electronic' => false,
                'is_active' => true,
            ],
            [
                'branch_id' => $mainBranch->id,
                'document_type' => SalesDocumentType::CreditNote,
                'series' => 'FC01',
                'is_electronic' => true,
                'is_active' => true,
            ],
            [
                'branch_id' => $mainBranch->id,
                'document_type' => SalesDocumentType::CreditNote,
                'series' => 'BC01',
                'is_electronic' => true,
                'is_active' => true,
            ],
        ];

        foreach ($series as $data) {
            DocumentSeries::updateOrCreate(
                [
                    'document_type' => $data['document_type'],
                    'series' => $data['series'],
                ],
                $data
            );
        }
    }
}
