<?php
/**
 * Weekly issue contents tests. Run on headstart.local:
 *   wp eval-file work/weekly-issue-contents/tests/wp-tests.php
 * Creates throwaway weekly updates and removes them at the end.
 */

$pass = 0;
$fail = 0;
$t    = function ( $name, $ok, $info = '' ) use ( &$pass, &$fail ) {
	if ( $ok ) {
		$pass++;
		echo "  ok    $name\n";
	} else {
		$fail++;
		echo "  FAIL  $name" . ( '' !== $info ? " — $info" : '' ) . "\n";
	}
};

$group = function ( $class, $inner ) {
	$attrs = array( 'module' => array( 'decoration' => array( 'attributes' => array( 'desktop' => array( 'value' => array( 'attributes' => array( array( 'name' => 'class', 'value' => $class ) ) ) ) ) ) ) );
	return '<!-- wp:divi/group ' . wp_json_encode( $attrs ) . " -->\n" . $inner . "\n<!-- /wp:divi/group -->\n";
};
$text = function ( $html ) {
	return '<!-- wp:divi/text ' . wp_json_encode( array( 'content' => array( 'innerContent' => array( 'desktop' => array( 'value' => $html ) ) ) ) ) . " /-->\n";
};
$section = function ( $inner ) {
	return "<!-- wp:divi/section -->\n<!-- wp:divi/row -->\n<!-- wp:divi/column -->\n" . $inner . "<!-- /wp:divi/column -->\n<!-- /wp:divi/row -->\n<!-- /wp:divi/section -->\n";
};
$content = function ( $headings, $extra = '' ) use ( $group, $text, $section ) {
	$inner = '';
	foreach ( $headings as $h ) {
		$inner .= $group( 'hs-weekly-article', $text( '<h3>' . esc_html( $h ) . '</h3><p>Body of ' . esc_html( $h ) . '.</p>' ) );
	}
	return $section( $inner . $extra );
};

$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
wp_set_current_user( (int) $admin[0] );
$made = array();
$make = function ( $body, $status = 'draft' ) use ( &$made ) {
	$id = wp_insert_post( wp_slash( array( 'post_type' => 'weekly_update', 'post_status' => $status, 'post_title' => 'Issue contents test', 'post_content' => $body ) ) );
	$made[] = $id;
	return $id;
};

