// Checks the weekly update "In this issue" box and the Weekly Updates list in a logged-in headless Chrome on headstart.local.
// Run from the repo root: node work/weekly-issue-contents/tests/box-check.mjs
// Creates one throwaway draft weekly update and force-deletes it at the end.
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
const port = 9337;

const tmp = mkdtempSync(join(tmpdir(), 'hs-issue-check-'));
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


const g = (cls, inner) => `<!-- wp:divi/group ${JSON.stringify({ module: { decoration: { attributes: { desktop: { value: { attributes: [{ name: 'class', value: cls }] } } } } } })} -->\n${inner}\n<!-- /wp:divi/group -->\n`;
const tx = (html) => `<!-- wp:divi/text ${JSON.stringify({ content: { innerContent: { desktop: { value: html } } } })} /-->\n`;
const sec = (inner) => `<!-- wp:divi/section -->\n<!-- wp:divi/row -->\n<!-- wp:divi/column -->\n${inner}<!-- /wp:divi/column -->\n<!-- /wp:divi/row -->\n<!-- /wp:divi/section -->\n`;
const issue = (heads) => sec(heads.map((h) => g('hs-weekly-article', tx(`<h3>${h}</h3><p>Body.</p>`))).join('') + g('hs-weekly-promo', tx('<h3>Promo: Open Day</h3>')));
const contentFile = join(tmp, 'content.html');
writeFileSync(contentFile, issue(['A very long heading about the Year 6 Science Fair and its winners', 'Reception Detectives', 'Sports Day']));
const ISSUE = +evalFile(`$id = wp_insert_post(wp_slash(['post_type' => 'weekly_update', 'post_status' => 'draft', 'post_title' => 'Issue contents browser check', 'post_content' => file_get_contents('${contentFile}')]));
update_post_meta($id, 'hs_week_number', 99);
$u = get_users(['role' => 'administrator', 'number' => 1])[0];
$exp = time() + 1800;
$tok = WP_Session_Tokens::get_instance($u->ID)->create($exp);
file_put_contents(getenv('HS_SESSION'), json_encode(['uid' => $u->ID, 'tok' => $tok, 'cookies' => [
  [LOGGED_IN_COOKIE, wp_generate_auth_cookie($u->ID, $exp, 'logged_in', $tok)],
  [AUTH_COOKIE, wp_generate_auth_cookie($u->ID, $exp, 'auth', $tok)],
]]));
echo $id;`).trim().split('\n').pop();
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
  const send = (method, params = {}) => new Promise((r) => { const i = ++id; waiting[i] = r; ws.send(JSON.stringify({ id: i, method, params })); });
  ws.onmessage = (e) => {
    const d = JSON.parse(e.data);
    if (d.id && waiting[d.id]) { waiting[d.id](d.result || d.error); return; }
    if (d.method === 'Runtime.exceptionThrown') { const x = d.params.exceptionDetails; errors.add((x.exception?.description || x.text).split('\n')[0].slice(0, 200)); }
    if (d.method === 'Page.javascriptDialogOpening') { send('Page.handleJavaScriptDialog', { accept: true }); }
  };
  await new Promise((r) => { ws.onopen = r; });
  for (const m of ['Network.enable', 'Runtime.enable', 'Page.enable']) await send(m);
  for (const [name, value] of session.cookies) for (const path of ['/', '/wp-admin']) await send('Network.setCookie', { name, value, domain: 'headstart.local', path });
  const run = async (expr) => {
    const r = await send('Runtime.evaluate', { expression: `(async function(){ ${expr} })()`, awaitPromise: true, returnByValue: true });
    if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
    return r.result.value;
  };
  const go = async (url, ms) => { await send('Page.navigate', { url }); await sleep(ms); };
  const wait = 'const wait = (ms) => new Promise((r) => setTimeout(r, ms));';

  console.log('\nWeekly Updates list');
  await go('http://headstart.local/wp-admin/edit.php?post_type=weekly_update&posts_per_page=40', 6000);
  const list = JSON.parse(await run(`const row = (id) => document.getElementById('post-' + id);
    const nine = row(991290), mine = row(${ISSUE});
    return JSON.stringify({ blurb: nine ? nine.querySelector('.hs-blurb-display').textContent.trim().slice(0, 40) : 'row missing',
      names: mine ? [...mine.querySelectorAll('.hs-issue-names .hs-tags-display span')].map((s) => s.textContent) : [],
      editLink: mine ? (mine.querySelector('.hs-issue-names a') || {}).href || '' : '' });`));
  t('Weekly Update #9 shows its blurb', /^Another amazing/.test(list.blurb), list.blurb);
  t('an issue with articles lists them (no promo) and Edit opens its box', JSON.stringify(list.names) === JSON.stringify(['A very long heading about the Year 6 Science Fair and its winners', 'Reception Detectives', 'Sports Day']) && /#hs-issue-contents-list$/.test(list.editLink), JSON.stringify(list));

  console.log('\nThe In this issue box');
  await go(`http://headstart.local/wp-admin/post.php?post=${ISSUE}&action=edit`, 16000);
  const box = JSON.parse(await run(`const b = document.getElementById('hs-issue-contents-list');
    return JSON.stringify({ box: !!b, rows: b ? [...b.querySelectorAll('.hs-ic-heading')].map((x) => x.textContent) : [], tagsField: !!document.querySelector('.acf-field[data-name="in_this_issue_tax"]'), inGroup: !!document.querySelector('#acf-group_6a3772490951b .hs-wu-contents #hs-issue-contents-list'),
      placeholder: b ? b.querySelector('.hs-ic-input').placeholder : '' });`));
  t('the box lists every article heading in order, no promo', box.box && JSON.stringify(box.rows) === JSON.stringify(['A very long heading about the Year 6 Science Fair and its winners', 'Reception Detectives', 'Sports Day']), JSON.stringify(box));
  t('the old tags field is hidden; the list sits in Weekly Update Details', !box.tagsField && box.inGroup, JSON.stringify(box));
  const lay = JSON.parse(await run(`const q = (c) => document.querySelector('#acf-group_6a3772490951b .acf-field.' + c).getBoundingClientRect();
    const b = q('hs-wu-blurb'), c = q('hs-wu-campus'), w = q('hs-wu-week'), i = q('hs-wu-contents'), a = q('hs-wu-archive');
    const list = document.getElementById('hs-issue-contents-list');
    return JSON.stringify({ cols: b.right <= c.left + 1 && c.right <= i.left + 1, campusOverWeek: Math.abs(c.left - w.left) < 2 && w.top >= c.bottom - 1, archiveBelow: a.top >= Math.max(b.bottom, i.bottom) - 1 && a.width > b.width + i.width,
      tops: Math.abs(b.top - c.top) < 2 && Math.abs(b.top - i.top) < 2, heights: [Math.round(b.height), Math.round(i.height)], widths: [Math.round(b.width), Math.round(c.width), Math.round(i.width)], scrolls: list.scrollHeight > list.clientHeight && getComputedStyle(list).overflowY === 'auto' });`));
  t('Blurb | Campus over Week number | In this issue, tops aligned, Archive Post across the bottom', lay.cols && lay.campusOverWeek && lay.archiveBelow && lay.tops, JSON.stringify(lay));
  if (shots) {
    for (const [w, name] of [[1440, 'details-1440.png'], [1100, 'details-1100.png']]) {
      await send('Emulation.setDeviceMetricsOverride', { width: w, height: 1800, deviceScaleFactor: 1, mobile: false });
      await sleep(800);
      const r = JSON.parse(await run(`document.querySelectorAll('.editor-visual-editor').forEach((n) => { n.style.display = 'none'; });
        document.querySelectorAll('.edit-post-meta-boxes-main, .edit-post-layout__metaboxes').forEach((n) => { n.style.height = '100%'; n.style.maxHeight = 'none'; n.style.flex = '1 1 100%'; });
        const g = document.getElementById('acf-group_6a3772490951b'); g.scrollIntoView({ block: 'start' });
        await new Promise((r) => setTimeout(r, 600)); const b = g.getBoundingClientRect();
        return JSON.stringify({ x: b.left, y: b.top, w: b.width, h: Math.min(b.height, 1700) });`));
      const pic = await send('Page.captureScreenshot', { format: 'png', clip: { x: r.x, y: r.y, width: r.w, height: r.h, scale: 1 } });
      writeFileSync(join(shots, name), Buffer.from(pic.data, 'base64'));
    }
    await send('Emulation.clearDeviceMetricsOverride');
  }
  t('the blurb and the list are about the same height', Math.abs(lay.heights[0] - lay.heights[1]) <= 40, JSON.stringify(lay.heights));

  const saved = JSON.parse(await run(`${wait}
    const input = document.querySelector('#hs-issue-contents-list .hs-ic-input');
    input.focus(); input.value = 'Science Fair'; input.dispatchEvent(new Event('input', { bubbles: true })); input.dispatchEvent(new Event('change', { bubbles: true }));
    const ed = wp.data.select('core/editor');
    const extra = ${JSON.stringify(g('hs-weekly-article', tx('<h3>Added After Load</h3><p>New.</p>')))};
    wp.data.dispatch('core/editor').editPost({ content: ed.getEditedPostContent().replace(/(<!-- \\/wp:divi\\/column -->)/, extra + '$1') });
    await wp.data.dispatch('core/editor').savePost();
    for (let i = 0; i < 60 && (ed.isSavingPost() || wp.data.select('core/edit-post').isSavingMetaBoxes()); i++) { await wait(500); }
    await wait(4000);
    return JSON.stringify({ rows: [...document.querySelectorAll('#hs-issue-contents-list .hs-ic-heading')].map((x) => x.textContent), first: document.querySelector('#hs-issue-contents-list .hs-ic-input').value });`));
  t('after saving, a newly added article appears in the box without a reload', saved.rows.length === 4 && saved.rows[3] === 'Added After Load', JSON.stringify(saved));
  t('the short name typed before saving is kept', saved.first === 'Science Fair', JSON.stringify(saved));

  await go(`http://headstart.local/wp-admin/post.php?post=${ISSUE}&action=edit`, 16000);
  const reloaded = JSON.parse(await run(`return JSON.stringify({ first: (document.querySelector('#hs-issue-contents-list .hs-ic-input') || {}).value, n: document.querySelectorAll('#hs-issue-contents-list .hs-ic-row').length });`));
  t('after a reload: the short name and all four articles are there', reloaded.first === 'Science Fair' && reloaded.n === 4, JSON.stringify(reloaded));
  const stored = evalFile(`echo wp_json_encode(hs_issue_contents_names(${ISSUE}));`).trim().split('\n').pop();
  t('saved for the Campus Hub: short name, then headings', stored === JSON.stringify(['Science Fair', 'Reception Detectives', 'Sports Day', 'Added After Load']), stored);
  t('no JavaScript errors', errors.size === 0, [...errors].join(' | '));
} catch (e) {
  t('check finished', false, String(e));
} finally {
  try { ws && ws.close(); } catch {}
  browser.kill();
  evalFile(`wp_delete_post(${ISSUE}, true); echo 'cleaned';`);
  rmSync(tmp, { recursive: true, force: true });
  console.log(`\n${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
}
