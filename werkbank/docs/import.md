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
- **Lokaal verwijderd blijft verwijderd.** Ook definitief verwijderde regels, betalingen en uren worden vastgelegd en niet opnieuw aangemaakt.
- **Regels en betalingen volgen hun document.** Is een offerte, opdracht, factuur of inkoopdocument in Werkbank gewijzigd, dan worden ook de regels en betalingen eronder niet meer vanuit Gripp bijgewerkt (conflict in het rapport), zodat regels en totalen bij elkaar blijven passen.
- **Samengevoegde relaties.** Is een relatie in Werkbank samengevoegd met een andere (Relaties › Dubbele relaties), dan wordt de opgeheven relatie niet meer bijgewerkt of hersteld (*overgeslagen* in het rapport). Alles wat in Gripp nog naar die relatie verwijst, komt bij de relatie terecht die is blijven bestaan.
- **Niets wordt verwijderd.** Staat een record niet meer in de back-up, dan blijft het in Werkbank bestaan; het rapport noemt het aantal.
- **Alles of niets.** De import is één databasetransactie. Mislukt er onderweg iets ernstigs, dan verandert er niets. Fouten in losse records worden gemeld en overgeslagen.
- **Bijlagen** worden gekopieerd naar de eigen opslag en ontdubbeld op inhoud (SHA-256). Ontbreekt een bestand in het archief, dan blijft het record bestaan "zonder inhoud".
- **Afgeleide koppelingen** (bijvoorbeeld de opdracht van een factuur, uit de regels) worden ingevuld zonder dat het record als "lokaal gewijzigd" telt.
- Verwijzingen naar records die niet bestaan worden leeg gelaten en gemeld.

## Onderdelen

In deze volgorde (de naam is wat je bij `--alleen` gebruikt):

| Onderdeel | Gripp-bron | Werkbank |
|---|---|---|
| `tag`, `employee`, `file` | tag, employee, file | labels, medewerkers (zonder gevoelige gegevens), bestanden |
| `company`, `contact` | company, contact | relaties, contactpersonen |
| `unit`, `ledger`, `costheading` | unit, ledger, costheading | eenheden, grootboekrekeningen, kostenplaatsen |
| `product`, `packet`, `packetline` | product, packet, packetline | producten, pakketten en regels |
| `offerphase`, `offer`, `offerline` | offerphase, offer, offerprojectline (bij offertes) | offertefasen, offertes (incl. sjablonen) en regels |
| `projectphase`, `project`, `projectline` | projectphase, project, offerprojectline (bij opdrachten) | opdrachtfasen, opdrachten en regels |
| `tasktype`, `taskphase`, `task`, `calendaritem`, `hour` | idem | taaksoorten, taakfasen, taken, agenda-items, uren |
| `contract`, `contractline` | idem | contracten en regels |
| `invoice`, `invoiceline`, `payment` | idem | verkoopfacturen, regels, betalingen |
| `purchaseorder`, `purchaseorderline` | idem | inkoopopdrachten en regels |
| `purchaseinvoice`, `purchaseinvoiceline`, `purchasepayment` | idem | inkoopfacturen, regels, betalingen |
| `memorial`, `memorialline`, `cost` | idem | memoriaal en kosten (alleen-lezen archief) |
| `timelineentry` | timelineentry | timeline, gekoppeld aan relatie, contactpersoon en document |

Btw-tarieven staan niet als tabel in Gripp; ze worden aangemaakt uit de verwijzingen op regels en producten, met het percentage uit de naam. Tarieven met "binnen EU", "buiten EU" of "verlegd" in de naam krijgen "btw verlegd".

Verlof (absencerequest en absencerequestline) wordt overgenomen, behalve ziekmeldingen (gezondheidsgegevens). Een aanvraag krijgt de status van haar dagen; zonder dagen is de status "onbekend". De veldnamen van de dagen (`date`, `amount`) zijn volgens de Gripp-API aangenomen: de echte back-up bevatte geen dagen om ze aan te toetsen.

Nog niet overgenomen: de onderdelen waarvoor de API-sleutel geen leesrechten had (afdelingen, jaardoelen, dossiers, omzetdoelen, staffelprijzen, overkoepelende projecten).

## Wat bewust niet wordt overgenomen

- Van medewerkers: gebruikersnaam, wachtwoord(-hash), BSN, bankgegevens, privéadres en gezinsleden. Inloggen gaat via eigen Werkbank-gebruikers.
- Gegevens die de Gripp-API niet levert (en dus ook niet in de back-up staan): bankmutaties, journaalposten, btw-aangifte, wijzigingsgeschiedenis, sjabloonopmaak en instellingen.

## Rapport

Elke run krijgt een rapport (Beheer › Import › run) met per onderdeel: aantal in de bron, nieuw, gewijzigd, ongewijzigd, conflicten, overgeslagen, fouten en "niet meer in bron", plus controletotalen (aantallen en, vanaf fase 4, financiële totalen) en de eerste 200 meldingen per onderdeel. De run zelf wordt vastgelegd in het logboek.

## Controles

Na elke import vergelijkt Werkbank de bron met het resultaat:

- aantal records per onderdeel;
- som van de totalen van offertes, opdrachten, verkoopfacturen (ook openstaand), inkoopopdrachten, inkoopfacturen (ook openstaand) en kosten, en het totaal aantal uren;
- per document of de regels optellen tot het totaal in Gripp (afwijkingen worden per document gemeld);
- per factuur en inkoopfactuur of totaal − betalingen gelijk is aan het openstaande bedrag.

## Getest met

De echte back-up van 15-10-2026: 12.600 records en 2.502 bijlagen (plus 187 offerte-, 34 opdracht-, 156 inkoopopdracht- en 71 factuur-PDF's). Volledige eerste import in een lege database met lege bestandsopslag: 15 seconden, 2.950 bestanden opgeslagen (443 MB na ontdubbelen), 5 bijlagen zonder inhoud in de back-up. Herhaalde import: 1,5 seconde met 0 nieuwe en 0 gewijzigde records. (Gemeten op een Windows-laptop met SQLite.) Alle controles kloppen, op twee afrondingsverschillen van 2 en 3 cent na die in Gripp zelf zitten (één inkoopopdracht en één inkoopfactuur); die staan als melding in het rapport.

## Overzichten na de import

- *Openstaande debiteuren* telt alle definitieve verkoopfacturen en sluit aan op het openstaande totaal van de back-up.
- *Openstaande crediteuren* telt geen conceptinkoopfacturen (nog niet geboekt). Het totaal is daardoor lager dan "openstaand" in het importrapport, dat alle inkoopfacturen telt; het verschil is het openstaande bedrag van de concepten.
