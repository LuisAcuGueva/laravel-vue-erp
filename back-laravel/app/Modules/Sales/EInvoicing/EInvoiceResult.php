<?php

declare(strict_types=1);

namespace App\Modules\Sales\EInvoicing;

use App\Modules\Sales\Enums\EInvoiceStatus;

final readonly class EInvoiceResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public EInvoiceStatus $status,
        public ?string $code = null,
        public ?string $message = null,
        public ?string $hash = null,
        public array $raw = [],
    ) {}
}
