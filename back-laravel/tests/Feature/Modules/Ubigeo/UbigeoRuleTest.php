<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ubigeo;

use App\Modules\Ubigeo\Models\Ubigeo;
use App\Modules\Ubigeo\Rules\ValidUbigeo;
use App\Modules\Ubigeo\Services\UbigeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class UbigeoRuleTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedUbigeoFixture();
    }

    /**
     * @return list<string>
     */
    private function errorsFor(mixed $value, ?string $current = null): array
    {
        return Validator::make(['ubigeo' => $value], ['ubigeo' => ['nullable', new ValidUbigeo($current)]])
            ->errors()
            ->get('ubigeo');
    }

    public function test_null_and_empty_pass(): void
    {
        $this->assertSame([], $this->errorsFor(null));
        $this->assertSame([], $this->errorsFor(''));
    }

    public function test_invalid_values_fail_with_spanish_message(): void
    {
        foreach (['15012', 'abc123', 150122, '999999'] as $value) {
            $this->assertSame(['El ubigeo seleccionado no es válido.'], $this->errorsFor($value), 'Valor: '.var_export($value, true));
        }
    }

    public function test_active_code_passes(): void
    {
        $this->assertSame([], $this->errorsFor('150122'));
    }

    public function test_inactive_code_fails_unless_it_is_the_current_value(): void
    {
        Ubigeo::query()->where('code', '150130')->update(['is_active' => false]);
        app(UbigeoService::class)->flushCache();

        $this->assertNotEmpty($this->errorsFor('150130'));
        $this->assertSame([], $this->errorsFor('150130', current: '150130'));
    }
}
