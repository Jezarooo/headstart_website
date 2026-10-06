<?php

/**
 * [Nick/off] Events-Calendar--CSV-Importer
 *
 * OWNED BY Nick (firedev). Disabled 2026-09-14 on origin-new rebuild — CSV/Rails import only, not needed for front-end. Re-enable only if Nick/Jeremy need imports.
 * 
 *
 */
/**
 * HeadStart Events Calendar - CSV Importer
 * 
 * Description: 
 * Adds a submenu under the Events post type to upload the parsed CSV file.
 * Safely inserts or updates events based on a unique hash (Title + Start Date).
 */

// 1. Register the Submenu Page
add_action('admin_menu', 'hs_v3_register_csv_importer_page');
function hs_v3_register_csv_importer_page() {
    $slug = function_exists('hs_v3_get_exact_event_slug') ? hs_v3_get_exact_event_slug() : 'events';
    
    add_submenu_page(
        'edit.php?post_type=' . $slug, // Parent slug
        'Import Calendar CSV',         // Page title
        'Import CSV',                  // Menu title
        'manage_options',              // Capability required
        'hs-calendar-importer',        // Menu slug
        'hs_v3_csv_importer_page_html'    // Callback function
    );
}

// 2. The Admin Page UI & Logic
function hs_v3_csv_importer_page_html() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $slug = function_exists('hs_v3_get_exact_event_slug') ? hs_v3_get_exact_event_slug() : 'events';

    // Handle Form Submission
    if (isset($_POST['hs_import_csv_nonce']) && wp_verify_nonce($_POST['hs_import_csv_nonce'], 'hs_import_csv')) {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, "r");
            
            if ($handle !== FALSE) {
                $headers = fgetcsv($handle, 1000, ","); // First row is headers
                
                // Clean headers to avoid BOM or spacing issues
                $headers = array_map('trim', $headers);
                // Strip hidden BOM characters if present in the first header
                $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);
                
                $created_count = 0;
                $updated_count = 0;
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Map CSV row to associative array based on headers
                    if (count($headers) !== count($data)) {
                        continue; // Skip malformed rows
                    }
                    $row = array_combine($headers, $data);
                    
                    if (!$row || empty($row['event_title']) || empty($row['event_start_date'])) {
                        continue; // Skip invalid rows
                    }
                    
                    $title = sanitize_text_field($row['event_title']);
                    $start_date = sanitize_text_field($row['event_start_date']);
                    
                    // Generate unique ID to prevent duplicates (must include campus so HSC and HSN don't overwrite each other)
                    $campus = isset($row['target_campus']) ? sanitize_text_field($row['target_campus']) : '';
                    $hash = md5($title . $start_date . $campus);
                    
                    // Check if post already exists
                    $existing_posts = get_posts(array(
                        'post_type' => $slug,
                        'post_status' => 'any',
                        'meta_key' => '_hs_import_hash',
                        'meta_value' => $hash,
                        'posts_per_page' => 1
                    ));
                    
                    $post_id = 0;
                    
                    if (!empty($existing_posts)) {
                        $post_id = $existing_posts[0]->ID;
                        // Update existing post title in case it changed slightly
                        wp_update_post(array(
                            'ID' => $post_id,
                            'post_title' => $title
                        ));
                        $updated_count++;
                    } else {
                        // Create new post
                        $post_id = wp_insert_post(array(
                            'post_title' => $title,
                            'post_type' => $slug,
                            'post_status' => 'publish'
                        ));
                        $created_count++;
                    }
                    
                    if ($post_id) {
                        // Update ACF Fields (using update_post_meta as a safe fallback that works with get_field)
                        update_post_meta($post_id, '_hs_import_hash', $hash);
                        update_post_meta($post_id, 'event_start_date', $start_date);
                        update_post_meta($post_id, 'event_end_date', sanitize_text_field($row['event_end_date']));
                        
                        // ACF true/false fields store as 1 or 0
                        $is_all_day = ($row['is_all_day'] == '1') ? 1 : 0;
                        update_post_meta($post_id, 'is_all_day', $is_all_day);
                        
                        update_post_meta($post_id, 'event_start_time', sanitize_text_field($row['event_start_time']));
                        update_post_meta($post_id, 'event_end_time', sanitize_text_field($row['event_end_time']));
                        update_post_meta($post_id, 'event_category', sanitize_text_field($row['event_category']));
                        update_post_meta($post_id, 'event_location', sanitize_text_field($row['event_location']));
                        
                        // Textarea needs specialized sanitization to keep linebreaks
                        update_post_meta($post_id, 'event_blurb', sanitize_textarea_field($row['event_blurb']));
                        
                        update_post_meta($post_id, 'registration_type', sanitize_text_field($row['registration_type']));
                        update_post_meta($post_id, 'target_campus', sanitize_text_field($row['target_campus']));
                    }
                }
                fclose($handle);
                
                echo '<div class="notice notice-success is-dismissible"><p><strong>Success:</strong> Imported ' . $created_count . ' new events and updated ' . $updated_count . ' existing events.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Please select a CSV file to upload.</p></div>';
        }
    }
    
    // UI Form
    ?>
    <div class="wrap">
        <h1>Import Calendar CSV</h1>
        <p>Upload the <code>import_ready_calendar.csv</code> file generated by the Python scraper to bulk import or update events.</p>
        
        <div class="card" style="max-width: 600px; margin-top: 20px; padding: 20px;">
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('hs_import_csv', 'hs_import_csv_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="csv_file">Select CSV File</label></th>
                            <td>
                                <input name="csv_file" type="file" id="csv_file" accept=".csv" required>
                                <p class="description">Only accepts .csv files.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Run Import">
                </p>
            </form>
        </div>
        
        <div style="margin-top: 30px;">
            <h3>How it works</h3>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li>The importer uses the Event Title and Start Date to check for duplicates.</li>
                <li>If you upload the same CSV twice, it will simply <strong>update</strong> the existing events rather than creating doubles.</li>
                <li>Ensure the CSV headers strictly match the ACF field names (the Python script handles this automatically).</li>
            </ul>
        </div>
    </div>
    <?php
}
