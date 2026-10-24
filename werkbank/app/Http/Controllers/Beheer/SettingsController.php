<?php

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private const RULES = [
        'bedrijf.naam' => ['nullable', 'string', 'max:255'],
        'bedrijf.adres' => ['nullable', 'string', 'max:255'],
        'bedrijf.postcode' => ['nullable', 'string', 'max:15'],
        'bedrijf.plaats' => ['nullable', 'string', 'max:100'],
        'bedrijf.land' => ['nullable', 'string', 'max:60'],
        'bedrijf.telefoon' => ['nullable', 'string', 'max:40'],
        'bedrijf.email' => ['nullable', 'email', 'max:255'],
        'bedrijf.website' => ['nullable', 'string', 'max:255'],
        'bedrijf.kvk' => ['nullable', 'string', 'max:20'],
        'bedrijf.btw' => ['nullable', 'string', 'max:30'],
        'bedrijf.iban' => ['nullable', 'string', 'max:40'],
        'bedrijf.bic' => ['nullable', 'string', 'max:15'],
        'offerte.geldigheid_dagen' => ['required', 'integer', 'between:1,365'],
        'offerte.voettekst' => ['nullable', 'string', 'max:5000'],
        'factuur.betaaltermijn' => ['required', 'integer', 'between:0,365'],
        'factuur.voettekst' => ['nullable', 'string', 'max:5000'],
        'nummer.offerte' => ['nullable', 'integer', 'min:1'],
        'nummer.opdracht' => ['nullable', 'integer', 'min:1'],
        'nummer.factuur' => ['nullable', 'integer', 'min:1'],
        'nummer.inkoopopdracht' => ['nullable', 'integer', 'min:1'],
        'nummer.boeknummer' => ['nullable', 'integer', 'min:1'],
        'inkoop.vier_ogen_drempel' => ['required', 'numeric', 'min:0'],
        'beveiliging.tweestaps_verplicht' => ['boolean'],
        'verlof.uren_per_jaar' => ['nullable', 'numeric', 'between:0,2000'],
        'mail.offerte.onderwerp' => ['nullable', 'string', 'max:255'],
        'mail.offerte.tekst' => ['nullable', 'string', 'max:5000'],
        'mail.factuur.onderwerp' => ['nullable', 'string', 'max:255'],
        'mail.factuur.tekst' => ['nullable', 'string', 'max:5000'],
        'mail.herinnering.onderwerp' => ['nullable', 'string', 'max:255'],
        'mail.herinnering.tekst' => ['nullable', 'string', 'max:5000'],
        'mail.opdracht.onderwerp' => ['nullable', 'string', 'max:255'],
        'mail.opdracht.tekst' => ['nullable', 'string', 'max:5000'],
        'mail.inkoopopdracht.onderwerp' => ['nullable', 'string', 'max:255'],
        'mail.inkoopopdracht.tekst' => ['nullable', 'string', 'max:5000'],
    ];

    public function edit(): View
    {
        $values = collect(array_keys(self::RULES))->mapWithKeys(fn ($k) => [str_replace('.', '__', $k) => Setting::get($k)])->all();

        return view('beheer.settings.edit', compact('values'));
    }

    public function update(Request $request): RedirectResponse
    {
        // Punten in veldnamen vervangen door __ (Laravel leest punten als geneste arrays).
        $rules = collect(self::RULES)->mapWithKeys(fn ($r, $k) => [str_replace('.', '__', $k) => $r])->all();
        // Beveiligingsinstellingen en de vier-ogendrempel (die controleert juist wie inkoopfacturen goedkeurt,
        // de administratie) mag alleen een beheerder wijzigen.
        if (! $request->user()->can('beheer.gebruikers')) {
            unset($rules['inkoop__vier_ogen_drempel'], $rules['beveiliging__tweestaps_verplicht']);
        }
        $data = $request->validate($rules);
        Setting::put(collect($data)->only(array_keys($rules))->mapWithKeys(fn ($v, $k) => [str_replace('__', '.', $k) => $v ?? ''])->all());

        return redirect()->route('beheer.instellingen.edit')->with('status', 'Instellingen opgeslagen.');
    }
}
