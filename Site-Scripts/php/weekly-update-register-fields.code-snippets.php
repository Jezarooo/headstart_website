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
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => 'hs-wu-blurb',
                    'id' => '',
                ),
                'default_value' => '',
                'tabs' => 'visual',
                'toolbar' => 'basic',
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

/** A wise length for a hub chip: longer names still work but get flagged in the editor. */
const HS_ISSUE_NAME_MAX = 30;

/** The name an item shows: its short name, or its heading. */
function hs_issue_item_name( $i ) {
    return '' !== trim( (string) ( $i['label'] ?? '' ) ) ? trim( (string) $i['label'] ) : (string) $i['heading'];
}

/** The names to show: the ticked articles' short names, or headings. Empty for issues without articles. */
/**
 * Tags first (10 Nov 2026): an issue that already has In This Issue tags keeps using them
 * (Campus Hub, admin list, edit screen). Only issues without tags use the article list.
 */
function hs_issue_uses_tags( $post_id ) {
    $terms = get_the_terms( $post_id, 'in_this_issue' );
    return ! empty( $terms ) && ! is_wp_error( $terms );
}

/** The ticked articles' names. Empty for tagged issues, so callers fall back to the tags. */
function hs_issue_contents_names( $post_id ) {
    if ( hs_issue_uses_tags( $post_id ) ) {
        return array();
    }
    $shown = array_filter( hs_issue_contents( $post_id ), function ( $i ) {
        return ! isset( $i['show'] ) || $i['show'];
    } );
    return array_values( array_map( 'hs_issue_item_name', $shown ) );
}

/**
 * Rebuild the list from the content, keeping short names by key.
 * $labels (key => text) overrides stored names, e.g. from the edit box; '' clears one.
 * Returns the new list. Deletes the meta when the issue has no articles.
 */
function hs_issue_contents_refresh( $post_id, $labels = null, $shows = null ) {
    $found = hs_issue_headings( get_post_field( 'post_content', $post_id ) );
    $old   = array();
    foreach ( hs_issue_contents( $post_id ) as $i ) {
        $old[ $i['key'] ] = $i;
    }
    $list = array();
    foreach ( $found['items'] as $item ) {
        $k     = $item['key'];
        $label = is_array( $labels ) && array_key_exists( $k, $labels ) ? $labels[ $k ] : ( $old[ $k ]['label'] ?? '' );
        $label = sanitize_text_field( (string) $label );
        $show  = is_array( $shows ) && array_key_exists( $k, $shows ) ? (bool) $shows[ $k ] : ( isset( $old[ $k ]['show'] ) ? (bool) $old[ $k ]['show'] : true );
        $list[] = array( 'key' => $k, 'heading' => $item['heading'], 'label' => $label === $item['heading'] ? '' : $label, 'show' => $show );
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
    $shows  = null;
    if ( isset( $_POST['hs_issue_contents_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hs_issue_contents_nonce'] ) ), 'hs_issue_contents_' . $post_id ) && current_user_can( 'edit_post', $post_id ) ) {
        $labels = array();
        foreach ( (array) ( $_POST['hs_issue_label'] ?? array() ) as $key => $text ) {
            if ( preg_match( '/^[a-z0-9-]+$/', (string) $key ) && is_string( $text ) ) {
                $labels[ (string) $key ] = wp_unslash( $text );
            }
        }
        // Every listed article posts its name box, so a name without a ticked box means "leave it out".
        $ticked = (array) ( $_POST['hs_issue_show'] ?? array() );
        $shows  = array();
        foreach ( array_keys( $labels ) as $key ) {
            $shows[ $key ] = ! empty( $ticked[ $key ] );
        }
    }
    hs_issue_contents_refresh( $post_id, $labels, $shows );
}

/** Archive Post only matters for issues imported from the old site: show it only where it's already ticked. */
add_filter( 'acf/prepare_field/key=field_6a3772e6f9322', 'hs_wu_hide_archive_field' );
function hs_wu_hide_archive_field( $field ) {
    return empty( $field['value'] ) ? false : $field;
}

