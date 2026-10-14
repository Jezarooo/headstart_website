<?php

/**
 * Staff-Directory--Admin-Columns
 */
/**
 * =============================================================================
 * HEADSTART STAFF DIRECTORY ADMIN COLUMNS
 * =============================================================================
 * PURPOSE: Logic and execution for STAFF DIRECTORY ADMIN COLUMNS
 * DEPENDENCIES: WordPress core, Divi
 * [TODO: REVIEW_OPTIMIZATIONS - Extract inline JS/CSS, implement Nonces, escape HTML]
 * =============================================================================
 *
 * HeadStart Staff Directory - Admin Enhancements
 * 
 * Description: 
 * Adds custom columns, color-coded badges, and dropdown filters to the 
 * "All Staff" screen in the WordPress Admin dashboard. Includes AJAX inline editing.
 * 
 * Instructions:
 * 1. Install the free "WPCode" or "Code Snippets" plugin.
 * 2. Create a new "PHP Snippet".
 * 3. Paste this file's contents into the snippet box.
 * 4. Set it to "Active" and click Save.
 */

// ---------------------------------------------------------------------------
// 1. REPAIR ACF METADATA (Self-Executing Fix)
// ---------------------------------------------------------------------------
// If profiles were created via CSV or missing hidden ACF keys, the edit screen defaults to Executive.
// This routine quietly restores the missing ACF hidden keys so the editor loads the correct values.
function hs_repair_staff_acf_keys() {
    if (!function_exists('acf_get_field') || get_transient('hs_staff_acf_repaired')) return;

    $staff_posts = get_posts(array('post_type' => 'staff', 'numberposts' => -1, 'post_status' => 'any'));
    $fields_to_repair = array('staff_tier', 'job_title', 'sort_order', 'leadership_sort_order', 'profile_blurb', 'reports_to');
    
    foreach ($staff_posts as $p) {
        foreach ($fields_to_repair as $field_name) {
            $val = get_post_meta($p->ID, $field_name, true);
            if ($val !== '') {
                $acf_field = acf_get_field($field_name);
                if ($acf_field && isset($acf_field['key'])) {
                    update_post_meta($p->ID, '_' . $field_name, $acf_field['key']);
                }
            }
        }
    }
    set_transient('hs_staff_acf_repaired', true, YEAR_IN_SECONDS);
}
add_action('admin_init', 'hs_repair_staff_acf_keys');

// ---------------------------------------------------------------------------
// 2. DEFINE COLUMNS
// ---------------------------------------------------------------------------
function hs_add_staff_admin_columns($columns) {
    // Helper for Tooltips
    $tt = function($text) {
        return '<span class="hs-custom-tooltip" style="position:relative; display:inline-block; margin-left:2px;">
            <span class="dashicons dashicons-editor-help" style="color:#888; font-size:15px; vertical-align:middle; cursor:help;"></span>
            <span class="hs-tooltip-text">' . esc_html($text) . '</span>
        </span>';
    };

    $new_columns = array();
    $new_columns['cb'] = $columns['cb']; // Checkbox
    
    // 1. Leadership Sort (Left of Photo)
    $new_columns['leadership_sort_order'] = 'Lead Sort ' . $tt("Controls the sorting order ONLY on the main 'Leadership Team' tab. Allows you to order Executives independently from their normal departments. Default is 50. Lower numbers go to the top.");
    
    // 2. Identity
    $new_columns['staff_photo'] = 'Photo ' . $tt("The staff member's profile picture. If you leave this empty, a blank placeholder image will show instead.");
    $new_columns['title'] = 'Name ' . $tt("The staff member's name. Long names will automatically shrink so they don't break the layout.");
    $new_columns['job_title'] = 'Job Title ' . $tt("The job title shown in the colored badge under the name. Very long titles will automatically shrink to fit.");
    $new_columns['staff_tier'] = 'Tier ' . $tt("Controls where the staff member is shown. Tiers 1, 2, and 3 automatically appear on the main 'Leadership Team' tab. Tiers 4, 5, and 6 only appear inside the specific campus tabs.");
    
    // 3. Placement
    $new_columns['staff_campus'] = 'Campus ' . $tt("Places the staff member on the correct campus tab. If someone works at both campuses and is set to 'Both Campuses', their profile will show up on both the Chaofah and Cherngtalay pages.");
    $new_columns['staff_display_group'] = 'Display Group ' . $tt("Creates the smaller category labels (like 'Year 1', 'Year 2', or 'Maths') inside a department. Leadership roles automatically move to the top, and support roles move to the bottom. If you leave this blank, they go into a 'General' section.");
    
    // 4. Group Sort (Next to Display Group)
    $new_columns['sort_order'] = 'Group Sort ' . $tt("Controls the sorting order of a staff member WITHIN their specific Display Group. For example, use this to order the teachers inside the 'Year 1' group. Default is 50. Lower numbers go to the top.");
    
    $new_columns['staff_department'] = 'Department ' . $tt("Creates the main drop-down sections. Select multiple to show them in multiple places (e.g., Head of Foundation & Primary).");
    
    return $new_columns;
}
add_filter('manage_staff_posts_columns', 'hs_add_staff_admin_columns');

