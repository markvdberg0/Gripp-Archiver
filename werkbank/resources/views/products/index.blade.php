@extends('layouts.app', ['title' => 'Producten'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Producten</h1><div class="sub">{{ $products->total() }} producten</div></div>
    <div class="actions">@can('producten.bewerken')<a class="btn btn-primary" href="{{ route('producten.create') }}">Nieuw product</a>@endcan</div>
</div>
<form class="filters" method="get">
    <div class="field"><label for="q">Zoeken</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Naam, nummer of bestelcode"></div>
    <x-keuze name="archief" label="Archief" :options="['' => 'Zonder gearchiveerde', '1' => 'Met gearchiveerde']" :value="request('archief')" />
    <button class="btn" type="submit">Filteren</button>
</form>
<div class="card"><div class="card-b flush">
    @if ($products->isEmpty())<div class="empty">Geen producten gevonden.</div>@else
    <table class="list">
        <thead><tr><th><a href="{{ request()->fullUrlWithQuery(['sort' => 'number']) }}">Nr.</a></th><th><a href="{{ request()->fullUrlWithQuery(['sort' => null]) }}">Naam</a></th><th>Eenheid</th><th class="num">Verkoop</th><th class="num">Inkoop</th><th class="num">Marge</th><th>Leverancier</th></tr></thead>
        <tbody>
        @foreach ($products as $p)
            <tr>
                <td class="muted">{{ $p->number }}</td>
                <td><a href="{{ route('producten.show', $p) }}">{{ $p->name }}</a> @if ($p->archived)<span class="badge">archief</span>@endif</td>
                <td>{{ $p->unit?->name }}</td>
                <td class="num">{{ Format::euro($p->selling_price) }}</td>
                <td class="num">{{ Format::euro($p->buying_price) }}</td>
                <td class="num">{{ $p->margin() !== null ? Format::number($p->margin(), 1).'%' : '' }}</td>
                <td>{{ $p->supplier?->name }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $products->links() }}
    @endif
</div></div>
@endsection
