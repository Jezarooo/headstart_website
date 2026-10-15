<?php

/**
 * Cap-Revisions
 */
// Limit post revisions to 5
add_filter('wp_revisions_to_keep', fn() => 5);
