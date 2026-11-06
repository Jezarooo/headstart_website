<?php

/**
 * Search-Admin
 */
/**
 * Site search: admin screens Index status and Documents (Site search menu).
 * Needs the Search-Index-Builder snippet (settings, build, documents store).
 * No rewrite rules: no permalink flush needed.
 */

/* ---------------------------------------------------------------------------
 * Admin: Index status
 * ------------------------------------------------------------------------ */
add_action( 'admin_menu', function () {
	add_submenu_page( hs_search_menu_slug(), 'Index status', 'Index status', 'edit_others_posts', hs_search_menu_slug(), 'hs_search_menu_router', 0 );
}, 11 );

add_action( 'admin_post_hs_search_status', function () {
	if ( ! current_user_can( 'edit_others_posts' ) || ! check_admin_referer( 'hs_search_status' ) ) {
		wp_die( 'Not allowed' );
	}
	$msg = 'saved';
	if ( isset( $_POST['rebuild'] ) ) {
		$msg = hs_search_run_locked_build() ? 'built' : 'busy';
	} else {
		$s = hs_search_settings();
		foreach ( array( 'src_page', 'src_person', 'src_issue', 'src_post', 'src_event', 'src_doc' ) as $k ) {
			$s[ $k ] = empty( $_POST[ $k ] ) ? 0 : 1;
		}
		$s['events_url']     = esc_url_raw( wp_unslash( $_POST['events_url'] ?? '' ) );
		$s['article_months'] = max( 0, (int) ( $_POST['article_months'] ?? 0 ) );
		update_option( 'hs_search_settings', $s, false );
		hs_search_schedule_build();
	}
	wp_safe_redirect( add_query_arg( array( 'page' => hs_search_menu_slug(), 'hs_msg' => $msg ), admin_url( 'admin.php' ) ) );
	exit;
} );

function hs_search_render_status_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	$meta = get_option( 'hs_search_index_meta', array() );
	$s    = hs_search_settings();
	$msgs = array( 'built' => 'Index rebuilt.', 'busy' => 'A build is already running; another one is queued.', 'saved' => 'Settings saved; the index rebuilds in about 30 seconds.' );
	$msg  = sanitize_key( $_GET['hs_msg'] ?? '' );
	echo '<div class="wrap"><h1>Site search: index status</h1>';
	if ( isset( $msgs[ $msg ] ) ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $msgs[ $msg ] ) . '</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'hs_search_status' );
	echo '<input type="hidden" name="action" value="hs_search_status">';

	echo '<h2>Index</h2><table class="widefat striped" style="max-width:720px"><tbody>';
	$file = hs_search_index_file();
	$rows = array(
		'Last built'   => $meta['built'] ?? 'never',
		'File'         => file_exists( $file ) ? hs_search_rel_url( trailingslashit( wp_upload_dir()['baseurl'] ) . 'hs-search/index.json' ) : 'missing',
		'Size'         => isset( $meta['bytes'] ) ? size_format( $meta['bytes'], 1 ) . ' raw · ' . size_format( $meta['gzip'], 1 ) . ' gzipped' : '–',
		'Build time'   => isset( $meta['ms'] ) ? number_format( $meta['ms'] ) . ' ms' : '–',
		'Events page'  => ! empty( $meta['events_url'] ) ? $meta['events_url'] : 'none found: events are not indexed',
	);
	foreach ( $rows as $k => $v ) {
		echo '<tr><th style="width:180px">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
	}
	$labels = array( 'topic' => 'Quick answers', 'person' => 'People', 'page' => 'Pages', 'issue' => 'Weekly update issues', 'article' => 'Articles (issues + posts)', 'event' => 'Events', 'doc' => 'Documents' );
	foreach ( $labels as $k => $l ) {
		echo '<tr><th>' . esc_html( $l ) . '</th><td>' . (int) ( $meta['counts'][ $k ] ?? 0 ) . '</td></tr>';
	}
	if ( ! empty( $meta['errors'] ) ) {
		echo '<tr><th>Errors</th><td><code>' . esc_html( implode( "\n", $meta['errors'] ) ) . '</code></td></tr>';
	}
	echo '</tbody></table><p><button class="button button-primary" name="rebuild" value="1">Rebuild now</button></p>';

	echo '<h2>Sources</h2><p>';
	foreach ( array( 'src_page' => 'Pages', 'src_person' => 'People', 'src_issue' => 'Weekly updates', 'src_post' => 'Posts', 'src_event' => 'Events', 'src_doc' => 'Documents' ) as $k => $l ) {
		echo '<label style="margin-right:18px"><input type="checkbox" name="' . esc_attr( $k ) . '" value="1" ' . checked( $s[ $k ], 1, false ) . '> ' . esc_html( $l ) . '</label>';
	}
	echo '</p><p><label>Events calendar page (leave empty to detect it): <input type="text" class="regular-text" name="events_url" value="' . esc_attr( $s['events_url'] ) . '" placeholder="/events/"></label></p>';
	echo '<p><label>Keep article previews for the last <input type="number" min="0" style="width:70px" name="article_months" value="' . (int) $s['article_months'] . '"> months (0 = all)</label></p>';
	echo '<p><button class="button" name="save" value="1">Save settings</button></p>';

	echo '<h2>Search report, synonyms and promoted results</h2><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=hs-site-search-terms' ) ) . '">Open Search terms</a></p>';
	echo '</form></div>';
}

