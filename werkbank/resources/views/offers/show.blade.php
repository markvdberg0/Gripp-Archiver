@extends('layouts.app', ['title' => $offer->title()])
@php use App\Support\Format; use App\Support\Html; @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $offer->title() }} · {{ $offer->name }}</h1>
        <div class="sub">
            @include('offers.status', ['offer' => $offer])
            @if ($offer->phase)<span class="badge">{{ $offer->phase->name }}</span>@endif
            @if ($offer->archived)<span class="badge">gearchiveerd</span>@endif
            @if ($offer->company) · <a href="{{ route('relaties.show', $offer->company) }}">{{ $offer->company->name }}</a>@endif
        </div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('offertes.pdf', $offer) }}" target="_blank" rel="noopener">PDF</a>
        @can('offertes.bewerken')
            @unless ($offer->is_template)<a class="btn" href="{{ route('mail.create', ['offerte', $offer->id]) }}">E-mailen</a>@endunless
            @if ($offer->isEditable())<a class="btn btn-primary" href="{{ route('offertes.edit', $offer) }}">Wijzigen</a>@endif
            <form method="post" action="{{ route('offertes.kopie', $offer) }}" class="inline">@csrf<button class="btn {{ $offer->is_template ? 'btn-primary' : '' }}" type="submit">{{ $offer->is_template ? 'Nieuwe offerte op basis van dit sjabloon' : 'Kopiëren' }}</button></form>
        @endcan
    </div>
</div>

@can('offertes.bewerken')
@unless ($offer->is_template)
<div class="card no-print"><div class="card-b actions">
    <span class="muted">Status:</span>
    @if ($offer->status === 'concept' && ! $offer->acceptance)
        <form method="post" action="{{ route('offertes.status', [$offer, 'verzonden']) }}" class="inline">@csrf<button class="btn btn-sm" type="submit">Markeren als verzonden</button></form>
    @endif
    @if (! $offer->acceptance && ! $offer->archived)
        <form method="post" action="{{ route('offertes.status', [$offer, 'geaccepteerd']) }}" class="inline" data-confirm="Offerte als geaccepteerd markeren?">@csrf<button class="btn btn-sm" type="submit">Geaccepteerd</button></form>
        <form method="post" action="{{ route('offertes.status', [$offer, 'afgewezen']) }}" class="inline actions">@csrf
            <input type="text" name="rejection_reason" placeholder="Reden van afwijzing (optioneel)" aria-label="Reden van afwijzing" style="width:240px">
            <button class="btn btn-sm" type="submit">Afgewezen</button></form>
    @endif
    @if ($offer->acceptance || $offer->archived)
        <form method="post" action="{{ route('offertes.status', [$offer, 'heropenen']) }}" class="inline">@csrf<button class="btn btn-sm" type="submit">Heropenen</button></form>
    @endif
    @if (! $offer->archived)
        <form method="post" action="{{ route('offertes.status', [$offer, 'archiveren']) }}" class="inline">@csrf<button class="btn btn-sm" type="submit">Archiveren</button></form>
    @endif
    @if ($offer->acceptance === 'geaccepteerd' && Route::has('opdrachten.uit-offerte') && empty($related['projects'] ?? []) )
        @can('opdrachten.bewerken')<form method="post" action="{{ route('opdrachten.uit-offerte', $offer) }}" class="inline">@csrf<button class="btn btn-sm btn-primary" type="submit">Opdracht maken</button></form>@endcan
    @endif
</div></div>
@endunless
@endcan

<div class="grid grid-2">
    <div>
        <div class="card"><div class="card-h"><h2>Gegevens</h2></div><div class="card-b">
            <dl class="props">
                <dt>Datum</dt><dd>{{ Format::date($offer->date) }}</dd>
                <dt>Geldig tot</dt><dd>{{ Format::date($offer->valid_until) ?: '—' }}</dd>
                @if ($offer->deadline)<dt>Deadline</dt><dd>{{ Format::date($offer->deadline) }}</dd>@endif
                <dt>Contactpersoon</dt><dd>{{ $offer->contact?->name ?? '—' }}</dd>
                <dt>Accountmanager</dt><dd>{{ $offer->accountManager?->name ?? '—' }}</dd>
                <dt>Kans</dt><dd>{{ $offer->chance !== null ? $offer->chance.'% · gewogen '.Format::euro($offer->weightedValue()) : '—' }}</dd>
                @if ($offer->client_reference)<dt>Referentie</dt><dd>{{ $offer->client_reference }}</dd>@endif
                @if ($offer->accepted_at)<dt>Geaccepteerd op</dt><dd>{{ Format::dateTime($offer->accepted_at) }}</dd>@endif
                @if ($offer->rejection_reason)<dt>Reden afwijzing</dt><dd>{{ $offer->rejection_reason }}</dd>@endif
                @if ($offer->template)<dt>Sjabloon (Gripp)</dt><dd class="small">{{ $offer->template }}</dd>@endif
                @foreach ($offer->extra_fields ?? [] as $k => $v)
                    @if ($v !== null && $v !== '')<dt>{{ ucfirst(str_replace('_', ' ', $k)) }}</dt><dd>{{ is_scalar($v) ? Html::plain((string) $v, 500) : json_encode($v) }}</dd>@endif
                @endforeach
            </dl>
        </div></div>
    </div>
    <div>
        @foreach ($related['projects'] ?? [] as $p)
            <div class="card"><div class="card-b">Opdracht: <a href="{{ route('opdrachten.show', $p) }}">{{ $p->number }} · {{ $p->name }}</a></div></div>
        @endforeach
        @include('partials.bestanden', ['model' => $offer, 'type' => 'offer', 'canUpload' => auth()->user()->can('offertes.bewerken')])
        @can('offertes.bewerken')
            @if ($offer->status === 'concept' && ! $offer->acceptance)
                <form method="post" action="{{ route('offertes.destroy', $offer) }}" data-confirm="Conceptofferte verwijderen?">@csrf @method('delete')<button class="btn btn-danger btn-sm" type="submit">Concept verwijderen</button></form>
            @endif
        @endcan
    </div>
</div>
@if ($offer->description)<div class="card"><div class="card-h"><h2>Inleiding</h2></div><div class="card-b rich">{{ Html::render($offer->description) }}</div></div>@endif
<div class="card"><div class="card-h"><h2>Regels</h2></div><div class="card-b flush">@include('partials.regels-tabel', ['doc' => $offer, 'vat' => $vat, 'showBuying' => true])</div></div>
<div class="card"><div class="card-h"><h2>Timeline</h2></div>@include('partials.timeline', ['entries' => $timeline])</div>
@endsection
