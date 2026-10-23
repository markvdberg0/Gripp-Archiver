@extends('layouts.app', ['title' => 'Instellingen'])

@section('content')
<div class="page-head"><div><h1>Instellingen</h1><div class="sub">Bedrijfsgegevens op documenten, standaardwaarden en nummering.</div></div></div>
<form method="post" action="{{ route('beheer.instellingen.update') }}">
    @csrf @method('put')
    <div class="grid grid-2">
        <fieldset><legend>Bedrijfsgegevens</legend>
            <x-veld name="bedrijf__naam" label="Bedrijfsnaam" :value="$values['bedrijf__naam']" />
            <x-veld name="bedrijf__adres" label="Adres" :value="$values['bedrijf__adres']" />
            <div class="form-grid">
                <x-veld name="bedrijf__postcode" label="Postcode" :value="$values['bedrijf__postcode']" />
                <x-veld name="bedrijf__plaats" label="Plaats" :value="$values['bedrijf__plaats']" />
            </div>
            <x-veld name="bedrijf__land" label="Land" :value="$values['bedrijf__land']" />
            <div class="form-grid">
                <x-veld name="bedrijf__telefoon" label="Telefoon" :value="$values['bedrijf__telefoon']" />
                <x-veld name="bedrijf__email" label="E-mail" type="email" :value="$values['bedrijf__email']" />
            </div>
            <x-veld name="bedrijf__website" label="Website" :value="$values['bedrijf__website']" />
            <div class="form-grid">
                <x-veld name="bedrijf__kvk" label="KvK-nummer" :value="$values['bedrijf__kvk']" />
                <x-veld name="bedrijf__btw" label="Btw-nummer" :value="$values['bedrijf__btw']" />
                <x-veld name="bedrijf__iban" label="IBAN" :value="$values['bedrijf__iban']" />
                <x-veld name="bedrijf__bic" label="BIC" :value="$values['bedrijf__bic']" />
            </div>
        </fieldset>
        <div>
            <fieldset><legend>Offertes</legend>
                <x-veld name="offerte__geldigheid_dagen" label="Standaard geldigheid (dagen)" type="number" :value="$values['offerte__geldigheid_dagen']" required />
                <x-tekst name="offerte__voettekst" label="Slottekst onder offertes" :value="$values['offerte__voettekst']" rows="3" />
            </fieldset>
            <fieldset><legend>Facturen</legend>
                <x-veld name="factuur__betaaltermijn" label="Standaard betaaltermijn (dagen)" type="number" :value="$values['factuur__betaaltermijn']" required />
                <x-tekst name="factuur__voettekst" label="Slottekst onder facturen" :value="$values['factuur__voettekst']" rows="3" hint="Bijvoorbeeld: Wij verzoeken u het bedrag binnen de termijn over te maken onder vermelding van het factuurnummer." />
            </fieldset>
            <fieldset><legend>Nummering</legend>
                <p class="muted small">Leeg laten: doornummeren vanaf het hoogste bestaande nummer. Een hoger getal laat de nummering daar verdergaan.</p>
                <div class="form-grid">
                    <x-veld name="nummer__offerte" label="Volgende offertenummer" type="number" :value="$values['nummer__offerte']" />
                    <x-veld name="nummer__opdracht" label="Volgende opdrachtnummer" type="number" :value="$values['nummer__opdracht']" />
                    <x-veld name="nummer__factuur" label="Volgende factuurnummer" type="number" :value="$values['nummer__factuur']" />
                    <x-veld name="nummer__inkoopopdracht" label="Volgende inkoopopdrachtnummer" type="number" :value="$values['nummer__inkoopopdracht']" />
                    <x-veld name="nummer__boeknummer" label="Volgende boeknummer inkoopfactuur" type="number" :value="$values['nummer__boeknummer']" />
                </div>
            </fieldset>
            <fieldset><legend>Inkoop</legend>
                @can('beheer.gebruikers')
                    <x-veld name="inkoop__vier_ogen_drempel" label="Vier-ogendrempel (€ incl. btw)" type="number" step="0.01" :value="$values['inkoop__vier_ogen_drempel']" required hint="Boven dit bedrag moet iemand anders dan de invoerder of laatste wijziger de inkoopfactuur goedkeuren. 0 = altijd." />
                @else
                    <p class="small">Vier-ogendrempel: € {{ $values['inkoop__vier_ogen_drempel'] }} incl. btw. Alleen een beheerder kan dit wijzigen.</p>
                @endcan
            </fieldset>
        </div>
    </div>
    <button class="btn btn-primary" type="submit">Opslaan</button>
</form>
@endsection
