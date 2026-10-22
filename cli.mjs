#!/usr/bin/env node
// Eén ingang voor alle onderdelen. De startscripts (Start-Windows.cmd, Start-Mac.command, start.sh) roepen dit aan.
//   node cli.mjs                        menu (zonder profiel: eerst de setup)
//   node cli.mjs setup                  nieuw account instellen (wizard)
//   node cli.mjs check [--profile=x]    verbinding en leesrechten controleren
//   node cli.mjs backup [opties]        back-up maken (opties: zie README, bv. --profile=naam --no-files)
//   node cli.mjs resume [opties]        onderbroken back-up hervatten
//   node cli.mjs open                   archief openen in de browser
//   node cli.mjs build | documents | serve | test
//   node cli.mjs help
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { TOOL_ROOT, listProfiles, loadConfig } from './lib/config.mjs';
import { checkAccess, formatCheck } from './lib/check.mjs';
import { ask, closePrompts, confirm } from './lib/prompt.mjs';
import { runSetup } from './lib/setup.mjs';

const SCRIPTS = { backup: 'importer/import.mjs', build: 'importer/build.mjs', documents: 'importer/documents.mjs', serve: 'serve.mjs', test: 'test/run.mjs' };

const HELP = `Gripp Back-up — onofficiële, alleen-lezende back-up van een Gripp-account

Gebruik:  Start-Windows.cmd <opdracht> [opties]     (macOS/Linux: ./start.sh <opdracht> [opties])

Opdrachten:
  (geen)      menu
  setup       nieuw account instellen (API-token, controle, back-upmap)
  check       verbinding en leesrechten per tabel controleren
  backup      back-up maken
  resume      onderbroken back-up hervatten
  open        archief openen in de browser
  build       viewer opnieuw bouwen (bijv. na handmatige exports)
  documents   alleen de PDF's van offertes, opdrachten en inkoopopdrachten ophalen
  serve       archief via http://localhost:4173 aanbieden
  test        zelftest tegen een nep-API (raakt Gripp nooit)
  help        deze uitleg

Opties:
  --profile=<naam>   kies het profiel (nodig als er meerdere zijn)
  --no-files         (backup) geen bijlagen en PDF's
  --accept-partial   (backup) ook publiceren als sommige tabellen mislukten
  --only=a,b         (backup) alleen deze tabellen, als test; wordt nooit gepubliceerd

Meer uitleg: README.md`;

function run(script, args) {
  const r = spawnSync(process.execPath, [path.join(TOOL_ROOT, script), ...args], { stdio: 'inherit' });
  return r.status ?? 1;
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
    run(SCRIPTS.backup, [`--profile=${profile}`]);
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
  console.log('\nGripp Back-up\n');
  let profile = await chooseProfile();
  while (profile) {
    const cfg = loadConfig({ profile });
    const p = [`--profile=${profile}`];
    const items = [
      ['Back-up maken', () => run(SCRIPTS.backup, p)],
      ['Onderbroken back-up hervatten', () => run(SCRIPTS.backup, ['--resume', ...p])],
      ['Archief openen', () => openArchive(profile)],
      ['Verbinding en rechten controleren', () => runCheck(profile)],
      ['Viewer opnieuw bouwen', () => run(SCRIPTS.build, p)],
      ['Nieuw account instellen', async () => { const np = await runSetup(); if (np) profile = np; return np ? 0 : 1; }],
      ['Zelftest (nep-API, raakt Gripp nooit)', () => run(SCRIPTS.test, [])],
    ];
    console.log(`\nAccount:    ${cfg.accountName} (profiel ${profile})\nBack-upmap: ${cfg.backupDir}\n`);
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
  if (cmd === '') code = process.stdin.isTTY ? await menu() : (console.log(HELP), 0);
  else if (cmd === 'setup') code = (await setupAndOfferBackup()) ? 0 : 1;
  else if (cmd === 'check') code = await runCheck();
  else if (cmd === 'resume') code = run(SCRIPTS.backup, ['--resume', ...rest]);
  else if (cmd === 'open') code = openArchive();
  else if (SCRIPTS[cmd]) code = run(SCRIPTS[cmd], rest);
  else if (['help', '--help', '-h'].includes(cmd)) { console.log(HELP); code = 0; }
  else { console.error(`Onbekende opdracht "${cmd}".\n`); console.log(HELP); code = 1; }
} catch (e) {
  console.error(`✖  ${e.message}`);
  code = 1;
} finally {
  closePrompts();
}
process.exitCode = code;
