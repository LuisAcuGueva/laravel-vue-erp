@extends('pdf.layouts.document')

@section('title', 'Recibo de Cobranza ' . $collection['number'])
@section('doc_title', 'RECIBO DE COBRANZA')
@section('doc_number', $collection['number'])

@section('content')
    <div class="section-title">Datos del Cliente</div>
    <table>
        <tr>
            <td style="width: 55%;">
                <strong>{{ $collection['client']['name'] }}</strong><br>
                {{ $collection['client']['document_type'] }}: {{ $collection['client']['document_number'] }}<br>
                @if (!empty($collection['client']['address']))
                    Dirección: {{ $collection['client']['address'] }}<br>
                @endif
                @if (!empty($collection['client']['phone']))
                    Teléfono: {{ $collection['client']['phone'] }}
                @endif
            </td>
            <td style="width: 45%;">
                <table>
                    <tr><td class="muted">Fecha de cobro:</td><td class="right">{{ $collection['collection_date'] }}</td></tr>
                    <tr><td class="muted">Sede:</td><td class="right">{{ $collection['branch_name'] ?? '—' }}</td></tr>
                    <tr><td class="muted">Registrado por:</td><td class="right">{{ $collection['creator_name'] ?? '—' }}</td></tr>
                    <tr><td class="muted">Moneda:</td><td class="right">{{ $collection['currency_code'] }} ({{ $collection['currency_symbol'] }})</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title" style="margin-top: 14px;">Detalle de Cuotas Amortizadas</div>
    <table class="items" style="margin-top: 6px;">
        <thead>
            <tr>
                <th style="width: 10%;" class="center">#</th>
                <th style="width: 35%;">Comprobante Venta</th>
                <th style="width: 20%;" class="center">N° Cuota</th>
                <th style="width: 15%;" class="center">Vencimiento</th>
                <th style="width: 20%;" class="right">Importe Cobrado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($allocations as $idx => $alloc)
                <tr>
                    <td class="center">{{ $idx + 1 }}</td>
                    <td><strong>{{ $alloc['sale_number'] }}</strong></td>
                    <td class="center">Cuota {{ $alloc['installment_number'] }}</td>
                    <td class="center">{{ $alloc['due_date'] }}</td>
                    <td class="right">{{ $collection['currency_symbol'] }} {{ number_format((float) $alloc['amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="center muted" style="padding: 12px;">Sin asignaciones específicas registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table style="margin-top: 16px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                @if (!empty($collection['notes']))
                    <div style="font-size: 9px; padding: 6px; background: #fafafa; border: 1px solid #eee;">
                        <strong>Observaciones:</strong><br>
                        {{ $collection['notes'] }}
                    </div>
                @endif
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table style="border: 1px solid #333; background: #fdfdfd;">
                    <tr>
                        <td style="padding: 8px; font-size: 11px; font-weight: bold;">TOTAL COBRADO</td>
                        <td style="padding: 8px; font-size: 12px; font-weight: bold;" class="right">
                            {{ $collection['currency_symbol'] }} {{ number_format((float) $collection['total_amount'], 2) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
