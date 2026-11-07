@extends('pdf.layouts.document')

@section('title', $sale->document_type->label().' '.$sale->full_number)
@section('doc_title', strtoupper($sale->document_type->label()))
@section('doc_number', $sale->full_number)

@section('content')
    <div class="section-title">Cliente</div>
    <table>
        <tr>
            <td style="width: 55%;">
                <strong>{{ $sale->client_name }}</strong><br>
                @if ($sale->client_document_number)
                    {{ $sale->client_document_type ?? 'DOC' }}: {{ $sale->client_document_number }}<br>
                @endif
                @if ($sale->client_address)
                    Dirección: {{ $sale->client_address }}<br>
                @endif
            </td>
            <td style="width: 45%;">
                <table>
                    <tr><td class="muted">Fecha de emisión</td><td class="right">{{ $sale->issue_date->format('d/m/Y') }}</td></tr>
                    @if ($sale->due_date)
                        <tr><td class="muted">Fecha de vencimiento</td><td class="right">{{ $sale->due_date->format('d/m/Y') }}</td></tr>
                    @endif
                    <tr><td class="muted">Condición de pago</td><td class="right">{{ $sale->payment_condition->label() }}</td></tr>
                    <tr><td class="muted">Vendedor</td><td class="right">{{ $sale->seller?->name ?? '—' }}</td></tr>
                    <tr><td class="muted">Moneda</td><td class="right">{{ $sale->currency->code }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 14px;">
        <thead>
            <tr>
                <th style="width: 5%;" class="center">#</th>
                <th style="width: 50%;">Descripción</th>
                <th style="width: 10%;" class="center">U.M.</th>
                <th style="width: 10%;" class="right">Cant.</th>
                <th style="width: 12%;" class="right">P. Unit</th>
                <th style="width: 13%;" class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td class="center">{{ $item->line }}</td>
                    <td>
                        {{ $item->description }}
                    </td>
                    <td class="center">{{ $item->unit_sunat_code ?? 'NIU' }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 10px;">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div style="font-size: 9px; padding: 6px; background: #f8f8f8; border: 1px solid #ddd; margin-bottom: 8px;">
                    <strong>SON:</strong> {{ $amount_in_words }}
                </div>

                @if ($sale->payments->isNotEmpty())
                    <div class="section-title" style="margin-top: 4px;">Información de Pagos</div>
                    <table style="font-size: 9px; margin-top: 4px;">
                        @foreach ($sale->payments as $payment)
                            <tr>
                                <td>{{ $payment->paymentMethod?->name ?? 'Pago' }}</td>
                                @if ($payment->reference)
                                    <td class="muted">Ref: {{ $payment->reference }}</td>
                                @endif
                                <td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $payment->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                @if ($sale->notes)
                    <div class="section-title" style="margin-top: 6px;">Observaciones</div>
                    <p style="font-size: 9px; margin: 4px 0;">{{ $sale->notes }}</p>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table style="width: 100%;">
                    @if ((float) $sale->taxable_base > 0)
                        <tr><td>Op. Gravada</td><td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $sale->taxable_base, 2) }}</td></tr>
                    @endif
                    @if ((float) $sale->exempt_base > 0)
                        <tr><td>Op. Exonerada</td><td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $sale->exempt_base, 2) }}</td></tr>
                    @endif
                    @if ((float) $sale->unaffected_base > 0)
                        <tr><td>Op. Inafecta</td><td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $sale->unaffected_base, 2) }}</td></tr>
                    @endif
                    @if ((float) $sale->discount_total > 0)
                        <tr><td>Descuento Total</td><td class="right">- {{ $sale->currency->symbol }} {{ number_format((float) $sale->discount_total, 2) }}</td></tr>
                    @endif
                    <tr><td>IGV ({{ number_format((float) $sale->tax_rate, 0) }}%)</td><td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $sale->tax_total, 2) }}</td></tr>
                    <tr style="font-weight: bold; font-size: 12px;">
                        <td style="border-top: 1px solid #333; padding-top: 4px;">TOTAL</td>
                        <td class="right" style="border-top: 1px solid #333; padding-top: 4px;">{{ $sale->currency->symbol }} {{ number_format((float) $sale->total, 2) }}</td>
                    </tr>
                    @if ((float) $sale->balance > 0)
                        <tr style="color: #b71c1c; font-weight: bold;">
                            <td>Saldo Pendiente</td>
                            <td class="right">{{ $sale->currency->symbol }} {{ number_format((float) $sale->balance, 2) }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
@endsection
