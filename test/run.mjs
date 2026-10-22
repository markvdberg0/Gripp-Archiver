// End-to-end test tegen de nep-API (raakt Gripp nooit):  npm test
// Werkt in een tijdelijke back-upmap (nooit in een profiel), zodat echte back-ups onaangeroerd blijven.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { startMock } from './mock-gripp.mjs';
import { GrippClient, ReadOnlyViolation } from '../lib/gripp-client.mjs';
import { runImport } from '../importer/import.mjs';
import { resolveConfig, parseEnvFile, TOOL_ROOT, slugify, writeProfile } from '../lib/config.mjs';
import { checkAccess, describeError, formatCheck } from '../lib/check.mjs';
import { cronLine, macPlist, parseTime, windowsScript } from '../lib/schedule.mjs';
import { insideAny } from '../lib/setup.mjs';

const SRC = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'gripp-backup-test-'));
const config = resolveConfig({ backupDir: TMP, token: 'test-token', accountName: 'Testbedrijf B.V.', documents: true });

let ok = 0;
const t = async (name, fn) => { try { await fn(); ok++; console.log(`  ✔ ${name}`); } catch (e) { console.error(`  ✖ ${name}\n    ${e.stack}`); process.exitCode = 1; } };

const quiet = () => {};

console.log('Configuratie');
await t('standaard back-upmap is backups/<profiel> in de tool', () => { const c = resolveConfig({ profile: 'acme' }); assert.equal(c.backupDir, path.join(TOOL_ROOT, 'backups', 'acme')); assert.equal(c.accountName, 'acme'); assert.equal(c.viewerDir, path.join(c.backupDir, 'viewer')); });
await t('zonder profiel en zonder back-upmap: duidelijke fout', () => assert.throws(() => resolveConfig({}), /Geen profiel/));
await t('config.env lezen (BOM, quotes, commentaar, CRLF)', () => { const f = path.join(TMP, 'c.env'); fs.writeFileSync(f, '﻿# commentaar\r\nGRIPP_API_TOKEN="abc"\r\nGRIPP_ACCOUNT_NAME=Mijn Bedrijf B.V.\r\n'); assert.deepEqual(parseEnvFile(f), { GRIPP_API_TOKEN: 'abc', GRIPP_ACCOUNT_NAME: 'Mijn Bedrijf B.V.' }); fs.rmSync(f); });

await t('profielnaam uit bedrijfsnaam', () => { assert.equal(slugify('Bakker & Zn. B.V.'), 'bakker-zn-bv'); assert.equal(slugify('Café Ünïcode'), 'cafe-unicode'); assert.equal(slugify('!!!'), 'gripp'); });
await t('profiel wegschrijven en teruglezen', () => { const dir = path.join(TMP, 'profiles'); const f = writeProfile({ profile: 'acme', token: 'tok', accountName: 'Acme B.V.', backupDir: 'D:\\Back-ups\\Acme B.V' }, dir); assert.deepEqual(parseEnvFile(f), { GRIPP_API_TOKEN: 'tok', GRIPP_ACCOUNT_NAME: 'Acme B.V.', GRIPP_BACKUP_DIR: 'D:\\Back-ups\\Acme B.V', GRIPP_DOCUMENTS: 'uit', GRIPP_KEEP_SNAPSHOTS: '7' }); assert.throws(() => writeProfile({ profile: '../x', token: 't' }, dir)); assert.throws(() => writeProfile({ profile: 'x', token: 't\nGRIPP_API_URL=evil' }, dir), /regeleinden/); fs.rmSync(dir, { recursive: true }); });

