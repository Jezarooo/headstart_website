<?php

/**
 * Weekly-Update--Articles-To-Posts
 */
/**
 * A panel on the weekly update edit screen: tick the articles that should also be
 * stand-alone Posts, fill in their details, and click Create Posts.
 * Each Post is a one-off copy. The weekly update itself is never changed or re-saved.
 * Required fields and colours apply only to ticked rows in this panel.
 * Run only in the administration area. Plan: work/weekly-articles-to-posts/plan.md
 */

const HS_A2P_ROWS_META   = '_hs_a2p_rows';
const HS_A2P_SOURCE_META = '_hs_a2p_source';
const HS_A2P_ARTICLE     = 'hs-weekly-article';

/* ---------------------------------------------------------------------------
 * Finding the articles
 * ------------------------------------------------------------------------ */

function hs_a2p_block_has_class( $b, $class ) {
	$attrs = $b['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? array();
	foreach ( (array) $attrs as $a ) {
		if ( isset( $a['name'], $a['value'] ) && 'class' === $a['name'] && preg_match( '/(^|\s)' . preg_quote( $class, '/' ) . '(\s|$)/', (string) $a['value'] ) ) {
			return true;
		}
	}
	return false;
}

function hs_a2p_block_text( $b ) {
	$v = $b['attrs']['content']['innerContent']['desktop']['value'] ?? '';
	return is_string( $v ) ? $v : '';
}

function hs_a2p_plain( $html ) {
	return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
}

function hs_a2p_first_h3( $blocks ) {
	foreach ( $blocks as $b ) {
		if ( preg_match( '#<h3[^>]*>(.*?)</h3>#is', hs_a2p_block_text( $b ), $m ) ) {
			return hs_a2p_plain( $m[1] );
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			$found = hs_a2p_first_h3( $b['innerBlocks'] );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

/** Normalised headline, used to recognise the same article after the update is edited. */
function hs_a2p_key( $headline ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( $headline ) ) ), '-' );
}

/** Dark-styled articles use inline white text, which disappears on the white Post card. */
function hs_a2p_has_white_text( $block ) {
	return (bool) preg_match( '/(?<![-a-z])color:\s*(?:#fff(?:fff)?\b|white\b|rgb\(\s*255\s*,\s*255\s*,\s*255)/i', wp_json_encode( $block['attrs'], JSON_UNESCAPED_SLASHES ) . serialize_block( $block ) );
}

/** The article's own images, inline images first, then galleries. Attachments only. */
function hs_a2p_article_image_ids( $block ) {
	$inline  = array();
	$gallery = array();
	$walk    = function ( $b ) use ( &$walk, &$inline, &$gallery ) {
		if ( 'divi/image' === $b['blockName'] ) {
			$v  = $b['attrs']['image']['innerContent']['desktop']['value'] ?? array();
			$id = (int) ( $v['id'] ?? 0 );
			if ( ! $id && ! empty( $v['src'] ) ) {
				$id = (int) attachment_url_to_postid( $v['src'] );
			}
			if ( $id ) {
				$inline[] = $id;
			}
		}
		if ( 'divi/gallery' === $b['blockName'] ) {
			$gallery = array_merge( $gallery, wp_parse_id_list( $b['attrs']['image']['advanced']['galleryIds']['desktop']['value'] ?? '' ) );
		}
		foreach ( $b['innerBlocks'] ?? array() as $child ) {
			$walk( $child );
		}
	};
	$walk( $block );
	return array_slice( array_values( array_unique( array_merge( $inline, $gallery ) ) ), 0, 12 );
}

/**
 * The Divi 5 articles in a weekly update.
 * Returns articles (key => headline, section, block, dark), the number left out for having no h3,
 * and whether the update is Divi 5 at all.
 */
function hs_a2p_articles( $issue_id ) {
	$content = (string) get_post_field( 'post_content', $issue_id );
	$result  = array( 'articles' => array(), 'skipped' => 0, 'divi5' => false !== strpos( $content, 'wp:divi/' ) );
	if ( ! $result['divi5'] ) {
		return $result;
	}
	$section = '';
	$walk    = function ( $list ) use ( &$walk, &$section, &$result ) {
		foreach ( $list as $b ) {
			if ( 'divi/group' === $b['blockName'] && hs_a2p_block_has_class( $b, HS_A2P_ARTICLE ) ) {
				$headline = hs_a2p_first_h3( array( $b ) );
				if ( null === $headline || '' === $headline ) {
					$result['skipped']++;
					continue;
				}
				$key = $base = hs_a2p_key( $headline );
				for ( $n = 2; isset( $result['articles'][ $key ] ); $n++ ) {
					$key = $base . '--' . $n;
				}
				$result['articles'][ $key ] = array(
					'key'      => $key,
					'headline' => $headline,
					'section'  => $section,
					'block'    => $b,
					'dark'     => hs_a2p_has_white_text( $b ),
				);
				continue;
			}
			if ( 'divi/text' === $b['blockName'] && preg_match( '#<h2[^>]*class="[^"]*hs-section[^"]*"[^>]*>(.*?)</h2>#is', hs_a2p_block_text( $b ), $m ) ) {
				$section = hs_a2p_plain( $m[1] );
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				$walk( $b['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( $content ) );
	return $result;
}

/* ---------------------------------------------------------------------------
 * Pre-filling a row
 * ------------------------------------------------------------------------ */

/** Website Section for a heading: exact name ignoring capitals, then the aliases. Never a guess. */
function hs_a2p_section_for_heading( $heading ) {
	$aliases = array(
		'pe & sports'          => 'Sports Academies',
		'academy news'         => 'Sports Academies',
		'sports & academies'   => 'Sports Academies',
		'eca news'             => 'ECA',
		'capa news'            => 'CAPA',
		'thai department'      => 'thai', // by slug, so it works before and after the Section is renamed
		'languages department' => 'World Languages',
		'langauges department' => 'World Languages',
		'house update'         => 'School Houses',
		'alumni network'       => 'HeadStart Alumni',
	);
	$heading = strtolower( trim( $heading ) );
	if ( '' === $heading ) {
		return 0;
	}
	$terms = get_terms( array( 'taxonomy' => 'hs_department', 'hide_empty' => false ) );
	$name  = function ( $term ) { return strtolower( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) ); };
	// 1. Exact name. 2. Slug (still finds a Section after it's renamed). 3. Alias, by name or slug.
	foreach ( $terms as $term ) {
		if ( $name( $term ) === $heading ) {
			return (int) $term->term_id;
		}
	}
	foreach ( $terms as $term ) {
		if ( $term->slug === sanitize_title( $heading ) ) {
			return (int) $term->term_id;
		}
	}
	if ( isset( $aliases[ $heading ] ) ) {
		$want = strtolower( $aliases[ $heading ] );
		foreach ( $terms as $term ) {
			if ( $name( $term ) === $want || $term->slug === $want ) {
				return (int) $term->term_id;
			}
		}
	}
	return 0;
}

/** Post campus slugs from the weekly update's campus: by slug, then by name. */
function hs_a2p_campus_for_issue( $issue_id ) {
	$out = array();
	$issue_terms = wp_get_object_terms( $issue_id, 'hs_campus' );
	if ( is_wp_error( $issue_terms ) ) {
		return $out;
	}
	$post_terms = get_terms( array( 'taxonomy' => 'hs_post_campus', 'hide_empty' => false ) );
	foreach ( $issue_terms as $issue_term ) {
		foreach ( $post_terms as $post_term ) {
			if ( $issue_term->slug === $post_term->slug || strtolower( $issue_term->name ) === strtolower( $post_term->name ) ) {
				$out[] = $post_term->slug;
			}
		}
	}
	return array_values( array_unique( $out ) );
}

function hs_a2p_row_defaults( $article, $issue_id ) {
	return array(
		'tick'          => false,
		'title'         => $article['headline'],
		'section'       => hs_a2p_section_for_heading( $article['section'] ),
		'customSection' => '',
		'campus'        => hs_a2p_campus_for_issue( $issue_id ),
		'tags'          => array(),
		'badgeText'     => '',
		'location'      => '',
		'publishNow'    => true,
		'date'          => current_time( 'Y-m-d\TH:i' ),
		'eventStart'    => get_post_time( 'Y-m-d', false, $issue_id ),
		'eventEnd'      => '',
		'heroFocus'     => '',
		'heroMobile'    => 0,
		'heroMobileFocus' => '',
		'image'         => 0,
		'portrait'      => 0,
		'status'        => $article['dark'] ? 'draft' : 'live',
	);
}

/* ---------------------------------------------------------------------------
 * Validating what the panel sends
 * ------------------------------------------------------------------------ */

function hs_a2p_sanitize_row( $raw ) {
	$raw     = is_array( $raw ) ? $raw : array();
	$section = $raw['section'] ?? 0;
	$campus  = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $raw['campus'] ?? array() ) ), array( 'chaofah', 'cherngtalay' ) ) );
	$date    = (string) ( $raw['date'] ?? '' );
	$ymd     = function ( $v ) { $v = (string) $v; return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : ''; };
	return array(
		'tick'          => ! empty( $raw['tick'] ),
		'title'         => sanitize_text_field( (string) ( $raw['title'] ?? '' ) ),
		'section'       => 'custom' === $section ? 'custom' : absint( $section ),
		'customSection' => sanitize_text_field( (string) ( $raw['customSection'] ?? '' ) ),
		'campus'        => $campus,
		'tags'          => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $raw['tags'] ?? array() ) ) ) ),
		'badgeText'     => sanitize_text_field( (string) ( $raw['badgeText'] ?? '' ) ),
		'location'      => sanitize_text_field( (string) ( $raw['location'] ?? '' ) ),
		'publishNow'    => ! empty( $raw['publishNow'] ) && 'false' !== $raw['publishNow'],
		'date'          => preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $date ) ? $date : '',
		'eventStart'    => $ymd( $raw['eventStart'] ?? '' ),
		'eventEnd'      => $ymd( $raw['eventEnd'] ?? '' ),
		'heroFocus'     => hs_a2p_hero_focus( $raw['heroFocus'] ?? '' ),
		'heroMobile'    => absint( $raw['heroMobile'] ?? 0 ),
		'heroMobileFocus' => hs_a2p_hero_focus( $raw['heroMobileFocus'] ?? '' ),
		'image'         => absint( $raw['image'] ?? 0 ),
		'portrait'      => absint( $raw['portrait'] ?? 0 ),
		'status'        => 'draft' === ( $raw['status'] ?? '' ) ? 'draft' : 'live',
	);
}

