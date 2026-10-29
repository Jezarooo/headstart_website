<?php

/**
 * ISQM Gold Announcement
 */
/**
 * =============================================================================
 * HEADSTART ISQM GOLD ANNOUNCEMENT (POP-UP, INLINE BLOCK AND QUALITY BAR)
 * =============================================================================
 * PURPOSE: Everything for the ISQM Gold accreditation (Education Development
 *          Trust, report published 26 October 2026), in one snippet:
 *            1. Sitewide celebratory pop-up with gold confetti.
 *            2. Shortcode [hs_isqm_gold]: the numbered areas and quote slider as an
 *               inline block (no pop-up, no confetti).
 *            3. Shortcode [hs_quality_bar]: the refreshed Quality Bar slider.
 *            4. Settings page: Settings > ISQM Gold (administrators only).
 * DEPENDENCIES: WordPress core only. Self-contained: CSS, JS and the ISQM logo
 *          are printed by this one snippet. Shortcode CSS and JS print once,
 *          and only on pages that use a shortcode.
 * SWITCHING OFF: untick "Show the pop-up" in Settings > ISQM Gold (the
 *          shortcodes keep working), or deactivate the snippet to remove
 *          everything. Purge WP Super Cache and Cloudflare APO afterwards.
 * TESTING: add ?hs_isqm=1 to any URL as a logged-in administrator to preview
 *          the pop-up whatever the settings (locally use /en/our-school/?hs_isqm=1:
 *          the local redirect from / drops the query string), and ?hs_isqm=0
 *          to see that it stays hidden. Visitors can use ?hs_isqm=1 only while
 *          the pop-up is on and the page is allowed.
 *          Settings > ISQM Gold also shows a live preview of both shortcodes.
 * QUOTES:  every quote is word for word from the EDT ISQM Accreditation
 *          Report (HeadStart, inspection 21 to 24 September 2026). The bold
 *          words are our emphasis. Shorter versions (mobile) end with a full
 *          sentence or an ellipsis. Do not reword them.
 * =============================================================================
 */

/* ---- Defaults (the Settings page overrides these once saved) ------------- */
if ( ! defined( 'HS_ISQM_START' ) )    define( 'HS_ISQM_START', '2026-10-28' );   // first day shown (site time); blank = no limit
if ( ! defined( 'HS_ISQM_END' ) )      define( 'HS_ISQM_END', '2026-11-30' );     // last day shown (site time); blank = no limit
if ( ! defined( 'HS_ISQM_POST_URL' ) ) define( 'HS_ISQM_POST_URL', '/accreditations/' ); // "Explore our accreditations" link
if ( ! defined( 'HS_ISQM_VERSION' ) )  define( 'HS_ISQM_VERSION', '1' );          // changes when "Show again to everyone" is ticked
if ( ! defined( 'HS_ISQM_REPEAT_DAYS' ) ) define( 'HS_ISQM_REPEAT_DAYS', 3 );      // a visitor who saw it is reminded again after this many days

/* ---- Settings ------------------------------------------------------------ */
function hs_isqm_defaults() {
    return array(
        'enabled'     => 1,
        'repeat_days' => (int) HS_ISQM_REPEAT_DAYS,
        'start'       => HS_ISQM_START,
        'end'         => HS_ISQM_END,
        'link'        => HS_ISQM_POST_URL,
        'version'     => HS_ISQM_VERSION,
        'admins'      => 1,
        'weekly'      => 1,
        'exclude'     => '',
    );
}

function hs_isqm_opts() {
    $saved = get_option( 'hs_isqm_settings', array() );
    return wp_parse_args( is_array( $saved ) ? $saved : array(), hs_isqm_defaults() );
}

function hs_isqm_sanitize( $in ) {
    $d   = hs_isqm_defaults();
    $old = hs_isqm_opts();
    $in  = is_array( $in ) ? $in : array();
    $out = array();
    $out['enabled'] = empty( $in['enabled'] ) ? 0 : 1;
    $out['admins']  = empty( $in['admins'] ) ? 0 : 1;
    $out['weekly']  = empty( $in['weekly'] ) ? 0 : 1;
    $days = isset( $in['repeat_days'] ) ? (int) $in['repeat_days'] : (int) $d['repeat_days'];
    $out['repeat_days'] = max( 0, min( 365, $days ) );
    foreach ( array( 'start', 'end' ) as $k ) {
        $v = isset( $in[ $k ] ) ? trim( (string) $in[ $k ] ) : '';
        $out[ $k ] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
    }
    $link = isset( $in['link'] ) ? trim( (string) $in['link'] ) : '';
    $out['link'] = ( $link === '' ) ? $d['link'] : esc_url_raw( $link );
    if ( $out['link'] === '' ) $out['link'] = $d['link'];
    $lines = preg_split( '/\r\n|\r|\n/', isset( $in['exclude'] ) ? (string) $in['exclude'] : '' );
    $keep  = array();
    foreach ( $lines as $line ) {
        $line = trim( sanitize_text_field( $line ) );
        if ( $line !== '' ) $keep[] = $line;
    }
    $out['exclude'] = implode( "\n", array_slice( $keep, 0, 200 ) );
    $out['version'] = ! empty( $in['reset'] ) ? (string) time() : (string) $old['version'];
    // Page caches hold the old page: clear them so the change shows at once.
    if ( function_exists( 'wp_cache_clear_cache' ) ) wp_cache_clear_cache();
    return $out;
}

add_action( 'admin_init', 'hs_isqm_register_settings' );
function hs_isqm_register_settings() {
    register_setting( 'hs_isqm_group', 'hs_isqm_settings', array( 'type' => 'array', 'sanitize_callback' => 'hs_isqm_sanitize', 'default' => hs_isqm_defaults() ) );
}

add_action( 'admin_menu', 'hs_isqm_admin_menu' );
function hs_isqm_admin_menu() {
    add_options_page( 'ISQM Gold', 'ISQM Gold', 'manage_options', 'hs-isqm-gold', 'hs_isqm_settings_page' );
}

/* ---- Page matching (exclusions and weekly updates) ----------------------- */
function hs_isqm_norm_path( $url ) {
    $p = wp_parse_url( trim( (string) $url ), PHP_URL_PATH );
    if ( ! is_string( $p ) || $p === '' ) $p = '/';
    $p = '/' . trim( strtolower( rawurldecode( $p ) ), '/' );
    return $p === '/' ? '/' : $p . '/';
}

function hs_isqm_is_excluded( $path, $list ) {
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $list ) as $line ) {
        $line = trim( $line );
        if ( $line === '' ) continue;
        $wild = ( substr( $line, -1 ) === '*' );
        if ( $wild ) $line = rtrim( $line, '*' );
        $want = hs_isqm_norm_path( $wild ? $line . 'x' : $line );
        if ( $wild ) $want = preg_replace( '#x/$#', '', $want ); // keep the prefix, drop the marker
        if ( $want === '/' && ! $wild ) { if ( $path === '/' ) return true; continue; }
        if ( $wild ) {
            if ( strpos( $path, $want ) !== false ) return true;
        } elseif ( $path === $want || substr( $path, -strlen( $want ) ) === $want ) {
            return true; // also matches when the site adds a language prefix such as /en/
        }
    }
    return false;
}

function hs_isqm_request_path() {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
    return hs_isqm_norm_path( $uri );
}

/* ---- Should this request print the pop-up? ------------------------------- */
function hs_isqm_should_print() {
    if ( is_admin() || is_feed() || is_embed() || is_robots() || is_trackback() ) return false;
    if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) return false;
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return false;
    if ( is_customize_preview() || is_preview() ) return false;
    // Divi Visual Builder and Theme Builder editing screens
    if ( isset( $_GET['et_fb'] ) || isset( $_GET['et_tb'] ) ) return false;
    if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) return false;

    // Administrators can preview with ?hs_isqm=1 whatever the settings say.
    if ( isset( $_GET['hs_isqm'] ) && current_user_can( 'manage_options' ) ) return true;

    $o = hs_isqm_opts();
    if ( empty( $o['enabled'] ) ) return false;
    if ( empty( $o['admins'] ) && current_user_can( 'manage_options' ) ) return false;
    $today = current_time( 'Y-m-d' );
    if ( $o['start'] !== '' && $today < $o['start'] ) return false;
    if ( $o['end'] !== '' && $today > $o['end'] ) return false;
    $path = hs_isqm_request_path();
    if ( empty( $o['weekly'] ) && strpos( $path, '/weekly-update/' ) !== false ) return false;
    if ( hs_isqm_is_excluded( $path, $o['exclude'] ) ) return false;
    return true;
}

/* ---- Content ------------------------------------------------------------- */
function hs_isqm_quotes() {
    // List order is slide order: left column top to bottom, then right column.
    return array(
        array( 'area' => 'Standards and achievement', 'title' => 'The standards reached by students in their work and the progress they make in the core subjects of English, mathematics and science', 'qb' => 6, 'q' => 'Pupils achieve <strong>exceptionally high standards</strong> in creative and performing arts, in physical and athletic pursuits, in computing, and in humanities.', 'qs' => 'Pupils achieve <strong>exceptionally high standards</strong> in creative and performing arts, in physical and athletic pursuits…' ),
        array( 'area' => 'Personal development', 'title' => 'Students’ personal development', 'qb' => 2, 'q' => 'Pupils are very proud that the school is an <strong>inclusive environment</strong> where everyone is welcomed and supported with their learning.', 'qs' => 'Pupils are very proud that the school is an <strong>inclusive environment</strong> where everyone is welcomed…' ),
        array( 'area' => 'Teaching and learning', 'title' => 'Teaching and learning', 'qb' => 0, 'q' => '<strong>High-quality teaching</strong> enables pupils to acquire excellent knowledge, skills and understanding to make <strong>outstanding rates of progress</strong>.' ),
        array( 'area' => 'The curriculum', 'title' => 'The curriculum', 'qb' => 4, 'q' => 'Almost all children begin the day <strong>excited about something new to learn</strong> or revisiting prior learning.' ),
        array( 'area' => 'Accommodation and resources', 'title' => 'The quality and quantity of the school’s accommodation and resources', 'qb' => 5, 'q' => 'The <strong>high-class facilities</strong> at both sites support and enhance pupils’ experience and learning.' ),
        array( 'area' => 'Care and support', 'title' => 'How well the school cares for and supports its students', 'qb' => 1, 'q' => 'Pupils enjoy being part of a <strong>caring learning community</strong>. They believe that their views are listened to and acted upon.', 'qs' => 'Pupils enjoy being part of a <strong>caring learning community</strong>.' ),
        array( 'area' => 'Parents and the community', 'title' => 'The school’s partnership with parents and the community', 'qb' => 7, 'q' => 'The school’s leaders succeed in their aim to value <strong>families as essential collaborators</strong> in the life of the school.' ),
        array( 'area' => 'Leadership and management', 'title' => 'Leadership and management', 'qb' => 3, 'q' => 'Staff, pupils and parents consistently describe the school as a <strong>family</strong> and an inclusive community.' ),
    );
}

