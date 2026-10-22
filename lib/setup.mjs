// Setup-wizard: maakt (of vervangt) een profiel. Vraagt het API-token, controleert meteen de
// verbinding en de leesrechten per tabel, en schrijft daarna profiles/<profiel>/config.env.
// Zet GRIPP_API_URL om tegen een andere endpoint te testen (bijv. de nep-API).
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { DEFAULT_API_URL, PROFILES_DIR, TOOL_ROOT, isValidProfileName, listProfiles, parseEnvFile, slugify, writeProfile } from './config.mjs';
import { checkAccess, formatCheck } from './check.mjs';
import { ask, askSecret, confirm } from './prompt.mjs';

const INTRO = `
Nieuw Gripp-account instellen
─────────────────────────────
Je hebt een API-sleutel uit Gripp nodig:
  1. Ga in Gripp naar Instellingen › API (API keys).
  2. Maak een API-rol die alleen mag LEZEN, en vink alle onderdelen aan.
  3. Maak een nieuwe sleutel met die rol en kopieer het token.

De tool kan niets wijzigen in Gripp, maar een sleutel die alleen mag lezen is extra zekerheid.
Het token wordt alleen op deze computer opgeslagen, in profiles/<naam>/config.env.
`;

function checkBackupDir(dir) {
  const warnings = [];
  fs.mkdirSync(dir, { recursive: true });
  const probe = path.join(dir, `.schrijftest-${process.pid}`);
  fs.writeFileSync(probe, 'ok');
  fs.rmSync(probe);
  const rel = path.relative(TOOL_ROOT, dir);
  const insideTool = rel === '' || (!rel.startsWith('..') && !path.isAbsolute(rel));
  if (insideTool && !(rel.split(path.sep)[0] === 'backups')) warnings.push('Deze map staat in de toolmap maar niet in backups/. Kies liever een map buiten de tool, zodat gegevens nooit per ongeluk in git komen.');
  if (process.platform === 'win32' && dir.length > 120) warnings.push(`Dit is een lang pad (${dir.length} tekens). Windows staat standaard maximaal 260 tekens per bestand toe; bijlagen met lange namen kunnen dan mislukken. Een kortere map is veiliger.`);
  if (fs.existsSync(path.join(dir, 'data', 'current'))) warnings.push('Deze map bevat al een back-up. Die wordt bij de volgende run bijgewerkt (de vorige versie blijft bewaard in data/previous).');
  return warnings;
}

// Geeft de profielnaam terug, of null als de gebruiker stopt.
export async function runSetup() {
  const url = process.env.GRIPP_API_URL || DEFAULT_API_URL;
  console.log(INTRO);

  // 1. Token + verbinding en rechten
  let token, result;
  for (;;) {
    token = await askSecret('Plak het API-token en druk op Enter:');
    if (!token) { if (await confirm('Geen token ingevuld. Opnieuw proberen?')) continue; return null; }
    console.log('\nVerbinding en leesrechten controleren…');
    try {
      result = await checkAccess({ token, url });
      break;
    } catch (e) {
      console.log(`✖  ${e.message}`);
      if (!(await confirm('Ander token proberen?'))) return null;
    }
  }
  console.log('\n' + formatCheck(result) + '\n');
  if (result.results.some((r) => !r.ok) && !(await confirm('Toch doorgaan? Je kunt de rechten later aanpassen en opnieuw controleren.'))) return null;

  // 2. Naam en profiel
  let accountName = '';
  while (!accountName) accountName = await ask('\nNaam van het bedrijf (komt in het archief):');
  let profile, existing = {};
  for (;;) {
    profile = (await ask('Korte naam voor dit profiel (letters, cijfers, - en _):', slugify(accountName))).toLowerCase();
    if (!isValidProfileName(profile)) { console.log('   Ongeldige naam.'); continue; }
    if (!listProfiles().includes(profile)) break;
    existing = parseEnvFile(path.join(PROFILES_DIR, profile, 'config.env'));
    if (await confirm(`Profiel "${profile}" bestaat al. Vervangen (de back-up zelf blijft staan)?`, false)) break;
  }

  // 3. Back-upmap
  let backupDir;
  for (;;) {
    const def = existing.GRIPP_BACKUP_DIR ? path.resolve(TOOL_ROOT, existing.GRIPP_BACKUP_DIR) : path.join(os.homedir(), 'Gripp-backup', profile);
    backupDir = path.resolve(TOOL_ROOT, await ask('\nIn welke map moet de back-up komen?', def));
    try {
      const warnings = checkBackupDir(backupDir);
      for (const w of warnings) console.log(`⚠  ${w}`);
      if (!warnings.length || (await confirm('Deze map gebruiken?'))) break;
    } catch (e) {
      console.log(`✖  Kan niet schrijven in deze map: ${e.message}`);
    }
  }

  // 4. Opslaan
  console.log(`\nSamenvatting\n  Account:    ${accountName}\n  Profiel:    ${profile}\n  Back-upmap: ${backupDir}\n`);
  if (!(await confirm('Opslaan?'))) return null;
  const file = writeProfile({ profile, token, accountName, backupDir, apiUrl: url });
  console.log(`✔  Opgeslagen in ${path.relative(TOOL_ROOT, file)}`);
  return profile;
}
