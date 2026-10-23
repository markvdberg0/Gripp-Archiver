@extends('layouts.app', ['title' => 'Memoriaal en kosten'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head"><div><h1>Memoriaal en kosten</h1><div class="sub">Alleen-lezen archief uit Gripp. Nieuwe boekingen maak je in het boekhoudpakket.</div></div></div>
<form class="filters" method="get"><x-veld name="jaar" label="Jaar" type="number" :value="$year" style="width:100px" /><button class="btn" type="submit">Filteren</button></form>
<div class="card"><div class="card-h"><h2>Memoriaalboekingen</h2><span class="muted small">{{ $memorials->count() }}</span></div><div class="card-b flush">
    @if ($memorials->isEmpty())<div class="empty">Geen memoriaalboekingen.</div>@else
    <table class="list">
        <thead><tr><th>Nr.</th><th>Datum</th><th>Soort</th><th>Omschrijving / grootboek</th><th>Btw</th><th class="num">Bedrag</th></tr></thead>
        <tbody>
        @foreach ($memorials as $m)
            <tr class="group"><td>{{ $m->number }}</td><td>{{ Format::date($m->date) }}</td><td>{{ $m->type }}</td><td colspan="2">{{ $m->name }} {{ $m->description }}</td><td class="num">{{ Format::euro($m->lines->sum('amount')) }}</td></tr>
            @foreach ($m->lines as $l)
                <tr><td></td><td></td><td></td><td>{{ $l->ledger?->label() }} <span class="muted">{{ $l->description }}</span></td><td class="small">{{ $l->vatRate?->name }}</td><td class="num">{{ Format::euro($l->amount) }}</td></tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
    @endif
</div></div>
<div class="card"><div class="card-h"><h2>Kosten</h2><span class="muted small">{{ $costs->total() }}</span></div><div class="card-b flush">
    @if ($costs->isEmpty())<div class="empty">Geen kosten.</div>@else
    <table class="list">
        <thead><tr><th>Nr.</th><th>Datum</th><th>Omschrijving</th><th>Grootboek</th><th>Kostenplaats</th><th class="num">Bedrag</th></tr></thead>
        <tbody>@foreach ($costs as $c)<tr><td>{{ $c->number }}</td><td>{{ Format::date($c->date) }}</td><td>{{ $c->description }}</td><td>{{ $c->ledger?->label() }}</td><td>{{ $c->costCenter?->label() }}</td><td class="num">{{ Format::euro($c->amount) }}</td></tr>@endforeach</tbody>
    </table>
    {{ $costs->links() }}
    @endif
</div></div>
@endsection
