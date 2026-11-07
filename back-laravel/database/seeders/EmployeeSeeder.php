<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Maintenance\Models\Area;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Users\Models\User;
use App\Support\Identity\DocumentType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * @var list<array{
     *     document_number: string,
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone: string,
     *     address: string,
     *     birth_date: string,
     *     hire_date: string,
     *     position: string,
     *     area_name: string,
     *     branch_code: string,
     *     user_email: ?string
     * }>
     */
    private const EMPLOYEES = [
        [
            'document_number' => '45892014',
            'first_name' => 'Ana Lucía',
            'last_name' => 'Rojas Delgado',
            'email' => 'arojas@empresa.com',
            'phone' => '987654321',
            'address' => 'Av. Javier Prado Este 1850, Dpto 402, San Borja',
            'birth_date' => '1988-04-12',
            'hire_date' => '2020-01-15',
            'position' => 'Gerente General',
            'area_name' => 'Dirección y Gerencia General',
            'branch_code' => 'PRINCIPAL',
            'user_email' => 'admin@example.com',
        ],
        [
            'document_number' => '47201938',
            'first_name' => 'Marco Antonio',
            'last_name' => 'Silva Castro',
            'email' => 'msilva@empresa.com',
            'phone' => '976543210',
            'address' => 'Jr. Las Palmeras 340, Los Olivos',
            'birth_date' => '1991-08-23',
            'hire_date' => '2021-03-01',
            'position' => 'Jefe de Operaciones y Logística',
            'area_name' => 'Operaciones y Logística',
            'branch_code' => 'PRINCIPAL',
            'user_email' => 'manager@example.com',
        ],
        [
            'document_number' => '70123456',
            'first_name' => 'Diego Fernando',
            'last_name' => 'Ramos Quispe',
            'email' => 'dramos@empresa.com',
            'phone' => '965432109',
            'address' => 'Av. Arequipa 2450, Lince',
            'birth_date' => '1995-11-05',
            'hire_date' => '2022-06-15',
            'position' => 'Ejecutivo de Ventas y Atención',
            'area_name' => 'Ventas y Comercial',
            'branch_code' => 'PRINCIPAL',
            'user_email' => 'user@example.com',
        ],
        [
            'document_number' => '46302819',
            'first_name' => 'Claudia Beatriz',
            'last_name' => 'Mendoza Flores',
            'email' => 'cmendoza.adm@empresa.com',
            'phone' => '954321098',
            'address' => 'Calle Ramón Ribeyro 420, Miraflores',
            'birth_date' => '1990-02-18',
            'hire_date' => '2022-09-01',
            'position' => 'Administradora de Sucursal',
            'area_name' => 'Administración y Finanzas',
            'branch_code' => 'MIRAFLORES',
            'user_email' => null,
        ],
        [
            'document_number' => '71234567',
            'first_name' => 'Carlos Eduardo',
            'last_name' => 'Torres Vega',
            'email' => 'ctorres@empresa.com',
            'phone' => '943210987',
            'address' => 'Av. Colonial 1890, Cercado de Lima',
            'birth_date' => '1994-07-30',
            'hire_date' => '2023-02-10',
            'position' => 'Encargado de Almacén y Despachos',
            'area_name' => 'Operaciones y Logística',
            'branch_code' => 'PRINCIPAL',
            'user_email' => null,
        ],
        [
            'document_number' => '72345678',
            'first_name' => 'Valeria Sofía',
            'last_name' => 'Chávez Morales',
            'email' => 'vchavez@empresa.com',
            'phone' => '932109876',
            'address' => 'Av. Pardo 890, Miraflores',
            'birth_date' => '1997-05-14',
            'hire_date' => '2023-05-20',
            'position' => 'Vendedora de Mostrador',
            'area_name' => 'Ventas y Comercial',
            'branch_code' => 'MIRAFLORES',
            'user_email' => null,
        ],
        [
            'document_number' => '48192034',
            'first_name' => 'Javier Alejandro',
            'last_name' => 'Morales Rivas',
            'email' => 'jmorales@empresa.com',
            'phone' => '921098765',
            'address' => 'Calle Las Camelias 560, San Borja',
            'birth_date' => '1989-10-08',
            'hire_date' => '2023-08-01',
            'position' => 'Administrador de Sucursal',
            'area_name' => 'Administración y Finanzas',
            'branch_code' => 'SANBORJA',
            'user_email' => null,
        ],
        [
            'document_number' => '73456789',
            'first_name' => 'Patricia Elena',
            'last_name' => 'Vidal Huamán',
            'email' => 'pvidal@empresa.com',
            'phone' => '910987654',
            'address' => 'Av. Sáenz Peña 670, Callao',
            'birth_date' => '1996-03-22',
            'hire_date' => '2024-01-15',
            'position' => 'Supervisora de Distribución',
            'area_name' => 'Operaciones y Logística',
            'branch_code' => 'CALLAO',
            'user_email' => null,
        ],
    ];

    public function run(): void
    {
        $areas = Area::get()->keyBy('name');
        $branches = Branch::get()->keyBy('code');
        $users = User::get()->keyBy('email');

        foreach (self::EMPLOYEES as $data) {
            $area = $areas->get($data['area_name']);
            $branch = $branches->get($data['branch_code']) ?? $branches->first();
            $user = $data['user_email'] !== null ? $users->get($data['user_email']) : null;

            Employee::updateOrCreate(
                [
                    'document_type' => DocumentType::Dni,
                    'document_number' => $data['document_number'],
                ],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'address' => $data['address'],
                    'birth_date' => Carbon::parse($data['birth_date']),
                    'hire_date' => Carbon::parse($data['hire_date']),
                    'position' => $data['position'],
                    'area_id' => $area?->id,
                    'branch_id' => $branch?->id,
                    'user_id' => $user?->id,
                    'is_active' => true,
                ]
            );
        }
    }
}
