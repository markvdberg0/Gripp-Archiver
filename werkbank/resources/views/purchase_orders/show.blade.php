@extends('layouts.app', ['title' => 'Inkoopopdracht '.$order->number])
@php use App\Support\Format; use App\Support\Html; @endphp

@section('content')
<div class="page-head">
    <div><h1>Inkoopopdracht {{ $order->number }} · {{ $order->name }}</h1>
        <div class="sub"><span class="badge badge-accent">{{ \App\Models\PurchaseOrder::STATUS[$order->status] ?? $order->status }}</span> @if ($order->archived)<span class="badge">gearchiveerd</span>@endif
            @if ($order->supplier) · <a href="{{ route('relaties.show', $order->supplier) }}">{{ $order->supplier->name }}</a>@endif
            @if ($order->project) · voor <a href="{{ route('opdrachten.show', $order->project) }}">opdracht {{ $order->project->number }}</a>@endif</div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('inkoopopdrachten.pdf', $order) }}" target="_blank" rel="noopener">PDF</a>
        @can('inkoop.bewerken')
            <a class="btn" href="{{ route('inkoopfacturen.create', ['purchase_order_id' => $order->id]) }}">Inkoopfactuur boeken</a>
            <a class="btn btn-primary" href="{{ route('inkoopopdrachten.edit', $order) }}">Wijzigen</a>
        @endcan
    </div>
</div>
@can('inkoop.bewerken')
<div class="card no-print"><div class="card-b actions"><span class="muted">Status:</span>
    @foreach (\App\Models\PurchaseOrder::STATUS as $k => $label)
        @if ($k !== $order->status)<form method="post" action="{{ route('inkoopopdrachten.status', [$order, $k]) }}" class="inline">@csrf<button class="btn btn-sm" type="submit">{{ $label }}</button></form>@endif
    @endforeach
    <form method="post" action="{{ route('inkoopopdrachten.status', [$order, 'archiveren']) }}" class="inline">@csrf<button class="btn btn-sm" type="submit">{{ $order->archived ? 'Uit archief' : 'Archiveren' }}</button></form>
</div></div>
@endcan
<div class="grid grid-2">
    <div class="card"><div class="card-h"><h2>Gegevens</h2></div><div class="card-b">
        <dl class="props">
            <dt>Datum</dt><dd>{{ Format::date($order->date) }}</dd>
            <dt>Leverdatum</dt><dd>{{ Format::date($order->delivery_date) ?: '—' }}</dd>
            <dt>Besteld door</dt><dd>{{ $order->employee?->name ?? '—' }}</dd>
            <dt>Contactpersoon</dt><dd>{{ $order->contact?->name ?? '—' }}</dd>
            @if ($order->reference)<dt>Referentie</dt><dd>{{ $order->reference }}</dd>@endif
            @if ($order->delivery_address)<dt>Afleveradres</dt><dd>{{ $order->delivery_address }}</dd>@endif
            <dt>Gefactureerd</dt><dd>{{ Format::euro($invoiced) }} van {{ Format::euro($order->total_excl) }} excl. btw</dd>
            @if ($order->description)<dt>Toelichting</dt><dd>{{ Html::render($order->description) }}</dd>@endif
        </dl>
    </div></div>
    <div>
        <div class="card"><div class="card-h"><h2>Inkoopfacturen</h2></div><div class="card-b flush">
            @if ($order->invoices->isEmpty())<div class="empty">Nog geen inkoopfacturen.</div>@else
            <table class="list"><tbody>@foreach ($order->invoices as $i)<tr><td><a href="{{ route('inkoopfacturen.show', $i) }}">{{ $i->booking_number }}</a></td><td>{{ $i->invoice_number }}</td><td><span class="badge">{{ $i->stateLabel() }}</span></td><td class="num">{{ Format::euro($i->total_incl) }}</td></tr>@endforeach</tbody></table>
            @endif
        </div></div>
        @include('partials.bestanden', ['model' => $order, 'type' => 'purchase_order', 'canUpload' => auth()->user()->can('inkoop.bewerken')])
    </div>
</div>
<div class="card"><div class="card-h"><h2>Regels</h2></div><div class="card-b flush">
    @include('partials.regels-tabel', ['doc' => $order, 'vat' => $vat, 'extraLine' => fn ($l) => $l->ledger || $l->project ? new \Illuminate\Support\HtmlString('<div class="muted small">'.e(trim(($l->ledger ? 'Grootboek '.$l->ledger->label() : '').($l->project ? ' · opdracht '.$l->project->number : ''), ' ·')).'</div>') : ''])
</div></div>
<div class="card"><div class="card-h"><h2>Timeline</h2></div>@include('partials.timeline', ['entries' => $timeline])</div>
@can('inkoop.bewerken')@if ($order->status === 'concept')
<form method="post" action="{{ route('inkoopopdrachten.destroy', $order) }}" data-confirm="Inkoopopdracht verwijderen?">@csrf @method('delete')<button class="btn btn-danger btn-sm" type="submit">Concept verwijderen</button></form>
@endif @endcan
@endsection
