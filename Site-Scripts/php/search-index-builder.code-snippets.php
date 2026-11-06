<?php

/**
 * Search-Index-Builder
 */
/**
 * Site search: builds wp-content/uploads/hs-search/index.json from WordPress data.
 *
 * Sources: pages, staff (people + class tokens), weekly updates (issues and the
 * .hs-weekly-article articles inside them), posts, events (only once a public calendar
 * page exists), documents (links found in content, curated on the Documents screen) and
 * published Quick answers (Search-Topics snippet).
 *
 * Rebuilds 30 s after a relevant edit (one WP-Cron event, locked), daily, and on
 * "Rebuild now" (Site search → Index status). The file is written atomically.
 * Admin screens: Search-Admin (Index status, Documents) and Search-Terms snippets.
 * No rewrite rules: no permalink flush needed.
 */

if ( ! function_exists( 'hs_search_menu_slug' ) ) {
	function hs_search_menu_slug() { return 'hs-site-search'; }
}

/* ---------------------------------------------------------------------------
 * Settings, paths, small helpers
 * ------------------------------------------------------------------------ */
function hs_search_settings() {
	$d = array(
		'src_page' => 1, 'src_person' => 1, 'src_issue' => 1, 'src_post' => 1, 'src_event' => 1, 'src_doc' => 1,
		'events_url'       => '',
		'doc_age_months'   => 0,
		'doc_exclude'      => '',
		'article_months'   => 0,
	);
	$s = get_option( 'hs_search_settings', array() );
	return array_merge( $d, is_array( $s ) ? $s : array() );
}

function hs_search_index_dir() { return trailingslashit( wp_upload_dir()['basedir'] ) . 'hs-search'; }
function hs_search_index_file() { return hs_search_index_dir() . '/index.json'; }

function hs_search_rel_url( $url ) {
	$home = untrailingslashit( home_url() );
	return ( 0 === strpos( $url, $home ) ) ? substr( $url, strlen( $home ) ) : $url;
}

function hs_search_clean_text( $s ) {
	$s = wp_strip_all_tags( strip_shortcodes( (string) $s ) );
	$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

function hs_search_cut( $s, $n ) {
	$s = hs_search_clean_text( $s );
	if ( mb_strlen( $s ) <= $n ) {
		return $s;
	}
	$cut = mb_substr( $s, 0, $n );
	$sp  = mb_strrpos( $cut, ' ' );
	return rtrim( $sp > $n * 0.6 ? mb_substr( $cut, 0, $sp ) : $cut, " ,.;:" ) . '…';
}

/** Campus slugs from any of the site's taxonomies/meta → ['chaofah'] / ['cherngtalay'] / [] (both). */
function hs_search_norm_campus( $values ) {
	$out = array();
	foreach ( (array) $values as $v ) {
		$v = strtolower( (string) $v );
		if ( false !== strpos( $v, 'both' ) ) {
			return array();
		}
		if ( false !== strpos( $v, 'chaofah' ) || false !== strpos( $v, 'city' ) ) {
			$out['chaofah'] = 1;
		} elseif ( false !== strpos( $v, 'cherngtalay' ) || false !== strpos( $v, 'north' ) ) {
			$out['cherngtalay'] = 1;
		}
	}
	$out = array_keys( $out );
	return 2 === count( $out ) ? array() : $out;
}

function hs_search_term_slugs( $post_id, $tax ) {
	$t = taxonomy_exists( $tax ) ? wp_get_post_terms( $post_id, $tax, array( 'fields' => 'slugs' ) ) : array();
	return is_wp_error( $t ) ? array() : $t;
}
function hs_search_term_names( $post_id, $tax ) {
	$t = taxonomy_exists( $tax ) ? wp_get_post_terms( $post_id, $tax, array( 'fields' => 'names' ) ) : array();
	return is_wp_error( $t ) ? array() : array_map( function ( $n ) { return html_entity_decode( $n, ENT_QUOTES, 'UTF-8' ); }, $t );
}

function hs_search_excluded( $post_id ) {
	return (bool) get_post_meta( $post_id, 'hs_search_exclude', true );
}

/* ---------------------------------------------------------------------------
 * Reading Divi 5 content
 * ------------------------------------------------------------------------ */

/** Blocks of a post with Divi global layouts expanded in place. */
function hs_search_blocks( $content, $depth = 0 ) {
	static $layout_cache = array();
	$blocks = parse_blocks( (string) $content );
	$out    = array();
	foreach ( $blocks as $b ) {
		if ( 'divi/global-layout' === $b['blockName'] && ! empty( $b['attrs']['globalModule'] ) && $depth < 3 ) {
			$id = (int) $b['attrs']['globalModule'];
			if ( ! isset( $layout_cache[ $id ] ) ) {
				$lp                   = get_post( $id );
				$layout_cache[ $id ]  = $lp ? hs_search_blocks( $lp->post_content, $depth + 1 ) : array();
			}
			foreach ( $layout_cache[ $id ] as $lb ) {
				$out[] = $lb;
			}
			continue;
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			$b['innerBlocks'] = hs_search_expand_inner( $b['innerBlocks'], $depth );
		}
		$out[] = $b;
	}
	return $out;
}
function hs_search_expand_inner( $inner, $depth ) {
	$out = array();
	foreach ( $inner as $b ) {
		if ( 'divi/global-layout' === $b['blockName'] && ! empty( $b['attrs']['globalModule'] ) && $depth < 3 ) {
			$lp = get_post( (int) $b['attrs']['globalModule'] );
			foreach ( ( $lp ? hs_search_blocks( $lp->post_content, $depth + 1 ) : array() ) as $lb ) {
				$out[] = $lb;
			}
			continue;
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			$b['innerBlocks'] = hs_search_expand_inner( $b['innerBlocks'], $depth );
		}
		$out[] = $b;
	}
	return $out;
}

/** HTML-ish strings found in a block's attributes, in order. */
function hs_search_attr_strings( $attrs, &$out ) {
	foreach ( (array) $attrs as $k => $v ) {
		if ( is_array( $v ) ) {
			hs_search_attr_strings( $v, $out );
		} elseif ( is_string( $v ) && ( false !== strpos( $v, '<' ) || 'linkUrl' === $k || 'url' === $k || 'text' === $k ) ) {
			if ( 'linkUrl' === $k || 'url' === $k ) {
				$out[] = '<a href="' . esc_attr( $v ) . '" data-hs-attr-link="1"></a>';
			} elseif ( 'text' === $k && false === strpos( $v, '<' ) ) {
				$out[] = '<span data-hs-attr-text="1">' . esc_html( $v ) . '</span>';
			} else {
				$out[] = $v;
			}
		}
	}
}

/** One HTML string for a list of blocks, in document order. Divi section admin labels become markers. */
function hs_search_blocks_html( $blocks ) {
	$html = '';
	foreach ( $blocks as $b ) {
		if ( 'divi/section' === $b['blockName'] ) {
			$label = $b['attrs']['module']['meta']['adminLabel']['desktop']['value'] ?? '';
			if ( $label ) {
				$html .= '<hs-section data-label="' . esc_attr( $label ) . '"></hs-section>';
			}
		}
		if ( 'divi/button' === $b['blockName'] ) {
			$url  = $b['attrs']['button']['innerContent']['desktop']['value']['linkUrl'] ?? '';
			$text = $b['attrs']['button']['innerContent']['desktop']['value']['text'] ?? '';
			if ( $url ) {
				$html .= '<a href="' . esc_attr( $url ) . '">' . esc_html( $text ) . '</a>';
			}
		} else {
			$parts = array();
			hs_search_attr_strings( $b['attrs'] ?? array(), $parts );
			$html .= implode( "\n", $parts );
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			$html .= hs_search_blocks_html( $b['innerBlocks'] );
		} elseif ( ! empty( $b['innerHTML'] ) && 0 !== strpos( (string) $b['blockName'], 'divi/' ) ) {
			$html .= $b['innerHTML'];
		}
	}
	return $html;
}

function hs_search_dom( $html ) {
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="UTF-8"><div id="hs-root">' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR );
	libxml_clear_errors();
	return $dom;
}

/** Text with a space between block elements ("Heading" + "Paragraph" never run together). */
function hs_search_block_text( $node ) {
	$block = array( 'p', 'div', 'li', 'ul', 'ol', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'section', 'article', 'table', 'tr', 'td', 'th', 'br', 'span' );
	$out   = '';
	foreach ( $node->childNodes as $c ) {
		if ( XML_TEXT_NODE === $c->nodeType ) {
			$out .= $c->nodeValue;
		} elseif ( XML_ELEMENT_NODE === $c->nodeType ) {
			$sep  = in_array( strtolower( $c->nodeName ), $block, true ) ? ' ' : '';
			$out .= $sep . hs_search_block_text( $c ) . $sep;
		}
	}
	return trim( preg_replace( '/\s+/u', ' ', $out ) );
}

function hs_search_node_text( $node ) {
	return $node ? trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ) : '';
}

