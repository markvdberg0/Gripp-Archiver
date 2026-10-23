# Analyse en implementatieplan

Werkbank is een opensource, zelf te hosten bedrijfsapplicatie (Laravel) voor de processen die we nu in Gripp doen. Dit document legt vast wat we al hebben, wat aantoonbaar nodig is, wat ontbreekt en in welke fasen we bouwen. De werknaam "Werkbank" is een aanname en kan worden aangepast met `APP_NAME`.

> Werkbank is geen product van, en niet verbonden aan, Gripp B.V. Er is geen code, vormgeving, logo of tekst van Gripp overgenomen. "Gripp" wordt alleen genoemd om de bron van een import aan te duiden.

## 1. Wat er al is (en herbruikbaar is)

| Onderdeel | Waar | Hergebruik in Werkbank |
|---|---|---|
| Alleen-lezende back-up van alle ±50 Gripp-tabellen via de API, hervatbaar, met rate-limit, profielen, planning | `importer/`, `lib/`, `cli.mjs` (Node) | Blijft ongewijzigd bestaan en is de **bron** voor de import. Werkbank leest het back-upformaat (`data/current/raw/*.json`, `state.json`, `documents.json`, `archive/`). |
| Bijlagen, factuur-PDF's en documenten (offertes, opdrachten, inkoopopdrachten) met SHA-256 | `archive/` in de back-upmap | Worden bij import gekopieerd naar de eigen opslag, ontdubbeld op SHA-256. |
| Schema van entiteiten en verwijzingen | `lib/schema.mjs` | Gebruikt als referentie voor het datamodel en de importvolgorde. |
| Volledigheidsrapport (aantallen, gebroken verwijzingen, controletotalen) | `importer/import.mjs` | Werkbank maakt per import een vergelijkbaar, controleerbaar rapport. |
| Offline viewer | `viewer/` | Blijft het noodarchief, los van Werkbank. |
| Tests met een nep-API | `test/` | Blijven; Werkbank krijgt eigen PHPUnit-tests met een nep-back-up. |

Er is ook **Nexus** (eigen Next.js-applicatie, propriëtair, geen licentie). Die is alleen als **procesbeschrijving** geraadpleegd (bijvoorbeeld de controle van inkoopfacturen en herinneringen). Er is geen code uit overgenomen.

## 2. Wat aantoonbaar nodig is

Bron: het importrapport van de echte back-up (aantallen per tabel, 15-10-2026), de veldnamen die daarin gevuld zijn, en de procesdocumentatie in Nexus. Alleen wat met data is aangetoond, telt mee.

| Proces | Bewijs (records) | Prioriteit |
|---|---|---|
| Relaties (bedrijven, rollen klant/leverancier/prospect/lead) en contactpersonen | company 217, contact 111, tag 31 | Hoog |
| Offertes met regels (groepslabels, producten, BTW), fasen, status, acceptatie, kans, sjablonen | offer 187, offerprojectline 4.346, offerphase 7 | Hoog |
| Producten en pakketten (verkoop-/inkoopprijs, eenheid, BTW, grootboek, leverancier, voorraadveld) | product 411, packet 4, packetline 27, unit 6 | Hoog |
| Opdrachten (projecten) met regels, fasen, medewerkers, start/einde/deadline | project 34, projectphase 9 | Hoog |
| Verkoopfacturen met regels, betalingen, openstaand bedrag, herinneringsfase | invoice 71, invoiceline 349, payment 62 | Hoog |
| Inkoopopdrachten met regels, gekoppeld aan opdrachten | purchaseorder 156, purchaseorderline 315 | Hoog |
| Inkoopfacturen met regels, betalingen, fasen Concept → Akkoord → Verwerkt, On-hold | purchaseinvoice 262, purchaseinvoiceline 427, purchasepayment 271 | Hoog |
| Timeline: notities, ontvangen/verzonden e-mails, systeemberichten per relatie/document | timelineentry 2.194 | Hoog |
| Bijlagen | file 2.507 (452 MB) | Hoog |
| Grootboekrekeningen en kostenplaatsen (als codering, niet als boekhouding) | ledger 248, costheading 3 | Middel |
| Taken en agenda-items | task 6, calendaritem 157 | Middel |
| Urenregistratie met fiattering | hour 12 | Laag (weinig gebruikt) |
| Contracten (periodiek factureren) | contract 2, contractline 2 | Laag |
| Memoriaal en kosten | memorial 13, memorialline 31, cost 106 | Laag (archief) |
| Verlof | absencerequest 3 | Laag |
| Medewerkers | employee 5 | Hoog (accountmanager, uren, rechten) |

Niet in gebruik (0 records of geen rechten): afdelingen, jaardoelen, dossiers, omzetdoelen, prijsuitzonderingen, staffelprijzen, overkoepelende projecten, externe links.

## 3. Wat ontbreekt en open vragen

**Ontbreekt nog volledig** (Werkbank begint vanaf nul als applicatie): authenticatie, rollen, logging, datamodel, schermen, documentgeneratie (PDF), e-mailverzending, nummering, rapportages.

