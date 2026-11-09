<?php
/**
 * Articles-to-Posts tests. Run on headstart.local: wp eval-file work/weekly-articles-to-posts/tests/wp-tests.php
 * A temporary weekly update, Posts, users and terms are created, then force-deleted.
 */
$pass = 0; $fail = 0; $made = array(); $users = array(); $terms = array();
$t = function ( $name, $ok, $detail = '' ) use ( &$pass, &$fail ) {
	if ( $ok ) { $pass++; echo "  ok    $name\n"; }
	else { $fail++; echo "  FAIL  $name" . ( '' !== $detail ? " — $detail" : '' ) . "\n"; }
};

$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_current_user( $admin->ID );

$image_id = (int) get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) )[0];
$image_url = wp_get_attachment_url( $image_id );

$v = '5.0.0-public-beta.4.1';
$text = function ( $html, $label = 'Body' ) use ( $v ) {
	return '<!-- wp:divi/text ' . wp_json_encode( array(
		'module' => array( 'meta' => array( 'adminLabel' => array( 'desktop' => array( 'value' => $label ) ) ) ),
		'content' => array( 'innerContent' => array( 'desktop' => array( 'value' => $html ) ) ),
		'builderVersion' => $v,
	), JSON_HEX_TAG | JSON_HEX_QUOT ) . ' /-->';
};
$article = function ( $inner, $class = 'hs-weekly-article' ) use ( $v ) {
	return '<!-- wp:divi/group ' . wp_json_encode( array(
		'module' => array( 'decoration' => array( 'attributes' => array( 'desktop' => array( 'value' => array( 'attributes' => array(
			array( 'id' => 'sameidsameid', 'name' => 'class', 'value' => $class, 'targetElement' => 'main' ),
		) ) ) ) ) ),
		'builderVersion' => $v,
	) ) . ' -->' . $inner . '<!-- /wp:divi/group -->';
};
$image = '<!-- wp:divi/image ' . wp_json_encode( array( 'image' => array( 'innerContent' => array( 'desktop' => array( 'value' => array( 'src' => $image_url, 'id' => (string) $image_id ) ) ) ), 'builderVersion' => $v ), JSON_UNESCAPED_SLASHES ) . ' /-->';
$gallery = '<!-- wp:divi/gallery ' . wp_json_encode( array( 'image' => array( 'advanced' => array( 'galleryIds' => array( 'desktop' => array( 'value' => (string) $image_id ) ) ) ), 'builderVersion' => $v ) ) . ' /-->';

$fixture = function ( $extra_top = '', $first_body = 'First body text.' ) use ( $text, $article, $image, $gallery, $v ) {
	$wrap = wp_json_encode( array( 'builderVersion' => $v ) );
	return '<!-- wp:divi/placeholder --><!-- wp:divi/section ' . $wrap . ' --><!-- wp:divi/row ' . wp_json_encode( array( 'module' => array( 'meta' => array( 'adminLabel' => array( 'desktop' => array( 'value' => 'CARD ROW' ) ) ) ), 'builderVersion' => $v ) ) . ' --><!-- wp:divi/column ' . $wrap . ' -->'
		. $text( '<h2 class="hs-section">primary</h2>', 'Section Heading' )
		. $extra_top
		. $article( $text( '<h3>First Article Headline</h3>', 'Heading' ) . $text( "<p>$first_body</p>" ) . $text( '<h3>A later sub-heading</h3><p>More.</p>' ) . $image . $gallery )
		. $text( '<h2 class="hs-section">PE &amp; Sports</h2>', 'Section Heading' )
		. $article( $text( '<h3>Sports Article</h3><p>Heading and text share this module.</p>', 'Heading' ) )
		. $article( $text( '<p style="color: #ffffff;">White text article</p><h3>Dark Styled Article</h3>' ) )
		. $text( '<h2 class="hs-section">Upcoming Events</h2>', 'Section Heading' )
		. $article( $text( '<h3>Repeat Headline</h3>' ) )
		. $article( $text( '<h3>Repeat Headline</h3><p>Second.</p>' ) )
		. $article( $image ) // no h3: left out and counted
		. '<!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';
};

