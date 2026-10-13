<?php

/**
 * Posts--Register-Fields
 */
/**
 * ====================================================================
 * HEADSTART POST ACF FIELDS
 * Registers the ACF Taxonomy fields for Department, Campus, and Tags
 * ====================================================================
 */

add_action('acf/init', 'hs_register_post_acf_fields');

function hs_register_post_acf_fields() {
    // Ensure ACF is active
    if( !function_exists('acf_add_local_field_group') ) return;

    acf_add_local_field_group(array(
        'key' => 'group_hs_post_classification',
        'title' => 'Post Classification',
        'fields' => array(
            
            // 1. Department (Website Section)
            array(
                'key' => 'field_hs_post_department',
                'label' => 'Website Section (Routing)',
                'name' => 'hs_department_tax', // Internal ACF name, not highly relevant since save_terms is true
                'type' => 'taxonomy',
                'instructions' => 'Select which website feeds this post should appear in (e.g. Primary, Secondary). This also sets the default text for the yellow badge on the post card.',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '16',
                    'class' => '',
                    'id' => '',
                ),
                'taxonomy' => 'hs_department',
                'field_type' => 'checkbox',
                'allow_null' => 0,
                'add_term' => 0, // Prevent editors from randomly adding new departments
                'save_terms' => 1,
                'load_terms' => 1,
                'return_format' => 'object',
                'multiple' => 1,
            ),

            // 2. Thematic Chip Override (Visual Badge Override)
            array(
                'key' => 'field_hs_thematic_chip',
                'label' => 'Yellow Badge Override (Optional)',
                'name' => 'hs_thematic_chip_tax',
                'type' => 'taxonomy',
                'instructions' => 'Optionally change the text printed on the yellow badge at the top of the post card (e.g. "Spotlight"). You can select an existing one or type a new one to add it. This does NOT change where the post appears.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '12',
                    'class' => '',
                    'id' => '',
                ),
                'taxonomy' => 'hs_thematic_chip',
                'field_type' => 'select',
                'allow_null' => 1,
                'add_term' => 1, 
                'save_terms' => 1,
                'load_terms' => 1,
                'return_format' => 'object',
                'multiple' => 0,
            ),

            // 3. Post Tags (Thematic)
            array(
                'key' => 'field_hs_post_tags',
                'label' => 'Thematic Tags',
                'name' => 'post_tag_tax',
                'type' => 'taxonomy',
                'instructions' => 'Select existing thematic tags, or type your own to create new ones (aim for 3-4 per post). These display as the small metadata chips at the bottom of the post cards. Use a prefix with a colon (e.g., "ACADEMIC: Math") to group them - the prefix will be automatically hidden on the website.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '28',
                    'class' => '',
                    'id' => '',
                ),
                'taxonomy' => 'post_tag',
                'field_type' => 'checkbox',
                'allow_null' => 1,
                'add_term' => 1,
                'save_terms' => 1,
                'load_terms' => 1,
                'return_format' => 'object',
                'multiple' => 1,
            ),

            // 4. Campus Location
            array(
                'key' => 'field_hs_post_campus',
                'label' => 'Campus Location',
                'name' => 'hs_campus_tax',
                'type' => 'taxonomy',
                'instructions' => 'Required for website routing. Select the campus(es) this post belongs to, even if the event happened off-campus. If you select multiple, the slider chip will display "Both Campuses" automatically.',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '16',
                    'class' => '',
                    'id' => '',
                ),
                'taxonomy' => 'hs_post_campus',
                'field_type' => 'checkbox',
                'allow_null' => 0,
                'add_term' => 0,
                'save_terms' => 1,
                'load_terms' => 1,
                'return_format' => 'object',
                'multiple' => 0,
            ),

            // 5. Display Location (Override)
            array(
                'key' => 'field_hs_location_override',
                'label' => 'Display Location Override',
                'name' => 'hs_location_override',
                'type' => 'text',
                'instructions' => 'Optional: Only fill this in if the event happened off-campus (e.g. "Bangkok Trip"). This overwrites the text on the front-end location chip, but you MUST still select a Campus above so the website knows which community page to put this post on.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '16',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
                'maxlength' => '',
            ),

            // 6. Portrait Featured Image (Slider)
            array(
                'key' => 'field_hs_portrait_image',
                'label' => 'Portrait Featured Image (Slider)',
                'name' => 'hs_portrait_image',
                'type' => 'image',
                'instructions' => 'Optional. A vertical (portrait) version used only by the Post Slider. The standard Featured Image is required, should be landscape (16:9), and is used as the hero. If this is left blank, the slider uses the Featured Image.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '12',
                    'class' => '',
                    'id' => '',
                ),
                'return_format' => 'url',
                'preview_size' => 'medium',
                'library' => 'all',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'post',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal', // Places it in the main column under the title/editor, NOT the sidebar
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => array(
            0 => 'categories', // Hide native categories meta box
            1 => 'tags',       // Hide native tags meta box (since we provide the ACF one)
        ),
        'active' => true,
        'description' => 'Classification and taxonomy assignments for standard Posts.',
        'show_in_rest' => 1,
    ));
}

/**
 * ====================================================================
 * ADMIN UI CLEANUP
 * Removes native taxonomy metaboxes from the sidebar (replaced by ACF)
 * and collapses the Divi Page Settings metabox by default.
 * ====================================================================
 */
add_action('do_meta_boxes', 'hs_remove_native_tax_metaboxes', 999);
function hs_remove_native_tax_metaboxes() {
    // Remove native Tags and Departments from the sidebar
    remove_meta_box('tagsdiv-post_tag', 'post', 'side');
    remove_meta_box('tagsdiv-post_tag', 'post', 'normal');
    remove_meta_box('tagsdiv-post_tag', 'post', 'advanced');
    
    remove_meta_box('tagsdiv-hs_department', 'post', 'side');
    remove_meta_box('hs_departmentdiv', 'post', 'side');
    remove_meta_box('hs_departmentdiv', 'post', 'normal');
    
    remove_meta_box('tagsdiv-hs_post_campus', 'post', 'side');
    remove_meta_box('hs_post_campusdiv', 'post', 'side');
    remove_meta_box('hs_post_campusdiv', 'post', 'normal');

    remove_meta_box('tagsdiv-hs_thematic_chip', 'post', 'side');
    remove_meta_box('hs_thematic_chipdiv', 'post', 'side');
    remove_meta_box('hs_thematic_chipdiv', 'post', 'normal');
}

// Close the Divi Page Settings metabox by default
add_filter('postbox_classes_post_et_settings_meta_box_gutenberg', 'hs_close_divi_settings_metabox');
add_filter('postbox_classes_post_et_settings_meta_box', 'hs_close_divi_settings_metabox');
function hs_close_divi_settings_metabox($classes) {
    if (!in_array('closed', $classes)) {
        $classes[] = 'closed';
    }
    return $classes;
}
