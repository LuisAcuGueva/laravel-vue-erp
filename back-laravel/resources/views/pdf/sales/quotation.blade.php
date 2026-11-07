@extends('pdf.layouts.document')

@section('title', 'Cotización '.$quotation['number'])
@section('doc_title', 'COTIZACIÓN')
@section('doc_number', $quotation['number'])

@section('content')
    <div class="section-title">Cliente</div>
    <table>
        <tr>
            <td style="width: 55%;">
                <strong>{{ $quotation['client']['name'] }}</strong><br>
                {{ $quotation['client']['document_type'] }}: {{ $quotation['client']['document_number'] }}<br>
                @if ($quotation['client']['address']){{ $quotation['client']['address'] }}<br>@endif
                {{ collect([$quotation['client']['phone'], $quotation['client']['email']])->filter()->implode(' · ') }}
                @if ($quotation['client']['contact'])<br>Atención: {{ $quotation['client']['contact'] }}@endif
            </td>
            <td style="width: 45%;">
                <table>
                    <tr><td class="muted">Fecha de emisión</td><td class="right">{{ $quotation['issue_date'] }}</td></tr>
                    <tr><td class="muted">Válido hasta</td><td class="right">{{ $quotation['valid_until'] }}</td></tr>
                    <tr><td class="muted">Vendedor</td><td class="right">{{ $quotation['seller'] }}</td></tr>
                    <tr><td class="muted">Condición de pago</td><td class="right">{{ $quotation['payment_terms'] }}</td></tr>
                    <tr><td class="muted">Tiempo de entrega</td><td class="right">{{ $quotation['delivery_time'] }}</td></tr>
                    <tr><td class="muted">Moneda</td><td class="right">{{ $quotation['currency_code'] }}@if ((float) $quotation['exchange_rate'] != 1) · T.C. {{ $quotation['exchange_rate'] }}@endif</td></tr>
                    <tr><td class="muted">Precios</td><td class="right">{{ $quotation['prices_include_tax'] ? 'Incluyen IGV' : 'No incluyen IGV' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @include('pdf.partials.items', ['items' => $items, 'currency_symbol' => $quotation['currency_symbol']])
    @include('pdf.partials.totals', ['totals' => $totals, 'tax_rate' => $quotation['tax_rate'], 'currency_symbol' => $quotation['currency_symbol']])

    @if (!empty($quotation['notes']))
        <div class="section-title">Observaciones</div>
        <p>{{ $quotation['notes'] }}</p>
    @endif

    @if (!empty($quotation['terms']))
        <div class="section-title">Términos y condiciones</div>
        <p>{{ $quotation['terms'] }}</p>
    @endif
@endsection
