<?php

/**
 * Weekly-Update--URL-Routing
 *
 * Handles the dynamic URL routing for the Weekly Update Custom Post Type. It registers the rewrite tags, adds custom rewrite rules to map URL structures like /cherngtalay/weekly-update/post-name, and filters links on the backend/frontend to dynamically insert the ticked campus taxonomy slug.
 */
/**
 * =============================================================================
 * HEADSTART WEEKLY UPDATE URL ROUTING
 * =============================================================================
 * PURPOSE: Logic and execution for WEEKLY UPDATE URL ROUTING
 * DEPENDENCIES: WordPress core, Divi
 * [TODO: REVIEW_OPTIMIZATIONS - Extract inline JS/CSS, implement Nonces, escape HTML]
 * =============================================================================
 */

/**
 * MASTER ENGINE 1: THE PHANTOM PROXY ROUTER (WITH DATES)
 * Routes weekly updates to /{campus}/weekly-update/YYYY/MM/post-name/
 */

// 1. Tell WP that "hs_campus" is a valid URL parameter to query
add_action( 'init', 'hs_register_campus_query_var', 1 );
function hs_register_campus_query_var() {
    add_rewrite_tag( '%hs_campus%', '([^/]+)' );
}

// 2. FORCE UNIQUE DB SLUGS ON SAVE
// Appends campus and date to DB slug to prevent WP from appending -2
add_filter( 'wp_insert_post_data', 'hs_force_unique_weekly_slug', 10, 2 );
function hs_force_unique_weekly_slug( $data, $postarr ) {
    if ( $data['post_type'] === 'weekly_update' && $data['post_status'] !== 'trash' && !empty( $data['post_name'] ) ) {
        
        $raw_slug = $data['post_name'];
        
        // Strip out any existing phantom suffix if they are updating a post
        // Pattern matches: -{campus}-YYYY-MM at the end of the slug
        $raw_slug = preg_replace('/-[a-z0-9]+-20\d{2}-\d{2}$/', '', $raw_slug);
        
        // Get Campus (handle both block editor and classic editor arrays)
        $campus_slug = 'general';
        if ( !empty( $postarr['tax_input']['hs_campus'] ) ) {
            $term_id = is_array($postarr['tax_input']['hs_campus']) ? $postarr['tax_input']['hs_campus'][0] : $postarr['tax_input']['hs_campus'];
            // If it's a new tag in Gutenberg, it might be a string
            if ( is_numeric($term_id) ) {
                $term = get_term( $term_id, 'hs_campus' );
                if ( $term && !is_wp_error( $term ) ) {
                    $campus_slug = $term->slug;
                }
            }
        } elseif ( !empty( $postarr['ID'] ) ) {
            $terms = wp_get_object_terms( $postarr['ID'], 'hs_campus' );
            if ( !is_wp_error( $terms ) && !empty( $terms ) ) {
                $campus_slug = $terms[0]->slug;
            }
        }

        // Get Date
        $time = strtotime( $data['post_date'] );
        $year = date( 'Y', $time );
        $month = date( 'm', $time );

        // Create a truly unique DB slug: e.g. weekly-update-1-cherngtalay-2026-08
        $data['post_name'] = $raw_slug . '-' . $campus_slug . '-' . $year . '-' . $month;
    }
    return $data;
}


// 3. THE INBOUND CATCHER: Catch both new (dated) and old URLs
add_action( 'init', 'hs_phantom_rewrite_rules', 10 );
function hs_phantom_rewrite_rules() {
    
    // NEW STRUCTURE: Catch /chaofah/weekly-update/2026/07/post-name/
    // We piece together the phantom DB slug here so WP_Query perfectly hits 1 post
    add_rewrite_rule(
        '^([^/]+)/weekly-update/([0-9]{4})/([0-9]{2})/([^/]+)/?$',
        'index.php?weekly_update=$matches[4]-$matches[1]-$matches[2]-$matches[3]&post_type=weekly_update',
        'top'
    );

    // FALLBACK: Catch old /chaofah/weekly-update/post-name/ so existing links don't 404
    add_rewrite_rule(
        '^([^/]+)/weekly-update/([^/]+)/?$',
        'index.php?weekly_update=$matches[2]&post_type=weekly_update',
        'top'
    );
}

