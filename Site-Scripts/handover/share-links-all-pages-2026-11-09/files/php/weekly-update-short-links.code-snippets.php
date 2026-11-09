<?php

/**
 * Weekly-Update-Short-Links
 */
/**
 * Short links for weekly updates, a per-channel click counter, and GTM data.
 *
 *   /{campus}/2026-27/weekly-update-{N}   WhatsApp
 *   /{campus}/26-27/weekly-update-{N}     Facebook
 * The year form decides the channel.
 *
 * Each link counts the click (bots and link previews excluded) and 302-redirects to the
 * update's permalink with UTM tags. School year = publish date, August cut-off.
 * No rewrite rules: the path is matched in parse_request, so no permalink flush is needed.
 *
 * Pages and Posts get share links too (section 5):
 *   /{campus}/2025-26/{post-slug}        Post, WhatsApp
 *   /{campus}/25-26/{post-slug}          Post, Facebook
 *   /wa/{page-path}  and  /fb/{page-path}  Page (no path = homepage)
 * Their click counts live in the {prefix}hs_share_links table.
 */

function hs_sl_campuses() { return array( 'chaofah', 'cherngtalay', 'both-campuses' ); }
function hs_sl_channels() { return array( 'whatsapp', 'facebook' ); }
function hs_sl_meta_key() { return '_hs_short_link_clicks'; }

/** School year label for a timestamp, e.g. 2026-27 (August cut-off). */
function hs_sl_school_year( $time ) {
    $y     = (int) date( 'Y', $time );
    $start = ( (int) date( 'n', $time ) >= 8 ) ? $y : $y - 1;
    return sprintf( '%d-%02d', $start, ( $start + 1 ) % 100 );
}

/** Campus slug, school year and week number of a published weekly update, or null. */
function hs_sl_parts( $post ) {
    $post = get_post( $post );
    if ( ! $post || 'weekly_update' !== $post->post_type || 'publish' !== $post->post_status ) {
        return null;
    }
    $week = (int) get_post_meta( $post->ID, 'hs_week_number', true );
    if ( $week < 1 ) {
        return null;
    }
    $terms = wp_get_object_terms( $post->ID, 'hs_campus', array( 'fields' => 'slugs' ) );
    if ( is_wp_error( $terms ) ) {
        return null;
    }
    $campus = '';
    foreach ( $terms as $slug ) {
        if ( in_array( $slug, hs_sl_campuses(), true ) ) { $campus = $slug; break; }
    }
    if ( '' === $campus ) {
        return null;
    }
    return array(
        'campus' => $campus,
        'year'   => hs_sl_school_year( strtotime( $post->post_date ) ),
        'week'   => $week,
    );
}

/** Short link for a weekly update and channel ('' if it has none). */
function hs_sl_url( $post, $channel = 'whatsapp' ) {
    $p = hs_sl_parts( $post );
    if ( ! $p ) {
        return '';
    }
    $year = ( 'facebook' === $channel ) ? substr( $p['year'], 2 ) : $p['year'];
    $path = $p['campus'] . '/' . $year . '/weekly-update-' . $p['week'];
    return home_url( '/' . $path );
}

function hs_sl_is_bot() {
    $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    if ( '' === $ua ) {
        return true;
    }
    return (bool) preg_match( '/whatsapp|facebookexternalhit|facebot|meta-externalagent|twitterbot|slackbot|telegrambot|linkedinbot|discordbot|skypeuripreview|googlebot|bingbot|applebot|yandex|baiduspider|duckduckbot|petalbot|bot\/|crawl|spider|preview|headless|curl|wget|python-requests/i', $ua );
}

