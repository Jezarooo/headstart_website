<?php

/**
 * Posts--Register-Taxonomies
 */
/**
 * ====================================================================
 * HEADSTART POST TAXONOMIES
 * Registers Department (Website Section) & links existing Campus taxonomy
 * ====================================================================
 */

add_action('init', 'hs_register_post_taxonomies', 0);

function hs_register_post_taxonomies() {

    // 1. Register "Website Section" (hs_department)
    // Used for Foundation, Primary, Secondary, etc.
    $labels_department = array(
        'name'              => _x('Website Sections', 'taxonomy general name', 'headstart'),
        'singular_name'     => _x('Website Section', 'taxonomy singular name', 'headstart'),
        'search_items'      => __('Search Sections', 'headstart'),
        'all_items'         => __('All Sections', 'headstart'),
        'parent_item'       => __('Parent Section', 'headstart'),
        'parent_item_colon' => __('Parent Section:', 'headstart'),
        'edit_item'         => __('Edit Section', 'headstart'),
        'update_item'       => __('Update Section', 'headstart'),
        'add_new_item'      => __('Add New Section', 'headstart'),
        'new_item_name'     => __('New Section Name', 'headstart'),
        'menu_name'         => __('Website Sections', 'headstart'),
    );

    $args_department = array(
        'hierarchical'      => true, // Like categories
        'labels'            => $labels_department,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'section'),
        'show_in_rest'      => true, // Enable block editor support if needed
    );

    register_taxonomy('hs_department', array('post'), $args_department);

    // 2. Register new Campus taxonomy exclusively for Posts (Divi 5 Theme Builder support)
    $args_campus = array(
        'labels' => array(
            'name'          => 'Campuses',
            'singular_name' => 'Campus',
            'menu_name'     => 'Campuses',
            'add_new_item'  => 'Add New Campus',
        ),
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => false,
        'show_in_nav_menus' => false,
        'show_tagcloud'     => false,
        'rewrite'           => array('slug' => 'post-campus'),
        'show_in_rest'      => true,
    );
    register_taxonomy('hs_post_campus', array('post'), $args_campus);
    // 3. Register "Thematic Chip" (hs_thematic_chip)
    // Used to optionally override the frontend badge text
    $args_thematic = array(
        'labels' => array(
            'name'          => 'Thematic Chips',
            'singular_name' => 'Thematic Chip',
            'menu_name'     => 'Thematic Chips',
        ),
        'hierarchical'      => false,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => false,
        'show_in_nav_menus' => false,
        'show_tagcloud'     => false,
        'rewrite'           => false,
        'show_in_rest'      => true,
        'meta_box_cb'       => false, // Hide native sidebar box so we can use ACF
    );
    register_taxonomy('hs_thematic_chip', array('post'), $args_thematic);

    // 4. Completely disable native Categories for Posts
    // We use Website Sections (hs_department) instead!
    unregister_taxonomy_for_object_type('category', 'post');
}

/**
 * 3. Seed Default Departments
 * Seeds the default list of departments into the hs_department taxonomy on init.
 */
add_action('init', 'hs_seed_default_departments', 20);

function hs_seed_default_departments() {
    $departments = array(
        'Foundation',
        'Primary',
        'Secondary',
        'Sixth Form',
        'Sports Academies',
        'CAPA',
        'Global Citizenship',
        'Values & Leadership',
        'DofE',
        'World Languages',
        'School Houses',
        'ECA',
        'HeadStart Alumni'
    );

    foreach ($departments as $dept) {
        if (!term_exists($dept, 'hs_department')) {
            wp_insert_term($dept, 'hs_department');
        }
    }
}

/**
 * 4. Seed Default Campuses for Posts
 */
add_action('init', 'hs_seed_default_post_campuses', 21);

function hs_seed_default_post_campuses() {
    $campuses = array('Chaofah', 'Cherngtalay');
    foreach ($campuses as $campus) {
        if (!term_exists($campus, 'hs_post_campus')) {
            wp_insert_term($campus, 'hs_post_campus');
        }
    }
}

/**
 * 5. Seed Default Thematic Chips
 */
add_action('init', 'hs_seed_default_thematic_chips', 22);

function hs_seed_default_thematic_chips() {
    $chips = array(
        'Spotlight',
        'School Life',
        'Community',
        'Academics',
        'Student Success',
        'Campus News',
        'Event',
        'Co-Curricular',
        'Creativity',
        'Innovation',
        'Well-being',
        'Leadership',
        'Perspectives',
        'Competition'
    );
    foreach ($chips as $chip) {
        if (!term_exists($chip, 'hs_thematic_chip')) {
            wp_insert_term($chip, 'hs_thematic_chip');
        }
    }
}
