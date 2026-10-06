<?php

/**
 * Posts--Admin-Columns
 */
/**
 * ====================================================================
 * ADMIN POST LIST CUSTOMIZER
 * Replaces native taxonomy columns with an interactive AJAX UI,
 * allowing editors to change Website Sections and Campus inline.
 * Also formats Tags as clean "chips" stripping their prefixes.
 * ====================================================================
 */

// 1. Unhook Native Columns & Register Custom Ones
add_filter('manage_post_posts_columns', 'hs_custom_post_columns', 99);
function hs_custom_post_columns($columns) {
    // Hide native taxonomy columns if they are showing
    unset($columns['taxonomy-hs_department']);
    unset($columns['taxonomy-hs_post_campus']);
    unset($columns['tags']); // Native tags
    unset($columns['comments']); // Remove comments column

    // 1.5 Helper for Tooltips
    $tt = function($text) {
        return '<span class="hs-custom-tooltip" style="position:relative; display:inline-block; margin-left:2px;">
            <span class="dashicons dashicons-editor-help" style="color:#888; font-size:15px; vertical-align:middle; cursor:help;"></span>
            <span class="hs-tooltip-text">' . esc_html($text) . '</span>
        </span>';
    };

    // Inject our custom interactive columns before the 'date' column
    $new_columns = array();
    foreach ($columns as $key => $title) {
        if ($key === 'date') {
            $new_columns['hs_department_col'] = 'Website Section ' . $tt('Select the main department/section this post belongs to.');
            $new_columns['hs_thematic_chip_col'] = 'Yellow Badge Override ' . $tt('Optionally change the text printed on the yellow badge.');
            $new_columns['hs_campus_col']     = 'Campus ' . $tt('Select the campus(es) this post is associated with. If you select multiple, the slider chip will display "Both Campuses" automatically.');
            $new_columns['hs_location_col']   = 'Display Location ' . $tt('Only fill this in if the event happened off-campus (e.g. "Bangkok Trip"). Note: This affects the Post Slider and will overwrite the text on the "location chip" at the top of the slider cards. If you leave this blank, the slider will just print the Campus you checked above.');
            $new_columns['hs_tags_col']       = 'Tags ' . $tt('Select relevant thematic tags. To group tags, use a prefix with a colon (e.g., "ACADEMIC: Math"). The prefix will be automatically hidden on the slider chips.');
        }
        $new_columns[$key] = $title;
    }
    return $new_columns;
}