// ---------------------------------------------------------------------------
// Helper for Inline Edit UI
// ---------------------------------------------------------------------------
function hs_inline_edit_wrapper($post_id, $field_name, $field_type, $current_value, $display_html, $options = array()) {
    $html = '<div class="hs-inline-edit-container" data-post-id="' . esc_attr($post_id) . '" data-field="' . esc_attr($field_name) . '">';
    
    // Display mode
    $html .= '<div class="hs-inline-view">';
    $html .= '<span class="hs-inline-val">' . $display_html . '</span>';
    $html .= '<a href="#" class="hs-inline-edit-trigger dashicons dashicons-edit" title="Edit"></a>';
    $html .= '</div>';
    
    // Edit mode (hidden by default)
    $html .= '<div class="hs-inline-form" style="display:none;">';
    
    if ($field_type === 'text' || $field_type === 'number') {
        $html .= '<input type="' . esc_attr($field_type) . '" class="hs-inline-input" value="' . esc_attr($current_value) . '" style="width:100%; box-sizing:border-box;">';
    } elseif ($field_type === 'select') {
        $html .= '<select class="hs-inline-input" style="width:100%; box-sizing:border-box;">';
        $html .= '<option value="">— Select —</option>';
        foreach ($options as $opt_val => $opt_label) {
            $selected = ($current_value == $opt_val) ? 'selected' : '';
            $html .= '<option value="' . esc_attr($opt_val) . '" ' . $selected . '>' . esc_html($opt_label) . '</option>';
        }
        $html .= '</select>';
    } elseif ($field_type === 'checkboxes') {
        $html .= '<div class="hs-inline-checkboxes" style="max-height: 150px; overflow-y: auto; background: #fff; border: 1px solid #ddd; padding: 5px;">';
        $current_values_array = is_array($current_value) ? $current_value : array();
        foreach ($options as $opt_val => $opt_label) {
            $checked = in_array($opt_val, $current_values_array) ? 'checked' : '';
            $html .= '<label style="display:block; margin-bottom:2px;"><input type="checkbox" class="hs-inline-checkbox" value="' . esc_attr($opt_val) . '" ' . $checked . '> ' . esc_html($opt_label) . '</label>';
        }
        $html .= '</div>';
    }

    $html .= '<div class="hs-inline-actions" style="margin-top:5px; text-align:right;">';
    $html .= '<span class="spinner hs-inline-spinner" style="float:left; margin:0;"></span>';
    $html .= '<button type="button" class="button button-small hs-inline-cancel">Cancel</button> ';
    $html .= '<button type="button" class="button button-small button-primary hs-inline-save">Save</button>';
    $html .= '</div>';
    $html .= '</div>'; // close form
    $html .= '</div>'; // close container

    return $html;
}

