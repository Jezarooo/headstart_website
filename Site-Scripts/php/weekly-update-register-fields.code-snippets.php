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
                'label' => 'Blurb',
                'name' => 'blurb',
                'type' => 'wysiwyg',
                'instructions' => 'Shown under this update on the Campus Hub page.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-wu-blurb',
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
                    'width' => '',
                    'class' => 'hs-wu-campus',
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
                'label' => 'Week number',
                'name' => 'hs_week_number',
                'type' => 'number',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-wu-week',
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
                'label' => 'In this issue',
                'name' => 'in_this_issue_tax',
                'type' => 'taxonomy',
                'instructions' => 'Tick the topics this issue covers, or add new ones. (Issues built with Divi articles list them automatically instead.)',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-wu-issue',
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

            // 4b. In this issue, for issues with Divi articles: the article headings with short names.
            // Drawn by hs_issue_contents_render_field(); saves nothing itself (see hs_issue_contents_on_save).
            array(
                'key' => 'field_weekly_update_contents',
                'label' => 'In this issue',
                'name' => 'hs_issue_contents_ui',
                'type' => 'message',
                'instructions' => '',
                'message' => '',
                'new_lines' => '',
                'esc_html' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-wu-issue hs-wu-contents',
                    'id' => '',
                ),
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
                    'width' => '',
                    'class' => 'hs-wu-archive',
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

/**
 * ====================================================================
 * IN THIS ISSUE: THE ISSUE'S ARTICLE HEADINGS (10 Nov 2026)
 * Instead of picking tags, the list is every article in the issue: each Divi group
 * with the class hs-weekly-article and its first h3 (the same rule as the Articles
 * → Posts tool, so promos are left out). Rebuilt on every save; an editor's short
 * names are kept by heading. Stored in hs_issue_contents as JSON:
 *   [ { "key": "heading-slug", "heading": "Full heading", "label": "Short name or ''" } ]
 * Issues without Divi 5 articles (older ones) keep their In This Issue tags.
 * Plan: work/weekly-issue-contents/plan.md
 * ====================================================================
 */

const HS_ISSUE_CONTENTS_META = 'hs_issue_contents';

/** Plain text of some HTML, spaces tidied. */
function hs_issue_plain( $html ) {
    return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) ) );
}

/** Does a Divi 5 block carry this CSS class? */
function hs_issue_block_has_class( $b, $class ) {
    $attrs = $b['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? array();
    foreach ( (array) $attrs as $a ) {
        if ( isset( $a['name'], $a['value'] ) && 'class' === $a['name'] && preg_match( '/(^|\s)' . preg_quote( $class, '/' ) . '(\s|$)/', (string) $a['value'] ) ) {
            return true;
        }
    }
    return false;
}

/** The first h3 inside some blocks, as plain text, or null. */
function hs_issue_first_h3( $blocks ) {
    foreach ( $blocks as $b ) {
        $text = $b['attrs']['content']['innerContent']['desktop']['value'] ?? '';
        if ( is_string( $text ) && preg_match( '#<h3[^>]*>(.*?)</h3>#is', $text, $m ) ) {
            return hs_issue_plain( $m[1] );
        }
        if ( ! empty( $b['innerBlocks'] ) ) {
            $found = hs_issue_first_h3( $b['innerBlocks'] );
            if ( null !== $found ) {
                return $found;
            }
        }
    }
    return null;
}

/**
 * The issue's articles in page order: [ 'items' => [ [ key, heading ] ], 'skipped' => articles with no h3 ].
 * Keys are the heading in lower case with dashes (repeats get --2, --3), so a short name
 * stays with its article when the issue is edited.
 */
function hs_issue_headings( $content ) {
    $out = array( 'items' => array(), 'skipped' => 0 );
    if ( false === strpos( (string) $content, 'wp:divi/' ) ) {
        return $out;
    }
    $seen = array();
    $walk = function ( $list ) use ( &$walk, &$out, &$seen ) {
        foreach ( $list as $b ) {
            if ( 'divi/group' === $b['blockName'] && hs_issue_block_has_class( $b, 'hs-weekly-article' ) ) {
                $heading = hs_issue_first_h3( array( $b ) );
                if ( null === $heading || '' === $heading ) {
                    $out['skipped']++;
                    continue;
                }
                $key = $base = trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( $heading ) ) ), '-' );
                for ( $n = 2; isset( $seen[ $key ] ); $n++ ) {
                    $key = $base . '--' . $n;
                }
                $seen[ $key ]   = true;
                $out['items'][] = array( 'key' => $key, 'heading' => $heading );
                continue;
            }
            if ( ! empty( $b['innerBlocks'] ) ) {
                $walk( $b['innerBlocks'] );
            }
        }
    };
    $walk( parse_blocks( (string) $content ) );
    return $out;
}