/** Does the group block carry a given CSS class (Divi 5 attributes)? */
function hs_search_block_has_class( $b, $class ) {
	$attrs = $b['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? array();
	foreach ( (array) $attrs as $a ) {
		if ( isset( $a['name'], $a['value'] ) && 'class' === $a['name'] && preg_match( '/(^|\s)' . preg_quote( $class, '/' ) . '(\s|$)/', $a['value'] ) ) {
			return true;
		}
	}
	return false;
}

/* ---------------------------------------------------------------------------
 * Sources
 * ------------------------------------------------------------------------ */

function hs_search_page_campus( $post ) {
	$path = trim( (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH ), '/' );
	$seg  = explode( '/', $path );
	return hs_search_norm_campus( array( $seg[0] ?? '' ) );
}

function hs_search_skip_page( $post ) {
	if ( post_password_required( $post ) || '' !== $post->post_password ) {
		return true;
	}
	if ( false !== stripos( $post->post_title, 'DO NOT DELETE' ) || in_array( $post->post_name, array( 'homepage-router', 'chaofah', 'cherngtalay' ), true ) ) {
		return true;
	}
	if ( preg_match( '/thank-?you|thanks/', $post->post_name ) ) {
		return true;
	}
	if ( '1' === (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) {
		return true;
	}
	return false;
}

/** Collected documents for this build: url => record. */
function hs_search_doc_collect( $html, $ctx, &$docs ) {
	if ( false === stripos( $html, 'href' ) ) {
		return;
	}
	$dom   = hs_search_dom( $html );
	$xpath = new DOMXPath( $dom );

	// Tab navigation: panel id → tab label.
	$tabs = array();
	foreach ( $xpath->query( '//*[@href or @data-tab or @data-target or @aria-controls]' ) as $n ) {
		foreach ( array( 'href', 'data-tab', 'data-target', 'aria-controls' ) as $at ) {
			$v = ltrim( (string) $n->getAttribute( $at ), '#' );
			if ( $v && preg_match( '/^[A-Za-z][\w-]*$/', $v ) && ! isset( $tabs[ $v ] ) ) {
				$t = hs_search_node_text( $n );
				if ( $t && mb_strlen( $t ) < 60 ) {
					$tabs[ $v ] = $t;
				}
			}
		}
	}

	foreach ( $xpath->query( '//a[@href]' ) as $a ) {
		$href = trim( $a->getAttribute( 'href' ) );
		$ft   = hs_search_doc_type( $href );
		if ( ! $ft ) {
			continue;
		}
		$url = hs_search_doc_norm_url( $href );
		list( $title, $rule ) = hs_search_doc_title( $a, $xpath, $href, $ft );

		// Section: enclosing tab panel label, else nearest preceding heading, else Divi section label.
		$sec = '';
		for ( $p = $a->parentNode; $p && $p->nodeType === XML_ELEMENT_NODE; $p = $p->parentNode ) {
			$id = $p->getAttribute( 'id' );
			if ( $id && isset( $tabs[ $id ] ) ) {
				$sec = $tabs[ $id ];
				break;
			}
		}
		$heading = hs_search_doc_prev_heading( $a, $xpath );
		if ( ! $sec ) {
			$sec = $heading;
		}
		if ( ! $sec ) {
			$m   = $xpath->query( 'preceding::hs-section[1]', $a );
			$sec = $m->length ? $m->item( 0 )->getAttribute( 'data-label' ) : '';
		}
		if ( in_array( $rule, array( 4, 5, 6 ), true ) && 5 === $rule && $heading && $sec && $heading !== $sec ) {
			$title = $sec . ': ' . $title;
		}

		$markup = $dom->saveHTML( $a->parentNode ?: $a );
		// Context for the tooltip: the text of the paragraph / list item / card around the link (without the title)
		// Only real prose counts: link texts are removed first, so a grid of download cards gives no context.
		$cx = '';
		for ( $p = $a->parentNode, $i = 0; $p && $p->nodeType === XML_ELEMENT_NODE && $i < 4; $p = $p->parentNode, $i++ ) {
			$clone = $p->cloneNode( true );
			foreach ( iterator_to_array( $clone->getElementsByTagName( 'a' ) ) as $link ) {
				$link->parentNode->removeChild( $link );
			}
			$t = trim( preg_replace( '/\b(View|Open|Download) (Document|PDF|File)\b/i', '', hs_search_block_text( $clone ) ) );
			if ( 'hs-root' === $p->getAttribute( 'id' ) || mb_strlen( $t ) > 700 ) {
				break; // too broad (a whole section): use the text just before the link instead
			}
			if ( mb_strlen( $t ) >= 40 ) {
				$cx = $t;
				break;
			}
		}
		if ( '' === $cx ) {
			$before = '';
			$prev   = $xpath->query( 'preceding::text()[normalize-space()][position() <= 40]', $a );
			for ( $k = $prev->length - 1; $k >= 0 && mb_strlen( $before ) < 260; $k-- ) {
				$before = trim( $prev->item( $k )->nodeValue ) . ' ' . $before;
			}
			$before = trim( preg_replace( '/\s+/u', ' ', $before ) );
			$cx     = mb_strlen( $before ) > 220 ? '…' . ltrim( mb_substr( $before, -220 ) ) : $before;
		}
		$cx = hs_search_cut( $cx, 220 );
		if ( isset( $docs[ $url ] ) ) {
			$docs[ $url ]['n']++;
			$docs[ $url ]['srcs'][ $ctx['src'] ] = 1;
			if ( 'page' === $ctx['src'] && 'page' !== $docs[ $url ]['on']['src'] ) {
				$docs[ $url ]['on'] = array( 'ti' => $ctx['ti'], 'sec' => hs_search_cut( $sec, 60 ), 'u' => $ctx['u'], 'src' => $ctx['src'], 'd' => '' );
			} elseif ( 'issue' === $ctx['src'] && 'issue' === $docs[ $url ]['on']['src'] && ( $ctx['d'] ?? '' ) > ( $docs[ $url ]['on']['d'] ?? '' ) ) {
				$docs[ $url ]['on'] = array( 'ti' => $ctx['ti'], 'sec' => hs_search_cut( $sec, 60 ), 'u' => $ctx['u'], 'src' => $ctx['src'], 'd' => $ctx['d'] ); // newest issue it appears in
			}
			$docs[ $url ]['last'] = max( $docs[ $url ]['last'], $ctx['date'] );
			continue;
		}
		$docs[ $url ] = array(
			'url'    => $href,
			'ti'     => $title,
			'rule'   => $rule,
			'ft'     => $ft,
			'on'     => array( 'ti' => $ctx['ti'], 'sec' => hs_search_cut( $sec, 60 ), 'u' => $ctx['u'], 'src' => $ctx['src'], 'd' => $ctx['d'] ?? '' ),
			'c'      => $ctx['c'],
			'n'      => 1,
			'srcs'   => array( $ctx['src'] => 1 ),
			'last'   => $ctx['date'],
			'markup' => mb_substr( preg_replace( '/\s+/u', ' ', strip_tags( $markup, '<a><span><strong><h2><h3><h4><p>' ) ), 0, 400 ),
			'cx'     => $cx,
		);
	}
}

function hs_search_doc_type( $href ) {
	if ( preg_match( '#docs\.google\.com/(document|spreadsheets|presentation|forms)/#i', $href, $m ) ) {
		return array( 'document' => 'gdoc', 'spreadsheets' => 'gsheet', 'presentation' => 'gslides', 'forms' => 'gform' )[ strtolower( $m[1] ) ];
	}
	if ( preg_match( '#drive\.google\.com/(drive/folders|file|open)#i', $href, $m ) ) {
		return 0 === stripos( $m[1], 'drive/folders' ) ? 'folder' : 'file';
	}
	$path = (string) wp_parse_url( $href, PHP_URL_PATH );
	if ( preg_match( '/\.(pdf|docx?|xlsx?|pptx?|zip|csv)$/i', $path, $m ) ) {
		$e = strtolower( $m[1] );
		return array( 'pdf' => 'pdf', 'doc' => 'word', 'docx' => 'word', 'xls' => 'excel', 'xlsx' => 'excel', 'ppt' => 'ppt', 'pptx' => 'ppt', 'zip' => 'zip', 'csv' => 'excel' )[ $e ];
	}
	return '';
}

function hs_search_doc_norm_url( $href ) {
	$u = preg_replace( '#^https?://#i', '', trim( $href ) );
	$u = preg_replace( '#[?&](usp|ouid|rtpof|sd)=[^&]*#i', '', $u );
	$u = preg_replace( '#/(edit|view|preview)/?$#i', '', rtrim( $u, '?&/' ) );
	return strtolower( $u );
}

function hs_search_doc_generic( $t ) {
	$t = strtolower( trim( $t, " \t\n\r\0\x0B→›»:." ) );
	return mb_strlen( $t ) < 4 || in_array( $t, array( 'click here', 'download', 'view', 'view document', 'here', 'link', 'read more', 'open', 'open document', 'view pdf', 'download pdf', 'more info', 'click' ), true );
}

function hs_search_doc_label( $ft ) {
	return array( 'gdoc' => 'Google Doc', 'gsheet' => 'Google Sheet', 'gslides' => 'Google Slides', 'gform' => 'Google Form', 'folder' => 'Drive folder', 'file' => 'Drive file', 'pdf' => 'PDF', 'word' => 'Word', 'excel' => 'Spreadsheet', 'ppt' => 'Slides', 'zip' => 'Zip' )[ $ft ] ?? 'Document';
}

/** [title, rule number] following plan section 2b. */
function hs_search_doc_title( $a, $xpath, $href, $ft ) {
	$t = $xpath->query( './/*[contains(concat(" ",normalize-space(@class)," ")," hs-download-title ")]', $a );
	if ( $t->length && ! hs_search_doc_generic( hs_search_node_text( $t->item( 0 ) ) ) ) {
		return array( hs_search_node_text( $t->item( 0 ) ), 1 );
	}
	foreach ( array( 'title', 'aria-label' ) as $at ) {
		$v = trim( $a->getAttribute( $at ) );
		if ( $v && ! hs_search_doc_generic( $v ) ) {
			return array( $v, 2 );
		}
	}
	$img = $xpath->query( './/img[@alt]', $a );
	if ( $img->length && ! hs_search_doc_generic( $img->item( 0 )->getAttribute( 'alt' ) ) ) {
		return array( trim( $img->item( 0 )->getAttribute( 'alt' ) ), 2 );
	}
	$own = hs_search_node_text( $a );
	if ( $own && ! hs_search_doc_generic( $own ) && mb_strlen( $own ) < 140 && ! preg_match( '#^https?://#', $own ) ) {
		return array( $own, 3 );
	}
	$label = ' (' . hs_search_doc_label( $ft ) . ')';
	for ( $p = $a->parentNode, $i = 0; $p && $p->nodeType === XML_ELEMENT_NODE && $i < 4; $p = $p->parentNode, $i++ ) {
		$h = $xpath->query( './/h2|.//h3|.//h4|.//h5|.//h6|.//strong', $p );
		if ( $h->length ) {
			$ht = hs_search_node_text( $h->item( 0 ) );
			if ( $ht && ! hs_search_doc_generic( $ht ) && mb_strlen( $ht ) < 120 ) {
				return array( $ht . $label, 4 );
			}
		}
	}
	$prev = hs_search_doc_prev_heading( $a, $xpath );
	if ( $prev ) {
		return array( $prev . $label, 5 );
	}
	$path = (string) wp_parse_url( $href, PHP_URL_PATH );
	$name = preg_replace( '/\.[a-z0-9]+$/i', '', basename( $path ) );
	$name = trim( preg_replace( '/[-_]+/', ' ', preg_replace( '/[-_ ]?(v\d+|\d{4}[-_]?\d{2}([-_]?\d{2})?|final|copy)$/i', '', $name ) ) );
	if ( ! $name || preg_match( '/^[\w-]{20,}$/', $name ) ) {
		$name = hs_search_doc_label( $ft );
	}
	return array( ucfirst( $name ) . $label, 6 );
}

function hs_search_doc_prev_heading( $a, $xpath ) {
	$h = $xpath->query( '(preceding::h2|preceding::h3|preceding::h4|preceding::h5|preceding::h6)[last()]', $a );
	$t = $h->length ? hs_search_node_text( $h->item( 0 ) ) : '';
	return ( $t && mb_strlen( $t ) < 120 ) ? $t : '';
}

/** Class tokens from display group + job title (plan: class teacher tokens). */
function hs_search_class_tokens( $groups, $title ) {
	$t   = array();
	$src = strtolower( implode( ' | ', (array) $groups ) . ' | ' . $title );
	if ( preg_match_all( '/\b(?:year|yr|y)\s?(\d{1,2})\b(?:\s*\((blue|white|yellow|red|green)\))?/i', $src, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) {
			$t[] = 'year ' . $x[1];
			$t[] = $x[1];
			if ( ! empty( $x[2] ) ) {
				$t[] = $x[2];
				$t[] = $x[1] . substr( $x[2], 0, 1 );
			}
		}
	}
	foreach ( array( 'pre-nursery', 'nursery', 'preschool', 'reception', 'foundation', 'kindergarten', 'early years' ) as $w ) {
		if ( false !== strpos( $src, $w ) ) {
			$t[] = $w;
			if ( preg_match( '/' . preg_quote( $w, '/' ) . '\s*\((blue|white|yellow|red|green)\)/', $src, $c ) ) {
				$t[] = $c[1];
			}
		}
	}
	if ( false !== strpos( $src, 'homeroom' ) || false !== strpos( $src, 'class teacher' ) ) {
		$t[] = 'class teacher';
		$t[] = 'homeroom';
	}
	return array_values( array_unique( $t ) );
}

function hs_search_find_page_with( $needle ) {
	global $wpdb;
	$like = '%' . $wpdb->esc_like( $needle ) . '%';
	$id   = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish' AND post_content LIKE %s ORDER BY ID LIMIT 1", $like ) );
	return $id ? hs_search_rel_url( get_permalink( $id ) ) : '';
}

/* ---------------------------------------------------------------------------
 * Build
 * ------------------------------------------------------------------------ */
function hs_search_build_index() {
	$t0       = microtime( true );
	$settings = hs_search_settings();
	$items    = array();
	$docs     = array();
	$counts   = array();
	$errors   = array();
	$add      = function ( $item ) use ( &$items, &$counts ) {
		$items[]               = $item;
		$counts[ $item['t'] ] = ( $counts[ $item['t'] ] ?? 0 ) + 1;
	};
	$langs = array( 'en' => 1, 'th' => 1, 'ru' => 1, 'zh' => 1 );

	/* Pages */
	foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $p ) {
		try {
			if ( hs_search_skip_page( $p ) || hs_search_excluded( $p->ID ) ) {
				continue;
			}
			$html = hs_search_blocks_html( hs_search_blocks( $p->post_content ) );
			$u    = hs_search_rel_url( get_permalink( $p ) );
			$c    = hs_search_page_campus( $p );
			$seg  = explode( '/', trim( $u, '/' ) );
			$lg   = function_exists( 'get_field' ) ? ( get_field( 'page_language', $p->ID ) ?: '' ) : '';
			if ( ! $lg ) {
				$lg = isset( $langs[ $seg[0] ?? '' ] ) ? $seg[0] : 'en';
			}
			if ( $settings['src_page'] ) {
				$dom   = hs_search_dom( $html );
				$xpath = new DOMXPath( $dom );
				$heads = array();
				foreach ( $xpath->query( '//h1|//h2|//h3' ) as $h ) {
					$heads[] = hs_search_node_text( $h );
				}
				$desc = get_post_meta( $p->ID, '_yoast_wpseo_metadesc', true );
				$parent = $p->post_parent ? get_post( $p->post_parent ) : null;
				$add( array_filter( array(
					'id' => 'p' . $p->ID, 't' => 'page', 'ti' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
					's'  => hs_search_cut( $desc ?: hs_search_node_text( $dom->documentElement ), 260 ),
					'x'  => hs_search_cut( implode( ' · ', array_unique( array_filter( $heads ) ) ), 600 ),
					'bc' => ( $parent && false === stripos( $parent->post_title, 'DO NOT DELETE' ) && ! isset( $langs[ $parent->post_name ] ) ) ? html_entity_decode( $parent->post_title, ENT_QUOTES, 'UTF-8' ) : '',
					'c'  => $c, 'lg' => $lg, 'u' => $u,
				), 'hs_search_keep' ) );
			}
			if ( $settings['src_doc'] ) {
				hs_search_doc_collect( $html, array( 'src' => 'page', 'ti' => get_the_title( $p ), 'u' => $u, 'c' => $c, 'date' => get_post_modified_time( 'Y-m-d', false, $p ) ), $docs );
			}
		} catch ( Throwable $e ) {
			$errors[] = 'page ' . $p->ID . ': ' . $e->getMessage();
		}
	}

	/* People */
	if ( $settings['src_person'] ) {
		$dir = hs_search_find_page_with( '[hs_staff_directory' ) ?: '/staff-directory/';
		foreach ( get_posts( array( 'post_type' => 'staff', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $p ) {
			try {
				$title  = (string) get_post_meta( $p->ID, 'job_title', true );
				$groups = hs_search_term_names( $p->ID, 'staff_display_group' );
				$class  = '';
				foreach ( $groups as $g ) {
					if ( preg_match( '/^(Year \d+|Reception|Preschool|Pre-?Nursery|Nursery|Early Years)/i', $g ) ) {
						$class = $g;
						break;
					}
				}
				$img = get_the_post_thumbnail_url( $p, 'thumbnail' );
				$add( array_filter( array(
					'id' => 's' . $p->ID, 't' => 'person', 'ti' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ), 's' => $title,
					'g'  => $class, 'cl' => hs_search_class_tokens( $groups, $title ),
					'k'  => array_values( array_unique( array_merge( hs_search_term_names( $p->ID, 'staff_department' ), array_diff( $groups, array( $class ) ) ) ) ),
					'c'  => hs_search_norm_campus( hs_search_term_slugs( $p->ID, 'staff_campus' ) ),
					'img' => $img ? hs_search_rel_url( $img ) : '', 'u' => $dir,
				), 'hs_search_keep' ) );
			} catch ( Throwable $e ) {
				$errors[] = 'staff ' . $p->ID . ': ' . $e->getMessage();
			}
		}
	}

	/* Weekly updates: issues + articles */
	$articles = array(); // normalised headline => index into $items
	$cut_date = $settings['article_months'] ? gmdate( 'Y-m-d', strtotime( '-' . (int) $settings['article_months'] . ' months' ) ) : '';
	if ( $settings['src_issue'] || $settings['src_doc'] ) {
		foreach ( get_posts( array( 'post_type' => 'weekly_update', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ) ) as $p ) {
			try {
				if ( hs_search_excluded( $p->ID ) ) {
					continue;
				}
				$u      = hs_search_rel_url( get_permalink( $p ) );
				$c      = hs_search_norm_campus( array_merge( hs_search_term_slugs( $p->ID, 'hs_campus' ), array( get_post_meta( $p->ID, 'hs_campus', true ) ) ) );
				$date   = get_the_date( 'Y-m-d', $p );
				$title  = html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' );
				$title  = trim( preg_replace( '/[,\s–-]+\d{1,2}(st|nd|rd|th)?\s+[A-Za-z]+\s+\d{4}\s*$/', '', $title ) ); // "Weekly Update #38, 26 June 2026" → "Weekly Update #38"
				$blocks = hs_search_blocks( $p->post_content );
				if ( $settings['src_issue'] ) {
					$issue_articles = hs_search_issue_articles( $blocks );
					$add( array_filter( array(
						'id' => 'w' . $p->ID, 't' => 'issue', 'ti' => $title, 'wk' => (int) get_post_meta( $p->ID, 'hs_week_number', true ),
						'd'  => $date, 'k' => hs_search_term_names( $p->ID, 'in_this_issue' ), 'c' => $c, 'u' => $u,
						// the headlines of the articles inside, so the issue itself is found by them ("international" → the issue with International Day)
						'x'  => hs_search_cut( implode( ' · ', wp_list_pluck( $issue_articles, 'ti' ) ), 900 ),
						'ar' => has_term( '', 'hs_weekly_archive_status', $p ) ? 1 : 0,
					), 'hs_search_keep' ) );
					foreach ( $issue_articles as $i => $art ) {
						$key = hs_search_headline_key( $art['ti'] );
						if ( isset( $articles[ $key ] ) ) {
							continue; // the same article in another issue (newest kept)
						}
						$old = $cut_date && $date < $cut_date;
						$add( array_filter( array(
							'id' => 'a' . $p->ID . '_' . $i, 't' => 'article', 'ti' => $art['ti'], 'sn' => $old ? '' : $art['sn'], 'sec' => $art['sec'],
							'p'  => $title, 'd' => $date, 'c' => $c, 'u' => $u . ( $art['id'] ? '#' . $art['id'] : '' ),
						), 'hs_search_keep' ) );
						$articles[ $key ] = count( $items ) - 1;
					}
				}
				if ( $settings['src_doc'] ) {
					hs_search_doc_collect( hs_search_blocks_html( $blocks ), array( 'src' => 'issue', 'ti' => $title, 'u' => $u, 'c' => $c, 'date' => $date, 'd' => $date ), $docs );
				}
			} catch ( Throwable $e ) {
				$errors[] = 'weekly_update ' . $p->ID . ': ' . $e->getMessage();
			}
		}
	}

	/* Posts (own pages; replace a matching issue article) */
	if ( $settings['src_post'] || $settings['src_doc'] ) {
		foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $p ) {
			try {
				if ( hs_search_excluded( $p->ID ) ) {
					continue;
				}
				$u      = hs_search_rel_url( get_permalink( $p ) );
				$c      = hs_search_norm_campus( hs_search_term_slugs( $p->ID, 'hs_post_campus' ) );
				$date   = get_the_date( 'Y-m-d', $p );
				$title  = html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' );
				$blocks = hs_search_blocks( $p->post_content );
				if ( $settings['src_post'] ) {
					$arts = hs_search_issue_articles( $blocks );
					$sn   = $p->post_excerpt ? hs_search_cut( $p->post_excerpt, 230 ) : ( $arts ? $arts[0]['sn'] : hs_search_cut( hs_search_blocks_html( $blocks ), 230 ) );
					$item = array(
						'id' => 'n' . $p->ID, 't' => 'article', 'ti' => $title, 'sn' => $sn,
						'k'  => hs_search_term_names( $p->ID, 'hs_department' ), 'd' => $date, 'c' => $c, 'u' => $u,
					);
					$key = hs_search_headline_key( $title );
					if ( isset( $articles[ $key ] ) ) {
						$prev            = $items[ $articles[ $key ] ];
						$item['p']       = $prev['p'] ?? '';
						$item['sec']     = $prev['sec'] ?? '';
						$items[ $articles[ $key ] ] = array_filter( $item, 'hs_search_keep' );
					} else {
						$add( array_filter( $item, 'hs_search_keep' ) );
						$articles[ $key ] = count( $items ) - 1;
					}
				}
				// Documents inside posts are not collected: posts are copies of weekly-update articles,
				// so the same links are already found in the issue (with its date).
			} catch ( Throwable $e ) {
				$errors[] = 'post ' . $p->ID . ': ' . $e->getMessage();
			}
		}
	}

	/* Events: only with a public calendar page */
	$events_url = $settings['events_url'] ?: hs_search_find_page_with( 'hs_events_calendar' );
	if ( $settings['src_event'] && $events_url ) {
		$from = gmdate( 'Ymd', strtotime( '-60 days' ) );
		foreach ( get_posts( array( 'post_type' => 'events', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $p ) {
			try {
				if ( hs_search_excluded( $p->ID ) ) {
					continue;
				}
				$start = (string) get_post_meta( $p->ID, 'event_start_date', true );
				$end   = (string) get_post_meta( $p->ID, 'event_end_date', true ) ?: $start;
				if ( ! $start || $end < $from ) {
					continue;
				}
				$iso = function ( $d ) { return preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $d, $m ) ? "$m[1]-$m[2]-$m[3]" : $d; };
				$add( array_filter( array(
					'id' => 'e' . $p->ID, 't' => 'event', 'ti' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
					's'  => hs_search_cut( get_post_meta( $p->ID, 'event_blurb', true ), 160 ),
					'd'  => $iso( $start ), 'de' => $iso( $end ),
					'tm' => trim( get_post_meta( $p->ID, 'event_start_time', true ) . ( get_post_meta( $p->ID, 'event_end_time', true ) ? ' – ' . get_post_meta( $p->ID, 'event_end_time', true ) : '' ) ),
					'loc' => (string) get_post_meta( $p->ID, 'event_location', true ),
					'k'  => array_filter( array( (string) get_post_meta( $p->ID, 'event_category', true ) ) ),
					'c'  => hs_search_norm_campus( array( get_post_meta( $p->ID, 'target_campus', true ) ) ),
					'u'  => $events_url,
				), 'hs_search_keep' ) );
			} catch ( Throwable $e ) {
				$errors[] = 'event ' . $p->ID . ': ' . $e->getMessage();
			}
		}
	}

	/* Documents (merged with the Documents manager) */
	if ( $settings['src_doc'] ) {
		foreach ( hs_search_docs_apply( $docs, $settings ) as $d ) {
			$add( $d );
		}
	}

	/* Quick answers */
	foreach ( get_posts( array( 'post_type' => 'hs_search_topic', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order', 'order' => 'ASC' ) ) as $p ) {
		$m    = function ( $k ) use ( $p ) { return trim( (string) get_post_meta( $p->ID, 'hs_topic_' . $k, true ) ); };
		$card = $m( 'card' ) ?: 'standard';
		$btns = array();
		foreach ( array( 1, 2 ) as $n ) {
			if ( $m( "btn{$n}_label" ) && $m( "btn{$n}_url" ) ) {
				$btns[] = array( $m( "btn{$n}_label" ), $m( "btn{$n}_url" ) );
			}
		}
		if ( 'standard' === $card && ! $btns ) {
			continue;
		}
		$syn  = array_values( array_unique( array_filter( array_map( function ( $s ) { return strtolower( trim( $s ) ); }, preg_split( '/[,\n]+/', $m( 'synonyms' ) ) ) ) ) );
		$item = array( 'id' => 'q' . $p->ID, 't' => 'topic', 'card' => $card, 'ti' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ), 'syn' => $syn, 'a' => $m( 'answer' ), 'note' => $m( 'note' ), 'b' => $btns, 'pin' => $m( 'pinned' ) ? 1 : 0 );
		if ( 'contact' === $card ) {
			$item['cc'] = array();
			foreach ( array( 'c1', 'c2' ) as $c ) {
				if ( $m( $c . '_name' ) ) {
					$item['cc'][] = array_filter( array( 'n' => $m( $c . '_name' ), 'ad' => $m( $c . '_address' ), 'ph' => array_values( array_filter( array( $m( $c . '_phone1' ), $m( $c . '_phone2' ) ) ) ), 'em' => $m( $c . '_email' ), 'em2' => $m( $c . '_email2' ), 'map' => $m( $c . '_map' ) ) );
				}
			}
			$item['hl'] = $m( 'hours_label' );
			$item['h']  = $m( 'hours' );
		}
		$add( array_filter( $item, 'hs_search_keep' ) );
	}

	// Campus codes are internal: always show the names (HSC → Chaofah, HSN → Cherngtalay)
	$items = hs_search_campus_names( $items );

	$index = array(
		'v'        => 1,
		'built'    => current_time( 'c' ),
		'campuses' => hs_search_vocab_campuses(),
		'rules'    => hs_search_vocab_rules(),
		'items'    => $items,
	);
	$json = wp_json_encode( $index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	$written = false;
	if ( wp_mkdir_p( hs_search_index_dir() ) ) {
		$tmp = hs_search_index_file() . '.tmp';
		if ( false !== file_put_contents( $tmp, $json ) ) {
			$written = rename( $tmp, hs_search_index_file() );
		}
		if ( ! file_exists( hs_search_index_dir() . '/index.php' ) ) {
			file_put_contents( hs_search_index_dir() . '/index.php', "<?php // Silence is golden.\n" );
		}
	}
	if ( ! $written ) {
		$errors[] = 'Could not write ' . hs_search_index_file();
	}

	update_option( 'hs_search_index_meta', array(
		'built'  => current_time( 'mysql' ),
		'counts' => $counts,
		'bytes'  => strlen( $json ),
		'gzip'   => function_exists( 'gzencode' ) ? strlen( gzencode( $json, 6 ) ) : 0,
		'ms'     => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
		'errors' => array_slice( $errors, 0, 20 ),
		'events_url' => $events_url,
	), false );

	return $index;
}

/** Replace the internal campus codes HSC / HSN with the campus names in every text field (never in URLs). */
function hs_search_campus_names( $v, $key = '' ) {
	if ( is_array( $v ) ) {
		foreach ( $v as $k => $x ) {
			$v[ $k ] = hs_search_campus_names( $x, is_string( $k ) ? $k : $key );
		}
		return $v;
	}
	if ( ! is_string( $v ) || in_array( $key, array( 'u', 'img', 'id' ), true ) ) {
		return $v;
	}
	return preg_replace( array( '/\bHSC\b/', '/\bHSN\b/' ), array( 'Chaofah', 'Cherngtalay' ), $v );
}

function hs_search_keep( $v ) {
	return ! ( null === $v || '' === $v || array() === $v );
}

function hs_search_headline_key( $s ) {
	return trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( remove_accents( html_entity_decode( $s, ENT_QUOTES, 'UTF-8' ) ) ) ) );
}

/** Articles inside an issue: groups with class hs-weekly-article. */
function hs_search_issue_articles( $blocks ) {
	$out = array();
	$sec = '';
	$walk = function ( $list, $section_label ) use ( &$walk, &$out, &$sec ) {
		foreach ( $list as $b ) {
			if ( 'divi/section' === $b['blockName'] ) {
				$section_label = $b['attrs']['module']['meta']['adminLabel']['desktop']['value'] ?? $section_label;
			}
			if ( 'divi/group' === $b['blockName'] && hs_search_block_has_class( $b, 'hs-weekly-article' ) ) {
				$dom   = hs_search_dom( hs_search_blocks_html( array( $b ) ) );
				$xpath = new DOMXPath( $dom );
				$h3    = $xpath->query( '//h3' );
				if ( ! $h3->length ) {
					continue;
				}
				$head = hs_search_node_text( $h3->item( 0 ) );
				$h3->item( 0 )->parentNode->removeChild( $h3->item( 0 ) );
				foreach ( $xpath->query( '//h2' ) as $h2 ) {
					$h2->parentNode->removeChild( $h2 );
				}
				$id = (string) ( $b['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] ?? '' );
				$out[] = array(
					'ti'  => $head,
					'sn'  => hs_search_cut( hs_search_node_text( $dom->documentElement ), 230 ),
					'sec' => $sec ?: $section_label,
					'id'  => $id,
				);
				continue;
			}
			// A section heading (h2.hs-section) in a text module sets the section for the articles after it.
			if ( 'divi/text' === $b['blockName'] ) {
				$v = $b['attrs']['content']['innerContent']['desktop']['value'] ?? '';
				if ( is_string( $v ) && preg_match( '#<h2[^>]*class="[^"]*hs-section[^"]*"[^>]*>(.*?)</h2>#is', $v, $m ) ) {
					$sec = hs_search_cut( $m[1], 60 );
				}
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				$walk( $b['innerBlocks'], $section_label );
			}
		}
	};
	$walk( $blocks, '' );
	if ( ! $out ) {
		$out = hs_search_html_articles( hs_search_blocks_html( $blocks ) );
	}
	return $out;
}

/**
 * Older (imported) issues and posts are plain HTML: <article class="hs-weekly-article"> with an
 * <h2 id="…"> headline, split over several wp:html blocks; sections are h2/h3.hs-section.
 */
function hs_search_html_articles( $html ) {
	if ( false === strpos( $html, 'hs-weekly-article' ) ) {
		return array();
	}
	$out   = array();
	$dom   = hs_search_dom( $html );
	$xpath = new DOMXPath( $dom );
	$cls   = function ( $c ) { return 'contains(concat(" ",normalize-space(@class)," ")," ' . $c . ' ")'; };
	foreach ( $xpath->query( '//*[' . $cls( 'hs-weekly-article' ) . ']' ) as $art ) {
		$h = $xpath->query( './/*[(self::h2 or self::h3 or self::h4) and not(' . $cls( 'hs-section' ) . ')]', $art );
		if ( ! $h->length ) {
			continue;
		}
		$head = $h->item( 0 );
		$ti   = hs_search_node_text( $head );
		$id   = $head->getAttribute( 'id' );
		$secn = $xpath->query( 'preceding::*[' . $cls( 'hs-section' ) . '][1]', $art );
		$clone = $art->cloneNode( true );
		foreach ( array( 'h2', 'h3', 'h4' ) as $tag ) {
			$first = $clone->getElementsByTagName( $tag )->item( 0 );
			if ( $first && hs_search_node_text( $first ) === $ti ) {
				$first->parentNode->removeChild( $first );
				break;
			}
		}
		$out[] = array(
			'ti'  => $ti,
			'sn'  => hs_search_cut( hs_search_node_text( $clone ), 230 ),
			'sec' => $secn->length ? hs_search_cut( hs_search_node_text( $secn->item( 0 ) ), 60 ) : '',
			'id'  => preg_match( '/^[A-Za-z][\w-]*$/', $id ) ? $id : '',
		);
	}
	return $out;
}

/* ---------------------------------------------------------------------------
 * Documents manager store (states, title overrides, rules)
 * ------------------------------------------------------------------------ */
function hs_search_docs_store() {
	$s = get_option( 'hs_search_docs', array() );
	return is_array( $s ) ? $s : array();
}

/** Merge found documents with the store; returns index items. Also refreshes the store. */
function hs_search_docs_apply( $docs, $settings ) {
	$store   = hs_search_docs_store();
	$today   = gmdate( 'Y-m-d' );
	$age_cut = $settings['doc_age_months'] ? gmdate( 'Y-m-d', strtotime( '-' . (int) $settings['doc_age_months'] . ' months' ) ) : '';
	$pats    = array_filter( array_map( 'trim', preg_split( '/\n+/', (string) $settings['doc_exclude'] ) ) );
	$items   = array();
	$audit   = array();
	foreach ( $docs as $key => $d ) {
		$h   = md5( $key );
		$rec = $store[ $h ] ?? array( 'state' => 'in', 'title' => '', 'first' => $today );
		$rec['last'] = $d['last'] ?: $today;
		$store[ $h ] = $rec;
		$only_updates = ! isset( $d['srcs']['page'] );
		// Legacy photo galleries: Drive folders shared in weekly updates/posts (the school no longer uses them).
		$legacy = ( 'folder' === $d['ft'] && $only_updates );
		$audit[ $h ] = array( 'url' => $d['url'], 'ti' => $d['ti'], 'rule' => $d['rule'], 'ft' => $d['ft'], 'on' => $d['on'], 'n' => $d['n'], 'markup' => $d['markup'], 'upd' => $only_updates ? 1 : 0, 'c' => $d['c'], 'legacy' => $legacy ? 1 : 0 );
		if ( 'out' === $rec['state'] ) {
			continue;
		}
		$pinned = 'pin' === $rec['state'];
		if ( $legacy && ! $pinned ) {
			continue;
		}
		if ( ! $pinned ) {
			$skip = false;
			foreach ( $pats as $pat ) {
				if ( false !== stripos( $d['url'], $pat ) ) {
					$skip = true;
					break;
				}
			}
			if ( $skip || ( $age_cut && $only_updates && $rec['last'] < $age_cut ) ) {
				continue;
			}
		}
		$items[] = array_filter( array(
			'id' => 'd' . substr( $h, 0, 10 ), 't' => 'doc', 'ti' => $rec['title'] ?: $d['ti'], 'ft' => $d['ft'], 'u' => $d['url'],
			'on' => array_filter( array( 'ti' => html_entity_decode( $d['on']['ti'], ENT_QUOTES, 'UTF-8' ), 'sec' => $d['on']['sec'], 'u' => $d['on']['u'], 'd' => $d['on']['d'] ?? '' ) ),
			'c'  => $d['c'], 'd' => $d['last'], 'pin' => $pinned ? 1 : 0, 'up' => $only_updates ? 1 : 0, 'cx' => $d['cx'] ?? '',
		), 'hs_search_keep' );
	}
	update_option( 'hs_search_docs', $store, false );
	update_option( 'hs_search_docs_audit', $audit, false );
	return $items;
}

/* ---------------------------------------------------------------------------
 * Triggers: debounced single WP-Cron build, daily rebuild, lock
 * ------------------------------------------------------------------------ */
function hs_search_schedule_build() {
	if ( ! wp_next_scheduled( 'hs_search_build_event' ) ) {
		wp_schedule_single_event( time() + 30, 'hs_search_build_event' );
	}
}

add_action( 'hs_search_build_event', 'hs_search_run_locked_build' );
add_action( 'hs_search_daily_event', 'hs_search_run_locked_build' );
function hs_search_run_locked_build() {
	if ( get_transient( 'hs_search_build_lock' ) ) {
		hs_search_schedule_build();
		return false;
	}
	set_transient( 'hs_search_build_lock', 1, 5 * MINUTE_IN_SECONDS );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore
	}
	try {
		hs_search_build_index();
	} finally {
		delete_transient( 'hs_search_build_lock' );
	}
	return true;
}

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'hs_search_daily_event' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'hs_search_daily_event' );
	}
} );

