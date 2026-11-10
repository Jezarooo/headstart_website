// Measures the Weekly Updates list in wp-admin (headless Chrome, logged in) for the compact In This Issue chips.
import { execFileSync, spawn } from 'node:child_process';
import { mkdtempSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir, homedir } from 'node:os';
import { join, resolve } from 'node:path';

const repo = resolve(process.cwd());
const shots = process.argv[2] || '';
const wpRoot = join(repo, 'app/public');
const php = '/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php';
const sock = join(homedir(), 'Library/Application Support/Local/run/l_0Fnw6Gq/mysql/mysqld.sock');
const chrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const port = 9338;

const tmp = mkdtempSync(join(tmpdir(), 'hs-list-check-'));
const sessionFile = join(tmp, 'session.json');
const evalFile = (code) => {
  const f = join(tmp, 'x.php');
  writeFileSync(f, `<?php\n${code}`);
  return execFileSync(php, ['-d', `mysqli.default_socket=${sock}`, '-d', `pdo_mysql.default_socket=${sock}`, '-d', 'error_reporting=E_ERROR', '-d', 'display_errors=0', 'wp-cli.phar', 'eval-file', f],
    { cwd: wpRoot, env: { ...process.env, HS_SESSION: sessionFile }, encoding: 'utf8' });
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let pass = 0; let fail = 0;
const t = (name, ok, detail = '') => { if (ok) { pass++; console.log(`  ok    ${name}`); } else { fail++; console.log(`  FAIL  ${name}${detail ? ` — ${detail}` : ''}`); } };



// Usage: node work/weekly-list-issue-chips/tests/list-check.mjs [screenshot-dir] [label]
// Measures the Weekly Updates list rows; with a label (before/after) saves a screenshot and the numbers.
const label = process.argv[3] || '';
evalFile(`$u = get_users(['role' => 'administrator', 'number' => 1])[0];
$exp = time() + 1800;
$tok = WP_Session_Tokens::get_instance($u->ID)->create($exp);
file_put_contents(getenv('HS_SESSION'), json_encode(['uid' => $u->ID, 'tok' => $tok, 'cookies' => [
  [LOGGED_IN_COOKIE, wp_generate_auth_cookie($u->ID, $exp, 'logged_in', $tok)],
  [AUTH_COOKIE, wp_generate_auth_cookie($u->ID, $exp, 'auth', $tok)],
]]));`);
const session = JSON.parse(readFileSync(sessionFile, 'utf8'));
const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--window-size=1440,1800', `--user-data-dir=${join(tmp, 'chrome')}`, `--remote-debugging-port=${port}`, 'about:blank'], { stdio: 'ignore' });
const BIG = 992122; // Weekly Update #10, 17 articles, no blurb yet
const NINE = 991290; // Weekly Update #9, 15 articles and a 517-character blurb
let ws;
try {
  for (let i = 0; i < 20; i++) { try { await fetch(`http://127.0.0.1:${port}/json/version`); break; } catch { await sleep(500); } }
  const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json();
  ws = new WebSocket(target.webSocketDebuggerUrl);
  let id = 0;
  const waiting = {};
  const errors = new Set();
  const send = (method, params = {}) => new Promise((r) => { const i = ++id; waiting[i] = r; ws.send(JSON.stringify({ id: i, method, params })); });
  ws.onmessage = (e) => {
    const d = JSON.parse(e.data);
    if (d.id && waiting[d.id]) { waiting[d.id](d.result || d.error); return; }
    if (d.method === 'Runtime.exceptionThrown') { const x = d.params.exceptionDetails; errors.add((x.exception?.description || x.text).split('\n')[0].slice(0, 200)); }
  };
  await new Promise((r) => { ws.onopen = r; });
  for (const m of ['Network.enable', 'Runtime.enable', 'Page.enable']) await send(m);
  for (const [name, value] of session.cookies) for (const path of ['/', '/wp-admin']) await send('Network.setCookie', { name, value, domain: 'headstart.local', path });
  await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1800, deviceScaleFactor: 1, mobile: false });
  const run = async (expr) => {
    const r = await send('Runtime.evaluate', { expression: `(async function(){ ${expr} })()`, awaitPromise: true, returnByValue: true });
    if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
    return r.result.value;
  };
  await send('Page.navigate', { url: 'http://headstart.local/wp-admin/edit.php?post_type=weekly_update&posts_per_page=40' });
  await sleep(6000);
  const m = JSON.parse(await run(`const row = document.getElementById('post-${BIG}');
    const cell = (r, c) => r.querySelector('td.column-' + c);
    const h = (el) => el ? Math.round(el.getBoundingClientRect().height) : 0;
    const w = (c) => Math.round(document.querySelector('th.column-' + c).getBoundingClientRect().width);
    const inner = (r, c) => { const el = cell(r, c); const kids = el ? [...el.children] : []; return kids.length ? Math.round(kids[kids.length - 1].getBoundingClientRect().bottom - kids[0].getBoundingClientRect().top) : 0; };
    const chip = row.querySelector('.column-hs_tags_col .hs-tags-display span');
    const old = [...document.querySelectorAll('#the-list tr')].find((r) => r.querySelector('.hs-tags-checkbox'));
    const editBtn = row.querySelector('.column-hs_tags_col a.button'), chips = [...row.querySelectorAll('.column-hs_tags_col .hs-tags-display span')];
    const lastChip = chips[chips.length - 1];
    const nine = document.getElementById('post-${NINE}');
    return JSON.stringify({ nine: nine ? { tags: inner(nine, 'hs_tags_col'), blurb: inner(nine, 'hs_blurb_col'), row: h(nine) } : null, row: h(row), tagsInner: inner(row, 'hs_tags_col'), blurbInner: inner(row, 'hs_blurb_col'), chips: chips.length,
      widths: { tags: w('hs_tags_col'), blurb: w('hs_blurb_col'), campus: w('hs_campus_col'), week: w('hs_week_col') }, all: [...document.querySelectorAll('.wp-list-table thead th, .wp-list-table thead td')].map((x) => x.id + ':' + Math.round(x.getBoundingClientRect().width)), titleH: h(cell(row, 'title') || row.querySelector('.column-title')),
      font: chip ? parseFloat(getComputedStyle(chip).fontSize) : 0,
      editInline: !!(editBtn && lastChip && editBtn.getBoundingClientRect().top < lastChip.getBoundingClientRect().bottom),
      old: old ? { tags: old.querySelectorAll('.hs-tags-display span').length, editor: !!old.querySelector('.hs-tags-editor .hs-tags-checkbox'), font: parseFloat(getComputedStyle(old.querySelector('.hs-tags-display span') || document.body).fontSize) } : null });`));
  console.log(JSON.stringify(m));
  if (shots && label) {
    writeFileSync(join(shots, `list-${label}.json`), JSON.stringify(m));
    const r = JSON.parse(await run(`const row = document.getElementById('post-${BIG}'); row.scrollIntoView({ block: 'start' }); await new Promise((r) => setTimeout(r, 300)); const b = row.getBoundingClientRect(); return JSON.stringify({ y: b.top + scrollY, h: 1000 });`));
    const pic = await send('Page.captureScreenshot', { format: 'png', clip: { x: 160, y: Math.max(0, r.y - 40), width: 1280, height: r.h, scale: 0.75 }, captureBeyondViewport: true });
    writeFileSync(join(shots, `list-${label}.png`), Buffer.from(pic.data, 'base64'));
  }
  if (label === 'after') {
    let before = null; try { before = JSON.parse(readFileSync(join(shots, 'list-before.json'), 'utf8')); } catch {}
    const ws4 = m.widths;
    t('In This Issue is the widest of the four columns', ws4.tags > ws4.blurb && ws4.tags > ws4.campus && ws4.tags > ws4.week, JSON.stringify(ws4));
    t('chips are 11px or smaller', m.font > 0 && m.font <= 11, String(m.font));
    t('Weekly Update #9 (15 articles): the In This Issue cell is no taller than 1.5× its blurb', m.nine && m.nine.tags <= 1.5 * m.nine.blurb, JSON.stringify(m.nine));
    t('Weekly Update #10 (17 articles): the row is at least 40% shorter than before', before && m.row <= 0.6 * before.row, JSON.stringify({ before: before && before.row, after: m.row }));
    t('the Title column keeps a readable width', +((m.all.find((x) => x.startsWith('title:')) || ':0').split(':')[1]) >= 150, JSON.stringify(m.all));
    t('Edit sits right after the chips', m.editInline);
    t('an older issue still shows its tags and tag editor, in the same compact chips', m.old && m.old.tags > 0 && m.old.editor && m.old.font <= 11, JSON.stringify(m.old));
    t('no JavaScript errors', errors.size === 0, [...errors].join(' | '));
  }
} catch (e) {
  t('check finished', false, String(e));
} finally {
  try { ws && ws.close(); } catch {}
  browser.kill();
  await sleep(500); try { rmSync(tmp, { recursive: true, force: true }); } catch {}
  if (label === 'after') { console.log(`\n${pass} passed, ${fail} failed`); }
  process.exit(fail ? 1 : 0);
}
