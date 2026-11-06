# Site search: one instant, predictive search panel for the whole site — dev notes

Package `site-search-2026-11-06.zip` · 2026-11-06 · branch `feat/site-search`

## Problem
The site has no usable search. The only option is WordPress's default `/?s=`, which knows nothing about the content inside Divi modules, staff, weekly-update articles or linked documents, so parents can't find fees, contacts, staff or forms.

## Change
This is all new code. No existing snippet, script or stylesheet is changed.

- **Index.** A JSON index, `wp-content/uploads/hs-search/index.json`, is built in WP-Cron.
  - Sources: pages, staff, weekly updates and the articles inside them, posts, documents linked from content, and Quick answers. Events stay out until a public calendar page exists.
  - The index is about 320 KB gzipped and the browser loads it on first use.
  - Searching happens in the browser, so there is no per-keystroke server load and every visitor gets the same page HTML (cache-safe).
- **Search Topics** (`search-topics`):
  - registers the CPT `hs_search_topic` ("Quick answers");
  - adds a "Hide from site search" checkbox (`hs_search_exclude` meta) on pages and posts;
  - adds the top-level **Site search** admin menu;
  - on the first admin load, seeds a set of **draft** Quick answers (option `hs_search_topics_seeded`).
- **Search Index Builder** (`search-index-builder`): reads the sources (100 posts at a time) and builds the index under a lock (`hs_search_build_lock` transient).
  - Rebuild triggers: saving or deleting indexed content queues `hs_search_build_event` 30 s later (debounced); a daily rebuild runs on `hs_search_daily_event`.
  - Password-protected posts, Yoast noindex posts and anything ticked "Hide from site search" are left out.
  - Links that aren't http(s), mailto, tel or relative are dropped.
- **Search Admin** (`search-admin`): the **Index status** screen (counts, size, Rebuild now, document audit) and the **Documents** screen (include, exclude, pin, title override, rules).
- **Search Terms** (`search-terms`):
  - The **Search terms** screen holds synonym groups, one-way expansions, campus names, promoted results, a test box, and CSV export/import.
  - The search report reads from a new table, `{prefix}hs_search_log`, created by dbDelta on first run (flag option `hs_search_log_db`). It stores terms only, never IPs. Rows older than 13 months are deleted daily.
  - Logging endpoint: `admin-ajax.php?action=hs_search_log` (logged out too). It is rate-limited per IP and site-wide, and refuses emails, long numbers and URLs.
- **Search Shortcodes** (`search-shortcodes`):
  - `[hs_search]` is the header search field. Without JavaScript it works as a plain GET form to `/?s=`.
  - `[hs_search_button]` is the magnifier button for the mobile header.
  - Fallback endpoint `admin-ajax.php?action=hs_search_index`: it builds the index only if the file is missing, at most once every 5 minutes.
- **`site-search.js`**: the search engine and the results panel (keyboard support, mobile sheet). It also pushes `hs_search`, `hs_search_click` and `hs_search_no_results` events to `dataLayer`.
- **`site-search.css`**: panel styles, all scoped under `.hs-search*`.

Not changed: Core CSS, the mega menu, mobile navigation, the staff directory, the events calendar and the weekly-update snippets. There are no rewrite rules, so no permalink flush is needed.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/mu-plugins/headstart-local-loader.php | modified | **Do not deploy.** Local development loader only; it doesn't exist on live | — |
| files/css/site-search.css | new | Custom CSS & JS → new CSS entry **"Site Search"** (front end, header) | — new: deactivate/remove to roll back |
| files/js/site-search.js | new | `wp-content/uploads/hs-head/ad6b24c794936673b17bfc08ba2c4c99.js` | — new: delete the file and its script tag to roll back |
| files/php/search-topics.code-snippets.php | new | Code Snippets: **Search Topics** | — new: deactivate to roll back |
| files/php/search-index-builder.code-snippets.php | new | Code Snippets: **Search Index Builder** | — new: deactivate to roll back |
| files/php/search-admin.code-snippets.php | new | Code Snippets: **Search Admin** | — new: deactivate to roll back |
| files/php/search-terms.code-snippets.php | new | Code Snippets: **Search Terms** | — new: deactivate to roll back |
| files/php/search-shortcodes.code-snippets.php | new | Code Snippets: **Search Shortcodes** | — new: deactivate to roll back |