/** Hero focal point "X Y" (0–100) or ''; the same rule as hs_hero_focus_sanitize() in Posts--Register-Fields. */
function hs_a2p_hero_focus( $value ) {
	if ( function_exists( 'hs_hero_focus_sanitize' ) ) {
		return hs_hero_focus_sanitize( $value );
	}
	return preg_match( '/^\s*(\d{1,3})\s+(\d{1,3})\s*$/', (string) $value, $m ) && (int) $m[1] <= 100 && (int) $m[2] <= 100 ? (int) $m[1] . ' ' . (int) $m[2] : '';
}

/** The required fields a ticked row is missing. These rules exist only in this panel. */
function hs_a2p_missing_fields( $row ) {
	$missing = array();
	if ( '' === $row['title'] ) {
		$missing[] = 'Title';
	}
	if ( 'custom' === $row['section'] ? '' === $row['customSection'] : ! term_exists( (int) $row['section'], 'hs_department' ) ) {
		$missing[] = 'Website Section';
	}
	if ( ! $row['campus'] ) {
		$missing[] = 'Campus';
	}
	if ( ! $row['tags'] ) {
		$missing[] = 'Thematic Tags';
	}
	if ( empty( $row['publishNow'] ) && '' === $row['date'] ) {
		$missing[] = 'Publish date';
	}
	$end = $row['eventEnd'] ?? '';
	if ( '' !== $end && ( '' === ( $row['eventStart'] ?? '' ) || $end < $row['eventStart'] ) ) {
		$missing[] = 'Event date (the end needs a start, on or before it)';
	}
	if ( 'live' === $row['status'] && ! wp_attachment_is_image( $row['image'] ) ) {
		$missing[] = 'Featured image';
	}
	return $missing;
}

/* ---------------------------------------------------------------------------
 * Building the Post content
 * ------------------------------------------------------------------------ */

/** Removes the first h3: the whole module if that is all it holds, otherwise just the heading. */
function hs_a2p_remove_first_h3( &$block ) {
	foreach ( $block['innerBlocks'] as $i => &$child ) {
		$text = hs_a2p_block_text( $child );
		if ( preg_match( '#<h3[^>]*>.*?</h3>#is', $text, $m ) ) {
			$rest = trim( str_replace( $m[0], '', $text ) );
			if ( '' === $rest ) {
				array_splice( $block['innerBlocks'], $i, 1 );
				$null_index = -1;
				foreach ( $block['innerContent'] as $k => $piece ) {
					if ( null === $piece && ++$null_index === $i ) {
						array_splice( $block['innerContent'], $k, 1 );
						break;
					}
				}
			} else {
				$child['attrs']['content']['innerContent']['desktop']['value'] = $rest;
			}
			return true;
		}
		if ( ! empty( $child['innerBlocks'] ) && hs_a2p_remove_first_h3( $child ) ) {
			return true;
		}
	}
	return false;
}

/** The article in a plain Divi section, row and column. The Post template's CSS draws the card. */
function hs_a2p_build_content( $article_block ) {
	hs_a2p_remove_first_h3( $article_block );
	$version = (string) ( $article_block['attrs']['builderVersion'] ?? '5.0.0-public-beta.4.1' );
	$attrs   = wp_json_encode( array( 'builderVersion' => $version ) );
	$column  = wp_json_encode( array( 'module' => array( 'advanced' => array( 'type' => array( 'desktop' => array( 'value' => '4_4' ) ) ) ), 'builderVersion' => $version ) );
	return '<!-- wp:divi/placeholder --><!-- wp:divi/section ' . $attrs . ' --><!-- wp:divi/row ' . $attrs . ' --><!-- wp:divi/column ' . $column . ' -->'
		. serialize_block( $article_block )
		. '<!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';
}

/* ---------------------------------------------------------------------------
 * Creating the Posts
 * ------------------------------------------------------------------------ */

function hs_a2p_source_value( $issue_id, $key ) {
	return (int) $issue_id . ':' . $key;
}

/** The Post already made from this article, ignoring any in the Trash. */
function hs_a2p_find_post( $issue_id, $key ) {
	$found = get_posts( array(
		'post_type'        => 'post',
		'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
		'numberposts'      => 1,
		'fields'           => 'ids',
		'meta_key'         => HS_A2P_SOURCE_META,
		'meta_value'       => hs_a2p_source_value( $issue_id, $key ),
		'suppress_filters' => true,
	) );
	return $found ? (int) $found[0] : 0;
}

/**
 * The term for a typed Yellow Badge override: an existing badge of that name, ignoring capitals, or a new one.
 * Created only when a Post is made, so abandoned text leaves nothing behind. Same permission as the Post screen.
 */
