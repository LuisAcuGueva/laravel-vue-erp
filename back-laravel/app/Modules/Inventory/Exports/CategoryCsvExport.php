<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exports;

use App\Modules\Inventory\Models\Category;
use App\Support\Export\CsvExport;
use Illuminate\Database\Eloquent\Model;

class CategoryCsvExport extends CsvExport
{
    protected function columns(): array
    {
        return [
            'code' => 'Código',
            'name' => 'Nombre',
            'path' => 'Ruta',
            'level' => 'Nivel',
            'description' => 'Descripción',
            'status' => 'Estado',
        ];
    }

    protected function map(Model $row): array
    {
        /** @var Category $row */
        return [
            'code' => $row->code,
            'name' => $row->name,
            'path' => $row->path(),
            'level' => $row->depth + 1,
            'description' => $row->description,
            'status' => $row->is_active ? 'Activo' : 'Inactivo',
        ];
    }
}
