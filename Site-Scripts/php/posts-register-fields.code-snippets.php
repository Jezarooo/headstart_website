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
                'label' => 'Website Section',
                'name' => 'hs_department_tax', // Internal ACF name, not highly relevant since save_terms is true
                'type' => 'taxonomy',
                'instructions' => 'Dictates where on the site the Post is shown (its feeds). Also the default yellow badge.',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-pc-section',
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
                'label' => 'Yellow badge override',
                'name' => 'hs_thematic_chip_tax',
                'type' => 'taxonomy',
                'instructions' => 'Optional. Leave empty to show the Website Section. Type a new one to add it.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-pc-badge',
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
                'instructions' => 'Aim for 3–4. The part before the colon groups them and is hidden on the site.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-pc-tags',
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
                'label' => 'Campus',
                'name' => 'hs_campus_tax',
                'type' => 'taxonomy',
                'instructions' => 'Tick both for Both Campuses. Sets the campus in the URL.',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-pc-campus',
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
                'label' => 'Location override',
                'name' => 'hs_location_override',
                'type' => 'text',
                'instructions' => 'Optional. Replaces the campus name in the chip and the URL, e.g. Bangkok Trip. Still tick a Campus.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-pc-location',
                    'id' => '',
                ),
                'default_value' => '',
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
                'maxlength' => '',
            ),

            // 5b. Event dates: what readers see instead of the publish date (hs_final_date_label).
            array(
                'key' => 'field_hs_event_start',
                'label' => 'Event start',
                'name' => 'hs_event_start',
                'type' => 'date_picker',
                'instructions' => 'Shown to readers instead of the publish date. Leave empty to use the publish date.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-pc-event-start',
                    'id' => '',
                ),
                'display_format' => 'd/m/Y',
                'return_format' => 'Ymd',
                'first_day' => 1,
            ),
            array(
                'key' => 'field_hs_event_end',
                'label' => 'Event end',
                'name' => 'hs_event_end',
                'type' => 'date_picker',
                'instructions' => 'Only for events over several days.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-pc-event-end',
                    'id' => '',
                ),
                'display_format' => 'd/m/Y',
                'return_format' => 'Ymd',
                'first_day' => 1,
            ),

            // 6. Portrait Featured Image (Slider)
            array(
                'key' => 'field_hs_portrait_image',
                'label' => 'Portrait image (slider)',
                'name' => 'hs_portrait_image',
                'type' => 'image',
                'instructions' => 'Optional. Used by the slider; otherwise the Featured image is used.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-pc-portrait',
                    'id' => '',
                ),
                'return_format' => 'url',
                'preview_size' => 'medium',
                'library' => 'all',
            ),

            // 7. Hero focal point (desktop): which part of the Featured image stays in view in the 70vh hero.
            array(
                'key' => 'field_hs_hero_focus',
                'label' => 'Hero focal point',
                'name' => 'hs_hero_focus',
                'type' => 'text',
                'instructions' => 'Set by clicking the hero preview. Two percentages, left and top, e.g. "50 30". Empty is the centre.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-pc-hero-focus',
                    'id' => '',
                ),
                'default_value' => '',
                'placeholder' => '50 50',
                'maxlength' => 7,
            ),

            // 8. Image for phones and tablets: shown in the hero below 981px instead of the Featured image.
            array(
                'key' => 'field_hs_hero_mobile',
                'label' => 'Image for phones and tablets',
                'name' => 'hs_hero_mobile',
                'type' => 'image',
                'instructions' => 'Optional. Shown at the top of the Post page on phones and tablets instead of the Featured image, at its own shape. Set it from the hero preview\'s Phone view.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-pc-hero-mobile',
                    'id' => '',
                ),
                'return_format' => 'id',
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
 * Hero focal point: two whole percentages "X Y" (0–100), or '' for the centre. Anything else is cleared.
 */
