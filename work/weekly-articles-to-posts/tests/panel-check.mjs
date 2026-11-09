// Drives the Articles → Posts panel in a logged-in headless Chrome on headstart.local.
// Run from the repo root: node work/weekly-articles-to-posts/tests/panel-check.mjs [screenshot-dir]
// It creates one Live and one Draft Post on a real weekly update, then force-deletes them and clears the saved rows.
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
const port = 9335;
const ISSUE = 990073;

const tmp = mkdtempSync(join(tmpdir(), 'hs-panel-check-'));
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

const issueState = () => evalFile(`$p = get_post(${ISSUE}); echo md5($p->post_content) . '|' . $p->post_modified . '|' . $p->post_status;`).trim();
const stateBefore = issueState();
evalFile(`delete_post_meta(${ISSUE}, '_hs_a2p_rows');
$u = get_users(['role' => 'administrator', 'number' => 1])[0];
$exp = time() + 1800;
$tok = WP_Session_Tokens::get_instance($u->ID)->create($exp);
file_put_contents(getenv('HS_SESSION'), json_encode(['uid' => $u->ID, 'tok' => $tok, 'cookies' => [
  [LOGGED_IN_COOKIE, wp_generate_auth_cookie($u->ID, $exp, 'logged_in', $tok)],
  [AUTH_COOKIE, wp_generate_auth_cookie($u->ID, $exp, 'auth', $tok)],
]]));`);
const session = JSON.parse(readFileSync(sessionFile, 'utf8'));
const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--window-size=1440,1800', `--user-data-dir=${join(tmp, 'chrome')}`, `--remote-debugging-port=${port}`, 'about:blank'], { stdio: 'ignore' });

