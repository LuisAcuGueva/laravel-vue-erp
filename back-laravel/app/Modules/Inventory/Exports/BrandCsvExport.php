<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exports;

use App\Modules\Inventory\Models\Brand;
use App\Support\Export\CsvExport;
use Illuminate\Database\Eloquent\Model;

class BrandCsvExport extends CsvExport
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
        /** @var Brand $row */
        return [
            'name' => $row->name,
            'description' => $row->description,
            'status' => $row->is_active ? 'Activo' : 'Inactivo',
        ];
    }
}
