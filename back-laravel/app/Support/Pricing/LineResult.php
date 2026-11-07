<?php

declare(strict_types=1);

namespace App\Support\Pricing;

use App\Modules\Inventory\Enums\TaxType;

/**
 * Resultado de una línea, redondeado a 2 decimales. `subtotal + tax = total`.
 */
final readonly class LineResult
{
    public function __construct(
        public TaxType $taxType,
        public string $discount,
        public string $subtotal,
        public string $tax,
        public string $total,
    ) {}
}
