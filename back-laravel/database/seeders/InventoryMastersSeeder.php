<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Inventory\BrandSeeder;
use Database\Seeders\Inventory\CategorySeeder;
use Database\Seeders\Inventory\UnitSeeder;
use Database\Seeders\Inventory\WarehouseReasonSeeder;
use Database\Seeders\Inventory\WarehouseSeeder;
use Illuminate\Database\Seeder;

/**
 * Maestros de configuración de inventario. Cada seeder es idempotente.
 */
class InventoryMastersSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UnitSeeder::class,
            WarehouseReasonSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            WarehouseSeeder::class,
        ]);
    }
}
