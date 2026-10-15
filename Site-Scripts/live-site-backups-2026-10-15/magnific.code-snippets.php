<?php

/**
 * Magnific
 */
/**
 * Load Magnific Popup on imported archive posts.
 *
 * Divi 5 enqueues the library only when its own detection finds a lightbox
 * module in the layout. Imported weeklies are raw HTML in the standard content
 * area, so it never arrives and the Weekly Update V2 popup init — which is
 * guarded by `if ($.fn.magnificPopup)` — skips silently. A click on a gallery
 * image then opens the raw JPEG.
 *
 * Add the extracted-posts type to is_singular() once it exists.
 */

add_action('wp_enqueue_scripts', function () {
    if (!is_singular(['weekly_update', 'post'])) {
        return;
    }

    if (class_exists('\ET\Builder\FrontEnd\Assets\DynamicAssetsUtils')) {
        \ET\Builder\FrontEnd\Assets\DynamicAssetsUtils::enqueue_magnific_popup_script();
    }

    wp_enqueue_style(
        'magnific-popup',
        get_template_directory_uri() . '/includes/builder/feature/dynamic-assets/assets/css/magnific_popup.css',
        [],
        ET_CORE_VERSION
    );
}, 20);