// 2. Output Column Content
add_action('manage_post_posts_custom_column', 'hs_custom_post_column_content', 10, 2);
function hs_custom_post_column_content($column_name, $post_id) {
    // WEBSITE SECTION (Department) Dropdown (Multi-Select)
    if ($column_name === 'hs_department_col') {
        $dept_terms = get_the_terms($post_id, 'hs_department');
        $current_dept_ids = array();
        
        echo '<div class="hs-dept-wrapper" style="position:relative;">';
        
        // Display Current Departments
        echo '<div class="hs-dept-display" style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:6px;">';
        if ($dept_terms && !is_wp_error($dept_terms)) {
            foreach ($dept_terms as $term) {
                $current_dept_ids[] = $term->term_id;
                echo '<span style="background: #eef2f6; border: 1px solid #c9d8e5; color: #003057; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600;">' . esc_html($term->name) . '</span>';
            }
        } else {
            echo '<span style="color:#d63638; font-size:11px;">Required</span>';
        }
        echo '</div>';
        
        // Edit Button
        echo '<a href="#" class="button button-small hs-edit-dept-btn">Edit Section</a>';
        
        // Dropdown Editor (Hidden by default)
        echo '<div class="hs-dept-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:200px; margin-top:5px; border-radius:4px;">';
        
        $all_depts = get_terms(array('taxonomy' => 'hs_department', 'hide_empty' => false));
        echo '<div style="max-height:150px; overflow-y:auto; margin-bottom:10px; border:1px solid #eee; padding:5px;">';
        if (!is_wp_error($all_depts) && !empty($all_depts)) {
            foreach ($all_depts as $term) {
                $checked = in_array($term->term_id, $current_dept_ids) ? 'checked' : '';
                echo '<label style="display:block; margin-bottom:4px; font-size:12px;">';
                echo '<input type="checkbox" class="hs-dept-checkbox" value="' . esc_attr($term->term_id) . '" ' . $checked . ' /> ';
                echo esc_html($term->name);
                echo '</label>';
            }
        }
        echo '</div>';
        
        echo '<button type="button" class="button button-primary hs-save-dept-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
        echo '<button type="button" class="button hs-cancel-dept-btn">Cancel</button>';
        echo '<span class="hs-dept-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
        
        echo '</div>'; // End dropdown
        echo '</div>'; // End wrapper
    }

    // THEMATIC CHIP (Override)
    if ($column_name === 'hs_thematic_chip_col') {
        $chips = get_the_terms($post_id, 'hs_thematic_chip');
        $current_chip_id = 0;
        
        echo '<div class="hs-chip-wrapper" style="position:relative;">';
        
        // Display
        echo '<div class="hs-chip-display" style="margin-bottom:6px;">';
        if ($chips && !is_wp_error($chips) && !empty($chips)) {
            $current_chip_id = $chips[0]->term_id;
            echo '<span style="background: #fffdf0; border: 1px solid #ffd700; color: #b8860b; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600;">' . esc_html($chips[0]->name) . '</span>';
        } else {
            echo '<span style="color:#999; font-size:11px;">(Default)</span>';
        }
        echo '</div>';
        
        echo '<a href="#" class="button button-small hs-edit-chip-btn">Edit</a>';
        
        // Dropdown Editor
        echo '<div class="hs-chip-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:200px; margin-top:5px; border-radius:4px;">';
        
        // Add "None" option
        echo '<label style="display:block; margin-bottom:4px; font-size:12px;">';
        $checked = ($current_chip_id === 0) ? 'checked' : '';
        echo '<input type="radio" name="hs_chip_' . intval($post_id) . '" class="hs-chip-radio" value="" ' . $checked . ' /> (None - Use Default)';
        echo '</label>';
        
        $all_chips = get_terms(array('taxonomy' => 'hs_thematic_chip', 'hide_empty' => false));
        echo '<div style="max-height:150px; overflow-y:auto; margin-bottom:10px; border:1px solid #eee; padding:5px;">';
        if (!is_wp_error($all_chips) && !empty($all_chips)) {
            foreach ($all_chips as $term) {
                $checked = ($term->term_id == $current_chip_id) ? 'checked' : '';
                echo '<label style="display:block; margin-bottom:4px; font-size:12px;">';
                echo '<input type="radio" name="hs_chip_' . intval($post_id) . '" class="hs-chip-radio" value="' . esc_attr($term->term_id) . '" ' . $checked . ' /> ';
                echo esc_html($term->name);
                echo '</label>';
            }
        }
        echo '</div>';
        
        echo '<button type="button" class="button button-primary hs-save-chip-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
        echo '<button type="button" class="button hs-cancel-chip-btn">Cancel</button>';
        echo '<span class="hs-chip-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
        
        echo '</div>'; // End dropdown
        echo '</div>'; // End wrapper
    }

    // CAMPUS Dropdown (Multi-Select)
    if ($column_name === 'hs_campus_col') {
        $campus_terms = get_the_terms($post_id, 'hs_post_campus');
        $current_campus_ids = array();
        
        echo '<div class="hs-campus-wrapper" style="position:relative;">';
        
        // Display Current Campuses
        echo '<div class="hs-campus-display" style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:6px;">';
        if ($campus_terms && !is_wp_error($campus_terms)) {
            foreach ($campus_terms as $term) {
                $current_campus_ids[] = $term->term_id;
                
                // Color code the admin chips
                $bg = '#e5f0e9'; $border = '#b2d6c1'; $text = '#004d22'; // Default (Green)
                if (stripos($term->name, 'Chaofah') !== false) {
                    $bg = '#e5f0fa'; $border = '#b2d6f2'; $text = '#00438a'; // Blue
                } else if (stripos($term->name, 'Cherngtalay') !== false) {
                    $bg = '#fdf2e8'; $border = '#f5d5b8'; $text = '#a65d14'; // Orange
                }
                
                echo '<span style="background: ' . $bg . '; border: 1px solid ' . $border . '; color: ' . $text . '; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600;">' . esc_html($term->name) . '</span>';
            }
        } else {
            echo '<span style="color:#d63638; font-size:11px;">Required</span>';
        }
        echo '</div>';
        
        // Edit Button
        echo '<a href="#" class="button button-small hs-edit-campus-btn">Edit</a>';
        
        // Dropdown Editor (Hidden)
        echo '<div class="hs-campus-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:220px; margin-top:5px; border-radius:4px;">';
        
        $all_campuses = get_terms(array('taxonomy' => 'hs_post_campus', 'hide_empty' => false));
        echo '<div style="max-height:150px; overflow-y:auto; margin-bottom:10px; border:1px solid #eee; padding:5px;">';
        if (!is_wp_error($all_campuses) && !empty($all_campuses)) {
            foreach ($all_campuses as $term) {
                $checked = in_array($term->term_id, $current_campus_ids) ? 'checked' : '';
                echo '<label style="display:block; margin-bottom:4px; font-size:12px;">';
                echo '<input type="checkbox" class="hs-campus-checkbox" value="' . esc_attr($term->term_id) . '" ' . $checked . ' /> ';
                echo esc_html($term->name);
                echo '</label>';
            }
        }
        echo '</div>';
        
        echo '<button type="button" class="button button-primary hs-save-campus-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
        echo '<button type="button" class="button hs-cancel-campus-btn">Cancel</button>';
        echo '<span class="hs-campus-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
        
        echo '</div>'; // End dropdown
        echo '</div>'; // End wrapper
    }

    // TAGS Chips (Prefix Stripped)
    if ($column_name === 'hs_tags_col') {
        $tags = get_the_terms($post_id, 'post_tag');
        $current_tag_ids = array();
        
        echo '<div class="hs-tags-wrapper" style="position:relative;">';
        
        // 1. Display Chips
        echo '<div class="hs-tags-display" style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:6px;">';
        if ($tags && !is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $current_tag_ids[] = $tag->term_id;
                $clean_name = $tag->name;
                if (strpos($clean_name, ':') !== false) {
                    $parts = explode(':', $clean_name, 2);
                    $clean_name = trim($parts[1]);
                }
                echo '<span style="background: #eef2f6; border: 1px solid #c9d8e5; color: #003057; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600;">' . esc_html($clean_name) . '</span>';
            }
        } else {
            echo '<span style="color:#999; font-size:11px;">No tags</span>';
        }
        echo '</div>';
        
        // 2. Edit Button
        echo '<a href="#" class="button button-small hs-edit-tags-btn">Edit Tags</a>';
        
        // 3. Dropdown Editor (Hidden by default)
        echo '<div class="hs-tags-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:220px; margin-top:5px; border-radius:4px;">';
        
        $all_tags = get_terms(array('taxonomy' => 'post_tag', 'hide_empty' => false));
        echo '<div style="max-height:150px; overflow-y:auto; margin-bottom:10px; border:1px solid #eee; padding:5px;">';
        if (!is_wp_error($all_tags) && !empty($all_tags)) {
            foreach ($all_tags as $term) {
                $checked = in_array($term->term_id, $current_tag_ids) ? 'checked' : '';
                echo '<label style="display:block; margin-bottom:4px; font-size:12px;">';
                echo '<input type="checkbox" class="hs-tag-checkbox" value="' . esc_attr($term->term_id) . '" ' . $checked . ' /> ';
                echo esc_html($term->name); // Show full name in dropdown (e.g. ACADEMIC: Math)
                echo '</label>';
            }
        }
        echo '</div>';
        
        echo '<div style="margin-bottom:8px; border-top:1px solid #eee; padding-top:8px;">';
        echo '<input type="text" class="hs-new-tag-input" placeholder="+ Type new tag name" style="width:100%; font-size:11px; padding:3px 5px;" />';
        echo '<p style="font-size:10px; color:#888; margin:2px 0 0 0; line-height:1.2;">Comma separate multiple tags.</p>';
        echo '</div>';
        
        echo '<button type="button" class="button button-primary hs-save-tags-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
        echo '<button type="button" class="button hs-cancel-tags-btn">Cancel</button>';
        echo '<span class="hs-tags-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
        
        echo '</div>'; // End dropdown
        echo '</div>'; // End wrapper
    }

    // DISPLAY LOCATION (ACF Override)
    if ($column_name === 'hs_location_col') {
        $location = get_field('hs_location_override', $post_id);
        
        echo '<div class="hs-location-wrapper" style="position:relative;">';
        
        echo '<div class="hs-location-display" style="margin-bottom:6px;">';
        if (!empty($location)) {
            echo '<span style="font-weight:600; font-size:12px; color:#003057;">' . esc_html($location) . '</span>';
        } else {
            echo '<span style="color:#999; font-size:11px;">(Default)</span>';
        }
        echo '</div>';
        
        echo '<a href="#" class="button button-small hs-edit-location-btn">Edit</a>';
        
        echo '<div class="hs-location-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:220px; margin-top:5px; border-radius:4px;">';
        echo '<input type="text" class="hs-location-input" value="' . esc_attr($location) . '" style="width:100%; margin-bottom:10px;" placeholder="Leave blank for default" />';
        echo '<button type="button" class="button button-primary hs-save-location-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
        echo '<button type="button" class="button hs-cancel-location-btn">Cancel</button>';
        echo '<span class="hs-location-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
        
        echo '</div>';
        echo '</div>';
    }
}