// 1. Redirect + count.
add_action( 'parse_request', 'hs_sl_handle_request', 1 );
function hs_sl_handle_request() {
    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    if ( is_admin() || ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
        return;
    }
    $path = (string) parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH );
    $home = (string) parse_url( home_url( '/' ), PHP_URL_PATH );
    if ( '' !== $home && '/' !== $home && 0 === strpos( $path, rtrim( $home, '/' ) ) ) {
        $path = substr( $path, strlen( rtrim( $home, '/' ) ) );
    }
    if ( ! preg_match( '#^/(' . implode( '|', hs_sl_campuses() ) . ')/(\d{4}|\d{2})-(\d{2})/weekly-update-(\d{1,3})/?$#', $path, $m ) ) {
        return;
    }
    $campus  = $m[1];
    $start   = ( 2 === strlen( $m[2] ) ) ? 2000 + (int) $m[2] : (int) $m[2];
    $week    = (int) $m[4];
    $channel = ( 2 === strlen( $m[2] ) ) ? 'facebook' : 'whatsapp';
    if ( (int) $m[3] !== ( $start + 1 ) % 100 || $week < 1 ) {
        return; // Not a valid school year: let WordPress 404 it.
    }

    $ids = get_posts( array(
        'post_type'        => 'weekly_update',
        'post_status'      => 'publish',
        'posts_per_page'   => 1,
        'fields'           => 'ids',
        'no_found_rows'    => true,
        'suppress_filters' => true,
        'orderby'          => 'date',
        'order'            => 'DESC',
        'tax_query'        => array( array( 'taxonomy' => 'hs_campus', 'field' => 'slug', 'terms' => $campus ) ),
        'meta_query'       => array( array( 'key' => 'hs_week_number', 'value' => $week, 'compare' => '=', 'type' => 'NUMERIC' ) ),
        'date_query'       => array( array(
            'after'     => sprintf( '%d-08-01 00:00:00', $start ),
            'before'    => sprintf( '%d-08-01 00:00:00', $start + 1 ),
            'inclusive' => true,
        ) ),
    ) );
    if ( empty( $ids ) ) {
        return; // No such update: normal 404.
    }
    $post_id = (int) $ids[0];

    if ( 'GET' === $method && ! hs_sl_is_bot() ) {
        $counts = get_post_meta( $post_id, hs_sl_meta_key(), true );
        $counts = is_array( $counts ) ? $counts : array();
        $counts[ $channel ] = ( isset( $counts[ $channel ] ) ? (int) $counts[ $channel ] : 0 ) + 1;
        update_post_meta( $post_id, hs_sl_meta_key(), $counts );
    }

    $target = add_query_arg( array(
        'utm_source'   => $channel,
        'utm_medium'   => 'social',
        'utm_campaign' => 'weekly-update',
        'utm_content'  => $campus . '-' . sprintf( '%d-%02d', $start, ( $start + 1 ) % 100 ) . '-wu' . $week,
    ), get_permalink( $post_id ) );

    nocache_headers();
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
    header( 'X-Robots-Tag: noindex', true );
    wp_safe_redirect( $target, 302, 'HeadStart short link' );
    exit;
}

// 2. "Share links" box on the weekly update edit screen.
add_action( 'add_meta_boxes_weekly_update', function () {
    add_meta_box( 'hs-short-links', 'Share links', 'hs_sl_meta_box', 'weekly_update', 'side', 'high' );
} );
function hs_sl_meta_box( $post ) {
    if ( ! hs_sl_parts( $post ) ) {
        echo '<p style="color:#666">Links appear once this update is published with a campus and a week number.</p>';
        return;
    }
    $counts = get_post_meta( $post->ID, hs_sl_meta_key(), true );
    $counts = is_array( $counts ) ? $counts : array();
    $labels = array( 'whatsapp' => 'WhatsApp', 'facebook' => 'Facebook' );
    foreach ( hs_sl_channels() as $ch ) {
        $url = hs_sl_url( $post, $ch );
        $n   = isset( $counts[ $ch ] ) ? (int) $counts[ $ch ] : 0;
        printf(
            '<p style="margin:0 0 4px"><strong>%1$s</strong> <span style="color:#666">· %2$d %3$s</span></p>'
            . '<div style="display:flex;gap:4px;margin-bottom:12px"><input type="text" readonly value="%4$s" style="flex:1;font-size:12px" onclick="this.select()">'
            . '<button type="button" class="button hs-sl-copy" data-url="%4$s">Copy</button></div>',
            esc_html( $labels[ $ch ] ), $n, 1 === $n ? 'click' : 'clicks', esc_attr( $url )
        );
    }
    echo '<p style="color:#666;font-size:12px;margin:0">Counts exclude link previews and bots. Refresh the page to update.</p>';
    ?>
    <script>
    document.querySelectorAll('#hs-short-links .hs-sl-copy').forEach(function (b) {
        b.addEventListener('click', function () {
            var done = function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy'; }, 1500); };
            if (navigator.clipboard) { navigator.clipboard.writeText(b.dataset.url).then(done); }
            else { var i = b.previousElementSibling; i.select(); document.execCommand('copy'); done(); }
        });
    });
    </script>
    <?php
}

