@extends('layouts.app', ['title' => 'Taken'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Taken</h1><div class="sub">{{ $tasks->total() }} taken</div></div>
    <div class="actions">
        @if (Route::has('planning.index'))<a class="btn" href="{{ route('planning.index') }}">Planning</a>@endif
        @can('taken.bewerken')<a class="btn btn-primary" href="{{ route('taken.create') }}">Nieuwe taak</a>@endcan
    </div>
</div>
<form class="filters" method="get">
    <x-keuze name="wie" label="Voor" :options="['ik' => 'Mij', 'iedereen' => 'Iedereen'] + $employees" :value="$who" />
    <x-keuze name="status" label="Status" :options="['open' => 'Open', 'afgerond' => 'Afgerond', 'alle' => 'Alle']" :value="request('status', 'open')" />
    <button class="btn" type="submit">Filteren</button>
</form>
<div class="card"><div class="card-b flush">
    @if ($tasks->isEmpty())<div class="empty">Geen taken.</div>@else
    <table class="list">
        <thead><tr><th>Taak</th><th>Soort</th><th>Opdracht / relatie</th><th>Medewerker</th><th>Deadline</th><th class="num">Geschat</th><th></th></tr></thead>
        <tbody>
        @foreach ($tasks as $t)
            <tr>
                <td><a href="{{ route('taken.show', $t) }}">{{ $t->title }}</a> @if ($t->completed)<span class="badge badge-ok">afgerond</span>@elseif ($t->isOverdue())<span class="badge badge-danger">te laat</span>@endif</td>
                <td>{{ $t->type?->name }}</td>
                <td>@if ($t->project)<a href="{{ route('opdrachten.show', $t->project) }}">{{ $t->project->number }}</a> @endif {{ $t->company?->name }}</td>
                <td>{{ $t->employee?->name }}</td>
                <td class="nowrap">{{ Format::date($t->deadline) }}</td>
                <td class="num">{{ $t->estimated_hours ? Format::number($t->estimated_hours) : '' }}</td>
                <td>@can('taken.bewerken')<form method="post" action="{{ route('taken.afronden', $t) }}" class="inline">@csrf<button class="btn btn-sm" type="submit">{{ $t->completed ? 'Heropenen' : 'Afronden' }}</button></form>@endcan</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $tasks->links() }}
    @endif
</div></div>
@endsection
