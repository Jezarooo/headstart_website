<?php
/*
Plugin Name: Local Homepage Redirect
Description: Replicates the Apache redirect from the live site for local testing.
*/

add_action('template_redirect', function() {
    $request_uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($request_uri, PHP_URL_PATH);
    
    // Redirect only the absolute root path to /en/our-school/
    if ($path === '/' || $path === '' || $path === null) {
        wp_redirect('/en/our-school/', 302);
        exit;
    }
}, 1);
