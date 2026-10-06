<?php

/**
 * Posts--Routing-Engine
 */
/**
 * MASTER ENGINE 2: STANDARD POST ROUTING (SCHOOL LIFE)
 * Routes standard WordPress posts to /{campus}/school-life/YYYY/MM/post-name/
 */

// 1. THE INBOUND CATCHER: Catch the new URL structure and tell WP it's a standard post
add_action( 'init', 'hs_school_life_rewrite_rules', 10 );
function hs_school_life_rewrite_rules() {
    // Catch /chaofah/school-life/2026/07/post-name/
    // Standard posts use the 'name' query var for the post slug
    add_rewrite_rule(
        '^([^/]+)/school-life/([0-9]{4})/([0-9]{2})/([^/]+)/?$',
        'index.php?name=$matches[4]&post_type=post',
        'top'
    );
}

// 2. THE OUTBOUND INJECTOR: Inject campus, year, and month into the permalink
add_filter( 'post_link', 'hs_school_life_link_injector', 10, 3 );
function hs_school_life_link_injector( $permalink, $post, $leavename = false ) {
    
    // Only intercept links for standard blog posts
    if ( is_object( $post ) && 'post' === $post->post_type ) {
        
        // Bail out if the post is a draft/pending (prevents 404s when Divi tries to load the builder)
        if ( in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft', 'trash' ) ) ) {
            return $permalink;
        }
        
        // A. Get Base URL Segment (Location Override or Campus Slug)
        $campus = 'phuket'; // Default fallback
        
        // 1. Check for explicit location override first
        $location_override = get_field('hs_location_override', $post->ID);
        if (!empty($location_override)) {
            $campus = sanitize_title($location_override);
        } else {
            // 2. Fall back to campus taxonomy
            $campus_terms = get_the_terms($post->ID, 'hs_post_campus');
            if ( empty($campus_terms) || is_wp_error($campus_terms) ) {
                $acf_campus = get_field('hs_campus_tax', $post->ID);
                if ( !empty($acf_campus) ) {
                    $campus_terms = array();
                    $acf_array = is_array($acf_campus) ? $acf_campus : array($acf_campus);
                    foreach ($acf_array as $term_data) {
                        $term = is_object($term_data) ? $term_data : get_term($term_data, 'hs_post_campus');
                        if ( $term && !is_wp_error($term) ) {
                            $campus_terms[] = $term;
                        }
                    }
                }
            }
            
            if (!empty($campus_terms) && !is_wp_error($campus_terms)) {
                if (count($campus_terms) === 1) {
                    $campus = $campus_terms[0]->slug;
                } else if (count($campus_terms) > 1) {
                    $campus = 'both-campuses';
                }
            }
        }

        // B. Get Post Year and Month
        $time = strtotime( $post->post_date );
        $year = date( 'Y', $time );
        $month = date( 'm', $time );

        // C. Construct the final custom URL
        $slug = $leavename ? '%postname%' : $post->post_name;
        $new_url = home_url( '/' . $campus . '/school-life/' . $year . '/' . $month . '/' . $slug . '/' );
        
        return $new_url;
    }

    return $permalink;
}
