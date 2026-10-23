<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Inloggen' }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
</head>
<body>
<div class="auth">
    <div style="width:100%;max-width:380px">
        <div class="brand"><span class="brand-mark" aria-hidden="true">W</span>{{ config('app.name') }}</div>
        @include('partials.flash')
        @yield('content')
    </div>
</div>
</body>
</html>
