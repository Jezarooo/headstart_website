<?php

/**
 * Admin--Hide-Menus
 */
/**
 * ====================================================================
 * ADMIN MENU TIDY & ROLE MANAGEMENT
 * Adds a toggle to User Profiles to hide complex menus for Content Admins.
 * ====================================================================
 */

/**
 * 1. Add Checkbox to User Profile
 */
function hs_add_advanced_menus_checkbox( $user ) {
    // Check if the current user has permission to edit this profile
    if ( ! current_user_can( 'edit_user', $user->ID ) ) {
        return;
    }
    
    // Get the current value.
    $show_menus = get_user_meta( $user->ID, 'hs_show_advanced_menus', true );
    if ( $show_menus === '' ) {
        // Default to true (checked) if never set, so existing admins don't lose menus
        $show_menus = '1';
    }
    ?>
    <h3>Admin Menu Settings</h3>
    <table class="form-table">
        <tr>
            <th><label for="hs_show_advanced_menus">Advanced Menus</label></th>
            <td>
                <label>
                    <input type="checkbox" name="hs_show_advanced_menus" id="hs_show_advanced_menus" value="1" <?php checked( '1', $show_menus ); ?> />
                    Show advanced settings and plugin menus in the sidebar.
                </label>
                <p class="description">Uncheck this to hide complex menus (Plugins, Settings, Appearance, etc.) for this user. Perfect for Editors promoted to Administrators.</p>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'hs_add_advanced_menus_checkbox' );
add_action( 'edit_user_profile', 'hs_add_advanced_menus_checkbox' );

/**
 * 2. Save the Checkbox State
 */
function hs_save_advanced_menus_checkbox( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }
    
    // If it's checked, save '1', else save '0'
    $value = isset( $_POST['hs_show_advanced_menus'] ) ? '1' : '0';
    update_user_meta( $user_id, 'hs_show_advanced_menus', $value );
}
add_action( 'personal_options_update', 'hs_save_advanced_menus_checkbox' );
add_action( 'edit_user_profile_update', 'hs_save_advanced_menus_checkbox' );

/**
 * 3. Hide specific menus based on the Checkbox
 */
function hs_hide_admin_menus_for_content_admins() {
    $user_id = get_current_user_id();
    $show_menus = get_user_meta( $user_id, 'hs_show_advanced_menus', true );
    
    // If it's strictly '0' (unchecked), hide the menus.
    if ( $show_menus === '0' ) {
        // Hide unused Theme/Plugin bloat
        remove_menu_page( 'edit.php?post_type=dipl-team-member' );   // DP Team Members
        remove_menu_page( 'edit.php?post_type=dipl-testimonial' );   // DP Testimonials
        remove_menu_page( 'edit-comments.php' );                     // Comments
        remove_menu_page( 'edit.php?post_type=project' );            // Projects
        
        // Hide technical Administrator menus
        remove_menu_page( 'plugins.php' );                           // Plugins
        remove_menu_page( 'themes.php' );                            // Appearance
        remove_menu_page( 'users.php' );                             // Users
        remove_menu_page( 'tools.php' );                             // Tools
        remove_menu_page( 'options-general.php' );                   // Settings
        remove_menu_page( 'et_divi_options' );                       // Divi
        remove_menu_page( 'wpcode' );                                // WPCode
        remove_menu_page( 'edit.php?post_type=acf-field-group' );    // ACF
        
        // Hide newly identified third-party plugin menus
        remove_menu_page( 'edit.php?post_type=custom-css-js' );      // Custom CSS & JS
        remove_menu_page( 'snippets' );                              // Snippets
        remove_menu_page( 'divi-extended' );                         // Divi Extended
        remove_menu_page( 'password-protected' );                    // Password Protected
        remove_menu_page( 'cptui_main_menu' );                       // CPT UI
        remove_menu_page( 'cptui_manage_post_types' );               // CPT UI Alternate
        remove_menu_page( 'duplicator' );                            // Duplicator
    }
}
add_action( 'admin_menu', 'hs_hide_admin_menus_for_content_admins', 999 );

/**
 * 4. Reorder the admin menu exactly as requested for ALL users
 */
function hs_reorder_admin_menus_globally( $menu_ord ) {
    if ( ! $menu_ord ) {
        return true;
    }

    // Your exact requested order, updated with the new combined hero images menu!
    // These will sit at the top for everyone. 
    $sensible_order = array(
        'index.php',                        // 1. Dashboard
        'edit.php',                         // 2. Posts
        'edit.php?post_type=weekly_update', // 3. Weekly Updates
        'hs-hero-images',                   // 4. Hero & Menu Images
        'edit.php?post_type=hs_notice',     // 5. Noticeboard
        'edit.php?post_type=staff',         // 6. Staff Directory
        'edit.php?post_type=events',        // 7. School Calendar
        'edit.php?post_type=page',          // 9. Pages
        'upload.php',                       // 10. Media
        'fluent_forms',                     // 11. Fluent Forms Pro
        'hs-socials-menu',                  // 12. Socials Menu
    );

    return $sensible_order;
}
add_filter( 'custom_menu_order', '__return_true' );
add_filter( 'menu_order', 'hs_reorder_admin_menus_globally', 999 );
