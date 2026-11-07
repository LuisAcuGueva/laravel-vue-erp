@extends('pdf.layouts.document')

@section('title', 'Estado de Cuenta - ' . $client['name'])
@section('doc_title', 'ESTADO DE CUENTA')
@section('doc_number', $from . ' al ' . $to)

@section('content')
    <div class="section-title">Datos del Cliente</div>
    <table>
        <tr>
            <td style="width: 55%;">
                <strong>{{ $client['name'] }}</strong><br>
                {{ $client['document_type'] ?? 'DOC' }}: {{ $client['document_number'] ?? '—' }}<br>
                @if (!empty($client['address']))
                    Dirección: {{ $client['address'] }}<br>
                @endif
                @if (!empty($client['phone']))
                    Teléfono: {{ $client['phone'] }}
                @endif
            </td>
            <td style="width: 45%;">
                <table>
                    <tr><td class="muted">Período:</td><td class="right">{{ $from }} al {{ $to }}</td></tr>
                    <tr><td class="muted">Moneda:</td><td class="right">{{ $currency['code'] }} ({{ $currency['symbol'] }})</td></tr>
                    @if (!empty($client['credit_limit']))
                        <tr><td class="muted">Línea de Crédito:</td><td class="right">{{ $currency['symbol'] }} {{ number_format((float) $client['credit_limit'], 2) }}</td></tr>
                    @endif
                    @if (!empty($client['credit_days']))
                        <tr><td class="muted">Días de Crédito:</td><td class="right">{{ $client['credit_days'] }} días</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 14px; border: 1px solid #ccc; background: #fcfcfc;">
        <tr>
            <td style="padding: 6px; text-align: center; border-right: 1px solid #ddd;">
                <div class="muted" style="font-size: 8px;">SALDO ANTERIOR</div>
                <div style="font-size: 11px; font-weight: bold;">{{ $currency['symbol'] }} {{ number_format((float) $opening_balance, 2) }}</div>
            </td>
            <td style="padding: 6px; text-align: center; border-right: 1px solid #ddd;">
                <div class="muted" style="font-size: 8px;">TOTAL CARGOS (+)</div>
                <div style="font-size: 11px; font-weight: bold; color: #b71c1c;">{{ $currency['symbol'] }} {{ number_format((float) $total_debits, 2) }}</div>
            </td>
            <td style="padding: 6px; text-align: center; border-right: 1px solid #ddd;">
                <div class="muted" style="font-size: 8px;">TOTAL ABONOS (-)</div>
                <div style="font-size: 11px; font-weight: bold; color: #1b5e20;">{{ $currency['symbol'] }} {{ number_format((float) $total_credits, 2) }}</div>
            </td>
            <td style="padding: 6px; text-align: center;">
                <div class="muted" style="font-size: 8px;">SALDO ACTUAL</div>
                <div style="font-size: 12px; font-weight: bold; color: #0d47a1;">{{ $currency['symbol'] }} {{ number_format((float) $closing_balance, 2) }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title" style="margin-top: 14px;">Movimientos del Período</div>
    <table class="items" style="margin-top: 6px;">
        <thead>
            <tr>
                <th style="width: 12%;">Fecha</th>
                <th style="width: 15%;">Tipo</th>
                <th style="width: 20%;">Documento</th>
                <th style="width: 20%;">Concepto</th>
                <th style="width: 11%;" class="right">Cargos (+)</th>
                <th style="width: 11%;" class="right">Abonos (-)</th>
                <th style="width: 11%;" class="right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background: #f7f7f7;">
                <td colspan="6"><em>Saldo Inicial al {{ $from }}</em></td>
                <td class="right"><strong>{{ number_format((float) $opening_balance, 2) }}</strong></td>
            </tr>
            @forelse ($movements as $m)
                <tr>
                    <td>{{ $m['date'] }}</td>
                    <td>{{ $m['type'] === 'sale' ? 'Venta Crédito' : 'Cobranza' }}</td>
                    <td><strong>{{ $m['number'] }}</strong></td>
                    <td>{{ $m['concept'] }}</td>
                    <td class="right">{{ (float)$m['debit'] > 0 ? number_format((float)$m['debit'], 2) : '—' }}</td>
                    <td class="right">{{ (float)$m['credit'] > 0 ? number_format((float)$m['credit'], 2) : '—' }}</td>
                    <td class="right"><strong>{{ number_format((float)$m['balance'], 2) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center muted" style="padding: 10px;">Sin movimientos en el período seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if (!empty($unpaid_installments) && count($unpaid_installments) > 0)
        <div class="section-title" style="margin-top: 14px;">Cuotas Pendientes de Pago</div>
        <table class="items" style="margin-top: 6px;">
            <thead>
                <tr>
                    <th style="width: 25%;">Comprobante Venta</th>
                    <th style="width: 15%;" class="center">N° Cuota</th>
                    <th style="width: 15%;" class="center">Vencimiento</th>
                    <th style="width: 15%;" class="right">Monto Original</th>
                    <th style="width: 15%;" class="right">Pagado</th>
                    <th style="width: 15%;" class="right">Saldo Pendiente</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($unpaid_installments as $inst)
                    <tr>
                        <td><strong>{{ $inst['sale_full_number'] }}</strong></td>
                        <td class="center">Cuota {{ $inst['number'] }}</td>
                        <td class="center">{{ $inst['due_date'] }}</td>
                        <td class="right">{{ number_format((float) $inst['amount'], 2) }}</td>
                        <td class="right">{{ number_format((float) $inst['paid_amount'], 2) }}</td>
                        <td class="right" style="color: #b71c1c; font-weight: bold;">{{ number_format((float) $inst['balance'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
