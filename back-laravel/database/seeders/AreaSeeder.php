<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Maintenance\Models\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    /**
     * @var list<array{name: string, description: string}>
     */
    private const AREAS = [
        [
            'name' => 'Dirección y Gerencia General',
            'description' => 'Alta dirección estratégica y toma de decisiones corporativas.',
        ],
        [
            'name' => 'Administración y Finanzas',
            'description' => 'Gestión financiera, contabilidad, tesorería y recursos generales.',
        ],
        [
            'name' => 'Tecnología y Sistemas (TI)',
            'description' => 'Infraestructura tecnológica, desarrollo, soporte y telecomunicaciones.',
        ],
        [
            'name' => 'Operaciones y Logística',
            'description' => 'Gestión de almacenes, despachos, distribución y compras operativas.',
        ],
        [
            'name' => 'Ventas y Comercial',
            'description' => 'Atención al cliente, ventas mayoristas, distribución y marketing.',
        ],
    ];

    public function run(): void
    {
        foreach (self::AREAS as $areaData) {
            Area::firstOrCreate(
                ['name' => $areaData['name']],
                [
                    'description' => $areaData['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
