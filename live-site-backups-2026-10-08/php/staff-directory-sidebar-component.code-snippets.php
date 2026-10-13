<?php

/**
 * Staff-Directory--Sidebar-Component
 */
/**
 * =============================================================================
 * HEADSTART STAFF DIRECTORY SIDEBAR COMPONENT
 * =============================================================================
 * PURPOSE: Logic and execution for STAFF DIRECTORY SIDEBAR COMPONENT
 * DEPENDENCIES: WordPress core, Divi
 * [TODO: REVIEW_OPTIMIZATIONS - Extract inline JS/CSS, implement Nonces, escape HTML]
 * =============================================================================
 *
 * Plugin Name: HeadStart Staff Sidebar Widget
 * Description: Renders the staff sidebar widget via shortcode [hs_staff_sidebar campus="all"]
 * Version: 1.0
 * Author: Antigravity
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register Shortcode
add_shortcode('hs_staff_sidebar', 'hs_staff_sidebar_shortcode');

function hs_staff_sidebar_shortcode($atts) {
    $atts = shortcode_atts(array(
        'campus' => 'all', // 'chaofah', 'cherngtalay', or 'all'
        'count'  => 20     // Fetch more avatars so JS can dynamically fill the space
    ), $atts, 'hs_staff_sidebar');

    // 1. Query Random Staff with Profile Pictures
    $args = array(
        'post_type'      => 'staff',
        'posts_per_page' => 20, // Fetch a pool to ensure we get enough valid images
        'post_status'    => 'publish',
        'orderby'        => 'rand', // Randomize!
        'meta_query'     => array(
            array(
                'key'     => '_thumbnail_id', // Only staff with a featured image
                'compare' => 'EXISTS'
            )
        )
    );

    // Apply Campus Filtering if specified
    if ($atts['campus'] !== 'all') {
        $campus_mapping = array(
            'chaofah'     => 'Chaofah',
            'cherngtalay' => 'Cherngtalay'
        );
        $campus_name = isset($campus_mapping[$atts['campus']]) ? $campus_mapping[$atts['campus']] : 'Both Campuses';

        $args['tax_query'] = array(
            'relation' => 'OR',
            array(
                'taxonomy' => 'staff_campus',
                'field'    => 'name',
                'terms'    => $campus_name
            ),
            array(
                'taxonomy' => 'staff_campus',
                'field'    => 'name',
                'terms'    => 'Both Campuses'
            )
        );
    }

    $query = new WP_Query($args);
    $avatars_html = '';
    $avatar_count = 0;
    $max_avatars = intval($atts['count']);

    if ($query->have_posts()) {
        while ($query->have_posts() && $avatar_count < $max_avatars) {
            $query->the_post();
            
            // Get the Thumbnail (150x150 is perfect for these small circles)
            $image_url = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
            
            if ($image_url) {
                // Inline styles for z-index overlapping (Bubble will be 100)
                $z_index = 99 - $avatar_count;
                $avatars_html .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr(get_the_title()) . '" class="hs-staff-avatar" style="z-index: ' . $z_index . ';">';
                $avatar_count++;
            }
        }
        wp_reset_postdata();
    }

    // If no staff found, hide the widget
    if ($avatar_count === 0) {
        return '';
    }

    // Estimate total staff for the "+X" bubble
    $total_staff = wp_count_posts('staff')->publish;
    $display_count = $total_staff > 100 ? '+100' : '+' . $total_staff;

    // Use a relative URL so it seamlessly works on staging and production
    $directory_url = '/staff-directory/';

    // Prepare variables for Heredoc insertion
    $escaped_url = esc_url($directory_url);
    $attr_total_staff = esc_attr($total_staff);
    $html_display_count = esc_html($display_count);

    $html = <<<HTML
    <div class="hs-staff-widget">
        <a href="{$escaped_url}" class="hs-staff-widget-link">
            
            <div class="hs-staff-widget-header">
                <h3 class="hs-staff-widget-title">Meet Our Team</h3>
                <div class="hs-staff-widget-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                    </svg>
                </div>
            </div>

            <p class="hs-staff-widget-desc">Get to know the dedicated educators and staff at our campus.</p>

            <div class="hs-staff-avatars">
                <div class="hs-staff-more" data-total="{$attr_total_staff}">{$html_display_count}</div>
                {$avatars_html}
            </div>

            <div class="hs-staff-footer">
                View Full Directory
                <svg viewBox="0 0 24 24">
                    <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                </svg>
            </div>

        </a>
    </div>
HTML;

    return $html;
}
