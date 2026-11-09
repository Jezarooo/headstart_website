<?php
/**
 * Share-link tests. Run on headstart.local: wp eval-file work/share-links-all-pages/tests/wp-tests.php
 * Temporary pages and posts are created and force-deleted. Their count rows are removed afterwards.
 */
$pass = 0; $fail = 0; $made = array();
$t = function ( $name, $ok, $detail = '' ) use ( &$pass, &$fail ) {
    if ( $ok ) { $pass++; echo "  ok    $name\n"; }
    else { $fail++; echo "  FAIL  $name" . ( '' !== $detail ? " — $detail" : '' ) . "\n"; }
};
$path = function ( $url ) { return (string) wp_parse_url( $url, PHP_URL_PATH ); };

hs_sl_share_install();
global $wpdb;
$t( 'table exists', hs_sl_share_table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', hs_sl_share_table() ) ) );

$page = function ( $slug, $parent = 0, $status = 'publish', $extra = array() ) use ( &$made ) {
    $id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => $slug, 'post_name' => $slug, 'post_parent' => $parent, 'post_status' => $status ) + $extra );
    $made[] = $id;
    return $id;
};
$post = function ( $slug, $date, $status = 'publish', $extra = array() ) use ( &$made ) {
    $id = wp_insert_post( array( 'post_type' => 'post', 'post_title' => $slug, 'post_name' => $slug, 'post_date' => $date, 'post_status' => $status ) + $extra );
    $made[] = $id;
    wp_set_object_terms( $id, 'chaofah', 'hs_post_campus' );
    return $id;
};

echo "\nPost links\n";
$p1 = $post( 'chaofah-hs-test-july', '2026-07-31 12:00:00' );
$p2 = $post( 'chaofah-hs-test-aug', '2026-08-01 12:00:00' );
$t( 'WhatsApp: full year', '/chaofah/2025-26/chaofah-hs-test-july' === $path( hs_sl_share_url( $p1, 'whatsapp' ) ), hs_sl_share_url( $p1, 'whatsapp' ) );
$t( 'Facebook: short year', '/chaofah/25-26/chaofah-hs-test-july' === $path( hs_sl_share_url( $p1, 'facebook' ) ), hs_sl_share_url( $p1, 'facebook' ) );
$t( '31 July is the old school year, 1 Aug the new one', '/chaofah/2026-27/chaofah-hs-test-aug' === $path( hs_sl_share_url( $p2, 'whatsapp' ) ) );
$r = hs_sl_share_resolve( '/chaofah/2025-26/chaofah-hs-test-july' );
$t( 'resolves: WhatsApp, post-share', $r && $p1 === $r[0] && 'whatsapp' === $r[2] && 'post-share' === $r[3] && 'chaofah-hs-test-july' === $r[4] );
$r = hs_sl_share_resolve( '/chaofah/25-26/chaofah-hs-test-july/' );
$t( 'resolves: Facebook, with a trailing slash', $r && 'facebook' === $r[2] );
$t( 'an impossible school year is refused', null === hs_sl_share_resolve( '/chaofah/2025-99/chaofah-hs-test-july' ) );
$t( 'an unknown slug is refused', null === hs_sl_share_resolve( '/chaofah/2025-26/no-such-post-hs' ) );
wp_set_object_terms( $p1, array( 'chaofah', 'cherngtalay' ), 'hs_post_campus' );
clean_post_cache( $p1 );
$both = hs_sl_share_url( $p1, 'whatsapp' );
$t( 'two campuses: starts /both-campuses/', 0 === strpos( $path( $both ), '/both-campuses/2025-26/' ), $both );
$t( 'and it resolves', ( $r = hs_sl_share_resolve( $path( $both ) ) ) && $p1 === $r[0] );
$t( 'a link made before a campus change still works', ( $r = hs_sl_share_resolve( '/chaofah/2025-26/chaofah-hs-test-july' ) ) && $p1 === $r[0] );
wp_update_post( array( 'ID' => $p2, 'post_name' => 'chaofah-hs-test-renamed' ) );
$r = hs_sl_share_resolve( '/chaofah/2026-27/chaofah-hs-test-aug' );
$t( 'a renamed Post: old link goes to the new URL', $r && $p2 === $r[0] && false !== strpos( $r[1], 'chaofah-hs-test-renamed' ) );

echo "\nPage links\n";
$a = $page( 'hs-test-parent' );
$b = $page( 'hs-test-child', $a );
$c = $page( 'hs-test-child', $page( 'hs-test-other' ) );
$t( 'WhatsApp: full path', '/wa/hs-test-parent/hs-test-child' === $path( hs_sl_share_url( $b, 'whatsapp' ) ), hs_sl_share_url( $b, 'whatsapp' ) );
$t( 'Facebook: full path', '/fb/hs-test-parent/hs-test-child' === $path( hs_sl_share_url( $b, 'facebook' ) ) );
$r = hs_sl_share_resolve( '/wa/hs-test-parent/hs-test-child' );
$t( 'the same slug under two parents stays separate', $r && $b === $r[0] && ( $r2 = hs_sl_share_resolve( '/wa/hs-test-other/hs-test-child' ) ) && $c === $r2[0] );
$t( 'label uses hyphens, campaign is page-share', $r && 'hs-test-parent-hs-test-child' === $r[4] && 'page-share' === $r[3] );
$en = get_page_by_path( 'en/foundation', OBJECT, 'page' );
if ( $en ) {
    $r = hs_sl_share_resolve( '/wa/en/foundation' ); $r2 = hs_sl_share_resolve( '/fb/th/foundation' );
    $t( '/en/ and /th/ resolve to their own page', $r && $en->ID === $r[0] && $r2 && $r2[0] !== $r[0] && 'facebook' === $r2[2] );
}
$front = (int) get_option( 'page_on_front' );
$r = hs_sl_share_resolve( '/wa' );
$t( '/wa is the homepage, with its tags', $r && $front === $r[0] && home_url( '/' ) === $r[1] && 'home' === $r[4] );
$t( '/fb is Facebook', ( $r = hs_sl_share_resolve( '/fb/' ) ) && 'facebook' === $r[2] );
if ( $front ) { $t( "the homepage's own link is /wa", '/wa' === $path( hs_sl_share_url( $front, 'whatsapp' ) ) ); }
$t( 'lookalike paths are not links', null === hs_sl_share_resolve( '/wax/foo' ) && null === hs_sl_share_resolve( '/xx/hs-test-parent' ) );

