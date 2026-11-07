<?php

declare(strict_types=1);

namespace App\Modules\Sales\Contracts;

use App\Modules\Sales\Models\Sale;

interface AfterSaleIssuedHook
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Sale $sale, array $input): void;
}
