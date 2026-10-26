@extends('layouts.app', ['title' => $product->name])
@php use App\Support\Format; use App\Support\Html; @endphp

@section('content')
<div class="page-head">
    <div><h1>{{ $product->name }}</h1><div class="sub">Product {{ $product->number }} @if ($product->archived)<span class="badge">gearchiveerd</span>@endif @foreach ($product->tags as $t)<span class="badge">{{ $t->name }}</span> @endforeach</div></div>
    <div class="actions">@can('producten.bewerken')<a class="btn btn-primary" href="{{ route('producten.edit', $product) }}">Wijzigen</a>@endcan</div>
</div>
<div class="grid grid-side">
    <div>
        <div class="card"><div class="card-h"><h2>Gegevens</h2></div><div class="card-b">
            <dl class="props">
                <dt>Verkoopprijs</dt><dd>{{ Format::euro($product->selling_price) }} per {{ $product->unit?->name ?? 'eenheid' }} · {{ $product->vatRate?->name }}</dd>
                <dt>Inkoopprijs</dt><dd>{{ Format::euro($product->buying_price) }} · {{ $product->purchaseVatRate?->name }}</dd>
                <dt>Marge</dt><dd>{{ $product->margin() !== null ? Format::number($product->margin(), 1).'%' : '—' }}</dd>
                <dt>Factureren</dt><dd>{{ \App\Models\Product::INVOICE_BASIS[$product->invoice_basis] ?? $product->invoice_basis }}</dd>
                <dt>Grootboek verkoop</dt><dd>{{ $product->ledger?->label() ?? '—' }}</dd>
                <dt>Grootboek inkoop</dt><dd>{{ $product->purchaseLedger?->label() ?? '—' }}</dd>
                <dt>Kostenplaats</dt><dd>{{ $product->costCenter?->label() ?? '—' }}</dd>
                <dt>Leverancier</dt><dd>@if ($product->supplier)<a href="{{ route('relaties.show', $product->supplier) }}">{{ $product->supplier->name }}</a>@else — @endif {{ $product->supplier_code ? '· bestelcode '.$product->supplier_code : '' }}</dd>
                @if ($product->description)<dt>Omschrijving</dt><dd>{{ Html::render($product->description) }}</dd>@endif
                @if ($product->internal_note)<dt>Interne notitie</dt><dd>{{ Html::render($product->internal_note) }}</dd>@endif
                @foreach ($product->extra_fields ?? [] as $k => $v)
                    @if ($v !== null && $v !== '')<dt>{{ ucfirst(str_replace('_', ' ', $k)) }}</dt><dd>{{ is_bool($v) ? ($v ? 'ja' : 'nee') : $v }}</dd>@endif
                @endforeach
            </dl>
        </div></div>
        <div class="card"><div class="card-h"><h2>Recent gebruikt in offertes</h2></div><div class="card-b flush">
            @if ($usage->isEmpty())<div class="empty">Nog niet gebruikt.</div>@else
            <table class="list"><thead><tr><th>Offerte</th><th class="num">Aantal</th><th class="num">Prijs</th></tr></thead><tbody>
                @foreach ($usage as $u)
                    @if ($u->offer)<tr><td><a href="{{ route('offertes.show', $u->offer) }}">{{ $u->offer->number }} · {{ $u->offer->name }}</a></td><td class="num">{{ Format::number($u->quantity, 4) }}</td><td class="num">{{ Format::euro($u->unit_price) }}</td></tr>@endif
                @endforeach
            </tbody></table>
            @endif
        </div></div>
    </div>
    <div>@include('partials.bestanden', ['model' => $product, 'type' => 'product', 'canUpload' => auth()->user()->can('producten.bewerken')])</div>
</div>
@endsection
