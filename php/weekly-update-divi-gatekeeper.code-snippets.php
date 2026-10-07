<?php

/**
 * Weekly-Update--Divi-Gatekeeper
 */
/**
 * ====================================================================
 * WEEKLY UPDATES GATEKEEPER
 * Enforces required information on Weekly Updates before they can be
 * PUBLISHED (from anywhere) or OPENED IN DIVI (from anywhere).
 * Drafts can always be saved; missing fields are highlighted instead.
 *
 * THE RULES (single source of truth: hs_weekly_gate_missing_fields())
 *   1. Post Title
 *   2. Week Number (hs_week_number)
 *   3. Campus Selection (hs_campus taxonomy, via campus_bridge)
 *
 * THE LAYERS
 *   A. Browser alerts (post editor)  - instant, friendly warning. Detects
 *      Divi by button ID (#et-switch-to-divi), not by label. Saves the post
 *      (incl. ACF fields) before letting Divi open.
 *   B. Divi entry guard (server)     - any request that opens the Divi
 *      Builder (?et_fb=1 / ?et_fb_activation_nonce) for an incomplete
 *      post is redirected back to the post editor with a notice.
 *   C. Publish guard (server)        - newly publishing an incomplete post
 *      from Divi's own Publish button, the block editor, Quick Edit or
 *      Bulk Edit leaves it as a Draft with a notice.
 * ====================================================================
 */

/* ====================================================================
 * 1. SHARED RULES
 * ==================================================================== */

function hs_weekly_gate_missing_fields( $post_id, $overrides = array() ) {
    $post = get_post( $post_id );
    if ( ! $post || 'weekly_update' !== $post->post_type ) {
        return array();
    }

    $missing = array();

    // 1. Title
    $title = array_key_exists( 'title', $overrides ) ? (string) $overrides['title'] : (string) $post->post_title;
    $title = trim( $title );
    if ( '' === $title || 'Auto Draft' === $title ) {
        $missing[] = 'Post Title';
    }

    // 2. Week Number
    $week = get_post_meta( $post->ID, 'hs_week_number', true );
    if ( '' === trim( (string) $week ) ) {
        $missing[] = 'Week Number';
    }

    // 3. Campus
    $has_campus = hs_weekly_gate_has_terms( $post->ID, 'hs_campus' );
    // Fallback if ACF hasn't synced it to the taxonomy yet
    if ( ! $has_campus && ! empty( get_post_meta( $post->ID, 'campus_bridge', true ) ) ) {
        $has_campus = true;
    }
    if ( ! $has_campus ) {
        $missing[] = 'Campus Selection';
    }

    return apply_filters( 'hs_weekly_gate_missing_fields', $missing, $post->ID );
}

function hs_weekly_gate_has_terms( $post_id, $taxonomy ) {
    $terms = get_the_terms( $post_id, $taxonomy );
    return ( ! empty( $terms ) && ! is_wp_error( $terms ) );
}

function hs_weekly_gate_set_notice( $missing, $context, $post_id ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) return;
    
    set_transient(
        'hs_weekly_gate_notice_' . $user_id,
        array(
            'missing' => array_values( $missing ),
            'context' => $context,
            'post_id' => (int) $post_id,
        ),
        10 * MINUTE_IN_SECONDS
    );
}

function hs_weekly_gate_take_notice_message( $only_post_id = 0 ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) return '';
    
    $key    = 'hs_weekly_gate_notice_' . $user_id;
    $notice = get_transient( $key );
    if ( empty( $notice ) || ! is_array( $notice ) ) return '';
    if ( $only_post_id && (int) $notice['post_id'] !== (int) $only_post_id ) return '';
    
    delete_transient( $key );

    $title = get_the_title( $notice['post_id'] );
    $title = ( '' !== trim( (string) $title ) ) ? $title : '(no title)';

    if ( 'divi' === $notice['context'] ) {
        $intro = sprintf( 'The Divi Builder was not opened for “%s”. Please complete these required fields first:', $title );
    } else {
        $intro = sprintf( '“%s” was saved as a Draft instead of being published. Please complete these required fields first:', $title );
    }

    return '🔒 ' . $intro . ' ' . implode( ' • ', $notice['missing'] );
}


