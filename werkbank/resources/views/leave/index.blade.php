@extends('layouts.app', ['title' => 'Verlof'])
@php
    use App\Support\Format;
    $badge = ['aangevraagd' => 'badge-warn', 'goedgekeurd' => 'badge-ok', 'afgewezen' => 'badge-danger', 'ingetrokken' => '', 'onbekend' => ''];
@endphp

@section('content')
<div class="page-head">
    <div><h1>Verlof {{ $year }}</h1><div class="sub">{{ $employee?->name ?? 'Iedereen' }}</div></div>
    <div class="actions">
        @if ($all)<a class="btn" href="{{ route('verlof.fiatteren') }}">Goedkeuren @if ($pending)<span class="badge badge-warn">{{ $pending }}</span>@endif</a>@endif
        <a class="btn btn-primary" href="{{ route('verlof.create') }}">Verlof aanvragen</a>
    </div>
</div>

<form class="filters" method="get">
    @if ($all)<x-keuze name="medewerker" label="Medewerker" :options="['alle' => 'Iedereen'] + $employees" :value="request('medewerker', $employee?->id)" />@endif
    <div class="field"><label for="jaar">Jaar</label><input id="jaar" type="number" name="jaar" value="{{ $year }}" min="2000" max="2100" style="width:100px"></div>
    <button class="btn" type="submit">Tonen</button>
</form>

@if ($balance)
<div class="tiles">
    <div class="tile"><div class="k">Recht {{ $year }}</div><div class="v">{{ Format::number($balance['recht']) }} uur</div></div>
    <div class="tile"><div class="k">Goedgekeurd</div><div class="v">{{ Format::number($balance['goedgekeurd']) }} uur</div>@if ($balance['aangevraagd'] > 0)<div class="small muted">+ {{ Format::number($balance['aangevraagd']) }} uur aangevraagd</div>@endif</div>
    <div class="tile"><div class="k">Over</div><div class="v {{ $balance['over'] < 0 ? 'neg' : '' }}">{{ Format::number($balance['over']) }} uur</div></div>
</div>
<p class="muted small">Alleen vakantie en vrije dagen tellen mee voor het saldo; bijzonder en onbetaald verlof niet. Niet-opgenomen uren van vorig jaar worden niet automatisch meegenomen.</p>
@endif

<div class="card">
    <div class="card-b flush">
        @if ($requests->isEmpty())
            <div class="empty">Geen verlof in {{ $year }}.</div>
        @else
        <table class="list">
            <thead><tr>@if (! $employee)<th>Medewerker</th>@endif<th>Periode</th><th>Soort</th><th>Omschrijving</th><th class="num">Uren</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($requests as $r)
                <tr>
                    @if (! $employee)<td>{{ $r->employee?->name }}</td>@endif
                    <td class="nowrap">{{ $r->period() }}</td>
                    <td>{{ $r->typeLabel() }}</td>
                    <td>{{ $r->description }}@if ($r->decision_note)<div class="muted small">{{ $r->decider?->name }}: {{ $r->decision_note }}</div>@endif</td>
                    <td class="num">{{ Format::number($r->hours()) }}</td>
                    <td><span class="badge {{ $badge[$r->status] ?? '' }}">{{ $r->statusLabel() }}</span></td>
                    <td class="num">
                        @if ($r->canWithdraw() && ($r->employee_id === auth()->user()->employee?->id || $all))
                            <form method="post" action="{{ route('verlof.intrekken', $r) }}" data-confirm="Deze aanvraag intrekken?">@csrf<button class="btn" type="submit">Intrekken</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
