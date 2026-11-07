<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

use App\Modules\Finance\Models\PaymentMethod;

enum SystemPaymentMethod: string
{
    case Cash = 'CASH';

    public function model(): ?PaymentMethod
    {
        /** @var array<string, PaymentMethod|null> $cache */
        static $cache = [];

        return $cache[$this->value] ??= PaymentMethod::where('code', $this->value)->first();
    }
}
