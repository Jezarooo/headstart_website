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

Assumed, to be confirmed at approval:
- **Readable links**, not short codes: `/go/wa/admissions` and `/go/fb/admissions`.
- **Scope:** published Pages and Posts only. Events, notices and other post types are left out; weekly updates keep their own link format.

## Proposed changes
**Changed file: `Site-Scripts/php/weekly-update-short-links.code-snippets.php`** (live snippet 66, `Weekly-Update--Short-Links`). All existing code stays as it is: the weekly update route, counter, share box, Clicks column and GTM data. The additions below use the same `hs_sl_` prefix and reuse `hs_sl_is_bot()`.

1. **New table `{prefix}hs_share_links`**, created once on `admin_init` with `dbDelta` and a version option `hs_share_links_db`, the same pattern as the search log.
   - Columns: `post_id` (primary key), `slug` (unique), `whatsapp` (count), `facebook` (count).
   - One row per Page or Post, so nothing is added to post meta and no personal data is stored.
   - Counts use `SET whatsapp = whatsapp + 1` in a single query, so clicks at the same moment can't overwrite each other.
2. **Link names that don't change.** A Page or Post gets its link name the first time its edit screen is opened while it is published, and the name is stored in the table. Links work from that point.
   - Taken from the slug: `admissions`. A child page includes its parents, joined with hyphens: `cherngtalay-admissions`. A clash gets `-2`.
   - Renaming a page later doesn't change its link name, and the redirect always goes to the current permalink, so links already shared in WhatsApp keep working.
3. **New redirect.** A separate `parse_request` handler matches only `^/go/(wa|fb)/([a-z0-9-]+)/?$`. No rewrite rules, so no permalink flush.
   - Looks up the link name, then checks the item is still a published Page or Post.
   - Counts the click on GET only, excluding bots and link previews, as weekly updates do.
   - Sends a 302 with `no-store` and `X-Robots-Tag: noindex` to the permalink, plus `utm_source=whatsapp|facebook`, `utm_medium=social`, `utm_campaign=page-share` or `post-share`, `utm_content={link name}`.
   - An unknown link name, or an item that is unpublished, trashed or password-protected, gets the normal WordPress 404.
4. **"Share links" box** on Page and Post edit screens: both links, copy buttons and counts, matching the weekly update box. Before publishing it shows "Links appear once this is published".
5. **"Clicks" column** in the Pages and Posts lists (`WA 3 · FB 1`). Counts are loaded once per list screen rather than once per row.

**New file: `work/share-links-all-pages/tests/wp-tests.php`** (local only), run with `wp eval-file`.

No GTM changes: GA4 picks up the UTM tags without any new tags.

### Build step 0 (before any code)
With Local running, confirm that no page, post or redirect already uses `/go/`, and check that the Share links box displays on Page and Post edit screens that use Divi 5. If either result changes this plan, it is updated and re-approved.

## Risks & rollback
- **Weekly update links breaking:** the weekly handler isn't edited, and the new pattern can't match its URLs. The automated checks below re-test those links.
- **Existing `/go/` URLs:** anything already at `/go/wa/…` or `/go/fb/…` would be taken over. Only those two prefixes are matched, so `/go/` or any other path below it is untouched. Checked in step 0 locally; live is checked with the fresh export where possible and by the smoke checks.
- **Links not existing until the edit screen is opened:** a page nobody has opened has no link to share, so nothing can be broken by this. The box is the only place that shows links.
- **Caching:** 302 + `no-store`, as weekly updates do, so every click should reach WordPress. To be confirmed through Cloudflare after deploy.
- **Counts are close, not exact:** bot filtering is best-effort, repeat clicks count each time, and forwarded links keep their original channel.
- **Social previews:** WhatsApp and Facebook follow the 302 and read the Yoast tags on the page. Checked after deploy because it can't be tested locally.
- **PDPA:** the click counter stores no personal data. UTM visits add to GA4 data collected without consent mode until the consent banner (roadmap P2) is done.
- **Nick's live edits:** snippet 66 may change on live before this ships. A fresh export is compared before handover.
- **Rollback:** restore the previous code of snippet 66 from `rollback/`. Weekly update links keep working; `/go/` links 404 until the feature is restored. The table stays and is inert, so counts survive a rollback.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the changed snippet.
- `wp eval-file work/share-links-all-pages/tests/wp-tests.php`, using temporary items deleted afterwards: link names, child page names, clashes, a name surviving a rename, atomic counts, and the skip rules.
- On `http://headstart.local`:
  - `/go/wa/{page}` → 302 to the permalink with `utm_source=whatsapp&utm_medium=social&utm_campaign=page-share&utm_content={page}`;
  - `/go/fb/{post}` → 302 with `utm_source=facebook` and `utm_campaign=post-share`;
  - after a slug rename, the old link → 302 to the new permalink;
  - `/go/wa/does-not-exist`, `/go/xx/{page}`, and a draft's link → 404;
  - a WhatsApp user agent and a HEAD request → 302, count unchanged;
  - regression: `/cherngtalay/2026-27/weekly-update-7` → 302 with `utm_source=whatsapp`, `/cherngtalay/26-27/weekly-update-7` → 302 with `utm_source=facebook`, and the weekly update's saved count goes up as before;
  - still 200: the week 7 permalink, `/cherngtalay/`, a Page, a Post.

### UAT checklist for the user (Gate 5)
- [ ] Edit a published Page: the Share links box shows the WhatsApp and Facebook links, and the copy buttons work.
- [ ] Same for a published Post.
- [ ] A draft shows "Links appear once this is published".
- [ ] Open each link in a browser: it lands on the right page and its count goes up by 1 after refreshing the edit screen.
- [ ] The Pages and Posts lists show the Clicks column.
- [ ] Change a page's slug: the link you copied earlier still works and lands on the new URL.
- [ ] A weekly update's existing WhatsApp and Facebook links still work, and its box and column look unchanged.
- [ ] Paste a Page link and a Post link into WhatsApp and Facebook: the preview shows the right title and image. *(Live only, after deploy.)*

## Deployment notes for the dev (Gate 7)
- **Replace the code of existing snippet 66** (`Weekly-Update--Short-Links`, run everywhere) with `files/php/weekly-update-short-links.code-snippets.php`. No new snippet.
- **Load any wp-admin page once** after saving, which creates the table.
- **No permalinks flush needed** (no rewrite rules).
- **Purge WP Super Cache and Cloudflare APO.**
- Smoke: open a Page in wp-admin and copy its links. Each should return 302 with `cache-control: no-store` through Cloudflare. An existing weekly update link should also still return 302.
