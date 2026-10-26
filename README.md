# Gripp Back-up

Maak een volledige, **alleen-lezende** back-up van je Gripp-account, met een archief dat je offline kunt openen in de browser. Gripp hoeft daarvoor niet bereikbaar te zijn en je hebt geen internet nodig om het archief te bekijken.

> **Onofficieel.** Dit project is niet gemaakt door, en niet verbonden aan, Gripp B.V. Het gebruikt alleen de openbare Gripp API.

- Haalt alle ±50 Gripp-tabellen op die de API levert, plus bijlagen, factuur-PDF's en (optioneel) PDF's van offertes, opdrachten en inkoopopdrachten.
- Kan technisch niets wijzigen in Gripp. De API-client weigert elke methode behalve `*.get` en `file.getContent`, en de tests controleren dat.
- Niets te installeren: de startscripts halen zo nodig zelf een portable Node.js op.
- Ondersteunt meerdere Gripp-accounts naast elkaar (profielen), en een automatische dagelijkse back-up.

> **Nieuw: Werkbank.** In de map [`werkbank/`](werkbank/README.md) staat een opensource, zelf te hosten bedrijfsapplicatie (Laravel) voor relaties, offertes, opdrachten, uren, facturatie en inkoop. Werkbank kan een back-up van deze tool controleerbaar importeren. De back-uptool zelf blijft ongewijzigd werken.

## Downloaden

Download de nieuwste versie bij **[Releases](../../releases/latest)** (`gripp-backup-<versie>.zip`) en pak hem uit. Heb je git, dan kan ook `git clone`; bijwerken gaat dan met `git pull`. Wat er per versie veranderd is, staat in [CHANGELOG.md](CHANGELOG.md).

## Benodigd

- Windows 10/11, macOS of Linux (x64 of ARM).
- Een **API-sleutel** uit Gripp (Instellingen › API keys). Advies: maak een nieuwe sleutel met een **API-rol die alleen mag lezen**.

Node.js hoeft niet geïnstalleerd te zijn. Staat er al Node 18.17 of nieuwer op de computer, dan wordt die gebruikt. Zo niet, dan downloaden de startscripts eenmalig de officiële Node.js-versie uit [tools/node.lock](tools/node.lock) van nodejs.org naar de map `.runtime/`. De download wordt gecontroleerd met het SHA-256-controlegetal dat in de repository vastligt; klopt dat niet, dan wordt hij verwijderd. Er is geen installatie en er zijn geen beheerdersrechten nodig. Wil je altijd de portable versie gebruiken, zet dan `GRIPP_PORTABLE_NODE=1`.

## Starten

| Systeem | Hoe |
|---|---|
| Windows | Dubbelklik op `Start-Windows.cmd` |
| macOS | Dubbelklik op `Start-Mac.command` (de eerste keer eventueel: rechtsklik › Open, omdat het script niet is ondertekend) |
| Linux | `./start.sh` in een terminal |

Zonder opdracht krijg je een menu: back-up maken, hervatten, archief openen, verbinding en rechten controleren, viewer opnieuw bouwen, nieuw account instellen en zelftest. De eerste keer start automatisch de setup. Je kunt ook direct een opdracht meegeven, bijvoorbeeld `Start-Windows.cmd backup --profile=mijnbedrijf` of `./start.sh open`. Alle opdrachten: `Start-Windows.cmd help`.

Heb je zelf Node.js, dan werkt `node cli.mjs <opdracht>` ook.

## Instellen

