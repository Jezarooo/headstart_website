<?php

/**
 * Posts--Compute-Taxonomies-From-Custom-Fields
 */
/**
 * =============================================================================
 * HEADSTART POSTS COMPUTE TAXONOMIES FROM CUSTOM FIELDS
 * =============================================================================
 * PURPOSE: Logic and execution for POSTS COMPUTE TAXONOMIES FROM CUSTOM FIELDS
 * DEPENDENCIES: WordPress core, Divi
 * =============================================================================
 */

/**
 * Compute Final Chips for Divi Builder
 * 
 * Automatically calculates the final strings for Location and Website Section
 * when a post is saved, merging taxonomies and overrides into simple custom fields.
 */

add_action('save_post', 'hs_compute_final_divi_chips', 20);

function hs_compute_final_divi_chips($post_id) {
    // Skip on autosaves to prevent unnecessary database writes
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Only run this logic for standard Posts
    if (get_post_type($post_id) !== 'post') {
        return;
    }

    // ========================================================================
    // 1. LOCATION CHIP (hs_location_override OR hs_post_campus)
    // ========================================================================
    $location_text = '';
    $location_override = get_post_meta($post_id, 'hs_location_override', true);

    if (!empty($location_override)) {
        $location_text = sanitize_text_field($location_override);
    } else {
        $campus_terms = get_the_terms($post_id, 'hs_post_campus');
        if ($campus_terms && !is_wp_error($campus_terms)) {
            if (count($campus_terms) > 1) {
                $location_text = 'Both Campuses';
            } else {
                $location_text = sanitize_text_field($campus_terms[0]->name);
            }
        }
    }
    update_post_meta($post_id, 'hs_final_location_chip', $location_text);

    // ========================================================================
    // 2. WEBSITE SECTION (hs_thematic_chip override OR hs_department)
    // ========================================================================
    $section_text = '';
    
    // Check Thematic Chip taxonomy (Yellow Badge Override) first
    $thematic_terms = get_the_terms($post_id, 'hs_thematic_chip');
    
    if ($thematic_terms && !is_wp_error($thematic_terms) && !empty($thematic_terms)) {
        $section_text = sanitize_text_field($thematic_terms[0]->name);
    } else {
        // Fallback to standard Department (Website Section)
        $dept_terms = get_the_terms($post_id, 'hs_department');
        if ($dept_terms && !is_wp_error($dept_terms) && !empty($dept_terms)) {
            $section_text = sanitize_text_field($dept_terms[0]->name);
        }
    }
    update_post_meta($post_id, 'hs_final_section_chip', $section_text);
}

// ========================================================================
// ONE-TIME BULK UPDATE FOR EXISTING POSTS
// ========================================================================
// This runs exactly once when you load the WordPress Admin Dashboard.
// It loops through all existing posts, generates the chip, and sets a flag
// so it never runs again and doesn't slow down your site.

// add_action('admin_init', 'hs_run_bulk_location_update_once');

function hs_run_bulk_location_update_once() {
    /*
    // Check if we already ran this
    if (!get_option('hs_bulk_location_update_completed_v1')) {
        
        // Grab all standard posts
        $all_posts = get_posts(array(
            'post_type' => 'post',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));

        // Run our save function on every single one
        foreach ($all_posts as $post) {
            hs_compute_final_divi_chips($post->ID);
        }

        // Set a flag in the database so this never runs again
        add_option('hs_bulk_location_update_completed_v1', true);
    }
    */
}

// ========================================================================
// CATCH AJAX UPDATES (List View)
// ========================================================================
// When the admin list view toggles are used, WP bypasses save_post.
// We must explicitly trigger our chip calculator when taxonomies change.
add_action('set_object_terms', 'hs_compute_chips_on_ajax_tax_update', 10, 4);
function hs_compute_chips_on_ajax_tax_update($object_id, $terms, $tt_ids, $taxonomy) {
    // Only run if one of our chip-related taxonomies was just updated
    if (in_array($taxonomy, array('hs_post_campus', 'hs_thematic_chip', 'hs_department'))) {
        hs_compute_final_divi_chips($object_id);
    }
}

// Catch List View Meta updates (Location Override)
add_action('updated_post_meta', 'hs_compute_chips_on_ajax_meta_update', 10, 4);
function hs_compute_chips_on_ajax_meta_update($meta_id, $object_id, $meta_key, $meta_value) {
    if ($meta_key === 'hs_location_override') {
        hs_compute_final_divi_chips($object_id);
    }
}
