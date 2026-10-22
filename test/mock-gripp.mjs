// Nep-Gripp-API voor tests: JSON-RPC batch, paging (max 250), rate-limit headers,
// file.getContent, directpdfurl, en willekeurige 503's om retries te testen.
// Weigert schrijfmethodes met een fout (en telt ze, zodat de test kan bewijzen dat er nooit één wordt gestuurd).
import http from 'node:http';

export function makeData() {
  const d = (s) => ({ date: `${s} 00:00:00.000000`, timezone_type: 3, timezone: 'Europe/Amsterdam' });
  const ref = (id, searchname) => ({ id, searchname });
  const data = {
    tag: [{ id: 1, name: '2000', searchname: '2000' }, { id: 2, name: 'betreedbaar', searchname: 'betreedbaar' }],
    unit: [{ id: 1, name: 'stuks', searchname: 'stuks' }, { id: 2, name: 'uur', searchname: 'uur' }],
    offerphase: ['Acquisitie', 'Verzoek', 'Schrijven', 'Indienen', 'Beoordeling', 'Besluit'].map((n, i) => ({ id: i + 1, name: n, searchname: n, _ordering: i })),
    projectphase: ['Intake', 'Werkvoorbereiding', 'Wachten op materialen', 'Productie', 'Uitvoering', 'Oplevering'].map((n, i) => ({ id: i + 1, name: n, searchname: n, _ordering: i })),
    ledger: [{ id: 8000, number: '8000', name: 'Omzet handelsgoederen', searchname: '8000 Omzet' }, { id: 4219, number: '42190', name: 'Overige algemene kosten', searchname: '42190' }],
    employee: [{ id: 100, firstname: 'Jan', lastname: 'Jansen', searchname: 'Jan Jansen', number: 100, email: 'jan@example.test', active: true, socialsecuritynumber: '123456789' }, { id: 103, firstname: 'Anna', infix: 'de', lastname: 'Vries', searchname: 'Anna de Vries', active: true }],
    company: [], contact: [], offer: [], project: [], offerprojectline: [], invoice: [], invoiceline: [], payment: [],
    purchaseinvoice: [], purchaseinvoiceline: [], purchaseorder: [], purchaseorderline: [], timelineentry: [], hour: [], task: [], product: [], file: [],
    memorial: [{ id: 1, number: 1004, name: 'Betonindustrie', reportdate: d('2026-02-16') }], memorialline: [{ id: 1, memorial: ref(1, '1004'), ledger: ref(4219, '42190'), value: 18.15, description: 'correctie' }, { id: 2, memorial: ref(1, '1004'), ledger: ref(8000, '8000'), value: -18.15, description: 'correctie' }],
    contract: [{ id: 1, number: 50002, name: 'Huur containerstation', company: ref(1, 'Bedrijf 1'), status: 'ACTIVE', frequency: 'EVERYMONTH', date: d('2026-07-14') }],
  };
  for (let i = 1; i <= 620; i++) data.company.push({ id: i, companyname: `Bedrijf ${i} B.V.`, searchname: `Bedrijf ${i} B.V.`, customernumber: 1000000 + i, active: i % 50 !== 0, relationtype: 'COMPANY', companyroles: i % 3 ? ['CUSTOMER'] : ['SUPPLIER'], visitingaddress_city: 'Zwolle', tags: i === 1 ? [ref(1, '2000')] : [], accountmanager: ref(100, 'Jan Jansen'), files: i === 1 ? [ref(9001, 'kvk.pdf')] : [], createdon: d('2025-01-01') });
  for (let i = 1; i <= 40; i++) data.contact.push({ id: i, firstname: 'Piet', lastname: `Persoon ${i}`, company: ref(i, `Bedrijf ${i} B.V.`), email: `p${i}@example.test`, active: true });
  data.product.push({ id: 42008, number: 42008, name: 'PM5110 Power Meter', searchname: 'PM5110', sellingprice: 2740, buyingprice: 1790, unit: ref(1, 'stuks'), invoicebasis: 'FIXED' });
  for (let i = 1; i <= 30; i++) {
    const id = 500 + i;
    data.offer.push({ id, number: 890300 + i, name: `Offerte compactstation ${i}`, company: ref(i, `Bedrijf ${i} B.V.`), phase: ref(1 + (i % 6), ''), totalexclvat: 10000 * i, totalinclvat: 12100 * i, chance: 50, status: i % 7 ? 'SENT' : 'CONCEPT', acceptancestatus: i % 5 === 0 ? 'ACCEPTED' : null, archived: i % 11 === 0, date: d('2026-09-01'), description: '<p>Voorbeeld B.V. <b>verzorgt</b> het traject<script>alert(1)</script><img src="https://evil.test/x.png"></p>', customfield_levertijd: '12 weken', directpdfurl: `__BASE__/pdf/offer-${id}`, employees: [ref(100, 'Jan')] });
    data.offerprojectline.push({ id: 10000 + i, offerprojectbase: ref(id, ''), rowtype: 'GROUP', additionalsubject: 'Compactstation', amount: 1, sellingprice: 0, discount: 0, _ordering: 0 });
    data.offerprojectline.push({ id: 11000 + i, offerprojectbase: ref(id, ''), product: ref(42008, 'PM5110'), amount: 2, sellingprice: 2740, discount: 0, buyingprice: 1790, unit: ref(1, 'stuks'), description: 'Meter klasse 0,5S', invoicebasis: 'FIXED', _ordering: 1 });
  }
  for (let i = 1; i <= 12; i++) {
    const id = 900 + i;
    data.project.push({ viewonlineurl: `__BASE__/view/${id}`, id, number: 890190 + i, name: `Opdracht ${i}`, company: ref(i, `Bedrijf ${i} B.V.`), phase: ref(1 + (i % 6), ''), totalexclvat: 50000, totalinclvat: 60500, archived: false });
    data.offerprojectline.push({ id: 12000 + i, offerprojectbase: ref(id, ''), product: ref(42008, 'PM5110'), amount: 10, sellingprice: 5000, discount: 0, _ordering: 1 });
    data.task.push({ id: i, number: 1000 + i, content: `Taak ${i} inplannen`, company: ref(i, ''), offerprojectbase: ref(id, ''), isafgerond: i % 2 === 0 });
    data.hour.push({ id: i, date: d(`2026-0${1 + (i % 9)}-10`), employee: ref(100, 'Jan'), offerprojectbase: ref(id, ''), amount: 8, status: 'DEFINITIVE' });
  }
  for (let i = 1; i <= 71; i++) {
    const open = i % 4 === 0 ? 1210 : 0;
    data.invoice.push({ id: 2000 + i, number: 200440 + i, company: ref(1 + (i % 20), ''), status: i === 71 ? 'CONCEPT' : 'SENT', subject: `Factuur ${i}`, date: d(`2026-0${1 + (i % 9)}-01`), reportdate: d(`2026-0${1 + (i % 9)}-01`), expirydate: d(`2026-0${1 + (i % 9)}-15`), totalinclvat: 1210, totalincldiscountexclvat: 1000, totalopeninclvat: open, totalpayed: 1210 - open, directpdfurl: i <= 3 ? `__BASE__/pdf/${2000 + i}` : null });
    data.invoiceline.push({ id: 3000 + i, invoice: ref(2000 + i, ''), product: ref(42008, ''), amount: 1, sellingprice: 1000, discount: 0, project: i <= 12 ? ref(900 + i, '') : null, ledger: ref(8000, '') });
    if (!open) data.payment.push({ id: 4000 + i, invoice: ref(2000 + i, ''), amount: 1210, date: d('2026-10-01') });
  }
  for (let i = 1; i <= 25; i++) {
    data.purchaseinvoice.push({ id: 6000 + i, number: `INK-${i}`, bookingnumber: 1200 + i, company: ref(600 - i, ''), phase: ['CONCEPT', 'CHECK', 'AGREED', 'PROCESSED', 'ONHOLD'][i % 5], totalinclvat: 500, totalopeninclvat: i % 2 ? 500 : 0, date: d('2026-09-21'), expirydate: d('2026-07-21'), files: i <= 2 ? [ref(9001 + i, `factuur-${i}.pdf`)] : [] });
    data.purchaseinvoiceline.push({ id: 7000 + i, purchaseinvoice: ref(6000 + i, ''), amount: 1, sellingprice: 413.22, discount: 0, ledger: ref(4219, ''), project: ref(901, '') });
  }
  for (let i = 1; i <= 8; i++) {
    data.purchaseorder.push({ id: 8000 + i, number: 202505170 + i, name: `Transport ${i}`, supplier: ref(3 * i, ''), company: ref(3 * i, ''), purchaseorderstatus: 'SENT', totalincldiscountexclvat: 995, date: d('2026-09-23') });
    data.purchaseorderline.push({ id: 8500 + i, purchaseorder: ref(8000 + i, ''), amount: 1, sellingprice: 995, discount: 0, project: ref(901, ''), description: 'Transport en plaatsing' });
  }
  for (let i = 1; i <= 300; i++) data.timelineentry.push({ id: i, company: ref(1 + (i % 30), ''), offer: i % 3 === 0 ? ref(500 + (i % 30) + 1, '') : null, timelinetype: i % 2 ? 'USERMESSAGE' : 'RECEIVEDEMAIL', date: d('2026-09-22'), message: `<p>Bericht ${i} @jan_jansen</p>`, employee: ref(103, 'Anna'), files: i === 3 ? [ref(9010, 'mail.pdf')] : [] });
  // gebroken verwijzing (record wijst naar niet-bestaand bedrijf) → moet in rapport verschijnen
  data.contact.push({ id: 999, firstname: 'Wees', lastname: 'Contact', company: ref(99999, 'bestaat niet') });
  return data;
}

