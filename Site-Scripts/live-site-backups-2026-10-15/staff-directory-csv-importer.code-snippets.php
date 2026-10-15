<?php

/**
 * Staff-Directory--CSV-Importer
 *
 * OWNED BY Nick (firedev). Disabled 2026-09-14 on origin-new rebuild — CSV/Rails import only, not needed for front-end. Re-enable only if Nick/Jeremy need imports.
 * 
 *
 */
/**
 * =============================================================================
 * HEADSTART STAFF DIRECTORY CSV IMPORTER
 * =============================================================================
 * PURPOSE: Logic and execution for STAFF DIRECTORY CSV IMPORTER
 * DEPENDENCIES: WordPress core, Divi
 * [TODO: REVIEW_OPTIMIZATIONS - Extract inline JS/CSS, implement Nonces, escape HTML]
 * =============================================================================
 */

/**
 * Plugin Name: HeadStart Staff Directory Importer
 * Description: A custom, lightning-fast CSV importer built specifically for the HeadStart Staff Directory.
 * Version: 1.3
 * Author: Antigravity
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Add Submenu Page
add_action('admin_menu', 'hs_staff_importer_menu');
function hs_staff_importer_menu() {
    add_submenu_page(
        'edit.php?post_type=staff',
        'Import/Export Staff CSV',
        'Import/Export',
        'manage_options',
        'hs-staff-importer',
        'hs_staff_importer_page'
    );
}

// Enqueue Papaparse for fast CSV parsing and ExcelJS for generation
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'staff_page_hs-staff-importer') {
        wp_enqueue_script('papaparse', 'https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js', array(), '5.4.1', true);
        wp_enqueue_script('exceljs', 'https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js', array(), '4.3.0', true);
    }
});

// Helper: Convert string to Term ID
function hs_get_term_id_by_name($term_name, $taxonomy) {
    if (empty($term_name)) return false;
    
    $term = term_exists($term_name, $taxonomy);
    if ($term !== 0 && $term !== null) {
        return (int) $term['term_id'];
    }
    
    // Check HTML encoded string (WordPress often stores & as &amp;)
    $encoded_name = str_replace('&', '&amp;', $term_name);
    $term = term_exists($encoded_name, $taxonomy);
    if ($term !== 0 && $term !== null) {
        return (int) $term['term_id'];
    }
    
    $new_term = wp_insert_term($term_name, $taxonomy);
    if (!is_wp_error($new_term)) {
        return (int) $new_term['term_id'];
    }
    return false;
}

// Helper: Find column index by partial or exact name match
function hs_get_col_index($header_row, $search_terms) {
    if (!is_array($search_terms)) $search_terms = array($search_terms);
    
    foreach ($header_row as $index => $col_name) {
        foreach ($search_terms as $term) {
            if (strcasecmp(trim($col_name), $term) === 0) {
                return $index;
            }
        }
    }
    
    foreach ($header_row as $index => $col_name) {
        foreach ($search_terms as $term) {
            if (stripos(trim($col_name), $term) !== false) {
                return $index;
            }
        }
    }
    return -1;
}

// AJAX: Wipe Staff
add_action('wp_ajax_hs_wipe_staff', 'hs_ajax_wipe_staff');
function hs_ajax_wipe_staff() {
    check_ajax_referer('hs_import_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    // ponytail: the backup keeps text fields only — no photos (_thumbnail_id on 347 staff), drafts or reports_to —
    // so wipe + revert would unlink every photo. Off until the backup covers them (Nick, 2026-10-13).
    wp_send_json_error('Wipe is disabled: the backup does not keep staff photos yet. Import still updates existing staff by name.');
    
    $skip_backup = isset($_POST['skip_backup']) && $_POST['skip_backup'] == '1';
    
    if (!$skip_backup) {
        $backup_data = hs_get_all_staff_data();
        $backup = array(
            'time' => current_time('mysql'),
            'count' => count($backup_data),
            'data' => $backup_data
        );
        update_option('hs_staff_last_backup', $backup, false);
    }
    
    // OPTIMIZATION: ID-Only Wiping (fields => ids avoids loading massive WP_Post objects)
    $existing_staff = get_posts(array(
        'post_type' => 'staff', 
        'numberposts' => -1, 
        'post_status' => 'any',
        'fields' => 'ids'
    ));
    
    foreach ($existing_staff as $staff_id) {
        wp_delete_post($staff_id, true);
    }
    
    wp_send_json_success(array('count' => count($existing_staff)));
}

// AJAX: Get Last Backup
add_action('wp_ajax_hs_get_backup', 'hs_ajax_get_backup');
function hs_ajax_get_backup() {
    check_ajax_referer('hs_import_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    
    $backup = get_option('hs_staff_last_backup');
    if (!$backup || empty($backup['data'])) {
        wp_send_json_error('No backup found.');
    }
    
    wp_send_json_success($backup);
}

// AJAX: Import Chunk
add_action('wp_ajax_hs_import_chunk', 'hs_ajax_import_chunk');
function hs_ajax_import_chunk() {
    check_ajax_referer('hs_import_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    
    $rows = isset($_POST['rows']) ? wp_unslash($_POST['rows']) : array();
    $headers = isset($_POST['headers']) ? wp_unslash($_POST['headers']) : array();
    
    if (empty($rows) || empty($headers)) {
        wp_send_json_error('No data received');
    }
    
    $idx_name   = hs_get_col_index($headers, array('Name', 'Full Name'));
    $idx_title  = hs_get_col_index($headers, array('Job', 'Title'));
    $idx_campus = hs_get_col_index($headers, 'Campus');
    $idx_dept   = hs_get_col_index($headers, 'Department'); 
    $idx_tier   = hs_get_col_index($headers, array('Tier', 'Role Level'));
    $idx_sort   = hs_get_col_index($headers, array('Sort', 'Priority', 'Group Sort'));
    $idx_group  = hs_get_col_index($headers, array('Group', 'Sub-Group'));
    $idx_sec    = hs_get_col_index($headers, array('Secondary', 'Additional'));
    $idx_lead   = hs_get_col_index($headers, array('Lead', 'Leadership'));
    $idx_blurb  = hs_get_col_index($headers, 'Blurb');
    $idx_photo  = hs_get_col_index($headers, array('Photo', 'Image', 'Filename'));
    
    if ($idx_name === -1) {
        wp_send_json_error('Could not find Name column');
    }
    
    global $wpdb;
    $imported = 0;
    
    foreach ($rows as $data) {
        $name       = ($idx_name !== -1 && isset($data[$idx_name])) ? sanitize_text_field($data[$idx_name]) : '';
        $title      = ($idx_title !== -1 && isset($data[$idx_title])) ? sanitize_text_field($data[$idx_title]) : '';
        $campus     = ($idx_campus !== -1 && isset($data[$idx_campus])) ? sanitize_text_field($data[$idx_campus]) : '';
        $department = ($idx_dept !== -1 && isset($data[$idx_dept])) ? sanitize_text_field($data[$idx_dept]) : '';
        $tier       = ($idx_tier !== -1 && isset($data[$idx_tier])) ? sanitize_text_field($data[$idx_tier]) : '';
        $sort_order = ($idx_sort !== -1 && isset($data[$idx_sort])) ? sanitize_text_field($data[$idx_sort]) : '';
        $group      = ($idx_group !== -1 && isset($data[$idx_group])) ? sanitize_text_field($data[$idx_group]) : '';
        $secondary  = ($idx_sec !== -1 && isset($data[$idx_sec])) ? sanitize_text_field($data[$idx_sec]) : '';
        $lead_sort  = ($idx_lead !== -1 && isset($data[$idx_lead])) ? sanitize_text_field($data[$idx_lead]) : '';
        $blurb      = ($idx_blurb !== -1 && isset($data[$idx_blurb])) ? wp_kses_post($data[$idx_blurb]) : '';
        $photo      = ($idx_photo !== -1 && isset($data[$idx_photo])) ? sanitize_text_field($data[$idx_photo]) : '';

        if (empty($name)) continue;

        // 1. Check if Post Exists (Upsert)
        $existing_post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_type = 'staff' AND post_title = %s AND post_status != 'trash' LIMIT 1",
            $name
        ));

        if ($existing_post_id) {
            $post_id = $existing_post_id;
        } else {
            $post_id = wp_insert_post(array(
                'post_title'  => $name,
                'post_type'   => 'staff',
                'post_status' => 'publish'
            ));
        }

        if ($post_id) {
            $imported++;

            // 2. Save Meta
            if (function_exists('update_field')) {
                if ($title) update_field('job_title', $title, $post_id);
                if ($tier) update_field('staff_tier', $tier, $post_id);
                if ($sort_order) update_field('sort_order', (int)$sort_order, $post_id);
                if ($lead_sort) update_field('leadership_sort_order', (int)$lead_sort, $post_id);
                if ($blurb) update_field('profile_blurb', $blurb, $post_id);
                if ($photo) update_post_meta($post_id, 'staff_photo_filename', $photo); // Keep as native meta since it's a raw filename for later sync
            } else {
                if ($title) update_post_meta($post_id, 'job_title', $title);
                if ($tier) update_post_meta($post_id, 'staff_tier', $tier);
                if ($sort_order) update_post_meta($post_id, 'sort_order', (int)$sort_order);
                if ($lead_sort) update_post_meta($post_id, 'leadership_sort_order', (int)$lead_sort);
                if ($blurb) update_post_meta($post_id, 'profile_blurb', $blurb);
                if ($photo) update_post_meta($post_id, 'staff_photo_filename', $photo);
            }
            
            // Note: In this AJAX version, we defer image matching to the Retroactive Image Sync button entirely to keep import blindingly fast!

            // 4. Save Hierarchical Taxonomies
            if ($campus) {
                $campus_array = array_filter(array_map('trim', explode(',', $campus)));
                $term_ids = array();
                foreach ($campus_array as $c) {
                    $tid = hs_get_term_id_by_name($c, 'staff_campus');
                    if ($tid) $term_ids[] = $tid;
                }
                if (!empty($term_ids)) wp_set_object_terms($post_id, $term_ids, 'staff_campus', false);
            } else {
                wp_set_object_terms($post_id, array(), 'staff_campus');
            }

            if ($department) {
                $dept_array = array_filter(array_map('trim', explode(',', $department)));
                $term_ids = array();
                foreach ($dept_array as $d) {
                    $tid = hs_get_term_id_by_name($d, 'staff_department');
                    if ($tid) $term_ids[] = $tid;
                }
                if (!empty($term_ids)) wp_set_object_terms($post_id, $term_ids, 'staff_department', false);
            } else {
                wp_set_object_terms($post_id, array(), 'staff_department');
            }

            if ($group) {
                $group_array = array_filter(array_map('trim', explode(',', $group)));
                $term_ids = array();
                foreach ($group_array as $g) {
                    $tid = hs_get_term_id_by_name($g, 'staff_display_group');
                    if ($tid) $term_ids[] = $tid;
                }
                if (!empty($term_ids)) wp_set_object_terms($post_id, $term_ids, 'staff_display_group', false);
            } else {
                wp_set_object_terms($post_id, array(), 'staff_display_group');
            }
        }
    }
    
    wp_send_json_success(array('processed' => $imported));
}

function hs_get_all_staff_data() {
    $staff_posts = get_posts(array(
        'post_type' => 'staff',
        'numberposts' => -1,
        'post_status' => 'any'
    ));

    $data = array();
    foreach ($staff_posts as $post) {
        $post_id = $post->ID;
        
        $job_title = get_post_meta($post_id, 'job_title', true);
        $tier = get_post_meta($post_id, 'staff_tier', true);
        $sort_order = get_post_meta($post_id, 'sort_order', true);
        $lead_sort = get_post_meta($post_id, 'leadership_sort_order', true);
        $blurb = get_post_meta($post_id, 'profile_blurb', true);
        $photo = get_post_meta($post_id, 'staff_photo_filename', true);
        
        $campus_terms = wp_get_object_terms($post_id, 'staff_campus', array('fields' => 'names'));
        $campus = !is_wp_error($campus_terms) && !empty($campus_terms) ? implode(', ', $campus_terms) : '';
        
        $dept_terms = wp_get_object_terms($post_id, 'staff_department', array('fields' => 'names'));
        $department = !is_wp_error($dept_terms) && !empty($dept_terms) ? implode(', ', $dept_terms) : '';
        
        $group_terms = wp_get_object_terms($post_id, 'staff_display_group', array('fields' => 'names'));
        $group = !is_wp_error($group_terms) && !empty($group_terms) ? implode(', ', $group_terms) : '';

        $data[] = array(
            $post->post_title,
            $job_title,
            $campus,
            $department,
            $tier,
            $sort_order,
            $group,
            '', // Additional Departments
            $lead_sort,
            $blurb,
            $photo
        );
    }
    return $data;
}

// AJAX: Export Full Directory
add_action('wp_ajax_hs_export_staff', 'hs_ajax_export_staff');
function hs_ajax_export_staff() {
    check_ajax_referer('hs_import_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    wp_send_json_success(array('staff' => hs_get_all_staff_data()));
}

// 2. Render Page
function hs_staff_importer_page() {
    if (!current_user_can('manage_options')) return;

    global $wpdb;

    // Fetch terms for dropdowns
    $campuses = get_terms(array('taxonomy' => 'staff_campus', 'hide_empty' => false, 'fields' => 'names'));
    $departments = get_terms(array('taxonomy' => 'staff_department', 'hide_empty' => false, 'fields' => 'names'));
    $groups = get_terms(array('taxonomy' => 'staff_display_group', 'hide_empty' => false, 'fields' => 'names'));
    
    // Fetch unique Role Tiers from postmeta
    $tiers = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'staff_tier' AND meta_value != ''");
    
    // Filter out invalid/legacy values and sort naturally
    if (is_array($tiers)) {
        $tiers = array_filter($tiers, function($t) {
            // Must contain at least one number to be a valid tier
            return preg_match('/\d/', $t);
        });
        natsort($tiers);
    }
    
    $campuses = is_wp_error($campuses) ? array() : array_map('html_entity_decode', array_values($campuses));
    $departments = is_wp_error($departments) ? array() : array_map('html_entity_decode', array_values($departments));
    $groups = is_wp_error($groups) ? array() : array_map('html_entity_decode', array_values($groups));
    $tiers = is_array($tiers) ? array_map('html_entity_decode', array_values($tiers)) : array();
    ?>
    <script>
        window.hsStaffTerms = {
            campuses: <?php echo json_encode($campuses); ?>,
            departments: <?php echo json_encode($departments); ?>,
            tiers: <?php echo json_encode($tiers); ?>,
            groups: <?php echo json_encode($groups); ?>
        };
    </script>
    <div class="wrap">
        <h1 style="margin-bottom: 20px;">Staff Directory CSV Importer v1.3</h1>
        
        <div id="hs-import-messages"></div>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 800px;">
            <p><strong>Instructions:</strong></p>
            <ol>
                <li>Open your master Staff Data Excel file.</li>
                <li>Go to <strong>File -> Save As</strong> and choose <strong>CSV (Comma Delimited)</strong>.</li>
                <li>Upload that `.csv` file here.</li>
                <li>The script will intelligently read your column headers (no matter what order they are in!) and map the data perfectly.</li>
            </ol>
            
            <hr style="margin: 20px 0;">
            
            <div style="margin-bottom: 30px;">
                <p style="font-weight: bold; margin-bottom: 10px;">Need the exact format?</p>
                <button type="button" id="hs-download-excel-btn" class="button button-secondary">Download Empty XLS Template</button>
                <button type="button" id="hs-download-full-directory-btn" class="button button-primary" style="margin-left: 10px;">Download Full Directory (XLS)</button>
            </div>
            
            <form id="hs-import-form">
                <?php wp_nonce_field('hs_import_nonce', 'hs_import_nonce'); ?>
                
                <div style="margin-bottom: 20px;">
                    <label for="staff_csv" style="display: block; font-weight: bold; margin-bottom: 5px;">Upload CSV File:</label>
                    <input type="file" id="staff_csv" accept=".csv" required>
                </div>
                
                <div style="margin-bottom: 20px; padding: 10px; background: #fff5f5; border-left: 4px solid #dc3232;">
                    <label style="font-weight: bold; color: #dc3232;">
                        <input type="checkbox" id="delete_existing" value="yes"> 
                        DANGER: Delete test batches and upload full amended directory?
                    </label>
                    <p style="margin-top: 5px; margin-bottom: 0; font-size: 13px; color: #666;">Check this box if you want to cleanly wipe the database (e.g. deleting test batches and uploaded full amended directory) before loading this new file.</p>
                </div>
                
                <button type="submit" id="hs-run-import-btn" class="button button-primary button-large">Run Import Now</button>
            </form>
            
            <div id="hs-progress-wrap" style="display:none; margin-top: 20px;">
                <p id="hs-progress-text" style="font-weight:bold; margin-bottom: 5px;">Processing: 0%</p>
                <div style="width: 100%; background: #e2e8f0; height: 20px; border-radius: 10px; overflow: hidden;">
                    <div id="hs-progress-bar" style="width: 0%; height: 100%; background: #2271b1; transition: width 0.3s;"></div>
                </div>
            </div>
            
            <hr style="margin: 30px 0;">
            <div style="padding: 15px; background: #f0f0f1; border-left: 4px solid #f56e28;">
                <h3 style="margin-top: 0;">Emergency Revert</h3>
                <?php $backup = get_option('hs_staff_last_backup'); ?>
                <?php if ($backup && !empty($backup['count'])): ?>
                    <p>A backup of <strong><?php echo intval($backup['count']); ?></strong> staff members was automatically saved on <em><?php echo esc_html($backup['time']); ?></em> before a deletion.</p>
                    <button type="button" id="hs-revert-btn" class="button button-secondary">Revert to this Backup</button>
                <?php else: ?>
                    <p>No automatic backups found. A backup is automatically created when you check "DANGER: Delete test batches" during an import.</p>
                    <button type="button" class="button button-secondary" disabled>Revert to Backup</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        
        function applyHsHeaders(ws) {
            var headers = [
                "Full Name (inc Nickname)", "Job Title", "Campus", "Department",
                "Role Tier", "Sort Order (Priority)", "Display Group (Chip Label)",
                "Additional Departments (Comma separated)", "Lead Sort (Leadership)",
                "Profile Blurb", "Photo Filename"
            ];
            ws.addRow(headers);
            
            ws.getCell('A1').note = "The full name of the staff member.";
            ws.getCell('B1').note = "Their main job title, e.g. 'Class Teacher'.";
            ws.getCell('C1').note = "Primary campus (Chaofah, Cherngtalay, or Both Campuses).";
            ws.getCell('D1').note = "The primary department they belong to. You can type a new one and it will be auto-created.";
            ws.getCell('E1').note = "Determines where they appear in the hierarchy.";
            ws.getCell('F1').note = "Order within their display group (1 goes first).";
            ws.getCell('G1').note = "Smaller category labels within a department (e.g. 'Year 1'). You can type a new one and it will be auto-created.";
            ws.getCell('H1').note = "Any extra departments they belong to.";
            ws.getCell('I1').note = "For Leadership Team sorting only. Overrides normal order.";
            ws.getCell('J1').note = "Profile biography text (can contain HTML).";
            ws.getCell('K1').note = "The exact filename of their uploaded photo, e.g. 'john-smith.jpg'.";
            
            ws.getRow(1).font = { bold: true };
        }

        function applyHsValidation(ws, terms, rowCount) {
            if (terms.campuses && terms.campuses.length) terms.campuses.forEach(function(c, i) { ws.getCell('Z' + (i+1)).value = c; });
            if (terms.departments && terms.departments.length) terms.departments.forEach(function(d, i) { ws.getCell('AA' + (i+1)).value = d; });
            if (terms.tiers && terms.tiers.length) terms.tiers.forEach(function(t, i) { ws.getCell('AB' + (i+1)).value = t; });
            if (terms.groups && terms.groups.length) terms.groups.forEach(function(g, i) { ws.getCell('AC' + (i+1)).value = g; });

            ws.getColumn('Z').hidden = true;
            ws.getColumn('AA').hidden = true;
            ws.getColumn('AB').hidden = true;
            ws.getColumn('AC').hidden = true;

            var warnConfig = {
                showErrorMessage: true,
                errorStyle: 'warning',
                errorTitle: 'New Value Detected',
                error: 'This value is not currently in the system dropdowns. If you save the CSV like this, the importer will automatically create it for you! Click Yes to continue.'
            };

            for (let i = 2; i <= rowCount; i++) {
                if (terms.campuses && terms.campuses.length) ws.getCell('C' + i).dataValidation = Object.assign({ type: 'list', allowBlank: true, formulae: [`'SAVE AS CSV'!$Z$1:$Z$${terms.campuses.length}`] }, warnConfig);
                if (terms.departments && terms.departments.length) ws.getCell('D' + i).dataValidation = Object.assign({ type: 'list', allowBlank: true, formulae: [`'SAVE AS CSV'!$AA$1:$AA$${terms.departments.length}`] }, warnConfig);
                if (terms.tiers && terms.tiers.length) ws.getCell('E' + i).dataValidation = Object.assign({ type: 'list', allowBlank: true, formulae: [`'SAVE AS CSV'!$AB$1:$AB$${terms.tiers.length}`] }, warnConfig);
                if (terms.groups && terms.groups.length) ws.getCell('G' + i).dataValidation = Object.assign({ type: 'list', allowBlank: true, formulae: [`'SAVE AS CSV'!$AC$1:$AC$${terms.groups.length}`] }, warnConfig);
            }
        }

        $('#hs-download-excel-btn').on('click', async function(e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.prop('disabled', true).text('Generating...');

            try {
                var wb = new ExcelJS.Workbook();
                var ws = wb.addWorksheet('SAVE AS CSV');

                applyHsHeaders(ws);
                applyHsValidation(ws, window.hsStaffTerms, 1000);

                const buffer = await wb.xlsx.writeBuffer();
                const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'Staff_Import_Template.xlsx';
                a.click();
                window.URL.revokeObjectURL(url);
                
            } catch(err) {
                console.error(err);
                alert("Error generating Excel template.");
            }
            
            $btn.prop('disabled', false).text('Download Empty XLS Template');
        });

        $('#hs-download-full-directory-btn').on('click', async function(e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.prop('disabled', true).text('Exporting Data...');
            $('#hs-import-messages').empty();
            
            $.post(ajaxurl, {
                action: 'hs_export_staff',
                nonce: $('#hs_import_nonce').val()
            }, async function(res) {
                if (res.success) {
                    try {
                        var wb = new ExcelJS.Workbook();
                        var ws = wb.addWorksheet('SAVE AS CSV');

                        applyHsHeaders(ws);

                        // Add data rows FIRST so addRow starts at row 2
                        if (res.data.staff && res.data.staff.length > 0) {
                            res.data.staff.forEach(function(staffRow) {
                                ws.addRow(staffRow);
                            });
                        }

                        // Apply data validation to rows 2-MAX (ensure enough rows even for big directories)
                        var maxRow = Math.max(1000, (res.data.staff ? res.data.staff.length : 0) + 100);
                        applyHsValidation(ws, window.hsStaffTerms, maxRow);

                        const buffer = await wb.xlsx.writeBuffer();
                        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'Staff_Directory_Export.xlsx';
                        a.click();
                        window.URL.revokeObjectURL(url);

                    } catch(err) {
                        console.error(err);
                        alert("Error generating Excel export.");
                    }
                } else {
                    alert("Failed to fetch staff data.");
                }
                $btn.prop('disabled', false).text('Download Full Directory (XLS)');
            });
        });

        function showMessage(msg, type = 'success') {
            $('#hs-import-messages').append('<div class="notice notice-' + type + '"><p>' + msg + '</p></div>');
        }

        
        $('#hs-import-form').on('submit', function(e) {
            e.preventDefault();
            
            var fileInput = document.getElementById('staff_csv');
            if (fileInput.files.length === 0) {
                alert('Please select a file.');
                return;
            }
            
            var $btn = $('#hs-run-import-btn');
            var nonce = $('#hs_import_nonce').val();
            var doWipe = $('#delete_existing').is(':checked');
            
            $btn.prop('disabled', true).text('Reading CSV...');
            $('#hs-import-messages').empty();
            
            // 1. Parse CSV
            Papa.parse(fileInput.files[0], {
                skipEmptyLines: true,
                complete: function(results) {
                    // PapaParse throws TooFewFields/TooManyFields on Excel CSVs. We can safely ignore them.
                    var fatalErrors = results.errors.filter(function(e) { 
                        return e.code !== 'TooFewFields' && e.code !== 'TooManyFields'; 
                    });
                    
                    if (fatalErrors.length > 0) {
                        showMessage('Error parsing CSV file: ' + fatalErrors[0].message, 'error');
                        $btn.prop('disabled', false).text('Run Import Now');
                        return;
                    }
                    
                    var data = results.data;
                    if (data.length < 2) {
                        showMessage('CSV file is empty or only has headers.', 'error');
                        $btn.prop('disabled', false).text('Run Import Now');
                        return;
                    }
                    
                    var headers = data[0];
                    var rows = data.slice(1);
                    
                    // 2. Wipe if requested
                    if (doWipe) {
                        $btn.text('Wiping Database...');
                        $.post(ajaxurl, {
                            action: 'hs_wipe_staff',
                            nonce: nonce
                        }, function(res) {
                            if (res.success) {
                                showMessage('<strong>Success:</strong> Wiped ' + res.data.count + ' existing staff members.', 'warning');
                                startBatchImport(headers, rows, $btn, nonce);
                            } else {
                                showMessage('Error wiping database.', 'error');
                                $btn.prop('disabled', false).text('Run Import Now');
                            }
                        });
                    } else {
                        startBatchImport(headers, rows, $btn, nonce);
                    }
                }
            });
        });

        function startBatchImport(headers, rows, $btn, nonce, callback = null) {
            $('#hs-progress-wrap').show();
            var batchSize = 50;
            var totalRows = rows.length;
            var currentProcessed = 0;
            
            function processNextBatch(startIndex) {
                var chunk = rows.slice(startIndex, startIndex + batchSize);
                if (chunk.length === 0) {
                    // Finished
                    $btn.prop('disabled', false).text('Run Import Now');
                    $('#hs-progress-text').text('Processing: 100%');
                    $('#hs-progress-bar').css('width', '100%');
                    showMessage('<strong>Success:</strong> Completely imported ' + totalRows + ' staff members in batches!');
                    if (callback) callback();
                    return;
                }
                
                $btn.text('Importing Batch (' + startIndex + ' to ' + (startIndex + chunk.length) + ')...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hs_import_chunk',
                        nonce: nonce,
                        headers: headers,
                        rows: chunk
                    },
                    success: function(res) {
                        if (res.success) {
                            currentProcessed += chunk.length;
                            var percent = Math.round((currentProcessed / totalRows) * 100);
                            $('#hs-progress-text').text('Processing: ' + percent + '% (' + currentProcessed + ' of ' + totalRows + ')');
                            $('#hs-progress-bar').css('width', percent + '%');
                            
                            // Next batch
                            processNextBatch(startIndex + batchSize);
                        } else {
                            showMessage('Error importing chunk: ' + (res.data || 'Unknown error'), 'error');
                            $btn.prop('disabled', false).text('Run Import Now');
                        }
                    },
                    error: function() {
                        showMessage('Server error while importing chunk.', 'error');
                        $btn.prop('disabled', false).text('Run Import Now');
                    }
                });
            }
            
            processNextBatch(0);
        }

        $('#hs-revert-btn').on('click', function(e) {
            e.preventDefault();
            if (!confirm("Are you sure? This will completely wipe the current directory and restore it from the backup.")) return;
            
            var $btn = $(this);
            var nonce = $('#hs_import_nonce').val();
            
            $btn.prop('disabled', true).text('Fetching Backup...');
            $('#hs-import-messages').empty();
            $('#hs-progress-wrap').hide();
            
            $.post(ajaxurl, { action: 'hs_get_backup', nonce: nonce }, function(res) {
                if (!res.success) {
                    showMessage(res.data || 'Failed to fetch backup.', 'error');
                    $btn.prop('disabled', false).text('Revert to this Backup');
                    return;
                }
                
                var backupData = res.data.data;
                var headers = [
                    "Full Name (inc Nickname)", "Job Title", "Campus", "Department",
                    "Role Tier", "Sort Order (Priority)", "Display Group (Chip Label)",
                    "Additional Departments (Comma separated)", "Lead Sort (Leadership)",
                    "Profile Blurb", "Photo Filename"
                ];
                
                $btn.text('Wiping Database...');
                $.post(ajaxurl, { action: 'hs_wipe_staff', nonce: nonce, skip_backup: 1 }, function(wipeRes) {
                    if (wipeRes.success) {
                        showMessage('<strong>Success:</strong> Wiped current directory for revert.', 'warning');
                        $('#hs-run-import-btn').prop('disabled', true);
                        startBatchImport(headers, backupData, $btn, nonce, function() {
                            $btn.prop('disabled', false).text('Revert Complete');
                            setTimeout(function() { location.reload(); }, 2000);
                        });
                    } else {
                        showMessage('Error wiping database for revert.', 'error');
                        $btn.prop('disabled', false).text('Revert to this Backup');
                    }
                });
            });
        });
    });
    </script>
    <?php
}
