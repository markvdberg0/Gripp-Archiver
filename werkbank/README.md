# Werkbank

Opensource, zelf te hosten bedrijfsapplicatie voor de processen die we nu in Gripp doen: relaties, offertes, opdrachten, uren, verkoopfacturen en inkoop. Gebouwd met Laravel 13 en PHP 8.4, met een Nederlandstalige interface.

> **Onofficieel en zelfstandig.** Werkbank is niet gemaakt door, en niet verbonden aan, Gripp B.V. Er is geen code, vormgeving of logo van Gripp gebruikt. Werkbank kan wel gegevens overnemen uit een back-up die met de [Gripp Back-up](../README.md) in deze repository is gemaakt.

**Status:** in ontwikkeling. Werkbank is (nog) niet volledig gelijkwaardig aan Gripp. Wat per fase werkt en wat ontbreekt, staat in [docs/voortgang.md](docs/voortgang.md).

## Wat zit erin

- **Relaties**: bedrijven en particulieren, contactpersonen, labels, bestanden, timeline met notities en e-mails.
- **Verkoop**: producten en pakketten, offertes met regels, statussen, sjablonen en PDF; opdrachten (ook uit een geaccepteerde offerte) met opdrachtbevestiging.
- **Planning en uren**: taken, weekplanning, urenregistratie met indienen en fiatteren.
- **Facturatie**: verkoopfacturen met definitief maken, creditfacturen, betalingen en herinneringen; factureren vanuit een opdracht (restregels of termijn); contracten met periodieke facturatie.
- **Inkoop**: inkoopopdrachten en inkoopfacturen met controle, goedkeuring (vier-ogenprincipe), on hold en betalingen; btw verlegd.
- **Overzichten**: openstaande debiteuren en crediteuren met ouderdom, omzet per maand, offertepijplijn, uren; CSV-export.
- **Beheer**: rollen en rechten, logboek, instellingen en nummering, stamgegevens, automatische back-ups met herstel, AVG-anonimiseren.
- **Import** uit de Gripp Back-up: droogloop, herhaalbaar zonder duplicaten, lokale wijzigingen worden nooit overschreven, controletotalen per onderdeel.

Wat (nog) niet kan, staat in [docs/voortgang.md](docs/voortgang.md#eindstand-en-gelijkwaardigheid).

## Snel starten

Met Docker (aanbevolen voor een server):

```bash
cp .env.example .env
docker compose run --rm --no-deps web php artisan key:generate --show   # zet de uitkomst als APP_KEY in .env
docker compose up -d
docker compose exec web php artisan werkbank:gebruiker jij@example.test   # eerste beheerder, met tijdelijk wachtwoord
```

Open daarna `http://localhost:8080`. Zonder Docker: zie [docs/installatie.md](docs/installatie.md).

## Documentatie

| Onderwerp | Waar |
|---|---|
| Analyse, aannames en fasering | [docs/analyse-en-plan.md](docs/analyse-en-plan.md) |
| Installatie (Docker en handmatig) | [docs/installatie.md](docs/installatie.md) |
| Configuratie (`.env`) | [docs/configuratie.md](docs/configuratie.md) |
| Import uit de Gripp Back-up | [docs/import.md](docs/import.md) |
| Updates | [docs/updates.md](docs/updates.md) |
| Back-ups en herstel | [docs/backup-en-herstel.md](docs/backup-en-herstel.md) |
| Rollen, rechten en privacy | [docs/beveiliging.md](docs/beveiliging.md) |
| Licenties van afhankelijkheden | [docs/licenties.md](docs/licenties.md) |
| Bankkoppeling via Ponto zelf bouwen | [docs/bankkoppeling-ponto.md](docs/bankkoppeling-ponto.md) |
| Voortgang per fase | [docs/voortgang.md](docs/voortgang.md) |

## Ontwikkelen

```bash
composer run setup      # afhankelijkheden, .env, sleutel, SQLite-database, migraties
php artisan serve       # http://127.0.0.1:8000
php artisan test        # alle tests (SQLite in het geheugen)
vendor/bin/pint         # code-opmaak
```

Er is geen JavaScript-buildstap: de CSS en het kleine script staan in `public/`.

## Licentie

MIT, zie [LICENSE](../LICENSE). Licenties van afhankelijkheden: [docs/licenties.md](docs/licenties.md).