// ---------------------------------------------------------------------------
// 2. POPULATE COLUMNS WITH DATA & COLOR CODING
// ---------------------------------------------------------------------------
function hs_populate_staff_admin_columns($column, $post_id) {
    // Reusable Options Lookups
    static $campuses = null;
    static $departments = null;
    static $display_groups = null;
    static $tiers = array(
        '1. Executive' => '1. Executive',
        '2. School Director' => '2. School Director',
        '3. Senior Management' => '3. Senior Management',
        '4. Department Head / Manager' => '4. Department Head / Manager',
        '5. Teacher / Coach' => '5. Teacher / Coach',
        '6. Assistant / Nanny / Technician' => '6. Assistant / Nanny / Technician',
        '7. Secretary / Admin' => '7. Secretary / Admin'
    );

    if ($campuses === null) {
        $campuses = array();
        $terms = get_terms(array('taxonomy' => 'staff_campus', 'hide_empty' => false));
        if (!is_wp_error($terms)) foreach ($terms as $t) $campuses[$t->slug] = $t->name;
    }
    if ($departments === null) {
        $departments = array();
        $terms = get_terms(array('taxonomy' => 'staff_department', 'hide_empty' => false));
        if (!is_wp_error($terms)) foreach ($terms as $t) $departments[$t->slug] = $t->name;
    }
    if ($display_groups === null) {
        $display_groups = array();
        $terms = get_terms(array('taxonomy' => 'staff_display_group', 'hide_empty' => false));
        if (!is_wp_error($terms)) foreach ($terms as $t) $display_groups[$t->slug] = $t->name;
    }

    switch ($column) {
        
        case 'staff_photo':
            if (has_post_thumbnail($post_id)) {
                echo get_the_post_thumbnail($post_id, array(50, 50), array('style' => 'border-radius: 5px; object-fit: cover; width: 40px; height: 40px; border: 1px solid #e2e8f0;'));
            } else {
                echo '<div style="width: 40px; height: 40px; background: #edf2f7; border-radius: 5px; border: 1px dashed #cbd5e0; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #a0aec0;">No Img</div>';
            }
            break;

        case 'job_title':
            $title = get_field('job_title', $post_id);
            $display = $title ? esc_html($title) : '<em>—</em>';
            echo hs_inline_edit_wrapper($post_id, 'job_title', 'text', $title, $display);
            break;

        case 'staff_tier':
            $tier = get_field('staff_tier', $post_id);
            $display = '<em>—</em>';
            if ($tier) {
                $color = '#4a5568'; $bg = '#edf2f7'; $border = '#e2e8f0';
                if (strpos($tier, '1.') !== false || strpos($tier, '2.') !== false || strpos($tier, '3.') !== false) {
                    $color = '#975a16'; $bg = '#fffff0'; $border = '#f6e05e';
                } elseif (strpos($tier, '4.') !== false) {
                    $color = '#553c9a'; $bg = '#faf5ff'; $border = '#d6bcfa';
                } elseif (strpos($tier, '5.') !== false) {
                    $color = '#2b6cb0'; $bg = '#ebf8ff'; $border = '#90cdf4';
                } elseif (strpos($tier, '6.') !== false) {
                    $color = '#2f855a'; $bg = '#f0fff4'; $border = '#9ae6b4';
                } elseif (strpos($tier, '7.') !== false) {
                    $color = '#c53030'; $bg = '#fff5f5'; $border = '#feb2b2';
                }
                $display = '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 700; border-radius: 4px; background-color: ' . $bg . '; color: ' . $color . '; border: 1px solid ' . $border . ';">' . esc_html($tier) . '</span>';
            }
            echo hs_inline_edit_wrapper($post_id, 'staff_tier', 'select', $tier, $display, $tiers);
            break;

        case 'staff_campus':
            $terms = get_the_terms($post_id, 'staff_campus');
            $current_slug = '';
            $display = '<em>—</em>';
            if ($terms && !is_wp_error($terms)) {
                $term = $terms[0]; // Single choice
                $current_slug = $term->slug;
                $name = $term->name;
                if (stripos($name, 'chaofah') !== false) {
                    $display = '<span class="hs-badge-campus hs-campus-chaofah">' . esc_html($name) . '</span>';
                } elseif (stripos($name, 'cherngtalay') !== false) {
                    $display = '<span class="hs-badge-campus hs-campus-cherngtalay">' . esc_html($name) . '</span>';
                } else {
                    $display = '<span class="hs-badge-campus hs-campus-both">' . esc_html($name) . '</span>';
                }
            } else {
                $display = '<span class="hs-missing-data"><i class="fa-solid fa-triangle-exclamation"></i> Missing Campus</span>';
            }
            echo hs_inline_edit_wrapper($post_id, 'staff_campus', 'select', $current_slug, $display, $campuses);
            break;
            
        case 'staff_display_group':
            $terms = get_the_terms($post_id, 'staff_display_group');
            $current_slug = '';
            $display = '<em>—</em>';
            if ($terms && !is_wp_error($terms)) {
                $term = $terms[0]; // Single choice
                $current_slug = $term->slug;
                $display = esc_html($term->name);
            }
            echo hs_inline_edit_wrapper($post_id, 'staff_display_group', 'select', $current_slug, $display, $display_groups);
            break;

        case 'staff_department':
            $terms = get_the_terms($post_id, 'staff_department');
            $current_slugs = array();
            $display = '<em>—</em>';
            if ($terms && !is_wp_error($terms)) {
                $out = array();
                foreach ($terms as $term) {
                    $out[] = esc_html($term->name);
                    $current_slugs[] = $term->slug;
                }
                $display = join(', ', $out);
            } else {
                $tier = get_field('staff_tier', $post_id);
                if ($tier && (strpos($tier, '1.') !== false || strpos($tier, '2.') !== false || strpos($tier, '3.') !== false)) {
                    $display = '<em>—</em>'; // Leadership tiers don't need a department warning
                } else {
                    $display = '<span class="hs-missing-data"><i class="fa-solid fa-triangle-exclamation"></i> Missing Dept</span>';
                }
            }
            echo hs_inline_edit_wrapper($post_id, 'staff_department', 'checkboxes', $current_slugs, $display, $departments);
            break;

        case 'sort_order':
            $sort = get_field('sort_order', $post_id);
            $display = ($sort !== '' && $sort !== null) ? '<span style="font-family: monospace; font-weight: bold; font-size: 13px; color: #718096;">' . esc_html($sort) . '</span>' : '<em>—</em>';
            echo hs_inline_edit_wrapper($post_id, 'sort_order', 'number', $sort, $display);
            break;
            
        case 'leadership_sort_order':
            $sort = get_field('leadership_sort_order', $post_id);
            $display = ($sort !== '' && $sort !== null) ? '<span style="font-family: monospace; font-weight: bold; font-size: 13px; color: #718096;">' . esc_html($sort) . '</span>' : '<em>—</em>';
            echo hs_inline_edit_wrapper($post_id, 'leadership_sort_order', 'number', $sort, $display);
            break;
    }
}
add_action('manage_staff_posts_custom_column', 'hs_populate_staff_admin_columns', 10, 2);

