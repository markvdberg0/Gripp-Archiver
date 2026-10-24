<?php

namespace App\Models;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Instellingen in de database (bedrijfsgegevens, nummering, standaardteksten). */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public const DEFAULTS = [
        'bedrijf.naam' => '', 'bedrijf.adres' => '', 'bedrijf.postcode' => '', 'bedrijf.plaats' => '', 'bedrijf.land' => 'Nederland',
        'bedrijf.telefoon' => '', 'bedrijf.email' => '', 'bedrijf.website' => '', 'bedrijf.kvk' => '', 'bedrijf.btw' => '',
        'bedrijf.iban' => '', 'bedrijf.bic' => '',
        'offerte.geldigheid_dagen' => '30', 'offerte.voettekst' => '', 'factuur.betaaltermijn' => '30', 'factuur.voettekst' => '',
        'nummer.offerte' => '', 'nummer.opdracht' => '', 'nummer.factuur' => '', 'nummer.inkoopopdracht' => '', 'nummer.boeknummer' => '',
        'inkoop.vier_ogen_drempel' => '2500',
        'beveiliging.tweestaps_verplicht' => '0',
        'verlof.uren_per_jaar' => '160', // wettelijk minimum bij 40 uur per week (4 × de wekelijkse arbeidsduur)
        // E-mailteksten. Plaatshouders: {nummer} {omschrijving} {relatie} {contactpersoon} {bedrag} {openstaand} {vervaldatum} {bedrijf} {afzender}
        'mail.offerte.onderwerp' => 'Offerte {nummer}: {omschrijving}',
        'mail.offerte.tekst' => "Beste {contactpersoon},\n\nHierbij ontvangt u onze offerte {nummer} voor {omschrijving}. U vindt de offerte als PDF in de bijlage.\n\nHeeft u vragen, neem dan gerust contact met ons op.\n\nMet vriendelijke groet,\n\n{afzender}\n{bedrijf}",
        'mail.factuur.onderwerp' => 'Factuur {nummer}: {omschrijving}',
        'mail.factuur.tekst' => "Beste {contactpersoon},\n\nIn de bijlage vindt u factuur {nummer} van {bedrag} (incl. btw). Wij verzoeken u het bedrag vóór {vervaldatum} over te maken onder vermelding van het factuurnummer.\n\nMet vriendelijke groet,\n\n{afzender}\n{bedrijf}",
        'mail.herinnering.onderwerp' => 'Herinnering factuur {nummer}',
        'mail.herinnering.tekst' => "Beste {contactpersoon},\n\nVolgens onze administratie staat van factuur {nummer} nog {openstaand} open. De vervaldatum was {vervaldatum}. Wij verzoeken u het bedrag per omgaande over te maken. U vindt de herinnering als PDF in de bijlage.\n\nIs de betaling inmiddels onderweg, dan kunt u dit bericht als niet verzonden beschouwen.\n\nMet vriendelijke groet,\n\n{afzender}\n{bedrijf}",
        'mail.opdracht.onderwerp' => 'Opdrachtbevestiging {nummer}: {omschrijving}',
        'mail.opdracht.tekst' => "Beste {contactpersoon},\n\nHartelijk dank voor uw opdracht. In de bijlage vindt u de opdrachtbevestiging {nummer}.\n\nMet vriendelijke groet,\n\n{afzender}\n{bedrijf}",
        'mail.inkoopopdracht.onderwerp' => 'Inkoopopdracht {nummer}: {omschrijving}',
        'mail.inkoopopdracht.tekst' => "Beste {contactpersoon},\n\nIn de bijlage vindt u onze inkoopopdracht {nummer}. Wilt u de opdracht en de leverdatum bevestigen en ons inkoopnummer vermelden op pakbon en factuur?\n\nMet vriendelijke groet,\n\n{afzender}\n{bedrijf}",
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever('werkbank.settings', fn () => static::query()->pluck('value', 'key')->all());

        return ($all[$key] ?? null) !== null && $all[$key] !== '' ? $all[$key] : ($default ?? (self::DEFAULTS[$key] ?? null));
    }

    public static function put(array $values): void
    {
        $changes = [];
        foreach ($values as $key => $value) {
            $old = static::query()->find($key)?->value;
            if ((string) $old !== (string) $value) {
                $changes[$key] = [$old, $value];
                static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }
        Cache::forget('werkbank.settings');
        if ($changes) {
            Audit::log('instellingen_gewijzigd', null, 'Instellingen', $changes);
        }
    }

    public static function flush(): void
    {
        Cache::forget('werkbank.settings');
    }
}
