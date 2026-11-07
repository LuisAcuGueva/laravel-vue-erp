<?php

declare(strict_types=1);

namespace Database\Seeders\Inventory;

use App\Modules\Inventory\Enums\SystemReason;
use App\Modules\Inventory\Enums\WarehouseReasonType;
use App\Modules\Inventory\Models\WarehouseReason;
use Illuminate\Database\Seeder;

/**
 * Motivos de almacén: los de sistema salen de `SystemReason` y se sincronizan siempre;
 * los operativos se crean una sola vez y luego los administra el usuario.
 */
class WarehouseReasonSeeder extends Seeder
{
    /**
     * @var list<array{0: string, 1: string, 2: WarehouseReasonType, 3: bool}> [código, nombre, tipo, requiere aprobación]
     */
    private const OPERATIONAL = [
        ['DONATION_IN', 'Donación recibida', WarehouseReasonType::In, true],
        ['SUPPLIER_BONUS', 'Bonificación de proveedor', WarehouseReasonType::In, false],
        ['PRODUCTION_IN', 'Ingreso por producción', WarehouseReasonType::In, false],
        ['LOAN_RETURN_IN', 'Devolución de préstamo', WarehouseReasonType::In, false],
        ['SHRINKAGE', 'Merma', WarehouseReasonType::Out, true],
        ['EXPIRED', 'Producto vencido', WarehouseReasonType::Out, true],
        ['DAMAGED', 'Producto dañado', WarehouseReasonType::Out, true],
        ['THEFT_LOSS', 'Robo o extravío', WarehouseReasonType::Out, true],
        ['INTERNAL_USE', 'Consumo interno', WarehouseReasonType::Out, true],
        ['SAMPLE_GIFT', 'Muestra u obsequio', WarehouseReasonType::Out, true],
        ['DONATION_OUT', 'Donación entregada', WarehouseReasonType::Out, true],
        ['PRODUCTION_OUT', 'Consumo en producción', WarehouseReasonType::Out, false],
        ['CUSTOMER_BONUS', 'Bonificación a cliente', WarehouseReasonType::Out, true],
        ['LOAN_OUT', 'Préstamo a terceros', WarehouseReasonType::Out, true],
        ['ERROR_CORRECTION', 'Corrección de error de registro', WarehouseReasonType::Adjustment, true],
    ];

    public function run(): void
    {
        foreach (SystemReason::cases() as $systemReason) {
            WarehouseReason::updateOrCreate(
                ['code' => $systemReason->value],
                [
                    'name' => $systemReason->label(),
                    'type' => $systemReason->type(),
                    'requires_approval' => false,
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }

        foreach (self::OPERATIONAL as [$code, $name, $type, $requiresApproval]) {
            WarehouseReason::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'requires_approval' => $requiresApproval,
                    'is_system' => false,
                    'is_active' => true,
                ]
            );
        }
    }
}
