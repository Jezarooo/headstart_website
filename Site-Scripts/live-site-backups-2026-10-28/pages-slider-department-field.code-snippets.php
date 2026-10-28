<?php

/**
 * Pages--Slider-Department-Field
 */
/**
 * Enables the hs_department taxonomy for Pages.
 * This allows the standard WordPress sidebar taxonomy checklist to appear on Page editing screens,
 * which the Sliders use for auto-detecting the current section.
 */
add_action('init', 'hs_enable_departments_for_pages', 99);

function hs_enable_departments_for_pages() {
    // Ensure the taxonomy exists before trying to register it for pages
    if (taxonomy_exists('hs_department')) {
        register_taxonomy_for_object_type('hs_department', 'page');
    }
}