/* Size band for a slide title on phones (matches the JS in the pop-up): s up to 33 characters, m up to 75, l longer. */
function hs_isqm_tsize( $t ) {
    $n = function_exists( 'mb_strlen' ) ? mb_strlen( $t, 'UTF-8' ) : strlen( $t );
    return $n > 75 ? 'l' : ( $n > 33 ? 'm' : 's' );
}

function hs_isqm_link( $override = '' ) {
    $o   = hs_isqm_opts();
    $url = $override !== '' ? $override : $o['link'];
    if ( strpos( $url, 'http' ) !== 0 ) $url = home_url( $url );
    return esc_url_raw( $url );
}

function hs_isqm_medal() {
    return '<svg viewBox="0 0 40 48" aria-hidden="true"><path d="M11 27L5 45l8-4 6 6 2-18z" fill="#b8820a"/><path d="M29 27l6 18-8-4-6 6-2-18z" fill="#d99a0b"/><circle cx="20" cy="18" r="16" fill="#f0b323"/><circle cx="20" cy="18" r="11.5" fill="none" stroke="#8a6300" stroke-width="1.6"/><path d="M20 10.5l2.2 4.6 5 .6-3.7 3.5 1 5L20 21.7 15.5 24.2l1-5-3.7-3.5 5-.6z" fill="#8a6300"/></svg>';
}

