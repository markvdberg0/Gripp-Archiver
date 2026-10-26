@extends('layouts.app', ['title' => $packet->exists ? 'Pakket wijzigen' : 'Nieuw pakket'])

@section('content')
<div class="page-head"><h1>{{ $packet->exists ? $packet->name : 'Nieuw pakket' }}</h1></div>
<form method="post" action="{{ $packet->exists ? route('pakketten.update', $packet) : route('pakketten.store') }}">
    @csrf @if ($packet->exists) @method('put') @endif
    <div class="card"><div class="card-b"><div class="form-grid">
        <x-veld name="name" label="Naam" :value="$packet->name" required />
        <x-veld name="number" label="Nummer" type="number" :value="$packet->number" />
        <x-keuze name="unit_id" label="Eenheid" :options="$units" :value="$packet->unit_id" placeholder="—" />
        <x-vink name="archived" label="Gearchiveerd" :checked="$packet->archived" />
        <x-tekst name="description" label="Omschrijving" :value="$packet->description" wrap="span-2" />
    </div></div></div>
    <div class="card"><div class="card-h"><h2>Regels</h2></div><div class="card-b">@include('partials.regels', ['lines' => $packet->lines ?? collect(), 'showBuying' => true])</div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Opslaan</button><a class="btn" href="{{ $packet->exists ? route('pakketten.show', $packet) : route('pakketten.index') }}">Annuleren</a></div>
</form>
@endsection
