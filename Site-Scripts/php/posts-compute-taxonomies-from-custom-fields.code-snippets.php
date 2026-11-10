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

    // ========================================================================
    // 3. DATE LABEL (event start/end OR publish date), shown by the Post templates
    // ========================================================================
    update_post_meta($post_id, 'hs_final_date_label', hs_date_label_for_post($post_id));
}

/**
 * A stored Ymd date as 'Y-m-d 00:00:00', or '' when it isn't a real date.
 * Plain calendar dates: no timezone conversion, so the day never shifts.
 */
function hs_event_ymd_to_mysql($value) {
    $digits = preg_replace('/\D/', '', (string) $value);
    if (strlen($digits) !== 8) {
        return '';
    }
    $y = (int) substr($digits, 0, 4);
    $m = (int) substr($digits, 4, 2);
    $d = (int) substr($digits, 6, 2);
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $d) : '';
}

/**
 * The date readers see: the event date or range when set, otherwise the publish date.
 * 12 November 2026 · 12–14 November 2026 · 30 October – 2 November 2026 · 30 December 2026 – 2 January 2027
 */
function hs_date_label_for_post($post_id) {
    $start = hs_event_ymd_to_mysql(get_post_meta($post_id, 'hs_event_start', true));
    $end   = hs_event_ymd_to_mysql(get_post_meta($post_id, 'hs_event_end', true));

    if ($start === '') {
        $post = get_post($post_id);
        return $post ? mysql2date('j F Y', $post->post_date) : '';
    }
    if ($end === '' || $end <= $start) {
        return mysql2date('j F Y', $start);
    }
    if (substr($start, 0, 7) === substr($end, 0, 7)) {
        return mysql2date('j', $start) . "\u{2013}" . mysql2date('j F Y', $end);
    }
    if (substr($start, 0, 4) === substr($end, 0, 4)) {
        return mysql2date('j F', $start) . " \u{2013} " . mysql2date('j F Y', $end);
    }
    return mysql2date('j F Y', $start) . " \u{2013} " . mysql2date('j F Y', $end);
}

/**
 * The short date for slider cards: the event date or range when set, otherwise the
 * publish date exactly as the slider has always shown it (jS F Y). Worked out when
 * the card is drawn, so nothing is stored. Months are shortened only for ranges.
 * 12th November 2026 · 12–14 Nov 2026 · 30 Oct – 2 Nov 2026 · 30 Dec 2026 – 2 Jan 2027
 */
function hs_card_date_label($post_id) {
    $start = hs_event_ymd_to_mysql(get_post_meta($post_id, 'hs_event_start', true));
    $end   = hs_event_ymd_to_mysql(get_post_meta($post_id, 'hs_event_end', true));

    if ($start === '') {
        return get_the_date('jS F Y', $post_id);
    }
    if ($end === '' || $end <= $start) {
        return mysql2date('jS F Y', $start);
    }
    if (substr($start, 0, 7) === substr($end, 0, 7)) {
        return mysql2date('j', $start) . "\u{2013}" . mysql2date('j M Y', $end);
    }
    if (substr($start, 0, 4) === substr($end, 0, 4)) {
        return mysql2date('j M', $start) . " \u{2013} " . mysql2date('j M Y', $end);
    }
    return mysql2date('j M Y', $start) . " \u{2013} " . mysql2date('j M Y', $end);
}

// ========================================================================
// ONE-OFF FILL OF hs_final_date_label (10 Nov 2026)
// ========================================================================
// Gives every existing Post its date label, so the templates can show the label
// instead of the publish date. 200 Posts per admin page load until none is
// missing, then a flag stops it. Writes only that meta key: no re-save, no date change.
add_action('admin_init', 'hs_fill_date_labels_v1');
function hs_fill_date_labels_v1() {
    if (get_option('hs_date_label_fill_v1') || wp_doing_ajax()) {
        return;
    }
    $ids = get_posts(array(
        'post_type'        => 'post',
        'post_status'      => 'any',
        'posts_per_page'   => 200,
        'fields'           => 'ids',
        'orderby'          => 'ID',
        'order'            => 'ASC',
        'no_found_rows'    => true,
        'suppress_filters' => true,
        'meta_query'       => array(array('key' => 'hs_final_date_label', 'compare' => 'NOT EXISTS')),
    ));
    foreach ($ids as $id) {
        update_post_meta($id, 'hs_final_date_label', hs_date_label_for_post($id));
    }
    if (count($ids) < 200) {
        update_option('hs_date_label_fill_v1', gmdate('c'), false);
    }
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

// Catch List View Meta updates (Location Override, event dates), including first-time and cleared values
add_action('updated_post_meta', 'hs_compute_chips_on_ajax_meta_update', 10, 4);
add_action('added_post_meta', 'hs_compute_chips_on_ajax_meta_update', 10, 4);
add_action('deleted_post_meta', 'hs_compute_chips_on_ajax_meta_update', 10, 4);
function hs_compute_chips_on_ajax_meta_update($meta_id, $object_id, $meta_key, $meta_value) {
    if (in_array($meta_key, array('hs_location_override', 'hs_event_start', 'hs_event_end'), true)) {
        hs_compute_final_divi_chips($object_id);
    }
}
