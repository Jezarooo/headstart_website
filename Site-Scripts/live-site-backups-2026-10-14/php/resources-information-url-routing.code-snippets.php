<?php

/**
 * Resources-Information--URL-Routing
 */
/**
 * 1. Redirect direct visits to the parent page back to the homepage
 */
add_action( 'template_redirect', 'redirect_resources_parent_page' );
function redirect_resources_parent_page() {
    // 'resources-and-information' is the page slug
    if ( is_page( 'resources-and-information' ) ) {
        // 301 = Permanent Redirect. home_url() automatically gets the current domain.
        wp_redirect( home_url(), 301 );
        exit;
    }
}

/**
 * 2. Disable the link in the WordPress menu (Changes href to #)
 */
add_filter( 'nav_menu_link_attributes', 'disable_resources_menu_link', 10, 3 );
function disable_resources_menu_link( $atts, $item, $args ) {
    
    // Remove trailing slashes to standardize the URL check
    $menu_url = rtrim( $item->url, '/' );
    
    // Make sure it only targets the parent (ends with the slug) and not the child pages
    if ( preg_match( '/resources-and-information$/', $menu_url ) ) {
        
        // Replace the link destination
        $atts['href'] = '#';
        
        // Change the cursor to default so it doesn't look clickable
        $atts['style'] = isset($atts['style']) ? $atts['style'] . ' cursor: default;' : 'cursor: default;';
    }
    
    return $atts;
}
