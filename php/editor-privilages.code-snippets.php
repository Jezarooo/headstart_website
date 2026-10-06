<?php

/**
 * editor privilages
 */
/**
 * ====================================================================
 * RESTORE EDITOR ROLE
 * Removes the admin capabilities we temporarily granted for testing.
 * ====================================================================
 */

add_action('admin_init', 'hs_restore_editor_capabilities');

function hs_restore_editor_capabilities() {
    $role = get_role('editor');

    if ($role) {
        // Remove the Admin capabilities we added for the test
        if ($role->has_cap('manage_options')) {
            $role->remove_cap('manage_options');
        }
        if ($role->has_cap('edit_theme_options')) {
            $role->remove_cap('edit_theme_options');
        }
        
        // Note: We leave 'unfiltered_html' as it's safe and actually required 
        // by standard Divi (non-beta) for Editors to use the builder.
    }
}
