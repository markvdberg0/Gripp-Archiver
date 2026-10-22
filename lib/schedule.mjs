// Automatische back-up inplannen met wat het besturingssysteem zelf heeft, zonder beheerdersrechten:
//   Windows  Taakplanner (taak voor de huidige gebruiker; draait als je bent aangemeld,
//            een gemiste run wordt ingehaald zodra de computer weer aan staat)
//   macOS    launchd (~/Library/LaunchAgents)
//   Linux    crontab van de gebruiker
// De geplande taak draait:  <starter> backup --auto --scheduled --profile=<profiel>
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { TOOL_ROOT } from './config.mjs';

const ARGS = (profile) => ['backup', '--auto', '--scheduled', `--profile=${profile}`];
export const taskName = (profile) => `Gripp Back-up (${profile})`;
const label = (profile) => `nl.gripp-backup.${profile}`;
const plistPath = (profile) => path.join(os.homedir(), 'Library', 'LaunchAgents', `${label(profile)}.plist`);
const cronMarker = (profile) => `# gripp-backup:${profile}`;

export function parseTime(s) {
  const m = String(s ?? '').trim().match(/^(\d{1,2})[:.](\d{2})$/);
  if (!m || +m[1] > 23 || +m[2] > 59) throw new Error(`Ongeldig tijdstip "${s}". Gebruik UU:MM, bijvoorbeeld 02:00.`);
  return { hour: +m[1], minute: +m[2], text: `${m[1].padStart(2, '0')}:${m[2]}` };
}

// ---------------------------------------------------------------- Windows
const psq = (s) => `'${String(s).replace(/'/g, "''")}'`;
function powershell(script) {
  const r = spawnSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-EncodedCommand', Buffer.from(script, 'utf16le').toString('base64')], { encoding: 'utf8' });
  if (r.error) throw r.error;
  return { ok: r.status === 0, out: r.stdout.trim(), err: r.stderr.trim() };
}

export function windowsScript(profile, t, { register = true } = {}) {
  const starter = path.join(TOOL_ROOT, 'tools', 'start.ps1');
  const argument = `-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "${starter}" ${ARGS(profile).join(' ')}`;
  return [
    "$ErrorActionPreference = 'Stop'",
    `$a = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ${psq(argument)} -WorkingDirectory ${psq(TOOL_ROOT)}`,
    `$t = New-ScheduledTaskTrigger -Daily -At ${psq(t.text)}`,
    '$s = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Hours 12)',
    register
      ? `Register-ScheduledTask -TaskName ${psq(taskName(profile))} -Description ${psq(`Dagelijkse back-up van Gripp-profiel ${profile} (${TOOL_ROOT})`)} -Action $a -Trigger $t -Settings $s -Force | Out-Null`
      : "'ok'",
  ].join('\n');
}

// ---------------------------------------------------------------- macOS
const xml = (s) => String(s).replace(/[<>&'"]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', "'": '&apos;', '"': '&quot;' }[c]));
export function macPlist(profile, t) {
  const args = ['/bin/sh', path.join(TOOL_ROOT, 'start.sh'), ...ARGS(profile)];
  return `<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>Label</key><string>${xml(label(profile))}</string>
  <key>ProgramArguments</key>
  <array>${args.map((a) => `<string>${xml(a)}</string>`).join('')}</array>
  <key>WorkingDirectory</key><string>${xml(TOOL_ROOT)}</string>
  <key>StartCalendarInterval</key>
  <dict><key>Hour</key><integer>${t.hour}</integer><key>Minute</key><integer>${t.minute}</integer></dict>
</dict>
</plist>
`;
}
const launchctl = (...a) => spawnSync('launchctl', a, { encoding: 'utf8' });
const domain = () => `gui/${process.getuid()}`;