// 3. "Clicks" column in the weekly update list.
add_filter( 'manage_weekly_update_posts_columns', function ( $columns ) {
    $out = array();
    foreach ( $columns as $key => $title ) {
        if ( 'date' === $key ) {
            $out['hs_sl_clicks'] = 'Clicks';
        }
        $out[ $key ] = $title;
    }
    if ( ! isset( $out['hs_sl_clicks'] ) ) {
        $out['hs_sl_clicks'] = 'Clicks';
    }
    return $out;
}, 100 );
add_action( 'manage_weekly_update_posts_custom_column', function ( $column, $post_id ) {
    if ( 'hs_sl_clicks' !== $column ) {
        return;
    }
    $c = get_post_meta( $post_id, hs_sl_meta_key(), true );
    $c = is_array( $c ) ? $c : array();
    $w = isset( $c['whatsapp'] ) ? (int) $c['whatsapp'] : 0;
    $f = isset( $c['facebook'] ) ? (int) $c['facebook'] : 0;
    echo $w || $f ? sprintf( 'WA %d · FB %d', $w, $f ) : '<span style="color:#aaa">—</span>';
}, 10, 2 );

// 4. GTM data: weekly update details on its page, and gallery pop-up opens everywhere.
add_action( 'wp_head', function () {
    if ( ! is_singular( 'weekly_update' ) ) {
        return;
    }
    $p = hs_sl_parts( get_queried_object_id() );
    if ( ! $p ) {
        return;
    }
    $data = array(
        'event'          => 'weekly_update_view',
        'hs_campus'      => $p['campus'],
        'hs_school_year' => $p['year'],
        'hs_week'        => $p['week'],
    );
    echo "<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push(" . wp_json_encode( $data ) . ");</script>\n";
}, 1 );

add_action( 'wp_footer', function () {
    if ( is_admin() ) {
        return;
    }
    // Capture phase on window runs before gallery-mosaic.js (document, capture) stops the event.
    echo "<script>window.addEventListener('click',function(e){var t=e.target&&e.target.closest&&e.target.closest('.hs-gallery-trigger,.hs-gallery-trigger-split,.hs-gallery-trigger-full');if(!t)return;var s=t.closest('.hs-summary-mosaic')?'summary':(t.closest('.hs-gallery-container')?'strip':'other');window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'hs_gallery_open',hs_gallery_type:s});},true);</script>\n";
}, 99 );

// 5. Share links for Pages and Posts.

function hs_sl_share_table() {
    global $wpdb;
    return $wpdb->prefix . 'hs_share_links';
}

/** Create the click-count table once. The version flag is autoloaded, so the check costs nothing. */
add_action( 'admin_init', 'hs_sl_share_install' );
function hs_sl_share_install() {
    if ( (int) get_option( 'hs_share_links_db' ) >= 1 ) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( 'CREATE TABLE ' . hs_sl_share_table() . " (
  post_id bigint(20) unsigned NOT NULL,
  whatsapp int(10) unsigned NOT NULL DEFAULT 0,
  facebook int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (post_id)
) " . $wpdb->get_charset_collate() . ';' );
    update_option( 'hs_share_links_db', 1, true );
}

