<?php

/**
 * Weekly-Update--Admin-Columns
 */
/**
 * ====================================================================
 * WEEKLY UPDATE LIST CUSTOMIZER
 * Replaces native columns with an interactive AJAX UI,
 * allowing editors to change Campus and Week Number inline.
 * ====================================================================
 */

// 1. Unhook Native Columns & Register Custom Ones
add_filter('manage_weekly_update_posts_columns', 'hs_weekly_update_columns', 99);
function hs_weekly_update_columns($columns) {
    // Hide native taxonomy columns if they are showing
    unset($columns['taxonomy-hs_campus']);
    unset($columns['taxonomy-in_this_issue']);
    unset($columns['comments']); // Remove comments column

    // Helper for Tooltips
    $tt = function($text) {
        return '<span class="hs-custom-tooltip" style="position:relative; display:inline-block; margin-left:2px;">
            <span class="dashicons dashicons-editor-help" style="color:#888; font-size:15px; vertical-align:middle; cursor:help;"></span>
            <span class="hs-tooltip-text">' . esc_html($text) . '</span>
        </span>';
    };

    // Inject our custom interactive columns
    $new_columns = array();
    foreach ($columns as $key => $title) {
        if ($key === 'date') {
            $new_columns['hs_blurb_col'] = 'Blurb ' . $tt('The post blurb for this update.');
            $new_columns['hs_campus_col'] = 'Campus ' . $tt('Select the campus this weekly update is associated with.');
            $new_columns['hs_week_col'] = 'Week Number ' . $tt('The week number for this update.');
            $new_columns['hs_tags_col'] = 'In This Issue ' . $tt('Select the tags associated with this update.');
        }
        $new_columns[$key] = $title;
    }
    return $new_columns;
}

