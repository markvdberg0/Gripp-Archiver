#!/usr/bin/env node
// Eén ingang voor alle onderdelen. De startscripts (Start-Windows.cmd, Start-Mac.command, start.sh) roepen dit aan.
//   node cli.mjs                        menu (zonder profiel: eerst de setup)
//   node cli.mjs setup                  nieuw account instellen (wizard)
//   node cli.mjs check [--profile=x]    verbinding en leesrechten controleren
//   node cli.mjs backup [opties]        back-up maken (opties: zie README, bv. --profile=naam --no-files)
//   node cli.mjs resume [opties]        onderbroken back-up hervatten
//   node cli.mjs open                   archief openen in de browser
//   node cli.mjs schedule [--at=02:00 | --off]   automatische dagelijkse back-up instellen (zonder opties: status)
//   node cli.mjs build | documents | serve | test
//   node cli.mjs help
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { TOOL_ROOT, listProfiles, loadConfig } from './lib/config.mjs';
import { checkAccess, formatCheck } from './lib/check.mjs';
import { ask, closePrompts, confirm } from './lib/prompt.mjs';
import { runSetup } from './lib/setup.mjs';
import { disableSchedule, enableSchedule, scheduleStatus } from './lib/schedule.mjs';

const VERSION = JSON.parse(fs.readFileSync(path.join(TOOL_ROOT, 'package.json'), 'utf8')).version;
const STATUS_FILE = 'laatste-back-up.json';
const RESULT = { 0: 'gelukt', 2: 'klaar, maar niet gepubliceerd (zie rapport)' };
const profileOf = (args) => args.find((a) => a.startsWith('--profile='))?.slice(10);

const SCRIPTS = { backup: 'importer/import.mjs', build: 'importer/build.mjs', documents: 'importer/documents.mjs', serve: 'serve.mjs', test: 'test/run.mjs' };

const HELP = () => `Gripp Back-up ${VERSION} — onofficiële, alleen-lezende back-up van een Gripp-account

Gebruik:  Start-Windows.cmd <opdracht> [opties]     (macOS/Linux: ./start.sh <opdracht> [opties])

Opdrachten:
  (geen)      menu
  setup       nieuw account instellen (API-token, controle, back-upmap)
  check       verbinding en leesrechten per tabel controleren
  backup      back-up maken
  resume      onderbroken back-up hervatten
  open        archief openen in de browser
  schedule    automatische dagelijkse back-up: --at=02:00 om in te stellen, --off om uit te zetten
  build      viewer opnieuw bouwen (bijv. na handmatige exports)
  documents   alleen de PDF's van offertes, opdrachten en inkoopopdrachten ophalen
  serve       archief via http://localhost:4173 aanbieden
  test        zelftest tegen een nep-API (raakt Gripp nooit)
  help        deze uitleg

Opties:
  --profile=<naam>   kies het profiel (nodig als er meerdere zijn)
  --no-files         (backup) geen bijlagen en PDF's
  --accept-partial   (backup) ook publiceren als sommige tabellen mislukten
  --documents        (backup) ook PDF's van offertes/opdrachten/inkoopopdrachten (of --no-documents)
  --refresh-files    (backup) alle bijlagen opnieuw ophalen
  --only=a,b         (backup) alleen deze tabellen, als test; wordt nooit gepubliceerd

Meer uitleg: README.md`;

function run(script, args) {
  const r = spawnSync(process.execPath, [path.join(TOOL_ROOT, script), ...args], { stdio: 'inherit' });
  return r.status ?? 1;
}

// Back-up draaien en het resultaat vastleggen in <back-upmap>/laatste-back-up.json.
// Met --scheduled (geplande taak) gaat alle uitvoer naar <back-upmap>/logs/.
function runBackup(args) {
  const cfg = loadConfig({ profile: profileOf(args) });
  const scheduled = args.includes('--scheduled');
  const startedAt = new Date();
  let fd = null, logFile = null;
  if (scheduled) {
    const dir = path.join(cfg.backupDir, 'logs');
    fs.mkdirSync(dir, { recursive: true });
    for (const old of fs.readdirSync(dir).filter((f) => f.endsWith('.log')).sort().slice(0, -30)) fs.rmSync(path.join(dir, old), { force: true });
    logFile = path.join(dir, `back-up-${startedAt.toISOString().slice(0, 19).replace(/[:T]/g, '-')}.log`);
    fd = fs.openSync(logFile, 'a');
  }
  const r = spawnSync(process.execPath, [path.join(TOOL_ROOT, SCRIPTS.backup), ...args.filter((a) => a !== '--scheduled')], { stdio: fd == null ? 'inherit' : ['ignore', fd, fd] });
  if (fd != null) fs.closeSync(fd);
  const code = r.status ?? 1;
  if (!args.some((a) => a.startsWith('--only'))) {
    fs.mkdirSync(cfg.backupDir, { recursive: true });
    const status = { startedAt: startedAt.toISOString(), finishedAt: new Date().toISOString(), exitCode: code, result: RESULT[code] ?? 'mislukt', scheduled, log: logFile };
    fs.writeFileSync(path.join(cfg.backupDir, STATUS_FILE), JSON.stringify(status, null, 1));
  }
  return code;
}