wp_update_post( array( 'ID' => $a, 'post_name' => 'hs-test-renamed' ) );
$r = hs_sl_share_resolve( '/wa/hs-test-parent' );
$t( 'a renamed Page: old link goes to the new URL', $r && $a === $r[0] && false !== strpos( $r[1], 'hs-test-renamed' ) );
$r = hs_sl_share_resolve( '/wa/hs-test-parent/hs-test-child' );
$t( 'a renamed parent: its child keeps working', $r && $b === $r[0] && false !== strpos( $r[1], 'hs-test-renamed/hs-test-child' ) );
$t( 'the new path works too', ( $r = hs_sl_share_resolve( '/wa/hs-test-renamed/hs-test-child' ) ) && $b === $r[0] );
$taker = $page( 'hs-test-parent' );
$r = hs_sl_share_resolve( '/wa/hs-test-parent' );
$t( 'a current Page beats an old path', $r && $taker === $r[0] );

echo "\nSkip rules\n";
$d1 = $post( 'chaofah-hs-test-draft', '2026-09-01 12:00:00', 'draft' );
$d2 = $page( 'hs-test-draft', 0, 'draft' );
$d3 = $post( 'chaofah-hs-test-locked', '2026-09-01 12:00:00', 'publish', array( 'post_password' => 'x' ) );
$d4 = $page( 'hs-test-locked', 0, 'publish', array( 'post_password' => 'x' ) );
$t( 'draft Post: no link, and refused', '' === hs_sl_share_url( $d1 ) && null === hs_sl_share_resolve( '/chaofah/2026-27/chaofah-hs-test-draft' ) );
$t( 'draft Page: no link, and refused', '' === hs_sl_share_url( $d2 ) && null === hs_sl_share_resolve( '/wa/hs-test-draft' ) );
$t( 'password-protected Post: refused', '' === hs_sl_share_url( $d3 ) && null === hs_sl_share_resolve( '/chaofah/2026-27/chaofah-hs-test-locked' ) );
$t( 'password-protected Page: refused', '' === hs_sl_share_url( $d4 ) && null === hs_sl_share_resolve( '/wa/hs-test-locked' ) );
$wu = get_posts( array( 'post_type' => 'weekly_update', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );
if ( $wu ) { $t( 'a weekly update gets no share link', '' === hs_sl_share_url( $wu[0] ) ); }

echo "\nClick counts\n";
hs_sl_share_count( $a, 'whatsapp' );
$c1 = hs_sl_share_counts( array( $a ) );
$t( 'first click adds a row', isset( $c1[ $a ] ) && 1 === $c1[ $a ]['whatsapp'] && 0 === $c1[ $a ]['facebook'] );
hs_sl_share_count( $a, 'whatsapp' ); hs_sl_share_count( $a, 'facebook' );
$c1 = hs_sl_share_counts( array( $a ) );
$t( 'repeat clicks add up per channel', 2 === $c1[ $a ]['whatsapp'] && 1 === $c1[ $a ]['facebook'] );
$t( 'an item with no clicks has no row', array() === hs_sl_share_counts( array( $b ) ) );
hs_sl_share_count( 0, 'whatsapp' );
$t( 'id 0 is never counted', '0' === (string) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . hs_sl_share_table() . ' WHERE post_id = 0' ) );

echo "\nBox and columns\n";
$t( 'the box is registered for Pages and Posts', has_action( 'add_meta_boxes_page', 'hs_sl_share_add_box' ) && has_action( 'add_meta_boxes_post', 'hs_sl_share_add_box' ) );
$cols = hs_sl_share_columns( array( 'title' => 'Title', 'date' => 'Date' ) );
$t( 'Clicks sits just before Date', array( 'title', 'hs_sl_clicks', 'date' ) === array_keys( $cols ) );
ob_start(); hs_sl_share_box( get_post( $a ) ); $html = ob_get_clean();
$t( 'the box shows both links and counts', false !== strpos( $html, '/wa/hs-test-renamed' ) && false !== strpos( $html, '/fb/hs-test-renamed' ) && false !== strpos( $html, '2 clicks' ) && false !== strpos( $html, '1 click<' ) );
ob_start(); hs_sl_share_box( get_post( $d2 ) ); $html = ob_get_clean();
$t( 'a draft shows the waiting message', false !== strpos( $html, 'Links appear once this is published' ) );

foreach ( $made as $id ) {
    $wpdb->delete( hs_sl_share_table(), array( 'post_id' => $id ) );
    wp_delete_post( $id, true );
}
$left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name LIKE '%hs-test%' AND post_type IN ('page','post')" );
$t( 'test items cleaned up', 0 === $left, "$left left" );
printf( "\n%d passed, %d failed\n", $pass, $fail );
