@extends('layouts.app', ['title' => 'Relatie samenvoegen'])

@php
    $labels = [
        'contacts' => 'Contactpersonen', 'timeline_entries' => 'Timeline-items', 'offers' => 'Offertes', 'projects' => 'Opdrachten',
        'tasks' => 'Taken', 'contracts' => 'Contracten', 'invoices' => 'Verkoopfacturen', 'products' => 'Producten (als leverancier)',
        'purchase_orders' => 'Inkoopopdrachten', 'purchase_invoices' => 'Inkoopfacturen', 'attachments' => 'Bestanden', 'taggables' => 'Labels',
    ];
    $financial = ['iban', 'bic', 'bank_account_holder'];
@endphp

@section('content')
<div class="page-head">
    <div><h1>Relatie samenvoegen</h1><div class="sub">Opheffen: {{ $company->number }} {{ $company->name }}</div></div>
    <div class="actions"><a class="btn" href="{{ route('relaties.show', $company) }}">Annuleren</a></div>
</div>

@unless ($target)
<div class="card">
    <div class="card-h"><h2>Met welke relatie samenvoegen?</h2></div>
    <div class="card-b">
        <p class="small">Kies de relatie die <strong>blijft</strong>. Alles van {{ $company->name }} gaat daarnaartoe.</p>
        @if ($candidates->isNotEmpty())
            <p class="small muted">Mogelijke dubbelingen:</p>
            <ul>
                @foreach ($candidates as $c)
                    <li><a href="{{ route('relaties.samenvoegen', [$company, 'doel' => $c->id]) }}">{{ $c->number }} {{ $c->name }}</a> <span class="muted">{{ $c->visit_city }}</span></li>
                @endforeach
            </ul>
        @endif
        <form class="filters" method="get">
            <div class="field"><label for="q">Zoek relatie</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Naam, plaats of nummer"></div>
            <button class="btn" type="submit">Zoeken</button>
        </form>
        @if ($results->isNotEmpty())
            <ul>
                @foreach ($results as $c)
                    <li><a href="{{ route('relaties.samenvoegen', [$company, 'doel' => $c->id]) }}">{{ $c->number }} {{ $c->name }}</a> <span class="muted">{{ $c->visit_city }}</span></li>
                @endforeach
            </ul>
        @elseif (request('q'))
            <p class="muted small">Niets gevonden.</p>
        @endif
    </div>
</div>
@else
<div class="grid grid-2">
    <div class="card">
        <div class="card-h"><h2>Verdwijnt</h2></div>
        <div class="card-b">
            <dl class="props">
                <dt>Relatie</dt><dd>{{ $company->number }} {{ $company->name }}</dd>
                <dt>Plaats</dt><dd>{{ $company->visit_city ?: '—' }}</dd>
                <dt>E-mail</dt><dd>{{ $company->email ?: '—' }}</dd>
                <dt>KvK / btw</dt><dd>{{ $company->coc_number ?: '—' }} / {{ $company->vat_number ?: '—' }}</dd>
            </dl>
        </div>
    </div>
    <div class="card">
        <div class="card-h"><h2>Blijft</h2></div>
        <div class="card-b">
            <dl class="props">
                <dt>Relatie</dt><dd><a href="{{ route('relaties.show', $target) }}">{{ $target->number }} {{ $target->name }}</a></dd>
                <dt>Plaats</dt><dd>{{ $target->visit_city ?: '—' }}</dd>
                <dt>E-mail</dt><dd>{{ $target->email ?: '—' }}</dd>
                <dt>KvK / btw</dt><dd>{{ $target->coc_number ?: '—' }} / {{ $target->vat_number ?: '—' }}</dd>
            </dl>
            <p class="small"><a href="{{ route('relaties.samenvoegen', $company) }}">Andere relatie kiezen</a></p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-h"><h2>Wat er gebeurt</h2></div>
    <div class="card-b">
        <table class="list">
            <thead><tr><th>Naar {{ $target->name }}</th><th class="num">Aantal</th></tr></thead>
            <tbody>
            @foreach ($preview as $table => $n)
                @if ($n > 0)<tr><td>{{ $labels[$table] ?? $table }}</td><td class="num">{{ $n }}</td></tr>@endif
            @endforeach
            @if (array_sum($preview) === 0)<tr><td colspan="2" class="muted">Geen gekoppelde gegevens.</td></tr>@endif
            </tbody>
        </table>
        @if ($fill)
            <p class="small">Lege velden bij {{ $target->name }} die worden aangevuld:
                {{ collect(array_keys($fill))->map(fn ($f) => in_array($f, $financial, true) && ! auth()->user()->can('relaties.financieel') ? 'bankgegevens' : str_replace('_', ' ', $f))->unique()->implode(', ') }}.
            </p>
        @endif
        <p class="small">{{ $company->name }} wordt daarna verwijderd (zacht: terug te zien in het logboek). Een volgende Gripp-import zet deze relatie niet terug; gegevens die in Gripp nog aan {{ $company->name }} hangen, komen voortaan bij {{ $target->name }} terecht. <strong>Samenvoegen is niet ongedaan te maken</strong>, behalve door een back-up terug te zetten.</p>

        <form method="post" action="{{ route('relaties.samenvoegen.store', $company) }}">
            @csrf
            <input type="hidden" name="doel" value="{{ $target->id }}">
            @if ($fill)<x-vink name="aanvullen" label="Lege velden aanvullen met gegevens van de opgeheven relatie" :checked="true" />@endif
            <x-vink name="bevestig" label="Ik begrijp dat dit niet ongedaan te maken is" />
            @error('doel')<div class="error-text">{{ $message }}</div>@enderror
            <button class="btn btn-danger" type="submit">Samenvoegen</button>
        </form>
    </div>
</div>
@endunless
@endsection
