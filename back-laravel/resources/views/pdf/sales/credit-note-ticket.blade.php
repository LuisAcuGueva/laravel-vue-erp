<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nota de Crédito {{ $note->full_number }}</title>
    <style>
        @page { margin: 8px 6px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #111; line-height: 1.25; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #444; margin: 5px 0; }
        .double-divider { border-top: 2px solid #222; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .cancelled-banner {
            border: 2px dashed #b71c1c;
            color: #b71c1c;
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            padding: 4px;
            margin: 6px 0;
        }
    </style>
</head>
<body>
    @if ($note->status->value === 'cancelled')
        <div class="cancelled-banner">*** NOTA ANULADA ***</div>
    @endif

    <div class="center">
        @if (!empty($logo_data_uri))
            <img src="{{ $logo_data_uri }}" alt="Logo" style="max-height: 36px; max-width: 140px; margin-bottom: 2px;"><br>
        @endif
        <span class="bold" style="font-size: 10px;">{{ $company->legal_name }}</span><br>
        <span>RUC: {{ $company->ruc }}</span><br>
        @if ($company->address)
            <span>{{ $company->address }}</span><br>
        @endif
        <span class="bold">{{ $note->branch?->name }}</span>
    </div>

    <div class="double-divider"></div>

    <div class="center">
        <span class="bold" style="font-size: 10px;">NOTA DE CRÉDITO ELECTRÓNICA</span><br>
        <span class="bold" style="font-size: 11px;">{{ $note->full_number }}</span>
    </div>

    <div class="divider"></div>

    <table>
        <tr><td class="bold">Doc. Modificado:</td><td class="right bold">{{ $note->sale->full_number }}</td></tr>
        <tr><td>Fecha:</td><td class="right">{{ $note->issued_at?->format('d/m/Y H:i') ?? $note->issue_date }}</td></tr>
        <tr><td>Motivo:</td><td class="right">[{{ $note->reason_code->value }}] {{ $note->reason_code->description() }}</td></tr>
        <tr><td>Sustento:</td><td class="right">{{ $note->reason_description }}</td></tr>
    </table>

    <div class="divider"></div>

    <table>
        <tr><td class="bold">Cliente:</td><td>{{ $note->client_name }}</td></tr>
        @if ($note->client_document_number)
            <tr><td>{{ $note->client_document_type ?? 'DOC' }}:</td><td>{{ $note->client_document_number }}</td></tr>
        @endif
    </table>

    <div class="divider"></div>

    <table>
        <thead>
            <tr class="bold">
                <td>Cant. x Descrip.</td>
                <td class="right">Total</td>
            </tr>
        </thead>
        <tbody>
            @foreach ($note->items as $item)
                <tr>
                    <td colspan="2">{{ $item->description }}</td>
                </tr>
                <tr>
                    <td>{{ number_format((float) $item->quantity, 2) }} x {{ $note->currency->symbol }} {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right bold">{{ $note->currency->symbol }} {{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table>
        @if ((float) $note->taxable_base > 0)
            <tr><td>Op. Gravadas:</td><td class="right">{{ $note->currency->symbol }} {{ number_format((float) $note->taxable_base, 2) }}</td></tr>
        @endif
        <tr><td>IGV ({{ number_format((float) $note->tax_rate, 0) }}%):</td><td class="right">{{ $note->currency->symbol }} {{ number_format((float) $note->tax_total, 2) }}</td></tr>
        <tr class="bold" style="font-size: 10px;">
            <td>TOTAL:</td>
            <td class="right">{{ $note->currency->symbol }} {{ number_format((float) $note->total, 2) }}</td>
        </tr>
    </table>

    @if ((float) $note->applied_to_balance > 0 || (float) $note->refunded_amount > 0)
        <div class="divider"></div>
        <table>
            @if ((float) $note->applied_to_balance > 0)
                <tr><td>Aplicado a saldo:</td><td class="right bold">{{ $note->currency->symbol }} {{ number_format((float) $note->applied_to_balance, 2) }}</td></tr>
            @endif
            @if ((float) $note->refunded_amount > 0)
                <tr><td>Reembolsado:</td><td class="right bold">{{ $note->currency->symbol }} {{ number_format((float) $note->refunded_amount, 2) }}</td></tr>
            @endif
        </table>
    @endif

    <div class="divider"></div>
    <div class="center" style="font-size: 7.5px;">
        SON: {{ $amount_in_words }}
    </div>

    @if ($note->cancel_reason)
        <div class="divider"></div>
        <div style="color: #b71c1c; font-size: 8px;">
            <strong>ANULADO:</strong> {{ $note->cancel_reason }}
        </div>
    @endif
</body>
</html>
