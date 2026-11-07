<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exports;

use App\Modules\Inventory\Models\Warehouse;
use App\Support\Export\CsvExport;
use Illuminate\Database\Eloquent\Model;

class WarehouseCsvExport extends CsvExport
{
    protected function columns(): array
    {
        return [
            'code' => 'Código',
            'name' => 'Nombre',
            'branch' => 'Sede',
            'address' => 'Dirección',
            'is_default' => 'Por defecto',
            'status' => 'Estado',
        ];
    }

    protected function map(Model $row): array
    {
        /** @var Warehouse $row */
        return [
            'code' => $row->code,
            'name' => $row->name,
            'branch' => $row->branch?->name,
            'address' => $row->address,
            'is_default' => $row->is_default,
            'status' => $row->is_active ? 'Activo' : 'Inactivo',
        ];
    }
}
