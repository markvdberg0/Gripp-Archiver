# Voortgang per fase

Per fase: wat werkt aantoonbaar, hoe is het getest, welke beperkingen zijn er en wat is de volgende stap. Werkbank claimt geen volledige gelijkwaardigheid met Gripp.

## Fase 1 — Fundament en relaties

**Wat werkt**

- Laravel 13 / PHP 8.4, SQLite standaard, migraties database-onafhankelijk (SQLite, MariaDB, PostgreSQL in CI).
- Inloggen, uitloggen, verplicht eigen wachtwoord na tijdelijk wachtwoord, blokkade na te veel pogingen, deactiveren van gebruikers.
- Vijf rollen met een vaste rechtenmatrix; menu, knoppen en routes volgen de rechten.
- Logboek van wijzigingen (oud → nieuw), inlogpogingen, uploads en imports, met maskering van gevoelige velden.
- Gebruikers- en medewerkersbeheer (koppeling gebruiker ↔ medewerker).
- Relaties: lijst met zoeken, filteren op rol en status, sorteren; aanmaken, wijzigen, (zacht) verwijderen; adressen, facturatie, bankgegevens (alleen met recht), labels, accountmanager.
- Contactpersonen, timeline met notities (en geïmporteerde e-mails, veilig weergegeven), bestanden uploaden en downloaden met rechtencontrole.
- Globaal zoeken.
- Import uit de Gripp Back-up voor labels, medewerkers, bestanden, relaties, contactpersonen en timeline: droogloop, idempotent, conflictdetectie, rapport met controletotalen, via scherm (queue) en opdrachtregel.
- Docker-image en Docker Compose (web, queue, scheduler, optioneel MariaDB).

**Hoe getest**

- 44 geautomatiseerde tests (304 controles): inloggen en rechten, relatiebeheer, validatie, logboek, bestanden, import (droogloop, herhaalde import zonder duplicaten, bronwijziging, lokale wijziging = conflict, lokaal verwijderd, verdwenen bronrecords, mislukte import draait alles terug, ontdubbelen van bijlagen, geen gevoelige medewerkergegevens).
- Handmatig in de browser: inloggen met tijdelijk wachtwoord, wachtwoord wijzigen, relatielijst en relatiedetail met echte data.
- Echte back-up (15-10-2026): droogloop 4,2 s; import 23 s met 5.065 records en 2.502 bijlagen (452 MB in de back-up, 371 MB na ontdubbelen), 0 fouten; tweede import 0,4 s met 0 nieuw en 0 gewijzigd.

**Beperkingen**

- Docker-image en de CI-jobs voor MariaDB/PostgreSQL zijn geschreven maar lokaal niet uitgevoerd (hier is geen Docker beschikbaar); ze draaien in GitHub Actions bij een push.
- Geen tweestapsverificatie en geen wachtwoordherstel per e-mail.
- Een AVG-verwijderfunctie (definitief wissen) ontbreekt nog.
- Koppelingen van timeline-items aan offertes, opdrachten en facturen komen in de fasen waarin die documenten bestaan.

**Volgende stap:** fase 2, producten en offertes.

## Fase 2 — Producten en offertes

**Wat werkt**

- Instellingen (bedrijfsgegevens voor documenten, standaardgeldigheid en -betaaltermijn, slotteksten, nummering) en stamgegevens (btw-tarieven, eenheden, grootboekrekeningen, kostenplaatsen, offertefasen).
- Producten (verkoop- en inkoopprijs, marge, eenheid, btw, grootboek, kostenplaats, leverancier, bestelcode, vrije velden uit Gripp, bijlagen, labels) en pakketten met regels.
- Offertes: lijst met zoeken, filters (status, fase, accountmanager, archief, sjablonen) en totaal/gewogen waarde; aanmaken en wijzigen met een regels-editor (groepskoppen, tekstregels, producten, korting, btw per regel, live berekening, volgorde); pakket toevoegen; statussen concept → verzonden → geaccepteerd/afgewezen, heropenen, archiveren (met systeemregel in de timeline); kopiëren; sjablonen (zonder nummer en relatie) als basis voor nieuwe offertes; PDF in eigen opmaak; verwijderen alleen als concept; vergrendeld na acceptatie.
- Doorlopende nummering die aansluit op geïmporteerde nummers of een ingestelde startwaarde.
- Import: eenheden, grootboek, kostenplaatsen, producten, pakketten, offertefasen, offertes (incl. sjablonen, vrije velden, het Gripp-document als PDF) en offerteregels; timeline gekoppeld aan offertes; btw-tarieven afgeleid uit de verwijzingen.
- Berekening volgens de regel die in de Gripp-data is aangetoond: regeltotaal = aantal × prijs × (1 − korting), groepskoppen tellen niet mee; btw per tarief.

