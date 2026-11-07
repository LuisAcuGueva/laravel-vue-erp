<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Clients\Enums\ClientType;
use App\Modules\Clients\Models\Client;
use App\Modules\Inventory\Enums\PriceType;
use App\Support\Identity\DocumentType;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Siembra el cliente de sistema único (CLIENTES VARIOS). Reutilizable en cualquier seeder o entorno.
     */
    public static function seedSystemClient(): Client
    {
        $systemClient = Client::where('is_system', true)->first();

        if ($systemClient === null) {
            $systemClient = new Client;
            $systemClient->forceFill([
                'client_type' => ClientType::Person,
                'document_type' => DocumentType::Other,
                'document_number' => '00000000',
                'name' => 'CLIENTES VARIOS',
                'trade_name' => null,
                'default_price_type' => PriceType::Retail,
                'credit_limit' => 0,
                'credit_days' => 0,
                'is_system' => true,
                'is_active' => true,
                'description' => 'Cliente genérico del sistema para ventas varias.',
            ]);
            $systemClient->save();
        }

        return $systemClient;
    }

    /**
     * Seed the clients table with sample data.
     */
    public function run(): void
    {
        self::seedSystemClient();

        if (app()->environment('local', 'testing')) {
            if (Client::where('is_system', false)->count() === 0) {
                // Empresas
                $sanJorge = Client::factory()->company()->withCredit(10000, 30)->create([
                    'name' => 'DISTRIBUIDORA SAN JORGE S.A.C.',
                    'trade_name' => 'San Jorge',
                    'document_number' => '20123456789',
                    'email' => 'ventas@sanjorge.pe',
                    'phone' => '01-4455667',
                    'address' => 'Av. Argentina 1234, Callao',
                ]);

                $sanJorge->contacts()->create([
                    'name' => 'Luis Alberto Mendoza Chávez',
                    'position' => 'Gerente de Ventas',
                    'phone' => '987112233',
                    'email' => 'lmendoza@sanjorge.pe',
                    'is_primary' => true,
                ]);

                $corpnorte = Client::factory()->company()->create([
                    'name' => 'CORPORACION INDUSTRIAL DEL NORTE S.A.',
                    'trade_name' => 'Corpnorte',
                    'document_number' => '20987654321',
                    'email' => 'contacto@corpnorte.pe',
                    'phone' => '074-223344',
                    'address' => 'Carretera Panamericana Km 5, Chiclayo',
                ]);

                $corpnorte->contacts()->create([
                    'name' => 'Rosa Isabel Castillo Vargas',
                    'position' => 'Jefa de Compras',
                    'phone' => '979223344',
                    'email' => 'rcastillo@corpnorte.pe',
                    'is_primary' => true,
                ]);

                // Personas
                $juanPerez = Client::factory()->person()->create([
                    'name' => 'Juan Carlos Pérez Ramos',
                    'document_number' => '41234567',
                    'email' => 'jperez@gmail.com',
                    'phone' => '987654321',
                    'address' => 'Jr. Los Cedros 345, Trujillo',
                ]);

                $juanPerez->contacts()->create([
                    'name' => 'Carmen Rosa Ramos Delgado',
                    'position' => 'Esposa',
                    'phone' => '987654322',
                    'email' => null,
                    'is_primary' => true,
                ]);

                $mariaFlores = Client::factory()->person()->withCredit(1500, 15)->create([
                    'name' => 'María Elena Flores Gómez',
                    'document_number' => '42345678',
                    'email' => 'mflores@outlook.com',
                    'phone' => '912345678',
                    'address' => 'Calle San Martín 567, Piura',
                ]);

                $mariaFlores->contacts()->create([
                    'name' => 'Pedro Antonio Gómez Silva',
                    'position' => 'Hermano',
                    'phone' => '912345679',
                    'email' => null,
                    'is_primary' => true,
                ]);

                // Persona con RUC (10)
                $robertoDiaz = Client::factory()->create([
                    'client_type' => ClientType::Person,
                    'document_type' => DocumentType::Ruc,
                    'document_number' => '10434567891',
                    'name' => 'ROBERTO CARLOS DIAZ BAZAN',
                    'trade_name' => 'Comercial Díaz',
                    'email' => 'rdiaz@comercialdiaz.pe',
                    'phone' => '976543210',
                    'address' => 'Av. Tacna 789, Lima',
                    'default_price_type' => PriceType::Retail,
                    'credit_limit' => 3000,
                    'credit_days' => 15,
                ]);

                $robertoDiaz->contacts()->create([
                    'name' => 'Diana Patricia Diaz Bazan',
                    'position' => 'Administradora',
                    'phone' => '976543211',
                    'email' => 'ddiaz@comercialdiaz.pe',
                    'is_primary' => true,
                ]);
            }
        }
    }
}