// ---------------------------------------------------------------------------
// 3. MAKE COLUMNS SORTABLE
// ---------------------------------------------------------------------------
function hs_staff_sortable_columns($columns) {
    $columns['staff_tier'] = 'staff_tier';
    $columns['sort_order'] = 'sort_order';
    $columns['leadership_sort_order'] = 'leadership_sort_order';
    return $columns;
}
add_filter('manage_edit-staff_sortable_columns', 'hs_staff_sortable_columns');

// ---------------------------------------------------------------------------
// 4. ADD DROPDOWN FILTERS TO ADMIN BAR
// ---------------------------------------------------------------------------
function hs_admin_staff_filters($post_type = '') {
    global $typenow;
    $current_type = !empty($post_type) ? $post_type : $typenow;
    
    if ($current_type === 'staff') {
        
        // 1. Campus Filter
        $campus_selected = isset($_GET['staff_campus']) ? sanitize_text_field($_GET['staff_campus']) : '';
        echo '<select name="staff_campus" id="hs_campus"><option value="">All Campuses</option>';
        $campuses = get_terms(array('taxonomy' => 'staff_campus', 'hide_empty' => false));
        if (!is_wp_error($campuses)) foreach ($campuses as $campus) echo '<option value="' . esc_attr($campus->slug) . '" ' . selected($campus_selected, $campus->slug, false) . '>' . esc_html($campus->name) . '</option>';
        echo '</select>';

        // 2. Department Dropdown
        $dept_selected = isset($_GET['staff_department']) ? sanitize_text_field($_GET['staff_department']) : '';
        echo '<select name="staff_department" id="hs_department"><option value="">All Departments</option>';
        $db_depts = get_terms(array('taxonomy' => 'staff_department', 'hide_empty' => false));
        if (!is_wp_error($db_depts)) foreach ($db_depts as $d) echo '<option value="' . esc_attr($d->slug) . '" ' . selected($dept_selected, $d->slug, false) . '>' . esc_html($d->name) . '</option>';
        echo '</select>';
        
        // 3. Display Group Filter
        $group_selected = isset($_GET['staff_display_group']) ? sanitize_text_field($_GET['staff_display_group']) : '';
        echo '<select name="staff_display_group" id="hs_display_group"><option value="">All Display Groups</option>';
        $groups = get_terms(array('taxonomy' => 'staff_display_group', 'hide_empty' => false));
        if (!is_wp_error($groups)) foreach ($groups as $group) echo '<option value="' . esc_attr($group->slug) . '" ' . selected($group_selected, $group->slug, false) . '>' . esc_html($group->name) . '</option>';
        echo '</select>';

        // 4. Tier Dropdown
        $tier_selected = isset($_GET['hs_tier']) ? sanitize_text_field($_GET['hs_tier']) : '';
        $tiers = array('1. Executive', '2. School Director', '3. Senior Management', '4. Department Head / Manager', '5. Teacher / Coach', '6. Assistant / Nanny / Technician', '7. Secretary / Admin');
        echo '<select name="hs_tier" id="hs_tier"><option value="">All Tiers</option>';
        foreach ($tiers as $tier) echo '<option value="' . esc_attr($tier) . '" ' . selected($tier_selected, $tier, false) . '>' . esc_html($tier) . '</option>';
        echo '</select>';
    }
}
add_action('restrict_manage_posts', 'hs_admin_staff_filters');

// ---------------------------------------------------------------------------
// 5. PROCESS FILTERS & SORTING
// ---------------------------------------------------------------------------
function hs_admin_staff_filter_query($query) {
    global $pagenow;
    if (is_admin() && $pagenow === 'edit.php' && $query->is_main_query()) {
        $post_type = $query->get('post_type');
        if (empty($post_type) && isset($_GET['post_type'])) $post_type = sanitize_text_field($_GET['post_type']);
        
        if ($post_type === 'staff') {
            // Campus, Display Group, and Department rely entirely on WP Core native query parsing via URL parameters
            if (!empty($_GET['hs_tier'])) {
                $meta_query = $query->get('meta_query');
                if (!is_array($meta_query)) $meta_query = array();
                $meta_query[] = array('key' => 'staff_tier', 'value' => sanitize_text_field($_GET['hs_tier']), 'compare' => '=');
                $query->set('meta_query', $meta_query);
            }
            if (isset($_GET['orderby'])) {
                if ($_GET['orderby'] === 'staff_tier') {
                    $query->set('meta_key', 'staff_tier');
                    $query->set('orderby', 'meta_value');
                } elseif ($_GET['orderby'] === 'sort_order') {
                    $query->set('meta_key', 'sort_order');
                    $query->set('orderby', 'meta_value_num');
                } elseif ($_GET['orderby'] === 'leadership_sort_order') {
                    $query->set('meta_key', 'leadership_sort_order');
                    $query->set('orderby', 'meta_value_num');
                }
            }
        }
    }
}
add_action('pre_get_posts', 'hs_admin_staff_filter_query');

