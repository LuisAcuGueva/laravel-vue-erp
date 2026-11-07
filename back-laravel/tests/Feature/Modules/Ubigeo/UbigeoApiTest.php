<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ubigeo;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Ubigeo\Models\Ubigeo;
use App\Modules\Ubigeo\Services\UbigeoService;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class UbigeoApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedUbigeoFixture();
    }

    private function authenticate(): void
    {
        $pair = app(JwtService::class)->createTokenPair(User::factory()->create());
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);
    }

    public function test_catalog_requires_authentication(): void
    {
        $this->getJson('/api/v1/ubigeo')->assertUnauthorized();
    }

    public function test_catalog_returns_envelope_sorted_by_code_with_etag(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/v1/ubigeo');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.code', '010101')
            ->assertJsonPath('data.9.code', '150501')
            ->assertJsonPath('data.6.district', 'MIRAFLORES')
            ->assertJsonStructure(['data' => [['code', 'department', 'province', 'district', 'is_active']], 'meta' => ['version']])
            ->assertJsonMissingPath('data.0.id');

        $version = app(UbigeoService::class)->version();
        $response->assertJsonPath('meta.version', $version);
        $this->assertSame('"'.$version.'"', $response->headers->get('ETag'));
        $this->assertStringContainsString('max-age=86400', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_matching_if_none_match_returns_304_without_body(): void
    {
        $this->authenticate();

        $etag = $this->getJson('/api/v1/ubigeo')->headers->get('ETag');

        $response = $this->getJson('/api/v1/ubigeo', ['If-None-Match' => $etag]);

        $response->assertStatus(304);
        $this->assertSame('', $response->getContent());
    }

    public function test_etag_changes_after_catalog_changes_and_cache_flush(): void
    {
        $this->authenticate();
        $before = $this->getJson('/api/v1/ubigeo')->headers->get('ETag');

        Ubigeo::query()->where('code', '150122')->update(['district' => 'MIRAFLORES 2']);
        app(UbigeoService::class)->flushCache();

        $after = $this->getJson('/api/v1/ubigeo')->headers->get('ETag');

        $this->assertNotSame($before, $after);
    }

    public function test_service_describe_and_single_query_catalog(): void
    {
        $service = app(UbigeoService::class);
        $service->flushCache();

        $this->assertSame('LIMA, LIMA, MIRAFLORES', $service->describe('150122'));
        $this->assertSame('—', $service->describe(null));
        $this->assertSame('—', $service->describe('999999'));

        $service->flushCache();
        DB::enableQueryLog();
        $service->catalog();
        $service->catalog();
        $queries = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'ubigeos'));
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
    }
}