/** A Page or Post that may have share links: published and not password-protected. */
function hs_sl_share_ok( $post ) {
    return $post && in_array( $post->post_type, array( 'page', 'post' ), true )
        && 'publish' === $post->post_status && '' === $post->post_password;
}

/** Share link for a Page or Post and channel ('' if it can't have one). */
function hs_sl_share_url( $post, $channel = 'whatsapp' ) {
    $post = get_post( $post );
    if ( ! hs_sl_share_ok( $post ) ) {
        return '';
    }
    if ( 'post' === $post->post_type ) {
        // The first part of the Post's own URL: a campus, both-campuses or a location override.
        $home = (string) parse_url( home_url( '/' ), PHP_URL_PATH );
        $path = (string) parse_url( get_permalink( $post ), PHP_URL_PATH );
        if ( '' !== $home && '/' !== $home && 0 === strpos( $path, rtrim( $home, '/' ) ) ) {
            $path = substr( $path, strlen( rtrim( $home, '/' ) ) );
        }
        $parts = explode( '/', trim( $path, '/' ) );
        $year  = hs_sl_school_year( strtotime( $post->post_date ) );
        $year  = ( 'facebook' === $channel ) ? substr( $year, 2 ) : $year;
        return home_url( '/' . $parts[0] . '/' . $year . '/' . $post->post_name );
    }
    $prefix = ( 'facebook' === $channel ) ? 'fb' : 'wa';
    if ( (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
        return home_url( '/' . $prefix );
    }
    return home_url( '/' . $prefix . '/' . get_page_uri( $post ) );
}

/** Add one click. A single query, so clicks at the same moment can't overwrite each other. */
function hs_sl_share_count( $post_id, $channel ) {
    if ( $post_id < 1 || (int) get_option( 'hs_share_links_db' ) < 1 ) {
        return;
    }
    global $wpdb;
    $column = ( 'facebook' === $channel ) ? 'facebook' : 'whatsapp';
    $wpdb->query( $wpdb->prepare(
        'INSERT INTO ' . hs_sl_share_table() . " (post_id, {$column}) VALUES (%d, 1) ON DUPLICATE KEY UPDATE {$column} = {$column} + 1", // phpcs:ignore
        $post_id
    ) );
}

/** Click counts for these ids, as id => array( whatsapp, facebook ). */
function hs_sl_share_counts( $ids ) {
    $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    if ( ! $ids || (int) get_option( 'hs_share_links_db' ) < 1 ) {
        return array();
    }
    global $wpdb;
    $rows = $wpdb->get_results( 'SELECT post_id, whatsapp, facebook FROM ' . hs_sl_share_table() . ' WHERE post_id IN (' . implode( ',', $ids ) . ')' ); // phpcs:ignore
    $out  = array();
    foreach ( (array) $rows as $r ) {
        $out[ (int) $r->post_id ] = array( 'whatsapp' => (int) $r->whatsapp, 'facebook' => (int) $r->facebook );
    }
    return $out;
}

/** Find what a share-link path points at: array( post id, target URL, channel, campaign, label ), or null. */
function hs_sl_share_resolve( $path ) {
    // Pages: /wa or /fb, then the page's full path.
    if ( preg_match( '#^/(wa|fb)(?:/(.+?))?/?$#', $path, $m ) ) {
        $channel = ( 'fb' === $m[1] ) ? 'facebook' : 'whatsapp';
        if ( empty( $m[2] ) ) {
            return array( (int) get_option( 'page_on_front' ), home_url( '/' ), $channel, 'page-share', 'home' );
        }
        $page = get_page_by_path( $m[2], OBJECT, 'page' );
        if ( ! $page ) { // Renamed since the link was shared?
            $old = get_posts( array(
                'post_type'        => 'page',
                'post_status'      => 'publish',
                'posts_per_page'   => 1,
                'no_found_rows'    => true,
                'suppress_filters' => true,
                'meta_key'         => '_hs_sl_old_path',
                'meta_value'       => trim( $m[2], '/' ),
            ) );
            $page = $old ? $old[0] : null;
        }
        if ( ! hs_sl_share_ok( $page ) ) {
            return null;
        }
        return array( (int) $page->ID, get_permalink( $page ), $channel, 'page-share', str_replace( '/', '-', trim( $m[2], '/' ) ) );
    }

    // Posts: /{first part of its URL}/{school year}/{slug}. The year form decides the channel.
    if ( ! preg_match( '#^/[a-z0-9-]+/(\d{4}|\d{2})-(\d{2})/([^/]+)/?$#', $path, $m ) ) {
        return null;
    }
    $start = ( 2 === strlen( $m[1] ) ) ? 2000 + (int) $m[1] : (int) $m[1];
    if ( (int) $m[2] !== ( $start + 1 ) % 100 ) {
        return null;
    }
    $channel = ( 2 === strlen( $m[1] ) ) ? 'facebook' : 'whatsapp';
    $slug    = sanitize_title( rawurldecode( $m[3] ) );
    $args    = array(
        'post_type'        => 'post',
        'post_status'      => 'publish',
        'posts_per_page'   => 1,
        'no_found_rows'    => true,
        'suppress_filters' => true,
    );
    $found = get_posts( $args + array( 'name' => $slug ) );
    if ( ! $found ) { // Renamed since the link was shared? WordPress keeps old Post slugs.
        $found = get_posts( $args + array( 'meta_key' => '_wp_old_slug', 'meta_value' => $slug ) );
    }
    if ( ! $found || ! hs_sl_share_ok( $found[0] ) ) {
        return null;
    }
    return array( (int) $found[0]->ID, get_permalink( $found[0] ), $channel, 'post-share', $found[0]->post_name );
}

// Runs after the weekly update handler (priority 1), which exits on its own links.
add_action( 'parse_request', 'hs_sl_share_handle_request', 2 );
function hs_sl_share_handle_request() {
    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    if ( is_admin() || ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
        return;
    }
    $path = (string) parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH );
    $home = (string) parse_url( home_url( '/' ), PHP_URL_PATH );
    if ( '' !== $home && '/' !== $home && 0 === strpos( $path, rtrim( $home, '/' ) ) ) {
        $path = substr( $path, strlen( rtrim( $home, '/' ) ) );
    }
    $hit = hs_sl_share_resolve( $path );
    if ( ! $hit ) {
        return; // Not a share link, or nothing to send it to: WordPress carries on as normal.
    }
    list( $post_id, $url, $channel, $campaign, $label ) = $hit;

    if ( 'GET' === $method && ! hs_sl_is_bot() ) {
        hs_sl_share_count( $post_id, $channel );
    }

    $target = add_query_arg( array(
        'utm_source'   => $channel,
        'utm_medium'   => 'social',
        'utm_campaign' => $campaign,
        'utm_content'  => $label,
    ), $url );

    nocache_headers();
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
    header( 'X-Robots-Tag: noindex', true );
    wp_safe_redirect( $target, 302, 'HeadStart short link' );
    exit;
}

