# Plan: Share links and click counts for all pages and posts

- Task: `feat/share-links-all-pages` · opened 2026-11-09
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-05`
- Roadmap: not linked yet (the roadmap artifact could not be opened from this session on 9 Nov; add the item when it is reachable)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
Weekly updates have WhatsApp and Facebook short links with a click count per channel (`short-links`, live as snippet 66). Pages and Posts have nothing, so we can't tell how much traffic each channel sends them. WhatsApp gives no link-click data at all, and Facebook Insights only covers posts on our own Page. Goal: every published Page and Post gets a WhatsApp link and a Facebook link, with click counts visible in WordPress and UTM tags that feed GA4. Weekly update links keep working exactly as they do today.

Decisions (user, 9 Nov):
- Build this into the existing short-links snippet and redeploy it as a whole, not as a separate snippet.
- Existing weekly update links must keep working.
- **Posts** follow the weekly update pattern, with the school year form picking the channel and the full slug kept:
  - WhatsApp `/chaofah/2025-26/chaofah-senior-prom-2026`
  - Facebook `/chaofah/25-26/chaofah-senior-prom-2026`
- **Pages** have no date, so the channel comes first, followed by the page's full path:
  - WhatsApp `/wa/resources-and-information/tuition-fees`
  - Facebook `/fb/th/foundation`
  - Homepage `/wa` and `/fb`

Assumed, to be confirmed at approval:
- **Scope:** published Pages and Posts only. Events, notices and other post types are left out; weekly updates keep their own links.
- **School year:** the same August cut-off as weekly updates, from the Post's publish date.

## Proposed changes
**Changed file: `Site-Scripts/php/weekly-update-short-links.code-snippets.php`** (live snippet 66, `Weekly-Update--Short-Links`). All existing code stays as it is: the weekly update route, counter, share box, Clicks column and GTM data. The additions below use the same `hs_sl_` prefix and reuse `hs_sl_is_bot()` and `hs_sl_school_year()`.

1. **New table `{prefix}hs_share_links`** for click counts only, created once on `admin_init` with `dbDelta` and a version option `hs_share_links_db`, the same pattern as the search log.
   - Columns: `post_id` (primary key), `whatsapp` (count), `facebook` (count).
   - A row is added on an item's first click, using `INSERT … ON DUPLICATE KEY UPDATE` in a single query, so clicks at the same moment can't overwrite each other.
   - No personal data is stored. Weekly update counts stay in their existing post meta.
2. **New redirects.** A separate `parse_request` handler runs after the weekly update handler, which isn't edited. No rewrite rules, so no permalink flush.
   - **Posts:** matches `^/([a-z0-9-]+)/(\d{4}|\d{2})-(\d{2})/([^/]+)/?$` with a valid school year. The 4-digit form means WhatsApp and the 2-digit form means Facebook. The first part isn't limited to `chaofah` and `cherngtalay`, because `posts-routing-engine` can also give a Post `both-campuses`, `phuket` or a custom location. Share links use the same first part as the Post's permalink.
     - Looks up the published Post by its exact slug. Post slugs are unique across all 730, so the lookup can't land on the wrong one.
     - If the slug isn't found, it tries WordPress's own old-slug record (`_wp_old_slug`), so links survive a rename.
     - Campus and year aren't enforced at click time, so links already shared keep working if a Post's campus or date is edited later.
   - **Pages:** matches `^/(wa|fb)(/.+)?$`.
     - Looks up the published Page by its full path, or uses the homepage when there is no path.
     - If the path isn't found, it tries the page's old paths (item 3).
   - **Both:** count the click on GET only, excluding bots and link previews. They send a 302 with `no-store` and `X-Robots-Tag: noindex` to the current permalink, plus `utm_source=whatsapp|facebook`, `utm_medium=social`, `utm_campaign=post-share|page-share`, and `utm_content` set to the slug (Posts) or the path with hyphens (Pages, e.g. `en-foundation`).
   - Nothing found, or an item that is unpublished, trashed or password-protected, gets the normal WordPress 404.
3. **Page renames.** WordPress doesn't keep old slugs for Pages, so when a published Page's path changes, `post_updated` saves the old path in post meta `_hs_sl_old_path`, for that Page and its child Pages. Shared links then keep working. An old path is only used if no current Page has that path.
4. **"Share links" box** on Page and Post edit screens: both links, copy buttons and counts, matching the weekly update box. Before publishing it shows "Links appear once this is published".
5. **"Clicks" column** in the Pages and Posts lists (`WA 3 · FB 1`). Counts are loaded once per list screen rather than once per row.

**New file: `work/share-links-all-pages/tests/wp-tests.php`** (local only), run with `wp eval-file`.

No GTM changes: GA4 picks up the UTM tags without any new tags.

### Build step 0 (before any code)
With Local running, check that the Share links box displays in the Gutenberg sidebar on Page and Post edit screens, the same place as the weekly update box. Pages, Posts and weekly updates all use the block editor, and the Posts gatekeeper only hides its own taxonomy panels. If this changes the plan, it is updated and re-approved.

Already checked on 9 Nov, on the local database (newest Post 26 June 2026, so live has more): no Page or Post uses `wa` or `fb` as a slug, none of the 51 Pages or 730 Posts has a school year as the second part of its URL, and post slugs are all unique. `/en/…` and `/th/…` Pages resolve by full path, even though their `en` and `th` parent pages are drafts, so only the linked item's own status is checked.

## Risks & rollback
- **Weekly update links breaking:** the weekly handler isn't edited and runs first. The automated checks below re-test those links.
- **The Post pattern is broad:** it matches any `/{word}/{school-year}/{anything}`. No current URL has this shape, and if no Post is found the request carries on to WordPress as normal, so a future page at such a URL would still load unless a Post has the same slug.
- **`/wa` and `/fb` become reserved:** a future top-level page called `wa` or `fb` would never load. Nothing uses them today, and live is covered by the smoke checks.
- **Links are long:** nested pages and long post slugs (median 55 characters, longest 101) make long links. This was accepted in favour of links that stay readable and can't clash. WhatsApp and Facebook both show the preview card, so length matters little in practice.
- **Homepage redirect:** `/wa` and `/fb` redirect to `/` with the UTM tags, so live's own homepage routing (Apache, not WordPress) still decides where the visitor lands. Locally, a local-only mu-plugin (`headstart-local-redirect.php`) sends `/` to `/en/our-school/` and drops the tags, which says nothing about live. Whether Apache keeps the tags can only be checked after deploy. If it doesn't, clicks still count in WordPress, but GA4 won't credit those homepage visits to the channel.
- **Language redirect drops UTM tags:** a visitor whose saved language preference differs from the Page's language is redirected to the translated Page by `language-switcher-engine` (`get_permalink`, no query string). The click is still counted in WordPress, but GA4 sees that visit without its UTM tags. Accepted for now: this affects only visitors who have switched language and Pages with a translation, and it leaves that snippet untouched. Posts aren't affected.
- **Caching:** 302 + `no-store`, as weekly updates do, so every click should reach WordPress. To be confirmed through Cloudflare after deploy.
- **Counts are close, not exact:** bot filtering is best-effort, repeat clicks count each time, and forwarded links keep their original channel.
- **Social previews:** WhatsApp and Facebook follow the 302 and read the Yoast tags on the page. Checked after deploy because Yoast isn't on the local site.
- **PDPA:** the click counter stores no personal data. UTM visits add to GA4 data collected without consent mode until the consent banner (roadmap P2) is done.
- **Nick's live edits:** snippet 66 may change on live before this ships. A fresh export is compared before handover.
- **Rollback:** restore the previous code of snippet 66 from `rollback/`. Weekly update links keep working; Page and Post share links 404 until the feature is restored. The table and `_hs_sl_old_path` stay and are inert, so counts survive a rollback.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the changed snippet.
- `wp eval-file work/share-links-all-pages/tests/wp-tests.php`, using temporary items deleted afterwards: link building for each channel, school year either side of the August cut-off, a first click and repeat clicks, renamed Posts and Pages, a renamed parent Page, and the skip rules.
- On `http://headstart.local`:
  - `/wa/resources-and-information/tuition-fees` → 302 to the permalink with `utm_source=whatsapp&utm_medium=social&utm_campaign=page-share`;
  - `/fb/th/foundation` and `/wa/en/foundation` → 302 to the matching language's page;
  - `/wa/chaofah/campus-hub` and `/wa/cherngtalay/campus-hub` → 302 to the matching campus's page;
  - `/wa` and `/fb` → 302 to `/` with the UTM tags (locally the next redirect drops them, as expected);
  - a real Post's `/{campus}/{yyyy-yy}/{slug}` → 302 with `utm_source=whatsapp` and `utm_campaign=post-share`; the `/{campus}/{yy-yy}/{slug}` form → 302 with `utm_source=facebook`;
  - a temporary Post with two campuses → its links start `/both-campuses/…` and return 302;
  - a renamed Post's and Page's old link → 302 to the new permalink;
  - `/wa/does-not-exist`, `/chaofah/2025-26/does-not-exist`, `/chaofah/2025-99/{slug}` and a draft's link → 404;
  - a WhatsApp user agent and a HEAD request → 302, count unchanged;
  - regression: `/cherngtalay/2026-27/weekly-update-7` → 302 with `utm_source=whatsapp`, `/cherngtalay/26-27/weekly-update-7` → 302 with `utm_source=facebook`, and the weekly update's saved count goes up as before;
  - still 200: the week 7 permalink, a Page, a Post. `/cherngtalay/` keeps its current 301 to the homepage (seen 9 Nov, before any of this work).

