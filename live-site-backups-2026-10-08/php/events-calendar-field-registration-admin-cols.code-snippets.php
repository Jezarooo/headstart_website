<?php

/**
 * Events-Calendar--Field-Registration-Admin-Cols
 */
// HeadStart Events Calendar v2 - Admin (CPT, Meta Boxes, Columns, Filters)

// ===================================================
// 1. REGISTER CUSTOM POST TYPE
// ===================================================
function hs_v3_register_events_cpt() {
    $labels = array(
        'name'                  => 'Events Calendar',
        'singular_name'         => 'Event',
        'menu_name'             => 'Events Calendar',
        'all_items'             => 'All Events',
        'add_new'               => 'Add new',
        'add_new_item'          => 'Add new Event',
        'edit_item'             => 'Edit Event',
        'new_item'              => 'New Event',
        'view_item'             => 'View Event',
        'view_items'            => 'View Events',
        'search_items'          => 'Search Events',
        'not_found'             => 'No Events found',
        'not_found_in_trash'    => 'No Events found in bin',
        'featured_image'        => 'Event Banner (16:9)',
        'set_featured_image'    => 'Set Event Banner',
        'remove_featured_image' => 'Remove Event Banner',
        'use_featured_image'    => 'Use as Event Banner',
    );

    $args = array(
        'label'                 => 'Events',
        'description'           => 'Manages all school calendar events.',
        'labels'                => $labels,
        'supports'              => array('title', 'thumbnail', 'revisions'),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_icon'             => 'dashicons-calendar-alt',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => false,
        'has_archive'           => true,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'post',
        'show_in_rest'          => false,
        'rewrite'               => true,
    );

    register_post_type('events', $args);

    // Register Event Category Taxonomy
    $tax_labels = array(
        'name'              => 'Event Categories',
        'singular_name'     => 'Event Category',
        'search_items'      => 'Search Event Categories',
        'all_items'         => 'All Event Categories',
        'edit_item'         => 'Edit Event Category',
        'update_item'       => 'Update Event Category',
        'add_new_item'      => 'Add New Event Category',
        'new_item_name'     => 'New Event Category Name',
        'menu_name'         => 'Event Categories',
    );
    $tax_args = array(
        'hierarchical'      => false,
        'labels'            => $tax_labels,
        'show_ui'           => true,
        'show_admin_column' => false,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'event-category'),
        'show_in_rest'      => false,
    );
    register_taxonomy('hs_event_category', array('events'), $tax_args);
}
add_action('init', 'hs_v3_register_events_cpt');


// ===================================================
// 2. META BOXES (Event Details Form)
// ===================================================
function hs_v3_add_events_meta_boxes() {
    add_meta_box(
        'hs_v3_event_details',
        'Event Details',
        'hs_v3_render_event_details_meta_box',
        'events',
        'normal',
        'high'
    );
    // Remove the default featured image sidebar box (we embed it in the form instead)
    remove_meta_box('postimagediv', 'events', 'side');
}
add_action('add_meta_boxes', 'hs_v3_add_events_meta_boxes');

// Enqueue the WP media uploader on event edit screens
function hs_v3_enqueue_event_admin_scripts($hook) {
    global $post_type;
    if ($post_type === 'events' && in_array($hook, array('post.php', 'post-new.php'))) {
        wp_enqueue_media();
    }
}
add_action('admin_enqueue_scripts', 'hs_v3_enqueue_event_admin_scripts');

