<?php

/**
 * [Nick/off] Rails--Import-Support
 *
 * OWNED BY Nick (firedev). Disabled 2026-09-14 on origin-new rebuild — CSV/Rails import only, not needed for front-end. Re-enable only if Nick/Jeremy need imports.
 * 
 *
 */
/**
 * Weekly Update — Rails import support
 *
 * Everything bin/wp-import-weekly-page needs on the target. Run scope: global.
 * Each piece fails quietly rather than loudly if it is missing:
 *
 *   custom-fields support   core REST exposes registered meta only when the
 *                           post type supports custom fields. Without it every
 *                           meta value below is dropped on write.
 *   source_page_id          the idempotency key. Without it nothing is stored
 *                           and every re-run creates another copy.
 *   hs_legacy_archive       the Archive checkbox the front end branches on.
 *   campus_bridge           the ACF campus field. The ACF group is not exposed
 *                           through REST, so the importer writes the backing
 *                           meta directly.
 *   source_image_url        marks each uploaded attachment with the Rails URL
 *                           it came from, so re-runs reuse it.
 *   rest_*_query filters    let the importer look a post or an attachment up by
 *                           meta. Without the attachment one the media lookup
 *                           ignores its filter and returns the same first
 *                           attachment every time — every weekly then shares
 *                           one featured image.
 */

add_action('init', function () {
    $auth = function () { return current_user_can('edit_posts'); };

    add_post_type_support('weekly_update', 'custom-fields');

    register_post_meta('weekly_update', 'source_page_id', [
        'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth,
    ]);

    foreach (['hs_week_number', 'hs_legacy_archive', 'campus_bridge'] as $key) {
        register_post_meta('weekly_update', $key, [
            'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth,
        ]);
    }

    // Extracted articles land in the standard `post` type (Jeremy's "Posts -
    // Register ACF Fields" snippet binds his classification group to it), keyed
    // by which weekly and which article within it.
    foreach (['source_page_id', 'source_article_seq'] as $key) {
        register_post_meta('post', $key, [
            'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth,
        ]);
    }

    register_post_meta('attachment', 'source_image_url', [
        'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth,
    ]);
}, 11);

$hs_weekly_meta_filter = function ($args, $request) {
    $meta_key   = $request->get_param('meta_key');
    $meta_value = $request->get_param('meta_value');
    if ($meta_key !== null && $meta_value !== null) {
        $args['meta_query'] = [
            [
                'key'   => $meta_key,
                'value' => $meta_value,
            ],
        ];
    }
    return $args;
};

add_filter('rest_weekly_update_query', $hs_weekly_meta_filter, 10, 2);
add_filter('rest_post_query', $hs_weekly_meta_filter, 10, 2);
add_filter('rest_attachment_query', $hs_weekly_meta_filter, 10, 2);
