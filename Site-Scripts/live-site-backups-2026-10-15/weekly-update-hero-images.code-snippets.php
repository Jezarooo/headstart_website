<?php

/**
 * Weekly-Update--Hero-Images
 */
/**
 * =============================================================================
 * HEADSTART WEEKLY UPDATE HERO IMAGES
 * =============================================================================
 * PURPOSE: Logic and execution for WEEKLY UPDATE HERO IMAGES
 * DEPENDENCIES: WordPress core, Divi
 * [TODO: REVIEW_OPTIMIZATIONS - Extract inline JS/CSS, implement Nonces, escape HTML]
 * =============================================================================
 */

/**
 * Plugin Name: HeadStart Hero Images & Mega Menu Management
 * Description: Combined settings for Campus Hub Hero CSS Injection and Mega Menu Latest Update Shortcode with aggressive caching.
 */

/* ==========================================================================
 * 1. SHARED MEDIA UPLOADER SCRIPT
 * ========================================================================== */
add_action('admin_enqueue_scripts', 'hs_hero_management_load_media_scripts');
function hs_hero_management_load_media_scripts($hook) {
    if ($hook === 'toplevel_page_hs-hero-images') {
        wp_enqueue_media();
    }
}

/* ==========================================================================
 * 2. COMBINED ADMIN MENU & SETTINGS
 * ========================================================================== */

// Register Menu
add_action('admin_menu', 'hs_hero_images_add_admin_menu');
function hs_hero_images_add_admin_menu() {
    add_menu_page(
        'Hero & Menu Images',
        'Hero & Menu Images',
        'manage_options',
        'hs-hero-images',
        'hs_hero_images_settings_page',
        'dashicons-images-alt2',
        25
    );
}

// Register Settings
add_action('admin_init', 'hs_hero_images_settings_init');
function hs_hero_images_settings_init() {
    // Campus Hub Heroes
    register_setting('hs_hero_settings_group', 'chaofah_hero_image');
    register_setting('hs_hero_settings_group', 'chaofah_hero_align_xxl');
    register_setting('hs_hero_settings_group', 'chaofah_hero_align_xl');
    register_setting('hs_hero_settings_group', 'chaofah_hero_align_lg');
    register_setting('hs_hero_settings_group', 'chaofah_hero_align_md');
    register_setting('hs_hero_settings_group', 'chaofah_hero_size_xxl');
    register_setting('hs_hero_settings_group', 'chaofah_hero_size_xl');
    register_setting('hs_hero_settings_group', 'chaofah_hero_size_lg');
    register_setting('hs_hero_settings_group', 'chaofah_hero_size_md');

    register_setting('hs_hero_settings_group', 'cherngtalay_hero_image');
    register_setting('hs_hero_settings_group', 'cherngtalay_hero_align_xxl');
    register_setting('hs_hero_settings_group', 'cherngtalay_hero_align_xl');
    register_setting('hs_hero_settings_group', 'cherngtalay_hero_align_lg');
    register_setting('hs_hero_settings_group', 'cherngtalay_hero_align_md');
    register_setting('hs_hero_settings_group', 'cherngtalay_hero_size_xxl');
    register_setting('hs_hero_settings_group', 'cherngtalay_hero_size_xl');
    register_setting('hs_hero_settings_group', 'cherngtalay_hero_size_lg');
    register_setting('hs_hero_settings_group', 'cherngtalay_hero_size_md');
    // Mega Menu Overrides (Images)
    register_setting('hs_hero_settings_group', 'chaofah_megamenu_override');
    register_setting('hs_hero_settings_group', 'cherngtalay_megamenu_override');
    register_setting('hs_hero_settings_group', 'alumni_megamenu_image');
    
    // Mega Menu Overrides (Links)
    register_setting('hs_hero_settings_group', 'chaofah_megamenu_link');
    register_setting('hs_hero_settings_group', 'cherngtalay_megamenu_link');
    register_setting('hs_hero_settings_group', 'alumni_megamenu_link');
}