// 2. Output Column Content
add_action('manage_weekly_update_posts_custom_column', 'hs_weekly_update_column_content', 10, 2);
function hs_weekly_update_column_content($column_name, $post_id) {
    // BLURB (Meta Field)
    if ($column_name === 'hs_blurb_col') {
        $blurb = get_field('hs_weekly_update_blurb', $post_id);
        
        echo '<div class="hs-blurb-wrapper" style="position:relative; width: 100%; min-width: 150px;">';
        
        echo '<div class="hs-blurb-display" style="margin-bottom:6px; font-size:12px; color:#555; max-height:4.5em; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical;">';
        if (!empty($blurb)) {
            echo wp_kses_post($blurb);
        } else {
            echo '<span style="color:#999; font-style:italic;">No blurb...</span>';
        }
        echo '</div>';
        
        echo '<a href="#" class="button button-small hs-edit-blurb-btn">Edit</a>';
        
        echo '<div class="hs-blurb-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:300px; margin-top:5px; border-radius:4px;">';
        echo '<textarea class="hs-blurb-input" style="width:100%; height:100px; margin-bottom:10px; font-size:12px; font-family:monospace;">' . esc_textarea($blurb) . '</textarea>';
        echo '<button type="button" class="button button-primary hs-save-blurb-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
        echo '<button type="button" class="button hs-cancel-blurb-btn">Cancel</button>';
        echo '<span class="hs-blurb-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
        echo '</div>';
        
        echo '</div>';
    }

    // CAMPUS Dropdown (Single-Select Radio)
    if ($column_name === 'hs_campus_col') {
        $campus_terms = get_the_terms($post_id, 'hs_campus');
        $current_campus_id = ($campus_terms && !is_wp_error($campus_terms)) ? $campus_terms[0]->term_id : 0;
        
        echo '<div class="hs-campus-wrapper" style="position:relative;">';
        
        // Display Current Campus
        echo '<div class="hs-campus-display" style="margin-bottom:6px;">';
        if ($campus_terms && !is_wp_error($campus_terms)) {
            $term = $campus_terms[0];
            $bg = '#e5f0e9'; $border = '#b2d6c1'; $text = '#004d22'; // Default (Green)
            if (stripos($term->name, 'Chaofah') !== false) {
                $bg = '#e5f0fa'; $border = '#b2d6f2'; $text = '#00438a'; // Blue
            } else if (stripos($term->name, 'Cherngtalay') !== false) {
                $bg = '#fdf2e8'; $border = '#f5d5b8'; $text = '#a65d14'; // Orange
            }
            echo '<span style="background: ' . $bg . '; border: 1px solid ' . $border . '; color: ' . $text . '; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600;">' . esc_html($term->name) . '</span>';
        } else {
            echo '<span style="color:#d63638; font-size:11px;">Required</span>';
        }
        echo '</div>';
        
        // Edit Button
        echo '<a href="#" class="button button-small hs-edit-campus-btn">Edit</a>';
        
        // Dropdown Editor (Hidden)
        echo '<div class="hs-campus-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:220px; margin-top:5px; border-radius:4px;">';
        
        $all_campuses = get_terms(array('taxonomy' => 'hs_campus', 'hide_empty' => false));
        echo '<div style="max-height:150px; overflow-y:auto; margin-bottom:10px; border:1px solid #eee; padding:5px;">';
        if (!is_wp_error($all_campuses) && !empty($all_campuses)) {
            foreach ($all_campuses as $term) {
                $checked = ($term->term_id === $current_campus_id) ? 'checked' : '';
                echo '<label style="display:block; margin-bottom:4px; font-size:12px;">';
                echo '<input type="radio" name="hs_campus_radio_' . intval($post_id) . '" class="hs-campus-radio" value="' . esc_attr($term->term_id) . '" ' . $checked . ' /> ';
                echo esc_html($term->name);
                echo '</label>';
            }
        }
        echo '</div>';
        
        echo '<button type="button" class="button button-primary hs-save-campus-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
        echo '<button type="button" class="button hs-cancel-campus-btn">Cancel</button>';
        echo '<span class="hs-campus-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
        
        echo '</div>'; // End dropdown
        echo '</div>'; // End wrapper
    }

    // WEEK NUMBER (Meta Field)
    if ($column_name === 'hs_week_col') {
        $week_number = get_field('hs_week_number', $post_id);
        
        echo '<div class="hs-week-wrapper" style="position:relative;">';
        
        echo '<div class="hs-week-display" style="margin-bottom:6px;">';
        if (!empty($week_number)) {
            echo '<span style="font-weight:600; font-size:12px; color:#003057;">' . esc_html($week_number) . '</span>';
        } else {
            echo '<span style="color:#d63638; font-size:11px;">Required</span>';
        }
        echo '</div>';
        
        echo '<a href="#" class="button button-small hs-edit-week-btn">Edit</a>';
        
        echo '<div class="hs-week-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:150px; margin-top:5px; border-radius:4px;">';
        echo '<input type="number" class="hs-week-input" value="' . esc_attr($week_number) . '" style="width:100%; margin-bottom:10px;" />';
        echo '<button type="button" class="button button-primary hs-save-week-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
        echo '<button type="button" class="button hs-cancel-week-btn">Cancel</button>';
        echo '<span class="hs-week-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
        
        echo '</div>';
        echo '</div>';
    }
    // IN THIS ISSUE TAGS (Multi-Select Checkboxes)
    if ($column_name === 'hs_tags_col') {
        $tags = get_the_terms($post_id, 'in_this_issue');
        $current_tag_ids = array();
        
        echo '<div class="hs-tags-wrapper" style="position:relative;">';
        
        echo '<div class="hs-tags-display" style="margin-bottom:6px;">';
        if ($tags && !is_wp_error($tags)) {
            foreach ($tags as $t) {
                $current_tag_ids[] = $t->term_id;
                echo '<span style="background: #f0f0f1; border: 1px solid #dcc; color: #3c434a; font-size: 11px; padding: 2px 6px; border-radius: 4px; font-weight: 500; display:inline-block; margin-bottom:2px; margin-right:2px;">' . esc_html($t->name) . '</span> ';
            }
        } else {
            echo '<span style="color:#999; font-size:11px;">None</span>';
        }
        echo '</div>';
        
        echo '<a href="#" class="button button-small hs-edit-tags-btn">Edit</a>';
        
        echo '<div class="hs-tags-editor" style="display:none; position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; box-shadow:0 2px 5px rgba(0,0,0,0.2); padding:10px; width:220px; margin-top:5px; border-radius:4px;">';
        
        $all_tags = get_terms(array('taxonomy' => 'in_this_issue', 'hide_empty' => false));
        echo '<div style="max-height:150px; overflow-y:auto; margin-bottom:10px; border:1px solid #eee; padding:5px;">';
        if (!is_wp_error($all_tags) && !empty($all_tags)) {
            foreach ($all_tags as $term) {
                $checked = in_array($term->term_id, $current_tag_ids) ? 'checked' : '';
                echo '<label style="display:block; margin-bottom:4px; font-size:12px;">';
                echo '<input type="checkbox" class="hs-tags-checkbox" value="' . esc_attr($term->term_id) . '" ' . $checked . ' /> ';
                echo esc_html($term->name);
                echo '</label>';
            }
        }
        echo '</div>';
        
        // Input for adding a new custom tag
        echo '<div style="margin-bottom:8px;">';
        echo '<input type="text" class="hs-new-tag-input" placeholder="Or type a new tag..." style="width:100%; font-size:12px; padding:2px 6px;" />';
        echo '</div>';
        
        echo '<button type="button" class="button button-primary hs-save-tags-btn" data-post-id="' . intval($post_id) . '">Save</button> ';
        echo '<button type="button" class="button hs-cancel-tags-btn">Cancel</button>';
        echo '<span class="hs-tags-ajax-status" style="margin-left:5px; font-weight:bold; font-size:12px;"></span>';
        
        echo '</div>';
        echo '</div>';
    }
}

