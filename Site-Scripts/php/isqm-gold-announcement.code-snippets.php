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
if ( ! defined( 'HS_ISQM_POST_URL' ) ) define( 'HS_ISQM_POST_URL', '/accreditations/' ); // "Read the full story" link
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
        array( 'area' => 'Teaching and learning', 'q' => '<strong>High-quality teaching</strong> enables pupils to acquire excellent knowledge, skills and understanding to make <strong>outstanding rates of progress</strong>.' ),
        array( 'area' => 'Care and support', 'q' => 'Pupils enjoy being part of a <strong>caring learning community</strong>. They believe that their views are listened to and acted upon.', 'qs' => 'Pupils enjoy being part of a <strong>caring learning community</strong>.' ),
        array( 'area' => 'Personal development', 'q' => 'Pupils are very proud that the school is an <strong>inclusive environment</strong> where everyone is welcomed and supported with their learning.', 'qs' => 'Pupils are very proud that the school is an <strong>inclusive environment</strong> where everyone is welcomed…' ),
        array( 'area' => 'Leadership, management and governance', 'q' => 'Staff, pupils and parents consistently describe the school as a <strong>family</strong> and an inclusive community.' ),
        array( 'area' => 'The curriculum', 'q' => 'Almost all children begin the day <strong>excited about something new to learn</strong> or revisiting prior learning.' ),
        array( 'area' => 'Accommodation and resources', 'q' => 'The <strong>high-class facilities</strong> at both sites support and enhance pupils’ experience and learning.' ),
        array( 'area' => 'Standards and achievement', 'q' => 'Pupils achieve <strong>exceptionally high standards</strong> in creative and performing arts, in physical and athletic pursuits, in computing, and in humanities.', 'qs' => 'Pupils achieve <strong>exceptionally high standards</strong> in creative and performing arts, in physical and athletic pursuits…' ),
        array( 'area' => 'Partnership with parents and the community', 'q' => 'The school’s leaders succeed in their aim to value <strong>families as essential collaborators</strong> in the life of the school.' ),
    );
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
        'logo'    => 'data:image/webp;base64,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',
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
#hs-isqm .hs-isqm-card { position: relative; z-index: 2; background: #003057; color: var(--i-fg) !important; border-radius: 12px; border: 1px solid rgba(255, 255, 255, .14); box-shadow: 0 30px 80px rgba(0, 0, 0, .55); width: min(780px, 90%); max-height: 90%; overflow: auto; padding: 28px 32px 24px; animation: hs-isqm-pop .55s cubic-bezier(.2, .9, .25, 1.1) both; animation-delay: .5s; }
#hs-isqm.hs-isqm-out .hs-isqm-bd, #hs-isqm.hs-isqm-out .hs-isqm-card { animation: hs-isqm-fadeout .22s ease forwards; }
@keyframes hs-isqm-pop { from { opacity: 0; transform: translateY(18px) scale(.96); } to { opacity: 1; transform: none; } }
@keyframes hs-isqm-fade { from { opacity: 0; } to { opacity: 1; } }
@keyframes hs-isqm-fadeout { to { opacity: 0; } }

/* close: same gold pill as the site's lightboxes */
#hs-isqm .hs-isqm-x { position: absolute; top: 20px; right: 20px; z-index: 3; display: flex; align-items: center; justify-content: center; gap: 6px; width: max-content; height: auto; background-color: rgba(240, 179, 35, .95); color: #003057 !important; border: 4px solid rgba(255, 255, 255, .3); background-clip: padding-box; -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); padding: 6px 16px 6px 20px; border-radius: 50px; box-shadow: 0 4px 15px rgba(0, 0, 0, .25); line-height: 1; transition: all .3s ease; }
#hs-isqm .hs-isqm-x:hover { background-color: #fff; transform: scale(1.05); }
#hs-isqm .hs-isqm-x .ct { font: 700 .85rem/1 var(--i-head); letter-spacing: 1px; text-transform: uppercase; color: #003057 !important; }
#hs-isqm .hs-isqm-x .ci { font-size: 1.6rem; line-height: 1; margin-top: -2px; color: #003057 !important; }