// Settings Page UI
function hs_hero_images_settings_page() {
    // Hero Data
    $chaofah_img = get_option('chaofah_hero_image');
    $chaofah_align_xxl = get_option('chaofah_hero_align_xxl', 'top center');
    $chaofah_align_xl = get_option('chaofah_hero_align_xl', 'top center');
    $chaofah_align_lg = get_option('chaofah_hero_align_lg', 'top center');
    $chaofah_align_md = get_option('chaofah_hero_align_md', 'center center');
    
    $chaofah_size_xxl = get_option('chaofah_hero_size_xxl', 'cover');
    $chaofah_size_xl = get_option('chaofah_hero_size_xl', 'cover');
    $chaofah_size_lg = get_option('chaofah_hero_size_lg', 'cover');
    $chaofah_size_md = get_option('chaofah_hero_size_md', 'cover');
    
    $cherngtalay_img = get_option('cherngtalay_hero_image');
    $cherngtalay_align_xxl = get_option('cherngtalay_hero_align_xxl', 'top center');
    $cherngtalay_align_xl = get_option('cherngtalay_hero_align_xl', 'top center');
    $cherngtalay_align_lg = get_option('cherngtalay_hero_align_lg', 'top center');
    $cherngtalay_align_md = get_option('cherngtalay_hero_align_md', 'center center');
    
    $cherngtalay_size_xxl = get_option('cherngtalay_hero_size_xxl', 'cover');
    $cherngtalay_size_xl = get_option('cherngtalay_hero_size_xl', 'cover');
    $cherngtalay_size_lg = get_option('cherngtalay_hero_size_lg', 'cover');
    $cherngtalay_size_md = get_option('cherngtalay_hero_size_md', 'cover');
    // Mega Menu Data
    $chaofah_override = get_option('chaofah_megamenu_override');
    $cherngtalay_override = get_option('cherngtalay_megamenu_override');
    $alumni_image = get_option('alumni_megamenu_image');
    
    $chaofah_link = get_option('chaofah_megamenu_link');
    $cherngtalay_link = get_option('cherngtalay_megamenu_link');
    $alumni_link = get_option('alumni_megamenu_link');
    ?>
    <div class="wrap">
        <h1>Hero & Menu Images</h1>
        <?php settings_errors(); ?>
        
        <form method="post" action="options.php">
            <?php settings_fields('hs_hero_settings_group'); ?>
            
            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">
            <h2 style="font-size: 20px; font-weight: 600;">1. Campus Hub Hero Images (CSS Injection)</h2>
            <p>Manage the background hero images for each campus. Just add the provided CSS Class to your Divi Hero Section!</p>
<?php
    if (!function_exists('render_hero_controls')) {
        function render_hero_controls($prefix, $align_xxl, $align_xl, $align_lg, $align_md, $size_xxl, $size_xl, $size_lg, $size_md) {
            $alignments = ['top left', 'top center', 'top right', 'center left', 'center center', 'center right', 'bottom left', 'bottom center', 'bottom right'];
            $sizes = [
                'cover' => 'Fill Space (Cover) - No white space, might crop image',
                'contain' => 'Fit Inside (Contain) - Shows whole image, might have blank spaces',
                '100% 100%' => 'Stretch to Fit - Distorts image to fill the area',
                'auto' => 'Original Size (Auto) - Keeps actual image dimensions'
            ];
            
            echo '<div style="display: flex; gap: 15px; margin-top: 15px; flex-wrap: wrap;">';
            
            $breakpoints = [
                'xxl' => 'Desktop (work computers) (1920px)',
                'xl'  => 'Desktop (mac) (1592px)',
                'lg'  => 'Tablet (920px)',
                'md'  => 'Mobile (484px)'
            ];
            
            foreach ($breakpoints as $bp => $label) {
                echo '<div style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-width: 140px;">';
                echo '<h4 style="margin-top:0; margin-bottom: 10px; font-size: 13px;">' . $label . '</h4>';
                
                $align_val = ${'align_' . $bp};
                echo '<label style="display:block; margin-bottom: 5px;"><strong>Alignment:</strong><br><select name="' . $prefix . '_hero_align_' . $bp . '">';
                foreach($alignments as $a) {
                    echo '<option value="' . $a . '" ' . selected($align_val, $a, false) . '>' . ucwords($a) . '</option>';
                }
                echo '</select></label>';
                
                $size_val = ${'size_' . $bp};
                echo '<label style="display:block;"><strong>Size:</strong><br><select name="' . $prefix . '_hero_size_' . $bp . '">';
                foreach($sizes as $css_val => $friendly_name) {
                    echo '<option value="' . $css_val . '" ' . selected($size_val, $css_val, false) . '>' . $friendly_name . '</option>';
                }
                echo '</select></label>';
                
                echo '</div>';
            }
            echo '</div>';
        }
    }
?>

            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <h3>Chaofah Campus</h3>
                        <p><strong>Divi CSS Class:</strong><br><code style="user-select: all;">campus-hero-chaofah</code></p>
                    </th>
                    <td>
                        <input type="hidden" name="chaofah_hero_image" id="chaofah_hero_image" value="<?php echo esc_attr($chaofah_img); ?>" />
                        <div style="margin-bottom: 15px;">
                            <img id="preview_chaofah_hero_image" src="<?php echo esc_url($chaofah_img); ?>" style="max-width: 400px; height: auto; display: <?php echo $chaofah_img ? 'block' : 'none'; ?>; border: 2px solid #ccc; border-radius: 5px;" />
                        </div>
                        <button type="button" class="button button-primary upload_image_button" data-target="chaofah_hero_image">Select Image</button>
                        <button type="button" class="button remove_image_button" data-target="chaofah_hero_image">Remove Image</button>
                        <?php render_hero_controls('chaofah', $chaofah_align_xxl, $chaofah_align_xl, $chaofah_align_lg, $chaofah_align_md, $chaofah_size_xxl, $chaofah_size_xl, $chaofah_size_lg, $chaofah_size_md); ?>
                    </td>
                </tr>
                <tr valign="top" style="border-top: 1px solid #ddd;">
                    <th scope="row">
                        <br><h3>Cherngtalay Campus</h3>
                        <p><strong>Divi CSS Class:</strong><br><code style="user-select: all;">campus-hero-cherngtalay</code></p>
                    </th>
                    <td>
                        <br>
                        <input type="hidden" name="cherngtalay_hero_image" id="cherngtalay_hero_image" value="<?php echo esc_attr($cherngtalay_img); ?>" />
                        <div style="margin-bottom: 15px;">
                            <img id="preview_cherngtalay_hero_image" src="<?php echo esc_url($cherngtalay_img); ?>" style="max-width: 400px; height: auto; display: <?php echo $cherngtalay_img ? 'block' : 'none'; ?>; border: 2px solid #ccc; border-radius: 5px;" />
                        </div>
                        <button type="button" class="button button-primary upload_image_button" data-target="cherngtalay_hero_image">Select Image</button>
                        <button type="button" class="button remove_image_button" data-target="cherngtalay_hero_image">Remove Image</button>
                        <?php render_hero_controls('cherngtalay', $cherngtalay_align_xxl, $cherngtalay_align_xl, $cherngtalay_align_lg, $cherngtalay_align_md, $cherngtalay_size_xxl, $cherngtalay_size_xl, $cherngtalay_size_lg, $cherngtalay_size_md); ?>
                    </td>
                </tr>
            </table>

            <hr style="margin: 40px 0; border: 0; border-top: 1px solid #ddd;">
            <h2 style="font-size: 20px; font-weight: 600;">2. Mega Menu Images & Links</h2>
            <p>By default, the Mega Menu pulls the featured image from the latest Weekly Update. You can override the image and link destinations here.</p>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <h3>Chaofah Override</h3>
                        <p>Leave image empty to use the latest Weekly Update image automatically.</p>
                        <p><strong>Shortcode:</strong><br><code style="user-select: all;">[latest_update_image campus="chaofah" label="Chaofah Campus Hub"]</code></p>
                    </th>
                    <td>
                        <input type="hidden" name="chaofah_megamenu_override" id="chaofah_megamenu_override" value="<?php echo esc_attr($chaofah_override); ?>" />
                        <div style="margin-bottom: 15px;">
                            <img id="preview_chaofah_megamenu_override" src="<?php echo esc_url($chaofah_override); ?>" style="max-width: 300px; height: auto; display: <?php echo $chaofah_override ? 'block' : 'none'; ?>; border: 2px solid #ccc; border-radius: 5px;" />
                        </div>
                        <button type="button" class="button button-primary upload_image_button" data-target="chaofah_megamenu_override">Select Override Image</button>
                        <button type="button" class="button remove_image_button" data-target="chaofah_megamenu_override">Remove Override Image</button>
                        
                        <div style="margin-top: 20px;">
                            <label><strong>Select Link Target:</strong><br>
                                <?php 
                                wp_dropdown_pages(array(
                                    'name'              => 'chaofah_megamenu_link',
                                    'selected'          => $chaofah_link,
                                    'show_option_none'  => '&mdash; Default (/chaofah/campus-hub/) &mdash;',
                                    'option_none_value' => '0'
                                )); 
                                ?>
                            </label>
                            <p class="description">Select a page from the database. If none is selected, defaults to <code>/chaofah/campus-hub/</code>.</p>
                        </div>
                    </td>
                </tr>
                <tr valign="top" style="border-top: 1px solid #ddd;">
                    <th scope="row">
                        <br><h3>Cherngtalay Override</h3>
                        <p>Leave image empty to use the latest Weekly Update image automatically.</p>
                        <p><strong>Shortcode:</strong><br><code style="user-select: all;">[latest_update_image campus="cherngtalay" label="Cherngtalay Campus Hub"]</code></p>
                    </th>
                    <td>
                        <br>
                        <input type="hidden" name="cherngtalay_megamenu_override" id="cherngtalay_megamenu_override" value="<?php echo esc_attr($cherngtalay_override); ?>" />
                        <div style="margin-bottom: 15px;">
                            <img id="preview_cherngtalay_megamenu_override" src="<?php echo esc_url($cherngtalay_override); ?>" style="max-width: 300px; height: auto; display: <?php echo $cherngtalay_override ? 'block' : 'none'; ?>; border: 2px solid #ccc; border-radius: 5px;" />
                        </div>
                        <button type="button" class="button button-primary upload_image_button" data-target="cherngtalay_megamenu_override">Select Override Image</button>
                        <button type="button" class="button remove_image_button" data-target="cherngtalay_megamenu_override">Remove Override Image</button>
                        
                        <div style="margin-top: 20px;">
                            <label><strong>Select Link Target:</strong><br>
                                <?php 
                                wp_dropdown_pages(array(
                                    'name'              => 'cherngtalay_megamenu_link',
                                    'selected'          => $cherngtalay_link,
                                    'show_option_none'  => '&mdash; Default (/cherngtalay/campus-hub/) &mdash;',
                                    'option_none_value' => '0'
                                )); 
                                ?>
                            </label>
                            <p class="description">Select a page from the database. If none is selected, defaults to <code>/cherngtalay/campus-hub/</code>.</p>
                        </div>
                    </td>
                </tr>
                <tr valign="top" style="border-top: 1px solid #ddd;">
                    <th scope="row">
                        <br><h3>HeadStart Alumni</h3>
                        <p>This image is <strong>only</strong> controlled from here (no weekly updates).</p>
                        <p><strong>Shortcode:</strong><br><code style="user-select: all;">[latest_update_image campus="alumni" label="HeadStart Alumni"]</code></p>
                    </th>
                    <td>
                        <br>
                        <input type="hidden" name="alumni_megamenu_image" id="alumni_megamenu_image" value="<?php echo esc_attr($alumni_image); ?>" />
                        <div style="margin-bottom: 15px;">
                            <img id="preview_alumni_megamenu_image" src="<?php echo esc_url($alumni_image); ?>" style="max-width: 300px; height: auto; display: <?php echo $alumni_image ? 'block' : 'none'; ?>; border: 2px solid #ccc; border-radius: 5px;" />
                        </div>
                        <button type="button" class="button button-primary upload_image_button" data-target="alumni_megamenu_image">Select Image</button>
                        <button type="button" class="button remove_image_button" data-target="alumni_megamenu_image">Remove Image</button>
                        
                        <div style="margin-top: 20px;">
                            <label><strong>Select Link Target:</strong><br>
                                <?php 
                                wp_dropdown_pages(array(
                                    'name'              => 'alumni_megamenu_link',
                                    'selected'          => $alumni_link,
                                    'show_option_none'  => '&mdash; Default (/alumni-network/) &mdash;',
                                    'option_none_value' => '0'
                                )); 
                                ?>
                            </label>
                            <p class="description">Select a page from the database. If none is selected, defaults to <code>/alumni-network/</code>.</p>
                        </div>
                    </td>
                </tr>
            </table>

            <hr style="margin: 30px 0; border: 0; border-top: 1px solid #ddd;">
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary button-large" value="Save All Image Settings" />
            </p>
        </form>
    </div>
    <?php hs_render_media_uploader_js(); ?>
    <?php
}

