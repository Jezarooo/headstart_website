<?php

/**
 * Posts--Slider-Shortcode
 */
/**
 * ====================================================================
 * HEADSTART POST SLIDER SHORTCODE
 * Registers [hs_post_slider department="foundation" campus="chaofah"]
 * Outputs 5/3/1 responsive Swiper carousel of latest posts.
 * Includes Round-Robin randomized algorithm for "All Posts" view.
 * Includes AJAX Smart Pagination for Grid View.
 * ====================================================================
 */

// The manual switch for the new logic
if (!defined('HS_ENABLE_LATEST_STRATEGY')) {
    define('HS_ENABLE_LATEST_STRATEGY', true);
}

add_shortcode('hs_post_slider', 'hs_render_post_slider_shortcode');

function hs_render_post_slider_shortcode($atts) {
    // 1. Parse Shortcode Attributes
    
    $default_orderby = HS_ENABLE_LATEST_STRATEGY ? 'hybrid' : 'random';
    
    $args = shortcode_atts(array(
        'department'     => '',       // e.g., 'foundation', 'primary'
        'campus'         => '',       // e.g., 'chaofah', 'cherngtalay'
        'posts_per_page' => 15,       // Max posts to cycle (or show per page)
        'display'        => 'slider', // 'slider' or 'grid'
        'show_filters'   => 'false',  // 'true' to show category buttons
        'title'          => '',       // Custom title for grid mode
        'exclude_recent_days' => '0', // e.g. 14 to exclude posts from last 14 days
        'enforce_context'=> 'false',  // 'true' to append ?hs_campus to all post links
        'ajax_cards_only'=> 'false',  // bypass HTML wrapper
        'orderby'        => $default_orderby, // 'random', 'latest', or 'hybrid'
    ), $atts);

    // 1.5 Handle Dynamic $_GET Filtering
    $show_filters = filter_var($args['show_filters'], FILTER_VALIDATE_BOOLEAN);
    $active_filter = '';
    
    if ($show_filters && isset($_GET['hs_dept']) && !empty($_GET['hs_dept'])) {
        $active_filter = sanitize_text_field($_GET['hs_dept']);
        if ($active_filter !== 'all') {
            $args['department'] = $active_filter;
        }
    }

    // 1.5.5 Context Bubble (Referrer Tracker)
    if (isset($_GET['hs_campus']) && !empty($_GET['hs_campus'])) {
        $args['campus'] = sanitize_text_field($_GET['hs_campus']);
        $args['enforce_context'] = 'true'; // Keep propagating the context
    }

    // 1.6 Handle Auto-Detect Campus and Department for non-standard-posts
    if (is_singular()) {
        // Auto-detect Department (ONLY for Pages)
        if (empty($args['department']) && get_post_type() === 'page') {
            $current_dept_terms = get_the_terms(get_the_ID(), 'hs_department');
            if ($current_dept_terms && !is_wp_error($current_dept_terms) && !empty($current_dept_terms)) {
                $slugs = array();
                foreach ($current_dept_terms as $t) {
                    $slugs[] = $t->slug;
                }
                $args['department'] = implode(',', $slugs);
            }
        }
        
        // Auto-detect Campus (Only for non-standard-posts, e.g. Weekly Updates)
        if (empty($args['campus']) && get_post_type() !== 'post') {
            $current_campus_terms = get_the_terms(get_the_ID(), 'hs_post_campus');
            if (empty($current_campus_terms) || is_wp_error($current_campus_terms)) {
                $current_campus_terms = get_the_terms(get_the_ID(), 'hs_campus');
            }
            if ($current_campus_terms && !is_wp_error($current_campus_terms) && !empty($current_campus_terms)) {
                $slugs = array();
                foreach ($current_campus_terms as $t) {
                    $slugs[] = $t->slug;
                }
                $args['campus'] = implode(',', $slugs);
                $args['enforce_context'] = 'true'; // Automatically enforce context for the detected campus!
            }
        }
    }
    
    // If user explicitly passed campus="all", clear it so it doesn't filter by campus
    if (strtolower($args['campus']) === 'all') {
        $args['campus'] = '';
    }

    $is_all_posts_mode = empty($args['department']);
    $is_dept_only_mode = !empty($args['department']) && empty($args['campus']);
    
    $target_count = intval($args['posts_per_page']);
    $display = $args['display'];
    
    // Pagination (from URL or AJAX)
    $paged = 1;
    if (isset($_POST['page']) && intval($_POST['page']) > 0) {
        $paged = intval($_POST['page']);
    } elseif (get_query_var('paged')) {
        $paged = get_query_var('paged');
    } elseif (get_query_var('page')) {
        $paged = get_query_var('page');
    }

    $posts_per_page = ($display === 'slider') ? 12 : $target_count;

    // Determine Seed for Stable Randomness
    // Single posts use their ID to get a unique but stable mix. Others use the current hour.
    // If this is an AJAX request, we receive the exact seed from the frontend to maintain state.
    if (isset($_POST['seed']) && !empty($_POST['seed'])) {
        $seed = intval($_POST['seed']);
    } else {
        $seed = is_singular() ? get_the_ID() : intval(date('G'));
    }

    // The post being read is left out of its own slider. AJAX requests are not is_single(), so the JS sends it back.
    $exclude_id = is_single() ? get_the_ID() : ((wp_doing_ajax() && !empty($_POST['exclude_id'])) ? intval($_POST['exclude_id']) : 0);
    
    // HTML Cache Key (caches the final rendered HTML per page)
    $html_transient_key = 'hs_slhtml54_' . md5(json_encode($args) . $active_filter . $paged . $seed);
    
    $cached_html = get_transient($html_transient_key);
    if ($cached_html !== false && empty($active_filter)) {
        $slider_instance_id = 'hspostslider' . uniqid();
        return str_replace('%%HS_SLIDER_ID_PLACEHOLDER%%', $slider_instance_id, $cached_html);
    }

    
    // Master List Cache Key (caches the full shuffled array of ~1000 IDs)
    $list_transient_key = 'hs_sllist33_' . md5(json_encode($args) . $active_filter . $seed . '_' . $exclude_id);
    $master_id_list = get_transient($list_transient_key);
    
    if ($master_id_list === false) {
        // 2. Deep Pull: Fetch ALL matching lightweight IDs
        $query_args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1000, 
            'fields'         => 'ids',
            'no_found_rows'  => true, // Massive optimization
        );
        
        // EXCLUDE CURRENT POST
        if ($exclude_id) {
            $query_args['post__not_in'] = array($exclude_id);
        }

        // EXCLUDE RECENT POSTS (e.g. 14 days for Weekly Update)
        $exclude_days = intval($args['exclude_recent_days']);
        if ($exclude_days > 0) {
            $query_args['date_query'] = array(
                array(
                    'before' => $exclude_days . ' days ago',
                ),
            );
        }

        $tax_query = array();
        if (!empty($args['department'])) {
            $dept_slugs = array_map('trim', explode(',', $args['department']));
            $tax_query[] = array(
                'taxonomy' => 'hs_department',
                'field'    => 'slug',
                'terms'    => $dept_slugs,
            );
        }

        if (!empty($args['campus'])) {
            $tax_query[] = array(
                'taxonomy' => 'hs_post_campus',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($args['campus']),
            );
        }

        if (count($tax_query) > 0) {
            $tax_query['relation'] = 'AND';
            $query_args['tax_query'] = $tax_query;
        }

        $deep_query = new WP_Query($query_args);
        
        $high_visual = array();
        $std_visual = array();
        $text_only = array();
        
        // 3. Bucket them by Image Count
        if ($deep_query->have_posts()) {
            global $wpdb;
            
            // SURGICAL OPTIMIZATION: Fetch ONLY the '_hs_body_image_count' for all posts in a single lightweight query
            $safe_ids = array_map('intval', $deep_query->posts);
            $post_ids_string = implode(',', $safe_ids);
            
            $meta_results = $wpdb->get_results("
                SELECT post_id, meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_hs_body_image_count' 
                AND post_id IN ($post_ids_string)
            ", OBJECT_K);
            
            foreach ($deep_query->posts as $pid) {
                $count = 0;
                if (isset($meta_results[$pid])) {
                    $count = (int) $meta_results[$pid]->meta_value;
                }
                
                if ($count >= 8) {
                    $high_visual[] = $pid;
                } elseif ($count >= 1) {
                    $std_visual[] = $pid;
                } else {
                    $text_only[] = $pid;
                }
            }
        }
        
        // 4. Handle Ordering (Random Seeded vs Latest)
        if ($args['orderby'] === 'random') {
            mt_srand($seed);
            $seeded_shuffle = function(&$items) {
                for ($i = count($items) - 1; $i > 0; $i--) {
                    $j = mt_rand(0, $i);
                    $tmp = $items[$i];
                    $items[$i] = $items[$j];
                    $items[$j] = $tmp;
                }
            };
            
            $seeded_shuffle($high_visual);
            $seeded_shuffle($std_visual);
            $seeded_shuffle($text_only);
            mt_srand();
        }
        
        // 5. The Weighted Weave
        $master_id_list = array();
        $total_posts = count($high_visual) + count($std_visual) + count($text_only);
        
        if ($args['orderby'] === 'hybrid') {
            $latest_pool = $deep_query->posts; // Date ordered natively
            $gallery_pool = $high_visual; // High visual posts
            
            // Shuffle the gallery pool for variety
            mt_srand($seed);
            $seeded_shuffle = function(&$items) {
                for ($i = count($items) - 1; $i > 0; $i--) {
                    $j = mt_rand(0, $i);
                    $tmp = $items[$i];
                    $items[$i] = $items[$j];
                    $items[$j] = $tmp;
                }
            };
            $seeded_shuffle($gallery_pool);
            mt_srand();
            
            $used_ids = array();
            $latest_idx = 0;
            $gallery_idx = 0;
            $pattern_step = 0;
            
            while (count($master_id_list) < $total_posts) {
                if ($pattern_step < 2) {
                    while ($latest_idx < count($latest_pool) && isset($used_ids[$latest_pool[$latest_idx]])) {
                        $latest_idx++;
                    }
                    if ($latest_idx < count($latest_pool)) {
                        $pid = $latest_pool[$latest_idx];
                        $master_id_list[] = $pid;
                        $used_ids[$pid] = true;
                        $latest_idx++;
                        $pattern_step++;
                    } else {
                        $pattern_step = 2; // switch to gallery
                    }
                } else {
                    while ($gallery_idx < count($gallery_pool) && isset($used_ids[$gallery_pool[$gallery_idx]])) {
                        $gallery_idx++;
                    }
                    if ($gallery_idx < count($gallery_pool)) {
                        $pid = $gallery_pool[$gallery_idx];
                        $master_id_list[] = $pid;
                        $used_ids[$pid] = true;
                        $gallery_idx++;
                    }
                    $pattern_step = 0; // reset
                }
                
                if ($latest_idx >= count($latest_pool) && $gallery_idx >= count($gallery_pool)) {
                    break;
                }
            }
        } elseif ($args['orderby'] === 'random') {
            // BATCH OPTIMIZATION: Cache all terms at once for maximum performance (1 DB query)
            update_object_term_cache($deep_query->posts, 'post');
            
            // Build a fast lookup map for taxonomies: $tax_map[post_id] = "deptSlug_campusSlug"
            $tax_map = array();
            foreach ($deep_query->posts as $pid) {
                $dept_terms = get_the_terms($pid, 'hs_department');
                $campus_terms = get_the_terms($pid, 'hs_post_campus');
                $d = ($dept_terms && !is_wp_error($dept_terms) && !empty($dept_terms)) ? $dept_terms[0]->slug : 'none';
                $c = ($campus_terms && !is_wp_error($campus_terms) && !empty($campus_terms)) ? $campus_terms[0]->slug : 'none';
                $tax_map[$pid] = $d . '_' . $c;
            }
            
            $last_dept = '';
            $last_campus = '';
            
            // Helper to intelligently pull the next post from a visual bucket (for randomized layout)
            $shift_func = function(&$bucket) use (&$last_dept, &$last_campus, &$tax_map) {
                if (empty($bucket)) return null;
                
                $chosen_idx = 0; // Fallback to first available if constraints fail
                
                // Try Strict Constraint: Different Dept AND Different Campus
                foreach ($bucket as $idx => $pid) {
                    $parts = explode('_', $tax_map[$pid], 2);
                    if ($parts[0] !== $last_dept && $parts[1] !== $last_campus) {
                        $chosen_idx = $idx;
                        break;
                    }
                }
                
                // Try Relaxed Constraint: Different Dept OR Different Campus
                if ($chosen_idx === 0 && !empty($last_dept)) {
                    foreach ($bucket as $idx => $pid) {
                        $parts = explode('_', $tax_map[$pid], 2);
                        if ($parts[0] !== $last_dept || $parts[1] !== $last_campus) {
                            $chosen_idx = $idx;
                            break;
                        }
                    }
                }
                
                $pid = $bucket[$chosen_idx];
                array_splice($bucket, $chosen_idx, 1);
                
                $parts = explode('_', $tax_map[$pid], 2);
                $last_dept = $parts[0];
                $last_campus = $parts[1];
                
                return $pid;
            };
            
            while (count($master_id_list) < $total_posts) {
                if (!empty($high_visual)) $master_id_list[] = $shift_func($high_visual);
                if (!empty($std_visual)) $master_id_list[] = $shift_func($std_visual);
                if (!empty($std_visual)) $master_id_list[] = $shift_func($std_visual);
                if (!empty($text_only)) $master_id_list[] = $shift_func($text_only);
            }
        } else {
            // For 'latest' ordering, just take the next newest post from the bucket without mixing logic
            $shift_func = function(&$bucket) {
                if (empty($bucket)) return null;
                return array_shift($bucket);
            };
            
            while (count($master_id_list) < $total_posts) {
                if (!empty($high_visual)) $master_id_list[] = $shift_func($high_visual);
                if (!empty($std_visual)) $master_id_list[] = $shift_func($std_visual);
                if (!empty($std_visual)) $master_id_list[] = $shift_func($std_visual);
                if (!empty($text_only)) $master_id_list[] = $shift_func($text_only);
            }
        }
        
        // Restore normal randomness for rest of WordPress
        mt_srand();
        
        // Save Master List Cache
        set_transient($list_transient_key, $master_id_list, 5 * MINUTE_IN_SECONDS);
    }
    
    // 6. Slice out the cards to render
    // Slider initial render: the last few cards + the first batch, so there is always a card to the left.
    // Slider lazy request (AJAX with offset/limit): an exact slice, loaded in either direction by the JS.
    // Grid: the classic page slice.
    $total_posts_count = count($master_id_list);
    $hs_tail_count = 3;
    $hs_left_start = $total_posts_count; // first index of the cards rendered at the end of the list
    $hs_right_end = $total_posts_count;  // one past the last index rendered from the start of the list
    $selected_indexes = array();
    $is_offset_request = wp_doing_ajax() && $display === 'slider' && isset($_POST['offset']);

    if ($is_offset_request) {
        $hs_offset = max(0, intval($_POST['offset']));
        $hs_limit = isset($_POST['limit']) ? max(1, min(24, intval($_POST['limit']))) : $posts_per_page;
        for ($i = $hs_offset; $i < min($total_posts_count, $hs_offset + $hs_limit); $i++) {
            $selected_indexes[] = $i;
        }
    } elseif ($display === 'slider') {
        if ($total_posts_count > $posts_per_page + $hs_tail_count) {
            for ($i = $total_posts_count - $hs_tail_count; $i < $total_posts_count; $i++) {
                $selected_indexes[] = $i;
            }
            for ($i = 0; $i < $posts_per_page; $i++) {
                $selected_indexes[] = $i;
            }
            $hs_left_start = $total_posts_count - $hs_tail_count;
            $hs_right_end = $posts_per_page;
        } else {
            for ($i = 0; $i < $total_posts_count; $i++) {
                $selected_indexes[] = $i;
            }
        }
    } else {
        $offset = ($paged - 1) * $posts_per_page;
        for ($i = $offset; $i < min($total_posts_count, $offset + $posts_per_page); $i++) {
            $selected_indexes[] = $i;
        }
    }

    $selected_ids = array();
    $index_by_id = array(); // post ID => position in the shuffled master list
    foreach ($selected_indexes as $idx) {
        $selected_ids[] = $master_id_list[$idx];
        $index_by_id[$master_id_list[$idx]] = $idx;
    }
    $max_pages = (int) ceil($total_posts_count / max(1, $posts_per_page));
    
    // 7. Hydrate the sliced IDs into full Post Objects
    $final_posts = array();
    if (!empty($selected_ids)) {
        $hydration_query = new WP_Query(array(
            'post__in'       => $selected_ids,
            'post_type'      => 'post',
            'posts_per_page' => count($selected_ids),
            'orderby'        => 'post__in', // Strictly preserve our woven order!
            'no_found_rows'  => true,       // Huge speed boost
        ));
        $final_posts = $hydration_query->posts;
    }
    
    // BACKFILL LOGIC REMOVED (Per User Request - Option 1)

    if (empty($final_posts)) {
        return '<p>No posts found for this section.</p>';
    }

    // Unique ID Placeholder for caching (will be replaced before output)
    $slider_id = '%%HS_SLIDER_ID_PLACEHOLDER%%';
    
    // AJAX BYPASS: If we only want the cards (from an AJAX request), skip the header and wrapper HTML
    $ajax_cards_only = isset($args['ajax_cards_only']) && $args['ajax_cards_only'] === 'true';

    // 4. Start Output Buffering
    ob_start();
    ?>
    <?php if (!$ajax_cards_only) : ?>
    <style>
        .hs-shortcode-slider-wrapper .hs-story-card { overflow: visible !important; }
        .hs-shortcode-slider-wrapper .hs-story-image { border-radius: 12px !important; overflow: hidden !important; }
        .hs-shortcode-slider-wrapper .hs-story-image::after {
            background: linear-gradient(to bottom, rgba(0,48,87,0.8) 0%, rgba(0,48,87,0) 25%), linear-gradient(to top, rgba(0,48,87,0.95) 0%, rgba(0,48,87,0) 50%) !important;
        }
        .hs-shortcode-slider-wrapper .hs-story-date { margin: -3px 0 0 0 !important; padding: 4px 0 !important; }
        .hs-shortcode-slider-wrapper .hs-story-title p { font-size: 1.3rem !important; line-height: 1.1 !important; }
        .hs-shortcode-slider-wrapper .hs-badge-location p { color: var(--hs-gold, #f0b323) !important; }
        .hs-shortcode-slider-wrapper .hs-badge-location p::before { display: none !important; }
        
        /* Kill pagination dots universally */
        .hs-shortcode-slider-wrapper .swiper-pagination { display: none !important; }
    </style>
    <div class="hs-shortcode-slider-wrapper" id="<?php echo esc_attr($slider_id); ?>" data-display="<?php echo esc_attr($display); ?>" data-dept="<?php echo esc_attr(isset($args['department']) ? $args['department'] : ''); ?>" data-campus="<?php echo esc_attr(isset($args['campus']) ? $args['campus'] : ''); ?>" data-enforce-context="<?php echo esc_attr($args['enforce_context']); ?>" data-exclude-days="<?php echo esc_attr($args['exclude_recent_days']); ?>" data-count="<?php echo esc_attr($args['posts_per_page']); ?>" data-title="<?php echo esc_attr($args['title']); ?>" data-seed="<?php echo esc_attr($seed); ?>" data-orderby="<?php echo esc_attr($args['orderby']); ?>" data-exclude-id="<?php echo intval($exclude_id); ?>">
    <?php endif; ?>
        
        <?php
        
        if (!$ajax_cards_only) {
            $show_header = $show_filters || (!empty($args['title']) && $args['title'] !== 'none');
        
        if ($show_header) {
            echo '<div class="hs-grid-header-wrap hs-header-wrap-' . esc_attr($slider_id) . '" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; width: 100%; box-sizing: border-box;">';
            
            $grid_title = !empty($args['title']) ? $args['title'] : 'Latest Stories';
            if ($grid_title !== 'none') {
                echo '<h2 style="font-family: var(--hs-font-head, \'Montserrat\', sans-serif) !important; color: #003057 !important; font-size: 1.6rem !important; font-weight: 700 !important; line-height: 1.2 !important; margin: 0 !important; padding: 5px 0 5px 12px !important; border-left: 6px solid #f0b323 !important; display: block !important; box-sizing: border-box; flex: 1 1 auto;">' . esc_html($grid_title) . '</h2>';
            }
            
            echo '<div class="hs-grid-controls-right hs-controls-' . esc_attr($slider_id) . '" style="display: flex; align-items: center; gap: 15px; margin-left: auto; flex: 0 1 auto;">';
            
            if ($show_filters) {
                $departments = get_terms(array(
                    'taxonomy'   => 'hs_department',
                    'hide_empty' => true,
                ));
                if (!is_wp_error($departments) && !empty($departments)) {
                    // Custom select styling with SVG caret
                    echo '<select class="hs-slider-grid-filter" data-slider-id="' . esc_attr($slider_id) . '" style="padding: 0 36px 0 16px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: var(--hs-font-head, \'Montserrat\', sans-serif); font-size: 0.8rem; font-weight: 700; color: #003057; background: #ffffff; outline: none; cursor: pointer; height: 38px; box-sizing: border-box; appearance: none; -webkit-appearance: none; -moz-appearance: none; background-image: url(\'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23003057%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E\'); background-repeat: no-repeat; background-position: right 16px center; background-size: 12px; margin: 0 !important;">';
                    
                    $all_selected = empty($active_filter) ? 'selected' : '';
                    echo '<option value="all" ' . $all_selected . '>All Departments</option>';
                    
                    foreach ($departments as $dept) {
                        $is_selected = ($active_filter === $dept->slug) ? 'selected' : '';
                        echo '<option value="' . esc_attr($dept->slug) . '" ' . $is_selected . '>' . esc_html($dept->name) . '</option>';
                    }
                    echo '</select>';
                }
            }
            
            // Top Arrows (always show if pages > 1 or display slider)
            // For Grid: custom AJAX pagination logic handles these arrows.
            // For Slider: we link these directly to Swiper in JS.
            if ($max_pages > 1 || $display === 'slider') {
                $arrow_classes = ($display === 'grid') ? 'hs-page-btn hs-prev' : 'hs-slider-nav hs-slider-prev-' . esc_attr($slider_id);
                $arrow_classes_next = ($display === 'grid') ? 'hs-page-btn hs-next' : 'hs-slider-nav hs-slider-next-' . esc_attr($slider_id);
                
                echo '<div class="hs-top-arrows hs-top-arrows-' . esc_attr($slider_id) . '" style="display: flex; gap: 8px;">';
                echo '<button class="' . esc_attr($arrow_classes) . '" ' . ($display === 'grid' ? 'disabled' : '') . ' style="background-color: var(--hs-gold, #f0b323); border: none; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--hs-dark, #003057); font-size: 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.15); transition: all 0.2s ease;"><i class="fas fa-chevron-left"></i></button>';
                echo '<button class="' . esc_attr($arrow_classes_next) . '" style="background-color: var(--hs-gold, #f0b323); border: none; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--hs-dark, #003057); font-size: 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.15); transition: all 0.2s ease;"><i class="fas fa-chevron-right"></i></button>';
                echo '</div>';
            }
            
            echo '</div>'; // End hs-grid-controls-right
            echo '</div>'; // Close hs-grid-header-wrap
            
            // CSS to handle mobile vs desktop arrows (Mobile keeps filter full width, hides top arrows, shows default swiper arrows. Desktop hides default swiper arrows, shows top arrows)
            if ($display === 'slider') {
                echo '<style>';
                echo '@media(max-width: 767px) { .hs-header-wrap-' . esc_attr($slider_id) . ' .hs-slider-grid-filter { width: 100% !important; margin-bottom: 15px !important; } .hs-controls-' . esc_attr($slider_id) . ' { width: 100%; justify-content: space-between; } .hs-top-arrows-' . esc_attr($slider_id) . ' { display: none !important; } }';
                echo '@media(min-width: 768px) { .hsPostSliderInit-' . esc_attr($slider_id) . ' .swiper-button-prev, .hsPostSliderInit-' . esc_attr($slider_id) . ' .swiper-button-next { display: none !important; } }';
                echo '</style>';
            }
        }
        } // End !$ajax_cards_only
        ?>
        <?php if (!$ajax_cards_only) : ?>
            <?php if ($display === 'slider') : ?>
            <div class="swiper hsPostSliderInit-<?php echo esc_attr($slider_id); ?>" data-total="<?php echo intval($total_posts_count); ?>" data-left-start="<?php echo intval($hs_left_start); ?>" data-right-end="<?php echo intval($hs_right_end); ?>">
                <div class="hs-slider-loader">Loading Posts...</div>
                <div class="swiper-wrapper">
            <?php else : ?>
            <div class="hs-post-grid swiper hsPostSliderInit-<?php echo esc_attr($slider_id); ?>" id="<?php echo esc_attr($slider_id); ?>-grid" data-dept="<?php echo esc_attr($args['department']); ?>" data-campus="<?php echo esc_attr($args['campus']); ?>" data-ppp="<?php echo esc_attr($target_count); ?>">
                <div class="hs-slider-loader">Loading Posts...</div>
                <div class="swiper-wrapper">
            <?php endif; ?>
        <?php endif; ?>
                
                <?php 
                global $post;
                foreach ($final_posts as $post) {
                    setup_postdata($post);
                    $card_html = hs_get_post_slider_card_html($post, $args['department'], ($display === 'slider'), $args['enforce_context'], $args['campus']);
                    if (isset($index_by_id[$post->ID])) {
                        // data-hs-index = position in the shuffled list (used by the lazy loader)
                        $card_html = preg_replace('/<div class="swiper-slide"/', '<div class="swiper-slide" data-hs-index="' . intval($index_by_id[$post->ID]) . '"', $card_html, 1);
                    }
                    echo $card_html;
                }
                wp_reset_postdata(); 
                ?>
        <?php if ($ajax_cards_only) {
            $cards_html = ob_get_clean();
            // In AJAX mode, we ONLY want to return the raw HTML of the slides, nothing else.
            // We append a hidden meta tag so the JS knows the true total posts for this filter.
            $cards_html .= '<div class="hs-ajax-meta" data-total="' . intval($total_posts_count) . '" data-left-start="' . intval($hs_left_start) . '" data-right-end="' . intval($hs_right_end) . '" style="display:none;"></div>';
            return $cards_html;
        } ?>

        <?php if (!$ajax_cards_only) : ?>
            <?php if ($display === 'slider') : ?>
                </div> <!-- /swiper-wrapper -->
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
                </div> <!-- /swiper -->
                <?php else : ?>
                </div> <!-- /swiper-wrapper -->
                <div class="swiper-pagination"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
                </div> <!-- /hs-post-grid swiper -->
                
                <?php if ($max_pages > 1) : ?>
                <div class="hs-pagination" id="<?php echo esc_attr($slider_id); ?>-pagination" data-current="<?php echo esc_attr($paged); ?>" data-total="<?php echo esc_attr($max_pages); ?>">
                    <!-- Pagination will be rendered here via JS -->
                </div>
                <?php endif; ?>
                
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Initialize Swiper -->
    <script>
        function init_hs_slider_%%HS_SLIDER_ID_PLACEHOLDER%%() {
            console.log('INIT START: init_hs_slider_%%HS_SLIDER_ID_PLACEHOLDER%%');
            if (typeof Swiper === 'undefined') {
                console.log('Swiper is undefined, waiting 100ms...');
                if (!document.getElementById('swiper-js-script')) {
                    var script = document.createElement('script');
                    script.id = 'swiper-js-script';
                    script.src = '/wp-content/mu-plugins/hs-swiper/swiper-bundle.min.js?ver=11.2.10';
                    document.head.appendChild(script);
                }
                // Try again in 100ms
                setTimeout(init_hs_slider_%%HS_SLIDER_ID_PLACEHOLDER%%, 100);
                return;
            }
            
            var container = document.querySelector('.hsPostSliderInit-%%HS_SLIDER_ID_PLACEHOLDER%%');
            var wrapper = container ? container.querySelector('.swiper-wrapper') : null;
            var filterSelect = document.querySelector('select[data-slider-id="%%HS_SLIDER_ID_PLACEHOLDER%%"]');
            var rootContainer = document.getElementById('%%HS_SLIDER_ID_PLACEHOLDER%%');
            
            if (container && !container.classList.contains('hs-swiper-loaded')) {
                console.log('Container found, Swiper initializing...');
                // Determine if we have enough slides to safely loop
                var slideCount = container.querySelectorAll('.swiper-slide:not(.swiper-slide-duplicate)').length;
                console.log('Slide count:', slideCount);
                var shouldLoop = slideCount > 3; // 4 or more slides = loop safely
                
                // Bulletproof failsafe: reveal posts after 1s even if Swiper fails
                setTimeout(function() { 
                    console.log('Failsafe triggered');
                    container.classList.add('hs-swiper-loaded');
                    var loader = container.querySelector('.hs-slider-loader');
                    if (loader) loader.style.display = 'none'; 
                }, 1000);
                
                // Expose Swiper instance on the container so we can destroy it later if needed
                    setTimeout(function() {
                        try {
                            // ---- Lazy-load state ----
                            // The server renders a slice of the shuffled post list: the last few cards (so there is
                            // always a card to the left) plus the first batch. Loaded = [0, rightEnd) + [leftStart, totalPosts).
                            // When the two ends meet, every card is in the DOM and the slider switches to a true loop.
                            var isSlider = <?php echo ($display === 'slider') ? 'true' : 'false'; ?>;
                            var totalPosts = parseInt(container.getAttribute('data-total'), 10) || 0;
                            var leftStart = parseInt(container.getAttribute('data-left-start'), 10);
                            var rightEnd = parseInt(container.getAttribute('data-right-end'), 10);
                            var meta = wrapper ? wrapper.querySelector('.hs-ajax-meta') : null;
                            if (meta) {
                                // Filter dropdown reload: the AJAX response carries its own state
                                totalPosts = parseInt(meta.getAttribute('data-total'), 10) || 0;
                                leftStart = parseInt(meta.getAttribute('data-left-start'), 10);
                                rightEnd = parseInt(meta.getAttribute('data-right-end'), 10);
                                meta.parentNode.removeChild(meta);
                            }
                            if (isNaN(leftStart)) { leftStart = totalPosts; }
                            if (isNaN(rightEnd)) { rightEnd = totalPosts; }
                            var HS_BATCH = 12; // cards per lazy request
                            var HS_EDGE = 4;   // start loading when this close to an edge
                            var hsAllLoaded = !(leftStart > rightEnd);
                            var hsLoading = false;
                            var hsSilent = false; // ignore slideChange caused by our own DOM updates
                            var hsLeftCount = Math.max(0, totalPosts - leftStart); // cards rendered before card 0
                            var hs_ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                            var swiper = new Swiper(container, {
                                slidesPerView: 'auto',
                                slidesPerGroup: 1,
                                spaceBetween: 10,
                                speed: 300,
                                preventInteractionOnTransition: true, // a click mid-transition is ignored instead of stacking
                                loop: false,   // switched on by hsEnableLoop() once every card is loaded
                                rewind: false, // rewind jumps from the first card to the last (the old zap)
                                initialSlide: hsLeftCount,
                                // stopOnLastSlide: never let autoplay rewind to card 1 if it ever reaches the end
                                autoplay: isSlider ? { delay: 3500, disableOnInteraction: true, pauseOnMouseEnter: true, stopOnLastSlide: true } : false,
                                pagination: false,
                                breakpoints: {
                                    320: { slidesPerView: 'auto', slidesPerGroup: 1, centeredSlides: true },
                                    768: { slidesPerView: 'auto', slidesPerGroup: 1, centeredSlides: false, enabled: isSlider },
                                    1024: { slidesPerView: 'auto', slidesPerGroup: 1, centeredSlides: false, enabled: isSlider },
                                    1400: { slidesPerView: 'auto', slidesPerGroup: 1, centeredSlides: false, enabled: isSlider }
                                },
                                on: {
                                    init: function () {
                                        container.classList.add('hs-swiper-loaded');
                                        var loader = container.querySelector('.hs-slider-loader');
                                        if (loader) loader.style.display = 'none';
                                    }
                                }
                            });
                            container.hsSwiperInstance = swiper;
                            function hsVisibleCount() {
                                var cr = container.getBoundingClientRect();
                                var n = 0;
                                for (var i = 0; swiper.slides.length > i; i++) {
                                    var r = swiper.slides[i].getBoundingClientRect();
                                    if (r.right > cr.left + 2) { if (cr.right - 2 > r.left) { n++; } }
                                }
                                return n;
                            }
                            function hsWhenIdle(cb) {
                                // Re-check after every transitionEnd: a queued arrow press may start a new slide on the same event
                                if (!swiper.animating) { cb(); return; }
                                swiper.once('transitionEnd', function () { hsWhenIdle(cb); });
                            }
                            function hsFetchRange(offset, limit, cb) {
                                if (limit > 0) {
                                    var fs = document.querySelector('select[data-slider-id="%%HS_SLIDER_ID_PLACEHOLDER%%"]');
                                    var data = new URLSearchParams();
                                    data.append('action', 'hs_load_slider_posts');
                                    data.append('offset', offset);
                                    data.append('limit', limit);
                                    data.append('department', fs ? fs.value : (rootContainer.getAttribute('data-dept') || ''));
                                    data.append('campus', rootContainer.getAttribute('data-campus') || '');
                                    data.append('enforce_context', rootContainer.getAttribute('data-enforce-context') || 'false');
                                    data.append('exclude_recent_days', rootContainer.getAttribute('data-exclude-days') || '0');
                                    data.append('seed', rootContainer.getAttribute('data-seed') || '');
                                    data.append('orderby', rootContainer.getAttribute('data-orderby') || '');
                                    data.append('exclude_id', rootContainer.getAttribute('data-exclude-id') || '');
                                    data.append('ajax_cards_only', 'true');
                                    data.append('display', 'slider');
                                    fetch(hs_ajaxurl, { method: 'POST', body: data })
                                    .then(function (res) { return res.json(); })
                                    .then(function (res) {
                                        var nodes = [];
                                        if (res.success) {
                                            var tmp = document.createElement('div');
                                            tmp.innerHTML = res.data.html;
                                            var found = tmp.querySelectorAll('.swiper-slide[data-hs-index]');
                                            for (var i = 0; found.length > i; i++) { nodes.push(found[i]); }
                                        }
                                        cb(nodes);
                                    })
                                    .catch(function (err) { console.error('Slider lazy load error:', err); cb(null); });
                                } else {
                                    cb([]);
                                }
                            }
                            function hsEnableLoop() {
                                hsAllLoaded = true;
                                var n = swiper.slides.length;
                                if (isSlider) {
                                    if (n >= Math.max(6, hsVisibleCount() + 2)) {
                                        var activeEl = swiper.slides[swiper.activeIndex];
                                        hsSilent = true;
                                        swiper.params.loop = true;
                                        swiper.loopCreate();
                                        swiper.update();
                                        swiper.slideToLoop(parseInt(activeEl.getAttribute('data-swiper-slide-index'), 10), 0, false, true); // internal: keeps autoplay running
                                        hsSilent = false;
                                        return;
                                    }
                                }
                                // Too few cards to loop: on mobile start on card 2 so a card fills the left edge
                                if (swiper.params.centeredSlides) {
                                    // slideTo(..., internal = true) so autoplay keeps running
                                    if (n > 1) { if (swiper.activeIndex === 0) { swiper.slideTo(1, 0, false, true); } }
                                }
                            }
                            function hsAfterLoad(dir) {
                                if (leftStart > rightEnd) { hsCheckEdges(dir); } else { hsEnableLoop(); }
                            }
                            function hsLoadLeft() {
                                hsLoading = true;
                                var from = Math.max(leftStart - HS_BATCH, rightEnd);
                                hsFetchRange(from, leftStart - from, function (nodes) {
                                    if (nodes === null) { hsLoading = false; return; } // network error: retry on next move
                                    hsWhenIdle(function () {
                                        hsSilent = true;
                                        // prependSlide inserts each node at the front, so pass them in reverse order
                                        if (nodes.length) { swiper.prependSlide(nodes.slice().reverse()); }
                                        hsSilent = false;
                                        leftStart = from;
                                        hsLoading = false;
                                        hsAfterLoad('left');
                                    });
                                });
                            }
                            function hsLoadRight() {
                                hsLoading = true;
                                var to = Math.min(rightEnd + HS_BATCH, leftStart);
                                hsFetchRange(rightEnd, to - rightEnd, function (nodes) {
                                    if (nodes === null) { hsLoading = false; return; } // network error: retry on next move
                                    hsWhenIdle(function () {
                                        hsSilent = true;
                                        if (nodes.length) { swiper.appendSlide(nodes); }
                                        hsSilent = false;
                                        rightEnd = to;
                                        hsLoading = false;
                                        hsAfterLoad('right');
                                    });
                                });
                            }
                            function hsCheckEdges(dir) {
                                if (hsAllLoaded || hsLoading || !isSlider) { return; }
                                if (dir === 'left') {
                                    if (HS_EDGE >= swiper.activeIndex) { hsLoadLeft(); }
                                } else {
                                    if (swiper.activeIndex + hsVisibleCount() + HS_EDGE >= swiper.slides.length) { hsLoadRight(); }
                                }
                            }
                            swiper.on('slideChange', function () {
                                if (hsSilent) { return; }
                                hsCheckEdges(swiper.activeIndex > swiper.previousIndex ? 'right' : 'left');
                            });
                            if (hsAllLoaded) { hsEnableLoop(); }
                console.log('Swiper instantiation complete.');
                } catch (e) {
                    console.error('Swiper instantiation error:', e);
                }
                
                // Attach AJAX listener to the dropdown filter
                if (filterSelect && rootContainer && !filterSelect.dataset.listenerAttached) {
                    console.log('Attaching AJAX listener to filterSelect');
                    filterSelect.dataset.listenerAttached = 'true';
                    filterSelect.addEventListener('change', function(e) {
                        console.log('Filter changed to:', this.value);
                        var dept = this.value;
                        var campus = rootContainer.getAttribute('data-campus') || '';
                        var enforceContext = rootContainer.getAttribute('data-enforce-context') || 'false';
                        var excludeDays = rootContainer.getAttribute('data-exclude-days') || '0';
                        var count = rootContainer.getAttribute('data-count') || '-1';
                        var title = rootContainer.getAttribute('data-title') || '';
                        var seed = rootContainer.getAttribute('data-seed') || '';
                        
                        var loader = container.querySelector('.hs-slider-loader');
                        if (loader) loader.style.display = 'flex';
                        container.classList.remove('hs-swiper-loaded');
                        
                        var data = new URLSearchParams();
                        data.append('action', 'hs_load_slider_posts');
                        data.append('department', dept);
                        data.append('campus', campus);
                        data.append('enforce_context', enforceContext);
                        data.append('exclude_recent_days', excludeDays);
                        data.append('count', count);
                        data.append('title', title);
                        data.append('seed', seed);
                        data.append('orderby', rootContainer.getAttribute('data-orderby') || '');
                        data.append('exclude_id', rootContainer.getAttribute('data-exclude-id') || '');
                        data.append('ajax_cards_only', 'true');
                        data.append('display', '<?php echo esc_js($display); ?>');
                        
                        var hs_ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                        console.log('Sending AJAX request to:', hs_ajaxurl, Object.fromEntries(data.entries()));

                        fetch(hs_ajaxurl, {
                            method: 'POST',
                            body: data
                        }).then(function(res) { 
                            console.log('AJAX response status:', res.status);
                            if (!res.ok) { throw new Error('Network response was not ok'); }
                            return res.json(); 
                        })
                        .then(function(res) {
                            console.log('AJAX JSON response:', res);
                            if (res.success && wrapper) {
                                // Destroy current instance
                                if (container.hsSwiperInstance) {
                                    container.hsSwiperInstance.destroy(true, true);
                                    container.hsSwiperInstance = null;
                                }
                                
                                // Clean up Swiper generated classes/attributes
                                var isGrid = ('<?php echo esc_js($display); ?>' === 'grid');
                                container.className = (isGrid ? 'hs-post-grid swiper ' : 'swiper ') + 'hsPostSliderInit-%%HS_SLIDER_ID_PLACEHOLDER%%';
                                wrapper.className = 'swiper-wrapper';
                                wrapper.removeAttribute('style');
                                
                                // Inject new slides
                                wrapper.innerHTML = res.data.html;
                                console.log('Re-initializing Swiper in 50ms...');
                                // Re-initialize Swiper
                                setTimeout(init_hs_slider_%%HS_SLIDER_ID_PLACEHOLDER%%, 50);
                            } else {
                                console.error('AJAX request failed or wrapper not found. res:', res);
                                if (loader) loader.style.display = 'none';
                                container.classList.add('hs-swiper-loaded');
                            }
                        }).catch(function(err) {
                            console.error('AJAX Fetch Catch Error:', err);
                            if (loader) loader.style.display = 'none';
                            container.classList.add('hs-swiper-loaded');
                        });
                    });
                }
                
                // Immediate fallback for grid mode where Swiper might disable itself
                if (container.hsSwiperInstance && !container.hsSwiperInstance.enabled) {
                    container.classList.add('hs-swiper-loaded');
                    var loader = container.querySelector('.hs-slider-loader');
                    if (loader) loader.style.display = 'none';
                }
                }, 500); // 500ms forced loader delay
            }
        }

        // 1. Standard Frontend Load (Handles if DOM is already ready)
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(init_hs_slider_%%HS_SLIDER_ID_PLACEHOLDER%%, 50);
        } else {
            document.addEventListener('DOMContentLoaded', init_hs_slider_%%HS_SLIDER_ID_PLACEHOLDER%%);
        }

        // 2. Divi Visual Builder AJAX Support
        if (typeof jQuery !== 'undefined' && typeof jQuery(document).ajaxComplete === 'function') {
            jQuery(document).ajaxComplete(function(event, xhr, settings) {
                if (settings.data && typeof settings.data === 'string' && settings.data.indexOf('et_pb_get_shortcode_preview') !== -1) {
                    setTimeout(init_hs_slider_%%HS_SLIDER_ID_PLACEHOLDER%%, 500);
                }
            });
        }
    </script>
    
    <?php

    $html = ob_get_clean();
    
    if (empty($active_filter)) {
       set_transient($html_transient_key, $html, 5 * MINUTE_IN_SECONDS);
    }
    
    // Replace placeholder for this specific render with a safe, hyphen-free ID
    $slider_instance_id = 'hspostslider' . uniqid();
    return str_replace('%%HS_SLIDER_ID_PLACEHOLDER%%', $slider_instance_id, $html);
}

/**
 * Helper Function: Render Single Story Card HTML
 */
function hs_get_post_slider_card_html($post, $shortcode_dept_slug = '', $is_slider = false, $enforce_context = 'false', $campus = '') {
    $post_id = $post->ID;
    $permalink = get_permalink($post_id);
    if ($enforce_context === 'true' && !empty($campus)) {
        $permalink = add_query_arg('hs_campus', urlencode($campus), $permalink);
    }
    $title = get_the_title($post_id);
    $date = get_the_date('jS F Y', $post_id);
    $portrait_image = get_field('hs_portrait_image', $post_id);
    if (!empty($portrait_image)) {
        $image_url = $portrait_image; // URL format
    } else {
        $image_url = get_the_post_thumbnail_url($post_id, 'large');
    }
    
    if (!$image_url) {
        $image_url = '/wp-content/uploads/placeholder.jpg'; 
    }

    // Taxonomy: Department (Context-Aware for Multi-Select)
    $dept_terms = get_the_terms($post_id, 'hs_department');
    // 2. Department Name (Context-Aware)
    // If the post has a Thematic Chip override, we ALWAYS use that for the text!
    // Otherwise, if we are on a specific department page, show that department's name.
    // Otherwise, fall back to the first department term (or combined logic).
    $dept_name = 'News';
    $dept_color_class = 'hs-dept-news';
    $matched_term = null;
    
    if ($dept_terms && !is_wp_error($dept_terms) && !empty($dept_terms)) {
        // First, determine what the base routing/color logic dictates
        if ($shortcode_dept_slug) {
            $shortcode_slugs = array_map(function($slug) {
                return sanitize_title(trim($slug));
            }, explode(',', $shortcode_dept_slug));
            foreach ($dept_terms as $term) {
                if (in_array($term->slug, $shortcode_slugs)) {
                    $matched_term = $term;
                    break;
                }
            }
        }
        
        if ($matched_term) {
            $dept_name = esc_html($matched_term->name);
            $dept_color_class = 'hs-dept-' . $matched_term->slug;
        } else {
            $dept_name = esc_html($dept_terms[0]->name);
            $dept_color_class = 'hs-dept-' . $dept_terms[0]->slug;
        }
    }

    // CHECK THEMATIC OVERRIDE ("Yellow Badge Override") - Move outside dept block
    $thematic_chips = get_the_terms($post_id, 'hs_thematic_chip');
    if ($thematic_chips && !is_wp_error($thematic_chips) && !empty($thematic_chips)) {
        $dept_name = esc_html($thematic_chips[0]->name);
    }

    // Taxonomy: Campus
    $campus_terms = get_the_terms($post_id, 'hs_post_campus');
    $campus_name = '';
    $location_override = get_field('hs_location_override', $post_id);

    if (!empty($location_override)) {
        $campus_name = esc_html($location_override);
    } else if ($campus_terms && !is_wp_error($campus_terms)) {
        if (count($campus_terms) > 1) {
            $campus_name = 'Both Campuses';
        } else {
            $campus_name = esc_html($campus_terms[0]->name);
        }
    }

    // Taxonomy: Tags
    $tag_terms = get_the_terms($post_id, 'post_tag');
    $cleaned_tags = array();
    if ($tag_terms && !is_wp_error($tag_terms)) {
        foreach ($tag_terms as $t) {
            $name_parts = explode(':', $t->name);
            $cleaned_tags[] = trim(end($name_parts));
        }
    }

    ob_start();
    ?>
    <div class="swiper-slide">
        <a href="<?php echo esc_url($permalink); ?>" class="hs-story-card" style="justify-content: space-between !important; padding: 15px 0 20px 0 !important; text-decoration: none;">
            <div class="hs-story-image">
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" decoding="async">
            </div>
            
            <?php if ($dept_name) : ?>
                <div class="hs-badge-primary" style="position: absolute !important; margin: 0 !important; top: 0 !important; left: 50% !important; transform: translate(-50%, -50%) !important; right: auto !important; z-index: 30;">
                    <div class="hs-badge-primary-inner"><p><?php echo $dept_name; ?></p></div>
                </div>
            <?php endif; ?>
            
            <!-- TOP BADGE GROUP -->
            <div class="hs-story-top-group" style="position: relative; z-index: 20; display: flex; flex-direction: column; align-items: center; gap: 0; width: 100%; padding-top: 5px;">
                
                <?php if ($campus_name) : ?>
                    <div class="hs-badge-location" style="position: static !important; margin: 0 !important; top: auto !important; left: auto !important; right: auto !important;"><p style="display:flex; align-items:center;"><?php echo $campus_name; ?></p></div>
                <?php endif; ?>

                <div class="hs-story-date" style="position: static !important; margin: 0 !important; top: auto !important; right: auto !important; display: flex; justify-content: center; width: 100%; background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(4px); padding: 4px 0;"><p style="color: white !important; text-shadow: 0 1px 4px rgba(0,0,0,0.8) !important; margin: 0 !important; font-weight: 700 !important;"><?php echo esc_html($date); ?></p></div>
            </div>
            
            <!-- BOTTOM TITLE & TAGS GROUP -->
            <div class="hs-story-bottom-group" style="position: relative; z-index: 20; display: flex; flex-direction: column; align-items: center; width: 100%;">
                <div class="hs-story-title" style="position: static !important; margin: 0 0 12px 0 !important; padding: 0 25px !important;"><p><?php echo esc_html($title); ?></p></div>
                
                <?php if (!empty($cleaned_tags)) : ?>
                    <div class="hs-story-tags" style="position: static !important; margin: 0 !important; bottom: auto !important; padding: 0 15px !important;">
                        <p>
                            <?php foreach ($cleaned_tags as $tag_name) : ?>
                                <span><?php echo esc_html($tag_name); ?></span>
                            <?php endforeach; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </a>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * AJAX Handler for Post Slider
 */
add_action('wp_ajax_hs_load_slider_posts', 'hs_ajax_load_slider_posts');
add_action('wp_ajax_nopriv_hs_load_slider_posts', 'hs_ajax_load_slider_posts');
function hs_ajax_load_slider_posts() {
    $dept = isset($_POST['department']) ? sanitize_text_field($_POST['department']) : 'all';
    
    // Construct the args array matching the shortcode parameters
    $args = array(
        'posts_per_page'      => isset($_POST['count']) ? intval($_POST['count']) : -1,
        'department'          => ($dept === 'all') ? '' : $dept,
        'campus'              => isset($_POST['campus']) ? sanitize_text_field($_POST['campus']) : '',
        'display'             => isset($_POST['display']) ? sanitize_text_field($_POST['display']) : 'slider',
        'enforce_context'     => isset($_POST['enforce_context']) ? sanitize_text_field($_POST['enforce_context']) : 'false',
        'exclude_recent_days' => isset($_POST['exclude_recent_days']) ? sanitize_text_field($_POST['exclude_recent_days']) : '0',
        'ajax_cards_only'     => 'true' // Trigger the bypass to only return the raw slides
    );
    if (!empty($_POST['orderby'])) {
        $args['orderby'] = sanitize_key($_POST['orderby']);
    }

    // Render the shortcode using our bypass to get JUST the cards HTML
    $cards_html = hs_render_post_slider_shortcode($args);

    if (empty(trim($cards_html)) || strpos($cards_html, 'No posts found') !== false) {
        wp_send_json_success(array('html' => '<div class="swiper-slide"><p style="padding: 20px;">No posts found for this filter.</p></div>'));
    } else {
        wp_send_json_success(array('html' => $cards_html));
    }
}

/**
 * Auto-flush Slider Transients on Post Save
 * Instantly clears the cache across the site whenever a post is created or updated.
 */
add_action('save_post', 'hs_flush_slider_transients', 10, 3);
function hs_flush_slider_transients($post_id, $post, $update) {
    // Only flush if it's an actual post update, ignore revisions and auto-drafts
    if (wp_is_post_revision($post_id) || $post->post_status === 'auto-draft') {
        return;
    }
    
    global $wpdb;
    
    // Delete all transients (and their timeouts) that start with our unique prefix
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_hs\_sl%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_hs\_sl%'");
}
