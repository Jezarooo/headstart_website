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
      f.push([[it.s, it.sec, it.p, it.bc, on.ti, on.sec, it.loc, it.wk ? 'week ' + it.wk : ''].join(' '), W.sub]);
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
    function matchToken(ctx, tok, allowPrefix) {
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
          if (Math.abs(t.length - tok.length) <= 1 && dl1(tok, t)) { add(t, 0.4); }
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
      var res = [], relaxed = false;
      var topic = null;

      // Quick answers by phrase (before stopwords are removed)
      ctx.topics.forEach(function (tp) {
        var it = ctx.items[tp.idx];
        var s = topicScore(ctx, it, p.norm);
        if (s >= 45 && (!topic || s > topic.score)) { topic = { it: it, score: s }; }
      });

      if (!words.length && !p.optional.length && !campus && !p.range && !p.week) {
        return { parsed: p, results: [], topic: topic, relaxed: false, words: [] };
      }

      var run = function (mode) {
        var scores = null;
        var lists = words.map(function (w, i) { return matchToken(ctx, w, i === words.length - 1 || w.length >= 3); });
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
          if (p.optional.length && words.length) {
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
      return { parsed: p, results: res, topic: topic, relaxed: relaxed, words: words.concat(p.optional) };
    }

    var ORDER = ['person', 'page', 'topic', 'issue', 'event', 'doc'];
    /** Group results by display group; groups ordered by their best score. */
    function group(results) {
      var g = {};
      results.forEach(function (r) {
        var key = r.it.t === 'article' ? 'issue' : r.it.t;
        (g[key] || (g[key] = { key: key, items: [], best: r.score })).items.push(r);
      });
      return Object.keys(g).map(function (k) { return g[k]; }).sort(function (a, b) { return b.best - a.best || ORDER.indexOf(a.key) - ORDER.indexOf(b.key); });
    }

    /** Short text window around the first matching word. */
    function snippet(text, words, len) {
      text = String(text || '');
      len = len || 150;
      if (text.length <= len) { return text; }
      var lower = text.toLowerCase(), at = -1;
      (words || []).forEach(function (w) { var j = lower.indexOf(w); if (j > -1 && (at < 0 || j < at)) { at = j; } });
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

  /* UI: added in the next build step. */
})(typeof window !== 'undefined' ? window : this);
