<?php

/**
 * Posts--Divi-Gatekeeper
 */
/**
 * ====================================================================
 * POSTS GATEKEEPER
 * Enforces required information on standard Posts before they can be
 * PUBLISHED (from anywhere) or OPENED IN DIVI (from anywhere).
 * Drafts can always be saved; missing fields are highlighted instead.
 *
 * THE RULES (single source of truth: hs_post_gate_missing_fields())
 *   1. Post Title
 *   2. Website Section (hs_department)  OR  Yellow Badge Override (hs_thematic_chip)
 *   3. Campus (hs_post_campus)          OR  Display Location Override (hs_location_override)
 *   4. Featured Image (landscape - used as the hero). The Portrait Featured
 *      Image (hs_portrait_image) is optional, slider-only, and does NOT count.
 *      A portrait/square Featured Image shows an amber warning (not a block).
 *
 * THE LAYERS
 *   A. Browser alerts (post editor)  - instant, friendly warning. Detects
 *      Divi by button ID (#et-switch-to-divi), not by label, and also
 *      listens inside the block editor's canvas iframe. Saves the post
 *      (incl. ACF fields) before letting Divi open.
 *   B. Divi entry guard (server)     - any request that opens the Divi
 *      Builder (?et_fb=1 / ?et_fb_activation_nonce) for an incomplete
 *      post is redirected back to the post editor with a notice.
 *   C. Publish guard (server)        - newly publishing an incomplete post
 *      from Divi's own Publish button, Quick Edit or Bulk Edit is held
 *      as a Draft before it is saved, with a notice. Divi layout content
 *      is still saved. Already-live posts are never unpublished.
 *
 * The list-view alert lives in Posts--Admin-Columns (same rules).
 * ====================================================================
 */


/* ====================================================================
 * 1. SHARED RULES
 * ==================================================================== */

/**
 * Returns a list of human-readable labels for anything missing.
 * Empty array = the post passes.
 *
 * @param int   $post_id
 * @param array $overrides Optional in-flight values not yet saved:
 *                         'title' (string), 'has_thumbnail' (bool).
 * @return string[]
 */
function hs_post_gate_missing_fields( $post_id, $overrides = array() ) {
    $post = get_post( $post_id );
    if ( ! $post || 'post' !== $post->post_type ) {
        return array();
    }

    $missing = array();

    // 1. Title
    $title = array_key_exists( 'title', $overrides ) ? (string) $overrides['title'] : (string) $post->post_title;
    $title = trim( $title );
    if ( '' === $title || 'Auto Draft' === $title ) {
        $missing[] = 'Post Title';
    }

    // 2. Website Section OR Yellow Badge Override
    if ( ! hs_post_gate_has_terms( $post->ID, 'hs_department' ) && ! hs_post_gate_has_terms( $post->ID, 'hs_thematic_chip' ) ) {
        $missing[] = 'Website Section (or a Yellow Badge Override)';
    }

    // 3. Campus OR Display Location Override
    $location_override = trim( (string) get_post_meta( $post->ID, 'hs_location_override', true ) );
    if ( ! hs_post_gate_has_terms( $post->ID, 'hs_post_campus' ) && '' === $location_override ) {
        $missing[] = 'Campus Location (or a Display Location Override)';
    }

    // 4. Featured Image (landscape hero). The Portrait image is optional and does not count.
    $has_thumbnail = array_key_exists( 'has_thumbnail', $overrides )
        ? (bool) $overrides['has_thumbnail']
        : ( (int) get_post_thumbnail_id( $post->ID ) > 0 );
    if ( ! $has_thumbnail ) {
        $missing[] = 'Featured Image (landscape, used as the hero)';
    }

    return apply_filters( 'hs_post_gate_missing_fields', $missing, $post->ID );
}

function hs_post_gate_has_terms( $post_id, $taxonomy ) {
    $terms = get_the_terms( $post_id, $taxonomy );
    return ( ! empty( $terms ) && ! is_wp_error( $terms ) );
}

/**
 * Stores a one-time notice for the current user (shown on next admin page load).
 *
 * @param string[] $missing
 * @param string   $context 'divi' | 'publish'
 * @param int      $post_id
 */
