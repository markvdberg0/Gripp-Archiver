// Werkbank — kleine hulpjes zonder afhankelijkheden. De Content-Security-Policy staat geen inline scripts toe.
(function () {
  'use strict';

  // Bevestiging bij formulieren met data-confirm="…".
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Keuzelijst die bij wijzigen het formulier verstuurt (filters).
  document.addEventListener('change', function (e) {
    if (e.target.matches && e.target.matches('[data-autosubmit]')) e.target.form.submit();
  });

  // Regels-editor: regels toevoegen/verwijderen en totalen live tonen.
  // Zelfde regel als de server (Bedragen::parse): met komma is de punt een duizendtal-scheiding.
  function num(v) { var s = String(v || '').replace(/[\s€]/g, ''); if (s.indexOf(',') >= 0) s = s.replace(/\./g, '').replace(',', '.'); var n = parseFloat(s); return isNaN(n) ? 0 : n; }
  function eur(n) { return '€ ' + n.toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  function recalc(table) {
    var excl = 0, vat = 0;
    table.querySelectorAll('tbody tr[data-line]').forEach(function (tr) {
      var type = tr.querySelector('[data-f=row_type]');
      var isGroup = type && type.value === 'groep';
      tr.classList.toggle('group', isGroup);
      var q = num(tr.querySelector('[data-f=quantity]') && tr.querySelector('[data-f=quantity]').value);
      var p = num(tr.querySelector('[data-f=unit_price]') && tr.querySelector('[data-f=unit_price]').value);
      var d = num(tr.querySelector('[data-f=discount]') && tr.querySelector('[data-f=discount]').value);
      var vatSel = tr.querySelector('[data-f=vat_rate_id]');
      var pct = vatSel && vatSel.selectedOptions[0] ? num(vatSel.selectedOptions[0].getAttribute('data-pct')) : 0;
      var total = isGroup ? 0 : Math.round(q * p * (1 - d / 100) * 100) / 100;
      excl += total; vat += Math.round(total * pct) / 100;
      var out = tr.querySelector('[data-total]');
      if (out) out.textContent = isGroup ? '' : eur(total);
    });
    var set = function (sel, v) { var el = table.querySelector(sel); if (el) el.textContent = eur(v); };
    set('[data-sum=excl]', excl); set('[data-sum=vat]', vat); set('[data-sum=incl]', excl + vat);
  }

  document.querySelectorAll('table[data-lines]').forEach(function (table) {
    var tpl = document.getElementById(table.getAttribute('data-template'));
    var tbody = table.querySelector('tbody');
    var counter = tbody.querySelectorAll('tr[data-line]').length + 1000;
    table.addEventListener('input', function () { recalc(table); });
    table.addEventListener('change', function (e) {
      var sel = e.target;
      // Product gekozen: omschrijving, prijs, eenheid en btw overnemen als die nog leeg zijn.
      if (sel.matches('[data-f=product_id]') && sel.selectedOptions[0]) {
        var o = sel.selectedOptions[0], tr = sel.closest('tr');
        var fill = function (f, v) { var el = tr.querySelector('[data-f=' + f + ']'); if (el && v != null && v !== '' && (!el.value || el.value === '0' || el.value === '0,00')) el.value = v; };
        fill('description', o.getAttribute('data-name'));
        fill('unit_price', o.getAttribute('data-price'));
        fill('unit', o.getAttribute('data-unit'));
        var vatEl = tr.querySelector('[data-f=vat_rate_id]');
        if (vatEl && o.getAttribute('data-vat')) vatEl.value = o.getAttribute('data-vat');
      }
      recalc(table);
    });
    table.addEventListener('click', function (e) {
      if (e.target.matches('[data-remove]')) { e.preventDefault(); e.target.closest('tr').remove(); recalc(table); }
      if (e.target.matches('[data-up],[data-down]')) {
        e.preventDefault();
        var tr = e.target.closest('tr');
        if (e.target.matches('[data-up]') && tr.previousElementSibling) tr.parentNode.insertBefore(tr, tr.previousElementSibling);
        if (e.target.matches('[data-down]') && tr.nextElementSibling) tr.parentNode.insertBefore(tr.nextElementSibling, tr);
      }
    });
    var add = document.querySelector('[data-add-line="' + table.id + '"]');
    if (add && tpl) add.addEventListener('click', function (e) {
      e.preventDefault();
      tbody.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, String(counter++)));
      recalc(table);
    });
    recalc(table);
  });
})();
