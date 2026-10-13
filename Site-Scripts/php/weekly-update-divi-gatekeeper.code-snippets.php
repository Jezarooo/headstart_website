<?php

/**
 * Weekly-Update--Divi-Gatekeeper
 *
 * Freezes the Divi Builder button until a Campus is saved to the database.
 */
/**
 * ====================================================================
 * WEEKLY UPDATES GATEKEEPER
 * Enforces required fields before allowing Publish or Divi Builder access.
 * Fixed: Only runs on the editor page, not the list view.
 * Fixed: Doesn't interfere with the Gutenberg Title Block / Slug editor.
 * ====================================================================
 */

add_action('admin_head', 'hs_weekly_update_gatekeeper');

function hs_weekly_update_gatekeeper() {
    $screen = get_current_screen();
    // FIX 1: Ensure we are exactly on the post editor screen for weekly_update, not the edit.php list view
    if ( !$screen || $screen->post_type !== 'weekly_update' || $screen->base !== 'post' ) return;

    ?>
    <style>
        /* CLEAN UP REDUNDANT NATIVE UI */
        .components-panel__body:has([aria-label="Categories"]),
        .components-panel__body:has([aria-label="Tags"]),
        .components-panel__body:has([aria-label="Campuses"]),
        .components-panel__body:has([aria-label="In This Issue"]) {
            display: none !important;
        }

        /* GAMIFIED ZONES (Excluded Title to fix permalink/slug editing bug) */
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

        .acf-field[data-name="hs_week_number"],
        .acf-field[data-name="campus_bridge"],
        .acf-field[data-name="in_this_issue_tax"] { padding: 12px !important; }

        /* REDUCE WYSIWYG BLURB HEIGHT */
        .acf-field[data-name="blurb"] iframe {
            height: 120px !important;
            min-height: 120px !important;
        }
        .acf-field[data-name="blurb"] .wp-editor-container {
            height: auto !important;
        }
    </style>

    <script>
        // ==========================================
        // REMOVE NATIVE SIDEBAR PANELS VIA REACT
        // ==========================================
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
        hsWhenEditorReady(['taxonomy-panel-in_this_issue', 'taxonomy-panel-in-this-issue']);
        // ==========================================
        // VISUAL OBSERVER (Turns boxes Green/Red for the humans)
        // ==========================================
        function hs_live_field_monitor() {
            // Title Validation (Applied directly to the input to prevent breaking the slug editor)
            let titleWrapper = document.querySelector('.editor-post-title');
            if (titleWrapper) {
                let titleInput = titleWrapper.querySelector('textarea, input');
                if (titleInput) {
                    if (titleInput.value.trim() !== '' && titleInput.value.trim() !== 'Auto Draft') {
                        titleInput.classList.remove('hs-missing'); titleInput.classList.add('hs-valid');
                    } else {
                        titleInput.classList.remove('hs-valid'); titleInput.classList.add('hs-missing');
                    }
                }
            }
            
            // Week Number
            let weekField = document.querySelector('.acf-field[data-name="hs_week_number"]');
            if (weekField) {
                let weekInput = weekField.querySelector('input');
                if (weekInput && weekInput.value.trim() !== '') {
                    weekField.classList.remove('hs-missing'); weekField.classList.add('hs-valid');
                } else {
                    weekField.classList.remove('hs-valid'); weekField.classList.add('hs-missing');
                }
            }

            // Campus Bridge
            let campusField = document.querySelector('.acf-field[data-name="campus_bridge"]');
            if (campusField) {
                if (campusField.querySelector('input[type="radio"]:checked')) {
                    campusField.classList.remove('hs-missing'); campusField.classList.add('hs-valid');
                } else {
                    campusField.classList.remove('hs-valid'); campusField.classList.add('hs-missing');
                }
            }

            // Featured Image Highlight (Visual Only - No Interceptor Validation)
            let featuredMedia = wp.data ? wp.data.select('core/editor').getEditedPostAttribute('featured_media') : 0;
            let fiContainer = document.querySelector('.editor-post-featured-image');
            let featuredImagePanel = fiContainer ? fiContainer.closest('.components-panel__body') || fiContainer : null;
            if (featuredImagePanel) {
                if (featuredMedia > 0) {
                    featuredImagePanel.classList.remove('hs-missing'); featuredImagePanel.classList.add('hs-valid');
                } else {
                    featuredImagePanel.classList.remove('hs-valid'); featuredImagePanel.classList.add('hs-missing');
                }
            }

            // Slug Highlight (Sidebar Only - No Interceptor Validation)
            let currentSlug = wp.data ? wp.data.select('core/editor').getEditedPostAttribute('slug') : '';
            let isSlugSet = (currentSlug && currentSlug.trim() !== '' && !currentSlug.includes('auto-draft'));
            
            let sidebar = document.querySelector('.edit-post-sidebar');
            let slugEl = null;
            if (sidebar) {
                let allRows = sidebar.querySelectorAll('.components-panel__row, .components-button');
                for (let r of allRows) {
                    let text = (r.innerText || '').toLowerCase().trim();
                    if (text === 'url' || text === 'permalink' || text.includes('url ')) {
                        slugEl = r.closest('.components-panel__row') || r;
                        break;
                    }
                }
            }

            if (slugEl) {
                if (isSlugSet) {
                    slugEl.classList.remove('hs-missing'); slugEl.classList.add('hs-valid');
                } else {
                    slugEl.classList.remove('hs-valid'); slugEl.classList.add('hs-missing');
                }
            }
        }

        window.addEventListener('load', function() {
            setInterval(hs_live_field_monitor, 250);
        });

        // ==========================================
        // THE HARD INTERCEPTOR (0ms Latency)
        // ==========================================
        window.addEventListener('click', function(event) {
            let clickedWrapper = event.target.closest('button, a, [role="button"]');
            if (!clickedWrapper) return;

            let wrapperText = clickedWrapper.innerText ? clickedWrapper.innerText.toLowerCase() : '';
            
            let isDiviTrigger = (
                clickedWrapper.classList.contains('et_pb_gutenberg_button') ||
                clickedWrapper.classList.contains('et-gb-toggle-builder-button') ||
                clickedWrapper.id === 'et_pb_toggle_builder' ||
                wrapperText.includes('divi')
            );

            let isPublishTrigger = (
                clickedWrapper.classList.contains('editor-post-publish-button') ||
                clickedWrapper.classList.contains('editor-post-publish-panel__toggle') ||
                wrapperText === 'publish'
            );

            if ( isDiviTrigger || isPublishTrigger ) {
                let editor = wp.data ? wp.data.select('core/editor') : null;

                // 1. Title Truth
                let domTitle = document.querySelector('.editor-post-title input, .editor-post-title textarea')?.value.trim() || '';
                let reactTitle = editor?.getEditedPostAttribute('title')?.trim() || '';
                let isTitleOk = (domTitle !== '' && domTitle !== 'Auto Draft') || (reactTitle !== '' && reactTitle !== 'Auto Draft');

                // 2. Week Truth
                let isWeekOk = document.querySelector('.acf-field[data-name="hs_week_number"] input')?.value.trim() !== '';

                // 3. Campus Truth
                let isCampusOk = document.querySelector('.acf-field[data-name="campus_bridge"] input[type="radio"]:checked') !== null;

                // TRUE BYPASS: If the raw data exists, get out of the way instantly.
                if (isTitleOk && isWeekOk && isCampusOk) {
                    return true; 
                }

                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                
                let debugMsg = "🔒 ACTION DENIED: Please complete all required fields.\n\nMissing:\n";
                if (!isTitleOk) debugMsg += "❌ Post Title\n";
                if (!isWeekOk) debugMsg += "❌ Week Number\n";
                if (!isCampusOk) debugMsg += "❌ Campus Selection\n";
                
                alert(debugMsg);
                return false;
            }
        }, true);
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
