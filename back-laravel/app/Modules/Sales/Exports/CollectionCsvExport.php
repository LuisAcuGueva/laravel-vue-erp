<?php

declare(strict_types=1);

namespace App\Modules\Sales\Exports;

use App\Modules\Sales\Models\Collection;
use App\Support\Export\CsvExport;
use Illuminate\Database\Eloquent\Model;

class CollectionCsvExport extends CsvExport
{
    protected function columns(): array
    {
        return [
            'number' => 'N° Cobranza',
            'collection_date' => 'Fecha',
            'client_doc' => 'Doc. Cliente',
            'client_name' => 'Cliente',
            'currency' => 'Moneda',
            'total_amount' => 'Monto Total',
            'status' => 'Estado',
            'creator' => 'Registrado Por',
        ];
    }

    protected function map(Model $row): array
    {
        /** @var Collection $row */
        return [
            'number' => $row->number,
            'collection_date' => $row->payment_date->toDateString(),
            'client_doc' => $row->client->document_number ?? '—',
            'client_name' => $row->client->name,
            'currency' => $row->currency->code,
            'total_amount' => $row->amount,
            'status' => $row->status->label(),
            'creator' => $row->creator ? $row->creator->name : '—',
        ];
    }
}
