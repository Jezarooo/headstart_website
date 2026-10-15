<?php

/**
 * Weekly-Update--Shortcode-Engine
 */
/**
 * Snippet #9: Weekly-Update--Shortcode-Engine
 * active: 1 | scope: global | priority: 10
 * modified: 2026-08-29 11:38:17
 * 
 */


/**
 * =============================================================================
 * HEADSTART WEEKLY UPDATE SHORTCODE ENGINE
 * =============================================================================
 * PURPOSE: Logic and execution for WEEKLY UPDATE SHORTCODE ENGINE
 * DEPENDENCIES: WordPress core, Divi
 * [TODO: REVIEW_OPTIMIZATIONS - Extract inline JS/CSS, implement Nonces, escape HTML]
 * =============================================================================
 */

/**
 * ====================================================================
 * WEEKLY UPDATES: MASTER PHP SHORTCODE ENGINE (V2 - AJAX OPTIMIZED)
 * Registers [weekly_updates_list campus="chaofah"] (or cherngtalay / all)
 * Outputs initial skeleton DOM and provides AJAX endpoints for 10-year scale.
 * ====================================================================
 */

// Helper: Academic Year Calculator (August Rollover)
if (!function_exists('hs_get_academic_year')) {
    function hs_get_academic_year($date_string) {
        $timestamp = strtotime($date_string);
        if (!$timestamp) $timestamp = time(); // Fallback for drafts without dates
        
        $month = (int)date('n', $timestamp);
        $year = (int)date('Y', $timestamp);
        
        if ($month >= 8) {
            return $year . '-' . ($year + 1);
        } else {
            return ($year - 1) . '-' . $year;
        }
    }
}

// Helper: Generate dynamic list of academic years based on oldest post
if (!function_exists('hs_get_all_academic_years')) {
    function hs_get_all_academic_years() {
        $current_year = (int)date('Y');
        $current_month = (int)date('n');
        $start_year = ($current_month >= 8) ? $current_year : ($current_year - 1);
        
        // Cache the oldest year to prevent DB queries on every page load
        $oldest_year = get_transient('hs_oldest_weekly_update_year');
        if (false === $oldest_year) {
            $oldest_query = new WP_Query(array(
                'post_type' => 'weekly_update',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'orderby' => 'date',
                'order' => 'ASC',
                'fields' => 'ids'
            ));
            
            if (!empty($oldest_query->posts)) {
                $oldest_date = get_the_date('Y-m-d', $oldest_query->posts[0]);
                $oldest_acad_year_str = hs_get_academic_year($oldest_date);
                // Extract just the start year of the oldest academic year (e.g. 2013 from "2013-2014")
                $oldest_year = (int)explode('-', $oldest_acad_year_str)[0];
            } else {
                $oldest_year = $start_year; // Fallback
            }
            set_transient('hs_oldest_weekly_update_year', $oldest_year, 24 * HOUR_IN_SECONDS);
        }

        $years = array();
        for ($yr = $start_year; $yr >= $oldest_year; $yr--) {
            $years[] = $yr . '-' . ($yr + 1);
        }
        return $years;
    }
}

add_shortcode('weekly_updates_list', 'hs_render_weekly_updates_shortcode');

