<?php

/**
 * Events-Calendar--Shortcode-Engine
 */
// ==========================================
// BEGIN: shortcode.php
// ==========================================
/**
 * HeadStart Events Calendar Shortcode (Dynamic WP_Query)
 * 
 * Usage in Divi or WordPress Editor: 
 * [hs_events_calendar] or [hs_events_calendar view="sidebar"]
 * 
 * Custom Post Type: 'events'
 * 
 * Description: 
 * This shortcode dynamically fetches 'events' post types. 
 * 'slider' view ONLY fetches featured events.
 * 'sidebar' view fetches ALL events.
 */

function hs_v3_events_calendar_shortcode($atts) {
    // ---------------------------------------------------------------------------
    // 1. PARSE SHORTCODE ATTRIBUTES
    // ---------------------------------------------------------------------------
    $a = shortcode_atts(array(
        'campus'    => 'both',  // Options: 'chaofah', 'cherngtalay', or 'both'
        'show_past' => 'false', // If 'true', it bypasses the date filter and shows old events
        'view'      => 'slider', // Options: 'slider' or 'sidebar'
    ), $atts);

    // ---------------------------------------------------------------------------
    // 2. BUILD THE WP_QUERY (Database Fetch)
    // ---------------------------------------------------------------------------
    $today = date('Ymd');
    
    $meta_query = array('relation' => 'AND');
    
    // Date Filtering Logic (Upcoming OR Ongoing)
    if ($a['show_past'] !== 'true') {
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => 'event_start_date',
                'value'   => $today,
                'compare' => '>='
            ),
            array(
                'key'     => 'event_end_date',
                'value'   => $today,
                'compare' => '>='
            )
        );
    }

    // Campus Filtering Logic
    if ($a['campus'] !== 'both' && $a['view'] !== 'sidebar') {
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => 'target_campus',
                'value'   => $a['campus'],
                'compare' => 'LIKE'
            ),
            array(
                'key'     => 'target_campus',
                'value'   => 'both',
                'compare' => 'LIKE'
            )
        );
    }

    // View Filtering Logic (Slider = Featured Only, Sidebar = All)
    if ($a['view'] === 'slider') {
        $meta_query[] = array(
            'key'     => 'is_featured',
            'value'   => '1',
            'compare' => '='
        );
    }

    $args = array(
        'post_type'      => array('event', 'events'),
        'posts_per_page' => -1,
        'post_status'    => array('publish', 'draft', 'private'),
        'meta_key'       => 'event_start_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => $meta_query
    );

    $events_query = new WP_Query($args);

    // ---------------------------------------------------------------------------
    // 3. DYNAMIC CATEGORY COLLECTION (Only needed for Slider view)
    // ---------------------------------------------------------------------------
    $used_categories = array();
    
    if ($a['view'] === 'slider' && $events_query->have_posts()) {
        while ($events_query->have_posts()) {
            $events_query->the_post();
            $cat = get_post_meta(get_the_ID(), 'event_category', true);
            if ($cat) {
                // Formatting category name for label
                $cat_label = ucfirst(str_replace('-', ' ', $cat));
                $used_categories[$cat] = $cat_label;
            }
        }
        $events_query->rewind_posts();
        asort($used_categories);
    }

    // ---------------------------------------------------------------------------
    // 4. HTML GENERATION (Output Buffer)
    // ---------------------------------------------------------------------------
    ob_start();
    ?>
    
    <!-- Inject FontAwesome strictly for this component if theme doesn't have it -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php if ($a['view'] === 'sidebar'): ?>
        
        <!-- ========================================== -->
        <!-- SIDEBAR VIEW (Important Dates)             -->
        <!-- ========================================== -->
        <div id="hs-events-sidebar-wrapper" data-active-campus="<?php echo esc_attr($a['campus']); ?>">
            <?php if ($a['campus'] !== 'both'): ?>
            <div class="hs-noticeboard-chips">
                <div id="hs-events-campus-chip">
                    <?php echo esc_html(strtoupper($a['campus'])); ?>
                </div>
                <button id="hs-events-campus-toggle" onclick="window.hsToggleEventsCampus()">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <?php endif; ?>
            
            <div class="hs-events-sidebar">
                <div class="hs-events-sidebar-scroll-wrapper">
                    <div class="hs-events-sidebar-scroll-area">
                        <ul class="hs-events-sidebar-list">
                             
                            <?php if ($events_query->have_posts()) :
                                while ($events_query->have_posts()) : $events_query->the_post();
                                    $post_id = get_the_ID();
                                    
                                    $iso_date     = get_post_meta($post_id, 'event_start_date', true);
                                    $iso_end_date = get_post_meta($post_id, 'event_end_date', true);
                                    
                                    // Fetch campus for filtering
                                    $target_campus = get_post_meta($post_id, 'target_campus', true);
                                    $campus_attr = is_array($target_campus) ? implode(',', $target_campus) : (string)$target_campus;
                                    
                                    $display_date = $iso_date ? date_i18n('M d', strtotime($iso_date)) : '';
                                    $display_end_date = $iso_end_date ? date_i18n('M d', strtotime($iso_end_date)) : '';
                                    
                                    $final_date_str = $display_date;
                                    if ($display_end_date && $display_date !== $display_end_date) {
                                        $final_date_str .= ' - ' . $display_end_date;
                                    }
                                    
                                    ?>
                                    <li class="hs-events-sidebar-item" data-campus="<?php echo esc_attr($campus_attr); ?>">
                                        <div class="hs-sidebar-event-date">
                                            <i class="fa-regular fa-calendar"></i> <?php echo esc_html($final_date_str); ?>
                                        </div>
                                        <h3 class="hs-sidebar-event-title"><?php echo esc_html(get_the_title()); ?></h3>
                                    </li>
                                <?php
                            endwhile;
                            wp_reset_postdata();
                        else:
                            echo '<li class="hs-events-sidebar-item"><h3 class="hs-sidebar-event-title">No upcoming events.</h3></li>';
                        endif;
                        ?>
                    </ul>
                </div>
                
                <!-- Down Arrow Overlay -->
                <div class="hs-scroll-arrow-overlay-down">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>
                </div>
            </div>
        </div>

    <?php else: ?>

        <!-- ========================================== -->
        <!-- SLIDER VIEW (Featured Events)              -->
        <!-- ========================================== -->
        <div class="hs-section-events-calendar" data-feed-campus="<?php echo esc_attr($a['campus']); ?>">
            
            <!-- HEADER ROW (Title and Filters) -->
            <div class="hs-section-header">
                <div class="hs-header-left">
                    <h2 class="hs-section-title">Upcoming Events</h2>
                </div>
        
                <div class="hs-header-right">
                    <div class="hs-mobile-filter-container hs-main-mobile-filter">
                        <span class="hs-filter-label">Filter:</span>
                        <select class="hs-mobile-filter">
                            <option value="all">All Events</option>
                            <?php foreach($used_categories as $val => $label): ?>
                                <option value="<?php echo esc_attr($label); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hs-header-divider" id="carousel-divider" style="display: none;"></div>

                    <div class="hs-carousel-controls-wrapper" id="carousel-controls" style="display: none;">
                        <button class="hs-carousel-btn hs-carousel-prev"><i class="fa-solid fa-arrow-left"></i></button>
                        <div class="hs-carousel-dots" id="carousel-dots"></div>
                        <button class="hs-carousel-btn hs-carousel-next"><i class="fa-solid fa-arrow-right"></i></button>
                    </div>
                    
                    <div class="hs-header-divider hs-desktop-divider" id="carousel-divider-2" style="display: none;"></div>

                    <div class="hs-cal-dropdown-container">
                        <button class="hs-btn-add-cal" id="btn-global-cal" title="Subscribe to School Calendar">
                            <i class="fa-regular fa-calendar-plus"></i>
                        </button>
                        <div class="hs-cal-dropdown" id="global-cal-dropdown">
                            <div class="hs-cal-drop-header">Add to calendar</div>
                            <button><i class="fa-brands fa-google"></i> Google Calendar</button>
                            <button><i class="fa-brands fa-apple"></i> Apple Calendar</button>
                            <button><i class="fa-brands fa-windows"></i> Outlook</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EVENTS TRACK (Holds the Event Cards) -->
            <div class="hs-events-track">
                 
                <?php if ($events_query->have_posts()) :
                    while ($events_query->have_posts()) : $events_query->the_post();
                        $post_id = get_the_ID();
                        
                        // Native Meta Fetching (Replaces ACF get_field)
                        $iso_date     = get_post_meta($post_id, 'event_start_date', true);
                        $iso_end_date = get_post_meta($post_id, 'event_end_date', true);
                        $is_all_day   = get_post_meta($post_id, 'is_all_day', true);
                        $start_time   = get_post_meta($post_id, 'event_start_time', true);
                        $end_time     = get_post_meta($post_id, 'event_end_time', true);
                        $category     = get_post_meta($post_id, 'event_category', true);
                        $location     = get_post_meta($post_id, 'event_location', true);
                        $blurb        = get_post_meta($post_id, 'event_blurb', true);
                        $reg_type     = get_post_meta($post_id, 'registration_type', true);
                        $ext_reg_link = get_post_meta($post_id, 'external_registration_link', true);
                        $fluent_link  = get_post_meta($post_id, 'fluent_form_page_link', true);
                        
                        $title = esc_attr(get_the_title());
                        
                        $img_url = get_the_post_thumbnail_url($post_id, 'large');
                        if (!$img_url) $img_url = 'https://via.placeholder.com/600x400?text=No+Image'; 
                        
                        $display_date = $iso_date ? date_i18n('F j, Y', strtotime($iso_date)) : '';
                        $display_end_date = $iso_end_date ? date_i18n('F j, Y', strtotime($iso_end_date)) : '';
                        
                        $time_str = "TBD";
                        if ($is_all_day) {
                            $time_str = "All Day Event";
                        } elseif ($start_time) {
                            $time_str = $start_time;
                            if ($end_time) $time_str .= ' - ' . $end_time;
                        }
                        
                        $reg_link = '';
                        if ($reg_type === 'external') {
                            $reg_link = esc_url($ext_reg_link);
                        } elseif ($reg_type === 'fluent') {
                            $reg_link = esc_url($fluent_link);
                        }

                        $cat_val = $category ? $category : 'event';
                        $cat_label = isset($used_categories[$cat_val]) ? $used_categories[$cat_val] : ucfirst(str_replace('-', ' ', $cat_val));
                        $cat_color = hs_v3_get_category_color($cat_val);
                        
                        $clean_blurb = esc_attr(wp_strip_all_tags($blurb));

                        
                        ?>
                        <div class="hs-card-v1" 
                            data-matches="true" 
                            data-category="<?php echo esc_attr($cat_label); ?>" 
                            data-title="<?php echo $title; ?>" 
                            data-img="<?php echo esc_url($img_url); ?>" 
                            data-cat="<?php echo esc_attr($cat_label); ?>" 
                            data-catcolor="<?php echo esc_attr($cat_color); ?>" 
                            data-date="<?php echo esc_attr($display_date); ?>" 
                            data-iso-date="<?php echo esc_attr($iso_date); ?>" 
                            <?php if ($iso_end_date): ?>
                            data-end-date="<?php echo esc_attr($display_end_date); ?>" 
                            data-iso-end-date="<?php echo esc_attr($iso_end_date); ?>"
                            <?php endif; ?>
                            data-time="<?php echo esc_attr($time_str); ?>" 
                            data-raw-start-time="<?php echo esc_attr($start_time ? date('His', strtotime($start_time)) : ''); ?>"
                            data-raw-end-time="<?php echo esc_attr($end_time ? date('His', strtotime($end_time)) : ''); ?>"
                            data-all-day="<?php echo esc_attr($is_all_day ? 'true' : 'false'); ?>"
                            data-loc="<?php echo esc_attr($location); ?>" 
                            data-blurb="<?php echo $clean_blurb; ?>" 
                            data-register-link="<?php echo $reg_link; ?>">
                            
                            <div class="hs-card-v1-img-wrap">
                                <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo $title; ?>">
                                <div class="hs-category-chip" style="color: <?php echo esc_attr($cat_color); ?>">
                                    <?php echo esc_html($cat_label); ?>
                                </div>
                                <?php if ($reg_link): ?>
                                <div class="hs-card-register-btn"><i class="fa-regular fa-clipboard"></i> Register</div>
                                <?php endif; ?>
                                <div class="hs-date-overlay">
                                    <?php
                                    if ($iso_date) {
                                        $m = date_i18n('M', strtotime($iso_date)); 
                                        $d = date_i18n('d', strtotime($iso_date)); 
                                        echo '<div class="hs-date-badge-v1"><div class="hs-db-month">'.strtoupper($m).'</div><div class="hs-db-day">'.$d.'</div></div>';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="hs-card-v1-content">
                                <h3 class="hs-card-title"><?php echo esc_html(get_the_title()); ?></h3>
                                <div class="hs-card-meta">
                                    <span><i class="fa-regular fa-clock"></i> <?php echo esc_html($time_str); ?></span>
                                    <span class="hs-meta-divider">&bull;</span>
                                    <span><i class="fa-solid fa-location-dot"></i> <?php echo esc_html($location); ?></span>
                                    <?php if ($reg_link): ?>
                                    <span class="hs-meta-divider">&bull;</span>
                                    <span class="hs-meta-register-btn" style="color:var(--hs-gold)"><i class="fa-regular fa-clipboard"></i> Register</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                    <?php
                    endwhile;
                    wp_reset_postdata(); 
                else :
                    echo '<p>No upcoming featured events found.</p>';
                endif;
                ?>
                
            </div>
            
            <!-- MODAL STRUCTURE -->
            <div id="hs-modal-overlay">
                <div class="hs-modal-dialog">
                    <button class="hs-shared-close" onclick="document.querySelector('.hs-section-events-calendar').hsCalendarInstance.closeModal()">
                        <span class="hs-modal-close-text">CLOSE</span>
                        <span class="hs-modal-close-icon">×</span>
                    </button>
                    <button class="hs-modal-nav-btn hs-modal-nav-prev" onclick="document.querySelector('.hs-section-events-calendar').hsCalendarInstance.navigateSingleModal(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="hs-modal-nav-btn hs-modal-nav-next" onclick="document.querySelector('.hs-section-events-calendar').hsCalendarInstance.navigateSingleModal(1)"><i class="fa-solid fa-chevron-right"></i></button>

                    <div class="hs-modal-body">
                        <div id="modal-single-view" class="hs-modal-content-box hs-split-preview hs-active-view">
                            <div class="hs-preview-img-wrapper">
                                <div class="hs-category-chip" id="modal-cat"></div>
                                <div id="modal-date-container"></div>
                                <img id="modal-img" src="" alt="Event Image">
                            </div>
                            <div class="hs-preview-text-wrapper">
                                <div class="hs-mobile-date" id="modal-mobile-date"></div>
                                <div class="hs-modal-top-row">
                                    <h2 class="hs-mod-title" id="modal-title"></h2>
                                    <div class="hs-mod-meta-wrapper">
                                        <div class="hs-mod-meta">
                                            <span><i class="fa-regular fa-clock"></i> <span id="modal-time"></span></span>
                                            <span><i class="fa-solid fa-location-dot"></i> <span id="modal-loc"></span></span>
                                        </div>
                                        <div class="hs-cal-dropdown-container">
                                            <button class="hs-btn-add-cal" id="btn-add-cal-event" title="Add to Calendar">
                                                <i class="fa-solid fa-calendar-plus"></i>
                                            </button>
                                            <div class="hs-cal-dropdown" id="cal-dropdown-event">
                                                <div class="hs-cal-drop-header">Add to calendar</div>
                                                <button><i class="fa-brands fa-google"></i> Google Calendar</button>
                                                <button><i class="fa-brands fa-apple"></i> Apple Calendar</button>
                                                <button><i class="fa-brands fa-windows"></i> Outlook</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <a id="modal-register-btn" href="#" class="hs-btn-side" target="_blank" style="display:none;">
                                    Register Attendance <i class="fa-solid fa-chevron-right"></i>
                                </a>
                                <p class="hs-mod-blurb" id="modal-blurb"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}
add_shortcode('hs_events_calendar', 'hs_v3_events_calendar_shortcode');

if (!function_exists('hs_v3_get_category_color')) {
    function hs_v3_get_category_color($cat_val) {
        $cat_val = strtolower($cat_val);
        $defined_colors = array(
            'sports'     => '#3182ce', 'capa'       => '#d53f8c',
            'academic'   => '#805ad5', 'community'  => '#dd6b20',
            'exams'      => '#e53e3e', 'trips'      => '#38a169',
            'holidays'   => '#319795', 'foundation' => '#ed8936',
            'primary'    => '#ecc94b', 'secondary'  => '#4fd1c5'
        );
        if (isset($defined_colors[$cat_val])) return $defined_colors[$cat_val];
        $palette = array('#f56565', '#ed8936', '#ecc94b', '#48bb78', '#38b2ac', '#4299e1', '#667eea', '#9f7aea', '#ed64a6');
        return $palette[abs(crc32($cat_val)) % count($palette)];
    }
}
