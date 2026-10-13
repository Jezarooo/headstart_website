<?php

/**
 * Noticeboard--Shortcode-Engine
 */
/**
 * HeadStart Noticeboard - WordPress Integration
 * 
 * This file contains:
 * 1. Custom Post Type Registration (hs_notice)
 * 2. ACF Field Group Registration (via PHP)
 * 3. Shortcode Registration ([hs_noticeboard])
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   1. REGISTER CUSTOM POST TYPE
   ========================================================================== */
function hs_register_noticeboard_cpt() {
    $labels = array(
        'name'                  => _x( 'Notices', 'Post Type General Name', 'headstart' ),
        'singular_name'         => _x( 'Notice', 'Post Type Singular Name', 'headstart' ),
        'menu_name'             => __( 'Noticeboard', 'headstart' ),
        'name_admin_bar'        => __( 'Notice', 'headstart' ),
        'archives'              => __( 'Notice Archives', 'headstart' ),
        'attributes'            => __( 'Notice Attributes', 'headstart' ),
        'parent_item_colon'     => __( 'Parent Notice:', 'headstart' ),
        'all_items'             => __( 'All Notices', 'headstart' ),
        'add_new_item'          => __( 'Add New Notice', 'headstart' ),
        'add_new'               => __( 'Add New', 'headstart' ),
        'new_item'              => __( 'New Notice', 'headstart' ),
        'edit_item'             => __( 'Edit Notice', 'headstart' ),
        'update_item'           => __( 'Update Notice', 'headstart' ),
        'view_item'             => __( 'View Notice', 'headstart' ),
        'view_items'            => __( 'View Notices', 'headstart' ),
        'search_items'          => __( 'Search Notice', 'headstart' ),
        'not_found'             => __( 'Not found', 'headstart' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'headstart' ),
        'featured_image'        => __( 'Notice Image', 'headstart' ),
        'set_featured_image'    => __( 'Set notice image', 'headstart' ),
        'remove_featured_image' => __( 'Remove notice image', 'headstart' ),
        'use_featured_image'    => __( 'Use as notice image', 'headstart' ),
        'insert_into_item'      => __( 'Insert into notice', 'headstart' ),
        'uploaded_to_this_item' => __( 'Uploaded to this notice', 'headstart' ),
        'items_list'            => __( 'Notices list', 'headstart' ),
        'items_list_navigation' => __( 'Notices list navigation', 'headstart' ),
        'filter_items_list'     => __( 'Filter notices list', 'headstart' ),
    );
    $args = array(
        'label'                 => __( 'Notice', 'headstart' ),
        'description'           => __( 'Noticeboard Announcements', 'headstart' ),
        'labels'                => $labels,
        'supports'              => array( 'title' ), // Standard editor and thumbnail removed, handled via ACF
        'hierarchical'          => false,
        'public'                => false, // Set to false as these are only displayed via shortcode
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 25,
        'menu_icon'             => 'dashicons-megaphone',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'post',
        'show_in_rest'          => false, // No Gutenberg needed since we use ACF for all content
    );
    register_post_type( 'hs_notice', $args );
}
add_action( 'init', 'hs_register_noticeboard_cpt', 0 );


/* ==========================================================================
   2. REGISTER ACF FIELDS (DIRECTLY IN PHP)
   ========================================================================== */
if( function_exists('acf_add_local_field_group') ):

    acf_add_local_field_group(array(
        'key' => 'group_hs_noticeboard_fields',
        'title' => 'Notice Content & Details',
        'fields' => array(
            // Notice Body (Replaces standard editor)
            array(
                'key' => 'field_hs_notice_body',
                'label' => 'Notice Content',
                'name' => 'notice_body',
                'type' => 'wysiwyg',
                'instructions' => 'The full content of the notice that appears in the pop-up modal.',
                'required' => 1,
                'media_upload' => 0,
                'toolbar' => 'full',
            ),
            // Notice Image (Replaces Featured Image)
            array(
                'key' => 'field_hs_notice_image',
                'label' => 'Notice Image',
                'name' => 'notice_image',
                'type' => 'image',
                'instructions' => 'An optional image to display inside the pop-up modal.',
                'required' => 0,
                'return_format' => 'url',
                'preview_size' => 'medium',
                'library' => 'all',
            ),
            // Target Campus
            array(
                'key' => 'field_hs_notice_campus',
                'label' => 'Target Campus',
                'name' => 'notice_campus',
                'type' => 'select',
                'instructions' => 'Which campus should this notice appear on?',
                'required' => 1,
                'choices' => array(
                    'both' => 'Both Campuses',
                    'chaofah' => 'Chaofah',
                    'cherngtalay' => 'Cherngtalay',
                ),
                'default_value' => 'both',
                'return_format' => 'value',
            ),

            // Expiry Date
            array(
                'key' => 'field_hs_notice_expiry',
                'label' => 'Expiry Date',
                'name' => 'expiry_date',
                'type' => 'date_picker',
                'instructions' => 'The date this notice should automatically disappear from the board. Leave blank to show indefinitely.',
                'required' => 0,
                'display_format' => 'd M Y',
                'return_format' => 'Ymd', // Used for easy numerical comparison in WP_Query
                'first_day' => 1,
            ),
            // Mark as Important
            array(
                'key' => 'field_hs_notice_important',
                'label' => 'Mark as Important',
                'name' => 'is_important',
                'type' => 'true_false',
                'instructions' => 'Adds the yellow "Important" tag to this notice. (Note: Pinned notices cannot also be marked as Important).',
                'message' => 'Yes, mark this notice as important',
                'default_value' => 0,
                'ui' => 1,
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_hs_notice_pinned',
                            'operator' => '==',
                            'value' => '0',
                        ),
                    ),
                ),
            ),
            // Sort Order
            array(
                'key' => 'field_hs_notice_order',
                'label' => 'Sort Order',
                'name' => 'notice_order',
                'type' => 'number',
                'instructions' => 'Set the display order (e.g. 1, 2, 3). Lower numbers appear first. Leave blank to appear at the bottom.',
                'required' => 0,
                'default_value' => 999,
            ),
            // Pin to Top
            array(
                'key' => 'field_hs_notice_pinned',
                'label' => 'Pin to Top',
                'name' => 'is_pinned',
                'type' => 'select',
                'instructions' => 'Replaces the main featured spot at the top of the Noticeboard. You can pin a notice to a specific campus even if the notice itself targets both campuses.',
                'choices' => array(
                    '0' => 'Not Pinned',
                    'both' => 'Pin on Both Campuses',
                    'chaofah' => 'Pin on Chaofah Only',
                    'cherngtalay' => 'Pin on Cherngtalay Only',
                ),
                'default_value' => '0',
                'return_format' => 'value',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'hs_notice',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal', // Moved under the title instead of the sidebar
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
        'show_in_rest' => 0,
    ));
    
