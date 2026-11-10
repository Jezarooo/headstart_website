<?php

/**
 * Posts--Card-Preview
 */
/**
 * =============================================================================
 * HEADSTART POSTS CARD PREVIEW (admin only)
 * =============================================================================
 * PURPOSE: One shared, labelled preview of a Post's slider card and the top of its
 *          Post page, used by the Post edit screen (Posts--Register-Fields) and the
 *          weekly update Articles → Posts panel (Weekly-Update--Articles-To-Posts).
 * HOW:     Prints window.hsCardPreview and its CSS in the head of those screens.
 *          hsCardPreview.create() returns { node, update(values), redraw() }.
 *          values: { title, section, badgeText, campusNames[], location,
 *                    publishDate 'Y-m-d', eventStart 'Y-m-d', eventEnd 'Y-m-d',
 *                    image (URL), tags[] (raw names, prefixes kept) }
 *          The rules match the PHP: hs_final_section_chip / hs_final_location_chip,
 *          hs_card_date_label() and hs_date_label_for_post().
 * DEPENDENCIES: none. Saves nothing; no front-end output.
 * =============================================================================
 */

/** True on the screens that use the preview: editing a Post or a weekly update. */
function hs_card_preview_screen() {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}
	$screen = get_current_screen();
	return $screen && 'post' === $screen->base && in_array( $screen->post_type, array( 'post', 'weekly_update' ), true );
}