**Hoe getest**

- 64 tests (463 controles), nieuw: offerte maken met totalen en btw per tarief, Nederlandse getalnotatie, validatie (o.a. contactpersoon moet bij relatie horen), nummering, regels wijzigen/verwijderen/volgorde, statusovergangen en vergrendeling, kopie, sjablonen, pakket, PDF, rechten, filters, producten, instellingen, stamgegevens; import van alle nieuwe onderdelen inclusief controle dat opdrachtregels niet als offerteregels worden geïmporteerd.
- Browser: offertelijst, offertedetail met 42 regels, nieuwe offerte met live berekening (gecontroleerd: 2 × 1.234,50 + 3 × 100 − 10% = € 2.739,00, btw € 575,19).
- Echte back-up: 187 offertes en 3.730 offerteregels geïmporteerd; som van de totalen gelijk aan de bron; bij **alle** 187 offertes tellen de regels exact op tot het Gripp-totaal; 187 Gripp-offerte-PDF's gekoppeld. Vooraf gecontroleerd dat dezelfde rekenregel ook voor alle 34 opdrachten en 71 facturen klopt.

**Beperkingen**

- De PDF heeft een eenvoudige eigen opmaak, niet de Gripp-sjablonen (aanname 2 in de analyse). Visueel alleen in de code beoordeeld: hier is geen PDF-renderer beschikbaar; de test controleert dat er een geldige PDF komt.
- Offertes versturen per e-mail en digitaal akkoord door de klant ontbreken (aanname 3 en 4).
- Contactpersonenlijst in het formulier wordt pas na opslaan gefilterd op de gekozen relatie.
- Voorraadbeheer: de Gripp-velden (voorraad, drempelwaarde) zijn zichtbaar als vrije velden, maar er is geen voorraadlogica.

**Volgende stap:** fase 3, opdrachten, taken en uren.

## Fase 3 — Opdrachten, taken, planning en uren

**Wat werkt**

- Opdrachten: lijst met filters, fase, deadline-signalering en geschreven uren; aanmaken en wijzigen met regels (begroting) en medewerkers; **opdracht maken uit een geaccepteerde offerte** (gegevens, regels en bijlagen gaan mee, één opdracht per offerte, offerte daarna niet meer te heropenen); opdrachtbevestiging als PDF; archiveren; verwijderen alleen zonder uren.
- Opdrachtdetail met uren per medewerker (geschreven/gefiatteerd) en per regel (geschreven tegenover begrote uren), taken, bestanden en timeline.
- Taken: mijn taken / iedereen, open/afgerond, te-laat-signalering, afronden en heropenen, inplannen als agenda-item.
- Planning: weekoverzicht van agenda-items per medewerker per dag; los inplannen.
- Uren: weekstaat per medewerker, uren toevoegen op opdracht en (optioneel) regel, week indienen (concept → definitief), fiatteren of terugzetten door iemand met het recht "uren fiatteren"; gefiatteerde en ingediende uren zijn niet meer te wijzigen; je kunt alleen eigen uren wijzigen (tenzij je mag fiatteren).
- Import: opdrachtfasen, opdrachten (medewerkers, vrije velden, Gripp-PDF), opdrachtregels (gescheiden van offerteregels), taaksoorten en -fasen, taken, agenda-items en uren (met status en fiattering); timeline gekoppeld aan opdrachten en taken.

**Hoe getest**

- 75 tests (555 controles), nieuw: opdracht uit offerte (eenmalig, vergrendeling offerte), handmatige opdracht met validatie, verwijderen vs archiveren, volledige urenflow (schrijven met komma-notatie, validatie, indienen, fiatteren, rechten op andermans uren), taken en planning, rechten voor alleen lezen; import van alle nieuwe onderdelen en herhaalde import.
- Browser: opdrachtenlijst, opdrachtdetail, planning met echte agenda-items, takenlijst, fiatteerscherm.
- Echte back-up: 34 opdrachten (som van de totalen gelijk aan de bron, regels van alle 34 tellen op tot het totaal), 616 opdrachtregels, 6 taken, 157 agenda-items, 12 urenregels (33,00 uur gelijk aan de bron).