endif;


/* ==========================================================================
   3. SHORTCODE REGISTRATION & RENDER LOGIC
   ========================================================================== */
function hs_noticeboard_shortcode( $atts ) {
    // 1. Return Placeholder if inside Divi Visual Builder
    $is_divi_builder = (isset($_GET['et_fb']) && $_GET['et_fb'] == '1') || (function_exists('is_et_pb_preview') && is_et_pb_preview());
    if ($is_divi_builder) {
        return '<div style="padding: 30px 20px; background: #f4f7f6; border: 2px dashed #003057; border-radius: 12px; text-align: center; font-family: \'Montserrat\', sans-serif; color: #003057;">
            <svg viewBox="0 0 24 24" style="width: 36px; height: 36px; fill: currentColor; margin-bottom: 10px;">
                <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
            </svg>
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700;">Noticeboard Component</h3>
            <p style="margin: 5px 0 0; font-size: 0.85rem; color: #555;">[ Frontend Render Only ]</p>
        </div>';
    }

    $atts = shortcode_atts( array(
        'campus' => '', // Default to empty so we can detect if it was passed
    ), $atts, 'hs_noticeboard' );

    $campus_val = strtolower(sanitize_text_field($atts['campus']));
    
    // Fallback: If WordPress failed to parse the attribute due to smart quotes (e.g. campus=”cherngtalay”),
    // it will be passed as a positional array value instead of a key. Let's aggressively search all $atts.
    if ( empty($campus_val) && is_array($atts) ) {
        foreach ( $atts as $val ) {
            if ( is_string($val) ) {
                $val_lower = strtolower($val);
                if ( strpos($val_lower, 'cherng') !== false ) {
                    $campus_val = 'cherngtalay';
                    break;
                } elseif ( strpos($val_lower, 'chaofah') !== false ) {
                    $campus_val = 'chaofah';
                    break;
                }
            }
        }
    }

    // If still empty, try to get from ACF field on the page
    if ( empty($campus_val) ) {
        // Use get_queried_object_id() to reliably get the page ID even inside Divi Theme Builder templates
        $current_page_id = get_queried_object_id();
        $page_campus = get_field('campus', $current_page_id);
        
        if ( $page_campus ) {
            $campus_val = strtolower($page_campus);
        } else {
            // Default to chaofah if nothing is found to prevent JS crash
            $campus_val = 'chaofah';
        }
    }

    // Normalize values (Strictly chaofah or cherngtalay to prevent JS crashing on 'all')
    // We check for 'cherng' to catch common misspellings like 'cherngataly'
    if ( strpos($campus_val, 'cherng') !== false ) {
        $campus_val = 'cherngtalay';
    } else {
        $campus_val = 'chaofah';
    }
    
    $atts['campus'] = $campus_val;

    $meta_query = array( 'relation' => 'AND' );
    
    // 1. Expiry Date Logic (Must be empty OR >= today)
    $today = date('Ymd');
    $meta_query[] = array(
        'relation' => 'OR',
        array(
            'key'     => 'expiry_date',
            'compare' => 'NOT EXISTS'
        ),
        array(
            'key'     => 'expiry_date',
            'value'   => '',
            'compare' => '='
        ),
        array(
            'key'     => 'expiry_date',
            'value'   => $today,
            'type'    => 'NUMERIC',
            'compare' => '>='
        )
    );

    // 2. Filter by campus if specified
    if ( $atts['campus'] !== 'all' ) {
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => 'notice_campus',
                'value'   => sanitize_text_field( $atts['campus'] ),
                'compare' => '='
            ),
            array(
                'key'     => 'notice_campus',
                'value'   => 'both',
                'compare' => '='
            ),
            array(
                'key'     => 'notice_campus',
                'compare' => 'NOT EXISTS'
            )
        );
    }

    $args = array(
        'post_type'      => 'hs_notice',
        'posts_per_page' => 50, // Reasonable limit
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => $meta_query
    );
    
    $notices_query = new WP_Query( $args );
    
    // 3. Early Return if NO active notices found
    if ( ! $notices_query->have_posts() ) {
        return ''; // Completely hide component
    }
    
    $notice_data = array();
    $standard_notice_ids = array();
    $pinned_notice_id = null;
    
    while ( $notices_query->have_posts() ) {
        $notices_query->the_post();
        
        $post_id = get_the_ID();
        $notice_key = 'n' . $post_id;
        
        // ACF Fields
        $is_pinned    = get_field('is_pinned');
        $is_important = get_field('is_important');
        $body_content = get_field('notice_body');
        $preview      = get_field('notice_preview');
        $image_url    = get_field('notice_image'); // Returns URL
        $notice_order = get_field('notice_order');
        
        $notice_obj = array(
            'title'       => get_the_title(),
            'image'       => $image_url ? $image_url : null,
            'isImportant' => $is_important ? true : false,
            'body'        => apply_filters('the_content', $body_content),
            'order'       => ($notice_order !== '' && $notice_order !== null) ? (int)$notice_order : 999,
            'timestamp'   => get_the_time('U')
        );
        
        // Sort into Pinned vs Standard
        // If multiple are pinned, we take the newest one (first encountered)
        $is_pinned_for_this_campus = false;
        
        if ( $is_pinned === '1' || $is_pinned === true || $is_pinned === 'both' ) {
            $is_pinned_for_this_campus = true;
        } elseif ( $atts['campus'] !== 'all' && $is_pinned === sanitize_text_field( $atts['campus'] ) ) {
            $is_pinned_for_this_campus = true;
        }

        if ( $is_pinned_for_this_campus && $pinned_notice_id === null ) {
            $notice_data['pinned'] = $notice_obj;
            $pinned_notice_id = 'pinned';
        } else {
            $notice_data[$notice_key] = $notice_obj;
            $standard_notice_ids[] = $notice_key;
        }
    }
    
    // 4. Sort standard notices: Order ASC, then Timestamp DESC
    usort($standard_notice_ids, function($a, $b) use ($notice_data) {
        $order_a = $notice_data[$a]['order'];
        $order_b = $notice_data[$b]['order'];
        
        if ($order_a === $order_b) {
            return $notice_data[$b]['timestamp'] - $notice_data[$a]['timestamp'];
        }
        return $order_a - $order_b;
    });

    wp_reset_postdata();
    
    // Safety check: if ONLY pinned notice exists and it somehow failed, or no standard notices.
    // If there is literally zero data populated, return early.
    if ( empty($notice_data) ) {
        return '';
    }
    
    // Output buffering for the shortcode HTML
    ob_start();
    ?>
    
    <!-- DEBUG_INFO: atts=<?php echo htmlspecialchars(json_encode($atts)); ?>, page_id=<?php echo get_queried_object_id(); ?>, acf_campus=<?php echo esc_attr(get_field('campus', get_queried_object_id())); ?> -->

    <!-- Required CDNs (If not globally loaded) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <div id="hs-noticeboard-wrapper" class="hs-noticeboard-wrapper" data-campus="<?php echo esc_attr($atts['campus']); ?>">
        <!-- CHIPS -->
        <div class="hs-noticeboard-chips">
            <div id="hs-campus-chip">
                <?php echo esc_html(strtoupper($atts['campus'])); ?>
            </div>
            <button id="hs-campus-toggle" onclick="window.hsToggleCampus()">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <div class="hs-weekly-update-list-sidebar" style="position: relative;">

            <!-- PINNED HIGHLIGHT -->
            <div id="hs-pinned-notice-container">
                <!-- JS Injects Pinned Notice Here -->
            </div>
            
            <!-- OLDER NOTICES LIST -->
            <div class="hs-older-notices-container" style="margin-top: 5px;">
                <div id="hs-noticeboard-loading" style="padding: 20px; text-align: center; color: #003057;">
                    <i class="fas fa-circle-notch fa-spin"></i> Loading notices...
                </div>
                <ul id="hs-older-notices-list" class="m-0 p-0 block hidden" style="display: none;">
                    <!-- JS Injected List -->
                </ul>

                <div id="hs-notice-pagination" class="hs-notice-pagination hidden" style="display: none;">
                    <!-- Pagination renders here dynamically -->
                </div>
            </div>
        </div>

        <!-- SHARED NOTICE DETAIL MODAL -->
        <div id="hs-notice-modal" class="hs-noticeboard-modal-scope hidden" style="display: none;">
            <div class="hs-modal-backdrop" onclick="window.closeNoticeModal()"></div>
            
            <div class="hs-modal-content" id="hs-modal-container">
                <button onclick="window.closeNoticeModal()" class="hs-modal-close-btn">
                    <i class="fas fa-times"></i>
                </button>

                <div class="hs-modal-inner">
                    <div id="hs-modal-img-container" class="hs-modal-img-wrapper hidden">
                        <img id="hs-modal-img" src="" alt="Notice Image">
                    </div>
                    <h3 id="hs-modal-title" class="hs-modal-title"></h3>
                    <div id="hs-modal-body" class="hs-modal-prose"></div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    // OUTPUT JSON PRELOAD
    if (function_exists('hs_get_notices_for_campus')) {
        $preload_data = array(
            'chaofah' => hs_get_notices_for_campus('chaofah'),
            'cherngtalay' => hs_get_notices_for_campus('cherngtalay')
        );
        echo '<script>window.hsNoticeDataPreload = ' . json_encode($preload_data) . ';</script>';
    }

    return ob_get_clean();
}
add_shortcode( 'hs_noticeboard', 'hs_noticeboard_shortcode' );

