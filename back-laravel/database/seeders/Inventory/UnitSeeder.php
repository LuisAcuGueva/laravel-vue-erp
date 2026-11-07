<?php

declare(strict_types=1);

namespace Database\Seeders\Inventory;

use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use Illuminate\Database\Seeder;

/**
 * Catálogo de unidades de medida con códigos SUNAT (Catálogo 03, UN/ECE Rec. 20/21).
 * La primera unidad de cada categoría es la base; los factores se expresan respecto a ella.
 * La categoría de empaque no tiene base ni factor: el factor lo define la presentación de cada producto.
 */
class UnitSeeder extends Seeder
{
    /**
     * Cada unidad: [nombre, símbolo, código SUNAT, factor, admite decimales].
     *
     * @var array<string, array{description: string, is_packaging?: bool, units: list<array{0: string, 1: string, 2: string, 3: string, 4: bool}>}>
     */
    private const CATALOG = [
        'Cantidad' => [
            'description' => 'Unidades de conteo o cantidad',
            'units' => [
                ['Unidad', 'UND', 'NIU', '1', false],
                ['Par', 'PAR', 'PR', '2', false],
                ['Docena', 'DOC', 'DZN', '12', false],
                ['Ciento', 'CTO', 'CEN', '100', false],
                ['Millar', 'MLL', 'MIL', '1000', false],
            ],
        ],
        'Masa' => [
            'description' => 'Unidades de masa y peso',
            'units' => [
                ['Kilogramo', 'KG', 'KGM', '1', true],
                ['Gramo', 'G', 'GRM', '0.001', true],
                ['Miligramo', 'MG', 'MGM', '0.000001', true],
                ['Tonelada', 'TN', 'TNE', '1000', true],
                ['Libra', 'LB', 'LBR', '0.453592', true],
                ['Onza', 'OZ', 'ONZ', '0.028350', true],
            ],
        ],
        'Volumen' => [
            'description' => 'Unidades de volumen y capacidad',
            'units' => [
                ['Litro', 'L', 'LTR', '1', true],
                ['Mililitro', 'ML', 'MLT', '0.001', true],
                ['Metro cúbico', 'M3', 'MTQ', '1000', true],
                ['Galón', 'GAL', 'GLL', '3.785412', true],
            ],
        ],
        'Longitud' => [
            'description' => 'Unidades de longitud',
            'units' => [
                ['Metro', 'M', 'MTR', '1', true],
                ['Centímetro', 'CM', 'CMT', '0.01', true],
                ['Milímetro', 'MM', 'MMT', '0.001', true],
                ['Kilómetro', 'KM', 'KTM', '1000', true],
                ['Pulgada', 'PULG', 'INH', '0.0254', true],
                ['Pie', 'PIE', 'FOT', '0.3048', true],
                ['Yarda', 'YD', 'YRD', '0.9144', true],
            ],
        ],
        'Área' => [
            'description' => 'Unidades de superficie',
            'units' => [
                ['Metro cuadrado', 'M2', 'MTK', '1', true],
                ['Centímetro cuadrado', 'CM2', 'CMK', '0.0001', true],
                ['Pie cuadrado', 'FT2', 'FTK', '0.092903', true],
                ['Hectárea', 'HA', 'HAR', '10000', true],
            ],
        ],
        'Tiempo' => [
            'description' => 'Unidades de tiempo para servicios y alquileres',
            'units' => [
                ['Minuto', 'MIN', 'MIN', '1', true],
                ['Hora', 'H', 'HUR', '60', true],
                ['Día', 'DIA', 'DAY', '1440', true],
            ],
        ],
        'Servicio' => [
            'description' => 'Unidades para servicios y mano de obra',
            'units' => [
                ['Servicio', 'SRV', 'ZZ', '1', false],
            ],
        ],
        'Empaque' => [
            'description' => 'Presentaciones de empaque; el contenido lo define cada producto',
            'is_packaging' => true,
            'units' => [
                ['Caja', 'CJA', 'BX', '1', false],
                ['Paquete', 'PQT', 'PK', '1', false],
                ['Bolsa', 'BLS', 'BG', '1', false],
                ['Saco', 'SCO', 'SA', '1', false],
                ['Rollo', 'RLL', 'RO', '1', false],
                ['Botella', 'BOT', 'BO', '1', false],
                ['Lata', 'LTA', 'CA', '1', false],
                ['Balde', 'BLD', 'BJ', '1', false],
                ['Bobina', 'BOB', '4A', '1', false],
                ['Juego', 'JGO', 'SET', '1', false],
                ['Kit', 'KIT', 'KT', '1', false],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $categoryName => $categoryData) {
            $isPackaging = $categoryData['is_packaging'] ?? false;
            $category = UnitCategory::firstOrCreate(
                ['name' => $categoryName],
                ['description' => $categoryData['description'], 'is_packaging' => $isPackaging, 'is_active' => true]
            );

            foreach ($categoryData['units'] as $index => [$name, $symbol, $sunatCode, $factor, $allowDecimals]) {
                Unit::updateOrCreate(
                    ['unit_category_id' => $category->id, 'name' => $name],
                    [
                        'symbol' => $symbol,
                        'is_base' => ! $isPackaging && $index === 0,
                        'conversion_factor' => $factor,
                        'allow_decimals' => $allowDecimals,
                        'sunat_code' => $sunatCode,
                    ]
                );
            }
        }
    }
}
