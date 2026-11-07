<?php

declare(strict_types=1);

namespace Database\Seeders\Inventory;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Branch;
use Illuminate\Database\Seeder;

/**
 * Almacenes iniciales: cuatro en la sede principal y uno por defecto en cada sede activa sin almacenes.
 * Nunca marca un almacén por defecto si la sede ya tiene otro.
 */
class WarehouseSeeder extends Seeder
{
    /**
     * @var array<string, array{0: string, 1: bool}> código => [nombre, por defecto]
     */
    private const MAIN_BRANCH_WAREHOUSES = [
        'ALM01' => ['Almacén Principal', true],
        'ALM02' => ['Almacén de Tienda / Exhibición', false],
        'ALM03' => ['Mermas y Productos Dañados', false],
        'ALM04' => ['Devoluciones', false],
    ];

    public function run(): void
    {
        $mainBranch = Branch::where('is_main', true)->first() ?? Branch::first();
        if ($mainBranch === null) {
            return;
        }

        foreach (self::MAIN_BRANCH_WAREHOUSES as $code => [$name, $isDefault]) {
            $this->createWarehouse($mainBranch, $code, $name, $isDefault);
        }

        Branch::where('id', '!=', $mainBranch->id)
            ->where('is_active', true)
            ->whereDoesntHave('warehouses')
            ->orderBy('name')
            ->each(fn (Branch $branch) => $this->createWarehouse(
                $branch,
                $this->nextCode(),
                'Almacén '.$branch->name,
                true,
            ));
    }

    private function createWarehouse(Branch $branch, string $code, string $name, bool $isDefault): void
    {
        $hasDefault = Warehouse::where('branch_id', $branch->id)->where('is_default', true)->exists();

        Warehouse::firstOrCreate(
            ['code' => $code],
            [
                'branch_id' => $branch->id,
                'name' => $name,
                'address' => $branch->address,
                'is_default' => $isDefault && ! $hasDefault,
                'is_active' => true,
            ]
        );
    }

    private function nextCode(): string
    {
        $number = 1;
        while (Warehouse::withTrashed()->where('code', sprintf('ALM%02d', $number))->exists()) {
            $number++;
        }

        return sprintf('ALM%02d', $number);
    }
}
