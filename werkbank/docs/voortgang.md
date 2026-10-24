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

## Fase 5 — Inkoopopdrachten en inkoopfacturen

**Wat werkt**

- Inkoopopdrachten: lijst met filters, aanmaken met regels (grootboek, kostenplaats, opdracht per regel; regels erven de opdracht), statussen concept → verzonden → bevestigd → afgerond, archiveren, PDF voor de leverancier, overzicht van wat er al is gefactureerd.
- Inkoopfacturen: boeken met eigen boeknummer en het factuurnummer van de leverancier, vervaldatum uit de betaaltermijn van de leverancier, regels met grootboek/kostenplaats/opdracht, voorstel vanuit een inkoopopdracht (regels gekoppeld), scan uploaden bij Bestanden.
- **Controle vóór goedkeuring** (op basis van het werkproces in jullie documentatie): leverancier, factuurnummer, dubbel factuurnummer bij dezelfde leverancier (blokkeert), regels aanwezig, totaal van de regels gelijk aan het totaal op de factuur (blokkeert), koppeling aan inkoopopdracht/opdracht/kostenplaats en grootboek per regel (waarschuwing), bedrag binnen de inkoopopdracht (waarschuwing).
- **Fasen** concept → controle → akkoord → verwerkt, terugzetten, **on hold** met reden en vrijgeven; goedkeuren alleen met het recht "inkoopfacturen goedkeuren"; **vier-ogenprincipe**: boven een instelbare drempel mag de invoerder niet zelf goedkeuren.
- Betalingen op inkoopfacturen (na goedkeuring), openstaand en vervallen.
- **Btw verlegd** (intracommunautair, import): btw wordt berekend en getoond maar telt niet mee in het te betalen totaal; instelbaar per btw-tarief en automatisch herkend bij de import.
- Import: inkoopopdrachten (leverancier, status, Gripp-PDF), regels (bestelcode, eenheid, opdracht), inkoopfacturen (fase, on hold, inbox, boeknummer met detectie van dubbele nummers), regels en betalingen; inkoopopdracht en opdracht van een inkoopfactuur afgeleid uit de regels. Het importrapport noemt nu per document waar regels en kopbedrag in de bron niet op elkaar aansluiten.

**Hoe getest**

- 96 tests (741 controles), nieuw: inkoopopdracht met nummering, status, PDF en verwijderbeveiliging; inkoopfactuur uit inkoopopdracht; btw verlegd; controle en goedkeuring met vier-ogenprincipe, betaling en verwerken; dubbel factuurnummer; on hold; rechten; import inclusief afgeleide koppelingen, dubbele boeknummers, verlegde btw en de meldingen per afwijkend document.
- Browser: inkoopfacturen per fase (aantallen gelijk aan Gripp: 12 concept, 2 akkoord, 246 verwerkt, 2 on hold), detail van een on-hold-factuur met de controle en de echte timeline.
- Echte back-up: 156 inkoopopdrachten (som gelijk aan de bron), 315 regels, 262 inkoopfacturen (totaal en openstaand gelijk aan de bron en het rapport van de back-uptool), 427 regels, 271 betalingen (sluiten aan bij alle 262 facturen). Regels tellen op tot het kopbedrag bij 155 van 156 inkoopopdrachten en 256 van 257 inkoopfacturen met regels; de twee uitzonderingen zijn afrondingsverschillen van 2 en 3 cent in Gripp zelf en staan als melding in het rapport.

**Beperkingen**

- Geen ontvangstregistratie (goederenontvangst) en dus geen volledige drieweg-controle; de controle vergelijkt factuur en inkoopopdracht.
- Geen inbox voor inkoopfacturen per e-mail en geen automatisch uitlezen van PDF's.
- Geen SEPA-betaalbestand en geen koppeling met bank of boekhouding.
- Betalingen op inkoopfacturen kunnen nog niet worden verwijderd in het scherm.
- Bij leveranciers zijn in de Gripp-data dubbele relaties zichtbaar (bijvoorbeeld drie keer dezelfde naam); samenvoegen kan nog niet.

**Volgende stap:** fase 6, overzichten, back-up en herstel, documentatie.

## Fase 6 — Overzichten, back-up en herstel, beheer

**Wat werkt**