/** The tags field shows only on issues that already have tags; untagged issues use the article list instead. */
add_filter( 'acf/prepare_field/key=field_weekly_update_tags', 'hs_issue_hide_tags_field' );
function hs_issue_hide_tags_field( $field ) {
    $post_id = (int) ( $GLOBALS['post']->ID ?? 0 );
    if ( $post_id && 'weekly_update' === get_post_type( $post_id ) && ! hs_issue_uses_tags( $post_id ) ) {
        return false;
    }
    return $field;
}

/* The edit box: inside Weekly Update Details, in the In this issue slot ----- */

/** The list shows only on untagged issues that have articles (tagged issues show the tags field). */
add_filter( 'acf/prepare_field/key=field_weekly_update_contents', 'hs_issue_contents_prepare_field' );
function hs_issue_contents_prepare_field( $field ) {
    $post_id = (int) ( $GLOBALS['post']->ID ?? 0 );
    if ( ! $post_id || 'weekly_update' !== get_post_type( $post_id ) || hs_issue_uses_tags( $post_id ) || ! hs_issue_headings( get_post_field( 'post_content', $post_id ) )['items'] ) {
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
    $tip = sprintf( 'Every article in this issue, from its heading (promos are left out). Untick one to leave it off the Campus Hub. Short names over %d characters turn red: they make long chips.', HS_ISSUE_NAME_MAX );
    echo '<div class="hs-ic-card">';
    echo '<div class="hs-ic-head"><span class="hs-ic-title">In this issue</span><span class="hs-ic-tip" tabindex="0" role="button" aria-label="About this list" aria-describedby="hs-ic-tipbox"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><span class="hs-ic-tipbox" id="hs-ic-tipbox" role="tooltip">' . esc_html( $tip ) . '</span></span><span class="hs-ic-count"></span></div>';
    echo '<div id="hs-issue-contents-list" class="hs-ic-scroll" data-post="' . (int) $post->ID . '" data-max="' . (int) HS_ISSUE_NAME_MAX . '" data-nonce="' . esc_attr( wp_create_nonce( 'hs_issue_contents_get' ) ) . '">';
    hs_issue_contents_print_rows( hs_issue_contents_rows( $post->ID ) );
    echo '</div></div>';
}

function hs_issue_contents_print_rows( $data ) {
    echo '<ol class="hs-ic-list">';
    foreach ( $data['items'] as $i ) {
        $show = ! isset( $i['show'] ) || $i['show'];
        $len  = mb_strlen( hs_issue_item_name( $i ) );
        $over = $len > HS_ISSUE_NAME_MAX;
        printf(
            '<li class="hs-ic-row%5$s"><input type="checkbox" class="hs-ic-show" id="hs-ic-show-%2$s" name="hs_issue_show[%2$s]" value="1"%4$s aria-label="Show on the Campus Hub" /><label class="hs-ic-heading" for="hs-ic-show-%2$s">%1$s</label><input type="text" class="hs-ic-input%6$s" name="hs_issue_label[%2$s]" value="%3$s" placeholder="Short name (optional)" maxlength="80" aria-label="Short name for %1$s" /><span class="hs-ic-len%6$s">%7$d/%8$d</span></li>',
            esc_html( $i['heading'] ),
            esc_attr( $i['key'] ),
            esc_attr( $i['label'] ),
            $show ? ' checked' : '',
            $show ? '' : ' is-off',
            $over ? ' is-long' : '',
            $len,
            HS_ISSUE_NAME_MAX
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
/* Weekly Update Details as a grid: Blurb | Campus over Week number | In this issue, all one height
   (the Campus + Week number column sets it), Archive Post across the bottom. Narrow boxes stack. */
#acf-group_6a3772490951b { container-type: inline-size; }
#acf-group_6a3772490951b > .inside.acf-fields { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(220px, 0.65fr) minmax(0, 1.45fr); grid-template-areas: "blurb campus issue" "blurb week issue" "archive archive archive"; grid-template-rows: auto 1fr auto; column-gap: 16px; row-gap: 6px; padding: 14px 12px 12px !important; align-items: stretch; }
#acf-group_6a3772490951b > .inside.acf-fields::after { display: none; }
#acf-group_6a3772490951b > .inside > .acf-field { width: auto !important; float: none; clear: none; min-height: 0; margin: 0; border-left: 0; border-top: 0; padding: 12px !important; }
#acf-group_6a3772490951b > .inside > .acf-field.hs-wu-blurb { grid-area: blurb; display: flex; flex-direction: column; padding: 0 !important; }
#acf-group_6a3772490951b > .inside > .acf-field.hs-wu-campus { grid-area: campus; }
#acf-group_6a3772490951b > .inside > .acf-field.hs-wu-week { grid-area: week; }
#acf-group_6a3772490951b > .inside > .acf-field.hs-wu-issue { grid-area: issue; min-width: 0; position: relative; padding: 0 !important; }
#acf-group_6a3772490951b > .inside > .acf-field.hs-wu-archive { grid-area: archive; border-top: 1px solid #f0f0f1; padding: 10px 0 0 !important; }
#acf-group_6a3772490951b > .inside > .acf-field > .acf-label { margin-bottom: 6px; }
#acf-group_6a3772490951b > .inside > .acf-field > .acf-label label { font-weight: 600; }
#acf-group_6a3772490951b .hs-wu-week input[type=number] { max-width: 120px; }
#acf-group_6a3772490951b .hs-wu-campus ul.acf-radio-list { display: grid; gap: 4px; }
#acf-group_6a3772490951b .hs-wu-campus ul.acf-radio-list label { white-space: nowrap; }
/* Blurb: its label and a one-row toolbar, the editor filling the rest of the column's height. */
#acf-group_6a3772490951b .hs-wu-blurb > .acf-label { padding: 13px 0 0; }
#acf-group_6a3772490951b .hs-wu-blurb > .acf-input { flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; }
#acf-group_6a3772490951b .hs-wu-blurb .acf-editor-wrap, #acf-group_6a3772490951b .hs-wu-blurb .wp-editor-container { flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; }
#acf-group_6a3772490951b .hs-wu-blurb .mce-tinymce, #acf-group_6a3772490951b .hs-wu-blurb .mce-container-body.mce-stack-layout { flex: 1 1 auto; display: flex !important; flex-direction: column; min-height: 0; }
#acf-group_6a3772490951b .hs-wu-blurb .mce-edit-area { flex: 1 1 auto; min-height: 0; }
#acf-group_6a3772490951b .hs-wu-blurb .mce-statusbar { display: none; }
#acf-group_6a3772490951b .hs-wu-blurb iframe { height: 100% !important; min-height: 110px !important; }
/* In this issue (and the older issues' tags): a card filling the row, its list scrolling inside. */
#acf-group_6a3772490951b .hs-wu-issue > .acf-label { display: none; }
#acf-group_6a3772490951b .hs-wu-contents > .acf-input, #acf-group_6a3772490951b .hs-wu-contents .hs-ic-card { position: absolute; inset: 0; }
#acf-group_6a3772490951b .hs-ic-card { display: flex; flex-direction: column; border: 1px solid #dcdcde; border-radius: 4px; background: #fff; }
#acf-group_6a3772490951b .hs-ic-head { position: relative; z-index: 5; display: flex; align-items: center; gap: 4px; padding: 12px 12px 8px; border-bottom: 1px solid #f0f0f1; }
#acf-group_6a3772490951b .hs-ic-title { font-weight: 600; }
#acf-group_6a3772490951b .hs-ic-tip { position: relative; display: inline-flex; color: #8c8f94; cursor: help; outline: 0; border-radius: 50%; }
#acf-group_6a3772490951b .hs-ic-tip .dashicons { font-size: 16px; width: 16px; height: 16px; }
#acf-group_6a3772490951b .hs-ic-tip:hover, #acf-group_6a3772490951b .hs-ic-tip:focus { color: #2271b1; }
#acf-group_6a3772490951b .hs-ic-tip:focus-visible { box-shadow: 0 0 0 2px #2271b1; }
#acf-group_6a3772490951b .hs-ic-tipbox { position: absolute; top: calc(100% + 8px); left: -12px; z-index: 100; width: 260px; padding: 8px 10px; border-radius: 4px; background: #1d2327; color: #fff; font-size: 12px; font-weight: 400; line-height: 1.45; box-shadow: 0 4px 12px rgba(0, 0, 0, .2); opacity: 0; visibility: hidden; transition: opacity .12s; pointer-events: none; }
#acf-group_6a3772490951b .hs-ic-tipbox::before { content: ""; position: absolute; top: -5px; left: 15px; border: 5px solid transparent; border-top: 0; border-bottom-color: #1d2327; }
#acf-group_6a3772490951b .hs-ic-tip:hover .hs-ic-tipbox, #acf-group_6a3772490951b .hs-ic-tip:focus .hs-ic-tipbox { opacity: 1; visibility: visible; }
#acf-group_6a3772490951b .hs-ic-count { margin-left: auto; font-size: 11px; color: #646970; }
#hs-issue-contents-list.hs-ic-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
#hs-issue-contents-list .hs-ic-list { margin: 0; padding: 0; list-style: none; }
#hs-issue-contents-list .hs-ic-row { display: grid; grid-template-columns: 16px minmax(0, 1fr) auto; column-gap: 8px; row-gap: 3px; align-items: center; margin: 0; padding: 6px 12px; border-top: 1px solid #f6f7f7; }
#hs-issue-contents-list .hs-ic-row:first-child { border-top: 0; }
#hs-issue-contents-list .hs-ic-show { grid-row: 1; grid-column: 1; margin: 0; }
#hs-issue-contents-list .hs-ic-heading { grid-row: 1; grid-column: 2 / 4; font-size: 12px; font-weight: 600; line-height: 16px; color: #1d2327; overflow-wrap: anywhere; cursor: pointer; }
#hs-issue-contents-list .hs-ic-input { grid-row: 2; grid-column: 2; width: 100%; min-height: 26px; height: 26px; padding: 0 6px; font-size: 12px; line-height: 24px; }
#hs-issue-contents-list .hs-ic-len { grid-row: 2; grid-column: 3; font-size: 11px; color: #8c8f94; font-variant-numeric: tabular-nums; }
#hs-issue-contents-list .hs-ic-len.is-long { color: #b32d2e; font-weight: 600; }
#hs-issue-contents-list .hs-ic-input.is-long { border-color: #d63638; box-shadow: 0 0 0 1px #d63638; }
#hs-issue-contents-list .hs-ic-row.is-off .hs-ic-heading, #hs-issue-contents-list .hs-ic-row.is-off .hs-ic-input, #hs-issue-contents-list .hs-ic-row.is-off .hs-ic-len { opacity: .45; }
#hs-issue-contents-list .hs-ic-row.is-off .hs-ic-input.is-long { border-color: #8c8f94; box-shadow: none; }
#hs-issue-contents-list .hs-ic-row.is-off .hs-ic-len.is-long { color: #8c8f94; font-weight: 400; }
.hs-wu-contents .hs-ic-skipped { margin: 6px 12px 8px; }
#acf-group_6a3772490951b .hs-wu-issue:not(.hs-wu-contents) { padding: 12px !important; border: 1px solid #dcdcde; border-radius: 4px; }
#acf-group_6a3772490951b .hs-wu-issue:not(.hs-wu-contents) > .acf-label { display: block; }
#acf-group_6a3772490951b .hs-wu-issue .acf-checkbox-list { max-height: 170px; overflow-y: auto; font-size: 12px; }
#acf-group_6a3772490951b .hs-wu-issue .acf-checkbox-list li { margin: 0 0 2px; }
@container (max-width: 820px) {
    #acf-group_6a3772490951b > .inside.acf-fields { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); grid-template-areas: "blurb blurb" "campus week" "issue issue" "archive archive"; grid-template-rows: none; }
    #acf-group_6a3772490951b .hs-wu-blurb iframe { min-height: 160px !important; }
    #acf-group_6a3772490951b .hs-wu-contents > .acf-input, #acf-group_6a3772490951b .hs-wu-contents .hs-ic-card { position: static; }
    #hs-issue-contents-list.hs-ic-scroll { max-height: 300px; }
}
</style>
<script id="hs-issue-contents-js">
(function () {
    var PLACEHOLDER = 'Shown under this update on the Campus Hub page.';
    function list() { return document.getElementById('hs-issue-contents-list'); }
    // Count, colours and "N of M shown", worked out from the boxes so they follow every keystroke and tick.
    function paint() {
        var box = list();
        if (!box) { return; }
        var max = +box.dataset.max || 30, rows = box.querySelectorAll('.hs-ic-row'), on = 0;
        rows.forEach(function (row) {
            var input = row.querySelector('.hs-ic-input'), show = row.querySelector('.hs-ic-show'), len = row.querySelector('.hs-ic-len');
            var name = (input.value.trim() || row.querySelector('.hs-ic-heading').textContent).trim();
            var long = name.length > max;
            if (show.checked) { on++; }
            row.classList.toggle('is-off', !show.checked);
            input.classList.toggle('is-long', long);
            len.classList.toggle('is-long', long);
            len.textContent = name.length + '/' + max;
            len.title = long ? 'Long for a Campus Hub chip: try ' + max + ' characters or fewer.' : '';
        });
        var count = box.closest('.hs-ic-card') && box.closest('.hs-ic-card').querySelector('.hs-ic-count');
        if (count) { count.textContent = on + ' of ' + rows.length + ' shown'; }
    }
    document.addEventListener('input', function (e) { if (e.target.closest && e.target.closest('#hs-issue-contents-list')) { paint(); } });
    document.addEventListener('change', function (e) { if (e.target.closest && e.target.closest('#hs-issue-contents-list')) { paint(); } });
    document.addEventListener('DOMContentLoaded', paint);
    if (document.readyState !== 'loading') { paint(); }

    // The blurb's starter text: shown in the editor while it's empty (TinyMCE has no placeholder of its own).
    function placeholder(ed) {
        if (!ed || !ed.getBody) { return; }
        var doc = ed.getDoc();
        if (!doc.getElementById('hs-wu-ph')) {
            var st = doc.createElement('style');
            st.id = 'hs-wu-ph';
            st.textContent = 'body.hs-empty::before{content:attr(data-placeholder);position:absolute;color:#8c8f94;pointer-events:none;}';
            doc.head.appendChild(st);
        }
        var body = ed.getBody();
        body.setAttribute('data-placeholder', PLACEHOLDER);
        var sync = function () { body.classList.toggle('hs-empty', !ed.getContent({ format: 'text' }).trim()); };
        ['keyup', 'change', 'SetContent', 'input', 'focus', 'blur'].forEach(function (ev) { ed.on(ev, sync); });
        sync();
    }
    if (window.acf && acf.addAction) {
        acf.addAction('wysiwyg_tinymce_init', function (ed, id, init, field) {
            if (field && field.get && field.get('name') === 'blurb') { placeholder(ed); }
        });
    }

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
        var box = list();
        if (!box) { return; }
        var body = new URLSearchParams({ action: 'hs_issue_contents_get', nonce: box.dataset.nonce, post_id: box.dataset.post });
        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) { if (res && res.success) { box.innerHTML = res.data; paint(); } })
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
