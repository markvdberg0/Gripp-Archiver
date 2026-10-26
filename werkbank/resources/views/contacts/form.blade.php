@extends('layouts.app', ['title' => $contact->exists ? 'Contactpersoon wijzigen' : 'Nieuwe contactpersoon'])

@section('content')
<div class="page-head"><h1>{{ $contact->exists ? $contact->name : 'Nieuwe contactpersoon' }}</h1></div>
<form method="post" action="{{ $contact->exists ? route('contactpersonen.update', $contact) : route('contactpersonen.store') }}">
    @csrf @if ($contact->exists) @method('put') @endif
    <div class="card"><div class="card-b">
        <div class="form-grid">
            <x-keuze name="company_id" label="Relatie" :options="$companies" :value="$contact->company_id" placeholder="— geen relatie —" wrap="span-2" />
            <x-keuze name="salutation" label="Aanhef" :options="\App\Models\Contact::AANHEF" :value="$contact->salutation" placeholder="—" />
            <x-veld name="initials" label="Voorletters" :value="$contact->initials" />
            <x-veld name="first_name" label="Voornaam" :value="$contact->first_name" />
            <x-veld name="infix" label="Tussenvoegsel" :value="$contact->infix" />
            <x-veld name="last_name" label="Achternaam" :value="$contact->last_name" required />
            <x-veld name="function" label="Functie" :value="$contact->function" />
            <x-veld name="department" label="Afdeling" :value="$contact->department" />
            <x-veld name="email" label="E-mail" type="email" :value="$contact->email" />
            <x-veld name="phone" label="Telefoon" :value="$contact->phone" />
            <x-veld name="mobile" label="Mobiel" :value="$contact->mobile" />
            <x-veld name="tags" label="Labels" :value="$contact->exists ? $contact->tags->pluck('name')->join(', ') : ''" hint="Gescheiden door komma's." />
            <x-tekst name="notes" label="Notities" :value="$contact->notes" wrap="span-2" />
            <x-vink name="show_on_company" label="Tonen bij de relatie" :checked="$contact->show_on_company" />
            <x-vink name="active" label="Actief" :checked="$contact->active" />
        </div>
    </div></div>
    <div class="actions">
        <button class="btn btn-primary" type="submit">Opslaan</button>
        <a class="btn" href="{{ $contact->exists ? route('contactpersonen.show', $contact) : url()->previous() }}">Annuleren</a>
    </div>
</form>
@endsection
