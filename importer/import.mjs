#!/usr/bin/env node
// Eenmalige volledige, uitsluitend-lezende import van Gripp naar lokale snapshot.
//
//   npm run import                 volledige import (nieuwe staging-snapshot)
//   npm run import -- --resume     verder met de laatste onderbroken import
//   npm run import -- --no-files   geen bijlagen downloaden
//   npm run import -- --accept-partial   ook publiceren als sommige entiteiten faalden
//   npm run import -- --only=company,offer   alleen deze entiteiten (test; wordt nooit gepubliceerd)
//   npm run import -- --profile=naam    kies het profiel (nodig als er meerdere zijn)
//   npm run import -- --auto            hervat een onderbroken import van < 1 dag, anders nieuw (geplande runs)
//   npm run import -- --documents       ook PDF's van offertes/opdrachten/inkoopopdrachten (of --no-documents;
//                                       standaard volgens GRIPP_DOCUMENTS in het profiel)
//   npm run import -- --refresh-files   alle bijlagen opnieuw ophalen, ook als ze al in het archief staan
//
// Structuur, binnen de back-upmap van het profiel (zie lib/config.mjs):
//   data/staging-<batch>/raw/<entiteit>.json   ruwe API-records (bron van waarheid)
//   data/staging-<batch>/state.json            checkpoint
//   data/staging-<batch>/report.{json,md}      volledigheidsrapport
//   data/current/                              gepubliceerde snapshot (alleen na geslaagde validatie)
//   data/previous/                             vorige snapshot
//   data/history/<batch>/                      oudere snapshots (aantal: GRIPP_KEEP_SNAPSHOTS)
//   archive/index.json                         wat er al in archive/ staat (bijlagen worden hergebruikt)
//   archive/files/<fileId>/<naam>              bijlagen (file.getContent)
//   archive/invoices/<nummer>_<id>.pdf         factuur-PDF's (directpdfurl)
import fs from 'node:fs';
import fsp from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { GrippClient, parseGetResult } from '../lib/gripp-client.mjs';
import { loadConfig, profileArg } from '../lib/config.mjs';
import { ENTITIES, REFS, FILE_FIELDS } from '../lib/schema.mjs';
import { build } from './build.mjs';
import { fetchDocuments } from './documents.mjs';

const PAGE = 250;          // maximum volgens Gripp
const BATCH = 8;           // RPC-calls per HTTP-request

const args = new Set(process.argv.slice(2).filter((a) => !a.includes('=')));
const kv = Object.fromEntries(process.argv.slice(2).filter((a) => a.includes('=')).map((a) => a.replace(/^--/, '').split('=')));