add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( in_array( $post->post_type, array( 'page', 'post', 'weekly_update', 'events', 'staff', 'hs_search_topic' ), true ) && ( 'publish' === $new || 'publish' === $old ) ) {
		hs_search_schedule_build();
	}
}, 10, 3 );
add_action( 'save_post', function ( $id, $post ) {
	if ( 'publish' === $post->post_status && in_array( $post->post_type, array( 'page', 'post', 'weekly_update', 'events', 'staff', 'hs_search_topic', 'et_pb_layout' ), true ) ) {
		hs_search_schedule_build();
	}
}, 20, 2 );
add_action( 'deleted_post', function () { hs_search_schedule_build(); } );
foreach ( array( 'edited_term', 'created_term', 'delete_term' ) as $hs_search_hook ) {
	add_action( $hs_search_hook, function ( $term_id, $tt_id = 0, $taxonomy = '' ) {
		if ( in_array( $taxonomy, array( 'staff_campus', 'staff_department', 'staff_display_group', 'hs_campus', 'hs_post_campus', 'hs_department', 'in_this_issue' ), true ) ) {
			hs_search_schedule_build();
		}
	}, 10, 3 );
}
unset( $hs_search_hook );

/* ---------------------------------------------------------------------------
 * Search terms vocabulary (option hs_search_vocab), read by the build.
 * Edited on Site search → Search terms (Search-Terms snippet).
 * ------------------------------------------------------------------------ */
