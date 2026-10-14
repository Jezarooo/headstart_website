# Plan: Weekly update short links, click counter and GTM events

- Task: `feat/short-links` · opened 2026-10-14
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-14`
- Roadmap: `wu-shortlinks` (P1)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
Weekly updates go out mainly on WhatsApp and are shared on Facebook. We want short, readable links per channel, a reliable click count per channel visible in WordPress, and engagement events in Google Analytics through GTM.

Decisions (user, 13–14 Oct):
- Link format: `headstartphuket.com/{campus}/{school-year}/weekly-update-{N}`, e.g. `/cherngtalay/2026-27/weekly-update-7`.
- Channels: **WhatsApp** `/{campus}/2026-27/weekly-update-{N}`; **Facebook** `/{campus}/26-27/update-{N}` (short year + shorter word, chosen 14 Oct instead of a `/facebook` suffix). The word decides the channel; either year form is accepted on both. No email or LINE links.
- School year: from the publish date, August cut-off (Aug 2026 – Jul 2027 = `2026-27`).
- GTM events in this task (not deferred to the consent banner).

## Proposed changes
**New snippet `weekly-update-short-links.code-snippets.php`**
1. **Redirect.** Matches the request path `^/(chaofah|cherngtalay|both-campuses)/(\d{4}|\d{2})-(\d{2})/(weekly-)?update-(\d+)/?$` (with `weekly-` = WhatsApp, without = Facebook) early (`parse_request`). No rewrite rules, so no permalink flush.
   - Finds the published `weekly_update` with that campus (`hs_campus`), `hs_week_number` = N, published within that school year.
   - Sends a **302** (temporary, not cached) with `Cache-Control: no-store` to its permalink plus:
     - `utm_source` = `whatsapp` or `facebook`;
     - `utm_medium=social`;
     - `utm_campaign=weekly-update`;
     - `utm_content={campus}-{2026-27}-wu{N}`.
   - UTMs already on the short link are dropped, so ours always win.
   - No match → normal WordPress 404.
2. **Click counter.** Each redirect adds 1 to that channel's count in post meta `_hs_short_link_clicks`.
   - Not counted: link-preview bots and crawlers (WhatsApp, facebookexternalhit, Twitterbot, Slackbot, Telegram, LinkedIn, Googlebot, bingbot, generic bot/crawler/spider/preview user agents) and HEAD requests.
   - Counts only; no personal data stored.
3. **"Share links" box** on the weekly update edit screen: the WhatsApp and Facebook links with copy buttons and their click counts. It appears once the update is published and has a campus and week number.
4. **"Clicks" column** in the weekly update list (WhatsApp / Facebook).
5. **GTM data.** On single weekly update pages, a `dataLayer` push is output early in the head: `{event:'weekly_update_view', hs_campus, hs_school_year, hs_week}`. A small inline listener (window, capture phase) also pushes `{event:'hs_gallery_open'}` when a gallery pop-up is opened. It works alongside `gallery-mosaic.js` without changing that file.

**GTM import file** (`work/short-links/gtm-weekly-update-tracking.json`, imported by the user with a click-by-click guide)
- Data layer variables: campus, school year, week.
- GA4 event tags (measurement ID `G-64D4CSDKDR`):
  - `weekly_update_view` (with campus / school year / week);
  - `weekly_update_scroll` at 50% and 90% on weekly update pages;
  - `gallery_open`.
- Imported in **merge** mode into the existing container; nothing existing is renamed or overwritten.
- The guide also covers: registering the three custom dimensions in GA4, Preview/Tag Assistant on live, and publishing.

## Risks & rollback
- **Path clash:** the pattern only matches `/{campus}/{yyyy}-{yy}/weekly-update-{n}`. Existing campus pages and weekly update URLs (`/cherngtalay/weekly-update/2026/10/…`) don't match. Automated checks below confirm they still return 200.
- **Caching:** 302 + `no-store`, so neither WP Super Cache nor Cloudflare APO stores the redirect, and every click reaches WordPress. WP Super Cache doesn't cache redirects; to be confirmed on live after deploy.
- **Bot filtering is best-effort:** counts are close to people, not exact.
- **PDPA:** GA4/GTM still has no consent mode. The new events add to analytics collected without consent until the consent banner (roadmap P2) is done. The WordPress click counter needs no consent.
- **GTM publish:** publishing the workspace also publishes any other unpublished changes already in it (the 13 Oct export was an unpublished draft, workspace 4). The guide checks the change list before publishing.
- **Rollback:** deactivate the snippet (short links then 404; nothing else changes). In GTM, revert to the previous container version (Versions → Publish the earlier one).

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the new snippet.
- On `http://headstart.local`:
  - `/cherngtalay/2026-27/weekly-update-7` → 302 to `/cherngtalay/weekly-update/2026/10/weekly-update-7/?utm_source=whatsapp&utm_medium=social&utm_campaign=weekly-update&utm_content=cherngtalay-2026-27-wu7`;
  - `/cherngtalay/26-27/update-7` → 302 with `utm_source=facebook` (and `/cherngtalay/2026-27/update-7` too);
  - `/chaofah/2026-27/weekly-update-7` → 302 to the Chao Fah week 7;
  - `/cherngtalay/2026-27/weekly-update-99` and `/cherngtalay/2025-26/weekly-update-7` → 404;
  - a WhatsApp user agent → 302, but the count doesn't change;
  - still 200: the week 7 permalink, `/cherngtalay/`, a posts URL.
- Console on the week 7 page: `dataLayer` contains `weekly_update_view` with `hs_campus: "cherngtalay"`, `hs_school_year: "2026-27"`, `hs_week: 7`.

### UAT checklist for the user (Gate 5)
- [ ] Edit a published weekly update: the "Share links" box shows the WhatsApp and Facebook links; the copy buttons work.
- [ ] Open the WhatsApp link in a browser: it lands on the right update; the count goes up by 1 after a refresh of the edit screen.
- [ ] Same for the Facebook link.
- [ ] The weekly update list shows the Clicks column.
- [ ] Paste a link into WhatsApp (to yourself): the preview shows the update's title and image. *(Live only, after deploy: WhatsApp can't see your local site.)*
- [ ] The weekly update page, galleries and pop-ups behave as before.

## Deployment notes for the dev (Gate 7)
- **Add and activate a new Code Snippet:** `weekly-update-short-links` (run everywhere).
- **No permalinks flush needed** (no rewrite rules).
- **Purge WP Super Cache and Cloudflare APO.**
- Then check that a short link returns 302 with `cache-control: no-store` through Cloudflare.
- **GTM** is done by Jeremy (import + publish), not the dev.