function hs_render_weekly_updates_shortcode($atts) {
    $args_atts = shortcode_atts(array(
        'campus' => 'all', 
    ), $atts);

    $academic_years = hs_get_all_academic_years();

    ob_start();
    ?>
    <div class="weekly-update-list hs-weekly-update-v2" data-campus="<?php echo esc_attr($args_atts['campus']); ?>">
        <!-- Initial loading state & cards container -->
        <div class="hs-update-cards-container" id="hs-update-cards-container">
            <?php for ($i = 0; $i < 4; $i++): ?>
            <article class="weekly-card is-legacy hs-skeleton-card" style="background: rgba(0, 48, 87, 0.05); border: 1px solid #e0e0e0;">
                <div class="weekly-card-img-wrapper" style="display: flex; align-items: center; justify-content: center; position: absolute; inset: 0;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: var(--hs-blue, #0077c8); opacity: 0.5;"></i>
                </div>
                <div class="weekly-card-content" style="opacity: 0.6; z-index: 1;">
                    <div class="hs-legacy-inner-flex">
                        <div class="hs-legacy-flex-1">
                            <div style="height: 14px; background: #e0e0e0; width: 40%; margin-bottom: 12px; border-radius: 4px;"></div>
                            <div style="height: 24px; background: #e0e0e0; width: 70%; border-radius: 4px;"></div>
                        </div>
                    </div>
                </div>
            </article>
            <?php endfor; ?>
        </div>

        <!-- CAROUSEL CONTROLS & DYNAMIC YEAR DROPDOWN -->
        <div class="hs-carousel-controls-wrapper hs-pagination-controls" style="display: none;">
            <div class="hs-controls-left">
                <button class="hs-jump-first" id="hs-jump-first">Latest Newsletters</button>
            </div>
            <div class="hs-controls-center">
                <button class="hs-carousel-btn hs-carousel-prev" id="hs-carousel-prev" disabled><i class="fa-solid fa-arrow-left"></i></button>
                <div class="hs-carousel-dots hs-pagination-dots" id="hs-pagination-dots"></div>
                <button class="hs-carousel-btn hs-carousel-next" id="hs-carousel-next"><i class="fa-solid fa-arrow-right"></i></button>
            </div>
            <div class="hs-controls-right">
                <div class="hs-mobile-filter-container">
                    <select class="hs-mobile-filter" id="hs-year-filter">
                        <option value="all">All Years</option>
                        <?php 
                        foreach($academic_years as $yr) {
                            echo '<option value="' . esc_attr($yr) . '">Academic Year ' . esc_html($yr) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>

    </div>

    <?php
    $hs_config = array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => hs_weekly_updates_token()
    );
    $hs_config_b64 = base64_encode(wp_json_encode($hs_config));
    ?>
    <div id="hs-weekly-update-config-v2" data-config="<?php echo esc_attr($hs_config_b64); ?>" style="display:none;"></div>
    <?php
    return ob_get_clean();
}

/**
 * ====================================================================
 * AJAX ENDPOINT: LOAD WEEKLY UPDATES
 * ====================================================================
 */

// Self-contained token, not a WP nonce: this action is public/read-only
// (registered nopriv) and this page gets cached for longer than a WP nonce
// survives, which 403'd the widget once the cache outlived the nonce. A WP
// nonce ties to current-user/session state that isn't relevant here anyway;
// this depends on nothing but the site's own salt and a 30-day time window.
function hs_weekly_updates_token($window_offset = 0) {
    $window = floor(time() / (30 * DAY_IN_SECONDS)) - $window_offset;
    return substr(hash_hmac('sha256', 'hs_weekly_updates_nonce|' . $window, wp_salt('nonce')), 0, 12);
}

function hs_weekly_updates_token_valid($token) {
    return hash_equals(hs_weekly_updates_token(0), (string) $token)
        || hash_equals(hs_weekly_updates_token(1), (string) $token);
}

add_action('wp_ajax_hs_load_weekly_updates', 'hs_ajax_load_weekly_updates_handler');
add_action('wp_ajax_nopriv_hs_load_weekly_updates', 'hs_ajax_load_weekly_updates_handler');

