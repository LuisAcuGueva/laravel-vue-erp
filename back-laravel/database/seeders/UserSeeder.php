<?php

namespace Database\Seeders;

use App\Modules\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $users = [
            [
                'email' => 'admin@example.com',
                'name' => 'Super Administrador',
                'password' => $password,
                'role' => 'admin',
            ],
            [
                'email' => 'manager@example.com',
                'name' => 'Gerente Operativo',
                'password' => $password,
                'role' => 'manager',
            ],
            [
                'email' => 'user@example.com',
                'name' => 'Operador de Ventas',
                'password' => $password,
                'role' => 'user',
            ],
            [
                'email' => 'demo@example.com',
                'name' => 'Usuario Demo',
                'password' => $password,
                'role' => 'user',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => $userData['password'],
                    'status' => User::STATUS_ACTIVE,
                    'token_version' => 1,
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$userData['role']]);
        }
    }
}
