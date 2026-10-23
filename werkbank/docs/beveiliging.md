# Rollen, rechten en privacy

## Rollen

Elke gebruiker heeft één rol. De rechten per rol staan vast in de code ([app/Support/Rechten.php](../app/Support/Rechten.php)) en zijn in de applicatie te bekijken onder Beheer › Gebruikers.

| Rol | Bedoeld voor |
|---|---|
| Beheerder | Alles, inclusief gebruikers, import en logboek. |
| Administratie | Alle bedrijfsgegevens, facturen, inkoop en goedkeuren; geen gebruikersbeheer of import. |
| Projectleider | Relaties, offertes, opdrachten, taken, uren fiatteren, inkoop invoeren; facturen alleen bekijken; geen bankgegevens. |
| Medewerker | Bekijken van relaties, offertes en opdrachten; eigen uren en taken. |
| Alleen lezen | Alles bekijken behalve beheer; niets wijzigen. |

Er moet altijd minstens één actieve beheerder zijn. Een beheerder kan zijn eigen rol niet wijzigen en zichzelf niet deactiveren.

## Inloggen

- Wachtwoorden: minstens 10 tekens met letters en cijfers, opgeslagen met bcrypt.
- Nieuwe gebruikers en gebruikers van wie de beheerder het wachtwoord heeft gereset, krijgen een tijdelijk wachtwoord en moeten eerst een eigen wachtwoord kiezen. Een reset logt de gebruiker overal uit.
- Na 5 mislukte pogingen per minuut (per e-mailadres en IP) wordt inloggen tijdelijk geblokkeerd.
- Een gedeactiveerde gebruiker wordt bij het volgende verzoek uitgelogd.
- Sessies zijn versleuteld (`SESSION_ENCRYPT=true`) en verlopen na `SESSION_LIFETIME` minuten.
- Nog niet beschikbaar: tweestapsverificatie en wachtwoordherstel per e-mail (de beheerder maakt een tijdelijk wachtwoord).

## Logboek

Elke wijziging aan relaties, contactpersonen, medewerkers, gebruikers en (in latere fasen) documenten wordt vastgelegd met gebruiker, tijdstip, IP-adres en oude en nieuwe waarden. Ook inloggen, mislukte inlogpogingen, uitloggen, wachtwoordwijzigingen, uploads en importruns staan erin. Wachtwoorden en bankgegevens worden gemaskeerd (`***`). Het logboek is alleen-lezen en te bekijken met het recht "Logboek bekijken".

## Persoonsgegevens

- **Dataminimalisatie:** van medewerkers worden geen BSN, bankgegevens, privéadressen of gezinsleden opgeslagen, ook niet bij de import uit Gripp (die velden staan in de back-up maar worden overgeslagen en niet eens gehasht).
- **Bankgegevens van relaties** zijn alleen zichtbaar en wijzigbaar met het recht "Bankgegevens van relaties" (beheerder en administratie).
- **Bestanden** staan buiten de webroot en zijn alleen te downloaden door gebruikers die een record mogen zien waar het bestand aan hangt. Ze worden met een strikte Content-Security-Policy en `sandbox` geserveerd.
- **HTML uit e-mails** (geïmporteerde timeline) wordt opgeschoond met `symfony/html-sanitizer`: geen scripts, formulieren, stijlen of externe afbeeldingen.
- **Beveiligingsheaders:** Content-Security-Policy zonder externe bronnen en zonder inline scripts, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, en HSTS bij https.
- Werkbank stuurt geen gegevens naar derden en heeft geen telemetrie.
- **Verwijderen:** relaties en contactpersonen worden "zacht" verwijderd (ze blijven in de database en in back-ups). Voor een AVG-verwijderverzoek moet een beheerder het record ook uit de database en uit oude back-ups verwijderen; een functie daarvoor ontbreekt nog.

## Kwetsbaarheden melden

Zie [SECURITY.md](../../SECURITY.md) in de hoofdmap.
