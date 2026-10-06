<?php

/**
 * Staff Directory - One time name fix
 */
/**
 * HeadStart - One-Time Database Fix for Staff Names
 * 
 * Instructions:
 * 1. Create a new "PHP Snippet" in WPCode.
 * 2. Paste this code.
 * 3. Save & set to Active.
 * 4. Load ANY page on your website ONE TIME. (e.g. refresh your admin dashboard)
 * 5. IMMEDIATELY DEACTIVATE this snippet so it doesn't run again.
 */

function hs_fix_staff_double_brackets_once() {
    // Prevent running multiple times if we've already done it
    if (get_option('hs_staff_brackets_fixed')) {
        return;
    }

    global $wpdb;

    // Run SQL to find and replace all instances of (( in post titles
    $wpdb->query("
        UPDATE {$wpdb->posts}
        SET post_title = REPLACE(post_title, '((', '(')
        WHERE post_type = 'staff' AND post_title LIKE '%((%'
    ");

    // Run SQL to find and replace all instances of )) in post titles
    $wpdb->query("
        UPDATE {$wpdb->posts}
        SET post_title = REPLACE(post_title, '))', ')')
        WHERE post_type = 'staff' AND post_title LIKE '%))%'
    ");

    // Mark as fixed so it never runs again
    update_option('hs_staff_brackets_fixed', true);
}
add_action('admin_init', 'hs_fix_staff_double_brackets_once');
