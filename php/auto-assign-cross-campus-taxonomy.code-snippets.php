<?php
/**
 * Auto-assign Cross Campus Taxonomy Term
 * 
 * =============================================================================
 * HEADSTART AUTO-ASSIGN CROSS CAMPUS
 * =============================================================================
 * PURPOSE: Automatically assigns a "Cross Campus" term to a post if both 
 *          Chaofah and Cherngtalay are selected in the ACF checkboxes.
 * DEPENDENCIES: WordPress core, hs_post_campus taxonomy
 * =============================================================================
 */

function hs_auto_assign_cross_campus_term( $post_id, $post, $update ) {
    // Prevent running on autosave or if it's a revision
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }

    $taxonomy = 'hs_post_campus';
    
    // Ensure the taxonomy is actually registered before trying to modify it
    if ( ! taxonomy_exists( $taxonomy ) ) {
        return;
    }
    
    // Get the current terms assigned to the post (as an array of slugs)
    $terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
    
    if ( is_wp_error( $terms ) ) {
        return;
    }

    // Check for the specific campus slugs
    $has_chaofah     = in_array( 'chaofah', $terms );
    $has_cherngtalay = in_array( 'cherngtalay', $terms );
    $has_cross       = in_array( 'cross-campus', $terms );

    if ( $has_chaofah && $has_cherngtalay ) {
        // Post has BOTH campuses checked. It needs the cross-campus term.
        if ( ! $has_cross ) {
            // true = append the term without deleting existing terms
            wp_set_post_terms( $post_id, 'cross-campus', $taxonomy, true ); 
        }
    } else {
        // Post does NOT have both checked. It should not have cross-campus.
        if ( $has_cross ) {
            wp_remove_object_terms( $post_id, 'cross-campus', $taxonomy );
        }
    }
}
// Use priority 99 to ensure this runs after ACF or other standard saves have processed the terms
add_action( 'save_post', 'hs_auto_assign_cross_campus_term', 99, 3 );
