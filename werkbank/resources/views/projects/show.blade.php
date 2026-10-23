@extends('layouts.app', ['title' => 'Opdracht '.$project->number])
@php use App\Support\Format; use App\Support\Html; @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Opdracht {{ $project->number }} · {{ $project->name }}</h1>
        <div class="sub">
            @if ($project->phase)<span class="badge badge-accent">{{ $project->phase->name }}</span>@endif
            @if ($project->archived)<span class="badge">gearchiveerd</span>@endif
            @if ($project->company) · <a href="{{ route('relaties.show', $project->company) }}">{{ $project->company->name }}</a>@endif
            @if ($project->offer) · uit <a href="{{ route('offertes.show', $project->offer) }}">offerte {{ $project->offer->number }}</a>@endif
        </div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('opdrachten.pdf', $project) }}" target="_blank" rel="noopener">Opdrachtbevestiging (PDF)</a>
        @can('facturen.bewerken')@if (Route::has('facturen.uit-opdracht'))<form method="post" action="{{ route('facturen.uit-opdracht', $project) }}" class="inline">@csrf<button class="btn" type="submit">Factuur maken</button></form>@endif @endcan
        @can('opdrachten.bewerken')
            <form method="post" action="{{ route('opdrachten.archiveren', $project) }}" class="inline">@csrf<button class="btn" type="submit">{{ $project->archived ? 'Uit archief' : 'Archiveren' }}</button></form>
            <a class="btn btn-primary" href="{{ route('opdrachten.edit', $project) }}">Wijzigen</a>
        @endcan
    </div>
</div>

<div class="grid grid-2">
    <div class="card"><div class="card-h"><h2>Gegevens</h2></div><div class="card-b">
        <dl class="props">
            <dt>Contactpersoon</dt><dd>{{ $project->contact?->name ?? '—' }}</dd>
            <dt>Projectleider</dt><dd>{{ $project->accountManager?->name ?? '—' }}</dd>
            <dt>Medewerkers</dt><dd>{{ $project->employees->pluck('name')->join(', ') ?: '—' }}</dd>
            <dt>Start / einde</dt><dd>{{ Format::date($project->start_date) ?: '—' }} / {{ Format::date($project->end_date) ?: '—' }}</dd>
            <dt>Deadline</dt><dd>{{ Format::date($project->deadline) ?: '—' }}</dd>
            @if ($project->delivery_date)<dt>Leverdatum</dt><dd>{{ Format::date($project->delivery_date) }}</dd>@endif
            @if ($project->client_reference)<dt>Referentie</dt><dd>{{ $project->client_reference }}</dd>@endif
            @if ($project->work_address)<dt>Werkadres</dt><dd>{{ $project->work_address }}</dd>@endif
            @foreach ($project->extra_fields ?? [] as $k => $v)
                @if ($v !== null && $v !== '')<dt>{{ ucfirst(str_replace('_', ' ', $k)) }}</dt><dd>{{ is_scalar($v) ? Html::plain((string) $v, 500) : json_encode($v) }}</dd>@endif
            @endforeach
        </dl>
    </div></div>
    <div>
        <div class="card"><div class="card-h"><h2>Uren</h2>@can('uren.schrijven')<a class="small" href="{{ route('uren.index', ['project_id' => $project->id]) }}">Uren schrijven</a>@endcan</div><div class="card-b flush">
            @if ($hoursPerEmployee->isEmpty())<div class="empty">Nog geen uren geschreven.</div>@else
            <table class="list"><thead><tr><th>Medewerker</th><th class="num">Geschreven</th><th class="num">Gefiatteerd</th></tr></thead><tbody>
                @foreach ($hoursPerEmployee as $h)<tr><td>{{ $h['name'] }}</td><td class="num">{{ Format::number($h['total']) }}</td><td class="num">{{ Format::number($h['approved']) }}</td></tr>@endforeach
            </tbody><tfoot><tr><td>Totaal</td><td class="num">{{ Format::number($hoursPerEmployee->sum('total')) }}</td><td class="num">{{ Format::number($hoursPerEmployee->sum('approved')) }}</td></tr></tfoot></table>
            @endif
        </div></div>
        @foreach ($related as $rel => $items)
            @include('projects.related.'.$rel, ['items' => $items])
        @endforeach
        @include('partials.bestanden', ['model' => $project, 'type' => 'project', 'canUpload' => auth()->user()->can('opdrachten.bewerken')])
    </div>
</div>

@if ($project->description)<div class="card"><div class="card-h"><h2>Omschrijving</h2></div><div class="card-b rich">{{ Html::render($project->description) }}</div></div>@endif

<div class="card"><div class="card-h"><h2>Regels</h2><span class="muted small">Bij regels in uren staat het aantal geschreven uren erbij.</span></div><div class="card-b flush">
    @include('partials.regels-tabel', ['doc' => $project, 'vat' => $vat, 'showBuying' => true, 'extraLine' => fn ($l) => isset($hoursPerLine[$l->id]) ? new \Illuminate\Support\HtmlString('<div class="small">Geschreven: <strong>'.e(Format::number($hoursPerLine[$l->id])).'</strong> uur'.($l->unit?->type === 'uren' ? ' van '.e(Format::number($l->quantity)) : '').'</div>') : ''])
</div></div>

<div class="card">
    <div class="card-h"><h2>Taken</h2>@can('taken.bewerken')<a class="small" href="{{ route('taken.create', ['project_id' => $project->id]) }}">Taak toevoegen</a>@endcan</div>
    <div class="card-b flush">
        @if ($tasks->isEmpty())<div class="empty">Geen taken.</div>@else
        <table class="list"><tbody>
            @foreach ($tasks as $t)
                <tr><td>@if ($t->completed)<span class="badge badge-ok">afgerond</span>@elseif ($t->isOverdue())<span class="badge badge-danger">te laat</span>@endif <a href="{{ route('taken.show', $t) }}">{{ $t->title }}</a></td><td>{{ $t->employee?->name }}</td><td class="nowrap">{{ Format::date($t->deadline) }}</td></tr>
            @endforeach
        </tbody></table>
        @endif
    </div>
</div>

<div class="card"><div class="card-h"><h2>Timeline</h2></div>@include('partials.timeline', ['entries' => $timeline])</div>

@can('opdrachten.bewerken')
<form method="post" action="{{ route('opdrachten.destroy', $project) }}" data-confirm="Opdracht verwijderen?" class="no-print">@csrf @method('delete')<button class="btn btn-danger btn-sm" type="submit">Opdracht verwijderen</button></form>
@endcan
@endsection
