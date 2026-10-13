<?php
/*
Plugin Name: HeadStart Local Site-Scripts Loader
Description: LOCAL ONLY — never deploy. Loads PHP snippets, JS and CSS straight from the repo's Site-Scripts folder so an edit shows on the next reload.
Version: 2.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where Site-Scripts lives. Defaults to the repo root (four levels above this
 * file: app/public/wp-content/mu-plugins). Override with HS_SITE_SCRIPTS_DIR
 * in wp-config.php if the folders ever move.
 */
function hs_local_scripts_dir() {
	if ( defined( 'HS_SITE_SCRIPTS_DIR' ) ) {
		return rtrim( HS_SITE_SCRIPTS_DIR, '/' );
	}
	return dirname( __DIR__, 4 ) . '/Site-Scripts';
}

/**
 * TEMPORARY diagnostic switch (feat/workflow-tooling UAT): Site-Scripts/.loader-compat
 * containing "hash-order" and/or "no-css" restores the pre-2.0 behaviour for that part.
 */
function hs_local_compat( $flag ) {
	static $flags = null;
	if ( null === $flags ) {
		$f     = hs_local_scripts_dir() . '/.loader-compat';
		$flags = is_readable( $f ) ? file_get_contents( $f ) : '';
	}
	return false !== strpos( $flags, $flag );
}

/** Files named *.off.php / *.off.js / *.off.css are switched off locally. */
function hs_local_is_off( $file ) {
	return (bool) preg_match( '/\.off\.(php|js|css)$/', $file );
}

/** JS files in live load order (Site-Scripts/js/MANIFEST.tsv), then any unlisted ones A–Z. */
function hs_local_js_files() {
	$dir      = hs_local_scripts_dir() . '/js';
	$ordered  = array();
	$manifest = $dir . '/MANIFEST.tsv';
	if ( is_readable( $manifest ) ) {
		foreach ( file( $manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
			if ( '' === trim( $line ) || '#' === $line[0] ) {
				continue;
			}
			$clean = trim( explode( "\t", $line )[0] );
			$path  = $dir . '/' . $clean;
			if ( '' !== $clean && is_file( $path ) && ! hs_local_is_off( $path ) ) {
				$ordered[ $path ] = true;
			}
		}
	}
	if ( hs_local_compat( 'hash-order' ) && $ordered ) {
		// Old loader order: alphabetical by the live (hash) file name.
		$map = array();
		foreach ( file( $manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
			if ( '#' !== $line[0] ) {
				$cols = explode( "\t", $line );
				if ( isset( $cols[1] ) ) {
					$map[ $dir . '/' . trim( $cols[0] ) ] = trim( $cols[1] );
				}
			}
		}
		uksort( $ordered, function ( $a, $b ) use ( $map ) {
			return strcmp( $map[ $a ] ?? basename( $a ), $map[ $b ] ?? basename( $b ) );
		} );
	}
	$rest = glob( $dir . '/*.js' ) ?: array();
	sort( $rest );
	foreach ( $rest as $path ) {
		if ( ! isset( $ordered[ $path ] ) && ! hs_local_is_off( $path ) ) {
			$ordered[ $path ] = true;
		}
	}
	return array_keys( $ordered );
}

// 1. PHP snippets (A–Z), as Code Snippets runs them on live.
$hs_php = glob( hs_local_scripts_dir() . '/php/*.php' ) ?: array();
sort( $hs_php );
foreach ( $hs_php as $hs_file ) {
	if ( ! hs_local_is_off( $hs_file ) ) {
		require_once $hs_file;
	}
}
unset( $hs_php, $hs_file );

// 2. JS in the footer, in live load order.
add_action(
	'wp_footer',
	function () {
		echo "<!-- HeadStart local JS loader -->\n";
		foreach ( hs_local_js_files() as $file ) {
			echo '<script data-hs-src="' . esc_attr( basename( $file ) ) . "\">\n" . file_get_contents( $file ) . "\n</script>\n";
		}
	},
	9999
);

// 3. CSS in the head: every Site-Scripts/css/*.css, then local-only overrides.
add_action(
	'wp_head',
	function () {
		$css = hs_local_compat( 'no-css' ) ? array() : ( glob( hs_local_scripts_dir() . '/css/*.css' ) ?: array() );
		sort( $css );
		foreach ( $css as $file ) {
			if ( ! hs_local_is_off( $file ) ) {
				echo '<style data-hs-src="' . esc_attr( basename( $file ) ) . "\">\n" . file_get_contents( $file ) . "\n</style>\n";
			}
		}
		echo "<style data-hs-src=\"local-overrides\">\n/* Local override for staff hover effect */\n.hs-staff-directory .hs-staff-card.hs-has-profile:hover img { filter: none !important; }\n</style>\n";
	}
);