/* ==========================================================================
   4. CUSTOM ADMIN COLUMNS
   ========================================================================== */
add_filter( 'manage_hs_notice_posts_columns', 'hs_notice_set_custom_columns' );
function hs_notice_set_custom_columns( $columns ) {
    $new_columns = array();

    // Helper for Tooltips
    $tt = function($text) {
        return '<span class="hs-custom-tooltip" style="position:relative; display:inline-block; margin-left:2px;">
            <span class="dashicons dashicons-editor-help" style="color:#888; font-size:15px; vertical-align:middle; cursor:help;"></span>
            <span class="hs-tooltip-text">' . esc_html($text) . '</span>
        </span>';
    };

    $tt_warning = function($text) {
        return '<span class="hs-custom-tooltip" style="position:relative; display:inline-block; margin-left:2px;">
            <span class="dashicons dashicons-warning" style="color:#d63638; font-size:15px; vertical-align:middle; cursor:help;"></span>
            <span class="hs-tooltip-text">' . esc_html($text) . '</span>
        </span>';
    };
    foreach ($columns as $key => $title) {
        if ($key === 'title') {
            $new_columns['hs_title_col'] = 'Title';
        } else {
            $new_columns[$key] = $title;
        }
    }
    unset($new_columns['title']);
    
    // Reorder safely
    $ordered = array();
    $ordered['cb'] = $new_columns['cb'];
    $ordered['notice_image'] = __( 'Image', 'headstart' );
    $ordered['hs_title_col'] = $new_columns['hs_title_col'];
    $ordered['blurb'] = 'Blurb ' . $tt_warning('Inline editing supports plain text only. Formatting (bold, links, etc.) will be preserved but you cannot add new formatting here. Use Edit Full Notice to access the Rich Text Editor.');
    $ordered['campus'] = __( 'Campus', 'headstart' );
    $ordered['pinned'] = __( 'Status', 'headstart' );
    $ordered['notice_order'] = __( 'Order', 'headstart' );
    $ordered['expiry_date'] = __( 'Expiry Date', 'headstart' );
    $ordered['date'] = $new_columns['date'];
    return $ordered;
}

