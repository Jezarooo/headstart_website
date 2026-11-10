// Drives the Post edit screen in a logged-in headless Chrome on headstart.local:
// grouped Post Classification layout, the shared labelled preview, the gatekeeper borders, and save/reload.
// Run from the repo root: node work/post-screen-layout-preview/tests/post-screen-check.mjs [screenshot-dir]
// A temporary draft Post and badge term are created, then force-deleted.
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
const port = 9343;
const tmp = mkdtempSync(join(tmpdir(), 'hs-post-screen-'));
const sessionFile = join(tmp, 'session.json');
const evalFile = (code) => {
  const f = join(tmp, 'x.php');
  writeFileSync(f, `<?php\n${code}`);
  return execFileSync(php, ['-d', `mysqli.default_socket=${sock}`, '-d', 'error_reporting=E_ERROR', '-d', 'display_errors=0', 'wp-cli.phar', 'eval-file', f],
    { cwd: wpRoot, env: { ...process.env, HS_SESSION: sessionFile }, encoding: 'utf8' });
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let pass = 0; let fail = 0;
// The same image at any size: drop WordPress's -WIDTHxHEIGHT and -scaled suffixes.
const sameImage = (a, b) => { const base = (u) => String(u || '').split('/').pop().replace(/-(\d+x\d+|scaled)(?=\.[a-z]+$)/i, ''); return !!a && base(a) === base(b); };
const t = (name, ok, detail = '') => { if (ok) { pass++; console.log(`  ok    ${name}`); } else { fail++; console.log(`  FAIL  ${name}${detail ? ` — ${detail}` : ''}`); } };

// A temporary draft Post: Section Primary, Chaofah, one tag, a featured image and a portrait.
const setup = JSON.parse(evalFile(`
$u = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($u->ID);
$imgs = get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 4, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC']);
// Content with two Media Library photos (plain HTML, as in older Posts) and one that isn't in the Media Library.
$body = '<p>Body</p><img class="wp-image-' . $imgs[2] . '" src="' . wp_get_attachment_url($imgs[2]) . '" alt=""/><img class="aligncenter wp-image-' . $imgs[3] . ' size-large" src="' . wp_get_attachment_url($imgs[3]) . '" alt=""/><img src="' . home_url('/wp-content/uploads/legacy-images/x/old.jpg') . '" alt=""/>';
$id = wp_insert_post(['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'PS Check Post', 'post_content' => '<!-- wp:html -->' . $body . '<!-- /wp:html -->']);
wp_set_object_terms($id, ['primary'], 'hs_department');
wp_set_object_terms($id, ['chaofah'], 'hs_post_campus');
$tag = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false, 'number' => 1, 'name__like' => 'ACADEMIC'])[0];
wp_set_object_terms($id, [$tag->term_id], 'post_tag');
set_post_thumbnail($id, $imgs[0]);
update_post_meta($id, 'hs_portrait_image', $imgs[1]); update_post_meta($id, '_hs_portrait_image', 'field_hs_portrait_image');
update_post_meta($id, '_et_pb_use_builder', 'off');
$badge = wp_insert_term('PS Check Badge', 'hs_thematic_chip');
$exp = time() + 1800; $tok = WP_Session_Tokens::get_instance($u->ID)->create($exp);
file_put_contents(getenv('HS_SESSION'), json_encode(['uid' => $u->ID, 'tok' => $tok, 'cookies' => [[LOGGED_IN_COOKIE, wp_generate_auth_cookie($u->ID, $exp, 'logged_in', $tok)], [AUTH_COOKIE, wp_generate_auth_cookie($u->ID, $exp, 'auth', $tok)]]]));
echo wp_json_encode(['id' => $id, 'badge' => is_wp_error($badge) ? 0 : $badge['term_id'], 'tag' => $tag->name,
  'featured' => wp_get_attachment_image_url($imgs[0], 'medium'), 'featuredId' => $imgs[0], 'portrait' => wp_get_attachment_url($imgs[1]),
  'rackIds' => [$imgs[2], $imgs[3]], 'rackUrls' => [wp_get_attachment_url($imgs[2]), wp_get_attachment_url($imgs[3])]]);
`).trim());
const session = JSON.parse(readFileSync(sessionFile, 'utf8'));
const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--window-size=1920,1200', `--user-data-dir=${join(tmp, 'chrome')}`, `--remote-debugging-port=${port}`, 'about:blank'], { stdio: 'ignore' });

let ws;
try {
  for (let i = 0; i < 20; i++) { try { await fetch(`http://127.0.0.1:${port}/json/version`); break; } catch { await sleep(500); } }
  const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json();
  ws = new WebSocket(target.webSocketDebuggerUrl);
  let id = 0; const waiting = {}; const errors = new Set();
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
  const setWidth = (w) => send('Emulation.setDeviceMetricsOverride', { width: w, height: 1200, deviceScaleFactor: 1, mobile: false });
  await setWidth(1920);
  const open = async () => { await send('Page.navigate', { url: `http://headstart.local/wp-admin/post.php?post=${setup.id}&action=edit` }); await sleep(16000); };
  await open();
  const run = async (expr) => {
    const r = await send('Runtime.evaluate', { expression: `(async function(){ ${expr} })()`, awaitPromise: true, returnByValue: true });
    if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
    return r.result.value;
  };
  const shot = async (name) => {
    if (!shots) return;
    await run(`document.querySelectorAll('.editor-visual-editor').forEach((n) => { n.style.display = 'none'; }); document.getElementById('acf-group_hs_post_classification').scrollIntoView({ block: 'start' });`);
    await sleep(800);
    const p = await send('Page.captureScreenshot', { format: 'png' });
    writeFileSync(join(shots, name), Buffer.from(p.data, 'base64'));
  };
  const helpers = `
    const box = document.getElementById('acf-group_hs_post_classification');
    const f = (n) => box.querySelector('.acf-field[data-name="' + n + '"]');
    const top = (n) => Math.round(n.getBoundingClientRect().top);
    const pv = box.querySelector('.hs-cpv');
    const q = (sel) => ((pv && pv.querySelector(sel)) || {}).textContent || '';
    const card = () => ({ badge: q('.hs-badge-primary p'), place: q('.hs-badge-location p'), date: q('.hs-story-date p'), title: q('.hs-story-title p'),
      tags: [...pv.querySelectorAll('.hs-story-tags span')].map((x) => x.textContent), img: (pv.querySelector('.hs-story-image img') || {}).src || '',
      sec: q('.hs-cpv-sec p'), chipDate: (pv.querySelectorAll('.hs-cpv-chip p')[0] || {}).textContent, chipPlace: (pv.querySelectorAll('.hs-cpv-chip p')[1] || {}).textContent });
    const wait = (ms) => new Promise((r) => setTimeout(r, ms));
    const tick = (name, label, on) => { const i = [...f(name).querySelectorAll('input[type=checkbox]')].find((x) => x.closest('label').textContent.trim() === label); if (i && i.checked !== on) { i.click(); } return !!i; };`;

  console.log('\nLayout at 1920');
  const lay = JSON.parse(await run(`${helpers}
    const r = (n) => f(n).getBoundingClientRect();
    const heads = [...box.querySelectorAll('.hs-pc-head')].map((h) => h.textContent);
    const fieldsRight = Math.max(...['hs_campus_tax', 'hs_location_override', 'hs_event_end', 'post_tag_tax'].map((n) => r(n).right));
    return JSON.stringify({ grid: box.querySelector('.inside').classList.contains('hs-pc-grid'), heads,
      pair1: top(f('hs_department_tax')) === top(f('hs_campus_tax')), pair2: top(f('hs_thematic_chip_tax')) === top(f('hs_location_override')), pair3: top(f('hs_event_start')) === top(f('hs_event_end')),
      sideBySide: r('hs_campus_tax').left > r('hs_department_tax').right - 2, tagsWide: r('post_tag_tax').width > r('hs_department_tax').width * 1.8,
      order: ['hs_department_tax', 'hs_thematic_chip_tax', 'hs_event_start', 'post_tag_tax'].map((n) => top(f(n))).concat([top(box.querySelector('.hs-pc-images'))]),
      preview: !!pv && pv.offsetParent !== null, previewRight: pv ? pv.getBoundingClientRect().left >= fieldsRight - 2 : false,
      toggle: (box.querySelector('.hs-pc-toggle') || {}).offsetParent !== null, sticky: pv ? getComputedStyle(box.querySelector('.hs-pc-preview')).position : '',
      labels: [...box.querySelectorAll('.hs-cpv-label strong')].map((x) => x.textContent), lines: box.querySelectorAll('.hs-cpv-lines polyline').length,
      hints: { section: (f('hs_department_tax').querySelector('.description') || {}).textContent, badge: (f('hs_thematic_chip_tax').querySelector('.description') || {}).textContent, campus: (f('hs_campus_tax').querySelector('.description') || {}).textContent, location: (f('hs_location_override').querySelector('.description') || {}).textContent } })`));
  t('fields are on the grid with the five group headings', lay.grid && JSON.stringify(lay.heads) === JSON.stringify(['Website Section', 'Campus', 'Event date (shown to readers)', 'Tags', 'Images']), JSON.stringify(lay.heads));
  t('Website Section beside Campus, badge override beside location override, event start beside end (same line)', lay.pair1 && lay.pair2 && lay.pair3 && lay.sideBySide, JSON.stringify(lay));
  t('order down the page: Section, badge, event dates, tags (full width), images', lay.order.every((v, i, a) => i === 0 || v > a[i - 1]) && lay.tagsWide, JSON.stringify(lay.order));
  t('preview to the right of the fields, no toggle, not sticky', lay.preview && lay.previewRight && !lay.toggle && lay.sticky !== 'sticky', JSON.stringify({ p: lay.preview, r: lay.previewRight, t: lay.toggle, s: lay.sticky }));
  t('nine labels with lines', lay.labels.length === 9 && lay.lines === 9, JSON.stringify(lay.labels));
  t('hints: Section → feeds (not the URL); Campus and Location override → URL', /its feeds\)/.test(lay.hints.section || '') && !/URL/.test(lay.hints.section || '') && /Leave empty to show the Website Section/.test(lay.hints.badge || '') && /Sets the campus in the URL/.test(lay.hints.campus || '') && /chip and the URL/.test(lay.hints.location || ''), JSON.stringify(lay.hints));
  await shot('post-1-layout-1920.png');

  console.log('\nThe preview follows the saved Post');
  const start = JSON.parse(await run(`${helpers} await wait(300); return JSON.stringify(card());`));
  t('badge and section heading show the Website Section', start.badge === 'Primary' && start.sec === 'Primary', JSON.stringify(start));
  t('campus line and chip show the campus', /Chaofah/.test(start.place) && /Chaofah/.test(start.chipPlace), JSON.stringify(start));
  t('title from the editor', start.title === 'PS Check Post', start.title);
  t('photo is the Portrait', sameImage(start.img, setup.portrait), `${start.img} vs ${setup.portrait}`);
  t('tag chip without its prefix', start.tags.length === 1 && start.tags[0] === setup.tag.split(':').pop().trim(), JSON.stringify(start.tags));
  t('card date and chip are the publish date (today for a new draft)', /^\d{1,2}(st|nd|rd|th) [A-Z][a-z]+ \d{4}$/.test(start.date) && /^\d{1,2} [A-Z][a-z]+ \d{4}$/.test(start.chipDate), JSON.stringify({ d: start.date, c: start.chipDate }));

  console.log('\nHero preview');
  const hf = JSON.parse(await run(`${helpers}
    const chf = document.getElementById('acf-group_hs_post_classification').querySelector('.hs-chf');
    const input = f('hs_hero_focus').querySelector('input[type=text]');
    const hiddenFields = ['hs_hero_focus', 'hs_hero_mobile', 'hs_hero_mobile_focus'].every((n) => f(n).style.display === 'none');
    const startFocus = input.value;
    const tab = (k) => chf.querySelector('.hs-chf-devices button[data-device=' + k + ']');
    const heroEl = () => chf.querySelector('.hs-chf-hero');
    const shape = () => { const b = heroEl().getBoundingClientRect(); return +(b.width / b.height).toFixed(2); };
    const where = () => (chf.querySelector('.hs-chf-where') || {}).textContent || '';
    const key = async (k, shift) => { heroEl().dispatchEvent(new KeyboardEvent('keydown', { key: k, bubbles: true, shiftKey: !!shift })); await wait(60); };
    const centre = async () => { [...chf.querySelectorAll('.hs-chf-actions button')].find((b) => b.textContent === 'Centre').click(); await wait(120); };
    await wait(700);
    const out = { tabs: [...chf.querySelectorAll('.hs-chf-devices button')].map((b) => b.textContent), on: (chf.querySelector('.hs-chf-devices .is-on') || {}).dataset.device, desktop: shape(), fold: !!chf.querySelector('.hs-chf-fold'), mobileHiddenOnDesktop: chf.querySelector('.hs-chf-mobile').style.display === 'none' };
    tab('laptop').click(); await wait(300); out.laptop = shape();
    await centre();
    const h = heroEl(), hr = h.getBoundingClientRect();
    const ev = (t, x, y) => h.dispatchEvent(new PointerEvent(t, { bubbles: true, clientX: x, clientY: y, pointerId: 1 }));
    ev('pointerdown', hr.left + hr.width / 2, hr.top + hr.height / 2); ev('pointermove', hr.left + hr.width / 2, hr.top + hr.height / 2 - 20); ev('pointerup', hr.left + hr.width / 2, hr.top + hr.height / 2 - 20); await wait(100);
    out.dragged = where();
    await centre();
    for (let i = 0; i < 3; i++) { await key('ArrowDown'); }
    await key('ArrowRight');
    out.keys = where();
    tab('tablet').click(); await wait(300); out.tablet = shape();
    tab('phone').click(); await wait(400); out.phone = shape();
    const scr = chf.querySelector('.hs-chf-screen').getBoundingClientRect(), hb = heroEl().getBoundingClientRect();
    out.spaceBelow = Math.round(scr.bottom - hb.bottom); out.foldOnPhone = !!chf.querySelector('.hs-chf-fold');
    await key('ArrowRight'); await key('ArrowRight');
    out.phoneKeys = where();
    const mb = chf.querySelector('.hs-chf-mobile'); out.mobileShown = mb.style.display !== 'none';
    const slot = mb.querySelector('.hs-cip-slot[data-slot=portrait]'); out.slotPad = slot ? parseFloat(getComputedStyle(slot).paddingLeft) : 0;
    const rack = mb.querySelector('.hs-cip-rack');
    if (rack) { rack.scrollTop = 40; out.rackSet = Math.round(rack.scrollTop); await key('ArrowLeft'); await key('ArrowRight'); out.rackKept = rack.isConnected && Math.round(mb.querySelector('.hs-cip-rack').scrollTop); }
    const items = [...mb.querySelectorAll('.hs-cip-rack-item')];
    out.rackN = items.length;
    if (items.length) { items[items.length - 1].click(); await wait(800); out.mobileId = +items[items.length - 1].dataset.id; }
    out.phoneSrc = (heroEl().querySelector('img') || {}).src || '';
    out.mobileStart = where();
    await key('ArrowLeft', true); await key('ArrowUp', true);
    out.mobileMoved = where();
    tab('desktop').click(); await wait(800);
    out.backOnDesktop = where(); out.hidden = hiddenFields; out.start = startFocus; out.focus = input.value; out.mobileInput = +f('hs_hero_mobile').querySelector('.acf-image-uploader input[type=hidden]').value; out.mobileFocus = f('hs_hero_mobile_focus').querySelector('input[type=text]').value;
    return JSON.stringify(out);`));
  t('the hero fields are hidden behind the preview; it starts centred', hf.hidden && hf.start === '', JSON.stringify(hf));
  t('Desktop, Laptop, Tablet, Phone at the measured shapes', JSON.stringify(hf.tabs) === JSON.stringify(['Desktop', 'Laptop', 'Tablet', 'Phone']) && Math.abs(hf.desktop - 1632 / 756) < 0.05 && Math.abs(hf.laptop - 1380 / 581) < 0.05 && Math.abs(hf.tablet - 779 / 826) < 0.05 && Math.abs(hf.phone - 371 / 591) < 0.05, JSON.stringify(hf));
  t('one focal point: up/down from Desktop/Laptop, left/right from Phone', hf.focus === '52 53' && hf.keys === '53% down' && hf.phoneKeys === '52% across', JSON.stringify({ f: hf.focus, k: hf.keys, p: hf.phoneKeys }));
  t('Phone: no empty screen below the hero; picker padded; rack keeps its scroll', hf.spaceBelow <= 12 && hf.slotPad >= 8 && (hf.rackSet > 0 && hf.rackKept === hf.rackSet || hf.rackN <= 4), JSON.stringify({ s: hf.spaceBelow, pad: hf.slotPad, set: hf.rackSet, kept: hf.rackKept, n: hf.rackN }));
  t('picking a phone image sets the ACF field and gives it its own position', hf.mobileInput === hf.mobileId && hf.mobileFocus !== '' && hf.mobileStart === 'Centred (default)', JSON.stringify({ id: hf.mobileInput, want: hf.mobileId, mf: hf.mobileFocus }));
  if (shots) { await run(`document.querySelector('.hs-chf .hs-chf-devices button[data-device=phone]').click(); document.querySelector('.hs-chf').scrollIntoView({ block: 'start' });`); await sleep(1000); const p = await send('Page.captureScreenshot', { format: 'png' }); writeFileSync(join(shots, 'post-4-hero-phone.png'), Buffer.from(p.data, 'base64')); await run(`document.querySelector('.hs-chf .hs-chf-devices button[data-device=desktop]').click();`); await sleep(400); }

  console.log('\nEditing fields updates the preview');
  const edited = JSON.parse(await run(`${helpers}
    const out = {};
    tick('hs_department_tax', 'Primary', false); tick('hs_department_tax', 'Secondary', true); await wait(500);
    out.section = card().badge;
    // The badge dropdown loads its options as you type; picking one adds it to the select, as here.
    const sel = f('hs_thematic_chip_tax').querySelector('select'); sel.appendChild(new Option('PS Check Badge', '${setup.badge}', true, true)); jQuery(sel).trigger('change'); await wait(500);
    out.badge = card(); 
    jQuery(f('hs_thematic_chip_tax').querySelector('select')).val('').trigger('change'); await wait(500);
    out.badgeCleared = card().badge;
    tick('hs_campus_tax', 'Cherngtalay Campus', true); await wait(500);
    out.both = card().place;
    const loc = f('hs_location_override').querySelector('input[type=text]'); loc.value = 'Bangkok Trip'; loc.dispatchEvent(new Event('input', { bubbles: true })); await wait(500);
    out.loc = card();
    loc.value = ''; loc.dispatchEvent(new Event('input', { bubbles: true }));
    const setDate = (name, ymd, shown) => { const h = f(name).querySelector('input[type=hidden]'); h.value = ymd; f(name).querySelector('input[type=text]').value = shown; jQuery(h).trigger('change'); };
    setDate('hs_event_start', '20261112', '12/11/2026'); setDate('hs_event_end', '20261114', '14/11/2026'); await wait(500);
    out.range = card();
    setDate('hs_event_start', '', ''); setDate('hs_event_end', '', ''); await wait(300);
    wp.data.dispatch('core/editor').editPost({ title: 'PS Title Edited', date: '2026-09-05T10:00:00' }); await wait(800);
    out.editor = card();
    tick('post_tag_tax', ${JSON.stringify(setup.tag)}, false); await wait(500);
    out.noTags = card().tags.length;
    tick('post_tag_tax', ${JSON.stringify(setup.tag)}, true); await wait(300);
    const remove = f('hs_portrait_image').querySelector('a[data-name="remove"]'); if (remove) { remove.click(); } await wait(1200);
    out.featured = card().img;
    out.dirty = wp.data.select('core/editor').isEditedPostDirty();
    out.gate = { sectionOk: f('hs_department_tax').classList.contains('hs-valid') };
    tick('hs_department_tax', 'Secondary', false); await wait(500);
    out.gate.sectionMissing = f('hs_department_tax').classList.contains('hs-missing');
    tick('hs_department_tax', 'Secondary', true); await wait(500);
    out.gate.sectionBack = f('hs_department_tax').classList.contains('hs-valid');
    return JSON.stringify(out);`));
  t('ticking another Website Section updates the badge', edited.section === 'Secondary', edited.section);
  t('a Yellow badge override replaces it on the card and the heading; clearing it goes back', edited.badge.badge === 'PS Check Badge' && edited.badge.sec === 'PS Check Badge' && edited.badgeCleared === 'Secondary', JSON.stringify(edited.badge));
  t('both campuses → "Both Campuses"', edited.both === 'Both Campuses', edited.both);
  t('location override replaces the campus on the card and the chip', edited.loc.place === 'Bangkok Trip' && edited.loc.chipPlace === 'Bangkok Trip', JSON.stringify(edited.loc));
  t('event range: card short form, chip full form', edited.range.date === '12–14 Nov 2026' && edited.range.chipDate === '12–14 November 2026', JSON.stringify(edited.range));
  t('title and publish date from the editor sidebar', edited.editor.title === 'PS Title Edited' && edited.editor.date === '5th September 2026' && edited.editor.chipDate === '5 September 2026', JSON.stringify(edited.editor));
  t('unticking the tag removes its chip', edited.noTags === 0);
  t('removing the Portrait falls back to the Featured image', sameImage(edited.featured, setup.featured), `${edited.featured} vs ${setup.featured}`);
  t('the gatekeeper still marks Website Section: green when set, red when not', edited.gate.sectionOk && edited.gate.sectionMissing && edited.gate.sectionBack, JSON.stringify(edited.gate));
  await shot('post-2-edited-1920.png');

  console.log('\nSave and reload');
  await run(`${helpers}
    const loc = f('hs_location_override').querySelector('input[type=text]'); loc.value = 'PS Saved Place'; loc.dispatchEvent(new Event('input', { bubbles: true }));
    const h = f('hs_event_start').querySelector('input[type=hidden]'); h.value = '20261201'; jQuery(h).trigger('change');
    await wp.data.dispatch('core/editor').savePost(); await wait(6000);`);
  const saved = JSON.parse(evalFile(`$id = ${setup.id}; clean_post_cache($id); wp_cache_flush();
echo wp_json_encode(['title' => get_the_title($id), 'section' => wp_get_object_terms($id, 'hs_department', ['fields' => 'names']), 'campus' => wp_get_object_terms($id, 'hs_post_campus', ['fields' => 'slugs']),
  'location' => get_post_meta($id, 'hs_location_override', true), 'start' => get_post_meta($id, 'hs_event_start', true), 'portrait' => get_post_meta($id, 'hs_portrait_image', true),
  'tags' => wp_get_object_terms($id, 'post_tag', ['fields' => 'names']), 'focus' => get_post_meta($id, 'hs_hero_focus', true), 'mobile' => (int) get_post_meta($id, 'hs_hero_mobile', true), 'mfocus' => get_post_meta($id, 'hs_hero_mobile_focus', true), 'label' => get_post_meta($id, 'hs_final_date_label', true), 'chip' => get_post_meta($id, 'hs_final_section_chip', true)]);`).trim());
  t('saving stores every field', saved.title === 'PS Title Edited' && saved.section.join() === 'Secondary' && saved.campus.sort().join() === 'chaofah,cherngtalay' && saved.location === 'PS Saved Place' && saved.start === '20261201' && !saved.portrait && saved.tags.length === 1, JSON.stringify(saved));
  t('saving stores the hero position, the phone image and its position', saved.focus === '52 53' && saved.mobile === hf.mobileId && saved.mfocus === hf.mobileFocus && saved.mfocus !== '', JSON.stringify({ f: saved.focus, m: saved.mobile, mf: saved.mfocus }));
  t('the chips are recalculated on save', saved.chip === 'Secondary' && saved.label === '1 December 2026', JSON.stringify({ c: saved.chip, l: saved.label }));
  await open();
  const reloaded = JSON.parse(await run(`${helpers} await wait(400);
    return JSON.stringify({ card: card(), sec: [...f('hs_department_tax').querySelectorAll('input:checked')].map((i) => i.closest('label').textContent.trim()), loc: f('hs_location_override').querySelector('input[type=text]').value });`));
  t('after reload the fields and preview show the saved values', reloaded.sec.join() === 'Secondary' && reloaded.loc === 'PS Saved Place' && reloaded.card.place === 'PS Saved Place' && reloaded.card.date === '1st December 2026', JSON.stringify(reloaded));

  console.log('\nImage picker');
  const pk = JSON.parse(await run(`${helpers}
    const ed = () => wp.data.select('core/editor').getEditedPostAttribute('featured_media');
    const items = () => [...box.querySelectorAll('.hs-cip-rack-item')].filter((x) => !x.closest('.hs-chf-mobile'));
    const slotPick = (n) => box.querySelector('.hs-cip-slot[data-slot=' + n + '] .hs-cip-slot-pick');
    const out = { rack: items().map((b) => +b.dataset.id), acfHidden: f('hs_portrait_image').style.display === 'none', picker: !!box.querySelector('.hs-pc-images .hs-cip-grid') };
    slotPick('image').click(); await wait(200);
    items()[0].click(); await wait(800);
    out.featured = ed(); out.fTag = !!items()[0].querySelector('.hs-cip-tag.is-f'); out.focusAfterNewPhoto = f('hs_hero_focus').querySelector('input[type=text]').value; out.activeAfter = box.querySelector('.hs-cip-slot.is-active').dataset.slot;
    items()[1].click(); await wait(800);
    out.portrait = +f('hs_portrait_image').querySelector('.acf-image-uploader input[type=hidden]').value; out.pTag = !!items()[1].querySelector('.hs-cip-tag.is-p');
    out.previewImg = card().img;
    wp.data.dispatch('core/editor').editPost({ featured_media: ${setup.featuredId} }); await wait(2000);
    out.slotAfterSidebar = (box.querySelector('.hs-cip-slot[data-slot=image] .hs-cip-thumb') || {}).src || '';
    out.fTagGone = !items()[0].querySelector('.hs-cip-tag.is-f');
    slotPick('image').click(); await wait(100); items()[0].click(); await wait(500);
    await wp.data.dispatch('core/editor').savePost(); await wait(6000);
    return JSON.stringify(out);`));
  t('the rack lists this Post\'s Media Library photos in order (not the old-site one)', JSON.stringify(pk.rack) === JSON.stringify(setup.rackIds) && pk.picker, JSON.stringify(pk.rack));
  t('ACF\'s own Portrait box is hidden behind the picker', pk.acfHidden);
  t('clicking a photo sets the real featured image (editor store), tags it F, then picks the Portrait', pk.featured === setup.rackIds[0] && pk.fTag && pk.activeAfter === 'portrait', JSON.stringify(pk));
  t('a new Featured image starts the hero centred', pk.focusAfterNewPhoto === '', JSON.stringify(pk.focusAfterNewPhoto));
  t('the next click sets the ACF Portrait, tags it P, and the preview shows it', pk.portrait === setup.rackIds[1] && pk.pTag && sameImage(pk.previewImg, setup.rackUrls[1]), JSON.stringify({ p: pk.portrait, tag: pk.pTag, img: pk.previewImg }));
  t('changing the featured image in the sidebar updates the slot', sameImage(pk.slotAfterSidebar, setup.featured) && pk.fTagGone, pk.slotAfterSidebar);
  const pkSaved = JSON.parse(evalFile(`$id = ${setup.id}; clean_post_cache($id); echo wp_json_encode(['thumb' => (int) get_post_thumbnail_id($id), 'portrait' => (int) get_post_meta($id, 'hs_portrait_image', true)]);`).trim());
  t('saving stores the picked Featured image and Portrait', pkSaved.thumb === setup.rackIds[0] && pkSaved.portrait === setup.rackIds[1], JSON.stringify(pkSaved));
  await run(`${helpers}
    const rm = [...box.querySelectorAll('.hs-cip-slot[data-slot=image] .button-link')].find((b) => b.textContent === 'Remove'); if (rm) { rm.click(); } await wait(500);
    await wp.data.dispatch('core/editor').savePost(); await wait(6000);`);
  const gate = JSON.parse(evalFile(`$id = ${setup.id}; clean_post_cache($id); echo wp_json_encode(['thumb' => (int) get_post_thumbnail_id($id), 'missing' => function_exists('hs_post_gate_missing_fields') ? hs_post_gate_missing_fields($id) : null]);`).trim());
  t('Remove clears the featured image, and the gatekeeper then lists it as missing', gate.thumb === 0 && Array.isArray(gate.missing) && gate.missing.some((m) => /Featured Image/.test(m)), JSON.stringify(gate));
  await shot('post-3-picker-1920.png');

  console.log('\nNarrower window');
  await setWidth(1280); await sleep(1500);
  const narrow = JSON.parse(await run(`${helpers}
    const wrap = box.querySelector('.hs-pc-preview'), toggle = box.querySelector('.hs-pc-toggle');
    const hidden = wrap.offsetParent === null, toggleShown = toggle.offsetParent !== null;
    toggle.click(); await wait(400);
    const shownBelow = wrap.offsetParent !== null && wrap.getBoundingClientRect().top >= box.querySelector('.hs-pc-images').getBoundingClientRect().bottom - 2;
    toggle.click();
    return JSON.stringify({ hidden, toggleShown, shownBelow });`));
  t('at 1280 the preview is under the fields behind Show preview', narrow.hidden && narrow.toggleShown && narrow.shownBelow, JSON.stringify(narrow));
  await send('Emulation.clearDeviceMetricsOverride');

  t('no JavaScript errors on the page', errors.size === 0, [...errors].join(' | '));
  for (const [url, label] of [['http://headstart.local/cherngtalay/school-life/2026/10/headstart-cherngtalay-celebrates-exceed-standards-onesqa-accreditation-results/', 'a Post'], ['http://headstart.local/cherngtalay/weekly-update/2026/10/weekly-update-9/', 'a weekly update']]) {
    const r = await fetch(url);
    t(`${label} is 200 logged out`, r.status === 200, String(r.status));
  }
} catch (e) {
  t('check finished', false, e.message);
} finally {
  if (ws) ws.close();
  browser.kill();
  const left = evalFile(`wp_delete_post(${setup.id}, true); if (${setup.badge}) { wp_delete_term(${setup.badge}, 'hs_thematic_chip'); }
$d = json_decode(file_get_contents(getenv('HS_SESSION')), true); WP_Session_Tokens::get_instance($d['uid'])->destroy($d['tok']); echo 'deleted ${setup.id}';`);
  console.log(`  cleanup: ${left.trim()}, the temporary badge and the login session`);
  await sleep(300);
  rmSync(tmp, { recursive: true, force: true });
}
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
