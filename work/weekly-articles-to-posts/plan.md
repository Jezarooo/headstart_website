# Plan: Weekly update articles to draft Posts

- Task: `feat/weekly-articles-to-posts` · opened 2026-11-06
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-09`
- Roadmap: item `weekly-articles-to-posts`
- Refreshed 9 Nov against the 9 Nov backup and the local database (5 Nov import); see "What the survey found"

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

## What the survey found (9 Nov)
This replaces the old "build step 0": real issues were inspected read-only before this revision. The local database is the 5 Nov import.
- **174 published weekly updates: 18 are Divi 5** (since 26 Aug 2026), holding 355 article groups. The other 156 are older plain HTML.
- **No article has an HTML id**, so the headline is the real key. The `id` inside each group's class attribute repeats within all 18 issues, so it can't be used.
- **No issue repeats a headline** across the 334 articles that have one. The other **21 have no `h3`**; those sampled are nearly all image-only promotions.
- **Section headings are free text**: 44 spellings, including capitals and typos ("Langauges Department", "sCHOOL hOUSES"). Many are not Sections at all (Upcoming Events, Announcements, Promotions). Every Divi section's admin label is "Weekly Update", so that fallback is useless and is removed.
- **`_et_builder_version` is empty** on weekly updates and Posts. The version lives in each block's `builderVersion`, so the meta is not set. `_et_pb_use_builder = on` and the `divi/placeholder` wrapper are confirmed.
- **Only 2 Posts use Divi 5 blocks, both drafts; the published Posts sampled are plain HTML.** A published Divi 5 Post has not been seen on the Post template yet, so this is the first thing the build proves (see Build step 1).
- **Parsing then re-saving the blocks is lossless** on all 18 issues: `serialize_blocks( parse_blocks() )` returned the same text.
- **Site search already merges a Post with its article** when the headlines match (`hs_search_headline_key`), so an unchanged title won't show twice.
- **The Yellow Badge is a fixed list** (a single-choice select), not free text. Thematic Tags do allow new terms. Section allows several on a Post; the panel sets one, and the editor can add more on the Post.

## Proposed changes
### New file: `Site-Scripts/php/weekly-articles-to-posts.code-snippets.php` (new live snippet "Weekly-Update--Articles-To-Posts", admin only)
1. **Finding the articles.** It reads the weekly update's saved Divi 5 content with `parse_blocks()` and lists each `divi/group` with class `hs-weekly-article`: its headline (first `h3`), its section (the preceding `h2.hs-section`) and its position. This is the same detection `hs_search_issue_articles()` uses for site search. The code is a separate copy with its own `hs_a2p_` prefix, so neither snippet depends on the other being active. Groups with no `h3` aren't listed; the panel shows how many were left out. **Divi 5 issues only:** on an older plain-HTML issue the panel just says the tool doesn't cover it.
2. **Recognising the same article after edits.** Articles are keyed by their normalised headline, with a counter added if an issue ever repeats one. Nothing is written into the weekly update. If a saved row's article can no longer be found (headline renamed or article deleted), the panel keeps the row and shows "Article not found", rather than dropping it or attaching it to another article.
3. **Panel** (a meta box under the builder on `weekly_update` edit screens, shown once the update has content). One row per article: a **Make this a Post** tick, then for ticked rows:
   - **Title:** pre-filled from the headline, editable.
   - **Website Section** (`hs_department`): a dropdown of existing Sections, pre-selected by matching the article's section heading, ignoring capitals (exact name, then the alias list below). If nothing matches it stays empty and the editor must choose; the tool never guesses. It ends with a **Custom…** option: type a new Section name, which is created as a new Section term when the drafts are made.
   - **Campus** (`hs_post_campus`): Chaofah / Cherngtalay checkboxes, pre-ticked from the weekly update's campus (`hs_campus`, matched by slug, then name). Both can be ticked; the existing chip logic then shows "Both Campuses".
   - **Thematic Tags** (`post_tag`): type freely, with existing tags suggested.
   - **Yellow Badge Override** (`hs_thematic_chip`): optional, a dropdown of the existing badges, one choice, like the Post screen.
   - **Publish date:** a date and time picker, defaulting to the weekly update's date.
   - A read-only note: "Featured image: set it on the Post."

   **Alias list** (heading → Section): PE & Sports, Academy News, Sports & Academies → Sports Academies; ECA News → ECA; CAPA News → CAPA; Thai Department → Thai; Languages Department (and the "Langauges" typo) → World Languages; House Update → School Houses; Alumni Network → HeadStart Alumni. Everything else (Student Voice, Sustainability, Library News and so on) starts empty.

   Ticks and fields start empty, because many articles are events or notices that never become Posts. The panel saves on its own through `admin-ajax`, with a nonce check and `edit_post` capability on the weekly update. Its inputs have no `name` attribute, so the normal Update button can't submit them. It stores everything in post meta `_hs_a2p_rows` on the weekly update and never triggers a save of the weekly update. Saving a published weekly update with the normal Update button carries on as today and ignores the panel.
4. **Create drafts** (a button in the panel; needs `edit_posts`, plus `manage_categories` for a Custom Section). For each ticked row that has no Post yet, it calls `wp_insert_post` with:
   - post type `post`, status `draft`, the title, and the chosen date with both `post_date` and `post_date_gmt` set, so WordPress keeps the date when the Post is published later instead of using the publish day;
   - content: the article group inside the same section, row and column it sits in on the weekly update (their settings copied, so it looks the same), inside the `divi/placeholder` wrapper; the article's own blocks, images and gallery attributes copied unchanged. The content goes through `wp_slash()` first, because `wp_insert_post` strips backslashes and would otherwise break Divi's `<` encoding;
   - `_et_pb_use_builder = on`, so the Post opens in Divi 5;
   - the taxonomies set with `wp_set_object_terms` after the insert (`hs_department`, `hs_post_campus`, `post_tag`, `hs_thematic_chip`). The Post screen's ACF fields read those terms directly (`load_terms`), and the existing `set_object_terms` hook recomputes the chips, so the result matches a hand-made Post without depending on ACF's save flow;
   - source meta `_hs_a2p_source` = weekly update ID + article key, and the new Post's ID written back into that row.

   Afterwards the row shows **Draft created → Edit** (or **Published → View** once it is live) and can't be created twice. A **Re-copy** action overwrites that Post's content from the article, after an "overwrite?" confirmation; its fields stay as they are. Rows with errors (missing Section or Campus) are skipped and named; the other rows still go through.
5. **Posts list.** A small "From weekly update: Week N, Campus" note on the Post's edit screen, linking back to the source.

Inline admin JS and CSS live in the same snippet: one file, no build step, nothing on the front end. No rewrite rules, no front-end output, no change to existing snippets.

### New file: `work/weekly-articles-to-posts/tests/wp-tests.php` (local only)
Run with `wp eval-file` (see Testing).

### Build step 1 (before the panel)
Prove the riskiest part first, with a throwaway script: copy one real article with a gallery into a draft Post on `headstart.local`, publish it, and check the Post template in a browser (text, images, gallery lightbox, chips, hero). Also confirm it opens in the Divi builder. If a published Divi 5 Post doesn't display properly, stop: the plan is updated and re-approved before the panel is built.

## Risks & rollback
- **Content copied in a shape Divi 5 doesn't accept** (wrong wrapper, or backslashes stripped) means the Post opens empty or the builder complains. Mitigations: build step 1; a test that creates a Post and renders it; drafts only, so nothing broken goes public.
- **Published Divi 5 Posts are new territory.** The published Posts sampled are plain HTML, so the Post template, slider and CSS may behave differently. Build step 1 checks this before anything else is built.
- **Section matching only helps part of the time.** Many headings have no matching Section, so the editor will often have to choose one by hand. That is deliberate: a wrong guess would put the Post at the wrong `/school-life/...` URL.
- **The Posts gatekeeper** blocks the Divi builder on a draft until the featured image is set. This is expected and matches "you set the image", but the UAT checks the message is clear.
- **Website Section drives Post routing** (`/school-life/...`), so a Custom Section changes URLs for Posts in it. Custom Sections need `manage_categories` (admins/editors only) and are named in the result message.
- **Wrong article matched after heavy edits.** Mitigated by id/headline keys and the "not found" state; the tool never guesses.
- **Duplicate content in search engines.** Article and Post are both public (agreed). Search indexing: drafts are not indexed. Once published, site search merges the Post with its article if the headline is unchanged; if the title was edited, both appear.
- **Caching:** nothing public changes when drafts are made; publishing a Post behaves like any other Post.
- **Rollback:** deactivate the snippet "Weekly-Update--Articles-To-Posts". Drafts already created remain as ordinary draft Posts (trash them if unwanted). The meta keys `_hs_a2p_rows` and `_hs_a2p_source` are inert without the snippet.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` (php -l with Local's PHP 8.2) on the new snippet.
- `wp eval-file work/weekly-articles-to-posts/tests/wp-tests.php` on headstart.local, using a temporary weekly update built from a fixture of real Divi 5 article markup, deleted afterwards:
  - article detection: count, headlines, sections, keys; groups with no `h3` left out and counted; a plain-HTML issue lists nothing and shows the message;
  - key stability: content edited (text changed, an article added above) still matches the right rows; a renamed headline → "not found"; a repeated headline gets its counter;
  - campus and section mapping, including Both, capitals, the alias list, and an unmatched heading staying empty (that row is skipped and named);
  - Create drafts: status draft; title; date kept (`post_date_gmt` set); taxonomies and chips equal those of a hand-saved Post; `_et_pb_use_builder` on; content parses as valid blocks containing the article, with its `<` encoding intact; second run makes no duplicate; Re-copy replaces content only;
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