add_action( 'manage_hs_notice_posts_custom_column' , 'hs_notice_custom_column_data', 10, 2 );
function hs_notice_custom_column_data( $column, $post_id ) {
    switch ( $column ) {
        case 'notice_image':
            $image_url = get_field('notice_image', $post_id);
            if ( $image_url ) {
                echo '<img src="' . esc_url($image_url) . '" style="max-width:60px; max-height:60px; border-radius:4px; object-fit:cover;" />';
            } else {
                echo '<span style="color:#aaa;">-</span>';
            }
            break;
            
        case 'hs_title_col':
            $title = get_the_title($post_id);
            $edit_url = get_edit_post_link($post_id);
            echo '<div class="hs-inline-wrapper" style="position:relative;">';
            echo '<div class="hs-inline-display" style="margin-bottom:6px;"><strong>' . esc_html($title) . '</strong></div>';
            echo '<div class="hs-inline-actions">';
            echo '<a href="' . esc_url($edit_url) . '" class="button button-small">Edit Full Notice</a>';
            echo '</div>';
            echo '</div>';
            break;
            
        case 'blurb':
            $body = get_field('notice_body', $post_id);
            $trimmed = '';
            if ( $body ) {
                $trimmed = wp_trim_words( wp_strip_all_tags($body), 10, '...' );
            }
            
            echo '<div class="hs-inline-wrapper" style="position:relative;">';
            echo '<div class="hs-inline-display" style="margin-bottom:6px; font-size:12px;">' . ($trimmed ? esc_html($trimmed) : '<span style="color:#aaa;">-</span>') . '</div>';
            echo '<a href="#" class="button button-small hs-edit-inline-btn" data-type="blurb">Edit</a>';
            
            echo '<div class="hs-inline-editor hs-blurb-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:300px; margin-top:5px; border-radius:4px;">';
            echo '<textarea class="hs-inline-input hs-blurb-input" style="width:100%; height:100px; margin-bottom:10px;">' . esc_textarea($body) . '</textarea>';
            echo '<button type="button" class="button button-primary hs-save-inline-btn" data-post-id="' . intval($post_id) . '" data-field="notice_body">Save</button> ';
            echo '<button type="button" class="button hs-cancel-inline-btn">Cancel</button>';
            echo '<span class="hs-inline-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
            echo '</div>';
            echo '</div>';
            break;
            
        case 'campus':
            $campus = get_field('notice_campus', $post_id);
            if (!$campus) $campus = 'both';
            
            // Color code the admin chips
            $bg = '#e5f0e9'; $border = '#b2d6c1'; $text = '#004d22'; // Default / Both
            $display_name = 'Both Campuses';
            if ($campus === 'chaofah') {
                $bg = '#e5f0fa'; $border = '#b2d6f2'; $text = '#00438a'; // Blue
                $display_name = 'Chaofah';
            } else if ($campus === 'cherngtalay') {
                $bg = '#fdf2e8'; $border = '#f5d5b8'; $text = '#a65d14'; // Orange
                $display_name = 'Cherngtalay';
            }
            
            echo '<div class="hs-inline-wrapper" style="position:relative;">';
            echo '<div class="hs-inline-display" style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:6px;">';
            echo '<span style="background: ' . $bg . '; border: 1px solid ' . $border . '; color: ' . $text . '; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600;">' . esc_html($display_name) . '</span>';
            echo '</div>';
            
            echo '<a href="#" class="button button-small hs-edit-inline-btn" data-type="campus">Edit</a>';
            
            echo '<div class="hs-inline-editor hs-campus-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:220px; margin-top:5px; border-radius:4px;">';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;"><input type="radio" name="campus_' . $post_id . '" class="hs-inline-input" value="both" ' . checked($campus, 'both', false) . '> Both Campuses</label>';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;"><input type="radio" name="campus_' . $post_id . '" class="hs-inline-input" value="chaofah" ' . checked($campus, 'chaofah', false) . '> Chaofah</label>';
            echo '<label style="display:block; margin-bottom:10px; font-size:12px;"><input type="radio" name="campus_' . $post_id . '" class="hs-inline-input" value="cherngtalay" ' . checked($campus, 'cherngtalay', false) . '> Cherngtalay</label>';
            echo '<button type="button" class="button button-primary hs-save-inline-btn" data-post-id="' . intval($post_id) . '" data-field="notice_campus">Save</button> ';
            echo '<button type="button" class="button hs-cancel-inline-btn">Cancel</button>';
            echo '<span class="hs-inline-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
            echo '</div>';
            echo '</div>';
            break;
            
        case 'pinned':
            $is_pinned = get_field('is_pinned', $post_id);
            $is_important = get_field('is_important', $post_id);
            
            $current_status = 'standard';
            if ($is_important) $current_status = 'important';
            if ($is_pinned && $is_pinned !== '0') {
                if ($is_pinned === '1' || $is_pinned === true) $current_status = 'both';
                else $current_status = $is_pinned;
            }
            
            $bg = '#eef2f6'; $border = '#c9d8e5'; $text = '#003057'; // Standard
            $display_name = 'Standard';
            if ($current_status === 'important') {
                $bg = '#fdf2e8'; $border = '#f5d5b8'; $text = '#a65d14';
                $display_name = 'Important';
            } else if (in_array($current_status, array('both', 'chaofah', 'cherngtalay'))) {
                $bg = '#fff3cd'; $border = '#ffeeba'; $text = '#856404';
                $display_name = 'Pinned (' . ucfirst($current_status) . ')';
            }
            
            echo '<div class="hs-inline-wrapper" style="position:relative;">';
            echo '<div class="hs-inline-display" style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:6px;">';
            echo '<span style="background: ' . $bg . '; border: 1px solid ' . $border . '; color: ' . $text . '; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600;">' . esc_html($display_name) . '</span>';
            echo '</div>';
            
            echo '<a href="#" class="button button-small hs-edit-inline-btn" data-type="status">Edit</a>';
            
            echo '<div class="hs-inline-editor hs-status-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:220px; margin-top:5px; border-radius:4px;">';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;"><input type="radio" name="status_' . $post_id . '" class="hs-inline-input" value="standard" ' . checked($current_status, 'standard', false) . '> Standard</label>';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;"><input type="radio" name="status_' . $post_id . '" class="hs-inline-input" value="important" ' . checked($current_status, 'important', false) . '> Important</label>';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;"><input type="radio" name="status_' . $post_id . '" class="hs-inline-input" value="both" ' . checked($current_status, 'both', false) . '> Pinned (Both)</label>';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;"><input type="radio" name="status_' . $post_id . '" class="hs-inline-input" value="chaofah" ' . checked($current_status, 'chaofah', false) . '> Pinned (Chaofah)</label>';
            echo '<label style="display:block; margin-bottom:10px; font-size:12px;"><input type="radio" name="status_' . $post_id . '" class="hs-inline-input" value="cherngtalay" ' . checked($current_status, 'cherngtalay', false) . '> Pinned (Cherngtalay)</label>';
            echo '<button type="button" class="button button-primary hs-save-inline-btn" data-post-id="' . intval($post_id) . '" data-field="status">Save</button> ';
            echo '<button type="button" class="button hs-cancel-inline-btn">Cancel</button>';
            echo '<span class="hs-inline-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
            echo '</div>';
            echo '</div>';
            break;
            
        case 'notice_order':
            $order = get_field('notice_order', $post_id);
            $display_order = ($order !== '' && $order !== null) ? esc_html($order) : '999';
            
            echo '<div class="hs-inline-wrapper" style="position:relative;">';
            echo '<div class="hs-inline-display" style="margin-bottom:6px; font-size:12px;">' . $display_order . '</div>';
            echo '<a href="#" class="button button-small hs-edit-inline-btn" data-type="order">Edit</a>';
            
            echo '<div class="hs-inline-editor hs-order-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:150px; margin-top:5px; border-radius:4px;">';
            echo '<input type="number" class="hs-inline-input hs-order-input" value="' . esc_attr($display_order) . '" style="width:100%; margin-bottom:10px;" />';
            echo '<button type="button" class="button button-primary hs-save-inline-btn" data-post-id="' . intval($post_id) . '" data-field="notice_order">Save</button> ';
            echo '<button type="button" class="button hs-cancel-inline-btn">Cancel</button>';
            echo '<span class="hs-inline-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
            echo '</div>';
            echo '</div>';
            break;
            
        case 'expiry_date':
            $expiry = get_field('expiry_date', $post_id);
            $input_val = '';
            if ($expiry) {
                $input_val = date('Y-m-d', strtotime($expiry));
            }
            
            echo '<div class="hs-inline-wrapper" style="position:relative;">';
            echo '<div class="hs-inline-display" style="margin-bottom:6px; font-size:12px;">';
            if ( $expiry ) {
                echo date_i18n('d M Y', strtotime($expiry));
            } else {
                echo '<span style="color:#aaa;">-</span>';
            }
            echo '</div>';
            
            echo '<a href="#" class="button button-small hs-edit-inline-btn" data-type="expiry_date">Edit</a>';
            
            echo '<div class="hs-inline-editor hs-expiry-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:220px; margin-top:5px; border-radius:4px;">';
            echo '<input type="date" class="hs-inline-input hs-expiry-input" value="' . esc_attr($input_val) . '" style="width:100%; margin-bottom:10px;" />';
            echo '<button type="button" class="button button-primary hs-save-inline-btn" data-post-id="' . intval($post_id) . '" data-field="expiry_date">Save</button> ';
            echo '<button type="button" class="button hs-cancel-inline-btn">Cancel</button>';
            echo '<span class="hs-inline-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
            echo '</div>';
            echo '</div>';
            break;
    }
}

