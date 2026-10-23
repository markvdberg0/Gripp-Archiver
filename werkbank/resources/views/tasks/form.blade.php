@extends('layouts.app', ['title' => $task->exists ? 'Taak wijzigen' : 'Nieuwe taak'])

@section('content')
<div class="page-head"><h1>{{ $task->exists ? $task->title : 'Nieuwe taak' }}</h1></div>
<form method="post" action="{{ $task->exists ? route('taken.update', $task) : route('taken.store') }}" class="card">
    @csrf @if ($task->exists) @method('put') @endif
    <div class="card-b">
        <div class="form-grid">
            <x-veld name="title" label="Omschrijving" :value="$task->title" required wrap="span-2" />
            <x-keuze name="type_id" label="Soort" :options="$types" :value="$task->type_id" placeholder="—" />
            <x-keuze name="phase_id" label="Fase" :options="$phases" :value="$task->phase_id" placeholder="—" />
            <x-keuze name="project_id" label="Opdracht" :options="$projects" :value="$task->project_id" placeholder="—" />
            <x-keuze name="company_id" label="Relatie" :options="$companies" :value="$task->company_id" placeholder="— (van de opdracht)" />
            <x-keuze name="employee_id" label="Medewerker" :options="$employees" :value="$task->employee_id" placeholder="—" />
            <x-veld name="estimated_hours" label="Geschatte uren" type="number" step="0.25" :value="$task->estimated_hours" />
            <x-veld name="start_date" label="Start" type="date" :value="$task->start_date" />
            <x-veld name="deadline" label="Deadline" type="date" :value="$task->deadline" />
            <x-veld name="deadline_time" label="Tijd" type="time" :value="$task->deadline_time ? substr($task->deadline_time, 0, 5) : null" />
            <x-tekst name="description" label="Omschrijving" :value="$task->description" wrap="span-2" />
        </div>
        <div class="actions"><button class="btn btn-primary" type="submit">Opslaan</button><a class="btn" href="{{ $task->exists ? route('taken.show', $task) : route('taken.index') }}">Annuleren</a></div>
    </div>
</form>
@endsection
