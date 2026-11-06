# Plan: Weekly update articles to draft Posts

- Task: `feat/weekly-articles-to-posts` · opened 2026-11-06
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-05`
- Roadmap: not linked yet (the roadmap artifact could not be read from this session; add the item when it is reachable)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
Every weekly update contains several articles (Divi 5 groups with class `hs-weekly-article`). Some of them should also exist as stand-alone Posts, and today each one is copied out by hand: new Post, paste the content, set the Section, Campus, tags and date. Goal: after a weekly update is published, open it, tick the articles that should become Posts, fill in their Post details in one panel, and click **Create drafts**. Each ticked article becomes a **draft** Post with its Divi 5 content and details already set. The editor then adds the featured image (and portrait, if used) and publishes as normal.

Decisions agreed with the user (6 Nov):
- The new Posts are created as drafts for review; nothing goes live automatically.
- Each Post is a one-off copy. Editing the weekly update later never changes the Post.
- The weekly update itself is never changed: no links are added and its content is never re-saved.
- The tool never sets the featured image or portrait image; the editor sets those by hand.
- No excerpt (Posts don't use one).
- The fields live in a panel on the weekly update's edit screen, not inside the Divi builder (that would need a separate built Divi 5 extension, which is fragile across Divi updates).

## Proposed changes
### New file: `Site-Scripts/php/weekly-articles-to-posts.code-snippets.php` (new live snippet "Weekly-Update--Articles-To-Posts", admin only)
1. **Finding the articles.** It reads the weekly update's saved Divi 5 content with `parse_blocks()` and lists each `divi/group` with class `hs-weekly-article`: its headline (first `h3`), its section (the preceding `h2.hs-section`, else the Divi section's admin label) and its position. This is the same detection `hs_search_issue_articles()` uses for site search. The code is a separate copy with its own `hs_a2p_` prefix, so neither snippet depends on the other being active. Older imported issues (plain-HTML `<article class="hs-weekly-article">`) are listed too, but marked "plain HTML": their content is copied as one Text module.
2. **Recognising the same article after edits.** Articles are keyed by the group's HTML id when it has one; otherwise by its normalised headline, with its position added when two headlines are the same. Nothing is written into the weekly update. If a saved row's article can no longer be found (headline renamed or article deleted), the panel keeps the row and shows "Article not found", rather than dropping it or attaching it to another article.
3. **Panel** (a meta box under the builder on `weekly_update` edit screens, shown once the update has content). One row per article: a **Make this a Post** tick, then for ticked rows:
   - **Title:** pre-filled from the headline, editable.
   - **Website Section** (`hs_department`): a dropdown of existing Sections, pre-selected by matching the article's section name (exact name, then a small alias list such as Sports → Sports Academies). It ends with a **Custom…** option: type a new Section name, which is created as a new Section term when the drafts are made.
   - **Campus** (`hs_post_campus`): Chaofah / Cherngtalay checkboxes, pre-ticked from the weekly update's campus (`hs_campus`, matched by slug, then name). Both can be ticked; the existing chip logic then shows "Both Campuses".
   - **Thematic Tags** (`post_tag`): type freely, with existing tags suggested.
   - **Yellow Badge Override** (`hs_thematic_chip`): optional, same style as the tags.
   - **Publish date:** a date and time picker, defaulting to the weekly update's date.
   - A read-only note: "Featured image: set it on the Post."

   The panel saves on its own through `admin-ajax`, with a nonce check and `edit_post` capability on the weekly update. It stores everything in post meta `_hs_a2p_rows` on the weekly update and never triggers a save of the weekly update. Saving a published weekly update with the normal Update button carries on as today and ignores the panel.
4. **Create drafts** (a button in the panel; needs `edit_posts`, plus `manage_categories` for a Custom Section). For each ticked row that has no Post yet, it calls `wp_insert_post` with:
   - post type `post`, status `draft`, the title, and the chosen date with both `post_date` and `post_date_gmt` set, so WordPress keeps the date when the Post is published later instead of using the publish day;
   - content: the article group wrapped in a Divi 5 section, row and column, inside the `divi/placeholder` wrapper; the article's own blocks, images and gallery attributes copied unchanged;
   - `_et_pb_use_builder = on` and the same `_et_builder_version` as the weekly update, so the Post opens in Divi 5;
   - the taxonomies set through the same ACF fields the Post screen uses (`hs_department_tax`, `hs_campus_tax`, `post_tag_tax`, `hs_thematic_chip_tax`), so `hs_compute_final_divi_chips` and the other `save_post` logic run exactly as they do for a hand-made Post;
   - source meta `_hs_a2p_source` = weekly update ID + article key, and the new Post's ID written back into that row.

   Afterwards the row shows **Draft created → Edit** (or **Published → View** once it is live) and can't be created twice. A **Re-copy** action overwrites that Post's content from the article, after an "overwrite?" confirmation; its fields stay as they are. Rows with errors (missing Section or Campus) are skipped and named; the other rows still go through.
5. **Posts list.** A small "From weekly update: Week N, Campus" note on the Post's edit screen, linking back to the source.

Inline admin JS and CSS live in the same snippet: one file, no build step, nothing on the front end. No rewrite rules, no front-end output, no change to existing snippets.

### New file: `work/weekly-articles-to-posts/tests/wp-tests.php` (local only)
Run with `wp eval-file` (see Testing).

### Build step 0 (before any code)
With Local running, inspect three or four real weekly updates (Divi 5 and imported). Confirm the group, class, `h3` and section structure, whether the groups carry HTML ids, and which `_et_builder_version` / placeholder wrapper a Divi 5 Post uses. If the structure differs from the above in a way that changes this plan, the plan is updated and re-approved.

## Risks & rollback
- **Content copied in a shape Divi 5 doesn't accept** (wrong wrapper or version meta) means the Post opens empty or the builder complains. Mitigations: build step 0; a test that creates a Post and renders it; drafts only, so nothing broken goes public.
- **The Posts gatekeeper** blocks the Divi builder on a draft until the featured image is set. This is expected and matches "you set the image", but the UAT checks the message is clear.
- **Website Section drives Post routing** (`/school-life/...`), so a Custom Section changes URLs for Posts in it. Custom Sections need `manage_categories` (admins/editors only) and are named in the result message.
- **Wrong article matched after heavy edits.** Mitigated by id/headline keys and the "not found" state; the tool never guesses.
- **Duplicate content in search engines.** Article and Post are both public (agreed). Search indexing: drafts are not indexed; once published, the Post appears in site search like any Post.
- **Caching:** nothing public changes when drafts are made; publishing a Post behaves like any other Post.
- **Rollback:** deactivate the snippet "Weekly-Update--Articles-To-Posts". Drafts already created remain as ordinary draft Posts (trash them if unwanted). The meta keys `_hs_a2p_rows` and `_hs_a2p_source` are inert without the snippet.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` (php -l with Local's PHP 8.2) on the new snippet.
- `wp eval-file work/weekly-articles-to-posts/tests/wp-tests.php` on headstart.local, using a temporary weekly update built from a fixture of real Divi 5 article markup, deleted afterwards:
  - article detection: count, headlines, sections, keys; plain-HTML issue fallback;
  - key stability: content edited (text changed, an article added above) still matches the right rows; a renamed headline → "not found";
  - campus and section mapping, including Both and the alias list;
  - Create drafts: status draft; title; date kept (`post_date_gmt` set); taxonomies and chips equal those of a hand-saved Post; `_et_pb_use_builder` on; content parses as valid blocks containing the article; second run makes no duplicate; Re-copy replaces content only;
  - permissions: an Author-level user can't create a Custom Section; a bad nonce → 403.
- Endpoints: `curl` admin-ajax without a nonce → 403 / `-1`; the front-end preview of a created draft (logged in) → 200 with the article headline in the HTML.

### UAT checklist for the user (Gate 5)
- [ ] Open a published weekly update in wp-admin: the "Articles → Posts" panel lists every article with the right headline and section.
- [ ] Tick two articles (one from each of two sections) and check the pre-filled Title, Section, Campus and date.
- [ ] Change one Section using **Custom…**, tick both campuses on the other, add tags and a badge override, and set a different publish date.
- [ ] Reload the page: the ticks and fields are still there, and the weekly update itself shows no "unsaved changes" and is unchanged on the front end.
- [ ] Click **Create drafts**: two drafts appear in Posts with the right title, Section, Campus, tags, badge and date.
- [ ] Open each draft: the content looks like the article (text, images, gallery lightbox in preview), and the gatekeeper asks for the featured image.
- [ ] Add a featured image and publish one: its URL, date and slider chip (incl. "Both Campuses") are right.
- [ ] Back on the weekly update, the rows show Draft / Published with links, and clicking Create drafts again makes no duplicates.
- [ ] Edit the article text in the weekly update: the Post doesn't change; **Re-copy** updates it after the confirmation.

## Deployment notes for the dev (Gate 7)
- **Add** a new Code Snippet "Weekly-Update--Articles-To-Posts" from `files/php/weekly-articles-to-posts.code-snippets.php`, set to "Only run in administration area", and activate it. No existing snippet changes.
- No permalinks flush; no cache purge needed (admin only, nothing public changes).
- Smoke: open one weekly update in wp-admin → the panel shows its articles. Create one draft from a test row, check it, then trash it.
