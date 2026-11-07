<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Configuration;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Configuration\Enums\ParameterValueType;
use App\Modules\Configuration\Models\SystemParameter;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemParametersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new SystemParameterSeeder)->run();
    }

    private function authenticateWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $slug) {
            $perm = Permission::where('slug', $slug)->first() ?? Permission::create([
                'slug' => $slug,
                'name' => $slug,
                'module' => explode('.', $slug)[0],
            ]);
            $user->givePermissionTo($perm);
        }

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    private function authenticateWithoutPermissions(): User
    {
        $user = User::factory()->create();
        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    public function test_parameters_can_be_listed_by_authorized_user(): void
    {
        $this->authenticateWithPermissions(['configuration.view']);

        $response = $this->getJson('/api/v1/configuration/parameters');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'group_key',
                        'item_key',
                        'key',
                        'label',
                        'description',
                        'value_type',
                        'value',
                        'is_editable',
                        'is_active',
                    ],
                ],
            ]);

        $this->assertCount(SystemParameter::count(), $response->json('data'));
    }

    public function test_parameters_can_be_filtered_by_group(): void
    {
        $this->authenticateWithPermissions(['configuration.view']);

        $response = $this->getJson('/api/v1/configuration/parameters?group=sales');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        foreach ($data as $item) {
            $this->assertEquals('sales', $item['group_key']);
        }
    }

    public function test_parameters_listing_requires_permission(): void
    {
        $this->authenticateWithoutPermissions();

        $response = $this->getJson('/api/v1/configuration/parameters');

        $response->assertForbidden();
    }

    public function test_parameter_decimal_can_be_updated(): void
    {
        $this->authenticateWithPermissions(['configuration.update']);

        /** @var SystemParameter $param */
        $param = SystemParameter::where('group_key', 'sales')
            ->where('item_key', 'max_line_discount_percent')
            ->firstOrFail();

        $response = $this->putJson("/api/v1/configuration/parameters/{$param->id}", [
            'value' => 25.50,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.value', 25.5);

        $param->refresh();
        $this->assertEquals(25.5, $param->value);
    }

    public function test_parameter_update_fails_with_invalid_type(): void
    {
        $this->authenticateWithPermissions(['configuration.update']);

        /** @var SystemParameter $param */
        $param = SystemParameter::where('group_key', 'sales')
            ->where('item_key', 'max_line_discount_percent')
            ->firstOrFail();

        // Enviar un string no numérico a un decimal
        $response = $this->putJson("/api/v1/configuration/parameters/{$param->id}", [
            'value' => 'no-es-numero',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    }

    public function test_parameter_update_fails_when_not_editable(): void
    {
        $this->authenticateWithPermissions(['configuration.update']);

        /** @var SystemParameter $param */
        $param = SystemParameter::factory()->notEditable()->create([
            'value_type' => ParameterValueType::String,
            'value' => 'fijo',
        ]);

        $response = $this->putJson("/api/v1/configuration/parameters/{$param->id}", [
            'value' => 'nuevo-valor',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'El parámetro no es editable.')
            ->assertJsonPath('errors.value.0', 'El parámetro no es editable.');
    }

    public function test_parameter_update_returns_404_when_not_found(): void
    {
        $this->authenticateWithPermissions(['configuration.update']);

        $fakeUuid = (string) Str::uuid();

        $response = $this->putJson("/api/v1/configuration/parameters/{$fakeUuid}", [
            'value' => 'algo',
        ]);

        $response->assertNotFound();
    }

    public function test_parameter_update_returns_404_when_id_is_not_uuid(): void
    {
        $this->authenticateWithPermissions(['configuration.update']);

        $response = $this->putJson('/api/v1/configuration/parameters/id-no-uuid', [
            'value' => 'algo',
        ]);

        $response->assertNotFound();
    }

    public function test_parameter_update_requires_permission(): void
    {
        $this->authenticateWithoutPermissions();

        /** @var SystemParameter $param */
        $param = SystemParameter::firstOrFail();

        $response = $this->putJson("/api/v1/configuration/parameters/{$param->id}", [
            'value' => 20,
        ]);

        $response->assertForbidden();
    }
}