$issue = wp_insert_post( wp_slash( array( 'post_type' => 'weekly_update', 'post_status' => 'publish', 'post_title' => 'A2P test issue', 'post_content' => $fixture(), 'post_date' => '2026-10-30 09:00:00' ) ) );
$made[] = $issue;
wp_set_object_terms( $issue, 'chaofah', 'hs_campus' );

$section_id = fn( $name ) => (int) get_term_by( 'name', $name, 'hs_department' )->term_id;
$badge_id   = (int) get_term_by( 'name', 'Spotlight', 'hs_thematic_chip' )->term_id;
$primary    = $section_id( 'Primary' );
$good_row = function ( $over = array() ) use ( $primary, $badge_id, $image_id ) {
	return array_merge( array(
		'tick' => true, 'title' => 'A2P Test Post', 'section' => $primary, 'customSection' => '', 'campus' => array( 'chaofah' ), 'tags' => array( 'A2P test tag' ),
		'badge' => $badge_id, 'location' => '', 'date' => '2026-10-30T09:00', 'image' => $image_id, 'portrait' => 0, 'status' => 'live',
	), $over );
};
$track = function ( $issue_id ) use ( &$made ) {
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => HS_A2P_SOURCE_META, 'meta_compare' => 'LIKE', 'meta_value' => $issue_id . ':' ) ) as $id ) {
		$made[] = $id;
	}
};

