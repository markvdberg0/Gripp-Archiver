// Verbindingstest en rechtencheck: vraagt van elke tabel één record op (alleen *.get) en meldt
// welke tabellen de API-rol mag lezen. Kost ongeveer 5–20 requests van de 1000 per uur.
import { GrippClient, parseGetResult } from './gripp-client.mjs';
import { ENTITIES } from './schema.mjs';

const BATCH = 10;
const errText = (e) => (typeof e === 'string' ? e : e?.message ?? JSON.stringify(e));

// Gripp: 'Insufficient rights. Please check you API-role settings ... privileges for entity "afdeling".'
// De Nederlandse naam is wat je in Gripp bij de API-rol aanvinkt.
export function describeError(msg) {
  const rights = /insufficient rights|onvoldoende rechten/i.test(msg);
  const grippName = msg.match(/entity "([^"]+)"/)?.[1] ?? null;
  return { rights, grippName };
}

export async function checkAccess({ token, url, log = () => {} }) {
  const client = new GrippClient({ token, url, log, maxRetries: 0 });   // interactief: direct melden, niet wachten
  const names = ENTITIES.map((e) => e.name);
  const results = [];
  for (let i = 0; i < names.length; i += BATCH) {
    const chunk = names.slice(i, i + BATCH);
    const res = await client.batch(chunk.map((n) => ({ method: `${n}.get`, params: [[], { paging: { firstresult: 0, maxresults: 1 } }] })));
    chunk.forEach((name, j) => {
      const r = res[j];
      if (r.error) results.push({ name, ok: false, error: errText(r.error), ...describeError(errText(r.error)) });
      else results.push({ name, ok: true, count: parseGetResult(r.result, 1).count });
    });
  }
  return { results, rateLimit: client.rateLimit, requests: client.requests };
}

export function formatCheck({ results, rateLimit }) {
  const ok = results.filter((r) => r.ok);
  const bad = results.filter((r) => !r.ok);
  const records = ok.reduce((a, r) => a + (r.count ?? 0), 0);
  const lines = [`✔  Verbinding met Gripp gelukt. ${ok.length} van ${results.length} tabellen leesbaar (${records.toLocaleString('nl-NL')} records).`];
  if (bad.length) {
    const rights = bad.filter((r) => r.rights);
    const other = bad.filter((r) => !r.rights);
    if (rights.length) {
      lines.push('', `⚠  Geen leesrechten voor ${rights.length} tabel(len). Zet in Gripp bij Instellingen › API › de API-rol van deze sleutel het leesrecht aan voor:`);
      for (const r of rights) lines.push(`     - ${r.grippName ?? r.name}${r.grippName ? `  (${r.name})` : ''}`);
      lines.push('   Zonder die rechten mislukken deze tabellen en wordt de back-up niet als volledig gepubliceerd.');
    }
    for (const r of other) lines.push(`✖  ${r.name}: ${r.error.slice(0, 160)}`);
  }
  if (rateLimit) lines.push('', `   API-limiet: nog ${rateLimit.remaining} van ${rateLimit.limit} requests dit uur.`);
  return lines.join('\n');
}
