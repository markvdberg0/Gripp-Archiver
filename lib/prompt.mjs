// Kleine invoerhulp voor menu en wizard.
// - In een terminal opent en sluit elke vraag zijn eigen readline, zodat stdin vrij is voor
//   processen die tussendoor draaien (bv. de import).
// - Zonder terminal (antwoorden via een pipe, bv. in een script of test) worden de regels
//   één voor één uit één gedeelde lezer gehaald; sluit die aan het eind met closePrompts().
import readline from 'node:readline/promises';

let piped = null;   // { rl, lines } bij invoer zonder terminal

async function nextPipedLine(question) {
  process.stdout.write(question);
  if (!piped) { const rl = readline.createInterface({ input: process.stdin }); piped = { rl, lines: rl[Symbol.asyncIterator]() }; }
  const { value, done } = await piped.lines.next();
  if (done) throw new Error('Invoer onverwacht beëindigd.');
  process.stdout.write('\n');
  return value;
}

export function closePrompts() {
  piped?.rl.close();
  piped = null;
}

export async function ask(question, def = '') {
  const q = def ? `${question} [${def}] ` : `${question} `;
  if (!process.stdin.isTTY) return (await nextPipedLine(q)).trim() || def;
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  rl.on('SIGINT', () => { rl.close(); process.stdout.write('\n'); process.exit(130); });   // anders blijft Ctrl+C hangen
  try {
    return (await rl.question(q)).trim() || def;
  } finally {
    rl.close();
  }
}

export async function confirm(question, def = true) {
  for (;;) {
    const a = (await ask(`${question} (${def ? 'J/n' : 'j/N'})`)).toLowerCase();
    if (!a) return def;
    if (['j', 'ja', 'y', 'yes'].includes(a)) return true;
    if (['n', 'nee', 'no'].includes(a)) return false;
  }
}

// Verborgen invoer (voor het API-token): toont een * per teken. Plakken werkt.
export function askSecret(question) {
  const stdin = process.stdin;
  if (!stdin.isTTY) return nextPipedLine(`${question} `).then((s) => s.trim());
  return new Promise((resolve) => {
    let buf = '';
    process.stdout.write(`${question} `);
    const cleanup = () => { stdin.off('data', onData); stdin.setRawMode(false); stdin.pause(); };
    const onData = (s) => {
      for (const ch of s) {
        if (ch === '\r' || ch === '\n') { cleanup(); process.stdout.write('\n'); resolve(buf.trim()); return; }
        if (ch === '\u0003') { cleanup(); process.stdout.write('\n'); process.exit(130); }   // Ctrl+C
        if (ch === '\u007f' || ch === '\b') { if (buf) { buf = buf.slice(0, -1); process.stdout.write('\b \b'); } continue; }
        if (ch >= ' ') { buf += ch; process.stdout.write('*'); }
      }
    };
    stdin.setRawMode(true);
    stdin.setEncoding('utf8');
    stdin.on('data', onData);
    stdin.resume();
  });
}
