@extends('layouts.app', ['title' => $contract->exists ? 'Contract wijzigen' : 'Nieuw contract'])

@section('content')
<div class="page-head"><h1>{{ $contract->exists ? 'Contract '.$contract->number : 'Nieuw contract' }}</h1></div>
<form method="post" action="{{ $contract->exists ? route('contracten.update', $contract) : route('contracten.store') }}">
    @csrf @if ($contract->exists) @method('put') @endif
    <div class="card"><div class="card-b"><div class="form-grid">
        <x-veld name="name" label="Omschrijving" :value="$contract->name" required wrap="span-2" />
        <x-keuze name="company_id" label="Relatie" :options="$companies" :value="$contract->company_id" placeholder="— kies een relatie —" required />
        <x-keuze name="contact_id" label="Contactpersoon" :options="$contacts" :value="$contract->contact_id" placeholder="—" />
        <x-keuze name="status" label="Status" :options="\App\Models\Contract::STATUS" :value="$contract->status" required />
        <x-keuze name="frequency" label="Factureren" :options="\App\Models\Contract::FREQUENCIES" :value="$contract->frequency" required />
        <x-veld name="start_date" label="Startdatum" type="date" :value="$contract->start_date" required />
        <x-veld name="end_date" label="Einddatum" type="date" :value="$contract->end_date" />
        <x-veld name="next_invoice_date" label="Volgende factuurdatum" type="date" :value="$contract->next_invoice_date" hint="Leeg: er worden geen facturen meer gemaakt." />
        <x-vink name="auto_renew" label="Automatisch verlengen na einddatum" :checked="$contract->auto_renew" />
        <x-veld name="invoice_subject" label="Onderwerp op de factuur" :value="$contract->invoice_subject" />
        <x-veld name="client_reference" label="Referentie klant" :value="$contract->client_reference" />
        <x-tekst name="description" label="Omschrijving" :value="$contract->description" wrap="span-2" rows="3" />
    </div></div></div>
    <div class="card"><div class="card-h"><h2>Regels per periode</h2></div><div class="card-b">@include('partials.regels', ['lines' => $contract->lines ?? collect()])</div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Opslaan</button><a class="btn" href="{{ $contract->exists ? route('contracten.show', $contract) : route('contracten.index') }}">Annuleren</a></div>
</form>
@endsection
