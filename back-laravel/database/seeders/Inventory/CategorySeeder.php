<?php

declare(strict_types=1);

namespace Database\Seeders\Inventory;

use App\Modules\Inventory\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Árbol general de categorías (máximo 3 niveles). Los códigos son jerárquicos: TEC, TEC-COM, TEC-COM-LAP.
 * Se crean una sola vez; luego las administra el usuario.
 */
class CategorySeeder extends Seeder
{
    /**
     * Cada nodo: código => [nombre, hijos]. El código de un hijo se antepone con el del padre.
     *
     * @var array<string, array{0: string, 1?: array<string, mixed>}>
     */
    private const TREE = [
        'TEC' => ['Tecnología y electrónica', [
            'COM' => ['Cómputo', [
                'LAP' => ['Laptops'],
                'DES' => ['Computadoras de escritorio'],
                'IMP' => ['Impresoras y escáneres'],
                'COMP' => ['Componentes y almacenamiento'],
            ]],
            'TEL' => ['Telefonía y tablets'],
            'AUD' => ['Audio y video'],
            'ACC' => ['Accesorios tecnológicos'],
        ]],
        'HOG' => ['Hogar', [
            'MUE' => ['Muebles'],
            'MEN' => ['Menaje y cocina'],
            'DEC' => ['Decoración'],
            'ELE' => ['Electrodomésticos'],
        ]],
        'ALI' => ['Alimentos y bebidas', [
            'ABA' => ['Abarrotes'],
            'BEB' => ['Bebidas'],
            'SNK' => ['Snacks y golosinas'],
            'LAC' => ['Lácteos y derivados'],
        ]],
        'LIM' => ['Limpieza e higiene', [
            'HOG' => ['Limpieza del hogar'],
            'PER' => ['Higiene personal'],
            'IND' => ['Limpieza industrial'],
        ]],
        'OFI' => ['Oficina y papelería', [
            'UTI' => ['Útiles de escritorio'],
            'PAP' => ['Papel e impresión'],
            'ARC' => ['Archivo y organización'],
        ]],
        'FER' => ['Ferretería y construcción', [
            'HMA' => ['Herramientas manuales'],
            'HEL' => ['Herramientas eléctricas'],
            'MEL' => ['Material eléctrico'],
            'GAS' => ['Gasfitería'],
            'CON' => ['Materiales de construcción'],
        ]],
        'AUT' => ['Automotriz', [
            'REP' => ['Repuestos'],
            'LUB' => ['Lubricantes y aditivos'],
            'ACC' => ['Accesorios para vehículos'],
        ]],
        'MOD' => ['Moda', [
            'ROP' => ['Ropa'],
            'CAL' => ['Calzado'],
            'ACC' => ['Accesorios de moda'],
        ]],
        'SAL' => ['Salud y cuidado personal', [
            'BOT' => ['Botiquín y primeros auxilios'],
            'COS' => ['Cosmética'],
        ]],
        'INS' => ['Insumos y materias primas'],
        'SUM' => ['Suministros internos'],
        'SRV' => ['Servicios'],
    ];

    public function run(): void
    {
        $this->seedLevel(self::TREE, null);
    }

    /**
     * @param  array<string, array{0: string, 1?: array<string, mixed>}>  $nodes
     */
    private function seedLevel(array $nodes, ?Category $parent): void
    {
        foreach ($nodes as $code => $node) {
            $category = Category::firstOrCreate(
                ['code' => $parent === null ? $code : $parent->code.'-'.$code],
                [
                    'parent_id' => $parent?->id,
                    'name' => $node[0],
                    'depth' => $parent === null ? 0 : $parent->depth + 1,
                    'is_active' => true,
                ]
            );

            if (isset($node[1])) {
                $this->seedLevel($node[1], $category);
            }
        }
    }
}
