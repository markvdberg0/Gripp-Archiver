@extends('layouts.app', ['title' => 'Opdrachten'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Opdrachten</h1><div class="sub">{{ $sum->n }} opdrachten · {{ Format::euro($sum->total) }} excl. btw</div></div>
    <div class="actions">@can('opdrachten.bewerken')<a class="btn btn-primary" href="{{ route('opdrachten.create') }}">Nieuwe opdracht</a>@endcan</div>
</div>
<form class="filters" method="get">
    <div class="field"><label for="q">Zoeken</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nummer, omschrijving of referentie"></div>
    <x-keuze name="fase" label="Fase" :options="$phases" :value="request('fase')" placeholder="Alle fasen" />
    <x-keuze name="accountmanager" label="Accountmanager" :options="$employees" :value="request('accountmanager')" placeholder="Iedereen" />
    <x-keuze name="archief" label="Archief" :options="['' => 'Zonder gearchiveerde', '1' => 'Met gearchiveerde']" :value="request('archief')" />
    <button class="btn" type="submit">Filteren</button>
</form>
<div class="card"><div class="card-b flush">
    @if ($projects->isEmpty())<div class="empty">Geen opdrachten gevonden.</div>@else
    <table class="list">
        <thead><tr>
            <th><x-sorteer field="number" label="Nr." :default="true" default-dir="desc" /></th><th><x-sorteer field="name" label="Omschrijving" /></th><th>Relatie</th><th>Fase</th>
            <th><x-sorteer field="start" label="Start" /></th><th><x-sorteer field="deadline" label="Deadline" /></th><th class="num">Uren</th><th class="num"><x-sorteer field="total" label="Totaal excl." /></th>
        </tr></thead>
        <tbody>
        @foreach ($projects as $p)
            <tr>
                <td><a href="{{ route('opdrachten.show', $p) }}">{{ $p->number }}</a></td>
                <td><a href="{{ route('opdrachten.show', $p) }}">{{ $p->name }}</a> @if ($p->archived)<span class="badge">archief</span>@endif</td>
                <td>{{ $p->company?->name }}</td>
                <td>{{ $p->phase?->name }}</td>
                <td class="nowrap">{{ Format::date($p->start_date) }}</td>
                <td class="nowrap {{ $p->deadline && $p->deadline->isPast() && ! $p->archived ? 'neg' : '' }}">{{ Format::date($p->deadline) }}</td>
                <td class="num">{{ $p->hours_written ? Format::number($p->hours_written) : '' }}</td>
                <td class="num">{{ Format::euro($p->total_excl) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $projects->links() }}
    @endif
</div></div>
@endsection
