# Wijzigingen

## 0.5.0 — eerste openbare versie

- **Geen installatie nodig:** startscripts voor Windows, macOS en Linux halen zo nodig een portable Node.js op, gecontroleerd met een vastgelegd controlegetal.
- **Profielen:** meerdere Gripp-accounts naast elkaar, elk met een eigen back-upmap buiten de tool.
- **Setup-wizard** met verbindingstest en een rechtencheck per tabel. Ontbrekende rechten worden genoemd met de naam die je in Gripp bij de API-rol aanvinkt.
- **Herhaalbare back-ups:** bijlagen en definitieve factuur-PDF's worden hergebruikt; oude snapshots blijven bewaard (`GRIPP_KEEP_SNAPSHOTS`).
- **Automatische dagelijkse back-up** via Taakplanner (Windows), launchd (macOS) of cron (Linux), met logbestanden en een statusregel in het menu.
- **Documentenstap** (PDF's van offertes, opdrachten en inkoopopdrachten) is een keuze per profiel, en staat standaard uit.
- **Grote accounts:** viewer-data in delen met een laadscherm, en een schatting van de duur tijdens de import.
- **Veiliger:**
  - een import met `--only` vervangt nooit de echte back-up;
  - doorverwijzingen (zoals bij een Gripp-storing) worden niet gevolgd en geven een duidelijke melding;
  - de setup waarschuwt voor OneDrive- en SharePoint-mappen.