- **Back-up en herstel van Werkbank**: `werkbank:backup` (database als JSON per tabel plus alle bestanden, met SHA-256 per onderdeel), automatisch elke nacht met opruimen; `werkbank:herstel` met volledige controle vooraf, automatische veiligheidsback-up, herstel op het schema van de back-up en daarna de nieuwere migraties; databasetype-onafhankelijk. Beheerpagina om back-ups te maken en (gelogd) te downloaden.
- **Overzichten** met CSV-export: openstaande debiteuren en crediteuren op een peildatum met ouderdomsanalyse per relatie, omzet en inkoop per maand, offertepijplijn per fase met gewogen waarde, uren per medewerker per maand.
- **Overzichtsscherm** met cijfers per rol: open offertes, openstaand en vervallen, concepten, inkoopfacturen ter goedkeuring, te fiatteren uren, mijn taken.
- **CSV-export** van relaties en verkoopfacturen (met bescherming tegen formules in spreadsheets; elke export staat in het logboek).
- **AVG**: contactpersoon anonimiseren (beheerder); ook de oude waarden in het logboek worden gewist.
- **Memoriaal en kosten** uit Gripp als alleen-lezen archief.
- Documentatie: installatie, configuratie, import, updates, back-up en herstel, beveiliging, licenties.

**Hoe getest**

- 107 tests (817 controles; na de codereview 118), nieuw: back-up en herstel met bestanden en veiligheidsback-up, weigeren van een gewijzigde back-up, controleren en opruimen, beheerpagina en padbeveiliging bij downloaden; debiteuren op peildatum met ouderdom (betaling na de peildatum telt niet mee), omzet per maand (concepten tellen niet), rechten op overzichten, CSV met formule-injectie, anonimiseren, memoriaal/kosten, overzichtsscherm per rol.
- Echte gegevens: back-up 12 s, controle 2 s, herstel in een lege database 8 s met identieke aantallen en totalen; openstaande debiteuren gelijk aan het rapport van de back-uptool; som van de kosten gelijk aan de bron.

**Beperkingen**

- Terugzetten alleen via de opdrachtregel (bewust).
- De `APP_KEY` zit niet in de back-up; bewaar die apart.
- Geen winst-en-verliesrekening, balans of btw-aangifte (geen boekhouding, aanname 1).
- Verlof is niet overgenomen.

## Codereview

Na fase 6 is de code onafhankelijk gereviewd op rechten, bestanden, import, back-up/herstel en geldlogica. Opgelost (met regressietests in `tests/Feature/ReviewFixesTest.php`, 118 tests in totaal):

- Vier-ogenprincipe was te omzeilen door een factuur eerst te wijzigen en dan goed te keuren; geldt nu ook voor de laatste wijziger. De drempel kan alleen nog door een beheerder worden gewijzigd.
- Verwerkte of betaalde inkoopfacturen konden terug naar concept; vrijgeven van "on hold" kon zonder goedkeurrecht; betalingen op een factuur die on hold staat waren mogelijk.
- Lokaal definitief verwijderde betalingen, regels en uren kwamen terug bij een volgende import; regels en betalingen werden bijgewerkt onder een lokaal gewijzigd document; afgeleide velden werden ook bij lokaal gewijzigde records ingevuld; id-koppelingen van een teruggedraaide importrij bleven bewaard.
- Bijlagen en timeline-items bij (inkoop)facturen waren zichtbaar voor rollen zonder financiële rechten.
- Dubbel klikken op "definitief maken" kon een gat in de factuurnummering geven; een factuur kon meerdere keren worden gecrediteerd; eigen uren konden zelf worden gefiatteerd.
- "1.250" werd gelezen als 1,25 en "1,234.56" verkeerd; nu volgens een vaste regel (zie `Bedragen::parse`).
- Herstel zet de gegevens nu in één transactie terug en noemt bij een fout de veiligheidsback-up; een back-up is één samenhangende momentopname; contractfacturatie kan niet twee keer tegelijk draaien; bestandsnamen met een schuine streep zijn weer te downloaden.

Bewust niet gewijzigd: voor geïmporteerde inkoopfacturen is niet bekend wie ze in Gripp invoerde, dus het vier-ogenprincipe geldt pas na een wijziging in Werkbank. Bij een mislukt herstel op MariaDB/MySQL (geen transactionele schemawijzigingen) kan de database leeg achterblijven; zet dan de genoemde veiligheidsback-up terug.

## Eindstand en gelijkwaardigheid

Werkbank dekt nu de processen die in de Gripp-data aantoonbaar gebruikt worden: relaties, offertes, opdrachten, taken en planning, uren, verkoopfacturen met betalingen en herinneringen, contracten, inkoopopdrachten en inkoopfacturen met goedkeuring, plus import, back-up en overzichten. De echte back-up is volledig te importeren en alle controletotalen sluiten aan.

**Volledige gelijkwaardigheid met Gripp is daarmee niet aangetoond.** Wat bewust of nog niet is gedaan:

