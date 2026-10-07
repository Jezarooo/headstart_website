<?php
$file = '/Users/jeremylinay-mason/Desktop/Websites/HeadStart/Site-Scripts/php/staff-directory-shortcode-engine.php';
$content = file_get_contents($file);
$content = preg_replace('/}\n\s*}\n\s*\/\/\s*Taxonomies/', "}\n\n                // Taxonomies", $content);
file_put_contents($file, $content);
echo "Fixed!";