console.log('Verbinding en rechtencheck');
{
  const m = await startMock({ failEvery: 0, deny: ['invoice', 'department'] });
  const r = await checkAccess({ token: 'test-token', url: m.url });
  const by = Object.fromEntries(r.results.map((x) => [x.name, x]));
  await t('alle tabellen gecontroleerd, alleen met .get', () => { assert.equal(r.results.length, 50); assert.equal(m.stats.writeAttempts, 0); for (const x of m.stats.methods) assert.match(x, /\.get$/); });
  await t('geweigerde tabellen herkend, met Gripp-naam', () => { assert.equal(by.invoice.ok, false); assert.equal(by.invoice.rights, true); assert.equal(by.invoice.grippName, 'nl-invoice'); assert.equal(by.company.ok, true); assert.equal(by.company.count, 620); });
  await t('melding noemt wat je in Gripp moet aanvinken', () => { const s = formatCheck(r); assert.match(s, /48 van 50 tabellen leesbaar/); assert.match(s, /nl-department/); });
  await t('ongeldig token: duidelijke fout', () => assert.rejects(() => checkAccess({ token: 'fout', url: m.url }), /401/));
  await t('storing (doorverwijzing naar statuspagina): duidelijke fout, verwijzing niet gevolgd', async () => { const mm = await startMock({ maintenance: true }); await assert.rejects(() => checkAccess({ token: 'test-token', url: mm.url }), /tijdelijk niet beschikbaar: doorgestuurd naar status.example.test/); assert.equal(mm.stats.requests, 0); mm.server.close(); });
  await t('foutmelding van echte Gripp wordt herkend', () => assert.deepEqual(describeError('Insufficient rights. Please check you API-role settings to see if you have "read" privileges for entity "afdeling".'), { rights: true, grippName: 'afdeling' }));
  m.server.close();
}

console.log('Read-only garantie');
await t('client weigert create/update/delete', async () => {
  const c = new GrippClient({ token: 'x', url: 'http://127.0.0.1:9/', log: quiet });
  for (const m of ['company.create', 'invoice.update', 'offer.delete', 'invoice.markAsSent', 'notification.emit', 'file.uploadContent', 'company.addInteractionByCompanyId']) {
    await assert.rejects(() => c.batch([{ method: m, params: [] }]), ReadOnlyViolation, m);
  }
});

console.log('Volledige import tegen nep-API (met 503-storingen)');
const mock = await startMock({ failEvery: 7 });
process.env.GRIPP_ALLOWED_HOSTS = '127.0.0.1';
const res = await runImport({ config, url: mock.url, resume: false });
await t('snapshot gepubliceerd', () => assert.equal(res.published, true));
await t('nooit een schrijfmethode verstuurd', () => { assert.equal(mock.stats.writeAttempts, 0); for (const m of mock.stats.methods) assert.match(m, /\.get$|^file\.getContent$/); });
const raw = (e) => JSON.parse(fs.readFileSync(path.join(TMP, 'data/current/raw', `${e}.json`), 'utf8'));
await t('paging: alle 620 relaties (3 pagina\'s van max 250)', () => assert.equal(raw('company').length, 620));
await t('geen dubbele records', () => { const ids = raw('company').map((r) => r.id); assert.equal(new Set(ids).size, ids.length); });
await t('alle entiteiten aanwezig', () => { for (const e of ['offer', 'project', 'invoice', 'invoiceline', 'purchaseinvoice', 'timelineentry', 'memorialline']) assert.ok(raw(e).length > 0, e); });
const report = JSON.parse(fs.readFileSync(path.join(TMP, 'data/current/report.json'), 'utf8'));
await t('rapport: gebroken verwijzing gedetecteerd', () => assert.equal(report.entities.contact.brokenRefs.company, 1));
await t('rapport: controletotalen', () => { assert.equal(report.totals.verkoopfacturen.aantal, 71); assert.equal(report.totals.verkoopfacturen.totaalInclBtw, 71 * 1210); });
await t('bijlagen opgehaald + sha256', () => { assert.ok(report.files.ok >= 4); const st = JSON.parse(fs.readFileSync(path.join(TMP, 'data/current/state.json'), 'utf8')); const f = Object.values(st.files)[0]; assert.match(f.sha256, /^[0-9a-f]{64}$/); assert.ok(fs.existsSync(path.join(TMP, 'archive', f.path))); });
await t('factuur-PDF\'s via directpdfurl', () => assert.equal(report.invoicePdfs.ok, 3));
await t('offerte-PDF\'s via directpdfurl', () => { const d = JSON.parse(fs.readFileSync(path.join(TMP, 'data/current/documents.json'), 'utf8')); assert.equal(Object.values(d.offer).filter((x) => x.ok).length, 30); });
await t('opdracht-PDF\'s via online weergave (link gevonden)', () => { const d = JSON.parse(fs.readFileSync(path.join(TMP, 'data/current/documents.json'), 'utf8')); const ok = Object.values(d.project).filter((x) => x.ok); assert.equal(ok.length, 12); assert.equal(ok[0].via, 'weergave'); assert.ok(fs.existsSync(path.join(TMP, 'archive', ok[0].path))); });
await t('viewer-data gebouwd', () => { const js = fs.readFileSync(path.join(TMP, 'viewer/data/data.js'), 'utf8'); assert.ok(js.startsWith('window.GRIPP_DATA=')); assert.ok(!js.includes('</script')); assert.ok(js.includes('"accountName":"Testbedrijf B.V."')); });
await t('back-upmap staat op zichzelf (viewer + startpagina)', () => { for (const f of ['viewer/index.html', 'viewer/app.js', 'viewer/style.css', 'Archief openen.html']) assert.ok(fs.existsSync(path.join(TMP, f)), f); });
await t('tool-map blijft schoon (geen data in de code)', () => { for (const d of ['data', 'archive', path.join('viewer', 'data')]) assert.ok(!fs.existsSync(path.join(SRC, d)), d); });