// 3. Register Filter Dropdowns at the top of the list
add_action('restrict_manage_posts', 'hs_add_admin_filters', 10, 2);
function hs_add_admin_filters($post_type, $which) {
    if ($post_type !== 'post') return;

    // Department Filter
    $dept_tax = get_taxonomy('hs_department');
    if ($dept_tax) {
        wp_dropdown_categories(array(
            'show_option_all' => $dept_tax->labels->all_items,
            'taxonomy'        => 'hs_department',
            'name'            => 'hs_department',
            'orderby'         => 'name',
            'selected'        => isset($_GET['hs_department']) ? $_GET['hs_department'] : '',
            'show_count'      => true,
            'hide_empty'      => false,
            'value_field'     => 'slug',
        ));
    }

    // Campus Filter
    $campus_tax = get_taxonomy('hs_post_campus');
    if ($campus_tax) {
        wp_dropdown_categories(array(
            'show_option_all' => $campus_tax->labels->all_items,
            'taxonomy'        => 'hs_post_campus',
            'name'            => 'hs_post_campus',
            'orderby'         => 'name',
            'selected'        => isset($_GET['hs_post_campus']) ? $_GET['hs_post_campus'] : '',
            'show_count'      => true,
            'hide_empty'      => false,
            'value_field'     => 'slug',
        ));
    }
}

