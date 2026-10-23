@extends('layouts.app', ['title' => 'Omzet en inkoop '.$year])
@php use App\Support\Format; $max = max(1, $months->max('sales'), $months->max('purchases')); @endphp

@section('content')
<div class="page-head">
    <div><h1>Omzet en inkoop {{ $year }}</h1><div class="sub">Definitieve verkoopfacturen en (niet-concept) inkoopfacturen op factuurdatum, excl. btw.</div></div>
    <div class="actions"><a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">CSV</a></div>
</div>
<form class="filters" method="get"><x-keuze name="jaar" label="Jaar" :options="$years->mapWithKeys(fn ($y) => [$y => $y])->all()" :value="$year" data-autosubmit /></form>
<div class="tiles">
    <div class="tile"><div class="k">Omzet</div><div class="v">{{ Format::euro($months->sum('sales')) }}</div></div>
    <div class="tile"><div class="k">Inkoop</div><div class="v">{{ Format::euro($months->sum('purchases')) }}</div></div>
    <div class="tile"><div class="k">Verschil</div><div class="v {{ $months->sum('result') < 0 ? 'neg' : '' }}">{{ Format::euro($months->sum('result')) }}</div></div>
</div>
<div class="card"><div class="card-b flush">
    <table class="list">
        <thead><tr><th>Maand</th><th class="num">Omzet</th><th class="num">Inkoop</th><th class="num">Verschil</th><th style="width:40%"><span class="muted small">■ omzet ■ inkoop</span></th></tr></thead>
        <tbody>
        @foreach ($months as $m)
            <tr>
                <td>{{ $m['label'] }}</td>
                <td class="num">{{ Format::euro($m['sales']) }}</td>
                <td class="num">{{ Format::euro($m['purchases']) }}</td>
                <td class="num {{ $m['result'] < 0 ? 'neg' : '' }}">{{ Format::euro($m['result']) }}</td>
                <td>
                    <div title="Omzet {{ Format::euro($m['sales']) }}" style="height:8px;border-radius:4px;background:var(--accent);width:{{ max(0, round($m['sales'] / $max * 100, 1)) }}%"></div>
                    <div title="Inkoop {{ Format::euro($m['purchases']) }}" style="height:8px;border-radius:4px;background:#c58b2a;margin-top:3px;width:{{ max(0, round($m['purchases'] / $max * 100, 1)) }}%"></div>
                </td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td>Totaal</td><td class="num">{{ Format::euro($months->sum('sales')) }}</td><td class="num">{{ Format::euro($months->sum('purchases')) }}</td><td class="num">{{ Format::euro($months->sum('result')) }}</td><td></td></tr></tfoot>
    </table>
</div></div>
<p class="muted small">Dit is geen winst-en-verliesrekening: lonen, afschrijvingen en andere kosten zitten in de boekhouding.</p>
@endsection