// 4. THE OUTBOUND INJECTOR: every weekly update link is built as
//    /{campus}/weekly-update/{YYYY}/{MM}/{clean-slug}/  (the preferred URL).
//    Built from scratch (not string-replaced), so it also covers the editable
//    form WordPress asks for in the block editor ($leavename = true).

/**
 * Campus, year, month and clean slug for a weekly update: the one place these rules live.
 * Returns null when the post has no slug yet.
 */
function hs_weekly_update_parts( $post ) {
    $post = get_post( $post );
    if ( ! $post || 'weekly_update' !== $post->post_type || '' === (string) $post->post_name ) {
        return null;
    }
    $terms  = wp_get_object_terms( $post->ID, 'hs_campus' );
    $campus = ( ! is_wp_error( $terms ) && ! empty( $terms ) && is_object( $terms[0] ) ) ? $terms[0]->slug : 'general';
    $time   = strtotime( $post->post_date );
    $year   = date( 'Y', $time );
    $month  = date( 'm', $time );
    $suffix = '-' . $campus . '-' . $year . '-' . $month;
    $slug   = $post->post_name;
    if ( strlen( $slug ) > strlen( $suffix ) && substr( $slug, -strlen( $suffix ) ) === $suffix ) {
        $slug = substr( $slug, 0, -strlen( $suffix ) );
    }
    return array( 'campus' => $campus, 'year' => $year, 'month' => $month, 'slug' => $slug );
}

/** Site-relative preferred path, e.g. /cherngtalay/weekly-update/2026/10/weekly-update-7/ */
function hs_weekly_update_path( $parts, $last_segment = null ) {
    return '/' . $parts['campus'] . '/weekly-update/' . $parts['year'] . '/' . $parts['month'] . '/'
        . ( null === $last_segment ? $parts['slug'] : $last_segment ) . '/';
}

add_filter( 'post_type_link', 'hs_phantom_link_injector', 10, 3 );
function hs_phantom_link_injector( $post_link, $post, $leavename = false ) {
    if ( ! is_object( $post ) || 'weekly_update' !== $post->post_type ) {
        return $post_link;
    }
    // Unpublished posts keep WordPress's own link (?post_type=…&p=…) so previews work.
    if ( ! $leavename && in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
        return $post_link;
    }
    $parts = hs_weekly_update_parts( $post );
    if ( ! $parts ) {
        return $post_link;
    }
    return home_url( hs_weekly_update_path( $parts, $leavename ? '%weekly_update%' : null ) );
}

// 4b. EDITOR SLUG: show the clean slug (weekly-update-7), not the stored one
//     (weekly-update-7-cherngtalay-2026-10). Saving still works: section 2 strips
//     and re-adds the suffix on every save.
function hs_weekly_update_strip_suffix( $slug ) {
    return preg_replace( '/-[a-z0-9]+-20\d{2}-\d{2}$/', '', (string) $slug );
}

add_filter( 'get_sample_permalink', 'hs_weekly_update_sample_permalink', 10, 5 );
function hs_weekly_update_sample_permalink( $permalink, $post_id, $title, $name, $post ) {
    if ( is_object( $post ) && 'weekly_update' === $post->post_type && is_array( $permalink ) && isset( $permalink[1] ) ) {
        $permalink[1] = hs_weekly_update_strip_suffix( $permalink[1] );
    }
    return $permalink;
}