function hs_hero_focus_sanitize($value) {
    if (preg_match('/^\s*(\d{1,3})\s+(\d{1,3})\s*$/', (string) $value, $m) && (int) $m[1] <= 100 && (int) $m[2] <= 100) {
        return (int) $m[1] . ' ' . (int) $m[2];
    }
    return '';
}
add_filter('acf/update_value/key=field_hs_hero_focus', 'hs_hero_focus_sanitize');

// On a single Post's page, tell the hero (class hs-post-hero in the Post template) where to focus.
add_action('wp_head', function () {
    if (!is_singular('post')) {
        return;
    }
    $post_id = get_queried_object_id();
    $focus   = hs_hero_focus_sanitize(get_post_meta($post_id, 'hs_hero_focus', true));
    if ('' !== $focus) {
        list($x, $y) = explode(' ', $focus);
        echo '<style id="hs-hero-focus">.hs-post-hero{--hs-hero-focus:' . (int) $x . '% ' . (int) $y . '%}</style>' . "\n";
    }
    // Phones and tablets (below Divi's 981px desktop breakpoint): the Post's own image for small screens, at its own shape.
    $mobile = (int) get_post_meta($post_id, 'hs_hero_mobile', true);
    $src    = $mobile && wp_attachment_is_image($mobile) ? wp_get_attachment_image_src($mobile, 'large') : false;
    if ($src && $src[1] && $src[2]) {
        echo '<style id="hs-hero-mobile">@media (max-width:980px){'
            . '.hs-post-hero .et_pb_image_wrap{display:block!important;width:100%!important;height:auto!important;aspect-ratio:' . (int) $src[1] . '/' . (int) $src[2] . ';background:url("' . esc_url($src[0]) . '") center/cover no-repeat}'
            . '.hs-post-hero .et_pb_image_wrap img{display:none!important}'
            . '}</style>' . "\n";
    }
});

/**
 * Event end needs an Event start, and can't be before it. ACF posts dates as Ymd.
 */