/* top: logo left, award description right */
#hs-isqm .hs-isqm-top { display: grid; grid-template-columns: auto minmax(0, 1fr); grid-template-areas: "logo titles" "logo blurb"; gap: 8px 28px; align-items: center; }
#hs-isqm .hs-isqm-logo { grid-area: logo; display: inline-block; background: #fff; border: 1px solid #fff; border-radius: 12px; padding: 10px 18px; }
#hs-isqm .hs-isqm-logo img { display: block; width: 190px; max-width: 100%; height: auto; margin: 0; }
#hs-isqm .hs-isqm-titles { grid-area: titles; align-self: end; padding-right: 112px; }
#hs-isqm .hs-isqm-eyebrow { margin: 0 0 6px; font: 700 12px/1 var(--i-head); letter-spacing: .16em; text-transform: uppercase; color: var(--i-gold-text) !important; }
#hs-isqm .hs-isqm-card h2.hs-isqm-title { font: 700 clamp(30px, 5.2vw, 42px)/1.1 var(--i-head); letter-spacing: -.5px; margin: 0 0 4px; padding: 0; color: var(--i-fg) !important; }
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
#hs-isqm .hs-isqm-slide h3.hs-isqm-area { font: 700 clamp(20px, 3vw, 23px)/1.2 var(--i-head); letter-spacing: -.01em; margin: 0 0 10px; padding: 0; color: var(--i-fg) !important; }
#hs-isqm .hs-isqm-slide blockquote { margin: 0 0 8px; padding: 0; border: 0; background: none; font-size: 17.5px; line-height: 1.6; color: var(--i-fg) !important; text-wrap: pretty; quotes: none; }
#hs-isqm .hs-isqm-slide blockquote strong { font-weight: 700; background: linear-gradient(transparent 62%, rgba(240, 179, 35, .45) 62%); }
#hs-isqm .hs-isqm-slide .src { margin: 0; font-size: 12.5px; color: var(--i-muted) !important; }
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
  #hs-isqm .hs-isqm-ahead { display: flex; align-items: center; gap: 10px; margin: 0 0 8px; }
  #hs-isqm .hs-isqm-amedal { display: block; flex: none; }
  #hs-isqm .hs-isqm-amedal svg { display: block; width: 30px; height: auto; }
  #hs-isqm .hs-isqm-slide h3.hs-isqm-area { font-size: 17px; margin: 0; }
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
      '<div class="hs-isqm-slidebody"><div class="hs-isqm-ahead"><span class="hs-isqm-amedal">' + MEDAL + '</span><h3 class="hs-isqm-area">' + s.area + '</h3></div>' +
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
      '<button type="button" class="hs-isqm-x" data-close aria-label="Close announcement"><span class="ct">Close</span><span class="ci" aria-hidden="true">&times;</span></button>' +
      '<div class="hs-isqm-top"><div class="hs-isqm-logo"><img src="' + CFG.logo + '" alt="ISQM International Schools Quality Mark, Gold Accreditation"></div>' +
      '<div class="hs-isqm-titles"><p class="hs-isqm-eyebrow">Proud to announce</p><h2 class="hs-isqm-title" id="hs-isqm-title">HeadStart is <span class="gy">Gold!</span></h2></div>' +
      '<p class="hs-isqm-blurb"><span class="bf">' + CFG.blurb + '</span><span class="bs">' + CFG.blurbShort + '</span></p></div>' +
      '<hr class="hs-isqm-rule">' +
      '<p class="hs-isqm-listhead">Rated outstanding in all eight areas</p>' +
      '<ul class="hs-isqm-areas">' + items + '</ul>' +
      '<div class="hs-isqm-qpanel"><div class="hs-isqm-slides" aria-live="polite">' + slides + '</div></div>' +
      '<div class="hs-isqm-foot"><a class="hs-isqm-btn" data-read href="' + CFG.readUrl + '">Read the full story' + ARROW + '</a>' +
      '<div class="hs-isqm-ctl"><button type="button" class="hs-isqm-arrow" data-step="-1" aria-label="Previous quote">&lsaquo;</button><div class="hs-isqm-dots">' + dots + '</div><button type="button" class="hs-isqm-arrow" data-step="1" aria-label="Next quote">&rsaquo;</button></div></div>' +
    '</div>' +
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
    var f = card.querySelectorAll('button, a[href]');
    if (!f.length) return;
    var a = f[0], z = f[f.length - 1];
    if (e.shiftKey && (document.activeElement === a || !card.contains(document.activeElement))) { e.preventDefault(); z.focus(); }
    else if (!e.shiftKey && (document.activeElement === z || !card.contains(document.activeElement))) { e.preventDefault(); a.focus(); }
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

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', open); else open();
})();
</script>
    <?php
}