// 3. Register Filter Dropdowns at the top of the list
add_action('restrict_manage_posts', 'hs_add_weekly_update_filters', 10, 2);
function hs_add_weekly_update_filters($post_type, $which) {
    if ($post_type !== 'weekly_update') return;

    $campus_tax = get_taxonomy('hs_campus');
    if ($campus_tax) {
        wp_dropdown_categories(array(
            'show_option_all' => $campus_tax->labels->all_items,
            'taxonomy'        => 'hs_campus',
            'name'            => 'hs_campus',
            'orderby'         => 'name',
            'selected'        => isset($_GET['hs_campus']) ? $_GET['hs_campus'] : '',
            'show_count'      => true,
            'hide_empty'      => false,
            'value_field'     => 'slug',
        ));
    }
}

// 4. Inject Javascript for AJAX calls
add_action('admin_footer-edit.php', 'hs_inject_weekly_update_tax_js');
function hs_inject_weekly_update_tax_js() {
    global $post_type;
    if ($post_type !== 'weekly_update') return;
    ?>
    <style>
    .hs-custom-tooltip .hs-tooltip-text { display: none !important; }
    .hs-global-tooltip {
        position: absolute; background: #1e1e1e; color: #fff; padding: 6px 10px;
        border-radius: 4px; font-size: 12px; font-weight: 500; white-space: normal;
        width: max-content; max-width: 220px; text-align: center;
        z-index: 999999999 !important; box-shadow: 0 3px 8px rgba(0,0,0,0.4);
        pointer-events: none; line-height: 1.3;
    }
    .hs-global-tooltip::after {
        content: ""; position: absolute; top: 100%; left: 50%; margin-left: -5px;
        border-width: 5px; border-style: solid; border-color: #1e1e1e transparent transparent transparent;
    }
    </style>
    <script>
    jQuery(document).ready(function($) {
        // Global Tooltips
        $('.hs-custom-tooltip').on('mouseenter', function() {
            var $this = $(this);
            var text = $this.find('.hs-tooltip-text').html();
            var offset = $this.offset();
            var $tt = $('<div class="hs-global-tooltip"></div>').html(text).appendTo('body');
            $tt.css({
                top: offset.top - $tt.outerHeight() - 10,
                left: offset.left + ($this.outerWidth() / 2) - ($tt.outerWidth() / 2)
            });
        }).on('mouseleave', function() {
            $('.hs-global-tooltip').remove();
        });
        
        // Helper to close all popovers
        function closeAllEditors() {
            $('.hs-campus-editor, .hs-week-editor, .hs-tags-editor, .hs-blurb-editor').hide();
        }

        // Blurb UI
        $('.hs-edit-blurb-btn').on('click', function(e) {
            e.preventDefault();
            closeAllEditors();
            $(this).siblings('.hs-blurb-editor').show();
        });

        $('.hs-cancel-blurb-btn').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.hs-blurb-editor').hide();
        });

        $('.hs-save-blurb-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest('.hs-blurb-editor');
            var $status = $editor.find('.hs-blurb-ajax-status');
            var post_id = $btn.data('post-id');
            var val = $editor.find('.hs-blurb-input').val();

            $btn.prop('disabled', true);
            $status.text('Saving...').css('color', '#888');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hs_inline_meta_update',
                    security: '<?php echo wp_create_nonce("hs_inline_tax_nonce"); ?>',
                    post_id: post_id,
                    meta_key: 'hs_weekly_update_blurb',
                    meta_value: val
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('Saved!').css('color', '#00a32a');
                        setTimeout(function(){ window.location.reload(); }, 500);
                    } else {
                        $status.text('Failed').css('color', '#d63638');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    $status.text('Error').css('color', '#d63638');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Single-Select Campus Dropdown UI
        $('.hs-edit-campus-btn').on('click', function(e) {
            e.preventDefault();
            closeAllEditors();
            $(this).siblings('.hs-campus-editor').show();
        });
        
        $('.hs-cancel-campus-btn').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.hs-campus-editor').hide();
        });

        $('.hs-save-campus-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest('.hs-campus-editor');
            var $status = $editor.find('.hs-campus-ajax-status');
            var post_id = $btn.data('post-id');
            var selected_campus = $editor.find('.hs-campus-radio:checked').val();

            $btn.prop('disabled', true);
            $status.text('Saving...').css('color', '#888');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hs_inline_tax_update', // Reusing the same endpoint from post customizer
                    security: '<?php echo wp_create_nonce("hs_inline_tax_nonce"); ?>',
                    post_id: post_id,
                    taxonomy: 'hs_campus',
                    term_id: selected_campus
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('Saved!').css('color', '#00a32a');
                        setTimeout(function(){ window.location.reload(); }, 500);
                    } else {
                        $status.text('Failed').css('color', '#d63638');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    $status.text('Error').css('color', '#d63638');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Week Number UI
        $('.hs-edit-week-btn').on('click', function(e) {
            e.preventDefault();
            closeAllEditors();
            $(this).siblings('.hs-week-editor').show();
        });
        
        $('.hs-cancel-week-btn').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.hs-week-editor').hide();
        });

        $('.hs-save-week-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest('.hs-week-editor');
            var $status = $editor.find('.hs-week-ajax-status');
            var post_id = $btn.data('post-id');
            var val = $editor.find('.hs-week-input').val();

            $btn.prop('disabled', true);
            $status.text('Saving...').css('color', '#888');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hs_inline_meta_update', // Reusing the meta update endpoint
                    security: '<?php echo wp_create_nonce("hs_inline_tax_nonce"); ?>',
                    post_id: post_id,
                    meta_key: 'hs_week_number',
                    meta_value: val
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('Saved!').css('color', '#00a32a');
                        setTimeout(function(){ window.location.reload(); }, 500);
                    } else {
                        $status.text('Failed').css('color', '#d63638');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    $status.text('Error').css('color', '#d63638');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Tags UI
        $('.hs-edit-tags-btn').on('click', function(e) {
            e.preventDefault();
            closeAllEditors();
            $(this).siblings('.hs-tags-editor').show();
        });
        
        $('.hs-cancel-tags-btn').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.hs-tags-editor').hide();
        });

        $('.hs-save-tags-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $editor = $btn.closest('.hs-tags-editor');
            var $status = $editor.find('.hs-tags-ajax-status');
            var post_id = $btn.data('post-id');
            
            var selected_tags = [];
            $editor.find('.hs-tags-checkbox:checked').each(function() {
                selected_tags.push($(this).val());
            });
            
            // Also grab any new custom tag string
            var new_tag = $editor.find('.hs-new-tag-input').val().trim();
            if (new_tag !== '') {
                selected_tags.push(new_tag);
            }

            // If nothing is selected and nothing is typed, send an explicit empty string array 
            // so jQuery doesn't strip the parameter completely, allowing terms to be cleared!
            if (selected_tags.length === 0) {
                selected_tags.push('');
            }

            $btn.prop('disabled', true);
            $status.text('Saving...').css('color', '#888');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hs_inline_tax_update', 
                    security: '<?php echo wp_create_nonce("hs_inline_tax_nonce"); ?>',
                    post_id: post_id,
                    taxonomy: 'in_this_issue',
                    term_ids: selected_tags
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('Saved!').css('color', '#00a32a');
                        setTimeout(function(){ window.location.reload(); }, 500);
                    } else {
                        $status.text('Failed').css('color', '#d63638');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    $status.text('Error').css('color', '#d63638');
                    $btn.prop('disabled', false);
                }
            });
        });
        // List View Divi Gatekeeper
        $('a').on('click', function(e) {
            var $link = $(this);
            var isDivi = $link.hasClass('edit_with_builder') || ($link.attr('href') && $link.attr('href').indexOf('et_fb=1') > -1);
            
            if (isDivi) {
                var $row = $link.closest('tr');
                if ($row.length) {
                    var title = $row.find('.row-title').text().trim();
                    var campusDisplay = $row.find('.hs-campus-display').text().trim();
                    var weekDisplay = $row.find('.hs-week-display').text().trim();

                    var isTitleOk = title !== '' && title !== '(no title)' && title !== 'Auto Draft';
                    var isCampusOk = campusDisplay !== 'Required';
                    var isWeekOk = weekDisplay !== 'Required';

                    if (isTitleOk && isCampusOk && isWeekOk) {
                        return true; // Let them through
                    }

                    e.preventDefault();
                    e.stopImmediatePropagation();
                    
                    var debugMsg = "🔒 ACTION DENIED: Please complete all required fields before entering the Divi Builder.\n\nMissing:\n";
                    if (!isTitleOk) debugMsg += "❌ Post Title\n";
                    if (!isWeekOk) debugMsg += "❌ Week Number\n";
                    if (!isCampusOk) debugMsg += "❌ Campus Selection\n";
                    
                    alert(debugMsg);
                    return false;
                }
            }
        });
    });
    </script>
    <?php
}
