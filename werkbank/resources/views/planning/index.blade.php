@extends('layouts.app', ['title' => 'Planning'])
@php use App\Support\Format; $days = collect(range(0, 6))->map(fn ($i) => $week->addDays($i)); @endphp

@section('content')
<div class="page-head">
    <div><h1>Planning</h1><div class="sub">Week {{ $week->isoWeek() }} · {{ Format::date($week) }} t/m {{ Format::date($week->addDays(6)) }}</div></div>
    <div class="actions">
        <a class="btn" href="{{ route('planning.index', ['week' => $week->subWeek()->toDateString()]) }}">‹ Vorige</a>
        <a class="btn" href="{{ route('planning.index') }}">Deze week</a>
        <a class="btn" href="{{ route('planning.index', ['week' => $week->addWeek()->toDateString()]) }}">Volgende ›</a>
    </div>
</div>
<div class="card"><div class="card-b flush">
    <table class="list">
        <thead><tr><th>Medewerker</th>@foreach ($days as $d)<th>{{ ucfirst($d->translatedFormat('D j M')) }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($employees as $e)
            <tr>
                <td class="nowrap"><strong>{{ $e->name }}</strong></td>
                @foreach ($days as $d)
                    @php $cell = $grid[$e->id.'|'.$d->toDateString()] ?? collect(); @endphp
                    <td style="min-width:110px">
                        @foreach ($cell as $i)
                            <div class="small" style="margin-bottom:4px;padding:3px 6px;border-radius:5px;background:var(--accent-soft)">
                                {{ $i->start_time ? substr($i->start_time, 0, 5).' · ' : '' }}{{ Format::number($i->hours) }} u
                                <div>@if ($i->task)<a href="{{ route('taken.show', $i->task) }}">{{ \Illuminate\Support\Str::limit($i->subject ?? $i->task->title, 40) }}</a>@else{{ \Illuminate\Support\Str::limit($i->subject, 40) }}@endif</div>
                                @if ($i->task?->project)<div class="muted">{{ $i->task->project->number }}</div>@endif
                            </div>
                        @endforeach
                        @if ($cell->sum('hours') > 0)<div class="muted small">{{ Format::number($cell->sum('hours')) }} u</div>@endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Geen medewerkers.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
@can('taken.bewerken')
<div class="card" style="max-width:640px"><div class="card-h"><h2>Inplannen</h2><span class="muted small">Voor een taak: open de taak en kies "Inplannen".</span></div><div class="card-b">
    <form method="post" action="{{ route('planning.store') }}" class="form-grid">
        @csrf
        <x-keuze name="employee_id" label="Medewerker" :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->name])->all()" required />
        <x-veld name="date" label="Datum" type="date" :value="$week" required />
        <x-veld name="subject" label="Onderwerp" required wrap="span-2" />
        <x-veld name="start_time" label="Begintijd" type="time" />
        <x-veld name="hours" label="Uren" type="number" step="0.25" value="1" required />
        <div><button class="btn btn-primary" type="submit">Inplannen</button></div>
    </form>
</div></div>
@endcan
@endsection