/* ---------------------------------------------------------------------------
 * Admin: Documents manager (Site search → Documents)
 * ------------------------------------------------------------------------ */
add_action( 'admin_menu', function () {
	add_submenu_page( hs_search_menu_slug(), 'Documents', 'Documents', 'edit_others_posts', 'hs-site-search-docs', 'hs_search_render_docs_page', 2 );
}, 11 );

add_action( 'admin_post_hs_search_docs', function () {
	if ( ! current_user_can( 'edit_others_posts' ) || ! check_admin_referer( 'hs_search_docs' ) ) {
		wp_die( 'Not allowed' );
	}
	$store = hs_search_docs_store();
	$ids   = array_map( 'sanitize_key', (array) ( $_POST['doc'] ?? array() ) );
	$bulk  = sanitize_key( $_POST['bulk'] ?? '' );
	if ( in_array( $bulk, array( 'in', 'out', 'pin' ), true ) ) {
		foreach ( $ids as $h ) {
			if ( isset( $store[ $h ] ) ) {
				$store[ $h ]['state'] = $bulk;
			}
		}
	}
	foreach ( (array) ( $_POST['title'] ?? array() ) as $h => $t ) {
		$h = sanitize_key( $h );
		if ( isset( $store[ $h ] ) ) {
			$store[ $h ]['title'] = sanitize_text_field( wp_unslash( $t ) );
		}
	}
	foreach ( (array) ( $_POST['state'] ?? array() ) as $h => $st ) {
		$h = sanitize_key( $h );
		if ( isset( $store[ $h ] ) && in_array( $st, array( 'in', 'out', 'pin' ), true ) && ! ( $bulk && in_array( $h, $ids, true ) ) ) {
			$store[ $h ]['state'] = $st;
		}
	}
	update_option( 'hs_search_docs', $store, false );
	if ( isset( $_POST['rules'] ) ) {
		$s                   = hs_search_settings();
		$s['doc_age_months'] = max( 0, (int) ( $_POST['doc_age_months'] ?? 0 ) );
		$s['doc_exclude']    = sanitize_textarea_field( wp_unslash( $_POST['doc_exclude'] ?? '' ) );
		update_option( 'hs_search_settings', $s, false );
	}
	hs_search_schedule_build();
	$back = wp_get_referer() ?: admin_url( 'admin.php?page=hs-site-search-docs' );
	wp_safe_redirect( add_query_arg( 'hs_msg', 'saved', $back ) );
	exit;
} );

