<?php

/**
 * Images--Meta-Info-On-Upload
 */
/**
 * Plugin/Snippet Name: Strip Image Titles & Captions
 * Description: Adds a tool under Media to bulk remove titles, captions, and descriptions from images. Also auto-strips on new uploads.
 */

// ==========================================
// 1. AUTO STRIP ON UPLOAD
// ==========================================
add_filter( 'wp_insert_attachment_data', 'agy_auto_strip_image_captions', 10, 2 );
function agy_auto_strip_image_captions( $data, $postarr ) {
    // Check if the upload is an attachment and specifically an image
    if ( $data['post_type'] === 'attachment' && strpos( $data['post_mime_type'], 'image/' ) === 0 ) {
        // Clear the title, excerpt (caption), and content (description)
        $data['post_title']   = '';
        $data['post_excerpt'] = '';
        $data['post_content'] = '';
    }
    return $data;
}

// ==========================================
// 2. ADMIN MENU TOOL FOR EXISTING IMAGES
// ==========================================
add_action('admin_menu', 'agy_strip_captions_menu');
function agy_strip_captions_menu() {
    add_submenu_page(
        'upload.php',
        'Strip Captions',
        'Strip Captions',
        'manage_options',
        'agy-strip-captions',
        'agy_strip_captions_page'
    );
}

// Render Page and Handle Logic
function agy_strip_captions_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $step = isset($_GET['step']) ? intval($_GET['step']) : 0;
    $include_cat = isset($_GET['include_cat']) ? intval($_GET['include_cat']) : 0;
    $exclude_cat = isset($_GET['exclude_cat']) ? intval($_GET['exclude_cat']) : 0;
    
    echo '<div class="wrap">';
    echo '<h1>Bulk Strip Image Titles & Captions</h1>';

    if ($step > 0) {
        // Processing Step (Batch of 200)
        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => 200,
            'paged'          => $step,
            'fields'         => 'ids' // Only get IDs for performance
        );

        $tax_query = array();

        // Add inclusion logic
        if ($include_cat > 0) {
            $tax_query[] = array(
                'taxonomy' => 'attachment-category', 
                'field'    => 'term_id',
                'terms'    => $include_cat,
                'operator' => 'IN',
            );
        }

        // Add exclusion logic
        if ($exclude_cat > 0) {
            $tax_query[] = array(
                'taxonomy' => 'attachment-category', 
                'field'    => 'term_id',
                'terms'    => $exclude_cat,
                'operator' => 'NOT IN',
            );
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                // Update post to remove title, excerpt (caption), and content (description)
                wp_update_post(array(
                    'ID'           => $post_id,
                    'post_title'   => '',
                    'post_excerpt' => '',
                    'post_content' => ''
                ));
            }
            
            $next_step = $step + 1;
            $url = admin_url("upload.php?page=agy-strip-captions&step={$next_step}&include_cat={$include_cat}&exclude_cat={$exclude_cat}");
            
            echo '<div class="notice notice-info is-dismissible"><p>Processed batch ' . $step . ' (' . count($query->posts) . ' images). Moving to next batch...</p></div>';
            echo '<script>setTimeout(function(){ window.location.href = "' . $url . '"; }, 1500);</script>';
        } else {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Finished! All applicable images have been cleaned.</strong></p></div>';
            echo '<a href="' . admin_url('upload.php?page=agy-strip-captions') . '" class="button">Run Again</a>';
        }
    } else {
        // Start Page UI
        ?>
        <div class="notice notice-warning">
            <p><strong>Note:</strong> Auto-stripping for NEW uploads is active. This tool below is to clean up EXISTING images in your library.</p>
        </div>
        <form method="GET" action="<?php echo admin_url('upload.php'); ?>">
            <input type="hidden" name="page" value="agy-strip-captions">
            <input type="hidden" name="step" value="1">
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="include_cat">Category to INCLUDE (Only strip these)</label></th>
                    <td>
                        <?php 
                        wp_dropdown_categories(array(
                            'show_option_all' => 'Process All Categories',
                            'taxonomy'        => 'attachment-category', 
                            'name'            => 'include_cat',
                            'hide_empty'      => 0,
                        )); 
                        ?>
                        <p class="description">If selected, ONLY images with this category will be stripped.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="exclude_cat">Category to EXCLUDE (Protect these)</label></th>
                    <td>
                        <?php 
                        wp_dropdown_categories(array(
                            'show_option_all' => 'Do not exclude any',
                            'taxonomy'        => 'attachment-category', 
                            'name'            => 'exclude_cat',
                            'hide_empty'      => 0,
                        )); 
                        ?>
                        <p class="description">Images assigned to this category will KEEP their titles and captions.</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Run Caption Stripper">
            </p>
        </form>
        <?php
    }
    echo '</div>';
}
