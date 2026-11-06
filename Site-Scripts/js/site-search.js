/**
 * =============================================================================
 * HEADSTART SITE SEARCH (feat/site-search, Nov 2026)
 * =============================================================================
 * PURPOSE: The search field in the utility bar ([hs_search]) and the mobile
 *          magnifier ([hs_search_button]). Loads /wp-content/uploads/hs-search/
 *          index.json on first use, searches it in memory on every keystroke and
 *          shows grouped results in a panel that drops down under the bar.
 * PARTS:   HSSearchEngine  pure functions, no DOM (unit-tested in Node:
 *                          work/site-search/tests/run.js)
 *          UI              only in a browser, never in the Divi builder
 * DEPENDS: nothing (plain ES2017). Index built by the Search-Index-Builder snippet.
 * =============================================================================
 */
(function (root) {
  'use strict';

  /* =========================================================================
   * ENGINE
   * ====================================================================== */
  var HSSearchEngine = (function () {
    var STOP = {};
    'how much what is the a an do does i my for of to in at and are when where can you me about on with from by'.split(' ').forEach(function (w) { STOP[w] = 1; });

    var VARIANTS = { enroll: 'enrol', enrolment: 'enrolment', enrollment: 'enrolment', enrolling: 'enrol', enrolled: 'enrol', program: 'programme', programs: 'programmes', color: 'colour', colors: 'colours', center: 'centre', organize: 'organise', yr: 'year' };

    var TYPE_WORDS = {
      staff: 'person', people: 'person', person: 'person', teacher: 'person', teachers: 'person', who: 'person',
      event: 'event', events: 'event', calendar: 'event', whats: 'event',
      weekly: 'issue', update: 'issue', updates: 'issue', newsletter: 'issue', bulletin: 'issue', news: 'article', story: 'article', stories: 'article', article: 'article', articles: 'article',
      doc: 'doc', docs: 'doc', document: 'doc', documents: 'doc', pdf: 'doc', form: 'doc', forms: 'doc', handbook: 'doc', download: 'doc', downloads: 'doc',
      page: 'page', pages: 'page'
    };
    /* Type words that are also real content words: they stay required search words. */
    var TYPE_AND_TEXT = { teacher: 1, teachers: 1, handbook: 1, form: 1, forms: 1, calendar: 1 };

    var MONTHS = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
    var MON3 = {}; MONTHS.forEach(function (m, i) { MON3[m] = i; MON3[m.slice(0, 3)] = i; });
    delete MON3.mar; // "mar" is too ambiguous on its own
    MON3.march = 2; MON3.sept = 8;

    var W = { titleExact: 100, titlePrefix: 80, title: 50, syn: 50, cls: 45, kw: 30, sub: 20, text: 10 };

    var SEG = null;
    try { if (typeof Intl !== 'undefined' && Intl.Segmenter) { SEG = new Intl.Segmenter(undefined, { granularity: 'word' }); } } catch (e) { SEG = null; }

    function norm(s) {
      s = String(s == null ? '' : s).toLowerCase();
      if (s.normalize) { s = s.normalize('NFKD').replace(/[̀-ͯ]/g, ''); }
      return s.replace(/&/g, ' and ').replace(/[’'`]/g, '').replace(/[^\p{L}\p{N}#]+/gu, ' ').replace(/#/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function splitWords(s) {
      var n = norm(s);
      if (!n) { return []; }
      var out = [];
      n.split(' ').forEach(function (w) {
        if (/^[a-z0-9]+$/.test(w)) { out.push(VARIANTS[w] || w); return; }
        // Thai, Chinese and other scripts without spaces
        if (SEG) {
          var it = SEG.segment(w);
          var arr = Array.from ? Array.from(it) : [];
          arr.forEach(function (x) { if (x.isWordLike !== false && x.segment.trim()) { out.push(x.segment); } });
          if (arr.length) { return; }
        }
        if (/[^\x00-\x7f]/.test(w) && w.length > 2) {
          for (var i = 0; i < w.length - 1; i++) { out.push(w.substr(i, 2)); }
        } else { out.push(w); }
      });
      return out;
    }

    function dl1(a, b) {
      // Damerau-Levenshtein distance <= 1 ?
      if (a === b) { return true; }
      var la = a.length, lb = b.length;
      if (Math.abs(la - lb) > 1) { return false; }
      var i = 0;
      while (i < la && i < lb && a[i] === b[i]) { i++; }
      if (la === lb) {
        if (a.slice(i + 1) === b.slice(i + 1)) { return true; }                      // substitution
        return a[i] === b[i + 1] && a[i + 1] === b[i] && a.slice(i + 2) === b.slice(i + 2); // transposition
      }
      return la > lb ? a.slice(i + 1) === b.slice(i) : a.slice(i) === b.slice(i + 1);   // deletion / insertion
    }

    function fieldsOf(it) {
      var f = [];
      var on = it.on || {};
      f.push([it.ti, W.title]);
      if (it.syn) { f.push([it.syn.join(' '), W.syn]); }
      if (it.cl) { f.push([it.cl.join(' '), W.cls]); }
      if (it.g) { f.push([it.g, W.kw]); }
      if (it.k) { f.push([[].concat(it.k).join(' '), W.kw]); }
      // the year (and month) of dated items, so "christmas 2025" or "prom 2026" work
      var dt = it.d && /^\d{4}-\d{2}/.test(it.d) ? it.d.slice(0, 4) + ' ' + ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'][+it.d.slice(5, 7) - 1] : '';
      f.push([[it.s, it.sec, it.p, it.bc, on.ti, on.sec, it.loc, it.wk ? 'week ' + it.wk : '', it.t === 'issue' || it.t === 'article' || it.t === 'doc' ? dt : ''].join(' '), W.sub]);
      f.push([[it.sn, it.x, it.a].join(' '), W.text]);
      return f;
    }

    /** Build the in-memory index from the JSON index. */
    function build(index) {
      var items = (index && index.items) || [];
      var postings = Object.create(null);
      var topics = [];
      items.forEach(function (it, idx) {
        it._tn = norm(it.ti);
        if (it.t === 'topic') {
          topics.push({ idx: idx, phrases: (it.syn || []).map(norm).concat([it._tn]).filter(Boolean) });
        }
        fieldsOf(it).forEach(function (pair) {
          splitWords(pair[0]).forEach(function (tok) {
            var p = postings[tok] || (postings[tok] = Object.create(null));
            if (!(p[idx] >= pair[1])) { p[idx] = pair[1]; }
          });
        });
      });
      var tokens = Object.keys(postings).sort();
      var aliases = [];
      ((index && index.campuses) || []).forEach(function (c) {
        (c.a || []).concat([c.k]).forEach(function (a) { aliases.push({ k: c.k, l: c.l, a: norm(a) }); });
      });
      aliases.sort(function (a, b) { return b.a.length - a.a.length; });
      return { items: items, postings: postings, tokens: tokens, topics: topics, aliases: aliases, campuses: (index && index.campuses) || [] };
    }

    function lowerBound(arr, s) {
      var lo = 0, hi = arr.length;
      while (lo < hi) { var mid = (lo + hi) >> 1; if (arr[mid] < s) { lo = mid + 1; } else { hi = mid; } }
      return lo;
    }

    /** item idx -> best weighted score for one query token. */
    function matchToken(ctx, tok, allowPrefix, fuzzyHits) {
      var out = Object.create(null);
      var add = function (t, factor) {
        var p = ctx.postings[t];
        for (var k in p) { var v = p[k] * factor; if (!(out[k] >= v)) { out[k] = v; } }
      };
      if (ctx.postings[tok]) { add(tok, 1); }
      if (allowPrefix) {
        var i = lowerBound(ctx.tokens, tok), n = 0;
        while (i < ctx.tokens.length && ctx.tokens[i].indexOf(tok) === 0 && n < 400) {
          if (ctx.tokens[i] !== tok) { add(ctx.tokens[i], 0.7); }
          i++; n++;
        }
      }
      if (tok.length >= 4 && Object.keys(out).length === 0) {
        var j = lowerBound(ctx.tokens, tok[0]);
        while (j < ctx.tokens.length && ctx.tokens[j][0] === tok[0]) {
          var t = ctx.tokens[j];
          if (Math.abs(t.length - tok.length) <= 1 && dl1(tok, t)) { add(t, 0.4); if (fuzzyHits) { fuzzyHits.push([tok, t]); } }
          j++;
        }
      }
      return out;
    }

    function ymd(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }

    /** Query → chips + words. */
    function parse(ctx, raw, now) {
      now = now || new Date();
      var q = ' ' + norm(raw) + ' ';
      var chips = [], campus = '', range = null, week = 0;

      // campus (longest alias first)
      for (var i = 0; i < ctx.aliases.length; i++) {
        var al = ctx.aliases[i];
        if (al.a && q.indexOf(' ' + al.a + ' ') > -1) {
          campus = al.k;
          chips.push({ k: 'campus', v: al.k, label: al.l, words: al.a });
          q = q.replace(' ' + al.a + ' ', ' ');
          break;
        }
      }

      // relative dates
      var rel = [['this week', 0, 'w'], ['next week', 1, 'w'], ['last week', -1, 'w'], ['this month', 0, 'm'], ['next month', 1, 'm'], ['today', 0, 'd'], ['tomorrow', 1, 'd']];
      rel.forEach(function (r) {
        if (range || q.indexOf(' ' + r[0] + ' ') < 0) { return; }
        var a = new Date(now.getFullYear(), now.getMonth(), now.getDate()), b;
        if (r[2] === 'd') { a.setDate(a.getDate() + r[1]); b = new Date(a); }
        if (r[2] === 'w') { var dow = (a.getDay() + 6) % 7; a.setDate(a.getDate() - dow + 7 * r[1]); b = new Date(a); b.setDate(a.getDate() + 6); }
        if (r[2] === 'm') { a = new Date(now.getFullYear(), now.getMonth() + r[1], 1); b = new Date(a.getFullYear(), a.getMonth() + 1, 0); }
        range = { from: ymd(a), to: ymd(b) };
        chips.push({ k: 'date', label: r[0], words: r[0] });
        q = q.replace(' ' + r[0] + ' ', ' ');
      });

      // week N (weekly updates)
      var wm = q.match(/ (?:week|wk) (\d{1,2}) /) || q.match(/ (?:weekly update|update) (\d{1,2}) /);
      if (wm) { week = +wm[1]; chips.push({ k: 'week', label: 'Week ' + week, words: wm[0].trim() }); q = q.replace(wm[0], ' '); }

      var words = q.trim() ? q.trim().split(' ') : [];
      var free = [], optional = [], types = {};
      var hasEventWord = words.some(function (w) { return TYPE_WORDS[w] === 'event'; });
      words.forEach(function (w, wi) {
        w = VARIANTS[w] || w;
        // month (+ optional year); "may" only next to an event word or a year
        if (!range && MON3.hasOwnProperty(w) && (w !== 'may' || hasEventWord || /^\d{4}$/.test(words[wi + 1] || ''))) {
          var mi = MON3[w], yr = /^\d{4}$/.test(words[wi + 1] || '') ? +words[wi + 1] : 0;
          if (!yr) { yr = now.getFullYear(); if (mi < now.getMonth() - 2) { yr++; } }
          range = { from: ymd(new Date(yr, mi, 1)), to: ymd(new Date(yr, mi + 1, 0)) };
          chips.push({ k: 'date', label: MONTHS[mi].charAt(0).toUpperCase() + MONTHS[mi].slice(1) + (words[wi + 1] && /^\d{4}$/.test(words[wi + 1]) ? ' ' + yr : ''), words: w });
          return;
        }
        if (/^\d{4}$/.test(w) && range) { return; }
        if (TYPE_WORDS[w]) {
          types[TYPE_WORDS[w]] = 1;
          if (TYPE_WORDS[w] === 'issue') { types.article = 1; }
          if (TYPE_WORDS[w] === 'article') { types.issue = 1; }
          if (TYPE_AND_TEXT[w]) { free.push(w); } else { optional.push(w); }
          return;
        }
        if (STOP[w]) { return; }
        free.push(w);
      });
      Object.keys(types).forEach(function (t) {
        if (t === 'article') { return; }
        chips.push({ k: 'type', v: t, label: { person: 'People', event: 'Events', issue: 'Weekly updates', doc: 'Documents', page: 'Pages' }[t] || t });
      });
      return { raw: raw, norm: norm(raw), free: free, optional: optional, campus: campus, range: range, week: week, types: types, chips: chips };
    }

    function topicScore(ctx, it, qn) {
      if (!qn || qn.split(' ').every(function (w) { return STOP[w]; }) && !/how much/.test(qn)) {
        // stopword-only queries ("the") never trigger a card; "how much" is a real phrase
        if (!qn || !(it.syn || []).some(function (x) { return norm(x) === qn; })) { return 0; }
      }
      var best = 0, phrases = [it._tn].concat((it.syn || []).map(norm));
      var padded = ' ' + qn + ' ';
      phrases.forEach(function (ph) {
        if (!ph) { return; }
        if (padded.indexOf(' ' + ph + ' ') > -1) { best = Math.max(best, 60 + Math.min(ph.length, 20)); }
        else if (qn.length >= 3 && (' ' + ph).indexOf(' ' + qn) > -1) { best = Math.max(best, 45 + Math.min(qn.length, 15)); }
      });
      return best;
    }

    function ageDays(d, now) { return d ? Math.max(0, (now - new Date(d + 'T00:00:00')) / 864e5) : 0; }

    /**
     * Search. opts: {campus, now, lang, limit}
     * Returns {parsed, results:[{it, score}], topic, relaxed, words}
     */
    function search(ctx, raw, opts) {
      opts = opts || {};
      var now = opts.now || new Date();
      var p = parse(ctx, raw, now);
      var campus = opts.campus || p.campus;
      var words = p.free.filter(function (w) { return w.length; });
      // "staff" / "people" on their own are searched as text, not as "list every member of staff":
      // they find the Staff Directory page and quick answer, and only people whose first or last name
      // contains the word (never their job title or profile). (Other type words alone, e.g. "documents", still list that type.)
      var typeOnly = !words.length && p.optional.length > 0 && !p.range && !p.week && !!p.types.person;
      if (typeOnly) { words = p.optional.slice(); }
      var res = [], relaxed = false;
      var topic = null, topics = [];

      // Quick answers by phrase (before stopwords are removed)
      ctx.topics.forEach(function (tp) {
        var it = ctx.items[tp.idx];
        var s = topicScore(ctx, it, p.norm);
        if (s >= 45) { topics.push({ it: it, score: s }); }
      });
      topics.sort(function (a, b) { return b.score - a.score; });
      topic = topics[0] || null;

      if (!words.length && !p.optional.length && !campus && !p.range && !p.week) {
        return { parsed: p, results: [], topic: topic, topics: topics, relaxed: false, words: [] };
      }

      var fuzzyHits = [];
      var run = function (mode) {
        var scores = null;
        fuzzyHits.length = 0;
        var lists = words.map(function (w, i) { return matchToken(ctx, w, i === words.length - 1 || w.length >= 3, fuzzyHits); });
        if (words.length) {
          if (mode === 'and') {
            lists.forEach(function (m) {
              if (scores === null) { scores = Object.create(null); for (var k in m) { scores[k] = m[k]; } return; }
              var next = Object.create(null);
              for (var k2 in scores) { if (m[k2] !== undefined) { next[k2] = scores[k2] + m[k2]; } }
              scores = next;
            });
          } else {
            scores = Object.create(null);
            lists.forEach(function (m) { for (var k in m) { scores[k] = (scores[k] || 0) + m[k]; } });
          }
        } else {
          // only type words, campus, dates: everything that fits the filters
          scores = Object.create(null);
          ctx.items.forEach(function (it, idx) { if (it.t !== 'topic') { scores[idx] = 1; } });
          p.optional.forEach(function (w) { var m = matchToken(ctx, w, false); for (var k in m) { if (scores[k] !== undefined) { scores[k] += m[k] / 2; } } });
        }
        var out = [];
        var freeJoined = words.join(' ');
        for (var key in scores) {
          var it = ctx.items[key], s = scores[key];
          if (it.t === 'topic') { continue; }
          if (campus && it.c && it.c.length && it.c.indexOf(campus) < 0) { continue; }
          if (p.types.person && it.t !== 'person' && words.length === 0) { continue; }
          if (typeOnly && it.t === 'person' && !words.every(function (w) { return (' ' + it._tn + ' ').indexOf(' ' + w) > -1; })) { continue; }
          if (p.range) {
            if (it.t === 'event') { if ((it.de || it.d) < p.range.from || it.d > p.range.to) { continue; } }
            else if (it.t === 'issue' || it.t === 'article') { if (!it.d || it.d < p.range.from || it.d > p.range.to) { continue; } }
            else { continue; }
          }
          if (p.week) { if (it.t !== 'issue' || it.wk !== p.week) { continue; } }
          if (!p.week && !words.length && p.types && Object.keys(p.types).length && !p.types[it.t]) { continue; }
          if (freeJoined) {
            if (it._tn === freeJoined) { s += W.titleExact; }
            else if (it._tn.indexOf(freeJoined) === 0) { s += W.titlePrefix; }
          }
          if (p.types[it.t]) { s += 25; }
          if (campus && it.c && it.c.indexOf(campus) > -1) { s += 5; }
          if (p.optional.length && words.length && !typeOnly) {
            p.optional.forEach(function (w) { var m = ctx.postings[w]; if (m && m[key]) { s += m[key] / 5; } });
          }
          if (it.t === 'event') { s += ((it.de || it.d) >= ymd(now)) ? 15 : -25; }
          if (it.t === 'issue' || it.t === 'article') { s -= Math.min(ageDays(it.d, now) / 30, 20); }
          if (it.t === 'issue' && (p.types.issue || p.week)) { s += (it.wk || 0) / 10 + Math.max(0, 20 - ageDays(it.d, now) / 30); }
          if (it.ar) { s -= 10; }
          if (it.t === 'doc') { if (it.pin) { s += 40; } if (it.up) { s -= 8 + Math.min(ageDays(it.d, now) / 60, 10); } }
          if (opts.lang && it.lg) { s += it.lg === opts.lang ? 10 : -15; }
          out.push({ it: it, score: s });
        }
        return out;
      };

      res = run('and');
      if (!res.length && words.length > 1) { res = run('or'); relaxed = res.length > 0; }
      res.sort(function (a, b) { return b.score - a.score || (b.it.d || '').localeCompare(a.it.d || '') || a.it.ti.localeCompare(b.it.ti); });
      if (opts.limit) { res = res.slice(0, opts.limit); }
      // Typo tolerance: highlight (and report) the words that actually matched, e.g. "internationl" → "international"
      var corrected = {}, extra = [];
      fuzzyHits.forEach(function (h) { if (!corrected[h[0]]) { corrected[h[0]] = h[1]; } if (extra.indexOf(h[1]) < 0) { extra.push(h[1]); } });
      var shownWords = words.map(function (w) { return corrected[w] || w; });
      return { parsed: p, results: res, topic: topic, topics: topics, relaxed: relaxed, words: words.concat(p.optional, extra),
        corrected: Object.keys(corrected).length ? shownWords.join(' ') : '' };
    }

    var ORDER = ['person', 'page', 'topic', 'issue', 'article', 'event', 'doc'];
    /** Group results by display group; groups ordered by their best score. */
    function group(results) {
      var g = {};
      results.forEach(function (r) {
        var key = r.it.t;
        (g[key] || (g[key] = { key: key, items: [], best: r.score })).items.push(r);
      });
      return Object.keys(g).map(function (k) { return g[k]; }).sort(function (a, b) { return b.best - a.best || ORDER.indexOf(a.key) - ORDER.indexOf(b.key); });
    }

    /** Short text window around the first matching word. */
    function snippet(text, words, len) {
      text = String(text || '');
      len = len || 150;
      var lower = text.toLowerCase(), at = -1;
      (words || []).forEach(function (w) { var j = lower.indexOf(w); if (j > -1 && (at < 0 || j < at)) { at = j; } });
      // Short text, or the match is near the start: show it from the beginning.
      // Otherwise start just before the match, so it is visible within the 2-3 lines a card shows.
      if (at < 70 && text.length <= len) { return text; }
      if (at < 70) { at = -1; }
      var lead = Math.min(40, Math.floor(len / 3));
      var from = at > lead ? at - lead : 0;
      if (from > 0) { var sp = text.indexOf(' ', from); if (sp > -1 && sp < at) { from = sp + 1; } }
      var out = text.substr(from, len);
      var cut = out.lastIndexOf(' ');
      if (from + len < text.length && cut > len * 0.6) { out = out.slice(0, cut); }
      return (from > 0 ? '…' : '') + out.trim() + (from + out.length < text.length ? '…' : '');
    }

    return { norm: norm, words: splitWords, build: build, parse: parse, search: search, group: group, snippet: snippet, dl1: dl1 };
  })();

  if (typeof module !== 'undefined' && module.exports) { module.exports = HSSearchEngine; }
  if (typeof document === 'undefined') { return; }
  root.HSSearchEngine = HSSearchEngine;

  /* =========================================================================
   * UI
   * ====================================================================== */
  var href = String(root.location && root.location.href);
  if (href.indexOf('et_fb=1') > -1 || href.indexOf('et_pb_preview=true') > -1 || root.top !== root.self) { return; }

  var INDEX_URL = '/wp-content/uploads/hs-search/index.json';
  var AJAX_URL = '/wp-admin/admin-ajax.php';
  var DEBUG = /[?&]hs_search_debug=1/.test(root.location.search);
  var GROUP_LABEL = { person: 'People', page: 'Pages', topic: 'Quick answers', issue: 'Weekly updates', article: 'Articles', event: 'Events', doc: 'Documents' };
  var MONTH = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  var ICON = {
    search: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>',
    page: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/></svg>',
    issue: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8M15 18h-5M10 6h8v4h-8z"/></svg>',
    doc: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M12 11v6M9.5 14.5 12 17l2.5-2.5"/></svg>',
    info: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.6h.01"/></svg>',
    topic: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/></svg>',
    person: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8.5" r="4"/><path d="M4 22c0-4.4 3.6-8 8-8s8 3.6 8 8z"/></svg>',
    phone: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>',
    pin: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>'
  };
  var INFOBTN = '<span class="hs-sp-info" role="button" aria-label="Details" data-info="1"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.6h.01"/></svg>Info</span>';
  var DOCTYPE = { gdoc: 'Google Doc', gsheet: 'Google Sheet', gslides: 'Google Slides', gform: 'Google Form', folder: 'Google Drive folder', file: 'Google Drive file', pdf: 'PDF', word: 'Word document', excel: 'Spreadsheet', ppt: 'Presentation', zip: 'Zip file' };
  var FT = { gdoc: 'DOC', gsheet: 'SHEET', gslides: 'SLIDES', gform: 'FORM', folder: 'FOLDER', file: 'FILE', pdf: 'PDF', word: 'DOC', excel: 'XLS', ppt: 'PPT', zip: 'ZIP' };

  var state = { ctx: null, loading: null, campus: '', expanded: {}, active: 0, flat: [], field: null, wrap: null, sheet: false, lastQ: '', idleT: 0, committed: '' };
  var panel = null, els = {};

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function hl(text, words) {
    var out = esc(text);
    (words || []).filter(function (w) { return w && w.length > 0; }).forEach(function (w) {
      var re = new RegExp('(^|[^\\p{L}\\p{N}])(' + esc(w).replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'giu');
      out = out.replace(re, '$1<mark>$2</mark>');
    });
    return out;
  }
  function fmtDate(d, withYear) {
    if (!d) { return ''; }
    var p = d.split('-');
    return (+p[2]) + ' ' + MONTH[+p[1] - 1] + (withYear ? ' ' + p[0] : '');
  }
  function campusTag(c) {
    if (!c || !c.length) { return '<span class="hs-sp-tag">Both</span>'; }
    var name = c[0] === 'chaofah' ? 'Chaofah' : 'Cherngtalay';
    return '<span class="hs-sp-tag hs-sp-tag--campus">' + name + '</span>';
  }
  function tel(n) { return 'tel:' + String(n).replace(/[^\d+]/g, ''); }
  function lsGet(k) { try { return JSON.parse(root.localStorage.getItem(k) || 'null'); } catch (e) { return null; } }
  function lsSet(k, v) { try { root.localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ } }

  /* ---------- index loading ---------- */
  function loadIndex() {
    if (state.ctx || state.loading) { return state.loading; }
    var t0 = Date.now();
    var parse = function (json) {
      state.ctx = HSSearchEngine.build(json);
      if (DEBUG) { console.log('[hs-search] index', json.items.length, 'items in', Date.now() - t0, 'ms'); }
      render();
    };
    state.loading = fetch(INDEX_URL, { cache: 'no-cache', credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
      .catch(function () {
        var body = new URLSearchParams(); body.append('action', 'hs_search_index');
        return fetch(AJAX_URL, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); });
      })
      .then(parse)
      .catch(function () { state.failed = true; render(); });
    return state.loading;
  }

  /* ---------- panel ---------- */
  function buildPanel() {
    if (panel) { return panel; }
    panel = document.createElement('div');
    panel.className = 'hs-search-panel';
    panel.id = 'hs-search-panel';
    panel.setAttribute('role', 'region');
    panel.setAttribute('aria-label', 'Search results');
    panel.innerHTML =
      '<div class="hs-sp-sheethead">' +
        '<input class="hs-sp-sheetfield" type="search" autocomplete="off" spellcheck="false" placeholder="Search the site…" aria-label="Search the site" role="combobox" aria-controls="hs-search-list" aria-expanded="true">' +
        '<button type="button" class="hs-sp-close" aria-label="Close search">Close</button></div>' +
      '<div class="hs-sp-filters"><div class="hs-sp-seg" role="group" aria-label="Campus">' +
        '<button type="button" data-c="" class="is-on">Both campuses</button><button type="button" data-c="chaofah">Chaofah</button><button type="button" data-c="cherngtalay">Cherngtalay</button>' +
      '</div><div class="hs-sp-understood"></div></div>' +
      '<div class="hs-sp-body" id="hs-search-list" role="listbox" aria-label="Results"></div>' +
      '<div class="hs-sp-foot"><span><b>↑ ↓</b> move</span><span><b>Enter</b> open</span><span><b>Esc</b> clear / close</span><span class="hs-sp-sp"></span><span>Press <b>/</b> or <b>Ctrl K</b> to search</span></div>' +
      '<div class="hs-sp-live" aria-live="polite"></div>';
    els = {
      body: panel.querySelector('.hs-sp-body'),
      under: panel.querySelector('.hs-sp-understood'),
      seg: panel.querySelector('.hs-sp-seg'),
      live: panel.querySelector('.hs-sp-live'),
      sheetField: panel.querySelector('.hs-sp-sheetfield')
    };
    els.seg.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) { return; }
      state.campus = b.getAttribute('data-c');
      Array.prototype.forEach.call(els.seg.children, function (x) { x.classList.toggle('is-on', x === b); });
      state.active = 0; render(); currentField().focus();
    });
    els.under.addEventListener('click', function (e) {
      var b = e.target.closest('[data-rm]'); if (!b) { return; }
      var f = currentField();
      f.value = (' ' + f.value + ' ').replace(new RegExp('\\s' + b.getAttribute('data-rm').replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?=\\s)', 'i'), ' ').replace(/\s+/g, ' ').trim();
      syncFields(f); render(); f.focus();
    });
    els.body.addEventListener('mousemove', function (e) {
      var r = e.target.closest('.hs-sp-row'); if (!r) { return; }
      var i = +r.getAttribute('data-i'); if (i !== state.active) { state.active = i; markActive(false); }
    });
    els.body.addEventListener('click', function (e) {
      var q = e.target.closest('[data-q]');
      if (q) { e.preventDefault(); setQuery(q.getAttribute('data-q')); return; }
      var dt = e.target.closest('[data-docs]');
      if (dt) { e.preventDefault(); state.docsOpen = !state.docsOpen; place(); render(); return; }
      var lg = e.target.closest('[data-lang]');
      if (lg) { e.preventDefault(); state.pageLang = lg.getAttribute('data-lang'); state.active = 0; state.keepActive = true; render(); return; }
      var pt = e.target.closest('[data-people]');
      if (pt) { e.preventDefault(); state.peopleOpen = !state.peopleOpen; place(); render(); return; }
      var ex = e.target.closest('[data-ex]');
      if (ex) { e.preventDefault(); var k = ex.getAttribute('data-ex'); state.expanded[k] = (state.expanded[k] || 0) + 10; render(); return; }
      var a = e.target.closest('a[href]');
      if (a) { commit('click', a.getAttribute('data-type') || 'link', +(a.getAttribute('data-pos') || 0)); }
    });
    panel.querySelector('.hs-sp-close').addEventListener('click', close);

    // Document tooltip: our own (quick, readable) instead of the browser's slow title tooltip
    els.tip = document.createElement('div');
    els.tip.className = 'hs-sp-tip';
    els.tip.setAttribute('role', 'tooltip');
    document.body.appendChild(els.tip);
    els.tip.addEventListener('click', function (e) {
      if (e.target.closest('.hs-sp-tip-x')) { e.preventDefault(); e.stopPropagation(); els.tipRow = null; hideTip(); }
    });
    // Details appear only from the ⓘ on the hovered / selected card (option C): hover it (instant),
    // tap or click it (toggle, for touch), or press → at the end of the typed text.
    els.body.addEventListener('mouseover', function (e) {
      var ic = e.target.closest('[data-info]');
      if (!ic) { return; }
      var row = ic.closest('[data-tip]');
      if (row && row !== els.tipRow) { els.tipRow = row; showTip(row); }
    });
    els.body.addEventListener('mouseout', function (e) {
      var ic = e.target.closest('[data-info]');
      if (ic && !ic.contains(e.relatedTarget) && !els.tipPinned) { els.tipRow = null; hideTip(); }
    });
    els.body.addEventListener('click', function (e) {
      var ic = e.target.closest('[data-info]');
      if (!ic) { if (els.tipPinned) { els.tipPinned = false; els.tipRow = null; hideTip(); } return; }
      e.preventDefault(); e.stopPropagation();
      var row = ic.closest('[data-tip]');
      if (els.tipPinned && els.tipRow === row) { els.tipPinned = false; els.tipRow = null; hideTip(); return; }
      els.tipRow = row; els.tipPinned = true; showTip(row);
    }, true);
    els.body.addEventListener('scroll', hideTip, true);
    els.sheetField.addEventListener('input', onInput);
    els.sheetField.addEventListener('keydown', onKey);
    return panel;
  }

  /** One tooltip for every result type (same look as the document tooltip). */
  function showTip(row) {
    var it = state.flat[+row.getAttribute('data-i')];
    if (!it) { return; }
    var campus = it.c && it.c.length ? (it.c[0] === 'chaofah' ? 'Chaofah' : 'Cherngtalay') : 'Both campuses';
    var rows = [], quote = '', list = null, listLabel = '';
    switch (it.t) {
      case 'person':
        rows = [['Job title', it.s || ''], ['Department', (it.k || []).join(', ')], ['Campus', campus]];
        break;
      case 'doc':
        var on = it.on || {};
        rows = [['Found on', (on.ti || '') + (on.sec ? ' › ' + on.sec : '')], ['Date', on.d ? fmtDate(on.d, true) : ''], ['Type', DOCTYPE[it.ft] || 'Document'], ['Campus', campus]];
        quote = it.cx || '';
        break;
      case 'page':
        rows = [['Section', it.bc || ''], ['Language', (it.lg || 'en') === 'th' ? 'Thai' : (it.lg || 'en') === 'en' ? 'English' : (it.lg || '').toUpperCase()], ['Campus', campus]];
        quote = it.s || '';
        if (it.x) { list = it.x.split(' · ').filter(Boolean).slice(0, 5); listLabel = 'On this page'; }
        break;
      case 'article':
        rows = [['From', it.p || (it.k || []).join(', ')], ['Date', fmtDate(it.d, true)], ['Section', it.sec && it.sec !== it.ti ? it.sec : ''], ['Campus', campus]];
        quote = it.sn || '';
        break;
      case 'issue':
        rows = [['Week', it.wk ? String(it.wk) : ''], ['Date', fmtDate(it.d, true)], ['Campus', campus]];
        // matching headlines first, so the reason it was found is visible
        var all = (it.x ? it.x.split(' · ') : (it.k || [])).filter(Boolean), tw = state.words || [];
        var hits = all.filter(function (h) { var l = h.toLowerCase(); return tw.some(function (w) { return l.indexOf(w) > -1; }); });
        list = hits.concat(all.filter(function (h) { return hits.indexOf(h) < 0; })).slice(0, 6); listLabel = 'In this issue';
        break;
      case 'event':
        rows = [['Date', fmtDate(it.d, true) + (it.de && it.de !== it.d ? ' – ' + fmtDate(it.de, true) : '') + (it.tm ? ' · ' + it.tm : '')], ['Location', it.loc || ''], ['Campus', campus]];
        quote = it.s || '';
        break;
      default:
        return;
    }
    rows = rows.filter(function (r) { return r[1]; });
    els.tip.classList.toggle('is-touch', !!state.sheet);
    els.tip.innerHTML = (state.sheet ? '<button type="button" class="hs-sp-tip-x" aria-label="Close details">✕</button>' : '') +
      '<div class="hs-sp-tip-t">' + esc(it.ti) + '</div>' +
      (rows.length ? '<dl>' + rows.map(function (r) { return '<dt>' + r[0] + '</dt><dd>' + esc(r[1]) + '</dd>'; }).join('') + '</dl>' : '') +
      (list && list.length ? '<div class="hs-sp-tip-l"><b>' + listLabel + '</b><ul>' + list.map(function (x) { return '<li>' + hl(x, state.words) + '</li>'; }).join('') + '</ul></div>' : '') +
      (quote ? '<p class="hs-sp-tip-q">' + hl(quote, state.words) + '</p>' : '');
    var r = row.getBoundingClientRect(), vw = document.documentElement.clientWidth, vh = root.innerHeight;
    var tw = Math.min(340, vw - 16);
    // Phones (sheet): centred in the view; desktop: next to the card
    els.tip.style.left = (state.sheet ? Math.round((vw - tw) / 2) : Math.max(8, Math.min(r.left + 50, vw - 360))) + 'px';
    els.tip.classList.add('is-on');
    var th = els.tip.offsetHeight;
    // Never under the WordPress admin bar (logged-in editors)
    var ab = document.getElementById('wpadminbar'), minTop = ab ? Math.max(8, ab.getBoundingClientRect().bottom + 8) : 8;
    els.tip.style.top = (r.bottom + 8 + th <= vh - 8 ? r.bottom + 8 : Math.max(minTop, r.top - th - 8)) + 'px'; // below, unless no room
  }
  function hideTip() { if (els.tip) { els.tip.classList.remove('is-on'); } if (els) { els.tipPinned = false; } }

  function currentField() { return state.sheet ? els.sheetField : state.field; }
  function syncFields(from) {
    if (state.field && from !== state.field) { state.field.value = from.value; }
    if (els.sheetField && from !== els.sheetField) { els.sheetField.value = from.value; }
    if (state.wrap) {
      var clr = state.wrap.querySelector('.hs-search-clear');
      if (clr) { clr.hidden = !from.value; }
    }
  }

  function isOpen() { return panel && panel.classList.contains('is-open'); }

  function open(field, sheet) {
    buildPanel();
    loadIndex();
    state.sheet = !!sheet;
    if (field) { state.field = field; state.wrap = field.closest('.hs-search'); }
    if (state.sheet) {
      document.body.appendChild(panel);
      panel.classList.add('is-sheet');
      els.sheetField.value = state.field ? state.field.value : els.sheetField.value;
    } else {
      // Desktop: a fixed panel just behind the utility bar (like the mega menus), so it slides down from under it
      panel.classList.remove('is-sheet');
      // Put the panel where the mega menus live: next to the utility bar's section, inside the header layout,
      // so it shares their stacking context and sits behind the bar (z 1001 < 1002) while sliding down.
      var barSection = state.wrap && (state.wrap.closest('.hs-utility-bar') || state.wrap.closest('.et_pb_section'));
      var home = barSection && barSection.parentNode ? barSection.parentNode : document.body;
      if (panel.parentNode !== home) { home.appendChild(panel); }
    }
    document.documentElement.classList.toggle('hs-search-sheet-open', state.sheet);
    if (!panel.classList.contains('is-open')) {
      place();
      void panel.offsetHeight; // commit the closed position first, so the slide-down always animates
    }
    panel.classList.add('is-open');
    if (state.wrap) { state.wrap.classList.add('is-open'); }
    if (state.field) { state.field.setAttribute('aria-expanded', 'true'); }
    place();
    render();
    if (state.sheet) { setTimeout(function () { els.sheetField.focus(); }, 30); }
  }

  function close() {
    if (!panel) { return; }
    hideTip();
    panel.classList.remove('is-open');
    document.documentElement.classList.remove('hs-search-sheet-open');
    if (state.wrap) { state.wrap.classList.remove('is-open'); }
    if (state.field) { state.field.setAttribute('aria-expanded', 'false'); }
    commitIdle();
  }

  /** Desktop: drop the panel under the utility bar, aligned to the bar's row container (same left edge as the social icons). */
  function place() {
    if (!panel || state.sheet || !state.wrap) { return; }
    var vw = document.documentElement.clientWidth;
    var bar = state.wrap.closest('.hs-utility-bar') || state.wrap.closest('.et_pb_section') || state.wrap;
    var row = state.wrap.closest('.et_pb_row') || bar;
    var br = bar.getBoundingClientRect(), rr = row.getBoundingClientRect(), wr = state.wrap.getBoundingClientRect();
    // Below 1920 px the panel spans the row container (aligned left and right); at 1920+ it stays at max 1320 px
    var left = Math.max(8, rr.left), width = vw >= 1920 ? Math.min(1320, rr.width, vw - left - 8) : Math.min(rr.width, vw - left - 8);
    panel.classList.toggle('is-cols', vw >= 1100);
    panel.style.width = width + 'px';
    panel.style.left = left + 'px';
    panel.style.top = Math.max(0, br.bottom) + 'px';
    // Fixed standard height (~30% shorter than the space below the bar); the columns scroll inside it.
    // "Show all documents" makes it as tall as the space allows.
    var space = root.innerHeight - br.bottom - 24;
    var h = Math.max(300, Math.round(space * (state.docsOpen || state.peopleOpen ? 1 : 0.7)));
    panel.style.maxHeight = h + 'px';
    panel.style.height = panel.classList.contains('is-cols') ? h + 'px' : '';
  }

  /* ---------- rendering ---------- */
  /**
   * One card shape for every result: a 38 px icon/photo, a one-line title (with the campus tag),
   * then exactly two lines of text. Only the icon and the text differ by type.
   */
  function rowHTML(r, words, idx) {
    var it = r.it, lead, text = '', ext = false, go = 'Open ↵';
    var lead2 = function (main, rest) { return '<b class="hs-sp-from">' + main + '</b>' + (rest ? ' ' + rest : ''); };
    switch (it.t) {
      case 'person':
        // Three single-line rows: name, job title, then class + campus; the photo is as tall as the three rows
        var photo = '<span class="hs-sp-lead hs-sp-lead--photo">' + (it.img ? '<img src="' + esc(it.img) + '" alt="" loading="lazy" decoding="async">' : ICON.person) +
          '<span class="hs-sp-info hs-sp-info--photo" role="button" aria-label="Details" data-info="1">i</span></span>';
        var staffUrl = (it.u || '/staff-directory/') + ((it.u || '').indexOf('?') > -1 ? '&' : '?') + 'hs_staff=' + encodeURIComponent(it.ti);
        return '<a class="hs-sp-row hs-sp-row--person" role="option" id="hs-sp-opt-' + idx + '" data-i="' + idx + '" data-type="person" data-pos="' + idx + '" data-tip="1" href="' + esc(staffUrl) + '">' + photo +
          '<span class="hs-sp-tx"><span class="hs-sp-ti"><span class="hs-sp-ti-t">' + hl(it.ti, words) + '</span></span>' +
          '<span class="hs-sp-role">' + hl(it.s || '', words) + '</span>' +
          '<span class="hs-sp-tags">' + (it.g ? '<span class="hs-sp-tag hs-sp-tag--class">' + esc(it.g) + '</span>' : '') + campusTag(it.c) + '</span></span>' +
          '<span class="hs-sp-go">View in directory ↵</span></a>';
      case 'event':
        lead = '<span class="hs-sp-lead hs-sp-lead--date">' + esc((it.d || '').slice(8)) + '<small>' + esc(MONTH[+(it.d || '').slice(5, 7) - 1] || '') + '</small></span>';
        text = lead2(esc(fmtDate(it.d, true) + (it.tm ? ' · ' + it.tm : '')), hl([it.loc, it.s].filter(Boolean).join(' · '), words));
        break;
      case 'doc':
        // Documents have their own row, so their cards are taller: title, then page (› section), date, type
        var on = it.on || {};
        return '<a class="hs-sp-row hs-sp-row--doc" role="option" id="hs-sp-opt-' + idx + '" data-i="' + idx + '" data-type="doc" data-pos="' + idx + '" data-tip="1" href="' + esc(it.u) + '" target="_blank" rel="noopener">' +
          '<span class="hs-sp-leadcol"><span class="hs-sp-lead hs-sp-lead--doc">' + ICON.doc + '</span>' + INFOBTN + '</span>' +
          '<span class="hs-sp-tx"><span class="hs-sp-ti"><span class="hs-sp-ti-t">' + hl(it.ti, words) + '</span>' + campusTag(it.c) + '</span>' +
          '<span class="hs-sp-line">' + hl((on.ti || '') + (on.sec ? ' › ' + on.sec : ''), words) + '</span>' +
          '<span class="hs-sp-line hs-sp-ft">' + (on.d ? '<b class="hs-sp-date">' + hl(fmtDate(on.d, true), words) + '</b> · ' : '') + esc(DOCTYPE[it.ft] || 'Document') + '</span></span>' +
          '<span class="hs-sp-go">Open document ↗</span></a>';
      case 'issue':
        lead = '<span class="hs-sp-lead hs-sp-lead--issue">' + ICON.issue + '</span>';
        // If the search words are in one of its articles, say which; otherwise list the issue's topics
        var heads = (it.x || '').split(' · '), hit = '';
        for (var h = 0; h < heads.length && !hit; h++) {
          var hlow = heads[h].toLowerCase();
          if (words.some(function (w) { return hlow.indexOf(w) > -1; })) { hit = heads[h]; }
        }
        var summary = hit ? 'Includes: ' + hl(hit, words)
          : (it.k && it.k.length ? 'In this issue: ' + hl(it.k.slice(0, 6).join(', '), words)
          : (it.x ? 'In this issue: ' + hl(heads.slice(0, 4).join(', '), words) : ''));
        text = lead2(hl([it.wk ? 'Week ' + it.wk : '', fmtDate(it.d, true)].filter(Boolean).join(' · '), words), summary);
        break;
      case 'article':
        lead = '<span class="hs-sp-lead hs-sp-lead--issue">' + ICON.issue + '</span>';
        text = lead2(hl([it.p || (it.k && it.k[0]) || 'Article', fmtDate(it.d, true)].filter(Boolean).join(' · '), words), hl(HSSearchEngine.snippet(it.sn || '', words, 230), words));
        break;
      case 'topic':
        lead = '<span class="hs-sp-lead hs-sp-lead--topic">' + ICON.topic + '</span>';
        text = hl(it.a || (it.b && it.b[0] ? it.b[0][0] : ''), words);
        break;
      default:
        lead = '<span class="hs-sp-lead hs-sp-lead--page">' + ICON.page + '</span>';
        // Description (Yoast or first text); if the search words are only in the page's headings, show those instead.
        var desc = it.s || '', lowerDesc = desc.toLowerCase();
        if (words.length && it.x && !words.some(function (w) { return lowerDesc.indexOf(w) > -1; })) {
          desc = HSSearchEngine.snippet(it.x, words, 230);
        }
        text = (it.bc ? '<b class="hs-sp-from">' + esc(it.bc) + '</b> ' : '') + hl(desc, words);
    }
    var url = it.t === 'topic' ? ((it.b && it.b[0] && it.b[0][1]) || '#') : it.u;
    return '<a class="hs-sp-row" role="option" id="hs-sp-opt-' + idx + '" data-i="' + idx + '" data-type="' + it.t + '" data-pos="' + idx + '"' + (it.t !== 'topic' ? ' data-tip="1"' : '') + ' href="' + esc(url) + '"' + (ext ? ' target="_blank" rel="noopener"' : '') + '>' +
      (it.t !== 'topic' ? '<span class="hs-sp-leadcol">' + lead + INFOBTN + '</span>' : lead) + '<span class="hs-sp-tx"><span class="hs-sp-ti"><span class="hs-sp-ti-t">' + hl(it.ti, words) + '</span>' + (it.t === 'topic' ? '' : campusTag(it.c)) + '</span>' +
      '<span class="hs-sp-su">' + text + '</span></span><span class="hs-sp-go">' + go + '</span></a>';
  }

  function cardHTML(it) {
    var btns = (it.b || []).map(function (b, i) { return '<a class="hs-sp-btn" data-type="topic" data-pos="0" href="' + esc(b[1]) + '">' + esc(b[0]) + ' →</a>'; }).join('');
    if (it.card === 'contact') {
      return '<div class="hs-sp-card hs-sp-card--contact"><div class="hs-sp-card-k">Quick answer</div><div class="hs-sp-card-q">' + esc(it.ti) + '</div><div class="hs-sp-ct">' +
        (it.cc || []).map(function (c) {
          return '<div class="hs-sp-cp"><div class="hs-sp-cp-n">' + esc(c.n) + '</div>' + (c.ad ? '<p class="hs-sp-cp-ad">' + esc(c.ad) + '</p>' : '') +
            '<div class="hs-sp-cp-bt">' + (c.ph || []).map(function (n) { return '<a class="hs-sp-btn" data-type="topic" href="' + esc(tel(n)) + '">' + ICON.phone + esc(n) + '</a>'; }).join('') +
            (c.map ? '<a class="hs-sp-btn hs-sp-btn--o" data-type="topic" href="' + esc(c.map) + '" target="_blank" rel="noopener">' + ICON.pin + 'Google Maps</a>' : '') + '</div>' +
            (c.em ? '<a class="hs-sp-cp-em" data-type="topic" href="mailto:' + esc(c.em) + '">' + esc(c.em) + '</a>' : '') + '</div>';
        }).join('') + '</div>' +
        '<div class="hs-sp-ct-foot"><div class="hs-sp-ct-h">' + (it.hl ? '<b>' + esc(it.hl) + '</b>' : '') + esc(it.h || '') + '</div>' + btns + '</div></div>';
    }
    var note = it.note ? esc(it.note).replace(/([\w.+-]+@[\w-]+\.[\w.-]+)/g, '<a href="mailto:$1" data-type="topic">$1</a>') : '';
    return '<div class="hs-sp-card"><div class="hs-sp-card-k">Quick answer</div><div class="hs-sp-card-q">' + esc(it.ti) + '</div>' +
      (it.a ? '<p>' + esc(it.a) + '</p>' : '') + (note ? '<p class="hs-sp-card-note">' + note + '</p>' : '') + '<div class="hs-sp-card-bt">' + btns + '</div></div>';
  }

  function emptyHTML() {
    var ctx = state.ctx, html = '<div class="hs-sp-empty">';
    var recent = lsGet('hs_search_recent') || [];
    if (recent.length) {
      html += '<div class="hs-sp-h">Recent searches</div><div class="hs-sp-try">' + recent.map(function (q) { return '<button type="button" data-q="' + esc(q) + '">' + esc(q) + '</button>'; }).join('') + '</div>';
    }
    var pins = ctx ? ctx.items.filter(function (it) { return it.t === 'topic' && it.pin; }).slice(0, 6) : [];
    if (pins.length) {
      html += '<div class="hs-sp-h">Quick answers</div><div class="hs-sp-qa">' + pins.map(function (it) {
        var line = it.a || (it.card === 'contact' ? 'Phone numbers, emails, addresses and maps for both campuses.' : '');
        return '<button type="button" data-q="' + esc(it.ti.toLowerCase()) + '"><b>' + esc(it.ti) + '</b>' + (line ? '<span>' + esc(line) + '</span>' : '') + '</button>';
      }).join('') + '</div>';
    }
    return html + '</div>';
  }

  function render() {
    if (!panel || !isOpen()) { return; }
    var f = currentField();
    var raw = f ? f.value : '';
    var t0 = Date.now();
    state.flat = [];
    if (state.failed && !state.ctx) { els.body.innerHTML = '<div class="hs-sp-none">Search is not available right now. <a href="/?s=' + encodeURIComponent(raw) + '">Search the whole site</a></div>'; return; }
    if (!state.ctx) { els.under.innerHTML = ''; els.body.innerHTML = '<div class="hs-sp-loading">Loading search…</div>'; return; }

    var r = HSSearchEngine.search(state.ctx, raw, { campus: state.campus, lang: (document.documentElement.lang || 'en').slice(0, 2) });
    var words = r.words;
    state.words = words;

    // "Understood as" chips
    if (raw.trim()) {
      var chips = (r.parsed.free.length ? '<span class="hs-sp-chip">' + esc(r.parsed.free.join(' ')) + '</span>' : '') +
        r.parsed.chips.map(function (c) {
          if (c.k === 'type') { return '<span class="hs-sp-chip hs-sp-chip--type">' + esc(c.label) + '</span>'; }
          return '<span class="hs-sp-chip' + (c.k === 'campus' ? ' hs-sp-chip--campus' : '') + '">' + esc(c.label) + '<button type="button" data-rm="' + esc(c.words) + '" aria-label="Remove ' + esc(c.label) + '">✕</button></span>';
        }).join('');
      var fix = r.corrected && r.results.length ? '<span class="hs-sp-fix">Showing results for <b>' + esc(r.corrected) + '</b></span>' : '';
      els.under.innerHTML = state.sheet ? fix : 'Understood as: ' + chips + fix; // phones: only the typo hint
    } else {
      els.under.innerHTML = state.sheet ? '' : 'Add a campus or a type to the same box, e.g. <i>science teacher chaofah</i>';
    }

    if (!raw.trim() && !state.campus) { els.body.innerHTML = emptyHTML(); els.live.textContent = ''; return; }

    var results = r.results, html = '', R = function (arr) { return arr.map(function (x) { state.flat.push(x.it); return rowHTML(x, words, state.flat.length - 1); }).join(''); };
    var card = r.topic && r.topic.score >= 45 ? r.topic.it : null;
    if (!results.length && !card) {
      els.body.innerHTML = '<div class="hs-sp-none"><b>No results for “' + esc(raw) + '”</b>Try fewer words, check the spelling, or choose “Both campuses”.' +
        '<div class="hs-sp-try"><button type="button" data-q="fees">fees</button><button type="button" data-q="contact">contact</button><button type="button" data-q="staff directory">staff directory</button></div>' +
        '<a class="hs-sp-all" href="/?s=' + encodeURIComponent(raw) + '">Search the whole site →</a></div>';
      els.live.textContent = 'No results';
      scheduleMiss(raw);
      return;
    }
    if (r.relaxed) { html += '<div class="hs-sp-relaxed">No exact matches, showing close results.</div>'; }
    if (card) { html += cardHTML(card); }
    var otherTopics = (r.topics || []).slice(1).map(function (t) { return { it: t.it, score: t.score }; });
    var groups = HSSearchEngine.group(results);
    if (otherTopics.length) { groups.push({ key: 'topic', items: otherTopics, best: otherTopics[0].score }); }
    var wide = panel.classList.contains('is-cols') && !state.sheet;
    if (!wide) {
      // Same order as the desktop layout: People, Quick answers, Pages, Weekly updates, Articles, Events, Documents
      var LIST_ORDER = ['person', 'topic', 'page', 'issue', 'article', 'event', 'doc'];
      groups.sort(function (a, b) { return LIST_ORDER.indexOf(a.key) - LIST_ORDER.indexOf(b.key); });
      groups.forEach(function (g) {
        // 3 to start, then 10 more per tap ("Show 10 more"), never the whole list at once
        var nShow = 3 + (state.expanded[g.key] || 0), shown = g.items.slice(0, nShow), left = g.items.length - shown.length;
        html += '<div class="hs-sp-grp"><div class="hs-sp-h">' + GROUP_LABEL[g.key] + '<em>' + g.items.length + '</em></div>' + R(shown) +
          (left > 0 ? '<a href="#" class="hs-sp-showmore" data-ex="' + g.key + '">Show ' + Math.min(10, left) + ' more <span>(' + left + ' left)</span></a>' : '') + '</div>';
      });
    } else {
      // Desktop: the same four columns and documents row for every search
      var by = {}; groups.forEach(function (g) { by[g.key] = g.items; });
      var col = function (key, n) {
        var arr = by[key] || []; if (!arr.length) { return ''; }
        var shown = arr.slice(0, n + (state.expanded[key] || 0)), left = arr.length - shown.length;
        return '<div class="hs-sp-grp"><div class="hs-sp-h">' + GROUP_LABEL[key] + '<em>' + arr.length + '</em></div>' + R(shown) +
          (left > 0 ? '<a href="#" class="hs-sp-more" data-ex="' + key + '">Show ' + Math.min(10, left) + ' more (' + left + ' left)</a>' : '') + '</div>';
      };
      var none = function (label) { return '<div class="hs-sp-grp"><div class="hs-sp-h">' + label + '</div><div class="hs-sp-nomatch">No ' + label.toLowerCase() + ' match</div></div>'; };
      // People: a row of cards above the columns, only when there are matches (not aligned to the columns)
      var people = by.person || [];
      if (people.length) {
        var pOpen = state.peopleOpen, shownP = pOpen ? people : people.slice(0, 5);
        html += '<div class="hs-sp-people"><div class="hs-sp-grp"><div class="hs-sp-h">People<em>' + people.length + '</em>' +
          (people.length > 5 ? '<a href="#" data-people="1">' + (pOpen ? 'Show fewer' : 'Show all ' + people.length + ' people') + '</a>' : '') + '</div>' +
          '<div class="hs-sp-pgrid">' + R(shownP) + '</div></div></div>';
      }
      // Pages exist in English and Thai: if both match, a small EN | TH switch filters the Pages column
      var pagesAll = by.page || [], langs = {};
      pagesAll.forEach(function (x) { langs[x.it.lg || 'en'] = 1; });
      var langList = Object.keys(langs).sort(function (a, b) { return a === 'en' ? -1 : b === 'en' ? 1 : a.localeCompare(b); });
      var pageLang = state.pageLang && langs[state.pageLang] ? state.pageLang : (langs.en ? 'en' : langList[0]); // English by default
      if (langList.length > 1) { by.page = pagesAll.filter(function (x) { return (x.it.lg || 'en') === pageLang; }); }
      var langSwitch = langList.length > 1 ? '<span class="hs-sp-lang" role="group" aria-label="Page language">' + langList.map(function (l) {
        return '<button type="button" data-lang="' + l + '" class="' + (l === pageLang ? 'is-on' : '') + '">' + l.toUpperCase() + '</button>';
      }).join('') + '</span>' : '';
      var c1 = (col('page', 8) + col('topic', 4)) || none('Pages');
      if (langSwitch) { c1 = c1.replace('</div>', langSwitch + '</div>'); }
      var c2 = (col('issue', 8) + col('event', 6)) || none('Weekly updates');
      var c3 = col('article', 12) || none('Articles');
      html += '<div class="hs-sp-cols"><div class="hs-sp-col">' + c1 + '</div><div class="hs-sp-col">' + c2 + '</div><div class="hs-sp-col">' + c3 + '</div></div>';
      // Documents: one row (four across); "Show all" makes the whole panel taller instead of scrolling this row
      var docs = by.doc || [];
      if (!docs.length) { html += '<div class="hs-sp-docs">' + none('Documents') + '</div>'; }
      else {
        var showAll = state.docsOpen, shownDocs = showAll ? docs : docs.slice(0, 4);
        html += '<div class="hs-sp-docs"><div class="hs-sp-grp"><div class="hs-sp-h">Documents<em>' + docs.length + '</em>' +
          (docs.length > 4 ? '<a href="#" data-docs="1">' + (showAll ? 'Show fewer' : 'Show all ' + docs.length + ' documents') + '</a>' : '') + '</div>' + R(shownDocs) + '</div></div>';
      }
      panel.classList.toggle('is-tall', (!!state.docsOpen && docs.length > 4) || (!!state.peopleOpen && people.length > 5));
    }
    hideTip();
    els.body.innerHTML = html;
    // Columns that can scroll get a "More below" hint until scrolled to the end
    Array.prototype.forEach.call(els.body.querySelectorAll('.hs-sp-col'), function (col) {
      var hint = document.createElement('div');
      hint.className = 'hs-sp-hint';
      hint.innerHTML = '<span>More below ↓</span>';
      col.appendChild(hint);
      var update = function () { col.classList.toggle('can-scroll', col.scrollHeight - col.scrollTop - col.clientHeight > 6); };
      col.addEventListener('scroll', update, { passive: true });
      setTimeout(update, 0);
    });
    state.active = Math.min(state.active, Math.max(0, state.flat.length - 1));
    if (state.keepActive) {
      state.keepActive = false;
      var firstPage = els.body.querySelector('.hs-sp-cols .hs-sp-col:first-child .hs-sp-row');
      if (firstPage) { state.active = +firstPage.getAttribute('data-i'); }
    }
    markActive(false);
    els.live.textContent = results.length + (results.length === 1 ? ' result' : ' results');
    if (DEBUG) { console.log('[hs-search] "' + raw + '"', results.length, 'results in', Date.now() - t0, 'ms'); }
    scheduleIdle(raw, results.length);
  }

  function markActive(scroll) {
    var rows = els.body.querySelectorAll('.hs-sp-row');
    Array.prototype.forEach.call(rows, function (row) { row.classList.toggle('is-active', +row.getAttribute('data-i') === state.active); });
    var f = currentField();
    if (f) { if (rows.length) { f.setAttribute('aria-activedescendant', 'hs-sp-opt-' + state.active); } else { f.removeAttribute('aria-activedescendant'); } }
    if (scroll) { var a = els.body.querySelector('.hs-sp-row.is-active'); if (a) { a.scrollIntoView({ block: 'nearest' }); } }
  }

  /* ---------- analytics + logging ---------- */
  function okTerm(q) { return q && q.length >= 2 && q.indexOf('@') < 0 && !/\d{6,}/.test(q) && !/https?:|www\./i.test(q); }
  function beacon(q, kind, n) {
    if (!okTerm(q)) { return; }
    try {
      var body = new URLSearchParams(); body.append('action', 'hs_search_log'); body.append('q', q.slice(0, 60)); body.append('kind', kind); body.append('n', String(n || 0));
      if (navigator.sendBeacon) { navigator.sendBeacon(AJAX_URL, body); } else { fetch(AJAX_URL, { method: 'POST', body: body, keepalive: true }); }
    } catch (e) { /* never break search */ }
  }
  function push(o) { try { (root.dataLayer = root.dataLayer || []).push(o); } catch (e) { /* no GTM */ } }
  function commit(how, type, pos) {
    var q = (currentField() || {}).value || '';
    q = q.trim().toLowerCase();
    if (!q) { return; }
    if (state.committed !== q) {
      state.committed = q;
      push({ event: 'hs_search', search_term: okTerm(q) ? q : '(filtered)', hs_results: state.flat.length, hs_campus: state.campus || 'both' });
      beacon(q, 'commit', state.flat.length);
      var recent = (lsGet('hs_search_recent') || []).filter(function (x) { return x !== q; });
      recent.unshift(q); lsSet('hs_search_recent', recent.slice(0, 5));
    }
    if (how === 'click') { push({ event: 'hs_search_click', search_term: okTerm(q) ? q : '(filtered)', hs_result_type: type, hs_result_position: pos + 1 }); }
  }
  function scheduleIdle(q, n) {
    clearTimeout(state.idleT);
    state.idleT = setTimeout(function () { if (n && q.trim().length >= 3) { commit('idle'); } }, 2500);
  }
  function commitIdle() { clearTimeout(state.idleT); }
  function scheduleMiss(q) {
    clearTimeout(state.idleT);
    state.idleT = setTimeout(function () {
      q = q.trim().toLowerCase();
      if (q.length >= 3 && state.missed !== q) { state.missed = q; push({ event: 'hs_search_no_results', search_term: okTerm(q) ? q : '(filtered)' }); beacon(q, 'miss', 0); }
    }, 1200);
  }

  /* ---------- events ---------- */
  function onInput(e) {
    syncFields(e.target);
    state.active = 0; state.expanded = {}; state.docsOpen = false; state.peopleOpen = false; state.pageLang = '';
    if (!isOpen()) { open(state.field, state.sheet); }
    render();
  }
  function onKey(e) {
    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && els.tip) { els.tipPinned = false; els.tipRow = null; hideTip(); }
    if (e.key === 'Escape' && els.tip && els.tip.classList.contains('is-on')) { e.preventDefault(); els.tipPinned = false; els.tipRow = null; hideTip(); return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); if (!isOpen()) { open(state.field, state.sheet); } state.active = Math.min(state.flat.length - 1, state.active + 1); markActive(true); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); state.active = Math.max(0, state.active - 1); markActive(true); }
    else if (e.key === 'ArrowRight' && e.target.selectionStart === e.target.value.length && els.body) {
      var arow = els.body.querySelector('.hs-sp-row.is-active[data-tip]');
      if (arow) {
        e.preventDefault();
        if (els.tipRow === arow && els.tip.classList.contains('is-on')) { els.tipRow = null; els.tipPinned = false; hideTip(); }
        else { els.tipRow = arow; els.tipPinned = true; showTip(arow); }
      }
    }
    else if (e.key === 'Enter') {
      e.preventDefault();
      var row = els.body && els.body.querySelector('.hs-sp-row.is-active');
      if (!row) { var card = els.body && els.body.querySelector('.hs-sp-card a'); if (card) { commit('click', 'topic', 0); root.location.href = card.href; } return; }
      commit('click', row.getAttribute('data-type'), +row.getAttribute('data-i'));
      if (row.target === '_blank') { root.open(row.href, '_blank', 'noopener'); } else { root.location.href = row.href; }
    } else if (e.key === 'Escape') {
      if (e.target.value) { e.target.value = ''; syncFields(e.target); render(); }
      else { close(); if (!state.sheet) { e.target.blur(); } }
    }
  }
  function setQuery(q) {
    var f = currentField(); if (!f) { return; }
    f.value = q; syncFields(f); state.active = 0; state.expanded = {}; render(); f.focus();
  }

  /**
   * Staff deep link: /staff-directory/?hs_staff=<name> uses the directory's OWN search (staff-directory.js is not
   * changed): type the name into its search box, then pick the matching suggestion. The directory then switches
   * campus tab, opens the section, scrolls to the card and pulses it, exactly as when a visitor searches there.
   */
  function staffDeepLink() {
    var m = root.location.search.match(/[?&]hs_staff=([^&]+)/);
    if (!m) { return; }
    var name = decodeURIComponent(m[1].replace(/\+/g, ' '));
    var tries = 0;
    (function attempt() {
      var input = document.getElementById('staff-search-new');
      var ready = input && document.querySelector('.hs-staff-card');
      if (!ready) { if (++tries < 60) { setTimeout(attempt, 150); } return; }
      input.value = name;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      setTimeout(function () {
        var items = document.querySelectorAll('#hs-search-dropdown .hs-suggest-item');
        var pick = null;
        Array.prototype.forEach.call(items, function (it) { if (!pick && it.getAttribute('data-exact-name') === name) { pick = it; } });
        if (!pick && items.length === 1) { pick = items[0]; }
        if (pick) { pick.click(); }
        try { root.history.replaceState(null, '', root.location.pathname + root.location.search.replace(/([?&])hs_staff=[^&]*&?/, '$1').replace(/[?&]$/, '') + root.location.hash); } catch (e) { /* old browsers */ }
      }, 60);
    })();
  }

  function init() {
    staffDeepLink();
    var fields = document.querySelectorAll('.hs-search .hs-search-field');
    Array.prototype.forEach.call(fields, function (f) {
      var wrap = f.closest('.hs-search');
      var warm = function () { loadIndex(); };
      wrap.addEventListener('pointerenter', warm, { once: true });
      f.addEventListener('focus', function () { open(f, false); });
      f.addEventListener('input', function (e) { state.field = f; state.wrap = wrap; onInput(e); });
      f.addEventListener('keydown', onKey);
      var form = wrap.querySelector('form');
      if (form) { form.addEventListener('submit', function (e) { if (state.ctx) { e.preventDefault(); } }); }
      var clr = wrap.querySelector('.hs-search-clear');
      if (clr) { clr.addEventListener('click', function () { f.value = ''; syncFields(f); render(); f.focus(); }); }
    });

    // mobile magnifier (delegated, so cloned drawer copies work too)
    document.addEventListener('click', function (e) {
      var t = e.target.closest && e.target.closest('.hs-search-trigger');
      if (t) { e.preventDefault(); open(document.querySelector('.hs-search .hs-search-field'), true); return; }
      // A click on a button that re-rendered the panel (recent search, See all) has a detached target: not an outside click.
      if (isOpen() && !state.sheet && e.target.isConnected && !e.target.closest('.hs-search') && !e.target.closest('.hs-search-panel') && !e.target.closest('.hs-sp-tip')) { close(); }
    });
    document.addEventListener('keydown', function (e) {
      var tag = (document.activeElement && document.activeElement.tagName) || '';
      var typing = /INPUT|TEXTAREA|SELECT/.test(tag) || (document.activeElement && document.activeElement.isContentEditable);
      if ((e.key === '/' && !typing) || ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K'))) {
        var f = document.querySelector('.hs-search .hs-search-field');
        e.preventDefault();
        if (f && f.offsetParent !== null) { f.focus(); } else { open(f, true); }
      } else if (e.key === 'Escape' && isOpen() && state.sheet && document.activeElement !== els.sheetField) { close(); }
    });
    root.addEventListener('resize', function () { if (isOpen()) { place(); render(); } });
    root.addEventListener('scroll', function () { if (isOpen() && !state.sheet) { place(); } }, { passive: true });

    // ?q= opens the panel pre-filled
    var m = root.location.search.match(/[?&]q=([^&]+)/);
    if (m) {
      var f = document.querySelector('.hs-search .hs-search-field');
      var c = root.location.search.match(/[?&]campus=(chaofah|cherngtalay)/);
      if (c) { state.campus = c[1]; }
      var val = decodeURIComponent(m[1].replace(/\+/g, ' '));
      if (f) { f.value = val; if (f.offsetParent !== null) { f.focus(); } else { open(f, true); } }
      if (c && panel) { Array.prototype.forEach.call(els.seg.children, function (x) { x.classList.toggle('is-on', x.getAttribute('data-c') === state.campus); }); }
    }
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(typeof window !== 'undefined' ? window : this);
