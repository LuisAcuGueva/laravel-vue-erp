@extends('pdf.layouts.document')

@section('title', 'Nota de Crédito '.$note->full_number)
@section('doc_title', 'NOTA DE CRÉDITO ELECTRÓNICA')
@section('doc_number', $note->full_number)

@section('content')
    <div class="section-title">Datos del Comprobante Modificado y Motivo</div>
    <table style="margin-bottom: 12px;">
        <tr>
            <td style="width: 50%;">
                <table>
                    <tr><td class="muted" style="width: 45%;">Doc. que modifica:</td><td><strong>{{ $note->sale->full_number }}</strong></td></tr>
                    <tr><td class="muted">Fecha doc. origen:</td><td>{{ $note->sale->issue_date instanceof \DateTimeInterface ? $note->sale->issue_date->format('d/m/Y') : $note->sale->issue_date }}</td></tr>
                    <tr><td class="muted">Fecha de emisión:</td><td>{{ $note->issue_date instanceof \DateTimeInterface ? $note->issue_date->format('d/m/Y') : $note->issue_date }}</td></tr>
                    <tr><td class="muted">Moneda:</td><td>{{ $note->currency->code }} ({{ $note->currency->symbol }})</td></tr>
                </table>
            </td>
            <td style="width: 50%;">
                <table>
                    <tr><td class="muted" style="width: 35%;">Tipo de nota:</td><td>[{{ $note->reason_code->value }}] {{ $note->reason_code->description() }}</td></tr>
                    <tr><td class="muted">Sustento:</td><td>{{ $note->reason_description }}</td></tr>
                    @if ($note->warehouse)
                        <tr><td class="muted">Almacén dev.:</td><td>{{ $note->warehouse->name }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title">Cliente</div>
    <table>
        <tr>
            <td style="width: 60%;">
                <strong>{{ $note->client_name }}</strong><br>
                @if ($note->client_document_number)
                    {{ $note->client_document_type ?? 'DOC' }}: {{ $note->client_document_number }}<br>
                @endif
                @if ($note->client_address)
                    Dirección: {{ $note->client_address }}<br>
                @endif
            </td>
            <td style="width: 40%; text-align: right;">
                <div style="font-size: 11px;">
                    @if ((float) $note->applied_to_balance > 0)
                        <span>Aplicado a saldo: <strong>{{ $note->currency->symbol }} {{ number_format((float) $note->applied_to_balance, 2) }}</strong></span><br>
                    @endif
                    @if ((float) $note->refunded_amount > 0)
                        <span>Reembolso: <strong>{{ $note->currency->symbol }} {{ number_format((float) $note->refunded_amount, 2) }}</strong></span><br>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 14px;">
        <thead>
            <tr>
                <th style="width: 5%;" class="center">#</th>
                <th style="width: 50%;">Descripción</th>
                <th style="width: 10%;" class="right">Cant.</th>
                <th style="width: 15%;" class="right">P. Unit</th>
                <th style="width: 20%;" class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($note->items as $item)
                <tr>
                    <td class="center">{{ $item->line }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="right">{{ $note->currency->symbol }} {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ $note->currency->symbol }} {{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top: 14px;">
        @if ((float) $note->taxable_base > 0)
            <tr>
                <td class="label">Op. Gravadas:</td>
                <td class="value">{{ $note->currency->symbol }} {{ number_format((float) $note->taxable_base, 2) }}</td>
            </tr>
        @endif
        @if ((float) $note->exempt_base > 0)
            <tr>
                <td class="label">Op. Exoneradas:</td>
                <td class="value">{{ $note->currency->symbol }} {{ number_format((float) $note->exempt_base, 2) }}</td>
            </tr>
        @endif
        @if ((float) $note->unaffected_base > 0)
            <tr>
                <td class="label">Op. Inafectas:</td>
                <td class="value">{{ $note->currency->symbol }} {{ number_format((float) $note->unaffected_base, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">IGV ({{ number_format((float) $note->tax_rate, 0) }}%):</td>
            <td class="value">{{ $note->currency->symbol }} {{ number_format((float) $note->tax_total, 2) }}</td>
        </tr>
        <tr class="grand-total">
            <td class="label">TOTAL:</td>
            <td class="value">{{ $note->currency->symbol }} {{ number_format((float) $note->total, 2) }}</td>
        </tr>
    </table>

    <div style="clear: both; margin-top: 12px; font-size: 10px;" class="muted">
        SON: {{ $amount_in_words }}
    </div>

    @if ($note->cancel_reason)
        <div style="margin-top: 15px; padding: 8px; border: 1px solid #e53e3e; color: #c53030; font-size: 11px;">
            <strong>MOTIVO DE ANULACIÓN:</strong> {{ $note->cancel_reason }}
        </div>
    @endif
@endsection
