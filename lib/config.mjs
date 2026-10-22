// Configuratie per Gripp-account ("profiel"). Code en data zijn gescheiden:
//   profiles/<profiel>/config.env   instellingen + API-token (staat nooit in git)
//   <GRIPP_BACKUP_DIR>/             de back-up zelf: data/, archive/, viewer/
//
// config.env (zie config.env.example):
//   GRIPP_API_TOKEN=...                 verplicht voor import
//   GRIPP_ACCOUNT_NAME=Mijn Bedrijf     naam in de viewer
//   GRIPP_BACKUP_DIR=D:\Back-ups\Gripp  optioneel; standaard backups/<profiel>
//   GRIPP_API_URL=...                   optioneel; standaard de officiële endpoint
//
// Profielkeuze: --profile=<naam>, anders GRIPP_PROFILE, anders het enige profiel dat er is.
// Omgevingsvariabelen gaan vóór config.env.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const TOOL_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
export const PROFILES_DIR = path.join(TOOL_ROOT, 'profiles');
export const DEFAULT_API_URL = 'https://api.gripp.com/public/api3.php';
const PROFILE_NAME = /^[a-z0-9][a-z0-9_-]{0,63}$/i;

export function parseEnvFile(file) {
  const out = {};
  if (!fs.existsSync(file)) return out;
  for (const line of fs.readFileSync(file, 'utf8').replace(/^\uFEFF/, '').split(/\r?\n/)) {
    const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);
    if (m) out[m[1]] = m[2].replace(/^["']|["']$/g, '');
  }
  return out;
}

export function listProfiles() {
  if (!fs.existsSync(PROFILES_DIR)) return [];
  return fs.readdirSync(PROFILES_DIR, { withFileTypes: true })
    .filter((d) => d.isDirectory() && PROFILE_NAME.test(d.name) && fs.existsSync(path.join(PROFILES_DIR, d.name, 'config.env')))
    .map((d) => d.name).sort();
}

function chooseProfile(explicit) {
  const name = explicit ?? process.argv.find((a) => a.startsWith('--profile='))?.slice(10) ?? process.env.GRIPP_PROFILE;
  const all = listProfiles();
  if (name) {
    if (!PROFILE_NAME.test(name)) throw new Error(`Ongeldige profielnaam "${name}" (alleen letters, cijfers, - en _).`);
    if (!all.includes(name)) throw new Error(`Profiel "${name}" niet gevonden: profiles/${name}/config.env bestaat niet.${all.length ? ` Beschikbaar: ${all.join(', ')}.` : ''}`);
    return name;
  }
  if (all.length === 1) return all[0];
  if (all.length > 1) throw new Error(`Meerdere profielen gevonden (${all.join(', ')}). Kies er een met --profile=<naam>.`);
  return null;
}

// Zet ruwe instellingen om naar een volledige configuratie met alle paden.
export function resolveConfig({ profile = null, token, accountName, apiUrl, backupDir }) {
  if (!backupDir) {
    if (!profile) throw new Error('Geen profiel gevonden. Maak profiles/<naam>/config.env aan (zie config.env.example en README).');
    backupDir = path.join('backups', profile);
  }
  const dir = path.resolve(TOOL_ROOT, backupDir);
  return {
    profile,
    token: token || null,
    accountName: accountName || profile || 'Gripp',
    apiUrl: apiUrl || DEFAULT_API_URL,
    backupDir: dir,
    dataDir: path.join(dir, 'data'),
    archiveDir: path.join(dir, 'archive'),
    viewerDir: path.join(dir, 'viewer'),
  };
}

export function loadConfig({ profile } = {}) {
  const name = chooseProfile(profile);
  const file = name ? parseEnvFile(path.join(PROFILES_DIR, name, 'config.env')) : {};
  const get = (k) => process.env[k] || file[k];
  return resolveConfig({
    profile: name,
    token: get('GRIPP_API_TOKEN'),
    accountName: get('GRIPP_ACCOUNT_NAME'),
    apiUrl: get('GRIPP_API_URL'),
    backupDir: get('GRIPP_BACKUP_DIR'),
  });
}

// Hint voor foutmeldingen: hoe herhaal je dit commando voor hetzelfde profiel.
export const profileArg = (cfg) => (cfg?.profile && listProfiles().length > 1 ? ` --profile=${cfg.profile}` : '');
