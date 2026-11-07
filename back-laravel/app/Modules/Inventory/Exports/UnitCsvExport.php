<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exports;

use App\Modules\Inventory\Models\Unit;
use App\Support\Export\CsvExport;
use Illuminate\Database\Eloquent\Model;

class UnitCsvExport extends CsvExport
{
    protected function columns(): array
    {
        return [
            'category' => 'Categoría',
            'name' => 'Nombre',
            'symbol' => 'Símbolo',
            'is_base' => 'Unidad base',
            'conversion_factor' => 'Factor de conversión',
            'allow_decimals' => 'Permite decimales',
            'sunat_code' => 'Código SUNAT',
            'status' => 'Estado',
        ];
    }

    protected function map(Model $row): array
    {
        /** @var Unit $row */
        $isPackaging = (bool) $row->category?->is_packaging;

        return [
            'category' => $row->category?->name,
            'name' => $row->name,
            'symbol' => $row->symbol,
            'is_base' => $row->is_base,
            // Las unidades de empaque no tienen factor fijo: lo define cada presentación.
            'conversion_factor' => $isPackaging ? null : $row->conversion_factor,
            'allow_decimals' => $row->allow_decimals,
            'sunat_code' => $row->sunat_code,
            'status' => $row->is_active ? 'Activo' : 'Inactivo',
        ];
    }
}
