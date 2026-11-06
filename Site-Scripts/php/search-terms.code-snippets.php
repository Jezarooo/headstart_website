<?php

/**
 * Search-Terms
 */
/**
 * Site search: the Search terms screen (synonyms, expansions, campus names, promoted
 * results, test box), the search report and its log table, and CSV export / import.
 * Needs the Search-Index-Builder snippet (vocabulary, index file).
 * Creates the table {prefix}hs_search_log on its first run. No rewrite rules.
 */

add_action( 'admin_menu', function () {
	add_submenu_page( hs_search_menu_slug(), 'Search terms', 'Search terms', 'edit_others_posts', 'hs-site-search-terms', 'hs_search_render_terms_page', 3 );
}, 11 );

add_action( 'admin_post_hs_search_terms', function () {
	if ( ! current_user_can( 'edit_others_posts' ) || ! check_admin_referer( 'hs_search_terms' ) ) {
		wp_die( 'Not allowed' );
	}
	$v   = hs_search_vocab();
	$in  = wp_unslash( $_POST );
	$txt = function ( $x ) { return sanitize_text_field( (string) $x ); };
	if ( isset( $in['handled'] ) ) {
		$v['handled'][ strtolower( $txt( $in['handled'] ) ) ] = time();
	} elseif ( isset( $in['clear_terms'] ) ) {
		delete_option( 'hs_search_terms' );
	} else {
		$v['groups'] = array();
		foreach ( (array) ( $in['g_w'] ?? array() ) as $i => $w ) {
			if ( '' !== trim( (string) $w ) && empty( $in['g_del'][ $i ] ) ) {
				$v['groups'][] = array( 'w' => $txt( $w ), 'on' => empty( $in['g_on'][ $i ] ) ? 0 : 1 );
			}
		}
		$v['expand'] = array();
		foreach ( (array) ( $in['x_f'] ?? array() ) as $i => $f ) {
			if ( '' !== trim( (string) $f ) && empty( $in['x_del'][ $i ] ) ) {
				$v['expand'][] = array( 'f' => $txt( $f ), 't' => $txt( $in['x_t'][ $i ] ?? '' ), 'on' => empty( $in['x_on'][ $i ] ) ? 0 : 1 );
			}
		}
		$v['promote'] = array();
		foreach ( (array) ( $in['p_term'] ?? array() ) as $i => $t ) {
			if ( '' !== trim( (string) $t ) && empty( $in['p_del'][ $i ] ) ) {
				$v['promote'][] = array( 'term' => $txt( $t ), 'ids' => preg_replace( '/[^a-z0-9_,]/i', '', (string) ( $in['p_ids'][ $i ] ?? '' ) ), 'on' => empty( $in['p_on'][ $i ] ) ? 0 : 1 );
			}
		}
		$v['campus'] = array(
			'chaofah'     => $txt( $in['c_chaofah'] ?? '' ) ?: 'chaofah',
			'cherngtalay' => $txt( $in['c_cherngtalay'] ?? '' ) ?: 'cherngtalay',
		);
	}
	update_option( 'hs_search_vocab', $v, false );
	hs_search_schedule_build();
	wp_safe_redirect( add_query_arg( array( 'page' => 'hs-site-search-terms', 'hs_msg' => 'saved' ), admin_url( 'admin.php' ) ) . ( isset( $in['test_q'] ) ? '&test_q=' . rawurlencode( $txt( $in['test_q'] ) ) : '' ) );
	exit;
} );