add_filter('acf/validate_value/key=field_hs_event_end', 'hs_validate_event_end', 10, 4);
function hs_validate_event_end($valid, $value, $field, $input_name) {
    if ($valid !== true || $value === '' || $value === null) {
        return $valid;
    }
    $start = isset($_POST['acf']['field_hs_event_start']) ? preg_replace('/\D/', '', (string) wp_unslash($_POST['acf']['field_hs_event_start'])) : '';
    if ($start === '') {
        return 'Add an Event start, or clear the Event end.';
    }
    if (preg_replace('/\D/', '', (string) $value) < $start) {
        return 'The Event end can\'t be before the Event start.';
    }
    return $valid;
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

/**
 * ====================================================================
 * POST CLASSIFICATION LAYOUT AND LABELLED PREVIEW (10 Nov 2026)
 * Groups the fields like the weekly update Articles → Posts panel and shows the
 * shared labelled card preview (Posts--Card-Preview) beside them. Layout only:
 * the fields keep their order, names and saving, so the Posts gatekeeper
 * (which finds them by data-name) works as before.
 * ====================================================================
 */
function hs_post_classification_screen() {
    if (!function_exists('get_current_screen')) {
        return false;
    }
    $screen = get_current_screen();
    return $screen && 'post' === $screen->base && 'post' === $screen->post_type;
}

add_action('admin_head', function () {
    if (!hs_post_classification_screen()) {
        return;
    }
    ?>
<style id="hs-pc-css">
/* A grid with named areas; headings are added by the script below. The preview column needs ~1400px. */
#acf-group_hs_post_classification { container-type: inline-size; }
#acf-group_hs_post_classification .acf-fields.hs-pc-grid {
    display: grid; column-gap: 12px; row-gap: 0; align-items: stretch; padding: 4px 16px 16px !important;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    grid-template-areas:
        "h-section h-campus" "section campus" "badge location"
        "h-event h-event" "start end"
        "h-tags h-tags" "tags tags"
        "h-images h-images" "images images"
        "toggle toggle" "pv pv";
}
@container (min-width: 1400px) {
    #acf-group_hs_post_classification .acf-fields.hs-pc-grid {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 560px;
        grid-template-rows: repeat(9, auto) 1fr;
        grid-template-areas:
            "h-section h-campus pv" "section campus pv" "badge location pv"
            "h-event h-event pv" "start end pv"
            "h-tags h-tags pv" "tags tags pv"
            "h-images h-images pv" "images images pv" ". . pv";
    }
    #acf-group_hs_post_classification .hs-pc-toggle { display: none; }
    #acf-group_hs_post_classification .hs-pc-preview { display: block !important; border-top: 0; border-left: 1px solid #f0f0f1; padding: 0 0 0 16px; margin-left: 4px; }
}
#acf-group_hs_post_classification .acf-fields.hs-pc-grid > .acf-field { float: none !important; width: auto !important; min-width: 0; margin: 0 !important; border-left: 0; border-top: 0; clear: none; }
#acf-group_hs_post_classification .acf-fields.hs-pc-grid > .acf-field + .acf-field { border-left: 0; }
#acf-group_hs_post_classification .hs-pc-section { grid-area: section; }
#acf-group_hs_post_classification .hs-pc-campus { grid-area: campus; }
#acf-group_hs_post_classification .hs-pc-badge { grid-area: badge; margin-top: 6px !important; }
#acf-group_hs_post_classification .hs-pc-location { grid-area: location; margin-top: 6px !important; }
#acf-group_hs_post_classification .hs-pc-event-start { grid-area: start; }
#acf-group_hs_post_classification .hs-pc-event-end { grid-area: end; }
#acf-group_hs_post_classification .hs-pc-tags { grid-area: tags; }
#acf-group_hs_post_classification .hs-pc-portrait, #acf-group_hs_post_classification .hs-pc-images { grid-area: images; }
#acf-group_hs_post_classification .hs-pc-hero-focus, #acf-group_hs_post_classification .hs-pc-hero-mobile { grid-column: 1 / 3; }
#acf-group_hs_post_classification .hs-pc-images .hs-pc-slot { padding: 10px 12px; background: #f6f7f7; border-radius: 2px; }
#acf-group_hs_post_classification .hs-pc-images .hs-pc-slot .hs-cip-label { display: block; font-weight: 600; margin-bottom: 4px; }
#acf-group_hs_post_classification .hs-pc-images .hs-cip-hint { margin: 4px 0 0; font-size: 12px; color: #646970; }
#acf-group_hs_post_classification .hs-pc-head { margin: 14px 0 6px; font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #50575e; }
#acf-group_hs_post_classification .hs-pc-toggle { grid-area: toggle; justify-self: start; margin: 12px 0 0; }
#acf-group_hs_post_classification .hs-pc-preview { grid-area: pv; display: none; border-top: 1px solid #f0f0f1; padding-top: 12px; margin-top: 12px; align-self: start; }
#acf-group_hs_post_classification .hs-pc-preview.is-open { display: block; }
/* The card and strip at 80% like the panel. */
#acf-group_hs_post_classification .hs-pc-preview .hs-cpv-cardbox, #acf-group_hs_post_classification .hs-pc-preview .hs-cpv-stripbox { zoom: .8; }
/* Tighter, consistent field boxes; the gatekeeper's own borders still show. */
#acf-group_hs_post_classification .hs-pc-grid > .acf-field { padding: 10px 12px !important; background: #f6f7f7; }
#acf-group_hs_post_classification .hs-pc-grid .acf-label { margin-bottom: 6px; }
#acf-group_hs_post_classification .hs-pc-grid .acf-label label { margin-bottom: 2px; }
#acf-group_hs_post_classification .hs-pc-grid .acf-label .description { font-size: 12px; color: #646970; }
#acf-group_hs_post_classification .hs-pc-tags .acf-checkbox-list { max-height: 220px; overflow-y: auto; columns: 3 220px; column-gap: 16px; background: #fff; }
#acf-group_hs_post_classification .hs-pc-tags .acf-checkbox-list li { break-inside: avoid; }
#acf-group_hs_post_classification .hs-pc-section .acf-checkbox-list { columns: 2 140px; column-gap: 12px; }
</style>
    <?php
});

