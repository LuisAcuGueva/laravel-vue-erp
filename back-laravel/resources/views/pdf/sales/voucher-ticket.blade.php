<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $sale->document_type->label() }} {{ $sale->full_number }}</title>
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
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            padding: 4px;
            margin: 6px 0;
        }
    </style>
</head>
<body>
    @if ($sale->status->value === 'cancelled')
        <div class="cancelled-banner">*** DOCUMENTO ANULADO ***</div>
    @endif

    <div class="center">
        @if (!empty($logo_data_uri))
            <img src="{{ $logo_data_uri }}" alt="Logo" style="max-height: 36px; max-width: 140px; margin-bottom: 2px;"><br>
        @endif
        <span class="bold" style="font-size: 10px;">{{ $company->legal_name }}</span><br>
        @if ($company->trade_name)
            <span>{{ $company->trade_name }}</span><br>
        @endif
        <span>RUC: {{ $company->ruc }}</span><br>
        @if ($company->address)
            <span>{{ $company->address }}</span><br>
        @endif
        @if ($company->phone)
            <span>Telf: {{ $company->phone }}</span><br>
        @endif
        <span class="bold">{{ $sale->branch?->name }}</span>
    </div>

    <div class="double-divider"></div>

    <div class="center">
        <span class="bold" style="font-size: 10px;">{{ strtoupper($sale->document_type->label()) }}</span><br>
        <span class="bold" style="font-size: 11px;">{{ $sale->full_number }}</span>
    </div>

    <div class="divider"></div>

    <table>
        <tr><td class="bold">Fecha:</td><td class="right">{{ $sale->issued_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td class="bold">Cliente:</td><td>{{ $sale->client_name }}</td></tr>
        @if ($sale->client_document_number)
            <tr><td class="bold">{{ $sale->client_document_type ?? 'DOC' }}:</td><td>{{ $sale->client_document_number }}</td></tr>
        @endif
        @if ($sale->client_address)
            <tr><td class="bold">Dirección:</td><td>{{ $sale->client_address }}</td></tr>
        @endif
        <tr><td class="bold">Condición:</td><td class="right">{{ $sale->payment_condition->label() }}</td></tr>
        @if ($sale->seller)
            <tr><td class="bold">Vendedor:</td><td class="right">{{ $sale->seller->name }}</td></tr>
        @endif
    </table>

    <div class="divider"></div>

    <table>
        <thead>
            <tr class="bold" style="border-bottom: 1px solid #444;">
                <th style="width: 15%; text-align: left;">Cant.</th>
                <th style="width: 55%; text-align: left;">Descripción</th>
                <th style="width: 30%; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ number_format((float) $item->quantity, 2) }}</td>
                    <td>
                        {{ $item->description }}<br>
                        <span style="font-size: 7.5px; color: #555;">{{ $sale->currency->symbol }} {{ number_format((float) $item->unit_price, 2) }} c/u</span>
                    </td>
                    <td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table>
        @if ((float) $sale->taxable_base > 0)
            <tr><td>Op. Gravada:</td><td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $sale->taxable_base, 2) }}</td></tr>
        @endif
        @if ((float) $sale->exempt_base > 0)
            <tr><td>Op. Exonerada:</td><td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $sale->exempt_base, 2) }}</td></tr>
        @endif
        @if ((float) $sale->unaffected_base > 0)
            <tr><td>Op. Inafecta:</td><td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $sale->unaffected_base, 2) }}</td></tr>
        @endif
        @if ((float) $sale->discount_total > 0)
            <tr><td>Descuento:</td><td class="right">- {{ $sale->currency->symbol }} {{ number_format((float) $sale->discount_total, 2) }}</td></tr>
        @endif
        <tr><td>IGV ({{ number_format((float) $sale->tax_rate, 0) }}%):</td><td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $sale->tax_total, 2) }}</td></tr>
        <tr class="bold" style="font-size: 11px;">
            <td>TOTAL:</td>
            <td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $sale->total, 2) }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <div style="font-size: 7.5px;">
        <strong>SON:</strong> {{ $amount_in_words }}
    </div>

    @if ($sale->payments->isNotEmpty())
        <div class="divider"></div>
        <table>
            <tr class="bold"><td colspan="2">PAGOS:</td></tr>
            @foreach ($sale->payments as $payment)
                <tr>
                    <td>{{ $payment->paymentMethod?->name ?? 'Pago' }}</td>
                    <td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $payment->amount, 2) }}</td>
                </tr>
                @if ($payment->received_amount && (float) $payment->change_amount > 0)
                    <tr style="font-size: 7.5px; color: #555;">
                        <td style="padding-left: 6px;">Recibido: {{ $sale->currency->symbol }} {{ number_format((float) $payment->received_amount, 2) }}</td>
                        <td class="right">Vuelto: {{ $sale->currency->symbol }} {{ number_format((float) $payment->change_amount, 2) }}</td>
                    </tr>
                @endif
            @endforeach
        </table>
    @endif

    <div class="double-divider"></div>

    <div class="center" style="font-size: 8px;">
        @if ($sale->einvoice_status->value === 'accepted')
            <span>Comprobante emitido electrónicamente</span><br>
        @endif
        <span>¡Gracias por su preferencia!</span>
    </div>
</body>
</html>
