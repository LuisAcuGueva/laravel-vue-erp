<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Documento')</title>
    <style>
        @page { margin: 32px 36px 56px 36px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #222; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .company-name { font-size: 14px; font-weight: bold; }
        .muted { color: #666; }
        .doc-box { border: 1px solid #333; padding: 8px; text-align: center; }
        .doc-box .doc-title { font-size: 12px; font-weight: bold; }
        .doc-box .doc-number { font-size: 13px; font-weight: bold; margin-top: 4px; }
        .section-title { font-weight: bold; background: #eee; padding: 4px 6px; margin-top: 12px; }
        .items th { background: #333; color: #fff; padding: 5px 4px; font-size: 9px; text-align: left; }
        .items td { border-bottom: 1px solid #ddd; padding: 4px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .watermark {
            position: fixed; top: 38%; left: 8%; width: 84%;
            text-align: center; font-size: 96px; font-weight: bold;
            color: #e5e5e5; transform: rotate(-30deg); z-index: -1;
        }
        .footer { position: fixed; bottom: -36px; left: 0; right: 0; text-align: center; font-size: 8px; color: #888; }
    </style>
</head>
<body>
@if (!empty($watermark))
    <div class="watermark">{{ $watermark }}</div>
@endif

<table class="header">
    <tr>
        <td style="width: 60%;">
            @if (!empty($logo_data_uri))
                <img src="{{ $logo_data_uri }}" alt="Logo" style="max-height: 56px; max-width: 160px; margin-bottom: 4px;">
            @endif
            <div class="company-name">{{ $company->legal_name }}</div>
            @if ($company->trade_name)
                <div>{{ $company->trade_name }}</div>
            @endif
            @if ($company->address)
                <div class="muted">{{ $company->address }}</div>
            @endif
            <div class="muted">
                {{ collect([$company->phone, $company->email])->filter()->implode(' · ') }}
            </div>
        </td>
        <td style="width: 40%;">
            <div class="doc-box">
                @if ($company->ruc)
                    <div>RUC {{ $company->ruc }}</div>
                @endif
                <div class="doc-title">@yield('doc_title')</div>
                <div class="doc-number">@yield('doc_number')</div>
            </div>
        </td>
    </tr>
</table>

@yield('content')

<div class="footer">
    {{ $company->legal_name }}@if ($company->ruc) · RUC {{ $company->ruc }}@endif · Generado el {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
