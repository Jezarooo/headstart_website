<?php

/**
 * Language-Switcher--Engine
 */
/**
 * HeadStart International School - Custom Multi-Language Engine
 * 
 * Instructions:
 * Paste this entire file into your Snippets plugin.
 * Ensure you have the free ACF plugin active.
 */

// ==============================================================================
// 1. ACF DATA ARCHITECTURE
// ==============================================================================
add_action('acf/init', 'headstart_register_language_fields');
function headstart_register_language_fields() {
    if( function_exists('acf_add_local_field_group') ):

        acf_add_local_field_group(array(
            'key' => 'group_headstart_language',
            'title' => 'Language Configuration',
            'fields' => array(
                array(
                    'key' => 'field_page_language',
                    'label' => 'Page Language',
                    'name' => 'page_language',
                    'type' => 'select',
                    'allow_null' => 1,
                    'choices' => array(
                        'en' => 'English',
                        'th' => 'Thai',
                        'ru' => 'Russian',
                        'zh' => 'Mandarin',
                    ),
                    'return_format' => 'value',
                ),
                array(
                    'key' => 'field_linked_translations',
                    'label' => 'Linked Translations',
                    'name' => 'linked_translations',
                    'type' => 'relationship',
                    'instructions' => 'Select the translated versions of this page.',
                    'post_type' => array('page'),
                    'filters' => array('search'),
                    'return_format' => 'id',
                    // The 'bidirectional' setting is natively supported in ACF 6.2+
                    // We also include a PHP fallback below just in case.
                    'bidirectional' => 1, 
                    'bidirectional_target' => array('field_linked_translations'),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'page',
                    ),
                ),
            ),
            'position' => 'side',
            'style' => 'default',
        ));
        
    endif;
}

// Optional: PHP Fallback for Bidirectional Relationships (ensures it works on older ACF free)
add_filter('acf/update_value/key=field_linked_translations', 'headstart_bidirectional_fallback', 10, 3);
function headstart_bidirectional_fallback($value, $post_id, $field) {
    if (isset($field['bidirectional']) && $field['bidirectional']) {
        return $value; // ACF handles it natively, skip our fallback
    }
    $old_value = get_field($field['name'], $post_id, false);
    if (!is_array($old_value)) $old_value = array();
    if (!is_array($value)) $value = array();

    $added = array_diff($value, $old_value);
    $removed = array_diff($old_value, $value);

    // Add back-links
    foreach ($added as $target_id) {
        $target_val = get_field($field['name'], $target_id, false);
        if (!is_array($target_val)) $target_val = array();
        if (!in_array($post_id, $target_val)) {
            $target_val[] = $post_id;
            update_field($field['key'], $target_val, $target_id);
        }
    }
    // Remove back-links
    foreach ($removed as $target_id) {
        $target_val = get_field($field['name'], $target_id, false);
        if (is_array($target_val) && ($key = array_search($post_id, $target_val)) !== false) {
            unset($target_val[$key]);
            update_field($field['key'], $target_val, $target_id);
        }
    }
    return $value;
}

// Optional: UI Improvement for ACF Relationship Search Results
add_filter('acf/fields/relationship/result/name=linked_translations', 'headstart_acf_language_labels', 10, 4);
function headstart_acf_language_labels($title, $post, $field, $post_id) {
    $lang = get_field('page_language', $post->ID);
    
    $lang_map = array(
        'en' => 'English',
        'th' => 'Thai',
        'ru' => 'Russian',
        'zh' => 'Mandarin'
    );

    if ($lang && isset($lang_map[$lang])) {
        // Appends e.g., " - (Thai)" to the end of the page title in the dropdown
        $title .= ' - <strong>(' . $lang_map[$lang] . ')</strong>';
    }
    
    return $title;
}

// Bypasses the 20-item limit so you can just scroll to find pages if the AJAX search is broken by a plugin
add_filter('acf/fields/relationship/query/name=linked_translations', 'headstart_acf_relationship_query', 10, 3);
function headstart_acf_relationship_query($args, $field, $post_id) {
    $args['posts_per_page'] = 500; // Load up to 500 pages
    $args['orderby'] = 'title';
    $args['order'] = 'ASC';
    return $args;
}

// ==============================================================================
// 2. AUTOMATED URL ROUTING (PARENT PAGE MAPPING)
// ==============================================================================
add_action('acf/save_post', 'headstart_auto_set_language_parent', 20);
function headstart_auto_set_language_parent($post_id) {
    // Ignore revisions and autosaves
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

    $lang = get_field('page_language', $post_id);
    if (!$lang) return;

    // We don't want to change the parent of the actual placeholder pages!
    $post_slug = get_post_field('post_name', $post_id);
    if (in_array($post_slug, array('en', 'th', 'ru', 'zh'))) return;

    // Find the placeholder page for this language
    $parent_page = get_page_by_path($lang);
    
    if ($parent_page) {
        $current_parent = wp_get_post_parent_id($post_id);
        if ($current_parent !== $parent_page->ID) {
            // Unhook to prevent infinite loops during wp_update_post
            remove_action('acf/save_post', 'headstart_auto_set_language_parent', 20);
            
            wp_update_post(array(
                'ID' => $post_id,
                'post_parent' => $parent_page->ID
            ));
            
            add_action('acf/save_post', 'headstart_auto_set_language_parent', 20);
        }
    }
}