// 4. Register Sortable Columns
add_filter('manage_edit-post_sortable_columns', 'hs_register_sortable_columns');
function hs_register_sortable_columns($columns) {
    $columns['hs_department_col'] = 'hs_department';
    $columns['hs_campus_col']     = 'hs_post_campus';
    return $columns;
}

// Ensure WordPress actually sorts by these taxonomies (basic implementation)
add_action('pre_get_posts', 'hs_custom_orderby');
function hs_custom_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    
    $orderby = $query->get('orderby');
    if ($orderby == 'hs_department' || $orderby == 'hs_post_campus') {
        // WordPress native taxonomy sorting requires complex SQL clauses.
        // We will default to standard post ordering, as full taxonomy string 
        // sorting in the admin column usually requires a custom JOIN.
        // However, the filter dropdowns provide the exact utility needed!
    }
}

// 5. AJAX Handler for Inline Updates
add_action('wp_ajax_hs_inline_tax_update', 'hs_ajax_inline_tax_update');
function hs_ajax_inline_tax_update() {
    check_ajax_referer('hs_inline_tax_nonce', 'security');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied.');
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    
    if (!$post_id || !$taxonomy) {
        wp_send_json_error('Missing parameters.');
    }

    // Determine if we are receiving a single ID (Campus/Dept) or an array (Tags)
    $terms_to_set = array();
    
    if (isset($_POST['term_ids']) && is_array($_POST['term_ids'])) {
        // Multi-select (Tags)
        foreach ($_POST['term_ids'] as $val) {
            $val = trim($val);
            if (is_numeric($val)) {
                $terms_to_set[] = intval($val);
            } elseif (!empty($val)) {
                // Allows creating new tags via strings
                $terms_to_set[] = sanitize_text_field($val);
            }
        }
    } elseif (isset($_POST['term_id'])) {
        // Single-select (Campus, Dept)
        $term_id = intval($_POST['term_id']);
        if ($term_id !== 0) {
            $terms_to_set = array($term_id);
        }
    }

    // Set the terms (replaces existing terms)
    $result = wp_set_object_terms($post_id, $terms_to_set, $taxonomy, false);

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    wp_send_json_success('Updated');
}