const PDF = Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<<>>\n%%EOF');

export function startMock({ port = 0, failEvery = 7, rateLimit = 1000, deny = [], maintenance = false } = {}) {
  const data = makeData();
  const stats = { requests: 0, calls: 0, writeAttempts: 0, methods: new Set() };
  let remaining = rateLimit;
  const server = http.createServer(async (req, res) => {
    const base = `http://127.0.0.1:${server.address().port}`;
    if (req.method === 'GET' && req.url.startsWith('/view/')) { res.writeHead(200, { 'Content-Type': 'text/html' }); return res.end(`<html><body><h1>Opdrachtbevestiging</h1><a href="/pdf/doc-${req.url.slice(6)}?type=pdf&amp;download=1">Download PDF</a></body></html>`); }
    if (req.method === 'GET' && req.url.startsWith('/pdf/')) { res.writeHead(200, { 'Content-Type': 'application/pdf' }); return res.end(PDF); }
    if (req.method !== 'POST') { res.writeHead(405); return res.end(); }
    // Zoals Gripp tijdens een storing: doorsturen naar de statuspagina van Exact
    if (maintenance) { stats.redirects = (stats.redirects ?? 0) + 1; res.writeHead(301, { Location: 'https://status.example.test:443/' }); return res.end(); }
    if (req.headers.authorization !== 'Bearer test-token') { res.writeHead(401); return res.end('unauthorized'); }
    stats.requests++;
    if (failEvery && stats.requests % failEvery === 0) { res.writeHead(503); return res.end('busy'); }
    let body = ''; for await (const c of req) body += c;
    const calls = JSON.parse(body);
    remaining -= calls.length; stats.calls += calls.length;
    const out = calls.map(({ method, params, id }) => {
      stats.methods.add(method);
      const [ent, m] = method.split('.');
      if (m !== 'get' && method !== 'file.getContent') { stats.writeAttempts++; return { id, error: 'write not allowed in mock' }; }
      if (deny.includes(ent)) return { id, error: `Insufficient rights. Please check you API-role settings to see if you have "read" privileges for entity "nl-${ent}".` };   // zoals Gripp
      if (method === 'file.getContent') return { id, result: { name: `bijlage-${params[0]}.pdf`, content: PDF.toString('base64') } };
      const rows = (data[ent] ?? []).map((r) => JSON.parse(JSON.stringify(r).replaceAll('__BASE__', base)));
      const { firstresult = 0, maxresults = 250 } = params?.[1]?.paging ?? {};
      if (maxresults > 250) return { id, error: 'maxresults too large' };
      const page = rows.slice(firstresult, firstresult + maxresults);
      return { id, result: { rows: page, count: rows.length, start: firstresult, limit: maxresults, next_start: firstresult + page.length, more_items_in_collection: firstresult + page.length < rows.length } };
    });
    // Zoals Gripp: batch = één transactie. Faalt één call, dan alleen die fout terug, geen andere antwoorden.
    const failed = out.find((o) => o.error);
    const body2 = failed && out.length > 1 ? [failed] : out;
    res.writeHead(200, { 'Content-Type': 'application/json', 'X-RateLimit-Limit': String(rateLimit), 'X-RateLimit-Remaining': String(Math.max(0, remaining)) });
    res.end(JSON.stringify(body2));
  });
  return new Promise((resolve) => server.listen(port, '127.0.0.1', () => resolve({ server, stats, data, url: `http://127.0.0.1:${server.address().port}/public/api3.php` })));
}
