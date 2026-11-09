# Weekly update articles to Posts — dev notes

Package `weekly-articles-to-posts-2026-11-09.zip` · 2026-11-09 · branch `feat/weekly-articles-to-posts`

## Problem
Articles in a weekly update (Divi 5 `divi/group` modules with class `hs-weekly-article`) are copied into stand-alone Posts by hand: new Post, paste the content, then set the Section, Campus, tags, badge, date and images. This adds a tool for that. Nothing was broken; it is a new feature.

## Change
One new admin-only snippet. It adds an **Articles → Posts** meta box to the `weekly_update` edit screen, listing each article that has an `h3` headline. Editors tick articles, fill in the Post details and click **Create Posts**. Each Post is a one-off copy.

- **Finding articles:** `hs_a2p_articles()` uses `parse_blocks()` on the saved content, with its own `hs_a2p_` copy of the detection in `hs_search_issue_articles()`, so it doesn't depend on the search snippet. Rows are keyed by normalised headline. Older plain-HTML issues aren't covered; the panel says so.
- **Saving the panel:** AJAX actions `hs_a2p_save`, `hs_a2p_create`, `hs_a2p_add_term` and `hs_a2p_recopy`, each with a nonce and capability checks. Rows are stored in post meta `_hs_a2p_rows` on the weekly update. The inputs have no `name` attribute, so the normal Save can't submit them.
- **Creating a Post:** `hs_a2p_create_post()` inserts it as a draft with `wp_slash()`, sets the meta, images and terms, and then, for a Live row, publishes with `wp_update_post()`. The two steps mean the Posts gatekeeper checks the finished Post. The new Post stores `_hs_a2p_source` (`issueID:key`) to prevent duplicates.
- **Post content:** the article group in a plain section, row and column inside `divi/placeholder`, with the first `h3` removed because the template hero shows the title. `core.css` (`.single-post .hs-post-content-sandbox .hs-weekly-article`) draws the card.
- **Required fields:** enforced only for ticked rows, in the panel and in `hs_a2p_missing_fields()`: Title, Section, Campus, at least one tag, Yellow Badge and date, plus Featured image when Live. Location override and portrait are optional.
- **New terms:** tags and badges can be added from the panel, needing the taxonomy's `manage_terms` capability, the same as the Post screen. A custom Section needs `manage_categories`.

**Deliberately unchanged:** no existing snippet, Theme Builder template, rewrite rule or CSS changes, and nothing is added to the front end by the snippet itself. The weekly update's content is never written; the only write to it is the `_hs_a2p_rows` meta. Existing chip logic runs through its existing `set_object_terms` hook.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/weekly-articles-to-posts.code-snippets.php | new | Code Snippet **Weekly-Update--Articles-To-Posts** | — new: deactivate/remove to roll back |

## Watch out for
- Add it as a new Code Snippet set to **Only run in administration area**, then activate it. No activation order, permalinks flush or file in `uploads/hs-head`.
- Installing it needs no cache purge, because the snippet changes nothing public by itself.
- **This tool can publish.** Live is the default on each row, so anything created while checking is public straight away. Use **Draft** for tests.
- Tested on the local site (WordPress 7.1.3, Divi 5.14.0, the 5 Nov database) with WP Super Cache off and no Cloudflare. It has not been run against live's cache setup. The first real Live Post should be checked on the slider and campus pages; if those lists are stale compared with publishing by hand, please tell us.
- The local editor needs a local-only guard for the existing `Weekly-Update--Divi-Gatekeeper` script, which throws on `removeEditorPanel` there. You've confirmed live is fine, so it isn't in this package. If the weekly update editor on live shows this panel area blank or misbehaving, tell us.
- Please check the existing badge list: the local copy has a badge named **Test**, which would appear in the panel's dropdown if it's on live too.
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.

## Quick check after deploy (optional)
Open any recent weekly update in wp-admin. The editor loads normally and **Articles → Posts** appears in the meta boxes under the content, listing its articles. Tick one, fill it in, set it to **Draft**, click **Create Posts**, check the draft in Posts, then trash it. An older plain-HTML update should show a message saying the tool doesn't cover it.

## Previous version
This is a new snippet with no previous version. To roll back, deactivate it. Posts already created stay as ordinary Posts, and the meta keys `_hs_a2p_rows` and `_hs_a2p_source` do nothing without it.
