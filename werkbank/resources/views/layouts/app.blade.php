<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
</head>
<body>
@php
    $nav = [
        'Verkoop' => [
            ['relaties.index', 'Relaties', 'relaties.bekijken', 'relaties.*'],
            ['contactpersonen.index', 'Contactpersonen', 'relaties.bekijken', 'contactpersonen.*'],
            ['offertes.index', 'Offertes', 'offertes.bekijken', 'offertes.*'],
            ['opdrachten.index', 'Opdrachten', 'opdrachten.bekijken', 'opdrachten.*'],
            ['producten.index', 'Producten', 'producten.bekijken', 'producten.*'],
            ['pakketten.index', 'Pakketten', 'producten.bekijken', 'pakketten.*'],
        ],
        'Planning' => [
            ['taken.index', 'Taken', 'opdrachten.bekijken', 'taken.*'],
            ['planning.index', 'Planning', 'opdrachten.bekijken', 'planning.*'],
            ['uren.index', 'Uren', 'uren.schrijven', 'uren.*'],
            ['verlof.index', 'Verlof', 'verlof.aanvragen', 'verlof.*'],
        ],
        'Financieel' => [
            ['facturen.index', 'Verkoopfacturen', 'facturen.bekijken', 'facturen.*'],
            ['contracten.index', 'Contracten', 'facturen.bekijken', 'contracten.*'],
            ['inkoopopdrachten.index', 'Inkoopopdrachten', 'inkoop.bekijken', 'inkoopopdrachten.*'],
            ['inkoopfacturen.index', 'Inkoopfacturen', 'inkoop.bekijken', 'inkoopfacturen.*'],
            ['rapporten.index', 'Overzichten', 'rapporten.bekijken', 'rapporten.*'],
            ['boekingen.index', 'Memoriaal en kosten', 'rapporten.bekijken', 'boekingen.*'],
        ],
        'Beheer' => [
            ['beheer.medewerkers.index', 'Medewerkers', 'beheer.instellingen', 'beheer.medewerkers.*'],
            ['beheer.stamgegevens.index', 'Stamgegevens', 'beheer.instellingen', 'beheer.stamgegevens.*'],
            ['beheer.instellingen.edit', 'Instellingen', 'beheer.instellingen', 'beheer.instellingen.*'],
            ['beheer.gebruikers.index', 'Gebruikers', 'beheer.gebruikers', 'beheer.gebruikers.*'],
            ['beheer.import.index', 'Import', 'beheer.import', 'beheer.import.*'],
            ['beheer.backups.index', 'Back-ups', 'beheer.import', 'beheer.backups.*'],
            ['beheer.logboek', 'Logboek', 'beheer.auditlog', 'beheer.logboek'],
        ],
    ];
@endphp
<div class="shell">
    <aside class="side">
        <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark" aria-hidden="true">W</span>{{ config('app.name') }}</a>
        <a href="{{ route('dashboard') }}" class="nav {{ request()->routeIs('dashboard') ? 'active' : '' }}">Overzicht</a>
        @foreach ($nav as $group => $items)
            @php $visible = array_filter($items, fn ($i) => Route::has($i[0]) && Gate::allows($i[2])); @endphp
            @if ($visible)
                <div class="nav-group">{{ $group }}</div>
                @foreach ($visible as [$route, $label, $ability, $pattern])
                    <a href="{{ route($route) }}" class="nav {{ request()->routeIs($pattern) ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
            @endif
        @endforeach
    </aside>
    <div class="main">
        <header class="topbar">
            <form class="search" method="get" action="{{ route('zoeken') }}" role="search">
                <input type="search" name="q" value="{{ request()->routeIs('zoeken') ? request('q') : '' }}" placeholder="Zoeken in relaties, offertes, opdrachten, facturen…" aria-label="Zoeken">
            </form>
            <div class="user">
                <span>{{ auth()->user()->name }} · {{ auth()->user()->roleLabel() }}</span>
                <a href="{{ route('account.edit') }}">Account</a>
                <form method="post" action="{{ route('logout') }}" class="inline">@csrf<button class="btn-link" type="submit">Uitloggen</button></form>
            </div>
        </header>
        <main class="content">
            @include('partials.flash')
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