function hs_a2p_badge_term_id( $text ) {
	$existing = term_exists( $text, 'hs_thematic_chip' );
	if ( $existing ) {
		return (int) $existing['term_id'];
	}
	if ( ! current_user_can( get_taxonomy( 'hs_thematic_chip' )->cap->manage_terms ) ) {
		return new WP_Error( 'hs_a2p_term_permission', 'You do not have permission to create the badge "' . $text . '"' );
	}
	$new = wp_insert_term( $text, 'hs_thematic_chip' );
	return is_wp_error( $new ) ? $new : (int) $new['term_id'];
}

/** Adds a Thematic Tag, or returns the existing tag of that name. Same permission as the Post screen. */
function hs_a2p_add_term( $taxonomy, $name ) {
	$name = sanitize_text_field( $name );
	if ( 'post_tag' !== $taxonomy || '' === $name ) {
		return new WP_Error( 'hs_a2p_bad_term', 'Enter a name to add.' );
	}
	$existing = term_exists( $name, $taxonomy );
	if ( ! $existing ) {
		if ( ! current_user_can( get_taxonomy( $taxonomy )->cap->manage_terms ) ) {
			return new WP_Error( 'hs_a2p_term_permission', 'You do not have permission to add to this list.' );
		}
		$existing = wp_insert_term( $name, $taxonomy );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}
	}
	$term = get_term( (int) $existing['term_id'], $taxonomy );
	return array( 'id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) );
}

/** Makes one Post. It is always saved as a draft first, so the gatekeeper checks the finished Post on publish. */
function hs_a2p_create_post( $issue_id, $article, $row ) {
	foreach ( $row['tags'] as $tag ) {
		if ( ! term_exists( $tag, 'post_tag' ) && ! current_user_can( get_taxonomy( 'post_tag' )->cap->manage_terms ) ) {
			return new WP_Error( 'hs_a2p_term_permission', 'You do not have permission to create the tag "' . $tag . '"' );
		}
	}
	$badge_id = 0;
	if ( '' !== $row['badgeText'] ) {
		$badge_id = hs_a2p_badge_term_id( $row['badgeText'] );
		if ( is_wp_error( $badge_id ) ) {
			return $badge_id;
		}
	}
	$section_id = (int) $row['section'];
	if ( 'custom' === $row['section'] ) {
		$existing = term_exists( $row['customSection'], 'hs_department' );
		if ( $existing ) {
			$section_id = (int) $existing['term_id'];
		} elseif ( current_user_can( 'manage_categories' ) ) {
			$new = wp_insert_term( $row['customSection'], 'hs_department' );
			if ( is_wp_error( $new ) ) {
				return $new;
			}
			$section_id = (int) $new['term_id'];
		} else {
			return new WP_Error( 'hs_a2p_custom_section', 'You do not have permission to create a new Website Section' );
		}
	}

	$insert = array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => $row['title'],
		'post_content' => hs_a2p_build_content( $article['block'] ),
	);
	if ( ! empty( $row['publishNow'] ) ) {
		if ( 'live' === $row['status'] ) { // Dated the moment it is created.
			$insert['post_date']     = current_time( 'mysql' );
			$insert['post_date_gmt'] = current_time( 'mysql', true );
		} // A Draft gets no fixed date, so WordPress dates it when it is published.
	} else {
		$local                   = str_replace( 'T', ' ', $row['date'] ) . ':00';
		$insert['post_date']     = $local;
		$insert['post_date_gmt'] = get_gmt_from_date( $local );
	}
	$id = wp_insert_post( wp_slash( $insert ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	update_post_meta( $id, '_et_pb_use_builder', 'on' );
	update_post_meta( $id, HS_A2P_SOURCE_META, hs_a2p_source_value( $issue_id, $article['key'] ) );
	if ( $row['image'] ) {
		set_post_thumbnail( $id, $row['image'] );
	}
	if ( ! empty( $row['heroMobile'] ) && wp_attachment_is_image( $row['heroMobile'] ) ) {
		update_post_meta( $id, 'hs_hero_mobile', (int) $row['heroMobile'] );
		update_post_meta( $id, '_hs_hero_mobile', 'field_hs_hero_mobile' );
		if ( '' !== ( $row['heroMobileFocus'] ?? '' ) ) {
			update_post_meta( $id, 'hs_hero_mobile_focus', $row['heroMobileFocus'] );
			update_post_meta( $id, '_hs_hero_mobile_focus', 'field_hs_hero_mobile_focus' );
		}
	}
	if ( '' !== ( $row['heroFocus'] ?? '' ) ) {
		update_post_meta( $id, 'hs_hero_focus', $row['heroFocus'] );
		update_post_meta( $id, '_hs_hero_focus', 'field_hs_hero_focus' );
	}
	if ( $row['portrait'] ) {
		update_post_meta( $id, 'hs_portrait_image', $row['portrait'] );
		update_post_meta( $id, '_hs_portrait_image', 'field_hs_portrait_image' );
	}
	// Before the terms, so the chip hook sees them. Ymd, as ACF stores date pickers.
	foreach ( array( 'eventStart' => 'hs_event_start', 'eventEnd' => 'hs_event_end' ) as $from => $meta ) {
		if ( '' !== ( $row[ $from ] ?? '' ) ) {
			update_post_meta( $id, $meta, str_replace( '-', '', $row[ $from ] ) );
			update_post_meta( $id, '_' . $meta, 'field_' . $meta );
		}
	}
	if ( '' !== $row['location'] ) {
		update_post_meta( $id, 'hs_location_override', $row['location'] );
		update_post_meta( $id, '_hs_location_override', 'field_hs_location_override' );
	}
	wp_set_object_terms( $id, $row['tags'], 'post_tag' );
	wp_set_object_terms( $id, array( $section_id ), 'hs_department' );
	if ( $badge_id ) { // With no override the existing chip logic shows the Website Section.
		wp_set_object_terms( $id, $badge_id, 'hs_thematic_chip' );
	}
	wp_set_object_terms( $id, $row['campus'], 'hs_post_campus' );

	if ( 'live' === $row['status'] && current_user_can( 'publish_posts' ) ) {
		$published = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
		if ( is_wp_error( $published ) ) {
			return $published;
		}
	}
	return $id;
}

/**
 * Saves the rows, then makes a Post for each ticked row that has none yet.
 * Rows missing a required field are skipped and named; the others still go through.
 */
function hs_a2p_create_posts( $issue_id, $incoming ) {
	$rows    = hs_a2p_save_rows( $issue_id, $incoming );
	$found   = hs_a2p_articles( $issue_id );
	$result  = array( 'created' => array(), 'skipped' => array() );
	foreach ( $rows as $key => $row ) {
		if ( empty( $row['tick'] ) || empty( $found['articles'][ $key ] ) || hs_a2p_find_post( $issue_id, $key ) ) {
			continue;
		}
		$label   = '' !== $row['title'] ? $row['title'] : $found['articles'][ $key ]['headline'];
		$missing = hs_a2p_missing_fields( $row );
		if ( $missing ) {
			$result['skipped'][] = array( 'title' => $label, 'reason' => 'Missing: ' . implode( ', ', $missing ) );
			continue;
		}
		$id = hs_a2p_create_post( $issue_id, $found['articles'][ $key ], $row );
		if ( is_wp_error( $id ) ) {
			$result['skipped'][] = array( 'title' => $label, 'reason' => $id->get_error_message() );
			continue;
		}
		$status = get_post_status( $id );
		$item   = array( 'title' => $label, 'status' => $status, 'id' => $id );
		if ( 'live' === $row['status'] && 'draft' === $status ) {
			$item['held'] = true; // The Posts gatekeeper or the user's permissions kept it as a draft.
		}
		$result['created'][] = $item;
	}
	return $result;
}

/* ---------------------------------------------------------------------------
 * Saved rows and what the panel shows
 * ------------------------------------------------------------------------ */

function hs_a2p_get_rows( $issue_id ) {
	$rows = get_post_meta( $issue_id, HS_A2P_ROWS_META, true );
	return is_array( $rows ) ? $rows : array();
}

/** Writes post meta only. It never saves the weekly update itself. Rows for missing articles are kept. */
function hs_a2p_save_rows( $issue_id, $incoming ) {
	$rows = hs_a2p_get_rows( $issue_id );
	foreach ( (array) $incoming as $key => $raw ) {
		$rows[ sanitize_text_field( (string) $key ) ] = hs_a2p_sanitize_row( $raw );
	}
	update_post_meta( $issue_id, HS_A2P_ROWS_META, wp_slash( $rows ) );
	return $rows;
}

function hs_a2p_image_url( $id ) {
	return $id ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '';
}

function hs_a2p_post_state( $post_id ) {
	$labels = array( 'publish' => 'Live', 'future' => 'Scheduled', 'draft' => 'Draft', 'pending' => 'Pending', 'private' => 'Private' );
	$status = get_post_status( $post_id );
	return array(
		'id'    => $post_id,
		'label' => $labels[ $status ] ?? $status,
		'live'  => 'publish' === $status,
		'view'  => 'publish' === $status ? get_permalink( $post_id ) : '',
		'edit'  => get_edit_post_link( $post_id, 'raw' ),
	);
}

function hs_a2p_term_options( $taxonomy ) {
	$out = array();
	foreach ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $term ) {
		$out[] = array( 'id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) );
	}
	return $out;
}

/** Everything the panel's script needs. */
function hs_a2p_panel_data( $issue_id ) {
	$found = hs_a2p_articles( $issue_id );
	$saved = hs_a2p_get_rows( $issue_id );
	$rows  = array();
	foreach ( $found['articles'] as $key => $article ) {
		$fields = isset( $saved[ $key ] ) ? array_merge( hs_a2p_row_defaults( $article, $issue_id ), array( 'publishNow' => false ), $saved[ $key ] ) : hs_a2p_row_defaults( $article, $issue_id );
		$post   = hs_a2p_find_post( $issue_id, $key );
		$images = array();
		foreach ( hs_a2p_article_image_ids( $article['block'] ) as $image_id ) {
			$url = hs_a2p_image_url( $image_id );
			if ( $url ) {
				$image = array( 'id' => $image_id, 'url' => $url, 'thumb' => (string) wp_get_attachment_image_url( $image_id, 'thumbnail' ) );
				// Real shape for the rack's Portrait / Landscape chip (Posts--Card-Preview); skipped if that snippet is off.
				$images[] = function_exists( 'hs_card_preview_image_size' ) ? array_merge( $image, hs_card_preview_image_size( $image_id ) ) : $image;
			}
		}
		$rows[] = array(
			'key'         => $key,
			'found'       => true,
			'headline'    => $article['headline'],
			'section'     => $article['section'],
			'dark'        => $article['dark'],
			'images'      => $images,
			'fields'      => $fields,
			'imageUrl'    => hs_a2p_image_url( (int) $fields['image'] ),
			'portraitUrl' => hs_a2p_image_url( (int) $fields['portrait'] ),
			'heroMobileUrl' => hs_a2p_image_url( (int) ( $fields['heroMobile'] ?? 0 ) ),
			'post'        => $post ? hs_a2p_post_state( $post ) : null,
		);
	}
	foreach ( $saved as $key => $fields ) {
		if ( ! isset( $found['articles'][ $key ] ) ) {
			$post   = hs_a2p_find_post( $issue_id, $key );
			$rows[] = array(
				'key'      => $key,
				'found'    => false,
				'headline' => '' !== ( $fields['title'] ?? '' ) ? $fields['title'] : $key,
				'fields'   => $fields,
				'post'     => $post ? hs_a2p_post_state( $post ) : null,
			);
		}
	}
	$tags = array();
	foreach ( hs_a2p_term_options( 'post_tag' ) as $tag ) {
		$tags[] = $tag['name'];
	}
	return array(
		'issue'      => (int) $issue_id,
		'divi5'      => $found['divi5'],
		'skipped'    => $found['skipped'],
		'rows'       => $rows,
		'sections'   => hs_a2p_term_options( 'hs_department' ),
		'campuses'   => hs_a2p_term_options( 'hs_post_campus' ),
		'tags'       => $tags,
		'canPublish' => current_user_can( 'publish_posts' ),
		'canSection' => current_user_can( 'manage_categories' ),
		'canTag'     => current_user_can( get_taxonomy( 'post_tag' )->cap->manage_terms ),
		'today'      => current_time( 'Y-m-d' ),
	);
}

/* ---------------------------------------------------------------------------
 * AJAX. The panel saves on its own and never triggers a save of the weekly update.
 * ------------------------------------------------------------------------ */

function hs_a2p_ajax_issue() {
	if ( ! check_ajax_referer( 'hs_a2p', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Your session has expired. Reload the page.' ), 403 );
	}
	$issue_id = absint( $_POST['issue'] ?? 0 );
	if ( 'weekly_update' !== get_post_type( $issue_id ) || ! current_user_can( 'edit_post', $issue_id ) ) {
		wp_send_json_error( array( 'message' => 'You do not have permission to edit this weekly update.' ), 403 );
	}
	return $issue_id;
}

function hs_a2p_ajax_rows() {
	$rows = json_decode( wp_unslash( (string) ( $_POST['rows'] ?? '' ) ), true );
	return is_array( $rows ) ? $rows : array();
}

add_action( 'wp_ajax_hs_a2p_save', function () {
	$issue_id = hs_a2p_ajax_issue();
	hs_a2p_save_rows( $issue_id, hs_a2p_ajax_rows() );
	wp_send_json_success();
} );

add_action( 'wp_ajax_hs_a2p_create', function () {
	$issue_id = hs_a2p_ajax_issue();
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'You do not have permission to create Posts.' ), 403 );
	}
	$result = hs_a2p_create_posts( $issue_id, hs_a2p_ajax_rows() );
	wp_send_json_success( array( 'result' => $result, 'panel' => hs_a2p_panel_data( $issue_id ) ) );
} );

