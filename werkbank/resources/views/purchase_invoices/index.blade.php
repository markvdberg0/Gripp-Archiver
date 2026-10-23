@extends('layouts.app', ['title' => 'Inkoopfacturen'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Inkoopfacturen</h1><div class="sub">{{ $sum->n }} facturen · {{ Format::euro($sum->incl) }} incl. btw · openstaand {{ Format::euro($sum->open) }}</div></div>
    <div class="actions">@can('inkoop.bewerken')<a class="btn btn-primary" href="{{ route('inkoopfacturen.create') }}">Inkoopfactuur boeken</a>@endcan</div>
</div>
<div class="actions" style="margin-bottom:12px">
    <a class="btn btn-sm {{ ! request('fase') ? 'btn-primary' : '' }}" href="{{ route('inkoopfacturen.index') }}">Alle</a>
    @foreach (\App\Models\PurchaseInvoice::PHASES as $k => $label)
        <a class="btn btn-sm {{ request('fase') === $k ? 'btn-primary' : '' }}" href="{{ route('inkoopfacturen.index', ['fase' => $k]) }}">{{ $label }} ({{ $counts[$k] ?? 0 }})</a>
    @endforeach
    <a class="btn btn-sm {{ request('fase') === 'on_hold' ? 'btn-primary' : '' }}" href="{{ route('inkoopfacturen.index', ['fase' => 'on_hold']) }}">On hold ({{ $onHold }})</a>
</div>
<form class="filters" method="get">
    <input type="hidden" name="fase" value="{{ request('fase') }}">
    <div class="field"><label for="q">Zoeken</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Factuurnummer, boeknummer of onderwerp"></div>
    <x-keuze name="leverancier" label="Leverancier" :options="$suppliers" :value="request('leverancier')" placeholder="Alle" />
    <x-keuze name="openstaand" label="Betaling" :options="['' => 'Alle', '1' => 'Openstaand']" :value="request('openstaand')" />
    <x-keuze name="vervallen" label="Vervallen" :options="['' => '—', '1' => 'Alleen vervallen']" :value="request('vervallen')" />
    <button class="btn" type="submit">Filteren</button>
</form>
<div class="card"><div class="card-b flush">
    @if ($invoices->isEmpty())<div class="empty">Geen inkoopfacturen gevonden.</div>@else
    <table class="list">
        <thead><tr><th>Boeknr.</th><th>Datum</th><th>Leverancier</th><th>Factuurnr.</th><th>Onderwerp</th><th>Fase</th><th>Vervalt</th><th class="num">Totaal incl.</th><th class="num">Openstaand</th></tr></thead>
        <tbody>
        @foreach ($invoices as $i)
            <tr>
                <td><a href="{{ route('inkoopfacturen.show', $i) }}">{{ $i->booking_number ?? '—' }}</a></td>
                <td class="nowrap">{{ Format::date($i->date) }}</td>
                <td>{{ $i->supplier?->name }}</td>
                <td>{{ $i->invoice_number }}</td>
                <td>{{ \Illuminate\Support\Str::limit($i->subject, 50) }}</td>
                <td><span class="badge {{ $i->on_hold ? 'badge-danger' : (['akkoord' => 'badge-accent', 'verwerkt' => 'badge-ok', 'controle' => 'badge-warn'][$i->phase] ?? '') }}">{{ $i->stateLabel() }}</span></td>
                <td class="nowrap {{ $i->isOverdue() ? 'neg' : '' }}">{{ Format::date($i->due_date) }}</td>
                <td class="num">{{ Format::euro($i->total_incl) }}</td>
                <td class="num">{{ abs((float) $i->total_open) >= 0.005 ? Format::euro($i->total_open) : '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $invoices->links() }}
    @endif
</div></div>
@endsection