function hs_search_vocab_defaults() {
	return array(
		'groups'  => array(
			array( 'w' => 'enrol, enroll, enrolment, enrollment, enrolling, enrolled', 'on' => 1 ),
			array( 'w' => 'programme, program, programmes, programs', 'on' => 1 ),
			array( 'w' => 'colour, color, colours, colors', 'on' => 1 ),
			array( 'w' => 'centre, center', 'on' => 1 ),
			array( 'w' => 'organise, organize, organised, organized', 'on' => 1 ),
			array( 'w' => 'uniform, kit, clothing', 'on' => 1 ),
			array( 'w' => 'eca, ecas, club, clubs, after school, extracurricular, extra curricular', 'on' => 1 ),
		),
		'expand'  => array(
			array( 'f' => 'yr', 't' => 'year', 'on' => 1 ),
			array( 'f' => 'eyfs', 't' => 'foundation, reception, preschool, nursery', 'on' => 1 ),
			array( 'f' => 'ks1', 't' => 'year 1, year 2', 'on' => 1 ),
			array( 'f' => 'ks2', 't' => 'year 3, year 4, year 5, year 6', 'on' => 1 ),
			array( 'f' => 'ks3', 't' => 'year 7, year 8, year 9', 'on' => 1 ),
			array( 'f' => 'ks4', 't' => 'year 10, year 11, igcse', 'on' => 1 ),
			array( 'f' => 'ks5', 't' => 'year 12, year 13, sixth form, a level', 'on' => 1 ),
		),
		'campus'  => array(
			'chaofah'     => 'chaofah, chao fah, city campus, hsc',
			'cherngtalay' => 'cherngtalay, cherng talay, north, hsn',
		),
		'promote' => array(),
		'handled' => array(),
	);
}

