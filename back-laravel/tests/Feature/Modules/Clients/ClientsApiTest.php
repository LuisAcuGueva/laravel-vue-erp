<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Clients;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Clients\Models\Client;
use App\Modules\Clients\Models\ClientContact;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Users\Models\User;
use App\Support\Identity\DocumentType;
use Database\Seeders\ClientSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class ClientsApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private User $authorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        (new RolePermissionSeeder)->run();
        $this->seedUbigeoFixture();

        $this->authorizedUser = $this->authenticateWith([
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',
            'clients.export',
            'clients.manage_credit',
        ]);
    }

    private function authenticateWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $slug) {
            $permission = Permission::where('slug', $slug)->first();
            if ($permission) {
                $user->givePermissionTo($permission);
            }
        }

        $pair = app(JwtService::class)->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    public function test_clients_can_be_listed(): void
    {
        Client::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/clients');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'client_type',
                        'document_type',
                        'document_number',
                        'name',
                        'default_price_type',
                        'credit_limit',
                        'credit_days',
                        'is_system',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_clients_can_be_filtered_by_client_type_and_is_active(): void
    {
        Client::factory()->company()->create(['is_active' => true]);
        Client::factory()->person()->create(['is_active' => true]);
        Client::factory()->company()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/clients?client_type=company&is_active=1');
        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.client_type', 'company');
    }

    public function test_clients_can_be_searched(): void
    {
        Client::factory()->create(['name' => 'Distribuidora Alfa', 'document_number' => '41112233']);
        Client::factory()->create(['name' => 'Comercial Beta', 'document_number' => '42223344']);

        $response = $this->getJson('/api/v1/clients?search=Alfa');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Distribuidora Alfa');
    }

    public function test_existing_clients_without_document_can_still_be_listed_and_retrieved(): void
    {
        $client = new Client;
        $client->forceFill([
            'name' => 'Cliente Legado Sin Documento',
            'description' => 'Migrado del sistema anterior',
            'client_type' => 'person',
            'document_type' => null,
            'document_number' => null,
            'default_price_type' => 'retail',
            'credit_limit' => 0,
            'credit_days' => 0,
            'is_system' => false,
            'is_active' => true,
        ]);
        $client->save();

        $listResponse = $this->getJson('/api/v1/clients');
        $listResponse->assertOk()
            ->assertJsonPath('data.0.name', 'Cliente Legado Sin Documento')
            ->assertJsonPath('data.0.document_number', null);

        $showResponse = $this->getJson("/api/v1/clients/{$client->id}");
        $showResponse->assertOk()
            ->assertJsonPath('data.name', 'Cliente Legado Sin Documento')
            ->assertJsonPath('data.document_type', null);
    }

    public function test_clients_can_be_created_with_dni(): void
    {
        $payload = [
            'client_type' => 'person',
            'document_type' => 'DNI',
            'document_number' => '45678912',
            'name' => 'Juan Pérez López',
            'phone' => '987654321',
            'email' => 'juan.perez@example.com',
            'address' => 'Calle Los Pinos 123',
            'ubigeo' => '150101',
            'default_price_type' => 'retail',
            'description' => 'Cliente recurrente',
        ];

        $response = $this->postJson('/api/v1/clients', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.client_type', 'person')
            ->assertJsonPath('data.document_type', 'DNI')
            ->assertJsonPath('data.document_number', '45678912')
            ->assertJsonPath('data.name', 'Juan Pérez López')
            ->assertJsonPath('data.credit_limit', 0)
            ->assertJsonPath('data.credit_days', 0)
            ->assertJsonPath('data.is_system', false);

        $this->assertDatabaseHas('clients', [
            'document_number' => '45678912',
            'name' => 'Juan Pérez López',
        ]);
    }

    public function test_clients_can_be_created_with_ruc_for_company(): void
    {
        $payload = [
            'client_type' => 'company',
            'document_type' => 'RUC',
            'document_number' => '20601234567',
            'name' => 'inversiones andinas s.a.c.',
            'trade_name' => 'Andinas',
            'representative_name' => 'Carlos Mendoza',
            'representative_document' => '09876543',
            'email' => 'contacto@andinas.pe',
            'default_price_type' => 'wholesale',
        ];

        $response = $this->postJson('/api/v1/clients', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.client_type', 'company')
            ->assertJsonPath('data.document_type', 'RUC')
            ->assertJsonPath('data.name', 'INVERSIONES ANDINAS S.A.C.')
            ->assertJsonPath('data.trade_name', 'Andinas')
            ->assertJsonPath('data.default_price_type', 'wholesale');
    }

    public function test_clients_can_be_created_with_ruc_for_person(): void
    {
        $payload = [
            'client_type' => 'person',
            'document_type' => 'RUC',
            'document_number' => '10456789123',
            'name' => 'Pedro Castillo Negocio',
            'default_price_type' => 'retail',
        ];

        $response = $this->postJson('/api/v1/clients', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.client_type', 'person')
            ->assertJsonPath('data.document_type', 'RUC');
    }

    public function test_clients_creation_validates_document_format(): void
    {
        $invalidDni = [
            'client_type' => 'person',
            'document_type' => 'DNI',
            'document_number' => '1234',
            'name' => 'Test',
            'default_price_type' => 'retail',
        ];

        $this->postJson('/api/v1/clients', $invalidDni)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_number']);

        $invalidRuc = [
            'client_type' => 'company',
            'document_type' => 'RUC',
            'document_number' => '30123456789', // Invalid prefix for Peruvian RUC
            'name' => 'Empresa Falsa S.A.',
            'default_price_type' => 'wholesale',
        ];

        $this->postJson('/api/v1/clients', $invalidRuc)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_number']);
    }

    public function test_clients_creation_requires_ruc_for_company(): void
    {
        $payload = [
            'client_type' => 'company',
            'document_type' => 'DNI',
            'document_number' => '45678912',
            'name' => 'Empresa Con DNI S.A.',
            'default_price_type' => 'wholesale',
        ];

        $this->postJson('/api/v1/clients', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_type']);
    }

    public function test_clients_creation_enforces_unique_document_per_type(): void
    {
        $client = Client::factory()->person()->create([
            'document_type' => DocumentType::Dni,
            'document_number' => '44556677',
        ]);

        $payload = [
            'client_type' => 'person',
            'document_type' => 'DNI',
            'document_number' => '44556677',
            'name' => 'Otro Con Mismo DNI',
            'default_price_type' => 'retail',
        ];

        $this->postJson('/api/v1/clients', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_number']);

        // Soft delete first client, then re-register should succeed
        $client->delete();

        $this->postJson('/api/v1/clients', $payload)
            ->assertCreated();
    }

    public function test_clients_can_be_retrieved_by_id_with_contacts_and_account(): void
    {
        $client = Client::factory()->company()->withCredit(2500, 30)->create();
        ClientContact::create([
            'client_id' => $client->id,
            'name' => 'Contacto Principal',
            'position' => 'Gerente de Compras',
            'phone' => '998877665',
            'email' => 'compras@cliente.pe',
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/clients/{$client->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.credit_limit', 2500)
            ->assertJsonPath('data.credit_days', 30)
            ->assertJsonPath('data.account.debt', 0)
            ->assertJsonPath('data.account.available_credit', 2500)
            ->assertJsonCount(1, 'data.contacts')
            ->assertJsonPath('data.contacts.0.name', 'Contacto Principal')
            ->assertJsonPath('data.contacts.0.is_primary', true);
    }

    public function test_clients_returns_404_when_not_found(): void
    {
        $fakeUuid = (string) Str::uuid();
        $response = $this->getJson("/api/v1/clients/{$fakeUuid}");

        $response->assertNotFound();
    }

    public function test_clients_can_be_updated(): void
    {
        $client = Client::factory()->person()->create([
            'name' => 'Nombre Antiguo',
            'document_type' => DocumentType::Dni,
            'document_number' => '11223344',
        ]);

        $payload = [
            'client_type' => 'person',
            'document_type' => 'DNI',
            'document_number' => '11223344',
            'name' => 'Nombre Modificado',
            'phone' => '999888777',
            'default_price_type' => 'retail',
            'description' => 'Descripción actualizada',
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Nombre Modificado')
            ->assertJsonPath('data.phone', '999888777');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Nombre Modificado',
        ]);
    }

    public function test_clients_update_cannot_set_non_ruc_for_company(): void
    {
        $client = Client::factory()->company()->create([
            'document_type' => DocumentType::Ruc,
            'document_number' => '20112233445',
        ]);

        $payload = [
            'client_type' => 'company',
            'document_type' => 'DNI',
            'document_number' => '11223344',
            'name' => 'Empresa Modificada',
            'default_price_type' => 'wholesale',
        ];

        $this->putJson("/api/v1/clients/{$client->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_type']);
    }

    public function test_clients_update_ignores_credit_fields(): void
    {
        $client = Client::factory()->withCredit(1000, 15)->create([
            'document_type' => DocumentType::Dni,
            'document_number' => '33445566',
        ]);

        $payload = [
            'client_type' => 'person',
            'document_type' => 'DNI',
            'document_number' => '33445566',
            'name' => $client->name,
            'default_price_type' => 'retail',
            'credit_limit' => 99999,
            'credit_days' => 120,
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}", $payload);
        $response->assertOk();

        $client->refresh();
        $this->assertEquals(1000, (float) $client->credit_limit);
        $this->assertSame(15, $client->credit_days);
    }

    public function test_clients_can_be_deleted(): void
    {
        $client = Client::factory()->create();

        $response = $this->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('clients', [
            'id' => $client->id,
        ]);
    }

    public function test_system_client_cannot_be_updated_or_deleted(): void
    {
        $systemClient = ClientSeeder::seedSystemClient();

        $updatePayload = [
            'client_type' => 'person',
            'document_type' => 'OTHER',
            'document_number' => '00000000',
            'name' => 'Intento De Cambio',
            'default_price_type' => 'retail',
        ];

        $this->putJson("/api/v1/clients/{$systemClient->id}", $updatePayload)
            ->assertStatus(422)
            ->assertJsonPath('errors.client.0', 'El cliente genérico no se puede modificar.');

        $this->deleteJson("/api/v1/clients/{$systemClient->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.client.0', 'El cliente genérico no se puede eliminar.');

        $this->putJson("/api/v1/clients/{$systemClient->id}/credit", [
            'credit_limit' => 500,
            'credit_days' => 30,
        ])->assertStatus(422)
            ->assertJsonPath('errors.client.0', 'No se puede modificar el crédito del cliente genérico.');
    }

    public function test_client_options_returns_active_clients_with_system_first(): void
    {
        $systemClient = ClientSeeder::seedSystemClient();
        $clientA = Client::factory()->create(['name' => 'Alfa Corp']);
        $inactive = Client::factory()->create(['name' => 'Inactivo Corp', 'is_active' => false]);

        $response = $this->getJson('/api/v1/clients/options');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'client_type',
                        'document_type',
                        'document_number',
                        'name',
                        'address',
                        'default_price_type',
                        'credit_limit',
                        'is_system',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertTrue($data[0]['is_system']);
        $this->assertSame($systemClient->id, $data[0]['id']);

        $ids = array_column($data, 'id');
        $this->assertContains($clientA->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_client_options_matches_exact_document_first(): void
    {
        ClientSeeder::seedSystemClient();

        $clientExact = Client::factory()->create([
            'name' => 'Zeta Cliente',
            'document_number' => '10203040',
        ]);

        $clientNameMatch = Client::factory()->create([
            'name' => '10203040 Negocios S.A.',
            'document_number' => '20998877665',
        ]);

        $response = $this->getJson('/api/v1/clients/options?search=10203040');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertSame($clientExact->id, $data[0]['id']);
    }

    public function test_client_credit_can_be_updated_with_permission(): void
    {
        $client = Client::factory()->create([
            'credit_limit' => 0,
            'credit_days' => 0,
        ]);

        $response = $this->putJson("/api/v1/clients/{$client->id}/credit", [
            'credit_limit' => 15000.50,
            'credit_days' => 45,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.credit_limit', 15000.5)
            ->assertJsonPath('data.credit_days', 45)
            ->assertJsonPath('data.account.available_credit', 15000.5);

        $client->refresh();
        $this->assertEquals(15000.50, (float) $client->credit_limit);
        $this->assertSame(45, $client->credit_days);
    }

    public function test_client_credit_cannot_be_updated_without_permission(): void
    {
        $client = Client::factory()->create();

        // User without clients.manage_credit
        $this->authenticateWith(['clients.view', 'clients.update']);

        $this->putJson("/api/v1/clients/{$client->id}/credit", [
            'credit_limit' => 5000,
            'credit_days' => 30,
        ])->assertForbidden();
    }

    public function test_client_contacts_can_be_synced(): void
    {
        $client = Client::factory()->create();

        $payload = [
            'contacts' => [
                [
                    'name' => 'Ana Gómez',
                    'position' => 'Jefa de Compras',
                    'phone' => '987111222',
                    'email' => 'ana@example.pe',
                    'is_primary' => true,
                ],
                [
                    'name' => 'Luis Paredes',
                    'position' => 'Asistente',
                    'phone' => '987333444',
                    'email' => 'luis@example.pe',
                    'is_primary' => false,
                ],
            ],
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}/contacts", $payload);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Ana Gómez')
            ->assertJsonPath('data.0.is_primary', true);

        $this->assertDatabaseCount('client_contacts', 2);

        $contactId = $response->json('data.0.id');

        // Update contacts: keep first, remove second, add third
        $updatedPayload = [
            'contacts' => [
                [
                    'id' => $contactId,
                    'name' => 'Ana Gómez Actualizada',
                    'position' => 'Gerente',
                    'phone' => '987111222',
                    'email' => 'ana.gomez@example.pe',
                    'is_primary' => false,
                ],
                [
                    'name' => 'Nuevo Contacto',
                    'position' => 'Tesorero',
                    'is_primary' => true,
                ],
            ],
        ];

        $updateResponse = $this->putJson("/api/v1/clients/{$client->id}/contacts", $updatedPayload);
        $updateResponse->assertOk()->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('client_contacts', [
            'id' => $contactId,
            'name' => 'Ana Gómez Actualizada',
            'is_primary' => false,
        ]);

        $this->assertDatabaseHas('client_contacts', [
            'client_id' => $client->id,
            'name' => 'Nuevo Contacto',
            'is_primary' => true,
        ]);

        $this->assertDatabaseMissing('client_contacts', [
            'name' => 'Luis Paredes',
        ]);
    }

    public function test_client_contacts_sync_validates_at_most_one_primary(): void
    {
        $client = Client::factory()->create();

        $payload = [
            'contacts' => [
                [
                    'name' => 'Contacto 1',
                    'is_primary' => true,
                ],
                [
                    'name' => 'Contacto 2',
                    'is_primary' => true,
                ],
            ],
        ];

        $this->putJson("/api/v1/clients/{$client->id}/contacts", $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.contacts.0', 'Solo puede haber un contacto principal');
    }

    public function test_user_without_permission_cannot_perform_clients_actions(): void
    {
        $client = Client::factory()->create();

        $this->authenticateWith([]);

        $this->getJson('/api/v1/clients')->assertForbidden();
        $this->getJson('/api/v1/clients/options')->assertForbidden();
        $this->postJson('/api/v1/clients', ['name' => 'Test'])->assertForbidden();
        $this->getJson("/api/v1/clients/{$client->id}")->assertForbidden();
        $this->putJson("/api/v1/clients/{$client->id}", ['name' => 'Updated'])->assertForbidden();
        $this->deleteJson("/api/v1/clients/{$client->id}")->assertForbidden();
        $this->putJson("/api/v1/clients/{$client->id}/credit", ['credit_limit' => 10, 'credit_days' => 10])->assertForbidden();
        $this->putJson("/api/v1/clients/{$client->id}/contacts", ['contacts' => []])->assertForbidden();
    }
}
