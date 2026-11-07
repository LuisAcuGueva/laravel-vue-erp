@extends('pdf.layouts.document')

@section('title', 'Orden de compra '.$order['number'])
@section('doc_title', 'ORDEN DE COMPRA')
@section('doc_number', $order['number'])

@section('content')
    <div class="section-title">Proveedor</div>
    <table>
        <tr>
            <td style="width: 55%;">
                <strong>{{ $order['provider']['business_name'] }}</strong><br>
                {{ $order['provider']['document_type'] }} {{ $order['provider']['document_number'] }}<br>
                @if ($order['provider']['address']){{ $order['provider']['address'] }}<br>@endif
                {{ collect([$order['provider']['phone'], $order['provider']['email']])->filter()->implode(' · ') }}
            </td>
            <td style="width: 45%;">
                <table>
                    <tr><td class="muted">Fecha de emisión</td><td class="right">{{ $order['order_date'] }}</td></tr>
                    <tr><td class="muted">Entrega esperada</td><td class="right">{{ $order['expected_date'] ?? '—' }}</td></tr>
                    <tr><td class="muted">Entrega en</td><td class="right">{{ $order['warehouse'] }}</td></tr>
                    <tr><td class="muted">Condición de pago</td><td class="right">{{ $order['payment_condition'] }}@if ($order['credit_days'] > 0) ({{ $order['credit_days'] }} días)@endif</td></tr>
                    <tr><td class="muted">Moneda</td><td class="right">{{ $order['currency_code'] }}@if ((float) $order['exchange_rate'] != 1) · T.C. {{ $order['exchange_rate'] }}@endif</td></tr>
                    <tr><td class="muted">Precios</td><td class="right">{{ $order['prices_include_tax'] ? 'Incluyen IGV' : 'No incluyen IGV' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @include('pdf.partials.items', ['items' => $items, 'currency_symbol' => $order['currency_symbol']])
    @include('pdf.partials.totals', ['totals' => $totals, 'tax_rate' => $order['tax_rate'], 'currency_symbol' => $order['currency_symbol']])

    @if (!empty($order['notes']))
        <div class="section-title">Observaciones</div>
        <p>{{ $order['notes'] }}</p>
    @endif

    @if (!empty($order['terms']))
        <div class="section-title">Términos y condiciones</div>
        <p>{{ $order['terms'] }}</p>
    @endif
@endsection
