#!/usr/bin/env node
// Eén ingang voor alle onderdelen. De startscripts (Start-Windows.cmd, Start-Mac.command, start.sh) roepen dit aan.
//   node cli.mjs                        menu
//   node cli.mjs backup [opties]        back-up maken (opties: zie README, bv. --profile=naam --no-files)
//   node cli.mjs resume [opties]        onderbroken back-up hervatten
//   node cli.mjs open                   archief openen in de browser
//   node cli.mjs build | documents | serve | test
//   node cli.mjs help
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import readline from 'node:readline/promises';
import { TOOL_ROOT, listProfiles, loadConfig } from './lib/config.mjs';

const SCRIPTS = { backup: 'importer/import.mjs', build: 'importer/build.mjs', documents: 'importer/documents.mjs', serve: 'serve.mjs', test: 'test/run.mjs' };

const HELP = `Gripp Back-up — onofficiële, alleen-lezende back-up van een Gripp-account

Gebruik:  Start-Windows.cmd <opdracht> [opties]     (macOS/Linux: ./start.sh <opdracht> [opties])

Opdrachten:
  (geen)      menu
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

Meer uitleg: README.md`;

const NO_PROFILE = `Er is nog geen profiel. Maak er een aan:
  1. Maak een map in profiles/, bijvoorbeeld profiles/mijnbedrijf
  2. Kopieer config.env.example naar profiles/mijnbedrijf/config.env
  3. Vul daarin GRIPP_API_TOKEN en GRIPP_ACCOUNT_NAME in
Zie README.md voor de stappen.`;

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

async function menu() {
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  const ask = async (q) => (await rl.question(q)).trim();
  try {
    console.log('\nGripp Back-up\n');
    const profiles = listProfiles();
    if (!profiles.length) { console.log(NO_PROFILE); await ask('\nDruk op Enter om af te sluiten.'); return 0; }
    let profile = profiles[0];
    if (profiles.length > 1) {
      profiles.forEach((p, i) => console.log(`  ${i + 1}. ${p}`));
      const i = Number(await ask('\nWelk profiel? ')) - 1;
      if (!profiles[i]) return 0;
      profile = profiles[i];
    }
    const cfg = loadConfig({ profile });
    const p = [`--profile=${profile}`];
    const items = [
      ['Back-up maken', () => run(SCRIPTS.backup, p)],
      ['Onderbroken back-up hervatten', () => run(SCRIPTS.backup, ['--resume', ...p])],
      ['Archief openen', () => openArchive(profile)],
      ['Viewer opnieuw bouwen', () => run(SCRIPTS.build, p)],
      ['Zelftest (nep-API, raakt Gripp nooit)', () => run(SCRIPTS.test, [])],
    ];
    for (;;) {
      console.log(`\nAccount:    ${cfg.accountName} (profiel ${profile})\nBack-upmap: ${cfg.backupDir}\n`);
      items.forEach(([label], i) => console.log(`  ${i + 1}. ${label}`));
      console.log('  0. Afsluiten');
      const item = items[Number(await ask('\nKeuze: ')) - 1];
      if (!item) return 0;
      console.log('');
      const code = item[1]();
      console.log(code ? '\n✖  Afgerond met fouten (zie hierboven).' : '\n✔  Klaar.');
    }
  } finally {
    rl.close();
  }
}

const [cmd = '', ...rest] = process.argv.slice(2);
let code;
try {
  if (cmd === '') code = process.stdin.isTTY ? await menu() : (console.log(HELP), 0);
  else if (cmd === 'resume') code = run(SCRIPTS.backup, ['--resume', ...rest]);
  else if (cmd === 'open') code = openArchive();
  else if (SCRIPTS[cmd]) code = run(SCRIPTS[cmd], rest);
  else if (['help', '--help', '-h'].includes(cmd)) { console.log(HELP); code = 0; }
  else { console.error(`Onbekende opdracht "${cmd}".\n`); console.log(HELP); code = 1; }
} catch (e) {
  console.error(`✖  ${e.message}`);
  code = 1;
}
process.exitCode = code;
