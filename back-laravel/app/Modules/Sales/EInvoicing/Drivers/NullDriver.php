<?php

declare(strict_types=1);

namespace App\Modules\Sales\EInvoicing\Drivers;

use App\Modules\Sales\EInvoicing\EInvoiceResult;
use App\Modules\Sales\EInvoicing\ElectronicInvoicingDriver;
use App\Modules\Sales\Enums\EInvoiceStatus;
use Illuminate\Database\Eloquent\Model;

class NullDriver implements ElectronicInvoicingDriver
{
    public function send(Model $document): EInvoiceResult
    {
        return new EInvoiceResult(
            status: EInvoiceStatus::NotApplicable,
            code: '0',
            message: 'Driver nulo de facturación electrónica: no se realiza envío externo.'
        );
    }
}
