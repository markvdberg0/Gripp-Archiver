@extends('layouts.app', ['title' => 'Inkoopfactuur '.$invoice->booking_number])
@php use App\Support\Format; use App\Support\Html; @endphp

@section('content')
<div class="page-head">
    <div><h1>Inkoopfactuur {{ $invoice->booking_number ?? '#'.$invoice->id }} · {{ $invoice->supplier?->name }}</h1>
        <div class="sub"><span class="badge {{ $invoice->on_hold ? 'badge-danger' : 'badge-accent' }}">{{ $invoice->stateLabel() }}</span>
            @if ($invoice->invoice_number) · factuurnummer {{ $invoice->invoice_number }}@endif
            @if ($invoice->purchaseOrder) · <a href="{{ route('inkoopopdrachten.show', $invoice->purchaseOrder) }}">inkoopopdracht {{ $invoice->purchaseOrder->number }}</a>@endif
            @if ($invoice->project) · <a href="{{ route('opdrachten.show', $invoice->project) }}">opdracht {{ $invoice->project->number }}</a>@endif</div>
    </div>
    <div class="actions">@can('inkoop.bewerken')@if ($invoice->isEditable())<a class="btn btn-primary" href="{{ route('inkoopfacturen.edit', $invoice) }}">Wijzigen</a>@endif @endcan</div>
</div>

@if ($invoice->on_hold)<div class="alert alert-warn">On hold: {{ $invoice->hold_reason }}</div>@endif

<div class="tiles">
    <div class="tile"><div class="k">Totaal incl. btw</div><div class="v">{{ Format::euro($invoice->total_incl) }}</div></div>
    <div class="tile"><div class="k">Betaald</div><div class="v">{{ Format::euro($invoice->total_paid) }}</div></div>
    <div class="tile"><div class="k">Openstaand</div><div class="v {{ $invoice->isOverdue() ? 'neg' : '' }}">{{ Format::euro($invoice->total_open) }}</div></div>
    <div class="tile"><div class="k">Vervaldatum</div><div class="v" style="font-size:18px">{{ Format::date($invoice->due_date) ?: '—' }}</div></div>
</div>

<div class="grid grid-2">
    <div class="card"><div class="card-h"><h2>Controle</h2></div><div class="card-b">
        <ul style="list-style:none;padding:0;margin:0">
            @foreach ($controls as [$label, $ok, $blocking])
                <li style="padding:3px 0">{!! $ok ? '<span class="badge badge-ok">✓</span>' : ($blocking ? '<span class="badge badge-danger">✕</span>' : '<span class="badge badge-warn">!</span>') !!} {{ $label }}</li>
            @endforeach
        </ul>
        <p class="muted small" style="margin-top:8px">Rood blokkeert goedkeuren; oranje is een waarschuwing.</p>
        @can('inkoop.bewerken')
        <div class="actions" style="margin-top:10px">
            @if ($invoice->phase === 'concept')<form method="post" action="{{ route('inkoopfacturen.fase', [$invoice, 'indienen']) }}" class="inline">@csrf<button class="btn btn-sm" type="submit">Ter controle indienen</button></form>@endif
            @if (in_array($invoice->phase, ['concept', 'controle']) && ! $invoice->on_hold)
                <form method="post" action="{{ route('inkoopfacturen.fase', [$invoice, 'goedkeuren']) }}" class="inline">@csrf<button class="btn btn-sm btn-primary" type="submit" @disabled($denial) title="{{ $denial }}">Goedkeuren</button></form>
            @endif
            @if ($invoice->phase === 'akkoord' && ! $invoice->on_hold)@can('inkoop.goedkeuren')<form method="post" action="{{ route('inkoopfacturen.fase', [$invoice, 'verwerken']) }}" class="inline">@csrf<button class="btn btn-sm" type="submit">Verwerken</button></form>@endcan @endif
            @if ($invoice->phase !== 'concept')<form method="post" action="{{ route('inkoopfacturen.fase', [$invoice, 'terug']) }}" class="inline" data-confirm="Terugzetten naar concept? De goedkeuring vervalt.">@csrf<button class="btn btn-sm" type="submit">Terug naar concept</button></form>@endif
            @if ($invoice->on_hold)
                <form method="post" action="{{ route('inkoopfacturen.fase', [$invoice, 'vrijgeven']) }}" class="inline">@csrf<button class="btn btn-sm" type="submit">Vrijgeven</button></form>
            @elseif ($invoice->phase !== 'verwerkt')
                <form method="post" action="{{ route('inkoopfacturen.fase', [$invoice, 'hold']) }}" class="inline actions">@csrf<input type="text" name="hold_reason" placeholder="Reden on hold" aria-label="Reden on hold" style="width:200px" required><button class="btn btn-sm" type="submit">On hold</button></form>
            @endif
        </div>
        @if ($denial && in_array($invoice->phase, ['concept', 'controle']))<p class="muted small">{{ $denial }}</p>@endif
        @endcan
    </div></div>
    <div class="card"><div class="card-h"><h2>Gegevens</h2></div><div class="card-b">
        <dl class="props">
            <dt>Factuurdatum</dt><dd>{{ Format::date($invoice->date) }}</dd>
            <dt>Betaalwijze</dt><dd>{{ \App\Models\Company::BETAALWIJZEN[$invoice->payment_method] ?? $invoice->payment_method }}</dd>
            @if ($invoice->expected_total !== null)<dt>Totaal volgens factuur</dt><dd>{{ Format::euro($invoice->expected_total) }}</dd>@endif
            <dt>Ingevoerd door</dt><dd>{{ $invoice->creator?->name ?? '—' }}</dd>
            <dt>Goedgekeurd</dt><dd>{{ $invoice->approver ? $invoice->approver->name.' · '.Format::dateTime($invoice->approved_at) : '—' }}</dd>
            @if ($invoice->subject)<dt>Onderwerp</dt><dd>{{ $invoice->subject }}</dd>@endif
            @if ($invoice->description)<dt>Toelichting</dt><dd>{{ Html::render($invoice->description) }}</dd>@endif
        </dl>
    </div></div>
