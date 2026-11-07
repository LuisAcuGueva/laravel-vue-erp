<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\UserBranch;
use App\Modules\Users\Models\User;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string, address: string, ubigeo: string, phone: string, email: string, is_main: bool}>
     */
    private const BRANCHES = [
        [
            'code' => 'PRINCIPAL',
            'name' => 'Sede Principal - Lima',
            'address' => 'Av. Nicolás de Piérola 1200, Cercado de Lima',
            'ubigeo' => '150101', // Lima
            'phone' => '01-4281234',
            'email' => 'principal@empresa.com',
            'is_main' => true,
        ],
        [
            'code' => 'MIRAFLORES',
            'name' => 'Sucursal Miraflores',
            'address' => 'Av. Larco 745, Miraflores',
            'ubigeo' => '150122', // Miraflores
            'phone' => '01-4458920',
            'email' => 'miraflores@empresa.com',
            'is_main' => false,
        ],
        [
            'code' => 'SANBORJA',
            'name' => 'Sucursal San Borja',
            'address' => 'Av. Javier Prado Este 2580, San Borja',
            'ubigeo' => '150130', // San Borja
            'phone' => '01-2245678',
            'email' => 'sanborja@empresa.com',
            'is_main' => false,
        ],
        [
            'code' => 'CALLAO',
            'name' => 'Sucursal Callao',
            'address' => 'Av. Elmer Faucett 1520, Callao',
            'ubigeo' => '070101', // Callao
            'phone' => '01-5743210',
            'email' => 'callao@empresa.com',
            'is_main' => false,
        ],
    ];

    public function run(): void
    {
        $createdBranches = [];

        foreach (self::BRANCHES as $data) {
            $createdBranches[$data['code']] = Branch::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'address' => $data['address'],
                    'ubigeo' => $data['ubigeo'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'is_main' => $data['is_main'],
                    'is_active' => true,
                ]
            );
        }

        $mainBranch = $createdBranches['PRINCIPAL'] ?? null;
        if ($mainBranch === null) {
            return;
        }

        $admin = User::where('email', 'admin@example.com')->first();
        $manager = User::where('email', 'manager@example.com')->first();
        $user = User::where('email', 'user@example.com')->first();

        // 1. Admin tiene acceso a todas las sedes, siendo PRINCIPAL la predeterminada
        if ($admin !== null) {
            foreach ($createdBranches as $code => $branch) {
                UserBranch::updateOrCreate(
                    [
                        'user_id' => $admin->id,
                        'branch_id' => $branch->id,
                    ],
                    [
                        'is_default' => ($code === 'PRINCIPAL'),
                    ]
                );
            }
        }

        // 2. Manager tiene acceso a PRINCIPAL y MIRAFLORES
        if ($manager !== null) {
            foreach (['PRINCIPAL', 'MIRAFLORES'] as $code) {
                if (isset($createdBranches[$code])) {
                    UserBranch::updateOrCreate(
                        [
                            'user_id' => $manager->id,
                            'branch_id' => $createdBranches[$code]->id,
                        ],
                        [
                            'is_default' => ($code === 'PRINCIPAL'),
                        ]
                    );
                }
            }
        }

        // 3. User tiene acceso a PRINCIPAL
        if ($user !== null) {
            UserBranch::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'branch_id' => $mainBranch->id,
                ],
                [
                    'is_default' => true,
                ]
            );
        }
    }
}
