<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Inventory\Enums\SystemReason;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseReason;
use App\Modules\Maintenance\Models\Branch;
use Database\Seeders\BranchSeeder;
use Database\Seeders\InventoryMastersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMastersSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new BranchSeeder)->run();
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(InventoryMastersSeeder::class);
        $counts = $this->counts();

        $this->seed(InventoryMastersSeeder::class);

        $this->assertSame($counts, $this->counts());
        $this->assertGreaterThan(0, min($counts));
    }

    public function test_each_unit_category_has_exactly_one_base_unit_with_factor_one(): void
    {
        $this->seed(InventoryMastersSeeder::class);

        UnitCategory::where('is_packaging', false)->get()->each(function (UnitCategory $category): void {
            $bases = Unit::where('unit_category_id', $category->id)->where('is_base', true)->get();

            $this->assertCount(1, $bases, "La categoría {$category->name} debe tener una unidad base.");
            $this->assertEquals(1, (float) $bases->first()->conversion_factor);
        });
    }

    public function test_packaging_category_has_no_base_unit(): void
    {
        $this->seed(InventoryMastersSeeder::class);

        $packaging = UnitCategory::where('is_packaging', true)->firstOrFail();

        $this->assertTrue($packaging->units()->exists());
        $this->assertFalse($packaging->units()->where('is_base', true)->exists());
    }

    public function test_categories_do_not_exceed_three_levels_and_depth_matches_parent(): void
    {
        $this->seed(InventoryMastersSeeder::class);

        $this->assertSame(0, Category::where('depth', '>', 2)->count());

        Category::whereNotNull('parent_id')->with('parent')->get()->each(function (Category $category): void {
            $this->assertSame($category->parent->depth + 1, $category->depth);
            $this->assertStringStartsWith($category->parent->code.'-', $category->code);
        });
    }

    public function test_system_reasons_are_synced_from_enum(): void
    {
        $this->seed(InventoryMastersSeeder::class);

        foreach (SystemReason::cases() as $case) {
            $reason = WarehouseReason::where('code', $case->value)->firstOrFail();
            $this->assertTrue($reason->is_system);
            $this->assertFalse($reason->requires_approval);
        }
        $this->assertTrue(WarehouseReason::where('is_system', false)->exists());
    }

    public function test_every_active_branch_gets_one_default_warehouse(): void
    {
        Branch::factory()->create(['is_main' => false, 'is_active' => true]);

        $this->seed(InventoryMastersSeeder::class);

        Branch::where('is_active', true)->get()->each(function (Branch $branch): void {
            $this->assertSame(1, Warehouse::where('branch_id', $branch->id)->where('is_default', true)->count());
        });
        $this->assertSame(4, Warehouse::where('branch_id', Branch::where('is_main', true)->value('id'))->count());
    }

    public function test_reseeding_keeps_user_changes_to_editable_masters(): void
    {
        $this->seed(InventoryMastersSeeder::class);
        Brand::where('name', 'Genérico')->update(['is_active' => false]);
        WarehouseReason::where('code', 'SHRINKAGE')->update(['requires_approval' => false]);

        $this->seed(InventoryMastersSeeder::class);

        $this->assertFalse(Brand::where('name', 'Genérico')->value('is_active'));
        $this->assertFalse(WarehouseReason::where('code', 'SHRINKAGE')->value('requires_approval'));
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'unit_categories' => UnitCategory::count(),
            'units' => Unit::count(),
            'warehouse_reasons' => WarehouseReason::count(),
            'categories' => Category::count(),
            'brands' => Brand::count(),
            'warehouses' => Warehouse::count(),
        ];
    }
}
