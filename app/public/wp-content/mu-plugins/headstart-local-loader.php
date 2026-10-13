<?php
/*
Plugin Name: HeadStart Local Site-Scripts Auto-Loader
Description: Bypasses the database Snippets plugin and dynamically loads raw PHP/JS from the local Site-Scripts repository for active development.
Author: Antigravity
Version: 1.0
*/

// 1. Load all PHP Snippets dynamically from our Git folder
$php_dir = '/Users/jeremylinay-mason/Desktop/Websites/HeadStart/Site-Scripts/php';
foreach (glob($php_dir . '/*.php') as $file) {
    require_once $file;
}

// 2. Inject Local JS into the Footer
add_action('wp_footer', function() {
    $js_dir = '/Users/jeremylinay-mason/Desktop/Websites/HeadStart/Site-Scripts/js';
    echo "<!-- HeadStart Local JS Auto-Loader -->\n";
    foreach (glob($js_dir . '/*.js') as $file) {
        echo "<script>\n" . file_get_contents($file) . "\n</script>\n";
    }
}, 9999);

// 3. Inject CSS overrides for local testing
add_action('wp_head', function() {
    echo "<style>\n/* Local override for staff hover effect */\n.hs-staff-directory .hs-staff-card.hs-has-profile:hover img { filter: none !important; }\n</style>\n";
    
    // Auto-load core CSS file
    $css_dir = '/Users/jeremylinay-mason/Desktop/Websites/HeadStart/Site-Scripts/css';
    if (file_exists($css_dir . '/core-site.css')) {
        echo "<style>\n" . file_get_contents($css_dir . '/core-site.css') . "\n</style>\n";
    }
});
