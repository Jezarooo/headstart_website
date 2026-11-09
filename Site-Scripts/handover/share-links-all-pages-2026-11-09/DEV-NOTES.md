# Share links and click counts for all pages and posts — dev notes

Package `share-links-all-pages-2026-11-09.zip` · 2026-11-09 · branch `feat/share-links-all-pages`

## Problem
Weekly updates have per-channel short links and click counts (snippet 66). Pages and Posts have nothing, so we can't measure WhatsApp or Facebook traffic to them. This extends the same snippet to every published Page and Post.

## Change
One existing snippet changes: `Weekly-Update--Short-Links` (id 66). The change only adds code: the existing weekly update route, counter, box, column and GTM output are not edited, and existing links keep working.

- **Links, no rewrite rules.** `hs_sl_share_handle_request()` on `parse_request` (priority 2, after the weekly handler at 1) calls `hs_sl_share_resolve()`:
  - **Pages:** `^/(wa|fb)(/path)?/?$`, looked up with `get_page_by_path()`, e.g. `/wa/resources-and-information/tuition-fees`. No path means the homepage.
  - **Posts:** `^/{word}/{yyyy-yy or yy-yy}/{slug}/?$` with a valid school year, looked up by exact slug, e.g. `/chaofah/2025-26/chaofah-senior-prom-2026`. The 4-digit year means WhatsApp and the 2-digit year means Facebook. The first part isn't enforced at click time, nor is the year's value beyond being a valid pair, so links survive later campus or date edits.
  - **Renames:** Posts fall back to `_wp_old_slug`. Pages fall back to new post meta `_hs_sl_old_path`, written by `post_updated` for the Page and its child Pages.
  - **Match:** counts the click (GET and non-bot only), then sends a 302 with `no-store` and `X-Robots-Tag: noindex` to the permalink plus `utm_source=whatsapp|facebook`, `utm_medium=social`, `utm_campaign=page-share|post-share`, `utm_content={slug or hyphenated path}`.
  - **No match,** or an item that is unpublished or password-protected: falls through to WordPress as normal.
- **Counts.** New table `{prefix}hs_share_links` (`post_id` primary key, `whatsapp`, `facebook`), created once on `admin_init` with `dbDelta`, version option `hs_share_links_db`. Each click is one `INSERT … ON DUPLICATE KEY UPDATE`. No personal data. Weekly update counts stay in `_hs_short_link_clicks`.
- **Admin.** A "Share links" meta box in the Gutenberg sidebar on Page and Post edit screens, and a "Clicks" column in both lists.

Weekly updates, events, notices and other post types get no new links.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/weekly-update-short-links.code-snippets.php | modified | Replace the code of `Code Snippets: Weekly-Update--Short-Links` (id 66, run everywhere) | rollback/php/weekly-update-short-links.code-snippets.php |

## Watch out for
- **Snippet 66 was checked against the live export of 9 Nov 2026** and matched. If it has been edited on live since then, stop and tell Jeremy before replacing it.
- **Paste the code without the opening `<?php`** if your Code Snippets editor doesn't accept it, as with the other snippets.
- **Load any wp-admin page once after saving.** That creates the table. Until then links still redirect, but clicks aren't counted.
- **No permalink flush needed.**
- **`/wa` and `/fb` become reserved** at the top level, so a future page with either slug wouldn't load.
- Purge WP Super Cache and Cloudflare APO after deploying if URLs, redirects or page output changed. This one adds new redirect URLs, so please purge both.
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.

**Separate question, not part of this change:** the live snippet `Posts--Compute-Taxonomies-From-Custom-Fields` shows a `?>` straight after its header comment in the Code Snippets export (line 10), with no `<?php` after it. Our working copy doesn't have it. Could you check what the live snippet really contains? Please don't change it as part of this deploy.

## Quick check after deploy (optional)
With a browser user agent:
- `curl -sI -A "Mozilla/5.0" https://headstartphuket.com/wa/resources-and-information/tuition-fees` → `302`, `location: …/tuition-fees/?utm_source=whatsapp…`, `cache-control: no-store…`
- `curl -sI -A "Mozilla/5.0" https://headstartphuket.com/chaofah/25-26/chaofah-senior-prom-2026` → `302`, `location: …?utm_source=facebook&utm_campaign=post-share…`
- `curl -sI -A "Mozilla/5.0" https://headstartphuket.com/cherngtalay/2026-27/weekly-update-7` → `302` with `utm_source=whatsapp`, as before.
- `curl -sIL -A "Mozilla/5.0" https://headstartphuket.com/wa` → please tell Jeremy whether the final URL still has its `utm_` tags. It depends on how Apache handles `/`.
- Open a Page in wp-admin: the Share links box appears in the sidebar.

## Previous version
`rollback/` holds the live snippet exactly as exported on 9 Nov 2026. To revert, put that code back in snippet 66. Weekly update links keep working; Page and Post share links then fall through to WordPress, usually a 404. The table and `_hs_sl_old_path` meta can stay, as they do nothing without the snippet, and counts survive if the feature is restored.
