/**
 * =============================================================================
 * HEADSTART ALUMNI STORIES (V2, 14 Oct 2026)
 * =============================================================================
 * PURPOSE: Turns the Divi "Alumni stories" Groups into the story slider layout.
 * DIVI STRUCTURE (built and edited in Divi 5, no code):
 *   Group .hs-alumni-stories                (wrapper)
 *     Group .hs-alumni-story                (one per story, in card order)
 *       Gallery .hs-story-slides            Instagram slides, 4:5, in order
 *       Image   .hs-story-card              plain photo for the story card
 *       Text    .hs-story-text              H3 name, 1st paragraph = meta line,
 *                                           further paragraphs = blurb, a link = Instagram
 *     Text    .hs-story-invite              optional "Your story next?" tile: heading,
 *                                           one paragraph, a link (where the tile goes;
 *                                           its text, e.g. an email address, is shown)
 * OUTPUT: slider (arrows, dots, swipe, keyboard) + joined navy blurb panel +
 *         cards for the OTHER stories (+ the invite tile): a 2x2 grid when there are
 *         4 tiles or fewer, otherwise a two-row strip with its own controls.
 *         Below 981px: stacked, with a one-row card strip and its own controls.
 * Without this script the Groups stay visible as plain stacked content.
 * Not run in the Visual Builder, so editors see the plain Groups.
 * =============================================================================
 */
