<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Clientes') · {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f5f6f8; color: #1f2937; }
        header { background: #1f2937; color: #fff; padding: 1rem 2rem; }
        header a { color: #fff; text-decoration: none; font-weight: 600; }
        main { max-width: 960px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .flash { background: #dcfce7; color: #166534; padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .6rem .5rem; border-bottom: 1px solid #e5e7eb; }
        .actions { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
        .btn { display: inline-block; padding: .45rem .9rem; border-radius: 6px; border: 0; background: #2563eb; color: #fff; text-decoration: none; font-size: .9rem; cursor: pointer; }
        .btn-secondary { background: #6b7280; }
        .btn-danger { background: #dc2626; }
        .field { margin-bottom: 1rem; }
        label { display: block; font-weight: 600; margin-bottom: .3rem; }
        input { width: 100%; box-sizing: border-box; padding: .5rem .6rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; }
        .error { color: #b91c1c; font-size: .85rem; margin-top: .3rem; }
        .muted { color: #6b7280; }
        .pagination { margin-top: 1rem; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: .5rem 1.5rem; }
        dt { font-weight: 600; }
        dd { margin: 0; }
        @media (max-width: 600px) { header, main { padding-left: 16px; padding-right: 16px; } }
    </style>
</head>
<body>
<header>
    <a href="{{ route('clients.index') }}">Clientes</a>
</header>

<main>
    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>
</body>
</html>
