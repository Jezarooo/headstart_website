<?php

/**
 * Reset User Preferences
 */
add_action('admin_init', 'hs_reset_weekly_update_metabox_positions');
function hs_reset_weekly_update_metabox_positions() {
    $user_id = get_current_user_id();
    
    // Deletes the saved layout order for the weekly_update post type for your user account
    delete_user_meta($user_id, 'meta-box-order_weekly_update');
}