function hs_post_gate_set_notice( $missing, $context, $post_id ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }
    set_transient(
        'hs_post_gate_notice_' . $user_id,
        array(
            'missing' => array_values( $missing ),
            'context' => $context,
            'post_id' => (int) $post_id,
        ),
        10 * MINUTE_IN_SECONDS
    );
}

/**
 * Builds the notice text. Returns '' if there is no notice (or it belongs
 * to another post when $only_post_id is given). Consumes the notice.
 */
function hs_post_gate_take_notice_message( $only_post_id = 0 ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return '';
    }
    $key    = 'hs_post_gate_notice_' . $user_id;
    $notice = get_transient( $key );
    if ( empty( $notice ) || ! is_array( $notice ) ) {
        return '';
    }
    if ( $only_post_id && (int) $notice['post_id'] !== (int) $only_post_id ) {
        return '';
    }
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
 * Runs before Divi's own activation handler (template_redirect @10).
 * ==================================================================== */

add_action( 'template_redirect', 'hs_post_gate_divi_entry_guard', 1 );

function hs_post_gate_divi_entry_guard() {
    if ( is_admin() ) {
        return;
    }
    if ( ! isset( $_GET['et_fb'] ) && ! isset( $_GET['et_fb_activation_nonce'] ) ) {
        return;
    }

    $post_id = (int) get_queried_object_id();
    if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $missing = hs_post_gate_missing_fields( $post_id );
    if ( empty( $missing ) ) {
        return;
    }

    $edit_url = get_edit_post_link( $post_id, 'raw' );
    if ( ! $edit_url ) {
        return;
    }

    hs_post_gate_set_notice( $missing, 'divi', $post_id );
    wp_safe_redirect( $edit_url );
    exit;
}


/* ====================================================================
 * 3. LAYER C: PUBLISH GUARD (server)
 * ==================================================================== */

/**
 * C1. Divi's own Publish button (Divi 5: POST /divi/v1/sync-to-server).
 * Before Divi saves, downgrade a new publish to 'draft' if incomplete.
 * The layout content in the same request is still saved.
 */
add_filter( 'rest_request_before_callbacks', 'hs_post_gate_divi_publish_guard', 10, 3 );

function hs_post_gate_divi_publish_guard( $response, $handler, $request ) {
    if ( is_wp_error( $response ) || ! ( $request instanceof WP_REST_Request ) ) {
        return $response;
    }
    if ( '/divi/v1/sync-to-server' !== $request->get_route() ) {
        return $response;
    }
    if ( 'preview' === $request->get_param( 'syncType' ) ) {
        return $response;
    }

    $requested_status = $request->get_param( 'post_status' );
    if ( ! in_array( $requested_status, array( 'publish', 'future' ), true ) ) {
        return $response;
    }

    $post = get_post( (int) $request->get_param( 'post_id' ) );
    if ( ! $post || 'post' !== $post->post_type ) {
        return $response;
    }
    // Never unpublish a post that is already live/scheduled.
    if ( in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
        return $response;
    }

    // A title Divi may be saving in this same request (Divi 5.13: pageSettingsByLayout.postContent).
    // Divi's sync route does not set the Featured Image, so only the title can change here.
    $overrides = array();
    $by_layout = $request->get_param( 'pageSettingsByLayout' );
    if ( isset( $by_layout['postContent']['postTitle'] ) ) {
        $overrides['title'] = (string) $by_layout['postContent']['postTitle'];
    }

    $missing = hs_post_gate_missing_fields( $post->ID, $overrides );
    if ( empty( $missing ) ) {
        return $response;
    }

    $request->set_param( 'post_status', 'draft' );
    hs_post_gate_set_notice( $missing, 'publish', $post->ID );

    return $response;
}

/**
 * C2. Quick Edit, Bulk Edit and other non-REST saves.
 * Keeps an incomplete post as a Draft BEFORE it is written, so it is never
 * live (no IndexNow ping, no APO purge, no publish hooks).
 * REST saves are skipped: Divi is handled by C1, and the block editor saves
 * ACF terms in a second request, so its publish can't be judged here. Layer A
 * blocks the block editor's Publish button.
 */
add_filter( 'wp_insert_post_data', 'hs_post_gate_hold_publish', 99, 2 );

