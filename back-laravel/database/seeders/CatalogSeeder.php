<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Inventory\Enums\CatalogItemType;
use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\ItemPrice;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Datos de muestra del catálogo (solo local/testing). Depende de InventoryMastersSeeder.
 * Es idempotente: re-ejecutarlo corrige categoría, marca y precios de los ítems demo.
 */
class CatalogSeeder extends Seeder
{
    /**
     * Bienes: código => [nombre, categoría, marca, unidad base, afectación, EAN, minorista, mayorista, mín. mayorista, presentaciones extra].
     * Cada presentación extra: [nombre, símbolo de unidad, factor, EAN, descuento sobre el precio por unidad base].
     *
     * @var list<array<string, mixed>>
     */
    private const PRODUCTS = [
        ['ITM-000001', 'Laptop Profesional 15" Core i7', 'TEC-COM-LAP', 'Lenovo', 'UND', 'taxable', '7750000000011', 3899.00, 3650.00, 3],
        ['ITM-000002', 'Monitor LED 27" 4K UHD', 'TEC-AUD', 'Samsung', 'UND', 'taxable', '7750000000028', 1250.00, 1120.00, 3],
        ['ITM-000003', 'Teclado Mecánico Inalámbrico RGB', 'TEC-ACC', 'Xiaomi', 'UND', 'taxable', '7750000000035', 280.00, 245.00, 5],
        ['ITM-000004', 'Mouse Óptico Ergonómico Bluetooth', 'TEC-ACC', 'Xiaomi', 'UND', 'taxable', '7750000000042', 120.00, 99.00, 5],
        ['ITM-000005', 'Disco Sólido SSD 1TB NVMe M.2', 'TEC-COM-COMP', 'Samsung', 'UND', 'taxable', '7750000000059', 380.00, 340.00, 5],
        ['ITM-000006', 'Memoria RAM 16GB DDR5 5600MHz', 'TEC-COM-COMP', 'Genérico', 'UND', 'taxable', '7750000000066', 260.00, 230.00, 5],
        ['ITM-000007', 'Impresora Multifuncional WiFi Tinta Continua', 'TEC-COM-IMP', 'Epson', 'UND', 'taxable', '7750000000073', 890.00, 820.00, 3],
        ['ITM-000008', 'Papel Fotocopia A4 75g (Paquete 500 hojas)', 'OFI-PAP', 'Genérico', 'PQT', 'taxable', '7750000000080', 18.00, 15.50, 10, [
            ['Caja x 10 paquetes', 'CJA', 10, '7750000000110', 0.05],
        ]],
        ['ITM-000009', 'Cable HDMI 2.1 Ultra High Speed 2m', 'TEC-ACC', '3M', 'UND', 'taxable', '7750000000097', 45.00, 35.00, 6, [
            ['Docena (12 unidades)', 'DOC', 12, '7750000000999', 0.04],
        ]],
        ['ITM-000010', 'Arroz Extra Seleccionado 1 kg', 'ALI-ABA', 'Sin marca', 'KG', 'unaffected', '7750000000103', 4.80, 4.20, 5, [
            ['Saco 50 kg', 'SCO', 50, '7750000000127', 0.12],
        ]],
        ['ITM-000011', 'Aceite Vegetal Botella 1 L', 'ALI-ABA', 'Genérico', 'BOT', 'taxable', '7750000000134', 9.50, 8.60, 6, [
            ['Caja x 12 botellas', 'CJA', 12, '7750000000141', 0.08],
        ]],
        ['ITM-000012', 'Leche Evaporada Lata 400 g', 'ALI-LAC', 'Gloria', 'LTA', 'taxable', '7750000000158', 4.90, 4.40, 24, [
            ['Caja x 48 latas', 'CJA', 48, '7750000000165', 0.1],
        ]],
        ['ITM-000013', 'Detergente en Polvo Bolsa 2 kg', 'LIM-HOG', 'Procter & Gamble', 'BLS', 'taxable', '7750000000172', 21.90, 19.80, 6],
        ['ITM-000014', 'Pasta Dental Menta 120 g', 'LIM-PER', 'Colgate', 'UND', 'taxable', '7750000000189', 7.50, 6.60, 12, [
            ['Caja x 24 unidades', 'CJA', 24, '7750000000196', 0.1],
        ]],
        ['ITM-000015', 'Lapicero Punta Fina Azul', 'OFI-UTI', 'Faber-Castell', 'UND', 'taxable', '7750000000202', 1.20, 0.90, 50, [
            ['Caja x 50 unidades', 'CJA', 50, '7750000000219', 0.15],
        ]],
        ['ITM-000016', 'Taladro Percutor Inalámbrico 20V', 'FER-HEL', 'Bosch', 'UND', 'taxable', '7750000000226', 420.00, 385.00, 2],
        ['ITM-000017', 'Aceite de Motor 20W-50 Galón', 'AUT-LUB', 'Castrol', 'GAL', 'taxable', '7750000000233', 95.00, 86.00, 4],
    ];

