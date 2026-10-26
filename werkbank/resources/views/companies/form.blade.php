@extends('layouts.app', ['title' => $company->exists ? 'Relatie wijzigen' : 'Nieuwe relatie'])

@section('content')
<div class="page-head">
    <div><h1>{{ $company->exists ? $company->name : 'Nieuwe relatie' }}</h1>
        @if ($company->exists && $company->gripp_id)<div class="sub">Geïmporteerd uit Gripp (#{{ $company->gripp_id }}). Na wijzigen neemt een volgende import bronwijzigingen niet meer over.</div>@endif
    </div>
</div>

<form method="post" action="{{ $company->exists ? route('relaties.update', $company) : route('relaties.store') }}">
    @csrf
    @if ($company->exists) @method('put') @endif

    <div class="card"><div class="card-b">
        <div class="form-grid">
            <x-veld name="name" label="Naam" :value="$company->name" required maxlength="255" />
            <x-veld name="legal_name" label="Statutaire naam" :value="$company->legal_name" />
            <x-veld name="number" label="Relatienummer" type="number" :value="$company->number" hint="Leeg laten: volgende vrije nummer." />
            <x-keuze name="relation_type" label="Soort" :options="['bedrijf' => 'Bedrijf', 'particulier' => 'Particulier']" :value="$company->relation_type" required />
            <div class="field span-2"><span class="label">Rollen</span>
                <div class="actions">
                    @foreach (\App\Models\Company::ROLLEN as $field => $label)
                        <x-vink :name="$field" :label="$label" :checked="$company->{$field}" />
                    @endforeach
                </div>
            </div>
            <x-keuze name="account_manager_id" label="Accountmanager" :options="$employees" :value="$company->account_manager_id" placeholder="—" />
            <x-veld name="tags" label="Labels" :value="$company->exists ? $company->tags->pluck('name')->join(', ') : ''" hint="Gescheiden door komma's." />
        </div>
    </div></div>

    <div class="grid grid-2">
        <fieldset><legend>Contact</legend>
            <x-veld name="email" label="E-mail" type="email" :value="$company->email" />
            <x-veld name="phone" label="Telefoon" :value="$company->phone" />
            <x-veld name="mobile" label="Mobiel" :value="$company->mobile" />
            <x-veld name="website" label="Website" :value="$company->website" />
        </fieldset>
        <fieldset><legend>Registratie</legend>
            <x-veld name="coc_number" label="KvK-nummer" :value="$company->coc_number" />
            <x-veld name="vat_number" label="Btw-nummer" :value="$company->vat_number" />
            <x-vink name="vat_shifted" label="Btw verlegd" :checked="$company->vat_shifted" />
            <x-veld name="founded_on" label="Oprichtingsdatum" type="date" :value="$company->founded_on" />
        </fieldset>
        @foreach (['visit' => 'Bezoekadres', 'postal' => 'Postadres', 'invoice' => 'Factuuradres'] as $p => $legend)
            <fieldset><legend>{{ $legend }}</legend>
                @if ($p === 'invoice')
                    <x-veld name="invoice_company_name" label="Bedrijfsnaam op factuur" :value="$company->invoice_company_name" />
                    <x-veld name="invoice_attention" label="Ter attentie van" :value="$company->invoice_attention" />
                @endif
                <div class="form-grid">
                    <x-veld :name="$p.'_street'" label="Straat" :value="$company->{$p.'_street'}" />
                    <x-veld :name="$p.'_house_number'" label="Huisnummer" :value="$company->{$p.'_house_number'}" />
                    <x-veld :name="$p.'_postcode'" label="Postcode" :value="$company->{$p.'_postcode'}" />
                    <x-veld :name="$p.'_city'" label="Plaats" :value="$company->{$p.'_city'}" />
                    <x-veld :name="$p.'_country'" label="Land" :value="$company->{$p.'_country'}" />
                </div>
            </fieldset>
        @endforeach
        <fieldset><legend>Facturatie en betaling</legend>
            <x-veld name="invoice_email" label="E-mail voor facturen" type="email" :value="$company->invoice_email" />
            <x-keuze name="invoice_send_by" label="Facturen versturen per" :options="['email' => 'E-mail', 'post' => 'Post']" :value="$company->invoice_send_by" />
            <x-keuze name="payment_method" label="Betaalwijze" :options="\App\Models\Company::BETAALWIJZEN" :value="$company->payment_method" />
            <x-veld name="payment_term_days" label="Betaaltermijn klant (dagen)" type="number" :value="$company->payment_term_days" />
            <x-veld name="supplier_payment_term_days" label="Betaaltermijn leverancier (dagen)" type="number" :value="$company->supplier_payment_term_days" />
            @can('relaties.financieel')
                <x-veld name="iban" label="IBAN" :value="$company->iban" />
                <x-veld name="bic" label="BIC" :value="$company->bic" />
                <x-veld name="bank_account_holder" label="Ten name van" :value="$company->bank_account_holder" />
            @endcan
        </fieldset>
    </div>

    <div class="card"><div class="card-b">
        <x-tekst name="notes" label="Notities" :value="$company->notes" />
        <x-vink name="active" label="Actief" :checked="$company->active" />
    </div></div>

    <div class="actions">
        <button class="btn btn-primary" type="submit">Opslaan</button>
        <a class="btn" href="{{ $company->exists ? route('relaties.show', $company) : route('relaties.index') }}">Annuleren</a>
    </div>
</form>
@endsection
