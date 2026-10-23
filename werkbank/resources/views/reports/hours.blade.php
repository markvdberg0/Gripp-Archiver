@extends('layouts.app', ['title' => 'Uren '.$year])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Uren per medewerker {{ $year }}</h1></div>
    <div class="actions"><a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">CSV</a></div>
</div>
<form class="filters" method="get"><x-veld name="jaar" label="Jaar" type="number" :value="$year" style="width:100px" /><button class="btn" type="submit">Toon</button></form>
<div class="card"><div class="card-b flush">
    @if ($rows->isEmpty())<div class="empty">Geen uren in {{ $year }}.</div>@else
    <table class="list">
        <thead><tr><th>Medewerker</th>@foreach (range(1, 12) as $m)<th class="num">{{ \Carbon\CarbonImmutable::create($year, $m, 1)->translatedFormat('M') }}</th>@endforeach<th class="num">Totaal</th><th class="num">Gefiatteerd</th></tr></thead>
        <tbody>@foreach ($rows as $r)<tr><td>{{ $r['name'] }}</td>@foreach ($r['months'] as $h)<td class="num">{{ $h ? Format::number($h) : '' }}</td>@endforeach<td class="num"><strong>{{ Format::number($r['total']) }}</strong></td><td class="num">{{ Format::number($r['approved']) }}</td></tr>@endforeach</tbody>
    </table>
    @endif
</div></div>
@endsection