// ==============================================================================
// 3. HTML LANG ATTRIBUTE INTERCEPTION
// ==============================================================================
add_filter('language_attributes', 'headstart_custom_lang_attr');
function headstart_custom_lang_attr($output) {
    // Safety check: Abort during AJAX, REST APIs, or Backend to prevent crashing page builders like Divi
    if (wp_doing_ajax() || wp_is_json_request() || is_admin()) {
        return $output;
    }

    if (is_singular()) {
        $obj_id = get_queried_object_id();
        if ($obj_id && function_exists('get_field')) {
            $lang = @get_field('page_language', $obj_id);
            
            $lang_map = array(
                'en' => 'en-GB',
                'th' => 'th',
                'ru' => 'ru-RU',
                'zh' => 'zh-CN'
            );

            if ($lang && isset($lang_map[$lang])) {
                return 'lang="' . $lang_map[$lang] . '"';
            }
        }
    }
    return $output;
}


// ==============================================================================
// 4. ROOT URL BROWSER ROUTING & PLACEHOLDER PROTECTION
// ==============================================================================
add_action('template_redirect', 'headstart_language_routing');
function headstart_language_routing() {
    $request_uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($request_uri, PHP_URL_PATH);
    $placeholders = array('en', 'th', 'ru', 'zh');

    // A+B. Homepage and /en|/th|/ru|/zh/ placeholders: Apache on the new origin (rebuild 2026-09).
    // Keep this snippet for [language_switcher] + sticky page translation only.
    if ($path === '/' || $path === '' || $path === null) {
        return;
    }
    if (in_array(trim($path, '/'), $placeholders, true)) {
        return;
    }

    // C. Sticky Language Routing for Pages
    if (is_singular('page')) {
        // Prevent redirects if Divi Builder or AJAX is active to protect backend
        if (isset($_GET['et_fb']) || isset($_GET['et_tb'])) return;
        if (wp_doing_ajax() || wp_is_json_request()) return;

        if (isset($_COOKIE['headstart_lang_pref'])) {
            $pref = $_COOKIE['headstart_lang_pref'];
            
            if (in_array($pref, $placeholders)) {
                $current_id = get_queried_object_id();
                $current_lang = get_field('page_language', $current_id);
                
                // If the current page language is set, and differs from preferred
                if ($current_lang && $current_lang !== $pref) {
                    $linked_ids = get_field('linked_translations', $current_id);
                    
                    if (!empty($linked_ids) && is_array($linked_ids)) {
                        foreach ($linked_ids as $target_id) {
                            if (get_post_status($target_id) !== 'publish') continue;
                            
                            $target_lang = get_field('page_language', $target_id);
                            
                            if ($target_lang === $pref) {
                                // Translation found! Redirect to preferred translation
                                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                                wp_safe_redirect(get_permalink($target_id), 302);
                                exit;
                            }
                        }
                    }
                }
            }
        }
    }
}


// ==============================================================================
// 5. THE LANGUAGE SWITCHER SHORTCODE
// ==============================================================================
add_shortcode('language_switcher', 'headstart_language_switcher_sc');
function headstart_language_switcher_sc($atts) {
    $current_id = get_queried_object_id();
    $current_lang = get_field('page_language', $current_id) ?: 'en';
    $linked_ids = get_field('linked_translations', $current_id);

    // Data for flags based on user's exact staging URLs
    $flag_data = array(
        'en' => array('url' => 'https://headstartphuket.com/wp-content/uploads/2025/12/UK-Flag-1.svg', 'title' => 'UK Flag'),
        'th' => array('url' => 'https://headstartphuket.com/wp-content/uploads/2025/12/Thai-Flag-1.svg', 'title' => 'Thai Flag'),
        'ru' => array('url' => 'https://headstartphuket.com/wp-content/uploads/2025/12/Russian-Flag-1.svg', 'title' => 'Russian Flag'),
        'zh' => array('url' => 'https://headstartphuket.com/wp-content/uploads/2025/12/Chinese-Flag-1.svg', 'title' => 'Chinese Flag'),
    );

    $is_builder_preview = false;
    
    // Legacy Divi 4 Checks
    if (isset($_GET['et_fb']) || isset($_GET['et_tb'])) {
        $is_builder_preview = true;
    }
    // Divi 5 REST API render check
    if (wp_is_json_request() && strpos($_SERVER['REQUEST_URI'], '/wp-json/divi') !== false) {
        $is_builder_preview = true;
    }

    // Map out all valid languages for this page
    $valid_langs = array();
    if ($current_lang) {
        $valid_langs[$current_lang] = $current_id; // Always include current
    }

    if (!empty($linked_ids)) {
        foreach ($linked_ids as $target_id) {
            if (get_post_status($target_id) !== 'publish') continue;
            $target_lang = get_field('page_language', $target_id);
            if ($target_lang) {
                $valid_langs[$target_lang] = $target_id;
            }
        }
    }

    if (count($valid_langs) <= 1 && !$is_builder_preview) { return ''; }

    $output = '<div class="headstart-language-switcher">';
    
    if (count($valid_langs) <= 1 && $is_builder_preview) {
        // Output Dummy Preview Flags for the Builder
        foreach ($flag_data as $t_lang => $flag) {
            $is_current = ($t_lang === 'en'); // dummy current
            $class = $is_current ? 'hs-lang-flag hs-current-lang' : 'hs-lang-flag';
            $output .= sprintf(
                '<a href="#" onclick="event.preventDefault();" class="%s" style="opacity:0.5;" title="Preview Mode"><img class="hs-lang-flag-img" loading="lazy" decoding="async" src="%s" alt="%s" width="22" height="22"></a>',
                esc_attr($class),
                esc_url($flag['url']),
                esc_attr($flag['title'])
            );
        }
    } else {
        // Output Live Linked Flags in exact order defined by $flag_data
        foreach (array_keys($flag_data) as $lang_key) {
            if (!isset($valid_langs[$lang_key])) continue;
            
            $target_id = $valid_langs[$lang_key];
            $is_current = ($lang_key === $current_lang);
            $flag = $flag_data[$lang_key];
            $class = $is_current ? 'hs-lang-flag hs-current-lang' : 'hs-lang-flag';

            $permalink = get_permalink($target_id);
            $cookie_js = "document.cookie='headstart_lang_pref={$lang_key}; path=/; max-age=2592000';";

            $output .= sprintf(
                '<a href="%s" onclick="%s" class="%s"><img class="hs-lang-flag-img" loading="lazy" decoding="async" src="%s" alt="%s" width="22" height="22"></a>',
                esc_url($permalink),
                esc_attr($cookie_js),
                esc_attr($class),
                esc_url($flag['url']),
                esc_attr($flag['title'])
            );
        }
    }

    $output .= '</div>';
    
    return $output;
}