**Beperkingen**

- Geen agenda-koppeling (iCal/Outlook) en geen sleep-en-zet-planning; de planning is een overzicht met invoer.
- Geen kostprijs/uurtarief per medewerker, dus nog geen nacalculatie in euro's (alleen uren).
- Verlof (Gripp: absencerequest, 3 records) is nog niet overgenomen.

**Volgende stap:** fase 4, verkoopfacturen.

## Fase 4 — Verkoopfacturen, betalingen en contracten

**Wat werkt**

- Facturen: lijst met filters (concept, openstaand, vervallen, betaald, credit; relatie; jaar) en totalen (excl., incl., openstaand); concept maken en wijzigen met regels inclusief grootboek en kostenplaats per regel.
- **Definitief maken**: doorlopend factuurnummer (sluit aan op geïmporteerde nummers of de ingestelde startwaarde), factuurdatum, vervaldatum uit de betaaltermijn van de relatie of de standaard; daarna niet meer te wijzigen of te verwijderen. Corrigeren gaat met een **creditfactuur** (concept met negatieve regels, gekoppeld aan het origineel).
- **Betalingen** registreren (ook gedeeltelijk en negatief) en verwijderen; openstaand = totaal incl. btw − betalingen; status openstaand, vervallen, betaald.
- **Herinneringen**: herinnering, tweede herinnering, aanmaning vastleggen bij vervallen facturen; de PDF toont dan de herinnering met openstaand bedrag.
- **Factuur uit een opdracht**: voorstel met de nog niet gefactureerde regels (per opdrachtregel bijgehouden), of een **termijnfactuur** met een percentage van het opdrachttotaal; het scherm toont hoeveel van de opdracht al is gefactureerd.
- **Contracten** met regels per periode en frequentie (week tot jaar); elke nacht (en met een knop) worden conceptfacturen gemaakt voor contracten die aan de beurt zijn, met inhalen van gemiste periodes en stoppen op de einddatum.
- Factuur-PDF in eigen opmaak, met factuuradres, btw-specificatie, btw-verlegd-vermelding en slottekst.
- Import: contracten en contractregels, facturen (status, herinneringsfase, betaald/openstaand, Gripp-PDF), factuurregels (grootboek, kostenplaats, opdracht en opdrachtregel) en betalingen; de hoofdopdracht van een factuur wordt afgeleid uit de regels.

**Hoe getest**

- 86 tests (656 controles), nieuw: concept zonder nummer, definitief maken met nummering en vervaldatum, vergrendeling, betalingen tot betaald, herinneringen en PDF, creditfactuur, factuur uit opdracht (restregels en termijn), contractfacturatie inclusief inhalen en einddatum, rechten; import van facturen, regels, betalingen, creditnota, concept, PDF en contracten, en een tweede import zonder wijzigingen of conflicten.
- Browser: facturenlijst met filter "vervallen", termijnvoorstel uit een echte opdracht (30% van het opdrachttotaal, met de melding welk deel al is gefactureerd).
- Echte back-up: 71 facturen, 349 regels, 62 betalingen, 2 contracten. Som incl. btw en som openstaand gelijk aan de bron (en aan het rapport van de back-uptool); bij alle 71 facturen tellen de regels op tot het totaal en sluit totaal − betalingen aan op openstaand; 71 Gripp-factuur-PDF's gekoppeld.

**Beperkingen**

- Facturen en herinneringen worden niet per e-mail verstuurd; je downloadt de PDF (aanname 3).
- Geen UBL/e-factuur-export, geen SEPA-incassobestand en geen koppeling met bank of boekhouding.
- Bij geïmporteerde opdrachten die in termijnen zijn gefactureerd (zonder koppeling per regel) kan het restregelvoorstel te hoog zijn; het scherm waarschuwt hiervoor met het al gefactureerde bedrag.
- Na de import hebben contracten geen volgende factuurdatum, zodat Werkbank niet ongemerkt naast Gripp gaat factureren. Die stel je per contract in bij de overstap.

**Volgende stap:** fase 5, inkoop.
