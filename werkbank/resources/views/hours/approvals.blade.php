@extends('layouts.app', ['title' => 'Uren fiatteren'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head"><div><h1>Uren fiatteren</h1><div class="sub">Ingediende (definitieve) uren die nog gefiatteerd moeten worden.</div></div>
    <div class="actions"><a class="btn" href="{{ route('uren.index') }}">Naar uren</a></div></div>
<form class="filters" method="get">
    <x-keuze name="medewerker" label="Medewerker" :options="$employees" :value="request('medewerker')" placeholder="Iedereen" />
    <x-keuze name="opdracht" label="Opdracht" :options="$projects" :value="request('opdracht')" placeholder="Alle" />
    <button class="btn" type="submit">Filteren</button>
</form>
<form method="post" action="{{ route('uren.fiatteren.opslaan') }}" class="card">
    @csrf
    <div class="card-b flush">
        @if ($hours->isEmpty())<div class="empty">Er staan geen uren klaar.</div>@else
        <table class="list">
            <thead><tr><th></th><th>Datum</th><th>Medewerker</th><th>Opdracht</th><th>Omschrijving</th><th class="num">Uren</th></tr></thead>
            <tbody>
            @foreach ($hours as $h)
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $h->id }}" checked aria-label="Selecteren"></td>
                    <td class="nowrap">{{ Format::date($h->date) }}</td>
                    <td>{{ $h->employee?->name }}</td>
                    <td>{{ $h->project?->number }} {{ $h->project?->name }} @if ($h->projectLine)<div class="muted small">{{ $h->projectLine->label() }}</div>@endif</td>
                    <td>{{ $h->description }}</td>
                    <td class="num">{{ Format::number($h->hours) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        {{ $hours->links() }}
        @endif
    </div>
    @if ($hours->isNotEmpty())
    <div class="card-b actions">
        <button class="btn btn-primary" name="actie" value="fiatteren" type="submit">Geselecteerde fiatteren</button>
        <button class="btn" name="actie" value="afwijzen" type="submit">Terugzetten naar concept</button>
    </div>
    @endif
</form>
@endsection
