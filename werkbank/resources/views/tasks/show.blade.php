@extends('layouts.app', ['title' => $task->title])
@php use App\Support\Format; use App\Support\Html; @endphp

@section('content')
<div class="page-head">
    <div><h1>{{ $task->title }}</h1>
        <div class="sub">Taak {{ $task->number }} @if ($task->completed)<span class="badge badge-ok">afgerond {{ Format::date($task->completed_at) }}</span>@elseif ($task->isOverdue())<span class="badge badge-danger">te laat</span>@endif
            @if ($task->phase)<span class="badge">{{ $task->phase->name }}</span>@endif</div>
    </div>
    <div class="actions">@can('taken.bewerken')
        <form method="post" action="{{ route('taken.afronden', $task) }}" class="inline">@csrf<button class="btn" type="submit">{{ $task->completed ? 'Heropenen' : 'Afronden' }}</button></form>
        <a class="btn btn-primary" href="{{ route('taken.edit', $task) }}">Wijzigen</a>
    @endcan</div>
</div>
<div class="grid grid-2">
    <div class="card"><div class="card-h"><h2>Gegevens</h2></div><div class="card-b">
        <dl class="props">
            <dt>Soort</dt><dd>{{ $task->type?->name ?? '—' }}</dd>
            <dt>Medewerker</dt><dd>{{ $task->employee?->name ?? '—' }}</dd>
            <dt>Opdracht</dt><dd>@if ($task->project)<a href="{{ route('opdrachten.show', $task->project) }}">{{ $task->project->number }} · {{ $task->project->name }}</a>@elseif ($task->offer)<a href="{{ route('offertes.show', $task->offer) }}">offerte {{ $task->offer->number }}</a>@else — @endif</dd>
            <dt>Relatie</dt><dd>@if ($task->company)<a href="{{ route('relaties.show', $task->company) }}">{{ $task->company->name }}</a>@else — @endif</dd>
            <dt>Start</dt><dd>{{ Format::date($task->start_date) ?: '—' }}</dd>
            <dt>Deadline</dt><dd>{{ Format::date($task->deadline) ?: '—' }} {{ $task->deadline_time ? substr($task->deadline_time, 0, 5) : '' }}</dd>
            <dt>Geschatte uren</dt><dd>{{ $task->estimated_hours ? Format::number($task->estimated_hours) : '—' }}</dd>
            @if ($task->description)<dt>Omschrijving</dt><dd>{{ Html::render($task->description) }}</dd>@endif
        </dl>
    </div></div>
    <div class="card"><div class="card-h"><h2>Ingepland</h2></div>
        <div class="card-b flush">
            @if ($task->calendarItems->isEmpty())<div class="empty">Nog niet ingepland.</div>@else
            <table class="list"><tbody>@foreach ($task->calendarItems as $c)<tr><td>{{ Format::date($c->date) }} {{ $c->start_time ? substr($c->start_time, 0, 5) : '' }}</td><td>{{ $c->employee?->name }}</td><td class="num">{{ Format::number($c->hours) }} u</td></tr>@endforeach</tbody></table>
            @endif
        </div>
        @can('taken.bewerken')
        <div class="card-b">
            <form method="post" action="{{ route('taken.inplannen', $task) }}" class="form-grid">
                @csrf
                <x-keuze name="employee_id" label="Medewerker" :options="$employees" :value="$task->employee_id" required />
                <x-veld name="date" label="Datum" type="date" :value="now()" required />
                <x-veld name="start_time" label="Begintijd" type="time" />
                <x-veld name="hours" label="Uren" type="number" step="0.25" value="1" required />
                <div><button class="btn btn-sm" type="submit">Inplannen</button></div>
            </form>
        </div>
        @endcan
    </div>
</div>
@endsection