add_action('admin_footer', function () {
    if (!hs_post_classification_screen()) {
        return;
    }
    ?>
<script id="hs-pc-js">
(function () {
    // This Post's own photos (saved content): Divi images, then galleries, then plain-HTML images.
    var IMAGES = <?php
        global $post;
        echo wp_json_encode( $post && function_exists( 'hs_card_preview_content_images' ) ? hs_card_preview_content_images( $post->post_content ) : array() );
    ?>;
    // Photos in the content that aren't Media Library items (imported archive Posts): they can't be picked.
    var UNLISTED = <?php echo (int) ( $post && function_exists( 'hs_card_preview_unlisted_image_count' ) ? hs_card_preview_unlisted_image_count( $post->post_content ) : 0 ); ?>;
    var BOX = '#acf-group_hs_post_classification';
    var fields, previewBox, pv, timer = null, last = '';

    function el(tag, cls, text) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (text) { n.textContent = text; } return n; }
    function field(name) { return document.querySelector(BOX + ' .acf-field[data-name="' + name + '"]'); }
    function checkedNames(name) {
        var f = field(name);
        return f ? Array.prototype.map.call(f.querySelectorAll('input[type="checkbox"]:checked'), function (i) {
            var label = i.closest('label');
            return label ? label.textContent.trim() : '';
        }).filter(Boolean) : [];
    }
    function inputValue(name, selector) { var f = field(name); var i = f && f.querySelector(selector); return i ? String(i.value || '') : ''; }
    function selectText(name) {
        var f = field(name); var s = f && f.querySelector('select');
        if (!s || !s.value) { return ''; }
        var o = s.options[s.selectedIndex];
        return o ? o.textContent.trim() : '';
    }
    function imageUrl(name) {
        var f = field(name); var up = f && f.querySelector('.acf-image-uploader');
        if (!up || !up.classList.contains('has-value')) { return ''; }
        var img = up.querySelector('img');
        return img ? img.getAttribute('src') || '' : '';
    }
    function editor() {
        var out = { title: '', date: '', featured: '' };
        if (!(window.wp && wp.data && wp.data.select)) { return out; }
        var ed = wp.data.select('core/editor');
        if (!ed || !ed.getEditedPostAttribute) { return out; }
        out.title = ed.getEditedPostAttribute('title') || '';
        out.date = ed.getEditedPostAttribute('date') || '';
        var id = ed.getEditedPostAttribute('featured_media');
        var core = wp.data.select('core');
        var media = id && core && core.getMedia ? core.getMedia(id) : null;
        if (media) {
            var sizes = media.media_details && media.media_details.sizes;
            out.featured = (sizes && (sizes.medium_large || sizes.medium || sizes.large) || {}).source_url || media.source_url || '';
        }
        return out;
    }
    function today() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function values() {
        var ed = editor();
        return {
            title: ed.title,
            section: checkedNames('hs_department_tax')[0] || '',
            badgeText: selectText('hs_thematic_chip_tax'),
            campusNames: checkedNames('hs_campus_tax'),
            location: inputValue('hs_location_override', 'input[type="text"]'),
            publishDate: ed.date ? String(ed.date).slice(0, 10) : today(), // a Post with no date yet is dated when published
            eventStart: inputValue('hs_event_start', 'input[type="hidden"]'),
            eventEnd: inputValue('hs_event_end', 'input[type="hidden"]'),
            image: imageUrl('hs_portrait_image') || ed.featured,
            tags: checkedNames('post_tag_tax')
        };
    }
    /* ---- Image picker: Featured = WordPress's own featured image (block editor store); Portrait = the ACF field ---- */
    var picker = null, pickState = {}, urls = {}, lastImages = '', hero = null, lastFeatured = null;
    IMAGES.forEach(function (i) { urls[i.id] = i.url; });
    function featuredNow() {
        var id = 0;
        if (window.wp && wp.data && wp.data.select('core/editor')) { id = wp.data.select('core/editor').getEditedPostAttribute('featured_media') || 0; }
        return { id: id, url: id ? (urls[id] || editor().featured || '') : '' };
    }
    function portraitNow() {
        var f = field('hs_portrait_image'), input = f && f.querySelector('.acf-image-uploader input[type="hidden"]');
        var id = input ? parseInt(input.value, 10) || 0 : 0;
        return { id: id, url: id ? (urls[id] || imageUrl('hs_portrait_image')) : '' };
    }
    function buildPicker() {
        var acfField = window.acf && acf.getField ? acf.getField('field_hs_portrait_image') : null;
        if (!window.hsCardPreview || !hsCardPreview.imagePicker || !acfField || !(window.wp && wp.data && wp.data.dispatch)) { return; } // the ACF box stays as it is
        picker = hsCardPreview.imagePicker({
            images: IMAGES,
            state: pickState,
            get: function (name) { return name === 'image' ? featuredNow() : portraitNow(); },
            set: function (name, id, url) {
                if (id && url) { urls[id] = url; }
                if (name === 'image') {
                    wp.data.dispatch('core/editor').editPost({ featured_media: id || 0 });
                } else if (id) {
                    acfField.render({ id: id, url: url });
                } else if (acfField.removeAttachment) {
                    acfField.removeAttachment();
                } else {
                    acfField.render({});
                }
                soon();
            },
            slots: {
                image: { label: 'Featured image', required: true, hint: function () { return 'Required to publish. The same image as Featured image in the sidebar.'; } },
                portrait: { label: 'Portrait (slider)', hint: function () { return 'Optional. Used by the slider; otherwise the Featured image is used.'; } }
            },
            source: 'Images in this Post',
            empty: UNLISTED
                ? 'This Post\u2019s ' + UNLISTED + ' photo' + (UNLISTED === 1 ? ' is' : 's are') + ' from the old site and not in the Media Library, so ' + (UNLISTED === 1 ? 'it' : 'they') + ' can\u2019t be picked here. Use Media Library.'
                : 'This Post has no images of its own yet. Use Media Library, or save after adding images in the Divi Builder.',
            classes: { slot: 'hs-pc-slot' }
        });
        var wrapper = el('div', 'hs-pc-images');
        wrapper.appendChild(picker.node);
        fields.appendChild(wrapper);
        field('hs_portrait_image').style.display = 'none'; // its input stays in the form, so the Portrait still saves
    }
    /* ---- Hero focal point (desktop): kept in the ACF field hs_hero_focus ---- */
    function focusInput() { var f = field('hs_hero_focus'); return f ? f.querySelector('input[type="text"]') : null; }
    function buildHero() {
        var input = focusInput();
        if (!input || !window.hsCardPreview || !hsCardPreview.heroFocus) { return null; } // the plain text field stays
        var mobileField = window.acf && acf.getField ? acf.getField('field_hs_hero_mobile') : null;
        function mobileNow() {
            var f = field('hs_hero_mobile'), i = f && f.querySelector('.acf-image-uploader input[type="hidden"]');
            var id = i ? parseInt(i.value, 10) || 0 : 0;
            return { id: id, url: id ? (urls[id] || imageUrl('hs_hero_mobile')) : '' };
        }
        hero = hsCardPreview.heroFocus({
            get: function () { return { url: featuredNow().url, focus: input.value, mobile: mobileNow() }; },
            set: function (focus) { input.value = focus; jQuery(input).trigger('change'); },
            images: IMAGES,
            setMobile: mobileField ? function (id, url) {
                if (id && url) { urls[id] = url; }
                if (id) { mobileField.render({ id: id, url: url }); } else if (mobileField.removeAttachment) { mobileField.removeAttachment(); } else { mobileField.render({}); }
            } : null
        });
        field('hs_hero_focus').style.display = 'none'; // its input stays in the form, so the focal point saves
        if (mobileField) { field('hs_hero_mobile').style.display = 'none'; } // likewise for the phone image
        return hero.node;
    }
    function refresh() {
        var featuredId = featuredNow().id;
        // A different photo starts centred. Only when one real image replaces another, so a slow-loading editor can't wipe a saved focus.
        if (lastFeatured && featuredId && featuredId !== lastFeatured && focusInput()) { focusInput().value = ''; jQuery(focusInput()).trigger('change'); }
        lastFeatured = featuredId;
        if (hero) { hero.draw(); }
        if (picker) {
            var key = JSON.stringify([featuredNow(), portraitNow()]);
            if (key !== lastImages) { lastImages = key; picker.draw(); }
        }
        if (!pv) { return; }
        var v = values(), key = JSON.stringify(v);
        if (key === last) { return; }
        last = key;
        pv.update(v);
    }
    function soon() { clearTimeout(timer); timer = setTimeout(refresh, 150); }

    function build() {
        fields = document.querySelector(BOX + ' > .inside.acf-fields') || document.querySelector(BOX + ' .acf-fields');
        if (!fields || fields.classList.contains('hs-pc-grid')) { return; }
        fields.classList.add('hs-pc-grid');
        [['h-section', 'Website Section'], ['h-campus', 'Campus'], ['h-event', 'Event date (shown to readers)'], ['h-tags', 'Tags'], ['h-images', 'Images']].forEach(function (h) {
            var head = el('div', 'hs-pc-head', h[1]);
            head.style.gridArea = h[0];
            fields.appendChild(head);
        });
        if (!window.hsCardPreview) { return; } // Posts--Card-Preview is off: the layout still works.
        pv = window.hsCardPreview.create({ labels: {
            badge: 'Shows the Yellow badge override if set, otherwise the Website Section.',
            section: 'Shows the Yellow badge override if set, otherwise the Website Section.',
            place: 'Shows the Location override if set, otherwise the Campus (or “Both Campuses”).',
            chipPlace: 'Shows the Location override if set, otherwise the Campus.',
            image: 'The Portrait image if chosen, otherwise the Featured image (in the sidebar).',
            title: 'The Post title.'
        } });
        buildPicker();
        previewBox = el('div', 'hs-pc-preview');
        previewBox.appendChild(pv.node);
        var heroNode = buildHero();
        if (heroNode) { previewBox.appendChild(heroNode); }
        var toggle = el('button', 'button-link hs-pc-toggle', 'Show preview');
        toggle.type = 'button';
        toggle.addEventListener('click', function () {
            var open = !previewBox.classList.contains('is-open');
            previewBox.classList.toggle('is-open', open);
            toggle.textContent = open ? 'Hide preview' : 'Show preview';
            pv.redraw();
        });
        fields.appendChild(toggle);
        fields.appendChild(previewBox);

        // Field changes: typing, ticks, select2, date pickers. Image picks change the DOM, so watch the fields too.
        fields.addEventListener('input', soon);
        fields.addEventListener('change', soon);
        if (window.jQuery) { jQuery(fields).on('select2:select select2:clear change', soon); }
        new MutationObserver(function (list) {
            if (list.some(function (m) { return !previewBox.contains(m.target); })) { soon(); }
        }).observe(fields, { subtree: true, childList: true, attributes: true, attributeFilter: ['class', 'src', 'value'] });
        // Title, publish date and featured image live in the editor's store.
        if (window.wp && wp.data && wp.data.subscribe) { wp.data.subscribe(soon); }
        refresh();
        setTimeout(function () { pv.redraw(); }, 300);
    }

    if (window.acf && acf.addAction) { acf.addAction('ready', build); }
    document.addEventListener('DOMContentLoaded', function () { setTimeout(build, 0); });
    window.addEventListener('load', build);
}());
</script>
    <?php
});
