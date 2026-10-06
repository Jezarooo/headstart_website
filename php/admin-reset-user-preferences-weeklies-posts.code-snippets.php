<?php

/**
 * Admin--Reset-User-Preferences-Weeklies-Posts
 */
/**
 * Plugin Name: Layout Reset
 * Description: Adds a settings page to manually reset the Weekly Update meta box positions when they get stuck in the sidebar.
 * Version: 1.0
 * Author: HeadStart
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Register the settings page under the "Settings" menu.
 */
add_action('admin_menu', 'hs_register_weekly_update_settings_page');
function hs_register_weekly_update_settings_page() {
    add_options_page(
        'Weekly Update Layout Reset', // Page title
        'Weekly Update Reset',        // Menu title
        'manage_options',             // Capability required
        'hs-weekly-update-reset',     // Menu slug
        'hs_render_weekly_update_settings_page' // Callback function
    );
}

/**
 * Render the settings page HTML.
 */
function hs_render_weekly_update_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Show success notice if it was just reset
    if (isset($_GET['reset_success'])) {
        $type = sanitize_text_field($_GET['reset_success']);
        $label = ($type === 'post') ? 'Standard Post' : 'Weekly Update';
        echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Your ACF ' . esc_html($label) . ' layout has been reset to the default "normal" position.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Weekly Update Layout Reset</h1>
        <p>If your Weekly Update or standard Post ACF fields have gotten stuck in the right sidebar of the editor, you can reset them here.</p>
        <p>Choose which layout you want to clear your personal saved preferences for, forcing the fields back below the main content area.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('hs_reset_weekly_update_action', 'hs_reset_weekly_update_nonce'); ?>
            <p>
                <button type="submit" name="hs_reset_target" value="weekly_update" class="button button-primary">Reset Weekly Update Layout</button>
                <button type="submit" name="hs_reset_target" value="post" class="button button-secondary">Reset Standard Post Layout</button>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Listen for the form submission and process the reset.
 */
add_action('admin_init', 'hs_process_weekly_update_reset');
function hs_process_weekly_update_reset() {
    // Ensure the form was submitted
    if (!isset($_POST['hs_reset_target'])) {
        return;
    }
    
    // Verify the nonce for security
    if (!isset($_POST['hs_reset_weekly_update_nonce']) || !wp_verify_nonce($_POST['hs_reset_weekly_update_nonce'], 'hs_reset_weekly_update_action')) {
        wp_die('Invalid security token. Please try again.');
    }
    
    // Check capabilities again
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to perform this action.');
    }
    
    // Perform the reset
    $user_id = get_current_user_id();
    $target = sanitize_text_field($_POST['hs_reset_target']);
    
    if ($target === 'weekly_update') {
        delete_user_meta($user_id, 'meta-box-order_weekly_update');
    } elseif ($target === 'post') {
        delete_user_meta($user_id, 'meta-box-order_post');
    }
    
    // Redirect back to the page with a success parameter to show the notice
    $redirect_url = add_query_arg(
        array(
            'page' => 'hs-weekly-update-reset',
            'reset_success' => $target
        ),
        admin_url('options-general.php')
    );
    
    wp_redirect($redirect_url);
    exit;
}
