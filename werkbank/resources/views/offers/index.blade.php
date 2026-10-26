@extends('layouts.app', ['title' => 'Offertes'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Offertes</h1><div class="sub">{{ $sum->n }} offertes · {{ Format::euro($sum->total) }} excl. btw · gewogen {{ Format::euro($sum->weighted) }}</div></div>
    <div class="actions">@can('offertes.bewerken')<a class="btn btn-primary" href="{{ route('offertes.create') }}">Nieuwe offerte</a>@endcan</div>
</div>
<form class="filters" method="get">
    <div class="field"><label for="q">Zoeken</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nummer, omschrijving of referentie"></div>
    <x-keuze name="status" label="Status" :options="['open' => 'Open (concept en verzonden)', 'concept' => 'Concept', 'verzonden' => 'Verzonden', 'geaccepteerd' => 'Geaccepteerd', 'afgewezen' => 'Afgewezen']" :value="request('status')" placeholder="Alle" />
    <x-keuze name="fase" label="Fase" :options="$phases" :value="request('fase')" placeholder="Alle fasen" />
    <x-keuze name="accountmanager" label="Accountmanager" :options="$employees" :value="request('accountmanager')" placeholder="Iedereen" />
    <x-keuze name="archief" label="Archief" :options="['' => 'Zonder gearchiveerde', '1' => 'Met gearchiveerde']" :value="request('archief')" />
    <x-keuze name="sjablonen" label="Soort" :options="['' => 'Offertes', '1' => 'Sjablonen']" :value="request('sjablonen')" />
    <button class="btn" type="submit">Filteren</button>
</form>
<div class="card"><div class="card-b flush">
    @if ($offers->isEmpty())<div class="empty">Geen offertes gevonden.</div>@else
    <table class="list">
        <thead><tr>
            <th><x-sorteer field="number" label="Nr." :default="true" default-dir="desc" /></th><th><x-sorteer field="date" label="Datum" /></th><th><x-sorteer field="name" label="Omschrijving" /></th>
            <th>Relatie</th><th>Fase</th><th>Status</th><th class="num">Kans</th><th class="num"><x-sorteer field="total" label="Totaal excl." /></th>
        </tr></thead>
        <tbody>
        @foreach ($offers as $o)
            <tr>
                <td><a href="{{ route('offertes.show', $o) }}">{{ $o->number ?? '—' }}</a></td>
                <td class="nowrap">{{ Format::date($o->date) }}</td>
                <td><a href="{{ route('offertes.show', $o) }}">{{ $o->name }}</a> @if ($o->archived)<span class="badge">archief</span>@endif</td>
                <td>{{ $o->company?->name }}</td>
                <td>{{ $o->phase?->name }}</td>
                <td>@include('offers.status', ['offer' => $o])</td>
                <td class="num">{{ $o->chance !== null ? $o->chance.'%' : '' }}</td>
                <td class="num">{{ Format::euro($o->total_excl) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $offers->links() }}
    @endif
</div></div>
@endsection