// The block editor reads a saved post's slug from the REST API (context=edit only;
// the public API is untouched).
add_filter( 'rest_prepare_weekly_update', 'hs_weekly_update_rest_slug', 10, 3 );
function hs_weekly_update_rest_slug( $response, $post, $request ) {
    if ( 'edit' === $request['context'] && isset( $response->data['slug'] ) ) {
        $response->data['slug'] = hs_weekly_update_strip_suffix( $response->data['slug'] );
    }
    return $response;
}

// 4c. CANONICAL REDIRECT: any other address for a weekly update (the raw
//     /weekly-update/{db-slug}/ form, or the older /{campus}/weekly-update/{slug}/
//     form) gets a 301 to the preferred URL, keeping the query string (UTM tags).
//     The preferred URL itself is never redirected.

/** Decide the redirect target for a request path. Returns null when no redirect is needed. */
function hs_weekly_update_redirect_target( $request_uri, $preferred_url ) {
    $req_path  = rawurldecode( (string) parse_url( $request_uri, PHP_URL_PATH ) );
    $want_path = rawurldecode( (string) parse_url( $preferred_url, PHP_URL_PATH ) );
    if ( '' === $want_path || untrailingslashit( strtolower( $req_path ) ) === untrailingslashit( strtolower( $want_path ) ) ) {
        return null;
    }
    $query = (string) parse_url( $request_uri, PHP_URL_QUERY );
    return $preferred_url . ( '' !== $query ? '?' . $query : '' );
}

add_action( 'template_redirect', 'hs_weekly_update_canonical_redirect', 5 );
function hs_weekly_update_canonical_redirect() {
    if ( ! is_singular( 'weekly_update' ) || is_preview() || is_feed() || is_admin() || wp_doing_ajax() ) {
        return;
    }
    if ( isset( $_GET['et_fb'] ) || isset( $_GET['et_tb'] ) || isset( $_GET['preview'] ) ) {
        return; // Divi builder and previews stay where they are.
    }
    $post = get_queried_object();
    if ( ! $post || 'publish' !== $post->post_status ) {
        return;
    }
    $preferred = get_permalink( $post );
    $target    = $preferred ? hs_weekly_update_redirect_target( $_SERVER['REQUEST_URI'] ?? '', $preferred ) : null;
    if ( $target ) {
        wp_safe_redirect( $target, 301 );
        exit;
    }
}

// 5. LEGACY/IMPORTED POST FALLBACK
// If a weekly update post doesn't exist with the phantom suffix (e.g. older posts),
// fall back to looking for the raw slug to prevent 404s on imported archives.
add_filter( 'request', 'hs_weekly_update_legacy_fallback' );
function hs_weekly_update_legacy_fallback( $query_vars ) {
    // Only intervene if this is a request for a weekly_update
    if ( isset( $query_vars['post_type'] ) && $query_vars['post_type'] === 'weekly_update' && !empty( $query_vars['weekly_update'] ) ) {
        global $wpdb;
        $slug = $query_vars['weekly_update'];
        
        // Ensure slug is a string to prevent TypeErrors if an array is passed via URL query string
        if ( is_string( $slug ) && preg_match( '/^(.*)-([a-z0-9]+)-([0-9]{4})-([0-9]{2})$/', $slug, $matches ) ) {
            $raw_slug = $matches[1];
            
            // Check if a post with the full phantom slug actually exists
            $post_exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'weekly_update' LIMIT 1", $slug ) );
            
            // If it doesn't exist, we check if the raw slug exists (which would be an imported/legacy post)
            if ( ! $post_exists ) {
                $raw_post_exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'weekly_update' LIMIT 1", $raw_slug ) );
                
                if ( $raw_post_exists ) {
                    // Overwrite the requested slug so WP_Query successfully finds the legacy post
                    $query_vars['weekly_update'] = $raw_slug;
                    if ( isset( $query_vars['name'] ) ) {
                        $query_vars['name'] = $raw_slug;
                    }
                }
            }
        }
    }
    
    return $query_vars;
}