// WordPress keeps old slugs for Posts but not for Pages, so links survive a Page rename through this.
add_action( 'post_updated', 'hs_sl_share_remember_path', 10, 3 );
function hs_sl_share_remember_path( $post_id, $after, $before ) {
    if ( 'page' !== $after->post_type || 'publish' !== $before->post_status || 'publish' !== $after->post_status ) {
        return;
    }
    $parent = $before->post_parent ? get_page_uri( $before->post_parent ) : '';
    $old    = trim( $parent . '/' . $before->post_name, '/' );
    $new    = get_page_uri( $after );
    if ( ! $new || $old === $new ) {
        return;
    }
    $remember = function ( $id, $path ) {
        if ( ! in_array( $path, get_post_meta( $id, '_hs_sl_old_path' ), true ) ) {
            add_post_meta( $id, '_hs_sl_old_path', $path );
        }
    };
    $remember( $post_id, $old );
    foreach ( get_pages( array( 'child_of' => $post_id ) ) as $child ) {
        $remember( $child->ID, $old . substr( get_page_uri( $child ), strlen( $new ) ) );
    }
}

// "Share links" box on Page and Post edit screens.
add_action( 'add_meta_boxes_page', 'hs_sl_share_add_box' );
add_action( 'add_meta_boxes_post', 'hs_sl_share_add_box' );
function hs_sl_share_add_box( $post ) {
    add_meta_box( 'hs-share-links', 'Share links', 'hs_sl_share_box', $post->post_type, 'side', 'high' );
}
function hs_sl_share_box( $post ) {
    if ( ! hs_sl_share_ok( $post ) ) {
        echo '<p style="color:#666">Links appear once this is published' . ( '' !== $post->post_password ? ' without a password' : '' ) . '.</p>';
        return;
    }
    $counts = hs_sl_share_counts( array( $post->ID ) );
    $counts = isset( $counts[ $post->ID ] ) ? $counts[ $post->ID ] : array();
    $labels = array( 'whatsapp' => 'WhatsApp', 'facebook' => 'Facebook' );
    foreach ( hs_sl_channels() as $ch ) {
        $url = hs_sl_share_url( $post, $ch );
        $n   = isset( $counts[ $ch ] ) ? (int) $counts[ $ch ] : 0;
        printf(
            '<p style="margin:0 0 4px"><strong>%1$s</strong> <span style="color:#666">· %2$d %3$s</span></p>'
            . '<div style="display:flex;gap:4px;margin-bottom:12px"><input type="text" readonly value="%4$s" style="flex:1;min-width:0;font-size:12px" onclick="this.select()">'
            . '<button type="button" class="button hs-sl-copy" data-url="%4$s">Copy</button></div>',
            esc_html( $labels[ $ch ] ), $n, 1 === $n ? 'click' : 'clicks', esc_attr( $url )
        );
    }
    echo '<p style="color:#666;font-size:12px;margin:0">Counts exclude link previews and bots. Refresh the page to update.</p>';
    ?>
    <script>
    document.querySelectorAll('#hs-share-links .hs-sl-copy').forEach(function (b) {
        b.addEventListener('click', function () {
            var done = function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy'; }, 1500); };
            if (navigator.clipboard) { navigator.clipboard.writeText(b.dataset.url).then(done); }
            else { var i = b.previousElementSibling; i.select(); document.execCommand('copy'); done(); }
        });
    });
    </script>
    <?php
}