function lastBackupLine(cfg) {
  try {
    const s = JSON.parse(fs.readFileSync(path.join(cfg.backupDir, STATUS_FILE), 'utf8'));
    const when = new Date(s.finishedAt).toLocaleString('nl-NL', { dateStyle: 'short', timeStyle: 'short' });
    return `${when} — ${s.result}${s.scheduled ? ' (automatisch)' : ''}`;
  } catch { return 'nog geen'; }
}

function scheduleLine(profile) {
  try {
    const s = scheduleStatus(profile);
    return s ? `elke dag om ${s.time}${s.next ? ` (volgende: ${s.next})` : ''}` : 'uit';
  } catch { return 'onbekend'; }
}

const SCHEDULE_NOTE = {
  win32: 'De taak draait in de Windows Taakplanner als jij bent aangemeld. Stond de computer uit, dan wordt de back-up ingehaald zodra je weer aanmeldt.',
  darwin: 'De taak draait via launchd als jij bent aangemeld. Stond de Mac uit of in slaap, dan draait hij bij het eerstvolgende moment.',
  linux: 'De taak staat in je crontab en draait alleen als de computer op dat moment aan staat.',
};

async function configureSchedule(profile) {
  const s = scheduleStatus(profile);
  console.log(s ? `Nu ingepland: elke dag om ${s.time}.` : 'Er is nog geen automatische back-up ingepland.');
  console.log(SCHEDULE_NOTE[process.platform] ?? SCHEDULE_NOTE.linux);
  const a = await ask("\nTijdstip (UU:MM), 'uit' om uit te zetten, of Enter om niets te wijzigen:", s ? '' : '02:00');
  if (!a) return 0;
  if (/^(uit|off|nee)$/i.test(a)) { disableSchedule(profile); console.log('✔  Automatische back-up uitgezet.'); return 0; }
  const t = enableSchedule(profile, a);
  console.log(`✔  Elke dag om ${t.text} wordt automatisch een back-up gemaakt. Het resultaat zie je in dit menu en in de map logs/ van de back-up.`);
  return 0;
}

function scheduleCommand(args) {
  const profile = loadConfig({ profile: profileOf(args) }).profile;
  if (!profile) throw new Error('Inplannen kan alleen voor een profiel (profiles/<naam>/config.env).');
  const at = args.find((a) => a.startsWith('--at='))?.slice(5);
  if (args.includes('--off')) { disableSchedule(profile); console.log('✔  Automatische back-up uitgezet.'); return 0; }
  if (at) {
    const dry = args.includes('--dry-run');
    const t = enableSchedule(profile, at, { dryRun: dry });
    console.log(dry ? `✔  Controle geslaagd (niets ingepland): elke dag om ${t.text}.` : `✔  Elke dag om ${t.text} automatisch een back-up van profiel ${profile}.`);
    return 0;
  }
  console.log(`Automatische back-up voor ${profile}: ${scheduleLine(profile)}`);
  return 0;
}

function openArchive(profile) {
  const cfg = loadConfig({ profile });
  const file = path.join(cfg.backupDir, 'Archief openen.html');
  if (!fs.existsSync(file)) { console.error(`Nog geen archief in ${cfg.backupDir}. Maak eerst een back-up.`); return 1; }
  const [cmd, args] = process.platform === 'win32' ? ['explorer.exe', [file]] : process.platform === 'darwin' ? ['open', [file]] : ['xdg-open', [file]];
  spawn(cmd, args, { detached: true, stdio: 'ignore' }).on('error', () => console.error(`Openen lukte niet; open zelf: ${file}`)).unref();
  console.log(`Archief geopend: ${file}`);
  return 0;
}

