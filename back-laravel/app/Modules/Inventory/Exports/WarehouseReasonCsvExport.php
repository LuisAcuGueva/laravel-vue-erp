<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exports;

use App\Modules\Inventory\Models\WarehouseReason;
use App\Support\Export\CsvExport;
use Illuminate\Database\Eloquent\Model;

class WarehouseReasonCsvExport extends CsvExport
{
    protected function columns(): array
    {
        return [
            'code' => 'Código',
            'name' => 'Nombre',
            'type' => 'Tipo',
            'requires_approval' => 'Requiere aprobación',
            'is_system' => 'De sistema',
            'status' => 'Estado',
        ];
    }

    protected function map(Model $row): array
    {
        /** @var WarehouseReason $row */
        return [
            'code' => $row->code,
            'name' => $row->name,
            'type' => $row->type->label(),
            'requires_approval' => $row->requires_approval,
            'is_system' => $row->is_system,
            'status' => $row->is_active ? 'Activo' : 'Inactivo',
        ];
    }
}
