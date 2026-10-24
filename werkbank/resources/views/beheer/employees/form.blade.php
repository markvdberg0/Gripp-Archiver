@extends('layouts.app', ['title' => $employee->exists ? 'Medewerker wijzigen' : 'Nieuwe medewerker'])

@section('content')
<div class="page-head"><h1>{{ $employee->exists ? $employee->name : 'Nieuwe medewerker' }}</h1></div>
<form method="post" action="{{ $employee->exists ? route('beheer.medewerkers.update', $employee) : route('beheer.medewerkers.store') }}" class="card">
    @csrf @if ($employee->exists) @method('put') @endif
    <div class="card-b">
        <div class="form-grid">
            <x-veld name="first_name" label="Voornaam" :value="$employee->first_name" />
            <x-veld name="infix" label="Tussenvoegsel" :value="$employee->infix" />
            <x-veld name="last_name" label="Achternaam" :value="$employee->last_name" required />
            <x-veld name="initials" label="Voorletters" :value="$employee->initials" />
            <x-veld name="number" label="Personeelsnummer" type="number" :value="$employee->number" />
            <x-veld name="function" label="Functie" :value="$employee->function" />
            <x-veld name="email" label="E-mail (werk)" type="email" :value="$employee->email" />
            <x-veld name="phone" label="Telefoon (werk)" :value="$employee->phone" />
            <x-veld name="mobile" label="Mobiel (werk)" :value="$employee->mobile" />
            <x-veld name="employed_since" label="In dienst sinds" type="date" :value="$employee->employed_since" />
            <x-veld name="leave_hours_per_year" label="Verlofuren per jaar" type="number" step="0.5" :value="$employee->leave_hours_per_year" hint="Leeg = standaard uit de instellingen." />
        </div>
        <x-vink name="active" label="Actief" :checked="$employee->active" />
        <p class="muted small">Werkbank slaat bewust geen BSN, bankgegevens of privéadres van medewerkers op. Die horen in de salarisadministratie.</p>
        <div class="actions"><button class="btn btn-primary" type="submit">Opslaan</button><a class="btn" href="{{ route('beheer.medewerkers.index') }}">Annuleren</a></div>
    </div>
</form>
@endsection
