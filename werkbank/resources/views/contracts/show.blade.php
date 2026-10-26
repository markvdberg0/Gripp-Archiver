@extends('layouts.app', ['title' => 'Contract '.$contract->number])
@php use App\Support\Format; use App\Support\Html; @endphp

@section('content')
<div class="page-head">
    <div><h1>Contract {{ $contract->number }} · {{ $contract->name }}</h1>
        <div class="sub"><span class="badge {{ $contract->status === 'actief' ? 'badge-ok' : '' }}">{{ \App\Models\Contract::STATUS[$contract->status] }}</span>
            @if ($contract->company) · <a href="{{ route('relaties.show', $contract->company) }}">{{ $contract->company->name }}</a>@endif</div>
    </div>
    <div class="actions">@can('facturen.bewerken')<a class="btn btn-primary" href="{{ route('contracten.edit', $contract) }}">Wijzigen</a>@endcan</div>
</div>
<div class="grid grid-2">
    <div class="card"><div class="card-h"><h2>Gegevens</h2></div><div class="card-b">
        <dl class="props">
            <dt>Frequentie</dt><dd>{{ \App\Models\Contract::FREQUENCIES[$contract->frequency] ?? $contract->frequency }}</dd>
            <dt>Looptijd</dt><dd>{{ Format::date($contract->start_date) }} t/m {{ Format::date($contract->end_date) ?: 'onbepaald' }} {{ $contract->auto_renew ? '(automatisch verlengen)' : '' }}</dd>
            <dt>Volgende factuur</dt><dd>{{ Format::date($contract->next_invoice_date) ?: '—' }} @if ($contract->isDue())<span class="badge badge-warn">aan de beurt</span>@endif</dd>
            <dt>Contactpersoon</dt><dd>{{ $contract->contact?->name ?? '—' }}</dd>
            @if ($contract->invoice_subject)<dt>Onderwerp factuur</dt><dd>{{ $contract->invoice_subject }}</dd>@endif
            @if ($contract->client_reference)<dt>Referentie</dt><dd>{{ $contract->client_reference }}</dd>@endif
            @if ($contract->description)<dt>Omschrijving</dt><dd>{{ Html::render($contract->description) }}</dd>@endif
        </dl>
    </div></div>
    <div class="card"><div class="card-h"><h2>Facturen</h2></div><div class="card-b flush">
        @if ($contract->invoices->isEmpty())<div class="empty">Nog geen facturen.</div>@else
        <table class="list"><tbody>@foreach ($contract->invoices->sortByDesc('id') as $i)<tr><td><a href="{{ route('facturen.show', $i) }}">{{ $i->number ?? 'concept' }}</a></td><td>{{ $i->subject }}</td><td>@include('invoices.status', ['invoice' => $i])</td><td class="num">{{ Format::euro($i->total_incl) }}</td></tr>@endforeach</tbody></table>
        @endif
    </div></div>
</div>
<div class="card"><div class="card-h"><h2>Regels per periode</h2></div><div class="card-b flush">@include('partials.regels-tabel', ['doc' => $contract, 'vat' => $vat])</div></div>
@endsection
