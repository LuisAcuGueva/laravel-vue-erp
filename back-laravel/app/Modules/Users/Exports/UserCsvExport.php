<?php

declare(strict_types=1);

namespace App\Modules\Users\Exports;

use App\Modules\Users\Models\User;
use App\Support\Export\CsvExport;
use Illuminate\Database\Eloquent\Model;

/**
 * Roles y sedes llegan precargados por `UserService::query()` (sin N+1).
 */
class UserCsvExport extends CsvExport
{
    protected function columns(): array
    {
        return [
            'name' => 'Nombre',
            'email' => 'Correo',
            'roles' => 'Roles',
            'branches' => 'Sedes',
            'status' => 'Estado',
            'last_login_at' => 'Último acceso',
        ];
    }

    protected function map(Model $row): array
    {
        /** @var User $row */
        return [
            'name' => $row->name,
            'email' => $row->email,
            'roles' => $row->roles->pluck('name')->sort()->implode('; '),
            'branches' => $row->branches->pluck('name')->sort()->implode('; '),
            'status' => $row->status === User::STATUS_ACTIVE ? 'Activo' : 'Inactivo',
            'last_login_at' => $row->last_login_at,
        ];
    }
}
