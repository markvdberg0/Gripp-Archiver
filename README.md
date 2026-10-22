# Gripp Back-up

Maak een volledige, **alleen-lezende** back-up van je Gripp-account, met een archief dat je offline kunt openen in de browser. Gripp hoeft daarvoor niet bereikbaar te zijn en je hebt geen internet nodig om het archief te bekijken.

> **Onofficieel.** Dit project is niet gemaakt door, en niet verbonden aan, Gripp B.V. Het gebruikt alleen de openbare Gripp API.

- Haalt alle ±50 Gripp-tabellen op die de API levert, plus bijlagen, factuur-PDF's en PDF's van offertes, opdrachten en inkoopopdrachten.
- Kan technisch niets wijzigen in Gripp. De API-client weigert elke methode behalve `*.get` en `file.getContent`, en de tests controleren dat.
- Gebruikt alleen Node.js, zonder extra pakketten (`npm install` is niet nodig).
- Ondersteunt meerdere Gripp-accounts naast elkaar (profielen).

## Benodigd

- **Node.js 18 of nieuwer** (https://nodejs.org, LTS). Controleer met `node --version`.
- Een **API-sleutel** uit Gripp (Instellingen › API keys). Advies: maak een nieuwe sleutel met een **API-rol die alleen mag lezen**.

## Instellen

1. Download deze repository (`git clone`, of **Code › Download ZIP** en uitpakken).
2. Maak een profiel voor je account. Kies een korte naam, bijvoorbeeld `mijnbedrijf`:
   ```powershell
   mkdir profiles\mijnbedrijf
   copy config.env.example profiles\mijnbedrijf\config.env
   notepad profiles\mijnbedrijf\config.env
   ```
   Op macOS/Linux: `mkdir -p profiles/mijnbedrijf && cp config.env.example profiles/mijnbedrijf/config.env`.
3. Vul in `config.env` minimaal `GRIPP_API_TOKEN` en `GRIPP_ACCOUNT_NAME` in. Kies bij voorkeur ook een `GRIPP_BACKUP_DIR` buiten de toolmap (een korte map, bijvoorbeeld `D:\Back-ups\Gripp`). Zonder die instelling komt de back-up in `backups/<profiel>/`.
4. Optioneel, om te controleren dat alles werkt (praat alleen met een nep-API, raakt Gripp nooit):
   ```powershell
   npm test
   ```

Profielen en back-ups staan nooit in git (zie `.gitignore`). Je token en je gegevens worden dus niet meegestuurd als je de tool bijwerkt of deelt.

## Back-up maken

```powershell
npm run import
```

- Haalt alle tabellen op (250 records per call, in batches), daarna alle bijlagen, factuur-PDF's en documenten.
- Houdt rekening met de Gripp-limiet van **1000 requests per uur**. Is die op, dan wacht de import zelf tot het volgende uur. Je mag ook stoppen (Ctrl+C) en later verdergaan met `npm run import -- --resume`.
- Een eerste back-up kan enkele uren duren, afhankelijk van de grootte van je account.
- Na afloop staat een **volledigheidsrapport** in `data/current/report.md` in de back-upmap: aantallen per tabel, gebroken verwijzingen, financiële controletotalen en bekende beperkingen.
- **Een mislukte import overschrijft nooit de bestaande back-up.** Mislukt een tabel (bijvoorbeeld door onvoldoende API-rechten), dan blijft de nieuwe snapshot in `data/staging-…` staan en blijft `data/current` ongewijzigd. Pas de API-rol aan en hervat met `--resume`, of publiceer toch met `--accept-partial`.

Opties:

| Optie | Betekenis |
|---|---|
| `--profile=<naam>` | Kies het profiel (nodig als er meer dan één is) |
| `--resume` | Ga verder met de laatste onderbroken import |
| `--no-files` | Sla bijlagen en PDF's over |
| `--accept-partial` | Publiceer ook als sommige tabellen mislukten |
| `--only=company,offer` | Alleen deze tabellen (om te testen) |

Opties geef je na `--` mee, bijvoorbeeld `npm run import -- --profile=mijnbedrijf --resume`.

## Archief openen

- **Zonder server:** dubbelklik in de back-upmap op `Archief openen.html` (of `viewer/index.html`).
- **Met lokale server:**
  ```powershell
  npm run serve       # http://localhost:4173
  ```
  De server accepteert alleen GET-verzoeken, luistert alleen op je eigen computer en serveert uitsluitend `viewer/` en `archive/`.

In het archief vind je:
- Relaties, contactpersonen, offertes, opdrachten, inkoopopdrachten, verkoop- en inkoopfacturen, contracten, taken, uren, producten, medewerkers, memoriaal, kosten en de timeline.
- Per record de gegevens, regels, alle gekoppelde records, timeline, bestanden (met PDF-voorbeeld) en de volledige brongegevens.
- Zoeken, sorteren en CSV-export, plus een zoekfunctie over het hele archief.
- Rapporten: openstaande debiteuren en crediteuren met peildatum, omzet per maand en uren per medewerker.

## Wat staat er in de back-upmap

De back-upmap staat op zichzelf: je kunt hem verplaatsen of kopiëren en daar openen, zonder de tool.

```
<back-upmap>/
├─ Archief openen.html   startpagina
├─ viewer/               het archief (inclusief data/data.js)
├─ data/current/         laatste snapshot: ruwe API-JSON (bron van waarheid) + rapport
├─ data/previous/        vorige snapshot
└─ archive/              bijlagen, factuur-PDF's, documenten en handmatige exports
```

## Documenten van offertes, opdrachten en inkoopopdrachten

Gripp levert bij elk record een document-link mee (`directpdfurl` bij offertes, `viewonlineurl` bij opdrachten en inkoopopdrachten). De import haalt daarmee de PDF's op, zonder het API-quotum te belasten. Los opnieuw draaien kan met `npm run documents`.

- **Alleen downloaden:** er wordt niets geaccepteerd, ondertekend of verzonden.
- **Let op:** de online weergave is dezelfde pagina die je klant of leverancier ziet. Gripp kan het openen ervan als "bekeken" in de timeline zetten.

## Handmatige exports

Sommige gegevens levert de API niet. Leg exports uit Gripp in `archive/manual/` in de back-upmap en draai daarna `npm run build`.

- `archive/manual/verkoopfacturen/Factuur_<nummer>.pdf` wordt automatisch aan de juiste factuur gekoppeld.
- Aanbevolen: de **XAF-auditfile** per boekjaar (Boekhouding › Belasting & BTW › Auditfile downloaden).

## Meerdere accounts

Maak per account een map in `profiles/`. Elk profiel heeft een eigen token, naam en back-upmap. Zie [profiles/README.md](profiles/README.md).

## Bijwerken

Werk de tool bij met `git pull` of een nieuwe ZIP. Profielen en back-ups blijven daarbij onaangeroerd. Bouw daarna de viewer opnieuw met `npm run build`, zodat de back-upmap de nieuwe viewer krijgt.

## Veiligheid en privacy

- Gebruik een API-sleutel met een rol die alleen mag lezen, en deel `config.env` met niemand.
- Een back-up bevat alle bedrijfs- en persoonsgegevens uit Gripp, ook bijzondere persoonsgegevens van medewerkers (BSN, bankrekening, privéadres). Die worden in de viewer niet bij Gegevens getoond en zijn gemaskeerd bij Brongegevens, maar staan wel in de ruwe bestanden. Bewaar de back-upmap daarom net zo zorgvuldig als Gripp zelf.
- Zet een back-upmap nooit in een git-repository of een openbaar gedeelde map.

## Bekende beperkingen

- Bankmutaties en de rapporten winst & verlies, balans, saldibalans en BTW zitten niet in de API. Gebruik hiervoor de XAF-auditfile.
- De wijzigingsgeschiedenis ("Bekijk geschiedenis"), interacties, publicaties en batches zitten niet in de API.
- Instellingen (identiteiten, BTW-tarieven, condities) zijn alleen als verwijzing aanwezig.

## Ontwikkelen

```
importer/import.mjs     import (alleen-lezen, hervatbaar)
importer/documents.mjs  PDF's van offertes, opdrachten, inkoopopdrachten
importer/build.mjs      snapshot → viewer in de back-upmap
lib/config.mjs          profielen en paden
lib/gripp-client.mjs    API-client (weigert schrijfacties)
lib/schema.mjs          entiteiten en verwijzingen
viewer/                 statische viewer (geen externe bronnen)
serve.mjs               optionele lokale webserver (alleen GET)
test/                   tests tegen een nep-API
```

`npm test` draait de volledige import tegen een nep-API in een tijdelijke map.

## Licentie

MIT, zie [LICENSE](LICENSE).
