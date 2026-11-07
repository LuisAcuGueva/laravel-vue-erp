<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Configuration;

use App\Modules\Configuration\Enums\ParameterValueType;
use App\Modules\Configuration\Exceptions\ParameterNotEditableException;
use App\Modules\Configuration\Models\SystemParameter;
use App\Modules\Configuration\Services\SystemParameterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SystemParameterServiceTest extends TestCase
{
    use RefreshDatabase;

    private SystemParameterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SystemParameterService::class);
        Cache::forget(SystemParameterService::CACHE_KEY);
    }

    public function test_get_casts_types_correctly(): void
    {
        SystemParameter::factory()->create([
            'group_key' => 'test',
            'item_key' => 'int_param',
            'value_type' => ParameterValueType::Integer,
            'value' => 42,
            'is_active' => true,
        ]);

        SystemParameter::factory()->create([
            'group_key' => 'test',
            'item_key' => 'dec_param',
            'value_type' => ParameterValueType::Decimal,
            'value' => 12.3456,
            'is_active' => true,
        ]);

        SystemParameter::factory()->create([
            'group_key' => 'test',
            'item_key' => 'bool_param',
            'value_type' => ParameterValueType::Boolean,
            'value' => true,
            'is_active' => true,
        ]);

        SystemParameter::factory()->create([
            'group_key' => 'test',
            'item_key' => 'str_param',
            'value_type' => ParameterValueType::String,
            'value' => 'hola mundo',
            'is_active' => true,
        ]);

        SystemParameter::factory()->create([
            'group_key' => 'test',
            'item_key' => 'json_param',
            'value_type' => ParameterValueType::Json,
            'value' => ['foo' => 'bar'],
            'is_active' => true,
        ]);

        $this->assertSame(42, $this->service->get('test.int_param'));
        $this->assertSame(12.3456, $this->service->get('test.dec_param'));
        $this->assertTrue($this->service->get('test.bool_param'));
        $this->assertSame('hola mundo', $this->service->get('test.str_param'));
        $this->assertSame(['foo' => 'bar'], $this->service->get('test.json_param'));
    }

    public function test_get_returns_default_when_not_found_or_inactive(): void
    {
        SystemParameter::factory()->create([
            'group_key' => 'test',
            'item_key' => 'inactive_param',
            'value_type' => ParameterValueType::String,
            'value' => 'oculto',
            'is_active' => false,
        ]);

        $this->assertSame('default_val', $this->service->get('test.non_existent', 'default_val'));
        $this->assertSame('default_val', $this->service->get('test.inactive_param', 'default_val'));
    }

    public function test_typed_shortcuts_work_as_expected(): void
    {
        SystemParameter::factory()->create([
            'group_key' => 'shop',
            'item_key' => 'max_discount',
            'value_type' => ParameterValueType::Decimal,
            'value' => 15.5,
            'is_active' => true,
        ]);

        SystemParameter::factory()->create([
            'group_key' => 'shop',
            'item_key' => 'days_limit',
            'value_type' => ParameterValueType::Integer,
            'value' => 30,
            'is_active' => true,
        ]);

        SystemParameter::factory()->create([
            'group_key' => 'shop',
            'item_key' => 'allow_credit',
            'value_type' => ParameterValueType::Boolean,
            'value' => false,
            'is_active' => true,
        ]);

        $this->assertSame(15.5, $this->service->decimal('shop.max_discount'));
        $this->assertSame(30, $this->service->integer('shop.days_limit'));
        $this->assertFalse($this->service->boolean('shop.allow_credit'));

        // Fallbacks
        $this->assertSame(5.0, $this->service->decimal('missing.key', 5.0));
        $this->assertSame(10, $this->service->integer('missing.key', 10));
        $this->assertTrue($this->service->boolean('missing.key', true));
    }

    public function test_update_invalidates_cache(): void
    {
        /** @var SystemParameter $param */
        $param = SystemParameter::factory()->create([
            'group_key' => 'cache',
            'item_key' => 'val',
            'value_type' => ParameterValueType::Integer,
            'value' => 100,
            'is_editable' => true,
            'is_active' => true,
        ]);

        // Cargar en caché
        $this->assertSame(100, $this->service->get('cache.val'));
        $this->assertTrue(Cache::has(SystemParameterService::CACHE_KEY));

        // Actualizar mediante el servicio
        $this->service->update($param, 200);

        // La caché debió borrarse y la siguiente lectura debe traer el nuevo valor
        $this->assertSame(200, $this->service->get('cache.val'));
    }

    public function test_update_throws_exception_when_parameter_is_not_editable(): void
    {
        /** @var SystemParameter $param */
        $param = SystemParameter::factory()->notEditable()->create([
            'group_key' => 'system',
            'item_key' => 'locked',
            'value_type' => ParameterValueType::String,
            'value' => 'original',
        ]);

        $this->expectException(ParameterNotEditableException::class);

        $this->service->update($param, 'nuevo');
    }
}
