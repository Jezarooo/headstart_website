# Weekly Updates Gatekeeper - Developer Handover

## Overview
The Weekly Updates Divi Gatekeeper has been completely rebuilt to use the same robust 3-layer architecture as the Posts Gatekeeper, replacing the fragile DOM-scraping JS logic with a deterministic server-side and client-side validation flow.

## 1. `weekly-update-divi-gatekeeper.code-snippets.php` (Complete Rewrite)
*   **Single Source of Truth:** Created `hs_weekly_gate_missing_fields()` PHP function to validate required database values (Title, `hs_week_number` post meta, and `hs_campus` taxonomy terms).
*   **Layer A (Client-Side Interceptor):**
    *   Replaced immediate `wp.data.subscribe()` call with a `window.addEventListener('load')` wrapper to fix a race condition causing a Gutenberg White Screen of Death (WSOD) on fast environments.
    *   JS now queries Gutenberg's `wp.data` store directly instead of relying solely on fragile DOM element states.
    *   Intercepts clicks on the Divi Builder button, automatically forcing a `savePost()` to sync ACF meta boxes *before* releasing the click to enter Divi.
*   **Layer B (Divi Entry Guard):**
    *   Added server-side `template_redirect` hook to intercept `?et_fb=1` and `?et_fb_activation_nonce` requests.
    *   Bounces users back to the post editor URL with a transient admin notice if DB fields are missing.
*   **Layer C (Publish Guard):**
    *   Intercepts Divi's internal publish route (`/divi/v1/sync-to-server`) via `rest_request_before_callbacks`.
    *   Intercepts native Gutenberg/Quick Edit publishes via `transition_post_status` and `wp_after_insert_post`.
    *   Downgrades unauthorized `publish` actions to `draft` while preserving layout/content saves, storing a transient admin notice.

## 2. `weekly-update-admin-columns.code-snippets.php` (Minor Update)
*   Updated the inline List View JS gatekeeper to also check for `et_fb_activation_nonce` links when determining if the Divi Builder is being launched from the Posts List.
