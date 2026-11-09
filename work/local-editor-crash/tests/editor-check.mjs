// Loads block editor screens in a logged-in headless Chrome on headstart.local and reports errors.
// Run from the repo root: node work/local-editor-crash/tests/editor-check.mjs
// It creates a temporary login session for the first admin user and removes it afterwards.
import { execFileSync, spawn } from 'node:child_process';
import { mkdtempSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir, homedir } from 'node:os';
import { join, resolve } from 'node:path';

const repo = resolve(process.cwd());
const wpRoot = join(repo, 'app/public');
const php = '/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php';
const sock = join(homedir(), 'Library/Application Support/Local/run/l_0Fnw6Gq/mysql/mysqld.sock');
const chrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const port = 9334;
const WEEKLY_ID = 991965;
const POST_ID = Number(process.env.HS_POST_ID || 18951);

const screens = [
  { name: 'weekly update, edit', url: `/wp-admin/post.php?post=${WEEKLY_ID}&action=edit`, panel: true },
  { name: 'weekly update, add new', url: '/wp-admin/post-new.php?post_type=weekly_update', panel: true },
  { name: 'post, edit', url: `/wp-admin/post.php?post=${POST_ID}&action=edit`, panel: false },
];

const tmp = mkdtempSync(join(tmpdir(), 'hs-editor-check-'));
const sessionFile = join(tmp, 'session.json');
const evalFile = (code) => {
  const f = join(tmp, 'x.php');
  writeFileSync(f, `<?php\n${code}`);
  return execFileSync(php, ['-d', `mysqli.default_socket=${sock}`, '-d', `pdo_mysql.default_socket=${sock}`, '-d', 'error_reporting=E_ERROR', '-d', 'display_errors=0', 'wp-cli.phar', 'eval-file', f],
    { cwd: wpRoot, env: { ...process.env, HS_SESSION: sessionFile }, encoding: 'utf8' });
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

evalFile(`$u = get_users(['role' => 'administrator', 'number' => 1])[0];
$exp = time() + 1800;
$tok = WP_Session_Tokens::get_instance($u->ID)->create($exp);
file_put_contents(getenv('HS_SESSION'), json_encode(['uid' => $u->ID, 'tok' => $tok, 'cookies' => [
  [LOGGED_IN_COOKIE, wp_generate_auth_cookie($u->ID, $exp, 'logged_in', $tok)],
  [AUTH_COOKIE, wp_generate_auth_cookie($u->ID, $exp, 'auth', $tok)],
]]));`);
const session = JSON.parse(readFileSync(sessionFile, 'utf8'));

const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--window-size=1440,1000', `--user-data-dir=${join(tmp, 'chrome')}`, `--remote-debugging-port=${port}`, 'about:blank'], { stdio: 'ignore' });

async function check(screen) {
  const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json();
  const ws = new WebSocket(target.webSocketDebuggerUrl);
  let id = 0;
  const waiting = {};
  const errors = new Set();
  const warnings = new Set();
  const send = (method, params = {}) => new Promise((r) => { const i = ++id; waiting[i] = r; ws.send(JSON.stringify({ id: i, method, params })); });
  ws.onmessage = (e) => {
    const d = JSON.parse(e.data);
    if (d.id && waiting[d.id]) { waiting[d.id](d.result || d.error); return; }
    if (d.method === 'Runtime.exceptionThrown') {
      const x = d.params.exceptionDetails;
      errors.add((x.exception?.description || x.text).split('\n')[0].slice(0, 160));
    }
    if (d.method === 'Runtime.consoleAPICalled' && d.params.type === 'warning') {
      const text = d.params.args.map((a) => a.value || '').join(' ');
      if (text.includes('hs-local-editor-guard')) warnings.add(text.slice(0, 160));
    }
  };
  await new Promise((r) => { ws.onopen = r; });
  await send('Network.enable');
  await send('Runtime.enable');
  await send('Page.enable');
  for (const [name, value] of session.cookies) {
    for (const path of ['/', '/wp-admin']) await send('Network.setCookie', { name, value, domain: 'headstart.local', path });
  }
  await send('Page.navigate', { url: `http://headstart.local${screen.url}` });
  await sleep(20000);
  const res = await send('Runtime.evaluate', {
    returnByValue: true,
    expression: `JSON.stringify({
      loaded: !!document.querySelector('.editor-editor-interface,.edit-post-layout,.editor-styles-wrapper'),
      guard: !!document.getElementById('hs-local-editor-guard'),
      panelEnabled: (function () { try { return wp.data.select('core/editor').isEditorPanelEnabled('taxonomy-panel-in_this_issue'); } catch (e) { return null; } })()
    })`,
  });
  ws.close();
  const state = JSON.parse(res.result.value);
  const pass = state.loaded && errors.size === 0 && (!screen.panel || state.panelEnabled === false);
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${screen.name}: editor loaded=${state.loaded}, errors=${errors.size}, guard present=${state.guard}${screen.panel ? `, In This Issue panel enabled=${state.panelEnabled}` : ''}`);
  for (const m of errors) console.log(`      error: ${m}`);
  for (const m of warnings) console.log(`      warning: ${m}`);
  return pass;
}

let failed = 0;
try {
  for (let i = 0; i < 20; i++) {
    try { await fetch(`http://127.0.0.1:${port}/json/version`); break; } catch { await sleep(500); }
  }
  for (const s of screens) if (!(await check(s))) failed++;
} finally {
  browser.kill();
  evalFile(`$d = json_decode(file_get_contents(getenv('HS_SESSION')), true);
WP_Session_Tokens::get_instance($d['uid'])->destroy($d['tok']);`);
  await sleep(500);
  rmSync(tmp, { recursive: true, force: true });
  console.log('temporary login session removed');
}
process.exit(failed ? 1 : 0);