// 5.5 AJAX Handler for Meta Updates
add_action('wp_ajax_hs_inline_meta_update', 'hs_ajax_inline_meta_update');
function hs_ajax_inline_meta_update() {
    check_ajax_referer('hs_inline_tax_nonce', 'security');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied.');
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $meta_key = isset($_POST['meta_key']) ? sanitize_text_field($_POST['meta_key']) : '';
    $meta_value = isset($_POST['meta_value']) ? sanitize_text_field($_POST['meta_value']) : '';
    
    if (!$post_id || !$meta_key) {
        wp_send_json_error('Missing parameters.');
    }

    update_field($meta_key, $meta_value, $post_id);

    wp_send_json_success('Updated');
}

// 6. Inject Javascript for AJAX calls
add_action('admin_footer-edit.php', 'hs_inject_inline_tax_js');
function hs_inject_inline_tax_js() {
    global $post_type;
    if ($post_type !== 'post') return;
    ?>
    <style>
    /* Global JS Tooltips (Appended to body to bypass clipping) */
    .hs-custom-tooltip .hs-tooltip-text {
        display: none !important; /* Hide the inline text */
    }
    
    .hs-global-tooltip {
        position: absolute;
        background: #1e1e1e;
        color: #fff;
        padding: 6px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
        white-space: normal;
        width: max-content;
        max-width: 220px;
        text-align: center;
        z-index: 999999999 !important;
        box-shadow: 0 3px 8px rgba(0,0,0,0.4);
        pointer-events: none;
        line-height: 1.3;
        text-transform: none;
    }
    
    .hs-global-tooltip::after {
        content: "";
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: #1e1e1e transparent transparent transparent;
    }
    </style>
    <script>
    jQuery(document).ready(function($) {
        
        // 0. Global Tooltips (Bypasses all table clipping)
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
        
        // 1. Multi-Select Department Dropdown UI
        
        // Open Editor
        $('.hs-edit-dept-btn').on('click', function(e) {
            e.preventDefault();
            $('.hs-campus-editor, .hs-tags-editor, .hs-location-editor, .hs-dept-editor, .hs-chip-editor').hide();
            $(this).siblings('.hs-dept-editor').show();
        });
        
        // Close Editor
        $('.hs-cancel-dept-btn').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.hs-dept-editor').hide();
        });

        // Save Departments
        $('.hs-save-dept-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest('.hs-dept-editor');
            var $status = $editor.find('.hs-dept-ajax-status');
            var post_id = $btn.data('post-id');
            
            var selected_depts = [];
            $editor.find('.hs-dept-checkbox:checked').each(function() {
                selected_depts.push($(this).val());
            });

            $btn.prop('disabled', true);
            $status.text('Saving...').css('color', '#888');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hs_inline_tax_update',
                    security: '<?php echo wp_create_nonce("hs_inline_tax_nonce"); ?>',
                    post_id: post_id,
                    taxonomy: 'hs_department',
                    term_ids: selected_depts
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('Saved! Reloading...').css('color', '#00a32a');
                        setTimeout(function(){ location.reload(); }, 600);
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

        // 2. Multi-Select Tags Dropdown UI
        
        // Open Editor
        $('.hs-edit-tags-btn').on('click', function(e) {
            e.preventDefault();
            $('.hs-campus-editor, .hs-tags-editor, .hs-location-editor, .hs-dept-editor, .hs-chip-editor').hide(); // Hide any other open ones
            $(this).siblings('.hs-tags-editor').show();
        });
        
        // Close Editor
        $('.hs-cancel-tags-btn').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.hs-tags-editor').hide();
        });

        // Save Tags
        $('.hs-save-tags-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest('.hs-tags-editor');
            var $status = $editor.find('.hs-tags-ajax-status');
            var post_id = $btn.data('post-id');
            
            var selected_tags = [];
            $editor.find('.hs-tag-checkbox:checked').each(function() {
                selected_tags.push($(this).val());
            });
            
            var new_tags = $editor.find('.hs-new-tag-input').val().trim();
            if (new_tags !== '') {
                // Split by comma if they typed multiple
                var tags_array = new_tags.split(',');
                $.each(tags_array, function(i, tag) {
                    if (tag.trim() !== '') {
                        selected_tags.push(tag.trim());
                    }
                });
            }

            $btn.prop('disabled', true);
            $status.text('Saving...').css('color', '#888');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hs_inline_tax_update',
                    security: '<?php echo wp_create_nonce("hs_inline_tax_nonce"); ?>',
                    post_id: post_id,
                    taxonomy: 'post_tag',
                    term_ids: selected_tags
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('Saved! Reloading...').css('color', '#00a32a');
                        setTimeout(function(){
                            window.location.reload(); // Reload to refresh chips
                        }, 500);
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

        // 3. Multi-Select Campus Dropdown UI
        $('.hs-edit-campus-btn').on('click', function(e) {
            e.preventDefault();
            $('.hs-campus-editor, .hs-tags-editor, .hs-location-editor, .hs-dept-editor, .hs-chip-editor').hide();
            $(this).siblings('.hs-campus-editor').show();
        });
        
        $('.hs-cancel-campus-btn').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.hs-campus-editor').hide();
        });

        $('.hs-save-campus-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest('.hs-campus-editor');
            var $status = $editor.find('.hs-campus-ajax-status');
            var post_id = $btn.data('post-id');
            
            var selected_campuses = [];
            $editor.find('.hs-campus-checkbox:checked').each(function() {
                selected_campuses.push($(this).val());
            });

            $btn.prop('disabled', true);
            $status.text('Saving...').css('color', '#888');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hs_inline_tax_update',
                    security: '<?php echo wp_create_nonce("hs_inline_tax_nonce"); ?>',
                    post_id: post_id,
                    taxonomy: 'hs_post_campus',
                    term_ids: selected_campuses
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('Saved! Reloading...').css('color', '#00a32a');
                        setTimeout(function(){
                            window.location.reload();
                        }, 500);
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

        // 4. Location Override UI
        $('.hs-edit-location-btn').on('click', function(e) {
            e.preventDefault();
            $('.hs-campus-editor, .hs-tags-editor, .hs-location-editor, .hs-dept-editor, .hs-chip-editor').hide();
            $(this).siblings('.hs-location-editor').show();
        });
        
        $('.hs-cancel-location-btn').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.hs-location-editor').hide();
        });

        $('.hs-save-location-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest('.hs-location-editor');
            var $status = $editor.find('.hs-location-ajax-status');
            var post_id = $btn.data('post-id');
            var val = $editor.find('.hs-location-input').val();

            $btn.prop('disabled', true);
            $status.text('Saving...').css('color', '#888');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hs_inline_meta_update',
                    security: '<?php echo wp_create_nonce("hs_inline_tax_nonce"); ?>',
                    post_id: post_id,
                    meta_key: 'hs_location_override',
                    meta_value: val
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('Saved! Reloading...').css('color', '#00a32a');
                        setTimeout(function(){
                            window.location.reload();
                        }, 500);
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
        
        // List View Divi Gatekeeper
        $('a').on('click', function(e) {
            var $link = $(this);
            var isDivi = $link.hasClass('edit_with_builder') || ($link.attr('href') && $link.attr('href').indexOf('et_fb=1') > -1);
            
            if (isDivi) {
                var $row = $link.closest('tr');
                if ($row.length) {
                    var title = $row.find('.row-title').text().trim();
                    var deptDisplay = $row.find('.hs-dept-display').text().trim();
                    var campusDisplay = $row.find('.hs-campus-display').text().trim();
                    var locOverride = $row.find('.hs-location-display').text().trim();

                    var isTitleOk = title !== '' && title !== '(no title)' && title !== 'Auto Draft';
                    var isDeptOk = deptDisplay !== 'Required';
                    var isCampusOk = campusDisplay !== 'Required' || locOverride !== '(Default)';

                    if (isTitleOk && isDeptOk && isCampusOk) {
                        return true; // Let them through
                    }

                    e.preventDefault();
                    e.stopImmediatePropagation();
                    
                    var debugMsg = "🔒 ACTION DENIED: Please complete all required fields before entering the Divi Builder.\n\nMissing:\n";
                    if (!isTitleOk) debugMsg += "❌ Post Title\n";
                    if (!isDeptOk) debugMsg += "❌ Website Section (Department)\n";
                    if (!isCampusOk) debugMsg += "❌ Campus Location\n";
                    
                    alert(debugMsg);
                    return false;
                }
            }
        });


        // 5. Chip Override UI
        $('.hs-edit-chip-btn').on('click', function(e) {
            e.preventDefault();
            $('.hs-campus-editor, .hs-tags-editor, .hs-location-editor, .hs-dept-editor, .hs-chip-editor').hide();
            $(this).siblings('.hs-chip-editor').show();
        });
        
        $('.hs-cancel-chip-btn').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.hs-chip-editor').hide();
        });

        $('.hs-save-chip-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest('.hs-chip-editor');
            var $status = $editor.find('.hs-chip-ajax-status');
            var post_id = $btn.data('post-id');
            var selected_chip = $editor.find('.hs-chip-radio:checked').val();
            
            var selected_chips = [];
            if (selected_chip) {
                selected_chips.push(selected_chip);
            }

            $btn.prop('disabled', true);
            $status.text('Saving...').css('color', '#888');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hs_inline_tax_update',
                    security: '<?php echo wp_create_nonce("hs_inline_tax_nonce"); ?>',
                    post_id: post_id,
                    taxonomy: 'hs_thematic_chip',
                    term_ids: selected_chips
                },
                success: function(response) {
                    if (response.success) {
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
