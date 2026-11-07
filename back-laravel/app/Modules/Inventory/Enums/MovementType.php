<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum MovementType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case PurchaseReturn = 'purchase_return';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case NoteIn = 'note_in';
    case NoteOut = 'note_out';
    case ReversalIn = 'reversal_in';
    case ReversalOut = 'reversal_out';

    public function direction(): MovementDirection
    {
        return match ($this) {
            self::Opening,
            self::Purchase,
            self::SaleReturn,
            self::TransferIn,
            self::AdjustmentIn,
            self::NoteIn,
            self::ReversalIn => MovementDirection::In,

            self::PurchaseReturn,
            self::Sale,
            self::TransferOut,
            self::AdjustmentOut,
            self::NoteOut,
            self::ReversalOut => MovementDirection::Out,
        };
    }
}
