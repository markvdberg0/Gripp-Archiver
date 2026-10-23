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
