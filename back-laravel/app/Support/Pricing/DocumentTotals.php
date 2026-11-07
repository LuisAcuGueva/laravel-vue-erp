<?php

declare(strict_types=1);

namespace App\Support\Pricing;

/**
 * Totales de un documento comercial, redondeados a 2 decimales.
 */
final readonly class DocumentTotals
{
    /**
     * @param  list<LineResult>  $lines  En el mismo orden que las líneas de entrada.
     */
    public function __construct(
        public string $taxableBase,
        public string $exemptBase,
        public string $unaffectedBase,
        public string $discountTotal,
        public string $taxTotal,
        public string $total,
        public array $lines,
    ) {}
}
