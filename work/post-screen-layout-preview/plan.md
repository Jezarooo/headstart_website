# Plan: Post edit screen: tidy layout and labelled card preview

- Task: `feat/post-screen-layout-preview` · opened 2026-11-10
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-09`
- Roadmap: item `post-screen-layout-preview`
- Builds on: `a2p-panel-event-date` (merged 10 Nov, not yet live). This package installs **after** it.

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## In plain English
The **Post Classification** box on a Post's edit screen gets the same tidy layout as the Articles → Posts panel:
- Website Section with the yellow badge override under it, and Campus with the location override under it, side by side and lined up;
- Event start and end in one row;
- Thematic Tags across the full width, with the Portrait image beside the preview;
- shorter, clearer hints.

On a wide screen, the same labelled preview of the slider card and the top of the Post page sits to the right, updating as you edit the fields, title, publish date and featured image. The fields, what they save, and the green/red publishing checks don't change. The main risk is the block editor's meta box area behaving differently on live's WordPress version, so it's checked on the local copy of live's setup and rolls back by restoring two snippets.

## Problem / goal
On the Post edit screen, the seven Post Classification fields sit in one long row. Each has a paragraph of instructions, so the row is uneven, Portrait wraps onto a second line, and related fields (Section and its badge override, Campus and its location override) aren't visibly paired. There's no way to see how the choices will look on the card or the Post page until the Post is published.

The Articles → Posts panel solved this with grouped fields and a labelled live preview (user, 10 Nov). Goal: the same on the Post screen, sharing one copy of the preview code so the two can't drift apart.

Decisions from the user (10 Nov): do it as its own task after the event-date package; match the panel's look; include the labelled preview.

## Proposed changes

### New: `Site-Scripts/php/posts-card-preview.code-snippets.php` (live "Posts--Card-Preview", admin only)
The preview moved out of the Articles to Posts snippet into one shared place:
- `hs_card_preview_print_assets()` prints the preview CSS and JS once per page (guarded by a static flag). The CSS is the existing scoped card and Post-top styles, now scoped to `.hs-cpv` so either screen can use them. The fonts load as now (Google Fonts, Montserrat and Open Sans), enqueued only on screens that use the preview.
- `window.hsCardPreview.create(container)` builds the card, Post-top strip, labels and lines. `.update(values)` takes plain values (title, section, badge text, campus names, location, publish date, event start/end, image URL, tags) and applies the same rules as the PHP: badge override, Both Campuses, tag prefixes, the card's short date and the Post page's date label. `.redraw()` re-runs the label lines.
- No PHP data, hooks on Posts, or front-end output.

### `Site-Scripts/php/weekly-articles-to-posts.code-snippets.php` (live "Weekly-Update--Articles-To-Posts")
- The panel's own preview code (CSS, `previewNode`, `updatePreview`, `drawCallouts`, the date helpers) is removed and replaced with calls to `hsCardPreview`. What the panel shows doesn't change. The toggle, the side-by-side layout and the 80% sizing stay.
- If the shared snippet is ever off, the panel works as before without the preview. The preview column simply doesn't appear, and nothing breaks.

### `Site-Scripts/php/posts-register-fields.code-snippets.php` (live "Posts--Register-Fields")
Data unchanged: same field keys, names, types and saving. Only presentation:
1. **Labels and instructions, shorter**, matching the panel:
   - Website Section *: "Dictates where on the site the Post is shown (its feeds and URL). Also the default yellow badge." *(user, 10 Nov; the Articles → Posts panel uses the same hint)*
   - Yellow badge override: "Optional. Leave empty to show the Website Section."
   - Campus *: "Tick both for Both Campuses."
   - Location override: "Optional. Replaces the campus name in the chip, e.g. Bangkok Trip. Still tick a Campus above."
   - Event start: "Shown to readers instead of the publish date. Leave empty to use the publish date."
   - Event end: "Only for events over several days."
   - Thematic Tags: "Aim for 3–4. The part before the colon groups them and is hidden on the site."
   - Portrait: "Optional. Used by the slider; otherwise the Featured image is used."
2. **Wrapper classes** on each field (`hs-pc-section`, `hs-pc-badge`, `hs-pc-campus`, `hs-pc-location`, `hs-pc-event-start`, `hs-pc-event-end`, `hs-pc-tags`, `hs-pc-portrait`), with widths reset to 100%. Layout is done in CSS (below), so the fields keep their order in the form and their `data-name`. The Posts gatekeeper finds fields by `data-name` and keeps working.
3. A small `acf/input/admin_head` block, Post screens only, that:
   - prints the layout CSS. It's a CSS grid with named areas:
     ```
     section  campus   | preview
     badge    location | preview
     start    end      | preview
     tags     tags     | preview
     portrait .        | preview
     ```
     Headings are drawn with CSS on the first field of each pair: "WEBSITE SECTION", "CAMPUS", "EVENT DATE (SHOWN TO READERS)", "TAGS", "IMAGES". Section/Campus and badge/location share rows, so each pair lines up. At 1400px and wider the preview is a right-hand column; below that, the fields go two-up and the preview sits under them behind **Show preview**.
   - adds the preview container inside the Post Classification box and calls `hsCardPreview`. The values come from:
     - the ACF inputs: Section, badge, Campus, location, event dates, tags, portrait;
     - the block editor's data store (`core/editor`): title, publish date, featured image. A Post with no date yet counts as "today".
   - It re-renders on ACF `change`, on typing (debounced), on select2 changes and on editor store changes.
4. **Image picker** *(user, 10 Nov)*: the Images group gets the same row as the Articles → Posts panel: a **Featured image** slot, a **Portrait** slot and one rack of **this Post's own photos**.
   - **Rack source:** a new PHP helper reads the Post's saved content and lists the attachment IDs, in this order: Divi 5 image modules, then gallery modules, then plain-HTML `<img>` tags with a `wp-image-ID` class, without repeats. **Found while building (10 Nov):** imported archive Posts' photos are plain files in `uploads/legacy-images/`, not Media Library items (none of 1,256 sampled matched). The featured image and the Portrait can only hold Media Library items, so for those Posts the rack says how many photos are from the old site and that they can't be picked here, and Media Library still works. Making them pickable would mean adding each to the Media Library when picked; that is a separate decision, not in this task. The list is passed to the page when it loads, with thumbnail and medium URLs. Photos added in the Divi Builder appear after saving and reopening.
   - **Featured slot = WordPress's real featured image:** picking writes `featured_media` through the block editor's store (`core/editor` → `editPost`). The sidebar's Featured image panel updates at once, it saves with the Post, and the gatekeeper sees it as usual. A change made in the sidebar updates the slot. **Remove** clears it.
   - **Portrait slot = the existing ACF Portrait field:** picking sets its value through ACF's field API, so it saves as now. ACF's own picker box is hidden; its input stays in the form.
   - **Active slot, F and P tags, Media Library and Remove** work as in the panel. The rack shows 150px lazy thumbnails, scrolls, and holds about 260px.
   - The panel's image row code moves into **Posts--Card-Preview** as `hsCardPreview.imagePicker(...)`, so both screens share one copy. The panel looks and works as before.
5. Nothing else in the file changes (taxonomy meta box removal, Divi settings box closed).

Not changed: the Posts gatekeeper, the chip calculation, front-end output, URLs, the sidebar (featured image, publish date and status stay where WordPress puts them; the Featured slot and the sidebar panel are two views of the same value).

## Risks & rollback
- **Block editor meta box area:** it has its own layout, a resizable split, and the canvas above it. The preview lives inside the Post Classification box, so it scrolls with it; it isn't fixed or sticky. Checked at 1920 and 1280.
- **Live may run a different WordPress / ACF version** than local (local is WP 7.1.3). The store selectors used (`getEditedPostAttribute`, `getMedia`) are long-standing; if one is missing, that part of the preview shows a placeholder rather than an error.
- **Gatekeeper borders:** they stay on the same fields; checked visually and by test.
- **Featured image through the editor store:** this is how WordPress's own sidebar panel sets it, so saving and the gatekeeper's featured-image check behave as with the sidebar. Tested: picking, then saving, stores `_thumbnail_id`; with none, Publish is still blocked.
- **Hidden ACF Portrait box:** if the picker script fails to load, the ACF box shows again (it's hidden by the script, not by CSS), so the Portrait can still be set.
- **select2 (Yellow badge override, tags with the Add box):** its value changes are read through ACF's `select2:select` / `change` events; tested.
- **Shared code:** if **Posts--Card-Preview** is off, both screens work without a preview.
- **Rollback:** restore **Posts--Register-Fields** and **Weekly-Update--Articles-To-Posts** from `rollback/` (the a2p rollback copy is the event-date version), and deactivate **Posts--Card-Preview**. No data to undo.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the three snippets; the inline JS is extracted and `node --check`ed.
- `wp eval-file work/weekly-articles-to-posts/tests/wp-tests.php` (all 171 still pass), plus new checks:
  - every Post Classification field keeps its key, name and type;
  - each has its wrapper class;
  - a Post saved through the edit screen stores the same values as before.
- `work/weekly-articles-to-posts/tests/panel-check.mjs` (all 65 still pass, now using the shared preview).
- New `work/post-screen-layout-preview/tests/post-screen-check.mjs` (headless Chrome, logged in, on a temporary Post that's deleted afterwards):
  - at 1920:
    - the field groups are in the layout above;
    - Section and Campus start on the same line, and so do badge and location;
    - Event start and end are on one row;
    - the preview sits to the right of the fields;
  - at 1280: the preview is under the fields behind Show preview;
  - changing Section, badge override, Campus, location, event dates, tags, portrait, the title (editor) and the publish date (editor) updates the card and the strip to the expected text and image; setting a featured image updates the card when there's no portrait;
  - the gatekeeper's green and red borders are still on the right fields;
  - saving the Post keeps every value (reloaded and compared);
  - image picker: the rack lists the Post's own Media Library photos (Divi images, then gallery, then `wp-image` `<img>` tags); old-site photos are counted, not listed; clicking fills Featured (and the sidebar's featured image), then Portrait; F/P tags; changing the featured image in the store updates the slot; Remove clears; saving stores `_thumbnail_id` and `hs_portrait_image`; with no featured image the gatekeeper still blocks publishing;
  - no JavaScript errors; the Post's front end and a weekly update return 200 logged out.

### UAT checklist for the user (Gate 5)
- [ ] Open a Post on your 1920 screen: Post Classification shows the grouped layout, with Website Section/Yellow badge override beside Campus/Location override, Event start and end in one row, Tags across, and Portrait below.
- [ ] The labelled preview is on the right and changes as you edit the fields, the title, the publish date and the featured image.
- [ ] The green/red checks still mark the required fields, and the Post still can't be published without them.
- [ ] Save, reload: everything you set is still there, and the front end shows it.
- [ ] On a narrower window the preview is under the fields behind **Show preview**.
- [ ] Images: the Featured and Portrait slots plus a rack of this Post's own photos. Clicking a photo sets the Featured image (the sidebar's Featured image changes too), then the Portrait. Media Library and Remove work.
- [ ] On an archive Post, the rack explains its photos are from the old site; Media Library still works for both slots.
- [ ] Save, reload: both images are kept, and the Post can't be published without a Featured image.
- [ ] Open a weekly update's Articles → Posts panel: its preview and image picker look and work as before.

## Deployment notes for the dev (Gate 7)
Install **after** `a2p-panel-event-date-2026-11-10.zip`.
1. **Add** a new Code Snippet **Posts--Card-Preview** from `files/php/posts-card-preview.code-snippets.php`, set to **Only run in administration area**, and activate it.
2. **Replace** the code of **Posts--Register-Fields** and **Weekly-Update--Articles-To-Posts** with the files in `files/php/`.
- No permalinks flush and no cache purge (admin only; nothing public changes).
- **Template note for Jeremy (pack 2):** only the two STANDARD templates (HSN Post STANDARD, HSC Posts STANDARD) need their Date module switched to `hs_final_date_label`. Archive Posts have no event dates, so the archive templates can stay on Post Publish Date and show the same date (user, 10 Nov).
- Smoke: open any Post's edit screen. Post Classification is grouped with the preview on the right, and a weekly update's Articles → Posts panel still shows its preview.
