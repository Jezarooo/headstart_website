<?php

/**
 * Search-Shortcodes
 */
/**
 * Site search: front-end shortcodes and endpoints.
 *
 *   [hs_search]          the search field for the utility bar (results panel drops down from it)
 *   [hs_search_button]   magnifier button for the mobile header (opens the panel as a sheet)
 *
 * Output is the same for every visitor, so page caching (WP Super Cache, Cloudflare APO) is safe.
 * Without JavaScript the field is a normal GET form to /?s=.
 *
 * admin-ajax endpoints (POST, not cached):
 *   hs_search_log    anonymous search terms for the admin report (no emails, long numbers, URLs or IPs stored)
 *   hs_search_index  builds and returns the index only when index.json is missing (at most once per 5 minutes)
 *
 * No rewrite rules: no permalink flush needed.
 */

add_shortcode( 'hs_search', 'hs_search_field_shortcode' );
function hs_search_field_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'placeholder' => 'Search the site…' ), $atts, 'hs_search' );
	$icon = '<svg class="hs-search-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>';
	return '<div class="hs-search" data-hs-search>'
		. '<form class="hs-search-form" role="search" action="' . esc_url( home_url( '/' ) ) . '" method="get">'
		. $icon
		. '<input class="hs-search-field" type="search" name="s" autocomplete="off" spellcheck="false" placeholder="' . esc_attr( $atts['placeholder'] ) . '" aria-label="Search the site" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="hs-search-panel">'
		. '<button class="hs-search-clear" type="button" aria-label="Clear search" hidden>&#x2715;</button>'
		. '<kbd class="hs-search-key" aria-hidden="true">/</kbd>'
		. '</form></div>';
}

add_shortcode( 'hs_search_button', 'hs_search_button_shortcode' );
function hs_search_button_shortcode() {
	return '<button type="button" class="hs-search-trigger" aria-label="Search the site" aria-haspopup="dialog">'
		. '<svg class="hs-search-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>'
		. '</button>';
}

/* ---------------------------------------------------------------------------
 * Search-terms logging (no personal data)
 * ------------------------------------------------------------------------ */
add_action( 'wp_ajax_hs_search_log', 'hs_search_log_term' );
add_action( 'wp_ajax_nopriv_hs_search_log', 'hs_search_log_term' );
function hs_search_log_term() {
	$q    = isset( $_POST['q'] ) ? strtolower( trim( preg_replace( '/\s+/u', ' ', wp_unslash( (string) $_POST['q'] ) ) ) ) : '';
	$kind = isset( $_POST['kind'] ) && 'miss' === $_POST['kind'] ? 'misses' : 'commits';
	$q    = sanitize_text_field( mb_substr( $q, 0, 60 ) );
	if ( '' === $q || mb_strlen( $q ) < 2 || false !== strpos( $q, '@' ) || preg_match( '/\d{6,}/', $q ) || preg_match( '#https?:|www\.|\.(com|net|org|co|th)\b#', $q ) ) {
		wp_send_json_success( array( 'logged' => 0 ) );
	}
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$rate = 'hs_search_rl_' . md5( $ip . wp_salt() );
	$n    = (int) get_transient( $rate );
	if ( $n >= 30 ) {
		wp_send_json_success( array( 'logged' => 0 ) );
	}
	set_transient( $rate, $n + 1, 10 * MINUTE_IN_SECONDS );

	$terms = get_option( 'hs_search_terms', array() );
	$terms = is_array( $terms ) ? $terms : array();
	$row   = $terms[ $q ] ?? array( 'commits' => 0, 'misses' => 0, 'last' => 0 );
	$row[ $kind ] = (int) ( $row[ $kind ] ?? 0 ) + 1;
	$row['last']  = time();
	// Per day (last 31 days, for "Last 30 days") and per month (last 12 months, for the trend)
	$day   = gmdate( 'Y-m-d' );
	$month = gmdate( 'Y-m' );
	$row[ 'd_' . $kind ]           = array_slice( (array) ( $row[ 'd_' . $kind ] ?? array() ), -31, null, true );
	$row[ 'd_' . $kind ][ $day ]   = (int) ( $row[ 'd_' . $kind ][ $day ] ?? 0 ) + 1;
	$row[ 'm_' . $kind ]           = array_slice( (array) ( $row[ 'm_' . $kind ] ?? array() ), -12, null, true );
	$row[ 'm_' . $kind ][ $month ] = (int) ( $row[ 'm_' . $kind ][ $month ] ?? 0 ) + 1;
	$terms[ $q ]  = $row;
	if ( count( $terms ) > 300 ) {
		uasort( $terms, function ( $a, $b ) { return ( $b['last'] ?? 0 ) <=> ( $a['last'] ?? 0 ); } );
		$terms = array_slice( $terms, 0, 300, true );
	}
	update_option( 'hs_search_terms', $terms, false );
	wp_send_json_success( array( 'logged' => 1 ) );
}

/* ---------------------------------------------------------------------------
 * Index fallback: only when index.json is missing
 * ------------------------------------------------------------------------ */
add_action( 'wp_ajax_hs_search_index', 'hs_search_index_fallback' );
add_action( 'wp_ajax_nopriv_hs_search_index', 'hs_search_index_fallback' );
function hs_search_index_fallback() {
	if ( ! function_exists( 'hs_search_index_file' ) || ! function_exists( 'hs_search_run_locked_build' ) ) {
		wp_send_json_error( 'Search index builder is not active.', 503 );
	}
	$file = hs_search_index_file();
	if ( ! file_exists( $file ) && ! get_transient( 'hs_search_fallback_recent' ) ) {
		set_transient( 'hs_search_fallback_recent', 1, 5 * MINUTE_IN_SECONDS );
		hs_search_run_locked_build();
	}
	if ( ! file_exists( $file ) ) {
		wp_send_json_error( 'Index not available yet.', 503 );
	}
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	readfile( $file ); // phpcs:ignore
	exit;
}
