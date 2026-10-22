#!/usr/bin/env node
// Documenten (PDF's) van offertes, opdrachten en inkoopopdrachten archiveren.
//
//   npm run documents            → werkt op data/current van het profiel, daarna wordt de viewer opnieuw gebouwd
//   npm run documents -- --profile=naam
//
// Bron: de document-links die Gripp zelf in de API-records meelevert (geen API-quotum nodig):
//   - offer.directpdfurl            → directe PDF
//   - project.viewonlineurl         → online weergave; daarin zoeken we de PDF-link
//   - purchaseorder.viewonlineurl   → idem
// Alleen GET-verzoeken naar *.gripp.com; er wordt niets geaccepteerd, ondertekend of verzonden.
// Let op: de online weergave is dezelfde pagina die een klant/leverancier ziet. Gripp kan het openen ervan
// in de timeline registreren ("bekeken"). Zie README.
import fs from 'node:fs';
import fsp from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { loadConfig } from '../lib/config.mjs';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const safe = (s) => String(s ?? '').replace(/[<>:"/\\|?*\x00-\x1f]/g, '_').slice(0, 120);
const isGripp = (u) => { try { const h = new URL(u).hostname; return /(^|\.)gripp\.com$/.test(h) || (process.env.GRIPP_ALLOWED_HOSTS || '').split(',').includes(h); /* extra hosts alleen voor tests */ } catch { return false; } };

async function get(u) {
  for (let i = 0; i < 4; i++) {
    try {
      const res = await fetch(u, { redirect: 'follow' });
      const buf = Buffer.from(await res.arrayBuffer());
      if (res.status >= 500 || res.status === 429) throw new Error(`HTTP ${res.status}`);
      return { res, buf };
    } catch (e) { if (i === 3) throw e; await sleep(1000 * 2 ** i); }
  }
}
const isPdf = (buf) => buf.subarray(0, 5).toString() === '%PDF-';

// Zoek in de HTML van de online weergave naar links die naar een PDF wijzen.
function pdfCandidates(html, base) {
  const out = new Set();
  const re = /(?:href|src|data-[a-z-]+|action)\s*=\s*["']([^"']+)["']|["'](https?:\/\/[^"']+)["']|["'](\/?(?:public\/)?[^"'\s]*viewer\.php\?[^"']+)["']/gi;
  let m;
  while ((m = re.exec(html))) {
    const raw = (m[1] || m[2] || m[3] || '').replace(/&amp;/g, '&');
    if (!raw || raw.startsWith('#') || raw.startsWith('javascript:')) continue;
    let abs; try { abs = new URL(raw, base).href; } catch { continue; }
    if (!isGripp(abs)) continue;
    if (/pdf|download|print|flush/i.test(abs) || /viewer\.php\?/.test(abs)) out.add(abs);
  }
  // Waarschijnlijkste eerst
  return [...out].filter((u) => u !== base).sort((a, b) => score(b) - score(a));
}
const score = (u) => (/type=pdf|\.pdf/i.test(u) ? 3 : 0) + (/download/i.test(u) ? 2 : 0) + (/pdf/i.test(u) ? 1 : 0);

export async function fetchDocuments({ dataDir, archiveDir, log = console.log, only } = {}) {
  const raw = (e) => { const f = path.join(dataDir, 'raw', `${e}.json`); return fs.existsSync(f) ? JSON.parse(fs.readFileSync(f, 'utf8')) : []; };
  const statePath = path.join(dataDir, 'documents.json');
  const state = fs.existsSync(statePath) ? JSON.parse(fs.readFileSync(statePath, 'utf8')) : {};
  const save = async () => { const t = statePath + '.tmp'; await fsp.writeFile(t, JSON.stringify(state, null, 1)); await fsp.rename(t, statePath); };
  const jobs = [
    ['offer', 'offertes', (r) => r.directpdfurl || r.viewonlineurl],
    ['project', 'opdrachten', (r) => r.directpdfurl || r.viewonlineurl],
    ['purchaseorder', 'inkoopopdrachten', (r) => r.directpdfurl || r.viewonlineurl],
  ].filter(([e]) => !only || only.includes(e));

  for (const [ent, dir, urlOf] of jobs) {
    const rows = raw(ent);
    const st = (state[ent] ??= {});
    const todo = rows.filter((r) => urlOf(r) && !st[r.id]?.ok);
    log(`▶  ${dir}: ${rows.length} records, ${todo.length} documenten op te halen`);
    let n = 0;
    for (const r of todo) {
      const u = urlOf(r);
      const name = safe(`${r.number ?? 'zonder-nummer'}_${r.id}`);
      try {
        if (!isGripp(u)) throw new Error('onverwachte host');
        let { res, buf } = await get(u);
        let source = u;
        if (!isPdf(buf)) {
          // Online weergave: HTML bewaren (leesbare kopie) en daarin de PDF zoeken
          const html = buf.toString('utf8');
          await fsp.mkdir(path.join(archiveDir, dir, 'weergave'), { recursive: true });
          await fsp.writeFile(path.join(archiveDir, dir, 'weergave', `${name}.html`), html);
          let found = null;
          for (const c of pdfCandidates(html, res.url || u).slice(0, 6)) {
            await sleep(250);
            const t = await get(c);
            if (isPdf(t.buf)) { found = t; source = c; break; }
          }
          if (!found) throw new Error(`geen PDF-link gevonden in online weergave (HTTP ${res.status}); HTML bewaard in archive/${dir}/weergave/`);
          buf = found.buf;
        }
        const rel = `${dir}/${name}.pdf`;
        await fsp.mkdir(path.join(archiveDir, dir), { recursive: true });
        await fsp.writeFile(path.join(archiveDir, rel), buf);
        st[r.id] = { ok: true, path: rel, size: buf.length, sha256: crypto.createHash('sha256').update(buf).digest('hex'), via: source === u ? 'direct' : 'weergave' };
      } catch (e) {
        st[r.id] = { ok: false, error: e.message };
        if (n < 3) log(`   ✖ ${ent} ${r.number ?? r.id}: ${e.message}`);
      }
      n++;
      if (n % 25 === 0) { log(`   ${n}/${todo.length}`); await save(); }
      await sleep(300);
    }
    await save();
    const ok = Object.values(st).filter((x) => x.ok).length;
    log(`✔  ${dir}: ${ok} van ${rows.length} PDF's gearchiveerd`);
  }
  return state;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const only = process.argv.find((a) => a.startsWith('--only='))?.slice(7).split(',');
  const { build } = await import('./build.mjs');
  Promise.resolve().then(async () => {
    const config = loadConfig();
    await fetchDocuments({ dataDir: path.join(config.dataDir, 'current'), archiveDir: config.archiveDir, only });
    await build({ config });
  }).catch((e) => { console.error('✖ ', e.message); process.exit(1); });
}
