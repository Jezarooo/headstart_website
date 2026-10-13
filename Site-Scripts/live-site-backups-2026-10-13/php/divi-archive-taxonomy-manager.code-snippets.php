<?php

/**
 * Divi--Archive-Taxonomy-Manager
 */
/**
 * ====================================================================
 * ARCHIVE STATUS MANAGER
 * Registers separate Archive taxonomies for Posts and Weekly Updates
 * to prevent Divi Theme Builder collisions.
 * ====================================================================
 */

// 1. Settings Page to Toggle UI
add_action('admin_menu', 'hs_archive_manager_menu');
function hs_archive_manager_menu() {
    add_options_page(
        'Legacy Archive UI',
        'Legacy Archive UI',
        'manage_options',
        'hs-legacy-archive-ui',
        'hs_archive_manager_page_html'
    );
}

function hs_archive_manager_page_html() {
    if (!current_user_can('manage_options')) return;
    
    if (isset($_POST['hs_save_archive_ui'])) {
        $show_ui = isset($_POST['hs_show_archive_ui']) ? 1 : 0;
        update_option('hs_show_archive_ui', $show_ui);
        echo '<div class="updated"><p>Settings saved. The Archive UI has been updated.</p></div>';
    }
    
    $current_status = get_option('hs_show_archive_ui', 1);
    ?>
    <div class="wrap">
        <h1>Legacy Archive UI Settings</h1>
        <p>Because assigning legacy Archive Statuses is a one-time job after importing, you can hide the UI when you are finished to keep the post editor screens clean.</p>
        <p><em>Note: Hiding the UI does NOT delete your archive tags or break your Divi Global Templates. It simply hides the columns and checkboxes from your editors.</em></p>
        
        <form method="POST" style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 600px;">
            <label style="font-size: 14px;">
                <input type="checkbox" name="hs_show_archive_ui" value="1" <?php checked($current_status, 1); ?> />
                <strong>Show Archive Status UI</strong>
            </label>
            <p style="color: #666; margin-top: 5px; margin-left: 25px;">Check this box to show the "Archive Status" columns, Quick Edit checkboxes, and bulk-edit options on the Posts and Weekly Updates screens.</p>
            
            <input type="submit" name="hs_save_archive_ui" class="button button-primary" style="margin-top: 15px;" value="Save Changes" />
        </form>
    </div>
    <?php
}

// 2. Register the Hidden Taxonomies
add_action('init', 'hs_register_archive_taxonomies', 0);
function hs_register_archive_taxonomies() {
    $show_columns = (bool) get_option('hs_show_archive_ui', 1);

    // Taxonomy 1: Archive Status (For Standard Posts)
    register_taxonomy('hs_archive_status', array('post'), array(
        'hierarchical'      => true,
        'labels'            => array('name' => 'Archive Status (Posts)', 'menu_name' => 'Archive Status'),
        'show_ui'           => true, 
        'show_in_menu'      => false, 
        'show_admin_column' => $show_columns,
        'show_in_quick_edit'=> $show_columns,
        'meta_box_cb'       => $show_columns ? null : false, 
        'query_var'         => true,
        'public'            => false,
        'show_in_rest'      => true,
    ));

    // Taxonomy 2: Archive Status (For Weekly Updates)
    register_taxonomy('hs_weekly_archive_status', array('weekly_update'), array(
        'hierarchical'      => true,
        'labels'            => array('name' => 'Archive Status (Weekly)', 'menu_name' => 'Archive Status'),
        'show_ui'           => true, 
        'show_in_menu'      => false, 
        'show_admin_column' => $show_columns,
        'show_in_quick_edit'=> $show_columns,
        'meta_box_cb'       => $show_columns ? null : false, 
        'query_var'         => true,
        'public'            => false,
        'show_in_rest'      => true,
    ));
}

// 3. Pre-populate terms
add_action('init', 'hs_populate_archive_terms', 20);
function hs_populate_archive_terms() {
    if (taxonomy_exists('hs_archive_status')) {
        $archive_terms = array('Archive: Chaofah' => 'archive-chaofah', 'Archive: Cherngtalay' => 'archive-cherngtalay', 'Archive: Both' => 'archive-both');
        foreach ($archive_terms as $name => $slug) {
            if (!term_exists($name, 'hs_archive_status')) wp_insert_term($name, 'hs_archive_status', array('slug' => $slug));
        }
    }
    
    if (taxonomy_exists('hs_weekly_archive_status')) {
        $weekly_terms = array('Archive: Chaofah' => 'archive-chaofah', 'Archive: Cherngtalay' => 'archive-cherngtalay');
        foreach ($weekly_terms as $name => $slug) {
            if (!term_exists($name, 'hs_weekly_archive_status')) wp_insert_term($name, 'hs_weekly_archive_status', array('slug' => $slug));
        }
    }
}

// 6. Force WordPress Bulk Edit to Replace (instead of Append)
add_action('save_post', 'hs_force_overwrite_archive_status_bulk_edit', 99, 2);
function hs_force_overwrite_archive_status_bulk_edit($post_id, $post) {
    if (isset($_REQUEST['bulk_edit']) && isset($_REQUEST['tax_input']['hs_archive_status'])) {
        $selected_terms = $_REQUEST['tax_input']['hs_archive_status'];
        $clean_terms = array_filter(array_map('intval', (array) $selected_terms));
        wp_set_object_terms($post_id, $clean_terms, 'hs_archive_status', false);
    }
    
    if (isset($_REQUEST['bulk_edit']) && isset($_REQUEST['tax_input']['hs_weekly_archive_status'])) {
        $selected_terms = $_REQUEST['tax_input']['hs_weekly_archive_status'];
        $clean_terms = array_filter(array_map('intval', (array) $selected_terms));
        wp_set_object_terms($post_id, $clean_terms, 'hs_weekly_archive_status', false);
    }
}