(function () {
    'use strict';

    var BREAKPOINT = 981;
    var CARD_GAP = 10;

    function inBuilder() {
        return /[?&]et_fb=1/.test(window.location.search) ||
            document.body.classList.contains('et-fb') ||
            document.body.classList.contains('et-fb-app');
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return '&#' + c.charCodeAt(0) + ';';
        });
    }

    var ICON_PREV = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"></path></svg>';
    var ICON_NEXT = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"></path></svg>';

    /* ---------- Read one story Group ---------- */
    function readStory(group) {
        var slides = [];
        var items = group.querySelectorAll('.hs-story-slides .et_pb_gallery_item');
        Array.prototype.forEach.call(items, function (item) {
            var a = item.querySelector('a[href]');
            var img = item.querySelector('img');
            var src = a ? a.getAttribute('href') : (img ? img.getAttribute('src') : '');
            if (!src) return;
            slides.push({
                src: src,
                srcset: img && img.dataset ? (img.dataset.hsSrcset || '') : '',
                alt: img ? (img.getAttribute('alt') || '') : ''
            });
        });

        var cardImg = group.querySelector('.hs-story-card img');
        var card = null;
        if (cardImg && cardImg.getAttribute('src')) {
            card = {
                src: cardImg.getAttribute('src'),
                srcset: cardImg.getAttribute('srcset') || (cardImg.dataset ? cardImg.dataset.hsSrcset || '' : '')
            };
        }

        var text = group.querySelector('.hs-story-text .et_pb_text_inner') || group.querySelector('.hs-story-text');
        var name = '', meta = '', paras = [], link = '';
        if (text) {
            var h = text.querySelector('h1, h2, h3, h4, h5, h6');
            name = h ? h.textContent.trim() : '';
            var a2 = text.querySelector('a[href]');
            link = a2 ? a2.getAttribute('href') : '';
            var ps = Array.prototype.filter.call(text.querySelectorAll('p'), function (p) {
                return p.textContent.trim() !== '';
            });
            ps.forEach(function (p, k) {
                var onlyLink = a2 && p.contains(a2) && p.textContent.trim() === a2.textContent.trim();
                if (onlyLink) return;
                if (k === 0) meta = p.textContent.trim();
                else paras.push(p.textContent.trim());
            });
        }

        if (!slides.length && card) slides.push({ src: card.src, srcset: card.srcset, alt: name });
        if (!card && slides.length) card = { src: slides[0].src, srcset: slides[0].srcset };
        if (!slides.length) return null;

        return {
            el: group,
            name: name || 'Alumni story',
            meta: meta,
            year: meta.split('·')[0].trim(),
            paras: paras,
            link: link,
            slides: slides,
            card: card
        };
    }

    /* ---------- Read the optional invite tile ---------- */
    function readInvite(wrapper) {
        var el = Array.prototype.filter.call(wrapper.querySelectorAll('.hs-story-invite'), function (t) {
            return t.closest('.hs-alumni-stories') === wrapper && !t.closest('.hs-alumni-story');
        })[0];
        if (!el) return null;
        var box = el.querySelector('.et_pb_text_inner') || el;
        var h = box.querySelector('h1, h2, h3, h4, h5, h6');
        var a = box.querySelector('a[href]');
        var line = '';
        Array.prototype.some.call(box.querySelectorAll('p'), function (p) {
            var t = p.textContent.trim();
            if (!t || (a && p.contains(a) && t === a.textContent.trim())) return false;
            line = t;
            return true;
        });
        var heading = h ? h.textContent.trim() : '';
        if (!heading && !line) return null;
        var cut = line.search(/[.!?]\s/);
        return {
            el: el,
            heading: heading,
            first: cut > -1 ? line.slice(0, cut + 1) : line,
            rest: cut > -1 ? line.slice(cut + 2) : '',
            href: a ? a.getAttribute('href') : '',
            linkText: a ? a.textContent.trim() : ''
        };
    }

    /* Link text for the invite tile: an email breaks only after the @ (and, on phones, before the ending) */
    function linkHtml(t) {
        var at = t.indexOf('@');
        if (at < 0) return esc(t);
        var host = esc(t.slice(at + 1)).replace(/\.([a-z]{2,6})$/i, '<wbr>.$1');
        return '<span>' + esc(t.slice(0, at + 1)) + '</span><span>' + host + '</span>';
    }

    var ICON_GO = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>';

    /* ---------- Build the widget ---------- */
    function build(wrapper) {
        if (wrapper.dataset.hsAsReady) return;
        var groups = Array.prototype.filter.call(wrapper.querySelectorAll('.hs-alumni-story'), function (g) {
            return g.closest('.hs-alumni-stories') === wrapper;
        });
        var stories = groups.map(readStory).filter(Boolean);
        if (!stories.length) return;
        var invite = readInvite(wrapper);
        wrapper.dataset.hsAsReady = '1';

        var root = document.createElement('div');
        root.className = 'hs-as';
        root.innerHTML =
            '<div class="hs-as-feature">' +
                '<div class="hs-as-slider" tabindex="0" role="region" aria-roledescription="carousel" aria-label="Story slides">' +
                    '<div class="hs-as-track"></div>' +
                    '<button type="button" class="hs-as-arrow hs-as-prev" aria-label="Previous slide">' + ICON_PREV + '</button>' +
                    '<button type="button" class="hs-as-arrow hs-as-next" aria-label="Next slide">' + ICON_NEXT + '</button>' +
                    '<button type="button" class="hs-as-nextstory">Next story</button>' +
                    '<div class="hs-as-dots"></div>' +
                '</div>' +
                '<div class="hs-as-panel" aria-live="polite">' +
                    '<p class="hs-as-kicker">Alumni story &middot; <span class="hs-as-count"></span></p>' +
                    '<h3 class="hs-as-name"></h3>' +
                    '<p class="hs-as-meta"></p>' +
                    '<span class="hs-as-rule" aria-hidden="true"></span>' +
                    '<div class="hs-as-blurb-wrap">' +
                        '<div class="hs-as-blurb" tabindex="0"></div>' +
                        '<div class="hs-as-blurb-fade" aria-hidden="true"></div>' +
                        '<button type="button" class="hs-as-blurb-more" aria-label="Scroll down to read more"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"></path></svg></button>' +
                    '</div>' +
                    '<a class="hs-as-ig" target="_blank" rel="noopener">View on Instagram</a>' +
                '</div>' +
            '</div>' +
            '<div class="hs-as-more">' +
                '<div class="hs-as-more-head">' +
                    '<p class="hs-as-more-title">More stories</p>' +
                    '<button type="button" class="hs-as-cbtn hs-as-cprev" aria-label="Show earlier stories">' + ICON_PREV + '</button>' +
                    '<button type="button" class="hs-as-cbtn hs-as-cnext" aria-label="Show more stories">' + ICON_NEXT + '</button>' +
                '</div>' +
                '<div class="hs-as-viewport"><div class="hs-as-strip"></div><div class="hs-as-fade" aria-hidden="true"></div></div>' +
                '<div class="hs-as-bar" aria-hidden="true"><div class="hs-as-thumb"></div></div>' +
            '</div>';

        var q = function (sel) { return root.querySelector(sel); };
        var track = q('.hs-as-track'), dotsBox = q('.hs-as-dots'), strip = q('.hs-as-strip');
        var slider = q('.hs-as-slider'), viewport = q('.hs-as-viewport');

        var tiles = [];
        function renderTiles() {
            var html = stories.map(function (st, k) {
                if (k === s) return '';
                return '<button type="button" class="hs-as-tile hs-as-card" data-k="' + k + '" aria-label="Show the story of ' + esc(st.name) + '">' +
                    '<img src="' + esc(st.card.src) + '"' + (st.card.srcset ? ' srcset="' + esc(st.card.srcset) + '" sizes="260px"' : '') +
                    ' alt="" loading="lazy" decoding="async" draggable="false">' +
                    '<span class="hs-as-card-label"><span class="hs-as-card-name">' + esc(st.name) + '</span>' +
                    (st.year ? '<span class="hs-as-card-year">' + esc(st.year) + '</span>' : '') + '</span>' +
                    '</button>';
            }).join('');
            if (invite) {
                var tag = invite.href ? 'a' : 'div';
                html += '<' + tag + ' class="hs-as-tile hs-as-invite' + (invite.linkText ? ' has-link' : '') + '"' + (invite.href ? ' href="' + esc(invite.href) + '" draggable="false"' : '') + '>' +
                    '<span class="hs-as-invite-top"><span class="hs-as-quote" aria-hidden="true">&ldquo;</span>' +
                    (invite.href ? '<span class="hs-as-invite-go" aria-hidden="true">' + ICON_GO + '</span>' : '') + '</span>' +
                    (invite.heading ? '<span class="hs-as-invite-h">' + esc(invite.heading) + '</span>' : '') +
                    (invite.first ? '<span class="hs-as-invite-p">' + esc(invite.first) +
                        (invite.rest ? ' <span class="hs-as-invite-rest">' + esc(invite.rest) + '</span>' : '') + '</span>' : '') +
                    (invite.linkText ? '<span class="hs-as-invite-link">' + linkHtml(invite.linkText) + '</span>' : '') +
                    '</' + tag + '>';
            }
            strip.innerHTML = html;
            tiles = Array.prototype.slice.call(strip.children);
        }

        var s = 0, i = 0, c = 0;
        var mq = window.matchMedia('(max-width: ' + (BREAKPOINT - 1) + 'px)');

        function renderStory() {
            var st = stories[s];
            track.style.transition = 'none';
            track.innerHTML = st.slides.map(function (sl, k) {
                return '<div class="hs-as-slide" role="group" aria-roledescription="slide" aria-label="' + (k + 1) + ' of ' + st.slides.length + '">' +
                    '<img src="' + esc(sl.src) + '"' + (sl.srcset ? ' srcset="' + esc(sl.srcset) + '" sizes="(max-width: 980px) 100vw, 440px"' : '') +
                    ' alt="' + esc(sl.alt || (st.name + ', slide ' + (k + 1))) + '"' + (k > 1 ? ' loading="lazy"' : '') + ' decoding="async" draggable="false">' +
                    '</div>';
            }).join('');
            dotsBox.innerHTML = st.slides.map(function (_, k) {
                return '<button type="button" class="hs-as-dot" data-i="' + k + '" aria-label="Go to slide ' + (k + 1) + '"></button>';
            }).join('');
            q('.hs-as-count').textContent = (s + 1) + ' of ' + stories.length;
            q('.hs-as-name').textContent = st.name;
            var metaEl = q('.hs-as-meta');
            metaEl.textContent = st.meta;
            metaEl.hidden = !st.meta;
            q('.hs-as-blurb').innerHTML = st.paras.map(function (p) { return '<p>' + esc(p) + '</p>'; }).join('');
            q('.hs-as-blurb').scrollTop = 0;
            updateBlurbHint();
            var ig = q('.hs-as-ig');
            if (st.link) { ig.href = st.link; ig.hidden = false; } else { ig.hidden = true; }
            renderTiles();
            i = 0;
            renderSlide();
            void track.offsetWidth;
            track.style.transition = '';
        }

        /* Blurb: scroll only when the text is longer than the panel, with a fade + "More" hint */
        function updateBlurbHint() {
            var b = q('.hs-as-blurb'), wrap = q('.hs-as-blurb-wrap');
            var overflow = b.scrollHeight > b.clientHeight + 2;
            var atEnd = b.scrollTop + b.clientHeight >= b.scrollHeight - 4;
            wrap.classList.toggle('is-overflow', overflow);
            wrap.classList.toggle('is-end', !overflow || atEnd);
            wrap.classList.toggle('is-scrolled', b.scrollTop > 4);
        }

        function renderSlide() {
            var n = stories[s].slides.length;
            track.style.transform = 'translateX(' + (-100 * i) + '%)';
            Array.prototype.forEach.call(dotsBox.children, function (d, k) {
                d.classList.toggle('is-active', k === i);
                if (k === i) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current');
            });
            q('.hs-as-prev').hidden = i === 0;
            q('.hs-as-next').hidden = i >= n - 1;
            q('.hs-as-nextstory').hidden = !(i >= n - 1 && stories.length > 1);
        }

        function goSlide(k) {
            var n = stories[s].slides.length;
            i = Math.max(0, Math.min(n - 1, k));
            renderSlide();
        }

        function pickStory(k) {
            s = (k + stories.length) % stories.length;
            c = 0;
            renderStory();
            renderStrip();
        }

        /* Tiles: 2x2 grid (desktop, 4 tiles or fewer), else a strip moved by column.
           Desktop strip: two rows, one column per step. Mobile: one row, one card per step. */
        function stepPx() {
            return tiles.length ? tiles[0].getBoundingClientRect().width + CARD_GAP : 1;
        }
        function renderStrip() {
            var mobile = mq.matches;
            var grid = !mobile && tiles.length <= 4;
            root.classList.toggle('hs-as-grid', grid);
            if (grid) {
                strip.style.transform = '';
                root.classList.remove('hs-as-has-more');
                return;
            }
            var step = stepPx();
            var perCol = mobile ? 1 : 2;
            var total = Math.ceil(tiles.length / perCol);
            var maxOff = Math.max(0, total * step - CARD_GAP - viewport.clientWidth);
            var m = Math.ceil(maxOff / step - 0.01);
            c = Math.max(0, Math.min(m, c));
            strip.style.transform = 'translateX(' + (-Math.min(c * step, maxOff)) + 'px)';
            q('.hs-as-cprev').disabled = c === 0;
            q('.hs-as-cnext').disabled = c >= m;
            q('.hs-as-fade').hidden = c >= m;
            root.classList.toggle('hs-as-has-more', m > 0);
            var visibleW = viewport.clientWidth;
            var fullW = Math.max(visibleW, total * step - CARD_GAP);
            var w = Math.min(100, (visibleW / fullW) * 100);
            var thumb = q('.hs-as-thumb');
            thumb.style.width = w + '%';
            thumb.style.left = (m === 0 ? 0 : (Math.min(c * step, maxOff) / maxOff) * (100 - w)) + '%';
        }
        function goCol(k) { c = k; renderStrip(); }

        /* Events */
        q('.hs-as-prev').addEventListener('click', function () { goSlide(i - 1); });
        q('.hs-as-next').addEventListener('click', function () { goSlide(i + 1); });
        q('.hs-as-nextstory').addEventListener('click', function () { pickStory(s + 1); });
        dotsBox.addEventListener('click', function (e) {
            var d = e.target.closest('.hs-as-dot');
            if (d) goSlide(+d.dataset.i);
        });
        strip.addEventListener('click', function (e) {
            if (strip.dataset.dragged) { e.preventDefault(); return; }
            var card = e.target.closest('.hs-as-card');
            if (card) pickStory(+card.dataset.k);
        });
        q('.hs-as-blurb').addEventListener('scroll', updateBlurbHint, { passive: true });
        q('.hs-as-blurb-more').addEventListener('click', function () {
            var b = q('.hs-as-blurb');
            b.scrollBy({ top: Math.round(b.clientHeight * 0.75), behavior: 'smooth' });
        });
        q('.hs-as-cprev').addEventListener('click', function () { goCol(c - 1); });
        q('.hs-as-cnext').addEventListener('click', function () { goCol(c + 1); });
        slider.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { e.preventDefault(); goSlide(i - 1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); goSlide(i + 1); }
        });

        function swipe(el, onLeft, onRight, markDrag) {
            var x0 = null;
            el.addEventListener('pointerdown', function (e) {
                if (e.pointerType === 'mouse' && e.button !== 0) return;
                x0 = e.clientX;
                if (markDrag) delete el.dataset.dragged;
            });
            el.addEventListener('pointerup', function (e) {
                if (x0 === null) return;
                var dx = e.clientX - x0;
                x0 = null;
                if (Math.abs(dx) < 40) return;
                if (markDrag) {
                    el.dataset.dragged = '1';
                    setTimeout(function () { delete el.dataset.dragged; }, 0);
                }
                if (dx < 0) onLeft(); else onRight();
            });
            el.addEventListener('pointercancel', function () { x0 = null; });
        }
        swipe(slider, function () { goSlide(i + 1); }, function () { goSlide(i - 1); }, false);
        swipe(strip, function () { goCol(c + 1); }, function () { goCol(c - 1); }, true);

        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () { renderStrip(); updateBlurbHint(); }, 120);
        });
        if (mq.addEventListener) mq.addEventListener('change', function () { renderStrip(); updateBlurbHint(); });

        /* Mount: widget first, original Groups hidden (kept in the page) */
        wrapper.insertBefore(root, wrapper.firstChild);
        groups.forEach(function (g) { g.hidden = true; g.setAttribute('aria-hidden', 'true'); });
        if (invite) { invite.el.hidden = true; invite.el.setAttribute('aria-hidden', 'true'); }
        wrapper.classList.add('hs-as-ready');
        renderStory();
        renderStrip();
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { renderStrip(); updateBlurbHint(); });
    }

    function init() {
        if (inBuilder()) return;
        Array.prototype.forEach.call(document.querySelectorAll('.hs-alumni-stories'), build);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