/* ====================================================================
 * 2. LAYER B: DIVI ENTRY GUARD (server)
 * ==================================================================== */

add_action( 'template_redirect', 'hs_weekly_gate_divi_entry_guard', 1 );

function hs_weekly_gate_divi_entry_guard() {
    if ( is_admin() ) return;
    if ( ! isset( $_GET['et_fb'] ) && ! isset( $_GET['et_fb_activation_nonce'] ) ) return;

    $post_id = (int) get_queried_object_id();
    if ( ! $post_id || 'weekly_update' !== get_post_type( $post_id ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $missing = hs_weekly_gate_missing_fields( $post_id );
    if ( empty( $missing ) ) return;

    $edit_url = get_edit_post_link( $post_id, 'raw' );
    if ( ! $edit_url ) return;

    hs_weekly_gate_set_notice( $missing, 'divi', $post_id );
    wp_safe_redirect( $edit_url );
    exit;
}


/* ====================================================================
 * 3. LAYER C: PUBLISH GUARD (server)
 * ==================================================================== */

add_filter( 'rest_request_before_callbacks', 'hs_weekly_gate_divi_publish_guard', 10, 3 );
function hs_weekly_gate_divi_publish_guard( $response, $handler, $request ) {
    if ( is_wp_error( $response ) || ! ( $request instanceof WP_REST_Request ) ) return $response;
    if ( '/divi/v1/sync-to-server' !== $request->get_route() ) return $response;
    if ( 'preview' === $request->get_param( 'syncType' ) ) return $response;

    $requested_status = $request->get_param( 'post_status' );
    if ( ! in_array( $requested_status, array( 'publish', 'future' ), true ) ) return $response;

    $post = get_post( (int) $request->get_param( 'post_id' ) );
    if ( ! $post || 'weekly_update' !== $post->post_type ) return $response;
    if ( in_array( $post->post_status, array( 'publish', 'future' ), true ) ) return $response;

    $overrides = array();
    $page_settings = $request->get_param( 'pageSettings' );
    if ( is_array( $page_settings ) && isset( $page_settings['postTitle'] ) ) {
        $overrides['title'] = (string) $page_settings['postTitle'];
    }

    $missing = hs_weekly_gate_missing_fields( $post->ID, $overrides );
    if ( empty( $missing ) ) return $response;

    $request->set_param( 'post_status', 'draft' );
    hs_weekly_gate_set_notice( $missing, 'publish', $post->ID );

    return $response;
}

add_action( 'transition_post_status', 'hs_weekly_gate_track_new_publish', 10, 3 );
function hs_weekly_gate_track_new_publish( $new_status, $old_status, $post ) {
    if ( ! empty( $GLOBALS['hs_weekly_gate_reverting'] ) ) return;
    if ( ! $post || 'weekly_update' !== $post->post_type ) return;
    if ( ! in_array( $new_status, array( 'publish', 'future' ), true ) ) return;
    if ( in_array( $old_status, array( 'publish', 'future' ), true ) ) return;
    if ( ! is_user_logged_in() ) return;

    $GLOBALS['hs_weekly_gate_new_publish'][ $post->ID ] = true;

    if ( hs_weekly_gate_is_block_editor_rest() ) {
        set_transient( 'hs_weekly_gate_pending_' . $post->ID, get_current_user_id(), 2 * MINUTE_IN_SECONDS );
    }
}

add_action( 'wp_after_insert_post', 'hs_weekly_gate_after_insert', 99, 4 );
function hs_weekly_gate_after_insert( $post_id, $post, $update, $post_before ) {
    if ( ! empty( $GLOBALS['hs_weekly_gate_reverting'] ) ) return;
    if ( ! $post || 'weekly_update' !== $post->post_type ) return;

    if ( hs_weekly_gate_is_block_editor_rest() ) return;
    if ( hs_weekly_gate_current_rest_route() === '/divi/v1/sync-to-server' ) {
        unset( $GLOBALS['hs_weekly_gate_new_publish'][ $post_id ] );
        return;
    }

    $is_new_publish = ! empty( $GLOBALS['hs_weekly_gate_new_publish'][ $post_id ] );
    $pending_key    = 'hs_weekly_gate_pending_' . $post_id;
    $is_pending     = ( false !== get_transient( $pending_key ) );

    if ( ! $is_new_publish && ! $is_pending ) return;

    unset( $GLOBALS['hs_weekly_gate_new_publish'][ $post_id ] );
    if ( $is_pending ) delete_transient( $pending_key );

    hs_weekly_gate_enforce( $post_id );
}

function hs_weekly_gate_enforce( $post_id ) {
    if ( ! in_array( get_post_status( $post_id ), array( 'publish', 'future' ), true ) ) return;
    
    $missing = hs_weekly_gate_missing_fields( $post_id );
    if ( empty( $missing ) ) return;

    $GLOBALS['hs_weekly_gate_reverting'] = true;
    wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
    $GLOBALS['hs_weekly_gate_reverting'] = false;

    hs_weekly_gate_set_notice( $missing, 'publish', $post_id );
}

function hs_weekly_gate_current_rest_route() {
    if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) return '';
    global $wp;
    $route = ( isset( $wp->query_vars['rest_route'] ) ) ? $wp->query_vars['rest_route'] : '';
    return '/' . ltrim( (string) $route, '/' );
}

function hs_weekly_gate_is_block_editor_rest() {
    return 0 === strpos( hs_weekly_gate_current_rest_route(), '/wp/v2/' );
}


/* ====================================================================
 * 4. NOTICES
 * ==================================================================== */

add_action( 'admin_notices', 'hs_weekly_gate_admin_notice' );
function hs_weekly_gate_admin_notice() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) return;
    
    $message = hs_weekly_gate_take_notice_message();
    if ( '' === $message ) return;
    
    echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html( $message ) . '</strong></p></div>';
}


