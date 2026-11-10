# Articles to Posts: yellow badge defaults to the Website Section — dev notes

Package `a2p-badge-override-2026-11-10.zip` · 2026-11-10 · branch `fix/a2p-badge-override`

**This package replaces `weekly-articles-to-posts-2026-11-09.zip`. Don't install that one.** If you already installed it, replace the snippet's code with the version here.

## Problem
1. **Yellow badge.** The 9 Nov Articles to Posts snippet made editors pick a Yellow Badge from a list, and that badge replaced the Website Section in the chip. On the site the yellow badge *is* the Website Section: every published Post shows its Section there, and the badge field (`hs_thematic_chip`) is only an optional override. The list came from `hs_seed_default_thematic_chips()` in **Posts--Register-Taxonomies**, which runs on `init` and recreates 14 default terms (Spotlight, Academics, …) whenever any is missing.
2. **Card padding (core.css).** On Posts made by the tool, the article card had no bottom padding. The Post card rule `.single-post .hs-post-content-sandbox .hs-weekly-article { padding: 2rem 2.5rem }` was overridden by the weekly-update separator rule `.hs-post-content-sandbox .et_pb_group.hs-weekly-article { padding-bottom: 0 !important }`. Imported Posts use plain-HTML `<article>` tags, so only Divi-group articles (the tool's Posts) were hit.

## Change
- **weekly-articles-to-posts** (new snippet "Weekly-Update--Articles-To-Posts"): the badge dropdown is now one optional text box, **Yellow Badge Override**. Empty sets no `hs_thematic_chip` term, so the existing chip logic shows the Section. Typed text reuses an existing term ignoring capitals (`term_exists()`), or creates one when the Post is made; creating needs the taxonomy's `manage_terms`, otherwise that row is skipped and named. The badge is no longer a required field. `hs_a2p_add_term()` now handles tags only. Everything else is as described in the 9 Nov notes.
- **posts-register-taxonomies:** section 5, `hs_seed_default_thematic_chips()` and its `init` hook, is removed. The `hs_thematic_chip` taxonomy itself stays; the Post screen's override field, chip logic, slider, gatekeeper and admin columns read whatever terms exist.
- **core.css:** one added Posts-only rule after the Post card rules: `.single-post .hs-post-content-sandbox .et_pb_group.hs-weekly-article { padding-bottom: 2rem !important }` (1.5rem at ≤768px). Weekly update pages (`single-weekly_update`) and imported Posts don't match it.

**Deliberately unchanged:** no rewrite rules, no Theme Builder templates, no published Post's badge (none has one).

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/posts-register-taxonomies.code-snippets.php | modified | Code Snippet **Posts--Register-Taxonomies** | rollback/php/posts-register-taxonomies.code-snippets.php |
| files/php/weekly-articles-to-posts.code-snippets.php | new on live | Code Snippet **Weekly-Update--Articles-To-Posts** | — new: deactivate to roll back |
| files/css/core.css | modified | Global stylesheet — **Jeremy adds this rule himself; reference only** | rollback/css/core.css |

## Deploy steps, in this order
1. **Replace** the code of **Posts--Register-Taxonomies** with `files/php/posts-register-taxonomies.code-snippets.php`.
2. **Then delete the badges** under Posts → Thematic Chips: Spotlight, School Life, Community, Academics, Student Success, Campus News, Event, Co-Curricular, Creativity, Innovation, Well-being, Leadership, Perspectives, Competition, and `Test` if it's there. Leave any with a count above 0 and tell us. Reload the page: they should not come back. (If they do, step 1 didn't take.)
3. **Add** a new Code Snippet **Weekly-Update--Articles-To-Posts** from `files/php/weekly-articles-to-posts.code-snippets.php`, set to **Only run in administration area**, and activate it.

## Watch out for
- **core.css is not yours to deploy.** Jeremy adds the padding rule to the live stylesheet himself; the file is included so you can see the change. Don't paste the whole file over live's.
- No permalinks flush. The snippets need no cache purge; the CSS does (Jeremy purges WP Super Cache and Cloudflare APO after adding it).
- **This tool can publish.** Live is the default on each row. Use **Draft** for tests.
- Order matters: deleting the badges before step 1 just lets the old code recreate them.
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.

## Quick check after deploy (optional)
Open a recent weekly update in wp-admin → **Articles → Posts**. Tick one article: there's no badge list, only an optional **Yellow Badge Override** text box. Fill in the rest, set **Draft**, leave the override empty, click **Create Posts**. Preview the draft via **View Post** (not Preview, which reads the autosave and shows blank chips): the yellow chip shows the Website Section. Trash the draft.

## Previous version
`rollback/` holds the live files as they were before this change. To roll back: restore **Posts--Register-Taxonomies** from `rollback/` (it recreates the 14 badges by itself on the next page load) and deactivate **Weekly-Update--Articles-To-Posts**. Posts already created stay as ordinary Posts.
