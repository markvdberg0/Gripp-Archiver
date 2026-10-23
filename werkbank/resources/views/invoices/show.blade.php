@extends('layouts.app', ['title' => $invoice->title()])
@php use App\Support\Format; use App\Support\Html; @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $invoice->title() }} · {{ $invoice->subject }}</h1>
        <div class="sub">@include('invoices.status', ['invoice' => $invoice])
            @if ($invoice->company) · <a href="{{ route('relaties.show', $invoice->company) }}">{{ $invoice->company->name }}</a>@endif
            @if ($invoice->project) · <a href="{{ route('opdrachten.show', $invoice->project) }}">opdracht {{ $invoice->project->number }}</a>@endif
            @if ($invoice->contract) · <a href="{{ route('contracten.show', $invoice->contract) }}">contract {{ $invoice->contract->number }}</a>@endif
            @if ($invoice->creditOf) · creditering van <a href="{{ route('facturen.show', $invoice->creditOf) }}">factuur {{ $invoice->creditOf->number }}</a>@endif
        </div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('facturen.pdf', $invoice) }}" target="_blank" rel="noopener">{{ $invoice->reminder_level ? \App\Models\Invoice::REMINDERS[$invoice->reminder_level].' (PDF)' : 'PDF' }}</a>
        @can('facturen.bewerken')
            @if ($invoice->isEditable())
                <a class="btn" href="{{ route('facturen.edit', $invoice) }}">Wijzigen</a>
                <form method="post" action="{{ route('facturen.definitief', $invoice) }}" class="inline" data-confirm="Factuur definitief maken? Hij krijgt een nummer en is daarna niet meer te wijzigen.">@csrf<button class="btn btn-primary" type="submit">Definitief maken</button></form>
            @else
                @if ($invoice->isOverdue())<form method="post" action="{{ route('facturen.herinnering', $invoice) }}" class="inline">@csrf<button class="btn" type="submit">{{ $invoice->reminder_level >= 3 ? 'Nogmaals aanmanen' : 'Herinnering vastleggen' }}</button></form>@endif
                @unless ($invoice->credit_of_id)<form method="post" action="{{ route('facturen.credit', $invoice) }}" class="inline" data-confirm="Een conceptcreditfactuur maken voor deze factuur?">@csrf<button class="btn" type="submit">Crediteren</button></form>@endunless
            @endif
        @endcan
    </div>
</div>

<div class="tiles">
    <div class="tile"><div class="k">Totaal incl. btw</div><div class="v">{{ Format::euro($invoice->total_incl) }}</div></div>
    <div class="tile"><div class="k">Betaald</div><div class="v">{{ Format::euro($invoice->total_paid) }}</div></div>
    <div class="tile"><div class="k">Openstaand</div><div class="v {{ $invoice->isOverdue() ? 'neg' : '' }}">{{ $invoice->status === 'concept' ? '—' : Format::euro($invoice->total_open) }}</div></div>
    <div class="tile"><div class="k">Vervaldatum</div><div class="v" style="font-size:18px">{{ Format::date($invoice->due_date) ?: '—' }}</div></div>
</div>

<div class="grid grid-2">
    <div class="card"><div class="card-h"><h2>Gegevens</h2></div><div class="card-b">
        <dl class="props">
            <dt>Factuurdatum</dt><dd>{{ Format::date($invoice->date) ?: 'bij definitief maken' }}</dd>
            <dt>Contactpersoon</dt><dd>{{ $invoice->contact?->name ?? '—' }}</dd>
            <dt>Referentie</dt><dd>{{ $invoice->client_reference ?: '—' }}</dd>
            <dt>Betaalwijze</dt><dd>{{ \App\Models\Company::BETAALWIJZEN[$invoice->payment_method] ?? $invoice->payment_method }}</dd>
            @if ($invoice->sent_at)<dt>Definitief sinds</dt><dd>{{ Format::dateTime($invoice->sent_at) }}</dd>@endif
            @if ($invoice->last_reminder_at)<dt>Laatste herinnering</dt><dd>{{ Format::dateTime($invoice->last_reminder_at) }} ({{ mb_strtolower(\App\Models\Invoice::REMINDERS[$invoice->reminder_level]) }})</dd>@endif
            @foreach ($invoice->credits as $c)<dt>Creditfactuur</dt><dd><a href="{{ route('facturen.show', $c) }}">{{ $c->number ?? 'concept' }}</a> {{ Format::euro($c->total_incl) }}</dd>@endforeach
            @if ($invoice->description)<dt>Toelichting</dt><dd>{{ Html::render($invoice->description) }}</dd>@endif
        </dl>
    </div></div>
    <div>
        <div class="card"><div class="card-h"><h2>Betalingen</h2></div>
            <div class="card-b flush">
                @if ($invoice->payments->isEmpty())<div class="empty">Nog geen betalingen.</div>@else
                <table class="list"><tbody>
                    @foreach ($invoice->payments as $p)
                        <tr><td>{{ Format::date($p->date) }}</td><td>{{ $p->notes }}</td><td class="num">{{ Format::euro($p->amount) }}</td>
                            <td>@can('facturen.bewerken')<form method="post" action="{{ route('facturen.betalingen.destroy', [$invoice, $p->id]) }}" class="inline" data-confirm="Betaling verwijderen?">@csrf @method('delete')<button class="btn-link neg" type="submit" aria-label="Verwijderen">✕</button></form>@endcan</td></tr>
                    @endforeach
                </tbody></table>
                @endif
            </div>
            @can('facturen.bewerken')@unless ($invoice->isEditable())
            <div class="card-b">
                <form method="post" action="{{ route('facturen.betalingen.store', $invoice) }}" class="form-grid">
                    @csrf
                    <x-veld name="date" label="Datum" type="date" :value="today()" required />
                    <x-veld name="amount" label="Bedrag" inputmode="decimal" :value="\App\Support\Bedragen::input($invoice->total_open)" required />
                    <x-veld name="notes" label="Omschrijving" wrap="span-2" placeholder="bijv. bankafschrift 12" />
                    <div><button class="btn btn-sm" type="submit">Betaling registreren</button></div>
                </form>
            </div>
            @endunless @endcan
        </div>
        @include('partials.bestanden', ['model' => $invoice, 'type' => 'invoice', 'canUpload' => auth()->user()->can('facturen.bewerken')])
    </div>
</div>

<div class="card"><div class="card-h"><h2>Regels</h2></div><div class="card-b flush">
    @include('partials.regels-tabel', ['doc' => $invoice, 'vat' => $vat, 'extraLine' => fn ($l) => $l->ledger || $l->project ? new \Illuminate\Support\HtmlString('<div class="muted small">'.e(trim(($l->ledger ? 'Grootboek '.$l->ledger->label() : '').($l->project ? ' · opdracht '.$l->project->number : ''), ' ·')).'</div>') : ''])
</div></div>
<div class="card"><div class="card-h"><h2>Timeline</h2></div>@include('partials.timeline', ['entries' => $timeline])</div>
@can('facturen.bewerken')@if ($invoice->isEditable())
<form method="post" action="{{ route('facturen.destroy', $invoice) }}" data-confirm="Conceptfactuur verwijderen?">@csrf @method('delete')<button class="btn btn-danger btn-sm" type="submit">Concept verwijderen</button></form>
@endif @endcan
@endsection