function hs_search_render_terms_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	$v      = hs_search_vocab();
	$range  = ( 'all' === ( $_GET['range'] ?? '' ) ) ? 'all' : '30';
	$test_q = sanitize_text_field( wp_unslash( $_GET['test_q'] ?? '' ) );
	echo '<div class="wrap hs-terms"><h1>Site search: search terms</h1>';
	do_action( 'hs_search_terms_page_top' );
	if ( 'saved' === ( $_GET['hs_msg'] ?? '' ) ) {
		echo '<div class="notice notice-success"><p>Saved. The search updates in about 30 seconds.</p></div>';
	}
	echo '<style>
		.hs-terms table.widefat input[type=text]{width:100%}
		.hs-terms .hs-cols{display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start}
		.hs-terms .hs-chip{display:inline-block;margin:2px 4px 2px 0;padding:2px 8px;border:1px solid #c3c4c7;border-radius:3px;background:#f6f7f7;font-size:12px}
		.hs-terms .hs-chip a{margin-left:4px;text-decoration:none;color:#b32d2e}
		.hs-terms .hs-pick{position:relative}
		.hs-terms .hs-pick-list{position:absolute;z-index:10;left:0;right:0;background:#fff;border:1px solid #c3c4c7;max-height:220px;overflow:auto;display:none}
		.hs-terms .hs-pick-list div{padding:5px 8px;cursor:pointer;border-bottom:1px solid #f0f0f1;font-size:12px}
		.hs-terms .hs-pick-list div:hover{background:#f0f6fc}
		.hs-terms .hs-pick-list em{color:#646970;font-style:normal;margin-left:6px}
		.hs-terms .hs-trend{color:#646970;font-size:11px}
		@media (max-width:1100px){.hs-terms .hs-cols{grid-template-columns:1fr}}
	</style>';

	/* ---- Report ---- */
	$terms = get_option( 'hs_search_terms', array() );
	$terms = is_array( $terms ) ? $terms : array();
	$cut   = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
	$count = function ( $t, $kind ) use ( $range, $cut ) {
		if ( 'all' === $range ) {
			return (int) ( $t[ $kind ] ?? 0 );
		}
		$n = 0;
		foreach ( (array) ( $t[ 'd_' . $kind ] ?? array() ) as $day => $c ) {
			if ( $day >= $cut ) {
				$n += (int) $c;
			}
		}
		return $n;
	};
	$months = array();
	for ( $i = 5; $i >= 0; $i-- ) {
		$months[] = gmdate( 'Y-m', strtotime( "first day of -$i months" ) );
	}
	echo '<h2>Search report</h2><p>';
	echo '<a class="button' . ( '30' === $range ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( 'range', '30' ) ) . '">Last 30 days</a> ';
	echo '<a class="button' . ( 'all' === $range ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( 'range', 'all' ) ) . '">All time</a></p>';
	echo '<div class="hs-cols">';
	foreach ( array( 'commits' => 'Top searches', 'misses' => 'Searches with no results' ) as $kind => $title ) {
		$rows = array();
		foreach ( $terms as $q => $t ) {
			$n = $count( $t, $kind );
			if ( $n < 1 ) {
				continue;
			}
			$handled = $v['handled'][ $q ] ?? 0;
			if ( $handled && ( $t['last'] ?? 0 ) <= $handled ) {
				continue;
			}
			$rows[ $q ] = array( $n, $t );
		}
		uasort( $rows, function ( $a, $b ) { return $b[0] <=> $a[0]; } );
		echo '<div><h3>' . esc_html( $title ) . '</h3><table class="widefat striped"><thead><tr><th>Search</th><th style="width:60px">Times</th><th style="width:110px">Last 6 months</th><th style="width:250px"></th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="4">Nothing yet.</td></tr>';
		}
		foreach ( array_slice( $rows, 0, 50, true ) as $q => $row ) {
			$trend = array();
			foreach ( $months as $m ) {
				$trend[] = (int) ( $row[1][ 'm_' . $kind ][ $m ] ?? 0 );
			}
			echo '<tr><td>' . esc_html( $q ) . '</td><td>' . (int) $row[0] . '</td><td class="hs-trend">' . esc_html( implode( ' · ', $trend ) ) . '</td><td>';
			echo '<button type="button" class="button button-small" data-add-group="' . esc_attr( $q ) . '">Add synonym</button> ';
			echo '<button type="button" class="button button-small" data-add-promo="' . esc_attr( $q ) . '">Promote a result</button> ';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( 'hs_search_terms' );
			echo '<input type="hidden" name="action" value="hs_search_terms"><button class="button-link" name="handled" value="' . esc_attr( $q ) . '">Mark handled</button></form>';
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
	echo '</div>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:8px">';
	wp_nonce_field( 'hs_search_terms' );
	echo '<input type="hidden" name="action" value="hs_search_terms"><button class="button-link" name="clear_terms" value="1" onclick="return confirm(\'Clear the whole search report?\')">Clear the report</button></form>';

	/* ---- Test box ---- */
	$preview = home_url( '/contact-us/' );
	echo '<h2 id="hs-test">Test a search</h2><p><input type="search" id="hs-test-q" class="regular-text" value="' . esc_attr( $test_q ) . '" placeholder="e.g. ks1, uniform, fees"> <button type="button" class="button" id="hs-test-go">Show results</button>';
	echo ' <span class="description">The search panel as visitors see it, including what the words were expanded to ("also: …").</span></p>';
	echo '<iframe id="hs-test-frame" title="Search preview" style="width:100%;height:720px;border:1px solid #c3c4c7;background:#fff;display:' . ( $test_q ? 'block' : 'none' ) . '" data-base="' . esc_url( $preview ) . '"' . ( $test_q ? ' src="' . esc_url( add_query_arg( array( 'q' => $test_q, 'hs_search_test' => 1 ), $preview ) ) . '"' : '' ) . '></iframe>';

	/* ---- Rules ---- */
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="hs-terms-form">';
	wp_nonce_field( 'hs_search_terms' );
	echo '<input type="hidden" name="action" value="hs_search_terms">';

	echo '<h2>Synonym groups</h2><p class="description">Words or short phrases that mean the same. A search for any of them also finds the others. Separate with commas.</p>';
	echo '<table class="widefat striped" id="hs-groups"><thead><tr><th>Words</th><th style="width:60px">On</th><th style="width:70px">Delete</th></tr></thead><tbody>';
	$i = 0;
	foreach ( array_merge( (array) $v['groups'], array( array( 'w' => '', 'on' => 1 ) ) ) as $g ) {
		echo '<tr><td><input type="text" name="g_w[' . $i . ']" value="' . esc_attr( $g['w'] ) . '" placeholder="e.g. uniform, kit, clothing"></td><td><input type="checkbox" name="g_on[' . $i . ']" value="1" ' . checked( ! empty( $g['on'] ), true, false ) . '></td><td><input type="checkbox" name="g_del[' . $i . ']" value="1"></td></tr>';
		$i++;
	}
	echo '</tbody></table><p><button type="button" class="button" data-add-row="hs-groups">Add a group</button></p>';

	echo '<h2>One-way expansions</h2><p class="description">A search for the word on the left also searches for the words on the right, not the other way round. Example: ks1 → year 1, year 2.</p>';
	echo '<table class="widefat striped" id="hs-expand"><thead><tr><th style="width:25%">Search for</th><th>Also search for</th><th style="width:60px">On</th><th style="width:70px">Delete</th></tr></thead><tbody>';
	$i = 0;
	foreach ( array_merge( (array) $v['expand'], array( array( 'f' => '', 't' => '', 'on' => 1 ) ) ) as $x ) {
		echo '<tr><td><input type="text" name="x_f[' . $i . ']" value="' . esc_attr( $x['f'] ) . '"></td><td><input type="text" name="x_t[' . $i . ']" value="' . esc_attr( $x['t'] ) . '"></td><td><input type="checkbox" name="x_on[' . $i . ']" value="1" ' . checked( ! empty( $x['on'] ), true, false ) . '></td><td><input type="checkbox" name="x_del[' . $i . ']" value="1"></td></tr>';
		$i++;
	}
	echo '</tbody></table><p><button type="button" class="button" data-add-row="hs-expand">Add an expansion</button></p>';

	echo '<h2>Campus names</h2><p class="description">Words that select a campus when typed in the search (they become the campus filter).</p><table class="form-table"><tr><th>Chaofah</th><td><input type="text" class="large-text" name="c_chaofah" value="' . esc_attr( $v['campus']['chaofah'] ) . '"></td></tr><tr><th>Cherngtalay</th><td><input type="text" class="large-text" name="c_cherngtalay" value="' . esc_attr( $v['campus']['cherngtalay'] ) . '"></td></tr></table>';

	echo '<h2>Promoted results</h2><p class="description">For a search term, up to five results to show first (with a "Recommended" tag), even if their text would not match. Type in the picker to find a page, person, document, article or weekly update.</p>';
	echo '<table class="widefat striped" id="hs-promo"><thead><tr><th style="width:20%">Search term</th><th>Results shown first</th><th style="width:60px">On</th><th style="width:70px">Delete</th></tr></thead><tbody>';
	$i = 0;
	foreach ( array_merge( (array) $v['promote'], array( array( 'term' => '', 'ids' => '', 'on' => 1 ) ) ) as $p ) {
		echo '<tr><td><input type="text" name="p_term[' . $i . ']" value="' . esc_attr( $p['term'] ) . '"></td><td><input type="hidden" class="hs-ids" name="p_ids[' . $i . ']" value="' . esc_attr( $p['ids'] ) . '"><div class="hs-chips"></div><div class="hs-pick"><input type="text" class="hs-pick-q" placeholder="Find a result…"><div class="hs-pick-list"></div></div></td><td><input type="checkbox" name="p_on[' . $i . ']" value="1" ' . checked( ! empty( $p['on'] ), true, false ) . '></td><td><input type="checkbox" name="p_del[' . $i . ']" value="1"></td></tr>';
		$i++;
	}
	echo '</tbody></table><p><button type="button" class="button" data-add-row="hs-promo">Add a promoted result</button></p>';
	echo '<p><button class="button button-primary">Save search terms</button></p></form>';

	$index_url = trailingslashit( wp_upload_dir()['baseurl'] ) . 'hs-search/index.json';
	// Admin-only script for the picker, the report buttons and the test box (kept out of the PHP close tags for Code Snippets)
	$js = <<<'HSJS'
<script>
(function () {
  var TYPES = { page: 'Page', person: 'Person', doc: 'Document', article: 'Article', issue: 'Weekly update', topic: 'Quick answer', event: 'Event' };
  var items = [], byId = {};
  fetch(%%INDEX_URL%%, { cache: 'no-cache' }).then(function (r) { return r.json(); }).then(function (j) {
    items = (j.items || []).filter(function (it) { return TYPES[it.t]; });
    items.forEach(function (it) { byId[it.id] = it; });
    document.querySelectorAll('#hs-promo tbody tr').forEach(drawChips);
  });
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function drawChips(tr) {
    var ids = tr.querySelector('.hs-ids'), box = tr.querySelector('.hs-chips');
    if (!ids || !box) { return; }
    var list = ids.value ? ids.value.split(',') : [];
    box.innerHTML = list.map(function (id) { var it = byId[id]; return '<span class="hs-chip">' + esc(it ? it.ti + ' (' + TYPES[it.t] + ')' : id) + '<a href="#" data-rm="' + esc(id) + '" title="Remove">✕</a></span>'; }).join('');
  }
  document.addEventListener('click', function (e) {
    var rm = e.target.closest('.hs-chip [data-rm]');
    if (rm) { e.preventDefault(); var tr = rm.closest('tr'), ids = tr.querySelector('.hs-ids'); ids.value = ids.value.split(',').filter(function (x) { return x && x !== rm.getAttribute('data-rm'); }).join(','); drawChips(tr); return; }
    var pick = e.target.closest('.hs-pick-list [data-id]');
    if (pick) { var tr2 = pick.closest('tr'), ids2 = tr2.querySelector('.hs-ids'), cur = ids2.value ? ids2.value.split(',') : []; if (cur.indexOf(pick.getAttribute('data-id')) < 0 && cur.length < 5) { cur.push(pick.getAttribute('data-id')); } ids2.value = cur.join(','); drawChips(tr2); tr2.querySelector('.hs-pick-list').style.display = 'none'; tr2.querySelector('.hs-pick-q').value = ''; return; }
    var add = e.target.closest('[data-add-row]');
    if (add) { addRow(add.getAttribute('data-add-row')); return; }
    var ag = e.target.closest('[data-add-group]');
    if (ag) { var r = addRow('hs-groups'); r.querySelector('input[type=text]').value = ag.getAttribute('data-add-group') + ', '; r.querySelector('input[type=text]').focus(); return; }
    var ap = e.target.closest('[data-add-promo]');
    if (ap) { var r2 = addRow('hs-promo'); r2.querySelector('input[name^=p_term]').value = ap.getAttribute('data-add-promo'); r2.querySelector('.hs-pick-q').focus(); return; }
    if (!e.target.closest('.hs-pick')) { document.querySelectorAll('.hs-pick-list').forEach(function (l) { l.style.display = 'none'; }); }
  });
  function addRow(id) {
    var tb = document.querySelector('#' + id + ' tbody'), last = tb.lastElementChild;
    var blank = last && Array.prototype.every.call(last.querySelectorAll('input[type=text]'), function (i) { return !i.value; });
    if (blank) { last.scrollIntoView({ block: 'center' }); return last; }
    var row = last.cloneNode(true), n = tb.children.length;
    row.querySelectorAll('input').forEach(function (i) {
      if (i.name) { i.name = i.name.replace(/\[\d+\]/, '[' + n + ']'); }
      if (i.type === 'checkbox') { i.checked = /_on\[/.test(i.name); } else { i.value = ''; }
    });
    var ch = row.querySelector('.hs-chips'); if (ch) { ch.innerHTML = ''; }
    tb.appendChild(row); row.scrollIntoView({ block: 'center' });
    return row;
  }
  document.addEventListener('input', function (e) {
    if (!e.target.classList.contains('hs-pick-q')) { return; }
    var q = e.target.value.toLowerCase().trim(), list = e.target.nextElementSibling;
    if (q.length < 2) { list.style.display = 'none'; return; }
    var hits = items.filter(function (it) { return it.ti.toLowerCase().indexOf(q) > -1; }).slice(0, 12);
    list.innerHTML = hits.map(function (it) { return '<div data-id="' + esc(it.id) + '">' + esc(it.ti) + '<em>' + TYPES[it.t] + (it.d ? ' · ' + it.d : '') + '</em></div>'; }).join('') || '<div>No match</div>';
    list.style.display = 'block';
  });
  var go = document.getElementById('hs-test-go'), tq = document.getElementById('hs-test-q'), fr = document.getElementById('hs-test-frame');
  function test() { if (!tq.value.trim()) { return; } fr.style.display = 'block'; fr.src = fr.getAttribute('data-base') + '?q=' + encodeURIComponent(tq.value.trim()) + '&hs_search_test=1'; }
  go.addEventListener('click', test);
  tq.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); test(); } });
})();
</script>
HSJS;
	echo str_replace( '%%INDEX_URL%%', wp_json_encode( $index_url ), $js ); // phpcs:ignore
	echo '</div>';
}

/* ---------------------------------------------------------------------------
 * Search terms: CSV export / import (with preview, and one-step undo)
 * Columns: type, on, key, values, info
 *   campus        key = chaofah | cherngtalay, values = words
 *   group         values = words that mean the same
 *   expand        key = searched word, values = also search for
 *   promote       key = search term, values = result ids, info = titles (ignored on import)
 *   quick_answer  key = quick answer title, values = its search words, info = id=123
 *   report        read-only (searches in the last 30 days / all time / no results); ignored on import
 * A section that is in the file replaces that section; sections not in the file are left alone.
 * ------------------------------------------------------------------------ */
function hs_search_vocab_csv_rows() {
	$v    = hs_search_vocab();
	$rows = array( array( 'type', 'on', 'key', 'values', 'info' ) );
	foreach ( array( 'chaofah', 'cherngtalay' ) as $c ) {
		$rows[] = array( 'campus', 1, $c, $v['campus'][ $c ] ?? '', 'words that select this campus' );
	}
	foreach ( (array) $v['groups'] as $g ) {
		$rows[] = array( 'group', empty( $g['on'] ) ? 0 : 1, '', $g['w'], '' );
	}
	foreach ( (array) $v['expand'] as $x ) {
		$rows[] = array( 'expand', empty( $x['on'] ) ? 0 : 1, $x['f'], $x['t'], '' );
	}
	$titles = array();
	$file   = hs_search_index_file();
	if ( file_exists( $file ) ) {
		$j = json_decode( (string) file_get_contents( $file ), true );
		foreach ( (array) ( $j['items'] ?? array() ) as $it ) {
			$titles[ $it['id'] ] = $it['ti'] . ' (' . $it['t'] . ')';
		}
	}
	foreach ( (array) $v['promote'] as $p ) {
		$ids    = array_filter( array_map( 'trim', explode( ',', (string) $p['ids'] ) ) );
		$rows[] = array( 'promote', empty( $p['on'] ) ? 0 : 1, $p['term'], implode( ', ', $ids ), implode( ' | ', array_map( function ( $id ) use ( $titles ) { return $titles[ $id ] ?? $id; }, $ids ) ) );
	}
	foreach ( get_posts( array( 'post_type' => 'hs_search_topic', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'menu_order', 'order' => 'ASC' ) ) as $p ) {
		$rows[] = array( 'quick_answer', 'publish' === $p->post_status ? 1 : 0, html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' ), (string) get_post_meta( $p->ID, 'hs_topic_synonyms', true ), 'id=' . $p->ID );
	}
	$terms = get_option( 'hs_search_terms', array() );
	$cut   = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
	foreach ( (array) $terms as $q => $t ) {
		$c30 = 0;
		$m30 = 0;
		foreach ( (array) ( $t['d_commits'] ?? array() ) as $d => $n ) { $c30 += $d >= $cut ? (int) $n : 0; }
		foreach ( (array) ( $t['d_misses'] ?? array() ) as $d => $n ) { $m30 += $d >= $cut ? (int) $n : 0; }
		$rows[] = array( 'report', '', $q, sprintf( '%d searches (30 days), %d all time; %d with no results (30 days), %d all time', $c30, (int) ( $t['commits'] ?? 0 ), $m30, (int) ( $t['misses'] ?? 0 ) ), 'read-only' );
	}
	return $rows;
}

add_action( 'admin_post_hs_search_vocab_export', function () {
	if ( ! current_user_can( 'edit_others_posts' ) || ! check_admin_referer( 'hs_search_vocab_csv' ) ) {
		wp_die( 'Not allowed' );
	}
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="site-search-terms-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel opens Thai and curly quotes correctly
	foreach ( hs_search_vocab_csv_rows() as $r ) {
		fputcsv( $out, $r );
	}
	fclose( $out );
	exit;
} );

/** Parse an uploaded CSV into a proposed vocabulary + quick-answer word changes. */
function hs_search_vocab_parse_csv( $path ) {
	$v       = hs_search_vocab();
	$new     = array();
	$qa      = array();
	$warn    = array();
	$known   = array();
	$file    = hs_search_index_file();
	if ( file_exists( $file ) ) {
		$j = json_decode( (string) file_get_contents( $file ), true );
		foreach ( (array) ( $j['items'] ?? array() ) as $it ) {
			$known[ $it['id'] ] = 1;
		}
	}
	$fh = fopen( $path, 'r' );
	if ( ! $fh ) {
		return new WP_Error( 'csv', 'Could not read the file.' );
	}
	$line = 0;
	$head = null;
	while ( false !== ( $r = fgetcsv( $fh ) ) ) {
		$line++;
		if ( 1 === $line && isset( $r[0] ) ) {
			$r[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $r[0] );
		}
		if ( null === $head ) {
			$head = array_map( 'strtolower', array_map( 'trim', $r ) );
			if ( array( 'type', 'on', 'key', 'values' ) !== array_slice( $head, 0, 4 ) ) {
				fclose( $fh );
				return new WP_Error( 'csv', 'The first row must be: type, on, key, values, info (use the exported file as the template).' );
			}
			continue;
		}
		$type = strtolower( trim( $r[0] ?? '' ) );
		$on   = '' === trim( (string) ( $r[1] ?? '1' ) ) ? 1 : ( in_array( strtolower( trim( $r[1] ) ), array( '0', 'no', 'off', 'false' ), true ) ? 0 : 1 );
		$key  = sanitize_text_field( $r[2] ?? '' );
		$val  = sanitize_text_field( $r[3] ?? '' );
		$info = (string) ( $r[4] ?? '' );
		if ( '' === $type || 'report' === $type ) {
			continue;
		}
		switch ( $type ) {
			case 'campus':
				if ( in_array( $key, array( 'chaofah', 'cherngtalay' ), true ) && $val ) {
					$new['campus'][ $key ] = $val;
				} else {
					$warn[] = "Line $line: campus key must be chaofah or cherngtalay.";
				}
				break;
			case 'group':
				if ( count( hs_search_split_list( $val ) ) > 1 ) {
					$new['groups'][] = array( 'w' => $val, 'on' => $on );
				} else {
					$warn[] = "Line $line: a group needs at least two words.";
				}
				break;
			case 'expand':
				if ( $key && $val ) {
					$new['expand'][] = array( 'f' => $key, 't' => $val, 'on' => $on );
				} else {
					$warn[] = "Line $line: an expansion needs a key and values.";
				}
				break;
			case 'promote':
				$ids     = array_slice( array_filter( array_map( 'trim', explode( ',', preg_replace( '/[^a-z0-9_,]/i', '', $val ) ) ) ), 0, 5 );
				$missing = array_diff( $ids, array_keys( $known ) );
				if ( $missing ) {
					$warn[] = "Line $line: unknown result id(s) " . implode( ', ', $missing ) . ' (they will be ignored until they exist).';
				}
				if ( $key && $ids ) {
					$new['promote'][] = array( 'term' => $key, 'ids' => implode( ',', $ids ), 'on' => $on );
				}
				break;
			case 'quick_answer':
				$id = preg_match( '/id=(\d+)/', $info, $m ) ? (int) $m[1] : 0;
				$p  = $id ? get_post( $id ) : null;
				if ( ! $p || 'hs_search_topic' !== $p->post_type ) {
					$found = get_posts( array( 'post_type' => 'hs_search_topic', 'post_status' => 'any', 'title' => $key, 'numberposts' => 1 ) );
					$p     = $found ? $found[0] : null;
				}
				if ( $p ) {
					$old = (string) get_post_meta( $p->ID, 'hs_topic_synonyms', true );
					if ( hs_search_split_list( $old ) !== hs_search_split_list( $val ) ) {
						$qa[ $p->ID ] = array( 'title' => $p->post_title, 'old' => $old, 'new' => $val );
					}
				} else {
					$warn[] = "Line $line: no quick answer called \"$key\" (create it first, then import).";
				}
				break;
			default:
				$warn[] = "Line $line: unknown type \"$type\" (skipped).";
		}
	}
	fclose( $fh );
	return array( 'vocab' => $new, 'qa' => $qa, 'warn' => $warn, 'current' => $v );
}

add_action( 'admin_post_hs_search_vocab_import', function () {
	if ( ! current_user_can( 'edit_others_posts' ) || ! check_admin_referer( 'hs_search_vocab_csv' ) ) {
		wp_die( 'Not allowed' );
	}
	$key  = 'hs_search_import_' . get_current_user_id();
	$back = admin_url( 'admin.php?page=hs-site-search-terms' );
	if ( isset( $_POST['apply'] ) ) {
		$pending = get_transient( $key );
		if ( is_array( $pending ) ) {
			$cur    = hs_search_vocab();
			$backup = array( 'vocab' => $cur, 'qa' => array(), 'at' => current_time( 'mysql' ) );
			foreach ( $pending['qa'] as $id => $c ) {
				$backup['qa'][ $id ] = (string) get_post_meta( $id, 'hs_topic_synonyms', true );
				update_post_meta( $id, 'hs_topic_synonyms', $c['new'] );
				update_post_meta( $id, '_hs_topic_synonyms', 'field_hs_search_synonyms' );
			}
			foreach ( array( 'campus', 'groups', 'expand', 'promote' ) as $sec ) {
				if ( isset( $pending['vocab'][ $sec ] ) ) {
					$cur[ $sec ] = 'campus' === $sec ? array_merge( $cur['campus'], $pending['vocab']['campus'] ) : $pending['vocab'][ $sec ];
				}
			}
			update_option( 'hs_search_vocab_backup', $backup, false );
			update_option( 'hs_search_vocab', $cur, false );
			delete_transient( $key );
			hs_search_schedule_build();
		}
		wp_safe_redirect( add_query_arg( 'hs_msg', 'imported', $back ) );
		exit;
	}
	if ( isset( $_POST['cancel'] ) ) {
		delete_transient( $key );
		wp_safe_redirect( $back );
		exit;
	}
	if ( isset( $_POST['restore'] ) ) {
		$b = get_option( 'hs_search_vocab_backup' );
		if ( is_array( $b ) ) {
			update_option( 'hs_search_vocab', $b['vocab'], false );
			foreach ( (array) $b['qa'] as $id => $old ) {
				update_post_meta( $id, 'hs_topic_synonyms', $old );
			}
			delete_option( 'hs_search_vocab_backup' );
			hs_search_schedule_build();
		}
		wp_safe_redirect( add_query_arg( 'hs_msg', 'restored', $back ) );
		exit;
	}
	if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) {
		wp_safe_redirect( add_query_arg( 'hs_msg', 'nofile', $back ) );
		exit;
	}
	$parsed = hs_search_vocab_parse_csv( $_FILES['csv']['tmp_name'] );
	if ( is_wp_error( $parsed ) ) {
		set_transient( $key . '_err', $parsed->get_error_message(), 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'hs_msg', 'badfile', $back ) );
		exit;
	}
	unset( $parsed['current'] );
	set_transient( $key, $parsed, HOUR_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'hs_msg', 'preview', $back ) . '#hs-import' );
	exit;
} );

/** Export / import box at the top of the Search terms screen. */
add_action( 'hs_search_terms_page_top', function () {
	$key     = 'hs_search_import_' . get_current_user_id();
	$pending = get_transient( $key );
	$v       = hs_search_vocab();
	$msg     = sanitize_key( $_GET['hs_msg'] ?? '' );
	$notes   = array( 'imported' => 'Import applied. The search updates in about 30 seconds. You can undo it below.', 'restored' => 'The previous search terms were restored.', 'nofile' => 'Choose a CSV file first.' );
	if ( isset( $notes[ $msg ] ) ) {
		echo '<div class="notice notice-' . ( 'nofile' === $msg ? 'warning' : 'success' ) . '"><p>' . esc_html( $notes[ $msg ] ) . '</p></div>';
	}
	if ( 'badfile' === $msg ) {
		echo '<div class="notice notice-error"><p>' . esc_html( (string) get_transient( $key . '_err' ) ) . '</p></div>';
	}
	echo '<div id="hs-import" style="background:#fff;border:1px solid #c3c4c7;padding:12px 16px;margin:12px 0 20px">';
	echo '<h2 style="margin-top:4px">Export and import (CSV)</h2>';
	echo '<p>Download everything on this screen (synonym groups, expansions, campus names, promoted results, every quick answer’s search words, and the search report) as one CSV. Edit it, or send it for review, then import it back. Each section that is in the file replaces that section; sections left out are not touched. You see a preview before anything changes.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:16px">';
	wp_nonce_field( 'hs_search_vocab_csv' );
	echo '<input type="hidden" name="action" value="hs_search_vocab_export"><button class="button">Download CSV</button></form>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block">';
	wp_nonce_field( 'hs_search_vocab_csv' );
	echo '<input type="hidden" name="action" value="hs_search_vocab_import"><input type="file" name="csv" accept=".csv,text/csv"> <button class="button">Upload and preview</button></form>';
	$backup = get_option( 'hs_search_vocab_backup' );
	if ( is_array( $backup ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:10px">';
		wp_nonce_field( 'hs_search_vocab_csv' );
		echo '<input type="hidden" name="action" value="hs_search_vocab_import"><button class="button-link" name="restore" value="1" onclick="return confirm(\'Restore the search terms from before the last import?\')">Undo the last import (' . esc_html( $backup['at'] ) . ')</button></form>';
	}
	if ( is_array( $pending ) ) {
		$cnt = function ( $a ) { return is_array( $a ) ? count( $a ) : 0; };
		echo '<h3>Preview of the import</h3><table class="widefat striped" style="max-width:760px"><thead><tr><th>Section</th><th>Now</th><th>After import</th></tr></thead><tbody>';
		foreach ( array( 'groups' => 'Synonym groups', 'expand' => 'One-way expansions', 'promote' => 'Promoted results' ) as $sec => $label ) {
			$after = isset( $pending['vocab'][ $sec ] ) ? $cnt( $pending['vocab'][ $sec ] ) : 'unchanged';
			echo '<tr><td>' . esc_html( $label ) . '</td><td>' . (int) $cnt( $v[ $sec ] ) . '</td><td>' . esc_html( (string) $after ) . '</td></tr>';
		}
		foreach ( array( 'chaofah', 'cherngtalay' ) as $c ) {
			$after = $pending['vocab']['campus'][ $c ] ?? null;
			echo '<tr><td>Campus names: ' . esc_html( ucfirst( $c ) ) . '</td><td>' . esc_html( $v['campus'][ $c ] ) . '</td><td>' . esc_html( null === $after ? 'unchanged' : $after ) . '</td></tr>';
		}
		echo '<tr><td>Quick answers with new search words</td><td colspan="2">' . (int) count( $pending['qa'] ) . '</td></tr></tbody></table>';
		foreach ( $pending['qa'] as $c ) {
			echo '<p style="margin:6px 0"><strong>' . esc_html( $c['title'] ) . '</strong><br><span style="color:#b32d2e">− ' . esc_html( $c['old'] ) . '</span><br><span style="color:#008a20">+ ' . esc_html( $c['new'] ) . '</span></p>';
		}
		if ( $pending['warn'] ) {
			echo '<div class="notice notice-warning inline"><p>' . implode( '<br>', array_map( 'esc_html', $pending['warn'] ) ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:10px">';
		wp_nonce_field( 'hs_search_vocab_csv' );
		echo '<input type="hidden" name="action" value="hs_search_vocab_import"><button class="button button-primary" name="apply" value="1">Apply import</button> <button class="button" name="cancel" value="1">Cancel</button></form>';
	}
	echo '</div>';
} );
