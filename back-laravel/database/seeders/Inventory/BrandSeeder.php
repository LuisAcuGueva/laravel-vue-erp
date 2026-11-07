<?php

declare(strict_types=1);

namespace Database\Seeders\Inventory;

use App\Modules\Inventory\Models\Brand;
use Illuminate\Database\Seeder;

/**
 * Marcas genéricas y marcas reconocidas de distintos rubros. Se crean una sola vez.
 */
class BrandSeeder extends Seeder
{
    /**
     * @var array<string, string> nombre => descripción
     */
    private const BRANDS = [
        'Genérico' => 'Productos sin marca comercial definida',
        'Sin marca' => 'Productos a granel o sin identificación de marca',
        'Marca propia' => 'Productos con la marca de la empresa',
        'Samsung' => 'Tecnología y electrodomésticos',
        'LG' => 'Tecnología y electrodomésticos',
        'HP' => 'Cómputo e impresión',
        'Lenovo' => 'Cómputo',
        'Epson' => 'Impresión',
        'Sony' => 'Audio y video',
        'Xiaomi' => 'Telefonía y accesorios',
        'Philips' => 'Electrodomésticos e iluminación',
        'Oster' => 'Electrodomésticos',
        'Bosch' => 'Herramientas y repuestos',
        'Stanley' => 'Herramientas manuales',
        'Truper' => 'Ferretería y herramientas',
        '3M' => 'Seguridad, oficina y abrasivos',
        'Faber-Castell' => 'Útiles de escritorio',
        'Artesco' => 'Útiles de escritorio',
        'Castrol' => 'Lubricantes',
        'Nestlé' => 'Alimentos y bebidas',
        'Gloria' => 'Lácteos y derivados',
        'Procter & Gamble' => 'Limpieza e higiene',
        'Colgate' => 'Higiene personal',
        'Adidas' => 'Ropa y calzado',
        'Nike' => 'Ropa y calzado',
    ];

    public function run(): void
    {
        foreach (self::BRANDS as $name => $description) {
            Brand::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true]
            );
        }
    }
}
