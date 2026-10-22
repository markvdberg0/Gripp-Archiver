#!/usr/bin/env node
// Optionele, read-only webserver (alleen localhost). Niet nodig: viewer/index.html werkt ook via dubbelklik.
//   npm run serve [-- --profile=naam]  →  http://localhost:4173
// Alleen GET/HEAD; alle andere methodes geven 405. Serveert uitsluitend viewer/ en archive/ uit de back-upmap.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { loadConfig } from './lib/config.mjs';

const ROOT = loadConfig().backupDir;
const PORT = Number(process.env.PORT || 4173);
const HOST = process.env.HOST || '127.0.0.1';
const TYPES = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.css': 'text/css', '.json': 'application/json', '.pdf': 'application/pdf', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.svg': 'image/svg+xml', '.zip': 'application/zip', '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '.xml': 'application/xml', '.xaf': 'application/xml' };

http.createServer((req, res) => {
  const sec = { 'X-Content-Type-Options': 'nosniff', 'Referrer-Policy': 'no-referrer', 'X-Frame-Options': 'SAMEORIGIN' };
  if (req.method !== 'GET' && req.method !== 'HEAD') { res.writeHead(405, { Allow: 'GET, HEAD', ...sec }); return res.end('Alleen-lezen archief'); }
  let p = decodeURIComponent(new URL(req.url, 'http://x').pathname);
  if (p === '/') { res.writeHead(302, { Location: '/viewer/index.html' }); return res.end(); }
  const abs = path.normalize(path.join(ROOT, p));
  const okRoot = [path.join(ROOT, 'viewer'), path.join(ROOT, 'archive')].some((r) => abs.startsWith(r + path.sep));
  if (!okRoot || !fs.existsSync(abs) || fs.statSync(abs).isDirectory()) { res.writeHead(404, sec); return res.end('Niet gevonden'); }
  res.writeHead(200, { 'Content-Type': TYPES[path.extname(abs).toLowerCase()] || 'application/octet-stream', ...sec });
  if (req.method === 'HEAD') return res.end();
  fs.createReadStream(abs).pipe(res);
}).listen(PORT, HOST, () => console.log(`Gripp-archief: http://${HOST === '0.0.0.0' ? 'localhost' : HOST}:${PORT}  (Ctrl+C om te stoppen)`));
