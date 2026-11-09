# Plan: Weekly update articles to Posts

- Task: `feat/weekly-articles-to-posts` · opened 2026-11-06
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-09`
- Roadmap: item `weekly-articles-to-posts`
- Refreshed 9 Nov against the 9 Nov backup and the local database (5 Nov import); see "What the survey found"

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
Every weekly update contains several articles (Divi 5 groups with class `hs-weekly-article`). Some of them should also exist as stand-alone Posts, and today each one is copied out by hand: new Post, paste the content, set the Section, Campus, tags and date. Goal: after a weekly update is published, open it, tick the articles that should become Posts, fill in their Post details in one panel, and click **Create Posts**. Each ticked article becomes a Post with its Divi 5 content and details already set. By default it goes live straight away, with no need to open Posts; a switch on the row makes it a draft instead.

Decisions agreed with the user (6 Nov, updated 9 Nov):
- **Changed 9 Nov:** each row has a **Live / Draft** switch, defaulting to Live.
- **Changed 9 Nov:** required fields and green/red highlighting apply only inside the tool, on ticked rows. Nobody is forced to fill anything in when editing a weekly update normally.
- Each Post is a one-off copy. Editing the weekly update later never changes the Post.
- The weekly update itself is never changed: no links are added and its content is never re-saved.
- **Changed 9 Nov:** editors set the images in the panel, using an image picker, because a Live Post has no later step for adding one. The tool never chooses an image by itself.
- No excerpt (Posts don't use one).
- The fields live in a panel on the weekly update's edit screen, not inside the Divi builder (that would need a separate built Divi 5 extension, which is fragile across Divi updates).

## What the survey found (9 Nov)
This replaces the old "build step 0": real issues were inspected read-only before this revision. The local database is the 5 Nov import.
- **174 published weekly updates: 18 are Divi 5** (since 26 Aug 2026), holding 355 article groups. The other 156 are older plain HTML.
- **No article has an HTML id**, so the headline is the real key. The `id` inside each group's class attribute repeats within all 18 issues, so it can't be used.
- **No issue repeats a headline** across the 334 articles that have one. The other **21 have no `h3`**; those sampled are nearly all image-only promotions.
- **Section headings are free text**: 44 spellings, including capitals and typos ("Langauges Department", "sCHOOL hOUSES"). Many are not Sections at all (Upcoming Events, Announcements, Promotions). Every Divi section's admin label is "Weekly Update", so that fallback is useless and is removed.
- **`_et_builder_version` is empty** on weekly updates and Posts. The version lives in each block's `builderVersion`, so the meta is not set. `_et_pb_use_builder = on` and the `divi/placeholder` wrapper are confirmed.
- **Only 2 Posts use Divi 5 blocks, both drafts; the published Posts are plain HTML imports.** The user confirmed the focus is new manual Posts, so older plain-HTML issues stay out of scope. The demo below tested a published Divi 5 Post.
- **Parsing then re-saving the blocks is lossless** on all 18 issues: `serialize_blocks( parse_blocks() )` returned the same text.
- **Site search already merges a Post with its article** when the headlines match (`hs_search_headline_key`), so an unchanged title won't show twice.
- **The Yellow Badge is a fixed list** (a single-choice select), not free text. Thematic Tags do allow new terms. Section allows several on a Post; the panel sets one, and the editor can add more on the Post.

## What the demo found (9 Nov)
Four throwaway `DEMO` Posts were built on `headstart.local` from real articles, by a script outside the repo. The user asked to see this before approving.
- **The Theme Builder templates already exist and work for Divi 5 Posts.** `HSC Posts STANDARD` (Chaofah) and `HSN Post STANDARD` (Cherngtalay) are assigned by `hs_post_campus` term. Hero, chips, content, slider and sidebar all render. No template needs creating for one-campus Posts.
- **The wrapper must be plain.** Copying the weekly update's row gave a card inside a card, because `.single-post .hs-post-content-sandbox .hs-weekly-article` in `core.css` already draws the card on Posts.
- **The article's first `h3` must be removed from the copy.** The template's hero already shows the Post title, and the imported Posts also have no headline in their content. Later `h3`s in the article stay (the Halloween article's sub-headings).
- **Both Campuses Posts match both templates, and the demo got the Chaofah sidebar.** The chip correctly says "Both Campuses" and the URL uses `/both-campuses/`. There is no "both" template for new Posts, and Divi can't target "has both terms" without a helper term. The calendar and noticeboard each have a working campus toggle (`hsToggleEventsCampus`, `hsToggleCampus`), Parent Fitness has tabs, and the existing "Both Campus Posts Archive" template is also set to Chaofah. The slider on the Post is set to Chaofah too.
- **The location override changes the URL as well as the chip** (`/bangkok-trip/school-life/...`, returned 200), so it belongs in the panel.
- **Articles styled for a dark background break on the white Post card.** The Phuket Sunshine Village article uses inline white text, which is invisible on the Post. About 10 of the 355 articles have inline white text. The tool copies content unchanged, so such a row starts on Draft with a warning; the editor fixes it on the Post.

## Proposed changes
### New file: `Site-Scripts/php/weekly-articles-to-posts.code-snippets.php` (new live snippet "Weekly-Update--Articles-To-Posts", admin only)
1. **Finding the articles.** It reads the weekly update's saved Divi 5 content with `parse_blocks()` and lists each `divi/group` with class `hs-weekly-article`: its headline (first `h3`), its section (the preceding `h2.hs-section`) and its position. This is the same detection `hs_search_issue_articles()` uses for site search. The code is a separate copy with its own `hs_a2p_` prefix, so neither snippet depends on the other being active. Groups with no `h3` aren't listed; the panel shows how many were left out. **Divi 5 issues only:** on an older plain-HTML issue the panel just says the tool doesn't cover it.
2. **Recognising the same article after edits.** Articles are keyed by their normalised headline, with a counter added if an issue ever repeats one. Nothing is written into the weekly update. If a saved row's article can no longer be found (headline renamed or article deleted), the panel keeps the row and shows "Article not found", rather than dropping it or attaching it to another article.
3. **Panel** (a meta box under the builder on `weekly_update` edit screens, shown once the update has content). One row per article: a **Make this a Post** tick, then for ticked rows:
   - **Title:** pre-filled from the headline, editable.
   - **Website Section** (`hs_department`): a dropdown of existing Sections, pre-selected by matching the article's section heading, ignoring capitals (exact name, then the alias list below). If nothing matches it stays empty and the editor must choose; the tool never guesses. It ends with a **Custom…** option: type a new Section name, which is created as a new Section term when the drafts are made.
   - **Campus** (`hs_post_campus`): Chaofah / Cherngtalay checkboxes, pre-ticked from the weekly update's campus (`hs_campus`, matched by slug, then name). Both can be ticked; the existing chip logic then shows "Both Campuses", and a hint says the sidebar opens on one campus and readers can switch.
   - **Thematic Tags** (`post_tag`): at least one; type freely, with existing tags suggested.
   - **Yellow Badge** (`hs_thematic_chip`): required; a dropdown of the existing badges, one choice, like the Post screen. The existing chip logic shows the badge in place of the Section name, so every Post made with this tool shows its badge there.
   - **Display Location Override** (`hs_location_override`): optional text, for off-campus events such as "Bangkok Trip". Its hint says it replaces the campus chip and changes the Post's URL.
   - **Publish date:** a date and time picker, defaulting to the weekly update's date.
   - **Featured image:** opens the Media Library, and the article's own images are offered as one-click choices. Nothing is chosen automatically, because the first image is often wrong for a hero (the demo picked a house-points graphic).
   - **Portrait image** (`hs_portrait_image`, optional): the same picker, for the slider.
   - **Status:** a **Live / Draft** switch, defaulting to Live. Without the `publish_posts` capability the row is fixed on Draft. A future date on a Live row schedules the Post, as WordPress normally does.
   - A row with inline white text starts on Draft with the warning "Styled for a dark background: check the text colours on the Post". The editor can still switch it to Live.

   **Required fields, only when a row is ticked:** Title, Website Section, Campus, Thematic Tags (at least one), Yellow Badge and Publish date, plus Featured image when the row is Live. Display Location Override and Portrait image are optional. Section and Campus are required even though the Post screen's gatekeeper would accept a Yellow Badge or a location override instead, because Section sets the feeds and Campus selects the Theme Builder template. These rules are stricter than a hand-made Post's and apply only in this panel; the Post screen doesn't change.

   **Green/red highlighting** uses the panel's own `hs-a2p-` classes, on ticked rows only. It does not reuse `hs-valid` / `hs-missing`, and it adds no rules to the weekly update's own fields. Unticked rows have no rules. The panel never blocks the weekly update's Update button, whether its rows are complete or not.

   **Alias list** (heading → Section): PE & Sports, Academy News, Sports & Academies → Sports Academies; ECA News → ECA; CAPA News → CAPA; Thai Department → Thai; Languages Department (and the "Langauges" typo) → World Languages; House Update → School Houses; Alumni Network → HeadStart Alumni. Everything else (Student Voice, Sustainability, Library News and so on) starts empty.

   Ticks and fields start empty, because many articles are events or notices that never become Posts. The panel saves on its own through `admin-ajax`, with a nonce check and `edit_post` capability on the weekly update. Its inputs have no `name` attribute, so the normal Update button can't submit them. It stores everything in post meta `_hs_a2p_rows` on the weekly update and never triggers a save of the weekly update. Saving a published weekly update with the normal Update button carries on as today and ignores the panel.
4. **Create Posts** (a button in the panel; needs `edit_posts`, plus `publish_posts` for Live rows and `manage_categories` for a Custom Section). It first asks for confirmation, naming how many Posts will go live and how many will be drafts. For each ticked row that has no Post yet, it calls `wp_insert_post`, always as a draft first, with:
   - post type `post`, status `draft`, the title, and the chosen date with both `post_date` and `post_date_gmt` set, so WordPress keeps that date when the Post is published instead of using the publish day;
   - content: the article group inside a plain Divi section, row and column (no settings copied from the weekly update), inside the `divi/placeholder` wrapper. The article's first `h3` is removed: the whole Text module if that is all it holds, otherwise just the heading. Everything else in the article, including its images and gallery attributes, is copied unchanged. The content goes through `wp_slash()` first, because `wp_insert_post` strips backslashes and would otherwise break Divi's escaped angle brackets;
   - `_et_pb_use_builder = on`, so the Post opens in Divi 5;
   - the taxonomies set with `wp_set_object_terms` after the insert (`hs_department`, `hs_post_campus`, `post_tag`, `hs_thematic_chip`). The Post screen's ACF fields read those terms directly (`load_terms`), and the existing `set_object_terms` hook recomputes the chips, so the result matches a hand-made Post without depending on ACF's save flow. The location override is written before the terms: the existing meta hook only fires when a value is updated, not when it is first added, so the terms are what trigger the chip;
   - the featured and portrait images;
   - source meta `_hs_a2p_source` = weekly update ID + article key, and the new Post's ID written back into that row.

   **Going live:** once everything is saved, a Live row is published with `wp_update_post`. Publishing as a second step means the Posts gatekeeper checks the finished Post, and all the normal publishing hooks run. (The gatekeeper doesn't check a brand-new Post that is inserted as published, so a one-step publish would skip it.) If the gatekeeper holds it, the Post stays a draft and the row says why.

   Afterwards the row shows **Live → View** or **Draft → Edit** and can't be created twice. A **Re-copy** action overwrites that Post's content from the article; its fields stay as they are. Its confirmation says so plainly when the Post is live: "This changes a public page". Rows missing a required field are skipped and named; the other rows still go through.
5. **Posts list.** A small "From weekly update: Week N, Campus" note on the Post's edit screen, linking back to the source.

Inline admin JS and CSS live in the same snippet: one file, no build step. The snippet adds nothing to the front end itself, but the Posts it publishes are public. No rewrite rules and no change to existing snippets.

### New file: `work/weekly-articles-to-posts/tests/wp-tests.php` (local only)
Run with `wp eval-file` (see Testing).

### Decision: Both Campuses (9 Nov)
Keep the existing templates for now, so a Both Campuses Post has the correct chip and URL. Its sidebar and slider open on whichever template Divi picks (Chaofah in the demo), and readers can switch the calendar, noticeboard and Parent Fitness to the other campus. The panel shows a hint when both campuses are ticked. A Both Campuses article is always one Post, never one per campus. A proper "both" template, the same layout with only the sidebar elements differing, is a later task (roadmap item `both-campus-template`).

### Demo Posts
The `DEMO` Posts stay on `headstart.local` until the user has reviewed them, then go to the Trash. They carry the meta `_hs_demo_a2p`.

## Risks & rollback
- **Content copied in a shape Divi 5 doesn't accept** (wrong wrapper, or backslashes stripped) means the Post opens empty or the builder complains. Mitigations: the demo; a test that creates a Post and renders it; the draft-first step, so a failure part-way leaves a draft rather than a public Post.
- **A mistake is public immediately.** Live is the default and there is no review step. Mitigations: required fields; the confirmation naming how many Posts go live; dark-styled articles starting on Draft; the Draft switch. To undo one, unpublish or trash the Post, using the row's link.
- **Published Divi 5 Posts are new territory.** The demo looked right on desktop, but mobile, the gallery lightbox and opening a Post in the Divi builder still need checking by the user.
- **The STANDARD templates are separate copies, not global.** A design change to Posts has to be made in `HSC Posts STANDARD` and `HSN Post STANDARD`, plus the archive templates if imported Posts should match. This task changes none of them.
- **Section matching only helps part of the time.** Many headings have no matching Section, so the editor will often have to choose one by hand. That is deliberate: a wrong guess would put the Post at the wrong `/school-life/...` URL.
- **The Posts gatekeeper** blocks the Divi builder on a draft until the featured image is set. A draft made without an image hits this as usual, and the UAT checks the message is clear.
- **Website Section drives Post routing** (`/school-life/...`), so a Custom Section changes URLs for Posts in it. Custom Sections need `manage_categories` (admins/editors only) and are named in the result message.
- **Wrong article matched after heavy edits.** Mitigated by id/headline keys and the "not found" state; the tool never guesses.
- **Duplicate content in search engines.** Article and Post are both public (agreed). Search indexing: drafts are not indexed. Once published, site search merges the Post with its article if the headline is unchanged; if the title was edited, both appear.
- **Caching:** nothing public changes when drafts are made. Publishing runs the normal WordPress hooks, so it should behave like publishing by hand, but that can't be proven locally, where WP Super Cache is off and there is no Cloudflare. The live smoke check covers it.
- **Rollback:** deactivate the snippet "Weekly-Update--Articles-To-Posts". Posts already created stay as ordinary Posts, live or draft (unpublish or trash any that are unwanted). The meta keys `_hs_a2p_rows` and `_hs_a2p_source` are inert without the snippet.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` (php -l with Local's PHP 8.2) on the new snippet.
- `wp eval-file work/weekly-articles-to-posts/tests/wp-tests.php` on headstart.local, using a temporary weekly update built from a fixture of real Divi 5 article markup, deleted afterwards:
  - article detection: count, headlines, sections, keys; groups with no `h3` left out and counted; a plain-HTML issue lists nothing and shows the message;
  - key stability: content edited (text changed, an article added above) still matches the right rows; a renamed headline → "not found"; a repeated headline gets its counter;
  - campus and section mapping, including Both, capitals, the alias list, and an unmatched heading staying empty (that row is skipped and named);
  - first `h3` removed and later `h3`s kept; wrapper has no copied row settings; inline-white-text warning shown; the Yellow Badge reaches the chip; the location override reaches the chip when set, and the campus shows when it isn't; a Post saves with no override or portrait;
  - Create Posts, Draft row: status draft; title; date kept (`post_date_gmt` set); taxonomies and chips equal those of a hand-saved Post; `_et_pb_use_builder` on; content parses as valid blocks containing the article, with its escaped angle brackets intact; second run makes no duplicate; Re-copy replaces content only;
  - Create Posts, Live row: status publish, keeping the chosen date and images; a future date gives `future`; no featured image means the row is skipped and named, and no Post is made; a dark-styled row defaults to Draft;
  - required fields: an unticked row has no rules; a ticked row missing each required field is skipped and named, while other rows go through; an incomplete panel doesn't affect saving the weekly update;
  - permissions: an Author-level user can't create a Custom Section; a user without `publish_posts` can't make a Live Post; a bad nonce → 403.
- Endpoints: `curl` admin-ajax without a nonce → 403 / `-1`; a Post created as Live → 200 logged out, with its title in the HTML; a Post created as Draft → not public logged out.
- Browser check (`work/local-editor-crash/tests/editor-check.mjs` adapted): the weekly update editor still loads with the panel, with no errors.

### UAT checklist for the user (Gate 5)
- [ ] Open a published weekly update in wp-admin: the "Articles → Posts" panel lists every article with the right headline and section.
- [ ] Tick two articles (one from each of two sections) and check the pre-filled Title, Section, Campus and date.
- [ ] Change one Section using **Custom…**, tick both campuses on the other, add a location override to one, and set a different publish date.
- [ ] Reload the page: the ticks and fields are still there, and the weekly update itself shows no "unsaved changes" and is unchanged on the front end.
- [ ] Tick a row and leave Tags and Yellow Badge empty: they show red, and turn green once filled. Unticked rows show no colours, and Location Override and Portrait are never red.
- [ ] Leave a required field empty on a ticked row, then click the weekly update's normal Update button: it saves as usual.
- [ ] Choose a featured image from the article's images on one row and from the Media Library on another.
- [ ] Set one row to Live and one to Draft, then click **Create Posts**: the confirmation gives the right counts.
- [ ] The Live Post is public with the right title, Section, Campus, tags, badge, date and hero, without opening Posts. The Draft is in Posts and isn't public.
- [ ] Check the content of both against the article: text, images, gallery lightbox, on desktop and a real phone. Open each in the Divi builder.
- [ ] A Both Campuses Post: the chip and URL are right, and the calendar and noticeboard arrows switch campus.
- [ ] A dark-styled article (Phuket Sunshine Village) starts on Draft with the warning.
- [ ] Back on the weekly update, the rows show Live / Draft with links, and clicking Create Posts again makes no duplicates.
- [ ] Edit the article text in the weekly update: the Post doesn't change; **Re-copy** updates it after the confirmation.

## Deployment notes for the dev (Gate 7)
- **Add** a new Code Snippet "Weekly-Update--Articles-To-Posts" from `files/php/weekly-articles-to-posts.code-snippets.php`, set to "Only run in administration area", and activate it. No existing snippet changes.
- No permalinks flush. No cache purge needed to install it, because the snippet changes nothing public by itself.
- Smoke: open one weekly update in wp-admin → the panel shows its articles and the editor loads normally. Create one Draft from a test row, check it, then trash it.
- First real Live Post: check it appears on the slider and the campus pages as a hand-published Post does. If the page lists are stale, tell us, because the tool would then need a purge step.
