<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum SystemReason: string
{
    case Opening = 'OPENING';
    case Purchase = 'PURCHASE';
    case Sale = 'SALE';
    case SaleReturn = 'SALE_RETURN';
    case PurchaseReturn = 'PURCHASE_RETURN';
    case Transfer = 'TRANSFER';
    case CountAdjustmentIn = 'COUNT_ADJUSTMENT_IN';
    case CountAdjustmentOut = 'COUNT_ADJUSTMENT_OUT';

    public function type(): WarehouseReasonType
    {
        return match ($this) {
            self::Opening => WarehouseReasonType::In,
            self::Purchase => WarehouseReasonType::In,
            self::Sale => WarehouseReasonType::Out,
            self::SaleReturn => WarehouseReasonType::In,
            self::PurchaseReturn => WarehouseReasonType::Out,
            self::Transfer => WarehouseReasonType::Transfer,
            self::CountAdjustmentIn => WarehouseReasonType::Adjustment,
            self::CountAdjustmentOut => WarehouseReasonType::Adjustment,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Inventario inicial',
            self::Purchase => 'Compra',
            self::Sale => 'Venta',
            self::SaleReturn => 'Devolución de cliente',
            self::PurchaseReturn => 'Devolución a proveedor',
            self::Transfer => 'Traslado entre almacenes',
            self::CountAdjustmentIn => 'Ajuste de inventario (ingreso)',
            self::CountAdjustmentOut => 'Ajuste de inventario (salida)',
        };
    }
}
