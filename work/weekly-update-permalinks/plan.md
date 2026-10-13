# Plan: Weekly update permalinks: clean editor link + 301s to the dated URL

- Task: `fix/weekly-update-permalinks` · opened 2026-10-13
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-13` (routing snippet identical to working copy)
- Roadmap: wu-permalinks

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
Every weekly update has one preferred address, e.g. `/cherngtalay/weekly-update/2026/10/weekly-update-7/`. That URL stays exactly as it is. Today:
- The block editor shows (and sometimes links to) the raw database address `/weekly-update/weekly-update-7-cherngtalay-2026-10/`, because the link filter `hs_phantom_link_injector` takes 2 arguments and never handles the `%weekly_update%` placeholder WordPress uses when it builds the editor's permalink.
- That raw address, and the older no-date address `/cherngtalay/weekly-update/weekly-update-7/`, both show the page with HTTP 200, so each update exists at up to three URLs.
Goal: the editor shows and links the preferred URL; the two other forms 301 to it; nothing else changes.

## Proposed changes
File: `Site-Scripts/php/weekly-update-url-routing.code-snippets.php` (live snippet "Weekly-Update--URL-Routing"). Sections 1, 2, 3 and 5 (query var, unique DB slug, inbound rewrite rules, legacy fallback) are unchanged.
1. Section 4, outbound link: `add_filter( 'post_type_link', 'hs_phantom_link_injector', 10, 3 )`. Build the URL from scratch with `home_url()` instead of string-replacing WordPress's default link:
   - published/scheduled/private posts → `/{campus}/weekly-update/{YYYY}/{MM}/{clean-slug}/`;
   - when WordPress asks for the editable form (`$leavename = true`) → the same path with `%weekly_update%` as the last segment;
   - drafts/pending (no slug yet) → leave WordPress's link untouched (previews keep working).
   One helper, `hs_weekly_update_parts( $post )`, returns campus / year / month / clean slug so every function uses the same rules.
2. New `get_sample_permalink` filter: the editor's slug box shows the clean slug (`weekly-update-7`), not the DB slug with the `-cherngtalay-2026-10` suffix. Saving still works because section 2 already strips and re-adds the suffix.
3. New canonical redirect on `template_redirect` (priority 5, before WordPress's own `redirect_canonical`): on a single weekly update, if the requested path is not the preferred path, `wp_safe_redirect( preferred + original query string, 301 )`. Covers the raw `/weekly-update/{db-slug}/` form and the older `/{campus}/weekly-update/{slug}/` form. Never fires for the preferred URL itself, previews (`?preview=true`), feeds, REST, admin, or the `/weekly-update/` archive.
4. Housekeeping carried from workflow-tooling (local only, not shipped): `tools/hs fetch-js` creates `Latest-Drop/js` and says where the head code goes; CLAUDE.md rule: verify every file copied to the Mac by checksum, and push task tags from the Mac (cloud sessions can't push tags).

## Risks & rollback
- Redirect loop if preferred and requested paths are compared inconsistently (trailing slash, case, encoding). Mitigation: compare normalised paths; unit test both forms + the preferred URL; the redirect only runs when they differ.
- Posts with no campus get `general` as today (unchanged behaviour).
- Caching: Cloudflare APO and WP Super Cache may hold the old 200 pages for the raw/no-date URLs until purged.
- Rollback: paste `rollback/php/weekly-update-url-routing.code-snippets.php` (today's live copy) back into the snippet, purge caches.

## Testing strategy
### Automated (Gate 4)
- `php -l` on the snippet (cloud container PHP 8.3; live runs 8.x).
- Unit run in the container with WordPress functions stubbed: link builder output for published / `$leavename` / draft / no-campus posts; redirect decision for the preferred URL (no redirect), raw DB URL (301 → preferred), no-date URL (301 → preferred), preferred + `?utm_source=whatsapp` (no redirect), raw + query (301 keeps the query), preview (no redirect).
- On the Mac, after Local reload: `curl -sI` the three forms of one real weekly update and the `/weekly-update/` archive; expect 200 / 301 / 301 / 200 with correct `Location`.

### UAT checklist for the user (Gate 5)
- [ ] Open a weekly update at its preferred URL: loads normally (no redirect, no loop).
- [ ] Paste the raw `/weekly-update/…-cherngtalay-2026-10/` URL: lands on the preferred URL.
- [ ] Paste the no-date `/cherngtalay/weekly-update/weekly-update-N/` URL: lands on the preferred URL.
- [ ] Weekly updates listing / archive pages still load and link correctly.
- [ ] If local admin works by then: the editor's permalink and "View" show the preferred URL. If admin is still blank (roadmap: local-admin-blank), check `http://headstart.local/wp-json/wp/v2/weekly-update?per_page=3` instead: each `link` is the preferred URL (that is what "View" uses).

## Deployment notes for the dev (Gate 7)
- Replace the code of the Code Snippet "Weekly-Update--URL-Routing" with `files/php/weekly-update-url-routing.code-snippets.php`. No new snippet, no rewrite-rule change (a permalinks Save is harmless but not required).
- Purge WP Super Cache and Cloudflare (APO) — essential, or the old URLs keep serving cached 200 pages.
- Smoke: `curl -sI` on one update's preferred / raw / no-date URLs → 200 / 301 / 301.