// ---------------------------------------------------------------- Linux
const shq = (s) => `'${String(s).replace(/'/g, `'\\''`)}'`;
export function cronLine(profile, t) {
  return `${t.minute} ${t.hour} * * * cd ${shq(TOOL_ROOT)} && /bin/sh ./start.sh ${ARGS(profile).join(' ')} >/dev/null 2>&1 ${cronMarker(profile)}`;
}
function readCrontab() {
  const r = spawnSync('crontab', ['-l'], { encoding: 'utf8' });
  if (r.error) throw new Error('crontab is niet beschikbaar op deze computer.');
  return r.status === 0 ? r.stdout : '';   // status 1 = nog geen crontab
}
function writeCrontab(text) {
  const r = spawnSync('crontab', ['-'], { input: text, encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`crontab bijwerken mislukt: ${r.stderr.trim()}`);
}
const withoutProfile = (text, profile) => text.split('\n').filter((l) => l.trim() && !l.endsWith(cronMarker(profile))).join('\n');

// ---------------------------------------------------------------- algemeen
export function enableSchedule(profile, time, { dryRun = false } = {}) {
  const t = parseTime(time);
  if (process.platform === 'win32') {
    const r = powershell(windowsScript(profile, t, { register: !dryRun }));
    if (!r.ok) throw new Error(`Taak aanmaken mislukt: ${r.err || r.out}`);
  } else if (process.platform === 'darwin') {
    if (dryRun) return t;
    fs.mkdirSync(path.dirname(plistPath(profile)), { recursive: true });
    fs.writeFileSync(plistPath(profile), macPlist(profile, t));
    launchctl('bootout', `${domain()}/${label(profile)}`);
    const r = launchctl('bootstrap', domain(), plistPath(profile));
    if (r.status !== 0 && launchctl('load', '-w', plistPath(profile)).status !== 0) throw new Error(`launchd: ${r.stderr.trim()}`);
  } else {
    if (dryRun) return t;
    const rest = withoutProfile(readCrontab(), profile);
    writeCrontab(`${rest ? rest + '\n' : ''}${cronLine(profile, t)}\n`);
  }
  return t;
}

export function disableSchedule(profile) {
  if (process.platform === 'win32') {
    const r = powershell(`Unregister-ScheduledTask -TaskName ${psq(taskName(profile))} -Confirm:$false -ErrorAction SilentlyContinue`);
    if (!r.ok) throw new Error(`Taak verwijderen mislukt: ${r.err}`);
  } else if (process.platform === 'darwin') {
    launchctl('bootout', `${domain()}/${label(profile)}`);
    fs.rmSync(plistPath(profile), { force: true });
  } else {
    const rest = withoutProfile(readCrontab(), profile);
    writeCrontab(rest ? rest + '\n' : '');
  }
}

// → null (niet ingepland) of { time: 'UU:MM', next?: string, lastRun?: string, lastResult?: number }
export function scheduleStatus(profile) {
  if (process.platform === 'win32') {
    const r = powershell(`$t = Get-ScheduledTask -TaskName ${psq(taskName(profile))} -ErrorAction SilentlyContinue
if ($t) { $i = $t | Get-ScheduledTaskInfo; $f = 'yyyy-MM-dd HH:mm'
  $last = ''; if ($i.LastRunTime -and $i.LastRunTime.Year -gt 2000) { $last = $i.LastRunTime.ToString($f) }
  $next = ''; if ($i.NextRunTime) { $next = $i.NextRunTime.ToString($f) }
  ([datetime]$t.Triggers[0].StartBoundary).ToString('HH:mm') + '|' + $next + '|' + $last + '|' + $i.LastTaskResult }`);
    if (!r.ok || !r.out) return null;
    const [time, next, lastRun, lastResult] = r.out.split('|');
    return { time, next: next || null, lastRun: lastRun || null, lastResult: lastRun ? Number(lastResult) : null };
  }
  if (process.platform === 'darwin') {
    if (!fs.existsSync(plistPath(profile))) return null;
    const m = fs.readFileSync(plistPath(profile), 'utf8').match(/<key>Hour<\/key><integer>(\d+)<\/integer><key>Minute<\/key><integer>(\d+)<\/integer>/);
    return { time: m ? `${m[1].padStart(2, '0')}:${m[2].padStart(2, '0')}` : '?' };
  }
  let tab;
  try { tab = readCrontab(); } catch { return null; }
  const line = tab.split('\n').find((l) => l.endsWith(cronMarker(profile)));
  if (!line) return null;
  const [min, hour] = line.split(' ');
  return { time: `${hour.padStart(2, '0')}:${min.padStart(2, '0')}` };
}
