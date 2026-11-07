<?php

declare(strict_types=1);

namespace App\Modules\Sales\Exports;

use App\Modules\Sales\Models\ReceivableInstallment;
use App\Support\Export\CsvExport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ReceivableInstallmentCsvExport extends CsvExport
{
    protected function columns(): array
    {
        return [
            'sale_number' => 'N° Venta',
            'client_doc' => 'Doc. Cliente',
            'client_name' => 'Cliente',
            'currency' => 'Moneda',
            'installment_number' => 'N° Cuota',
            'due_date' => 'F. Vencimiento',
            'amount' => 'Monto Cuota',
            'paid_amount' => 'Monto Pagado',
            'balance' => 'Saldo Pendiente',
            'days_overdue' => 'Días Vencidos',
            'status' => 'Estado',
        ];
    }

    protected function map(Model $row): array
    {
        /** @var ReceivableInstallment $row */
        $today = Carbon::today();
        $isOverdue = $row->due_date->lt($today) && (float) $row->balance > 0;
        $daysOverdue = $isOverdue ? (int) $row->due_date->diffInDays($today) : 0;

        return [
            'sale_number' => $row->sale->full_number,
            'client_doc' => $row->client->document_number ?? '—',
            'client_name' => $row->client->name,
            'currency' => $row->currency->code,
            'installment_number' => $row->number,
            'due_date' => $row->due_date->toDateString(),
            'amount' => $row->amount,
            'paid_amount' => $row->paid_amount,
            'balance' => $row->balance,
            'days_overdue' => $daysOverdue,
            'status' => $row->status->label(),
        ];
    }
}
