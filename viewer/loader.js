/* Laadt de viewer-data in delen (data/part-*.js, zie importer/build.mjs) met een voortgangsbalk,
   en start daarna app.js. Werkt ook zonder server (file://): alleen <script>-tags, geen fetch. */
(function () {
  'use strict';
  const app = document.getElementById('app');
  const D = window.GRIPP_DATA;
  const parts = (D && D.parts) || [];   // oudere data.js (één bestand) heeft geen delen
  const total = parts.reduce((a, p) => a + p.rows, 0);
  const LABEL = { company: 'relaties', contact: 'contactpersonen', offer: 'offertes', project: 'opdrachten', offerprojectline: 'offerte- en opdrachtregels', invoice: 'facturen', invoiceline: 'factuurregels', purchaseinvoice: 'inkoopfacturen', timelineentry: 'timeline', hour: 'uren', task: 'taken', file: 'bestanden' };
  let done = 0;

  function load(src, onload) {
    const s = document.createElement('script');
    s.src = src;
    s.onload = onload;
    s.onerror = () => { app.innerHTML = '<div class="wide"><div class="card"><div class="card-h">Archief niet volledig</div><div class="card-b"><p>Het bestand <code>viewer/' + src + '</code> ontbreekt of is beschadigd. Kies in de tool <b>Viewer opnieuw bouwen</b>.</p></div></div></div>'; };
    document.body.appendChild(s);
  }

  function next(i) {
    if (i >= parts.length) { load('app.js'); return; }
    const p = parts[i];
    const pct = total ? Math.round((done / total) * 100) : 0;
    app.innerHTML = '<div class="wide"><p class="muted pad">Archief laden… ' + pct + '% (' + (LABEL[p.entity] || p.entity) + ')</p><progress max="100" value="' + pct + '" style="width:100%;max-width:420px;margin:0 16px"></progress></div>';
    load('data/' + p.file, function () { done += p.rows; next(i + 1); });
  }

  next(0);
})();
