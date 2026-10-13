<?php

/**
 * Staff-Directory--Shortcode-Engine
 */
/**
 * =============================================================================
 * HEADSTART STAFF DIRECTORY SHORTCODE ENGINE
 * =============================================================================
 * PURPOSE: Logic and execution for STAFF DIRECTORY SHORTCODE ENGINE
 * DEPENDENCIES: WordPress core, Divi
 * [TODO: REVIEW_OPTIMIZATIONS - Extract inline JS/CSS, implement Nonces, escape HTML]
 * =============================================================================
 *
 * Plugin Name: HeadStart Staff Directory
 * Description: Renders the staff directory via shortcode [hs_staff_directory]
 * Version: 1.0
 * Author: Antigravity
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register Shortcode
add_shortcode('hs_staff_directory', 'hs_staff_directory_shortcode');

// Clear Staff Directory Cache on Post Save or Taxonomy Update
function hs_clear_staff_directory_cache($post_id = null) {
    if ($post_id && get_post_type($post_id) !== 'staff') {
        return;
    }
    delete_transient('hs_staff_directory_data');
}
add_action('save_post_staff', 'hs_clear_staff_directory_cache');

// Also clear cache when taxonomies are updated (e.g., Quick Edit, drag-and-drop builder)
add_action('set_object_terms', function($object_id, $terms, $tt_ids, $taxonomy) {
    if (in_array($taxonomy, array('staff_department', 'staff_display_group', 'staff_campus'))) {
        hs_clear_staff_directory_cache();
    }
}, 10, 4);

function hs_staff_directory_shortcode() {
    // CSS and JS have been extracted to headstart-core.css and headstart-core.js

    // 2. Fetch Data (with Transient Caching)
    $staff_data = get_transient('hs_staff_directory_data');

    if (false === $staff_data) {
        $staff_data = array();
        
        $args = array(
            'post_type' => 'staff',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'menu_order title',
            'order' => 'ASC'
        );
        
        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                
                // Basic ACF Fields
                $title = get_field('job_title');
                $blurb = get_field('profile_blurb');
                $tier = get_field('staff_tier');
                
                // Bulletproof data fetch (tries ACF, falls back to raw database if ACF is unregistered)
                $sort = get_field('sort_order');
                if ($sort === '' || $sort === null || $sort === false) $sort = get_post_meta(get_the_ID(), 'sort_order', true);
                
                $leadership_sort = get_field('leadership_sort_order');
                if ($leadership_sort === '' || $leadership_sort === null || $leadership_sort === false) $leadership_sort = get_post_meta(get_the_ID(), 'leadership_sort_order', true);
                
                $email = get_field('staff_email');
                
                // Handle Relational Post Object for Secretaries
                // Raw meta, not get_field(): rows imported by CSV hold the manager's name, and
                // ACF's post_object formatter turns a name into false. Edited rows hold a post ID.
                $reports_to = get_post_meta(get_the_ID(), 'reports_to', true);
                if (is_array($reports_to)) $reports_to = reset($reports_to);
                $manager = '';
                if (is_numeric($reports_to)) {
                    $manager = html_entity_decode(get_the_title((int)$reports_to), ENT_QUOTES, 'UTF-8');
                } elseif (is_string($reports_to)) {
                    $manager = html_entity_decode(trim($reports_to), ENT_QUOTES, 'UTF-8');
                }

                // Taxonomies (Extracting the names of the terms)
                $campus_terms = wp_get_post_terms(get_the_ID(), 'staff_campus', array('fields' => 'names'));
                $campuses = (!empty($campus_terms) && !is_wp_error($campus_terms)) ? array_map('html_entity_decode', $campus_terms) : array('Both Campuses');

                $dept_terms = wp_get_post_terms(get_the_ID(), 'staff_department', array('fields' => 'names'));
                $depts = (!empty($dept_terms) && !is_wp_error($dept_terms)) ? array_map('html_entity_decode', $dept_terms) : array('');

                $group_terms = wp_get_post_terms(get_the_ID(), 'staff_display_group', array('fields' => 'names'));
                $group = (!empty($group_terms) && !is_wp_error($group_terms)) ? html_entity_decode($group_terms[0]) : '';

                // Featured Image
                $image = get_the_post_thumbnail_url(get_the_ID(), 'large');

                // Build Staff Object exactly as the JS engine expects
                // Loop through all assigned Campuses and Departments to clone the profile
                foreach ($campuses as $c) {
                    foreach ($depts as $d) {
                        $staff_data[] = array(
                            'name' => html_entity_decode(get_the_title(), ENT_QUOTES, 'UTF-8'),
                            'title' => $title ? $title : '',
                            'blurb' => $blurb ? $blurb : '',
                            'tier' => $tier ? $tier : '',
                            'sort' => $sort !== '' && $sort !== null ? (int)$sort : 999,
                            'leadershipSort' => $leadership_sort !== '' && $leadership_sort !== null ? (int)$leadership_sort : 999,
                            'campus' => $c,
                            'department' => $d,
                            'group' => $group,
                            'reportsTo' => $manager,
                            'email' => $email ? $email : '',
                            'image' => $image ? $image : ''
                        );
                    }
                }
            }
            wp_reset_postdata();
        }
        
        // Save the transient for 12 hours
        set_transient('hs_staff_directory_data', $staff_data, 12 * HOUR_IN_SECONDS);
    }

    // Get the hierarchical directory structure to pass to JS
    $structure = get_option('hs_directory_structure', array());

    // 4. Output HTML Shell
    ob_start();
    ?>
    <script>
        // Inject data securely using Base64 to bypass strict WPCodeBox WAF and prevent HTML parsing errors
        window.hsStaffData = JSON.parse(atob("<?php echo base64_encode(wp_json_encode($staff_data)); ?>") || "[]");
        window.hsDirectoryStructure = JSON.parse(atob("<?php echo base64_encode(wp_json_encode($structure)); ?>") || "{}");
    </script>
    <div class="hs-staff-directory">
        <!-- The JS engine will inject the Option 3 toolbar here -->
        <div id="hs-header-inject"></div>

        <!-- The JS engine will build the accordion tetris layout here -->
        <div id="main-staff-grid"></div>

        <!-- Modal Popup Template -->
        <div class="hs-staff-modal-overlay" id="staff-modal-overlay">
            <button class="hs-modal-close" id="staff-modal-close">
                <span class="hs-modal-close-text">CLOSE</span>
                <span class="hs-modal-close-icon">&times;</span>
            </button>
            <div class="hs-staff-modal">
                <div class="hs-modal-img-col">
                    <img src="" alt="Staff Profile" id="modal-img">
                </div>
                <div class="hs-modal-content-col">
                    <h3 class="hs-staff-name" id="modal-name" style="text-align: left; font-size:1.5rem; margin-bottom:5px;"></h3>
                    <h4 class="hs-staff-title" id="modal-title" style="font-size:1rem; margin-bottom:20px;"></h4>
                    
                    <div class="hs-modal-meta-row">
                        <div class="hs-modal-meta-item">
                            <svg class="hs-modal-svg-icon" viewBox="0 0 640 512" width="16" height="16" fill="currentColor"><path d="M144 160c-44.2 0-80 35.8-80 80s35.8 80 80 80 80-35.8 80-80-35.8-80-80-80zm352 0c-44.2 0-80 35.8-80 80s35.8 80 80 80 80-35.8 80-80-35.8-80-80-80zM320 48c-61.9 0-112 50.1-112 112s50.1 112 112 112 112-50.1 112-112S381.9 48 320 48zM171.1 356c-31.1-16.7-67.7-25.1-104-22.3C27.8 337.2 0 370 0 409.3V464c0 26.5 21.5 48 48 48h138.8c-12-18-18.8-39.6-18.8-62.6v-34.5c0-19.9 8-38.9 22.3-52.9l-19.2-6zM468.9 356l-19.2 6c14.3 14 22.3 33 22.3 52.9v34.5c0 23-6.8 44.6-18.8 62.6H592c26.5 0 48-21.5 48-48v-54.7c0-39.3-27.8-72.1-67.1-75.6-36.3-2.8-72.9 5.6-104 22.3zM320 320c-70.7 0-128 57.3-128 128v18.7c0 24.9 19.8 45.3 44.7 45.3h166.6c24.9 0 44.7-20.4 44.7-45.3V448c0-70.7-57.3-128-128-128z"/></svg>
                            <span id="modal-dept"></span>
                        </div>
                        <div class="hs-modal-meta-item">
                            <svg class="hs-modal-svg-icon" viewBox="0 0 384 512" width="16" height="16" fill="currentColor"><path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/></svg>
                            <span id="modal-campus"></span>
                        </div>
                    </div>
                    
                    <div class="hs-modal-blurb" id="modal-blurb"></div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// 5. DRAG-AND-DROP DISPLAY GROUP ORDERING
// ---------------------------------------------------------------------------

// Register Menu
function hs_staff_group_ordering_menu() {
    add_submenu_page(
        'edit.php?post_type=staff',
        'Directory Builder',
        'Directory Builder',
        'manage_categories',
        'hs-directory-builder',
        'hs_staff_group_ordering_page'
    );
}
add_action('admin_menu', 'hs_staff_group_ordering_menu');

// Render Page
function hs_staff_group_ordering_page() {
    if (!current_user_can('manage_categories')) wp_die('Unauthorized');

    // Save logic
    if (isset($_POST['hs_builder_nonce']) && wp_verify_nonce($_POST['hs_builder_nonce'], 'hs_save_builder')) {
        if (isset($_POST['hs_directory_structure'])) {
            $structure_json = stripslashes($_POST['hs_directory_structure']);
            $structure = json_decode($structure_json, true);
            
            if (is_array($structure)) {
                // Sanitize keys and values
                $sanitized_structure = array();
                foreach ($structure as $dept => $groups) {
                    $clean_dept = sanitize_text_field($dept);
                    $clean_groups = array_map('sanitize_text_field', (array)$groups);
                    $sanitized_structure[$clean_dept] = $clean_groups;
                }
                update_option('hs_directory_structure', $sanitized_structure);
                echo '<div class="notice notice-success is-dismissible"><p>Directory Structure saved successfully!</p></div>';
            }
        }
    }

    // Get all departments
    $departments = get_terms(array(
        'taxonomy' => 'staff_department',
        'hide_empty' => false,
    ));

    // Get all display groups
    $groups = get_terms(array(
        'taxonomy' => 'staff_display_group',
        'hide_empty' => false,
    ));
    
    if (is_wp_error($departments) || empty($departments)) {
        echo '<div class="wrap"><h1>Directory Builder</h1><p>No Departments found. Please create some first.</p></div>';
        return;
    }

    // Build Maps by NAME (since frontend JS uses names for matching)
    $dept_map = array();
    foreach ($departments as $d) {
        $dept_map[] = html_entity_decode($d->name);
    }
    
    $group_map = array();
    foreach ($groups as $g) {
        $group_map[] = html_entity_decode($g->name);
    }

    // Get saved structure
    $saved_structure = get_option('hs_directory_structure', array());
    
    // Reconcile saved structure with current departments
    $final_structure = array();
    
    foreach ($saved_structure as $dept_name => $saved_groups) {
        if (in_array($dept_name, $dept_map)) {
            // Keep only groups that still exist
            $valid_groups = array();
            foreach ($saved_groups as $g_name) {
                if (in_array($g_name, $group_map)) {
                    $valid_groups[] = $g_name;
                }
            }
            $final_structure[$dept_name] = $valid_groups;
            
            // Remove from dept_map so we know what's left
            $key = array_search($dept_name, $dept_map);
            if ($key !== false) unset($dept_map[$key]);
        }
    }
    
    // Add any new/unassigned departments to the END
    foreach ($dept_map as $dept_name) {
        $final_structure[$dept_name] = array();
    }
    
    // Enqueue jQuery UI Sortable
    wp_enqueue_script('jquery-ui-sortable');
    
    // CSS
    echo '<style>
        .hs-builder-wrap { max-width: 800px; margin-top: 20px; }
        .hs-dept-list { list-style: none; padding: 0; }
        .hs-dept-block { background: #fff; border: 1px solid #c3c4c7; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04); border-radius: 4px; }
        .hs-dept-header { padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7; cursor: move; display: flex; align-items: center; border-radius: 4px 4px 0 0; }
        .hs-dept-header h3 { margin: 0; font-size: 16px; font-weight: 600; color: #1d2327; }
        .hs-dept-header .dashicons { color: #8c8f94; margin-right: 15px; }
        
        .hs-group-list { list-style: none; padding: 15px; margin: 0; min-height: 20px; }
        .hs-group-item { background: #fff; border: 1px solid #dcdcde; padding: 10px 15px; margin-bottom: 8px; cursor: move; display: flex; align-items: center; justify-content: space-between; border-radius: 3px; }
        .hs-group-item:hover { border-color: #2271b1; }
        .hs-group-item-left { display: flex; align-items: center; }
        .hs-group-item-left .dashicons { color: #a0aec0; margin-right: 10px; }
        .hs-group-remove { color: #d63638; cursor: pointer; text-decoration: none; display: flex; align-items: center; }
        .hs-group-remove:hover { color: #b32d2e; }
        
        .hs-dept-footer { padding: 15px; background: #fafafa; border-top: 1px solid #f0f0f1; display: flex; gap: 10px; border-radius: 0 0 4px 4px; }
        .hs-dept-footer select { max-width: 300px; flex-grow: 1; }
        
        .ui-sortable-placeholder { border: 2px dashed #ccd0d4; background: #f8f9fa; margin-bottom: 8px; visibility: visible !important; border-radius: 3px; }
        .hs-dept-placeholder { border: 2px dashed #ccd0d4; background: transparent; margin-bottom: 20px; height: 100px; }
    </style>';

    echo '<div class="wrap hs-builder-wrap">';
    echo '<h1 class="wp-heading-inline">Directory Builder</h1>';
    echo '<p style="font-size: 14px; color: #50575e; margin-bottom: 20px;">Use this builder to construct the exact sequence of the directory. Drag the large blocks to reorder Departments (Accordions). Inside each department, drag Display Groups to sequence them, or add new groups from the dropdown.</p>';
    
    echo '<form method="post" action="" id="hs-builder-form">';
    wp_nonce_field('hs_save_builder', 'hs_builder_nonce');
    echo '<input type="hidden" name="hs_directory_structure" id="hs_directory_structure_payload" value="">';
    
    echo '<ul class="hs-dept-list" id="hs-dept-sortable">';
    foreach ($final_structure as $dept_name => $dept_groups) {
        echo '<li class="hs-dept-block" data-dept="' . esc_attr($dept_name) . '">';
        
        echo '<div class="hs-dept-header">';
        echo '<span class="dashicons dashicons-menu"></span>';
        echo '<h3>' . esc_html($dept_name) . '</h3>';
        echo '</div>';
        
        echo '<ul class="hs-group-list">';
        foreach ($dept_groups as $g_name) {
            echo '<li class="hs-group-item" data-group="' . esc_attr($g_name) . '">';
            echo '<div class="hs-group-item-left"><span class="dashicons dashicons-menu"></span><span class="hs-g-name">' . esc_html($g_name) . '</span></div>';
            echo '<a class="hs-group-remove"><span class="dashicons dashicons-no-alt"></span></a>';
            echo '</li>';
        }
        echo '</ul>';
        
        echo '<div class="hs-dept-footer">';
        echo '<select class="hs-group-select">';
        echo '<option value="">-- Select Display Group to Add --</option>';
        foreach ($group_map as $gm) {
            echo '<option value="' . esc_attr($gm) . '">' . esc_html($gm) . '</option>';
        }
        echo '</select>';
        echo '<button type="button" class="button hs-add-group-btn">Add to ' . esc_html($dept_name) . '</button>';
        echo '</div>';
        
        echo '</li>';
    }
    echo '</ul>';
    
    echo '<p class="submit"><button type="submit" class="button button-primary button-hero" id="hs-save-btn">Save Directory Structure</button></p>';
    echo '</form>';
    echo '</div>';
    
    // JS
    echo '<script>
        jQuery(document).ready(function($) {
            $("#hs-dept-sortable").sortable({
                handle: ".hs-dept-header",
                placeholder: "hs-dept-placeholder",
                forcePlaceholderSize: true,
                axis: "y",
                opacity: 0.9,
                update: triggerChange
            });
            
            $(".hs-group-list").sortable({
                connectWith: ".hs-group-list",
                placeholder: "ui-sortable-placeholder",
                forcePlaceholderSize: true,
                opacity: 0.8,
                update: triggerChange
            });
            
            function triggerChange() {
                $("#hs-save-btn").css({"box-shadow": "0 0 0 2px #2271b1", "transition": "0.3s"});
            }
            
            $(document).on("click", ".hs-group-remove", function(e) {
                e.preventDefault();
                $(this).closest(".hs-group-item").remove();
                triggerChange();
            });
            
            $(".hs-add-group-btn").on("click", function(e) {
                e.preventDefault();
                var $block = $(this).closest(".hs-dept-block");
                var $select = $block.find(".hs-group-select");
                var groupName = $select.val();
                
                if (!groupName) return;
                
                var exists = false;
                $block.find(".hs-group-item").each(function() {
                    if ($(this).attr("data-group") === groupName) {
                        exists = true;
                    }
                });
                
                if (exists) {
                    alert("This group is already in this department.");
                    return;
                }
                
                var newItem = \'<li class="hs-group-item" data-group="\' + groupName.replace(/"/g, "&quot;") + \'">\' +
                              \'<div class="hs-group-item-left"><span class="dashicons dashicons-menu"></span><span class="hs-g-name">\' + groupName + \'</span></div>\' +
                              \'<a class="hs-group-remove"><span class="dashicons dashicons-no-alt"></span></a>\' +
                              \'</li>\';
                              
                $block.find(".hs-group-list").append(newItem);
                $select.val("");
                triggerChange();
            });
            
            $("#hs-builder-form").on("submit", function() {
                var structure = {};
                
                $(".hs-dept-block").each(function() {
                    var deptName = $(this).attr("data-dept");
                    var groups = [];
                    
                    $(this).find(".hs-group-item").each(function() {
                        groups.push($(this).attr("data-group"));
                    });
                    
                    structure[deptName] = groups;
                });
                
                $("#hs_directory_structure_payload").val(JSON.stringify(structure));
                return true;
            });
        });
    </script>';
}