/* ==========================================================================
 * 3. CAMPUS HUB HEROES (CSS INJECTION)
 * ========================================================================== */
add_action('wp_head', 'hs_campus_hero_inject_css');
function hs_campus_hero_inject_css() {
    $chaofah_img = get_option('chaofah_hero_image');
    $cherngtalay_img = get_option('cherngtalay_hero_image');

    if (empty($chaofah_img) && empty($cherngtalay_img)) return;

    echo "<!-- Campus Hub Hero Images CSS -->
<style>
";
    
    // Function to generate the breakpoints CSS
    $gen_css = function($campus, $img) {
        if (empty($img)) return;
        
        $align_xxl = get_option($campus . '_hero_align_xxl', 'top center');
        $align_xl  = get_option($campus . '_hero_align_xl', 'top center');
        $align_lg  = get_option($campus . '_hero_align_lg', 'top center');
        $align_md  = get_option($campus . '_hero_align_md', 'center center');
        
        $size_xxl = get_option($campus . '_hero_size_xxl', 'cover');
        $size_xl  = get_option($campus . '_hero_size_xl', 'cover');
        $size_lg  = get_option($campus . '_hero_size_lg', 'cover');
        $size_md  = get_option($campus . '_hero_size_md', 'cover');
        
        // Base class (Mobile First, < 920px) -- maps to 484px control
        echo ".campus-hero-{$campus} { background-image: url('" . esc_url($img) . "') !important; background-size: {$size_md} !important; background-position: {$align_md} !important; }
";
        
        // LG (≥ 920px)
        echo "@media (min-width: 920px) { .campus-hero-{$campus} { background-size: {$size_lg} !important; background-position: {$align_lg} !important; } }
";
        
        // XL (≥ 1592px)
        echo "@media (min-width: 1592px) { .campus-hero-{$campus} { background-size: {$size_xl} !important; background-position: {$align_xl} !important; } }
";
        
        // XXL (≥ 1920px)
        echo "@media (min-width: 1920px) { .campus-hero-{$campus} { background-size: {$size_xxl} !important; background-position: {$align_xxl} !important; } }
";
    };

    $gen_css('chaofah', $chaofah_img);
    $gen_css('cherngtalay', $cherngtalay_img);

    echo "</style>
";
}