/* ==========================================================================
   5. AUTOMATIC PIN MANAGEMENT
   ========================================================================== */
add_action('acf/save_post', 'hs_noticeboard_auto_unpin_others', 20);
function hs_noticeboard_auto_unpin_others( $post_id ) {
    if ( get_post_type($post_id) !== 'hs_notice' ) return;

    $pin_value = get_field('is_pinned', $post_id);
    
    if ( $pin_value && $pin_value !== '0' && $pin_value !== 0 ) {
        // If this post is pinned, ensure it is NOT also marked as important
        update_field('is_important', 0, $post_id);
    } else {
        return;
    }

    $args = array(
        'post_type' => 'hs_notice',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'post__not_in' => array($post_id),
        'meta_query' => array(
            'relation' => 'OR',
            array('key' => 'is_pinned', 'value' => '1', 'compare' => '='),
            array('key' => 'is_pinned', 'value' => 'both', 'compare' => '='),
        )
    );

    if ( $pin_value === 'both' || $pin_value == '1' || $pin_value === true ) {
        $args['meta_query'][] = array('key' => 'is_pinned', 'value' => 'chaofah', 'compare' => '=');
        $args['meta_query'][] = array('key' => 'is_pinned', 'value' => 'cherngtalay', 'compare' => '=');
    } else {
        $args['meta_query'][] = array('key' => 'is_pinned', 'value' => $pin_value, 'compare' => '=');
    }

    $others = get_posts($args);

    $unpinned_count = 0;
    foreach ( $others as $other ) {
        $other_pin = get_field('is_pinned', $other->ID);
        
        // Graceful demotion of global pins if a single campus pin is overriding it
        if ( $pin_value !== 'both' && $pin_value != '1' ) {
            if ( $other_pin === 'both' || $other_pin == '1' || $other_pin === true ) {
                $new_pin = ($pin_value === 'chaofah') ? 'cherngtalay' : 'chaofah';
                update_field('is_pinned', $new_pin, $other->ID);
                $unpinned_count++;
                continue;
            }
        }
        
        update_field('is_pinned', '0', $other->ID);
        $unpinned_count++;
    }

    if ( $unpinned_count > 0 ) {
        set_transient('hs_notice_unpinned_count', $unpinned_count, 60);
    }
}

