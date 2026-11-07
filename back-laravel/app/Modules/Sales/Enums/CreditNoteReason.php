<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

enum CreditNoteReason: string
{
    case ANULACION_OPERACION = '01';
    case DESCUENTO_GLOBAL = '04';
    case DESCUENTO_ITEM = '05';
    case DEVOLUCION_TOTAL = '06';
    case DEVOLUCION_ITEM = '07';
    case DISMINUCION_VALOR = '09';

    public function description(): string
    {
        return match ($this) {
            self::ANULACION_OPERACION => 'Anulación de la operación',
            self::DESCUENTO_GLOBAL => 'Descuento global',
            self::DESCUENTO_ITEM => 'Descuento por ítem',
            self::DEVOLUCION_TOTAL => 'Devolución total',
            self::DEVOLUCION_ITEM => 'Devolución por ítem',
            self::DISMINUCION_VALOR => 'Disminución en el valor',
        };
    }

    /**
     * Determina si el motivo permite o exige devolución de inventario.
     */
    public function canReturnToStock(): bool
    {
        return match ($this) {
            self::ANULACION_OPERACION,
            self::DEVOLUCION_TOTAL,
            self::DEVOLUCION_ITEM => true,
            default => false,
        };
    }
}
