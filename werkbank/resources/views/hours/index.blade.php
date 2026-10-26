@extends('layouts.app', ['title' => 'Uren'])
@php use App\Support\Format; $days = collect(range(0, 6))->map(fn ($i) => $week->addDays($i)); $byDay = $hours->groupBy(fn ($h) => $h->date->toDateString()); @endphp

@section('content')
<div class="page-head">
    <div><h1>Uren</h1><div class="sub">Week {{ $week->isoWeek() }} · {{ Format::date($week) }} t/m {{ Format::date($week->addDays(6)) }} · {{ $employee?->name ?? 'geen medewerker gekoppeld' }}</div></div>
    <div class="actions">
        <a class="btn" href="{{ request()->fullUrlWithQuery(['week' => $week->subWeek()->toDateString()]) }}">‹ Vorige week</a>
        <a class="btn" href="{{ request()->fullUrlWithQuery(['week' => null]) }}">Deze week</a>
        <a class="btn" href="{{ request()->fullUrlWithQuery(['week' => $week->addWeek()->toDateString()]) }}">Volgende week ›</a>
        @can('uren.fiatteren')<a class="btn" href="{{ route('uren.fiatteren') }}">Fiatteren</a>@endcan
    </div>
</div>

@if ($employees)
<form class="filters" method="get"><input type="hidden" name="week" value="{{ $week->toDateString() }}">
    <x-keuze name="medewerker" label="Medewerker" :options="$employees" :value="$employee?->id" data-autosubmit />
</form>
@endif

@if (! $employee)
    <div class="alert alert-warn">Je gebruikersaccount is niet gekoppeld aan een medewerker. Vraag de beheerder dat te doen (Beheer › Gebruikers).</div>
@else
<div class="grid grid-side">
    <div class="card"><div class="card-b flush">
        <table class="list">
            <thead><tr><th>Dag</th><th>Opdracht</th><th>Omschrijving</th><th>Status</th><th class="num">Uren</th><th></th></tr></thead>
            <tbody>
            @foreach ($days as $day)
                @php $items = $byDay[$day->toDateString()] ?? collect(); @endphp
                <tr class="group"><td colspan="4">{{ ucfirst($day->translatedFormat('l j F')) }}</td><td class="num">{{ $items->sum('hours') ? Format::number($items->sum('hours')) : '' }}</td><td></td></tr>
                @foreach ($items as $h)
                    <tr>
                        <td></td>
                        <td>@if ($h->project)<a href="{{ route('opdrachten.show', $h->project) }}">{{ $h->project->number }}</a> {{ $h->project->name }}@endif
                            @if ($h->projectLine)<div class="muted small">{{ $h->projectLine->label() }}</div>@endif</td>
                        <td>{{ $h->description }}</td>
                        <td><span class="badge {{ ['gefiatteerd' => 'badge-ok', 'definitief' => 'badge-accent'][$h->status] ?? '' }}">{{ \App\Models\Hour::STATUS[$h->status] }}</span></td>
                        <td class="num">{{ Format::number($h->hours) }}</td>
                        <td>@if ($h->isEditable())<form method="post" action="{{ route('uren.destroy', $h) }}" class="inline" data-confirm="Regel verwijderen?">@csrf @method('delete')<button class="btn-link neg" type="submit" aria-label="Verwijderen">✕</button></form>@endif</td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
            <tfoot><tr><td colspan="4">Totaal week</td><td class="num">{{ Format::number($hours->sum('hours')) }}</td><td></td></tr></tfoot>
        </table>
    </div></div>
    <div>
        <div class="card"><div class="card-h"><h2>Uren toevoegen</h2></div><div class="card-b">
            <form method="get" class="no-print">
                <input type="hidden" name="week" value="{{ $week->toDateString() }}">@if ($employees)<input type="hidden" name="medewerker" value="{{ $employee->id }}">@endif
                <x-keuze name="project_id" label="Opdracht" :options="$projects" :value="$projectId" placeholder="— kies een opdracht —" data-autosubmit />
            </form>
            @if ($projectId)
            <form method="post" action="{{ route('uren.store') }}">
                @csrf
                @if ($employees)<input type="hidden" name="medewerker" value="{{ $employee->id }}">@endif
                <input type="hidden" name="project_id" value="{{ $projectId }}">
                @if ($lines)<x-keuze name="project_line_id" label="Regel" :options="$lines" placeholder="— hele opdracht —" />@endif
                <x-veld name="date" label="Datum" type="date" :value="today()->between($week, $week->addDays(6)) ? today() : $week" required />
                <x-veld name="hours" label="Uren" inputmode="decimal" required placeholder="bijv. 7,5" />
                <x-tekst name="description" label="Omschrijving" rows="2" />
                <button class="btn btn-primary" type="submit">Toevoegen</button>
            </form>
            @else
                <p class="muted small">Kies eerst een opdracht.</p>
            @endif
        </div></div>
        @if ($hours->where('status', 'concept')->isNotEmpty())
            <form method="post" action="{{ route('uren.indienen') }}" data-confirm="Alle concepturen van deze week definitief maken? Daarna kun je ze niet meer wijzigen.">
                @csrf <input type="hidden" name="week" value="{{ $week->toDateString() }}">@if ($employees)<input type="hidden" name="medewerker" value="{{ $employee->id }}">@endif
                <button class="btn" type="submit">Week indienen ({{ Format::number($hours->where('status', 'concept')->sum('hours')) }} uur)</button>
            </form>
        @endif
    </div>
</div>
@endif
@endsection
