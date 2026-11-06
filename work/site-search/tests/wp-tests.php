<?php
/**
 * Site search: WordPress-side tests (review T6). Run against the local site:
 *   app/public: php wp-cli.phar --skip-themes eval-file ../../work/site-search/tests/wp-tests.php
 * Creates and deletes its own temporary posts, files and log rows. Exit code 1 on any failure.
 */

$fails = 0;
$t     = function ( $name, $ok ) use ( &$fails ) {
	echo ( $ok ? 'ok    ' : 'FAIL  ' ) . $name . "\n";
	$fails += $ok ? 0 : 1;
};
$tmp = function ( $content ) {
	$f = wp_tempnam( 'hs-search-test' );
	file_put_contents( $f, $content );
	return $f;
};

/* CSV: formula escaping (S1), round trip, limits (S6), campus key case (B3) */
$t( 'csv cell escapes =', "'=1+1" === hs_search_csv_cell( '=1+1' ) );
$t( 'csv cell escapes + - @', "'+x" === hs_search_csv_cell( '+x' ) && "'-x" === hs_search_csv_cell( '-x' ) && "'@x" === hs_search_csv_cell( '@x' ) );
$t( 'csv cell leaves text alone', 'fees, cost' === hs_search_csv_cell( 'fees, cost' ) && '' === hs_search_csv_cell( '' ) );
$t( 'csv uncell round trip', '=HYPERLINK("x")' === hs_search_csv_uncell( hs_search_csv_cell( '=HYPERLINK("x")' ) ) && "it's" === hs_search_csv_uncell( "it's" ) );

$f = $tmp( "\xEF\xBB\xBFtype,on,key,values,info\ncampus,1,Chaofah,\"chaofah, city\",\ngroup,1,,\"'-kit, uniform\",\nexpand,1,ks1,\"year 1, year 2\",\nreport,,=evil,1 search,read-only\nbogus,1,x,y,\n" );
$p = hs_search_vocab_parse_csv( $f );
unlink( $f );
$t( 'csv parses', is_array( $p ) );
$t( 'csv campus key any case', is_array( $p ) && 'chaofah, city' === ( $p['vocab']['campus']['chaofah'] ?? '' ) );
$t( 'csv group unescaped', is_array( $p ) && '-kit, uniform' === ( $p['vocab']['groups'][0]['w'] ?? '' ) );
$t( 'csv report rows ignored, unknown type warned', is_array( $p ) && 1 === count( $p['warn'] ) && false !== strpos( $p['warn'][0], 'bogus' ) );

$f = $tmp( "type,on,key,values,info\n" . str_repeat( "group,1,,\"a, b\",\n", 5001 ) );
$t( 'csv row limit', is_wp_error( hs_search_vocab_parse_csv( $f ) ) );
unlink( $f );
$f = $tmp( "type,on,key,values,info\n" . str_repeat( 'x', HS_SEARCH_CSV_MAX_BYTES + 1 ) );
$t( 'csv size limit', is_wp_error( hs_search_vocab_parse_csv( $f ) ) );
unlink( $f );
$f = $tmp( "name,value\n" );
$t( 'csv wrong header', is_wp_error( hs_search_vocab_parse_csv( $f ) ) );
unlink( $f );

/* Link check (S4) */
$t( 'url: javascript blocked', '' === hs_search_safe_url( 'javascript:alert(1)' ) && '' === hs_search_safe_url( "java\tscript:x" ) && '' === hs_search_safe_url( ' JAVASCRIPT:x' ) );
$t( 'url: data/vbscript blocked', '' === hs_search_safe_url( 'data:text/html,x' ) && '' === hs_search_safe_url( 'vbscript:x' ) );
$t( 'url: allowed kinds kept', '/contact-us/#school-tours' === hs_search_safe_url( '/contact-us/#school-tours' ) && 'https://a.b/c?d=1' === hs_search_safe_url( 'https://a.b/c?d=1' ) && 'mailto:x@y.z' === hs_search_safe_url( 'mailto:x@y.z' ) && 'tel:+6676' === hs_search_safe_url( 'tel:+6676' ) );

