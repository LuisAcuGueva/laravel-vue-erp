<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Console\VerifyStockCommand;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\InventoryCount;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\ItemPrice;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseNote;
use App\Modules\Inventory\Models\WarehouseReason;
use App\Modules\Inventory\Services\BrandService;
use App\Modules\Inventory\Services\CategoryService;
use App\Modules\Inventory\Services\KardexQueryService;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Inventory\Services\StockQueryService;
use App\Modules\Inventory\Services\UnitService;
use App\Modules\Inventory\Services\WarehouseNoteApprovalService;
use App\Modules\Inventory\Services\WarehouseNoteService;
use App\Modules\Inventory\Services\WarehouseReasonService;
use App\Modules\Inventory\Services\WarehouseService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CategoryService::class);
        $this->app->singleton(BrandService::class);
        $this->app->singleton(UnitService::class);
        $this->app->singleton(WarehouseService::class);
        $this->app->singleton(WarehouseReasonService::class);
        $this->app->singleton(StockLedgerService::class);
        $this->app->singleton(StockQueryService::class);
        $this->app->singleton(KardexQueryService::class);
        $this->app->singleton(WarehouseNoteApprovalService::class);
        $this->app->singleton(WarehouseNoteService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyStockCommand::class,
            ]);
        }

        Relation::morphMap([
            'warehouse' => Warehouse::class,
            'catalog_item' => CatalogItem::class,
            'warehouse_note' => WarehouseNote::class,
            'inventory_count' => InventoryCount::class,
            'brand' => Brand::class,
            'category' => Category::class,
            'item_presentation' => ItemPresentation::class,
            'item_price' => ItemPrice::class,
            'unit' => Unit::class,
            'unit_category' => UnitCategory::class,
            'warehouse_reason' => WarehouseReason::class,
        ], merge: true);
    }
}