// ---------------------------------------------------------------------------
// 6. FIX COLUMN WIDTHS & INLINE EDIT STYLES (CSS)
// ---------------------------------------------------------------------------
function hs_admin_staff_column_styles() {
    global $post_type;
    if ($post_type === 'staff') {
        echo '<style>
            .column-staff_photo { width: 60px; text-align: center; }
            .column-sort_order, .column-leadership_sort_order { width: 90px; text-align: center; }
            .column-staff_tier { width: 14%; }
            .column-staff_campus { width: 10%; }
            .column-staff_display_group { width: 12%; }
            .column-staff_department { width: 15%; }
            
            /* Visual Upgrades */
            .hs-row-band-1 { background-color: #ffffff !important; }
            .hs-row-band-2 { background-color: #f4f8fc !important; }
            .hs-faded-cell { opacity: 0.2; pointer-events: none; filter: grayscale(100%); }
            
            .hs-badge-campus { 
                display: inline-block; padding: 4px 8px; border-radius: 12px; 
                font-size: 11px; font-weight: 700; white-space: nowrap; line-height: 1; margin: 2px 0;
            }
            .hs-campus-chaofah { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
            .hs-campus-cherngtalay { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
            .hs-campus-both { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
            
            .hs-missing-data { color: #dc2626; font-weight: bold; background: #fee2e2; padding: 3px 6px; border-radius: 4px; border: 1px solid #fecaca; font-size: 11px; }
            
            /* Inline Edit Styles */
            .hs-inline-edit-container { position: relative; }
            .hs-inline-view { display: flex; align-items: center; justify-content: space-between; min-height: 24px; padding: 2px 4px; border-radius: 3px; border: 1px solid transparent; transition: background 0.2s, border 0.2s; }
            .hs-inline-view:hover { background: #f8f9fa; border-color: #e2e4e7; }
            .hs-inline-edit-trigger { color: #a0aec0; text-decoration: none !important; opacity: 0; transition: opacity 0.2s; flex-shrink: 0; margin-left: 5px; }
            .hs-inline-edit-trigger:hover { color: #2271b1; }
            .hs-inline-view:hover .hs-inline-edit-trigger { opacity: 1; }
            .hs-inline-form { background: #f0f0f1; padding: 8px; border: 1px solid #c3c4c7; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: absolute; z-index: 100; min-width: 200px; }
            
            /* Tooltip Styles */
            .hs-custom-tooltip .hs-tooltip-text { display: none !important; }
            .hs-global-tooltip {
                position: absolute; background: #1e1e1e; color: #fff; padding: 6px 10px;
                border-radius: 4px; font-size: 12px; font-weight: 500; white-space: normal;
                width: max-content; max-width: 220px; text-align: center;
                z-index: 999999999 !important; box-shadow: 0 3px 8px rgba(0,0,0,0.4);
                pointer-events: none; line-height: 1.3; text-transform: none;
            }
            .hs-global-tooltip::after {
                content: ""; position: absolute; top: 100%; left: var(--arrow-pos, 50%); margin-left: -5px;
                border-width: 5px; border-style: solid; border-color: #1e1e1e transparent transparent transparent;
            }
        </style>';
    }
}
add_action('admin_head', 'hs_admin_staff_column_styles');

// ---------------------------------------------------------------------------
// 7. INLINE EDIT JAVASCRIPT
// ---------------------------------------------------------------------------
function hs_admin_staff_inline_edit_js() {
    global $post_type;
    if ($post_type === 'staff') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            
            // Global Tooltips
            $('.hs-custom-tooltip').on('mouseenter', function() {
                var $this = $(this);
                var text = $this.find('.hs-tooltip-text').html();
                var offset = $this.offset();
                
                // Inject hidden first to prevent document blowout/scrollbars
                var $tt = $('<div class="hs-global-tooltip"></div>')
                    .html(text)
                    .css({ top: 0, left: 0, visibility: 'hidden' })
                    .appendTo('body');
                
                var windowWidth = $(window).width();
                var ttWidth = $tt.outerWidth();
                var ttHeight = $tt.outerHeight();
                
                var iconCenter = offset.left + ($this.outerWidth() / 2);
                var leftPos = iconCenter - (ttWidth / 2);
                
                // Keep tooltip perfectly on screen
                if (leftPos + ttWidth > windowWidth - 10) {
                    leftPos = windowWidth - ttWidth - 10;
                }
                if (leftPos < 10) {
                    leftPos = 10;
                }
                
                // Keep the little pointer arrow pointed exactly at the icon
                var arrowPos = iconCenter - leftPos;
                if (arrowPos < 10) arrowPos = 10;
                if (arrowPos > ttWidth - 10) arrowPos = ttWidth - 10;
                
                $tt.css({
                    '--arrow-pos': arrowPos + 'px',
                    top: offset.top - ttHeight - 10,
                    left: leftPos,
                    visibility: 'visible'
                });
            }).on('mouseleave', function() {
                $('.hs-global-tooltip').remove();
            });
            
            // Toggle edit form
            $('.hs-inline-edit-trigger').on('click', function(e) {
                e.preventDefault();
                var container = $(this).closest('.hs-inline-edit-container');
                $('.hs-inline-form').hide(); // Hide any open forms
                $('.hs-inline-view').show(); 
                
                container.find('.hs-inline-view').hide();
                container.find('.hs-inline-form').show();
            });
            
            // Cancel edit
            $('.hs-inline-cancel').on('click', function(e) {
                var container = $(this).closest('.hs-inline-edit-container');
                container.find('.hs-inline-form').hide();
                container.find('.hs-inline-view').show();
            });
            
            // Save edit via AJAX
            $('.hs-inline-save').on('click', function(e) {
                var btn = $(this);
                var container = btn.closest('.hs-inline-edit-container');
                var postId = container.data('post-id');
                var fieldName = container.data('field');
                var spinner = container.find('.spinner');
                
                // Get value depending on input type
                var value;
                if (container.find('.hs-inline-checkbox').length > 0) {
                    value = [];
                    container.find('.hs-inline-checkbox:checked').each(function() {
                        value.push($(this).val());
                    });
                } else {
                    value = container.find('.hs-inline-input').val();
                }
                
                btn.prop('disabled', true);
                spinner.addClass('is-active');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hs_inline_edit_staff',
                        post_id: postId,
                        field: fieldName,
                        value: value,
                        nonce: '<?php echo wp_create_nonce("hs_inline_edit_nonce"); ?>'
                    },
                    success: function(response) {
                        btn.prop('disabled', false);
                        spinner.removeClass('is-active');
                        
                        if (response.success) {
                            // Update display HTML
                            container.find('.hs-inline-val').html(response.data.display);
                            // Close form
                            container.find('.hs-inline-form').hide();
                            container.find('.hs-inline-view').show();
                            
                            // Re-apply visual upgrades on the fly
                            if (typeof applyVisualUpgrades === 'function') applyVisualUpgrades();
                            
                            // Visual success feedback
                            var view = container.find('.hs-inline-view');
                            var origBg = view.css('background-color');
                            view.css('background-color', '#e5f9e5');
                            setTimeout(function() { view.css('background-color', ''); }, 1000);
                        } else {
                            alert('Error: ' + (response.data || 'Unknown error'));
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false);
                        spinner.removeClass('is-active');
                        alert('AJAX request failed.');
                    }
                });
            });
            
            // -----------------------------------------
            // Visual Upgrades Script
            // -----------------------------------------
            function applyVisualUpgrades() {
                var currentColor = 'hs-row-band-1';
                var lastGroup = null;

                $('table.wp-list-table tbody tr').each(function() {
                    var $row = $(this);
                    
                    // Group Banding
                    var $groupCell = $row.find('.column-staff_display_group .hs-inline-val');
                    if ($groupCell.length) {
                        var currentGroup = $groupCell.text().trim();
                        if (currentGroup !== lastGroup && currentGroup !== '—') {
                            currentColor = (currentColor === 'hs-row-band-1') ? 'hs-row-band-2' : 'hs-row-band-1';
                            lastGroup = currentGroup;
                        }
                        $row.removeClass('alternate hs-row-band-1 hs-row-band-2').addClass(currentColor);
                    }
                    
                    // Fade out Lead Sort
                    var $tierCell = $row.find('.column-staff_tier .hs-inline-val');
                    if ($tierCell.length) {
                        var tier = $tierCell.text().trim();
                        if (tier && !tier.includes('1.') && !tier.includes('2.') && !tier.includes('3.')) {
                            $row.find('.column-leadership_sort_order').addClass('hs-faded-cell');
                        } else {
                            $row.find('.column-leadership_sort_order').removeClass('hs-faded-cell');
                        }
                    }
                    
                    // Style Sort numbers
                    ['sort_order', 'leadership_sort_order'].forEach(function(col) {
                        var $sortCell = $row.find('.column-' + col + ' .hs-inline-val span');
                        if ($sortCell.length) {
                            var sortVal = parseInt($sortCell.text().trim());
                            if (!isNaN(sortVal)) {
                                if (sortVal < 50) $sortCell.css({'color': '#16a34a', 'font-weight': '900'});
                                else if (sortVal > 50) $sortCell.css({'color': '#ea580c', 'font-weight': '900'});
                                else $sortCell.css({'color': '#718096', 'font-weight': 'bold'});
                            }
                        }
                    });
                });
            }
            
            // Run immediately on load
            applyVisualUpgrades();
            
        });
        </script>
        <?php
    }
}
add_action('admin_footer', 'hs_admin_staff_inline_edit_js');

// ---------------------------------------------------------------------------
// 8. AJAX HANDLER FOR INLINE EDIT
// ---------------------------------------------------------------------------
function hs_ajax_inline_edit_staff() {
    check_ajax_referer('hs_inline_edit_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
    $value = isset($_POST['value']) ? $_POST['value'] : ''; // Arrays allowed for checkboxes
    
    if (!$post_id || !$field) {
        wp_send_json_error('Missing post ID or field name');
    }
    
    $display_html = '<em>—</em>';
    
    // Process based on field type
    if (in_array($field, array('job_title', 'sort_order', 'leadership_sort_order', 'staff_tier'))) {
        // ACF Fields
        if (is_array($value)) {
            $value = map_deep($value, 'sanitize_text_field');
        } else {
            $value = sanitize_text_field($value);
        }
        
        update_field($field, $value, $post_id);
        
        // Prepare updated display HTML
        if ($field === 'job_title') {
            $display_html = $value ? esc_html($value) : '<em>—</em>';
        } elseif ($field === 'sort_order' || $field === 'leadership_sort_order') {
            $display_html = ($value !== '') ? '<span style="font-family: monospace; font-weight: bold; font-size: 13px; color: #718096;">' . esc_html($value) . '</span>' : '<em>—</em>';
        } elseif ($field === 'staff_tier') {
            if ($value) {
                $color = '#4a5568'; $bg = '#edf2f7'; $border = '#e2e8f0';
                if (strpos($value, '1.') !== false || strpos($value, '2.') !== false || strpos($value, '3.') !== false) {
                    $color = '#975a16'; $bg = '#fffff0'; $border = '#f6e05e';
                } elseif (strpos($value, '4.') !== false) {
                    $color = '#553c9a'; $bg = '#faf5ff'; $border = '#d6bcfa';
                } elseif (strpos($value, '5.') !== false) {
                    $color = '#2b6cb0'; $bg = '#ebf8ff'; $border = '#90cdf4';
                } elseif (strpos($value, '6.') !== false) {
                    $color = '#2f855a'; $bg = '#f0fff4'; $border = '#9ae6b4';
                } elseif (strpos($value, '7.') !== false) {
                    $color = '#c53030'; $bg = '#fff5f5'; $border = '#feb2b2';
                }
                $display_html = '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 700; border-radius: 4px; background-color: ' . $bg . '; color: ' . $color . '; border: 1px solid ' . $border . ';">' . esc_html($value) . '</span>';
            }
        }
        
    } elseif (in_array($field, array('staff_campus', 'staff_department', 'staff_display_group'))) {
        // Taxonomies
        if (is_array($value)) {
            $term_slugs = map_deep($value, 'sanitize_text_field');
        } else {
            $term_slugs = $value ? array(sanitize_text_field($value)) : array();
        }
        
        // Term slugs to IDs for wp_set_object_terms
        $term_ids = array();
        foreach ($term_slugs as $slug) {
            $term = get_term_by('slug', $slug, $field);
            if ($term) {
                $term_ids[] = $term->term_id;
            }
        }
        
        $result = wp_set_object_terms($post_id, $term_ids, $field, false);
        
        if (is_wp_error($result)) {
            wp_send_json_error('Failed to save taxonomy: ' . $result->get_error_message());
        }
        
        // Prepare updated display HTML
        $new_terms = get_the_terms($post_id, $field);
        if ($new_terms && !is_wp_error($new_terms)) {
            if ($field === 'staff_department') {
                $out = array();
                foreach ($new_terms as $t) $out[] = esc_html($t->name);
                $display_html = join(', ', $out);
            } elseif ($field === 'staff_campus') {
                $name = $new_terms[0]->name;
                if (stripos($name, 'chaofah') !== false) {
                    $display_html = '<span class="hs-badge-campus hs-campus-chaofah">' . esc_html($name) . '</span>';
                } elseif (stripos($name, 'cherngtalay') !== false) {
                    $display_html = '<span class="hs-badge-campus hs-campus-cherngtalay">' . esc_html($name) . '</span>';
                } else {
                    $display_html = '<span class="hs-badge-campus hs-campus-both">' . esc_html($name) . '</span>';
                }
            } else {
                // staff_display_group
                $display_html = esc_html($new_terms[0]->name);
            }
        }
    } else {
        wp_send_json_error('Invalid field');
    }
    
    // Clear the front-end cache since data was updated
    delete_transient('hs_staff_directory_data');
    
    wp_send_json_success(array('display' => $display_html));
}
add_action('wp_ajax_hs_inline_edit_staff', 'hs_ajax_inline_edit_staff');

// ---------------------------------------------------------------------------
// 9. DEFAULT MULTI-LEVEL SORT (Categorized View)
// ---------------------------------------------------------------------------
function hs_custom_staff_default_sort($clauses, $wp_query) {
    global $wpdb;

    // Only apply in admin, for 'staff' post type, on main query
    if (!is_admin() || !$wp_query->is_main_query() || $wp_query->get('post_type') !== 'staff') {
        return $clauses;
    }

    // If the user has explicitly clicked a column to sort, don't override it.
    if (isset($_GET['orderby'])) {
        return $clauses;
    }

    // Join Campus
    $clauses['join'] .= " 
        LEFT JOIN {$wpdb->term_relationships} AS tr_campus ON ({$wpdb->posts}.ID = tr_campus.object_id)
        LEFT JOIN {$wpdb->term_taxonomy} AS tt_campus ON (tr_campus.term_taxonomy_id = tt_campus.term_taxonomy_id AND tt_campus.taxonomy = 'staff_campus')
        LEFT JOIN {$wpdb->terms} AS t_campus ON (tt_campus.term_id = t_campus.term_id)
    ";

    // Join Department
    $clauses['join'] .= " 
        LEFT JOIN {$wpdb->term_relationships} AS tr_dept ON ({$wpdb->posts}.ID = tr_dept.object_id)
        LEFT JOIN {$wpdb->term_taxonomy} AS tt_dept ON (tr_dept.term_taxonomy_id = tt_dept.term_taxonomy_id AND tt_dept.taxonomy = 'staff_department')
        LEFT JOIN {$wpdb->terms} AS t_dept ON (tt_dept.term_id = t_dept.term_id)
    ";

    // Join Display Group
    $clauses['join'] .= " 
        LEFT JOIN {$wpdb->term_relationships} AS tr_group ON ({$wpdb->posts}.ID = tr_group.object_id)
        LEFT JOIN {$wpdb->term_taxonomy} AS tt_group ON (tr_group.term_taxonomy_id = tt_group.term_taxonomy_id AND tt_group.taxonomy = 'staff_display_group')
        LEFT JOIN {$wpdb->terms} AS t_group ON (tt_group.term_id = t_group.term_id)
    ";

    // Join Tier
    $clauses['join'] .= " 
        LEFT JOIN {$wpdb->postmeta} AS pm_tier ON ({$wpdb->posts}.ID = pm_tier.post_id AND pm_tier.meta_key = 'staff_tier')
    ";

    // Join Leadership Sort Order
    $clauses['join'] .= " 
        LEFT JOIN {$wpdb->postmeta} AS pm_lead_sort ON ({$wpdb->posts}.ID = pm_lead_sort.post_id AND pm_lead_sort.meta_key = 'leadership_sort_order')
    ";

    // Join Group Sort Order
    $clauses['join'] .= " 
        LEFT JOIN {$wpdb->postmeta} AS pm_group_sort ON ({$wpdb->posts}.ID = pm_group_sort.post_id AND pm_group_sort.meta_key = 'sort_order')
    ";

    // Group by Post ID to prevent duplicates if staff have multiple departments
    $clauses['groupby'] = "{$wpdb->posts}.ID";

    // Multi-level sort:
    // 1. Pull Leadership (Tiers 1, 2, 3) to the top using a CASE statement.
    // 2. Sort Leadership by their explicit Lead Sort (empty values get pushed to 9999).
    // 3. Sort everything else by Campus > Dept > Group > Tier
    // 4. Sort internally by Group Sort Order (empty values get pushed to 9999).
    // We use MIN() for taxonomies because of the GROUP BY clause
    $clauses['orderby'] = "
        CASE 
            WHEN pm_tier.meta_value LIKE '1.%' THEN 1
            WHEN pm_tier.meta_value LIKE '2.%' THEN 2
            WHEN pm_tier.meta_value LIKE '3.%' THEN 3
            ELSE 4
        END ASC,
        COALESCE( CAST( NULLIF(pm_lead_sort.meta_value, '') AS DECIMAL ), 9999 ) ASC,
        MIN(t_campus.name) ASC,
        MIN(t_dept.name) ASC,
        MIN(t_group.name) ASC,
        pm_tier.meta_value ASC,
        COALESCE( CAST( NULLIF(pm_group_sort.meta_value, '') AS DECIMAL ), 9999 ) ASC,
        {$wpdb->posts}.post_title ASC
    ";

    return $clauses;
}
add_filter('posts_clauses', 'hs_custom_staff_default_sort', 10, 2);