/** The stored list: [ { key, heading, label } ]. */
function hs_issue_contents( $post_id ) {
    $list = json_decode( (string) get_post_meta( $post_id, HS_ISSUE_CONTENTS_META, true ), true );
    return is_array( $list ) ? array_values( array_filter( $list, function ( $i ) { return is_array( $i ) && isset( $i['key'], $i['heading'] ); } ) ) : array();
}

/** The names to show: each article's short name, or its heading. Empty for issues without articles. */
function hs_issue_contents_names( $post_id ) {
    return array_map( function ( $i ) {
        return '' !== trim( (string) ( $i['label'] ?? '' ) ) ? trim( (string) $i['label'] ) : $i['heading'];
    }, hs_issue_contents( $post_id ) );
}

/**
 * Rebuild the list from the content, keeping short names by key.
 * $labels (key => text) overrides stored names, e.g. from the edit box; '' clears one.
 * Returns the new list. Deletes the meta when the issue has no articles.
 */
function hs_issue_contents_refresh( $post_id, $labels = null ) {
    $found = hs_issue_headings( get_post_field( 'post_content', $post_id ) );
    $old   = array();
    foreach ( hs_issue_contents( $post_id ) as $i ) {
        $old[ $i['key'] ] = (string) ( $i['label'] ?? '' );
    }
    $list = array();
    foreach ( $found['items'] as $item ) {
        $label = is_array( $labels ) && array_key_exists( $item['key'], $labels ) ? $labels[ $item['key'] ] : ( $old[ $item['key'] ] ?? '' );
        $label = sanitize_text_field( (string) $label );
        $list[] = array( 'key' => $item['key'], 'heading' => $item['heading'], 'label' => $label === $item['heading'] ? '' : $label );
    }
    if ( ! $list ) {
        delete_post_meta( $post_id, HS_ISSUE_CONTENTS_META );
        return array();
    }
    update_post_meta( $post_id, HS_ISSUE_CONTENTS_META, wp_slash( wp_json_encode( $list, JSON_UNESCAPED_UNICODE ) ) );
    return $list;
}

// Every save of a weekly update: block editor (REST), Divi, quick edit, and the edit box's form post.
add_action( 'save_post_weekly_update', 'hs_issue_contents_on_save', 20, 2 );
function hs_issue_contents_on_save( $post_id, $post ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
        return;
    }
    $labels = null;
    if ( isset( $_POST['hs_issue_contents_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hs_issue_contents_nonce'] ) ), 'hs_issue_contents_' . $post_id ) && current_user_can( 'edit_post', $post_id ) ) {
        $labels = array();
        foreach ( (array) ( $_POST['hs_issue_label'] ?? array() ) as $key => $text ) {
            if ( preg_match( '/^[a-z0-9-]+$/', (string) $key ) && is_string( $text ) ) {
                $labels[ (string) $key ] = wp_unslash( $text );
            }
        }
    }
    hs_issue_contents_refresh( $post_id, $labels );
}

/** Issues that have articles hide the old tags field: the list replaces it. Older issues keep it. */
add_filter( 'acf/prepare_field/key=field_weekly_update_tags', 'hs_issue_hide_tags_field' );
function hs_issue_hide_tags_field( $field ) {
    $post_id = (int) ( $GLOBALS['post']->ID ?? 0 );
    if ( $post_id && 'weekly_update' === get_post_type( $post_id ) && hs_issue_headings( get_post_field( 'post_content', $post_id ) )['items'] ) {
        return false;
    }
    return $field;
}

/* The edit box ------------------------------------------------------------ */

/** The list shows only on issues that have articles (the tags field shows on the others). */
add_filter( 'acf/prepare_field/key=field_weekly_update_contents', 'hs_issue_contents_prepare_field' );
function hs_issue_contents_prepare_field( $field ) {
    $post_id = (int) ( $GLOBALS['post']->ID ?? 0 );
    if ( ! $post_id || 'weekly_update' !== get_post_type( $post_id ) || ! hs_issue_headings( get_post_field( 'post_content', $post_id ) )['items'] ) {
        return false;
    }
    return $field;
}

add_action( 'acf/render_field/key=field_weekly_update_contents', 'hs_issue_contents_render_field' );
function hs_issue_contents_render_field( $field ) {
    $post_id = (int) ( $GLOBALS['post']->ID ?? 0 );
    if ( $post_id ) {
        hs_issue_contents_render_box( get_post( $post_id ) );
    }
}

/** Rows for the box: the stored list, refreshed from the content first so it's never stale. */
function hs_issue_contents_rows( $post_id ) {
    $list    = hs_issue_contents_refresh( $post_id );
    $skipped = hs_issue_headings( get_post_field( 'post_content', $post_id ) )['skipped'];
    return array( 'items' => $list, 'skipped' => $skipped );
}

