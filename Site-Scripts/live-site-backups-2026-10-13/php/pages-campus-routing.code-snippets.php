<?php

/**
 * Pages--Campus Routing
 */
/**
 * =============================================================================
 * HEADSTART CAMPUS PARENT PAGES ROUTING
 * =============================================================================
 * PURPOSE: Logic and execution for hiding/routing campus parent pages (chaofah, cherngtalay)
 * =============================================================================
 */

/**
 * 1. Redirect direct visits back to homepage and force de-indexing
 */
add_action( 'template_redirect', 'redirect_campus_parent_pages' );
function redirect_campus_parent_pages() {
    $parent_slugs = array( 'chaofah', 'cherngtalay' );
    
    if ( is_page( $parent_slugs ) ) {
        // Explicitly tell Google to drop this URL from search results
        header( 'X-Robots-Tag: noindex, nofollow', true );
        
        // 301 = Permanent Redirect to homepage
        wp_redirect( home_url(), 301 );
        exit;
    }
}

/**
 * 2. Disable the link in the WordPress menu (Changes href to #)
 */
add_filter( 'nav_menu_link_attributes', 'disable_campus_menu_link', 10, 3 );
function disable_campus_menu_link( $atts, $item, $args ) {
    $menu_url = rtrim( $item->url, '/' );
    
    if ( preg_match( '/\/(chaofah|cherngtalay)$/', $menu_url ) ) {
        $atts['href'] = '#';
        $atts['style'] = isset($atts['style']) ? $atts['style'] . ' cursor: default;' : 'cursor: default;';
    }
    
    return $atts;
}
