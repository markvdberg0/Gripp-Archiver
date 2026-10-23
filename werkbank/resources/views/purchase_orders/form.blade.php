@extends('layouts.app', ['title' => $order->exists ? 'Inkoopopdracht wijzigen' : 'Nieuwe inkoopopdracht'])

@section('content')
<div class="page-head"><div><h1>{{ $order->exists ? 'Inkoopopdracht '.$order->number : 'Nieuwe inkoopopdracht' }}</h1>
    <div class="sub">@unless ($order->exists) Krijgt nummer {{ $nextNumber }} bij opslaan. @endunless</div></div></div>
<form method="post" action="{{ $order->exists ? route('inkoopopdrachten.update', $order) : route('inkoopopdrachten.store') }}">
    @csrf @if ($order->exists) @method('put') @endif
    <div class="card"><div class="card-b"><div class="form-grid">
        <x-veld name="name" label="Omschrijving" :value="$order->name" required wrap="span-2" />
        <x-keuze name="supplier_id" label="Leverancier" :options="$suppliers" :value="$order->supplier_id" placeholder="— kies een leverancier —" required />
        <x-keuze name="contact_id" label="Contactpersoon" :options="$contacts" :value="$order->contact_id" placeholder="—" />
        <x-keuze name="project_id" label="Voor opdracht" :options="$projects" :value="$order->project_id" placeholder="—" />
        <x-keuze name="employee_id" label="Besteld door" :options="$employees" :value="$order->employee_id" placeholder="—" />
        <x-veld name="date" label="Datum" type="date" :value="$order->date" required />
        <x-veld name="delivery_date" label="Gewenste leverdatum" type="date" :value="$order->delivery_date" />
        <x-veld name="reference" label="Referentie / offertenummer leverancier" :value="$order->reference" />
        <x-veld name="delivery_address" label="Afleveradres" :value="$order->delivery_address" />
        <x-tekst name="description" label="Toelichting" :value="$order->description" wrap="span-2" rows="3" />
    </div></div></div>
    <div class="card"><div class="card-h"><h2>Regels</h2></div><div class="card-b">@include('partials.regels', ['lines' => $order->lines ?? collect(), 'hiddenFields' => ['project_id']])</div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Opslaan</button><a class="btn" href="{{ $order->exists ? route('inkoopopdrachten.show', $order) : route('inkoopopdrachten.index') }}">Annuleren</a></div>
</form>
@endsection
