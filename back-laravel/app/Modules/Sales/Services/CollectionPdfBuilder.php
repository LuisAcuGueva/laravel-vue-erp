<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Enums\CollectionStatus;
use App\Modules\Sales\Models\Collection;
use App\Modules\Sales\Models\CollectionAllocation;

class CollectionPdfBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Collection $collection): array
    {
        $collection->loadMissing([
            'client',
            'currency',
            'branch',
            'creator',
            'allocations.installment',
            'allocations.sale',
        ]);

        return [
            'watermark' => $collection->status === CollectionStatus::Cancelled ? 'ANULADO' : null,
            'collection' => [
                'number' => $collection->number,
                'collection_date' => $collection->payment_date->format('d/m/Y'),
                'total_amount' => $collection->amount,
                'notes' => $collection->notes,
                'status' => $collection->status->value,
                'currency_code' => $collection->currency?->code,
                'currency_symbol' => $collection->currency?->symbol,
                'creator_name' => $collection->creator?->name,
                'branch_name' => $collection->branch?->name,
                'client' => [
                    'name' => $collection->client->name,
                    'document_type' => $collection->client->document_type !== null ? $collection->client->document_type->value : 'DOC',
                    'document_number' => $collection->client->document_number ?? '—',
                    'address' => $collection->client->address,
                    'phone' => $collection->client->phone,
                    'email' => $collection->client->email,
                ],
            ],
            'allocations' => $collection->allocations->map(fn (CollectionAllocation $a): array => [
                'sale_number' => $a->sale !== null ? $a->sale->full_number : '—',
                'installment_number' => (string) $a->installment->number,
                'due_date' => $a->installment->due_date->format('d/m/Y'),
                'amount' => $a->amount,
            ])->all(),
        ];
    }
}