</div>

<div class="grid grid-2">
    <div class="card"><div class="card-h"><h2>Betalingen</h2></div>
        <div class="card-b flush">
            @if ($invoice->payments->isEmpty())<div class="empty">Nog geen betalingen.</div>@else
            <table class="list"><tbody>@foreach ($invoice->payments as $p)<tr><td>{{ Format::date($p->date) }}</td><td>{{ $p->notes }}</td><td class="num">{{ Format::euro($p->amount) }}</td></tr>@endforeach</tbody></table>
            @endif
        </div>
        @can('inkoop.goedkeuren')@if (in_array($invoice->phase, ['akkoord', 'verwerkt']) && abs((float) $invoice->total_open) >= 0.005)
        <div class="card-b"><form method="post" action="{{ route('inkoopfacturen.betalingen.store', $invoice) }}" class="form-grid">
            @csrf
            <x-veld name="date" label="Datum" type="date" :value="today()" required />
            <x-veld name="amount" label="Bedrag" inputmode="decimal" :value="\App\Support\Bedragen::input($invoice->total_open)" required />
            <x-veld name="notes" label="Omschrijving" wrap="span-2" />
            <div><button class="btn btn-sm" type="submit">Betaling registreren</button></div>
        </form></div>
        @endif @endcan
    </div>
    @include('partials.bestanden', ['model' => $invoice, 'type' => 'purchase_invoice', 'canUpload' => auth()->user()->can('inkoop.bewerken')])
</div>

<div class="card"><div class="card-h"><h2>Regels</h2></div><div class="card-b flush">
    @include('partials.regels-tabel', ['doc' => $invoice, 'vat' => $vat, 'extraLine' => fn ($l) => new \Illuminate\Support\HtmlString('<div class="muted small">'.e(implode(' · ', array_filter([$l->ledger ? 'Grootboek '.$l->ledger->label() : null, $l->costCenter ? 'Kostenplaats '.$l->costCenter->label() : null, $l->project ? 'Opdracht '.$l->project->number : null]))).'</div>')])
</div></div>
<div class="card"><div class="card-h"><h2>Timeline</h2></div>@include('partials.timeline', ['entries' => $timeline])</div>
@can('inkoop.bewerken')@if ($invoice->phase === 'concept')
<form method="post" action="{{ route('inkoopfacturen.destroy', $invoice) }}" data-confirm="Inkoopfactuur verwijderen?">@csrf @method('delete')<button class="btn btn-danger btn-sm" type="submit">Verwijderen</button></form>
@endif @endcan
@endsection