**Niet uit de API te halen** (dus ook niet te importeren): bankmutaties, journaalposten, BTW-aangifte, W&V/balans, wijzigingsgeschiedenis, sjabloon-opmaak, instellingen (identiteiten, BTW-tarieven, betalingscondities). BTW-tarieven, eenheden en dergelijke worden afgeleid uit de verwijzingen in de records.

**Open vragen** (blokkeren de huidige fase niet; er is een aanname gekozen):

1. *Boekhouding.* Moet Werkbank een volledige boekhouding worden (journaal, BTW-aangifte, bank)? **Aanname:** nee; grootboek en kostenplaats zijn codering op regels, de boekhouding blijft extern (export).
2. *Documentopmaak.* Moeten offertes en facturen er hetzelfde uitzien als de huidige Gripp-sjablonen? **Aanname:** eigen, eenvoudige huisstijl met bedrijfsgegevens uit de instellingen.
3. *E-mail.* Moeten offertes en facturen vanuit Werkbank gemaild worden? **Aanname:** in een latere fase; eerst PDF downloaden en status handmatig op "verzonden" zetten.
4. *Klantportaal en digitaal akkoord.* **Aanname:** buiten scope voor nu.
5. *Verhouding met Nexus.* Nexus vervult deels dezelfde rol. **Aanname:** Werkbank is het opensource, zelf te hosten alternatief; er is geen koppeling tussen beide.
6. *Overgang.* Blijft Gripp de bron tot een overstapdatum? **Aanname:** ja. Herhaalde imports zijn veilig; lokale wijzigingen worden niet overschreven maar als conflict gemeld.

## 4. Architectuur

- **Laravel 13** op **PHP 8.4** (minimaal 8.4.1, ook 8.5; de Symfony 8-onderdelen van Laravel 13 vragen dat). Eén applicatie (monoliet), Blade-schermen, eigen CSS, geen JavaScript-buildstap. Dat houdt selfhosting en onderhoud eenvoudig.
- **Database:** SQLite standaard (één bestand, eenvoudig te back-uppen); MariaDB/MySQL of PostgreSQL via `.env`. Migraties zijn database-onafhankelijk.
- **Bedragen** als decimal (2 of 4 decimalen) en berekend in PHP met afronding per regel. Geen floats in de database.
- **Rechten:** vaste rollen met een rechtenmatrix in code (`app/Support/Rechten.php`), afgedwongen via Gates en policies.
- **Audit-log** van elke wijziging (wie, wat, wanneer, oude en nieuwe waarden), met maskering van gevoelige velden.
- **Import:** idempotent op `gripp_id`, met een droogloop, een rapport per run, en conflictdetectie via een hash van het bronrecord.
- **Bestanden** in `storage/app/private/bestanden/`, ontdubbeld op SHA-256, alleen te downloaden met rechten.

## 5. Fasen

Elke fase levert een werkende verticale doorsnede: migraties, bedrijfslogica, schermen, import en tests. **Stand: alle zes fasen zijn opgeleverd**; per fase staat in [voortgang.md](voortgang.md) wat werkt, hoe het getest is en wat ontbreekt.

| Fase | Inhoud | Klaar als |
|---|---|---|
| 1. Fundament en relaties | Laravel, inloggen, gebruikers, rollen, audit-log, Docker, import-raamwerk; relaties, contactpersonen, labels, medewerkers, bijlagen, timeline-notities | Echte back-up importeert herhaalbaar zonder duplicaten; relaties beheren werkt met rechten |
| 2. Producten en offertes | Eenheden, BTW-tarieven, grootboek, kostenplaatsen, producten, pakketten, offertefasen, offertes met regels, totalen, status, acceptatie, PDF | Offerte maken, berekenen, accepteren en als PDF downloaden; import met regels |
| 3. Opdrachten, taken en uren | Opdrachten met regels en fasen, offerte → opdracht, taken, urenregistratie met fiattering | Geaccepteerde offerte wordt opdracht; uren schrijven en fiatteren |
| 4. Verkoopfacturen | Facturen met regels, nummering, factureren vanuit opdracht, betalingen, openstaand, herinneringsfase, PDF, contracten | Factuur maken uit opdracht, betaling registreren, openstaand klopt met import |
| 5. Inkoop | Inkoopopdrachten, inkoopfacturen met regels, controleflow (Concept → Controle → Akkoord → Verwerkt, On-hold), betalingen | Inkoopfactuur goedkeuren met vier-ogen; openstaand klopt met import |
| 6. Overzichten en beheer | Dashboard, openstaande debiteuren/crediteuren, omzet per maand, pijplijn, zoeken, CSV-export, memoriaal/kosten (alleen-lezen archief), back-up en herstel van Werkbank zelf, documentatie | Rapporten kloppen met de controletotalen van de back-up |

## 6. Gelijkwaardigheid

Werkbank claimt **geen** volledige gelijkwaardigheid met Gripp. Per fase staat in [voortgang.md](voortgang.md) wat werkt, hoe het getest is en wat ontbreekt.
