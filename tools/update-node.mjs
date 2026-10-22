#!/usr/bin/env node
// Legt de Node.js-versie en SHA-256-controlegetallen vast die de startscripts gebruiken (tools/node.lock).
// Alleen voor beheerders van deze repo; gebruikers hebben dit niet nodig.
//   node tools/update-node.mjs            nieuwste LTS-versie
//   node tools/update-node.mjs 24.22.1    een bepaalde versie
// Controleer de wijziging in tools/node.lock voordat je hem commit.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const LOCK = path.join(path.dirname(fileURLToPath(import.meta.url)), 'node.lock');
const PLATFORMS = ['win-x64.zip', 'win-arm64.zip', 'darwin-x64.tar.gz', 'darwin-arm64.tar.gz', 'linux-x64.tar.gz', 'linux-arm64.tar.gz'];

async function text(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error(`${url}: HTTP ${res.status}`);
  return res.text();
}

let version = process.argv[2]?.replace(/^v/, '');
if (!version) {
  const index = JSON.parse(await text('https://nodejs.org/dist/index.json'));
  version = index.find((r) => r.lts).version.slice(1);
}
if (!/^\d+\.\d+\.\d+$/.test(version)) throw new Error(`Ongeldige versie: ${version}`);

const sums = await text(`https://nodejs.org/dist/v${version}/SHASUMS256.txt`);
const lines = PLATFORMS.map((p) => {
  const file = `node-v${version}-${p}`;
  const line = sums.split('\n').find((l) => l.trim().endsWith(`  ${file}`));
  if (!line || !/^[0-9a-f]{64}  /.test(line)) throw new Error(`Geen controlegetal voor ${file}`);
  return line.trim();
});

fs.writeFileSync(LOCK, [
  '# Node.js die de startscripts gebruiken als er geen geschikte Node op de computer staat.',
  `# Bron: https://nodejs.org/dist/v${version}/SHASUMS256.txt`,
  '# Bijwerken: node tools/update-node.mjs [versie]',
  `version=${version}`,
  ...lines,
  '',
].join('\n'));
console.log(`✔  tools/node.lock → Node ${version}`);