// ---------- helpers ----------
const readJson = (f, d) => (fs.existsSync(f) ? JSON.parse(fs.readFileSync(f, 'utf8')) : d);
const writeJson = async (f, v) => { await fsp.mkdir(path.dirname(f), { recursive: true }); const tmp = f + '.tmp'; await fsp.writeFile(tmp, JSON.stringify(v)); await fsp.rename(tmp, f); };
const refId = (v) => (v && typeof v === 'object' ? v.id ?? null : v ?? null);
const asArray = (v) => (v == null ? [] : Array.isArray(v) ? v : [v]);
const safeName = (s) => String(s || 'bestand').replace(/[<>:"/\\|?*\x00-\x1f]/g, '_').slice(0, 150);
const sha256 = (buf) => crypto.createHash('sha256').update(buf).digest('hex');
const ts = () => new Date().toISOString();

let logFile = null;
let activeConfig = null;
function log(...a) {
  const line = a.join(' ');
  console.log(line);
  if (logFile) fs.appendFileSync(logFile, `[${ts()}] ${line}\n`);
}

// ---------- main ----------
export async function runImport(opts = {}) {
  const cfg = activeConfig = opts.config ?? loadConfig();
  const DATA = cfg.dataDir;
  const ARCHIVE = cfg.archiveDir;
  const token = opts.token ?? cfg.token;
  const url = opts.url ?? cfg.apiUrl;
  const client = new GrippClient({ token, url, log });
  let resume = opts.resume ?? args.has('--resume');
  const withFiles = !(opts.noFiles ?? args.has('--no-files'));
  const refreshFiles = opts.refreshFiles ?? args.has('--refresh-files');
  const withDocuments = opts.documents ?? (args.has('--documents') ? true : args.has('--no-documents') ? false : cfg.documents);
  const acceptPartial = opts.acceptPartial ?? args.has('--accept-partial');
  const only = (opts.only ?? kv.only)?.split(',').map((s) => s.trim()).filter(Boolean);
  if (only) {
    const unknown = only.filter((n) => !ENTITIES.some((e) => e.name === n));
    if (!only.length || unknown.length) throw new Error(`--only: onbekende of lege tabelnaam (${unknown.join(', ') || 'leeg'}). Geldig: ${ENTITIES.map((e) => e.name).join(', ')}`);
  }

  await fsp.mkdir(DATA, { recursive: true });
  // --auto (geplande runs): hervat een onderbroken import van minder dan een dag oud, anders begin opnieuw.
  if (!resume && !only && (opts.auto ?? args.has('--auto'))) {
    const st = fs.readdirSync(DATA).filter((d) => d.startsWith('staging-')).sort().pop();
    const s = st && readJson(path.join(DATA, st, 'state.json'), null);
    resume = !!(s && !s.finishedAt && Date.now() - Date.parse(s.startedAt) < 24 * 3600_000);
  }
  let stagingDir;
  if (resume) {
    const st = fs.readdirSync(DATA).filter((d) => d.startsWith('staging-')).sort().pop();
    if (!st) throw new Error('Geen onderbroken import gevonden om te hervatten.');
    stagingDir = path.join(DATA, st);
  } else {
    // --only krijgt een eigen naam, zodat --resume en build --preview zo'n testrun niet oppakken.
    stagingDir = path.join(DATA, `${only ? 'only' : 'staging'}-${new Date().toISOString().replace(/[:.]/g, '-')}`);
    if (!only) await pruneStaging(DATA);
  }
  await fsp.mkdir(path.join(stagingDir, 'raw'), { recursive: true });
  logFile = path.join(stagingDir, 'import.log');
  const statePath = path.join(stagingDir, 'state.json');
  const state = readJson(statePath, { batch: path.basename(stagingDir), startedAt: ts(), entities: {}, files: {}, invoicePdfs: {} });
  const saveState = () => writeJson(statePath, state);
  log(`▶  Import ${state.batch} ${resume ? '(hervat)' : ''} → ${url.replace(/\/public.*/, '/…')}`);
  log(`   Account: ${cfg.accountName} · back-upmap: ${cfg.backupDir}`);

  // ---- 1. entiteiten ----
  const list = ENTITIES.filter((e) => !only || only.includes(e.name));
  const buffers = {};
  for (const e of list) {
    const s = (state.entities[e.name] ??= { done: false, next: 0, rows: 0, apiCount: null, error: null, ordered: e.order !== false });
    if (s.done && s.error) { // bij hervatten: mislukte tabellen opnieuw proberen (bv. na aanpassen API-rol)
      log(`↻  ${e.name}: opnieuw proberen (vorige keer: ${s.error.slice(0, 60)})`);
      Object.assign(s, { done: false, next: 0, rows: 0, error: null, ordered: e.order !== false });
      await fsp.rm(path.join(stagingDir, 'raw', `${e.name}.partial.json`), { force: true });
    }
    if (s.done) continue;
    const partFile = path.join(stagingDir, 'raw', `${e.name}.partial.json`);
    buffers[e.name] = readJson(partFile, []);
    s.next = buffers[e.name].length; // hervat precies waar we waren
  }
  let queue = list.filter((e) => !state.entities[e.name].done).map((e) => e.name);
  let estimated = false;
  const knownFiles = withFiles && !refreshFiles ? Object.keys(loadArchiveIndex(ARCHIVE, DATA).files).length : 0;

  while (queue.length) {
    // Zodra van elke tabel het aantal records bekend is: schatting van wat er nog komt.
    if (!estimated && list.every((e) => state.entities[e.name].done || state.entities[e.name].apiCount != null)) {
      estimated = true;
      const pages = list.reduce((a, e) => { const s = state.entities[e.name]; return a + (s.done ? 0 : Math.ceil(Math.max(0, (s.apiCount ?? 0) - s.next) / PAGE)); }, 0);
      const files = withFiles ? Math.max(0, (state.entities.file?.apiCount ?? 0) - knownFiles) : 0;
      log(`ℹ  Schatting: nog ± ${pages + files} API-calls (${pages} voor tabellen, ${files} voor nieuwe bijlagen) → ${duration(pages + files, client.rateLimit)}.`);
    }
    const chunk = queue.slice(0, BATCH);
    const calls = chunk.map((name) => {
      const s = state.entities[name];
      const options = { paging: { firstresult: s.next, maxresults: PAGE } };
      if (s.ordered) options.orderings = [{ field: `${name}.id`, direction: 'asc' }];
      return { method: `${name}.get`, params: [[], options] };
    });
    const results = await client.batch(calls);
    const nextQueue = queue.slice(BATCH);
    for (let i = 0; i < chunk.length; i++) {
      const name = chunk[i];
      const s = state.entities[name];
      const r = results[i];
      if (r.error) {
        const msg = typeof r.error === 'string' ? r.error : JSON.stringify(r.error);
        if (/insufficient rights|onvoldoende rechten/i.test(msg)) { s.error = msg; s.done = true; log(`✖  ${name}: geen leesrechten in de API-rol — ${msg}`); continue; }
        if (s.ordered && s.next === 0) { s.ordered = false; nextQueue.push(name); log(`   ${name}: sortering niet ondersteund, opnieuw zonder`); continue; }
        s.error = msg; s.done = true; log(`✖  ${name}: ${msg}`);
        continue;
      }
      const { rows, more, count } = parseGetResult(r.result, PAGE);
      buffers[name].push(...rows);
      s.next += rows.length; s.rows = buffers[name].length;
      if (count != null) s.apiCount = count;
      if (more && rows.length > 0) nextQueue.push(name);
      else {
        s.done = true;
        await writeJson(path.join(stagingDir, 'raw', `${name}.json`), buffers[name]);
        await fsp.rm(path.join(stagingDir, 'raw', `${name}.partial.json`), { force: true });
        log(`✔  ${name}: ${s.rows}${s.apiCount != null ? ` / ${s.apiCount}` : ''}`);
        continue;
      }
      await writeJson(path.join(stagingDir, 'raw', `${name}.partial.json`), buffers[name]);
    }
    queue = nextQueue;
    await saveState();
  }

  // laad alle records (ook uit eerdere hervatte runs)
  const records = {};
  for (const e of list) records[e.name] = readJson(path.join(stagingDir, 'raw', `${e.name}.json`), []);

  // ---- 2. bijlagen ----
  if (withFiles) {
    const index = refreshFiles ? { files: {}, invoicePdfs: {} } : loadArchiveIndex(ARCHIVE, DATA);
    const reusable = (e) => { try { return e?.ok && fs.statSync(path.join(ARCHIVE, e.path)).size === e.size; } catch { return false; } };
    const save = async () => { await saveState(); await writeJson(path.join(ARCHIVE, 'index.json'), index); };
    const fileIds = new Set();
    for (const [ent, rows] of Object.entries(records)) {
      for (const row of rows) for (const f of FILE_FIELDS) for (const v of asArray(row[f])) { const id = refId(v); if (id) fileIds.add(Number(id)); }
      if (ent === 'file') for (const row of rows) if (row.id) fileIds.add(Number(row.id));
    }
    // Een bijlage hoort bij één file-id en verandert niet; staat hij al in archive/, dan niet opnieuw ophalen.
    let reused = 0;
    for (const id of fileIds) if (!state.files[id]?.ok && reusable(index.files[id])) { state.files[id] = index.files[id]; reused++; }
    const todo = [...fileIds].filter((id) => !state.files[id]?.ok);
    log(`▶  Bijlagen: ${fileIds.size} gevonden, ${reused} al in het archief, ${todo.length} op te halen${todo.length ? ` (${duration(todo.length, client.rateLimit)})` : ''}`);
    for (let i = 0; i < todo.length; i += 4) {
      const ids = todo.slice(i, i + 4);
      const res = await client.batch(ids.map((id) => ({ method: 'file.getContent', params: [id] })));
      for (let j = 0; j < ids.length; j++) {
        const id = ids[j];
        const r = res[j];
        if (r.error) { state.files[id] = { ok: false, error: String(r.error?.message ?? r.error) }; continue; }
        const v = Array.isArray(r.result) ? r.result[0] : r.result;
        const b64 = v?.content ?? v?.data ?? v?.base64 ?? v?.filedata;
        const name = safeName(v?.name ?? v?.filename ?? `bestand-${id}`);
        if (!b64) { state.files[id] = { ok: false, error: 'lege inhoud' }; continue; }
        const buf = Buffer.from(b64, 'base64');
        const rel = path.join('files', String(id), name);
        await fsp.mkdir(path.join(ARCHIVE, 'files', String(id)), { recursive: true });
        await fsp.writeFile(path.join(ARCHIVE, rel), buf);
        state.files[id] = index.files[id] = { ok: true, name, path: rel.split(path.sep).join('/'), size: buf.length, sha256: sha256(buf) };
      }
      if ((i / 4) % 10 === 0) { log(`   bijlagen ${Math.min(i + 4, todo.length)}/${todo.length}`); await save(); }
    }
    await save();

    // ---- 3. factuur-PDF's via directpdfurl ----
    // Een definitieve factuur (met nummer) verandert niet meer; concepten halen we altijd opnieuw op.
    const invoicePath = (iv) => `invoices/${safeName(`${iv.number ?? 'concept'}_${iv.id}`)}.pdf`;
    let reusedPdfs = 0;
    for (const iv of records.invoice ?? []) {
      const e = index.invoicePdfs[iv.id];
      if (iv.number != null && !state.invoicePdfs[iv.id]?.ok && e?.path === invoicePath(iv) && reusable(e)) { state.invoicePdfs[iv.id] = e; reusedPdfs++; }
    }
    const invs = (records.invoice ?? []).filter((iv) => iv.directpdfurl && !state.invoicePdfs[iv.id]?.ok);
    log(`▶  Factuur-PDF's: ${reusedPdfs} al in het archief, ${invs.length} op te halen`);
    for (const iv of invs) {
      try {
        const u = new URL(iv.directpdfurl);
        if (!/(^|\.)gripp\.com$/.test(u.hostname) && u.hostname !== new URL(url).hostname) throw new Error(`onverwachte host ${u.hostname}`);
        const res = await fetch(u);
        const buf = Buffer.from(await res.arrayBuffer());
        if (buf.subarray(0, 4).toString() !== '%PDF') throw new Error(`geen PDF (HTTP ${res.status}, ${res.headers.get('content-type')})`);
        const rel = invoicePath(iv);
        await fsp.mkdir(path.join(ARCHIVE, 'invoices'), { recursive: true });
        await fsp.writeFile(path.join(ARCHIVE, rel), buf);
        state.invoicePdfs[iv.id] = index.invoicePdfs[iv.id] = { ok: true, path: rel, size: buf.length, sha256: sha256(buf) };
      } catch (e) {
        state.invoicePdfs[iv.id] = { ok: false, error: e.message };
      }
      await new Promise((r) => setTimeout(r, 300));
    }
    await save();
  }

  // ---- 3b. PDF's van offertes, opdrachten, inkoopopdrachten (document-links uit de records) ----
  // Alleen als het profiel dat wil (GRIPP_DOCUMENTS=aan): Gripp kan het openen als "bekeken" registreren.
  if (withFiles && withDocuments && !only) await fetchDocuments({ dataDir: stagingDir, archiveDir: ARCHIVE, log });
  else if (withFiles && !only) log('   Documenten van offertes/opdrachten/inkoopopdrachten overgeslagen (zet GRIPP_DOCUMENTS=aan om ze mee te nemen).');

  // ---- 4. validatie + rapport ----
  const report = validate(records, state, list);
  report.requests = client.requests; report.rpcCalls = client.calls; report.rateLimit = client.rateLimit;
  await writeJson(path.join(stagingDir, 'report.json'), report);
  await fsp.writeFile(path.join(stagingDir, 'report.md'), reportMarkdown(report));
  log(`▶  Rapport: ${path.join(stagingDir, 'report.md')}`);

  // ---- 5. publiceren ----
  const failed = list.filter((e) => state.entities[e.name]?.error).map((e) => e.name);
  state.finishedAt = ts();
  state.status = failed.length ? 'partial' : 'complete';
  await saveState();
  // Een import van een deel van de tabellen is nooit een volledige back-up: nooit publiceren,
  // ook niet met --accept-partial, anders vervangt een test de echte snapshot.
  if (only) {
    log(`ℹ  Import met --only: snapshot NIET gepubliceerd; data/current blijft ongewijzigd. Resultaat: ${stagingDir}`);
    return { stagingDir, report, published: false, testRun: true };
  }
  if (failed.length && !acceptPartial) {
    log(`⚠  ${failed.length} entiteit(en) mislukt (${failed.join(', ')}). Snapshot NIET gepubliceerd; huidige versie blijft ongewijzigd.`);
    log(`   Controleer het rapport en draai eventueel opnieuw met --resume of --accept-partial.`);
    return { stagingDir, report, published: false };
  }
  const current = path.join(DATA, 'current');
  await rotateSnapshots(DATA, cfg.keepSnapshots);
  await fsp.rename(stagingDir, current);
  logFile = path.join(current, 'import.log');
  log(`✔  Snapshot gepubliceerd als data/current (${state.status}).`);
  await build({ log, config: cfg });
  return { stagingDir: current, report, published: true };
}

// Geschatte duur voor `calls` API-calls. Gripp telt elke call mee voor de uurlimiet (standaard 1000);
// binnen het tegoed van dit uur gaat het snel (± 4 calls per seconde), daarboven telt elk uur.
export function duration(calls, rateLimit) {
  const limit = rateLimit?.limit || 1000;
  const left = Math.max(0, (rateLimit?.remaining ?? limit) - 5);
  if (calls <= left) return `± ${Math.max(1, Math.ceil(calls / 4 / 60))} min`;
  const hours = Math.ceil((calls - left) / limit);
  return `± ${hours} uur door de limiet van ${limit} calls per uur`;
}

// Index van alles wat al in archive/ staat, over runs heen (archive/index.json). Bestaat hij nog niet
// (back-up van vóór deze versie), dan beginnen we met wat de laatste gepubliceerde snapshot kent.
function loadArchiveIndex(ARCHIVE, DATA) {
  const idx = readJson(path.join(ARCHIVE, 'index.json'), null);
  if (idx) return { files: idx.files ?? {}, invoicePdfs: idx.invoicePdfs ?? {} };
  const cur = readJson(path.join(DATA, 'current', 'state.json'), {});
  const ok = (m) => Object.fromEntries(Object.entries(m ?? {}).filter(([, e]) => e.ok));
  return { files: ok(cur.files), invoicePdfs: ok(cur.invoicePdfs) };
}

// Bij publiceren: current → previous → history/<batch>. Bewaart in totaal `keep` oudere snapshots
// (previous meegeteld). Bijlagen staan los in archive/ en worden door alle snapshots gedeeld.
async function rotateSnapshots(DATA, keep = 7) {
  const current = path.join(DATA, 'current');
  const previous = path.join(DATA, 'previous');
  const history = path.join(DATA, 'history');
  if (fs.existsSync(previous)) {
    if (keep > 1) {
      const batch = readJson(path.join(previous, 'state.json'), {}).batch ?? `snapshot-${Date.now()}`;
      const dest = path.join(history, batch.replace(/^staging-/, ''));
      await fsp.mkdir(history, { recursive: true });
      await fsp.rm(dest, { recursive: true, force: true });
      await fsp.rename(previous, dest);
    } else {
      await fsp.rm(previous, { recursive: true, force: true });
    }
  }
  if (fs.existsSync(current)) await fsp.rename(current, previous);
  if (fs.existsSync(history)) {
    const all = fs.readdirSync(history).sort();
    for (const d of all.slice(0, Math.max(0, all.length - (keep - 1)))) await fsp.rm(path.join(history, d), { recursive: true, force: true });
  }
}

// Bij een nieuwe import: oude, niet-gepubliceerde staging-mappen opruimen. De nieuwste blijft staan
// (voor analyse of hervatten met --resume).
async function pruneStaging(DATA) {
  const st = fs.readdirSync(DATA).filter((d) => d.startsWith('staging-')).sort();
  for (const d of st.slice(0, -1)) await fsp.rm(path.join(DATA, d), { recursive: true, force: true });
}

const DISCR = { bedrijf: 'company', contactpersoon: 'contact', medewerker: 'employee', offerte: 'offer', opdracht: 'project' };
function validate(records, state, list) {
  const idx = {};
  for (const [n, rows] of Object.entries(records)) idx[n] = new Set(rows.map((r) => String(r.id)));
  const entities = {};
  for (const e of list) {
    const s = state.entities[e.name];
    const rows = records[e.name] ?? [];
    const ids = rows.map((r) => String(r.id));
    const dup = ids.length - new Set(ids).size;
    const brokenRefs = {};
    for (const [field, target] of Object.entries(REFS[e.name] ?? {})) {
      const targets = target === '@offerproject' ? ['offer', 'project'] : [target];
      if (!targets.every((t) => idx[t])) continue;            // doel niet geïmporteerd
      if (/lines$|^payments$/.test(field)) continue;          // embedded arrays: worden apart gecontroleerd
      let n = 0;
      for (const r of rows) for (const v of asArray(r[field])) {
        const id = refId(v); if (id == null || id === 0) continue;
        if (v && typeof v === 'object' && v.discr) { // Gripp geeft het type mee: bedrijf/contactpersoon/medewerker/user/…
          if (v.discr === 'user') continue;           // systeemgebruikers (cron, viewer) bestaan niet als entiteit
          const t = DISCR[v.discr]; if (t && idx[t]) { if (!idx[t].has(String(id))) n++; continue; }
        }
        if (!targets.some((t) => idx[t].has(String(id)))) n++;
      }
      if (n) brokenRefs[field] = n;
    }
    entities[e.name] = { fetched: s?.rows ?? 0, stored: rows.length, apiCount: s?.apiCount ?? null, duplicates: dup, error: s?.error ?? null, brokenRefs };
  }
  const sum = (arr, f) => Math.round(arr.reduce((a, r) => a + (Number(r[f]) || 0), 0) * 100) / 100;
  const inv = (records.invoice ?? []).filter((r) => !r.isbasis), pinvAll = records.purchaseinvoice ?? [], pinv = pinvAll.filter((r) => !r.unprocessed);
  const totals = {
    verkoopfacturen: { aantal: inv.length, totaalInclBtw: sum(inv, 'totalinclvat'), openstaandInclBtw: sum(inv, 'totalopeninclvat') },
    inkoopfacturen: { inbox: pinvAll.length - pinv.length, aantal: pinv.length, totaalInclBtw: sum(pinv, 'totalinclvat'), openstaandInclBtw: sum(pinv, 'totalopeninclvat') },
    offertes: { aantal: (records.offer ?? []).length, totaalExclBtw: sum(records.offer ?? [], 'totalexclvat') },
    opdrachten: { aantal: (records.project ?? []).length, totaalExclBtw: sum(records.project ?? [], 'totalexclvat') },
  };
  const files = Object.values(state.files);
  const pdfs = Object.values(state.invoicePdfs);
  return {
    batch: state.batch, startedAt: state.startedAt, finishedAt: ts(), entities, totals,
    files: { total: files.length, ok: files.filter((f) => f.ok).length, failed: files.filter((f) => !f.ok).length, bytes: files.reduce((a, f) => a + (f.size || 0), 0) },
    invoicePdfs: { total: pdfs.length, ok: pdfs.filter((f) => f.ok).length, failed: pdfs.filter((f) => !f.ok).length, sampleError: pdfs.find((f) => !f.ok)?.error ?? null },
    knownGaps: [
      'Offerte-, opdracht- en inkoopopdracht-PDF\'s: geen API-methode; opgehaald via de document-links in de records (zie data/current/documents.json).',
      'Bankmutaties, journaal, W&V/Balans/BTW-overzichten: niet via API → XAF-auditfile per boekjaar.',
      'Wijzigingsgeschiedenis ("Bekijk geschiedenis"), interacties, publicaties, batches: niet via API.',
      'Instellingen (identiteiten, BTW-tarieven, condities): alleen als id-verwijzing aanwezig.',
    ],
  };
}

function reportMarkdown(r) {
  const eur = (n) => (n ?? 0).toLocaleString('nl-NL', { style: 'currency', currency: 'EUR' });
  const lines = [`# Importrapport ${r.batch}`, '', `Start ${r.startedAt} · einde ${r.finishedAt} · ${r.requests} HTTP-requests / ${r.rpcCalls} API-calls`, '',
    '## Entiteiten', '', '| Entiteit | Opgehaald | Opgeslagen | Volgens API | Dubbel | Gebroken verwijzingen | Fout |', '|---|---:|---:|---:|---:|---|---|'];
  for (const [n, e] of Object.entries(r.entities)) {
    const br = Object.entries(e.brokenRefs).map(([k, v]) => `${k}: ${v}`).join(', ');
    lines.push(`| ${n} | ${e.fetched} | ${e.stored} | ${e.apiCount ?? '–'} | ${e.duplicates || ''} | ${br} | ${e.error ? '✖ ' + e.error.slice(0, 80) : ''} |`);
  }
  lines.push('', '## Financiële controletotalen', '', '| | Aantal | Totaal | Openstaand |', '|---|---:|---:|---:|',
    `| Verkoopfacturen | ${r.totals.verkoopfacturen.aantal} | ${eur(r.totals.verkoopfacturen.totaalInclBtw)} incl. | ${eur(r.totals.verkoopfacturen.openstaandInclBtw)} |`,
    `| Inkoopfacturen (excl. ${r.totals.inkoopfacturen.inbox ?? 0} in inbox) | ${r.totals.inkoopfacturen.aantal} | ${eur(r.totals.inkoopfacturen.totaalInclBtw)} incl. | ${eur(r.totals.inkoopfacturen.openstaandInclBtw)} |`,
    `| Offertes | ${r.totals.offertes.aantal} | ${eur(r.totals.offertes.totaalExclBtw)} excl. | |`,
    `| Opdrachten | ${r.totals.opdrachten.aantal} | ${eur(r.totals.opdrachten.totaalExclBtw)} excl. | |`,
    '', 'Vergelijk met Gripp: Verkoopfacturen → zijbalk "Totalen incl. BTW", Inkoopfacturen → "Openstaand incl. BTW".', '',
    '## Bijlagen', '', `Bestanden: ${r.files.ok} van ${r.files.total} opgehaald (${(r.files.bytes / 1048576).toFixed(1)} MB), ${r.files.failed} mislukt.`,
    `Factuur-PDF's: ${r.invoicePdfs.ok} van ${r.invoicePdfs.total}${r.invoicePdfs.sampleError ? ` — voorbeeldfout: ${r.invoicePdfs.sampleError}` : ''}.`,
    '', '## Bekende beperkingen (niet via API)', '', ...r.knownGaps.map((g) => `- ${g}`), '',
    '> Deze snapshot is **niet** als 100% volledige back-up aan te merken zolang bovenstaande beperkingen niet met aanvullende exports zijn afgedekt.');
  return lines.join('\n');
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  // Exitcode 2: import klaar maar niet gepubliceerd (bv. ontbrekende rechten); 1: afgebroken.
  runImport().then((r) => { if (!r.published && !r.testRun) process.exitCode = 2; }).catch((e) => { log(`✖  Import afgebroken: ${e.message}`); log(`   Hervatten: kies 'Onderbroken back-up hervatten' in het menu (opdracht: resume${profileArg(activeConfig)}).`); process.exit(1); });
}