/* ---- 1. Pop-up ----------------------------------------------------------- */
add_action( 'wp_footer', 'hs_isqm_print', 99 );
function hs_isqm_print() {
    if ( ! hs_isqm_should_print() ) return;
    $o = hs_isqm_opts();
    $config = array(
        'version' => (string) $o['version'],
        'repeatDays' => (int) $o['repeat_days'],
        'readUrl' => hs_isqm_link(),
        'logo'    => content_url( 'uploads/hs-head/9a41e560505e7872cb5adbf43a124b29.webp' ), // the ISQM Gold logo, a file so it isn't inlined in every page,
        'source'  => 'Education Development Trust (EDT) ISQM Accreditation Report, 2026',
        'blurb'   => 'The International Schools Quality Mark is an independent inspection by Education Development Trust, and Gold is the highest level it awards. We are delighted to share that HeadStart was judged <strong>Outstanding</strong> in all eight areas. Here is what the inspectors said.',
        'blurbShort' => 'Gold is the highest level of the ISQM, an independent inspection by Education Development Trust. HeadStart was judged <strong>Outstanding</strong> in all eight areas.',
        'quotes'  => hs_isqm_quotes(),
    );
    ?>
<!-- HeadStart ISQM Gold announcement (snippet: isqm-gold-announcement) -->
<style id="hs-isqm-css">
#hs-isqm { --i-navy: #003057; --i-navy-deep: #001b36; --i-gold: #f0b323; --i-gold-light: #ffd466; --i-yellow: #f0b323; --i-gold-text: #f0b323; --i-fg: #ffffff; --i-muted: #c9d6e4; --i-line: rgba(255, 255, 255, .2); --i-panel: rgba(255, 255, 255, .08); --i-head: var(--hs-font-head, 'Montserrat', 'Helvetica Neue', Arial, sans-serif); --i-body: var(--hs-font-body, 'Open Sans', system-ui, sans-serif); position: fixed; inset: 0; z-index: 2147483000; display: grid; place-items: center; font-family: var(--i-body); }
#hs-isqm *, #hs-isqm *::before, #hs-isqm *::after { box-sizing: border-box; }
#hs-isqm button { font-family: inherit; cursor: pointer; }
html.hs-isqm-open { overflow: hidden; }
#hs-isqm .hs-isqm-bd { position: absolute; inset: 0; background: rgba(0, 0, 0, .8); -webkit-backdrop-filter: blur(5px); backdrop-filter: blur(5px); animation: hs-isqm-fade .4s ease both; }
#hs-isqm .hs-isqm-cv { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 3; }
#hs-isqm .hs-isqm-card { position: relative; z-index: 2; background: #003057; color: var(--i-fg) !important; border-radius: 12px; border: 1px solid rgba(255, 255, 255, .14); box-shadow: 0 30px 80px rgba(0, 0, 0, .55); width: min(780px, 90%); max-height: calc(100% - 24px); overflow: auto; padding: 26px 32px 22px; animation: hs-isqm-pop .55s cubic-bezier(.2, .9, .25, 1.1) both; animation-delay: .5s; }
#hs-isqm.hs-isqm-out .hs-isqm-bd, #hs-isqm.hs-isqm-out .hs-isqm-card { animation: hs-isqm-fadeout .22s ease forwards; }
@keyframes hs-isqm-pop { from { opacity: 0; transform: translateY(18px) scale(.96); } to { opacity: 1; transform: none; } }
@keyframes hs-isqm-fade { from { opacity: 0; } to { opacity: 1; } }
@keyframes hs-isqm-fadeout { to { opacity: 0; } }

/* close: same gold pill as the site's lightboxes */
#hs-isqm .hs-isqm-x { position: absolute; top: 20px; right: 20px; z-index: 3; display: flex; align-items: center; justify-content: center; gap: 6px; width: max-content; height: auto; background-color: rgba(240, 179, 35, .95); color: #003057 !important; border: 4px solid rgba(255, 255, 255, .3); background-clip: padding-box; -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); padding: 6px 16px 6px 20px; border-radius: 50px; box-shadow: 0 4px 15px rgba(0, 0, 0, .25); line-height: 1; transition: all .3s ease; }
#hs-isqm .hs-isqm-x:hover { background-color: #fff; transform: scale(1.05); }
#hs-isqm .hs-isqm-x .ct { font: 700 .85rem/1 var(--i-head); letter-spacing: 1px; text-transform: uppercase; color: #003057 !important; }
#hs-isqm .hs-isqm-x .ci { font-size: 1.6rem; line-height: 1; margin-top: -2px; color: #003057 !important; }

#hs-isqm .hs-isqm-x.xv { position: absolute; top: 24px; right: 24px; z-index: 5; }
@media (min-width: 769px) { #hs-isqm .hs-isqm-x.xin { display: none !important; } }
@media (max-width: 768px) { #hs-isqm .hs-isqm-x.xv { display: none !important; } }

/* top: logo left, award description right */
#hs-isqm .hs-isqm-top { display: grid; grid-template-columns: auto minmax(0, 1fr); grid-template-areas: "logo titles" "logo blurb"; gap: 8px 28px; align-items: center; }
#hs-isqm .hs-isqm-logo { grid-area: logo; display: inline-block; background: #fff; border: 1px solid #fff; border-radius: 12px; padding: 10px 18px; }
#hs-isqm .hs-isqm-logo img { display: block; width: 190px; max-width: 100%; height: auto; margin: 0; }
#hs-isqm .hs-isqm-titles { grid-area: titles; align-self: end; }
#hs-isqm .hs-isqm-eyebrow { margin: 0 0 6px; font: 700 12px/1 var(--i-head); letter-spacing: .16em; text-transform: uppercase; color: var(--i-gold-text) !important; }
#hs-isqm .hs-isqm-card h2.hs-isqm-title { font: 700 clamp(30px, 4.2vw, 40px)/1.1 var(--i-head); letter-spacing: -.5px; margin: 0 0 4px; white-space: nowrap; padding: 0; color: var(--i-fg) !important; }
#hs-isqm .hs-isqm-title .gy { color: var(--i-yellow) !important; font-weight: 800; }
#hs-isqm .hs-isqm-blurb { grid-area: blurb; align-self: start; margin: 0; max-width: 56ch; font-size: 15px; line-height: 1.6; color: var(--i-muted) !important; padding: 0; }
#hs-isqm .hs-isqm-blurb strong { color: var(--i-yellow) !important; font-weight: 700; }
#hs-isqm .hs-isqm-blurb .bs { display: none; }
#hs-isqm .hs-isqm-rule { border: 0; border-top: 1px solid var(--i-line); margin: 16px 0 14px; }

/* middle: the eight areas, numbered, selected one outlined in gold */
#hs-isqm .hs-isqm-listhead { margin: 0 0 12px; font: 700 13px/1 var(--i-head); letter-spacing: .14em; text-transform: uppercase; color: var(--i-yellow) !important; }
#hs-isqm .hs-isqm-areas { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-template-rows: repeat(4, auto); grid-auto-flow: column; gap: 2px 20px; }
#hs-isqm .hs-isqm-areas li { margin: 0; padding: 0; }
#hs-isqm .hs-isqm-areabtn { width: 100%; display: grid; grid-template-columns: 24px minmax(0, 1fr); gap: 10px; align-items: start; text-align: left; background: transparent; border: 0; border-radius: 4px; padding: 6px; color: var(--i-fg) !important; font: 600 14px/1.35 var(--i-head); }
#hs-isqm .hs-isqm-areabtn:hover { background: rgba(255, 255, 255, .08); }
#hs-isqm .hs-isqm-areabtn[aria-pressed="true"] { font-weight: 700; box-shadow: inset 0 0 0 1.5px var(--i-gold); background: rgba(255, 255, 255, .06); }
#hs-isqm .hs-isqm-areabtn .num { display: block; width: 24px; padding-top: 2px; font: 700 12px/1 var(--i-head); color: var(--i-gold-text) !important; }

/* quote panel: medal left, area and quote right; slides move sideways */
#hs-isqm .hs-isqm-qpanel { margin-top: 14px; padding: 18px 22px 16px; background: var(--i-panel); border-radius: 8px; }
#hs-isqm .hs-isqm-slides { display: grid; overflow: hidden; }
#hs-isqm .hs-isqm-slide { grid-area: 1 / 1; visibility: hidden; transform: translateX(100%); transition: transform .5s cubic-bezier(.45, 0, .2, 1), visibility 0s linear .5s; display: grid; grid-template-columns: 112px minmax(0, 1fr); gap: 22px; align-items: center; }
#hs-isqm .hs-isqm-slide.on { visibility: visible; transform: none; transition: transform .5s cubic-bezier(.45, 0, .2, 1); }
#hs-isqm .hs-isqm-slide.out-l { transform: translateX(-100%); }
#hs-isqm .hs-isqm-slide.from-l { transform: translateX(-100%); }
#hs-isqm .hs-isqm-slide.notrans { transition: none !important; }
#hs-isqm .hs-isqm-medalcol { text-align: center; }
#hs-isqm .hs-isqm-medalcol svg { display: block; width: 64px; height: auto; margin: 0 auto 10px; }
#hs-isqm .hs-isqm-mini { margin: 0; font: 700 10.5px/1.35 var(--i-head); letter-spacing: .1em; text-transform: uppercase; color: var(--i-gold-text) !important; }
#hs-isqm .hs-isqm-ahead { display: contents; }
#hs-isqm .hs-isqm-amedal { display: none; }
#hs-isqm .hs-isqm-slide h3.hs-isqm-area { font: 700 11px/1.45 var(--i-head); letter-spacing: .06em; text-transform: uppercase; min-height: 2.9em; margin: 0 0 8px; padding: 0; color: var(--i-gold-text) !important; }
#hs-isqm .hs-isqm-slide blockquote { margin: 0 0 8px; padding: 0; border: 0; background: none; font-size: 17.5px; line-height: 1.6; color: var(--i-fg) !important; text-wrap: pretty; quotes: none; }
#hs-isqm .hs-isqm-slide blockquote strong { font-weight: 700; background: linear-gradient(transparent 62%, rgba(240, 179, 35, .45) 62%); }
#hs-isqm .hs-isqm-slide .src { margin: 0; font-size: 12.5px; line-height: 1.3; color: var(--i-muted) !important; border-left: 2px solid var(--i-gold); padding-left: 5px; }
#hs-isqm .hs-isqm-qs { display: none; }

/* foot: button (site style) and slider controls */
#hs-isqm .hs-isqm-foot { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-top: 16px; }
#hs-isqm .hs-isqm-btn { border: 0; border-radius: 4px; padding: 14px 24px; font: 700 .75rem/1 var(--i-head); letter-spacing: .05em; text-transform: uppercase; display: inline-flex; align-items: center; gap: 8px; transition: all .2s ease; background: var(--i-gold); color: var(--i-navy) !important; text-decoration: none; }
#hs-isqm .hs-isqm-btn:hover, #hs-isqm .hs-isqm-btn:focus-visible { background: #fff; color: var(--i-navy) !important; box-shadow: 0 4px 12px rgba(240, 179, 35, .4); transform: translateY(-2px); }
#hs-isqm .hs-isqm-btn svg { width: 12px; height: 12px; fill: currentColor; transition: transform .2s ease; }
#hs-isqm .hs-isqm-btn:hover svg { transform: translateX(4px); }
#hs-isqm .hs-isqm-ctl { display: flex; align-items: center; gap: 12px; }
#hs-isqm .hs-isqm-arrow { width: 38px; height: 38px; padding: 0; border-radius: 50%; border: 1.5px solid var(--i-line); background: transparent; color: var(--i-fg) !important; font-size: 20px; line-height: 1; display: grid; place-items: center; }
#hs-isqm .hs-isqm-arrow:hover { border-color: var(--i-gold); }
#hs-isqm .hs-isqm-dots { display: flex; gap: 7px; }
#hs-isqm .hs-isqm-dots button { width: 10px; height: 10px; padding: 0; border-radius: 50%; border: 0; background: rgba(255, 255, 255, .3); }
#hs-isqm .hs-isqm-dots button[aria-current="true"] { background: var(--i-gold); box-shadow: 0 0 0 2px #003057, 0 0 0 3.5px var(--i-gold); }
#hs-isqm button:focus-visible, #hs-isqm a:focus-visible { outline: 3px solid #ffd466; outline-offset: 2px; }

/* tablet and phone */
@media (max-width: 768px) {
  #hs-isqm .hs-isqm-x { top: 15px; right: 15px; padding: 0; width: 36px; height: 36px; min-width: 36px; }
  #hs-isqm .hs-isqm-x .ct { display: none; }
  #hs-isqm .hs-isqm-x .ci { margin-top: 0; }
  #hs-isqm .hs-isqm-titles { padding-right: 46px; }
  #hs-isqm .hs-isqm-card h2.hs-isqm-title { white-space: normal; }
}
@media (max-width: 640px) {
  #hs-isqm .hs-isqm-card { padding: 20px 16px 16px; }
  #hs-isqm .hs-isqm-top { grid-template-columns: 112px minmax(0, 1fr); grid-template-areas: "logo titles" "blurb blurb"; gap: 12px 14px; align-items: center; margin-bottom: 12px; }
  #hs-isqm .hs-isqm-logo { padding: 6px 8px; border-radius: 8px; }
  #hs-isqm .hs-isqm-logo img { width: 100%; }
  #hs-isqm .hs-isqm-titles { padding-right: 38px; }
  #hs-isqm .hs-isqm-card h2.hs-isqm-title { font-size: 24px; margin: 0; }
  #hs-isqm .hs-isqm-eyebrow { font-size: 10px; letter-spacing: .1em; margin-bottom: 4px; white-space: nowrap; }
  #hs-isqm .hs-isqm-blurb { font-size: 13.5px; line-height: 1.55; }
  #hs-isqm .hs-isqm-blurb .bf { display: none; }
  #hs-isqm .hs-isqm-blurb .bs { display: inline; }
  #hs-isqm .hs-isqm-listhead, #hs-isqm .hs-isqm-areas, #hs-isqm .hs-isqm-rule { display: none; }
  #hs-isqm .hs-isqm-qf { display: none; }
  #hs-isqm .hs-isqm-qs { display: inline; }
  #hs-isqm .hs-isqm-qpanel { padding: 14px 14px 12px; margin-top: 12px; }
  #hs-isqm .hs-isqm-slide { grid-template-columns: minmax(0, 1fr); gap: 0; align-items: start; }
  #hs-isqm .hs-isqm-medalcol { display: none; }
  #hs-isqm .hs-isqm-ahead { display: flex; align-items: center; gap: 10px; margin: 0; }
  #hs-isqm .hs-isqm-amedal { display: none; }
  #hs-isqm .hs-isqm-amedal svg { display: block; width: 30px; height: auto; }
  /* every title block is the same height; short titles are set larger, long ones smaller, so slides match */
  #hs-isqm .hs-isqm-slide h3.hs-isqm-area { font-size: 10.5px; line-height: 1.35; min-height: 42px; margin: 0; display: flex; align-items: center; }
  #hs-isqm .hs-isqm-slide h3.hs-isqm-t-s { font-size: 14px; }
  #hs-isqm .hs-isqm-slide h3.hs-isqm-t-l { font-size: 9px; }
  /* phones: fixed gap from title to quote, source line on the bottom of the card; the card is as tall as its tallest slide */
  #hs-isqm .hs-isqm-slidebody { align-self: stretch; display: flex; flex-direction: column; }
  #hs-isqm .hs-isqm-slide blockquote { margin: 10px 0 12px; padding: 0; }
  #hs-isqm .hs-isqm-slide .src { margin-top: auto; }
  #hs-isqm .hs-isqm-slide blockquote { font-size: 15.5px; line-height: 1.55; }
  #hs-isqm .hs-isqm-slide .src { font-size: 11.5px; }
  #hs-isqm .hs-isqm-foot { flex-direction: column; align-items: stretch; justify-content: center; margin: 12px -16px -16px; padding: 12px 16px; position: sticky; bottom: -16px; background: #003057; border-top: 1px solid var(--i-line); gap: 10px; }
  #hs-isqm .hs-isqm-foot .hs-isqm-btn { width: 100%; justify-content: center; }
  #hs-isqm .hs-isqm-ctl { gap: 8px; justify-content: center; }
  #hs-isqm .hs-isqm-arrow { width: 34px; height: 34px; }
  #hs-isqm .hs-isqm-dots { gap: 5px; }
  #hs-isqm .hs-isqm-dots button { width: 8px; height: 8px; }
}
@media (max-width: 370px) {
  #hs-isqm .hs-isqm-top { grid-template-columns: 96px minmax(0, 1fr); }
  #hs-isqm .hs-isqm-x { top: 8px; right: 8px; width: 32px; height: 32px; min-width: 32px; }
  #hs-isqm .hs-isqm-titles { padding-right: 36px; }
  #hs-isqm .hs-isqm-card h2.hs-isqm-title { font-size: 21px; }
  #hs-isqm .hs-isqm-eyebrow { font-size: 9.5px; letter-spacing: .08em; }
  #hs-isqm .hs-isqm-blurb { font-size: 13px; }
  #hs-isqm .hs-isqm-slide blockquote { font-size: 14.5px; }
  #hs-isqm .hs-isqm-btn { padding: 12px 14px; }
}
@media (min-width: 769px) and (max-height: 820px) {
  #hs-isqm .hs-isqm-card { padding: 20px 28px 16px; }
  #hs-isqm .hs-isqm-rule { margin: 12px 0 10px; }
  #hs-isqm .hs-isqm-listhead { margin-bottom: 8px; }
  #hs-isqm .hs-isqm-areabtn { padding: 4px 6px; }
  #hs-isqm .hs-isqm-qpanel { margin-top: 10px; padding: 14px 20px 12px; }
  #hs-isqm .hs-isqm-slide blockquote { font-size: 16.5px; line-height: 1.5; }
  #hs-isqm .hs-isqm-foot { margin-top: 12px; }
}
@media (prefers-reduced-motion: reduce) {
  #hs-isqm .hs-isqm-bd, #hs-isqm .hs-isqm-card, #hs-isqm .hs-isqm-slide { animation: none !important; transition: none !important; }
}
</style>
<script id="hs-isqm-js">
(function () {
var CFG = <?php echo wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>;
var KEY = 'hs_isqm_gold_v' + CFG.version;
var q = location.search.match(/[?&]hs_isqm=([01])/), force = q ? q[1] : null;
if (force === '0') return;
var seen = false;
try {
  var ts = parseInt(window.localStorage.getItem(KEY), 10);
  // seen = shown less than repeatDays ago; after that, remind the visitor once more
  seen = ts > 0 && (Date.now() - ts) < CFG.repeatDays * 86400000;
} catch (e) {}
if (seen && force !== '1') return;

var MEDAL = '<svg viewBox="0 0 40 48" aria-hidden="true"><path d="M11 27L5 45l8-4 6 6 2-18z" fill="#b8820a"/><path d="M29 27l6 18-8-4-6 6-2-18z" fill="#d99a0b"/><circle cx="20" cy="18" r="16" fill="#f0b323"/><circle cx="20" cy="18" r="11.5" fill="none" stroke="#8a6300" stroke-width="1.6"/><path d="M20 10.5l2.2 4.6 5 .6-3.7 3.5 1 5L20 21.7 15.5 24.2l1-5-3.7-3.5 5-.6z" fill="#8a6300"/></svg>';
var ARROW = '<svg class="arr" viewBox="0 0 448 512" aria-hidden="true"><path d="M438.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L338.8 224 32 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l306.7 0-105.4 105.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l160-160z"/></svg>';
var RM = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
var root, card, cv, cx, parts = [], timers = [], raf = 0, last = 0, W = 0, H = 0, slide = 0, prevFocus = null, closed = false;

/* ---------- confetti: gold rain ---------- */
var GOLD = ['#f0b323', '#ffd466', '#d99a0b', '#fff1c2', '#c68a08'];
function rnd(a, b) { return a + Math.random() * (b - a); }
function sizeCv() {
  var dpr = Math.min(window.devicePixelRatio || 1, 2);
  W = window.innerWidth; H = window.innerHeight;
  cv.width = W * dpr; cv.height = H * dpr;
  cx.setTransform(dpr, 0, 0, dpr, 0, 0);
}
function spawn(n) {
  for (var i = 0; i < n; i++) {
    var a = (90 + (Math.random() - .5) * 30) * Math.PI / 180, v = rnd(1.5, 4);
    parts.push({
      x: rnd(0, W), y: -20, vx: Math.cos(a) * v, vy: Math.sin(a) * v,
      g: .1, d: .975, wob: .8, wp: rnd(0, 6.3),
      rot: rnd(0, 6.3), vr: rnd(-.22, .22), tp: rnd(0, 6.3), ts: rnd(.08, .26),
      s: rnd(7, 13), shape: ['rect', 'rect', 'dot', 'star'][Math.random() * 4 | 0],
      color: GOLD[Math.random() * GOLD.length | 0], life: 0, ttl: 700 * rnd(.8, 1.2)
    });
  }
  if (!raf && parts.length) { last = performance.now(); raf = requestAnimationFrame(frame); }
}
function star(r) {
  cx.beginPath();
  for (var i = 0; i < 10; i++) {
    var rr = i % 2 ? r * .45 : r, a = Math.PI * i / 5 - Math.PI / 2;
    cx[i ? 'lineTo' : 'moveTo'](rr * Math.cos(a), rr * Math.sin(a));
  }
  cx.closePath(); cx.fill();
}
function draw(p) {
  var rem = 1 - p.life / p.ttl, al = rem < .25 ? rem / .25 : 1;
  cx.save(); cx.translate(p.x, p.y); cx.rotate(p.rot); cx.scale(1, Math.cos(p.tp));
  cx.globalAlpha = Math.max(0, al); cx.fillStyle = p.color;
  if (p.shape === 'rect') cx.fillRect(-p.s / 2, -p.s * .28, p.s, p.s * .56);
  else if (p.shape === 'dot') { cx.beginPath(); cx.arc(0, 0, p.s * .4, 0, 6.283); cx.fill(); }
  else star(p.s * .8);
  cx.restore();
}
function frame(t) {
  var dt = Math.min((t - last) / 16.67, 3); last = t;
  cx.clearRect(0, 0, W, H);
  for (var i = parts.length - 1; i >= 0; i--) {
    var p = parts[i], dd = Math.pow(p.d, dt);
    p.vx *= dd; p.vy *= dd; p.vy += p.g * dt;
    p.x += (p.vx + Math.sin(p.wp) * p.wob) * dt; p.y += p.vy * dt;
    p.wp += .05 * dt; p.rot += p.vr * dt; p.tp += p.ts * dt; p.life += dt;
    if (p.y > H + 60 || p.life > p.ttl) { parts.splice(i, 1); continue; }
    draw(p);
  }
  raf = parts.length ? requestAnimationFrame(frame) : 0;
}
function goldRain(ms) {
  if (RM) return;
  var t0 = performance.now();
  var id = setInterval(function () {
    var k = 1 - (performance.now() - t0) / ms;
    if (k <= 0) { clearInterval(id); return; }
    spawn(Math.max(1, Math.round(70 * .05 * k)));
  }, 50);
  timers.push(id);
}
function clearFx() {
  timers.forEach(function (t) { clearTimeout(t); clearInterval(t); });
  timers = []; parts = [];
  if (raf) { cancelAnimationFrame(raf); raf = 0; }
  if (cx) cx.clearRect(0, 0, W, H);
}

/* ---------- markup ---------- */
function build() {
  var n = CFG.quotes.length;
  var items = CFG.quotes.map(function (s, i) {
    return '<li><button type="button" class="hs-isqm-areabtn" data-dot="' + i + '" aria-pressed="' + (i === 0) + '"><span class="num">0' + (i + 1) + '</span><span>' + s.area + '</span></button></li>';
  }).join('');
  var slides = CFG.quotes.map(function (s, i) {
    return '<div class="hs-isqm-slide' + (i === 0 ? ' on' : '') + '" role="group" aria-roledescription="slide" aria-label="' + (i + 1) + ' of ' + n + '">' +
      '<div class="hs-isqm-medalcol">' + MEDAL + '<p class="hs-isqm-mini">Rated outstanding</p></div>' +
      '<div class="hs-isqm-slidebody"><div class="hs-isqm-ahead"><span class="hs-isqm-amedal">' + MEDAL + '</span><h3 class="hs-isqm-area hs-isqm-t-' + ((s.title || s.area).length > 75 ? 'l' : ((s.title || s.area).length > 33 ? 'm' : 's')) + '">' + (s.title || s.area) + '</h3></div>' +
      '<blockquote>“' + (s.qs ? '<span class="hs-isqm-qf">' + s.q + '</span><span class="hs-isqm-qs">' + s.qs + '</span>' : s.q) + '”</blockquote>' +
      '<p class="src">' + CFG.source + '</p></div></div>';
  }).join('');
  var dots = CFG.quotes.map(function (s, i) {
    return '<button type="button" data-dot="' + i + '" aria-label="Go to quote ' + (i + 1) + ' of ' + n + '"' + (i === 0 ? ' aria-current="true"' : '') + '></button>';
  }).join('');
  root = document.createElement('div');
  root.id = 'hs-isqm';
  root.innerHTML =
    '<div class="hs-isqm-bd" data-close></div>' +
    '<div class="hs-isqm-card" role="dialog" aria-modal="true" aria-labelledby="hs-isqm-title">' +
      '<button type="button" class="hs-isqm-x xin" data-close aria-label="Close announcement"><span class="ct">Close</span><span class="ci" aria-hidden="true">&times;</span></button>' +
      '<div class="hs-isqm-top"><div class="hs-isqm-logo"><img src="' + CFG.logo + '" alt="ISQM International Schools Quality Mark, Gold Accreditation"></div>' +
      '<div class="hs-isqm-titles"><p class="hs-isqm-eyebrow">Proud to announce</p><h2 class="hs-isqm-title" id="hs-isqm-title">HeadStart is <span class="gy">Gold!</span></h2></div>' +
      '<p class="hs-isqm-blurb"><span class="bf">' + CFG.blurb + '</span><span class="bs">' + CFG.blurbShort + '</span></p></div>' +
      '<hr class="hs-isqm-rule">' +
      '<p class="hs-isqm-listhead">Rated outstanding in all eight areas</p>' +
      '<ul class="hs-isqm-areas">' + items + '</ul>' +
      '<div class="hs-isqm-qpanel"><div class="hs-isqm-slides" aria-live="polite">' + slides + '</div></div>' +
      '<div class="hs-isqm-foot"><a class="hs-isqm-btn" data-read href="' + CFG.readUrl + '">Explore our accreditations' + ARROW + '</a>' +
      '<div class="hs-isqm-ctl"><button type="button" class="hs-isqm-arrow" data-step="-1" aria-label="Previous quote">&lsaquo;</button><div class="hs-isqm-dots">' + dots + '</div><button type="button" class="hs-isqm-arrow" data-step="1" aria-label="Next quote">&rsaquo;</button></div></div>' +
    '</div>' +
    '<button type="button" class="hs-isqm-x xv" data-close aria-label="Close announcement"><span class="ct">Close</span><span class="ci" aria-hidden="true">&times;</span></button>' +
    '<canvas class="hs-isqm-cv" aria-hidden="true"></canvas>';
  card = root.querySelector('.hs-isqm-card');
  cv = root.querySelector('.hs-isqm-cv');
  cx = cv.getContext('2d');
}

/* ---------- slider ---------- */
function go(i, dir) {
  var n = CFG.quotes.length, prev = slide, next = (i + n) % n;
  if (next !== prev) {
    dir = dir || (next > prev ? 1 : -1);
    var els = root.querySelectorAll('.hs-isqm-slide');
    els.forEach(function (el, k) { if (k !== prev && k !== next) { el.classList.add('notrans'); el.classList.remove('on', 'out-l', 'from-l'); } });
    var inc = els[next], out = els[prev];
    inc.classList.add('notrans'); inc.classList.remove('out-l'); inc.classList.toggle('from-l', dir < 0);
    void inc.offsetWidth;
    els.forEach(function (el) { el.classList.remove('notrans'); });
    out.classList.remove('on', 'from-l');
    if (dir > 0) out.classList.add('out-l'); else out.classList.remove('out-l');
    inc.classList.remove('from-l'); inc.classList.add('on');
  }
  slide = next;
  root.querySelectorAll('.hs-isqm-areabtn').forEach(function (b, k) { b.setAttribute('aria-pressed', String(k === slide)); });
  root.querySelectorAll('.hs-isqm-dots [data-dot]').forEach(function (d, k) { if (k === slide) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
}

/* ---------- open / close ---------- */
function onKey(e) {
  if (e.key === 'Escape') { close(); return; }
  if (e.key === 'ArrowRight') go(slide + 1, 1);
  if (e.key === 'ArrowLeft') go(slide - 1, -1);
  if (e.key === 'Tab') {
    var f = Array.prototype.filter.call(root.querySelectorAll('button, a[href]'), function (el) { return el.getClientRects().length; });
    if (!f.length) return;
    var a = f[0], z = f[f.length - 1];
    if (e.shiftKey && (document.activeElement === a || !root.contains(document.activeElement))) { e.preventDefault(); z.focus(); }
    else if (!e.shiftKey && (document.activeElement === z || !root.contains(document.activeElement))) { e.preventDefault(); a.focus(); }
  }
}
function close() {
  if (closed) return;
  closed = true;
  document.removeEventListener('keydown', onKey, true);
  window.removeEventListener('resize', sizeCv);
  clearFx();
  root.classList.add('hs-isqm-out');
  setTimeout(function () {
    if (root.parentNode) root.parentNode.removeChild(root);
    document.documentElement.classList.remove('hs-isqm-open');
    if (prevFocus && prevFocus.focus) { try { prevFocus.focus({ preventScroll: true }); } catch (e) {} }
  }, 230);
}
function open() {
  build();
  prevFocus = document.activeElement;
  document.body.appendChild(root);
  document.documentElement.classList.add('hs-isqm-open');
  try { window.localStorage.setItem(KEY, String(Date.now())); } catch (e) {}
  sizeCv(); window.addEventListener('resize', sizeCv);
  document.addEventListener('keydown', onKey, true);
  var sx = null;
  root.querySelector('.hs-isqm-qpanel').addEventListener('pointerdown', function (e) { sx = e.clientX; });
  root.addEventListener('pointerup', function (e) {
    if (sx === null) return;
    var dx = e.clientX - sx; sx = null;
    if (Math.abs(dx) > 40) go(slide + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1);
  });
  root.addEventListener('click', function (e) {
    var step = e.target.closest('[data-step]'), dot = e.target.closest('[data-dot]');
    if (step) { go(slide + (+step.getAttribute('data-step')), +step.getAttribute('data-step')); return; }
    if (dot) { go(+dot.getAttribute('data-dot')); return; }
    if (e.target.closest('[data-close]')) close();
  });
  goldRain(4600);
  card.setAttribute('tabindex', '-1');
  setTimeout(function () { if (!closed) { try { card.focus({ preventScroll: true }); } catch (e) {} } }, 700);
}

// Open after the page has loaded, so the pop-up doesn't compete with the page's first paint (LCP).
function later() { setTimeout(open, 600); }
if (document.readyState === 'complete') later(); else window.addEventListener('load', later);
})();
</script>
    <?php
}

/* ---- Shortcode assets ---------------------------------------------------- */
/* The CSS is printed right beside the first shortcode of each request, so it also
   works when Divi renders the shortcode by itself in the Visual Builder. The JS is
   delegated (it does not need to find the markup at load) and prints in the footer,
   on pages that use a shortcode and inside the builder. */
$GLOBALS['hs_isqm_need'] = array();
function hs_isqm_in_builder() {
    if ( isset( $_GET['et_fb'] ) || isset( $_GET['et_tb'] ) ) return true;
    return function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled();
}
function hs_isqm_css_once( $key, $css ) {
    if ( ! empty( $GLOBALS['hs_isqm_need'][ $key . '_css' ] ) ) return '';
    $GLOBALS['hs_isqm_need'][ $key . '_css' ] = true;
    return '<style id="hs-' . $key . '-css">' . $css . '</style>';
}
function hs_isqm_inline_css() {
    ob_start();
    ?>
.hs-isqm-in { --i-navy: #003057; --i-gold: #f0b323; --i-fg: #ffffff; --i-muted: #c9d6e4; --i-line: rgba(255, 255, 255, .2); --i-panel: rgba(255, 255, 255, .08); --i-head: var(--hs-font-head, 'Montserrat', 'Helvetica Neue', Arial, sans-serif); --i-body: var(--hs-font-body, 'Open Sans', system-ui, sans-serif); display: block; width: 100%; font-family: var(--i-body); color: var(--i-fg) !important; text-align: left; }
.hs-isqm-in.boxed { background: #003057; border-radius: 12px; padding: 22px 24px 20px; }
.hs-isqm-in, .hs-isqm-in *, .hs-isqm-in *::before, .hs-isqm-in *::after { box-sizing: border-box; }
.hs-isqm-in button { font-family: inherit; cursor: pointer; }
.hs-isqm-in .hs-isqm-rule { border: 0; border-top: 1px solid var(--i-line); margin: 0 0 14px; }
.hs-isqm-in .hs-isqm-listhead { margin: 0 0 12px; font: 700 13px/1.2 var(--i-head); letter-spacing: .14em; text-transform: uppercase; color: var(--i-gold) !important; }
.hs-isqm-in .hs-isqm-areas { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-template-rows: repeat(4, auto); grid-auto-flow: column; gap: 2px 20px; }
.hs-isqm-in .hs-isqm-areas li { margin: 0; padding: 0; list-style: none; }
.hs-isqm-in .hs-isqm-areabtn { width: 100%; display: grid; grid-template-columns: 24px minmax(0, 1fr); gap: 10px; align-items: start; text-align: left; background: transparent; border: 0; border-radius: 4px; padding: 6px; color: var(--i-fg) !important; font: 600 14px/1.35 var(--i-head); text-transform: none; letter-spacing: 0; }
.hs-isqm-in .hs-isqm-areabtn:hover { background: rgba(255, 255, 255, .08); }
.hs-isqm-in .hs-isqm-areabtn[aria-pressed="true"] { font-weight: 700; box-shadow: inset 0 0 0 1.5px var(--i-gold); background: rgba(255, 255, 255, .06); }
.hs-isqm-in .hs-isqm-areabtn .num { display: block; width: 24px; padding-top: 2px; font: 700 12px/1 var(--i-head); color: var(--i-gold) !important; }
.hs-isqm-in .hs-isqm-qpanel { margin-top: 14px; padding: 18px 22px 16px; background: var(--i-panel); border-radius: 8px; }
.hs-isqm-in .hs-isqm-slides { display: grid; overflow: hidden; }
.hs-isqm-in .hs-isqm-slide { grid-area: 1 / 1; visibility: hidden; transform: translateX(100%); transition: transform .5s cubic-bezier(.45, 0, .2, 1), visibility 0s linear .5s; display: grid; grid-template-columns: 112px minmax(0, 1fr); gap: 22px; align-items: center; }
.hs-isqm-in .hs-isqm-slide.on { visibility: visible; transform: none; transition: transform .5s cubic-bezier(.45, 0, .2, 1); }
.hs-isqm-in .hs-isqm-slide.out-l { transform: translateX(-100%); }
.hs-isqm-in .hs-isqm-slide.from-l { transform: translateX(-100%); }
.hs-isqm-in .hs-isqm-slide.notrans { transition: none !important; }
.hs-isqm-in .hs-isqm-medalcol { text-align: center; }
.hs-isqm-in .hs-isqm-medalcol svg { display: block; width: 64px; height: auto; margin: 0 auto 10px; }
.hs-isqm-in .hs-isqm-mini { margin: 0; font: 700 10.5px/1.35 var(--i-head); letter-spacing: .1em; text-transform: uppercase; color: var(--i-gold) !important; }
.hs-isqm-in .hs-isqm-ahead { display: contents; }
.hs-isqm-in .hs-isqm-amedal { display: none; }
.hs-isqm-in .hs-isqm-slide h3.hs-isqm-area { font: 700 11px/1.45 var(--i-head); letter-spacing: .06em; text-transform: uppercase; min-height: 2.9em; margin: 0 0 8px; padding: 0; color: var(--i-gold) !important; }
.hs-isqm-in .hs-isqm-slide blockquote { margin: 0 0 8px; padding: 0; border: 0; background: none; font-size: 17.5px; line-height: 1.6; color: var(--i-fg) !important; text-wrap: pretty; quotes: none; font-style: normal; }
.hs-isqm-in .hs-isqm-slide blockquote::before, .hs-isqm-in .hs-isqm-slide blockquote::after { content: none; }
.hs-isqm-in .hs-isqm-slide blockquote strong { font-weight: 700; color: var(--i-fg) !important; background: linear-gradient(transparent 62%, rgba(240, 179, 35, .45) 62%); }
.hs-isqm-in .hs-isqm-slide .src { margin: 0; font-size: 12.5px; line-height: 1.3; color: var(--i-muted) !important; border-left: 2px solid var(--i-gold); padding-left: 5px; }
.hs-isqm-in .hs-isqm-qs { display: none; }
.hs-isqm-in .hs-isqm-foot { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-top: 16px; }
.hs-isqm-in .hs-isqm-btn { border: 0; border-radius: 4px; padding: 14px 24px; font: 700 .75rem/1 var(--i-head); letter-spacing: .05em; text-transform: uppercase; display: inline-flex; align-items: center; gap: 8px; transition: all .2s ease; background: var(--i-gold); color: var(--i-navy) !important; text-decoration: none; }
.hs-isqm-in .hs-isqm-btn:hover, .hs-isqm-in .hs-isqm-btn:focus-visible { background: #fff; color: var(--i-navy) !important; box-shadow: 0 4px 12px rgba(240, 179, 35, .4); transform: translateY(-2px); }
.hs-isqm-in .hs-isqm-btn svg { width: 12px; height: 12px; fill: currentColor; transition: transform .2s ease; }
.hs-isqm-in .hs-isqm-btn:hover svg { transform: translateX(4px); }
.hs-isqm-in .hs-isqm-ctl { display: flex; align-items: center; gap: 12px; margin-left: auto; }
.hs-isqm-in .hs-isqm-arrow { width: 38px; height: 38px; padding: 0; border-radius: 50%; border: 1.5px solid var(--i-line); background: transparent; color: var(--i-fg) !important; font-size: 20px; line-height: 1; display: grid; place-items: center; }
.hs-isqm-in .hs-isqm-arrow:hover { border-color: var(--i-gold); }
.hs-isqm-in .hs-isqm-dots { display: flex; gap: 7px; }
.hs-isqm-in .hs-isqm-dots button { width: 10px; height: 10px; padding: 0; border-radius: 50%; border: 0; background: rgba(255, 255, 255, .3); }
.hs-isqm-in .hs-isqm-dots button[aria-current="true"] { background: var(--i-gold); box-shadow: 0 0 0 2px #003057, 0 0 0 3.5px var(--i-gold); }
.hs-isqm-in button:focus-visible, .hs-isqm-in a:focus-visible { outline: 3px solid #ffd466; outline-offset: 2px; }
@media (max-width: 640px) {
  .hs-isqm-in.boxed { padding: 18px 14px 16px; }
  .hs-isqm-in .hs-isqm-areas { grid-template-columns: minmax(0, 1fr); grid-template-rows: none; grid-auto-flow: row; }
  .hs-isqm-in .hs-isqm-areabtn { padding: 4px 6px; font-size: 13.5px; }
  .hs-isqm-in .hs-isqm-qf { display: none; }
  .hs-isqm-in .hs-isqm-qs { display: inline; }
  .hs-isqm-in .hs-isqm-qpanel { padding: 14px 14px 12px; }
  .hs-isqm-in .hs-isqm-slide { grid-template-columns: minmax(0, 1fr); gap: 0; align-items: start; }
  .hs-isqm-in .hs-isqm-medalcol { display: none; }
  .hs-isqm-in .hs-isqm-ahead { display: flex; align-items: center; gap: 10px; margin: 0; }
  .hs-isqm-in .hs-isqm-amedal { display: none; }
  .hs-isqm-in .hs-isqm-amedal svg { display: block; width: 30px; height: auto; }
  /* every title block is the same height; short titles are set larger, long ones smaller, so slides match */
  .hs-isqm-in .hs-isqm-slide h3.hs-isqm-area { font-size: 10.5px; line-height: 1.35; min-height: 42px; margin: 0; display: flex; align-items: center; }
  .hs-isqm-in .hs-isqm-slide h3.hs-isqm-t-s { font-size: 14px; }
  .hs-isqm-in .hs-isqm-slide h3.hs-isqm-t-l { font-size: 9px; }
  /* phones: fixed gap from title to quote, source line on the bottom of the card; the card is as tall as its tallest slide */
  .hs-isqm-in .hs-isqm-slidebody { align-self: stretch; display: flex; flex-direction: column; }
  .hs-isqm-in .hs-isqm-slide blockquote { margin: 10px 0 12px; padding: 0; }
  .hs-isqm-in .hs-isqm-slide .src { margin-top: auto; }
  .hs-isqm-in .hs-isqm-slide blockquote { font-size: 15.5px; line-height: 1.55; }
  .hs-isqm-in .hs-isqm-slide .src { font-size: 11.5px; }
  .hs-isqm-in .hs-isqm-foot { flex-direction: column; align-items: stretch; gap: 10px; }
  .hs-isqm-in .hs-isqm-foot .hs-isqm-btn { width: 100%; justify-content: center; }
  .hs-isqm-in .hs-isqm-ctl { justify-content: center; gap: 8px; margin-left: 0; }
  .hs-isqm-in .hs-isqm-arrow { width: 34px; height: 34px; }
  .hs-isqm-in .hs-isqm-dots { gap: 5px; }
  .hs-isqm-in .hs-isqm-dots button { width: 8px; height: 8px; }
}
@media (prefers-reduced-motion: reduce) { .hs-isqm-in .hs-isqm-slide { transition: none !important; } }
    <?php
    return ob_get_clean();
}
function hs_isqm_qbar_css() {
    ob_start();
    ?>
.hs-qbar-wrap { container-type: inline-size; display: block; width: 100%; margin: 0; padding: 0; } /* no outer spacing: set it in Divi */
.hs-qbar { --qb-gold: var(--hs-gold, #f0b323); --qb-dark: var(--hs-dark, #003057); --qb-slider: #003057; --qb-mute: #b9cadb; --qb-head: var(--hs-font-head, 'Montserrat', 'Helvetica Neue', Arial, sans-serif); --qb-body: var(--hs-font-body, 'Open Sans', system-ui, sans-serif); position: relative; display: grid; margin: 0; padding: 0; border: 0; grid-template-columns: 250px minmax(0, 1fr); height: 172px; background: var(--qb-slider); color: #fff !important; font-family: var(--qb-body); overflow: hidden; text-align: left; }
.hs-qbar, .hs-qbar *, .hs-qbar *::before, .hs-qbar *::after { box-sizing: border-box; }
.hs-qbar .qb-bd { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 10px 12px; background: var(--qb-gold); color: var(--qb-dark) !important; font-family: var(--qb-head); text-transform: uppercase; }
.hs-qbar .qb-bd svg { width: 34px; height: auto; display: block; margin: 0 0 4px; fill: var(--qb-dark); overflow: visible; }
.hs-qbar .qb-bd .r { font: 700 14px/1.1 var(--qb-head); letter-spacing: .04em; color: var(--qb-dark) !important; }
.hs-qbar .qb-bd .o { font: 800 27px/1.05 var(--qb-head); letter-spacing: .01em; color: var(--qb-dark) !important; }
.hs-qbar .qb-bd .a { font: 700 9.5px/1.3 var(--qb-head); letter-spacing: .03em; margin-top: 2px; color: var(--qb-dark) !important; }
.hs-qbar .qb-bd .by { font: 700 10px/1.6 var(--qb-head); margin-top: 6px; padding-top: 1px; overflow: visible; flex: none; text-transform: none; letter-spacing: 0; color: var(--qb-dark) !important; }
.hs-qbar .qb-stage { position: relative; min-width: 0; overflow: hidden; }
.hs-qbar .qb-track { display: flex; height: 100%; transition: transform .45s cubic-bezier(.2, .7, .2, 1); }
.hs-qbar .qb-slide { flex: 0 0 100%; min-width: 0; height: 100%; padding: 0 130px 0 36px; display: flex; flex-direction: column; justify-content: center; gap: 6px; overflow: hidden; }
.hs-qbar .qb-slide h3 { font: 700 clamp(20px, 2vw, 24px)/1.2 var(--qb-head) !important; margin: 0 !important; padding: 0 !important; color: #fff !important; text-wrap: balance; text-transform: none !important; letter-spacing: 0 !important; }
.hs-qbar .qb-slide p { font: 400 clamp(16px, 1.75vw, 20px)/1.45 var(--qb-body) !important; margin: 0 !important; padding: 0 !important; max-width: none; color: #fff !important; }
.hs-qbar .qb-slide .src { font-size: 12px; color: var(--qb-mute) !important; margin-top: 2px; }
.hs-qbar .qb-slide strong, .hs-qbar .qb-slide .hl { color: var(--qb-gold) !important; font-weight: 700; }
.hs-qbar .qb-kicker { font: 700 12.5px var(--qb-head); letter-spacing: .14em; text-transform: uppercase; color: var(--qb-gold) !important; }
.hs-qbar .qb-kicker.qb-std { font-size: 10.5px; line-height: 1.35; letter-spacing: .06em; } /* full standard titles are long: smaller and tighter than the intro kicker */
.hs-qbar .qb-statrow { display: flex; align-items: center; gap: 20px; }
.hs-qbar .qb-bigcol { flex: none; text-align: center; }
.hs-qbar .qb-big { font: 800 clamp(44px, 5vw, 60px)/1 var(--qb-head); color: #fff !important; }
.hs-qbar .qb-big small { color: var(--qb-gold) !important; font-size: .55em; margin-left: 2px; }
.hs-qbar .qb-cap { font: 700 10.5px/1.2 var(--qb-head); letter-spacing: .08em; text-transform: uppercase; color: var(--qb-mute) !important; margin-top: 4px; }
.hs-qbar .qb-txt { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.hs-qbar .qb-list { display: flex; flex-wrap: wrap; gap: 6px 8px; margin: 4px -100px 0 0; padding: 0; }
.hs-qbar .qb-list span { border: 1px solid rgba(255, 255, 255, .28); border-radius: 4px; padding: 4px 10px; font: 600 12.5px var(--qb-head); white-space: nowrap; color: #fff !important; }
.hs-qbar .qb-list.sm { gap: 5px 6px; }
.hs-qbar .qb-list.sm span { font-size: 10.5px; padding: 2px 7px; }
.hs-qbar .qb-list span.gold { border-color: var(--qb-gold); color: var(--qb-gold) !important; }
.hs-qbar .qb-arrows { position: absolute; top: 10px; right: 16px; display: flex; gap: 8px; align-items: center; z-index: 30; }
.hs-qbar .qb-count { font: 700 12px var(--qb-head); letter-spacing: .08em; color: var(--qb-mute) !important; margin-right: 6px; }
.hs-qbar .qb-count b { color: var(--qb-gold) !important; }
.hs-qbar .qb-arrows button { width: 40px; height: 32px; margin: 0; background: rgba(255, 255, 255, .05); border: 1px solid rgba(255, 255, 255, .2); border-radius: 4px; color: var(--qb-gold) !important; display: flex; align-items: center; justify-content: center; font-size: 18px; line-height: 1; cursor: pointer; transition: all .3s ease; -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px); padding: 0; text-decoration: none; }
.hs-qbar .qb-arrows button:hover { background: var(--qb-gold); color: var(--qb-dark) !important; border-color: var(--qb-gold); }
.hs-qbar .qb-arrows button:focus-visible { outline: 2px solid var(--qb-gold); outline-offset: 2px; }
@container (max-width: 1100px) { .hs-qbar .qb-list.sm span:nth-last-child(-n+12) { display: none; } }
@container (max-width: 760px) {
  .hs-qbar { grid-template-columns: minmax(0, 1fr); grid-template-rows: auto 1fr; height: auto; }
  .hs-qbar .qb-bd { padding: .5rem 1rem; border-radius: 0 0 15px 15px; }
  .hs-qbar .qb-bd svg { width: 30px; margin: 0 0 2px; }
  .hs-qbar .qb-bd .r { font-size: 12.8px; margin-bottom: -2px; }
  .hs-qbar .qb-bd .o { font-size: 26px; }
  .hs-qbar .qb-bd .by { margin-top: 6px; }
  .hs-qbar .qb-stage { height: 284px; }
  .hs-qbar .qb-slide { padding: 10px 16px 44px 16px; justify-content: center; align-items: center; text-align: center; gap: 5px; }
  .hs-qbar .qb-slide h3 { font-size: 18px !important; }
  .hs-qbar .qb-slide p { font-size: 15px !important; line-height: 1.45 !important; }
  .hs-qbar .qb-kicker { font-size: 11.5px; }
  .hs-qbar .qb-kicker.qb-std { font-size: 10px; }
  .hs-qbar .qb-statrow { flex-direction: column; gap: 4px; }
  .hs-qbar .qb-txt { align-items: center; }
  .hs-qbar .qb-big { font-size: 36px; }
  .hs-qbar .qb-list { gap: 5px 6px; margin-right: 0; justify-content: center; }
  .hs-qbar .qb-list span { font-size: 11px; padding: 3px 7px; }
  .hs-qbar .qb-list .x { display: none; }
  .hs-qbar .qb-list.two { display: grid; grid-template-columns: 1fr 1fr; width: 100%; gap: 6px; }
  .hs-qbar .qb-list.two span { white-space: normal; text-align: center; display: flex; align-items: center; justify-content: center; padding: 4px 6px; line-height: 1.25; }
  .hs-qbar .qb-arrows { top: auto; bottom: 6px; left: 0; right: 0; justify-content: space-between; padding: 0 15px; }
  .hs-qbar .qb-count { order: 2; margin: 0; }
  .hs-qbar .qb-arrows .prev { order: 1; }
  .hs-qbar .qb-arrows .next { order: 3; }
}
@media (prefers-reduced-motion: reduce) { .hs-qbar .qb-track { transition: none; } }
    <?php
    return ob_get_clean();
}
add_action( 'wp_footer', 'hs_isqm_print_assets', 98 );
add_action( 'admin_footer', 'hs_isqm_print_assets', 98 );
function hs_isqm_print_assets() {
    $need    = isset( $GLOBALS['hs_isqm_need'] ) ? $GLOBALS['hs_isqm_need'] : array();
    $builder = hs_isqm_in_builder();
    if ( ( ! empty( $need['inline'] ) || $builder ) && empty( $need['inline_js'] ) ) {
        $GLOBALS['hs_isqm_need']['inline_js'] = true;
        if ( $builder && empty( $need['inline_css'] ) ) { $GLOBALS['hs_isqm_need']['inline_css'] = true; echo '<style id="hs-inline-css">' . hs_isqm_inline_css() . '</style>'; }
        ?>
<script id="hs-isqm-inline-js">
(function () {
/* Delegated, so it also works for blocks inserted after page load (Divi builder). */
if (window.hsIsqmInline) return;
window.hsIsqmInline = true;
function go(el, i, dir) {
  var slides = el.querySelectorAll('.hs-isqm-slide'), n = slides.length;
  if (!n) return;
  var prev = 0;
  slides.forEach(function (s, k) { if (s.classList.contains('on')) prev = k; });
  var next = (i + n) % n;
  if (next !== prev) {
    dir = dir || (next > prev ? 1 : -1);
    slides.forEach(function (s, k) { if (k !== prev && k !== next) { s.classList.add('notrans'); s.classList.remove('on', 'out-l', 'from-l'); } });
    var inc = slides[next], out = slides[prev];
    inc.classList.add('notrans'); inc.classList.remove('out-l'); inc.classList.toggle('from-l', dir < 0);
    void inc.offsetWidth;
    slides.forEach(function (s) { s.classList.remove('notrans'); });
    out.classList.remove('on', 'from-l');
    if (dir > 0) out.classList.add('out-l'); else out.classList.remove('out-l');
    inc.classList.remove('from-l'); inc.classList.add('on');
  }
  el.querySelectorAll('.hs-isqm-areabtn').forEach(function (b, k) { b.setAttribute('aria-pressed', String(k === next)); });
  el.querySelectorAll('.hs-isqm-dots [data-dot]').forEach(function (d, k) { if (k === next) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
}
function cur(el) {
  var c = 0;
  el.querySelectorAll('.hs-isqm-slide').forEach(function (s, k) { if (s.classList.contains('on')) c = k; });
  return c;
}
document.addEventListener('click', function (e) {
  var el = e.target.closest && e.target.closest('.hs-isqm-in');
  if (!el) return;
  var step = e.target.closest('[data-step]'), dot = e.target.closest('[data-dot]');
  if (step) { var d = +step.getAttribute('data-step'); go(el, cur(el) + d, d); }
  else if (dot) go(el, +dot.getAttribute('data-dot'));
});
var sx = null, sel = null;
document.addEventListener('pointerdown', function (e) {
  var p = e.target.closest && e.target.closest('.hs-isqm-in .hs-isqm-qpanel');
  sel = p ? p.closest('.hs-isqm-in') : null; sx = p ? e.clientX : null;
});
document.addEventListener('pointerup', function (e) {
  if (sx === null || !sel) return;
  var dx = e.clientX - sx, el = sel; sx = null; sel = null;
  if (Math.abs(dx) > 40) go(el, cur(el) + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1);
});
})();
</script>
        <?php
    }
    if ( ( ! empty( $need['qbar'] ) || $builder ) && empty( $need['qbar_js'] ) ) {
        $GLOBALS['hs_isqm_need']['qbar_js'] = true;
        if ( $builder && empty( $need['qbar_css'] ) ) { $GLOBALS['hs_isqm_need']['qbar_css'] = true; echo '<style id="hs-qbar-css">' . hs_isqm_qbar_css() . '</style>'; }
        ?>
<script id="hs-qbar-js">
(function () {
/* Delegated, so it also works for bars inserted after page load (Divi builder). */
if (window.hsQbar) return;
window.hsQbar = true;
function go(bar, k) {
  var track = bar.querySelector('.qb-track'), slides = bar.querySelectorAll('.qb-slide'), n = slides.length;
  if (!track || !n) return;
  var i = ((k % n) + n) % n;
  track.style.transform = 'translateX(-' + (i * 100) + '%)';
  track.setAttribute('data-i', i);
  var c = bar.querySelector('[data-cur]'); if (c) c.textContent = i + 1;
  slides.forEach(function (s, j) { s.setAttribute('aria-hidden', j === i ? 'false' : 'true'); });
}
function idx(bar) { var t = bar.querySelector('.qb-track'); return t ? (parseInt(t.getAttribute('data-i'), 10) || 0) : 0; }
document.addEventListener('click', function (e) {
  var b = e.target.closest && e.target.closest('.hs-qbar .qb-arrows button');
  if (!b) return;
  var bar = b.closest('.hs-qbar');
  go(bar, idx(bar) + (b.classList.contains('prev') ? -1 : 1));
});
document.addEventListener('keydown', function (e) {
  var bar = e.target.closest && e.target.closest('.hs-qbar');
  if (!bar) return;
  if (e.key === 'ArrowLeft') go(bar, idx(bar) - 1);
  if (e.key === 'ArrowRight') go(bar, idx(bar) + 1);
});
var x0 = null, xb = null;
document.addEventListener('touchstart', function (e) {
  var bar = e.target.closest && e.target.closest('.hs-qbar');
  xb = bar; x0 = bar ? e.touches[0].clientX : null;
}, { passive: true });
document.addEventListener('touchend', function (e) {
  if (x0 === null || !xb) return;
  var d = e.changedTouches[0].clientX - x0, bar = xb; x0 = null; xb = null;
  if (Math.abs(d) > 40) go(bar, idx(bar) + (d < 0 ? 1 : -1));
});
})();
</script>
        <?php
    }
}

/* ---- 2. Shortcode [hs_isqm_gold] ---------------------------------------- */
add_shortcode( 'hs_isqm_gold', 'hs_isqm_sc_inline' );
function hs_isqm_sc_inline( $atts ) {
    $a = shortcode_atts( array(
        'show'    => 'heading,areas,quotes,button',
        'link'    => '',
        'boxed'   => 'no',
        'heading' => 'Rated outstanding in all eight areas',
    ), $atts, 'hs_isqm_gold' );
    $show = array_map( 'trim', explode( ',', strtolower( $a['show'] ) ) );
    $has  = function ( $k ) use ( $show ) { return in_array( $k, $show, true ); };
    $GLOBALS['hs_isqm_need']['inline'] = true;

    $quotes = hs_isqm_quotes();
    $n      = count( $quotes );
    $medal  = hs_isqm_medal();
    $arrow  = '<svg class="arr" viewBox="0 0 448 512" aria-hidden="true"><path d="M438.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L338.8 224 32 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l306.7 0-105.4 105.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l160-160z"/></svg>';
    $boxed  = in_array( strtolower( $a['boxed'] ), array( 'yes', 'true', '1' ), true );

    $h = hs_isqm_css_once( 'inline', hs_isqm_inline_css() ) . '<div class="hs-isqm-in' . ( $boxed ? ' boxed' : '' ) . '">';
    if ( $has( 'heading' ) ) {
        $h .= '<hr class="hs-isqm-rule"><p class="hs-isqm-listhead">' . esc_html( $a['heading'] ) . '</p>';
    }
    if ( $has( 'areas' ) ) {
        $h .= '<ul class="hs-isqm-areas">';
        foreach ( $quotes as $i => $s ) {
            $h .= '<li><button type="button" class="hs-isqm-areabtn" data-dot="' . $i . '" aria-pressed="' . ( $i === 0 ? 'true' : 'false' ) . '"><span class="num">0' . ( $i + 1 ) . '</span><span>' . esc_html( $s['area'] ) . '</span></button></li>';
        }
        $h .= '</ul>';
    }
    if ( $has( 'quotes' ) ) {
        $h .= '<div class="hs-isqm-qpanel"><div class="hs-isqm-slides" aria-live="polite">';
        foreach ( $quotes as $i => $s ) {
            $q = isset( $s['qs'] ) ? '<span class="hs-isqm-qf">' . $s['q'] . '</span><span class="hs-isqm-qs">' . $s['qs'] . '</span>' : $s['q'];
            $h .= '<div class="hs-isqm-slide' . ( $i === 0 ? ' on' : '' ) . '" role="group" aria-roledescription="slide" aria-label="' . ( $i + 1 ) . ' of ' . $n . '">'
                . '<div class="hs-isqm-medalcol">' . $medal . '<p class="hs-isqm-mini">Rated outstanding</p></div>'
                . '<div class="hs-isqm-slidebody"><div class="hs-isqm-ahead"><span class="hs-isqm-amedal">' . $medal . '</span><h3 class="hs-isqm-area hs-isqm-t-' . hs_isqm_tsize( isset( $s['title'] ) ? $s['title'] : $s['area'] ) . '">' . esc_html( isset( $s['title'] ) ? $s['title'] : $s['area'] ) . '</h3></div>'
                . '<blockquote>“' . $q . '”</blockquote>'
                . '<p class="src">Education Development Trust (EDT) ISQM Accreditation Report, 2026</p></div></div>';
        }
        $h .= '</div></div>';
    }
    if ( $has( 'button' ) || $has( 'quotes' ) ) {
        $h .= '<div class="hs-isqm-foot">';
        if ( $has( 'button' ) ) $h .= '<a class="hs-isqm-btn" href="' . esc_url( hs_isqm_link( $a['link'] ) ) . '">Explore our accreditations' . $arrow . '</a>';
        if ( $has( 'quotes' ) ) {
            $h .= '<div class="hs-isqm-ctl"><button type="button" class="hs-isqm-arrow" data-step="-1" aria-label="Previous quote">&lsaquo;</button><div class="hs-isqm-dots">';
            for ( $i = 0; $i < $n; $i++ ) {
                $h .= '<button type="button" data-dot="' . $i . '" aria-label="Go to quote ' . ( $i + 1 ) . ' of ' . $n . '"' . ( $i === 0 ? ' aria-current="true"' : '' ) . '></button>';
            }
            $h .= '</div><button type="button" class="hs-isqm-arrow" data-step="1" aria-label="Next quote">&rsaquo;</button></div>';
        }
        $h .= '</div>';
    }
    return $h . '</div>';
}

/* ---- 3. Shortcode [hs_quality_bar] -------------------------------------- */
function hs_qbar_slides() {
    $quotes = hs_isqm_quotes();
    usort( $quotes, function ( $a, $b ) { return ( isset( $a['qb'] ) ? $a['qb'] : 99 ) - ( isset( $b['qb'] ) ? $b['qb'] : 99 ); } ); // keep the quality bar in its original order
    $other  = array(
        array( 'type' => 'stat', 'big' => '1600', 'sup' => '+', 'cap' => 'Students', 'h' => 'A Truly Global Community', 'p' => 'A culturally diverse learning environment across <strong>2 campuses</strong> with over <strong>1600 students</strong> representing families from all over the world.' ),
        array( 'type' => 'text', 'h' => '<span class="hl">100%</span> IGCSE Pass Rate', 'p' => 'Reflecting our unwavering commitment to foundational academic success, an outstanding <strong>42% of our IGCSE students received A* or A Grades</strong>.' ),
        array( 'type' => 'chips', 'h' => 'Globally Recognised &amp; Accredited', 'items' => array( '!ISQM Gold', 'Cambridge International', 'FOBISIA Member', 'White Rose Maths Champion School', 'Pearson Edexcel Approved', 'Duke of Edinburgh’s Award' ) ),
        array( 'type' => 'stat', 'big' => '47', 'sup' => '%', 'cap' => 'A or A* Grades', 'h' => 'A-Level Excellence', 'p' => 'Academic rigour proven by students consistently achieving Cambridge <strong>‘Top in Thailand’</strong> and <strong>‘Top in the World’</strong> awards.' ),
        array( 'type' => 'chips', 'sm' => 1, 'core' => 9, 'h' => 'Pathways to the <span class="hl">World’s Best Universities</span>', 'items' => array( 'Stanford University', 'Yale University', 'Ivy League Universities', 'University of Cambridge', 'University of Edinburgh', 'Durham University', 'University of Bristol', 'University of Manchester', 'University of York', 'University of Amsterdam', 'University of Twente', 'ESSEC Business School', 'University of Warwick', 'University of St Andrews', 'Newcastle University', 'Cardiff University', 'University of Glasgow', 'Lancaster University', 'Loughborough University', 'Utrecht University', 'University of Groningen', 'Eindhoven University of Technology', 'Royal Veterinary College', 'Curtin University', 'Chulalongkorn University', 'Mahidol University', 'Thammasat University', 'Prince of Songkla University', 'Srinakharinwirot University' ) ),
        array( 'type' => 'chips', 'two' => 1, 'h' => 'Environmental &amp; Social Responsibility', 'items' => array( 'Zero-single-use-plastic policies', 'Integrated sustainability curriculum', 'Regular coastal &amp; beach cleanups', 'Active student-led Eco-Committees', '80% solar-powered operations', 'Dedicated local community outreach' ) ),
        array( 'type' => 'stat', 'big' => '60', 'sup' => '%', 'cap' => '', 'h' => 'BTEC Vocational Success', 'p' => 'In our specialized career pathways, an impressive <strong>60% earned a Distinction Star or Distinction</strong> in their Year 13 BTEC grades.' ),
    );
    $slides = array( array( 'type' => 'intro' ) );
    foreach ( $quotes as $i => $q ) {
        $slides[] = array( 'type' => 'quote', 'area' => isset( $q['title'] ) ? $q['title'] : $q['area'], 'q' => $q['q'] );
        if ( isset( $other[ $i ] ) ) $slides[] = $other[ $i ];
    }
    return $slides;
}

add_shortcode( 'hs_quality_bar', 'hs_qbar_sc' );
function hs_qbar_sc( $atts ) {
    $GLOBALS['hs_isqm_need']['qbar'] = true;
    $award = '<svg viewBox="0 -40 448 590" aria-hidden="true"><path d="M245.9-25.9c-13.4-8.2-30.3-8.2-43.7 0-24.4 14.9-39.5 18.9-68.1 18.3-15.7-.4-30.3 8.1-37.9 21.9-13.7 25.1-24.8 36.2-49.9 49.9-13.8 7.5-22.2 22.2-21.9 37.9 .7 28.6-3.4 43.7-18.3 68.1-8.2 13.4-8.2 30.3 0 43.7 14.9 24.4 18.9 39.5 18.3 68.1-.4 15.7 8.1 30.3 21.9 37.9 22.1 12.1 33.3 22.1 45.1 41.5L42.7 458.5c-5.9 11.9-1.1 26.3 10.7 32.2l86 43c11.5 5.7 25.5 1.4 31.7-9.8l52.8-95.1 52.8 95.1c6.2 11.2 20.2 15.6 31.7 9.8l86-43c11.9-5.9 16.7-20.3 10.7-32.2l-48.6-97.2c11.7-19.4 23-29.4 45.1-41.5 13.8-7.5 22.2-22.2 21.9-37.9-.7-28.6 3.4-43.7 18.3-68.1 8.2-13.4 8.2-30.3 0-43.7-14.9-24.4-18.9-39.5-18.3-68.1 .4-15.7-8.1-30.3-21.9-37.9-25.1-13.7-36.2-24.8-49.9-49.9-7.5-13.8-22.2-22.2-37.9-21.9-28.6 .7-43.7-3.4-68.1-18.3zM224 96a96 96 0 1 1 0 192 96 96 0 1 1 0-192z"/></svg>';
    $slides = hs_qbar_slides();
    $out = hs_isqm_css_once( 'qbar', hs_isqm_qbar_css() ) . '<div class="hs-qbar-wrap"><div class="hs-qbar" role="region" aria-roledescription="carousel" aria-label="Quality and accreditation highlights" tabindex="0">'
        . '<div class="qb-bd">' . $award . '<div class="r">Rated</div><div class="o">Outstanding</div><div class="a">In all eight areas</div><div class="by">by Education Development Trust</div></div>'
        . '<div class="qb-stage"><div class="qb-track">';
    foreach ( $slides as $s ) {
        $out .= '<div class="qb-slide" role="group" aria-roledescription="slide">';
        switch ( $s['type'] ) {
            case 'intro':
                $out .= '<span class="qb-kicker">ISQM Gold · Education Development Trust</span><p>Independently assessed and rated Outstanding in <strong>all eight areas</strong>, from teaching and curriculum to care, community and leadership.</p>';
                break;
            case 'quote':
                $out .= '<span class="qb-kicker qb-std">' . esc_html( $s['area'] ) . '</span><p>“' . $s['q'] . '”</p><span class="src">Education Development Trust ISQM Accreditation Report, 2026</span>';
                break;
            case 'stat':
                $out .= '<div class="qb-statrow"><div class="qb-bigcol"><div class="qb-big">' . $s['big'] . '<small>' . $s['sup'] . '</small></div>' . ( $s['cap'] !== '' ? '<div class="qb-cap">' . esc_html( $s['cap'] ) . '</div>' : '' ) . '</div><div class="qb-txt"><h3>' . $s['h'] . '</h3><p>' . $s['p'] . '</p></div></div>';
                break;
            case 'text':
                $out .= '<h3>' . $s['h'] . '</h3><p>' . $s['p'] . '</p>';
                break;
            case 'chips':
                $cls = 'qb-list' . ( ! empty( $s['sm'] ) ? ' sm' : '' ) . ( ! empty( $s['two'] ) ? ' two' : '' );
                $out .= '<h3>' . $s['h'] . '</h3><div class="' . $cls . '">';
                foreach ( $s['items'] as $k => $item ) {
                    $c = array();
                    if ( strpos( $item, '!' ) === 0 ) { $item = substr( $item, 1 ); $c[] = 'gold'; }
                    if ( ! empty( $s['core'] ) && $k >= $s['core'] ) $c[] = 'x';
                    $out .= '<span' . ( $c ? ' class="' . implode( ' ', $c ) . '"' : '' ) . '>' . $item . '</span>';
                }
                $out .= '</div>';
                break;
        }
        $out .= '</div>';
    }
    $out .= '</div><div class="qb-arrows"><button type="button" class="prev" aria-label="Previous slide">&#8249;</button><span class="qb-count" aria-live="polite"><b data-cur>1</b> / ' . count( $slides ) . '</span><button type="button" class="next" aria-label="Next slide">&#8250;</button></div></div></div></div>';
    return $out;
}

/* ---- 4. Settings page ---------------------------------------------------- */
function hs_isqm_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $o = hs_isqm_opts();
    $f = 'hs_isqm_settings';
    ?>
<div class="wrap">
  <h1>ISQM Gold</h1>
  <p>Controls for the ISQM Gold announcement pop-up. The two shortcodes below keep working whatever the pop-up setting is.</p>
  <form method="post" action="options.php">
    <?php settings_fields( 'hs_isqm_group' ); ?>
    <table class="form-table" role="presentation">
      <tr><th scope="row">Pop-up</th><td>
        <label><input type="checkbox" name="<?php echo $f; ?>[enabled]" value="1" <?php checked( $o['enabled'], 1 ); ?>> Show the announcement pop-up sitewide</label>
        <p class="description">Untick to switch it off everywhere. The confetti is part of the pop-up, so it stops too.</p>
      </td></tr>
      <tr><th scope="row"><label for="hs_isqm_days">Days before it shows again</label></th><td>
        <input id="hs_isqm_days" type="number" min="0" max="365" step="1" class="small-text" name="<?php echo $f; ?>[repeat_days]" value="<?php echo esc_attr( $o['repeat_days'] ); ?>">
        <p class="description">A visitor who has seen it is reminded again after this many days. Use 0 to show it on every page load (handy for testing).</p>
      </td></tr>
      <tr><th scope="row">Show between</th><td>
        <input type="date" name="<?php echo $f; ?>[start]" value="<?php echo esc_attr( $o['start'] ); ?>"> and <input type="date" name="<?php echo $f; ?>[end]" value="<?php echo esc_attr( $o['end'] ); ?>">
        <p class="description">First and last day shown (site time). Leave a date empty for no limit.</p>
      </td></tr>
      <tr><th scope="row"><label for="hs_isqm_link">“Explore our accreditations” link</label></th><td>
        <input id="hs_isqm_link" type="text" class="regular-text" name="<?php echo $f; ?>[link]" value="<?php echo esc_attr( $o['link'] ); ?>">
        <p class="description">A full address, or a path such as /accreditations/.</p>
      </td></tr>
      <tr><th scope="row">Weekly updates</th><td>
        <label><input type="checkbox" name="<?php echo $f; ?>[weekly]" value="1" <?php checked( $o['weekly'], 1 ); ?>> Show the pop-up on weekly update pages</label>
        <p class="description">Weekly update pages are any page whose address contains /weekly-update/ (both campuses). Untick to hide it on all of them. To hide it on single weeks, use the box below.</p>
      </td></tr>
      <tr><th scope="row"><label for="hs_isqm_excl">Pages to leave out</label></th><td>
        <textarea id="hs_isqm_excl" class="large-text code" rows="7" name="<?php echo $f; ?>[exclude]" placeholder="https://headstartphuket.com/chaofah/weekly-update/2026/10/weekly-update-8/&#10;https://headstartphuket.com/cherngtalay/weekly-update/2026/10/weekly-update-8/&#10;/our-school/*"><?php echo esc_textarea( $o['exclude'] ); ?></textarea>
        <p class="description">One link per line, full address or path. The pop-up (and its confetti) does not show on these pages. Put <code>*</code> at the end to include everything below a path, for example <code>/chaofah/weekly-update/2026/10/*</code>. Week 9 is the same link with weekly-update-9.</p>
      </td></tr>
      <tr><th scope="row">Administrators</th><td>
        <label><input type="checkbox" name="<?php echo $f; ?>[admins]" value="1" <?php checked( $o['admins'], 1 ); ?>> Show the pop-up to logged-in administrators</label>
        <p class="description">Untick to stop it appearing while you edit the site. You can still preview it by adding <code>?hs_isqm=1</code> to any page address.</p>
      </td></tr>
      <tr><th scope="row">Show again to everyone</th><td>
        <label><input type="checkbox" name="<?php echo $f; ?>[reset]" value="1"> Tick and save to show the pop-up again to every visitor, including people who have already seen it</label>
        <p class="description">Current reset code: <code><?php echo esc_html( $o['version'] ); ?></code></p>
      </td></tr>
    </table>
    <?php submit_button(); ?>
  </form>
  <p><a class="button" href="<?php echo esc_url( add_query_arg( 'hs_isqm', '1', home_url( '/' ) ) ); ?>" target="_blank" rel="noopener">Preview the pop-up</a> <span class="description">Opens the home page with the pop-up forced on. If the preview does not appear on a local site, add <code>?hs_isqm=1</code> to another page.</span></p>

  <hr>
  <h2>Shortcodes</h2>
  <table class="widefat striped" style="max-width:900px">
    <thead><tr><th>Shortcode</th><th>What it does</th></tr></thead>
    <tbody>
      <tr><td><code>[hs_isqm_gold]</code></td><td>The eight areas and the quote slider inline, with the Explore our accreditations button. Place it in a Divi Code or Text module on a navy section.</td></tr>
      <tr><td><code>[hs_isqm_gold boxed="yes"]</code></td><td>The same on its own navy card, for light sections.</td></tr>
      <tr><td><code>[hs_isqm_gold show="areas,quotes"]</code></td><td>Choose the parts: <code>heading</code>, <code>areas</code>, <code>quotes</code>, <code>button</code>. Add <code>link="/news/"</code> to change the button link for this block.</td></tr>
      <tr><td><code>[hs_quality_bar]</code></td><td>The Quality Bar slider with the ISQM Gold badge, the eight EDT quotes and the existing slides.</td></tr>
    </tbody>
  </table>
  <h2>Live preview</h2>
  <p class="description">Both shortcodes as visitors will see them.</p>
  <div style="background:#003057;padding:24px;max-width:960px;margin-bottom:24px"><?php echo do_shortcode( '[hs_isqm_gold]' ); ?></div>
  <div style="max-width:1100px;margin-bottom:32px"><?php echo do_shortcode( '[hs_quality_bar]' ); ?></div>
</div>
    <?php
}
