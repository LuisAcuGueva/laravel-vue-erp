{{-- Totales. Espera $totals: ['taxable_base','exempt_base','unaffected_base','discount_total','tax_total','total'], $tax_rate y $currency_symbol. --}}
<table style="width: 40%; margin-left: 60%; margin-top: 10px;">
    @if ((float) $totals['taxable_base'] > 0)
        <tr><td>Op. gravada</td><td class="right">{{ $currency_symbol }} {{ number_format((float) $totals['taxable_base'], 2, '.', ',') }}</td></tr>
    @endif
    @if ((float) $totals['exempt_base'] > 0)
        <tr><td>Op. exonerada</td><td class="right">{{ $currency_symbol }} {{ number_format((float) $totals['exempt_base'], 2, '.', ',') }}</td></tr>
    @endif
    @if ((float) $totals['unaffected_base'] > 0)
        <tr><td>Op. inafecta</td><td class="right">{{ $currency_symbol }} {{ number_format((float) $totals['unaffected_base'], 2, '.', ',') }}</td></tr>
    @endif
    @if ((float) $totals['discount_total'] > 0)
        <tr><td>Descuentos</td><td class="right">- {{ $currency_symbol }} {{ number_format((float) $totals['discount_total'], 2, '.', ',') }}</td></tr>
    @endif
    <tr><td>IGV ({{ rtrim(rtrim(number_format((float) $tax_rate, 2), '0'), '.') }}%)</td><td class="right">{{ $currency_symbol }} {{ number_format((float) $totals['tax_total'], 2, '.', ',') }}</td></tr>
    <tr style="font-weight: bold; font-size: 12px;"><td style="border-top: 1px solid #333;">TOTAL</td><td class="right" style="border-top: 1px solid #333;">{{ $currency_symbol }} {{ number_format((float) $totals['total'], 2, '.', ',') }}</td></tr>
</table>