/* ==========================================================================
   6. ADMIN FILTER DROPDOWN
   ========================================================================== */
add_action( 'restrict_manage_posts', 'hs_notice_add_campus_filter' );
function hs_notice_add_campus_filter() {
    global $typenow;
    if ( $typenow == 'hs_notice' ) {
        $selected = isset( $_GET['notice_campus_filter'] ) ? $_GET['notice_campus_filter'] : '';
        echo '<select name="notice_campus_filter" id="notice_campus_filter">';
        echo '<option value="">All Campuses</option>';
        echo '<option value="both" ' . selected( $selected, 'both', false ) . '>Both Campuses</option>';
        echo '<option value="chaofah" ' . selected( $selected, 'chaofah', false ) . '>Chaofah</option>';
        echo '<option value="cherngtalay" ' . selected( $selected, 'cherngtalay', false ) . '>Cherngtalay</option>';
        echo '</select>';
    }
}

add_action( 'pre_get_posts', 'hs_notice_filter_by_campus' );
function hs_notice_filter_by_campus( $query ) {
    global $pagenow, $typenow;
    if ( is_admin() && $pagenow == 'edit.php' && $typenow == 'hs_notice' && $query->is_main_query() ) {
        if ( isset( $_GET['notice_campus_filter'] ) && $_GET['notice_campus_filter'] != '' ) {
            $campus = sanitize_text_field( $_GET['notice_campus_filter'] );
            $meta_query = $query->get( 'meta_query' );
            if ( ! is_array( $meta_query ) ) {
                $meta_query = array();
            }
            if ($campus === 'both') {
                $meta_query[] = array(
                    'relation' => 'OR',
                    array('key' => 'notice_campus', 'value' => 'both', 'compare' => '='),
                    array('key' => 'notice_campus', 'compare' => 'NOT EXISTS')
                );
            } else {
                $meta_query[] = array(
                    'key'     => 'notice_campus',
                    'value'   => $campus,
                    'compare' => '='
                );
            }
            $query->set( 'meta_query', $meta_query );
        }
    }
}

