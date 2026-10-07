<?php
$file = '/Users/jeremylinay-mason/Desktop/Websites/HeadStart/Site-Scripts/php/staff-directory-shortcode-engine.php';
$content = file_get_contents($file);

$old = <<<CODE
                if (\$reports_to_obj) {
                    // If it returns an array of objects (if multiple allowed), grab the first
                    if (is_array(\$reports_to_obj)) {
                        \$manager = html_entity_decode(get_the_title(\$reports_to_obj[0]->ID), ENT_QUOTES, 'UTF-8');
                    } else {
                        \$manager = html_entity_decode(get_the_title(\$reports_to_obj->ID), ENT_QUOTES, 'UTF-8');
                    }
                }
CODE;

$new = <<<CODE
                if (\$reports_to_obj) {
                    // If it returns an array (if multiple allowed), grab the first
                    if (is_array(\$reports_to_obj)) {
                        \$reports_to_obj = \$reports_to_obj[0];
                    }
                    
                    if (is_object(\$reports_to_obj) && isset(\$reports_to_obj->ID)) {
                        \$manager = html_entity_decode(get_the_title(\$reports_to_obj->ID), ENT_QUOTES, 'UTF-8');
                    } elseif (is_numeric(\$reports_to_obj)) {
                        \$manager = html_entity_decode(get_the_title(\$reports_to_obj), ENT_QUOTES, 'UTF-8');
                    } elseif (is_string(\$reports_to_obj)) {
                        \$manager = html_entity_decode(\$reports_to_obj, ENT_QUOTES, 'UTF-8');
                    }
                }
CODE;

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Patched ID bug!";
