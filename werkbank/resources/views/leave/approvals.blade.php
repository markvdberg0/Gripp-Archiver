@extends('layouts.app', ['title' => 'Verlof goedkeuren'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Verlof goedkeuren</h1><div class="sub">{{ $requests->count() }} {{ $requests->count() === 1 ? 'aanvraag' : 'aanvragen' }} wachten op een beslissing</div></div>
    <div class="actions"><a class="btn" href="{{ route('verlof.index', ['medewerker' => 'alle']) }}">Alle verlof</a></div>
</div>

@forelse ($requests as $r)
    @php $balance = $r->employee?->leaveBalance($r->lines->first()?->date?->year ?? now()->year); @endphp
    <div class="card">
        <div class="card-h"><h2>{{ $r->employee?->name }} · {{ $r->period() }}</h2><span class="badge">{{ $r->typeLabel() }}</span></div>
        <div class="card-b">
            <dl class="props">
                <dt>Uren</dt><dd>{{ Format::number($r->hours()) }} ({{ $r->lines->count() }} {{ $r->lines->count() === 1 ? 'dag' : 'dagen' }})</dd>
                @if ($r->description)<dt>Omschrijving</dt><dd>{{ $r->description }}</dd>@endif
                @if ($r->comment)<dt>Toelichting</dt><dd>{{ $r->comment }}</dd>@endif
                @if ($balance && \App\Models\LeaveRequest::TYPES[$r->type][1] ?? false)<dt>Saldo na goedkeuren</dt><dd>{{ Format::number($balance['over'] - $r->hours()) }} uur</dd>@endif
            </dl>
            @if ($r->employee_id === auth()->user()->employee?->id)
                <p class="muted small">Dit is je eigen aanvraag; iemand anders keurt hem goed.</p>
            @else
            <form method="post" action="{{ route('verlof.beslissen', $r) }}" class="filters">
                @csrf
                <div class="field" style="flex:1"><label for="note{{ $r->id }}">Toelichting (verplicht bij afwijzen)</label><input id="note{{ $r->id }}" name="decision_note" maxlength="255"></div>
                <button class="btn btn-primary" name="actie" value="goedkeuren" type="submit">Goedkeuren</button>
                <button class="btn btn-danger" name="actie" value="afwijzen" type="submit">Afwijzen</button>
            </form>
            @endif
        </div>
    </div>
@empty
    <div class="card"><div class="empty">Er wachten geen aanvragen.</div></div>
@endforelse
@endsection
