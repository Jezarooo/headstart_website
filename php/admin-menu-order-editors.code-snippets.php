<?php

/**
 * Admin Menu Order (Editors)
 */
/**
 * 1. Hide specific menus ONLY for Editors
 */
function hide_admin_menus_for_editors() {
    // Only apply to Editors (users who can publish pages but cannot manage options)
    if ( current_user_can( 'editor' ) && ! current_user_can( 'manage_options' ) ) {
        
        // Hide technical and settings menus
        remove_menu_page( 'tools.php' );                             // Tools
        remove_menu_page( 'options-general.php' );                   // Settings
        
        // Hide unused Theme/Plugin bloat
        remove_menu_page( 'edit.php?post_type=dipl-team-member' );   // DP Team Members
        remove_menu_page( 'edit.php?post_type=dipl-testimonial' );   // DP Testimonials
        remove_menu_page( 'edit-comments.php' );                     // Comments
        remove_menu_page( 'edit.php?post_type=project' );            // Projects
    }
}
add_action( 'admin_menu', 'hide_admin_menus_for_editors', 999 );


/**
 * 2. Reorder the admin menu exactly as requested for ALL users (Admins & Editors)
 */
function reorder_admin_menus_globally( $menu_ord ) {
    if ( ! $menu_ord ) {
        return true;
    }

    // Your exact requested order. 
    // These will sit at the top for everyone. 
    // For Admins, their extra menus (Plugins, etc) will appear below these.
    $sensible_order = array(
        'index.php',                        // 1. Dashboard
        'edit.php',                         // 2. Posts
        'edit.php?post_type=weekly_update', // 3. Weekly Updates
        'edit.php?post_type=hs_notice',     // 4. Noticeboard
        'edit.php?post_type=staff',         // 5. Staff Directory
        'edit.php?post_type=events',        // 6. School Calendar
        'edit.php?post_type=page',          // 7. Pages
        'upload.php',                       // 8. Media
        'fluent_forms',                     // 9. Fluent Forms Pro
    );

    return $sensible_order;
}
add_filter( 'custom_menu_order', '__return_true' );
add_filter( 'menu_order', 'reorder_admin_menus_globally', 999 );