| Onderwerp | Status |
|---|---|
| E-mail versturen (offertes, facturen, herinneringen, opdrachten, inkoopopdrachten) | Beschikbaar, met PDF-bijlage en archief in de timeline; geen ontvangst- of leesbevestiging |
| Digitaal akkoord door de klant, klantportaal | Ontbreekt |
| Documentopmaak gelijk aan de Gripp-sjablonen | Eigen, eenvoudige opmaak |
| Boekhouding (journaal, bank, btw-aangifte, W&V/balans) | Buiten scope; memoriaal en kosten alleen als archief |
| UBL/e-factuur, SEPA-bestanden, bankkoppeling | Ontbreekt |
| Agenda-koppeling, sleep-en-zet-planning | Ontbreekt; weekoverzicht met invoer |
| Nacalculatie in euro's (kostprijs per medewerker) | Ontbreekt; alleen uren |
| Verlof | Niet overgenomen |
| Voorraad | Alleen de Gripp-velden als vrije velden |
| Relaties samenvoegen (dubbele relaties) | Beschikbaar, met opsporing op naam, KvK, btw en IBAN; niet ongedaan te maken (alleen via back-up) |
| Wachtwoordherstel per e-mail | Beschikbaar |
| Tweestapsverificatie | Beschikbaar (TOTP met herstelcodes; optioneel verplicht) |
| Docker-image en CI op MariaDB/PostgreSQL | Geschreven, lokaal niet uitgevoerd (geen Docker beschikbaar); draait in GitHub Actions |

Deze punten zijn de logische volgende stappen; welke daarvan nodig zijn, hangt af van de antwoorden op de open vragen in [analyse-en-plan.md](analyse-en-plan.md).

## Uitbreidingen na de eindstand

### E-mail

- **Werkt:** offertes, verkoopfacturen, betalingsherinneringen, opdrachtbevestigingen en inkoopopdrachten per e-mail versturen met de PDF als bijlage. Ontvanger, onderwerp en tekst zijn vooraf ingevuld (teksten met plaatshouders zoals `{nummer}` en `{openstaand}` instelbaar onder Beheer → Instellingen). Bij versturen: offerte en inkoopopdracht gaan van concept naar verzonden, een herinnering verhoogt het herinneringsniveau. De verzonden PDF en tekst worden als timeline-item bewaard. Mislukt het versturen, dan wordt niets gewijzigd. Wachtwoord vergeten werkt via een herstellink per e-mail.
- **Getest:** `tests/Feature/MailTest.php` (versturen, adrescontrole, concept/sjabloon geweigerd, herinneringsniveau, rechten, terugdraaien bij een fout, volledige herstelflow met eenmalige token).
- **Beperkingen:** versturen gebeurt direct (niet via de queue), dus een trage mailserver laat de gebruiker wachten. Geen bounce- of leesbevestiging. Of de mail echt aankomt, hangt af van je mailserver en SPF/DKIM.

### Relaties samenvoegen

- **Werkt:** Relaties › Dubbele relaties toont kandidaten met een gelijke genormaliseerde naam ("Staal & Co B.V." = "staal co bv"), KvK-nummer, btw-nummer of IBAN. Bij samenvoegen gaan contactpersonen, timeline, offertes, opdrachten, taken, contracten, verkoop- en inkoopdocumenten, producten, bestanden en labels naar de relatie die blijft; lege velden worden desgewenst aangevuld en rollen samengevoegd. De opgeheven relatie wordt zacht verwijderd, oude links verwijzen door, en de import volgt de samenvoeging. Recht: `relaties.samenvoegen` (beheerder en administratie).
- **Getest:** `tests/Feature/CompanyMergeTest.php` (verplaatsen inclusief dubbele labels en bestanden, aanvullen, doorverwijzen, rechten en bevestiging, opsporing, import na samenvoegen). Op de echte data: 13 kandidaatgroepen, opsporing in ongeveer 5 ms.
- **Beperkingen:** niet ongedaan te maken behalve via een back-up. Contactpersonen worden niet ontdubbeld. De blijvende relatie geldt na aanvullen als lokaal gewijzigd, dus latere Gripp-wijzigingen aan die relatie worden als conflict gemeld.

### Tweestapsverificatie

- **Werkt:** onder Account › Tweestapsverificatie instellen met een QR-code (eigen TOTP-implementatie volgens RFC 6238, QR via `bacon/bacon-qr-code`), herstelcodes, vernieuwen en uitzetten met het huidige wachtwoord. Bij inloggen volgt na het wachtwoord de code. Een beheerder kan het verplicht stellen en voor een gebruiker uitzetten.
- **Getest:** `tests/Feature/TwoFactorTest.php`, onder meer met de officiële RFC 6238-testvector, het weigeren van een hergebruikte code, eenmalige herstelcodes, maximaal aantal pogingen en verlopen, en de rechten op de verplichting.
- **Beperkingen:** geen WebAuthn of passkeys, en geen "dit apparaat onthouden".