## Deploy steps
1. **Code Snippets.** Add the five PHP snippets ("Run snippet everywhere"), named as above, and activate them in this order: Search Topics, Search Index Builder, Search Admin, Search Terms, Search Shortcodes. Paste each file's contents without the opening `<?php` line.
2. **CSS.** In Custom CSS & JS, add a new CSS entry "Site Search" (front end, header) with `site-search.css`. Don't touch "Core CSS".
3. **JS.** Upload `site-search.js` to `wp-content/uploads/hs-head/` as `ad6b24c794936673b17bfc08ba2c4c99.js`. In Divi → Theme Options → Integration → head, add its tag straight after the `38655745c724b0c052e6a96d91db2ed6.js` (global-utilities) tag, in the same style as the others:
   `<script defer src="https://headstartphuket.com/wp-content/uploads/hs-head/ad6b24c794936673b17bfc08ba2c4c99.js"></script>`
4. **First build.** Go to Site search → Index status → **Rebuild now**. It takes about 30 s. Check the counts look sensible (local had: pages 47, people 342, weekly updates 174, articles 1,961, documents 277, quick answers 14) and that `uploads/hs-search/index.json` exists and is writable.
5. **Settings from local.** The user will do this or send you a CSV: Site search → Search terms → **Upload and preview** the CSV exported from local, then **Apply import**. It brings over the campus names, synonyms, expansions, promoted results and quick-answer search words. Quick answers are matched by title when the IDs differ.
6. **Header placement.** Jeremy does this after the steps above. Put `[hs_search]` in a Code module in the utility bar beside the social icons, and `[hs_search_button]` in the mobile header.
7. **Purge** WP Super Cache and Cloudflare APO.

## Watch out for
- **Cloudflare and the index file:** make sure Cloudflare doesn't edge-cache `/wp-content/uploads/hs-search/*` for long. The file changes after each rebuild and the browser revalidates it. Add a cache rule (bypass, or a short edge TTL) if it is cached.
- **Question for you:** does the server already restore real visitor IPs behind Cloudflare (mod_remoteip or similar)? The log's rate limit reads `CF-Connecting-IP` only when `REMOTE_ADDR` is in Cloudflare's published ranges (listed in `hs_search_cloudflare_ranges()` in Search Terms), and otherwise uses `REMOTE_ADDR`. Either way it works. Please tell us if the server is set up differently, or if you'd rather keep the Cloudflare range list up to date some other way.
- WP-Cron must run (normal page traffic or a server cron), otherwise rebuilds after edits wait until the next daily job.
- The Quick answers are seeded as **drafts**. The school reviews and publishes them; the CSV import doesn't publish them.
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.

## Quick check after deploy
- `https://headstartphuket.com/wp-content/uploads/hs-search/index.json` returns 200 with JSON.
- On any page, typing "fees" in the header field shows the fees card. A staff member's name, a word from a weekly-update article and a document name each return a result.
- The browser console shows no errors. The mega menu, mobile drawer, staff directory and events calendar behave as before.

## Previous version / rollback
Everything here is new, so `rollback/` has nothing to restore.
1. **Quick off:** remove the two shortcodes from the header and the `ad6b24c…js` script tag. Search disappears and nothing else changes.
2. **Then:** deactivate the five snippets and the "Site Search" CSS entry, and purge both caches.
3. **Full clean-up** (only if search is being removed for good; Code Snippets has no deactivation hook, so this is manual):
   - Scheduled events: `wp cron event delete hs_search_daily_event` and `wp cron event delete hs_search_build_event` (or WP Crontrol).
   - Options: `hs_search_settings`, `hs_search_vocab`, `hs_search_vocab_backup`, `hs_search_docs`, `hs_search_docs_audit`, `hs_search_index_meta`, `hs_search_topics_seeded`, `hs_search_log_db`. Transients: `hs_search_build_lock`, `hs_search_fallback_recent`, and any `hs_search_rl_*` / `hs_search_import_*`.
   - Table: `DROP TABLE {prefix}hs_search_log;`
   - Files: delete the folder `wp-content/uploads/hs-search/`.
   - Content (optional): Quick answer posts (`hs_search_topic`) and the `hs_search_exclude` post meta can stay or be deleted.