console.log('Mislukte import overschrijft bestaande snapshot niet');
const before = fs.statSync(path.join(TMP, 'data/current/state.json')).mtimeMs;
const mock2 = await startMock({ failEvery: 0, deny: ['invoice'] });
const res2 = await runImport({ config, url: mock2.url, resume: false, noFiles: true });
await t('gedeeltelijke import niet gepubliceerd', () => assert.equal(res2.published, false));
await t('data/current ongewijzigd', () => assert.equal(fs.statSync(path.join(TMP, 'data/current/state.json')).mtimeMs, before));
await t('andere tabellen in dezelfde batch wél opgehaald (transactie-fallback)', () => { const c = JSON.parse(fs.readFileSync(path.join(res2.stagingDir, 'raw', 'company.json'), 'utf8')); assert.equal(c.length, 620); assert.ok(fs.existsSync(path.join(res2.stagingDir, 'raw', 'invoiceline.json'))); });
await t('alleen de geweigerde tabel als fout gemeld', () => { const st2 = JSON.parse(fs.readFileSync(path.join(res2.stagingDir, 'state.json'), 'utf8')); assert.deepEqual(Object.entries(st2.entities).filter(([, s]) => s.error).map(([n]) => n), ['invoice']); });
await t('staging behouden voor analyse', () => assert.ok(fs.existsSync(path.join(res2.stagingDir, 'report.md'))));

console.log('Hervatten probeert mislukte tabel opnieuw (na aanpassen API-rol)');
const mockR = await startMock({ failEvery: 0 });
const resR = await runImport({ config, url: mockR.url, resume: true, noFiles: true });
await t('na resume: invoice alsnog opgehaald en gepubliceerd', () => { assert.equal(resR.published, true); assert.equal(raw('invoice').length, 71); });
await t('na resume: geslaagde tabellen niet opnieuw opgehaald', () => assert.ok(mockR.stats.calls <= 3, `calls=${mockR.stats.calls}`));
mockR.server.close();