add_action( 'admin_enqueue_scripts', function () {
	if ( hs_card_preview_screen() ) {
		// The site's own heading and body fonts (the same Google Fonts the front end loads).
		wp_enqueue_style( 'hs-card-preview-fonts', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Open+Sans:wght@400;700&display=swap', array(), null );
	}
} );

// In the head, so it exists before either screen's own script runs.
add_action( 'admin_head', function () {
	if ( hs_card_preview_screen() ) {
		hs_card_preview_print_assets();
	}
} );

/**
 * The photos in a Post's own content, for the image picker: Divi 5 image modules, then
 * gallery modules, then plain-HTML <img class="wp-image-ID"> (archive Posts), without repeats.
 * Returns [ { id, url (medium), thumb } ].
 */
function hs_card_preview_content_images( $content ) {
	$inline  = array();
	$gallery = array();
	$walk    = function ( $blocks ) use ( &$walk, &$inline, &$gallery ) {
		foreach ( $blocks as $b ) {
			if ( 'divi/image' === $b['blockName'] ) {
				$v  = $b['attrs']['image']['innerContent']['desktop']['value'] ?? array();
				$id = (int) ( $v['id'] ?? 0 );
				if ( ! $id && ! empty( $v['src'] ) ) {
					$id = (int) attachment_url_to_postid( $v['src'] );
				}
				if ( $id ) {
					$inline[] = $id;
				}
			}
			if ( 'divi/gallery' === $b['blockName'] ) {
				$gallery = array_merge( $gallery, wp_parse_id_list( $b['attrs']['image']['advanced']['galleryIds']['desktop']['value'] ?? '' ) );
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				$walk( $b['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( (string) $content ) );
	$html = array();
	if ( preg_match_all( '/<img[^>]+class="[^"]*\bwp-image-(\d+)\b/i', (string) $content, $m ) ) {
		$html = array_map( 'intval', $m[1] );
	}
	$out = array();
	foreach ( array_values( array_unique( array_merge( $inline, $gallery, $html ) ) ) as $id ) {
		$url = $id && wp_attachment_is_image( $id ) ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '';
		if ( $url ) {
			$out[] = array( 'id' => $id, 'url' => $url, 'thumb' => (string) wp_get_attachment_image_url( $id, 'thumbnail' ) );
		}
	}
	return $out;
}

/**
 * How many <img> tags in the content point at files that aren't in the Media Library
 * (e.g. imported archive Posts' legacy-images). They can't be used as a featured or portrait image.
 */
function hs_card_preview_unlisted_image_count( $content ) {
	$count = 0;
	if ( preg_match_all( '/<img\b[^>]*>/i', (string) $content, $m ) ) {
		foreach ( $m[0] as $tag ) {
			if ( preg_match( '/\bwp-image-\d+\b/', $tag ) ) {
				continue;
			}
			if ( preg_match( '/\ssrc="([^"]+)"/i', $tag, $src ) && ! attachment_url_to_postid( $src[1] ) ) {
				$count++;
			}
		}
	}
	return $count;
}

function hs_card_preview_print_assets() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
<style id="hs-card-preview-css">
#wpbody .hs-cpv-stage { position: relative; display: grid; grid-template-columns: 216px minmax(0, 1fr); column-gap: 44px; align-items: start; align-content: start; }
#wpbody .hs-cpv-labels { position: relative; grid-column: 2; grid-row: 2; align-self: stretch; }
#wpbody .hs-cpv-striplabels { grid-column: 1 / -1; grid-row: 5; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 22px; }
#wpbody .hs-cpv-striplabels .hs-cpv-label { position: static; }
/* The card and the strip are shown at 80% with zoom, so every part keeps its proportions. */
#wpbody .hs-cpv-cardbox, #wpbody .hs-cpv-stripbox { zoom: .8; }
#wpbody .hs-cpv-cardbox { grid-column: 1; width: 270px; }
#wpbody .hs-cpv-stripbox { grid-column: 1 / -1; justify-self: start; }
#wpbody .hs-cpv-cardbox { grid-row: 2; padding-top: 18px; }
#wpbody .hs-cpv-stripbox { grid-row: 4; }
#wpbody .hs-cpv-cap { grid-column: 1 / -1; justify-self: end; position: relative; z-index: 2; display: inline-flex; align-items: center; white-space: nowrap; margin: 0 0 8px; padding: 3px 10px; border-radius: 999px; background: #f0f0f1; color: #50575e; font-size: 11px; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; }
#wpbody .hs-cpv-cap:first-child { grid-row: 1; }
#wpbody .hs-cpv-cap:nth-child(3) { grid-row: 3; margin-top: 18px; }
#wpbody .hs-cpv-lines { position: absolute; left: 0; top: 0; pointer-events: none; overflow: visible; }
#wpbody .hs-cpv-lines polyline { fill: none; stroke: #2271b1; stroke-width: 1; }
#wpbody .hs-cpv-lines circle { fill: #2271b1; }
#wpbody .hs-cpv-label { position: absolute; left: 0; right: 0; padding: 3px 0 3px 8px; border-left: 2px solid #2271b1; background: #fff; font-size: 12px; line-height: 1.35; }
#wpbody .hs-cpv-label strong { display: block; color: #1d2327; font-size: 12px; }
#wpbody .hs-cpv-label span { display: block; color: #3c434a; font-size: 11.5px; line-height: 1.4; }

/* Slider card: copied from core.css (.hs-story-*, .hs-shortcode-slider-wrapper) and the slider snippet's inline styles. */
#wpbody .hs-cpv { --hs-dark: #003057; --hs-gold: #f0b323; --hs-font-head: 'Montserrat', sans-serif; }
#wpbody .hs-cpv .hs-story-card { container-type: inline-size; box-sizing: border-box; position: relative; display: flex; flex-direction: column; justify-content: space-between; width: 270px; aspect-ratio: 9 / 16; padding: 15px 0 20px; background-color: var(--hs-dark); color: #fff; border-radius: 12px; overflow: visible; box-shadow: 0 10px 25px rgba(0, 0, 0, .15); }
#wpbody .hs-cpv .hs-story-card p { margin: 0; padding: 0; }
#wpbody .hs-cpv .hs-story-image { position: absolute; inset: 0; z-index: 1; border-radius: 12px; overflow: hidden; background: #1d4466; }
#wpbody .hs-cpv .hs-story-image::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(0, 48, 87, .3) 0%, rgba(0, 48, 87, .8) 100%); z-index: 2; }
#wpbody .hs-cpv .hs-story-image img { display: block; width: 100%; height: 100%; object-fit: cover; }
#wpbody .hs-cpv .hs-badge-primary { position: absolute; top: 0; left: 50%; transform: translate(-50%, -50%); z-index: 30; display: inline-flex; max-width: 90%; }
#wpbody .hs-cpv .hs-badge-primary-inner { display: inline-flex; align-items: center; justify-content: center; padding: 4px; border-radius: 50px; background: rgba(255, 255, 255, .15); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, .2); box-shadow: 0 4px 15px rgba(0, 0, 0, .15); }
#wpbody .hs-cpv .hs-badge-primary p { display: block; padding: 6px 16px; border-radius: 50px; background: var(--hs-gold); color: var(--hs-dark); font-family: var(--hs-font-head); font-weight: 800; font-size: clamp(.3rem, 4.5cqw, .8rem); line-height: 1.2; letter-spacing: .5px; text-transform: uppercase; white-space: nowrap; }
#wpbody .hs-cpv .hs-story-top-group { position: relative; z-index: 20; display: flex; flex-direction: column; align-items: center; width: 100%; padding-top: 5px; }
#wpbody .hs-cpv .hs-badge-location p { display: inline-flex; align-items: center; color: #fff; font-family: var(--hs-font-head); font-size: .75rem; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; text-shadow: 0 2px 4px rgba(0, 0, 0, .6); white-space: nowrap; }
#wpbody .hs-cpv .hs-badge-location p::before { content: ''; width: 9px; height: 12px; margin-right: 6px; background: var(--hs-gold); -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 384 512'%3E%3Cpath d='M172 501C27 291 0 269 0 192 0 86 86 0 192 0s192 86 192 192c0 77-27 99-172 309a24 24 0 0 1-40 0zm20-229a80 80 0 1 0 0-160 80 80 0 0 0 0 160z'/%3E%3C/svg%3E") center / contain no-repeat; mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 384 512'%3E%3Cpath d='M172 501C27 291 0 269 0 192 0 86 86 0 192 0s192 86 192 192c0 77-27 99-172 309a24 24 0 0 1-40 0zm20-229a80 80 0 1 0 0-160 80 80 0 0 0 0 160z'/%3E%3C/svg%3E") center / contain no-repeat; }
#wpbody .hs-cpv .hs-story-date { display: flex; justify-content: center; width: 100%; margin-top: 4px; padding: 4px 0; background: rgba(255, 255, 255, .2); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
#wpbody .hs-cpv .hs-story-date p { color: #fff; font-family: var(--hs-font-head); font-size: .65rem; font-weight: 700; line-height: 1; letter-spacing: .5px; text-transform: uppercase; text-shadow: 0 1px 4px rgba(0, 0, 0, .8); }
#wpbody .hs-cpv .hs-story-bottom-group { position: relative; z-index: 20; display: flex; flex-direction: column; align-items: center; width: 100%; }
#wpbody .hs-cpv .hs-story-title { box-sizing: border-box; display: flex; justify-content: center; width: 100%; margin: 0 0 12px; padding: 0 25px; text-align: center; }
#wpbody .hs-cpv .hs-story-title p { display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; color: #fff; font-family: var(--hs-font-head); font-weight: 800; font-size: 1.3rem; line-height: 1.1; text-shadow: 0 2px 5px rgba(0, 0, 0, .4); }
#wpbody .hs-cpv .hs-story-tags { display: flex; justify-content: center; padding: 0 15px; }
#wpbody .hs-cpv .hs-story-tags p { display: flex; flex-wrap: wrap; gap: 4px; justify-content: center; }
#wpbody .hs-cpv .hs-story-tags span { display: inline-flex; align-items: center; padding: 5px 8px; border-radius: 6px; background: rgba(240, 179, 35, .15); border: 1px solid rgba(240, 179, 35, .3); color: var(--hs-gold); font-family: var(--hs-font-head); font-size: .55rem; font-weight: 700; line-height: 1; text-transform: uppercase; }

/* Top of the Post page: the Section, date and campus chips from the Post templates (measured on a live Post). */
#wpbody .hs-cpv-strip { display: flex; flex-wrap: nowrap; white-space: nowrap; align-items: center; gap: 16px; padding: 4px 0 16px; border-bottom: 1px solid #f0b323; font-family: 'Open Sans', sans-serif; }
#wpbody .hs-cpv-strip p { margin: 0; }
#wpbody .hs-cpv-sec { padding-right: 16px; border-right: 1px solid rgba(71, 85, 105, .2); }
#wpbody .hs-cpv-sec p { color: #003057; font-size: 32px; font-weight: 700; line-height: 1.05; text-transform: uppercase; }
#wpbody .hs-cpv-chips { display: flex; flex-wrap: nowrap; gap: 8px; }
#wpbody .hs-cpv-chip { padding: 2px 8px; border-radius: 5px; background: #003057; box-shadow: 0 2px 18px rgba(0, 0, 0, .3); }
#wpbody .hs-cpv-chip p { color: #fff; font-size: 11.2px; font-weight: 700; line-height: 27.2px; white-space: nowrap; }
/* Image picker: Featured and Portrait slots plus one rack of the content's own photos. */
#wpbody .hs-cip-grid { display: grid; grid-template-columns: 210px 170px minmax(0, 1fr); gap: 8px; align-items: start; }
@media (max-width: 960px) { #wpbody .hs-cip-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } #wpbody .hs-cip-rack-wrap { grid-column: 1 / -1; } }
#wpbody .hs-cip-slot { outline: 2px solid transparent; outline-offset: -2px; }
#wpbody .hs-cip-slot.is-active { outline-color: #2271b1; background: #f0f6fc; }
#wpbody .hs-cip-slot-pick { display: block; width: 100%; padding: 0; border: 0; background: none; text-align: left; cursor: pointer; }
#wpbody .hs-cip-thumb { display: block; width: 100%; height: 120px; object-fit: cover; border-radius: 2px; margin: 2px 0 4px; }
#wpbody .hs-cip-empty { display: flex; align-items: center; justify-content: center; height: 120px; margin: 2px 0 4px; border: 1px dashed #c3c4c7; border-radius: 2px; color: #8c8f94; font-size: 12px; }
#wpbody .hs-cip-picking { display: block; font-size: 11px; color: #646970; }
#wpbody .hs-cip-slot.is-active .hs-cip-picking { color: #2271b1; font-weight: 600; }
#wpbody .hs-cip-slot-actions { display: flex; align-items: center; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
#wpbody .hs-cip-rack { display: grid; grid-template-columns: repeat(auto-fill, minmax(68px, 1fr)); gap: 4px; max-height: 260px; overflow-y: auto; padding: 4px; background: #fff; border: 1px solid #dcdcde; border-radius: 2px; }
#wpbody .hs-cip-rack-item { position: relative; padding: 0; border: 2px solid transparent; background: #f0f0f1; cursor: pointer; line-height: 0; border-radius: 2px; aspect-ratio: 1; }
#wpbody .hs-cip-rack-item:hover, #wpbody .hs-cip-rack-item:focus { border-color: #2271b1; outline: 0; }
#wpbody .hs-cip-rack-item img { width: 100%; height: 100%; object-fit: cover; }
#wpbody .hs-cip-tag { position: absolute; top: 2px; left: 2px; min-width: 16px; padding: 0 3px; line-height: 16px; font-size: 10px; font-weight: 700; color: #fff; border-radius: 2px; }
#wpbody .hs-cip-tag.is-f { background: #2271b1; }
#wpbody .hs-cip-tag.is-p { background: #8a2be2; left: auto; right: 2px; }
#wpbody .hs-cip-rack-note { margin-top: 4px; }
#wpbody .hs-cip-rack-label { display: block; font-weight: 600; margin-bottom: 4px; }
</style>
<script id="hs-card-preview-js">
(function () {
	if (window.hsCardPreview) { return; }
	var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
	var DASH = '–';
	var SVG = 'http://www.w3.org/2000/svg';
	var instances = [];

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'text') { node.textContent = attrs[k]; } else { node.setAttribute(k, attrs[k]); }
		});
		(children || []).forEach(function (c) { if (c) { node.appendChild(c); } });
		return node;
	}
	function ymd(v) {
		var m = /^(\d{4})-?(\d{2})-?(\d{2})/.exec(v || '');
		if (!m) { return null; }
		var y = +m[1], mo = +m[2], d = +m[3];
		var check = new Date(Date.UTC(y, mo - 1, d));
		return check.getUTCMonth() === mo - 1 && check.getUTCDate() === d ? { y: y, m: mo, d: d, key: m[1] + m[2] + m[3] } : null;
	}
	function ordinal(n) { var s = ['th', 'st', 'nd', 'rd'], v = n % 100; return n + (s[(v - 20) % 10] || s[v] || s[0]); }
	function full(x) { return x.d + ' ' + MONTHS[x.m - 1] + ' ' + x.y; }
	function mon(x) { return MONTHS[x.m - 1].slice(0, 3); }
	// Same rules as hs_date_label_for_post(): event start/end, else the publish date.
	function postDateLabel(v) {
		var s = ymd(v.eventStart), e = ymd(v.eventEnd), p = ymd(v.publishDate);
		if (!s) { return p ? full(p) : ''; }
		if (!e || e.key <= s.key) { return full(s); }
		if (s.y === e.y && s.m === e.m) { return s.d + DASH + full(e); }
		if (s.y === e.y) { return s.d + ' ' + MONTHS[s.m - 1] + ' ' + DASH + ' ' + full(e); }
		return full(s) + ' ' + DASH + ' ' + full(e);
	}
	// Same rules as hs_card_date_label(): short months only for ranges.
	function cardDateLabel(v) {
		var s = ymd(v.eventStart), e = ymd(v.eventEnd), p = ymd(v.publishDate);
		var long = function (x) { return ordinal(x.d) + ' ' + MONTHS[x.m - 1] + ' ' + x.y; };
		if (!s) { return p ? long(p) : ''; }
		if (!e || e.key <= s.key) { return long(s); }
		if (s.y === e.y && s.m === e.m) { return s.d + DASH + e.d + ' ' + mon(e) + ' ' + e.y; }
		if (s.y === e.y) { return s.d + ' ' + mon(s) + ' ' + DASH + ' ' + e.d + ' ' + mon(e) + ' ' + e.y; }
		return s.d + ' ' + mon(s) + ' ' + s.y + ' ' + DASH + ' ' + e.d + ' ' + mon(e) + ' ' + e.y;
	}
	function shown(v) {
		var names = v.campusNames || [];
		return {
			badge: String(v.badgeText || '').trim() || String(v.section || '').trim() || 'News',
			place: String(v.location || '').trim() || (names.length > 1 ? 'Both Campuses' : names[0] || ''),
			sliderDate: cardDateLabel(v),
			postDate: postDateLabel(v),
			image: v.image || '',
			title: String(v.title || '').trim(),
			tags: (v.tags || []).map(function (t) { t = String(t); var i = t.indexOf(':'); return (i > -1 ? t.slice(i + 1) : t).trim(); }).filter(Boolean)
		};
	}

	// [where, target, part of the page, where its text comes from]
	var CALLOUTS = [
		['card', 'badge', 'Yellow badge', 'Shows the Yellow badge text if you fill it in, otherwise the Website Section.'],
		['card', 'place', 'Campus line', 'Shows the Location text if you fill it in, otherwise the campus name (or “Both Campuses”).'],
		['card', 'sliderDate', 'Date', 'The event date if set, otherwise the publish date.'],
		['card', 'image', 'Photo', 'The Portrait image if chosen, otherwise the Featured image.'],
		['card', 'title', 'Title', 'The Title field.'],
		['card', 'tags', 'Tag chips', 'Thematic Tags without their prefix: “SPORT: Swimming” shows as “Swimming”.'],
		['strip', 'section', 'Section heading', 'Shows the Yellow badge text if you fill it in, otherwise the Website Section.'],
		['strip', 'postDate', 'Date chip', 'The event date if set, otherwise the publish date.'],
		['strip', 'chipPlace', 'Campus chip', 'Shows the Location text if you fill it in, otherwise the campus name.']
	];

	function create(options) {
		options = options || {};
		var labelsFor = options.labels || {};
		var t = {};
		var card = el('div', { 'class': 'hs-shortcode-slider-wrapper' }, [
			el('div', { 'class': 'hs-story-card' }, [
				t.image = el('div', { 'class': 'hs-story-image' }),
				t.badge = el('div', { 'class': 'hs-badge-primary' }, [el('div', { 'class': 'hs-badge-primary-inner' }, [el('p')])]),
				el('div', { 'class': 'hs-story-top-group' }, [
					t.place = el('div', { 'class': 'hs-badge-location' }, [el('p')]),
					t.sliderDate = el('div', { 'class': 'hs-story-date' }, [el('p')])
				]),
				el('div', { 'class': 'hs-story-bottom-group' }, [
					t.title = el('div', { 'class': 'hs-story-title' }, [el('p')]),
					t.tags = el('div', { 'class': 'hs-story-tags' }, [el('p')])
				])
			])
		]);
		var strip = el('div', { 'class': 'hs-cpv-strip' }, [
			t.section = el('div', { 'class': 'hs-cpv-sec' }, [el('p')]),
			el('div', { 'class': 'hs-cpv-chips' }, [
				t.postDate = el('div', { 'class': 'hs-cpv-chip' }, [el('p')]),
				t.chipPlace = el('div', { 'class': 'hs-cpv-chip' }, [el('p')])
			])
		]);
		var lines = document.createElementNS(SVG, 'svg');
		lines.setAttribute('class', 'hs-cpv-lines');
		lines.setAttribute('aria-hidden', 'true');
		var labels = el('div', { 'class': 'hs-cpv-labels' });
		var stripLabels = el('div', { 'class': 'hs-cpv-striplabels' });
		var capCard = el('span', { 'class': 'hs-cpv-cap', text: 'Preview · Slider card' });
		var capStrip = el('span', { 'class': 'hs-cpv-cap', text: 'Preview · Top of the Post page' });
		var stage = el('div', { 'class': 'hs-cpv-stage' }, [
			capCard,
			el('div', { 'class': 'hs-cpv-cardbox' }, [card]),
			capStrip,
			el('div', { 'class': 'hs-cpv-stripbox' }, [strip]),
			stripLabels,
			labels, lines
		]);
		var node = el('div', { 'class': 'hs-cpv' }, [stage]);

		function labelNode(c) {
			// A screen can rename where a value comes from, e.g. the Post screen's "Yellow Badge Override".
			var text = labelsFor[c[1]] || c[3];
			return el('div', { 'class': 'hs-cpv-label' }, [el('strong', { text: c[2] }), text ? el('span', { text: text }) : null]);
		}
		function visible(key) { var n = t[key]; return n && n.style.display !== 'none' ? n : null; }

		// Card labels sit in the right-hand column, level with their target where they can.
		// Strip labels sit in a row under the strip, each joined by a line from below its target.
		function redraw() {
			if (!stage.isConnected) { return; }
			window.requestAnimationFrame(function () {
				var base = stage.getBoundingClientRect();
				if (!base.width) { return; }
				var svg = function (tag, attrs) { var n = document.createElementNS(SVG, tag); Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); }); lines.appendChild(n); };
				labels.textContent = '';
				stripLabels.textContent = '';
				labels.style.minHeight = '';
				while (lines.firstChild) { lines.removeChild(lines.firstChild); }

				var lx = labels.getBoundingClientRect().left - base.left;
				var next = capCard.getBoundingClientRect().bottom - base.top + 6;
				var placed = [];
				CALLOUTS.filter(function (c) { return c[0] === 'card'; }).forEach(function (c) {
					var target = visible(c[1]);
					if (!target) { return; }
					var r = (c[1] === 'image' ? target : (target.querySelector('p') || target)).getBoundingClientRect();
					if (!r.width) { return; }
					var ty = c[1] === 'image' ? r.top - base.top + r.height * 0.42 : r.top - base.top + r.height / 2;
					var tx = c[1] === 'image' ? r.right - base.left - 14 : r.right - base.left + 4;
					var label = labelNode(c);
					labels.appendChild(label);
					var h = label.offsetHeight;
					var top = Math.max(ty - h / 2, next);
					next = top + h + 6;
					placed.push({ label: label, top: top, h: h, tx: tx, ty: ty });
				});
				var labelsTop = labels.getBoundingClientRect().top - base.top;
				placed.forEach(function (p) { p.label.style.top = (p.top - labelsTop) + 'px'; });
				labels.style.minHeight = Math.max(0, next - labelsTop) + 'px'; // pushes the strip down if the labels run long

				var stripCallouts = CALLOUTS.filter(function (c) { return c[0] === 'strip' && visible(c[1]); });
				stripCallouts.forEach(function (c) { stripLabels.appendChild(labelNode(c)); });
				var sx = stripLabels.getBoundingClientRect();
				var stripBottom = stage.querySelector('.hs-cpv-stripbox').getBoundingClientRect().bottom - base.top;
				var midY = stripBottom + (sx.top - base.top - stripBottom) / 2;

				lines.setAttribute('width', base.width);
				lines.setAttribute('height', base.height);
				placed.forEach(function (p) {
					var ly = p.top + Math.min(p.h / 2, 10);
					svg('polyline', { points: p.tx + ',' + p.ty + ' ' + (lx - 16) + ',' + p.ty + ' ' + (lx - 4) + ',' + ly });
					svg('circle', { cx: p.tx, cy: p.ty, r: 3 });
				});
				stripCallouts.forEach(function (c, i) {
					var r = (t[c[1]].querySelector('p') || t[c[1]]).getBoundingClientRect();
					var l = stripLabels.children[i].getBoundingClientRect();
					var tx = r.left - base.left + r.width / 2, ty = r.bottom - base.top + 2;
					var x2 = l.left - base.left + 10, y2 = l.top - base.top;
					svg('polyline', { points: tx + ',' + ty + ' ' + tx + ',' + midY + ' ' + x2 + ',' + midY + ' ' + x2 + ',' + y2 });
					svg('circle', { cx: tx, cy: ty, r: 3 });
				});
			});
		}

		function update(values) {
			var v = shown(values || {});
			var setText = function (n, text) { n.querySelector('p').textContent = text; n.style.display = text ? '' : 'none'; };
			setText(t.badge, v.badge);
			setText(t.place, v.place);
			setText(t.sliderDate, v.sliderDate);
			setText(t.title, v.title);
			setText(t.section, v.badge);
			setText(t.postDate, v.postDate);
			setText(t.chipPlace, v.place);
			var tp = t.tags.querySelector('p');
			tp.textContent = '';
			v.tags.forEach(function (name) { tp.appendChild(el('span', { text: name })); });
			t.tags.style.display = v.tags.length ? '' : 'none';
			if (t.image.dataset.src !== v.image) {
				t.image.dataset.src = v.image;
				t.image.textContent = '';
				if (v.image) {
					var img = el('img', { src: v.image, alt: '' });
					img.addEventListener('load', redraw);
					t.image.appendChild(img);
				}
			}
			redraw();
		}

		var instance = { node: node, update: update, redraw: redraw };
		instances.push(instance);
		return instance;
	}

	window.addEventListener('resize', function () {
		instances = instances.filter(function (i) { return i.node.isConnected || !i.node.parentNode; });
		instances.forEach(function (i) { i.redraw(); });
	});

	/*
	 * Image picker. options:
	 *   images: [{ id, url (medium), thumb }]        the content's own photos, in order
	 *   get(name) -> { id, url }   set(name, id, url)  where 'image' (Featured) and 'portrait' live
	 *   state: {}                                    keeps the active slot across redraws
	 *   slots: { image: { label, required, hint() }, portrait: { label, hint() } }
	 *   source: 'Article images'                     the rack's heading
	 *   empty: 'This article has no images of its own. Use Media Library.'
	 *   classes: { slot, label, hint }               extra classes so each screen keeps its own look
	 *   onDraw(slotNodes)                            after every draw, e.g. to mark required fields
	 */
	function imagePicker(o) {
		var state = o.state || {};
		var cls = o.classes || {};
		var node = el('div', { 'class': 'hs-cip' });
		function label(name) { return (o.slots[name] || {}).label || name; }
		function pick(name, id, url) {
			o.set(name, id, url);
			if (name === 'image' && id && !o.get('portrait').id) { state.active = 'portrait'; }
			draw();
		}
		function button(text, klass, onClick) {
			var b = el('button', { type: 'button', 'class': klass, text: text });
			b.addEventListener('click', onClick);
			return b;
		}
		function slot(name) {
			var cur = o.get(name), active = state.active === name;
			var spec = o.slots[name] || {};
			var pickBtn = el('button', { type: 'button', 'class': 'hs-cip-slot-pick', 'aria-pressed': active ? 'true' : 'false' }, [
				el('span', { 'class': (cls.label || '') + ' hs-cip-label', text: spec.label + (spec.required ? ' *' : '') }),
				cur.url ? el('img', { 'class': 'hs-cip-thumb', src: cur.url, alt: '' }) : el('span', { 'class': 'hs-cip-empty', text: 'No image yet' }),
				el('span', { 'class': 'hs-cip-picking', text: active ? '◀ picking now' : 'Click to pick' })
			]);
			pickBtn.addEventListener('click', function () { state.active = name; draw(); });
			var media = button('Media Library', 'button button-small', function () {
				var frame = wp.media({ title: spec.label, multiple: false, library: { type: 'image' } });
				frame.on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					var size = a.sizes && (a.sizes.medium || a.sizes.full);
					state.active = name;
					pick(name, a.id, size ? size.url : a.url);
				});
				frame.open();
			});
			var remove = cur.id ? button('Remove', 'button-link', function () { state.active = name; o.set(name, 0, ''); draw(); }) : null;
			return el('div', { 'class': (cls.slot || '') + ' hs-cip-slot' + (active ? ' is-active' : ''), 'data-slot': name }, [
				pickBtn,
				el('div', { 'class': 'hs-cip-slot-actions' }, [media, remove]),
				spec.hint ? el('p', { 'class': (cls.hint || '') + ' hs-cip-hint', text: spec.hint() }) : null
			]);
		}
		function rack() {
			var images = o.images || [];
			if (!images.length) {
				return el('div', { 'class': 'hs-cip-rack-wrap' }, [el('p', { 'class': (cls.hint || '') + ' hs-cip-hint', text: o.empty || 'No images of its own. Use Media Library.' })]);
			}
			var f = o.get('image').id, p = o.get('portrait').id;
			var grid = el('div', { 'class': 'hs-cip-rack', role: 'list' }, images.map(function (image) {
				var b = el('button', { type: 'button', role: 'listitem', 'class': 'hs-cip-rack-item', title: 'Use as ' + label(state.active), 'data-id': String(image.id) }, [
					el('img', { src: image.thumb || image.url, alt: '', loading: 'lazy', width: '150', height: '150' }),
					image.id === f ? el('span', { 'class': 'hs-cip-tag is-f', text: 'F', title: label('image') }) : null,
					image.id === p ? el('span', { 'class': 'hs-cip-tag is-p', text: 'P', title: label('portrait') }) : null
				]);
				b.addEventListener('click', function () { pick(state.active, image.id, image.url); });
				return b;
			}));
			return el('div', { 'class': 'hs-cip-rack-wrap' }, [
				el('span', { 'class': (cls.label || '') + ' hs-cip-rack-label', text: (o.source || 'Images') + ' (' + images.length + ')' }),
				grid,
				el('p', { 'class': (cls.hint || '') + ' hs-cip-hint hs-cip-rack-note', text: 'Click an image to set it as the ' + label(state.active) + '.' })
			]);
		}
		function draw() {
			if (!state.active) { state.active = o.get('image').id && !o.get('portrait').id ? 'portrait' : 'image'; }
			var scroll = node.querySelector('.hs-cip-rack');
			var top = scroll ? scroll.scrollTop : 0;
			node.textContent = '';
			var slots = { image: slot('image'), portrait: slot('portrait') };
			node.appendChild(el('div', { 'class': 'hs-cip-grid' }, [slots.image, slots.portrait, rack()]));
			var again = node.querySelector('.hs-cip-rack');
			if (again) { again.scrollTop = top; }
			if (o.onDraw) { o.onDraw(slots); }
		}
		draw();
		return { node: node, draw: draw };
	}

	window.hsCardPreview = { create: create, imagePicker: imagePicker, cardDateLabel: cardDateLabel, postDateLabel: postDateLabel };
}());
</script>
	<?php
}
