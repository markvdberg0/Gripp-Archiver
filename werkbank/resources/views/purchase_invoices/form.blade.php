@extends('layouts.app', ['title' => $invoice->exists ? 'Inkoopfactuur wijzigen' : 'Inkoopfactuur boeken'])
@php use App\Support\Bedragen; @endphp

@section('content')
<div class="page-head"><div><h1>{{ $invoice->exists ? 'Inkoopfactuur '.$invoice->booking_number : 'Inkoopfactuur boeken' }}</h1>
    <div class="sub">Neem de factuur van de leverancier over. Upload de PDF na opslaan bij Bestanden.</div></div></div>
<form method="post" action="{{ $invoice->exists ? route('inkoopfacturen.update', $invoice) : route('inkoopfacturen.store') }}">
    @csrf @if ($invoice->exists) @method('put') @endif
    <div class="card"><div class="card-b"><div class="form-grid">
        <x-keuze name="supplier_id" label="Leverancier" :options="$suppliers" :value="$invoice->supplier_id" placeholder="— kies een leverancier —" required />
        <x-veld name="invoice_number" label="Factuurnummer leverancier" :value="$invoice->invoice_number" />
        <x-veld name="date" label="Factuurdatum" type="date" :value="$invoice->date" required />
        <x-veld name="due_date" label="Vervaldatum" type="date" :value="$invoice->due_date" hint="Leeg: factuurdatum + betaaltermijn leverancier (standaard 30 dagen)." />
        <x-veld name="expected_total" label="Totaal incl. btw volgens de factuur" inputmode="decimal" :value="Bedragen::input($invoice->expected_total)" hint="Wordt bij de controle vergeleken met het totaal van de regels." />
        <x-keuze name="payment_method" label="Betaalwijze" :options="\App\Models\Company::BETAALWIJZEN" :value="$invoice->payment_method ?? 'overboeking'" />
        <x-keuze name="purchase_order_id" label="Inkoopopdracht" :options="$orders" :value="$invoice->purchase_order_id" placeholder="—" />
        <x-keuze name="project_id" label="Opdracht" :options="$projects" :value="$invoice->project_id" placeholder="—" />
        <x-keuze name="contact_id" label="Contactpersoon" :options="$contacts" :value="$invoice->contact_id" placeholder="—" />
        <x-veld name="subject" label="Onderwerp" :value="$invoice->subject" />
        <x-tekst name="description" label="Toelichting" :value="$invoice->description" wrap="span-2" rows="2" />
    </div></div></div>
    <div class="card"><div class="card-h"><h2>Regels</h2></div><div class="card-b">@include('partials.regels', ['lines' => $prefill, 'hiddenFields' => ['project_id', 'purchase_order_line_id']])</div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Opslaan</button><a class="btn" href="{{ $invoice->exists ? route('inkoopfacturen.show', $invoice) : route('inkoopfacturen.index') }}">Annuleren</a></div>
</form>
@endsection
