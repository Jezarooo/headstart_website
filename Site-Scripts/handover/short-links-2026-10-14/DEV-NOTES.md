# Weekly update short links, click counter and GTM events — dev notes

Package `short-links-2026-10-14.zip` · 2026-10-14 · branch `feat/short-links`

## Problem
Weekly updates go out on WhatsApp and Facebook with the full permalink. We want short, per-channel links, a click count per channel in WordPress, and GA4 events (via GTM) for weekly update views and gallery pop-up opens.

## Change
One new snippet, `weekly-update-short-links`. Nothing existing is modified.

- **Short links.** `hs_sl_handle_request()` on `parse_request` (priority 1) matches the path `^/(chaofah|cherngtalay|both-campuses)/(\d{4}|\d{2})-(\d{2})/weekly-update-(\d{1,3})/?$`.
  - The year form sets the channel: `2026-27` = WhatsApp, `26-27` = Facebook.
  - It looks up the published `weekly_update` with that `hs_campus` term and `hs_week_number`, published between 1 Aug of the start year and 1 Aug of the next (school year).
  - It answers with a **302** to the permalink plus `utm_source={whatsapp|facebook}&utm_medium=social&utm_campaign=weekly-update&utm_content={campus}-{2026-27}-wu{N}`.
  - Headers: `Cache-Control: no-store` and `X-Robots-Tag: noindex`.
  - No match → falls through to WordPress (404).
  - No rewrite rules, so no permalink flush.
- **Counter.** GET requests from non-bot user agents increment post meta `_hs_short_link_clicks` (array keyed by channel). Bots and link previews are excluded by a UA regex (WhatsApp, facebookexternalhit, crawlers, curl…), and so are HEAD requests.
- **Admin.**
  - "Share links" meta box (side) on `weekly_update`, showing both links with copy buttons and counts. Links are built by `hs_sl_url()`.
  - "Clicks" column, added at priority 100, after the existing `hs_weekly_update_columns` (99).
- **GTM data.**
  - `wp_head` (priority 1) on single weekly updates pushes `{event:'weekly_update_view', hs_campus, hs_school_year, hs_week}`.
  - `wp_footer` adds a window capture-phase click listener that pushes `{event:'hs_gallery_open', hs_gallery_type}`. It runs before `gallery-mosaic.js`'s document capture handler and doesn't touch that file.

The GTM tags themselves are already imported in the container by Jeremy; he publishes them after this is live.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/weekly-update-short-links.code-snippets.php | new | `Code Snippets: weekly-update-short-links` (run everywhere) | — new: deactivate to roll back |

## Watch out for
- Purge WP Super Cache + Cloudflare APO after activating.
- Short links must reach PHP: if Cloudflare or Super Cache ever caches one, counts stop (the response sends `no-store`).

## Quick check after deploy (optional)
`curl -sI -A "Mozilla/5.0" https://headstartphuket.com/cherngtalay/2026-27/weekly-update-7` → `302`, `location: …/weekly-update-7/?utm_source=whatsapp…`, `cache-control: no-store…`.

## Previous version
New snippet; deactivate it to roll back.
