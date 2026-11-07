<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Services\ExchangeRateService;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Area;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

/**
 * Datos de prueba comunes de requerimientos y órdenes de compra: dos sedes, almacenes, moneda base y dólar con tipo de cambio,
 * un producto y un servicio del catálogo, un proveedor y autenticación por permisos.
 */
trait SetsUpPurchasing
{
    protected Branch $branch;

    protected Branch $otherBranch;

    protected Warehouse $warehouse;

    protected Warehouse $otherWarehouse;

    protected Area $area;

    protected Currency $pen;

    protected Currency $usd;

    protected CatalogItem $product;

    protected ItemPresentation $productPresentation;

    protected CatalogItem $service;

    protected ItemPresentation $servicePresentation;

    protected Provider $provider;

    protected function setUpPurchasing(): void
    {
        (new RolePermissionSeeder)->run();

        Currency::clearBaseCache();
        $this->pen = Currency::factory()->base()->create(['code' => 'PEN', 'symbol' => 'S/', 'decimals' => 2]);
        $this->usd = Currency::factory()->create(['code' => 'USD', 'symbol' => '$', 'decimals' => 2]);
        app(ExchangeRateService::class)->upsert($this->usd->id, Carbon::today()->toDateString(), 3.70, 3.80);

        $this->branch = Branch::factory()->create(['code' => 'SEDE-A', 'is_active' => true]);
        $this->otherBranch = Branch::factory()->create(['code' => 'SEDE-B', 'is_active' => true]);
        $this->warehouse = Warehouse::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->otherWarehouse = Warehouse::factory()->create(['branch_id' => $this->otherBranch->id, 'is_active' => true]);
        $this->area = Area::factory()->create();

        $this->product = CatalogItem::factory()->create(['name' => 'Cemento Sol', 'is_purchasable' => true]);
        $this->productPresentation = ItemPresentation::factory()->create([
            'catalog_item_id' => $this->product->id,
            'unit_id' => $this->product->base_unit_id,
            'name' => 'Bolsa 42.5 kg',
            'is_base' => true,
            'is_default' => true,
        ]);

        $this->service = CatalogItem::factory()->service()->create(['name' => 'Flete', 'is_purchasable' => true]);
        $this->servicePresentation = ItemPresentation::factory()->create([
            'catalog_item_id' => $this->service->id,
            'unit_id' => $this->service->base_unit_id,
            'name' => 'Servicio',
            'is_base' => true,
            'is_default' => true,
        ]);

        $this->provider = Provider::factory()->create(['business_name' => 'Distribuidora Andina S.A.C.', 'credit_days' => 30]);
    }

    protected function tearDownPurchasing(): void
    {
        Currency::clearBaseCache();
    }

    /**
     * Autentica a un usuario con los permisos dados, con acceso a la sede principal (y opcionalmente a la otra).
     *
     * @param  list<string>  $permissions
     */
    protected function actAs(array $permissions, bool $bothBranches = false): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $slug) {
            $user->givePermissionTo(Permission::where('slug', $slug)->firstOrFail());
        }

        $user->branches()->sync($bothBranches
            ? [$this->branch->id => ['is_default' => true], $this->otherBranch->id => ['is_default' => false]]
            : [$this->branch->id => ['is_default' => true]]);

        $this->withHeader('X-Branch-Id', $this->branch->id);
        $this->withHeader('Authorization', 'Bearer '.app(JwtService::class)->createTokenPair($user)['access_token']);

        return $user;
    }
}