/* ==========================================================================
   7. BACK TO LIST BUTTON ON EDIT SCREEN
   ========================================================================== */
add_action('admin_head', 'hs_noticeboard_back_to_list_btn');
function hs_noticeboard_back_to_list_btn() {
    global $pagenow, $typenow;
    if ( ($pagenow == 'post.php' || $pagenow == 'post-new.php') && $typenow == 'hs_notice' ) {
        $list_url = admin_url('edit.php?post_type=hs_notice');
        echo '<script>
            jQuery(document).ready(function($){
                // In classic editor, the h1 contains the title and the "Add New" button.
                // We append our custom button right after the title action.
                $("<a href=\"'.$list_url.'\" class=\"page-title-action\">View All Notices</a>").insertAfter(".page-title-action");
            });
        </script>';
    }
}

/* ==========================================================================
   8. UNPIN WARNING MESSAGE
   ========================================================================== */
add_action('admin_notices', 'hs_noticeboard_unpin_warning');
function hs_noticeboard_unpin_warning() {
    $count = get_transient('hs_notice_unpinned_count');
    if ( $count ) {
        echo '<div class="notice notice-warning is-dismissible"><p><strong>Notice:</strong> To make room for your newly pinned notice, ' . intval($count) . ' other notice(s) had their pins automatically removed or gracefully adjusted.</p></div>';
        delete_transient('hs_notice_unpinned_count');
    }
}

/* ==========================================================================
   9. AJAX INLINE COLUMN EDITING
   ========================================================================== */
