// Minimale, read-only Gripp API v3 client (JSON-RPC, batch).
// - Weigert elke methode die niet in ALLOWED_METHOD staat (geen create/update/delete).
// - Respecteert rate limits: token bucket (≤4 req/s), X-RateLimit-Remaining, en wacht bij 429/503.
// - Retries met exponential backoff.
// Het API-token wordt nooit gelogd.
import { ALLOWED_METHOD } from './schema.mjs';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

export class ReadOnlyViolation extends Error {}

export class GrippClient {
  constructor({ token, url = 'https://api.gripp.com/public/api3.php', log = console.log, maxRetries = 6, minRemaining = 5 }) {
    if (!token) throw new Error('GRIPP_API_TOKEN ontbreekt (zie README: profiles/<naam>/config.env).');
    this.token = token;
    this.url = url;
    this.log = log;
    this.maxRetries = maxRetries;
    this.minRemaining = minRemaining;
    this.nextId = 1;
    this.lastRequestAt = 0;
    this.requests = 0;          // HTTP-requests
    this.calls = 0;             // RPC-calls
    this.rateLimit = null;      // { limit, remaining, at }
  }

  assertAllowed(method) {
    if (!ALLOWED_METHOD.test(method)) {
      throw new ReadOnlyViolation(`Methode "${method}" is niet toegestaan: deze client is uitsluitend lezend.`);
    }
  }

  // calls: [{ method, params }] → array met { result } of { error } in dezelfde volgorde
  async batch(calls) {
    for (const c of calls) this.assertAllowed(c.method);
    const body = calls.map((c) => ({ method: c.method, params: c.params ?? [], id: this.nextId++ }));
    const ids = body.map((b) => b.id);

    for (let attempt = 0; ; attempt++) {
      await this.throttle(calls.length);
      let res, text;
      try {
        res = await fetch(this.url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${this.token}` },
          body: JSON.stringify(body),
        });
        this.requests++;
        this.readRateHeaders(res);
        text = await res.text();
      } catch (e) {
        if (attempt >= this.maxRetries) throw e;
        await this.backoff(attempt, `netwerkfout: ${e.message}`);
        continue;
      }

      if (res.status === 429 || res.status === 503 || res.status >= 500) {
        if (attempt >= this.maxRetries) throw new Error(`HTTP ${res.status} na ${attempt + 1} pogingen`);
        await this.backoff(attempt, `HTTP ${res.status}`, res.status === 429 ? 60_000 : 0);
        continue;
      }
      if (res.status === 401 || res.status === 403) throw new Error(`HTTP ${res.status}: API-token ongeldig of onvoldoende rechten.`);

      let json;
      try { json = JSON.parse(text); } catch { throw new Error(`Geen geldige JSON van API (HTTP ${res.status}): ${text.slice(0, 200)}`); }
      const arr = Array.isArray(json) ? json : [json];

      // Rate-limit-fout van Gripp als JSON-RPC-error (code 1004 of tekst)
      const rl = arr.find((r) => r && r.error && /rate|limit|1004/i.test(JSON.stringify(r.error) + (r.error_code ?? '')));
      if (rl) {
        if (attempt >= this.maxRetries) throw new Error(`Rate limit bleef actief: ${JSON.stringify(rl.error)}`);
        await this.waitForReset('rate limit melding van Gripp');
        continue;
      }

      this.calls += calls.length;
      const byId = new Map(arr.map((r) => [r.id, r]));
      // Gripp voert een batch uit als één transactie: faalt één call (bv. onvoldoende rechten),
      // dan krijgen de andere calls géén antwoord. Die voeren we daarom los opnieuw uit.
      const missing = ids.map((id, i) => (byId.has(id) ? -1 : i)).filter((i) => i >= 0);
      if (missing.length && calls.length > 1) {
        const out = ids.map((id) => byId.get(id));
        for (const i of missing) [out[i]] = await this.batch([calls[i]]);
        return out;
      }
      return ids.map((id) => byId.get(id) ?? { error: 'Geen antwoord voor dit verzoek' });
    }
  }

  async call(method, params) {
    const [r] = await this.batch([{ method, params }]);
    if (r.error) throw new Error(`${method}: ${typeof r.error === 'string' ? r.error : JSON.stringify(r.error)}`);
    return r.result;
  }

  readRateHeaders(res) {
    const limit = Number(res.headers.get('x-ratelimit-limit'));
    const remaining = Number(res.headers.get('x-ratelimit-remaining'));
    if (Number.isFinite(limit) && res.headers.has('x-ratelimit-limit')) {
      this.rateLimit = { limit, remaining, at: Date.now() };
    }
  }

  // Token bucket ~4 req/s + uurlimiet-bewaking
  async throttle(nCalls) {
    const gap = 300;
    const wait = this.lastRequestAt + gap - Date.now();
    if (wait > 0) await sleep(wait);
    if (this.rateLimit && this.rateLimit.remaining - nCalls < this.minRemaining) {
      await this.waitForReset(`nog ${this.rateLimit.remaining} van ${this.rateLimit.limit} requests over dit uur`);
    }
    this.lastRequestAt = Date.now();
  }

  async waitForReset(reason) {
    // Gripp geeft geen reset-tijdstip; we wachten tot het volgende hele uur + 1 minuut.
    const now = new Date();
    const next = new Date(now); next.setMinutes(61, 0, 0);
    const ms = Math.max(60_000, next - now);
    this.log(`⏸  Rate limit (${reason}). Wachten tot ${next.toLocaleTimeString('nl-NL')} … (Ctrl+C mag; later hervatten met --resume)`);
    await sleep(ms);
    this.rateLimit = null;
  }

  async backoff(attempt, reason, minMs = 0) {
    const ms = Math.max(minMs, Math.min(120_000, 1000 * 2 ** attempt + Math.random() * 500));
    this.log(`↻  ${reason} — opnieuw over ${Math.round(ms / 1000)} s (poging ${attempt + 2})`);
    await sleep(ms);
  }
}

// Haalt rows uit een .get-resultaat, ongeacht de exacte vorm.
export function parseGetResult(result, maxresults) {
  if (result == null) return { rows: [], more: false, count: null };
  if (Array.isArray(result)) return { rows: result, more: result.length >= maxresults, count: null };
  const rows = result.rows ?? result.items ?? result.data ?? [];
  const count = Number.isFinite(result.count) ? result.count : null;
  const more = typeof result.more_items_in_collection === 'boolean' ? result.more_items_in_collection : rows.length >= maxresults;
  return { rows, more, count };
}