console.log('Import met --only vervangt nooit data/current');
const mockO = await startMock({ failEvery: 0 });
const snap = (d) => fs.readFileSync(path.join(TMP, 'data', d, 'state.json'), 'utf8');
const [curBefore, prevBefore] = [snap('current'), snap('previous')];
const resO = await runImport({ config, url: mockO.url, resume: false, noFiles: true, only: 'company', acceptPartial: true });
await t('niet gepubliceerd, ook niet met --accept-partial', () => assert.equal(resO.published, false));
await t('data/current en data/previous ongewijzigd', () => { assert.equal(snap('current'), curBefore); assert.equal(snap('previous'), prevBefore); assert.equal(raw('invoice').length, 71); });
await t('resultaat in eigen map only-* (niet hervatbaar)', () => { assert.match(path.basename(resO.stagingDir), /^only-/); assert.equal(JSON.parse(fs.readFileSync(path.join(resO.stagingDir, 'raw', 'company.json'), 'utf8')).length, 620); });
await t('onbekende tabelnaam geweigerd', () => assert.rejects(() => runImport({ config, url: mockO.url, resume: false, noFiles: true, only: 'bestaatniet' }), /onbekende/));
mockO.server.close();

console.log('Hervatten na onderbreking');
const mock3 = await startMock({ failEvery: 0 });
// simuleer: staging met half-afgeronde state
const st = path.join(TMP, 'data', 'staging-9999-resume');
fs.mkdirSync(path.join(st, 'raw'), { recursive: true });
fs.writeFileSync(path.join(st, 'raw', 'company.partial.json'), JSON.stringify(mock3.data.company.slice(0, 250)));
fs.writeFileSync(path.join(st, 'state.json'), JSON.stringify({ batch: 'staging-9999-resume', startedAt: new Date().toISOString(), entities: { company: { done: false, next: 250, rows: 250, ordered: true } }, files: {}, invoicePdfs: {} }));
for (const d of fs.readdirSync(path.join(TMP, 'data')).filter((d) => d.startsWith('staging-') && d !== 'staging-9999-resume')) fs.rmSync(path.join(TMP, 'data', d), { recursive: true });
const callsBefore = mock3.stats.calls;
const res3 = await runImport({ config, url: mock3.url, resume: true, noFiles: true, only: 'company' });
await t('hervat de onderbroken staging, niet de only-testrun', () => assert.equal(path.basename(res3.stagingDir), 'staging-9999-resume'));
await t('hervat vanaf checkpoint (geen eerste pagina opnieuw)', () => { assert.equal(res3.published, false); assert.equal(JSON.parse(fs.readFileSync(path.join(st, 'raw', 'company.json'), 'utf8')).length, 620); assert.ok(mock3.stats.calls - callsBefore <= 3); });