### UAT checklist for the user (Gate 5)
- [ ] Edit a published Page: the Share links box shows the WhatsApp and Facebook links, and the copy buttons work.
- [ ] Edit a published Post: its links show the right campus and school year (`2025-26` for WhatsApp, `25-26` for Facebook).
- [ ] A draft shows "Links appear once this is published".
- [ ] Open each link in a browser: it lands on the right page and its count goes up by 1 after refreshing the edit screen.
- [ ] The Pages and Posts lists show the Clicks column.
- [ ] Change a Page's slug and a Post's slug: the links you copied earlier still work and land on the new URLs.
- [ ] A weekly update's existing WhatsApp and Facebook links still work, and its box and column look unchanged.
- [ ] Paste a Page link and a Post link into WhatsApp and Facebook: the preview shows the right title and image. *(Live only, after deploy.)*

## Deployment notes for the dev (Gate 7)
- **Replace the code of existing snippet 66** (`Weekly-Update--Short-Links`, run everywhere) with `files/php/weekly-update-short-links.code-snippets.php`. No new snippet.
- **Load any wp-admin page once** after saving, which creates the table.
- **No permalinks flush needed** (no rewrite rules).
- **Purge WP Super Cache and Cloudflare APO.**
- Smoke: open a Page and a Post in wp-admin and copy their links. Each should return 302 with `cache-control: no-store` through Cloudflare. An existing weekly update link should also still return 302. Follow `/wa` all the way through and report whether the final URL still has its UTM tags.
