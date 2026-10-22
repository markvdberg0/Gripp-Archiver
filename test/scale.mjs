// Schaaltest voor de viewer (geen onderdeel van npm test): maakt een nep-back-up van N× de testdata
// met realistische recordgrootte (± 1,7 kB) en bouwt de viewer. Open daarna "Archief openen.html".
//   node test/scale.mjs <map> [factor=100]
import fs from 'node:fs';
import path from 'node:path';
import { makeData } from './mock-gripp.mjs';
import { resolveConfig } from '../lib/config.mjs';
import { build } from '../importer/build.mjs';

const [dir, factorArg = '100'] = process.argv.slice(2);
if (!dir) { console.error('Gebruik: node test/scale.mjs <map> [factor]'); process.exit(1); }
const factor = Number(factorArg);
const base = makeData();
const PAD = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. '.repeat(20);   // ± 1,1 kB

// Elke kopie krijgt eigen id's; verwijzingen ({ id, … }) schuiven mee, zodat koppelingen blijven kloppen.
const shift = (v, off) => {
  if (Array.isArray(v)) return v.map((x) => shift(x, off));
  if (v && typeof v === 'object') {
    const o = {};
    for (const [k, x] of Object.entries(v)) o[k] = k === 'id' && typeof x === 'number' ? x + off : shift(x, off);
    return o;
  }
  return v;
};

const raw = path.join(dir, 'data', 'current', 'raw');
fs.mkdirSync(raw, { recursive: true });
let n = 0;
for (const [ent, rows] of Object.entries(base)) {
  const out = [];
  for (let k = 0; k < factor; k++) for (const r of rows) out.push({ ...shift(r, k * 1_000_000), notitie: `${PAD}${k}` });
  fs.writeFileSync(path.join(raw, `${ent}.json`), JSON.stringify(out));
  n += out.length;
}
fs.writeFileSync(path.join(dir, 'data', 'current', 'state.json'), JSON.stringify({ batch: `schaaltest-${factor}x`, status: 'complete', startedAt: new Date().toISOString(), files: {}, invoicePdfs: {} }));
console.log(`${n} records geschreven`);
await build({ config: resolveConfig({ backupDir: dir, accountName: `Schaaltest ${factor}×` }) });