function hs_issue_contents_render_box( $post ) {
    wp_nonce_field( 'hs_issue_contents_' . $post->ID, 'hs_issue_contents_nonce' );
    $data = hs_issue_contents_rows( $post->ID );
    echo '<p class="description hs-ic-hint">Each article’s heading (promos left out). Add a short name for the Campus Hub, or leave it blank.</p>';
    echo '<div id="hs-issue-contents-list" class="hs-ic-scroll" data-post="' . (int) $post->ID . '" data-nonce="' . esc_attr( wp_create_nonce( 'hs_issue_contents_get' ) ) . '">';
    hs_issue_contents_print_rows( $data );
    echo '</div>';
}

function hs_issue_contents_print_rows( $data ) {
    echo '<ol class="hs-ic-list">';
    foreach ( $data['items'] as $i ) {
        printf(
            '<li class="hs-ic-row"><label class="hs-ic-heading" for="hs-ic-%2$s">%1$s</label><input type="text" class="hs-ic-input" id="hs-ic-%2$s" name="hs_issue_label[%2$s]" value="%3$s" placeholder="Short name (optional)" maxlength="80" /></li>',
            esc_attr( $i['heading'] ),
            esc_attr( $i['key'] ),
            esc_attr( $i['label'] )
        );
    }
    echo '</ol>';
    if ( $data['skipped'] ) {
        printf( '<p class="description hs-ic-skipped">%d article%s left out because %s no heading (h3).</p>', (int) $data['skipped'], 1 === (int) $data['skipped'] ? ' is' : 's are', 1 === (int) $data['skipped'] ? 'it has' : 'they have' );
    }
}

/** The box's rows after a save, so new articles appear without a reload. */
add_action( 'wp_ajax_hs_issue_contents_get', 'hs_issue_contents_ajax_get' );
function hs_issue_contents_ajax_get() {
    check_ajax_referer( 'hs_issue_contents_get', 'nonce' );
    $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
    if ( ! $post_id || 'weekly_update' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( 'Not allowed.', 403 );
    }
    ob_start();
    hs_issue_contents_print_rows( hs_issue_contents_rows( $post_id ) );
    wp_send_json_success( ob_get_clean() );
}

