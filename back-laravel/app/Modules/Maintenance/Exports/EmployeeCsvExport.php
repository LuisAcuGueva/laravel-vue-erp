<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Exports;

use App\Modules\Maintenance\Models\Employee;
use App\Support\Export\CsvExport;
use App\Support\Identity\DocumentType;
use Illuminate\Database\Eloquent\Model;

class EmployeeCsvExport extends CsvExport
{
    protected function columns(): array
    {
        return [
            'full_name' => 'Nombres',
            'document' => 'Documento',
            'position' => 'Cargo',
            'area' => 'Área',
            'branch' => 'Sede',
            'phone' => 'Teléfono',
            'email' => 'Correo',
            'user' => 'Usuario vinculado',
            'status' => 'Estado',
        ];
    }

    protected function map(Model $row): array
    {
        /** @var Employee $row */
        $documentType = $row->document_type instanceof DocumentType
            ? $row->document_type->value
            : (string) $row->document_type;

        return [
            'full_name' => $row->full_name,
            'document' => trim("{$documentType} {$row->document_number}"),
            'position' => $row->position,
            'area' => $row->area?->name,
            'branch' => $row->branch?->name,
            'phone' => $row->phone,
            'email' => $row->email,
            'user' => $row->user?->email,
            'status' => $row->is_active ? 'Activo' : 'Inactivo',
        ];
    }
}