function hs_search_vocab() {
	$v = get_option( 'hs_search_vocab', null );
	if ( ! is_array( $v ) ) {
		$v = hs_search_vocab_defaults();
		update_option( 'hs_search_vocab', $v, false );
	}
	return array_merge( hs_search_vocab_defaults(), $v );
}

function hs_search_split_list( $s ) {
	return array_values( array_unique( array_filter( array_map( function ( $x ) {
		return strtolower( trim( preg_replace( '/\s+/u', ' ', $x ) ) );
	}, preg_split( '/[,\n]+/', (string) $s ) ) ) ) );
}

function hs_search_vocab_campuses() {
	$v = hs_search_vocab();
	return array(
		array( 'k' => 'chaofah', 'l' => 'Chaofah', 'a' => hs_search_split_list( $v['campus']['chaofah'] ?? 'chaofah' ) ),
		array( 'k' => 'cherngtalay', 'l' => 'Cherngtalay', 'a' => hs_search_split_list( $v['campus']['cherngtalay'] ?? 'cherngtalay' ) ),
	);
}

/** Rules for the engine: g = synonym groups, x = one-way expansions, p = promoted results. */
function hs_search_vocab_rules() {
	$v   = hs_search_vocab();
	$out = array( 'g' => array(), 'x' => array(), 'p' => array() );
	foreach ( (array) $v['groups'] as $g ) {
		$w = hs_search_split_list( $g['w'] ?? '' );
		if ( ! empty( $g['on'] ) && count( $w ) > 1 ) {
			$out['g'][] = $w;
		}
	}
	foreach ( (array) $v['expand'] as $x ) {
		$f = strtolower( trim( $x['f'] ?? '' ) );
		$t = hs_search_split_list( $x['t'] ?? '' );
		if ( ! empty( $x['on'] ) && $f && $t ) {
			$out['x'][] = array( $f, $t );
		}
	}
	foreach ( (array) $v['promote'] as $p ) {
		$term = strtolower( trim( $p['term'] ?? '' ) );
		$ids  = array_slice( array_filter( array_map( 'trim', explode( ',', (string) ( $p['ids'] ?? '' ) ) ) ), 0, 5 );
		if ( ! empty( $p['on'] ) && $term && $ids ) {
			$out['p'][] = array( $term, array_values( $ids ) );
		}
	}
	return $out;
}