function hs_post_gate_hold_publish( $data, $postarr ) {
    if ( 'post' !== $data['post_type'] || ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
        return $data;
    }
    if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_user_logged_in() ) {
        return $data;
    }
    $post_id = empty( $postarr['ID'] ) ? 0 : (int) $postarr['ID'];
    // Only NEW publishes. Updates to live posts and scheduled posts going live are left alone.
    if ( ! $post_id || in_array( get_post_status( $post_id ), array( 'publish', 'future' ), true ) ) {
        return $data;
    }

    // ponytail: reads saved terms, so ticking a Section in the same Quick Edit as Publish holds it once; read $postarr['tax_input'] if editors hit that
    $missing = hs_post_gate_missing_fields( $post_id, array( 'title' => wp_unslash( $data['post_title'] ) ) );
    if ( empty( $missing ) ) {
        return $data;
    }

    $data['post_status'] = 'draft';
    hs_post_gate_set_notice( $missing, 'publish', $post_id );
    return $data;
}


/* ====================================================================
 * 4. NOTICES (non block-editor admin screens, e.g. Posts list)
 * The block editor shows its notice via JS in section 5.
 * ==================================================================== */

add_action( 'admin_notices', 'hs_post_gate_admin_notice' );

function hs_post_gate_admin_notice() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
        return;
    }
    $message = hs_post_gate_take_notice_message();
    if ( '' === $message ) {
        return;
    }
    echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html( $message ) . '</strong></p></div>';
}


/* ====================================================================
 * 5. LAYER A: POST EDITOR UI (browser alerts + live highlights)
 * ==================================================================== */

add_action( 'admin_head', 'hs_posts_gatekeeper' );