add_action( 'wp_ajax_hs_a2p_add_term', function () {
	hs_a2p_ajax_issue();
	$term = hs_a2p_add_term( sanitize_key( $_POST['taxonomy'] ?? '' ), wp_unslash( (string) ( $_POST['name'] ?? '' ) ) );
	if ( is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => $term->get_error_message() ) );
	}
	wp_send_json_success( $term );
} );

/* ---------------------------------------------------------------------------
 * Screens
 * ------------------------------------------------------------------------ */

add_action( 'add_meta_boxes_weekly_update', function ( $post ) {
	if ( '' === trim( (string) $post->post_content ) ) {
		return;
	}
	add_meta_box( 'hs-a2p-panel', 'Articles → Posts', 'hs_a2p_render_panel', 'weekly_update', 'normal', 'default' );
} );

add_action( 'admin_enqueue_scripts', function () {
	$screen = get_current_screen();
	if ( $screen && 'weekly_update' === $screen->post_type && 'post' === $screen->base ) {
		wp_enqueue_media();
	}
} );

function hs_a2p_render_panel( $post ) {
	$config = array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'hs_a2p' ),
		'panel' => hs_a2p_panel_data( $post->ID ),
	);
	echo '<div id="hs-a2p"></div>';
	echo '<script id="hs-a2p-data" type="application/json">' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';
	add_action( 'admin_print_footer_scripts', 'hs_a2p_print_assets' );
}

