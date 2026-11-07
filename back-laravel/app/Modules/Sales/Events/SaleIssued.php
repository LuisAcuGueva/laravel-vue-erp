<?php

declare(strict_types=1);

namespace App\Modules\Sales\Events;

use App\Modules\Sales\Models\Sale;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleIssued
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Sale $sale
    ) {}
}