/* Skip rule (S3): temporary posts, deleted afterwards */
$ids  = array();
$mk   = function ( $extra ) use ( &$ids ) {
	$id    = wp_insert_post( array_merge( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'HS search test ' . wp_generate_password( 6, false ) ), $extra ) );
	$ids[] = $id;
	return get_post( $id );
};
$plain    = $mk( array() );
$password = $mk( array( 'post_password' => 'secret' ) );
$noindex  = $mk( array() );
update_post_meta( $noindex->ID, '_yoast_wpseo_meta-robots-noindex', '1' );
$hidden = $mk( array() );
update_post_meta( $hidden->ID, 'hs_search_exclude', '1' );
$t( 'skip: normal post kept', ! hs_search_skip_post( $plain ) );
$t( 'skip: password-protected post left out', hs_search_skip_post( $password ) );
$t( 'skip: noindex post left out', hs_search_skip_post( get_post( $noindex->ID ) ) );
$t( 'skip: "hide from search" left out', hs_search_skip_post( get_post( $hidden->ID ) ) );
foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
wp_clear_scheduled_hook( 'hs_search_build_event' ); // the test posts queued a rebuild

/* Document titles (plan 2b rules) */
$docs = array();
$ctx  = array( 'src' => 'page', 'ti' => 'Test page', 'u' => '/test/', 'c' => array(), 'date' => '2026-11-06' );
hs_search_doc_collect(
	'<div><a href="https://docs.google.com/document/d/abc123/edit"><span class="hs-download-title">Uniform list</span></a></div>'
	. '<div><h3>Annual report</h3><p>Our yearly figures. <a href="/files/annual.pdf">Download</a></p></div>',
	$ctx,
	$docs
);
hs_search_doc_collect( '<p><a href="/files/sports-day-results_2025-06.pdf">click here</a></p>', $ctx, $docs ); // no heading anywhere: file name
$titles = wp_list_pluck( $docs, 'ti' );
$t( 'doc title: download card title (rule 1)', in_array( 'Uniform list', $titles, true ) );
$t( 'doc title: card heading + type (rule 4)', in_array( 'Annual report (PDF)', $titles, true ) );
$t( 'doc title: from the file name (rule 6)', in_array( 'Sports day results (PDF)', $titles, true ) );
$t( 'doc type: Google Doc', 'gdoc' === ( $docs['docs.google.com/document/d/abc123']['ft'] ?? '' ) );

/* Search log table (O1) */
global $wpdb;
hs_search_log_install();
$term = 'zz test ' . strtolower( wp_generate_password( 5, false ) );
hs_search_log_add( $term, 'commit' );
hs_search_log_add( strtoupper( $term ), 'commit' );
hs_search_log_add( $term, 'miss' );
$r = hs_search_log_report();
$t( 'log: counts add up (case folded)', 2 === ( $r[ $term ]['c30'] ?? 0 ) && 2 === ( $r[ $term ]['call'] ?? 0 ) && 1 === ( $r[ $term ]['m30'] ?? 0 ) );
$t( 'log: month trend', 2 === ( $r[ $term ]['mon']['commits'][ gmdate( 'Y-m' ) ] ?? 0 ) );
$t( 'log: emails, long numbers and links refused', ! hs_search_log_add( 'a@b.com', 'commit' ) && ! hs_search_log_add( 'call 0812345678', 'commit' ) && ! hs_search_log_add( 'www.example.com', 'commit' ) );
$t( 'log: one row per term/kind/day', 2 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . hs_search_log_table() . ' WHERE term = %s', $term ) ) ); // phpcs:ignore
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . hs_search_log_table() . ' WHERE term = %s', $term ) ); // phpcs:ignore
$t( 'cloudflare: range check v4/v6', hs_search_ip_in_range( '162.158.1.2', '162.158.0.0/15' ) && ! hs_search_ip_in_range( '8.8.8.8', '162.158.0.0/15' ) && hs_search_ip_in_range( '2606:4700::1', '2606:4700::/32' ) );

echo $fails ? "\n$fails failed\n" : "\nAll passed\n";
if ( $fails ) {
	exit( 1 );
}
