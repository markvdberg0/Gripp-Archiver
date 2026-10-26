{{--
    Regels-editor. Variabelen:
      $lines     collectie DocumentLine (of oude invoer)
      $products  Product::forPicker()
      $units     Unit::options()
      $vatRates  VatRate::options()
      $extra     optioneel: ['ledger_id' => ['Grootboek', $options], …] extra kolommen per regel
      $showBuying optioneel: inkoopprijs tonen
--}}
@php
    use App\Support\Bedragen;
    $extra = $extra ?? [];
    $showBuying = $showBuying ?? false;
    $hiddenFields = $hiddenFields ?? [];
    $rows = old('lines', $lines->map(fn ($l) => is_array($l) ? $l : $l->only(array_merge(['id', 'row_type', 'product_id', 'title', 'description', 'quantity', 'unit_id', 'unit_price', 'buying_price', 'discount', 'vat_rate_id'], array_keys($extra), $hiddenFields)))->all());
    $tableId = $tableId ?? 'regels';
@endphp
@error('lines')<div class="alert alert-error">{{ $message }}</div>@enderror
@if ($errors->has('lines.*'))<div class="alert alert-error">Controleer de regels: {{ collect($errors->get('lines.*'))->flatten()->first() }}</div>@endif
<div style="overflow-x:auto">
<table class="list lines" id="{{ $tableId }}" data-lines data-template="{{ $tableId }}-tpl">
    <thead><tr>
        <th></th><th>Soort</th><th>Product</th><th style="min-width:220px">Omschrijving</th><th class="num">Aantal</th><th>Eenheid</th><th class="num">Prijs</th>
        @if ($showBuying)<th class="num">Inkoop</th>@endif
        <th class="num">Korting %</th><th>Btw</th>
        @foreach ($extra as [$label]) <th>{{ $label }}</th> @endforeach
        <th class="num">Totaal</th><th></th>
    </tr></thead>
    <tbody>
        @foreach ($rows as $i => $r)
            @include('partials.regel', ['i' => $i, 'r' => $r])
        @endforeach
    </tbody>
    <tfoot>
        <tr><td colspan="{{ 10 + count($extra) + ($showBuying ? 1 : 0) }}" class="right">Totaal excl. btw</td><td class="num" data-sum="excl"></td><td></td></tr>
        <tr><td colspan="{{ 10 + count($extra) + ($showBuying ? 1 : 0) }}" class="right">Btw</td><td class="num" data-sum="vat"></td><td></td></tr>
        <tr><td colspan="{{ 10 + count($extra) + ($showBuying ? 1 : 0) }}" class="right">Totaal incl. btw</td><td class="num" data-sum="incl"></td><td></td></tr>
    </tfoot>
</table>
</div>
<template id="{{ $tableId }}-tpl">@include('partials.regel', ['i' => '__i__', 'r' => ['row_type' => 'normaal', 'quantity' => 1, 'discount' => 0, 'vat_rate_id' => $vatRates[0]->id ?? null]])</template>
<p><button class="btn btn-sm" data-add-line="{{ $tableId }}" type="button">Regel toevoegen</button> <span class="muted small">Groepskoppen en tekstregels tellen niet mee in het totaal. De totalen worden bij opslaan opnieuw berekend.</span></p>