// "Clicks" column in the Pages and Posts lists.
function hs_sl_share_columns( $columns ) {
    $out = array();
    foreach ( $columns as $key => $title ) {
        if ( 'date' === $key ) {
            $out['hs_sl_clicks'] = 'Clicks';
        }
        $out[ $key ] = $title;
    }
    if ( ! isset( $out['hs_sl_clicks'] ) ) {
        $out['hs_sl_clicks'] = 'Clicks';
    }
    return $out;
}
add_filter( 'manage_page_posts_columns', 'hs_sl_share_columns', 100 );
add_filter( 'manage_post_posts_columns', 'hs_sl_share_columns', 100 );

function hs_sl_share_column( $column, $post_id ) {
    if ( 'hs_sl_clicks' !== $column ) {
        return;
    }
    static $counts = null;
    if ( null === $counts ) { // One query per list screen, not one per row.
        global $wp_query;
        $counts = hs_sl_share_counts( wp_list_pluck( (array) $wp_query->posts, 'ID' ) );
    }
    $c = isset( $counts[ $post_id ] ) ? $counts[ $post_id ] : array( 'whatsapp' => 0, 'facebook' => 0 );
    echo $c['whatsapp'] || $c['facebook'] ? sprintf( 'WA %d · FB %d', $c['whatsapp'], $c['facebook'] ) : '<span style="color:#aaa">—</span>';
}
add_action( 'manage_page_posts_custom_column', 'hs_sl_share_column', 10, 2 );
add_action( 'manage_post_posts_custom_column', 'hs_sl_share_column', 10, 2 );
