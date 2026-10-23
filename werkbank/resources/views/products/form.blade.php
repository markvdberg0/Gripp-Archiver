@extends('layouts.app', ['title' => $product->exists ? 'Product wijzigen' : 'Nieuw product'])
@php use App\Support\Bedragen; @endphp

@section('content')
<div class="page-head"><h1>{{ $product->exists ? $product->name : 'Nieuw product' }}</h1></div>
<form method="post" action="{{ $product->exists ? route('producten.update', $product) : route('producten.store') }}" class="card">
    @csrf @if ($product->exists) @method('put') @endif
    <div class="card-b">
        <div class="form-grid">
            <x-veld name="name" label="Naam" :value="$product->name" required wrap="span-2" />
            <x-veld name="number" label="Productnummer" type="number" :value="$product->number" />
            <x-keuze name="unit_id" label="Eenheid" :options="$units" :value="$product->unit_id" placeholder="—" />
            <x-veld name="selling_price" label="Verkoopprijs (excl. btw)" inputmode="decimal" :value="Bedragen::input($product->selling_price ?? 0)" required />
            <x-keuze name="vat_rate_id" label="Btw verkoop" :options="$vatSales" :value="$product->vat_rate_id" placeholder="—" />
            <x-veld name="buying_price" label="Inkoopprijs" inputmode="decimal" :value="Bedragen::input($product->buying_price ?? 0)" required />
            <x-keuze name="purchase_vat_rate_id" label="Btw inkoop" :options="$vatPurchase" :value="$product->purchase_vat_rate_id" placeholder="—" />
            <x-keuze name="ledger_id" label="Grootboek verkoop" :options="$ledgers" :value="$product->ledger_id" placeholder="—" />
            <x-keuze name="purchase_ledger_id" label="Grootboek inkoop" :options="$ledgers" :value="$product->purchase_ledger_id" placeholder="—" />
            <x-keuze name="cost_center_id" label="Kostenplaats" :options="$costCenters" :value="$product->cost_center_id" placeholder="—" />
            <x-keuze name="invoice_basis" label="Factureren" :options="\App\Models\Product::INVOICE_BASIS" :value="$product->invoice_basis" />
            <x-keuze name="supplier_id" label="Leverancier" :options="$suppliers" :value="$product->supplier_id" placeholder="—" />
            <x-veld name="supplier_code" label="Bestelcode leverancier" :value="$product->supplier_code" />
            <x-tekst name="description" label="Omschrijving (komt op offertes)" :value="$product->description" wrap="span-2" />
            <x-tekst name="internal_note" label="Interne notitie" :value="$product->internal_note" wrap="span-2" />
            <x-veld name="tags" label="Labels" :value="$product->exists ? $product->tags->pluck('name')->join(', ') : ''" />
            <x-vink name="archived" label="Gearchiveerd (niet meer kiezen in nieuwe regels)" :checked="$product->archived" />
        </div>
        <div class="actions"><button class="btn btn-primary" type="submit">Opslaan</button><a class="btn" href="{{ $product->exists ? route('producten.show', $product) : route('producten.index') }}">Annuleren</a></div>
    </div>
</form>
@endsection