function hs_ajax_load_weekly_updates_handler() {
    if (!hs_weekly_updates_token_valid(isset($_POST['nonce']) ? $_POST['nonce'] : '')) {
        wp_send_json_error('Invalid token', 403);
    }

    $campus = isset($_POST['campus']) ? sanitize_text_field($_POST['campus']) : 'all';
    $year_filter = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : 'all';
    $paged = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $posts_per_page = 20;

    // Include drafts and private posts for logged in users
    $is_logged_in = is_user_logged_in();
    $post_status = array('publish');
    if ($is_logged_in) {
        $post_status[] = 'draft';
        $post_status[] = 'private';
    }

    // 1. Check for Cached Transient (segmented by login status so drafts don't leak)
    $version = get_option('hs_weekly_updates_version', 1);
    $cache_key = 'hs_weekly_update_cache_v' . $version . '_' . md5($campus . '_' . $year_filter . '_' . $paged . '_' . ($is_logged_in ? '1' : '0'));
    $cached_response = get_transient($cache_key);

    if ($cached_response !== false) {
        wp_send_json_success($cached_response);
        return;
    }

    $query_args = array(
        'post_type'      => 'weekly_update',
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'post_status'    => $post_status,
        'orderby'        => 'date',
        'order'          => 'DESC'
    );

    // Filter by Campus
    if ($campus !== 'all') {
        $query_args['tax_query'] = array(
            array(
                'taxonomy' => 'hs_campus',
                'field'    => 'slug',
                'terms'    => $campus,
            ),
        );
    }

    // Date Query for Academic Year (Aug 1 to Jul 31)
    if ($year_filter !== 'all') {
        $parts = explode('-', $year_filter);
        if (count($parts) === 2) {
            $start_year = (int)$parts[0];
            $end_year = (int)$parts[1];
            $query_args['date_query'] = array(
                array(
                    'after'     => array('year' => $start_year, 'month' => 8, 'day' => 1),
                    'before'    => array('year' => $end_year, 'month' => 7, 'day' => 31),
                    'inclusive' => true,
                ),
            );
        }
    }

    $query = new WP_Query($query_args);
    $response_cards = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            // Raw values
            $raw_date = get_the_date('Y-m-d');
            
            // Use native get_post_meta instead of get_field to save massive DB overhead
            $week_num = get_post_meta($post_id, 'hs_week_number', true);
            $is_legacy = get_post_meta($post_id, 'hs_legacy_archive', true);
            
            // Image (use medium_large 768px for massive bandwidth savings)
            $img_url = get_the_post_thumbnail_url($post_id, 'medium_large');
            if (!$img_url) {
                $img_url = 'https://images.unsplash.com/photo-1588072432836-e10032774350?auto=format&fit=crop&w=600&q=80';
            }

            // Tags (lightweight taxonomy query, usually cached natively by WP)
            $tags = get_the_terms($post_id, 'in_this_issue');
            $tag_names = array();
            if ($tags && !is_wp_error($tags)) {
                foreach ($tags as $t) {
                    $tag_names[] = esc_html($t->name);
                }
            }

            // Check for custom blurb field
            $custom_blurb = get_post_meta($post_id, 'blurb', true);
            if (empty($custom_blurb)) $custom_blurb = get_post_meta($post_id, 'hs_blurb', true);
            if (empty($custom_blurb)) $custom_blurb = get_post_meta($post_id, 'weekly_update_blurb', true);
            if (empty($custom_blurb)) $custom_blurb = get_post_meta($post_id, 'custom_blurb', true);
            
            $excerpt = !empty($custom_blurb) ? $custom_blurb : get_the_excerpt();

            // Push clean JSON data, escaping text to prevent XSS
            $response_cards[] = array(
                'id' => $post_id,
                'status' => get_post_status($post_id),
                'title' => esc_html((string)get_the_title()),
                'link' => esc_url((string)get_permalink()),
                'excerpt' => esc_html((string)$excerpt),
                'display_date' => esc_html((string)get_the_date('l, j M Y')),
                'legacy_date' => esc_html((string)get_the_date('d M Y')),
                'acad_year' => esc_html((string)hs_get_academic_year($raw_date)),
                'week_num' => esc_html((string)$week_num),
                'is_legacy' => !empty($is_legacy),
                'img_url' => esc_url((string)$img_url),
                'tags' => $tag_names
            );
        }
        wp_reset_postdata();
    }

    $response_data = array(
        'cards' => $response_cards,
        'max_pages' => $query->max_num_pages,
        'total_posts' => $query->found_posts,
        'current_page' => $paged
    );

    // Cache the response payload for 24 hours (cleared automatically by save_post hook)
    set_transient($cache_key, $response_data, 24 * HOUR_IN_SECONDS);

    wp_send_json_success($response_data);
}

