#!/usr/bin/env node
/* Site search engine tests: node work/site-search/tests/run.js [--index path/to/index.json]
 * Uses the synthetic fixture (invented people only). With --index, also runs every case
 * against a real local index and prints the top results (no pass/fail; nothing is saved). */
'use strict';
var path = require('path');
var fs = require('fs');
var E = require(path.join(__dirname, '../../../Site-Scripts/js/site-search.js'));
var NOW = new Date('2026-11-06T10:00:00');
var fixture = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixture-index.json'), 'utf8'));
var cases = JSON.parse(fs.readFileSync(path.join(__dirname, 'cases.json'), 'utf8'));
var ctx = E.build(fixture);
var fails = 0;

function check(c) {
  var r = E.search(ctx, c.q, { now: NOW, lang: 'en' });
  var top = r.results[0] ? r.results[0].it : null;
  var errs = [];
  if (c.topic && (!r.topic || r.topic.it.ti !== c.topic)) { errs.push('topic ' + (r.topic ? r.topic.it.ti : 'none') + ' != ' + c.topic); }
  if (c.zero && r.results.length) { errs.push('expected no results, got ' + r.results.length + ' (top ' + top.ti + ')'); }
  if (c.nonEmpty && !r.results.length) { errs.push('expected results'); }
  if (c.topType && (!top || top.t !== c.topType)) { errs.push('top type ' + (top ? top.t : 'none') + ' != ' + c.topType); }
  if (c.top2Type && (!r.results[1] || r.results[1].it.t !== c.top2Type)) { errs.push('2nd type != ' + c.top2Type); }
  if (c.topTitle && (!top || top.ti !== c.topTitle)) { errs.push('top "' + (top ? top.ti : 'none') + '" != "' + c.topTitle + '"'); }
  if (c.topTitleIncludes && (!top || top.ti.indexOf(c.topTitleIncludes) < 0)) { errs.push('top "' + (top ? top.ti : 'none') + '" lacks "' + c.topTitleIncludes + '"'); }
  if (c.topIn && (!top || c.topIn.indexOf(top.ti) < 0)) { errs.push('top "' + (top ? top.ti : 'none') + '" not in ' + c.topIn.join('/')); }
  if (c.noType && r.results.some(function (x) { return x.it.t === c.noType; })) { errs.push('should have no ' + c.noType + ' results'); }
  if (c.corrected && r.corrected !== c.corrected) { errs.push('corrected "' + r.corrected + '" != "' + c.corrected + '"'); }
  if (c.corrected && r.words.indexOf(c.corrected) < 0) { errs.push('corrected word not in highlight words'); }
  if (c.notTitle && r.results.some(function (x) { return x.it.ti === c.notTitle; })) { errs.push('should not include ' + c.notTitle); }
  if (c.allCampus && r.results.some(function (x) { return x.it.c && x.it.c.length && x.it.c.indexOf(c.allCampus) < 0; })) { errs.push('result outside ' + c.allCampus); }
  (c.chips || []).forEach(function (ch) {
    if (!r.parsed.chips.some(function (x) { return x.k === ch[0] && (ch[1] === undefined || x.v === ch[1]); })) { errs.push('missing chip ' + ch.join(':')); }
  });
  return { errs: errs, r: r };
}

cases.forEach(function (c) {
  var out = check(c);
  if (out.errs.length) { fails++; console.log('FAIL  ' + JSON.stringify(c.q) + '  ' + out.errs.join('; ')); }
  else { console.log('ok    ' + JSON.stringify(c.q)); }
});

// Unit checks
var unit = [
  ['dl1 transposition', E.dl1('exmaple', 'example')],
  ['dl1 deletion', E.dl1('exampe', 'example')],
  ['dl1 rejects 2 edits', !E.dl1('exmapel', 'example')],
  ['norm & accents', E.norm('Café & Bar') === 'cafe and bar'],
  ['snippet window', E.snippet('a '.repeat(100) + 'target word here', ['target'], 40).indexOf('target') > -1]
];
unit.forEach(function (u) { if (!u[1]) { fails++; console.log('FAIL  unit: ' + u[0]); } else { console.log('ok    unit: ' + u[0]); } });

// Speed: ~3,000 synthetic items, 50 queries
var big = { campuses: fixture.campuses, items: [] };
var vocab = 'science maths primary secondary foundation sports music chess market fair holiday reading trip camp award parents term club art drama swim football basketball thai mandarin russian library uniform bus lunch menu exam result report meeting assembly'.split(' ');
for (var i = 0; i < 3000; i++) {
  var w = function () { return vocab[(i * 7 + Math.floor(Math.random() * vocab.length)) % vocab.length]; };
  big.items.push({ id: 'x' + i, t: ['article', 'person', 'doc', 'page', 'issue'][i % 5], ti: w() + ' ' + w() + ' ' + w() + ' ' + i, sn: Array.from({ length: 20 }, w).join(' '), d: '2026-10-' + ('0' + (1 + i % 28)).slice(-2), c: i % 3 ? ['chaofah'] : [] });
}
var bctx = E.build(big);
var qs = [];
for (var k = 0; k < 50; k++) { qs.push(vocab[k % vocab.length].slice(0, 2 + (k % 5)) + (k % 3 ? ' ' + vocab[(k * 3) % vocab.length] : '')); }
var t0 = Date.now();
qs.forEach(function (q) { E.search(bctx, q, { now: NOW }); });
var avg = (Date.now() - t0) / qs.length;
var speedOk = avg < 15;
if (!speedOk) { fails++; }
console.log((speedOk ? 'ok    ' : 'FAIL  ') + 'speed: ' + avg.toFixed(2) + ' ms per query on ' + big.items.length + ' items');

var ix = process.argv.indexOf('--index');
if (ix > -1 && process.argv[ix + 1]) {
  var real = E.build(JSON.parse(fs.readFileSync(process.argv[ix + 1], 'utf8')));
  console.log('\nReal index (' + real.items.length + ' items), top 3 per case:');
  var t1 = Date.now();
  cases.forEach(function (c) {
    var r = E.search(real, c.q, { now: NOW, lang: 'en' });
    console.log('  ' + JSON.stringify(c.q).padEnd(32) + (r.topic ? '[' + r.topic.it.ti + '] ' : '') + r.results.slice(0, 3).map(function (x) { return x.it.t + ':' + x.it.ti.slice(0, 40); }).join(' | '));
  });
  console.log('  ' + ((Date.now() - t1) / cases.length).toFixed(2) + ' ms per query');
}

console.log(fails ? '\n' + fails + ' failed' : '\nAll passed');
process.exit(fails ? 1 : 0);
