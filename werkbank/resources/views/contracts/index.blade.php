@extends('layouts.app', ['title' => 'Contracten'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Contracten</h1><div class="sub">Periodieke facturatie. Elke nacht worden conceptfacturen gemaakt voor contracten die aan de beurt zijn.</div></div>
    <div class="actions">@can('facturen.bewerken')
        @if ($due)<form method="post" action="{{ route('contracten.factureren') }}" class="inline">@csrf<button class="btn" type="submit">Nu factureren ({{ $due }})</button></form>@endif
        <a class="btn btn-primary" href="{{ route('contracten.create') }}">Nieuw contract</a>
    @endcan</div>
</div>
<form class="filters" method="get"><x-keuze name="status" label="Status" :options="['actief' => 'Actief', 'beeindigd' => 'Beëindigd', 'alle' => 'Alle']" :value="request('status', 'actief')" data-autosubmit /></form>
<div class="card"><div class="card-b flush">
    @if ($contracts->isEmpty())<div class="empty">Geen contracten.</div>@else
    <table class="list">
        <thead><tr><th>Nr.</th><th>Omschrijving</th><th>Relatie</th><th>Frequentie</th><th>Volgende factuur</th><th>Status</th><th class="num">Per periode excl.</th></tr></thead>
        <tbody>
        @foreach ($contracts as $c)
            <tr>
                <td><a href="{{ route('contracten.show', $c) }}">{{ $c->number }}</a></td>
                <td><a href="{{ route('contracten.show', $c) }}">{{ $c->name }}</a></td>
                <td>{{ $c->company?->name }}</td>
                <td>{{ \App\Models\Contract::FREQUENCIES[$c->frequency] ?? $c->frequency }}</td>
                <td class="{{ $c->isDue() ? 'neg' : '' }}">{{ Format::date($c->next_invoice_date) ?: '—' }}</td>
                <td><span class="badge {{ $c->status === 'actief' ? 'badge-ok' : '' }}">{{ \App\Models\Contract::STATUS[$c->status] }}</span></td>
                <td class="num">{{ Format::euro($c->total_excl) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div></div>
@endsection
