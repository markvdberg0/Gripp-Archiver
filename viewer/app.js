/* Gripp-archief viewer — read-only, zonder server, zonder externe bronnen.
   Eigen implementatie (clean-room), los van Gripp. */
(function () {
  'use strict';
  const D = window.GRIPP_DATA;
  const app = document.getElementById('app');
  const sidebar = document.getElementById('sidebar');

  // ---------------------------------------------------------------- utils
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const refId = (v) => (v && typeof v === 'object' ? (v.id ?? null) : (v === '' || v === 0 ? null : v ?? null));
  const arr = (v) => (v == null ? [] : Array.isArray(v) ? v : [v]);
  const num = (v) => (v == null || v === '' ? null : Number(v));
  const nlNum = new Intl.NumberFormat('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const eur = (v) => { const n = num(v); if (n == null || isNaN(n)) return ''; return `<span class="${n < 0 ? 'neg' : ''}">€${nlNum.format(n)}</span>`; };
  const eurTxt = (v) => (num(v) == null ? '' : '€' + nlNum.format(num(v)));
  const dateStr = (v) => { if (!v) return ''; if (typeof v === 'object') v = v.date ?? ''; return String(v).slice(0, 19); };
  const fdate = (v) => { const s = dateStr(v); const m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/); if (!m) return esc(s); return `${m[3]}-${m[2]}-${m[1]}${m[4] && (m[4] !== '00' || m[5] !== '00') ? ` ${m[4]}:${m[5]}` : ''}`; };
  const ymd = (v) => dateStr(v).slice(0, 10);
  const today = new Date().toISOString().slice(0, 10);
  const isHtml = (s) => /<\/?[a-z][\s\S]*>/i.test(String(s ?? ''));
  const trunc = (s, n) => { s = String(s ?? ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; };
  const plain = (s) => { if (!isHtml(s)) return String(s ?? ''); const d = new DOMParser().parseFromString(String(s), 'text/html'); return d.body.textContent || ''; };

  // HTML uit Gripp (e-mails, omschrijvingen) veilig tonen: geen scripts, geen externe bronnen.
  function sanitize(html) {
    const doc = new DOMParser().parseFromString(String(html ?? ''), 'text/html');
    const DROP = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'LINK', 'META', 'FORM', 'INPUT', 'BUTTON', 'TEXTAREA', 'SELECT', 'BASE', 'SVG', 'MATH', 'VIDEO', 'AUDIO', 'SOURCE', 'FRAME', 'FRAMESET']);
    const walk = (node) => {
      for (const el of [...node.children]) {
        if (DROP.has(el.tagName)) { el.remove(); continue; }
        for (const a of [...el.attributes]) {
          const n = a.name.toLowerCase();
          if (n.startsWith('on') || n === 'style' || n === 'srcset' || n === 'background' || n === 'id' || n === 'class') el.removeAttribute(a.name);
        }
        if (el.tagName === 'IMG') {
          const src = el.getAttribute('src') || '';
          if (!/^data:image\//i.test(src)) { el.replaceWith(doc.createTextNode('[afbeelding]')); continue; }
        }
        if (el.tagName === 'A') {
          const href = el.getAttribute('href') || '';
          if (/^(https?:|mailto:|tel:)/i.test(href)) { el.setAttribute('target', '_blank'); el.setAttribute('rel', 'noopener noreferrer'); }
          else el.removeAttribute('href');
        }
        walk(el);
      }
    };
    walk(doc.body);
    return doc.body.innerHTML;
  }
  const rich = (s) => (s == null || s === '' ? '' : isHtml(s) ? `<div class="rich">${sanitize(s)}</div>` : `<div class="rich">${esc(s).replace(/\n/g, '<br>')}</div>`);

  if (!D) {
    app.innerHTML = `<div class="wide"><div class="card"><div class="card-h">Nog geen archiefdata</div><div class="card-b">
      <p>Het bestand <code>viewer/data/data.js</code> ontbreekt. Draai eerst de import:</p>
      <pre class="raw">npm run import</pre><p class="muted">Zie README.md voor de stappen.</p></div></div></div>`;
    return;
  }

  // ---------------------------------------------------------------- indexes
  const E = D.entities || {};
  const REFS = D.refs || {};
  // Gripp levert keuzelijsten als { id, searchname: '<Nederlandse naam>' }. Zet om naar vaste codes,
  // zodat filters en segmenten werken; de originele waarden blijven zichtbaar bij "Brongegevens".
  const CODE = { Verzonden: 'SENT', Concept: 'CONCEPT', Geaccepteerd: 'ACCEPTED', Afgewezen: 'REJECTED', Actief: 'ACTIVE', Aflopend: 'ENDING', 'Beëindigd': 'ENDED',
    Afgerond: 'COMPLETED', Verwerkt: 'PROCESSED', Akkoord: 'AGREED', Controle: 'CHECK', 'On-hold': 'ONHOLD', Gefiatteerd: 'AUTHORIZED', Definitief: 'DEFINITIVE',
    Aangevraagd: 'PENDING', Goedgekeurd: 'APPROVED', Afgekeurd: 'REJECTED', Klant: 'CUSTOMER', Leverancier: 'SUPPLIER', Lead: 'LEAD', Prospect: 'PROSPECT',
    Bedrijf: 'COMPANY', Particulier: 'PRIVATEPERSON', Persoon: 'PRIVATEPERSON', NORMAAL: 'NORMAL', GROEPLABEL: 'GROUP', Groep: 'GROUP', Normaal: 'NORMAL',
    Fixed: 'FIXED', Vast: 'FIXED', Nacalculatie: 'COSTING', Budget: 'BUDGETED', 'Niet factureerbaar': 'NONBILLABLE', Overboeking: 'MANUALTRANSFER', Incasso: 'AUTOMATICTRANSFER',
    'Iedere maand': 'EVERYMONTH', 'Ieder kwartaal': 'EVERYQUARTER', 'Ieder jaar': 'EVERYYEAR', 'Iedere week': 'EVERYWEEK' };
  const ENUM_FIELDS = new Set(['status', 'acceptancestatus', 'purchaseorderstatus', 'relationtype', 'companyroles', 'frequency', 'timelinetype', 'rowtype', 'invoicebasis', 'paymentmethod', 'paymentmethod_purchaseinvoice', 'salutation', 'postaddress', 'invoicesendto', 'invoicesendby', 'convertto', 'absencerequeststatus', 'leavetype', 'unittype']);
  const toCode = (v) => (v && typeof v === 'object' && !Array.isArray(v) && 'searchname' in v ? CODE[v.searchname] ?? v.searchname : v);
  for (const [ent, rows] of Object.entries(E)) {
    for (const r of rows) {
      const orig = {};
      for (const k of Object.keys(r)) {
        if (!(ENUM_FIELDS.has(k) || (ent === 'purchaseinvoice' && k === 'phase'))) continue;
        const v = r[k]; const nv = Array.isArray(v) ? v.map(toCode) : toCode(v);
        if (JSON.stringify(nv) !== JSON.stringify(v)) { orig[k] = v; r[k] = nv; }
      }
      if (Object.keys(orig).length) Object.defineProperty(r, '__orig', { value: orig, enumerable: false });
    }
  }
  const BY = {};
  for (const [n, rows] of Object.entries(E)) { BY[n] = new Map(); for (const r of rows) BY[n].set(String(r.id), r); }
  const get = (ent, id) => (id == null ? null : BY[ent]?.get(String(id)) ?? null);
  const DISCR = { bedrijf: 'company', contactpersoon: 'contact', medewerker: 'employee', offerte: 'offer', opdracht: 'project' };
  const resolveTarget = (target, id, v) => {
    if (v && typeof v === 'object' && v.discr) { const t = DISCR[v.discr]; return t && get(t, id) ? t : null; }
    if (target !== '@offerproject') return get(target, id) ? target : null;
    if (get('offer', id)) return 'offer';
    if (get('project', id)) return 'project';
    return null;
  };
  // reverse: REV[target][id] = { entity: Set(rowId) }
  const REV = {};
  const EMBED = /lines$|^payments$|^calendaritems$/;
  for (const [ent, refs] of Object.entries(REFS)) {
    for (const r of E[ent] || []) {
      for (const [field, target] of Object.entries(refs)) {
        if (EMBED.test(field) || field === 'files' || field === 'tags') continue;
        for (const v of arr(r[field])) {
          const id = refId(v); if (id == null) continue;
          const t = resolveTarget(target, id, v) || (target === '@offerproject' || (v && v.discr) ? null : target); if (!t) continue;
          ((REV[t] ??= {})[id] ??= {});
          (REV[t][id][ent] ??= new Set()).add(String(r.id));
        }
      }
    }
  }
  const related = (ent, id, other) => [...(REV[ent]?.[id]?.[other] ?? [])].map((rid) => get(other, rid)).filter(Boolean);

  // label voor een verwijzing
  function refLabel(v, target) {
    const id = refId(v); if (id == null) return '';
    const t = resolveTarget(target, id, v);
    const rec = t ? get(t, id) : null;
    if (rec) return CFG[t]?.title ? CFG[t].title(rec) : rec.searchname ?? rec.name ?? `#${id}`;
    return (v && typeof v === 'object' && v.searchname) || `#${id}`;
  }
  function refLink(v, target) {
    const id = refId(v); if (id == null) return '';
    const t = resolveTarget(target, id, v);
    const label = esc(refLabel(v, target));
    return t && ROUTE[t] ? `<a href="#/${t}/${encodeURIComponent(id)}">${label}</a>` : label;
  }
  const tags = (r) => arr(r.tags).map((t) => `<span class="tag">${esc(refLabel(t, 'tag'))}</span>`).join('');
  const emps = (r, f = 'employees') => arr(r[f]).map((t) => `<span class="tag grey">${esc(trunc(refLabel(t, 'employee'), 18))}</span>`).join('');
  const filesOf = (r) => ['files', 'extrapdf1', 'extrapdf2', 'attachments_internal', 'attachments_external', 'userphoto'].flatMap((f) => arr(r[f]).map(refId)).filter(Boolean);
  const fileLink = (id) => { const f = D.files?.[id]; return f ? `<a href="../${encodeURI(f.path)}" target="_blank" rel="noopener">${esc(f.name)}</a> <span class="muted">${(f.size / 1024).toFixed(0)} kB</span>` : `<span class="muted">bestand #${esc(id)} (niet gearchiveerd)</span>`; };
  const phaseName = (tbl, v) => { const p = get(tbl, refId(v)); return p ? p.name ?? p.searchname : (v && v.searchname) || ''; };

  // ---------------------------------------------------------------- entity config
  const enumNL = {
    CONCEPT: 'Concept', SENT: 'Verzonden', COMPLETED: 'Afgerond', ACCEPTED: 'Geaccepteerd', REJECTED: 'Afgewezen',
    ACTIVE: 'Actief', ENDING: 'Aflopend', ENDED: 'Beëindigd', CHECK: 'Controle', AGREED: 'Akkoord', PROCESSED: 'Verwerkt', ONHOLD: 'On-hold',
    DEFINITIVE: 'Definitief', AUTHORIZED: 'Gefiatteerd', PENDING: 'Aangevraagd', APPROVED: 'Goedgekeurd',
    CUSTOMER: 'Klant', SUPPLIER: 'Leverancier', LEAD: 'Lead', PROSPECT: 'Prospect', COMPANY: 'Bedrijf', PRIVATEPERSON: 'Particulier',
    MANUALTRANSFER: 'Handmatige overboeking', AUTOMATICTRANSFER: 'Automatische incasso', FIXED: 'Vast', COSTING: 'Nacalculatie', BUDGETED: 'Budget', NONBILLABLE: 'Niet factureerbaar',
    EVERYMONTH: 'Iedere maand', EVERYQUARTER: 'Ieder kwartaal', EVERYYEAR: 'Ieder jaar', EVERYWEEK: 'Iedere week',
    USERMESSAGE: 'Notitie', SYSTEMMESSAGE: 'Systeembericht', SENTEMAIL: 'Verzonden e-mail', RECEIVEDEMAIL: 'Ontvangen e-mail',
  };
  const en = (v) => (v == null ? '' : enumNL[v] ?? (typeof v === 'object' ? v.searchname ?? '' : v));

  const docBtn = (ent, r) => { const d = D.docs?.[ent]?.[r.id]; return d ? [`<a class="side-btn" href="../${encodeURI(d)}" target="_blank" rel="noopener">📄 ${ent === 'offer' ? 'Offerte' : ent === 'project' ? 'Opdrachtbevestiging' : 'Inkoopopdracht'}-PDF openen</a>`] : []; };
  const invOpen = (r) => (num(r.totalopeninclvat) ?? 0) > 0.005;
  const pinvOpen = (r) => (typeof r.isopenstaand === 'boolean' ? r.isopenstaand : (num(r.totalopeninclvat) ?? 0) > 0.005);
  const invOverdue = (r) => invOpen(r) && r.status === 'SENT' && ymd(r.expirydate) && ymd(r.expirydate) < today;
  const companyRoles = (r) => arr(r.companyroles).map((x) => (typeof x === 'object' ? x.id ?? x.searchname : x));

  const COL = {
    main: (ent, sub) => ({ h: CFG[ent].single, cls: 'cell-main', v: (r) => `<a class="t" href="#/${ent}/${r.id}">${esc(CFG[ent].title(r))}</a>${sub ? `<div class="s">${sub(r)}</div>` : ''}`, s: (r) => CFG[ent].title(r) }),
    company: { h: 'Relatie', v: (r) => refLink(r.company, 'company'), s: (r) => refLabel(r.company, 'company') },
    number: { h: 'Nummer', v: (r) => esc(r.number ?? ''), s: (r) => num(r.number) ?? 0 },
    date: (f = 'date', h = 'Datum') => ({ h, v: (r) => fdate(r[f]), s: (r) => ymd(r[f]) }),
    money: (f, h) => ({ h, num: true, v: (r) => eur(r[f]), s: (r) => num(r[f]) ?? 0 }),
  };

  const CFG = {
    company: {
      label: 'Relaties', single: 'Relatie', icon: '🏢',
      title: (r) => r.companyname || [r.firstname, r.infix, r.lastname].filter(Boolean).join(' ') || r.searchname || `Relatie ${r.id}`,
      number: (r) => r.customernumber, pill: (r) => (r.relationtype === 'PRIVATEPERSON' ? ['Particulier', 'orange'] : ['Bedrijf', 'purple']),
      segments: [
        ['Actief', (r) => r.active !== false], ['Klanten', (r) => companyRoles(r).includes('CUSTOMER')], ['Leveranciers', (r) => companyRoles(r).includes('SUPPLIER')],
        ['Leads', (r) => companyRoles(r).includes('LEAD')], ['Prospects', (r) => companyRoles(r).includes('PROSPECT')], ['Alle', () => true], ['Niet actief', (r) => r.active === false]],
      columns: () => [
        { h: '', v: (r) => `<span class="dot" style="background:var(--c-${r.relationtype === 'PRIVATEPERSON' ? 'orange' : 'purple'})"></span>` },
        COL.main('company', (r) => esc([r.visitingaddress_street, r.visitingaddress_streetnumber].filter(Boolean).join(' ')) + '<br>' + esc([r.visitingaddress_zipcode, r.visitingaddress_city].filter(Boolean).join(' ')) + (r.accountmanager ? ` <span class="tag grey">${esc(refLabel(r.accountmanager, 'employee'))}</span>` : '')),
        { h: 'Nummer', v: (r) => esc(r.customernumber ?? ''), s: (r) => num(r.customernumber) ?? 0 },
        { h: 'Contact', v: (r) => [r.phone && `<a href="tel:${esc(r.phone)}">${esc(r.phone)}</a>`, r.email && `<a href="mailto:${esc(r.email)}">${esc(r.email)}</a>`, r.website && esc(r.website)].filter(Boolean).join('<br>') },
        { h: 'Rollen', v: (r) => companyRoles(r).map((x) => `<span class="tag grey">${esc(en(x))}</span>`).join('') }],
      sections: (r) => [
        ['Status', [['Rollen', companyRoles(r).map(en).join(', ')], ['Actief', yn(r.active)], ['Type', en(r.relationtype)]]],
        ['Relatie', [['Bedrijfsnaam', r.companyname], ['Statutaire naam', r.legalname], ['Naam (persoon)', [r.salutation && en(r.salutation), r.initials, r.firstname, r.infix, r.lastname].filter(Boolean).join(' ')]]],
        ['Contactgegevens', [['Telefoon', r.phone], ['Mobiel', r.mobile], ['E-mail', r.email], ['Website', r.website]]],
        ['Accountmanager', [['Medewerker', { html: refLink(r.accountmanager, 'employee') }]]],
        ['Tags', [['', { html: tags(r) }]]],
        ['Bezoekadres', [['Straat', [r.visitingaddress_street, r.visitingaddress_streetnumber].filter(Boolean).join(' ')], ['Adresregel 2', r.visitingaddress_addressline2], ['Postcode', r.visitingaddress_zipcode], ['Plaats', r.visitingaddress_city], ['Land', r.visitingaddress_country]]],
        ['Postadres', [['Type', en(r.postaddress)], ['Straat', [r.postaddress_street, r.postaddress_streetnumber].filter(Boolean).join(' ')], ['Postcode', r.postaddress_zipcode], ['Plaats', r.postaddress_city], ['Land', r.postaddress_country]]],
        ['Factuuradres', [['Versturen naar', en(r.invoicesendto)], ['Bedrijf', r.invoiceaddress_companyname], ['T.a.v.', r.invoiceaddress_attn], ['Straat', [r.invoiceaddress_street, r.invoiceaddress_streetnumber].filter(Boolean).join(' ')], ['Postcode', r.invoiceaddress_zipcode], ['Plaats', r.invoiceaddress_city], ['Land', r.invoiceaddress_country]]],
        ['Financieel', [['Relatienummer', r.customernumber], ['KvK', r.cocnumber], ['BTW-nummer', r.vatnumber], ['BTW verlegd', yn(r.vatshifted)], ['Oprichtingsdatum', { html: fdate(r.foundationdate) }], ['Externe referentie', r.externalreference]]],
        ['Bank', [['IBAN', r.bankaccount], ['Tenaamstelling', r.bankascription], ['BIC', r.bic], ['Plaats', r.bankcity]]],
        ['Facturatie', [['Verzenden per', en(r.invoicesendby)], ['E-mail facturen', r.invoiceemail], ['Betalingstermijn', r.termofpayment], ['Betalingswijze', en(r.paymentmethod)], ['Betalingstermijn inkoop', r.termofpayment_purchaseinvoice]]],
        ['Notities', [['', { html: rich(r.notes) }]]]],
    },
    contact: {
      label: 'Contactpersonen', single: 'Contactpersoon', icon: '👤',
      title: (r) => [r.firstname, r.infix, r.lastname].filter(Boolean).join(' ') || r.searchname || `Contact ${r.id}`,
      pill: () => ['Persoon', 'orange'], sub: (r) => refLink(r.company, 'company'),
      segments: [['Actief', (r) => r.active !== false], ['In-actief', (r) => r.active === false], ['Alle', () => true]],
      columns: () => [COL.main('contact', (r) => esc(r.function ?? '')), COL.company, { h: 'Contact', v: (r) => [r.phone, r.mobile, r.email].filter(Boolean).map(esc).join('<br>') }],
      sections: (r) => [
        ['Bedrijf', [['', { html: refLink(r.company, 'company') }]]],
        ['Naam', [['Aanhef', en(r.salutation)], ['Initialen', r.initials], ['Titel', r.title], ['Voornaam', r.firstname], ['Tussenvoegsel', r.infix], ['Achternaam', r.lastname], ['Actief', yn(r.active)], ['Tonen op relatiekaart', yn(r.showoncompanycard)]]],
        ['Contactgegevens', [['Vast', r.phone], ['Mobiel', r.mobile], ['E-mail', r.email]]],
        ['Werk', [['Functie', r.function], ['Afdeling', r.department]]], ['Geboortedatum', [['', { html: fdate(r.dateofbirth) }]]],
        ['Tags', [['', { html: tags(r) }]]], ['Notities', [['', { html: rich(r.notes) }]]]],
    },
    offer: {
      docs: (r) => docBtn('offer', r),
      label: 'Offertes', single: 'Offerte', icon: '📄',
      title: (r) => r.name || r.subject || r.searchname || `Offerte ${r.number ?? r.id}`, number: (r) => r.number,
      pill: (r) => (r.archived ? ['Archief', 'grey'] : r.acceptancestatus === 'ACCEPTED' ? ['Geaccepteerd', 'green'] : r.acceptancestatus === 'REJECTED' ? ['Afgewezen', 'red'] : r.status === 'CONCEPT' ? ['Concept', 'grey'] : ['Openstaand', 'orange']),
      sub: (r) => refLink(r.company, 'company'), phases: (r) => phaseSteps('offerphase', r.phase),
      segments: [['Openstaand', (r) => !r.archived && !r.acceptancestatus && !r.isbasis && r.status === 'SENT'], ['Geaccepteerd', (r) => r.acceptancestatus === 'ACCEPTED' && !r.isbasis], ['Afgewezen', (r) => r.acceptancestatus === 'REJECTED' && !r.isbasis], ['Archief', (r) => !!r.archived], ['Alle', (r) => !r.isbasis], ['Concept', (r) => r.status === 'CONCEPT' && !r.isbasis], ['Basis', (r) => !!r.isbasis]],
      columns: () => [pillCol('offer'), COL.main('offer', (r) => esc(refLabel(r.company, 'company')) + (r.clientreference ? `<br>Klantreferentie: ${esc(r.clientreference)}` : '') + '<br>' + tags(r) + emps(r)), COL.number, { h: 'Fase', v: (r) => esc(phaseName('offerphase', r.phase)) }, COL.date('date', 'Offertedatum'), COL.money('totalexclvat', 'Waarde ex. BTW')],
      totals: (r) => [['Totaal ex. BTW', r.totalexclvat, true], ['Totaal inc. BTW', r.totalinclvat], ['Slagingskans', r.chance != null ? r.chance + '%' : null, false, true]],
      sections: (r) => [
        ['Opdrachtgever', [['Bedrijf / persoon', { html: refLink(r.company, 'company') }], ['Contactpersoon', { html: refLink(r.contact, 'contact') }], ['Project / retainer', { html: refLink(r.umbrellaproject, 'umbrellaproject') }]]],
        ['Offerte', [['Onderwerp', r.name || r.subject], ['Nummer', r.number], ['Offertedatum', { html: fdate(r.date) }], ['Deadline uitvoering', { html: fdate(r.deadline) }], ['Referentie klant', r.clientreference], ['Slagingskans', r.chance != null ? r.chance + '%' : ''], ['Fase', phaseName('offerphase', r.phase)], ['Status', en(r.status)], ['Acceptatie', en(r.acceptancestatus)], ['Geaccepteerd op', { html: fdate(r.acceptedon) }], ['Gearchiveerd op', { html: fdate(r.archivedon) }]]],
        ['Medewerkers', [['Accountmanager', { html: refLink(r.accountmanager, 'employee') }], ['Verkoopmedewerker', { html: refLink(r.salesassociate, 'employee') }], ['Team', { html: emps(r) }]]],
        ['Bestemming', [['', { html: rich(r.workdeliveraddress) }]]], ['Omschrijving', [['', { html: rich(r.description) }]]],
        ['Tags', [['', { html: tags(r) }]]], ...customFields(r)],
    },
    project: {
      docs: (r) => docBtn('project', r),
      label: 'Opdrachten (verkoop)', single: 'Opdracht', icon: '📋',
      title: (r) => r.name || r.searchname || `Opdracht ${r.number ?? r.id}`, number: (r) => r.number,
      pill: (r) => (r.archived ? ['Archief', 'grey'] : r.enddate ? ['Afgerond', 'purple'] : ['Lopend', 'orange']),
      sub: (r) => refLink(r.company, 'company'), phases: (r) => phaseSteps('projectphase', r.phase),
      segments: [['Lopend', (r) => !r.archived && !r.isbasis && !r.enddate && !(ymd(r.startdate) > today)], ['Toekomst', (r) => !r.archived && !r.isbasis && ymd(r.startdate) > today], ['Afgerond', (r) => !!r.enddate && !r.archived], ['Archief', (r) => !!r.archived], ['Alle', (r) => !r.isbasis && !r.archived], ['Basis', (r) => !!r.isbasis]],
      columns: () => [pillCol('project'), COL.main('project', (r) => esc(refLabel(r.company, 'company')) + (r.clientreference ? `<br>Klantreferentie: ${esc(r.clientreference)}` : '') + '<br>' + tags(r) + emps(r)), COL.number, { h: 'Fase', v: (r) => esc(phaseName('projectphase', r.phase)) }, COL.date('deadline', 'Deadline'), COL.money('totalexclvat', 'Waarde ex. BTW')],
      totals: (r) => { const inv = related('project', r.id, 'invoiceline'); const invoiced = inv.reduce((a, l) => a + (num(l.amount) ?? 0) * (num(l.sellingprice) ?? 0) * (1 - (num(l.discount) ?? 0) / 100), 0); return [['Totaal ex. BTW', r.totalexclvat, true], ['Totaal inc. BTW', r.totalinclvat], ['Gefactureerd ex. BTW (regels)', invoiced], ['Nog te factureren', (num(r.totalexclvat) ?? 0) - invoiced]]; },
      sections: (r) => [
        ['Opdrachtgever', [['Bedrijf / persoon', { html: refLink(r.company, 'company') }], ['Contactpersoon', { html: refLink(r.contact, 'contact') }], ['Project / retainer', { html: refLink(r.umbrellaproject, 'umbrellaproject') }]]],
        ['Opdracht', [['Onderwerp', r.name], ['Nummer', r.number], ['Referentie klant', r.clientreference], ['Aanvangdatum', { html: fdate(r.startdate) }], ['Deadline', { html: fdate(r.deadline) }], ['Leverdatum', { html: fdate(r.deliverydate) }], ['Afgerond op', { html: fdate(r.enddate) }], ['Fase', phaseName('projectphase', r.phase)], ['Urenspecificatie', yn(r.addhoursspecification)]]],
        ['Medewerkers', [['Accountmanager', { html: refLink(r.accountmanager, 'employee') }], ['Team', { html: emps(r) }]]],
        ['Bestemming', [['', { html: rich(r.workdeliveraddress) }]]], ['Omschrijving', [['', { html: rich(r.description) }]]],
        ['Tags', [['', { html: tags(r) }]]], ...customFields(r)],
    },
    purchaseorder: {
      docs: (r) => docBtn('purchaseorder', r),
      label: 'Inkoopopdrachten', single: 'Inkoopopdracht', icon: '🛒',
      title: (r) => r.name || r.searchname || `Inkoopopdracht ${r.number ?? r.id}`, number: (r) => r.number,
      pill: (r) => (r.archived ? ['Archief', 'grey'] : r.purchaseorderstatus === 'COMPLETED' ? ['Afgerond', 'purple'] : r.purchaseorderstatus === 'CONCEPT' ? ['Concept', 'grey'] : ['Lopend', 'orange']),
      sub: (r) => refLink(r.supplier ?? r.company, 'company'),
      segments: [['Openstaand', (r) => !r.archived && r.purchaseorderstatus === 'SENT'], ['Verlopen', (r) => !r.archived && r.purchaseorderstatus === 'SENT' && ymd(r.deadline) && ymd(r.deadline) < today], ['Afgerond', (r) => r.purchaseorderstatus === 'COMPLETED'], ['Archief', (r) => !!r.archived], ['Alle', () => true], ['Concept', (r) => r.purchaseorderstatus === 'CONCEPT']],
      columns: () => [pillCol('purchaseorder'), COL.main('purchaseorder', (r) => esc(refLabel(r.supplier ?? r.company, 'company')) + '<br>' + emps(r)), { h: 'PO-nummer', v: (r) => esc(r.number ?? ''), s: (r) => num(r.number) ?? 0 }, { h: 'Status', v: (r) => esc(en(r.purchaseorderstatus)) }, COL.date(), COL.money('totalincldiscountexclvat', 'Waarde ex. BTW')],
      totals: (r) => [['Totaal excl. BTW', r.totalincldiscountexclvat, true]],
      sections: (r) => [
        ['Leverancier', [['Bedrijf / persoon', { html: refLink(r.supplier ?? r.company, 'company') }], ['Contactpersoon', { html: refLink(r.contact, 'contact') }], ['PO-nummer', r.number], ['Onderwerp', r.name], ['Referentie leverancier', r.clientreference], ['Opdrachtdatum', { html: fdate(r.date) }], ['Deadline', { html: fdate(r.deadline) }], ['Status', en(r.purchaseorderstatus)]]],
        ['Behoort bij opdracht(en)', [['', { html: [...new Set(related('purchaseorder', r.id, 'purchaseorderline').map((l) => refId(l.project)).filter(Boolean))].map((id) => refLink(id, '@offerproject')).join('<br>') }]]],
        ['Medewerkers', [['Accountmanager', { html: refLink(r.accountmanager, 'employee') }], ['Team', { html: emps(r) }]]],
        ['Bestemming', [['', { html: rich(r.workdeliveraddress) }]]], ['Omschrijving', [['', { html: rich(r.description) }]]], ['Tags', [['', { html: tags(r) }]]], ...customFields(r)],
    },
    invoice: {
      label: 'Verkoopfacturen', single: 'Factuur', icon: '€',
      title: (r) => `Factuur ${r.number ?? '(concept)'}`, number: (r) => r.number,
      pill: (r) => (r.status === 'CONCEPT' ? ['Concept', 'grey'] : invOverdue(r) ? ['Verlopen', 'red'] : invOpen(r) ? ['Openstaand', 'orange'] : ['Voldaan', 'green']),
      sub: (r) => refLink(r.company, 'company'),
      segments: [['Openstaand', (r) => r.status === 'SENT' && invOpen(r)], ['Verlopen', invOverdue], ['Voldaan', (r) => r.status === 'SENT' && !invOpen(r)], ['Alle', (r) => !r.isbasis], ['Concept', (r) => r.status === 'CONCEPT']],
      columns: () => [pillCol('invoice'), COL.main('invoice', (r) => esc(refLabel(r.company, 'company')) + '<br>' + esc(trunc(r.subject, 80)) + (r.clientreference ? `<br>Klantreferentie: ${esc(r.clientreference)}` : '') + '<br>' + tags(r)), COL.number, { h: 'Huidige fase', v: (r) => esc(typeof r.fase === 'string' ? r.fase : '') }, COL.date('date', 'Datum'), COL.date('expirydate', 'Vervaldatum'), COL.money('totalinclvat', 'Totaal incl. BTW'), COL.money('totalopeninclvat', 'Openstaand')],
      totals: (r) => [['Totaal excl. BTW', r.totalincldiscountexclvat], ['Totaal incl. BTW', r.totalinclvat, true], ['Betaald', r.totalpayed], ['Openstaand', r.totalopeninclvat, true]],
      docs: (r) => (D.invoicePdfs?.[r.id] ? [`<a class="side-btn" href="../${encodeURI(D.invoicePdfs[r.id])}" target="_blank" rel="noopener">📄 Factuur-PDF openen</a>`] : []),
      sections: (r) => [
        ['Klant', [['Bedrijf / persoon', { html: refLink(r.company, 'company') }], ['Contactpersoon', { html: refLink(r.contact, 'contact') }]]],
        ['Factuur', [['Nummer', r.number], ['Onderwerp', r.subject], ['Factuurdatum', { html: fdate(r.date) }], ['Rapportagedatum', { html: fdate(r.reportdate) }], ['Vervaldatum', { html: fdate(r.expirydate) }], ['Status', en(r.status)]]],
        ['Financieel', [['Betalingswijze', en(r.paymentmethod)], ['Referentie klant', r.clientreference], ['Urenspecificatie', yn(r.addhoursspecification)]]],
        ['Behoort bij opdracht(en)', [['', { html: [...new Set(related('invoice', r.id, 'invoiceline').map((l) => refId(l.project)).filter(Boolean))].map((id) => refLink(id, 'project')).join('<br>') }]]],
        ['Omschrijving', [['', { html: rich(r.description) }]]], ['Tags', [['', { html: tags(r) }]]], ...customFields(r)],
    },
    purchaseinvoice: {
      label: 'Inkoopfacturen', single: 'Inkoopfactuur', icon: '🧾',
      title: (r) => `Inkoopfactuur ${r.number ?? ''}`.trim(), number: (r) => r.bookingnumber,
      pill: (r) => (r.unprocessed ? ['Inbox', 'blue'] : [pinvOpen(r) ? (ymd(r.expirydate) && ymd(r.expirydate) < today ? 'Verlopen' : 'Openstaand') : 'Voldaan', pinvOpen(r) ? (ymd(r.expirydate) < today ? 'red' : 'orange') : 'green']),
      sub: (r) => `${refLink(r.company, 'company')}${r.bookingnumber ? ` · Boekstuk ${esc(r.bookingnumber)}` : ''}`,
      phases: (r) => ['CONCEPT', 'CHECK', 'ONHOLD', 'AGREED', 'PROCESSED'].map((p) => [en(p), p === r.phase]),
      segments: [['Openstaand', (r) => !r.unprocessed && pinvOpen(r)], ['Verlopen', (r) => !r.unprocessed && pinvOpen(r) && ymd(r.expirydate) && ymd(r.expirydate) < today], ['Voldaan', (r) => !r.unprocessed && !pinvOpen(r)], ['Alle', (r) => !r.unprocessed], ['Concept', (r) => !r.unprocessed && r.phase === 'CONCEPT'], ['Controle', (r) => !r.unprocessed && pinvOpen(r) && r.phase === 'CHECK'], ['On-hold', (r) => !r.unprocessed && pinvOpen(r) && r.phase === 'ONHOLD'], ['Akkoord', (r) => !r.unprocessed && pinvOpen(r) && r.phase === 'AGREED'], ['Verwerkt', (r) => !r.unprocessed && pinvOpen(r) && r.phase === 'PROCESSED'], ['Inbox', (r) => !!r.unprocessed]],
      columns: () => [pillCol('purchaseinvoice'), COL.main('purchaseinvoice', (r) => esc(refLabel(r.company, 'company')) + '<br>' + esc(trunc(r.subject, 80))), { h: 'Nummer', v: (r) => esc(r.number ?? ''), s: (r) => String(r.number ?? '') }, { h: 'Boekstuk', v: (r) => esc(r.bookingnumber ?? ''), s: (r) => num(r.bookingnumber) ?? 0 }, COL.date(), COL.money('totalinclvat', 'Totaal incl. BTW'), COL.money('totalopeninclvat', 'Openstaand')],
      totals: (r) => [['Totaal incl. BTW', r.totalinclvat, true], ['Betaald', r.totalpayed], ['Openstaand', r.totalopeninclvat, true]],
      preview: true,
      sections: (r) => [
        ['Leverancier', [['Bedrijf / persoon', { html: refLink(r.company, 'company') }], ['Contactpersoon', { html: refLink(r.contact, 'contact') }]]],
        ['Factuur', [['Factuurnummer', r.number], ['Boekstuk', r.bookingnumber], ['Onderwerp', r.subject], ['Factuurdatum', { html: fdate(r.date) }], ['Rapportagedatum', { html: fdate(r.reportdate) }], ['Vervaldatum', { html: fdate(r.expirydate) }], ['Fase', en(r.phase)], ['Betalingswijze', en(r.paymentmethod)]]],
        ['Medewerkers', [['Team', { html: emps(r) }]]], ['Omschrijving', [['', { html: rich(r.description) }]]], ['Tags', [['', { html: tags(r) }]]], ...customFields(r)],
    },
    contract: {
      label: 'Contracten', single: 'Contract', icon: '🔁',
      title: (r) => r.name || r.subject || `Contract ${r.number ?? r.id}`, number: (r) => r.number,
      pill: (r) => [en(r.status) || 'Contract', r.status === 'ACTIVE' ? 'orange' : r.status === 'ENDED' ? 'grey' : 'purple'], sub: (r) => refLink(r.company, 'company'),
      segments: [['Actief', (r) => r.status === 'ACTIVE'], ['Aflopend', (r) => r.status === 'ENDING'], ['Beëindigd', (r) => r.status === 'ENDED'], ['Alle', (r) => !r.isbasis], ['Basis', (r) => !!r.isbasis]],
      columns: () => [pillCol('contract'), COL.main('contract', (r) => esc(refLabel(r.company, 'company')) + (r.clientreference ? `<br>Klantreferentie: ${esc(r.clientreference)}` : '')), COL.number, COL.date('date', 'Contractdatum'), { h: 'Frequentie', v: (r) => esc(en(r.frequency)) }],
      sections: (r) => [
        ['Klant', [['Bedrijf / persoon', { html: refLink(r.company, 'company') }], ['Contactpersoon', { html: refLink(r.contact, 'contact') }]]],
        ['Contract', [['Onderwerp', r.name], ['Contractdatum', { html: fdate(r.date) }], ['Vervaldatum', { html: fdate(r.expirydate) }], ['Onderwerp factuur', r.subject_invoice], ['Status', en(r.status)]]],
        ['Financieel', [['Betalingswijze', en(r.paymentmethod)], ['Referentie klant', r.clientreference], ['Frequentie', en(r.frequency)], ['Max. termijnen', r.sendmaxtimescheckbox ? r.sendmaxtimes : ''], ['Automatisch verlengen', yn(r.extendautomatically)]]],
        ['Omschrijving', [['', { html: rich(r.description) }]]], ['Tags', [['', { html: tags(r) }]]], ...customFields(r)],
    },
    umbrellaproject: { label: 'Projecten / Retainers', single: 'Project', icon: '📁', title: (r) => r.name || r.searchname, number: (r) => r.number, columns: () => [COL.main('umbrellaproject'), COL.number, COL.date('deadline', 'Deadline'), COL.money('budget', 'Budget')] },
    task: {
      label: 'Taken', single: 'Taak', icon: '✔', title: (r) => r.content ? trunc(plain(r.content), 80) : r.searchname || `Taak ${r.number ?? r.id}`, number: (r) => r.number,
      pill: (r) => (r.isafgerond ? ['Afgerond', 'green'] : ['Lopend', 'orange']), sub: (r) => refLink(r.offerprojectbase, '@offerproject') || refLink(r.company, 'company'),
      segments: [['Lopend', (r) => !r.isafgerond], ['Afgerond', (r) => !!r.isafgerond], ['Alle', () => true]],
      columns: () => [COL.main('task', (r) => esc(refLabel(r.type, 'tasktype'))), COL.number, { h: 'Opdracht', v: (r) => refLink(r.offerprojectbase, '@offerproject') }, { h: 'Fase', v: (r) => esc(phaseName('taskphase', r.phase)) }, { h: 'Uitvoerder', v: (r) => refLink(r.preferredemployee, 'employee') }, COL.date('deadlinedate', 'Deadline'), COL.date('completedon', 'Afgerond op')],
      sections: (r) => [['Taak', [['Type', refLabel(r.type, 'tasktype')], ['Nummer', r.number], ['Fase', phaseName('taskphase', r.phase)], ['Relatie', { html: refLink(r.company, 'company') }], ['Opdracht/offerte', { html: refLink(r.offerprojectbase, '@offerproject') }], ['Uitvoerder', { html: refLink(r.preferredemployee, 'employee') }], ['Geschatte uren', r.estimatedhours], ['Startdatum', { html: fdate(r.startdate) }], ['Deadline', { html: fdate(r.deadlinedate) }], ['Afgerond op', { html: fdate(r.completedon) }]]], ['Inhoud', [['', { html: rich(r.content) }]]], ['Omschrijving', [['', { html: rich(r.description) }]]]],
    },
    calendaritem: { label: 'Activiteiten', single: 'Activiteit', icon: '📅', title: (r) => r.subject || r.searchname || `Activiteit ${r.id}`, segments: [['Komend', (r) => ymd(r.date) >= today], ['Verleden', (r) => ymd(r.date) < today], ['Alle', () => true]], columns: () => [COL.main('calendaritem'), COL.date('date', 'Gepland op'), { h: 'Tijd', v: (r) => esc(dateStr(r.time).slice(0, 5)) }, { h: 'Uren', num: true, v: (r) => esc(r.hours ?? ''), s: (r) => num(r.hours) ?? 0 }, { h: 'Medewerker', v: (r) => refLink(r.calendaritememployee, 'employee') }, { h: 'Taak', v: (r) => refLink(r.task, 'task') }, COL.date('completedon', 'Afgerond op')] },
    hour: {
      label: 'Uren', single: 'Urenregel', icon: '⏱', title: (r) => `${fdate(r.date)} · ${refLabel(r.employee, 'employee')} · ${r.amount ?? ''} u`,
      segments: [['Alle', () => true], ['Concept', (r) => r.status === 'CONCEPT'], ['Definitief', (r) => r.status === 'DEFINITIVE'], ['Gefiatteerd', (r) => r.status === 'AUTHORIZED']],
      columns: () => [COL.date(), { h: 'Medewerker', v: (r) => refLink(r.employee, 'employee'), s: (r) => refLabel(r.employee, 'employee') }, { h: 'Opdracht/offerte', v: (r) => refLink(r.offerprojectbase, '@offerproject'), s: (r) => refLabel(r.offerprojectbase, '@offerproject') }, { h: 'Onderdeel', v: (r) => esc(refLabel(r.offerprojectline, 'offerprojectline')) }, { h: 'Aantal', num: true, v: (r) => esc(r.amount ?? ''), s: (r) => num(r.amount) ?? 0 }, { h: 'Omschrijving', v: (r) => esc(trunc(plain(r.description), 80)) }, { h: 'Status', v: (r) => esc(en(r.status)) }],
    },
    product: {
      label: 'Producten', single: 'Product', icon: '📦', title: (r) => r.name || r.searchname, number: (r) => r.number,
      segments: [['In gebruik', (r) => !r.archived], ['Gearchiveerd', (r) => !!r.archived], ['Alle', () => true]],
      columns: () => [COL.main('product', (r) => esc(refLabel(r.supplier, 'company'))), COL.number, { h: 'Eenheid', v: (r) => esc(refLabel(r.unit, 'unit')) }, COL.money('sellingprice', 'Verkoopprijs'), COL.money('buyingprice', 'Inkoopprijs'), { h: 'Factuurbasis', v: (r) => esc(en(r.invoicebasis)) }],
      sections: (r) => [['Product', [['Naam', r.name], ['Nummer', r.number], ['Eenheid', refLabel(r.unit, 'unit')], ['Gearchiveerd', yn(r.archived)], ['Tags', { html: tags(r) }]]], ['Verkoop', [['Verkoopprijs', { html: eur(r.sellingprice) }], ['Factuurbasis', en(r.invoicebasis)], ['Grootboek verkoop', refLabel(r.ledger, 'ledger')], ['Verbergen bij urenschrijven', yn(r.hideontimetrackingform)]]], ['Inkoop', [['Inkoopprijs', { html: eur(r.buyingprice) }], ['Leverancier', { html: refLink(r.supplier, 'company') }], ['Bestelcode leverancier', r.supplierordercode], ['Grootboek inkoop', refLabel(r.purchaseledger, 'ledger')], ['Kostenplaats', refLabel(r.costheading, 'costheading')], ['Taaktype', refLabel(r.tasktype, 'tasktype')]]], ['Omschrijving', [['', { html: rich(r.description) }]]], ['Interne notitie', [['', { html: rich(r.internalnote) }]]], ...customFields(r)],
    },
    packet: { label: 'Pakketten', single: 'Pakket', icon: '🎁', title: (r) => r.name || r.searchname, number: (r) => r.number, segments: [['In gebruik', (r) => !r.archived], ['Gearchiveerd', (r) => !!r.archived], ['Alle', () => true]], columns: () => [COL.main('packet'), COL.number, { h: 'Regels', num: true, v: (r) => related('packet', r.id, 'packetline').length }], sections: (r) => [['Pakket', [['Naam', r.name], ['Nummer', r.number], ['Eenheid', refLabel(r.unit, 'unit')]]], ['Omschrijving', [['', { html: rich(r.description) }]]]] },
    employee: {
      label: 'Medewerkers', single: 'Medewerker', icon: '👥', title: (r) => [r.firstname, r.infix, r.lastname].filter(Boolean).join(' ') || r.screenname || r.searchname, number: (r) => r.number,
      segments: [['Actief', (r) => r.active !== false], ['Niet actief', (r) => r.active === false], ['Alle', () => true]],
      columns: () => [COL.main('employee', (r) => esc(r.function ?? '')), COL.number, { h: 'Contact', v: (r) => [r.email, r.phone].filter(Boolean).map(esc).join('<br>') }, { h: 'Afdeling', v: (r) => esc(refLabel(r.department, 'department')) }, COL.date('employeesince', 'In dienst sinds')],
      sections: (r) => [['Naam', [['Aanhef', en(r.salutation)], ['Initialen', r.initials], ['Voornaam', r.firstname], ['Tussenvoegsel', r.infix], ['Achternaam', r.lastname], ['Nummer', r.number], ['Gebruikersnaam', r.username]]], ['Werk', [['Functie', r.function], ['Afdeling', refLabel(r.department, 'department')], ['In dienst sinds', { html: fdate(r.employeesince) }], ['Skills', { html: arr(r.skills).map((s) => `<span class="tag grey">${esc(refLabel(s, 'tasktype'))}</span>`).join('') }]]], ['Contact', [['E-mail', r.email], ['Telefoon', r.phone], ['Mobiel', r.mobile]]], ['Notities', [['', { html: rich(r.notes) }]]]],
      sensitive: ['socialsecuritynumber', 'bankaccount', 'dateofbirth', 'street', 'streetnumber', 'zipcode', 'city', 'emailprivate', 'password'],
    },
    absencerequest: { label: 'Verlofaanvragen', single: 'Verlofaanvraag', icon: '🌴', title: (r) => `${refLabel(r.employee, 'employee')} — ${trunc(plain(r.description), 50)}`, columns: () => [COL.main('absencerequest'), { h: 'Medewerker', v: (r) => refLink(r.employee, 'employee') }, COL.date('createdon', 'Aangevraagd op')] },
    memorial: { label: 'Memoriaal', single: 'Memoriaalboeking', icon: '📒', title: (r) => r.name || `Memoriaal ${r.number}`, number: (r) => r.number, segments: yearSegments('reportdate'), columns: () => [COL.number, COL.main('memorial'), COL.date('reportdate', 'Datum'), { h: 'Waarde', num: true, v: (r) => eur(related('memorial', r.id, 'memorialline').reduce((a, l) => a + (num(l.value) > 0 ? num(l.value) : 0), 0)) }], sections: (r) => [['Boeking', [['Nummer', r.number], ['Naam', r.name], ['Datum', { html: fdate(r.reportdate) }]]], ['Omschrijving', [['', { html: rich(r.description) }]]]] },
    cost: { label: 'Directe kosten', single: 'Kostenpost', icon: '💸', title: (r) => trunc(plain(r.description) || `Kosten ${r.number}`, 80), number: (r) => r.number, segments: yearSegments('date'), columns: () => [{ h: 'Nr', v: (r) => esc(r.number ?? '') }, COL.date(), COL.main('cost'), COL.money('value', 'Bedrag'), { h: 'Grootboek', v: (r) => esc(refLabel(r.ledger, 'ledger')) }, { h: 'Kostenplaats', v: (r) => esc(refLabel(r.costheading, 'costheading')) }] },
    ledger: { label: 'Grootboekrekeningen', single: 'Grootboekrekening', icon: '📚', title: (r) => `${r.number ?? ''} ${r.name ?? r.searchname ?? ''}`.trim(), columns: () => [{ h: 'Nummer', v: (r) => esc(r.number ?? ''), s: (r) => String(r.number ?? '') }, COL.main('ledger'), { h: 'Actief', v: (r) => yn(r.active) }] },
    timelineentry: {
      label: 'Timeline', single: 'Timeline-item', icon: '🕓', title: (r) => r.subject || trunc(plain(r.message), 80) || `Bericht ${r.id}`,
      segments: [['Alle', () => true], ['Notities', (r) => r.timelinetype === 'USERMESSAGE'], ['E-mail', (r) => /EMAIL/.test(r.timelinetype ?? '')], ['Systeem', (r) => r.timelinetype === 'SYSTEMMESSAGE'], ['Met bijlage', (r) => arr(r.files).length > 0]],
      columns: () => [COL.date('date', 'Datum'), { h: 'Type', v: (r) => esc(en(r.timelinetype)) }, COL.main('timelineentry', (r) => esc(trunc(plain(r.message), 140))), COL.company, { h: 'Door', v: (r) => refLink(r.employee, 'employee') }],
      sections: (r) => [['Bericht', [['Type', en(r.timelinetype)], ['Datum', { html: fdate(r.date) }], ['Door', { html: refLink(r.employee, 'employee') }], ['Relatie', { html: refLink(r.company, 'company') }], ['Betreft', { html: TL_TARGETS.map(([f, t]) => r[f] ? refLink(r[f], t) : '').filter(Boolean).join('<br>') }]]], ['Inhoud', [['', { html: rich(r.message) }]]]],
    },
  };
  const TL_TARGETS = [['offer', 'offer'], ['project', 'project'], ['invoice', 'invoice'], ['purchaseinvoice', 'purchaseinvoice'], ['purchaseorder', 'purchaseorder'], ['contract', 'contract'], ['task', 'task'], ['contact', 'contact']];
  function yearSegments(f) {
    return [['Alle', () => true], ...[0, 1, 2, 3].map((d) => { const y = String(new Date().getFullYear() - d); return [y, (r) => ymd(r[f]).startsWith(y)]; })];
  }
  function yn(v) { return v == null ? '' : v ? 'Ja' : 'Nee'; }
  function pillCol(ent) { return { h: '', v: (r) => { const [l, c] = CFG[ent].pill(r); return `<span class="chip ${c}">${esc(l)}</span>`; }, s: (r) => CFG[ent].pill(r)[0] }; }
  function phaseSteps(tbl, v) {
    const phases = [...(E[tbl] || [])].sort((a, b) => (num(a._ordering) ?? 0) - (num(b._ordering) ?? 0) || a.id - b.id);
    if (!phases.length) return null;
    const cur = String(refId(v)); const idx = phases.findIndex((p) => String(p.id) === cur);
    return phases.map((p, i) => [p.name ?? p.searchname, i === idx, i < idx]);
  }
  function customFields(r) {
    const cf = r.customfields && typeof r.customfields === 'object' ? Object.entries(r.customfields) : Object.entries(r).filter(([k]) => k.startsWith('customfield_')).map(([k, v]) => [k.replace(/^customfield_/, ''), v]);
    const rows = cf.filter(([, v]) => v != null && v !== '' && !(Array.isArray(v) && !v.length));
    if (!rows.length) return [];
    return [['Vrije velden', rows.map(([k, v]) => [k, { html: typeof v === 'object' ? `<pre class="raw">${esc(JSON.stringify(v, null, 1))}</pre>` : rich(v) }])]];
  }
  // generieke config voor overige tabellen
  const LABELS = { offerprojectline: 'Offerte-/opdrachtregels', invoiceline: 'Factuurregels', payment: 'Betalingen', purchaseinvoiceline: 'Inkoopfactuurregels', purchasepayment: 'Inkoopbetalingen', purchaseorderline: 'Inkoopopdrachtregels', contractline: 'Contractregels', memorialline: 'Memoriaalregels', packetline: 'Pakketregels', bulkprice: 'Staffelprijzen', priceexception: 'Tariefuitzonderingen', companydossier: 'Dossier', revenuetarget: 'Omzettargets', employmentcontract: 'Arbeidsovereenkomsten', employeefamily: 'Familie', employeetarget: 'Medewerkerstargets', employeeyearlyleavebudget: 'Verlofbudgetten', absencerequestline: 'Verlofregels', tag: 'Tags', unit: 'Eenheden', department: 'Afdelingen', costheading: 'Kostenplaatsen', offerphase: 'Offertefasen', projectphase: 'Opdrachtfasen', taskphase: 'Taakfasen', tasktype: 'Taaktypes', rejectionreason: 'Afwijsredenen', yeartarget: 'Jaartargets', yeartargettype: 'Jaartargettypes', file: 'Bestanden (metadata)', externallink: 'Externe links' };
  for (const ent of Object.keys(E)) {
    if (CFG[ent]) continue;
    CFG[ent] = { label: LABELS[ent] || ent, single: LABELS[ent] || ent, icon: '·', generic: true, title: (r) => trunc(plain(r.searchname ?? r.name ?? r.description ?? r.additionalsubject ?? `#${r.id}`), 90) };
  }
  const ROUTE = Object.fromEntries(Object.keys(CFG).map((k) => [k, true]));

  // regels per documenttype
  const LINES = {
    offer: ['offerprojectline', 'offerprojectbase'], project: ['offerprojectline', 'offerprojectbase'], invoice: ['invoiceline', 'invoice'],
    purchaseorder: ['purchaseorderline', 'purchaseorder'], purchaseinvoice: ['purchaseinvoiceline', 'purchaseinvoice'], contract: ['contractline', 'contract'],
    memorial: ['memorialline', 'memorial'], packet: ['packetline', 'packet'],
  };

  // ---------------------------------------------------------------- navigation
  const MENU = [
    ['Home', '🏠', '#/'],
    ['Relaties', '🏢', [['Relaties', 'company'], ['Contactpersonen', 'contact']]],
    ['Offertes', '📄', 'offer'],
    ['Opdrachten', '📋', [['Verkoop', 'project'], ['Inkoop', 'purchaseorder'], ['Projecten / Retainers', 'umbrellaproject']]],
    ['Planning', '🕒', [['Taken', 'task'], ['Activiteiten', 'calendaritem']]],
    ['Uren', '⏱', 'hour'],
    ['Facturen', '€', [['Verkoop', 'invoice'], ['Inkoop', 'purchaseinvoice'], ['Betalingen', 'payment'], ['Inkoopbetalingen', 'purchasepayment']]],
    ['Contracten', '🔁', 'contract'],
    ['Boekhouding', '📒', [['Memoriaal', 'memorial'], ['Directe kosten', 'cost'], ['Grootboekrekeningen', 'ledger']]],
    ['Rapportage', '📊', [['Openstaande debiteuren', '#/rapport/debiteuren'], ['Openstaande crediteuren', '#/rapport/crediteuren'], ['Omzet per jaar en maand', '#/rapport/omzet'], ['Uren per medewerker', '#/rapport/uren']]],
    ['Producten', '📦', [['Producten', 'product'], ['Pakketten', 'packet']]],
    ['Medewerkers', '👥', [['Medewerkers', 'employee'], ['Verlofaanvragen', 'absencerequest']]],
    ['Archief', '🗄', [['Timeline', 'timelineentry'], ['Bijlagen', '#/bijlagen'], ['Handmatige exports', '#/exports'], ['Alle tabellen', '#/tabellen'], ['Importrapport', '#/rapport-import']]],
  ];
  const href = (t) => (t.startsWith('#') ? t : `#/${t}`);
  function renderNav(path) {
    sidebar.innerHTML = `<div class="brand">${esc(D.meta?.accountName || 'Gripp')}<small>Gripp-archief · ${esc(fdate(D.meta?.importedAt))}</small></div>` + MENU.map(([label, ico, t]) => {
      if (typeof t === 'string') {
        const h = href(t); const active = path === h.slice(1) || (h !== '#/' && path.startsWith(h.slice(1) + '/'));
        return `<div class="nav-group"><a class="nav-item ${active ? 'active' : ''}" href="${h}"><span class="ico">${ico}</span>${esc(label)}</a></div>`;
      }
      const hs = t.map(([, x]) => href(x).slice(1));
      const open = hs.some((h) => path === h || path.startsWith(h + '/'));
      return `<div class="nav-group ${open ? 'open' : ''}"><a class="nav-item ${open ? 'active' : ''}" data-toggle><span class="ico">${ico}</span>${esc(label)}<span class="chev">▼</span></a><div class="nav-sub">${t.map(([l, x]) => { const h = href(x); const a = path === h.slice(1) || path.startsWith(h.slice(1) + '/'); return `<a class="${a ? 'active' : ''}" href="${h}">${esc(l)}</a>`; }).join('')}</div></div>`;
    }).join('');
  }
  sidebar.addEventListener('click', (e) => { const t = e.target.closest('[data-toggle]'); if (t) { t.parentElement.classList.toggle('open'); e.preventDefault(); } else if (e.target.closest('a[href]')) document.body.classList.remove('nav-open'); });
  document.getElementById('menuToggle').addEventListener('click', () => document.body.classList.toggle('nav-open'));
  document.getElementById('globalSearch').addEventListener('submit', (e) => { e.preventDefault(); const q = document.getElementById('globalQ').value.trim(); if (q) location.hash = `#/zoeken?q=${encodeURIComponent(q)}`; });

  function parseHash() {
    const h = location.hash.replace(/^#/, '') || '/';
    const [p, qs] = h.split('?');
    return { path: p, q: Object.fromEntries(new URLSearchParams(qs || '')) };
  }
  const setQ = (patch) => { const { path, q } = parseHash(); const n = { ...q, ...patch }; for (const k of Object.keys(n)) if (n[k] == null || n[k] === '') delete n[k]; location.hash = `#${path}${Object.keys(n).length ? '?' + new URLSearchParams(n) : ''}`; };

  // ---------------------------------------------------------------- pages
  function pageHead(title, sub = '', extra = '') { return `<div class="page-head"><div><h1>${title}</h1>${sub ? `<div class="sub">${sub}</div>` : ''}</div><div class="grow"></div>${extra}</div>`; }

  function listTable(ent, rows, { q = {}, limit = 50, compact = false } = {}) {
    const cfg = CFG[ent];
    let cols = cfg.columns ? cfg.columns() : [COL.main(ent), { h: 'Gripp-ID', v: (r) => esc(r.id), s: (r) => num(r.id) }];
    if (compact) cols = cols.slice(0, 6);
    const sortI = q.sort != null ? Number(q.sort) : -1;
    if (sortI >= 0 && cols[sortI]) {
      const s = cols[sortI].s || ((r) => plain(cols[sortI].v(r)));
      const dir = q.dir === 'desc' ? -1 : 1;
      rows = [...rows].sort((a, b) => { const x = s(a), y = s(b); return (x > y ? 1 : x < y ? -1 : 0) * dir; });
    }
    const shown = rows.slice(0, limit);
    if (!rows.length) return `<div class="empty">Geen gegevens gevonden met huidige filtering.</div>`;
    return `<div class="table-wrap"><table class="list"><thead><tr>${cols.map((c, i) => `<th class="${c.num ? 'num' : ''}" ${compact ? '' : `data-sort="${i}"`}>${esc(c.h)}${i === sortI ? `<span class="arr"> ${q.dir === 'desc' ? '↓' : '↑'}</span>` : ''}</th>`).join('')}</tr></thead>
      <tbody>${shown.map((r) => `<tr>${cols.map((c) => `<td class="${c.num ? 'num' : ''} ${c.cls || ''}">${c.v(r) ?? ''}</td>`).join('')}</tr>`).join('')}</tbody></table></div>
      ${rows.length > limit ? `<div class="more"><span class="muted">${limit} van ${rows.length} getoond</span> ${compact ? '' : `<button class="btn" data-more="${limit + 100}">Toon meer</button> <button class="btn" data-more="${rows.length}">Toon alle</button>`}</div>` : ''}`;
  }

  function textMatch(ent, r, q) {
    const cfg = CFG[ent];
    const hay = [cfg.title(r), r.searchname, r.number, r.customernumber, r.clientreference, r.subject, r.name, r.email, r.phone, r.bookingnumber, refLabel(r.company, 'company'), cfg.number?.(r)].filter((x) => x != null).join(' ').toLowerCase();
    return q.toLowerCase().split(/\s+/).every((w) => hay.includes(w));
  }

  function pageList(ent, q) {
    const cfg = CFG[ent];
    const all = E[ent] || [];
    const segs = cfg.segments || [['Alle', () => true]];
    const segName = q.seg && segs.some(([n]) => n === q.seg) ? q.seg : segs[0][0];
    const segFn = segs.find(([n]) => n === segName)[1];
    let rows = all.filter(segFn);
    if (q.q) rows = rows.filter((r) => textMatch(ent, r, q.q));
    const extra = ent === 'offer' || ent === 'project' || ent === 'invoice' || ent === 'purchaseinvoice' ? sideTotals(ent, rows) : '';
    return pageHead(esc(cfg.label), `${all.length} records in archief`) +
      `<div class="layout"><div class="col-main"><div class="card"><div class="segbar"><div class="segs">${segs.map(([n, f]) => `<a class="seg ${n === segName ? 'active' : ''}" href="#/${ent}?seg=${encodeURIComponent(n)}${q.q ? '&q=' + encodeURIComponent(q.q) : ''}"><span class="l">${esc(n)}</span><span class="c">${all.filter(f).length}</span></a>`).join('')}</div>
      <div class="tools"><input class="filter-input" id="listQ" type="search" placeholder="Zoeken in ${esc(cfg.label.toLowerCase())}" value="${esc(q.q || '')}"><a class="btn" id="csv" title="Huidige selectie als CSV">⤓ CSV</a></div></div>
      <div class="card-b flush">${listTable(ent, rows, { q, limit: Number(q.limit) || 50 })}</div></div></div>
      <div class="col-side">${extra || `<div class="side-sec"><div class="side-h">Over deze lijst</div><p class="muted" style="font-size:13px">Alleen-lezen weergave van de geïmporteerde Gripp-gegevens. Klik op een kolomkop om te sorteren.</p></div>`}</div></div>`;
  }

  function sideTotals(ent, rows) {
    const s = (f) => rows.reduce((a, r) => a + (num(r[f]) || 0), 0);
    const items = {
      offer: [['Totaal ex. BTW', s('totalexclvat')], ['Verwacht (× slagingskans)', rows.reduce((a, r) => a + (num(r.totalexclvat) || 0) * ((num(r.chance) ?? 100) / 100), 0)]],
      project: [['Totaal ex. BTW', s('totalexclvat')]],
      invoice: [['Totaal incl. BTW', s('totalinclvat')], ['Openstaand incl. BTW', s('totalopeninclvat')]],
      purchaseinvoice: [['Totaal incl. BTW', s('totalinclvat')], ['Openstaand incl. BTW', s('totalopeninclvat')]],
    }[ent];
    return `<div class="side-sec"><div class="side-h">Totalen selectie</div><div class="totals">${items.map(([l, v], i) => `<div class="r ${i === items.length - 1 ? 'big' : ''}"><span>${esc(l)}</span><span class="num">${eur(v)}</span></div>`).join('')}</div><p class="meta">${rows.length} records · stand per ${esc(fdate(D.meta?.importedAt))}</p></div>`;
  }

  function fieldRows(sections) {
    return sections.map(([title, fields]) => {
      const vis = fields.filter(([, v]) => v != null && v !== '' && !(typeof v === 'object' && (v.html == null || v.html === '')));
      if (!vis.length) return '';
      const single = vis.length === 1 && vis[0][0] === '';
      return `<div class="frow"><div class="lbl">${esc(title)}</div><div>${single ? (typeof vis[0][1] === 'object' ? vis[0][1].html : esc(vis[0][1])) : `<div class="fields">${vis.map(([l, v]) => `<div class="fld"><div class="v">${typeof v === 'object' ? v.html : esc(v)}</div><div class="c">${esc(l)}</div></div>`).join('')}</div>`}</div></div>`;
    }).join('');
  }

  function genericSections(ent, r) {
    const refs = REFS[ent] || {};
    const hide = new Set(CFG[ent].sensitive || []);
    const items = Object.entries(r).filter(([k, v]) => !hide.has(k) && v != null && v !== '' && !(Array.isArray(v) && !v.length) && k !== 'customfields')
      .map(([k, v]) => {
        if (refs[k]) return [k, { html: arr(v).map((x) => refLink(x, refs[k])).join(', ') }];
        if (typeof v === 'object' && v.date) return [k, { html: fdate(v) }];
        if (typeof v === 'object') return [k, { html: `<code>${esc(trunc(JSON.stringify(v), 200))}</code>` }];
        if (typeof v === 'string' && v.length > 120) return [k, { html: rich(v) }];
        return [k, typeof v === 'boolean' ? yn(v) : String(v)];
      });
    return [['Velden', items]];
  }

  function linesTable(ent, r) {
    const spec = LINES[ent]; if (!spec) return null;
    const [lent, fk] = spec;
    let lines = (E[lent] || []).filter((l) => String(refId(l[fk])) === String(r.id));
    if (!lines.length) { // val terug op ingebedde regels in het record
      const emb = Object.entries(r).find(([k, v]) => /lines$/.test(k) && Array.isArray(v) && v.length && typeof v[0] === 'object' && v[0].amount !== undefined);
      if (emb) lines = emb[1];
    }
    lines = [...lines].sort((a, b) => (num(a._ordering) ?? 0) - (num(b._ordering) ?? 0) || a.id - b.id);
    if (ent === 'memorial') {
      return { count: lines.length, html: `<table class="list"><thead><tr><th>Grootboek</th><th>Omschrijving</th><th>Kostenplaats</th><th class="num">Bedrag</th></tr></thead><tbody>${lines.map((l) => `<tr><td>${esc(refLabel(l.ledger, 'ledger'))}</td><td>${esc(l.description ?? '')}</td><td>${esc(refLabel(l.costheading, 'costheading'))}</td><td class="num">${eur(l.value)}</td></tr>`).join('')}</tbody></table>` };
    }
    const price = (l) => num(l.sellingprice) ?? 0;
    const sub = (l) => (num(l.amount) ?? 0) * price(l) * (1 - (num(l.discount) ?? 0) / 100);
    const html = `<table class="list lines"><thead><tr><th>Product</th><th class="num">Aantal</th><th class="num">Prijs p.e.</th><th class="num">Korting</th><th class="num">Subtotaal</th></tr></thead><tbody>${lines.map((l) => {
      if (l.rowtype === 'GROUP') return `<tr><td class="grp" colspan="5">${esc(l.additionalsubject || refLabel(l.product, 'product') || 'Groep')}</td></tr>`;
      const prod = l.product ? refLink(l.product, 'product') : '';
      const extra = [l.buyingprice != null && num(l.buyingprice) ? `Inkoop: ${eurTxt(l.buyingprice)}` : '', l.ledger ? `Grootboek: ${esc(refLabel(l.ledger, 'ledger'))}` : '', l.invoicebasis ? en(l.invoicebasis) : '', l.project && ent !== 'project' ? `Opdracht: ${refLink(l.project, '@offerproject')}` : ''].filter(Boolean).join(' · ');
      return `<tr><td><div><strong>${esc(l.additionalsubject || '')}</strong> ${prod}</div>${l.description ? `<div class="desc">${isHtml(l.description) ? sanitize(l.description) : esc(l.description).replace(/\n/g, '<br>')}</div>` : ''}${extra ? `<div class="desc">${extra}</div>` : ''}</td><td class="num">${esc(l.amount ?? '')} ${esc(refLabel(l.unit, 'unit') || (typeof l.unit === 'string' ? l.unit : ''))}</td><td class="num">${eur(l.sellingprice)}</td><td class="num">${num(l.discount) ? esc(l.discount) + '%' : ''}</td><td class="num">${eur(sub(l))}</td></tr>`;
    }).join('')}</tbody><tfoot><tr><td colspan="4" class="num"><strong>Totaal regels excl. BTW</strong></td><td class="num"><strong>${eur(lines.filter((l) => l.rowtype !== 'GROUP').reduce((a, l) => a + sub(l), 0))}</strong></td></tr></tfoot></table>`;
    return { count: lines.length, html };
  }

  function timelineFor(ent, r) {
    const field = ent === 'company' ? 'company' : TL_TARGETS.find(([f]) => f === ent)?.[0];
    if (!field) return [];
    return (E.timelineentry || []).filter((t) => String(refId(t[field])) === String(r.id)).sort((a, b) => (dateStr(b.date) > dateStr(a.date) ? 1 : -1));
  }
  function timelineHtml(items, filter = 'Alle') {
    if (!items.length) return '<div class="empty">Geen timeline-items.</div>';
    return `<ul class="tl">${items.slice(0, 300).map((t) => `<li><div class="h">${esc(en(t.timelinetype))} · ${fdate(t.date)} · ${esc(refLabel(t.employee, 'employee'))}${t.subject ? ` · <strong>${esc(t.subject)}</strong>` : ''} · <a href="#/timelineentry/${t.id}">open</a></div><div class="b">${rich(t.message)}${arr(t.files).length ? `<div style="margin-top:8px">📎 ${arr(t.files).map((f) => fileLink(refId(f))).join('<br>📎 ')}</div>` : ''}</div></li>`).join('')}</ul>${items.length > 300 ? `<p class="muted">Eerste 300 van ${items.length} getoond.</p>` : ''}`;
  }

  const RELATED_ORDER = ['contact', 'offer', 'project', 'purchaseorder', 'invoice', 'purchaseinvoice', 'contract', 'task', 'hour', 'calendaritem', 'payment', 'purchasepayment', 'companydossier', 'priceexception', 'absencerequest', 'employmentcontract', 'invoiceline', 'offerprojectline', 'purchaseorderline', 'purchaseinvoiceline', 'contractline', 'packetline', 'bulkprice', 'memorialline', 'cost', 'product'];

  function pageDetail(ent, id, q) {
    const r = get(ent, id);
    const cfg = CFG[ent];
    if (!r) return pageHead('Niet gevonden') + `<div class="wide"><div class="empty">${esc(cfg?.single || ent)} met Gripp-ID ${esc(id)} staat niet in dit archief.</div></div>`;
    const tabs = [['gegevens', 'Gegevens']];
    const lines = linesTable(ent, r); if (lines) tabs.push(['regels', 'Regels', lines.count]);
    const rel = REV[ent]?.[r.id] || {};
    const relEnts = Object.keys(rel).filter((x) => x !== 'timelineentry' && !(LINES[ent] && x === LINES[ent][0])).sort((a, b) => (RELATED_ORDER.indexOf(a) + 1 || 99) - (RELATED_ORDER.indexOf(b) + 1 || 99));
    for (const x of relEnts) tabs.push([`rel-${x}`, CFG[x]?.label || x, rel[x].size]);
    const tl = timelineFor(ent, r); if (tl.length) tabs.push(['timeline', 'Timeline', tl.length]);
    const fids = [...new Set([...filesOf(r), ...tl.flatMap((t) => arr(t.files).map(refId))])].filter(Boolean);
    const docPdf = ent === 'invoice' ? D.invoicePdfs?.[r.id] : D.docs?.[ent]?.[r.id];
    if (fids.length || docPdf) tabs.push(['bestanden', 'Bestanden', fids.length + (docPdf ? 1 : 0)]);
    tabs.push(['bron', 'Brongegevens']);
    const tab = tabs.some(([k]) => k === q.tab) ? q.tab : 'gegevens';

    let body = '';
    if (tab === 'gegevens') body = `<div class="card">${fieldRows(cfg.sections ? cfg.sections(r) : genericSections(ent, r))}</div>`;
    else if (tab === 'regels') body = `<div class="card"><div class="card-b flush table-wrap">${lines.html}</div></div>`;
    else if (tab.startsWith('rel-')) { const x = tab.slice(4); body = `<div class="card"><div class="card-b flush">${listTable(x, [...rel[x]].map((i) => get(x, i)).filter(Boolean), { q, limit: Number(q.limit) || 100 })}</div></div>`; }
    else if (tab === 'timeline') body = timelineHtml(tl);
    else if (tab === 'bestanden') {
      const pdf = docPdf;
      const prev = pdf ? { name: pdf.split('/').pop(), path: pdf } : (cfg.preview || q.preview) && fids.map((f) => D.files?.[f]).find((f) => f && /\.pdf$/i.test(f.name));
      body = `<div class="card"><div class="card-b">${pdf ? `<p>📄 <a href="../${encodeURI(pdf)}" target="_blank" rel="noopener">${esc(cfg.single)}-PDF (document)</a></p>` : ''}${fids.map((f) => `<p>📎 ${fileLink(f)}</p>`).join('')}</div></div>${prev ? `<div class="card"><div class="card-h">Voorbeeld: ${esc(prev.name)}</div><iframe src="../${encodeURI(prev.path)}" style="width:100%;height:75vh;border:0"></iframe></div>` : ''}`;
    } else if (tab === 'bron') body = `<div class="notice">Volledige, onbewerkte API-gegevens van dit record (Gripp-ID ${esc(r.id)}).</div><pre class="raw">${esc(JSON.stringify({ ...r, ...(r.__orig || {}) }, (k, v) => ((cfg.sensitive || []).includes(k) && v ? '•••' : v), 2))}</pre>`;

    const pill = cfg.pill ? cfg.pill(r) : null;
    const steps = cfg.phases ? cfg.phases(r) : null;
    const nr = cfg.number?.(r);
    const head = `<div class="dhead">${pill ? `<span class="chip ${pill[1]}">${esc(pill[0])}</span>` : ''}<div><h1>${esc(cfg.title(r))}${nr != null && nr !== '' && !String(cfg.title(r)).includes(String(nr)) ? ` <span class="muted">- ${esc(nr)}</span>` : ''}</h1><div class="sub">${cfg.single}${cfg.sub ? ' · ' + cfg.sub(r) : ''}</div></div>
      ${steps ? `<div class="stepper">${steps.map(([n, cur, done]) => `<div class="step ${cur ? 'cur' : done ? 'done' : ''}"><span class="c"></span>${esc(n)}</div>`).join('')}</div>` : ''}</div>`;
    const tabsHtml = `<nav class="tabs">${tabs.map(([k, l, c]) => `<a class="${k === tab ? 'active' : ''}" href="#/${ent}/${encodeURIComponent(id)}?tab=${k}">${esc(l)}${c != null ? `<span class="cnt">${c}</span>` : ''}</a>`).join('')}</nav>`;

    const tot = cfg.totals ? cfg.totals(r).filter(([, v]) => v != null && v !== '') : [];
    const side = [
      tot.length ? `<div class="side-sec"><div class="side-h">Totalen</div><div class="totals">${tot.map(([l, v, big, raw]) => `<div class="r ${big ? 'big' : ''}"><span>${esc(l)}</span><span class="num">${raw ? esc(v) : eur(v)}</span></div>`).join('')}</div></div>` : '',
      cfg.docs ? `<div class="side-sec">${cfg.docs(r).join('')}</div>` : '',
      relEnts.length ? `<div class="side-sec"><div class="side-h">Gekoppeld</div>${relEnts.map((x) => `<a class="side-btn" href="#/${ent}/${encodeURIComponent(id)}?tab=rel-${x}">${esc(CFG[x]?.label || x)} <span class="muted">(${rel[x].size})</span></a>`).join('')}</div>` : '',
      `<div class="side-sec meta">Aangemaakt: ${fdate(r.createdon) || '–'}<br>Laatst gewijzigd: ${fdate(r.updatedon) || '–'}<br>Gripp-ID: ${esc(r.id)}<br>Stand archief: ${esc(fdate(D.meta?.importedAt))}</div>`,
    ].join('');
    return head + tabsHtml + `<div class="layout"><div class="col-main">${body}</div><div class="col-side">${side}</div></div>`;
  }

  function pageHome() {
    const k = (ent, seg) => { const c = CFG[ent]; const f = seg ? c.segments.find(([n]) => n === seg)?.[1] : () => true; return `<a class="kpi" href="#/${ent}${seg ? '?seg=' + encodeURIComponent(seg) : ''}"><div class="v">${(E[ent] || []).filter(f || (() => true)).length}</div><div class="l">${esc(c.label)}${seg ? ' · ' + esc(seg.toLowerCase()) : ''}</div></a>`; };
    const s = (ent, f, filt = () => true) => (E[ent] || []).filter(filt).reduce((a, r) => a + (num(r[f]) || 0), 0);
    const recent = [...(E.timelineentry || [])].sort((a, b) => (dateStr(b.date) > dateStr(a.date) ? 1 : -1)).slice(0, 12);
    const rep = D.report;
    const failed = rep ? Object.entries(rep.entities).filter(([, e]) => e.error) : [];
    return pageHead('Home', `Archief van de Gripp-omgeving · stand ${esc(fdate(D.meta?.importedAt))}`) + `<div class="wide">
      ${D.meta?.status === 'preview' ? `<div class="notice warn"><strong>Voorbeeld:</strong> de import loopt nog. Bijlagen die nog niet zijn opgehaald tonen als "niet gearchiveerd".</div>` : D.meta?.status !== 'complete' || failed.length ? `<div class="notice warn">Deze snapshot is <strong>gedeeltelijk</strong>: ${failed.length} tabel(len) konden niet worden opgehaald. Zie het <a href="#/rapport-import">importrapport</a>.</div>` : ''}
      <div class="grid-kpi" style="margin-bottom:16px">${k('company', 'Actief')}${k('offer', 'Openstaand')}${k('project', 'Lopend')}${k('purchaseorder', 'Openstaand')}${k('invoice', 'Openstaand')}${k('purchaseinvoice', 'Openstaand')}${k('contract', 'Actief')}${k('product')}</div>
      <div class="grid-kpi" style="margin-bottom:16px">
        <a class="kpi" href="#/rapport/debiteuren"><div class="v">${eur(s('invoice', 'totalopeninclvat'))}</div><div class="l">Debiteurenstand incl. BTW</div></a>
        <a class="kpi" href="#/rapport/crediteuren"><div class="v">${eur(s('purchaseinvoice', 'totalopeninclvat', (r) => !r.unprocessed))}</div><div class="l">Crediteurenstand incl. BTW</div></a>
        <a class="kpi" href="#/offer?seg=Openstaand"><div class="v">${eur(s('offer', 'totalexclvat', CFG.offer.segments[0][1]))}</div><div class="l">Openstaande offertewaarde ex. BTW</div></a>
        <a class="kpi" href="#/bijlagen"><div class="v">${Object.keys(D.files || {}).length + Object.keys(D.invoicePdfs || {}).length}</div><div class="l">Gearchiveerde documenten</div></a>
      </div>
      <div class="card"><div class="card-h">Laatste timeline-berichten<span class="right"><a href="#/timelineentry">Alles</a></span></div><div class="card-b">${timelineHtml(recent)}</div></div></div>`;
  }

  function pageSearch(q) {
    const term = q.q || '';
    const order = ['company', 'contact', 'offer', 'project', 'purchaseorder', 'invoice', 'purchaseinvoice', 'contract', 'product', 'employee', 'task', 'timelineentry'];
    const groups = order.map((ent) => [ent, (E[ent] || []).filter((r) => textMatch(ent, r, term))]).filter(([, rs]) => rs.length);
    return pageHead(`Zoekresultaten voor “${esc(term)}”`, `${groups.reduce((a, [, r]) => a + r.length, 0)} treffers`) + `<div class="wide">${groups.length ? groups.map(([ent, rs]) => `<div class="card"><div class="card-h">${esc(CFG[ent].label)} <span class="muted">(${rs.length})</span><span class="right"><a href="#/${ent}?seg=Alle&q=${encodeURIComponent(term)}">in lijst</a></span></div><div class="card-b flush">${listTable(ent, rs, { limit: 10, compact: true })}</div></div>`).join('') : '<div class="empty">Niets gevonden.</div>'}</div>`;
  }

  function ageing(rows, peil) {
    const out = new Map();
    for (const r of rows) {
      const open = num(r.totalopeninclvat) || 0; if (Math.abs(open) < 0.005) continue;
      const due = ymd(r.expirydate) || ymd(r.date);
      const days = due ? Math.floor((new Date(peil) - new Date(due)) / 864e5) : 0;
      const b = days <= 30 ? 0 : days <= 60 ? 1 : days <= 90 ? 2 : 3;
      const key = String(refId(r.company));
      const g = out.get(key) || { company: r.company, b: [0, 0, 0, 0], total: 0, docs: [] };
      g.b[b] += open; g.total += open; g.docs.push(r); out.set(key, g);
    }
    return [...out.values()].sort((a, b) => b.total - a.total);
  }

  function pageReport(name, q) {
    if (name === 'debiteuren' || name === 'crediteuren') {
      const ent = name === 'debiteuren' ? 'invoice' : 'purchaseinvoice';
      const peil = q.peil || today;
      const g = ageing((E[ent] || []).filter((r) => !r.unprocessed && (ent !== 'invoice' || r.status === 'SENT')), peil);
      const tot = [0, 1, 2, 3].map((i) => g.reduce((a, x) => a + x.b[i], 0));
      return pageHead(`Openstaande ${name}`, `Openstaande bedragen zoals op importdatum ${esc(fdate(D.meta?.importedAt))}; ouderdom berekend t.o.v. peildatum`) + `<div class="wide"><div class="card"><div class="card-b"><label>Peildatum <input type="date" class="filter-input" style="width:auto" id="peil" value="${esc(peil)}"></label></div></div>
        <div class="card"><div class="card-b flush table-wrap"><table class="list"><thead><tr><th>Relatie / documenten</th><th class="num">0-30</th><th class="num">31-60</th><th class="num">61-90</th><th class="num">91+</th><th class="num">Totaal incl. BTW</th></tr></thead><tbody>
        <tr><td><strong>Totaal</strong></td>${tot.map((t) => `<td class="num"><strong>${eur(t)}</strong></td>`).join('')}<td class="num"><strong>${eur(tot.reduce((a, b) => a + b, 0))}</strong></td></tr>
        ${g.map((x) => `<tr><td>${refLink(x.company, 'company')} <span class="muted">(${x.docs.length})</span><div class="s muted" style="font-size:12px">${x.docs.map((d) => `<a href="#/${ent}/${d.id}">${esc(d.number ?? d.id)}</a>`).join(', ')}</div></td>${x.b.map((v) => `<td class="num">${eur(v)}</td>`).join('')}<td class="num">${eur(x.total)}</td></tr>`).join('')}
        </tbody></table></div></div></div>`;
    }
    if (name === 'omzet') {
      const map = new Map();
      for (const r of E.invoice || []) { if (r.status === 'CONCEPT' || r.isbasis) continue; const d = ymd(r.reportdate) || ymd(r.date); if (!d) continue; const y = d.slice(0, 4), m = Number(d.slice(5, 7)); const row = map.get(y) || Array(13).fill(0); row[m - 1] += num(r.totalincldiscountexclvat) || 0; row[12] += num(r.totalincldiscountexclvat) || 0; map.set(y, row); }
      const years = [...map.keys()].sort().reverse();
      const months = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
      const byCompany = new Map(); for (const r of E.invoice || []) { if (r.status === 'CONCEPT' || r.isbasis) continue; const k = String(refId(r.company)); byCompany.set(k, (byCompany.get(k) || { c: r.company, t: 0 })); byCompany.get(k).t += num(r.totalincldiscountexclvat) || 0; }
      const top = [...byCompany.values()].sort((a, b) => b.t - a.t).slice(0, 25);
      return pageHead('Omzet per jaar en maand', 'Verzonden verkoopfacturen, excl. BTW, op rapportagedatum') + `<div class="wide"><div class="card"><div class="card-b flush table-wrap"><table class="list"><thead><tr><th>Jaar</th>${months.map((m) => `<th class="num">${m}</th>`).join('')}<th class="num">Totaal</th></tr></thead><tbody>${years.map((y) => `<tr><td><strong>${y}</strong></td>${map.get(y).map((v, i) => `<td class="num">${i === 12 ? '<strong>' : ''}${v ? nlNum.format(v) : ''}${i === 12 ? '</strong>' : ''}</td>`).join('')}</tr>`).join('')}</tbody></table></div></div>
        <div class="card"><div class="card-h">Top 25 klanten (alle jaren)</div><div class="card-b flush"><table class="list"><tbody>${top.map((x) => `<tr><td>${refLink(x.c, 'company')}</td><td class="num">${eur(x.t)}</td></tr>`).join('')}</tbody></table></div></div></div>`;
    }
    if (name === 'uren') {
      const map = new Map(); const months = new Set();
      for (const h of E.hour || []) { const m = ymd(h.date).slice(0, 7); if (!m) continue; months.add(m); const k = String(refId(h.employee)); const row = map.get(k) || { e: h.employee, m: {} }; row.m[m] = (row.m[m] || 0) + (num(h.amount) || 0); map.set(k, row); }
      const ms = [...months].sort().reverse().slice(0, 12).reverse();
      return pageHead('Uren per medewerker', 'Laatste 12 maanden met uren') + `<div class="wide"><div class="card"><div class="card-b flush table-wrap"><table class="list"><thead><tr><th>Medewerker</th>${ms.map((m) => `<th class="num">${m}</th>`).join('')}</tr></thead><tbody>${[...map.values()].map((x) => `<tr><td>${refLink(x.e, 'employee')}</td>${ms.map((m) => `<td class="num">${x.m[m] ? nlNum.format(x.m[m]) : ''}</td>`).join('')}</tr>`).join('') || '<tr><td>Geen uren</td></tr>'}</tbody></table></div></div></div>`;
    }
    return pageHead('Onbekend rapport');
  }

  function pageFiles() {
    const owners = {};
    for (const [ent, rows] of Object.entries(E)) for (const r of rows) for (const f of filesOf(r)) (owners[f] ??= []).push([ent, r]);
    const list = Object.entries(D.files || {}).sort((a, b) => a[1].name.localeCompare(b[1].name));
    const pdfs = Object.entries(D.invoicePdfs || {});
    return pageHead('Bijlagen', `${list.length} bestanden · ${pdfs.length} factuur-PDF's`) + `<div class="wide"><div class="card"><div class="card-b flush table-wrap"><table class="list"><thead><tr><th>Bestand</th><th>Hoort bij</th></tr></thead><tbody>
      ${pdfs.map(([id, p]) => `<tr><td>📄 <a href="../${encodeURI(p)}" target="_blank" rel="noopener">${esc(p.split('/').pop())}</a></td><td>${refLink(id, 'invoice')}</td></tr>`).join('')}
      ${Object.entries(D.docs || {}).flatMap(([ent, m]) => Object.entries(m).map(([id, p]) => `<tr><td>📄 <a href="../${encodeURI(p)}" target="_blank" rel="noopener">${esc(p.split('/').pop())}</a></td><td>${refLink(id, ent)}</td></tr>`)).join('')}
      ${list.map(([id, f]) => `<tr><td>📎 ${fileLink(id)}</td><td>${(owners[id] || []).map(([e, r]) => `<a href="#/${e}/${r.id}">${esc(CFG[e].single)}: ${esc(trunc(CFG[e].title(r), 60))}</a>`).join('<br>')}</td></tr>`).join('')}
      </tbody></table></div></div></div>`;
  }

  function pageExports() {
    const m = D.manual || [];
    return pageHead('Handmatige exports', 'Bestanden in archive/manual/ (bijv. factuurarchief-ZIP\'s, XAF-auditfiles, offerte-PDF\'s)') + `<div class="wide">
      <div class="notice">Plaats aanvullende exports uit Gripp in de map <code>archive/manual/</code> en draai <code>npm run build</code>; ze verschijnen dan hier.</div>
      <div class="card"><div class="card-b flush">${m.length ? `<table class="list"><tbody>${m.map((f) => `<tr><td>📦 <a href="../${encodeURI(f.path)}" target="_blank" rel="noopener">${esc(f.path.replace('archive/manual/', ''))}</a></td><td class="num">${(f.size / 1048576).toFixed(2)} MB</td></tr>`).join('')}</tbody></table>` : '<div class="empty">Nog geen handmatige exports.</div>'}</div></div></div>`;
  }

  function pageTables() {
    return pageHead('Alle tabellen', 'Elke geïmporteerde Gripp-entiteit') + `<div class="wide"><div class="card"><div class="card-b flush"><table class="list"><thead><tr><th>Tabel</th><th>API-naam</th><th class="num">Records</th></tr></thead><tbody>${Object.keys(E).sort((a, b) => CFG[a].label.localeCompare(CFG[b].label)).map((e) => `<tr><td><a href="#/${e}">${esc(CFG[e].label)}</a></td><td><code>${e}</code></td><td class="num">${E[e].length}</td></tr>`).join('')}</tbody></table></div></div></div>`;
  }

  function pageImportReport() {
    const r = D.report;
    if (!r) return pageHead('Importrapport') + '<div class="wide"><div class="empty">Geen rapport beschikbaar.</div></div>';
    return pageHead('Importrapport', `Batch ${esc(r.batch)} · ${esc(fdate(r.finishedAt))} · status ${esc(D.meta.status)}`) + `<div class="wide">
      <div class="notice warn"><strong>Bekende beperkingen:</strong><ul>${r.knownGaps.map((g) => `<li>${esc(g)}</li>`).join('')}</ul></div>
      <div class="card"><div class="card-h">Controletotalen</div><div class="card-b flush"><table class="list"><thead><tr><th></th><th class="num">Aantal</th><th class="num">Totaal</th><th class="num">Openstaand</th></tr></thead><tbody>
      <tr><td>Verkoopfacturen</td><td class="num">${r.totals.verkoopfacturen.aantal}</td><td class="num">${eur(r.totals.verkoopfacturen.totaalInclBtw)}</td><td class="num">${eur(r.totals.verkoopfacturen.openstaandInclBtw)}</td></tr>
      <tr><td>Inkoopfacturen</td><td class="num">${r.totals.inkoopfacturen.aantal}</td><td class="num">${eur(r.totals.inkoopfacturen.totaalInclBtw)}</td><td class="num">${eur(r.totals.inkoopfacturen.openstaandInclBtw)}</td></tr>
      <tr><td>Offertes (ex. BTW)</td><td class="num">${r.totals.offertes.aantal}</td><td class="num">${eur(r.totals.offertes.totaalExclBtw)}</td><td></td></tr>
      <tr><td>Opdrachten (ex. BTW)</td><td class="num">${r.totals.opdrachten.aantal}</td><td class="num">${eur(r.totals.opdrachten.totaalExclBtw)}</td><td></td></tr></tbody></table>
      <p class="pad muted" style="margin:0">Bijlagen: ${r.files.ok}/${r.files.total} · Factuur-PDF's: ${r.invoicePdfs.ok}/${r.invoicePdfs.total}</p></div></div>
      <div class="card"><div class="card-h">Per tabel</div><div class="card-b flush table-wrap"><table class="list"><thead><tr><th>Tabel</th><th class="num">Opgeslagen</th><th class="num">Volgens API</th><th>Gebroken verwijzingen</th><th>Fout</th></tr></thead><tbody>
      ${Object.entries(r.entities).map(([n, e]) => `<tr><td>${esc(CFG[n]?.label || n)} <code class="muted">${n}</code></td><td class="num">${e.stored}</td><td class="num">${e.apiCount ?? '–'}</td><td>${esc(Object.entries(e.brokenRefs).map(([k, v]) => `${k}: ${v}`).join(', '))}</td><td class="neg">${esc(e.error || '')}</td></tr>`).join('')}
      </tbody></table></div></div></div>`;
  }

  // ---------------------------------------------------------------- router
  function render() {
    const { path, q } = parseHash();
    renderNav(path);
    const parts = path.split('/').filter(Boolean);
    let html;
    if (!parts.length) html = pageHome();
    else if (parts[0] === 'zoeken') html = pageSearch(q);
    else if (parts[0] === 'rapport') html = pageReport(parts[1], q);
    else if (parts[0] === 'rapport-import') html = pageImportReport();
    else if (parts[0] === 'bijlagen') html = pageFiles();
    else if (parts[0] === 'exports') html = pageExports();
    else if (parts[0] === 'tabellen') html = pageTables();
    else if (CFG[parts[0]] && parts[1]) html = pageDetail(parts[0], decodeURIComponent(parts[1]), q);
    else if (CFG[parts[0]]) html = pageList(parts[0], q);
    else html = pageHead('Pagina niet gevonden') + '<div class="wide"><div class="empty"><a href="#/">Naar Home</a></div></div>';
    app.innerHTML = html;
    document.title = `${app.querySelector('h1')?.textContent || 'Archief'} · ${D?.meta?.accountName ? D.meta.accountName + ' · ' : ''}Gripp-archief`;
    app.focus({ preventScroll: true });
    window.scrollTo(0, 0);
  }

  app.addEventListener('click', (e) => {
    const th = e.target.closest('th[data-sort]');
    if (th) { const { q } = parseHash(); const i = th.dataset.sort; setQ({ sort: i, dir: q.sort === i && q.dir !== 'desc' ? 'desc' : 'asc' }); return; }
    const more = e.target.closest('[data-more]');
    if (more) { setQ({ limit: more.dataset.more }); return; }
    if (e.target.id === 'csv') { e.preventDefault(); exportCsv(); }
  });
  let tmr;
  app.addEventListener('input', (e) => {
    if (e.target.id === 'listQ') { clearTimeout(tmr); const v = e.target.value; tmr = setTimeout(() => { setQ({ q: v, limit: null }); setTimeout(() => { const el = document.getElementById('listQ'); if (el) { el.focus(); el.setSelectionRange(v.length, v.length); } }, 0); }, 300); }
    if (e.target.id === 'peil') setQ({ peil: e.target.value });
  });

  function exportCsv() {
    const table = app.querySelector('table.list'); if (!table) return;
    const rows = [...table.querySelectorAll('tr')].map((tr) => [...tr.children].map((td) => `"${td.innerText.replace(/\s+/g, ' ').trim().replace(/"/g, '""')}"`).join(';'));
    const blob = new Blob(['﻿' + rows.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `${parseHash().path.split('/')[1] || 'export'}.csv`; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 1000);
  }

  window.addEventListener('hashchange', render);
  render();
})();