try {
	echo "\nFinding articles\n";
	$found = hs_a2p_articles( $issue );
	$keys  = array_keys( $found['articles'] );
	$t( 'divi 5 detected', true === $found['divi5'] );
	$t( 'five articles with headlines', 5 === count( $keys ), implode( ', ', $keys ) );
	$t( 'one article without h3 left out and counted', 1 === $found['skipped'] );
	$t( 'headline read', 'First Article Headline' === $found['articles']['first-article-headline']['headline'] );
	$t( 'section heading read', 'primary' === $found['articles']['first-article-headline']['section'] );
	$t( 'section changes after next heading', 'PE & Sports' === $found['articles']['sports-article']['section'], $found['articles']['sports-article']['section'] );
	$t( 'repeated headline gets a counter', isset( $found['articles']['repeat-headline'], $found['articles']['repeat-headline--2'] ) );
	$t( 'white text detected', true === $found['articles']['dark-styled-article']['dark'] );
	$t( 'no white text on a normal article', false === $found['articles']['first-article-headline']['dark'] );
	$bg_only = array( 'attrs' => array( 'x' => 'background-color: #ffffff' ), 'blockName' => 'divi/text', 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
	$t( 'white background alone is not white text', false === hs_a2p_has_white_text( $bg_only ) );
	$t( 'article images found, including gallery, without repeats', array( $image_id ) === hs_a2p_article_image_ids( $found['articles']['first-article-headline']['block'] ) );

	$plain = wp_insert_post( array( 'post_type' => 'weekly_update', 'post_status' => 'publish', 'post_title' => 'A2P plain', 'post_content' => '<!-- wp:html --><article class="hs-weekly-article"><h3>Old</h3></article><!-- /wp:html -->' ) );
	$made[] = $plain;
	$pf = hs_a2p_articles( $plain );
	$t( 'plain-HTML issue lists nothing and is flagged', false === $pf['divi5'] && ! $pf['articles'] && false === hs_a2p_panel_data( $plain )['divi5'] );

	echo "\nKey stability\n";
	hs_a2p_save_rows( $issue, array( 'first-article-headline' => $good_row( array( 'tick' => false, 'title' => 'Saved Title' ) ) ) );
	wp_update_post( wp_slash( array( 'ID' => $issue, 'post_content' => $fixture( $article( $text( '<h3>New Article On Top</h3>' ) ), 'Changed body text.' ) ) ) );
	$data = hs_a2p_panel_data( $issue );
	$by   = array_column( $data['rows'], null, 'key' );
	$t( 'row stays on its article after edits above it and text changes', 'Saved Title' === $by['first-article-headline']['fields']['title'] );
	$t( 'new article gets a fresh row', isset( $by['new-article-on-top'] ) && false === $by['new-article-on-top']['fields']['tick'] );
	wp_update_post( wp_slash( array( 'ID' => $issue, 'post_content' => str_replace( 'First Article Headline', 'Renamed Headline', $fixture() ) ) ) );
	$by = array_column( hs_a2p_panel_data( $issue )['rows'], null, 'key' );
	$t( 'renamed headline: saved row shows as not found', isset( $by['first-article-headline'] ) && false === $by['first-article-headline']['found'] );
	$t( 'renamed article gets a new unattached row', isset( $by['renamed-headline'] ) && 'Renamed Headline' === $by['renamed-headline']['fields']['title'] );
	delete_post_meta( $issue, HS_A2P_ROWS_META );
	wp_update_post( wp_slash( array( 'ID' => $issue, 'post_content' => $fixture() ) ) );

	echo "\nPre-filling\n";
	$section_of = fn( $h ) => hs_a2p_section_for_heading( $h );
	$t( 'exact name ignoring capitals', $section_of( 'PRIMARY' ) === $primary && $section_of( 'foundation' ) === $section_id( 'Foundation' ) );
	foreach ( array( 'PE & Sports' => 'Sports Academies', 'Academy News' => 'Sports Academies', 'ACADEMY News' => 'Sports Academies', 'SPORTS & ACADEMIES' => 'Sports Academies', 'ECA News' => 'ECA', 'CAPA News' => 'CAPA', 'Thai Department' => 'Thai', 'Languages Department' => 'World Languages', 'Langauges Department' => 'World Languages', 'House Update' => 'School Houses', 'sCHOOL hOUSES' => 'School Houses', 'Alumni Network' => 'HeadStart Alumni' ) as $heading => $name ) {
		$t( "alias: $heading → $name", $section_of( $heading ) === $section_id( $name ) );
	}
	foreach ( array( 'Upcoming Events', 'Student Voice', 'Sustainability Initiatives', 'Library News', '' ) as $heading ) {
		$t( "no match stays empty: '$heading'", 0 === $section_of( $heading ) );
	}
	$t( 'campus comes from the weekly update', array( 'chaofah' ) === hs_a2p_campus_for_issue( $issue ) );
	$by = array_column( hs_a2p_panel_data( $issue )['rows'], null, 'key' );
	$d  = $by['first-article-headline']['fields'];
	$t( 'row defaults: unticked, Live, headline, Section, date; tags, badge, image empty', false === $d['tick'] && 'live' === $d['status'] && 'First Article Headline' === $d['title'] && $d['section'] === $primary && '2026-10-30T09:00' === $d['date'] && array() === $d['tags'] && 0 === $d['badge'] && 0 === $d['image'] );
	$t( 'dark article defaults to Draft', 'draft' === $by['dark-styled-article']['fields']['status'] );

	echo "\nRequired fields\n";
	$t( 'a complete Live row has nothing missing', array() === hs_a2p_missing_fields( hs_a2p_sanitize_row( $good_row() ) ) );
	foreach ( array( 'Title' => array( 'title' => '' ), 'Website Section' => array( 'section' => 0 ), 'Campus' => array( 'campus' => array() ), 'Thematic Tags' => array( 'tags' => array() ), 'Yellow Badge' => array( 'badge' => 0 ), 'Publish date' => array( 'date' => '' ), 'Featured image' => array( 'image' => 0 ) ) as $name => $over ) {
		$t( "missing $name is reported", in_array( $name, hs_a2p_missing_fields( hs_a2p_sanitize_row( $good_row( $over ) ) ), true ) );
	}
	$t( 'Draft row needs no featured image', array() === hs_a2p_missing_fields( hs_a2p_sanitize_row( $good_row( array( 'status' => 'draft', 'image' => 0 ) ) ) ) );
	$t( 'overrides and portrait are optional', array() === hs_a2p_missing_fields( hs_a2p_sanitize_row( $good_row( array( 'location' => '', 'portrait' => 0 ) ) ) ) );
	$t( 'custom Section needs a name', in_array( 'Website Section', hs_a2p_missing_fields( hs_a2p_sanitize_row( $good_row( array( 'section' => 'custom' ) ) ) ), true ) );
	$t( 'an unknown campus is dropped', array( 'chaofah' ) === hs_a2p_sanitize_row( $good_row( array( 'campus' => array( 'chaofah', 'evil' ) ) ) )['campus'] );

	echo "\nIncomplete rows never touch the weekly update\n";
	$before = get_post( $issue );
	$r = hs_a2p_create_posts( $issue, array( 'sports-article' => $good_row( array( 'tags' => array() ) ), 'repeat-headline' => $good_row( array( 'tick' => false ) ) ) );
	$t( 'ticked row missing a field is skipped and named', 0 === count( $r['created'] ) && 1 === count( $r['skipped'] ) && false !== strpos( $r['skipped'][0]['reason'], 'Thematic Tags' ), wp_json_encode( $r ) );
	$t( 'unticked row has no rules and makes no Post', 0 === hs_a2p_find_post( $issue, 'repeat-headline' ) );
	$after = get_post( $issue );
	$t( 'weekly update content and modified date unchanged', $before->post_content === $after->post_content && $before->post_modified === $after->post_modified );
	$saved_ok = wp_update_post( array( 'ID' => $issue, 'post_title' => 'A2P test issue' ), true );
	$t( 'normal Update of the weekly update still works with an incomplete row', ! is_wp_error( $saved_ok ) );

	echo "\nCreate: Live\n";
	$src_before = get_post_field( 'post_content', $issue );
	$r    = hs_a2p_create_posts( $issue, array( 'first-article-headline' => $good_row( array( 'tags' => array( 'A2P test tag', 'ACADEMIC: Literacy & Phonics' ) ) ), 'sports-article' => $good_row( array( 'tick' => false ) ) ) );
	$track( $issue );
	$live = (int) ( $r['created'][0]['id'] ?? 0 );
	$t( 'one Post created', 1 === count( $r['created'] ) && 0 === count( $r['skipped'] ), wp_json_encode( $r ) );
	$p = get_post( $live );
	$t( 'status publish, title', 'publish' === $p->post_status && 'A2P Test Post' === $p->post_title, $p->post_status );
	$t( 'chosen date kept, with gmt', '2026-10-30 09:00:00' === $p->post_date && get_gmt_from_date( '2026-10-30 09:00:00' ) === $p->post_date_gmt, $p->post_date );
	$t( 'featured image set', $image_id === (int) get_post_thumbnail_id( $live ) );
	$t( 'builder on', 'on' === get_post_meta( $live, '_et_pb_use_builder', true ) );
	$t( 'section, campus, badge terms', array( 'Primary' ) === wp_get_object_terms( $live, 'hs_department', array( 'fields' => 'names' ) ) && array( 'chaofah' ) === wp_get_object_terms( $live, 'hs_post_campus', array( 'fields' => 'slugs' ) ) && array( 'Spotlight' ) === wp_get_object_terms( $live, 'hs_thematic_chip', array( 'fields' => 'names' ) ) );
	$tag_names = array_map( fn( $n ) => html_entity_decode( $n, ENT_QUOTES, 'UTF-8' ), wp_get_object_terms( $live, 'post_tag', array( 'fields' => 'names' ) ) );
	$t( 'tags set', 2 === count( $tag_names ) && in_array( 'A2P test tag', $tag_names, true ) && in_array( 'ACADEMIC: Literacy & Phonics', $tag_names, true ), implode( ' | ', $tag_names ) );
	$t( 'existing tag with & is reused, not duplicated', 1 === count( get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false, 'search' => 'Literacy' ) ) ) );
	$t( 'chips: badge replaces Section, campus shown', 'Spotlight' === get_post_meta( $live, 'hs_final_section_chip', true ) && 'Chaofah City Campus' === get_post_meta( $live, 'hs_final_location_chip', true ), get_post_meta( $live, 'hs_final_section_chip', true ) . ' / ' . get_post_meta( $live, 'hs_final_location_chip', true ) );
	$c = $p->post_content;
	$t( 'content has the article and is valid blocks', 'divi/placeholder' === parse_blocks( $c )[0]['blockName'] && false !== strpos( $c, 'hs-weekly-article' ) && false !== strpos( $c, 'First body text.' ) );
	$t( 'escaped angle brackets survived the save', false !== strpos( $c, '\\u003cp\\u003e' ) && false === strpos( $c, 'u003cu003c' ) );
	$t( 'first h3 removed', false === strpos( $c, 'First Article Headline' ) );
	$t( 'later h3 kept', false !== strpos( $c, 'A later sub-heading' ) );
	$t( 'gallery and image copied', false !== strpos( $c, 'wp:divi/gallery' ) && false !== strpos( $c, 'wp:divi/image' ) );
	$t( 'wrapper has no copied row settings', false === strpos( $c, 'CARD ROW' ) );
	$t( 'weekly update not changed by creating a Post', $src_before === get_post_field( 'post_content', $issue ) );
	$t( 'source recorded', hs_a2p_source_value( $issue, 'first-article-headline' ) === get_post_meta( $live, HS_A2P_SOURCE_META, true ) );
	$by = array_column( hs_a2p_panel_data( $issue )['rows'], null, 'key' );
	$t( 'panel shows the row as Live with a View link', 'Live' === ( $by['first-article-headline']['post']['label'] ?? '' ) && '' !== $by['first-article-headline']['post']['view'] );

	$r2 = hs_a2p_create_posts( $issue, array( 'first-article-headline' => $good_row() ) );
	$t( 'second run makes no duplicate', 0 === count( $r2['created'] ) );

	$http = wp_remote_get( get_permalink( $live ), array( 'timeout' => 30, 'redirection' => 3 ) );
	$t( 'Live Post is public (logged out): 200 with its title', 200 === wp_remote_retrieve_response_code( $http ) && false !== stripos( wp_remote_retrieve_body( $http ), 'A2P Test Post' ), (string) wp_remote_retrieve_response_code( $http ) );

	echo "\nRe-copy\n";
	wp_update_post( wp_slash( array( 'ID' => $issue, 'post_content' => $fixture( '', 'Edited article body.' ) ) ) );
	$t( 'editing the weekly update does not change the Post', false !== strpos( get_post_field( 'post_content', $live ), 'First body text.' ) );
	$before_title = get_post_field( 'post_title', $live );
	$done = hs_a2p_recopy( $live );
	$t( 'Re-copy replaces the content', ! is_wp_error( $done ) && false !== strpos( get_post_field( 'post_content', $live ), 'Edited article body.' ) );
	$t( 'Re-copy keeps title, status and date', $before_title === get_post_field( 'post_title', $live ) && 'publish' === get_post_status( $live ) && '2026-10-30 09:00:00' === get_post_field( 'post_date', $live ) );
	$t( 'Re-copy keeps escaped angle brackets', false !== strpos( get_post_field( 'post_content', $live ), '\\u003cp\\u003e' ) );

	echo "\nCreate: Draft, future date, overrides, Both Campuses\n";
	$r = hs_a2p_create_posts( $issue, array(
		'sports-article'      => $good_row( array( 'title' => 'A2P Draft', 'status' => 'draft', 'image' => 0 ) ),
		'dark-styled-article' => $good_row( array( 'title' => 'A2P Future', 'date' => '2036-01-15T08:30' ) ),
		'repeat-headline'     => $good_row( array( 'title' => 'A2P Override', 'location' => 'Bangkok Trip', 'portrait' => $image_id ) ),
		'repeat-headline--2'  => $good_row( array( 'title' => 'A2P Both', 'campus' => array( 'chaofah', 'cherngtalay' ) ) ),
	) );
	$track( $issue );
	$ids = array_column( $r['created'], 'id', 'title' );
	$t( 'four created', 4 === count( $ids ), wp_json_encode( $r ) );
	$draft = $ids['A2P Draft'] ?? 0;
	$t( 'Draft row stays a draft with its date', 'draft' === get_post_status( $draft ) && '2026-10-30 09:00:00' === get_post_field( 'post_date', $draft ) );
	$http = wp_remote_get( add_query_arg( 'p', $draft, home_url( '/' ) ), array( 'timeout' => 30, 'redirection' => 3 ) );
	$t( 'Draft is not public (logged out)', 200 !== wp_remote_retrieve_response_code( $http ) || false === stripos( wp_remote_retrieve_body( $http ), 'A2P Draft' ), (string) wp_remote_retrieve_response_code( $http ) );
	$t( 'future date schedules a Live row', 'future' === get_post_status( $ids['A2P Future'] ?? 0 ), get_post_status( $ids['A2P Future'] ?? 0 ) );
	$o = $ids['A2P Override'] ?? 0;
	$t( 'location override reaches the chip', 'Bangkok Trip' === get_post_meta( $o, 'hs_final_location_chip', true ), get_post_meta( $o, 'hs_final_location_chip', true ) );
	$t( 'location override changes the URL', false !== strpos( get_permalink( $o ), '/bangkok-trip/school-life/' ), get_permalink( $o ) );
	$t( 'portrait saved', $image_id === (int) get_post_meta( $o, 'hs_portrait_image', true ) );
	$b = $ids['A2P Both'] ?? 0;
	$t( 'both campuses: one Post, chip and URL', 'Both Campuses' === get_post_meta( $b, 'hs_final_location_chip', true ) && false !== strpos( get_permalink( $b ), '/both-campuses/' ) );

	echo "\nTrash frees the row\n";
	wp_trash_post( $draft );
	$t( 'a trashed Post no longer holds the row', 0 === hs_a2p_find_post( $issue, 'sports-article' ) );
	wp_untrash_post( $draft );

	echo "\nCustom Section and permissions\n";
	$custom_name = 'A2P Custom Section';
	$issue2 = wp_insert_post( wp_slash( array( 'post_type' => 'weekly_update', 'post_status' => 'publish', 'post_title' => 'A2P test issue 2', 'post_content' => $fixture() ) ) );
	$made[] = $issue2;
	$r = hs_a2p_create_posts( $issue2, array( 'sports-article' => $good_row( array( 'section' => 'custom', 'customSection' => $custom_name ) ) ) );
	$track( $issue2 );
	$new_term = get_term_by( 'name', $custom_name, 'hs_department' );
	if ( $new_term ) { $terms[] = (int) $new_term->term_id; }
	$t( 'admin can create a custom Section', $new_term && has_term( $custom_name, 'hs_department', $r['created'][0]['id'] ?? 0 ) );

	$author = wp_insert_user( array( 'user_login' => 'a2p_author', 'user_pass' => wp_generate_password(), 'user_email' => 'a2p-author@example.invalid', 'role' => 'author' ) );
	$users[] = $author;
	wp_set_current_user( $author );
	$r = hs_a2p_create_posts( $issue2, array( 'first-article-headline' => $good_row( array( 'title' => 'A2P Author Custom', 'section' => 'custom', 'customSection' => 'A2P Forbidden Section' ) ) ) );
	$track( $issue2 );
	$t( 'Author can’t create a custom Section', ! term_exists( 'A2P Forbidden Section', 'hs_department' ) && 1 === count( $r['skipped'] ), wp_json_encode( $r ) );
	$r = hs_a2p_create_posts( $issue2, array( 'repeat-headline' => $good_row( array( 'title' => 'A2P Author Post' ) ) ) );
	$track( $issue2 );
	$aid = $r['created'][0]['id'] ?? 0;
	$t( 'Author-made Post keeps valid Divi content', $aid && false !== strpos( get_post_field( 'post_content', $aid ), 'wp:divi/group' ) && false !== strpos( get_post_field( 'post_content', $aid ), 'hs-weekly-article' ), substr( (string) get_post_field( 'post_content', $aid ), 0, 200 ) );

	$contributor = wp_insert_user( array( 'user_login' => 'a2p_contrib', 'user_pass' => wp_generate_password(), 'user_email' => 'a2p-contrib@example.invalid', 'role' => 'contributor' ) );
	$users[] = $contributor;
	wp_set_current_user( $contributor );
	$r = hs_a2p_create_posts( $issue2, array( 'repeat-headline--2' => $good_row( array( 'title' => 'A2P Contributor' ) ) ) );
	$track( $issue2 );
	$cid = $r['created'][0]['id'] ?? 0;
	$t( 'without publish_posts a Live row stays a draft and is flagged', $cid && 'draft' === get_post_status( $cid ) && ! empty( $r['created'][0]['held'] ), wp_json_encode( $r ) );
	wp_set_current_user( $admin->ID );

	echo "\nAdding tags and badges\n";
	$new_tag = hs_a2p_add_term( 'post_tag', 'A2P New Tag' );
	$new_badge = hs_a2p_add_term( 'hs_thematic_chip', 'A2P New Badge' );
	$t( 'admin can add a tag and a badge', ! is_wp_error( $new_tag ) && ! is_wp_error( $new_badge ) && 'A2P New Badge' === $new_badge['name'] );
	$t( 'adding an existing name reuses it', $new_tag['id'] === hs_a2p_add_term( 'post_tag', 'a2p new tag' )['id'] );
	$t( 'existing tag with & is reused', ! term_exists( 'x', 'post_tag' ) && (int) term_exists( 'ACADEMIC: Literacy & Phonics', 'post_tag' )['term_id'] === hs_a2p_add_term( 'post_tag', 'ACADEMIC: Literacy & Phonics' )['id'] );
	$t( 'only tags and badges can be added this way', is_wp_error( hs_a2p_add_term( 'hs_department', 'A2P Nope' ) ) && is_wp_error( hs_a2p_add_term( 'category', 'A2P Nope' ) ) );
	$t( 'an empty name is refused', is_wp_error( hs_a2p_add_term( 'post_tag', '   ' ) ) );
	$admin_panel = hs_a2p_panel_data( $issue );
	$t( 'new terms are offered in the panel', in_array( 'A2P New Tag', $admin_panel['tags'], true ) && in_array( $new_badge['id'], array_column( $admin_panel['badges'], 'id' ), true ) );
	$t( 'admin sees the add boxes', true === $admin_panel['canTag'] && true === $admin_panel['canBadge'] );
	$issue3 = wp_insert_post( wp_slash( array( 'post_type' => 'weekly_update', 'post_status' => 'publish', 'post_title' => 'A2P test issue 3', 'post_content' => $fixture() ) ) );
	$made[] = $issue3;
	$r = hs_a2p_create_posts( $issue3, array( 'sports-article' => $good_row( array( 'tags' => array( 'A2P New Tag' ), 'badge' => $new_badge['id'] ) ) ) );
	$track( $issue3 );
	$nid = $r['created'][0]['id'] ?? 0;
	$t( 'a Post can use the new tag and badge, and the badge reaches the chip', has_term( 'A2P New Tag', 'post_tag', $nid ) && 'A2P New Badge' === get_post_meta( $nid, 'hs_final_section_chip', true ) );
	wp_set_current_user( $author );
	$t( 'Author can’t add a tag or badge', is_wp_error( hs_a2p_add_term( 'post_tag', 'A2P Forbidden Tag' ) ) && is_wp_error( hs_a2p_add_term( 'hs_thematic_chip', 'A2P Forbidden Badge' ) ) );
	$t( 'Author can still choose an existing tag by name', ! is_wp_error( hs_a2p_add_term( 'post_tag', 'A2P New Tag' ) ) );
	$author_panel = hs_a2p_panel_data( $issue3 );
	$t( 'Author does not see the add boxes', false === $author_panel['canTag'] && false === $author_panel['canBadge'] );
	$r = hs_a2p_create_posts( $issue3, array( 'first-article-headline' => $good_row( array( 'title' => 'A2P Sneaky', 'status' => 'draft', 'tags' => array( 'A2P Forbidden Tag' ) ) ) ) );
	$track( $issue3 );
	$t( 'Author can’t create a tag through a row', ! term_exists( 'A2P Forbidden Tag', 'post_tag' ) && 1 === count( $r['skipped'] ) && 0 === count( $r['created'] ), wp_json_encode( $r ) );
	wp_set_current_user( $admin->ID );

	echo "\nPosts gatekeeper\n";
	$gate = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'A2P gate check' ) );
	$made[] = $gate;
	$t( 'a Post with no image shows as missing fields in the gatekeeper', in_array( 'Featured Image (landscape, used as the hero)', hs_post_gate_missing_fields( $gate ), true ) );
	$t( 'Posts made by the tool pass the gatekeeper', array() === hs_post_gate_missing_fields( $live ) );
} catch ( Throwable $e ) {
	$t( 'test run finished', false, $e->getMessage() . ' at line ' . $e->getLine() );
} finally {
	wp_set_current_user( $admin->ID );
	foreach ( array( $issue, $issue2 ?? 0, $issue3 ?? 0 ) as $i ) {
		if ( $i ) { $track( $i ); }
	}
	foreach ( array_unique( array_filter( $made ) ) as $id ) {
		wp_delete_post( $id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $u ) {
		wp_delete_user( $u );
	}
	foreach ( $terms as $term_id ) {
		wp_delete_term( $term_id, 'hs_department' );
	}
	foreach ( array( 'post_tag' => array( 'A2P test tag', 'A2P New Tag' ), 'hs_thematic_chip' => array( 'A2P New Badge' ) ) as $taxonomy => $names ) {
		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, $taxonomy );
			if ( $term ) { wp_delete_term( $term->term_id, $taxonomy ); }
		}
	}
}
echo "\n$pass passed, $fail failed\n";