function hs_posts_gatekeeper() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'post' ) return;
    if ( $screen->base !== 'post' ) return; // Only run on the actual post editor (not edit.php list view)

    global $post;
    $gate_notice = hs_post_gate_take_notice_message( $post ? $post->ID : 0 );

    ?>
    <style>
        /* PART 1: GAMIFIED ZONES */
        .hs-missing {
            border: 2px dashed #d63638 !important;
            background-color: #fcf0f1 !important;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .hs-valid {
            border: 2px solid #00a32a !important;
            background-color: #f0fdf4 !important;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        /* Amber = allowed, but worth a second look (e.g. portrait/square hero image) */
        .hs-warn {
            border: 2px solid #dba617 !important;
            background-color: #fcf9e8 !important;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        /* Specific style for Title to avoid breaking the slug popover */
        .hs-missing-title {
            border-left: 4px solid #d63638 !important;
        }
        .hs-valid-title {
            border-left: 4px solid #00a32a !important;
        }

        .acf-field[data-name="hs_department_tax"],
        .acf-field[data-name="hs_thematic_chip_tax"],
        .acf-field[data-name="hs_campus_tax"],
        .acf-field[data-name="hs_location_override"] { padding: 12px !important; }

        /* PART 1.5: HIDE NATIVE GUTENBERG TAXONOMY PANELS */
        /* Gutenberg ignores remove_meta_box(), so we force hide them here */
        .components-panel__body.taxonomy-panel-post_tag,
        .components-panel__body.taxonomy-panel-hs_department,
        .components-panel__body.taxonomy-panel-hs_post_campus {
            display: none !important;
        }

        /* PART 1.6: FEATURED IMAGE HINT */
        /* Gutenberg doesn't use the PHP thumbnail hook, so we inject the hint natively via CSS */
        .editor-post-featured-image::after {
            content: "Required: landscape image (16:9) - used as the hero. Add an optional Portrait version in Post Classification for the slider.";
            display: block;
            margin-top: 15px;
            padding: 10px;
            background: #f0fdf4;
            border-left: 4px solid #00a32a;
            color: #003057;
            font-weight: 600;
            border-radius: 3px;
        }
        /* Amber hint when the chosen Featured Image is portrait or square */
        .hs-image-warn .editor-post-featured-image::after,
        .editor-post-featured-image.hs-image-warn::after {
            content: "⚠️ This image is portrait or square. The hero works best with a landscape (16:9) image. You can still continue if this is intentional.";
            background: #fcf9e8;
            border-left-color: #dba617;
            color: #614a00;
        }
    </style>

    <script>
    (function () {
        var HS_GATE_NOTICE = <?php echo wp_json_encode( $gate_notice ); ?>;
        var HS_GATE = { bypassOnce: false, busy: false };

        // ==========================================
        // HELPERS
        // ==========================================

        // The block editor may render the post canvas inside an iframe.
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

        function hsHasCheckedTerm(fieldName) {
            return Array.from(document.querySelectorAll('.acf-field[data-name="' + fieldName + '"] input[type="checkbox"]:checked'))
                .some(function (cb) { return cb.value !== ''; }); // ACF can include an empty hidden checkbox
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

        // status: 'valid' | 'missing' | 'warn' (amber = allowed, but check it)
        function hsSetImageState(el, status) {
            if (!el) return;
            el.classList.remove('hs-valid', 'hs-missing', 'hs-warn', 'hs-image-warn');
            el.classList.add('hs-' + status);
            if (status === 'warn') el.classList.add('hs-image-warn');
        }

        // 'landscape' | 'portrait' (incl. square) | 'unknown' (not loaded yet / no dimensions, e.g. SVG)
        function hsFeaturedOrientation(featuredId) {
            if (!featuredId || !window.wp || !wp.data || !wp.data.select('core')) return 'unknown';
            var media = wp.data.select('core').getMedia(featuredId, { context: 'view' });
            var details = media && media.media_details;
            if (!details || !details.width || !details.height) return 'unknown';
            return (details.width > details.height) ? 'landscape' : 'portrait';
        }

        // ==========================================
        // THE RULES (mirror of hs_post_gate_missing_fields() in PHP)
        // ==========================================
        function hsGetState() {
            var editor = hsEditor();

            var reactTitle = editor ? (editor.getEditedPostAttribute('title') || '') : '';
            var titleEl = hsQueryAll('.editor-post-title input, .editor-post-title textarea, .editor-post-title__input, .editor-post-title');
            var domTitle = titleEl ? (titleEl.value !== undefined ? titleEl.value : titleEl.textContent) : '';
            var titleOk = [reactTitle, domTitle].some(function (t) {
                t = (t || '').trim();
                return t !== '' && t !== 'Auto Draft';
            });

            var deptOk     = hsHasCheckedTerm('hs_department_tax');
            var badgeOk    = hsFieldValue('hs_thematic_chip_tax', 'select') !== '';
            var campusOk   = hsHasCheckedTerm('hs_campus_tax');
            var locationOk = hsFieldValue('hs_location_override', 'input[type="text"]') !== '';

            // Featured Image = landscape hero (required). The Portrait image is optional and does not count.
            var featuredId = editor ? (editor.getEditedPostAttribute('featured_media') || 0) : 0;
            var featuredOk = featuredId > 0;

            return {
                title:     titleOk,
                section:   deptOk || badgeOk,
                campus:    campusOk || locationOk,
                image:     featuredOk,
                imageWarn: featuredOk && hsFeaturedOrientation(featuredId) === 'portrait' // amber only, never blocks
            };
        }

        function hsMissingLabels(state) {
            var missing = [];
            if (!state.title)   missing.push('❌ Post Title');
            if (!state.section) missing.push('❌ Website Section (or a Yellow Badge Override)');
            if (!state.campus)  missing.push('❌ Campus Location (or a Display Location Override)');
            if (!state.image)   missing.push('❌ Featured Image (landscape, used as the hero)');
            return missing;
        }

        // ==========================================
        // VISUAL OBSERVER (Turns boxes Green/Red/Amber for the humans)
        // ==========================================
        function hs_live_post_field_monitor() {
            if (!hsEditor()) return;
            hsSetupEditorCanvas();

            var state = hsGetState();

            // Title (left border on the wrapper so the slug popover still works)
            hsSetState(hsQueryAll('.editor-post-title'), state.title, 'hs-valid-title', 'hs-missing-title');

            // Website Section + its override share one state
            hsSetState(document.querySelector('.acf-field[data-name="hs_department_tax"]'), state.section, 'hs-valid', 'hs-missing');
            hsSetState(document.querySelector('.acf-field[data-name="hs_thematic_chip_tax"]'), state.section, 'hs-valid', 'hs-missing');

            // Campus + its override share one state
            hsSetState(document.querySelector('.acf-field[data-name="hs_campus_tax"]'), state.campus, 'hs-valid', 'hs-missing');
            hsSetState(document.querySelector('.acf-field[data-name="hs_location_override"]'), state.campus, 'hs-valid', 'hs-missing');

            // Featured Image: red = missing, amber = portrait/square (allowed), green = landscape
            var fi = document.querySelector('.editor-post-featured-image');
            var imageStatus = !state.image ? 'missing' : (state.imageWarn ? 'warn' : 'valid');
            hsSetImageState(fi ? (fi.closest('.components-panel__body') || fi) : null, imageStatus);
        }

        // If the post canvas is an iframe, it needs its own styles + click listener.
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

            // Notice from the server (e.g. bounced from Divi, or publish downgraded to Draft)
            if (HS_GATE_NOTICE && window.wp && wp.data && wp.data.dispatch('core/notices')) {
                wp.data.dispatch('core/notices').createErrorNotice(HS_GATE_NOTICE, { id: 'hs-post-gate', isDismissible: true });
            }

            // Hide native editor panels without ever breaking the editor (2026-10-13).
            // WP 6.5+ keeps removeEditorPanel on core/editor; older WP on core/edit-post.
            // Never call a store that isn't registered yet, and never let an error escape
            // into the editor's own start-up (that blanked the block editor on WP 7.1.3).
            function hsRemoveEditorPanels(panels) {
                try {
                    if (!window.wp || !wp.data) return false;
                    var stores = ['core/editor', 'core/edit-post'];
                    for (var i = 0; i < stores.length; i++) {
                        var d = wp.data.dispatch(stores[i]);
                        if (d && typeof d.removeEditorPanel === 'function') {
                            panels.forEach(function (p) { d.removeEditorPanel(p); });
                            return true;
                        }
                    }
                } catch (e) {
                    if (window.console) console.warn('HeadStart: could not hide editor panels', e);
                }
                return false;
            }
            // Run once the post has loaded, outside the editor's update cycle; retry once if no store was ready.
            function hsWhenEditorReady(panels) {
                if (!window.wp || !wp.data || !wp.data.subscribe) return;
                var done = false, unsub = null;
                unsub = wp.data.subscribe(function () {
                    if (done) return;
                    var ed = wp.data.select('core/editor');
                    if (!ed || typeof ed.getCurrentPostId !== 'function' || !ed.getCurrentPostId()) return;
                    done = true;
                    setTimeout(function () {
                        if (typeof unsub === 'function') unsub();
                        if (!hsRemoveEditorPanels(panels)) {
                            setTimeout(function () { hsRemoveEditorPanels(panels); }, 1000);
                        }
                    }, 0);
                });
            }
            hsWhenEditorReady(['taxonomy-panel-category', 'taxonomy-panel-post_tag', 'taxonomy-panel-hs_department', 'taxonomy-panel-hs_post_campus']);
        });

        // ==========================================
        // THE HARD INTERCEPTOR
        // ==========================================
        function hsClassifyTrigger(el) {
            var text = (el.innerText || el.textContent || '').trim().toLowerCase();
            var href = (el.getAttribute && el.getAttribute('href')) || '';

            // Divi: match by stable ID/class/URL first; label text only as a fallback.
            // (#et-switch-to-divi reads "Use Divi Builder" on new posts and
            //  "Edit With The Divi Builder" once a draft has been saved.)
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

            // Our own re-click after saving: let it through.
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
                // Save first (incl. ACF meta boxes) so the server-side Divi guard sees the ticked boxes.
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                hsSaveThenContinue(el);
                return false;
            }
            // Publish with everything complete: let it through.
        }

        function hsSaveThenContinue(el) {
            if (HS_GATE.busy) return;
            var dispatch = (window.wp && wp.data) ? wp.data.dispatch('core/editor') : null;

            var proceed = function () {
                HS_GATE.busy = false;
                // React may have re-rendered the button while saving.
                if (!el.isConnected && el.id) {
                    el = hsQueryAll('#' + el.id) || el;
                }
                HS_GATE.bypassOnce = true;
                el.click();
                // Safety: never leave the bypass armed if the click didn't reach us.
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

        // Waits until both the post and the ACF meta boxes have finished saving.
        function hsWaitForSaveToFinish(callback) {
            var start = Date.now();
            var quietTicks = 0;
            (function tick() {
                var ed = wp.data.select('core/editor');
                var ep = wp.data.select('core/edit-post');
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
