<?php

declare(strict_types=1);

namespace App\Modules\Clients\Exports;

use App\Modules\Clients\Models\Client;
use App\Support\Export\CsvExport;
use Illuminate\Database\Eloquent\Model;

class ClientCsvExport extends CsvExport
{
    protected function columns(): array
    {
        return [
            'name' => 'Nombre',
            'description' => 'Descripción',
            'created_at' => 'Fecha de registro',
        ];
    }

    protected function map(Model $row): array
    {
        /** @var Client $row */
        return [
            'name' => $row->name,
            'description' => $row->description,
            'created_at' => $row->created_at,
        ];
    }
}