/**
 * ====================================================================
 * TRANSIENT CACHE INVALIDATION
 * Clears Weekly Update AJAX cache when a post is published, updated, or deleted.
 * ====================================================================
 */
add_action('save_post_weekly_update', 'hs_clear_weekly_update_transients');
add_action('deleted_post', 'hs_clear_weekly_update_transients_on_delete');

function hs_clear_weekly_update_transients_on_delete($post_id) {
    if (get_post_type($post_id) === 'weekly_update') {
        hs_clear_weekly_update_transients();
    }
}

function hs_clear_weekly_update_transients() {
    // Increment the cache version to instantly invalidate all cached weekly updates
    $version = get_option('hs_weekly_updates_version', 1);
    update_option('hs_weekly_updates_version', $version + 1);
    
    // Clear the oldest year dynamic filter cache
    delete_transient('hs_oldest_weekly_update_year');
}

/* ==========================================================================
 * 4. HELPER SHORTCODES FOR DIVI THEME BUILDER
 * ========================================================================== */

add_shortcode('hs_academic_year', 'hs_academic_year_shortcode');
function hs_academic_year_shortcode() {
    $post_id = get_the_ID();
    if (!$post_id) return '';
    $raw_date = get_the_date('Y-m-d', $post_id);
    if (!function_exists('hs_get_academic_year')) return '';
    return esc_html(hs_get_academic_year($raw_date));
}

add_shortcode('hs_weekly_campus', 'hs_weekly_campus_shortcode');
function hs_weekly_campus_shortcode() {
    $post_id = get_the_ID();
    if (!$post_id) return '';
    $terms = get_the_terms($post_id, 'hs_campus');
    if ($terms && !is_wp_error($terms)) {
        $campus_name = strtolower($terms[0]->name);
        if (strpos($campus_name, 'chaofah') !== false) {
            return 'Chaofah City Campus';
        } elseif (strpos($campus_name, 'cherngtalay') !== false) {
            return 'Cherngtalay Campus';
        }
        return esc_html($terms[0]->name); // Fallback
    }
    return '';
}

add_shortcode('hs_weekly_blurb', 'hs_weekly_blurb_shortcode');
function hs_weekly_blurb_shortcode() {
    $post_id = get_the_ID();
    if (!$post_id) return '';
    
    // Check various fallback meta keys for the blurb
    $custom_blurb = get_post_meta($post_id, 'blurb', true);
    if (empty($custom_blurb)) $custom_blurb = get_post_meta($post_id, 'hs_blurb', true);
    if (empty($custom_blurb)) $custom_blurb = get_post_meta($post_id, 'weekly_update_blurb', true);
    if (empty($custom_blurb)) $custom_blurb = get_post_meta($post_id, 'custom_blurb', true);
    
    if (empty($custom_blurb)) {
        // Fallback to standard excerpt if completely empty
        $custom_blurb = get_the_excerpt($post_id);
    }
    
    // Use wp_kses_post to allow safe HTML (paragraphs, bold, etc.) but strip dangerous scripts
    return wp_kses_post($custom_blurb);
}

/**
 * ====================================================================
 * WEEKLY UPDATE: INDIVIDUAL WEEK NUMBER SHORTCODE
 * Used in Divi Global Templates to reliably pull the week number.
 * Usage: [hs_week_number]
 * ====================================================================
 */
add_shortcode('hs_week_number', 'hs_render_week_number_shortcode');
function hs_render_week_number_shortcode($atts) {
    $post_id = get_the_ID();
    if (!$post_id) return '';
    
    // Check current meta key
    $week_num = get_post_meta($post_id, 'hs_week_number', true);
    
    // Fallback for legacy key if someone typed it without the underscore
    if (empty($week_num)) {
        $week_num = get_post_meta($post_id, 'hs_weeknumber', true);
    }
    
    return esc_html($week_num);
}