    /**
     * Servicios: código => [nombre, minorista, mayorista, mín. mayorista].
     *
     * @var list<array<string, mixed>>
     */
    private const SERVICES = [
        ['SRV-000001', 'Mantenimiento Preventivo de Computadoras', 80.00, 65.00, 3],
        ['SRV-000002', 'Instalación y Configuración de Redes', 150.00, 120.00, 3],
        ['SRV-000003', 'Consultoría Técnica de TI por Hora', 100.00, 85.00, 3],
    ];

    public function run(): void
    {
        $units = Unit::query()->get()->keyBy('symbol');
        $categories = Category::query()->get()->keyBy('code');
        $brands = Brand::query()->get()->keyBy('name');

        if ($units->isEmpty() || $categories->isEmpty()) {
            return;
        }

        foreach (self::PRODUCTS as $row) {
            [$code, $name, $categoryCode, $brandName, $unitSymbol, $tax, $barcode, $retail, $wholesale, $minQty] = $row;
            $unit = $units[$unitSymbol] ?? null;
            $category = $categories[$categoryCode] ?? null;

            if ($unit === null || $category === null) {
                continue;
            }

            $item = $this->upsertItem($code, [
                'name' => $name,
                'type' => CatalogItemType::Product,
                'category_id' => $category->id,
                'brand_id' => $brands[$brandName]?->id ?? null,
                'base_unit_id' => $unit->id,
                'description' => "Artículo de muestra: {$name}.",
                'tax_type' => TaxType::from($tax),
                'track_stock' => true,
                'is_sellable' => true,
                'is_purchasable' => true,
            ]);

            $base = $this->upsertBasePresentation($item, $unit, $unit->name, $barcode);
            $this->setPrices($base, $retail, $wholesale, $minQty);

            foreach ($row[10] ?? [] as [$presName, $presSymbol, $factor, $presBarcode, $discount]) {
                $presUnit = $units[$presSymbol] ?? null;

                if ($presUnit === null) {
                    continue;
                }

                $presentation = ItemPresentation::updateOrCreate(
                    ['catalog_item_id' => $item->id, 'name' => $presName],
                    [
                        'unit_id' => $presUnit->id,
                        'factor' => $factor,
                        'barcode' => $presBarcode,
                        'is_base' => false,
                        'is_default' => false,
                        'is_active' => true,
                    ]
                );

                $this->setPrices(
                    $presentation,
                    round($retail * $factor * (1 - $discount), 2),
                    round($wholesale * $factor * (1 - $discount), 2),
                    max(1, intdiv($minQty, 2)),
                );
            }
        }

        $serviceUnit = $units['SRV'] ?? $units['UND'] ?? null;
        $serviceCategory = $categories['SRV'] ?? null;

        if ($serviceUnit === null || $serviceCategory === null) {
            return;
        }

        foreach (self::SERVICES as [$code, $name, $retail, $wholesale, $minQty]) {
            $service = $this->upsertItem($code, [
                'name' => $name,
                'type' => CatalogItemType::Service,
                'category_id' => $serviceCategory->id,
                'brand_id' => null,
                'base_unit_id' => $serviceUnit->id,
                'description' => "Servicio profesional: {$name}.",
                'tax_type' => TaxType::Taxable,
                'track_stock' => false,
                'is_sellable' => true,
                'is_purchasable' => false,
            ]);

            $base = $this->upsertBasePresentation($service, $serviceUnit, 'Servicio', null);
            $this->setPrices($base, $retail, $wholesale, $minQty);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertItem(string $code, array $attributes): CatalogItem
    {
        return CatalogItem::updateOrCreate(['code' => $code], $attributes + ['is_active' => true]);
    }

    private function upsertBasePresentation(CatalogItem $item, Unit $unit, string $name, ?string $barcode): ItemPresentation
    {
        return ItemPresentation::updateOrCreate(
            ['catalog_item_id' => $item->id, 'is_base' => true],
            [
                'unit_id' => $unit->id,
                'name' => $name,
                'factor' => 1,
                'barcode' => $barcode,
                'is_default' => true,
                'is_active' => true,
            ]
        );
    }

    private function setPrices(ItemPresentation $presentation, float $retail, float $wholesale, int $wholesaleMinQty): void
    {
        ItemPrice::updateOrCreate(
            ['item_presentation_id' => $presentation->id, 'price_type' => PriceType::Retail],
            ['price' => $retail, 'min_quantity' => 1]
        );

        ItemPrice::updateOrCreate(
            ['item_presentation_id' => $presentation->id, 'price_type' => PriceType::Wholesale],
            ['price' => $wholesale, 'min_quantity' => $wholesaleMinQty]
        );
    }
}
