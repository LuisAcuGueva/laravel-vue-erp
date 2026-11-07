<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Exports;

use App\Modules\Maintenance\Models\Area;
use App\Support\Export\CsvExport;
use Illuminate\Database\Eloquent\Model;

class AreaCsvExport extends CsvExport
{
    protected function columns(): array
    {
        return [
            'name' => 'Nombre',
            'description' => 'Descripción',
            'status' => 'Estado',
        ];
    }

    protected function map(Model $row): array
    {
        /** @var Area $row */
        return [
            'name' => $row->name,
            'description' => $row->description,
            'status' => $row->is_active ? 'Activo' : 'Inactivo',
        ];
    }
}
