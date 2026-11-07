<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Sales;

use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Sales\Exceptions\DiscountLimitExceededException;
use App\Modules\Sales\Services\DiscountPolicyService;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DiscountPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    private DiscountPolicyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(SystemParameterService::CACHE_KEY);
        $this->seed(SystemParameterSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->service = app(DiscountPolicyService::class);
    }

    public function test_calculate_effective_discount_with_percent_only(): void
    {
        $effective = $this->service->calculateEffectiveDiscount(100.0, 100.0, 10.0);
        $this->assertEquals(10.0, $effective);
    }

    public function test_calculate_effective_discount_with_reduced_unit_price(): void
    {
        // Precio lista 100, precio unitario 90, sin descuento explícito => 10%
        $effective = $this->service->calculateEffectiveDiscount(100.0, 90.0, 0.0);
        $this->assertEquals(10.0, $effective);
    }

    public function test_calculate_effective_discount_with_reduced_price_and_percent(): void
    {
        // Precio lista 100, unitario 90, descuento 10% => neto 81 => 19%
        $effective = $this->service->calculateEffectiveDiscount(100.0, 90.0, 10.0);
        $this->assertEquals(19.0, $effective);
    }

    public function test_calculate_effective_discount_returns_zero_for_null_or_zero_list_price(): void
    {
        $this->assertEquals(0.0, $this->service->calculateEffectiveDiscount(null, 50.0, 20.0));
        $this->assertEquals(0.0, $this->service->calculateEffectiveDiscount(0.0, 50.0, 20.0));
    }

    public function test_calculate_effective_discount_returns_zero_for_higher_unit_price(): void
    {
        $this->assertEquals(0.0, $this->service->calculateEffectiveDiscount(100.0, 120.0, 0.0));
    }

    public function test_validate_returns_null_when_discounts_are_within_limits(): void
    {
        $user = User::factory()->create();

        $lines = [
            [
                'list_price' => 100.0,
                'unit_price' => 100.0,
                'discount_percent' => 5.0, // 5% <= 10%
            ],
            [
                'list_price' => null, // texto libre
                'unit_price' => 50.0,
                'discount_percent' => 25.0,
            ],
        ];

        $result = $this->service->validate($lines, 5.0, $user);
        $this->assertNull($result);
    }

    public function test_validate_throws_exception_when_line_discount_exceeds_limit_without_permission(): void
    {
        $user = User::factory()->create();

        $lines = [
            [
                'list_price' => 100.0,
                'unit_price' => 100.0,
                'discount_percent' => 15.0, // 15% > 10%
            ],
        ];

        $this->expectException(DiscountLimitExceededException::class);

        try {
            $this->service->validate($lines, 0.0, $user);
        } catch (DiscountLimitExceededException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('items.0.discount_percent', $errors);
            $this->assertStringContainsString('15.00 %', $errors['items.0.discount_percent'][0]);
            throw $e;
        }
    }

    public function test_validate_returns_user_when_line_discount_exceeds_limit_with_permission(): void
    {
        $user = User::factory()->create();
        $permission = Permission::where('slug', 'sales.discount_override')->firstOrFail();
        $user->givePermissionTo($permission);

        $lines = [
            [
                'list_price' => 100.0,
                'unit_price' => 100.0,
                'discount_percent' => 15.0,
            ],
        ];

        $result = $this->service->validate($lines, 0.0, $user);
        $this->assertSame($user->id, $result?->id);
    }

    public function test_validate_throws_exception_when_global_discount_exceeds_limit_without_permission(): void
    {
        $user = User::factory()->create();

        $lines = [
            [
                'list_price' => 100.0,
                'unit_price' => 100.0,
                'discount_percent' => 5.0,
            ],
        ];

        $this->expectException(DiscountLimitExceededException::class);

        try {
            $this->service->validate($lines, 15.0, $user);
        } catch (DiscountLimitExceededException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('global_discount_percent', $errors);
            $this->assertStringContainsString('15.00 %', $errors['global_discount_percent'][0]);
            throw $e;
        }
    }

    public function test_validate_returns_user_when_global_discount_exceeds_limit_with_permission(): void
    {
        $user = User::factory()->create();
        $permission = Permission::where('slug', 'sales.discount_override')->firstOrFail();
        $user->givePermissionTo($permission);

        $lines = [
            [
                'list_price' => 100.0,
                'unit_price' => 100.0,
                'discount_percent' => 5.0,
            ],
        ];

        $result = $this->service->validate($lines, 15.0, $user);
        $this->assertSame($user->id, $result?->id);
    }
}