// 0 = alles leesbaar, 2 = verbinding goed maar niet alle tabellen leesbaar
async function runCheck(profile) {
  const cfg = loadConfig({ profile });
  if (!cfg.token) throw new Error(`Profiel "${cfg.profile}" heeft geen GRIPP_API_TOKEN. Draai de setup opnieuw.`);
  console.log(`Verbinding en leesrechten controleren voor ${cfg.accountName}…\n`);
  const result = await checkAccess({ token: cfg.token, url: cfg.apiUrl });
  console.log(formatCheck(result));
  return result.results.every((r) => r.ok) ? 0 : 2;
}

// Setup draaien en aanbieden om meteen een eerste back-up te maken.
async function setupAndOfferBackup() {
  const profile = await runSetup();
  if (profile && (await confirm('\nNu de eerste back-up maken? Dit kan enkele uren duren; onderbreken en later hervatten mag.', false))) {
    runBackup([`--profile=${profile}`]);
  }
  return profile;
}

async function chooseProfile() {
  const profiles = listProfiles();
  if (!profiles.length) {
    console.log('Er is nog geen Gripp-account ingesteld.');
    return setupAndOfferBackup();
  }
  if (profiles.length === 1) return profiles[0];
  profiles.forEach((p, i) => console.log(`  ${i + 1}. ${loadConfig({ profile: p }).accountName} (${p})`));
  console.log(`  ${profiles.length + 1}. Nieuw account instellen`);
  console.log('  0. Afsluiten');
  const i = Number(await ask('\nWelk account?')) - 1;
  if (i === profiles.length) return setupAndOfferBackup();
  return profiles[i] ?? null;
}

async function menu() {
  console.log(`\nGripp Back-up ${VERSION}\n`);
  let profile = await chooseProfile();
  while (profile) {
    const cfg = loadConfig({ profile });
    const p = [`--profile=${profile}`];
    const items = [
      ['Back-up maken', () => runBackup(p)],
      ['Onderbroken back-up hervatten', () => runBackup(['--resume', ...p])],
      ['Archief openen', () => openArchive(profile)],
      ['Verbinding en rechten controleren', () => runCheck(profile)],
      ['Automatische back-up instellen', () => configureSchedule(profile)],
      ['Viewer opnieuw bouwen', () => run(SCRIPTS.build, p)],
      ['Nieuw account instellen', async () => { const np = await runSetup(); if (np) profile = np; return np ? 0 : 1; }],
      ['Zelftest (nep-API, raakt Gripp nooit)', () => run(SCRIPTS.test, [])],
    ];
    console.log(`\nAccount:          ${cfg.accountName} (profiel ${profile})\nBack-upmap:       ${cfg.backupDir}\nLaatste back-up:  ${lastBackupLine(cfg)}\nAutomatisch:      ${scheduleLine(profile)}\n`);
    items.forEach(([label], i) => console.log(`  ${i + 1}. ${label}`));
    console.log('  0. Afsluiten');
    const item = items[Number(await ask('\nKeuze:')) - 1];
    if (!item) return 0;
    console.log('');
    let code;
    try { code = await item[1](); } catch (e) { console.error(`✖  ${e.message}`); code = 1; }
    console.log(code ? '\n✖  Afgerond met fouten of waarschuwingen (zie hierboven).' : '\n✔  Klaar.');
  }
  return 0;
}

const [cmd = '', ...rest] = process.argv.slice(2);
let code;
try {
  if (cmd === '') code = process.stdin.isTTY ? await menu() : (console.log(HELP()), 0);
  else if (cmd === 'setup') code = (await setupAndOfferBackup()) ? 0 : 1;
  else if (cmd === 'check') code = await runCheck();
  else if (cmd === 'backup') code = runBackup(rest);
  else if (cmd === 'resume') code = runBackup(['--resume', ...rest]);
  else if (cmd === 'schedule') code = scheduleCommand(rest);
  else if (cmd === 'open') code = openArchive();
  else if (SCRIPTS[cmd]) code = run(SCRIPTS[cmd], rest);
  else if (['help', '--help', '-h'].includes(cmd)) { console.log(HELP()); code = 0; }
  else { console.error(`Onbekende opdracht "${cmd}".\n`); console.log(HELP()); code = 1; }
} catch (e) {
  console.error(`✖  ${e.message}`);
  code = 1;
} finally {
  closePrompts();
}
process.exitCode = code;
