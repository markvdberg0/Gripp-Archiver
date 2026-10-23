@extends('layouts.app', ['title' => 'Inkoopopdrachten'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Inkoopopdrachten</h1><div class="sub">{{ $sum->n }} inkoopopdrachten · {{ Format::euro($sum->total) }} excl. btw</div></div>
    <div class="actions">@can('inkoop.bewerken')<a class="btn btn-primary" href="{{ route('inkoopopdrachten.create') }}">Nieuwe inkoopopdracht</a>@endcan</div>
</div>
<form class="filters" method="get">
    <div class="field"><label for="q">Zoeken</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nummer, omschrijving of referentie"></div>
    <x-keuze name="status" label="Status" :options="\App\Models\PurchaseOrder::STATUS" :value="request('status')" placeholder="Alle" />
    <x-keuze name="leverancier" label="Leverancier" :options="$suppliers" :value="request('leverancier')" placeholder="Alle" />
    <x-keuze name="archief" label="Archief" :options="['' => 'Zonder gearchiveerde', '1' => 'Met gearchiveerde']" :value="request('archief')" />
    <button class="btn" type="submit">Filteren</button>
</form>
<div class="card"><div class="card-b flush">
    @if ($orders->isEmpty())<div class="empty">Geen inkoopopdrachten gevonden.</div>@else
    <table class="list">
        <thead><tr><th>Nr.</th><th>Datum</th><th>Omschrijving</th><th>Leverancier</th><th>Opdracht</th><th>Status</th><th>Levering</th><th class="num">Totaal excl.</th></tr></thead>
        <tbody>
        @foreach ($orders as $o)
            <tr>
                <td><a href="{{ route('inkoopopdrachten.show', $o) }}">{{ $o->number }}</a></td>
                <td class="nowrap">{{ Format::date($o->date) }}</td>
                <td><a href="{{ route('inkoopopdrachten.show', $o) }}">{{ $o->name }}</a> @if ($o->archived)<span class="badge">archief</span>@endif</td>
                <td>{{ $o->supplier?->name }}</td>
                <td>@if ($o->project)<a href="{{ route('opdrachten.show', $o->project) }}">{{ $o->project->number }}</a>@endif</td>
                <td><span class="badge {{ ['afgerond' => 'badge-ok', 'bevestigd' => 'badge-accent', 'verzonden' => 'badge-accent'][$o->status] ?? '' }}">{{ \App\Models\PurchaseOrder::STATUS[$o->status] ?? $o->status }}</span></td>
                <td class="nowrap">{{ Format::date($o->delivery_date) }}</td>
                <td class="num">{{ Format::euro($o->total_excl) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $orders->links() }}
    @endif
</div></div>
@endsection