try {
	echo "\nFinding the articles\n";
	$promo = $group( 'hs-weekly-promo', $text( '<h3>Promo: Open Day</h3>' ) );
	$noh3  = $group( 'hs-weekly-article', $text( '<p>No heading here.</p>' ) );
	$found = hs_issue_headings( $content( array( 'Thai Department ONESQA “Exceed Standards”', 'Reception Detectives', 'Sports Day', 'Sports Day' ), $promo . $noh3 ) );
	$t( 'headings in page order, promos left out', array( 'Thai Department ONESQA “Exceed Standards”', 'Reception Detectives', 'Sports Day', 'Sports Day' ) === array_column( $found['items'], 'heading' ), wp_json_encode( $found ) );
	$t( 'keys are the heading slugs; a repeat gets --2', array( 'thai-department-onesqa-exceed-standards', 'reception-detectives', 'sports-day', 'sports-day--2' ) === array_column( $found['items'], 'key' ), wp_json_encode( array_column( $found['items'], 'key' ) ) );
	$t( 'an article without an h3 is counted as skipped', 1 === $found['skipped'] );
	$t( 'content that is not Divi 5: nothing found', array() === hs_issue_headings( '<p>Old issue</p><h3>Not an article</h3>' )['items'] );

	foreach ( get_posts( array( 'post_type' => 'weekly_update', 'post_status' => 'any', 'numberposts' => 4, 'fields' => 'ids', 'post__not_in' => array( 0 ) ) ) as $real ) {
		if ( ! function_exists( 'hs_a2p_articles' ) ) {
			break;
		}
		$a2p  = hs_a2p_articles( $real );
		$mine = hs_issue_headings( get_post_field( 'post_content', $real ) );
		$t( "issue $real: same articles as the Articles → Posts tool (" . count( $mine['items'] ) . ')', array_values( array_column( $a2p['articles'], 'headline' ) ) === array_column( $mine['items'], 'heading' ) && $a2p['skipped'] === $mine['skipped'] );
	}

	echo "\nSaving keeps short names\n";
	$id   = $make( $content( array( 'A very long article heading about the Science Fair', 'Reception Detectives', 'Sports Day' ) ) );
	$list = hs_issue_contents( $id );
	$t( 'saving builds the list', 3 === count( $list ) && '' === $list[0]['label'], wp_json_encode( $list ) );
	hs_issue_contents_refresh( $id, array( 'a-very-long-article-heading-about-the-science-fair' => 'Science Fair', 'sports-day' => '<b>Sports</b> Day' ) );
	$t( 'names show the short name, else the heading; tags stripped', array( 'Science Fair', 'Reception Detectives', 'Sports Day' ) === hs_issue_contents_names( $id ), wp_json_encode( hs_issue_contents_names( $id ) ) );
	wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content( array( 'New First Article', 'A very long article heading about the Science Fair', 'Sports Day' ) ) ) ) );
	$t( 'editing the issue: new article added in order, removed one dropped, short name kept', array( 'New First Article', 'Science Fair', 'Sports Day' ) === hs_issue_contents_names( $id ), wp_json_encode( hs_issue_contents( $id ) ) );
	$t( 'a short name equal to the heading is stored empty', '' === hs_issue_contents( $id )[2]['label'] );

	echo "\nThe edit box's form post\n";
	$_POST = array(
		'hs_issue_contents_nonce' => wp_create_nonce( 'hs_issue_contents_' . $id ),
		'hs_issue_label'          => array( 'new-first-article' => 'First', 'a-very-long-article-heading-about-the-science-fair' => '', 'sports-day' => '', 'bad key!' => 'x' ),
		'hs_issue_show'           => array( 'new-first-article' => '1', 'a-very-long-article-heading-about-the-science-fair' => '1', 'sports-day' => '1' ),
	);
	wp_update_post( array( 'ID' => $id, 'post_title' => 'Issue contents test 2' ) );
	$t( 'names typed in the box are saved; emptying one goes back to the heading', array( 'First', 'A very long article heading about the Science Fair', 'Sports Day' ) === hs_issue_contents_names( $id ), wp_json_encode( hs_issue_contents_names( $id ) ) );
	$_POST['hs_issue_show'] = array( 'new-first-article' => '1', 'sports-day' => '1' );
	wp_update_post( array( 'ID' => $id, 'post_title' => 'Issue contents test 2b' ) );
	$t( 'unticking an article leaves it off the names, but keeps it in the list', array( 'First', 'Sports Day' ) === hs_issue_contents_names( $id ) && 3 === count( hs_issue_contents( $id ) ) && false === hs_issue_contents( $id )[1]['show'], wp_json_encode( hs_issue_contents( $id ) ) );
	hs_issue_contents_refresh( $id );
	$t( 'an untick survives the next save', array( 'First', 'Sports Day' ) === hs_issue_contents_names( $id ) );
	$_POST['hs_issue_show'] = array( 'new-first-article' => '1', 'a-very-long-article-heading-about-the-science-fair' => '1', 'sports-day' => '1' );
	wp_update_post( array( 'ID' => $id, 'post_title' => 'Issue contents test 2c' ) );
	$t( 'ticking it again brings it back', 3 === count( hs_issue_contents_names( $id ) ) );
	$_POST['hs_issue_contents_nonce'] = 'wrong';
	$_POST['hs_issue_label']          = array( 'new-first-article' => 'Hacked' );
	wp_update_post( array( 'ID' => $id, 'post_title' => 'Issue contents test 3' ) );
	$t( 'a bad nonce saves nothing from the form', 'First' === hs_issue_contents_names( $id )[0] );
	$_POST = array();

	wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content( array( 'New First Article', 'A very long article heading about the Science Fair', 'Sports Day', 'Brand New' ) ) ) ) );
	$t( 'a newly added article starts ticked', 'Brand New' === end( hs_issue_contents_names( $id ) ) && true === end( hs_issue_contents( $id ) )['show'] );
	wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content( array( 'New First Article', 'A very long article heading about the Science Fair', 'Sports Day' ) ) ) ) );
	ob_start();
	hs_issue_contents_print_rows( array( 'items' => hs_issue_contents( $id ), 'skipped' => 0 ) );
	$rows = ob_get_clean();
	$t( 'the box flags names over ' . HS_ISSUE_NAME_MAX . ' characters (the long heading with no short name), not short ones', 1 === substr_count( $rows, 'hs-ic-len is-long' ) && false !== strpos( $rows, '50/' . HS_ISSUE_NAME_MAX ) && false !== strpos( $rows, '5/' . HS_ISSUE_NAME_MAX ), $rows );

	echo "\nOld issues keep their tags\n";
	$old = $make( '<p>Imported issue</p>' );
	wp_set_object_terms( $old, array( 'Sports' ), 'in_this_issue' );
	$t( 'no articles: no list, no names', '' === get_post_meta( $old, 'hs_issue_contents', true ) && array() === hs_issue_contents_names( $old ) );
	$GLOBALS['post'] = get_post( $old );
	$t( 'the tags field still shows on an old issue', false !== hs_issue_hide_tags_field( array( 'key' => 'field_weekly_update_tags' ) ) );
	$GLOBALS['post'] = get_post( $id );
	$t( 'the tags field is hidden on an issue with articles', false === hs_issue_hide_tags_field( array( 'key' => 'field_weekly_update_tags' ) ) );
	$t( 'an untagged issue with articles shows the In this issue box', is_array( hs_issue_contents_prepare_field( array( 'key' => 'field_weekly_update_contents' ) ) ) );

	echo "\nTags first: a tagged issue with articles keeps its tags\n";
	$tagged = $make( $content( array( 'Tagged Article One', 'Tagged Article Two' ) ) );
	wp_set_object_terms( $tagged, array( 'Swimming', 'Music' ), 'in_this_issue' );
	hs_issue_contents_refresh( $tagged );
	$t( 'the article list is still stored, but no names are given', 2 === count( hs_issue_contents( $tagged ) ) && array() === hs_issue_contents_names( $tagged ) );
	$GLOBALS['post'] = get_post( $tagged );
	$t( 'its edit screen shows the tags field', false !== hs_issue_hide_tags_field( array( 'key' => 'field_weekly_update_tags' ) ) );
	$t( 'its edit screen hides the In this issue box', false === hs_issue_contents_prepare_field( array( 'key' => 'field_weekly_update_contents' ) ) );
	$no_tags_yet = $make( '' );
	$GLOBALS['post'] = get_post( $no_tags_yet );
	$t( 'a new, empty issue hides the tags field', false === hs_issue_hide_tags_field( array( 'key' => 'field_weekly_update_tags' ) ) );
	$GLOBALS['post'] = get_post( $id );

	$t( 'Archive Post is hidden unless the issue is already marked as imported', false === hs_wu_hide_archive_field( array( 'value' => 0 ) ) && false === hs_wu_hide_archive_field( array( 'value' => '' ) ) && is_array( hs_wu_hide_archive_field( array( 'value' => 1 ) ) ) );

	echo "\nCampus Hub cards\n";
	wp_publish_post( $id );
	wp_update_post( array( 'ID' => $old, 'post_status' => 'publish' ) );
	wp_update_post( array( 'ID' => $tagged, 'post_status' => 'publish' ) );
	$cards = array();
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', function () { return function ( $m ) { throw new Exception( 'die' ); }; } );
	foreach ( array( 1, 2 ) as $page ) {
		$_POST = array( 'nonce' => function_exists( 'hs_weekly_updates_token' ) ? hs_weekly_updates_token() : '', 'campus' => 'all', 'year' => 'all', 'page' => $page );
		ob_start();
		try { hs_ajax_load_weekly_updates_handler(); } catch ( Exception $e ) {} // phpcs:ignore
		$json = json_decode( ob_get_clean(), true );
		foreach ( (array) ( $json['data']['cards'] ?? $json['data'] ?? array() ) as $c ) {
			if ( is_array( $c ) && isset( $c['id'] ) ) {
				$cards[ $c['id'] ] = $c;
			}
		}
	}
	$_POST = array();
	$t( 'an issue with articles shows its article names', isset( $cards[ $id ] ) && array( 'First', 'A very long article heading about the Science Fair', 'Sports Day' ) === $cards[ $id ]['tags'], isset( $cards[ $id ] ) ? wp_json_encode( $cards[ $id ]['tags'] ) : 'card not found (' . count( $cards ) . ' cards)' );
	$t( 'an old issue still shows its tags', isset( $cards[ $old ] ) && array( 'Sports' ) === $cards[ $old ]['tags'], isset( $cards[ $old ] ) ? wp_json_encode( $cards[ $old ]['tags'] ) : 'card not found' );
	$tc = isset( $cards[ $tagged ] ) ? $cards[ $tagged ]['tags'] : null;
	$t( 'a tagged issue with articles shows its tags, not article names', is_array( $tc ) && 2 === count( $tc ) && in_array( 'Swimming', $tc, true ) && ! in_array( 'Tagged Article One', $tc, true ), $tc ? wp_json_encode( $tc ) : 'card not found' );

	echo "\nAdmin list: blurb and In This Issue columns\n";
	update_field( 'field_weekly_update_blurb', 'A <strong>blurb</strong> written on the edit screen.', $id );
	ob_start();
	hs_weekly_update_column_content( 'hs_blurb_col', $id );
	$col = ob_get_clean();
	$t( 'the Blurb column shows the edit screen\'s blurb', false !== strpos( $col, 'A <strong>blurb</strong> written' ) && false === strpos( $col, 'No blurb' ) );
	ob_start();
	hs_weekly_update_column_content( 'hs_tags_col', $id );
	$col = ob_get_clean();
	$t( 'the In This Issue column lists the article names', false !== strpos( $col, '>First<' ) && false !== strpos( $col, '#hs-issue-contents' ) && false === strpos( $col, 'hs-tags-checkbox' ) );
	ob_start();
	hs_weekly_update_column_content( 'hs_tags_col', $old );
	$col = ob_get_clean();
	$t( 'an old issue keeps its tag editor', false !== strpos( $col, 'hs-tags-checkbox' ) && false !== strpos( $col, '>Sports<' ) );
	ob_start();
	hs_weekly_update_column_content( 'hs_tags_col', $tagged );
	$col = ob_get_clean();
	$t( 'a tagged issue with articles shows its tags and tag editor in the list', false !== strpos( $col, 'hs-tags-checkbox' ) && false !== strpos( $col, '>Swimming<' ) && false === strpos( $col, 'Tagged Article One' ) );

	echo "\nBlurb column's Save\n";
	$save = function ( $post_id, $blurb ) {
		$_POST = $_REQUEST = array( 'security' => wp_create_nonce( 'hs_wu_save_blurb' ), 'post_id' => $post_id, 'blurb' => wp_slash( $blurb ) );
		ob_start();
		try { hs_wu_ajax_save_blurb(); } catch ( Exception $e ) {} // phpcs:ignore
		$out   = json_decode( ob_get_clean(), true );
		$_POST = $_REQUEST = array();
		return $out;
	};
	$version = (int) get_option( 'hs_weekly_updates_version', 1 );
	$r = $save( $id, 'Saved from the <em>list</em><script>alert(1)</script>' );
	$t( 'saves to the blurb field, keeping safe HTML only', ! empty( $r['success'] ) && 'Saved from the <em>list</em>alert(1)' === get_post_meta( $id, 'blurb', true ), get_post_meta( $id, 'blurb', true ) );
	$t( 'and clears the Campus Hub cache', (int) get_option( 'hs_weekly_updates_version', 1 ) > $version );
	$editor = wp_insert_user( array( 'user_login' => 'hs_ic_contrib_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'contributor' ) );
	wp_set_current_user( $editor );
	$r = $save( $id, 'Not allowed' );
	$t( 'someone who can\'t edit that issue is refused', empty( $r['success'] ) && 'Saved from the <em>list</em>alert(1)' === get_post_meta( $id, 'blurb', true ) );
	wp_set_current_user( (int) $admin[0] );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $editor );

	remove_filter( 'wp_doing_ajax', '__return_true' );
	echo "\nOne-off fill\n";
	delete_post_meta( $id, 'hs_issue_contents' );
	$tags_before = wp_get_object_terms( $old, 'in_this_issue', array( 'fields' => 'ids' ) );
	$modified    = get_post_field( 'post_modified', $id );
	$flag        = get_option( 'hs_issue_contents_fill_v1' );
	delete_option( 'hs_issue_contents_fill_v1' );
	for ( $i = 0; $i < 10 && ! get_option( 'hs_issue_contents_fill_v1' ); $i++ ) {
		hs_issue_contents_fill_v1();
	}
	$t( 'the fill rebuilds a missing list and then stops itself', 3 === count( hs_issue_contents( $id ) ) && (bool) get_option( 'hs_issue_contents_fill_v1' ) );
	$t( 'the fill changes nothing else (tags, dates)', wp_get_object_terms( $old, 'in_this_issue', array( 'fields' => 'ids' ) ) === $tags_before && get_post_field( 'post_modified', $id ) === $modified );
	if ( $flag ) {
		update_option( 'hs_issue_contents_fill_v1', $flag, false );
	}
} catch ( Throwable $e ) {
	$t( 'tests finished', false, get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getLine() );
} finally {
	$_POST = array();
	foreach ( $made as $m ) {
		wp_delete_post( $m, true );
	}
}
echo "\n$pass passed, $fail failed\n";