let ws;
try {
  for (let i = 0; i < 20; i++) { try { await fetch(`http://127.0.0.1:${port}/json/version`); break; } catch { await sleep(500); } }
  const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json();
  ws = new WebSocket(target.webSocketDebuggerUrl);
  let id = 0;
  const waiting = {};
  const errors = new Set();
  const dialogs = [];
  const send = (method, params = {}) => new Promise((r) => { const i = ++id; waiting[i] = r; ws.send(JSON.stringify({ id: i, method, params })); });
  ws.onmessage = (e) => {
    const d = JSON.parse(e.data);
    if (d.id && waiting[d.id]) { waiting[d.id](d.result || d.error); return; }
    if (d.method === 'Runtime.exceptionThrown') { const x = d.params.exceptionDetails; errors.add((x.exception?.description || x.text).split('\n')[0].slice(0, 200)); }
    if (d.method === 'Page.javascriptDialogOpening') { dialogs.push(d.params.message); send('Page.handleJavaScriptDialog', { accept: true }); }
  };
  await new Promise((r) => { ws.onopen = r; });
  for (const m of ['Network.enable', 'Runtime.enable', 'Page.enable']) await send(m);
  for (const [name, value] of session.cookies) for (const path of ['/', '/wp-admin']) await send('Network.setCookie', { name, value, domain: 'headstart.local', path });
  await send('Page.navigate', { url: `http://headstart.local/wp-admin/post.php?post=${ISSUE}&action=edit` });
  await sleep(18000);

  const run = async (expr) => {
    const r = await send('Runtime.evaluate', { expression: `(async function(){ ${expr} })()`, awaitPromise: true, returnByValue: true });
    if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
    return r.result.value;
  };
  const shot = async (name) => {
    if (!shots) return;
    // Screenshots only: hide the content canvas so the meta box pane fills the window, then show the ticked row or the result.
    await run(`document.querySelectorAll('.editor-visual-editor').forEach((n) => { n.style.display = 'none'; });
      document.querySelectorAll('.edit-post-meta-boxes-main').forEach((n) => { n.style.height = '100%'; n.style.maxHeight = 'none'; n.style.flex = '1 1 100%'; });
      (document.querySelector('#hs-a2p .hs-a2p-message') || document.querySelector('#hs-a2p .hs-a2p-body') || document.getElementById('hs-a2p')).scrollIntoView({ block: 'center' });`);
    await sleep(1000);
    const p = await send('Page.captureScreenshot', { format: 'png' });
    writeFileSync(join(shots, name), Buffer.from(p.data, 'base64'));
  };
  const helpers = `
    const rows = () => [...document.querySelectorAll('#hs-a2p .hs-a2p-row')];
    const fieldOf = (row, label) => [...row.querySelectorAll('.hs-a2p-f')].find((f) => f.querySelector('.hs-a2p-label').textContent.startsWith(label));
    const fire = (node, type) => node.dispatchEvent(new Event(type, { bubbles: true }));
    const wait = (ms) => new Promise((r) => setTimeout(r, ms));
    const isBad = (f) => f.classList.contains('hs-a2p-bad');
    const isOk = (f) => f.classList.contains('hs-a2p-ok');`;

  console.log('\nPanel loads');
  const load = JSON.parse(await run(`return JSON.stringify({
    editor: !!document.querySelector('.editor-editor-interface,.edit-post-layout'), panel: !!document.querySelector('#hs-a2p .hs-a2p-row'),
    rows: document.querySelectorAll('#hs-a2p .hs-a2p-row').length, bodies: document.querySelectorAll('#hs-a2p .hs-a2p-body').length,
    colours: document.querySelectorAll('#hs-a2p .hs-a2p-bad,#hs-a2p .hs-a2p-ok').length, intro: (document.querySelector('.hs-a2p-intro')||{}).textContent,
    media: !!(window.wp && wp.media), create: (document.querySelector('#hs-a2p .button-primary')||{}).disabled })`));
  t('editor and panel load', load.editor && load.panel, JSON.stringify(load));
  t('lists the articles', load.rows > 5, String(load.rows));
  t('unticked rows show no fields or colours', load.bodies === 0 && load.colours === 0);
  t('media library available', load.media);
  t('Create Posts disabled until something is complete', load.create === true);
  console.log(`        ${load.rows} rows. ${load.intro}`);
  await shot('panel-1-unticked.png');

  console.log('\nTicking a row');
  const ticked = JSON.parse(await run(`${helpers}
    const row = rows().find((r) => /secondary/i.test(r.querySelector('.hs-a2p-sec').textContent) && r.querySelector('.hs-a2p-sec').textContent) || rows()[0];
    row.dataset.t = '1';
    row.querySelector('.hs-a2p-head input[type=checkbox]').click();
    await wait(100);
    const r = document.querySelector('#hs-a2p .hs-a2p-body').closest('.hs-a2p-row');
    return JSON.stringify({
      title: isOk(fieldOf(r,'Title')), section: fieldOf(r,'Website').querySelector('select').selectedOptions[0].textContent, sectionOk: isOk(fieldOf(r,'Website')), campusOk: isOk(fieldOf(r,'Campus')),
      tags: isBad(fieldOf(r,'Thematic')), badge: isBad(fieldOf(r,'Yellow')), image: isBad(fieldOf(r,'Featured')),
      locColour: isBad(fieldOf(r,'Display')) || isOk(fieldOf(r,'Display')), portraitColour: isBad(fieldOf(r,'Portrait')) || isOk(fieldOf(r,'Portrait')),
      live: fieldOf(r,'Status').querySelector('input').checked, disabled: document.querySelector('#hs-a2p .button-primary').disabled,
      summary: document.querySelector('.hs-a2p-actions .hs-a2p-saved').textContent, thumbs: r.querySelectorAll('.hs-a2p-choices img').length })`));
  t('pre-filled fields are green', ticked.title && ticked.campusOk);
  t('tags, badge and featured image are red', ticked.tags && ticked.badge && ticked.image);
  t('location override and portrait have no colour', !ticked.locColour && !ticked.portraitColour);
  t('defaults to Live', ticked.live);
  t('Create stays disabled while required fields are missing', ticked.disabled === true);
  t('article images offered as choices', ticked.thumbs > 0, String(ticked.thumbs));
  console.log(`        Section pre-filled: ${ticked.section}. ${ticked.summary}`);
  await shot('panel-2-ticked.png');

  console.log('\nFilling it in');
  const filled = JSON.parse(await run(`${helpers}
    const r = document.querySelector('#hs-a2p .hs-a2p-body').closest('.hs-a2p-row');
    const sec = fieldOf(r,'Website').querySelector('select'); if (sec.value === '0') { sec.value = sec.options[1].value; fire(sec,'change'); }
    const tagInput = fieldOf(r,'Thematic').querySelector('input');
    tagInput.value = document.querySelector('#hs-a2p-tags-list option').value;
    tagInput.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    const badge = fieldOf(r,'Yellow').querySelector('select'); badge.value = badge.options[1].value; fire(badge,'change');
    r.querySelector('.hs-a2p-choices button').click();
    await wait(1500);
    return JSON.stringify({ tags: isOk(fieldOf(r,'Thematic')), chips: r.querySelectorAll('.hs-a2p-chip').length, badge: isOk(fieldOf(r,'Yellow')), image: isOk(fieldOf(r,'Featured')), thumb: !!fieldOf(r,'Featured').querySelector('.hs-a2p-thumb'),
      disabled: document.querySelector('#hs-a2p .button-primary').disabled, saved: document.querySelectorAll('.hs-a2p-actions .hs-a2p-saved')[1].textContent,
      dirty: wp.data.select('core/editor').isEditedPostDirty() })`));
  t('filled fields turn green', filled.tags && filled.badge && filled.image && filled.thumb, JSON.stringify(filled));
  t('Create is enabled', filled.disabled === false);
  t('panel saves itself', filled.saved === 'Saved', filled.saved);
  t('weekly update shows no unsaved changes', filled.dirty === false);

  console.log('\nReload keeps the row');
  await send('Page.reload');
  await sleep(18000);
  const kept = JSON.parse(await run(`${helpers}
    const r = document.querySelector('#hs-a2p .hs-a2p-body'); if (!r) return JSON.stringify({ body: false });
    const row = r.closest('.hs-a2p-row');
    return JSON.stringify({ body: true, chips: row.querySelectorAll('.hs-a2p-chip').length, image: !!fieldOf(row,'Featured').querySelector('.hs-a2p-thumb'), badge: fieldOf(row,'Yellow').querySelector('select').value !== '0', dirty: wp.data.select('core/editor').isEditedPostDirty() })`));
  t('tick and fields still there, no unsaved changes', kept.body && kept.chips === 1 && kept.image && kept.badge && kept.dirty === false, JSON.stringify(kept));

  console.log('\nA second row as a Draft, then Create Posts');
  await run(`${helpers}
    const free = rows().filter((r) => !r.querySelector('.hs-a2p-body') && r.querySelector('.hs-a2p-head input[type=checkbox]'));
    free[free.length - 1].querySelector('.hs-a2p-head input[type=checkbox]').click();
    await wait(100);
    const open = [...document.querySelectorAll('#hs-a2p .hs-a2p-body')].map((b) => b.closest('.hs-a2p-row'));
    const r = open.find((x) => !x.querySelector('.hs-a2p-chip'));
    const sec = fieldOf(r,'Website').querySelector('select'); if (sec.value === '0') { sec.value = sec.options[1].value; fire(sec,'change'); }
    const tagInput = fieldOf(r,'Thematic').querySelector('input'); tagInput.value = document.querySelector('#hs-a2p-tags-list option').value;
    tagInput.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    const badge = fieldOf(r,'Yellow').querySelector('select'); badge.value = badge.options[1].value; fire(badge,'change');
    fieldOf(r,'Status').querySelectorAll('input')[1].click();
    await wait(200);
    document.querySelector('#hs-a2p .button-primary').click();`);
  await sleep(9000);
  const created = JSON.parse(await run(`
    const m = document.querySelector('#hs-a2p .hs-a2p-message');
    const states = [...document.querySelectorAll('#hs-a2p .hs-a2p-state')].map((s) => s.textContent);
    const view = [...document.querySelectorAll('#hs-a2p .hs-a2p-head a')].find((a) => a.textContent === 'View');
    return JSON.stringify({ message: m ? m.textContent : '', error: m ? m.classList.contains('is-error') : null, states, view: view ? view.href : '', dirty: wp.data.select('core/editor').isEditedPostDirty() })`));
  t('confirmation gave the counts', dialogs.some((d) => /1 Post\(s\) will go live now and 1 will be draft/.test(d)), dialogs.join(' / '));
  t('both created, shown as Live and Draft', created.states.includes('Live') && created.states.includes('Draft') && created.error === false, JSON.stringify(created));
  t('editor still clean after creating', created.dirty === false);
  console.log(`        ${created.message}`);
  await shot('panel-3-created.png');

  const live = await fetch(created.view, { redirect: 'follow' });
  t('Live Post is public (logged out)', live.status === 200, `${live.status} ${created.view}`);

  console.log('\nSecurity');
  const bad = JSON.parse(await run(`
    const go = async (extra) => { const b = new FormData(); b.append('action','hs_a2p_save'); b.append('issue','${ISSUE}'); b.append('rows','{}'); Object.keys(extra).forEach((k) => b.append(k, extra[k])); const r = await fetch(ajaxurl, { method:'POST', credentials:'same-origin', body:b }); return r.status; };
    return JSON.stringify({ bad: await go({ nonce: 'wrong' }), none: await go({}) })`));
  t('bad or missing nonce → 403', bad.bad === 403 && bad.none === 403, JSON.stringify(bad));
  const out = await fetch('http://headstart.local/wp-admin/admin-ajax.php', { method: 'POST', body: new URLSearchParams({ action: 'hs_a2p_create', issue: String(ISSUE) }) });
  t(`logged out request refused (${out.status})`, out.status === 400 || out.status === 403);

  t('no JavaScript errors on the page', errors.size === 0, [...errors].join(' | '));
} catch (e) {
  t('check finished', false, e.message);
} finally {
  if (ws) ws.close();
  browser.kill();
  const left = evalFile(`foreach (get_posts(['post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_hs_a2p_source', 'meta_compare' => 'LIKE', 'meta_value' => '${ISSUE}:']) as $id) { wp_delete_post($id, true); echo "deleted $id "; }
delete_post_meta(${ISSUE}, '_hs_a2p_rows');
$d = json_decode(file_get_contents(getenv('HS_SESSION')), true);
WP_Session_Tokens::get_instance($d['uid'])->destroy($d['tok']);`);
  t('weekly update content, modified date and status unchanged', issueState() === stateBefore);
  console.log(`  cleanup: ${left.trim()}; temporary login session removed`);
  await sleep(300);
  rmSync(tmp, { recursive: true, force: true });
}
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
