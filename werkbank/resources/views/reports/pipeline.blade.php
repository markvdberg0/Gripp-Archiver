@extends('layouts.app', ['title' => 'Offertepijplijn'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Offertepijplijn</h1><div class="sub">{{ $offers->count() }} open offertes · {{ Format::euro($offers->sum('total_excl')) }} · gewogen {{ Format::euro($offers->sum(fn ($o) => $o->weightedValue())) }}</div></div>
    <div class="actions"><a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">CSV</a></div>
</div>
<div class="card"><div class="card-h"><h2>Per fase</h2></div><div class="card-b flush">
    <table class="list">
        <thead><tr><th>Fase</th><th class="num">Aantal</th><th class="num">Totaal excl.</th><th class="num">Gewogen</th></tr></thead>
        <tbody>@foreach ($phases as $p)<tr><td>{{ $p['name'] }}</td><td class="num">{{ $p['count'] }}</td><td class="num">{{ Format::euro($p['total']) }}</td><td class="num">{{ Format::euro($p['weighted']) }}</td></tr>@endforeach</tbody>
    </table>
</div></div>
<div class="card"><div class="card-h"><h2>Open offertes</h2></div><div class="card-b flush">
    @if ($offers->isEmpty())<div class="empty">Geen open offertes.</div>@else
    <table class="list">
        <thead><tr><th>Nr.</th><th>Omschrijving</th><th>Relatie</th><th>Fase</th><th>Status</th><th class="num">Kans</th><th class="num">Totaal</th><th class="num">Gewogen</th></tr></thead>
        <tbody>@foreach ($offers as $o)<tr><td><a href="{{ route('offertes.show', $o) }}">{{ $o->number }}</a></td><td>{{ $o->name }}</td><td>{{ $o->company?->name }}</td><td>{{ $o->phase?->name }}</td><td>@include('offers.status', ['offer' => $o])</td><td class="num">{{ $o->chance !== null ? $o->chance.'%' : '' }}</td><td class="num">{{ Format::euro($o->total_excl) }}</td><td class="num">{{ Format::euro($o->weightedValue()) }}</td></tr>@endforeach</tbody>
    </table>
    @endif
</div></div>
@endsection
