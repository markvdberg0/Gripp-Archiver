#!/usr/bin/env node
// Zet de gepubliceerde snapshot (data/current) om naar viewer/data/data.js en zet de viewer
// in de back-upmap, zodat die map op zichzelf staat: viewer/index.html werkt zonder server (dubbelklik, file://).
//   npm run build                     voor het (enige) profiel
//   npm run build -- --profile=naam   voor een bepaald profiel
//   npm run build -- --preview        vanuit de lopende, nog niet gepubliceerde import
import fs from 'node:fs';
import fsp from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { REFS } from '../lib/schema.mjs';
import { TOOL_ROOT, loadConfig } from '../lib/config.mjs';

const VIEWER_FILES = ['index.html', 'loader.js', 'app.js', 'style.css'];
const PART_BYTES = 8 * 1024 * 1024;   // viewer-data in delen van ± 8 MB: één groot script kan een browser niet aan

// JSON in een <script>: escapen van '</' voorkomt dat HTML in records de script-tag sluit.
const safeJson = (v) => JSON.stringify(v).replace(/<\//g, '<\\/').replace(new RegExp('\\u2028', 'g'), '\\u2028').replace(new RegExp('\\u2029', 'g'), '\\u2029');

// Records per tabel in delen van hooguit PART_BYTES → [{ file, entity, rows }]
function splitParts(entities) {
  const parts = [];
  for (const [entity, rows] of Object.entries(entities)) {
    let buf = [], size = 0;
    const flush = () => { if (buf.length) parts.push({ entity, json: `[${buf.join(',')}]`, rows: buf.length }); buf = []; size = 0; };
    for (const r of rows) {
      const s = safeJson(r);
      if (size + s.length > PART_BYTES) flush();
      buf.push(s); size += s.length + 1;
    }
    flush();
  }
  return parts.map((p, i) => ({ ...p, file: `part-${String(i + 1).padStart(4, '0')}.js` }));
}

function listFiles(dir, base = dir) {
  if (!fs.existsSync(dir)) return [];
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((d) => {
    const p = path.join(dir, d.name);
    return d.isDirectory() ? listFiles(p, base) : [{ path: path.relative(base, p).split(path.sep).join('/'), size: fs.statSync(p).size }];
  });
}

export async function build({ log = console.log, config, dataDir, preview = false } = {}) {
  const cfg = config ?? loadConfig();
  dataDir ??= path.join(cfg.dataDir, 'current');
  if (!fs.existsSync(dataDir)) throw new Error(`Geen snapshot in ${dataDir}. Maak eerst een back-up.`);
  const rawDir = path.join(dataDir, 'raw');
  const entities = {};
  for (const f of fs.readdirSync(rawDir).filter((f) => f.endsWith('.json') && !f.includes('.partial'))) {
    entities[f.replace(/\.json$/, '')] = JSON.parse(fs.readFileSync(path.join(rawDir, f), 'utf8'));
  }
  const state = JSON.parse(fs.readFileSync(path.join(dataDir, 'state.json'), 'utf8'));
  const report = fs.existsSync(path.join(dataDir, 'report.json')) ? JSON.parse(fs.readFileSync(path.join(dataDir, 'report.json'), 'utf8')) : null;
  const files = Object.fromEntries(Object.entries(state.files ?? {}).filter(([, f]) => f.ok).map(([id, f]) => [id, { name: f.name, path: 'archive/' + f.path, size: f.size }]));
  const invoicePdfs = Object.fromEntries(Object.entries(state.invoicePdfs ?? {}).filter(([, f]) => f.ok).map(([id, f]) => [id, 'archive/' + f.path]));
  const docsState = fs.existsSync(path.join(dataDir, 'documents.json')) ? JSON.parse(fs.readFileSync(path.join(dataDir, 'documents.json'), 'utf8')) : {};
  const docs = Object.fromEntries(Object.entries(docsState).map(([ent, m]) => [ent, Object.fromEntries(Object.entries(m).filter(([, d]) => d.ok).map(([id, d]) => [id, 'archive/' + d.path]))]));
  const manual = listFiles(path.join(cfg.archiveDir, 'manual')).map((f) => ({ ...f, path: 'archive/manual/' + f.path }));
  // Handmatig geëxporteerde factuur-PDF's (Gripp: Facturen › selecteer › "Downloaden voor archief")
  // heten Factuur_<nummer>.pdf → koppel op factuurnummer als directpdfurl niets opleverde.
  const byNumber = new Map((entities.invoice ?? []).filter((iv) => iv.number != null).map((iv) => [String(iv.number), iv.id]));
  let matched = 0;
  for (const f of manual) {
    const m = f.path.match(/\/Factuur_(\d+)\.pdf$/i);
    const id = m && byNumber.get(m[1]);
    if (id != null && !invoicePdfs[id]) { invoicePdfs[id] = f.path; matched++; }
  }
  if (matched) log(`   ${matched} handmatige factuur-PDF's gekoppeld op factuurnummer`);
  // De lijst met handmatige exports in de viewer toont de losse factuur-PDF's niet (die hangen al aan de factuur).
  const manualList = manual.filter((f) => !/\/verkoopfacturen\/[^/]+\.pdf$/i.test(f.path));

  const parts = splitParts(entities);
  const payload = {
    meta: { batch: state.batch, accountName: cfg.accountName, importedAt: state.finishedAt ?? state.startedAt, status: preview ? 'preview' : state.status ?? 'unknown', builtAt: new Date().toISOString() },
    report, entities: {}, files, invoicePdfs, docs, manual: manualList, refs: REFS,
    parts: parts.map((p) => ({ file: p.file, entity: p.entity, rows: p.rows })),
  };

  // Viewer-code meenemen naar de back-upmap (altijd de versie van deze tool).
  await fsp.mkdir(path.join(cfg.viewerDir, 'data'), { recursive: true });
  for (const f of VIEWER_FILES) await fsp.copyFile(path.join(TOOL_ROOT, 'viewer', f), path.join(cfg.viewerDir, f));
  await fsp.writeFile(path.join(cfg.backupDir, 'Archief openen.html'),
    '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0; url=viewer/index.html"><title>Gripp-archief</title><a href="viewer/index.html">Archief openen</a>\n');

  const dataDirOut = path.join(cfg.viewerDir, 'data');
  for (const f of fs.readdirSync(dataDirOut).filter((f) => /^part-\d+\.js$/.test(f))) await fsp.rm(path.join(dataDirOut, f));
  let bytes = 0;
  for (const p of parts) {
    const js = `window.GRIPP_DATA.entities[${JSON.stringify(p.entity)}]=(window.GRIPP_DATA.entities[${JSON.stringify(p.entity)}]||[]).concat(${p.json});\n`;
    await fsp.writeFile(path.join(dataDirOut, p.file), js);
    bytes += Buffer.byteLength(js);
  }
  const out = path.join(dataDirOut, 'data.js');
  await fsp.writeFile(out, `window.GRIPP_DATA=${safeJson(payload)};\n`);
  bytes += fs.statSync(out).size;
  const n = Object.values(entities).reduce((a, r) => a + r.length, 0);
  log(`✔  Viewer-data gebouwd: ${n} records, ${Object.keys(files).length} bijlagen → ${dataDirOut} (${parts.length} delen, ${(bytes / 1048576).toFixed(1)} MB)`);
  log(`   Openen: ${path.join(cfg.backupDir, 'Archief openen.html')}`);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    const config = loadConfig();
    let dataDir;
    if (process.argv.includes('--preview')) {
      const st = fs.existsSync(config.dataDir) ? fs.readdirSync(config.dataDir).filter((d) => d.startsWith('staging-')).sort().pop() : null;
      if (!st) throw new Error('Geen lopende import (data/staging-*) gevonden.');
      dataDir = path.join(config.dataDir, st);
      console.log(`👁  Voorbeeld vanuit ${st} (nog niet gepubliceerd)`);
    }
    await build({ config, dataDir, preview: !!dataDir });
  } catch (e) { console.error('✖ ', e.message); process.exit(1); }
}
