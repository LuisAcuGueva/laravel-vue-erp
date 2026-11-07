<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Configuration;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Configuration\Models\CompanySetting;
use App\Modules\Configuration\Services\CompanySettingService;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class CompanySettingsApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        $this->seedUbigeoFixture();
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

    public function test_company_settings_can_be_retrieved_and_creates_default_record_if_not_exists(): void
    {
        $this->authenticateWithPermissions(['configuration.view']);

        $this->assertDatabaseEmpty('company_settings');

        $response = $this->getJson('/api/v1/configuration/company');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'legal_name',
                    'trade_name',
                    'legal_representative',
                    'ruc',
                    'address',
                    'ubigeo',
                    'phone',
                    'email',
                    'website',
                    'logo_url',
                    'default_currency_code',
                    'default_tax_rate',
                    'prices_include_tax',
                    'terms_conditions',
                    'warranty_policy',
                    'social_links',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.ruc', null)
            ->assertJsonMissingPath('data.tax_id')
            ->assertJsonMissingPath('data.tax_id_type')
            ->assertJsonPath('data.default_currency_code', 'PEN')
            ->assertJsonPath('data.default_tax_rate', 18);

        $this->assertDatabaseCount('company_settings', 1);
    }

    public function test_company_settings_current_always_returns_the_same_single_record(): void
    {
        $service = app(CompanySettingService::class);

        $first = $service->current();
        $second = $service->current();

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('company_settings', 1);
    }

    public function test_company_settings_table_rejects_a_second_record(): void
    {
        app(CompanySettingService::class)->current();

        $this->expectException(UniqueConstraintViolationException::class);

        CompanySetting::query()->create([
            'legal_name' => 'Empresa duplicada',
            'ruc' => '20123456786',
        ]);
    }

    public function test_company_settings_show_requires_permission(): void
    {
        $this->authenticateWithoutPermissions();

        $response = $this->getJson('/api/v1/configuration/company');

        $response->assertForbidden();
    }

    public function test_company_settings_show_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/configuration/company');

        $response->assertUnauthorized();
    }

    public function test_company_settings_can_be_updated_with_valid_data(): void
    {
        $this->authenticateWithPermissions(['configuration.view', 'configuration.update']);

        $payload = [
            'legal_name' => 'Empresa de Prueba S.A.C.',
            'trade_name' => 'Empresa Test',
            'legal_representative' => '  Juan Pérez Gómez  ',
            'ruc' => '20123456786',
            'address' => 'Av. Principal 123',
            'ubigeo' => '150122',
            'phone' => '+51999888777',
            'email' => 'contacto@empresa.com',
            'website' => 'https://empresa.com',
            'default_tax_rate' => 18.00,
            'prices_include_tax' => true,
            'terms_conditions' => 'Términos y condiciones de prueba',
            'warranty_policy' => 'Política de garantía de 1 año',
            'social_links' => [
                'facebook' => 'https://facebook.com/empresa',
                'tiktok' => 'https://tiktok.com/@empresa',
                'youtube' => 'https://youtube.com/@empresa',
            ],
        ];

        $response = $this->putJson('/api/v1/configuration/company', $payload);

        $response->assertOk()
            ->assertJsonPath('data.legal_name', 'Empresa de Prueba S.A.C.')
            ->assertJsonPath('data.ruc', '20123456786')
            ->assertJsonPath('data.legal_representative', 'Juan Pérez Gómez')
            ->assertJsonPath('data.ubigeo', '150122')
            ->assertJsonMissingPath('data.department')
            ->assertJsonMissingPath('data.province')
            ->assertJsonMissingPath('data.district')
            ->assertJsonPath('data.social_links.tiktok', 'https://tiktok.com/@empresa')
            ->assertJsonPath('data.social_links.youtube', 'https://youtube.com/@empresa');

        $this->assertDatabaseHas('company_settings', [
            'legal_name' => 'Empresa de Prueba S.A.C.',
            'ruc' => '20123456786',
            'legal_representative' => 'Juan Pérez Gómez',
            'ubigeo' => '150122',
        ]);
    }

    public function test_company_settings_update_rejects_default_currency_code_with_prohibited_error(): void
    {
        $this->authenticateWithPermissions(['configuration.update']);

        $response = $this->putJson('/api/v1/configuration/company', [
            'legal_name' => 'Empresa Moneda',
            'ruc' => '20123456786',
            'default_currency_code' => 'USD',
            'default_tax_rate' => 18.00,
            'prices_include_tax' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.default_currency_code.0', 'La moneda base se gestiona en Finanzas › Monedas');
    }

    public function test_company_settings_update_rejects_unknown_ubigeo(): void
    {
        $this->authenticateWithPermissions(['configuration.update']);

        $response = $this->putJson('/api/v1/configuration/company', [
            'legal_name' => 'Empresa Ubigeo',
            'ruc' => '20123456786',
            'ubigeo' => '999999',
            'default_tax_rate' => 18.00,
            'prices_include_tax' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.ubigeo.0', 'El ubigeo seleccionado no es válido.');
    }

    public function test_company_settings_update_fails_with_invalid_ruc(): void
    {
        $this->authenticateWithPermissions(['configuration.update']);

        $payload = [
            'legal_name' => 'Empresa Invalida',
            'ruc' => '12345', // Inválido: debe tener 11 dígitos
            'default_tax_rate' => 18.00,
            'prices_include_tax' => true,
        ];

        $response = $this->putJson('/api/v1/configuration/company', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ruc']);
    }

    public function test_company_settings_update_rejects_ruc_with_wrong_check_digit_or_prefix(): void
    {
        $this->authenticateWithPermissions(['configuration.update']);

        foreach (['20123456789', '30123456781', '2012345678A'] as $ruc) {
            $this->putJson('/api/v1/configuration/company', [
                'legal_name' => 'Empresa RUC',
                'ruc' => $ruc,
                'default_tax_rate' => 18.00,
                'prices_include_tax' => true,
            ])->assertUnprocessable()
                ->assertJsonPath('errors.ruc.0', 'El RUC no es válido: debe tener 11 dígitos y un dígito verificador correcto.');
        }

        $this->putJson('/api/v1/configuration/company', [
            'legal_name' => 'SUNAT',
            'ruc' => '20131312955',
            'default_tax_rate' => 18.00,
            'prices_include_tax' => true,
        ])->assertOk()
            ->assertJsonPath('data.ruc', '20131312955');
    }

    public function test_company_settings_update_requires_permission(): void
    {
        $this->authenticateWithoutPermissions();

        $response = $this->putJson('/api/v1/configuration/company', [
            'legal_name' => 'Sin permiso',
            'ruc' => '20123456786',
            'default_tax_rate' => 18.00,
            'prices_include_tax' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_company_logo_can_be_uploaded_and_replaces_previous_logo(): void
    {
        Storage::fake('public');
        $this->authenticateWithPermissions(['configuration.update']);

        $logo1 = UploadedFile::fake()->image('first_logo.png', 200, 200);

        $response1 = $this->postJson('/api/v1/configuration/company/logo', [
            'logo' => $logo1,
        ]);

        $response1->assertOk()
            ->assertJsonStructure(['data' => ['logo_url']]);

        $company = CompanySetting::firstOrFail();
        $firstPath = $company->logo_path;
        $this->assertNotNull($firstPath);
        Storage::disk('public')->assertExists($firstPath);

        // Subir segundo logo y verificar que el primero se elimina
        $logo2 = UploadedFile::fake()->image('second_logo.jpg', 300, 300);

        $response2 = $this->postJson('/api/v1/configuration/company/logo', [
            'logo' => $logo2,
        ]);

        $response2->assertOk();

        $company->refresh();
        $secondPath = $company->logo_path;
        $this->assertNotNull($secondPath);
        $this->assertNotEquals($firstPath, $secondPath);

        Storage::disk('public')->assertExists($secondPath);
        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_company_logo_upload_rejects_invalid_file_type(): void
    {
        Storage::fake('public');
        $this->authenticateWithPermissions(['configuration.update']);

        $txtFile = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $response = $this->postJson('/api/v1/configuration/company/logo', [
            'logo' => $txtFile,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_company_summary_is_accessible_to_any_authenticated_user_without_sensitive_data(): void
    {
        $this->authenticateWithoutPermissions();

        // Modificar empresa para tener datos
        CompanySetting::query()->create([
            'legal_name' => 'Empresa Secreta S.A.C.',
            'trade_name' => 'Nombre Comercial Público',
            'ruc' => '20999888772',
            'terms_conditions' => 'Condición super confidencial',
            'default_currency_code' => 'USD',
            'default_tax_rate' => 10.00,
            'prices_include_tax' => false,
        ]);

        $response = $this->getJson('/api/v1/configuration/company/summary');

        $response->assertOk()
            ->assertJsonPath('data.trade_name', 'Nombre Comercial Público')
            ->assertJsonPath('data.legal_name', 'Empresa Secreta S.A.C.')
            ->assertJsonPath('data.default_currency_code', 'USD')
            ->assertJsonPath('data.default_tax_rate', 10)
            ->assertJsonPath('data.prices_include_tax', false)
            ->assertJsonMissing([
                'ruc',
                'terms_conditions',
                'warranty_policy',
            ]);
    }
}