add_action( 'admin_head-post.php', 'hs_issue_contents_admin_assets' );
add_action( 'admin_head-post-new.php', 'hs_issue_contents_admin_assets' );
function hs_issue_contents_admin_assets() {
    $screen = get_current_screen();
    if ( ! $screen || 'weekly_update' !== $screen->post_type ) {
        return;
    }
    ?>
<style id="hs-issue-contents-css">
/* Weekly Update Details as a grid: Blurb wide on the left, Campus over Week number, In this issue on the right
   (scrolling, as tall as the blurb), Archive Post across the bottom. Narrow boxes stack. */
#acf-group_6a3772490951b { container-type: inline-size; }
#acf-group_6a3772490951b > .inside.acf-fields { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(220px, 0.65fr) minmax(0, 1.4fr); grid-template-areas: "blurb campus issue" "blurb week issue" "archive archive archive"; grid-template-rows: auto 1fr auto; column-gap: 16px; row-gap: 4px; padding: 4px 12px 12px; align-items: stretch; }
#acf-group_6a3772490951b > .inside.acf-fields::after { display: none; }
#acf-group_6a3772490951b > .inside > .acf-field { width: auto !important; float: none; clear: none; min-height: 0; margin: 0; border-left: 0; border-top: 0; padding: 12px !important; }
#acf-group_6a3772490951b > .inside > .acf-field.hs-wu-blurb { grid-area: blurb; }
#acf-group_6a3772490951b > .inside > .acf-field.hs-wu-campus { grid-area: campus; }
#acf-group_6a3772490951b > .inside > .acf-field.hs-wu-week { grid-area: week; align-self: start; }
#acf-group_6a3772490951b > .inside > .acf-field.hs-wu-issue { grid-area: issue; min-width: 0; }
#acf-group_6a3772490951b > .inside > .acf-field.hs-wu-archive { grid-area: archive; border-top: 1px solid #f0f0f1; padding-top: 10px !important; }
#acf-group_6a3772490951b > .inside > .acf-field > .acf-label { margin-bottom: 6px; }
#acf-group_6a3772490951b > .inside > .acf-field > .acf-label label { font-weight: 600; }
#acf-group_6a3772490951b > .inside > .acf-field > .acf-label .description { margin-top: 2px; color: #646970; }
#acf-group_6a3772490951b .hs-wu-blurb iframe { height: 260px !important; min-height: 260px !important; }
#acf-group_6a3772490951b .hs-wu-week input[type=number] { max-width: 120px; }
#acf-group_6a3772490951b .hs-wu-campus ul.acf-radio-list { display: grid; gap: 4px; }
#acf-group_6a3772490951b .hs-wu-campus ul.acf-radio-list label { white-space: nowrap; }
#acf-group_6a3772490951b .hs-wu-campus ul.acf-radio-list li { margin: 0; }
#acf-group_6a3772490951b .hs-wu-issue .acf-checkbox-list { max-height: 300px; overflow-y: auto; }
@container (max-width: 820px) {
    #acf-group_6a3772490951b > .inside.acf-fields { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); grid-template-areas: "blurb blurb" "campus week" "issue issue" "archive archive"; grid-template-rows: none; }
}
/* The article list: number, heading, then the short-name box under it; scrolls to the blurb's height. */
#hs-issue-contents-list.hs-ic-scroll { max-height: 312px; overflow-y: auto; border: 1px solid #dcdcde; border-radius: 4px; background: #fff; }
#hs-issue-contents-list .hs-ic-list { margin: 0; padding: 0; list-style: none; counter-reset: hsic; }
#hs-issue-contents-list .hs-ic-row { counter-increment: hsic; display: grid; grid-template-columns: 22px minmax(0, 1fr); column-gap: 6px; row-gap: 4px; margin: 0; padding: 8px 10px; border-top: 1px solid #f0f0f1; }
#hs-issue-contents-list .hs-ic-row:first-child { border-top: 0; }
#hs-issue-contents-list .hs-ic-row::before { content: counter(hsic) "."; grid-row: 1; color: #646970; font-size: 12px; line-height: 18px; text-align: right; }
#hs-issue-contents-list .hs-ic-heading { grid-column: 2; font-size: 12px; font-weight: 600; line-height: 18px; color: #1d2327; overflow-wrap: anywhere; }
#hs-issue-contents-list .hs-ic-input { grid-column: 2; width: 100%; min-height: 30px; font-size: 13px; }
#hs-issue-contents-list .hs-ic-input:placeholder-shown { background: #fbfbfc; }
.hs-wu-contents .hs-ic-skipped { margin: 6px 10px 8px; }
.hs-wu-contents .hs-ic-hint { margin: -2px 0 8px; color: #646970; }
</style>
<script id="hs-issue-contents-js">
(function () {
    if (!window.wp || !wp.data || !wp.data.subscribe) { return; }
    var wasSaving = false;
    wp.data.subscribe(function () {
        var ed = wp.data.select('core/editor'), ep = wp.data.select('core/edit-post');
        if (!ed) { return; }
        var saving = ed.isSavingPost() || (ep && ep.isSavingMetaBoxes && ep.isSavingMetaBoxes());
        if (wasSaving && !saving && !ed.isAutosavingPost()) { refresh(); }
        wasSaving = saving;
    });
    function refresh() {
        var box = document.getElementById('hs-issue-contents-list');
        if (!box) { return; }
        var body = new URLSearchParams({ action: 'hs_issue_contents_get', nonce: box.dataset.nonce, post_id: box.dataset.post });
        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) { if (res && res.success) { box.innerHTML = res.data; } })
            .catch(function () {});
    }
})();
</script>
    <?php
}

/* One-off fill for existing issues ----------------------------------------- */

// Builds hs_issue_contents for issues that have articles, 50 per admin page load, then a flag stops it.
// Writes only that meta key: no re-save, no change to tags or dates.
add_action( 'admin_init', 'hs_issue_contents_fill_v1' );
function hs_issue_contents_fill_v1() {
    if ( get_option( 'hs_issue_contents_fill_v1' ) || wp_doing_ajax() ) {
        return;
    }
    $done = (array) get_option( 'hs_issue_contents_fill_v1_done', array() );
    $ids  = get_posts( array(
        'post_type'        => 'weekly_update',
        'post_status'      => 'any',
        'posts_per_page'   => 50,
        'fields'           => 'ids',
        'orderby'          => 'ID',
        'order'            => 'ASC',
        'no_found_rows'    => true,
        'suppress_filters' => true,
        'post__not_in'     => $done ? $done : array( 0 ),
        'meta_query'       => array( array( 'key' => HS_ISSUE_CONTENTS_META, 'compare' => 'NOT EXISTS' ) ),
    ) );
    foreach ( $ids as $id ) {
        hs_issue_contents_refresh( $id );
        $done[] = (int) $id;
    }
    if ( count( $ids ) < 50 ) {
        update_option( 'hs_issue_contents_fill_v1', gmdate( 'c' ), false );
        delete_option( 'hs_issue_contents_fill_v1_done' );
        if ( function_exists( 'hs_clear_weekly_update_transients' ) ) {
            hs_clear_weekly_update_transients(); // the Campus Hub list is cached; show the new names
        }
    } else {
        update_option( 'hs_issue_contents_fill_v1_done', $done, false );
    }
}
