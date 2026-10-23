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