function hs_search_render_docs_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	$audit = get_option( 'hs_search_docs_audit', array() );
	$store = hs_search_docs_store();
	$s     = hs_search_settings();
	$f     = array(
		'src'   => sanitize_key( $_GET['src'] ?? '' ),
		'ft'    => sanitize_key( $_GET['ft'] ?? '' ),
		'c'     => sanitize_key( $_GET['c'] ?? '' ),
		'state' => sanitize_key( $_GET['state'] ?? '' ),
		'weak'  => ! empty( $_GET['weak'] ),
		'from'  => preg_replace( '/[^0-9-]/', '', $_GET['from'] ?? '' ),
		'to'    => preg_replace( '/[^0-9-]/', '', $_GET['to'] ?? '' ),
		'q'     => sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ),
	);
	$rows = array();
	foreach ( (array) $audit as $h => $d ) {
		$rec   = $store[ $h ] ?? array( 'state' => 'in', 'title' => '', 'first' => '', 'last' => '' );
		$src   = $d['upd'] ? 'updates' : 'pages';
		$title = $rec['title'] ?: $d['ti'];
		if ( ( $f['src'] && $f['src'] !== $src ) || ( $f['ft'] && $f['ft'] !== $d['ft'] ) || ( $f['state'] && 'legacy' !== $f['state'] && $f['state'] !== $rec['state'] ) || ( 'legacy' === $f['state'] && empty( $d['legacy'] ) ) ) {
			continue;
		}
		if ( $f['c'] && ! in_array( $f['c'], (array) $d['c'], true ) && ! ( 'both' === $f['c'] && empty( $d['c'] ) ) ) {
			continue;
		}
		if ( $f['weak'] && $d['rule'] < 4 ) {
			continue;
		}
		if ( ( $f['from'] && ( $rec['last'] ?? '' ) < $f['from'] ) || ( $f['to'] && ( $rec['last'] ?? '' ) > $f['to'] ) ) {
			continue;
		}
		if ( $f['q'] && false === stripos( $title . ' ' . $d['url'] . ' ' . $d['on']['ti'], $f['q'] ) ) {
			continue;
		}
		$rows[ $h ] = array( $d, $rec, $title, $src );
	}
	uasort( $rows, function ( $a, $b ) { return strcmp( $b[1]['last'] ?? '', $a[1]['last'] ?? '' ); } );
	$total = count( $rows );
	$per   = 100;
	$pg    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	$rows  = array_slice( $rows, ( $pg - 1 ) * $per, $per, true );
	$rules = array( 1 => 'download card title', 2 => 'link title / alt', 3 => 'link text', 4 => 'card heading', 5 => 'nearest heading', 6 => 'file name' );
	$states = array( 'in' => 'Included', 'out' => 'Excluded', 'pin' => 'Pinned' );

	echo '<div class="wrap"><h1>Site search: documents</h1>';
	if ( 'saved' === ( $_GET['hs_msg'] ?? '' ) ) {
		echo '<div class="notice notice-success"><p>Saved. The index rebuilds in about 30 seconds.</p></div>';
	}
	echo '<p>Every document link found on pages, weekly updates and posts. New links are <strong>Included</strong> automatically. <strong>Excluded</strong> links stay out after every rebuild; <strong>Pinned</strong> links always stay in and rank higher. A title typed here replaces the automatic one.</p>';

	// Filters (GET).
	echo '<form method="get" style="margin:12px 0"><input type="hidden" name="page" value="hs-site-search-docs">';
	$sel = function ( $name, $opts, $val ) {
		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( $opts as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $val, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> ';
	};
	$sel( 'src', array( '' => 'All sources', 'pages' => 'Found on pages', 'updates' => 'Only in weekly updates / posts' ), $f['src'] );
	$sel( 'ft', array( '' => 'All types', 'gdoc' => 'Google Doc', 'gsheet' => 'Google Sheet', 'gslides' => 'Google Slides', 'gform' => 'Google Form', 'folder' => 'Drive folder', 'file' => 'Drive file', 'pdf' => 'PDF', 'word' => 'Word', 'excel' => 'Spreadsheet', 'ppt' => 'Slides', 'zip' => 'Zip' ), $f['ft'] );
	$sel( 'c', array( '' => 'All campuses', 'chaofah' => 'Chaofah', 'cherngtalay' => 'Cherngtalay', 'both' => 'Both' ), $f['c'] );
	$sel( 'state', array( '' => 'All states' ) + $states + array( 'legacy' => 'Legacy galleries (left out)' ), $f['state'] );
	echo 'Last seen <input type="date" name="from" value="' . esc_attr( $f['from'] ) . '"> to <input type="date" name="to" value="' . esc_attr( $f['to'] ) . '"> ';
	echo '<label><input type="checkbox" name="weak" value="1" ' . checked( $f['weak'], true, false ) . '> Weak titles only</label> ';
	echo '<input type="search" name="q" value="' . esc_attr( $f['q'] ) . '" placeholder="Search titles"> <button class="button">Filter</button></form>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'hs_search_docs' );
	echo '<input type="hidden" name="action" value="hs_search_docs">';
	echo '<p>' . (int) $total . ' documents. With selected: <select name="bulk"><option value="">–</option><option value="in">Include</option><option value="out">Exclude</option><option value="pin">Pin</option></select> <button class="button button-primary">Save changes</button></p>';
	echo '<table class="widefat striped"><thead><tr><th style="width:24px"><input type="checkbox" onclick="document.querySelectorAll(\'.hs-doc-cb\').forEach(c=>c.checked=this.checked)"></th><th>Title (edit to override)</th><th>Type</th><th>Found on</th><th>Seen</th><th>State</th></tr></thead><tbody>';
	foreach ( $rows as $h => $r ) {
		list( $d, $rec, $title, $src ) = $r;
		echo '<tr><td><input class="hs-doc-cb" type="checkbox" name="doc[]" value="' . esc_attr( $h ) . '"></td>';
		echo '<td><input type="text" style="width:100%" name="title[' . esc_attr( $h ) . ']" value="' . esc_attr( $rec['title'] ) . '" placeholder="' . esc_attr( $d['ti'] ) . '">';
		echo '<div style="font-size:11px;color:#646970;margin-top:3px">Automatic: ' . esc_html( $d['ti'] ) . ' <em>(' . esc_html( $rules[ $d['rule'] ] ?? '' ) . ')</em> · <a href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener">open</a>';
		echo ' · <details style="display:inline"><summary style="display:inline;cursor:pointer">markup</summary><code style="display:block;white-space:normal">' . esc_html( $d['markup'] ) . '</code></details></div></td>';
		echo '<td>' . esc_html( hs_search_doc_label( $d['ft'] ) ) . '</td>';
		echo '<td><a href="' . esc_url( home_url( $d['on']['u'] ) ) . '" target="_blank">' . esc_html( html_entity_decode( $d['on']['ti'], ENT_QUOTES, 'UTF-8' ) ) . '</a>' . ( $d['on']['sec'] ? ' › ' . esc_html( $d['on']['sec'] ) : '' ) . ( $d['n'] > 1 ? ' <span style="color:#646970">(+' . ( (int) $d['n'] - 1 ) . ' more)</span>' : '' ) . '</td>';
		echo '<td style="white-space:nowrap">' . esc_html( $rec['first'] ?? '' ) . '<br>' . esc_html( $rec['last'] ?? '' ) . '</td>';
		echo '<td>' . ( ! empty( $d['legacy'] ) && 'pin' !== $rec['state'] ? '<div style="font-size:11px;color:#b32d2e;margin-bottom:3px">Legacy gallery, left out (Pin to include)</div>' : '' ) . '<select name="state[' . esc_attr( $h ) . ']">';
		foreach ( $states as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $rec['state'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
	}
	echo '</tbody></table>';
	$pages = (int) ceil( $total / $per );
	if ( $pages > 1 ) {
		echo '<p>';
		for ( $i = 1; $i <= $pages; $i++ ) {
			echo $i === $pg ? '<strong>' . $i . '</strong> ' : '<a href="' . esc_url( add_query_arg( 'paged', $i ) ) . '">' . $i . '</a> ';
		}
		echo '</p>';
	}
	echo '<p><button class="button button-primary">Save changes</button></p>';

	echo '<h2>Rules</h2><input type="hidden" name="rules" value="1">';
	echo '<p><label>Leave out documents found only in weekly updates or posts and not seen for <input type="number" min="0" style="width:70px" name="doc_age_months" value="' . (int) $s['doc_age_months'] . '"> months (0 = off). Pinned documents and documents on pages are always kept.</label></p>';
	echo '<p><label>Never include links containing (one per line):<br><textarea name="doc_exclude" rows="4" cols="60">' . esc_textarea( $s['doc_exclude'] ) . '</textarea></label></p>';
	echo '<p><button class="button">Save rules</button></p></form></div>';
}

// The top-level menu adds an automatic "Site search" sub-item with the same page as "Index status": keep one.
add_action( 'admin_menu', function () {
	global $submenu;
	$slug = hs_search_menu_slug();
	if ( empty( $submenu[ $slug ] ) ) {
		return;
	}
	$seen = false;
	foreach ( $submenu[ $slug ] as $k => $item ) {
		if ( $item[2] === $slug ) {
			if ( $seen || 'Index status' !== $item[0] ) {
				unset( $submenu[ $slug ][ $k ] );
			}
			$seen = $seen || 'Index status' === $item[0];
		}
	}
}, 99 );
