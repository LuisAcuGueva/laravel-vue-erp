{{-- Tabla de líneas. Espera $items: lista de ['code','description','unit','quantity','unit_price','discount_percent','total'] y $currency_symbol. --}}
<table class="items" style="margin-top: 10px;">
    <thead>
        <tr>
            <th style="width: 4%;">#</th>
            <th style="width: 40%;">Descripción</th>
            <th style="width: 9%;">Unidad</th>
            <th class="right" style="width: 10%;">Cant.</th>
            <th class="right" style="width: 12%;">P. unit.</th>
            <th class="right" style="width: 8%;">Dscto.</th>
            <th class="right" style="width: 17%;">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    @if (!empty($item['code']))<span class="muted">{{ $item['code'] }}</span><br>@endif
                    {{ $item['description'] }}
                </td>
                <td>{{ $item['unit'] ?? '' }}</td>
                <td class="right">{{ rtrim(rtrim(number_format((float) $item['quantity'], 4, '.', ','), '0'), '.') }}</td>
                <td class="right">{{ number_format((float) $item['unit_price'], 4, '.', ',') }}</td>
                <td class="right">{{ number_format((float) $item['discount_percent'], 2) }}%</td>
                <td class="right">{{ number_format((float) $item['total'], 2, '.', ',') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