/* ====================================================================
 * 5. LAYER A: POST EDITOR UI
 * ==================================================================== */

add_action( 'admin_head', 'hs_weekly_gatekeeper_ui' );

function hs_weekly_gatekeeper_ui() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'weekly_update' || $screen->base !== 'post' ) return;

    global $post;
    $gate_notice = hs_weekly_gate_take_notice_message( $post ? $post->ID : 0 );

    ?>
    <style>
        /* PART 1: GAMIFIED ZONES */
        .hs-missing { border: 2px dashed #d63638 !important; background-color: #fcf0f1 !important; border-radius: 4px; transition: all 0.2s ease; }
        .hs-valid { border: 2px solid #00a32a !important; background-color: #f0fdf4 !important; border-radius: 4px; transition: all 0.2s ease; }
        .hs-missing-title { border-left: 4px solid #d63638 !important; }
        .hs-valid-title { border-left: 4px solid #00a32a !important; }

        .acf-field[data-name="hs_week_number"],
        .acf-field[data-name="campus_bridge"],
        .acf-field[data-name="in_this_issue_tax"] { padding: 12px !important; }

        /* REDUCE WYSIWYG BLURB HEIGHT */
        .acf-field[data-name="blurb"] iframe { height: 120px !important; min-height: 120px !important; }
        .acf-field[data-name="blurb"] .wp-editor-container { height: auto !important; }

        /* PART 1.5: HIDE NATIVE GUTENBERG TAXONOMY PANELS */
        .components-panel__body:has([aria-label="Categories"]),
        .components-panel__body:has([aria-label="Tags"]),
        .components-panel__body:has([aria-label="Campuses"]),
        .components-panel__body:has([aria-label="In This Issue"]) {
            display: none !important;
        }
    </style>

    <script>
    (function () {
        var HS_GATE_NOTICE = <?php echo wp_json_encode( $gate_notice ); ?>;
        var HS_GATE = { bypassOnce: false, busy: false };

        function hsEditorCanvasDoc() {
            var ifr = document.querySelector('iframe[name="editor-canvas"]');
            try { return (ifr && ifr.contentDocument) ? ifr.contentDocument : null; } catch (e) { return null; }
        }

        function hsQueryAll(selector) {
            var el = document.querySelector(selector);
            if (el) return el;
            var canvas = hsEditorCanvasDoc();
            return canvas ? canvas.querySelector(selector) : null;
        }

        function hsEditor() {
            return (window.wp && wp.data && wp.data.select('core/editor')) || null;
        }

        function hsFieldValue(fieldName, selector) {
            var el = document.querySelector('.acf-field[data-name="' + fieldName + '"] ' + selector);
            return (el && el.value) ? String(el.value).trim() : '';
        }

        function hsSetState(el, ok, validClass, missingClass) {
            if (!el) return;
            el.classList.remove(ok ? missingClass : validClass);
            el.classList.add(ok ? validClass : missingClass);
        }

        function hsGetState() {
            var editor = hsEditor();

            var reactTitle = editor ? (editor.getEditedPostAttribute('title') || '') : '';
            var titleEl = hsQueryAll('.editor-post-title input, .editor-post-title textarea, .editor-post-title__input, .editor-post-title');
            var domTitle = titleEl ? (titleEl.value !== undefined ? titleEl.value : titleEl.textContent) : '';
            var titleOk = [reactTitle, domTitle].some(function (t) {
                t = (t || '').trim();
                return t !== '' && t !== 'Auto Draft';
            });

            var weekOk   = hsFieldValue('hs_week_number', 'input') !== '';
            var campusOk = document.querySelector('.acf-field[data-name="campus_bridge"] input[type="radio"]:checked') !== null;

            return {
                title:  titleOk,
                week:   weekOk,
                campus: campusOk
            };
        }

        function hsMissingLabels(state) {
            var missing = [];
            if (!state.title)  missing.push('❌ Post Title');
            if (!state.week)   missing.push('❌ Week Number');
            if (!state.campus) missing.push('❌ Campus Selection');
            return missing;
        }

        function hs_live_post_field_monitor() {
            if (!hsEditor()) return;
            hsSetupEditorCanvas();

            var state = hsGetState();

            hsSetState(hsQueryAll('.editor-post-title'), state.title, 'hs-valid-title', 'hs-missing-title');
            hsSetState(document.querySelector('.acf-field[data-name="hs_week_number"]'), state.week, 'hs-valid', 'hs-missing');
            hsSetState(document.querySelector('.acf-field[data-name="campus_bridge"]'), state.campus, 'hs-valid', 'hs-missing');
        }

        function hsSetupEditorCanvas() {
            var canvas = hsEditorCanvasDoc();
            if (!canvas || canvas.__hsGateReady || !canvas.head) return;
            var style = canvas.createElement('style');
            style.textContent = '.hs-missing-title{border-left:4px solid #d63638 !important;}.hs-valid-title{border-left:4px solid #00a32a !important;}';
            canvas.head.appendChild(style);
            canvas.addEventListener('click', hsInterceptor, true);
            canvas.__hsGateReady = true;
        }

        window.addEventListener('load', function () {
            setInterval(hs_live_post_field_monitor, 250);

            if (HS_GATE_NOTICE && window.wp && wp.data && wp.data.dispatch('core/notices')) {
                wp.data.dispatch('core/notices').createErrorNotice(HS_GATE_NOTICE, { id: 'hs-weekly-gate', isDismissible: true });
            }

            if (window.wp && wp.data) {
                var hs_panel_removed = false;
                wp.data.subscribe(function() {
                    if (hs_panel_removed) return;
                    var ep = wp.data.select('core/edit-post');
                    if (ep && ep.isEditorPanelEnabled && ep.isEditorPanelEnabled('taxonomy-panel-in_this_issue')) {
                        wp.data.dispatch('core/edit-post').removeEditorPanel('taxonomy-panel-in_this_issue');
                        wp.data.dispatch('core/edit-post').removeEditorPanel('taxonomy-panel-in-this-issue');
                        hs_panel_removed = true;
                    }
                });
            }
        });

        function hsClassifyTrigger(el) {
            var text = (el.innerText || el.textContent || '').trim().toLowerCase();
            var href = (el.getAttribute && el.getAttribute('href')) || '';

            var isDivi = (
                el.id === 'et-switch-to-divi' ||
                el.classList.contains('editor-post-switch-to-divi') ||
                el.id === 'et_pb_toggle_builder' ||
                href.indexOf('et_fb=1') > -1 ||
                href.indexOf('et_fb_activation_nonce') > -1 ||
                text.indexOf('divi builder') > -1 ||
                text.indexOf('edit with divi') > -1 ||
                text.indexOf('visual builder') > -1 ||
                text === 'build on the front end'
            );
            if (isDivi) return 'divi';

            var isPublish = (
                el.classList.contains('editor-post-publish-button') ||
                el.classList.contains('editor-post-publish-button__button') ||
                el.classList.contains('editor-post-publish-panel__toggle') ||
                text === 'publish'
            );
            if (isPublish) return 'publish';

            return null;
        }

        function hsInterceptor(event) {
            var target = event.target;
            if (!target || !target.closest) return;
            var el = target.closest('button, a, [role="button"]');
            if (!el) return;

            var kind = hsClassifyTrigger(el);
            if (!kind) return;

            if (HS_GATE.bypassOnce) {
                HS_GATE.bypassOnce = false;
                return;
            }

            var missing = hsMissingLabels(hsGetState());
            if (missing.length) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                alert('🔒 ACTION DENIED: Please complete all required fields.\n\nMissing:\n' + missing.join('\n'));
                return false;
            }

            if (kind === 'divi') {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                hsSaveThenContinue(el);
                return false;
            }
        }

        function hsSaveThenContinue(el) {
            if (HS_GATE.busy) return;
            var dispatch = (window.wp && wp.data) ? wp.data.dispatch('core/editor') : null;

            var proceed = function () {
                HS_GATE.busy = false;
                if (!el.isConnected && el.id) {
                    el = hsQueryAll('#' + el.id) || el;
                }
                HS_GATE.bypassOnce = true;
                el.click();
                setTimeout(function () { HS_GATE.bypassOnce = false; }, 1000);
            };

            if (!dispatch || !dispatch.savePost) {
                proceed();
                return;
            }

            HS_GATE.busy = true;
            Promise.resolve(dispatch.savePost()).then(function () {
                hsWaitForSaveToFinish(proceed);
            }, function () {
                HS_GATE.busy = false;
                alert('⚠️ The post could not be saved. Please click "Save draft" and try again.');
            });
        }

        function hsWaitForSaveToFinish(callback) {
            var start = Date.now();
            var quietTicks = 0;
            (function tick() {
                var ed = window.wp && wp.data ? wp.data.select('core/editor') : null;
                var ep = window.wp && wp.data ? wp.data.select('core/edit-post') : null;
                var saving = (ed && ed.isSavingPost && ed.isSavingPost()) ||
                             (ep && ep.isSavingMetaBoxes && ep.isSavingMetaBoxes());
                quietTicks = saving ? 0 : quietTicks + 1;
                var elapsed = Date.now() - start;
                if ((quietTicks >= 3 && elapsed > 400) || elapsed > 10000) {
                    callback();
                    return;
                }
                setTimeout(tick, 100);
            })();
        }

        window.addEventListener('click', hsInterceptor, true);
    })();
    </script>
    <?php
}

/**
 * ACF SYNC SAFETY NET
 */
add_action('save_post_weekly_update', 'hs_force_acf_taxonomy_sync', 20, 2);
function hs_force_acf_taxonomy_sync($post_id, $post) {
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( $post->post_status === 'auto-draft' || $post->post_status === 'trash' ) return;

    if ( isset($_POST['acf']) && is_array($_POST['acf']) ) {
        foreach ($_POST['acf'] as $field_key => $value) {
            $field_obj = get_field_object($field_key, $post_id, false, false);
            if ( $field_obj && $field_obj['type'] === 'taxonomy' && $field_obj['taxonomy'] === 'hs_campus' && !empty($value) ) {
                $term_slug = is_numeric($value) ? get_term($value)->slug : $value;
                wp_set_object_terms( $post_id, $term_slug, 'hs_campus', false );
                break;
            }
        }
    }
}
