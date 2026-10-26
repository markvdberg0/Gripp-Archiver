@extends('layouts.app', ['title' => 'Verkoopfacturen'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Verkoopfacturen</h1><div class="sub">{{ $sum->n }} facturen · {{ Format::euro($sum->excl) }} excl. · {{ Format::euro($sum->incl) }} incl. btw · openstaand {{ Format::euro($sum->open) }}</div></div>
    <div class="actions"><a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">CSV</a>@can('facturen.bewerken')<a class="btn btn-primary" href="{{ route('facturen.create') }}">Nieuwe factuur</a>@endcan</div>
</div>
<form class="filters" method="get">
    <div class="field"><label for="q">Zoeken</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nummer, onderwerp of referentie"></div>
    <x-keuze name="status" label="Status" :options="['concept' => 'Concept', 'open' => 'Openstaand', 'vervallen' => 'Vervallen', 'betaald' => 'Betaald', 'credit' => 'Creditfacturen']" :value="request('status')" placeholder="Alle" />
    <x-keuze name="relatie" label="Relatie" :options="$companies" :value="request('relatie')" placeholder="Alle klanten" />
    <x-veld name="jaar" label="Jaar" type="number" :value="request('jaar')" style="width:100px" />
    <button class="btn" type="submit">Filteren</button>
</form>
<div class="card"><div class="card-b flush">
    @if ($invoices->isEmpty())<div class="empty">Geen facturen gevonden.</div>@else
    <table class="list">
        <thead><tr>
            <th><x-sorteer field="number" label="Nr." :default="true" default-dir="desc" /></th><th><x-sorteer field="date" label="Datum" /></th><th>Relatie</th><th>Onderwerp</th>
            <th>Status</th><th><x-sorteer field="due" label="Vervalt" /></th><th class="num"><x-sorteer field="total" label="Totaal incl." /></th><th class="num"><x-sorteer field="open" label="Openstaand" /></th>
        </tr></thead>
        <tbody>
        @foreach ($invoices as $i)
            <tr>
                <td><a href="{{ route('facturen.show', $i) }}">{{ $i->number ?? 'concept' }}</a></td>
                <td class="nowrap">{{ Format::date($i->date) }}</td>
                <td>{{ $i->company?->name }}</td>
                <td><a href="{{ route('facturen.show', $i) }}">{{ \Illuminate\Support\Str::limit($i->subject, 60) }}</a></td>
                <td>@include('invoices.status', ['invoice' => $i])</td>
                <td class="nowrap {{ $i->isOverdue() ? 'neg' : '' }}">{{ Format::date($i->due_date) }}</td>
                <td class="num">{!! (float) $i->total_incl < 0 ? '<span class="neg">'.e(Format::euro($i->total_incl)).'</span>' : e(Format::euro($i->total_incl)) !!}</td>
                <td class="num">{{ $i->status !== 'concept' && abs((float) $i->total_open) >= 0.005 ? Format::euro($i->total_open) : '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $invoices->links() }}
    @endif
</div></div>
@endsection
