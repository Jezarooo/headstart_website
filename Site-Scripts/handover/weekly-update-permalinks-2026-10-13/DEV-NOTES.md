# Weekly update permalinks — dev notes

Package `weekly-update-permalinks-2026-10-13.zip` · 2026-10-13 · branch `fix/weekly-update-permalinks`

## Problem
- `weekly_update` is registered in CPT UI (rewrite slug `weekly-update`, query var on, REST base `weekly-update`). The Code Snippet **Weekly-Update--URL-Routing** serves the public URL `/{campus}/weekly-update/{YYYY}/{MM}/{slug}/`: a top rewrite rule maps it to a "phantom" DB slug `{slug}-{campus}-{YYYY}-{MM}`, which `hs_force_unique_weekly_slug` (`wp_insert_post_data`) writes on save so both campuses can use the same slug in the same month. Campus comes from the `hs_campus` term (`general` if none).
- **Bug 1, editor link.** `hs_phantom_link_injector` was hooked to `post_type_link` with 2 args and did `str_replace('/weekly-update/{post_name}/', <clean path>, $post_link)`. When core builds the editable permalink (`get_sample_permalink()` → `get_permalink($post, true)`), `$post_link` contains `%weekly_update%` instead of the post name, so nothing matched and the block editor's permalink panel / "View" showed the raw `/weekly-update/{db-slug}/`.
- **Bug 2, duplicate URLs.** The CPT's default single route `/weekly-update/{db-slug}/` and the legacy no-date rule `^([^/]+)/weekly-update/([^/]+)/?$` (kept for old links) both resolve with 200, so each update exists at up to three URLs. `redirect_canonical` doesn't catch either.

## Change (one snippet; sections 1, 2, 3 and 5 unchanged)
- **Link building.** `post_type_link` now takes 3 args and builds the URL from scratch with `home_url()` using the helper `hs_weekly_update_parts()` (campus term → `general`; Y/m from `post_date`; the phantom suffix is stripped only when it is a true suffix, so legacy slugs without one pass through). With `$leavename` the last segment is `%weekly_update%`. Draft / pending / auto-draft posts keep core's `?post_type=…&p=…` link so previews work.
- **Editor slug.** `get_sample_permalink` and `rest_prepare_weekly_update` (`context=edit` only; public REST untouched) strip the phantom suffix, so the editor shows `weekly-update-7`. On save, the existing unique-slug filter re-appends it as before.
- **Canonical 301.** `template_redirect` at priority 5 (before core's `redirect_canonical`): on a published singular `weekly_update`, if the request path ≠ the preferred path (compared case-insensitively, trailing slash ignored) → `wp_safe_redirect( preferred + original query string, 301 )`. Skips previews, `et_fb`/`et_tb` (Divi builder), feeds, admin and AJAX. Decision logic is the pure function `hs_weekly_update_redirect_target()`.
- **Unchanged:** rewrite tag and rules, `hs_force_unique_weekly_slug`, the legacy `request` fallback. No rewrite flush needed.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/weekly-update-url-routing.code-snippets.php | modified | Code Snippet **Weekly-Update--URL-Routing** (replace its code) | rollback/php/weekly-update-url-routing.code-snippets.php |

## Watch out for
- **Purge WP Super Cache and Cloudflare APO after saving the snippet.** The duplicate URLs are probably cached as 200s and will keep serving until purged.
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.
- Tested: PHP 8.3 lint; 15-case unit run with WP stubs (published / `$leavename` / draft / no campus / legacy slug; preferred URL never redirects incl. no trailing slash and `?utm_*`; raw, raw+query, no-date, wrong-campus → 301); on Local (PHP 8.2) 200 / 301 / 301 with query kept and REST `link` = preferred URL; after refreshing Local from the live database (13 Oct), the block editor shows `/cherngtalay/weekly-update/2026/10/weekly-update-7/` for week 7 and "View" opens it.

## Quick check after deploy (optional)
```
UA="Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 Chrome/128 Safari/537.36"
for u in /chaofah/weekly-update/2026/08/weekly-update-1/ /weekly-update/weekly-update-1-chaofah-2026-08/ /chaofah/weekly-update/weekly-update-1/; do
  printf '%-55s ' "$u"; curl -s -A "$UA" -o /dev/null -w '%{http_code} %{redirect_url}\n' "https://headstartphuket.com$u"; done
```
Expect 200, then 301 → the first URL, twice. (`weekly-update-1-chaofah-2026-08` is week 1's DB slug, confirmed from the live database of 13 Oct.)

## Previous version
`rollback/php/weekly-update-url-routing.code-snippets.php` is the live snippet as of 13 Oct, if you need to revert.

## FYI: block editor issue seen locally (no action needed on live)
Not part of this package; noting it in case it's useful before the next core update.
- On a local copy (WordPress 7.1.3, Divi 5.14.0, live database of 13 Oct) the block editor loaded blank. Cause: the inline script in **Weekly-Update--Divi-Gatekeeper** calls `wp.data.dispatch('core/edit-post').removeEditorPanel(...)` from a `wp.data.subscribe` callback before `core/edit-post` is registered, so `dispatch()` returns `null`, the callback throws inside the editor's start-up, and the editor never initialises.
- Live (7.1.1) works fine, so there's nothing to deploy now. Since WP 6.5 `removeEditorPanel` lives on `core/editor`; null-checking the store (and/or a try/catch) around that call avoids the crash. **Posts--Divi-Gatekeeper** uses the same call, with a guard. We have a tested local patch if you'd like it.