/** A note on Posts made by this tool, linking back to the weekly update. */
add_action( 'add_meta_boxes_post', function ( $post ) {
	if ( preg_match( '/^(\d+):/', (string) get_post_meta( $post->ID, HS_A2P_SOURCE_META, true ), $m ) && 'weekly_update' === get_post_type( (int) $m[1] ) ) {
		$issue_id = (int) $m[1];
		add_meta_box(
			'hs-a2p-source',
			'From weekly update',
			function () use ( $issue_id ) {
				$week   = get_post_meta( $issue_id, 'hs_week_number', true );
				$campus = wp_get_object_terms( $issue_id, 'hs_campus', array( 'fields' => 'names' ) );
				$label  = ( '' !== (string) $week ? 'Week ' . $week : get_the_title( $issue_id ) ) . ( is_array( $campus ) && $campus ? ', ' . implode( ', ', $campus ) : '' );
				printf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $issue_id ) ), esc_html( $label ) );
			},
			'post',
			'side',
			'low'
		);
	}
} );

function hs_a2p_print_assets() {
	?>
<style id="hs-a2p-css">
/* Layout: the preview sits beside the form when the meta box is wide (e.g. a 1920 screen), else under it behind a toggle. */
#hs-a2p { container-type: inline-size; }
#hs-a2p .hs-a2p-intro, #hs-a2p .hs-a2p-row, #hs-a2p .hs-a2p-actions, #hs-a2p .hs-a2p-message { max-width: 1100px; }
#hs-a2p .hs-a2p-preview { display: none; padding: 10px 12px 12px; border-top: 1px solid #f0f0f1; }
#hs-a2p .hs-a2p-preview.is-open { display: block; }
#hs-a2p .hs-a2p-quad { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; }
#hs-a2p .hs-a2p-quad > .hs-a2p-f { display: grid; grid-row: span 3; grid-template-rows: subgrid; row-gap: 4px; align-content: start; }
#hs-a2p .hs-a2p-quad > .hs-a2p-f > * { margin: 0; }
#hs-a2p .hs-a2p-quad > .hs-a2p-f > :nth-child(2) { align-self: center; }
#hs-a2p .hs-a2p-quad .hs-a2p-now, #hs-a2p .hs-a2p-quad .hs-a2p-switch { min-height: 0; }
@media (max-width: 960px) { #hs-a2p .hs-a2p-quad { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
#hs-a2p .hs-a2p-pv-toggle { margin: 0 12px 10px; }
@container (min-width: 1400px) {
	#hs-a2p .hs-a2p-row { max-width: 1720px; }
	#hs-a2p .hs-a2p-rowgrid { display: grid; grid-template-columns: minmax(0, 1fr) 560px; align-items: start; }
	#hs-a2p .hs-a2p-pv-toggle { display: none; }
	#hs-a2p .hs-a2p-preview { display: block; grid-column: 2; grid-row: 1; border-top: 0; border-left: 1px solid #f0f0f1; }
}
#hs-a2p .hs-a2p-intro { color: #50575e; margin: 0 0 12px; }
#hs-a2p .hs-a2p-row { border: 1px solid #dcdcde; border-radius: 4px; margin-bottom: 8px; background: #fff; }
#hs-a2p .hs-a2p-head { display: flex; align-items: center; gap: 10px; padding: 10px 12px; flex-wrap: wrap; }
#hs-a2p .hs-a2p-head label { display: flex; align-items: center; gap: 8px; font-weight: 600; flex: 1 1 320px; }
#hs-a2p .hs-a2p-sec { color: #646970; font-size: 12px; }
#hs-a2p .hs-a2p-state { font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 10px; background: #f0f0f1; }
#hs-a2p .hs-a2p-state.is-live { background: #d1f0d8; color: #0a5c1f; }
#hs-a2p .hs-a2p-state.is-missing { background: #fcf0d1; color: #6e4b00; }
#hs-a2p .hs-a2p-body { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; padding: 10px 12px 12px; border-top: 1px solid #f0f0f1; align-items: start; }
@media (max-width: 960px) { #hs-a2p .hs-a2p-body { grid-template-columns: minmax(0, 1fr); } }
#hs-a2p .hs-a2p-g { border: 1px solid #e2e4e7; border-radius: 4px; padding: 8px; background: #fbfbfc; min-width: 0; display: grid; gap: 6px; }
#hs-a2p .hs-a2p-g > h4 { margin: 0 0 2px; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #50575e; }
#hs-a2p .hs-a2p-g > .hs-a2p-hint { margin: 0 2px; }
#hs-a2p .hs-a2p-g-wide, #hs-a2p .hs-a2p-body > .hs-a2p-wide { grid-column: 1 / -1; }
/* Website Section and Campus share row lines, so their second fields line up. */
#hs-a2p .hs-a2p-g-sub { grid-row: span 3; grid-template-rows: subgrid; row-gap: 6px; }
#hs-a2p .hs-a2p-campuses { display: flex; flex-wrap: wrap; align-items: center; min-height: 30px; }
#hs-a2p .hs-a2p-when { display: grid; gap: 4px; }
#hs-a2p .hs-a2p-now { display: flex; align-items: center; gap: 4px; min-height: 30px; }
#hs-a2p .hs-a2p-pair { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; }
#hs-a2p .hs-a2p-f { padding: 6px 8px; border-left: 3px solid transparent; background: #f0f0f1; border-radius: 2px; min-width: 0; }
#hs-a2p .hs-a2p-f.hs-a2p-ok { border-left-color: #00a32a; }
#hs-a2p .hs-a2p-f.hs-a2p-bad { border-left-color: #d63638; background: #fcf0f1; }
#hs-a2p .hs-a2p-f > .hs-a2p-label { display: block; font-weight: 600; margin-bottom: 4px; }
#hs-a2p .hs-a2p-f input[type="text"], #hs-a2p .hs-a2p-f input[type="datetime-local"], #hs-a2p .hs-a2p-f input[type="date"], #hs-a2p .hs-a2p-f select { width: 100%; max-width: 100%; }
#hs-a2p .hs-a2p-hint { color: #646970; font-size: 12px; margin: 4px 0 0; }
#hs-a2p .hs-a2p-warn { grid-column: 1 / -1; background: #fcf0d1; color: #6e4b00; padding: 8px 10px; border-radius: 2px; margin: 8px 0 0; }
#hs-a2p .hs-a2p-f.hs-a2p-wide { grid-column: 1 / -1; }
#hs-a2p .hs-a2p-taglist { max-height: 220px; overflow-y: auto; columns: 3 220px; column-gap: 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 2px; padding: 8px 10px; }
#hs-a2p .hs-a2p-taggroup { break-inside: avoid; margin-bottom: 8px; }
#hs-a2p .hs-a2p-taggroup strong { display: block; font-size: 11px; color: #50575e; margin-bottom: 2px; }
#hs-a2p .hs-a2p-taglist label { display: block; line-height: 1.7; }
#hs-a2p .hs-a2p-add { display: flex; gap: 6px; margin-top: 6px; }
#hs-a2p .hs-a2p-add input[type="text"] { flex: 1 1 auto; min-width: 0; }
#hs-a2p .hs-a2p-add-error { color: #b32d2e; font-size: 12px; margin: 4px 0 0; }
#hs-a2p .hs-a2p-switch label { margin-right: 12px; }
#hs-a2p .hs-a2p-actions { display: flex; align-items: center; gap: 12px; margin-top: 12px; flex-wrap: wrap; }
#hs-a2p .hs-a2p-saved { color: #646970; font-size: 12px; }
#hs-a2p .hs-a2p-message { margin-top: 12px; padding: 8px 12px; border-left: 4px solid #2271b1; background: #f0f6fc; }
#hs-a2p .hs-a2p-message.is-error { border-left-color: #d63638; background: #fcf0f1; }
#hs-a2p .hs-a2p-message ul { margin: 4px 0 0 16px; list-style: disc; }
</style>
<script id="hs-a2p-js">
(function () {
	var root = document.getElementById('hs-a2p');
	var source = document.getElementById('hs-a2p-data');
	if (!root || !source) { return; }
	var config = JSON.parse(source.textContent);
	var panel = config.panel;
	var saveTimer = null;
	var saved = null;
	var message = null;

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'text') { node.textContent = attrs[k]; }
			else if (k === 'on') { Object.keys(attrs.on).forEach(function (e) { node.addEventListener(e, attrs.on[e]); }); }
			else if (k === 'checked' || k === 'disabled' || k === 'selected') { node[k] = !!attrs[k]; }
			else if (k === 'value') { node.value = attrs[k]; }
			else { node.setAttribute(k, attrs[k]); }
		});
		(children || []).forEach(function (c) { if (c) { node.appendChild(c); } });
		return node;
	}

	function post(action, extra) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', config.nonce);
		body.append('issue', panel.issue);
		Object.keys(extra || {}).forEach(function (k) { body.append(k, extra[k]); });
		return fetch(config.ajax, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) {
			return r.json().catch(function () { return { success: false, data: { message: 'The server sent an unexpected reply (' + r.status + ').' } }; });
		});
	}

	function editableRows() {
		return panel.rows.filter(function (r) { return r.found && !r.post; });
	}

	function rowsPayload() {
		var out = {};
		editableRows().forEach(function (r) { out[r.key] = r.fields; });
		return JSON.stringify(out);
	}

	function queueSave() {
		clearTimeout(saveTimer);
		if (saved) { saved.textContent = 'Saving…'; }
		saveTimer = setTimeout(function () {
			post('hs_a2p_save', { rows: rowsPayload() }).then(function (res) {
				if (saved) { saved.textContent = res && res.success ? 'Saved' : 'Not saved: ' + ((res.data && res.data.message) || 'please try again'); }
			}).catch(function () {
				if (saved) { saved.textContent = 'Not saved: check your connection'; }
			});
		}, 700);
	}

	function missing(row) {
		var f = row.fields, m = [];
		if (!String(f.title || '').trim()) { m.push('title'); }
		if (f.section === 'custom' ? !String(f.customSection || '').trim() : !f.section) { m.push('section'); }
		if (!f.campus || !f.campus.length) { m.push('campus'); }
		if (!f.tags || !f.tags.length) { m.push('tags'); }
		if (!f.publishNow && !f.date) { m.push('date'); }
		if (f.eventEnd && (!f.eventStart || f.eventEnd < f.eventStart)) { m.push('event'); }
		if (f.status === 'live' && !f.image) { m.push('image'); }
		return m;
	}

	var NAMES = { title: 'Title', section: 'Website Section', campus: 'Campus', tags: 'Thematic Tags', date: 'Publish date', event: 'Event date (end needs a start, on or before it)', image: 'Featured image' };

	function mark(row) {
		if (!row.dom) { return; }
		var gaps = missing(row);
		Object.keys(row.dom.fields).forEach(function (name) {
			var box = row.dom.fields[name];
			var needed = !(name === 'image' && row.fields.status !== 'live');
			var optional = name === 'event'; // only ever red, never green
			box.classList.toggle('hs-a2p-ok', needed && !optional && gaps.indexOf(name) === -1);
			box.classList.toggle('hs-a2p-bad', needed && gaps.indexOf(name) !== -1);
		});
		if (row.dom.bothHint) {
			row.dom.bothHint.style.display = row.fields.campus.length > 1 ? '' : 'none';
		}
	}

	function changed(row) {
		mark(row);
		updatePreview(row);
		updateActions();
		queueSave();
	}

	function field(row, label, name, children, hint) {
		var box = el('div', { 'class': 'hs-a2p-f' }, [
			el('span', { 'class': 'hs-a2p-label', text: label })
		].concat(children, hint ? [el('p', { 'class': 'hs-a2p-hint', text: hint })] : []));
		if (name) { row.dom.fields[name] = box; }
		return box;
	}

	// A box that creates a Thematic Tag straight away, as the Post screen does.
	function addBox(taxonomy, placeholder, onAdded) {
		var error = el('p', { 'class': 'hs-a2p-add-error' });
		error.style.display = 'none';
		function add() {
			var name = input.value.trim();
			if (!name) { return; }
			button.disabled = true;
			post('hs_a2p_add_term', { taxonomy: taxonomy, name: name }).then(function (res) {
				if (res.success) {
					onAdded(res.data);
					render();
					queueSave();
				} else {
					button.disabled = false;
					error.textContent = (res.data && res.data.message) || 'Could not add that.';
					error.style.display = '';
				}
			}).catch(function () {
				button.disabled = false;
				error.textContent = 'Could not add that: check your connection.';
				error.style.display = '';
			});
		}
		var input = el('input', { type: 'text', placeholder: placeholder, on: { keydown: function (e) { if (e.key === 'Enter') { e.preventDefault(); add(); } } } });
		var button = el('button', { type: 'button', 'class': 'button', text: 'Add', on: { click: add } });
		return el('div', {}, [el('div', { 'class': 'hs-a2p-add' }, [input, button]), error]);
	}

	function tagsField(row) {
		// Grouped by prefix, as in "SPORT: Football". A saved tag no longer in the list still shows.
		var names = panel.tags.concat(row.fields.tags.filter(function (t) { return panel.tags.indexOf(t) === -1; }));
		var groups = {};
		names.forEach(function (name) {
			var i = name.indexOf(':');
			var group = i > 0 ? name.slice(0, i).trim() : 'OTHER';
			(groups[group] = groups[group] || []).push(name);
		});
		var list = el('div', { 'class': 'hs-a2p-taglist' }, Object.keys(groups).sort().map(function (group) {
			return el('div', { 'class': 'hs-a2p-taggroup' }, [el('strong', { text: group })].concat(groups[group].map(function (name) {
				var i = name.indexOf(':');
				return el('label', {}, [
					el('input', { type: 'checkbox', value: name, checked: row.fields.tags.indexOf(name) !== -1, on: { change: function (e) {
						row.fields.tags = row.fields.tags.filter(function (t) { return t !== name; });
						if (e.target.checked) { row.fields.tags.push(name); }
						changed(row);
					} } }),
					document.createTextNode(' ' + (i > 0 ? name.slice(i + 1).trim() : name))
				]);
			})));
		}));
		var add = panel.canTag ? addBox('post_tag', 'New tag, e.g. SPORT: Sailing', function (term) {
			if (panel.tags.indexOf(term.name) === -1) { panel.tags.push(term.name); }
			if (row.fields.tags.indexOf(term.name) === -1) { row.fields.tags.push(term.name); }
		}) : null;
		var box = field(row, 'Thematic Tags *', 'tags', [list, add], 'Aim for 3–4 per Post.');
		box.classList.add('hs-a2p-wide');
		return box;
	}

	// Images: the shared picker (Posts--Card-Preview): Featured and Portrait slots plus one rack of the article's photos.
	var SLOT_URL = { image: 'imageUrl', portrait: 'portraitUrl' };
	var SLOT_LABEL = { image: 'Featured image', portrait: 'Portrait (slider)' };

	function imagesRow(row) {
		var f = row.fields;
		var wrap = el('div', { 'class': 'hs-a2p-g hs-a2p-g-wide hs-a2p-images' }, [el('h4', { text: 'Images' })]);
		function set(name, id, url) {
			if (name === 'image' && id !== f.image) { f.heroFocus = ''; } // a new photo starts centred
			f[name] = id; row[SLOT_URL[name]] = url; changed(row);
		}
		if (!window.hsCardPreview || !window.hsCardPreview.imagePicker) {
			// Posts--Card-Preview is off: plain Media Library buttons, so images can still be chosen.
			['image', 'portrait'].forEach(function (name) {
				var box = el('div', { 'class': 'hs-a2p-f' }, [
					el('span', { 'class': 'hs-a2p-label', text: SLOT_LABEL[name] + (name === 'image' ? ' *' : '') }),
					row[SLOT_URL[name]] ? el('img', { 'class': 'hs-cip-thumb', src: row[SLOT_URL[name]], alt: '', style: 'max-width:200px' }) : null,
					el('button', { type: 'button', 'class': 'button button-small', text: 'Media Library', on: { click: function () {
						var frame = wp.media({ title: SLOT_LABEL[name], multiple: false, library: { type: 'image' } });
						frame.on('select', function () { var a = frame.state().get('selection').first().toJSON(); var size = a.sizes && (a.sizes.medium || a.sizes.full); set(name, a.id, size ? size.url : a.url); render(); });
						frame.open();
					} } })
				]);
				if (name === 'image') { row.dom.fields.image = box; }
				wrap.appendChild(box);
			});
			return wrap;
		}
		var picker = window.hsCardPreview.imagePicker({
			images: row.images,
			state: row.pick || (row.pick = {}),
			get: function (name) { return { id: f[name] || 0, url: row[SLOT_URL[name]] || '' }; },
			set: set,
			slots: {
				image: { label: SLOT_LABEL.image, required: true, hint: function () { return f.status === 'live' ? 'Required for Live.' : 'Needed before it can go Live.'; } },
				portrait: { label: SLOT_LABEL.portrait, hint: function () { return 'Optional. Used by the slider.'; } }
			},
			source: 'Article images',
			empty: 'This article has no images of its own. Use Media Library.',
			classes: { slot: 'hs-a2p-f', label: 'hs-a2p-label', hint: 'hs-a2p-hint' },
			onDraw: function (slots) { if (row.dom) { row.dom.fields.image = slots.image; mark(row); } }
		});
		row.dom.redrawImages = picker.draw;
		wrap.appendChild(picker.node);
		return wrap;
	}

	function group(title, children, extra) {
		return el('div', { 'class': 'hs-a2p-g' + (extra ? ' ' + extra : '') }, [el('h4', { text: title })].concat(children));
	}

	function rowBody(row) {
		var f = row.fields;
		row.dom = { fields: {} };

		var sectionOptions = [el('option', { value: '0', text: 'Choose a Section…' })].concat(panel.sections.map(function (s) {
			return el('option', { value: String(s.id), text: s.name, selected: String(f.section) === String(s.id) });
		}));
		if (panel.canSection) {
			sectionOptions.push(el('option', { value: 'custom', text: 'Custom…', selected: f.section === 'custom' }));
		}
		var custom = el('input', { type: 'text', placeholder: 'New Section name', value: f.customSection || '', style: 'margin-top:6px;' + (f.section === 'custom' ? '' : 'display:none'), on: { input: function (e) { f.customSection = e.target.value; changed(row); } } });
		var section = el('select', { on: { change: function (e) {
			f.section = e.target.value === 'custom' ? 'custom' : parseInt(e.target.value, 10);
			custom.style.display = f.section === 'custom' ? '' : 'none';
			changed(row);
		} } }, sectionOptions);

		var campusBoxes = panel.campuses.map(function (c) {
			return el('label', { style: 'margin-right:12px' }, [
				el('input', { type: 'checkbox', checked: f.campus.indexOf(c.slug) !== -1, on: { change: function (e) {
					f.campus = f.campus.filter(function (s) { return s !== c.slug; });
					if (e.target.checked) { f.campus.push(c.slug); }
					changed(row);
				} } }),
				document.createTextNode(' ' + c.name)
			]);
		});
		row.dom.bothHint = el('p', { 'class': 'hs-a2p-hint', text: 'Both Campuses: one Post. Its sidebar opens on one campus, and readers can switch.' });

		var STATUS_TIPS = {
			live: 'Public as soon as you click Create Posts, or on the publish date if that is in the future.',
			draft: 'Saved but not public. Open it in Posts to finish it, then publish it yourself.'
		};
		var status = el('div', { 'class': 'hs-a2p-switch' }, ['live', 'draft'].map(function (value) {
			return el('label', { title: STATUS_TIPS[value] }, [
				el('input', { type: 'radio', name: 'hs-a2p-status-' + row.key, disabled: value === 'live' && !panel.canPublish, checked: f.status === value, on: { change: function () {
					f.status = value;
					changed(row);
					if (row.dom.redrawImages) { row.dom.redrawImages(); }
				} } }),
				document.createTextNode(value === 'live' ? ' Live' : ' Draft')
			]);
		}));
		if (!panel.canPublish) { f.status = 'draft'; }

		function text(name, placeholder) {
			return el('input', { type: 'text', value: f[name] || '', placeholder: placeholder || '', on: { input: function (e) { f[name] = e.target.value; changed(row); } } });
		}
		function dateInput(name, type) {
			return el('input', { type: type, value: f[name] || '', on: { input: function (e) { f[name] = e.target.value; changed(row); } } });
		}

		var picker = dateInput('date', 'datetime-local');
		var publishWhen = el('div', { 'class': 'hs-a2p-when' }, [
			el('label', { 'class': 'hs-a2p-now' }, [
				el('input', { type: 'checkbox', checked: !!f.publishNow, on: { change: function (e) {
					f.publishNow = e.target.checked;
					picker.style.display = f.publishNow ? 'none' : '';
					changed(row);
				} } }),
				document.createTextNode(' Publish now')
			]),
			picker
		]);
		picker.style.display = f.publishNow ? 'none' : '';

		var body = el('div', { 'class': 'hs-a2p-body' }, [
			row.dark ? el('p', { 'class': 'hs-a2p-warn', text: 'Styled for a dark background: check the text colours on the Post.' }) : null,
			group('Post', [
				field(row, 'Title *', 'title', [text('title')]),
				// Four fields that share row lines: labels, controls and hints each line up.
				el('div', { 'class': 'hs-a2p-quad' }, [
					field(row, 'Publish date *', 'date', [publishWhen], 'Untick Publish now to choose a date: a future date schedules it. The publish date sets the URL.'),
					field(row, 'Status', null, [status], 'Live: public once created. Draft: not public; finish and publish it from Posts (or Edit here).'),
					field(row, 'Event start', 'event', [dateInput('eventStart', 'date')], 'Shown to readers instead of the publish date. Leave empty to use the publish date.'),
					field(row, 'Event end', null, [dateInput('eventEnd', 'date')], 'Only for events over several days.')
				])
			], 'hs-a2p-g-wide'),
			group('Website Section', [
				field(row, 'Website Section *', 'section', [section, custom], 'Dictates where on the site the Post is shown (its feeds). Also the default yellow badge.'),
				field(row, 'Yellow badge text', null, [text('badgeText')], 'Optional. Leave empty to show the Website Section.')
			], 'hs-a2p-g-sub'),
			group('Campus', [
				field(row, 'Campus *', 'campus', [el('div', { 'class': 'hs-a2p-campuses' }, campusBoxes), row.dom.bothHint], 'Tick both for Both Campuses. Sets the campus in the URL.'),
				field(row, 'Location text', null, [text('location', 'e.g. Bangkok Trip')], 'Optional. Replaces the campus name in the chip and the URL, e.g. Bangkok Trip. Still tick a Campus.')
			], 'hs-a2p-g-sub'),
			imagesRow(row),
			tagsField(row)
		]);
		mark(row);
		return body;
	}

	/* ---- Labelled preview: the shared hsCardPreview (Posts--Card-Preview), fed from the row's fields ---- */
	function previewValues(row) {
		var f = row.fields;
		var section = f.section === 'custom' ? String(f.customSection || '').trim() : ((panel.sections.filter(function (s) { return String(s.id) === String(f.section); })[0] || {}).name || '');
		return {
			title: String(f.title || '').trim() || row.headline,
			section: section,
			badgeText: f.badgeText,
			campusNames: panel.campuses.filter(function (c) { return f.campus.indexOf(c.slug) !== -1; }).map(function (c) { return c.name; }),
			location: f.location,
			publishDate: f.publishNow ? panel.today : String(f.date || '').slice(0, 10),
			eventStart: f.eventStart,
			eventEnd: f.eventEnd,
			image: row.portraitUrl || row.imageUrl || '',
			tags: f.tags || []
		};
	}
	function previewNode(row) {
		if (!window.hsCardPreview) { return null; } // Posts--Card-Preview is off: the panel works without a preview.
		var pv = window.hsCardPreview.create({ labels: { badge: 'Shows the Yellow badge text if you fill it in, otherwise the Website Section.' } });
		row.dom.preview = pv;
		pv.update(previewValues(row));
		var hero = window.hsCardPreview.heroFocus ? window.hsCardPreview.heroFocus({
			get: function () { return { url: row.imageUrl || '', focus: row.fields.heroFocus || '', mobile: { id: row.fields.heroMobile || 0, url: row.heroMobileUrl || '', focus: row.fields.heroMobileFocus || '' } }; },
			set: function (focus) { row.fields.heroFocus = focus; queueSave(); },
			setMobileFocus: function (focus) { row.fields.heroMobileFocus = focus; queueSave(); },
			images: row.images,
			setMobile: function (id, url) { row.fields.heroMobile = id || 0; row.heroMobileUrl = url || ''; queueSave(); }
		}) : null;
		row.dom.hero = hero;
		return el('div', { 'class': 'hs-a2p-preview' }, [pv.node, hero ? hero.node : null]);
	}
	function updatePreview(row) {
		if (row.dom && row.dom.preview) { row.dom.preview.update(previewValues(row)); }
		if (row.dom && row.dom.hero) { row.dom.hero.draw(); }
	}
	function drawCallouts(row) {
		if (row.dom && row.dom.preview) { row.dom.preview.redraw(); }
	}

	function previewToggle(row, preview) {
		var button = el('button', { type: 'button', 'class': 'button-link hs-a2p-pv-toggle', text: row.showPreview ? 'Hide preview' : 'Show preview', on: { click: function () {
			row.showPreview = !row.showPreview;
			button.textContent = row.showPreview ? 'Hide preview' : 'Show preview';
			preview.classList.toggle('is-open', row.showPreview);
			drawCallouts(row);
		} } });
		preview.classList.toggle('is-open', !!row.showPreview);
		return button;
	}

	function rowNode(row) {
		var head;
		if (!row.found) {
			head = [el('strong', { text: row.headline }), el('span', { 'class': 'hs-a2p-state is-missing', text: 'Article not found' })];
		} else if (row.post) {
			head = [el('strong', { text: row.headline, style: 'flex:1 1 320px' })];
		} else {
			head = [
				el('label', {}, [
					el('input', { type: 'checkbox', checked: row.fields.tick, on: { change: function (e) { row.fields.tick = e.target.checked; render(); queueSave(); } } }),
					el('span', { text: row.headline })
				]),
				el('span', { 'class': 'hs-a2p-sec', text: row.section || 'No section heading' })
			];
		}
		if (row.post) {
			head.push(el('span', { 'class': 'hs-a2p-state' + (row.post.live ? ' is-live' : ''), text: row.post.label }));
			if (row.post.view) { head.push(el('a', { href: row.post.view, target: '_blank', rel: 'noopener', text: 'View' })); }
			if (row.post.edit) { head.push(el('a', { href: row.post.edit, target: '_blank', rel: 'noopener', text: 'Edit' })); }
		}
		var open = row.found && !row.post && row.fields.tick;
		var body = open ? rowBody(row) : null;
		var preview = open ? previewNode(row) : null;
		var node = el('div', { 'class': 'hs-a2p-row' }, [
			el('div', { 'class': 'hs-a2p-head' }, head),
			open ? el('div', { 'class': 'hs-a2p-rowgrid' }, [body, preview ? previewToggle(row, preview) : null, preview]) : null
		]);
		if (open) { setTimeout(function () { drawCallouts(row); }, 0); }
		return node;
	}

	var createButton = null;
	var summary = null;

	function counts() {
		var c = { live: 0, draft: 0, gaps: [] };
		editableRows().filter(function (r) { return r.fields.tick; }).forEach(function (r) {
			var m = missing(r);
			if (m.length) { c.gaps.push(r.headline + ' (' + m.map(function (x) { return NAMES[x]; }).join(', ') + ')'); }
			else if (r.fields.status === 'live') { c.live++; }
			else { c.draft++; }
		});
		return c;
	}

	function updateActions() {
		if (!createButton) { return; }
		var c = counts();
		createButton.disabled = !(c.live + c.draft);
		summary.textContent = c.live + ' to go live, ' + c.draft + ' as drafts' + (c.gaps.length ? ', ' + c.gaps.length + ' missing required fields' : '');
	}

	function create() {
		var c = counts();
		var text = c.live + ' Post(s) will go live now and ' + c.draft + ' will be draft(s).';
		if (c.gaps.length) { text += '\n\nSkipped, missing required fields:\n' + c.gaps.join('\n'); }
		if (!window.confirm(text + '\n\nCreate them?')) { return; }
		createButton.disabled = true;
		createButton.textContent = 'Creating…';
		clearTimeout(saveTimer);
		post('hs_a2p_create', { rows: rowsPayload() }).then(function (res) {
			if (!res.success) {
				message = { error: true, text: (res.data && res.data.message) || 'Could not create the Posts.' };
			} else {
				panel = res.data.panel;
				var r = res.data.result;
				var items = r.created.map(function (x) { return x.title + ': ' + (x.held ? 'kept as a draft by the Posts checks' : x.status === 'publish' ? 'live' : x.status === 'future' ? 'scheduled' : 'draft'); })
					.concat(r.skipped.map(function (x) { return x.title + ': not created. ' + x.reason; }));
				message = { error: r.skipped.length > 0, text: r.created.length + ' created, ' + r.skipped.length + ' not created.', items: items };
			}
			render();
		}).catch(function () {
			message = { error: true, text: 'The request failed. Reload the page before trying again, to check what was created.' };
			render();
		});
	}

	function render() {
		root.textContent = '';
		if (!panel.divi5) {
			root.appendChild(el('p', { text: 'This tool covers Divi 5 weekly updates. This update is older plain HTML, so it can’t be used here.' }));
			return;
		}
		if (!panel.rows.length) {
			root.appendChild(el('p', { text: 'No articles with a headline were found in this weekly update.' }));
			return;
		}
		root.appendChild(el('p', { 'class': 'hs-a2p-intro', text: 'Tick the articles that should also be Posts. Fields marked * are required only for ticked rows; nothing here affects saving the weekly update.' + (panel.skipped ? ' ' + panel.skipped + ' article(s) with no headline are not listed.' : '') }));
		panel.rows.forEach(function (row) { root.appendChild(rowNode(row)); });
		createButton = el('button', { type: 'button', 'class': 'button button-primary', text: 'Create Posts', on: { click: create } });
		summary = el('span', { 'class': 'hs-a2p-saved' });
		saved = el('span', { 'class': 'hs-a2p-saved' });
		root.appendChild(el('div', { 'class': 'hs-a2p-actions' }, [createButton, summary, saved]));
		if (message) {
			root.appendChild(el('div', { 'class': 'hs-a2p-message' + (message.error ? ' is-error' : '') }, [
				el('strong', { text: message.text }),
				message.items && message.items.length ? el('ul', {}, message.items.map(function (i) { return el('li', { text: i }); })) : null
			]));
		}
		updateActions();
	}

	render();
})();
</script>
	<?php
}
