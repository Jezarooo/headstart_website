<?php

/**
 * Posts--Themematic-Tag-Label-Strip
 */
/**
 * HeadStart: Clean Post Tags for Frontend Display
 * Strips out taxonomy prefixes (e.g., "ACADEMIC: ") from tags natively.
 */
add_filter('get_the_terms', 'hs_clean_post_tags_frontend', 10, 3);
function hs_clean_post_tags_frontend($terms, $post_id, $taxonomy) {
    
    // Only modify on the frontend, and only for 'post_tag'
    if (!is_admin() && $taxonomy === 'post_tag' && !empty($terms) && !is_wp_error($terms)) {
        
        foreach ($terms as $term) {
            // Check if the tag contains a colon
            if (strpos($term->name, ':') !== false) {
                // Split the text at the colon and keep only the right side
                $parts = explode(':', $term->name, 2);
                $term->name = trim($parts[1]); 
            }
        }
    }
    
    return $terms;
}