function hs_v3_render_event_details_meta_box($post) {
    wp_nonce_field('hs_v3_save_event_meta', 'hs_v3_event_meta_nonce');

    $start_date     = get_post_meta($post->ID, 'event_start_date', true);
    $end_date       = get_post_meta($post->ID, 'event_end_date', true);
    $is_all_day     = get_post_meta($post->ID, 'is_all_day', true);
    $start_time     = get_post_meta($post->ID, 'event_start_time', true);
    $end_time       = get_post_meta($post->ID, 'event_end_time', true);
    $category       = get_post_meta($post->ID, 'event_category', true);
    $location       = get_post_meta($post->ID, 'event_location', true);
    $blurb          = get_post_meta($post->ID, 'event_blurb', true);
    $reg_type       = get_post_meta($post->ID, 'registration_type', true);
    $ext_reg_link   = get_post_meta($post->ID, 'external_registration_link', true);
    $fluent_link    = get_post_meta($post->ID, 'fluent_form_page_link', true);
    $target_campus  = get_post_meta($post->ID, 'target_campus', true);
    $is_featured    = get_post_meta($post->ID, 'is_featured', true);

    $html_start_date = $start_date ? date('Y-m-d', strtotime($start_date)) : '';
    $html_end_date   = $end_date ? date('Y-m-d', strtotime($end_date)) : '';

    if (!$target_campus) $target_campus = 'both';
    if (!$reg_type) $reg_type = 'none';

    echo '<style>
        .hs-meta-row { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; display: flex; flex-wrap: wrap; gap: 20px; }
        .hs-meta-row:last-child { border-bottom: none; }
        .hs-meta-field { flex: 1; min-width: 200px; }
        .hs-meta-field label { display: block; font-weight: bold; margin-bottom: 5px; }
        .hs-meta-field input[type="text"], .hs-meta-field input[type="date"], .hs-meta-field input[type="time"], .hs-meta-field select, .hs-meta-field textarea { width: 100%; max-width: 400px; }
        .hs-meta-field textarea { height: 100px; max-width: 100%; }
        .hs-featured-highlight { background: #fffdf7; border-left: 4px solid #f0b323; padding: 15px; margin-bottom: 20px; }
        .hs-banner-upload-area { margin-top: 5px; }
        .hs-banner-preview { max-width: 480px; border-radius: 6px; overflow: hidden; margin-bottom: 10px; border: 2px dashed #ddd; background: #fafafa; }
        .hs-banner-preview img { display: block; width: 100%; height: auto; }
        .hs-banner-preview.has-image { border-style: solid; border-color: #ccc; }
        .hs-banner-buttons { display: flex; gap: 8px; margin-top: 8px; }
        .hs-banner-buttons .button { font-size: 12px; }
    </style>';

    echo '<div class="hs-featured-highlight">';
    echo '<div class="hs-meta-field">';
    echo '<label><input type="checkbox" name="is_featured" value="1" ' . checked(1, $is_featured, false) . ' /> <strong>Mark as Featured Event (Shows in Slider)</strong></label>';
    echo '<p class="description">If unchecked, this event will appear in the Sidebar "Important Dates" list.</p>';
    echo '</div>';
    echo '</div>';

    echo '<div class="hs-meta-row">';
    echo '<div class="hs-meta-field">';
    echo '<label>Target Campus</label>';
    echo '<select name="target_campus">';
    echo '<option value="both" ' . selected($target_campus, 'both', false) . '>Both Campuses</option>';
    echo '<option value="chaofah" ' . selected($target_campus, 'chaofah', false) . '>Chaofah City</option>';
    echo '<option value="cherngtalay" ' . selected($target_campus, 'cherngtalay', false) . '>Cherngtalay</option>';
    echo '</select>';
    echo '</div>';

    echo '<div class="hs-meta-field">';
    echo '<label>Event Category</label>';
    echo '<select name="event_category">';
    $terms = get_terms(array('taxonomy' => 'hs_event_category', 'hide_empty' => false));
    echo '<option value="">-- Select Category --</option>';
    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            echo '<option value="' . esc_attr($term->slug) . '" ' . selected($category, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    echo '<div class="hs-meta-row">';
    echo '<div class="hs-meta-field">';
    echo '<label>Start Date</label>';
    echo '<input type="date" name="event_start_date" value="' . esc_attr($html_start_date) . '" required />';
    echo '</div>';
    echo '<div class="hs-meta-field">';
    echo '<label>End Date (Optional)</label>';
    echo '<input type="date" name="event_end_date" value="' . esc_attr($html_end_date) . '" />';
    echo '</div>';
    echo '</div>';

    echo '<div class="hs-meta-row">';
    echo '<div class="hs-meta-field">';
    echo '<label><input type="checkbox" name="is_all_day" id="hs-all-day-check" value="1" ' . checked(1, $is_all_day, false) . ' /> All Day Event</label>';
    echo '</div>';
    $time_hide = $is_all_day ? ' style="display:none;"' : '';
    echo '<div class="hs-meta-field" id="hs-start-time-wrap"' . $time_hide . '>';
    echo '<label>Start Time (e.g., 15:30 or 3:30 pm)</label>';
    echo '<input type="text" name="event_start_time" value="' . esc_attr($start_time) . '" />';
    echo '</div>';
    echo '<div class="hs-meta-field" id="hs-end-time-wrap"' . $time_hide . '>';
    echo '<label>End Time (e.g., 17:00 or 5:00 pm)</label>';
    echo '<input type="text" name="event_end_time" value="' . esc_attr($end_time) . '" />';
    echo '</div>';
    echo '</div>';

    echo '<div class="hs-meta-row">';
    echo '<div class="hs-meta-field" style="max-width: 100%;">';
    echo '<label>Location</label>';
    echo '<input type="text" name="event_location" value="' . esc_attr($location) . '" style="max-width: 100%;" />';
    echo '</div>';
    echo '</div>';

    echo '<div class="hs-meta-row">';
    echo '<div class="hs-meta-field" style="max-width: 100%;">';
    echo '<label>Event Blurb / Description (Used in popups)</label>';
    echo '<textarea name="event_blurb">' . esc_textarea($blurb) . '</textarea>';
    echo '</div>';
    echo '</div>';

    echo '<div class="hs-meta-row">';
    echo '<div class="hs-meta-field">';
    echo '<label>Registration Type</label>';
    echo '<select name="registration_type" style="max-width: 100%;">';
    echo '<option value="none" ' . selected($reg_type, 'none', false) . '>No Registration Required</option>';
    echo '<option value="fluent" ' . selected($reg_type, 'fluent', false) . '>Fluent Forms Link</option>';
    echo '<option value="external" ' . selected($reg_type, 'external', false) . '>External URL</option>';
    echo '</select>';
    echo '</div>';
    echo '<div class="hs-meta-field">';
    echo '<label>Fluent Form Page Link</label>';
    echo '<input type="url" name="fluent_form_page_link" value="' . esc_url($fluent_link) . '" placeholder="https://..." style="max-width: 100%;" />';
    echo '</div>';
    echo '<div class="hs-meta-field">';
    echo '<label>External Registration URL</label>';
    echo '<input type="url" name="external_registration_link" value="' . esc_url($ext_reg_link) . '" placeholder="https://..." style="max-width: 100%;" />';
    echo '</div>';
    echo '</div>';

    // --- Event Banner Image (replaces sidebar featured image) ---
    $thumb_id  = get_post_thumbnail_id($post->ID);
    $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium_large') : '';
    $has_class = $thumb_url ? ' has-image' : '';

    echo '<div class="hs-meta-row">';
    echo '<div class="hs-meta-field" style="max-width: 100%;">';
    echo '<label>Event Banner Image (16:9 recommended)</label>';
    echo '<div class="hs-banner-upload-area">';
    echo '<div class="hs-banner-preview' . $has_class . '" id="hs-banner-preview">';
    if ($thumb_url) {
        echo '<img src="' . esc_url($thumb_url) . '" alt="Banner preview" />';
    }
    echo '</div>';
    echo '<div class="hs-banner-buttons">';
    echo '<button type="button" class="button button-primary" id="hs-upload-banner">Select Image</button>';
    echo '<button type="button" class="button" id="hs-remove-banner" style="' . ($thumb_url ? '' : 'display:none;') . '">Remove Image</button>';
    echo '</div>';
    echo '<input type="hidden" name="_thumbnail_id" id="hs-banner-id" value="' . esc_attr($thumb_id) . '" />';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // JS for the media uploader
    echo '<script>
    jQuery(document).ready(function($){
        var frame;
        $("#hs-upload-banner").on("click", function(e){
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({
                title: "Select Event Banner",
                button: { text: "Use as Banner" },
                multiple: false,
                library: { type: "image" }
            });
            frame.on("select", function(){
                var attachment = frame.state().get("selection").first().toJSON();
                var url = attachment.sizes && attachment.sizes.medium_large ? attachment.sizes.medium_large.url : attachment.url;
                $("#hs-banner-id").val(attachment.id);
                $("#hs-banner-preview").addClass("has-image").html("<img src=\"" + url + "\" alt=\"Banner preview\" />");
                $("#hs-remove-banner").show();
            });
            frame.open();
        });
        $("#hs-remove-banner").on("click", function(e){
            e.preventDefault();
            $("#hs-banner-id").val("");
            $("#hs-banner-preview").removeClass("has-image").html("");
            $(this).hide();
        });
        // Toggle time fields when All Day is checked/unchecked
        $("#hs-all-day-check").on("change", function(){
            if ($(this).is(":checked")) {
                $("#hs-start-time-wrap, #hs-end-time-wrap").slideUp(200);
            } else {
                $("#hs-start-time-wrap, #hs-end-time-wrap").slideDown(200);
            }
        });
    });
    </script>';
}


// ===================================================
// 3. SAVE META BOX DATA
// ===================================================
function hs_v3_save_event_meta($post_id) {
    if (!isset($_POST['hs_v3_event_meta_nonce']) || !wp_verify_nonce($_POST['hs_v3_event_meta_nonce'], 'hs_v3_save_event_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fields = array(
        'event_start_time', 'event_end_time', 'event_category',
        'event_location', 'event_blurb', 'registration_type',
        'external_registration_link', 'fluent_form_page_link', 'target_campus'
    );

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }

    if (isset($_POST['event_category'])) {
        wp_set_object_terms($post_id, sanitize_text_field($_POST['event_category']), 'hs_event_category');
    }

    if (!empty($_POST['event_start_date'])) {
        update_post_meta($post_id, 'event_start_date', date('Ymd', strtotime($_POST['event_start_date'])));
    } else {
        delete_post_meta($post_id, 'event_start_date');
    }

    if (!empty($_POST['event_end_date'])) {
        update_post_meta($post_id, 'event_end_date', date('Ymd', strtotime($_POST['event_end_date'])));
    } else {
        delete_post_meta($post_id, 'event_end_date');
    }

    update_post_meta($post_id, 'is_all_day', isset($_POST['is_all_day']) ? 1 : 0);
    update_post_meta($post_id, 'is_featured', isset($_POST['is_featured']) ? 1 : 0);

    // Save featured image (banner)
    if (isset($_POST['_thumbnail_id'])) {
        $thumb_id = intval($_POST['_thumbnail_id']);
        if ($thumb_id > 0) {
            set_post_thumbnail($post_id, $thumb_id);
        } else {
            delete_post_thumbnail($post_id);
        }
    }
}
add_action('save_post_events', 'hs_v3_save_event_meta');


// ===================================================
// 4. ADMIN COLUMNS
// ===================================================
if (!function_exists('hs_v3_get_category_color')) {
    function hs_v3_get_category_color($cat_val) {
        $cat_val = strtolower($cat_val);
        $colors = array(
            'sports' => '#3182ce', 'capa' => '#d53f8c', 'academic' => '#805ad5',
            'community' => '#dd6b20', 'exams' => '#e53e3e', 'trips' => '#38a169',
            'holidays' => '#319795', 'foundation' => '#ed8936', 'primary' => '#ecc94b',
            'secondary' => '#4fd1c5', 'sixth-form' => '#667eea', 'languages' => '#9f7aea',
            'thai' => '#f56565',
        );
        if (isset($colors[$cat_val])) return $colors[$cat_val];
        $palette = array('#f56565', '#ed8936', '#ecc94b', '#48bb78', '#38b2ac', '#4299e1', '#667eea', '#9f7aea', '#ed64a6');
        return $palette[abs(crc32($cat_val)) % count($palette)];
    }
}

function hs_v3_add_event_admin_columns($columns) {
    // Helper for Tooltips
    $tt = function($text) {
        return '<span class="hs-custom-tooltip" style="position:relative; display:inline-block; margin-left:2px;">
            <span class="dashicons dashicons-editor-help" style="color:#888; font-size:15px; vertical-align:middle; cursor:help;"></span>
            <span class="hs-tooltip-text">' . esc_html($text) . '</span>
        </span>';
    };

    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];
    $new_columns['event_campus'] = 'Campus ' . $tt('Select the campus this event is associated with.');
    $new_columns['event_date'] = 'Date ' . $tt('The start and end date for the event.');
    $new_columns['event_time'] = 'Time ' . $tt('The start and end time or all day status.');
    $new_columns['event_category'] = 'Category ' . $tt('The category of the event.');
    $new_columns['event_location'] = 'Location ' . $tt('The physical location of the event.');
    $new_columns['event_reg'] = 'Registration ' . $tt('Registration type and link if applicable.');
    $new_columns['date'] = 'Published On ' . $tt('The date the event was published.');
    return $new_columns;
}
add_filter('manage_events_posts_columns', 'hs_v3_add_event_admin_columns');

function hs_v3_populate_event_admin_columns($column, $post_id) {
    switch ($column) {
        case 'event_campus':
            $campus = get_post_meta($post_id, 'target_campus', true);
            echo '<div class="hs-campus-wrapper" style="position:relative;">';
            echo '<div class="hs-campus-display" style="margin-bottom:6px;">';
            if ($campus === 'chaofah') echo '<span style="background: #e5f0fa; border: 1px solid #b2d6f2; color: #00438a; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600;">Chaofah</span>';
            elseif ($campus === 'cherngtalay') echo '<span style="background: #fdf2e8; border: 1px solid #f5d5b8; color: #a65d14; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600;">Cherngtalay</span>';
            elseif ($campus === 'both') echo '<span style="background: #e5f0e9; border: 1px solid #b2d6c1; color: #004d22; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600;">Both Campuses</span>';
            else echo '<span style="color:#d63638; font-size:11px;">Required</span>';
            echo '</div>';
            
            echo '<a href="#" class="button button-small hs-edit-campus-btn">Edit</a>';
            
            echo '<div class="hs-campus-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:220px; margin-top:5px; border-radius:4px;">';
            echo '<div style="margin-bottom:10px;">';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;"><input type="radio" name="hs_campus_radio_' . intval($post_id) . '" class="hs-campus-radio" value="both" ' . checked($campus, 'both', false) . ' /> Both Campuses</label>';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;"><input type="radio" name="hs_campus_radio_' . intval($post_id) . '" class="hs-campus-radio" value="chaofah" ' . checked($campus, 'chaofah', false) . ' /> Chaofah City</label>';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;"><input type="radio" name="hs_campus_radio_' . intval($post_id) . '" class="hs-campus-radio" value="cherngtalay" ' . checked($campus, 'cherngtalay', false) . ' /> Cherngtalay</label>';
            echo '</div>';
            echo '<button type="button" class="button button-primary hs-save-campus-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
            echo '<button type="button" class="button hs-cancel-campus-btn">Cancel</button>';
            echo '<span class="hs-campus-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
            echo '</div>';
            echo '</div>';
            break;

        case 'event_date':
            $raw_start = get_post_meta($post_id, 'event_start_date', true);
            $raw_end   = get_post_meta($post_id, 'event_end_date', true);
            echo '<div class="hs-date-wrapper" style="position:relative;">';
            echo '<div class="hs-date-display" style="margin-bottom:6px;">';
            if ($raw_start) {
                $today_ymd = (int) current_time('Ymd');
                $start_val = (int) $raw_start;
                $end_val   = $raw_end ? (int) $raw_end : $start_val;
                $status = '';
                $badge  = '';
                if ($end_val < $today_ymd) {
                    $status = 'past';
                    $badge = '<span style="font-size: 10px; background: #fff5f5; color: #e53e3e; border: 1px solid #feb2b2; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; font-weight: 700;">Completed</span>';
                } elseif ($start_val > $today_ymd) {
                    $status = 'upcoming';
                    $badge = '<span style="font-size: 10px; background: #ebf8ff; color: #3182ce; border: 1px solid #90cdf4; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; font-weight: 700;">Upcoming</span>';
                } else {
                    $status = 'ongoing';
                    $badge = '<span style="font-size: 10px; background: #f0fff4; color: #38a169; border: 1px solid #9ae6b4; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; font-weight: 700;">In Progress</span>';
                }
                $style = ($status === 'past') ? 'color: #a0aec0;' : 'color: #2d3748; font-weight: 600;';
                $formatted_start = date_i18n('M j, Y', strtotime($raw_start));
                echo '<div style="' . $style . '">';
                if ($raw_end && $raw_end !== $raw_start) {
                    $formatted_end = date_i18n('M j, Y', strtotime($raw_end));
                    echo esc_html($formatted_start) . '<br><span style="color: #a0aec0; font-size: 10px;">to</span><br>' . esc_html($formatted_end);
                } else {
                    echo esc_html($formatted_start);
                }
                echo '</div>';
                echo '<div style="margin-top: 8px;">' . $badge . '</div>';
            } else {
                echo '<span style="color:#d63638; font-size:11px;">Required</span>';
            }
            echo '</div>';
            
            echo '<a href="#" class="button button-small hs-edit-date-btn">Edit</a>';
            echo '<div class="hs-date-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:220px; margin-top:5px; border-radius:4px;">';
            $html_start = $raw_start ? date('Y-m-d', strtotime($raw_start)) : '';
            $html_end = $raw_end ? date('Y-m-d', strtotime($raw_end)) : '';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;">Start Date <span style="color:red;">*</span></label>';
            echo '<input type="date" class="hs-date-start-input" value="' . esc_attr($html_start) . '" style="width:100%; margin-bottom:10px;" />';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;">End Date (Optional)</label>';
            echo '<input type="date" class="hs-date-end-input" value="' . esc_attr($html_end) . '" style="width:100%; margin-bottom:10px;" />';
            echo '<button type="button" class="button button-primary hs-save-date-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
            echo '<button type="button" class="button hs-cancel-date-btn">Cancel</button>';
            echo '<span class="hs-date-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
            echo '</div>';
            echo '</div>';
            break;

        case 'event_time':
            $is_all_day = get_post_meta($post_id, 'is_all_day', true);
            $start_time = get_post_meta($post_id, 'event_start_time', true);
            $end_time = get_post_meta($post_id, 'event_end_time', true);
            echo '<div class="hs-time-wrapper" style="position:relative;">';
            echo '<div class="hs-time-display" style="margin-bottom:6px;">';
            if ($is_all_day) {
                echo '<span style="font-size: 11px; background: #ebf8ff; color: #3182ce; padding: 2px 6px; border-radius: 4px; font-weight: 600;">All Day</span>';
            } elseif ($start_time) {
                echo esc_html($end_time ? $start_time . ' - ' . $end_time : $start_time);
            } else {
                echo '<em>&mdash;</em>';
            }
            echo '</div>';
            
            echo '<a href="#" class="button button-small hs-edit-time-btn">Edit</a>';
            echo '<div class="hs-time-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:220px; margin-top:5px; border-radius:4px;">';
            echo '<label style="display:block; margin-bottom:10px; font-size:12px;">';
            echo '<input type="checkbox" class="hs-time-allday-input" value="1" ' . checked(1, $is_all_day, false) . ' /> All Day Event';
            echo '</label>';
            
            $time_display = $is_all_day ? 'none' : 'block';
            echo '<div class="hs-time-inputs-wrap" style="display:' . $time_display . ';">';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;">Start Time</label>';
            echo '<input type="text" class="hs-time-start-input" value="' . esc_attr($start_time) . '" style="width:100%; margin-bottom:10px;" placeholder="e.g. 15:30" />';
            echo '<label style="display:block; margin-bottom:4px; font-size:12px;">End Time (Optional)</label>';
            echo '<input type="text" class="hs-time-end-input" value="' . esc_attr($end_time) . '" style="width:100%; margin-bottom:10px;" placeholder="e.g. 17:00" />';
            echo '</div>';
            
            echo '<button type="button" class="button button-primary hs-save-time-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
            echo '<button type="button" class="button hs-cancel-time-btn">Cancel</button>';
            echo '<span class="hs-time-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
            echo '</div>';
            echo '</div>';
            break;

        case 'event_category':
            $cat = get_post_meta($post_id, 'event_category', true);
            echo '<div class="hs-category-wrapper" style="position:relative;">';
            echo '<div class="hs-category-display" style="margin-bottom:6px;">';
            if ($cat) {
                $cat_label = ucfirst(str_replace('-', ' ', $cat));
                $color = hs_v3_get_category_color($cat);
                echo '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; border-radius: 4px; background-color: ' . esc_attr($color) . '15; color: ' . esc_attr($color) . '; border: 1px solid ' . esc_attr($color) . '30;">';
                echo esc_html($cat_label);
                echo '</span>';
            } else {
                echo '<span style="color:#d63638; font-size:11px;">Required</span>';
            }
            echo '</div>';
            
            echo '<a href="#" class="button button-small hs-edit-category-btn">Edit</a>';
            
            echo '<div class="hs-category-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:220px; margin-top:5px; border-radius:4px;">';
            $terms = get_terms(array('taxonomy' => 'hs_event_category', 'hide_empty' => false));
            echo '<select class="hs-category-select" style="width:100%; margin-bottom:10px;">';
            echo '<option value="">-- Select Category --</option>';
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    echo '<option value="' . esc_attr($term->slug) . '" ' . selected($cat, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
                }
            }
            echo '</select>';
            
            echo '<button type="button" class="button button-primary hs-save-category-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
            echo '<button type="button" class="button hs-cancel-category-btn">Cancel</button>';
            echo '<span class="hs-category-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
            echo '</div>';
            echo '</div>';
            break;

        case 'event_location':
            $loc = get_post_meta($post_id, 'event_location', true);
            echo '<div class="hs-location-wrapper" style="position:relative;">';
            echo '<div class="hs-location-display" style="margin-bottom:6px; font-size:12px; color:#555;">';
            echo $loc ? esc_html($loc) : '<span style="color:#999; font-style:italic;">None</span>';
            echo '</div>';
            
            echo '<a href="#" class="button button-small hs-edit-location-btn">Edit</a>';
            
            echo '<div class="hs-location-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:250px; margin-top:5px; border-radius:4px;">';
            echo '<input type="text" class="hs-location-input" value="' . esc_attr($loc) . '" style="width:100%; margin-bottom:10px;" placeholder="Location" />';
            echo '<button type="button" class="button button-primary hs-save-location-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
            echo '<button type="button" class="button hs-cancel-location-btn">Cancel</button>';
            echo '<span class="hs-location-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
            echo '</div>';
            echo '</div>';
            break;

        case 'event_reg':
            $reg_type = get_post_meta($post_id, 'registration_type', true);
            if ($reg_type === 'external') echo 'External Link';
            elseif ($reg_type === 'fluent') echo 'Fluent Form';
            else echo '<em>None</em>';
            break;
    }
}
add_action('manage_events_posts_custom_column', 'hs_v3_populate_event_admin_columns', 10, 2);


// ===================================================
// 5. SORTABLE COLUMNS
// ===================================================
function hs_v3_event_sortable_columns($columns) {
    $columns['event_date'] = 'event_start_date';
    return $columns;
}
add_filter('manage_edit-events_sortable_columns', 'hs_v3_event_sortable_columns');


// ===================================================
// 6. ADMIN FILTERS (Date, Campus, Category dropdowns)
// ===================================================
function hs_v3_admin_events_filters($post_type) {
    if ($post_type !== 'events') return;

    $date_selected = isset($_GET['hs_v3_date_filter']) ? $_GET['hs_v3_date_filter'] : 'upcoming';
    echo '<select name="hs_v3_date_filter">';
    echo '<option value="upcoming" ' . selected($date_selected, 'upcoming', false) . '>Upcoming &amp; Ongoing</option>';
    echo '<option value="all" ' . selected($date_selected, 'all', false) . '>Show All Dates</option>';
    echo '<option value="past" ' . selected($date_selected, 'past', false) . '>Past Events</option>';
    echo '</select>';

    $campus_selected = isset($_GET['hs_v3_campus_filter']) ? $_GET['hs_v3_campus_filter'] : '';
    echo '<select name="hs_v3_campus_filter">';
    echo '<option value="">All Campuses</option>';
    echo '<option value="chaofah" ' . selected($campus_selected, 'chaofah', false) . '>Chaofah</option>';
    echo '<option value="cherngtalay" ' . selected($campus_selected, 'cherngtalay', false) . '>Cherngtalay</option>';
    echo '<option value="both" ' . selected($campus_selected, 'both', false) . '>Both Campuses</option>';
    echo '</select>';

    $cat_selected = isset($_GET['hs_v3_category_filter']) ? $_GET['hs_v3_category_filter'] : '';
    global $wpdb;
    $categories = $wpdb->get_col("
        SELECT DISTINCT meta_value 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'event_category' AND meta_value != ''
        ORDER BY meta_value ASC
    ");
    echo '<select name="hs_v3_category_filter">';
    echo '<option value="">All Categories</option>';
    if ($categories) {
        foreach ($categories as $cat) {
            if (is_serialized($cat)) continue;
            $label = ucfirst(str_replace('-', ' ', $cat));
            echo '<option value="' . esc_attr($cat) . '" ' . selected($cat_selected, $cat, false) . '>' . esc_html($label) . '</option>';
        }
    }
    echo '</select>';
}
add_action('restrict_manage_posts', 'hs_v3_admin_events_filters');


// ===================================================
// 7. FILTER QUERY LOGIC
// ===================================================
function hs_v3_admin_events_filter_query($query) {
    global $pagenow;
    if (!is_admin() || $pagenow !== 'edit.php' || !$query->is_main_query()) return;
    if (!isset($query->query['post_type']) || $query->query['post_type'] !== 'events') return;

    $meta_query = $query->get('meta_query');
    if (!is_array($meta_query)) $meta_query = array();

    $date_filter = isset($_GET['hs_v3_date_filter']) ? sanitize_text_field($_GET['hs_v3_date_filter']) : 'upcoming';
    $today_ymd = date('Ymd');

    if ($date_filter === 'upcoming') {
        $meta_query[] = array(
            'relation' => 'OR',
            array('key' => 'event_start_date', 'value' => $today_ymd, 'compare' => '>='),
            array('key' => 'event_end_date', 'value' => $today_ymd, 'compare' => '>=')
        );
    } elseif ($date_filter === 'past') {
        $meta_query[] = array(
            'relation' => 'AND',
            array('key' => 'event_start_date', 'value' => $today_ymd, 'compare' => '<'),
            array(
                'relation' => 'OR',
                array('key' => 'event_end_date', 'value' => $today_ymd, 'compare' => '<'),
                array('key' => 'event_end_date', 'compare' => 'NOT EXISTS'),
                array('key' => 'event_end_date', 'value' => '', 'compare' => '=')
            )
        );
    }

    if (isset($_GET['hs_v3_campus_filter']) && $_GET['hs_v3_campus_filter'] !== '') {
        $meta_query[] = array('key' => 'target_campus', 'value' => sanitize_text_field($_GET['hs_v3_campus_filter']), 'compare' => 'LIKE');
    }

    if (isset($_GET['hs_v3_category_filter']) && $_GET['hs_v3_category_filter'] !== '') {
        $meta_query[] = array('key' => 'event_category', 'value' => sanitize_text_field($_GET['hs_v3_category_filter']), 'compare' => 'LIKE');
    }

    if (!empty($meta_query)) {
        $query->set('meta_query', $meta_query);
    }

    if (isset($_GET['orderby']) && $_GET['orderby'] === 'event_start_date') {
        $query->set('meta_key', 'event_start_date');
        $query->set('orderby', 'meta_value_num');
    } elseif (!isset($_GET['orderby'])) {
        $query->set('meta_key', 'event_start_date');
        $query->set('orderby', 'meta_value_num');
        $query->set('order', $date_filter === 'past' ? 'DESC' : 'ASC');
    }
}
add_action('pre_get_posts', 'hs_v3_admin_events_filter_query');


// ===================================================
// 8. SMART SORTING FOR "ALL DATES" VIEW
// ===================================================
function hs_v3_admin_events_custom_order($clauses, $query) {
    global $wpdb;
    if (!is_admin() || !$query->is_main_query()) return $clauses;
    if (!isset($query->query['post_type']) || $query->query['post_type'] !== 'events') return $clauses;

    $date_filter = isset($_GET['hs_v3_date_filter']) ? sanitize_text_field($_GET['hs_v3_date_filter']) : 'upcoming';

    if ($date_filter === 'all' && (!isset($_GET['orderby']) || $_GET['orderby'] === 'event_start_date')) {
        $today_ymd = date('Ymd');
        $clauses['join'] .= " 
            LEFT JOIN {$wpdb->postmeta} AS hs_v3_start ON ({$wpdb->posts}.ID = hs_v3_start.post_id AND hs_v3_start.meta_key = 'event_start_date')
            LEFT JOIN {$wpdb->postmeta} AS hs_v3_end ON ({$wpdb->posts}.ID = hs_v3_end.post_id AND hs_v3_end.meta_key = 'event_end_date')
        ";
        $clauses['orderby'] = "
            CASE 
                WHEN COALESCE(NULLIF(hs_v3_end.meta_value, ''), hs_v3_start.meta_value) < '{$today_ymd}' THEN 1
                WHEN hs_v3_start.meta_value > '{$today_ymd}' THEN 3
                ELSE 2
            END ASC,
            CASE 
                WHEN COALESCE(NULLIF(hs_v3_end.meta_value, ''), hs_v3_start.meta_value) < '{$today_ymd}' THEN hs_v3_start.meta_value 
            END DESC,
            hs_v3_start.meta_value ASC
        ";
    }

    return $clauses;
}
add_filter('posts_clauses', 'hs_v3_admin_events_custom_order', 10, 2);


// ===================================================
// 9. ADD "VIEW EVENTS" BUTTON NEXT TO "ADD NEW"
// ===================================================
function hs_v3_add_view_events_button() {
    global $post_type;
    if ($post_type === 'events') {
        $events_url = admin_url('edit.php?post_type=events');
        echo '<script>
        jQuery(document).ready(function($) {
            $(".wrap h1").append("<a href=\"" + "' . esc_url($events_url) . '" + "\" class=\"page-title-action\" style=\"margin-left:5px;\">View Events List</a>");
        });
        </script>';
    }
}
add_action('admin_head-post.php', 'hs_v3_add_view_events_button');
add_action('admin_head-post-new.php', 'hs_v3_add_view_events_button');

// ===================================================
// 10. AJAX INLINE EDITING ENDPOINT
// ===================================================
add_action('wp_ajax_hs_v3_events_inline_update', 'hs_v3_events_inline_update_handler');
function hs_v3_events_inline_update_handler() {
    check_ajax_referer('hs_inline_tax_nonce', 'security');
    if (!current_user_can('edit_posts')) wp_send_json_error('Permission denied.');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $type = isset($_POST['update_type']) ? sanitize_text_field($_POST['update_type']) : '';

    if (!$post_id || !$type) wp_send_json_error('Missing parameters.');

    if ($type === 'category') {
        $cat = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        update_post_meta($post_id, 'event_category', $cat);
        wp_set_object_terms($post_id, $cat, 'hs_event_category');
    } elseif ($type === 'date') {
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        if ($start_date) {
            update_post_meta($post_id, 'event_start_date', date('Ymd', strtotime($start_date)));
        }
        if ($end_date) {
            update_post_meta($post_id, 'event_end_date', date('Ymd', strtotime($end_date)));
        } else {
            delete_post_meta($post_id, 'event_end_date');
        }
    } elseif ($type === 'time') {
        $is_all_day = isset($_POST['is_all_day']) ? intval($_POST['is_all_day']) : 0;
        $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        $end_time = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';
        
        update_post_meta($post_id, 'is_all_day', $is_all_day);
        update_post_meta($post_id, 'event_start_time', $start_time);
        update_post_meta($post_id, 'event_end_time', $end_time);
    }
    
    wp_send_json_success('Updated');
}

// ===================================================
// 11. AJAX INLINE EDITING JS
// ===================================================
add_action('admin_footer-edit.php', 'hs_v3_inject_events_admin_js');
function hs_v3_inject_events_admin_js() {
    global $post_type;
    if ($post_type !== 'events') return;

    $nonce = wp_create_nonce('hs_inline_tax_nonce');

    echo '<style>
    .hs-custom-tooltip .hs-tooltip-text { display: none !important; }
    .hs-global-tooltip {
        position: absolute; background: #1e1e1e; color: #fff; padding: 6px 10px;
        border-radius: 4px; font-size: 12px; font-weight: 500; white-space: normal;
        width: max-content; max-width: 220px; text-align: center;
        z-index: 999999999 !important; box-shadow: 0 3px 8px rgba(0,0,0,0.4);
        pointer-events: none; line-height: 1.3;
    }
    .hs-global-tooltip::after {
        content: ""; position: absolute; top: 100%; left: 50%; margin-left: -5px;
        border-width: 5px; border-style: solid; border-color: #1e1e1e transparent transparent transparent;
    }
    </style>';

    echo '<script>
    jQuery(document).ready(function($) {
        // Global Tooltips
        $(".hs-custom-tooltip").on("mouseenter", function() {
            var $this = $(this);
            var text = $this.find(".hs-tooltip-text").html();
            var offset = $this.offset();
            var $tt = $("<div class=\"hs-global-tooltip\"></div>").html(text).appendTo("body");
            $tt.css({
                top: offset.top - $tt.outerHeight() - 10,
                left: offset.left + ($this.outerWidth() / 2) - ($tt.outerWidth() / 2)
            });
        }).on("mouseleave", function() {
            $(".hs-global-tooltip").remove();
        });
        
        function closeAllEditors() {
            $(".hs-campus-editor, .hs-category-editor, .hs-location-editor, .hs-date-editor, .hs-time-editor").hide();
        }

        // Date UI
        $(".hs-edit-date-btn").on("click", function(e) {
            e.preventDefault();
            closeAllEditors();
            $(this).siblings(".hs-date-editor").show();
        });
        
        $(".hs-cancel-date-btn").on("click", function(e) {
            e.preventDefault();
            $(this).closest(".hs-date-editor").hide();
        });

        $(".hs-save-date-btn").on("click", function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest(".hs-date-editor");
            var $status = $editor.find(".hs-date-ajax-status");
            var post_id = $btn.data("post-id");
            var start_date = $editor.find(".hs-date-start-input").val();
            var end_date = $editor.find(".hs-date-end-input").val();

            if (!start_date) {
                alert("Start Date is required.");
                return;
            }

            $btn.prop("disabled", true);
            $status.text("Saving...").css("color", "#888");

            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "hs_v3_events_inline_update",
                    security: "' . $nonce . '",
                    post_id: post_id,
                    update_type: "date",
                    start_date: start_date,
                    end_date: end_date
                },
                success: function(response) {
                    if (response.success) {
                        $status.text("Saved!").css("color", "#00a32a");
                        setTimeout(function(){ window.location.reload(); }, 500);
                    } else {
                        $status.text("Failed").css("color", "#d63638");
                        $btn.prop("disabled", false);
                    }
                },
                error: function() {
                    $status.text("Error").css("color", "#d63638");
                    $btn.prop("disabled", false);
                }
            });
        });

        // Time UI
        $(".hs-edit-time-btn").on("click", function(e) {
            e.preventDefault();
            closeAllEditors();
            $(this).siblings(".hs-time-editor").show();
        });
        
        $(".hs-cancel-time-btn").on("click", function(e) {
            e.preventDefault();
            $(this).closest(".hs-time-editor").hide();
        });

        $(".hs-time-allday-input").on("change", function() {
            var $wrap = $(this).closest(".hs-time-editor").find(".hs-time-inputs-wrap");
            if ($(this).is(":checked")) {
                $wrap.hide();
            } else {
                $wrap.show();
            }
        });

        $(".hs-save-time-btn").on("click", function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest(".hs-time-editor");
            var $status = $editor.find(".hs-time-ajax-status");
            var post_id = $btn.data("post-id");
            var is_all_day = $editor.find(".hs-time-allday-input").is(":checked") ? 1 : 0;
            var start_time = $editor.find(".hs-time-start-input").val();
            var end_time = $editor.find(".hs-time-end-input").val();

            $btn.prop("disabled", true);
            $status.text("Saving...").css("color", "#888");

            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "hs_v3_events_inline_update",
                    security: "' . $nonce . '",
                    post_id: post_id,
                    update_type: "time",
                    is_all_day: is_all_day,
                    start_time: start_time,
                    end_time: end_time
                },
                success: function(response) {
                    if (response.success) {
                        $status.text("Saved!").css("color", "#00a32a");
                        setTimeout(function(){ window.location.reload(); }, 500);
                    } else {
                        $status.text("Failed").css("color", "#d63638");
                        $btn.prop("disabled", false);
                    }
                },
                error: function() {
                    $status.text("Error").css("color", "#d63638");
                    $btn.prop("disabled", false);
                }
            });
        });

        // Campus UI
        $(".hs-edit-campus-btn").on("click", function(e) {
            e.preventDefault();
            closeAllEditors();
            $(this).siblings(".hs-campus-editor").show();
        });
        
        $(".hs-cancel-campus-btn").on("click", function(e) {
            e.preventDefault();
            $(this).closest(".hs-campus-editor").hide();
        });

        $(".hs-save-campus-btn").on("click", function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest(".hs-campus-editor");
            var $status = $editor.find(".hs-campus-ajax-status");
            var post_id = $btn.data("post-id");
            var val = $editor.find(".hs-campus-radio:checked").val();

            $btn.prop("disabled", true);
            $status.text("Saving...").css("color", "#888");

            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "hs_inline_meta_update",
                    security: "' . $nonce . '",
                    post_id: post_id,
                    meta_key: "target_campus",
                    meta_value: val
                },
                success: function(response) {
                    if (response.success) {
                        $status.text("Saved!").css("color", "#00a32a");
                        setTimeout(function(){ window.location.reload(); }, 500);
                    } else {
                        $status.text("Failed").css("color", "#d63638");
                        $btn.prop("disabled", false);
                    }
                },
                error: function() {
                    $status.text("Error").css("color", "#d63638");
                    $btn.prop("disabled", false);
                }
            });
        });

        // Category UI
        $(".hs-edit-category-btn").on("click", function(e) {
            e.preventDefault();
            closeAllEditors();
            $(this).siblings(".hs-category-editor").show();
        });
        
        $(".hs-cancel-category-btn").on("click", function(e) {
            e.preventDefault();
            $(this).closest(".hs-category-editor").hide();
        });

        $(".hs-save-category-btn").on("click", function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest(".hs-category-editor");
            var $status = $editor.find(".hs-category-ajax-status");
            var post_id = $btn.data("post-id");
            var val = $editor.find(".hs-category-select").val();

            $btn.prop("disabled", true);
            $status.text("Saving...").css("color", "#888");

            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "hs_v3_events_inline_update",
                    security: "' . $nonce . '",
                    post_id: post_id,
                    update_type: "category",
                    category: val
                },
                success: function(response) {
                    if (response.success) {
                        $status.text("Saved!").css("color", "#00a32a");
                        setTimeout(function(){ window.location.reload(); }, 500);
                    } else {
                        $status.text("Failed").css("color", "#d63638");
                        $btn.prop("disabled", false);
                    }
                },
                error: function() {
                    $status.text("Error").css("color", "#d63638");
                    $btn.prop("disabled", false);
                }
            });
        });

        // Location UI
        $(".hs-edit-location-btn").on("click", function(e) {
            e.preventDefault();
            closeAllEditors();
            $(this).siblings(".hs-location-editor").show();
        });
        
        $(".hs-cancel-location-btn").on("click", function(e) {
            e.preventDefault();
            $(this).closest(".hs-location-editor").hide();
        });

        $(".hs-save-location-btn").on("click", function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest(".hs-location-editor");
            var $status = $editor.find(".hs-location-ajax-status");
            var post_id = $btn.data("post-id");
            var val = $editor.find(".hs-location-input").val();

            $btn.prop("disabled", true);
            $status.text("Saving...").css("color", "#888");

            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "hs_inline_meta_update",
                    security: "' . $nonce . '",
                    post_id: post_id,
                    meta_key: "event_location",
                    meta_value: val
                },
                success: function(response) {
                    if (response.success) {
                        $status.text("Saved!").css("color", "#00a32a");
                        setTimeout(function(){ window.location.reload(); }, 500);
                    } else {
                        $status.text("Failed").css("color", "#d63638");
                        $btn.prop("disabled", false);
                    }
                },
                error: function() {
                    $status.text("Error").css("color", "#d63638");
                    $btn.prop("disabled", false);
                }
            });
        });
    });
    </script>';
}
