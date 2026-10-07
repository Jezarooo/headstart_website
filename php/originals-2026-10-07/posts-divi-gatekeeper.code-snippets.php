<?php

/**
 * Posts--Divi-Gatekeeper
 */
/**
 * ====================================================================
 * POSTS GATEKEEPER (THE "DOM-BYPASS" SENTINEL)
 * Enforces required fields (Department & Campus) on standard Posts
 * before allowing Publish or Divi Builder access.
 * ====================================================================
 */

add_action('admin_head', 'hs_posts_gatekeeper');

function hs_posts_gatekeeper() {
    $screen = get_current_screen();
    if ( !$screen || $screen->post_type !== 'post' ) return;
    if ( $screen->base !== 'post' ) return; // Only run on the actual post editor (not edit.php list view)

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

        /* Specific style for Title to avoid breaking the slug popover */
        .hs-missing-title {
            border-left: 4px solid #d63638 !important;
        }
        .hs-valid-title {
            border-left: 4px solid #00a32a !important;
        }

        .acf-field[data-name="hs_department_tax"] { padding: 12px !important; }
        .acf-field[data-name="hs_campus_tax"] { padding: 12px !important; }

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
            content: "Recommendation: 16x9 portrait preferred.";
            display: block;
            margin-top: 15px;
            padding: 10px;
            background: #f0fdf4;
            border-left: 4px solid #00a32a;
            color: #003057;
            font-weight: 600;
            border-radius: 3px;
        }
    </style>

    <script>
        // ==========================================
        // VISUAL OBSERVER (Turns boxes Green/Red for the humans)
        // ==========================================
        function hs_live_post_field_monitor() {
            let editor = wp.data ? wp.data.select('core/editor') : null;
            if (!editor) return;

            // Title Validation (Applied as a simple left border to the wrapper to prevent breaking the slug popover)
            let titleVal = editor.getEditedPostAttribute('title');
            let titleWrapper = document.querySelector('.editor-post-title');
            if (titleWrapper) {
                if (titleVal && titleVal.trim() !== '' && titleVal !== 'Auto Draft') {
                    titleWrapper.classList.remove('hs-missing-title'); titleWrapper.classList.add('hs-valid-title');
                } else {
                    titleWrapper.classList.remove('hs-valid-title'); titleWrapper.classList.add('hs-missing-title');
                }
            }

            // Department
            let deptField = document.querySelector('.acf-field[data-name="hs_department_tax"]');
            if (deptField) {
                // ACF taxonomy fields sometimes have a hidden checked checkbox with no value. We must check for actual values.
                let hasDept = Array.from(deptField.querySelectorAll('input[type="checkbox"]:checked')).some(cb => cb.value !== '');
                if (hasDept) {
                    deptField.classList.remove('hs-missing'); deptField.classList.add('hs-valid');
                } else {
                    deptField.classList.remove('hs-valid'); deptField.classList.add('hs-missing');
                }
            }

            let hasLocationOverride = false;
            let locationFieldInput = document.querySelector('.acf-field[data-name="hs_location_override"] input');
            if (locationFieldInput && locationFieldInput.value.trim() !== '') {
                hasLocationOverride = true;
            }

            // Campus
            let campusField = document.querySelector('.acf-field[data-name="hs_campus_tax"]');
            if (campusField) {
                let hasCampus = Array.from(campusField.querySelectorAll('input[type="checkbox"]:checked')).some(cb => cb.value !== '');
                if (hasCampus) {
                    campusField.classList.remove('hs-missing'); campusField.classList.add('hs-valid');
                } else {
                    campusField.classList.remove('hs-valid'); campusField.classList.add('hs-missing');
                }
            }
        }

        window.addEventListener('load', function() {
            setInterval(hs_live_post_field_monitor, 250);
            
            // Clean up Gutenberg sidebar panels safely natively via API
            let hs_panels_removed = false;
            if (wp && wp.data) {
                wp.data.subscribe(function() {
                    if (!hs_panels_removed && wp.data.select('core/edit-post')) {
                        // Check if one of them exists before attempting removal
                        if (wp.data.select('core/edit-post').isEditorPanelEnabled('taxonomy-panel-category')) {
                            wp.data.dispatch('core/edit-post').removeEditorPanel('taxonomy-panel-category');
                            wp.data.dispatch('core/edit-post').removeEditorPanel('taxonomy-panel-post_tag');
                            wp.data.dispatch('core/edit-post').removeEditorPanel('taxonomy-panel-hs_department');
                            wp.data.dispatch('core/edit-post').removeEditorPanel('taxonomy-panel-hs_post_campus');
                            hs_panels_removed = true;
                        }
                    }
                });
            }
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
                wrapperText === 'use the divi builder' ||
                wrapperText === 'use divi builder' ||
                wrapperText === 'edit with divi' ||
                wrapperText === 'build on the front end' ||
                wrapperText === 'return to standard editor'
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

                // 2. Department Truth
                let isDeptOk = Array.from(document.querySelectorAll('.acf-field[data-name="hs_department_tax"] input[type="checkbox"]:checked')).some(cb => cb.value !== '');

                // 3. Campus Truth
                let isCampusOk = Array.from(document.querySelectorAll('.acf-field[data-name="hs_campus_tax"] input[type="checkbox"]:checked')).some(cb => cb.value !== '');
                // hasOverride is no longer an excuse to skip campus selection
                
                // TRUE BYPASS: If the raw data exists, get out of the way instantly.
                if (isTitleOk && isDeptOk && isCampusOk) {
                    return true; 
                }

                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                
                let debugMsg = "🔒 ACTION DENIED: Please complete all required fields.\n\nMissing:\n";
                if (!isTitleOk) debugMsg += "❌ Post Title\n";
                if (!isDeptOk) debugMsg += "❌ Website Section (Department)\n";
                if (!isCampusOk) debugMsg += "❌ Campus Location (Required for routing even if using a Location Override)\n";
                
                alert(debugMsg);
                return false;
            }
        }, true);
    </script>
    <?php
}
