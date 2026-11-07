<?php

declare(strict_types=1);

namespace App\Modules\Sales\EInvoicing;

use App\Modules\Sales\EInvoicing\Drivers\NullDriver;
use Illuminate\Support\Manager;

class EInvoicingManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) ($this->config->get('einvoicing.driver') ?: 'null');
    }

    public function createNullDriver(): ElectronicInvoicingDriver
    {
        return new NullDriver;
    }
}