console.log('Herhaalde back-ups: bijlagen hergebruiken, snapshots bewaren, --auto');
{
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'gripp-backup-herhaal-'));
  const cfg2 = resolveConfig({ backupDir: dir, token: 'test-token', accountName: 'Herhaal', keepSnapshots: 2 });
  const batchOf = () => JSON.parse(fs.readFileSync(path.join(dir, 'data', 'current', 'state.json'), 'utf8')).batch;
  const run = async (opts = {}) => { const m = await startMock({ failEvery: 0 }); const r = await runImport({ config: cfg2, url: m.url, resume: false, ...opts }); m.server.close(); return { r, stats: m.stats }; };

  await run();
  const second = await run();
  await t('tweede back-up haalt geen bijlagen of factuur-PDF\'s opnieuw op', () => { assert.equal(second.r.published, true); assert.ok(!second.stats.methods.has('file.getContent')); assert.equal(second.stats.pdfGets, 0); assert.equal(second.r.report.files.ok, 4); assert.equal(second.r.report.invoicePdfs.ok, 3); });
  await t('documenten standaard niet opgehaald (GRIPP_DOCUMENTS uit)', () => assert.ok(!fs.existsSync(path.join(dir, 'data', 'current', 'documents.json'))));

  const idx = JSON.parse(fs.readFileSync(path.join(dir, 'archive', 'index.json'), 'utf8'));
  const [gone] = Object.values(idx.files);
  fs.rmSync(path.join(dir, 'archive', gone.path));
  const third = await run();
  await t('ontbrekende bijlage wordt wél opnieuw opgehaald', () => { assert.ok(third.stats.methods.has('file.getContent')); assert.ok(fs.existsSync(path.join(dir, 'archive', gone.path))); });
  const fourth = await run({ refreshFiles: true });
  await t('--refresh-files haalt alles opnieuw op', () => assert.equal(fourth.stats.pdfGets, 3));

  // Oude, onafgemaakte staging (> 1 dag): --auto begint opnieuw
  const stale = path.join(dir, 'data', 'staging-9998-oud');
  fs.mkdirSync(path.join(stale, 'raw'), { recursive: true });
  fs.writeFileSync(path.join(stale, 'state.json'), JSON.stringify({ batch: 'staging-9998-oud', startedAt: new Date(Date.now() - 2 * 86400_000).toISOString(), entities: {}, files: {}, invoicePdfs: {} }));
  await run({ resume: undefined, auto: true, noFiles: true });
  await t('--auto: oude onderbroken import niet hervat, nieuwe gemaakt', () => assert.notEqual(batchOf(), 'staging-9998-oud'));
  // Recente, onafgemaakte staging: --auto hervat
  const recent = path.join(dir, 'data', 'staging-9999-auto');
  fs.mkdirSync(path.join(recent, 'raw'), { recursive: true });
  fs.writeFileSync(path.join(recent, 'state.json'), JSON.stringify({ batch: 'staging-9999-auto', startedAt: new Date().toISOString(), entities: {}, files: {}, invoicePdfs: {} }));
  await run({ resume: undefined, auto: true, noFiles: true });
  await t('--auto: recente onderbroken import hervat', () => assert.equal(batchOf(), 'staging-9999-auto'));
  await t('oude staging-mappen opgeruimd (hooguit de laatste blijft voor analyse)', () => assert.ok(fs.readdirSync(path.join(dir, 'data')).filter((d) => d.startsWith('staging-')).length <= 1));

  await t('snapshots bewaard volgens instelling (2 = previous + 1 in history)', () => { assert.ok(fs.existsSync(path.join(dir, 'data', 'previous', 'state.json'))); assert.equal(fs.readdirSync(path.join(dir, 'data', 'history')).length, 1); });
  fs.rmSync(dir, { recursive: true, force: true });
}

await t('waarschuwing voor OneDrive/SharePoint-map: map binnen een gesynchroniseerde map herkend', () => { const root = path.join(TMP, 'OneDrive - Bedrijf'); assert.ok(insideAny(path.join(root, 'Gripp', 'acme'), [root])); assert.ok(!insideAny(path.join(TMP, 'OneDrive - BedrijfX'), [root])); assert.ok(!insideAny(path.join(TMP, 'elders'), [root])); });

console.log('Inplannen (alleen de opdrachten controleren, er wordt niets ingepland)');
await t('tijdstip controleren', () => { assert.deepEqual(parseTime('2:05'), { hour: 2, minute: 5, text: '02:05' }); assert.throws(() => parseTime('24:00')); assert.throws(() => parseTime('morgen')); });
await t('Windows: taak start de PowerShell-starter met --auto --scheduled', () => { const s = windowsScript('acme', parseTime('02:30')); assert.match(s, /Register-ScheduledTask -TaskName 'Gripp Back-up \(acme\)'/); assert.match(s, /start\.ps1" backup --auto --scheduled --profile=acme/); assert.match(s, /-StartWhenAvailable/); assert.match(s, /-At '02:30'/); });
await t('macOS: launchd-plist', () => { const x = macPlist('acme', parseTime('02:30')); assert.match(x, /<key>Hour<\/key><integer>2<\/integer><key>Minute<\/key><integer>30<\/integer>/); assert.match(x, /<string>--profile=acme<\/string>/); });
await t('Linux: cron-regel', () => assert.match(cronLine('acme', parseTime('02:30')), /^30 2 \* \* \* cd '.+' && \/bin\/sh \.\/start\.sh backup --auto --scheduled --profile=acme .*# gripp-backup:acme$/));

// terugzetten naar volledige dataset voor viewer-test
await runImport({ config, url: mock.url, resume: false });
for (const m of [mock, mock2, mock3]) m.server.close();

console.log(`\n${ok} tests geslaagd. Test-back-upmap (open "Archief openen.html"): ${TMP}`);
