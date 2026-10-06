<?php

/**
 * Weekly-Update--Register-Fields
 */
/**
 * ====================================================================
 * WEEKLY UPDATES ACF FIELDS
 * Registers the ACF fields for Weekly Updates via PHP.
 * ====================================================================
 */

add_action('acf/init', 'hs_register_weekly_update_acf_fields');

function hs_register_weekly_update_acf_fields() {
    if( !function_exists('acf_add_local_field_group') ) return;

    acf_add_local_field_group(array(
        'key' => 'group_6a3772490951b',
        'title' => 'Weekly Update Details',
        'fields' => array(
            // 1. Blurb (WYSIWYG for List View)
            array(
                'key' => 'field_weekly_update_blurb',
                'label' => 'Blurb (List View)',
                'name' => 'blurb',
                'type' => 'wysiwyg',
                'instructions' => 'This text will appear in the list view on the Campus Hub page.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '25',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'tabs' => 'all',
                'toolbar' => 'full',
                'media_upload' => 0,
                'delay' => 0,
            ),
            
            // 2. Campus Bridge (Taxonomy Radio)
            array(
                'key' => 'field_6a37737bf9323',
                'label' => 'Campus',
                'name' => 'campus_bridge',
                'type' => 'taxonomy',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '25',
                    'class' => '',
                    'id' => '',
                ),
                'taxonomy' => 'hs_campus',
                'add_term' => 0,
                'save_terms' => 1,
                'load_terms' => 1,
                'return_format' => 'object',
                'field_type' => 'radio',
                'allow_null' => 0,
                'multiple' => 0,
            ),

            // 3. Week Number
            array(
                'key' => 'field_6a377249f9320',
                'label' => 'Week Number',
                'name' => 'hs_week_number',
                'type' => 'number',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '25',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'min' => '',
                'max' => '',
                'step' => '',
            ),
            
            // 4. In This Issue (Tags)
            array(
                'key' => 'field_weekly_update_tags',
                'label' => 'In This Issue (Tags)',
                'name' => 'in_this_issue_tax',
                'type' => 'taxonomy',
                'instructions' => 'Select relevant tags (e.g., Primary, School Houses). You can also add new ones directly here.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '25',
                    'class' => '',
                    'id' => '',
                ),
                'taxonomy' => 'in_this_issue',
                'field_type' => 'checkbox',
                'allow_null' => 1,
                'add_term' => 1, // Allow adding new tags directly from editor
                'save_terms' => 1,
                'load_terms' => 1,
                'return_format' => 'object',
                'multiple' => 1,
            ),

            // 5. Legacy Archive
            array(
                'key' => 'field_6a3772e6f9322',
                'label' => 'Archive Post',
                'name' => 'hs_legacy_archive',
                'type' => 'true_false',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '100',
                    'class' => '',
                    'id' => '',
                ),
                'message' => 'Yes, this is an imported post from the previous website.',
                'default_value' => 0,
                'ui' => 0,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'weekly_update',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => array(
            0 => 'excerpt', // Hide the native excerpt box
        ),
        'active' => true,
        'description' => '',
        'show_in_rest' => 1,
    ));
}

/**
 * ====================================================================
 * PRE-POPULATE DEFAULT "IN THIS ISSUE" TAGS
 * Automatically creates base business domain tags if they don't exist yet.
 * ====================================================================
 */
add_action('init', 'hs_populate_default_weekly_update_tags', 20);
function hs_populate_default_weekly_update_tags() {
    $taxonomy = 'in_this_issue';
    if (!taxonomy_exists($taxonomy)) return;

    $base_tags = array(
        'Foundation',
        'Primary',
        'Secondary',
        'School Houses',
        'Sixth Form',
        'Sports',
        'VAPA',
        'Academics'
    );

    foreach ($base_tags as $tag) {
        if (!term_exists($tag, $taxonomy)) {
            wp_insert_term($tag, $taxonomy);
        }
    }
}

/**
 * ====================================================================
 * CLEANUP NATIVE UI
 * Closes the Divi Page Settings meta box by default.
 * ====================================================================
 */
add_filter('postbox_classes_weekly_update_et_settings_meta_box_gutenberg', 'hs_close_weekly_update_divi_settings_metabox');
add_filter('postbox_classes_weekly_update_et_settings_meta_box', 'hs_close_weekly_update_divi_settings_metabox');
function hs_close_weekly_update_divi_settings_metabox($classes) {
    if (!is_array($classes)) $classes = array();
    if (!in_array('closed', $classes)) {
        $classes[] = 'closed';
    }
    return $classes;
}
