<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Exports;

use App\Modules\Maintenance\Models\Branch;
use App\Modules\Ubigeo\Services\UbigeoService;
use App\Support\Export\CsvExport;
use Illuminate\Database\Eloquent\Model;

class BranchCsvExport extends CsvExport
{
    public function __construct(
        private readonly UbigeoService $ubigeoService,
    ) {}

    protected function columns(): array
    {
        return [
            'code' => 'Código',
            'name' => 'Nombre',
            'address' => 'Dirección',
            'location' => 'Ubicación',
            'phone' => 'Teléfono',
            'email' => 'Correo',
            'is_main' => 'Principal',
            'users_count' => 'Usuarios',
            'status' => 'Estado',
        ];
    }

    protected function map(Model $row): array
    {
        /** @var Branch $row */
        return [
            'code' => $row->code,
            'name' => $row->name,
            'address' => $row->address,
            'location' => $this->ubigeoService->find($row->ubigeo)?->label(),
            'phone' => $row->phone,
            'email' => $row->email,
            'is_main' => $row->is_main,
            'users_count' => (int) ($row->users_count ?? 0),
            'status' => $row->is_active ? 'Activo' : 'Inactivo',
        ];
    }
}
