# Beveiliging

## Een kwetsbaarheid melden

Meld een beveiligingsprobleem **niet** in een openbaar issue. Gebruik op GitHub **Security › Report a vulnerability** (een privé-melding aan de beheerders). Beschrijf wat je vond, hoe je het kunt nagaan en welke versie het betreft. Je krijgt zo snel mogelijk een reactie.

## Wat de tool doet met je gegevens

**API-token**
- Staat alleen in `profiles/<profiel>/config.env` op je eigen computer. Die map staat in `.gitignore` en komt dus nooit in de repository.
- Wordt alleen meegestuurd naar de API-host uit het profiel (standaard `api.gripp.com`), nooit gelogd en nooit naar een andere host gestuurd. Doorverwijzingen worden niet gevolgd.
- Advies: maak in Gripp een API-sleutel met een rol die **alleen mag lezen**.

**Alleen lezen**
- De API-client weigert technisch elke methode behalve `*.get` en `file.getContent` (`lib/schema.mjs`, `lib/gripp-client.mjs`). De tests controleren dat er nooit een schrijfmethode wordt verstuurd.
- De documentenstap (standaard uit) opent alleen de document-links die Gripp zelf in de records meelevert, en alleen op `*.gripp.com`. Er wordt niets geaccepteerd, ondertekend of verzonden.

**Netwerk.** De tool maakt alleen verbinding met:
- de Gripp-API (`api.gripp.com`, of de `GRIPP_API_URL` uit het profiel);
- `*.gripp.com`, voor factuur-PDF's en, als je dat aanzet, documenten;
- `nodejs.org`, alleen als de startscripts een portable Node.js ophalen. Die download wordt gecontroleerd met het SHA-256-controlegetal in `tools/node.lock` en verwijderd als dat niet klopt.

Er is geen telemetrie, en er worden geen gegevens naar de makers van deze tool gestuurd.

**De back-up zelf**
- Een back-up bevat alle bedrijfs- en persoonsgegevens uit Gripp, ook bijzondere persoonsgegevens van medewerkers (BSN, bankrekening, privéadres).
- De viewer toont die niet bij Gegevens en maskeert ze bij Brongegevens, maar ze staan wel in de ruwe bestanden in `data/`.
- Bewaar de back-upmap even zorgvuldig als Gripp zelf: niet in een git-repository en niet in een openbaar gedeelde map.
- De viewer laadt geen externe bronnen; een Content-Security-Policy blokkeert scripts, afbeeldingen en verbindingen van buitenaf. HTML uit Gripp (e-mails, omschrijvingen) wordt vóór het tonen opgeschoond.
- De optionele lokale server (`serve`) luistert alleen op `127.0.0.1` en accepteert alleen GET-verzoeken.
