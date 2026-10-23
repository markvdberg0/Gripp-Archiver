# Back-ups en herstel

Werkbank maakt zelf back-ups van alle gegevens en bestanden. Dit staat los van de **Gripp Back-up** in de hoofdmap van deze repository; die blijft bestaan zolang Gripp nog in gebruik is.

## Wat zit er in een back-up

Eén zip-bestand `werkbank-backup-<datum>-<tijd>.zip` met:

| Onderdeel | Inhoud |
|---|---|
| `manifest.json` | Versie, tijdstip, databasetype, uitgevoerde migraties, aantal rijen en SHA-256-controlegetal per tabel en per bestand |
| `database/<tabel>.jsonl` | Alle rijen van elke tabel, één JSON-object per regel |
| `bestanden/…` | Alle opgeslagen bestanden (bijlagen, PDF's) |

Niet in de back-up: sessies, cache, wachtrij en wachtwoordreset-tokens. De `APP_KEY` staat **niet** in de back-up; bewaar die apart (bijvoorbeeld in een wachtwoordkluis).

De gegevens staan als JSON in de back-up, zodat een back-up ook op een ander databasetype kan worden teruggezet (bijvoorbeeld van SQLite naar MariaDB of PostgreSQL). Booleans worden daarbij omgezet en tabellen worden in de volgorde van hun verwijzingen gevuld. Getest is herstel binnen hetzelfde databasetype (SQLite lokaal, MariaDB en PostgreSQL in CI); de overstap tussen typen is ontworpen maar niet als geheel getest. Maak bij zo'n overstap eerst een proefherstel.

## Automatisch

Elke nacht om 02:30 maakt de scheduler een back-up (Docker: de `scheduler`-container; zonder Docker: de cronregel uit [installatie.md](installatie.md)). De nieuwste `WERKBANK_BACKUP_KEEP` (standaard 14) blijven bewaard; back-ups met "voor-herstel" in de naam worden nooit automatisch verwijderd.

Back-ups komen in `WERKBANK_BACKUP_PATH` (Docker: `./backups` naast `docker-compose.yml`). **Kopieer die map regelmatig naar een andere plek** (NAS, versleutelde externe schijf of cloudopslag). Een back-up op dezelfde server beschermt niet tegen het kwijtraken van die server.

## Met de hand

```bash
php artisan werkbank:backup                       # Docker: docker compose exec web php artisan werkbank:backup
php artisan werkbank:backup --label=voor-update
```

Of in de applicatie: **Beheer › Back-ups › Nu een back-up maken**. Daar kun je back-ups ook downloaden (alleen beheerders; elke download staat in het logboek).

Na het maken wordt de back-up meteen gecontroleerd (alle controlegetallen).

## Controleren zonder terug te zetten

```bash
php artisan werkbank:herstel werkbank-backup-20261023-023000.zip --controleer
```

## Terugzetten

> Terugzetten **vervangt alle huidige gegevens** door die uit de back-up.

```bash
php artisan werkbank:herstel werkbank-backup-20261023-023000.zip
```

Zo werkt het:

1. De back-up wordt eerst volledig gecontroleerd (controlegetallen, en of hij niet van een nieuwere Werkbank-versie komt). Klopt er iets niet, dan stopt het herstel en verandert er niets.
2. Er wordt automatisch een back-up van de huidige toestand gemaakt (`…-voor-herstel.zip`), zodat ook een herstel terug te draaien is. Overslaan kan met `--zonder-veiligheidsbackup`.
3. De database wordt leeggemaakt en opgebouwd met precies de migraties van de back-up; daarna worden alle rijen teruggezet.
4. Is de back-up van een oudere versie, dan worden de nieuwere migraties daarna alsnog uitgevoerd.
5. De bestanden worden teruggezet.

In Docker: zet het zip-bestand in `./backups` en voer uit:

```bash
docker compose exec web php artisan werkbank:herstel werkbank-backup-20261023-023000.zip
```

Terugzetten kan bewust niet vanuit de webinterface.

## Overstappen naar een andere server

1. Op de oude server: `php artisan werkbank:backup`.
2. Installeer Werkbank op de nieuwe server (zelfde of nieuwere versie), met **dezelfde `APP_KEY`**.
3. Kopieer het zip-bestand naar de back-upmap van de nieuwe server en voer `php artisan werkbank:herstel <bestand>` uit.

## Getest

- Geautomatiseerd: back-up maken, gegevens en een bestand wijzigen/verwijderen, terugzetten, alles weer zoals in de back-up, inclusief veiligheidsback-up; een gewijzigde back-up wordt geweigerd; controleren wijzigt niets; opruimen bewaart het ingestelde aantal.
- Met de echte geïmporteerde gegevens (15.799 rijen, 1.916 bestanden, 370 MB): back-up in 12 seconden, controle in 2 seconden; terugzetten in een lege database in 8 seconden, met daarna dezelfde aantallen en totalen als het origineel.
