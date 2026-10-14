<?php

/**
 * Weekly-Update-Short-Links
 */
/**
 * Short links for weekly updates, a per-channel click counter, and GTM data.
 *
 *   /{campus}/{2026-27}/weekly-update-{N}   WhatsApp
 *   /{campus}/{2026-27}/update-{N}          Facebook
 *
 * Each link counts the click (bots and link previews excluded) and 302-redirects to the
 * update's permalink with UTM tags. School year = publish date, August cut-off.
 * No rewrite rules: the path is matched in parse_request, so no permalink flush is needed.
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
    $word = ( 'facebook' === $channel ) ? 'update-' : 'weekly-update-';
    $path = $p['campus'] . '/' . $p['year'] . '/' . $word . $p['week'];
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
    if ( ! preg_match( '#^/(' . implode( '|', hs_sl_campuses() ) . ')/(\d{4})-(\d{2})/(weekly-)?update-(\d{1,3})/?$#', $path, $m ) ) {
        return;
    }
    $campus  = $m[1];
    $start   = (int) $m[2];
    $week    = (int) $m[5];
    $channel = ( 'weekly-' === $m[4] ) ? 'whatsapp' : 'facebook';
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