add_action('admin_footer', 'hs_noticeboard_inline_edit_js');
function hs_noticeboard_inline_edit_js() {
    global $pagenow, $typenow;
    if ( $pagenow === 'edit.php' && $typenow === 'hs_notice' ) {
        ?>
        <style>
        .hs-custom-tooltip .hs-tooltip-text { display: none !important; }
        .hs-global-tooltip {
            position: absolute; background: #1e1e1e; color: #fff; padding: 6px 10px;
            border-radius: 4px; font-size: 12px; font-weight: 500; white-space: normal;
            width: max-content; max-width: 220px; text-align: center;
            z-index: 999999999 !important; box-shadow: 0 3px 8px rgba(0,0,0,0.4);
            pointer-events: none; line-height: 1.3; text-transform: none;
        }
        .hs-global-tooltip::after {
            content: ""; position: absolute; top: 100%; left: 50%; margin-left: -5px;
            border-width: 5px; border-style: solid; border-color: #1e1e1e transparent transparent transparent;
        }
        </style>
        <script>
        jQuery(document).ready(function($){
            // Global Tooltips
            $('.hs-custom-tooltip').on('mouseenter', function() {
                var $this = $(this);
                var text = $this.find('.hs-tooltip-text').html();
                var offset = $this.offset();
                var $tt = $('<div class="hs-global-tooltip"></div>').html(text).appendTo('body');
                $tt.css({
                    top: offset.top - $tt.outerHeight() - 10,
                    left: offset.left + ($this.outerWidth() / 2) - ($tt.outerWidth() / 2)
                });
            }).on('mouseleave', function() {
                $('.hs-global-tooltip').remove();
            });
            
            // 1. Open Editor Popup
            $('.hs-edit-inline-btn').on('click', function(e) {
                e.preventDefault();
                $('.hs-inline-editor').hide(); // Hide all open ones
                $(this).siblings('.hs-inline-editor').show();
            });
            
            // 2. Close Editor Popup
            $('.hs-cancel-inline-btn').on('click', function(e) {
                e.preventDefault();
                $(this).closest('.hs-inline-editor').hide();
            });

            // 3. Save Field
            $('.hs-save-inline-btn').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $editor = $btn.closest('.hs-inline-editor');
                var $status = $editor.find('.hs-inline-ajax-status');
                var postId = $btn.data('post-id');
                var fieldName = $btn.data('field');
                
                var newValue = '';
                
                // Get value depending on input type
                var $textInput = $editor.find('input[type="text"], input[type="number"], input[type="date"], textarea');
                var $radioInput = $editor.find('input[type="radio"]:checked');
                
                if ($textInput.length > 0) {
                    newValue = $textInput.val();
                } else if ($radioInput.length > 0) {
                    newValue = $radioInput.val();
                }

                $btn.prop('disabled', true);
                $status.text('Saving...').css('color', '#888');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hs_update_notice_field',
                        nonce: '<?php echo wp_create_nonce("hs_notice_ajax_nonce"); ?>',
                        post_id: postId,
                        field: fieldName,
                        value: newValue
                    },
                    success: function(response) {
                        if(response.success) {
                            $status.text('Saved! Reloading...').css('color', '#00a32a');
                            setTimeout(function(){ location.reload(); }, 500);
                        } else {
                            $status.text('Failed').css('color', '#d63638');
                            $btn.prop('disabled', false);
                        }
                    },
                    error: function() {
                        $status.text('Error').css('color', '#d63638');
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
}

add_action('wp_ajax_hs_update_notice_field', 'hs_ajax_update_notice_field');
function hs_ajax_update_notice_field() {
    check_ajax_referer('hs_notice_ajax_nonce', 'nonce');
    
    if ( !current_user_can('edit_posts') ) {
        wp_send_json_error();
    }
    
    $post_id = intval($_POST['post_id']);
    $field = sanitize_text_field($_POST['field']);
    // value could be HTML for notice_body, so use wp_kses_post instead of sanitize_text_field
    $value = wp_kses_post($_POST['value']);
    
    $requires_reload = true; // For WP-Posts style UI, we just reload the page to refresh chips/title
    
    if ( $field === 'title' ) {
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => sanitize_text_field($_POST['value'])
        ));
    } elseif ( $field === 'notice_campus' ) {
        update_field('notice_campus', sanitize_text_field($_POST['value']), $post_id);
    } elseif ( $field === 'notice_body' ) {
        update_field('notice_body', $value, $post_id);
    } elseif ( $field === 'notice_order' ) {
        update_field('notice_order', intval($_POST['value']), $post_id);
    } elseif ( $field === 'expiry_date' ) {
        $date_val = sanitize_text_field($_POST['value']);
        if ($date_val) {
            $date_val = str_replace('-', '', $date_val); // Convert YYYY-MM-DD to YYYYMMDD for ACF
        }
        update_field('expiry_date', $date_val, $post_id);
    } elseif ( $field === 'status' ) {
        $status_val = sanitize_text_field($_POST['value']);
        // Status maps to both is_important and is_pinned
        if ( $status_val === 'important' ) {
            update_field('is_important', 1, $post_id);
            update_field('is_pinned', '0', $post_id);
        } elseif ( in_array($status_val, array('both', 'chaofah', 'cherngtalay')) ) {
            update_field('is_important', 0, $post_id);
            update_field('is_pinned', $status_val, $post_id);
            
            // Trigger auto unpin logic since a pin was added
            hs_noticeboard_auto_unpin_others($post_id);
        } else {
            // standard
            update_field('is_important', 0, $post_id);
            update_field('is_pinned', '0', $post_id);
        }
    }
    
    wp_send_json_success(array('requires_reload' => $requires_reload));
}


/* ==========================================================================
   X. AJAX ENDPOINT FOR NOTICEBOARD DATA
   ========================================================================== */
function hs_get_notices_for_campus($campus) {
    $meta_query = array( 'relation' => 'AND' );
    $today = date('Ymd');
    $meta_query[] = array(
        'relation' => 'OR',
        array('key' => 'expiry_date', 'compare' => 'NOT EXISTS'),
        array('key' => 'expiry_date', 'value' => '', 'compare' => '='),
        array('key' => 'expiry_date', 'value' => $today, 'type' => 'NUMERIC', 'compare' => '>=')
    );

    if ( $campus !== 'all' ) {
        $meta_query[] = array(
            'relation' => 'OR',
            array('key' => 'notice_campus', 'value' => $campus, 'compare' => '='),
            array('key' => 'notice_campus', 'value' => 'both', 'compare' => '='),
            array('key' => 'notice_campus', 'compare' => 'NOT EXISTS')
        );
    }

    $args = array(
        'post_type'      => 'hs_notice',
        'posts_per_page' => 50,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => $meta_query
    );
    
    $notices_query = new WP_Query( $args );
    $notice_data = array();
    $standard_notice_ids = array();
    $pinned_notice_id = null;
    
    if ( $notices_query->have_posts() ) {
        while ( $notices_query->have_posts() ) {
            $notices_query->the_post();
            $post_id = get_the_ID();
            $notice_key = 'n' . $post_id;
            
            $is_pinned    = get_field('is_pinned');
            $is_important = get_field('is_important');
            $body_content = get_field('notice_body');
            $image_url    = get_field('notice_image');
            $notice_order = get_field('notice_order');
            
            $notice_obj = array(
                'title'       => get_the_title(),
                'image'       => $image_url ? esc_url($image_url) : null,
                'isImportant' => $is_important ? true : false,
                'body'        => apply_filters('the_content', $body_content),
                'order'       => ($notice_order !== '' && $notice_order !== null) ? (int)$notice_order : 999,
                'timestamp'   => get_the_time('U')
            );
            
            $is_pinned_for_this_campus = false;
            if ( $is_pinned === '1' || $is_pinned === true || $is_pinned === 'both' ) {
                $is_pinned_for_this_campus = true;
            } elseif ( $campus !== 'all' && $is_pinned === $campus ) {
                $is_pinned_for_this_campus = true;
            }

            if ( $is_pinned_for_this_campus && $pinned_notice_id === null ) {
                $notice_data['pinned'] = $notice_obj;
                $pinned_notice_id = 'pinned';
            } else {
                $notice_data[$notice_key] = $notice_obj;
                $standard_notice_ids[] = $notice_key;
            }
        }
        
        usort($standard_notice_ids, function($a, $b) use ($notice_data) {
            $order_a = $notice_data[$a]['order'];
            $order_b = $notice_data[$b]['order'];
            
            if ($order_a == $order_b) {
                return $notice_data[$b]['timestamp'] - $notice_data[$a]['timestamp'];
            }
            return $order_a - $order_b;
        });
        
        wp_reset_postdata();
    }
    
    return array(
        'noticeData' => $notice_data,
        'standardIDs' => $standard_notice_ids
    );
}
