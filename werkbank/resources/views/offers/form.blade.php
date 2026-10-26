@extends('layouts.app', ['title' => $offer->exists ? $offer->title().' wijzigen' : 'Nieuwe offerte'])

@section('content')
<div class="page-head">
    <div><h1>{{ $offer->exists ? $offer->title() : 'Nieuwe offerte' }}</h1>
        <div class="sub">@unless ($offer->exists) Krijgt nummer {{ $nextNumber }} bij opslaan. @endunless @if ($offer->gripp_id) Geïmporteerd uit Gripp; na wijzigen neemt een volgende import bronwijzigingen niet meer over. @endif</div>
    </div>
</div>
<form method="post" action="{{ $offer->exists ? route('offertes.update', $offer) : route('offertes.store') }}">
    @csrf @if ($offer->exists) @method('put') @endif
    <div class="card"><div class="card-b">
        <div class="form-grid">
            <x-veld name="name" label="Omschrijving" :value="$offer->name" required wrap="span-2" />
            <x-keuze name="company_id" label="Relatie" :options="$companies" :value="$offer->company_id" placeholder="— kies een relatie —" required />
            <x-keuze name="contact_id" label="Contactpersoon" :options="$contacts" :value="$offer->contact_id" placeholder="—" hint="De lijst toont contactpersonen van de gekozen relatie na opslaan." />
            <x-veld name="date" label="Datum" type="date" :value="$offer->date" required />
            <x-veld name="valid_until" label="Geldig tot" type="date" :value="$offer->valid_until" />
            <x-keuze name="phase_id" label="Fase" :options="$phases" :value="$offer->phase_id" placeholder="—" />
            <x-keuze name="account_manager_id" label="Accountmanager" :options="$employees" :value="$offer->account_manager_id" placeholder="—" />
            <x-veld name="chance" label="Kans op opdracht (%)" type="number" min="0" max="100" :value="$offer->chance" />
            <x-veld name="deadline" label="Deadline / opvolgen vóór" type="date" :value="$offer->deadline" />
            <x-veld name="client_reference" label="Referentie klant" :value="$offer->client_reference" />
            <x-tekst name="description" label="Inleiding / omschrijving" :value="$offer->description" wrap="span-2" rows="4" />
            @unless ($offer->exists)
                <x-vink name="is_template" label="Opslaan als sjabloon (zonder nummer en relatie, om nieuwe offertes van te maken)" :checked="false" />
            @endunless
        </div>
    </div></div>

    <div class="card">
        <div class="card-h"><h2>Regels</h2>
            @if ($packets)<div class="field" style="margin:0;min-width:240px"><x-keuze name="packet_id" :options="$packets" placeholder="Pakket toevoegen bij opslaan…" /></div>@endif
        </div>
        <div class="card-b">@include('partials.regels', ['lines' => $offer->lines ?? collect(), 'showBuying' => true])</div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit">Opslaan</button>
        <a class="btn" href="{{ $offer->exists ? route('offertes.show', $offer) : route('offertes.index') }}">Annuleren</a>
    </div>
</form>
@endsection