/* ==========================================================================
 * 4. MEGA MENU LATEST UPDATE SHORTCODE
 * ========================================================================== */

// Clear the WP_Query cache instantly when a new weekly update is saved!
add_action('save_post_weekly_update', 'hs_clear_megamenu_cache');
function hs_clear_megamenu_cache() {
    delete_transient('hs_latest_weekly_update_chaofah');
    delete_transient('hs_latest_weekly_update_cherngtalay');
}

// Shortcode Logic (Highly Optimized)
add_shortcode('latest_update_image', 'hs_latest_update_image_shortcode');
function hs_latest_update_image_shortcode($atts) {
    $atts = shortcode_atts(array(
        'campus' => 'chaofah',
        'css_class' => 'mega-menu-latest-image',
        'label' => '', // e.g. "Chaofah Campus Hub"
        'url' => '',   // Custom URL (useful for Alumni)
    ), $atts, 'latest_update_image');

    $campus = sanitize_text_field($atts['campus']);
    $label = sanitize_text_field($atts['label']);
    $custom_url = esc_url($atts['url']);
    
    // 1. Check for manual overrides and custom links
    if ($campus === 'cherngtalay') {
        $override_image = get_option('cherngtalay_megamenu_override');
        $custom_link_id = get_option('cherngtalay_megamenu_link');
        $default_link = '/cherngtalay/campus-hub/';
    } elseif ($campus === 'alumni') {
        $override_image = get_option('alumni_megamenu_image');
        $custom_link_id = get_option('alumni_megamenu_link');
        $default_link = '/alumni-network/';
    } else {
        $override_image = get_option('chaofah_megamenu_override');
        $custom_link_id = get_option('chaofah_megamenu_link');
        $default_link = '/chaofah/campus-hub/';
    }
    
    // Convert Page ID to URL if one was selected
    $custom_link = (!empty($custom_link_id)) ? get_permalink($custom_link_id) : '';
    
    // Determine the final link to use. Prioritize Admin Panel, then Default.
    // We ignore the shortcode url parameter now since the admin panel controls it.
    $final_link = !empty($custom_link) ? $custom_link : $default_link;

    $latest_data = array();

    // 2. Fetch the latest post data (SKIP IF ALUMNI)
    if ($campus !== 'alumni') {
        $transient_key = 'hs_latest_weekly_update_' . $campus;
        $latest_data = get_transient($transient_key);

        if (false === $latest_data) {
            $args = array(
                'post_type'              => 'weekly_update',
                'posts_per_page'         => 1,
                'post_status'            => 'publish',
                'no_found_rows'          => true,  // Optimization: Skips pagination calculation
                'update_post_term_cache' => false, // Optimization: Skips term cache
                'update_post_meta_cache' => false, // Optimization: Skips meta cache
                'fields'                 => 'ids', // Optimization: Only query the ID, not the whole object
                'tax_query'              => array(
                    array(
                        'taxonomy' => 'hs_campus',
                        'field'    => 'slug',
                        'terms'    => $campus,
                    ),
                ),
            );
            $query = new WP_Query($args);

            if ($query->have_posts()) {
                $post_id = $query->posts[0];
                $latest_data = array(
                    'permalink' => get_permalink($post_id),
                    'title'     => get_the_title($post_id),
                    'image_url' => get_the_post_thumbnail_url($post_id, 'large')
                );
                // Cache for 24 hours (but it clears instantly via the save_post hook anyway!)
                set_transient($transient_key, $latest_data, 24 * HOUR_IN_SECONDS);
            } else {
                $latest_data = array();
                set_transient($transient_key, $latest_data, 24 * HOUR_IN_SECONDS);
            }
        }
    } // Close if ($campus !== 'alumni')

    // 3. Build HTML Output
    // If override exists, use it. Otherwise, use the post's featured image.
    $final_image_url = !empty($override_image) ? $override_image : (!empty($latest_data) ? $latest_data['image_url'] : '');
    
    if (empty($final_image_url)) {
        return ''; // No image at all
    }

    // Always use the determined final link (Campus Hub or Custom Link)
    $link_url = $final_link;
    $wrapper_tag = !empty($link_url) ? 'a' : 'div';
    $href = ($wrapper_tag === 'a') ? ' href="' . esc_url($link_url) . '"' : '';
    
    // Safely determine the label text (hardcoded to prevent any whitespace or smart-quote bugs in Divi)
    if ($campus === 'cherngtalay') {
        $overlay_text = 'Cherngtalay Campus Community Hub';
    } elseif ($campus === 'alumni') {
        $overlay_text = 'HeadStart Alumni';
    } else {
        $overlay_text = 'Chaofah City Campus Community Hub';
    }
    
    $title_attr = ' title="' . esc_attr($overlay_text) . '"';
    
    // The parent .zoom-card has overflow:hidden + aspect-ratio:16/9, clipping anything outside its box.
    // So this wrapper must fill .zoom-card absolutely, and image + text must both live inside.
    // However, the mobile menu passes its own Tailwind classes (relative, aspect-video), so we only force absolute if it's the default class.
    if (strpos($atts['css_class'], 'mega-menu-latest-image') !== false) {
        $wrapper_style = 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; text-decoration: none; z-index: 2;';
    } else {
        $wrapper_style = 'display: block; text-decoration: none;';
    }
    
    $html = '<' . $wrapper_tag . $href . ' class="' . esc_attr($atts['css_class']) . '"' . $title_attr . ' style="' . $wrapper_style . '">';
    
    // Image fills the wrapper completely
    $html .= '<img src="' . esc_url($final_image_url) . '" alt="' . esc_attr($overlay_text) . '" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; display: block;" />';
    
    // Text overlay anchored to bottom of the same clipped box
    $overlay_style = 'position: absolute; bottom: 0; left: 0; width: 100%; padding: 40px 15px 15px; background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0) 100%); color: #ffffff; font-weight: 700; font-size: 16px; text-align: left; box-sizing: border-box; font-family: inherit; line-height: 1.2; z-index: 10;';
    $html .= '<div class="mega-menu-image-overlay" style="' . $overlay_style . '">' . esc_html($overlay_text) . '</div>';
    
    $html .= '</' . $wrapper_tag . '>';

    return $html;
}

/* ==========================================================================
 * 5. HELPER FUNCTION: JAVASCRIPT
 * ========================================================================== */
function hs_render_media_uploader_js() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($){
        $('.upload_image_button').click(function(e) {
            e.preventDefault();
            var button = $(this);
            var target_input = $('#' + button.data('target'));
            var preview_img = $('#preview_' + button.data('target'));
            var custom_uploader = wp.media({
                title: 'Select Image',
                button: { text: 'Use this image' },
                multiple: false
            }).on('select', function() {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                target_input.val(attachment.url);
                preview_img.attr('src', attachment.url).show();
            }).open();
        });
        $('.remove_image_button').click(function(e) {
            e.preventDefault();
            var button = $(this);
            var target_input = $('#' + button.data('target'));
            var preview_img = $('#preview_' + button.data('target'));
            target_input.val('');
            preview_img.attr('src', '').hide();
        });
    });
    </script>
    <?php
}