// ==============================================================================
// 6. ADMIN UI: CUSTOM COLUMNS
// ==============================================================================
// Add columns to Pages
add_filter('manage_pages_columns', 'headstart_add_language_columns');
function headstart_add_language_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $title) {
        $new_columns[$key] = $title;
        // Insert right after the title column
        if ($key === 'title') {
            $new_columns['hs_language'] = 'Language';
            $new_columns['hs_translations'] = 'Translations';
        }
    }
    return $new_columns;
}

// Populate the columns
add_action('manage_pages_custom_column', 'headstart_fill_language_columns', 10, 2);
function headstart_fill_language_columns($column, $post_id) {
    $lang_map = array('en' => '🇬🇧 EN', 'th' => '🇹🇭 TH', 'ru' => '🇷🇺 RU', 'zh' => '🇨🇳 ZH');

    if ($column === 'hs_language') {
        $lang = get_field('page_language', $post_id);
        if ($lang && isset($lang_map[$lang])) {
            echo '<strong>' . $lang_map[$lang] . '</strong>';
        } else {
            echo '<span style="color:#999;">-</span>';
        }
    }

    if ($column === 'hs_translations') {
        $linked = get_field('linked_translations', $post_id);
        if ($linked && is_array($linked)) {
            $links = array();
            foreach ($linked as $tid) {
                $t_lang = get_field('page_language', $tid);
                if ($t_lang && isset($lang_map[$t_lang])) {
                    $edit_url = get_edit_post_link($tid);
                    $links[] = '<a href="' . esc_url($edit_url) . '">' . $lang_map[$t_lang] . '</a>';
                }
            }
            if (!empty($links)) {
                echo implode(' | ', $links);
            } else {
                echo '<span style="color:#999;">-</span>';
            }
        } else {
            echo '<span style="color:#999;">-</span>';
        }
    }
}

// ==============================================================================
// 7. SEO: HREFLANG TAGS (from Jez, 23 Sep 2026; x-default added)
// ==============================================================================
// Maps each page to its linked translations for search engines, using the same
// ACF fields as the switcher. Outputs only when 2+ published versions exist.
add_action('wp_head', 'headstart_output_hreflang_tags', 1);
function headstart_output_hreflang_tags() {
    if (!is_singular('page')) return;

    $hreflang_map = array('en' => 'en-GB', 'th' => 'th', 'ru' => 'ru', 'zh' => 'zh');
    $current_id   = get_queried_object_id();
    $page_ids     = array_merge(array($current_id), (array) get_field('linked_translations', $current_id));

    $versions = array();
    foreach ($page_ids as $id) {
        if (!$id || get_post_status($id) !== 'publish') continue;
        $lang = get_field('page_language', $id);
        if (isset($hreflang_map[$lang])) $versions[$lang] = get_permalink($id);
    }
    if (count($versions) < 2) return;

    foreach ($versions as $lang => $url) {
        printf('<link rel="alternate" hreflang="%s" href="%s" />' . "\n", esc_attr($hreflang_map[$lang]), esc_url($url));
    }
    if (isset($versions['en'])) {
        printf('<link rel="alternate" hreflang="x-default" href="%s" />' . "\n", esc_url($versions['en']));
    }
}