1. Download en pak uit (zie [Downloaden](#downloaden)).
2. Maak in Gripp een API-sleutel: Instellingen › API › maak een **API-rol die alleen mag lezen** (vink alle onderdelen aan) en een nieuwe sleutel met die rol.
3. Start de tool (zie hierboven). De eerste keer start de **setup** vanzelf; later kies je **Nieuw account instellen** in het menu, of `Start-Windows.cmd setup`. De setup:
   - vraagt het API-token (je ziet alleen sterretjes; plakken mag);
   - controleert meteen de verbinding en de leesrechten per tabel. Ontbreken er rechten, dan noemt hij precies wat je in de API-rol moet aanvinken (bijvoorbeeld "afdeling" of "staffelprijs");
   - vraagt de bedrijfsnaam, een korte profielnaam en de map voor de back-up (standaard `Gripp-backup\<profiel>` in je gebruikersmap). Kies bij voorkeur een korte map buiten de toolmap, bijvoorbeeld `D:\Back-ups\Gripp`;
   - slaat alles op in `profiles/<profiel>/config.env` en biedt aan meteen de eerste back-up te maken.
4. Rechten later aangepast? Kies **Verbinding en rechten controleren** (`Start-Windows.cmd check`). Dit kost ongeveer 10–20 van de 1000 requests per uur.
5. Optioneel: **Zelftest** (`Start-Windows.cmd test`) controleert de tool zelf tegen een nep-API en raakt Gripp nooit.

Liever met de hand? Kopieer `config.env.example` naar `profiles/<profiel>/config.env` en vul het in.

Profielen en back-ups staan nooit in git (zie `.gitignore`). Je token en je gegevens worden dus niet meegestuurd als je de tool bijwerkt of deelt.

## Back-up maken

Kies in het menu **Back-up maken**, of:

```powershell
Start-Windows.cmd backup
```

- Haalt alle tabellen op (250 records per call, in batches), daarna de bijlagen en factuur-PDF's, en desgewenst de documenten (zie hieronder).
- **Bijlagen worden hergebruikt.** Wat al in `archive/` staat (zelfde bestand, zelfde grootte) wordt niet opnieuw opgehaald; de administratie daarvan staat in `archive/index.json`. Definitieve factuur-PDF's worden ook hergebruikt, concepten altijd opnieuw opgehaald. Een tweede back-up is daardoor veel sneller dan de eerste en kost veel minder API-quotum. Alles opnieuw ophalen: `--refresh-files`.
- Houdt rekening met de Gripp-limiet van **1000 requests per uur**. Is die op, dan wacht de import zelf tot het volgende uur. Je mag ook stoppen (Ctrl+C) en later verdergaan met **Onderbroken back-up hervatten** in het menu, of `Start-Windows.cmd resume`.
- Een eerste back-up kan enkele uren duren, afhankelijk van de grootte van je account. Zodra de aantallen bekend zijn, toont de import een schatting (bijvoorbeeld "nog ± 2600 API-calls → ± 2 uur door de limiet van 1000 calls per uur"). Vooral bijlagen kosten tijd: elke bijlage is één call.
- Na afloop staat een **volledigheidsrapport** in `data/current/report.md` in de back-upmap: aantallen per tabel, gebroken verwijzingen, financiële controletotalen en bekende beperkingen.
- Is Gripp in onderhoud of heeft het een storing (de API stuurt dan door naar een statuspagina zoals status.exact.com), dan wacht de import een paar keer en stopt daarna met een duidelijke melding. Hervat later.
- **Een mislukte import overschrijft nooit de bestaande back-up.** Mislukt een tabel (bijvoorbeeld door onvoldoende API-rechten), dan blijft de nieuwe snapshot in `data/staging-…` staan en blijft `data/current` ongewijzigd. Pas de API-rol aan en hervat met `--resume`, of publiceer toch met `--accept-partial`.

Opties:

| Optie | Betekenis |
|---|---|
| `--profile=<naam>` | Kies het profiel (nodig als er meer dan één is) |
| `--resume` | Ga verder met de laatste onderbroken import |
| `--no-files` | Sla bijlagen en PDF's over |
| `--documents` / `--no-documents` | Wel/niet de PDF's van offertes, opdrachten en inkoopopdrachten (standaard volgens het profiel) |
| `--refresh-files` | Alle bijlagen opnieuw ophalen, ook als ze al in het archief staan |
| `--auto` | Hervat een onderbroken back-up van minder dan een dag oud, anders begin opnieuw (gebruikt door de automatische back-up) |
| `--accept-partial` | Publiceer ook als sommige tabellen mislukten |
| `--only=company,offer` | Alleen deze tabellen, om te testen. Wordt **nooit** gepubliceerd: het resultaat komt in `data/only-…` en `data/current` blijft ongewijzigd |

Opties zet je achter de opdracht, bijvoorbeeld `Start-Windows.cmd backup --profile=mijnbedrijf --no-files` (macOS/Linux: `./start.sh backup …`).

Het resultaat van de laatste back-up staat in `laatste-back-up.json` in de back-upmap en bovenaan het menu. Exitcodes: `0` gelukt, `2` klaar maar niet gepubliceerd (bijvoorbeeld door ontbrekende rechten; zie het rapport), `1` afgebroken.

## Automatische back-up

Kies in het menu **Automatische back-up instellen** en geef een tijdstip op (standaard 02:00), of:

```powershell
Start-Windows.cmd schedule --at=02:00     # instellen
Start-Windows.cmd schedule                # status
Start-Windows.cmd schedule --off          # uitzetten
```

De tool gebruikt wat het besturingssysteem zelf heeft, zonder beheerdersrechten:

| Systeem | Hoe | Let op |
|---|---|---|
| Windows | Taakplanner, taak "Gripp Back-up (&lt;profiel&gt;)" | Draait als je bent aangemeld; stond de computer uit, dan wordt de back-up ingehaald zodra je weer aanmeldt |
| macOS | launchd (`~/Library/LaunchAgents`) | Draait bij het eerstvolgende moment als de Mac uit of in slaap was |
| Linux | je crontab | Draait alleen als de computer op dat moment aan staat |

Een automatische back-up hervat een onderbroken run van minder dan een dag oud en begint anders opnieuw. De uitvoer staat in `logs/` in de back-upmap (de laatste 30 runs).

## Oude snapshots

Bij elke gepubliceerde back-up schuift de vorige door naar `data/previous/` en daarna naar `data/history/<datum>/`. Standaard blijven 7 oudere snapshots bewaard; stel dat in met `GRIPP_KEEP_SNAPSHOTS` in het profiel. De bijlagen in `archive/` worden door alle snapshots gedeeld en tellen dus niet dubbel. Een oude snapshot bekijken: kopieer hem tijdelijk naar `data/current/` en kies **Viewer opnieuw bouwen**.

## Archief openen

- **Zonder server:** dubbelklik in de back-upmap op `Archief openen.html` (of `viewer/index.html`).
- **Met lokale server:**
  ```powershell
  Start-Windows.cmd serve       # http://localhost:4173
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
├─ viewer/               het archief; de gegevens staan in viewer/data/ (in delen van ± 8 MB)
├─ data/current/         laatste snapshot: ruwe API-JSON (bron van waarheid) + rapport
├─ data/previous/        vorige snapshot
├─ data/history/         oudere snapshots
├─ archive/              bijlagen, factuur-PDF's, documenten en handmatige exports
├─ logs/                 uitvoer van automatische back-ups
└─ laatste-back-up.json  resultaat van de laatste back-up
```

**OneDrive of SharePoint?** Liever niet direct als back-upmap. SharePoint voegt eigenschappen toe aan Word- en Excel-bestanden in een documentbibliotheek, waardoor bijlagen niet meer exact gelijk zijn aan Gripp en bij elke back-up opnieuw worden opgehaald. De setup waarschuwt hiervoor. Gebruik een lokale map of NAS en kopieer die desgewenst apart naar de cloud.

## Documenten van offertes, opdrachten en inkoopopdrachten

Gripp levert bij elk record een document-link mee (`directpdfurl` bij offertes, `viewonlineurl` bij opdrachten en inkoopopdrachten). Daarmee kan de import de PDF's ophalen, zonder het API-quotum te belasten. Dit staat **standaard uit**; de setup vraagt ernaar, en je zet het aan met `GRIPP_DOCUMENTS=aan` in het profiel. Los draaien kan altijd met de opdracht `documents`.

- **Alleen downloaden:** er wordt niets geaccepteerd, ondertekend of verzonden.
- **Let op:** de online weergave is dezelfde pagina die je klant of leverancier ziet. Gripp kan het openen ervan als "bekeken" in de timeline zetten.

## Handmatige exports

Sommige gegevens levert de API niet. Leg exports uit Gripp in `archive/manual/` in de back-upmap en kies daarna **Viewer opnieuw bouwen** in het menu (opdracht `build`).

- `archive/manual/verkoopfacturen/Factuur_<nummer>.pdf` wordt automatisch aan de juiste factuur gekoppeld.
- Aanbevolen: de **XAF-auditfile** per boekjaar (Boekhouding › Belasting & BTW › Auditfile downloaden).

## Meerdere accounts

Maak per account een map in `profiles/`. Elk profiel heeft een eigen token, naam en back-upmap. Zie [profiles/README.md](profiles/README.md).

## Bijwerken

Werk de tool bij met `git pull` of een nieuwe ZIP. Profielen en back-ups blijven daarbij onaangeroerd. Kies daarna **Viewer opnieuw bouwen**, zodat de back-upmap de nieuwe viewer krijgt.

## Veiligheid en privacy

Zie ook [SECURITY.md](SECURITY.md): wat de tool met je token en gegevens doet, met welke hosts hij verbinding maakt, en hoe je een kwetsbaarheid meldt.

- Gebruik een API-sleutel met een rol die alleen mag lezen, en deel `config.env` met niemand.
- Een back-up bevat alle bedrijfs- en persoonsgegevens uit Gripp, ook bijzondere persoonsgegevens van medewerkers (BSN, bankrekening, privéadres). Die worden in de viewer niet bij Gegevens getoond en zijn gemaskeerd bij Brongegevens, maar staan wel in de ruwe bestanden. Bewaar de back-upmap daarom net zo zorgvuldig als Gripp zelf.
- Zet een back-upmap nooit in een git-repository of een openbaar gedeelde map.

## Bekende beperkingen

- Bankmutaties en de rapporten winst & verlies, balans, saldibalans en BTW zitten niet in de API. Gebruik hiervoor de XAF-auditfile.
- De wijzigingsgeschiedenis ("Bekijk geschiedenis"), interacties, publicaties en batches zitten niet in de API.
- Instellingen (identiteiten, BTW-tarieven, condities) zijn alleen als verwijzing aanwezig.

## Grote accounts

De viewer laadt alle gegevens in de browser. Getest met een nep-account van 138.600 records (196 MB, ruim 10× een gemiddeld mkb-account): laden ± 5 seconden, ± 270 MB geheugen, lijsten openen direct en zoeken over alles kost ± 0,3 seconde. Dit werkt ook bij openen via dubbelklik. Zelf testen: `node test/scale.mjs <map> 100`.

## Ontwikkelen

```
Start-Windows.cmd       starter Windows (→ tools/start.ps1)
Start-Mac.command       starter macOS (→ start.sh)
start.sh                starter macOS/Linux
tools/node.lock         vastgezette Node.js-versie + SHA-256 (bijwerken: node tools/update-node.mjs)
cli.mjs                 menu en opdrachten
lib/setup.mjs           setup-wizard
lib/check.mjs           verbindingstest en rechtencheck
lib/prompt.mjs          invoer (ook via een pipe, voor scripts)
lib/schedule.mjs        automatische back-up (Taakplanner, launchd, cron)
importer/import.mjs     import (alleen-lezen, hervatbaar)
importer/documents.mjs  PDF's van offertes, opdrachten, inkoopopdrachten
importer/build.mjs      snapshot → viewer in de back-upmap
lib/config.mjs          profielen en paden
lib/gripp-client.mjs    API-client (weigert schrijfacties)
lib/schema.mjs          entiteiten en verwijzingen
viewer/                 statische viewer (geen externe bronnen); loader.js laadt de data in delen
test/scale.mjs          schaaltest voor de viewer (nep-back-up van N× de testdata)
serve.mjs               optionele lokale webserver (alleen GET)
test/                   tests tegen een nep-API
```

`node cli.mjs test` (of `npm test`) draait de volledige import tegen een nep-API in een tijdelijke map.

Op GitHub draaien bij elke push de tests op Windows, macOS en Linux (Node 24, en Node 18 als laagste versie), plus de startscripts met de portable Node, inclusief een controle dat een vervalste download wordt geweigerd ([.github/workflows/test.yml](.github/workflows/test.yml)).

**Release maken:** zet de nieuwe versie in `package.json` en in [CHANGELOG.md](CHANGELOG.md), commit, en push een tag `v<versie>` (bijvoorbeeld `git tag v0.5.0 && git push --tags`). De release-workflow draait de tests en zet `gripp-backup-<versie>.zip` bij een GitHub-release.

**Node.js bijwerken:** `node tools/update-node.mjs` legt de nieuwste LTS-versie en de controlegetallen vast in `tools/node.lock`.

## Licentie

MIT, zie [LICENSE](LICENSE).
