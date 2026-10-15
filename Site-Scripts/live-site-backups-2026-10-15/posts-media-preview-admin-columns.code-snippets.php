<?php

/**
 * Posts--Media-Preview-Admin-Columns
 */
/**
 * ====================================================================
 * ADMIN POST LIST MEDIA COLUMNS
 * Adds Featured Image preview and Body Image count to the Post List View.
 * Includes logic to make the image count sortable via postmeta.
 * ====================================================================
 */

// 1. Register Columns (Appended via filter)
add_filter('manage_post_posts_columns', 'hs_custom_media_columns', 100); // Run after admin-cols.php
function hs_custom_media_columns($columns) {
    $new_columns = array();
    
    foreach ($columns as $key => $title) {
        if ($key === 'title') {
            // Insert Featured Image right before the Title
            $new_columns['hs_featured_image_col'] = 'Image';
        }
        $new_columns[$key] = $title;
        
        if ($key === 'hs_tags_col' || $key === 'tags') {
            // Insert Body Images right after Tags (or wherever tags are)
            $new_columns['hs_body_image_count_col'] = 'Body Images';
        }
    }
    return $new_columns;
}

// 2. Output Column Content
add_action('manage_post_posts_custom_column', 'hs_custom_media_column_content', 10, 2);
function hs_custom_media_column_content($column_name, $post_id) {
    if ($column_name === 'hs_featured_image_col') {
        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            $img = wp_get_attachment_image_src($thumb_id, 'thumbnail');
            if ($img) {
                echo '<div style="width:60px; height:60px; overflow:hidden; border-radius:4px; border:1px solid #ddd; background:#f5f5f5; display:flex; align-items:center; justify-content:center;">';
                echo '<img src="' . esc_url($img[0]) . '" style="width:100%; height:100%; object-fit:cover;" />';
                echo '</div>';
            }
        } else {
            echo '<div style="width:60px; height:60px; border-radius:4px; background:#f0f0f1; border:1px dashed #ccc; display:flex; align-items:center; justify-content:center; color:#999; font-size:10px; text-align:center;">No<br>Img</div>';
        }
    }

    if ($column_name === 'hs_body_image_count_col') {
        $count = get_post_meta($post_id, '_hs_body_image_count', true);
        
        // If meta doesn't exist (e.g. freshly imported post before sync), calculate it on the fly just for display
        if ($count === '') {
            $post = get_post($post_id);
            $count = hs_calculate_post_image_count($post->post_content);
            // Optionally auto-heal single posts when viewed, though sync script handles bulk
            update_post_meta($post_id, '_hs_body_image_count', $count); 
        }
        
        echo '<span style="display:inline-block; padding:3px 8px; background:#f0f0f1; border-radius:12px; font-weight:600; font-size:12px; color:#333;">';
        echo '<span class="dashicons dashicons-format-image" style="font-size:14px; width:14px; height:14px; line-height:14px; margin-right:4px; vertical-align:middle; color:#888;"></span>';
        echo intval($count);
        echo '</span>';
    }
}

// 3. Image Count Calculator Engine
function hs_calculate_post_image_count($content) {
    $count = 0;
    if (empty($content)) return $count;

    // A. Count standard HTML <img> tags (handles imported block editor / raw HTML)
    $count += preg_match_all('/<img[^>]+>/i', $content);

    // B. Count Divi Image modules
    $count += preg_match_all('/\[et_pb_image\s/i', $content);

    // C. Count Divi Gallery module images
    if (preg_match_all('/\[et_pb_gallery[^\]]*gallery_ids="([^"]+)"/i', $content, $matches)) {
        foreach ($matches[1] as $ids_string) {
            $ids = array_filter(explode(',', $ids_string));
            $count += count($ids);
        }
    }

    // D. Count WordPress native gallery shortcodes
    if (preg_match_all('/\[gallery[^\]]*ids="([^"]+)"/i', $content, $matches)) {
        foreach ($matches[1] as $ids_string) {
            $ids = array_filter(explode(',', $ids_string));
            $count += count($ids);
        }
    }

    return $count;
}

// 4. Save Logic: Auto-calculate and store on post save
add_action('save_post', 'hs_update_body_image_count_on_save', 10, 2);
function hs_update_body_image_count_on_save($post_id, $post) {
    // Skip revisions, autosaves, and non-posts
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_type !== 'post') {
        return;
    }
    
    $count = hs_calculate_post_image_count($post->post_content);
    update_post_meta($post_id, '_hs_body_image_count', $count);
}

// 5. Make the column sortable
add_filter('manage_edit-post_sortable_columns', 'hs_register_sortable_media_columns');
function hs_register_sortable_media_columns($columns) {
    $columns['hs_body_image_count_col'] = 'hs_body_image_count';
    return $columns;
}

// 6. Handle the sort query
add_action('pre_get_posts', 'hs_custom_media_orderby');
function hs_custom_media_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    
    if ($query->get('orderby') === 'hs_body_image_count') {
        $query->set('meta_key', '_hs_body_image_count');
        $query->set('orderby', 'meta_value_num');
    }
}

// 7. Bulk Sync Utility for Imported Posts
// Adds a button to the admin screen if there are posts without the meta key
add_action('admin_notices', 'hs_image_count_sync_notice');
function hs_image_count_sync_notice() {
    global $pagenow, $post_type;
    
    if ($pagenow !== 'edit.php' || $post_type !== 'post') return;
    if (!current_user_can('manage_options')) return;

    // Check if sync was just run
    if (isset($_GET['hs_sync_images']) && $_GET['hs_sync_images'] === '1') {
        hs_run_bulk_image_count_sync();
        echo '<div class="notice notice-success is-dismissible"><p><strong>Success:</strong> Image counts have been synced for all posts!</p></div>';
        return;
    }

    // Quick check if there are posts missing the meta key (limits to 1 for speed)
    $missing_meta_posts = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => array(
            array(
                'key' => '_hs_body_image_count',
                'compare' => 'NOT EXISTS'
            )
        )
    ));

    if ($missing_meta_posts->found_posts > 0) {
        $sync_url = add_query_arg('hs_sync_images', '1');
        echo '<div class="notice notice-warning" style="padding:10px;"><p><strong>Database Optimization:</strong> There are imported posts that need their image counts calculated so they can be sorted in the list view.</p>';
        echo '<p><a href="' . esc_url($sync_url) . '" class="button button-primary">Sync Image Counts Now</a> (This may take a few seconds)</p></div>';
    }
}

function hs_run_bulk_image_count_sync() {
    // We fetch ALL posts missing the meta key. 
    // Since calculating regex on 1000 posts takes < 1 second in PHP, doing it in one chunk is usually safe.
    $query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            array(
                'key' => '_hs_body_image_count',
                'compare' => 'NOT EXISTS'
            )
        )
    ));

    if (!empty($query->posts)) {
        foreach ($query->posts as $post_id) {
            $post = get_post($post_id);
            $count = hs_calculate_post_image_count($post->post_content);
            update_post_meta($post_id, '_hs_body_image_count', $count);
        }
    }
}
