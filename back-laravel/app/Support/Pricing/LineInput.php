<?php

declare(strict_types=1);

namespace App\Support\Pricing;

use App\Modules\Inventory\Enums\TaxType;

/**
 * Línea de documento tal como llega al cálculo. Los importes son cadenas decimales (bcmath).
 */
final readonly class LineInput
{
    public function __construct(
        public string $quantity,
        public string $unitPrice,
        public string $discountPercent = '0',
        public ?string $discountAmount = null,
        public TaxType $taxType = TaxType::Taxable,
    ) {}
}
