<?php

declare(strict_types=1);

namespace App\Modules\Sales\EInvoicing;

use Illuminate\Database\Eloquent\Model;

interface ElectronicInvoicingDriver
{
    /**
     * Envía un comprobante electrónico (Sale o CreditNote) al PSE/OSE.
     */
    public function send(Model $document): EInvoiceResult;
}
