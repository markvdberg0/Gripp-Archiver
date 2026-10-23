<?php

namespace App\Support;

/**
 * Rollen en rechten. Bewust in code (en niet in de database): de matrix is klein, staat in versiebeheer
 * en is daardoor controleerbaar. Elk recht wordt in AppServiceProvider als Gate geregistreerd.
 */
final class Rechten
{
    public const ROLLEN = [
        'beheerder' => 'Beheerder',
        'administratie' => 'Administratie',
        'projectleider' => 'Projectleider',
        'medewerker' => 'Medewerker',
        'alleen_lezen' => 'Alleen lezen',
    ];

    /** Alle rechten met omschrijving. */
    public const RECHTEN = [
        'relaties.bekijken' => 'Relaties en contactpersonen bekijken',
        'relaties.bewerken' => 'Relaties en contactpersonen aanmaken en wijzigen',
        'relaties.financieel' => 'Bankgegevens van relaties zien en wijzigen',
        'producten.bekijken' => 'Producten bekijken',
        'producten.bewerken' => 'Producten en pakketten beheren',
        'offertes.bekijken' => 'Offertes bekijken',
        'offertes.bewerken' => 'Offertes maken en wijzigen',
        'opdrachten.bekijken' => 'Opdrachten bekijken',
        'opdrachten.bewerken' => 'Opdrachten maken en wijzigen',
        'taken.bewerken' => 'Taken maken en afronden',
        'uren.schrijven' => 'Eigen uren schrijven',
        'uren.fiatteren' => 'Uren van anderen bekijken en fiatteren',
        'facturen.bekijken' => 'Verkoopfacturen bekijken',
        'facturen.bewerken' => 'Verkoopfacturen maken, verzenden en betalingen registreren',
        'inkoop.bekijken' => 'Inkoopopdrachten en inkoopfacturen bekijken',
        'inkoop.bewerken' => 'Inkoopopdrachten en inkoopfacturen invoeren en wijzigen',
        'inkoop.goedkeuren' => 'Inkoopfacturen goedkeuren',
        'rapporten.bekijken' => 'Overzichten en rapporten bekijken',
        'beheer.gebruikers' => 'Gebruikers en rollen beheren',
        'beheer.import' => 'Gegevens importeren uit een back-up',
        'beheer.instellingen' => 'Instellingen, BTW-tarieven en stamgegevens beheren',
        'beheer.auditlog' => 'Logboek bekijken',
    ];

    private const MATRIX = [
        'beheerder' => ['*'],
        'administratie' => [
            'relaties.*', 'producten.*', 'offertes.*', 'opdrachten.*', 'taken.bewerken', 'uren.schrijven', 'uren.fiatteren',
            'facturen.*', 'inkoop.*', 'rapporten.bekijken', 'beheer.instellingen', 'beheer.auditlog',
        ],
        'projectleider' => [
            'relaties.bekijken', 'relaties.bewerken', 'producten.bekijken', 'offertes.*', 'opdrachten.*', 'taken.bewerken',
            'uren.schrijven', 'uren.fiatteren', 'facturen.bekijken', 'inkoop.bekijken', 'inkoop.bewerken', 'rapporten.bekijken',
        ],
        'medewerker' => [
            'relaties.bekijken', 'producten.bekijken', 'offertes.bekijken', 'opdrachten.bekijken', 'taken.bewerken', 'uren.schrijven',
        ],
        'alleen_lezen' => [
            'relaties.bekijken', 'producten.bekijken', 'offertes.bekijken', 'opdrachten.bekijken', 'facturen.bekijken',
            'inkoop.bekijken', 'rapporten.bekijken',
        ],
    ];

    public static function heeft(?string $rol, string $recht): bool
    {
        foreach (self::MATRIX[$rol] ?? [] as $patroon) {
            if ($patroon === '*' || $patroon === $recht || (str_ends_with($patroon, '.*') && str_starts_with($recht, substr($patroon, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function vanRol(string $rol): array
    {
        return array_values(array_filter(array_keys(self::RECHTEN), fn ($r) => self::heeft($rol, $r)));
    }
}