/* ---- Shortcode assets (print once, only when a shortcode was used) ------- */
$GLOBALS['hs_isqm_need'] = array();
add_action( 'wp_footer', 'hs_isqm_print_assets', 98 );
add_action( 'admin_footer', 'hs_isqm_print_assets', 98 );
function hs_isqm_print_assets() {
    $need = isset( $GLOBALS['hs_isqm_need'] ) ? $GLOBALS['hs_isqm_need'] : array();
    if ( ! empty( $need['inline'] ) && empty( $need['inline_done'] ) ) {
        $GLOBALS['hs_isqm_need']['inline_done'] = true;
        ?>
<style id="hs-isqm-inline-css">
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
.hs-isqm-in .hs-isqm-slide h3.hs-isqm-area { font: 700 clamp(20px, 3vw, 23px)/1.2 var(--i-head); letter-spacing: -.01em; margin: 0 0 10px; padding: 0; color: var(--i-fg) !important; text-transform: none; }
.hs-isqm-in .hs-isqm-slide blockquote { margin: 0 0 8px; padding: 0; border: 0; background: none; font-size: 17.5px; line-height: 1.6; color: var(--i-fg) !important; text-wrap: pretty; quotes: none; font-style: normal; }
.hs-isqm-in .hs-isqm-slide blockquote::before, .hs-isqm-in .hs-isqm-slide blockquote::after { content: none; }
.hs-isqm-in .hs-isqm-slide blockquote strong { font-weight: 700; color: var(--i-fg) !important; background: linear-gradient(transparent 62%, rgba(240, 179, 35, .45) 62%); }
.hs-isqm-in .hs-isqm-slide .src { margin: 0; font-size: 12.5px; color: var(--i-muted) !important; }
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
  .hs-isqm-in .hs-isqm-ahead { display: flex; align-items: center; gap: 10px; margin: 0 0 8px; }
  .hs-isqm-in .hs-isqm-amedal { display: block; flex: none; }
  .hs-isqm-in .hs-isqm-amedal svg { display: block; width: 30px; height: auto; }
  .hs-isqm-in .hs-isqm-slide h3.hs-isqm-area { font-size: 17px; margin: 0; }
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
</style>
<script id="hs-isqm-inline-js">
(function () {
function init(el) {
  var slides = el.querySelectorAll('.hs-isqm-slide');
  if (!slides.length) return;
  var n = slides.length, cur = 0;
  function go(i, dir) {
    var prev = cur, next = (i + n) % n;
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
    cur = next;
    el.querySelectorAll('.hs-isqm-areabtn').forEach(function (b, k) { b.setAttribute('aria-pressed', String(k === cur)); });
    el.querySelectorAll('.hs-isqm-dots [data-dot]').forEach(function (d, k) { if (k === cur) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
  }
  el.addEventListener('click', function (e) {
    var step = e.target.closest('[data-step]'), dot = e.target.closest('[data-dot]');
    if (step) { var d = +step.getAttribute('data-step'); go(cur + d, d); }
    else if (dot) go(+dot.getAttribute('data-dot'));
  });
  var sx = null, panel = el.querySelector('.hs-isqm-qpanel');
  if (panel) {
    panel.addEventListener('pointerdown', function (e) { sx = e.clientX; });
    panel.addEventListener('pointerup', function (e) {
      if (sx === null) return;
      var dx = e.clientX - sx; sx = null;
      if (Math.abs(dx) > 40) go(cur + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1);
    });
  }
}
document.querySelectorAll('.hs-isqm-in').forEach(init);
})();
</script>
        <?php
    }
    if ( ! empty( $need['qbar'] ) && empty( $need['qbar_done'] ) ) {
        $GLOBALS['hs_isqm_need']['qbar_done'] = true;
        ?>
<style id="hs-qbar-css">
.hs-qbar-wrap { container-type: inline-size; width: 100%; }
.hs-qbar { --qb-gold: var(--hs-gold, #f0b323); --qb-dark: var(--hs-dark, #003057); --qb-slider: #00294d; --qb-mute: #b9cadb; --qb-head: var(--hs-font-head, 'Montserrat', 'Helvetica Neue', Arial, sans-serif); --qb-body: var(--hs-font-body, 'Open Sans', system-ui, sans-serif); position: relative; display: grid; grid-template-columns: 250px minmax(0, 1fr); height: 172px; background: var(--qb-slider); color: #fff !important; font-family: var(--qb-body); overflow: hidden; text-align: left; }
.hs-qbar, .hs-qbar *, .hs-qbar *::before, .hs-qbar *::after { box-sizing: border-box; }
.hs-qbar .qb-bd { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 10px 12px; background: var(--qb-gold); color: var(--qb-dark) !important; font-family: var(--qb-head); text-transform: uppercase; }
.hs-qbar .qb-bd svg { width: 34px; height: auto; display: block; margin: 0 0 4px; fill: var(--qb-dark); overflow: visible; }
.hs-qbar .qb-bd .r { font: 700 14px/1.1 var(--qb-head); letter-spacing: .04em; color: var(--qb-dark) !important; }
.hs-qbar .qb-bd .o { font: 800 27px/1.05 var(--qb-head); letter-spacing: .01em; color: var(--qb-dark) !important; }
.hs-qbar .qb-bd .a { font: 700 9.5px/1.3 var(--qb-head); letter-spacing: .03em; margin-top: 2px; color: var(--qb-dark) !important; }
.hs-qbar .qb-bd .by { font: 700 10px/1.3 var(--qb-head); margin-top: 8px; text-transform: none; letter-spacing: 0; color: var(--qb-dark) !important; }
.hs-qbar .qb-stage { position: relative; min-width: 0; overflow: hidden; }
.hs-qbar .qb-track { display: flex; height: 100%; transition: transform .45s cubic-bezier(.2, .7, .2, 1); }
.hs-qbar .qb-slide { flex: 0 0 100%; min-width: 0; height: 100%; padding: 0 130px 0 36px; display: flex; flex-direction: column; justify-content: center; gap: 6px; overflow: hidden; }
.hs-qbar .qb-slide h3 { font: 700 clamp(20px, 2vw, 24px)/1.2 var(--qb-head) !important; margin: 0 !important; padding: 0 !important; color: #fff !important; text-wrap: balance; text-transform: none !important; letter-spacing: 0 !important; }
.hs-qbar .qb-slide p { font: 400 clamp(16px, 1.75vw, 20px)/1.45 var(--qb-body) !important; margin: 0 !important; padding: 0 !important; max-width: none; color: #fff !important; }
.hs-qbar .qb-slide .src { font-size: 12px; color: var(--qb-mute) !important; margin-top: 2px; }
.hs-qbar .qb-slide strong, .hs-qbar .qb-slide .hl { color: var(--qb-gold) !important; font-weight: 700; }
.hs-qbar .qb-kicker { font: 700 12.5px var(--qb-head); letter-spacing: .14em; text-transform: uppercase; color: var(--qb-gold) !important; }
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
</style>
<script id="hs-qbar-js">
(function () {
function initBar(bar) {
  var track = bar.querySelector('.qb-track'), slides = bar.querySelectorAll('.qb-slide');
  if (!track || !slides.length) return;
  var n = slides.length, i = 0, cur = bar.querySelector('[data-cur]');
  function go(k) {
    i = (k + n) % n;
    track.style.transform = 'translateX(-' + (i * 100) + '%)';
    if (cur) cur.textContent = i + 1;
    slides.forEach(function (s, j) { s.setAttribute('aria-hidden', j === i ? 'false' : 'true'); });
  }
  bar.querySelector('.prev').addEventListener('click', function () { go(i - 1); });
  bar.querySelector('.next').addEventListener('click', function () { go(i + 1); });
  bar.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft') go(i - 1);
    if (e.key === 'ArrowRight') go(i + 1);
  });
  var x0 = null;
  track.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
  track.addEventListener('touchend', function (e) {
    if (x0 === null) return;
    var d = e.changedTouches[0].clientX - x0;
    if (Math.abs(d) > 40) go(i + (d < 0 ? 1 : -1));
    x0 = null;
  });
  go(0);
}
document.querySelectorAll('.hs-qbar').forEach(initBar);
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

    $h = '<div class="hs-isqm-in' . ( $boxed ? ' boxed' : '' ) . '">';
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
                . '<div class="hs-isqm-slidebody"><div class="hs-isqm-ahead"><span class="hs-isqm-amedal">' . $medal . '</span><h3 class="hs-isqm-area">' . esc_html( $s['area'] ) . '</h3></div>'
                . '<blockquote>“' . $q . '”</blockquote>'
                . '<p class="src">Education Development Trust (EDT) ISQM Accreditation Report, 2026</p></div></div>';
        }
        $h .= '</div></div>';
    }
    if ( $has( 'button' ) || $has( 'quotes' ) ) {
        $h .= '<div class="hs-isqm-foot">';
        if ( $has( 'button' ) ) $h .= '<a class="hs-isqm-btn" href="' . esc_url( hs_isqm_link( $a['link'] ) ) . '">Read the full story' . $arrow . '</a>';
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
        $slides[] = array( 'type' => 'quote', 'area' => $q['area'], 'q' => $q['q'] );
        if ( isset( $other[ $i ] ) ) $slides[] = $other[ $i ];
    }
    return $slides;
}

add_shortcode( 'hs_quality_bar', 'hs_qbar_sc' );
function hs_qbar_sc( $atts ) {
    $GLOBALS['hs_isqm_need']['qbar'] = true;
    $award = '<svg viewBox="0 -40 448 590" aria-hidden="true"><path d="M245.9-25.9c-13.4-8.2-30.3-8.2-43.7 0-24.4 14.9-39.5 18.9-68.1 18.3-15.7-.4-30.3 8.1-37.9 21.9-13.7 25.1-24.8 36.2-49.9 49.9-13.8 7.5-22.2 22.2-21.9 37.9 .7 28.6-3.4 43.7-18.3 68.1-8.2 13.4-8.2 30.3 0 43.7 14.9 24.4 18.9 39.5 18.3 68.1-.4 15.7 8.1 30.3 21.9 37.9 22.1 12.1 33.3 22.1 45.1 41.5L42.7 458.5c-5.9 11.9-1.1 26.3 10.7 32.2l86 43c11.5 5.7 25.5 1.4 31.7-9.8l52.8-95.1 52.8 95.1c6.2 11.2 20.2 15.6 31.7 9.8l86-43c11.9-5.9 16.7-20.3 10.7-32.2l-48.6-97.2c11.7-19.4 23-29.4 45.1-41.5 13.8-7.5 22.2-22.2 21.9-37.9-.7-28.6 3.4-43.7 18.3-68.1 8.2-13.4 8.2-30.3 0-43.7-14.9-24.4-18.9-39.5-18.3-68.1 .4-15.7-8.1-30.3-21.9-37.9-25.1-13.7-36.2-24.8-49.9-49.9-7.5-13.8-22.2-22.2-37.9-21.9-28.6 .7-43.7-3.4-68.1-18.3zM224 96a96 96 0 1 1 0 192 96 96 0 1 1 0-192z"/></svg>';
    $slides = hs_qbar_slides();
    $out = '<div class="hs-qbar-wrap"><div class="hs-qbar" role="region" aria-roledescription="carousel" aria-label="Quality and accreditation highlights" tabindex="0">'
        . '<div class="qb-bd">' . $award . '<div class="r">Rated</div><div class="o">Outstanding</div><div class="a">In all eight areas</div><div class="by">by Education Development Trust</div></div>'
        . '<div class="qb-stage"><div class="qb-track">';
    foreach ( $slides as $s ) {
        $out .= '<div class="qb-slide" role="group" aria-roledescription="slide">';
        switch ( $s['type'] ) {
            case 'intro':
                $out .= '<span class="qb-kicker">ISQM Gold · Education Development Trust</span><p>Independently assessed and rated Outstanding in <strong>all eight areas</strong>, from teaching and curriculum to care, community and leadership.</p>';
                break;
            case 'quote':
                $out .= '<span class="qb-kicker">' . esc_html( $s['area'] ) . '</span><p>“' . $s['q'] . '”</p><span class="src">Education Development Trust ISQM Accreditation Report, 2026</span>';
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
      <tr><th scope="row"><label for="hs_isqm_link">“Read the full story” link</label></th><td>
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
      <tr><td><code>[hs_isqm_gold]</code></td><td>The eight areas and the quote slider inline, with the Read the full story button. Place it in a Divi Code or Text module on a navy section.</td></tr>
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
