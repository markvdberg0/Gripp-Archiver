@extends('layouts.app', ['title' => 'Openstaande '.$soort])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Openstaande {{ $soort }}</h1><div class="sub">Peildatum {{ Format::date($on) }} · {{ $rows->count() }} facturen · {{ Format::euro($total) }} openstaand</div></div>
    <div class="actions"><a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">CSV</a></div>
</div>
<form class="filters" method="get"><x-veld name="peildatum" label="Peildatum" type="date" :value="$on" /><button class="btn" type="submit">Toon</button></form>
<div class="tiles">
    @foreach ($bucketTotals as $label => $value)
        <div class="tile"><div class="k">{{ ucfirst($label) }}</div><div class="v" style="font-size:18px">{{ Format::euro($value) }}</div></div>
    @endforeach
</div>
<div class="card"><div class="card-h"><h2>Per {{ $soort === 'debiteuren' ? 'klant' : 'leverancier' }}</h2></div><div class="card-b flush">
    @if ($perParty->isEmpty())<div class="empty">Niets openstaand op deze datum.</div>@else
    <table class="list">
        <thead><tr><th>Relatie</th><th class="num">Facturen</th>@foreach ($buckets as $b)<th class="num">{{ ucfirst($b) }}</th>@endforeach<th class="num">Totaal</th></tr></thead>
        <tbody>@foreach ($perParty as $p)<tr><td>{{ $p['party'] }}</td><td class="num">{{ $p['count'] }}</td>@foreach ($p['buckets'] as $v)<td class="num">{{ $v ? Format::euro($v) : '' }}</td>@endforeach<td class="num"><strong>{{ Format::euro($p['open']) }}</strong></td></tr>@endforeach</tbody>
        <tfoot><tr><td>Totaal</td><td class="num">{{ $rows->count() }}</td>@foreach ($bucketTotals as $v)<td class="num">{{ Format::euro($v) }}</td>@endforeach<td class="num">{{ Format::euro($total) }}</td></tr></tfoot>
    </table>
    @endif
</div></div>
<div class="card"><div class="card-h"><h2>Facturen</h2></div><div class="card-b flush">
    @if ($rows->isNotEmpty())
    <table class="list">
        <thead><tr><th>Relatie</th><th>Nummer</th><th>Datum</th><th>Vervaldatum</th><th class="num">Dagen vervallen</th><th class="num">Totaal</th><th class="num">Openstaand</th></tr></thead>
        <tbody>@foreach ($rows as $r)<tr><td>{{ $r['party'] }}</td><td>{{ $r['number'] }}</td><td>{{ Format::date($r['date']) }}</td><td>{{ Format::date($r['due']) }}</td><td class="num {{ $r['days'] > 0 ? 'neg' : '' }}">{{ $r['days'] ?: '' }}</td><td class="num">{{ Format::euro($r['total']) }}</td><td class="num">{{ Format::euro($r['open']) }}</td></tr>@endforeach</tbody>
    </table>
    @endif
</div></div>
@endsection
