# Import uit de Gripp Back-up

Werkbank neemt gegevens over uit een back-upmap die is gemaakt met de Gripp Back-up in deze repository. Werkbank praat zelf **niet** met de Gripp-API: de back-uptool haalt de gegevens op (alleen-lezen), Werkbank leest de back-up.

## Waarvandaan

De back-upmap bevat `data/current/` (de laatste gepubliceerde snapshot) en `archive/` (bijlagen). Werkbank leest:

| Bestand | Gebruik |
|---|---|
| `data/current/raw/<onderdeel>.json` | De records per onderdeel |
| `data/current/state.json` | Welke bijlagen en factuur-PDF's er zijn |
| `data/current/documents.json` | PDF's van offertes, opdrachten en inkoopopdrachten |
| `archive/index.json` en `archive/…` | De bestanden zelf |

De back-upmap wordt nooit gewijzigd. In Docker is hij alleen-lezen gekoppeld.

## Zo voer je een import uit

**In de applicatie:** Beheer › Import. Kies eerst **Droogloop**: die laat zien wat er nieuw, gewijzigd of in conflict zou zijn, zonder iets op te slaan. Daarna **Importeren**. De import draait op de achtergrond (queue-worker); het rapport ververst als je de pagina opnieuw laadt.

**Op de opdrachtregel:**

```bash
php artisan werkbank:import /pad/naar/back-upmap --droogloop
php artisan werkbank:import /pad/naar/back-upmap
php artisan werkbank:import --alleen=company,contact          # alleen deze onderdelen
php artisan werkbank:import --snapshot=previous               # de vorige snapshot (of history/<naam>)
```

Zonder pad wordt `WERKBANK_IMPORT_PATH` gebruikt.

## Regels

- **Geen duplicaten.** Elk record houdt zijn Gripp-id (`gripp_id`). Een tweede import vindt hetzelfde record terug.
- **Ongewijzigd wordt overgeslagen.** Werkbank bewaart een hash van het bronrecord; is die gelijk, dan gebeurt er niets.
- **Lokale wijzigingen gaan voor.** Heb je een record in Werkbank gewijzigd of verwijderd na de vorige import, dan neemt de import een nieuwe versie uit Gripp niet over. Het rapport meldt dit als *conflict*.
- **Niets wordt verwijderd.** Staat een record niet meer in de back-up, dan blijft het in Werkbank bestaan; het rapport noemt het aantal.
- **Alles of niets.** De import is één databasetransactie. Mislukt er onderweg iets ernstigs, dan verandert er niets. Fouten in losse records worden gemeld en overgeslagen.
- **Bijlagen** worden gekopieerd naar de eigen opslag en ontdubbeld op inhoud (SHA-256). Ontbreekt een bestand in het archief, dan blijft het record bestaan "zonder inhoud".
- **Volgorde:** labels, medewerkers, bestanden, relaties, contactpersonen, timeline, en in latere fasen producten, offertes, opdrachten, facturen en inkoop. Verwijzingen naar records die (nog) niet bestaan worden leeg gelaten en gemeld.

## Wat bewust niet wordt overgenomen

- Van medewerkers: gebruikersnaam, wachtwoord(-hash), BSN, bankgegevens, privéadres en gezinsleden. Inloggen gaat via eigen Werkbank-gebruikers.
- Gegevens die de Gripp-API niet levert (en dus ook niet in de back-up staan): bankmutaties, journaalposten, btw-aangifte, wijzigingsgeschiedenis, sjabloonopmaak en instellingen.

## Rapport

Elke run krijgt een rapport (Beheer › Import › run) met per onderdeel: aantal in de bron, nieuw, gewijzigd, ongewijzigd, conflicten, overgeslagen, fouten en "niet meer in bron", plus controletotalen (aantallen en, vanaf fase 4, financiële totalen) en de eerste 200 meldingen per onderdeel. De run zelf wordt vastgelegd in het logboek.

## Getest met

De echte back-up van 15-10-2026 (fase 1: 5.065 records en 2.502 bijlagen): eerste import 23 seconden, tweede import 0,4 seconden met 0 nieuwe en 0 gewijzigde records.
