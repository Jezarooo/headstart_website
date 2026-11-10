# Plan: Articles to Posts panel layout, image picker and Post event dates

- Task: `feat/a2p-panel-event-date` · opened 2026-11-10
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-09`
- Roadmap: item `a2p-panel-event-date`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## In plain English
The Articles → Posts panel is tidied into labelled groups: Title, publish date and status together, the Website Section with its yellow badge text underneath, and the campus with its location text underneath. Images get their own row: a Featured slot, a Portrait slot and one rack of the article's photos, where you click a photo to put it in the slot you're filling. Every Post also gets an optional **Event date**, a single day or a range such as "12–14 November 2026". Readers see that instead of the publish date. Once you switch the date module in the Post templates (steps below), Posts without an event date look exactly as they do now. The main risks: switching a template before the code is live would blank the date chip, and Nick has to install this after the share-links and badge packages.

## Problem / goal
- **Panel layout.** Fields flow in one grid, so related ones end up apart: the badge override isn't next to the Section it overrides, the location override isn't under Campus, and the publish date is far from the title. Labels such as "Display Location Override" don't say what they change.
- **Two racks of the same photos.** The Featured and Portrait pickers each show the article's images, so a 30-photo gallery appears twice.
- **Event date.** A Post shows its publish date. When a Post is scheduled later than the event, or written up after it, readers see the wrong date. They care about when it happened, not when it was published.

Decisions from the user (10 Nov):
- URLs keep using the **publish** date (`/school-life/YYYY/MM/`).
- Sliders and feeds are unchanged (their ordering logic stays; may be reviewed later).
- The event date can be a **range**.
- Images: **click** to pick, no drag; one rack only.
- The user switches the Theme Builder templates himself; this plan gives the steps.

## Proposed changes

### `Site-Scripts/php/posts-register-fields.code-snippets.php` (live "Posts--Register-Fields")
Two new ACF fields in the existing **Post Classification** group, after Display Location Override:
- **Event start** (`hs_event_start`, `field_hs_event_start`): date picker, optional, stored `Ymd`, shown `d/m/Y`. Instructions: "When it happened. Readers see this instead of the publish date. Leave empty to show the publish date."
- **Event end** (`hs_event_end`, `field_hs_event_end`): same, optional. Instructions: "Only for events over several days."
- Validation (`acf/validate_value`): an end without a start, or an end before the start, is refused with a message.

### `Site-Scripts/php/posts-compute-taxonomies-from-custom-fields.code-snippets.php` (live "Posts--Compute-Taxonomies-From-Custom-Fields")
1. `hs_compute_final_divi_chips()` also writes **`hs_final_date_label`**, the text readers see:
   | Event fields | Label |
   |---|---|
   | none | publish date, `j F Y` (e.g. `30 October 2026`, as the template shows today) |
   | start only, or end = start | `12 November 2026` |
   | same month | `12–14 November 2026` |
   | same year, different months | `30 October – 2 November 2026` |
   | different years | `30 December 2026 – 2 January 2027` |
   Dates are read as plain calendar dates (no timezone shift). It uses an en dash, as above.
2. The existing `updated_post_meta` catch also recalculates on `hs_event_start` / `hs_event_end`, and a new `added_post_meta`/`deleted_post_meta` catch covers first-time and cleared values.
3. **One-off fill:** `hs_fill_date_labels_v1()` on `admin_init`, guarded by the option `hs_date_label_fill_v1`. It writes `hs_final_date_label` for every Post (any status) in batches of 200 per admin page load until done, then sets the option. It only writes that one meta key; it doesn't re-save Posts or touch `post_date`. On local it runs once I build it; on live it runs on Nick's first wp-admin page loads after install.
4. The repo copy has no stray `?>` after the header comment; live's copy does (it's the only difference). Handover uses `--accept-drift` for this, with the reason recorded.

### `Site-Scripts/php/weekly-articles-to-posts.code-snippets.php` (live "Weekly-Update--Articles-To-Posts", new in the badge package)
1. **Layout:** the row body becomes labelled groups (CSS grid, two columns on wide screens, one on narrow):
   - **Post:** Title *, then **Publish now** (checkbox, ticked by default) beside the Publish date picker, then Live / Draft. While Publish now is ticked the date picker is hidden and not required. *(Added 10 Nov after first UAT look.)*
     - Live + Publish now: published when Create Posts is clicked, dated that moment.
     - Live + a date: a future date schedules it; a past date backdates it.
     - Draft + Publish now: saved with no fixed date, so it takes its date when someone publishes it later.
     - Draft + a date: a draft with that date.
     Hint: "Untick to choose a date: a future date schedules it. The publish date sets the URL."
   - **Event date (shown to readers):** Start, End. Hint: "Leave End empty for a single day. Leave both empty to show the publish date."
   - **Website Section:** Website Section * (hint: "Sets the feeds, the URL and the yellow badge"), then **Yellow badge text** (optional; hint: "Leave empty to show the Website Section").
   - **Campus:** Campus * (checkboxes, Both hint as now), then **Location text** (optional; hint: "Replaces the campus name in the chip, e.g. Bangkok Trip").
   - **Images** (full width, own row): see 2.
   - **Thematic Tags *** (full width, as now).
   - **Alignment:** the Website Section and Campus groups sit side by side and share row lines (CSS `subgrid`), so Campus lines up with Website Section and **Location text lines up with Yellow badge text**. Campus gets a matching one-line hint ("Tick both for Both Campuses.").
   Required fields keep their own red/green markers. Spacing and hints are tightened (smaller gaps, shorter text).
2. **Images row:** column 1 **Featured image** slot (required for Live), column 2 **Portrait** slot (optional), column 3 **Article images** rack, scrollable, max ~260px high.
   - One slot is **active** (thick outline, "picking now"); it starts on Featured. Clicking a rack image puts it in the active slot. After Featured is filled, the active slot moves to Portrait if Portrait is empty. Clicking a slot makes it active.
   - Rack images in use show an **F** or **P** tag. A line under the rack says which slot a click fills.
   - Each slot keeps **Media Library** and **Remove**. Slot previews use the `medium` size; rack thumbnails use `thumbnail` (150px) with `loading="lazy"`, built only when the row is ticked.
   - Rack buttons are real `<button>`s, so keyboard and touch work.
   - The two separate per-field rows of choices are removed.
3. **Event date in rows:** new row fields `eventStart`, `eventEnd` (`Y-m-d`), sanitised; end before start, or end without start, is a missing-field error in the panel. On create, saved as `hs_event_start` / `hs_event_end` (`Ymd`) with their ACF key meta, **before** terms are set, so the chip hook writes the label.
4. **Defaults:** Event start defaults to the weekly update's date; Event end empty; **Publish now ticked** (new row field `publishNow`, boolean), with the hidden date picker pre-set to now in case it's unticked. A new Post therefore publishes today and its URL uses this month unless a date is chosen. Rows saved before this change have no `publishNow` and keep their stored date (treated as unticked). The date is only required when Publish now is unticked; on create, Publish now uses the current time at that moment (Live) or leaves the date floating (Draft).
5. **Re-copy** is unchanged (content only).

### Theme Builder templates (database, done by the user, not code)
Five templates show the date with a Text module labelled **Date**, using dynamic content **Post Publish Date**: `HSN Post STANDARD`, `HSC Posts STANDARD`, `HSN Post Archive`, `HSC Posts Archive`, `Both Campus Posts Archive`. Each is switched to the custom field `hs_final_date_label`, the same kind of dynamic content the Section chip uses for `hs_final_section_chip`. Steps (local first, then live after the snippets are in and the fill has run):
1. Divi → Theme Builder → open the template's Body layout.
2. Open the Section chip text module next to Date and note its Content setting (dynamic content, custom field, manual name `hs_final_section_chip`).
3. In **Date**, remove the **Post Publish Date** dynamic content; add the same kind of dynamic content as step 2 with the name `hs_final_date_label`; Before/After empty.
4. Save the layout, then Save in the Theme Builder. Repeat for all five.
5. Check: a Post with an event range shows the range; an imported Post shows its original date.

Not changed: URLs, slider cards and order, search results, RSS, Yoast dates (they keep the publish date).

### Tests
`work/weekly-articles-to-posts/tests/wp-tests.php` and `panel-check.mjs` are extended (below).

## Risks & rollback
- **Template switched before the code** → empty date chip. The order is in the deploy notes; the fill must finish first (check: the option `hs_date_label_fill_v1` exists, or any imported Post shows its date after the switch).
- **Label goes stale** if a Post's date or event fields change without a save hook (e.g. a direct DB edit or import). Any normal save, quick edit, scheduled publish, or ACF field change recalculates it. Same behaviour as the existing chips.
- **Preview** shows a blank date chip after editing a published Post, the same autosave quirk the Section and Campus chips already have.
- **Publish date default is now "now".** Posts made from an old weekly update get this month in their URL unless the date is changed. This is intended; the event date carries the "when".
- **Install order on live:** share-links package, then the badge package (`a2p-badge-override-2026-11-10`), then this one, because this one builds on the badge version of the panel snippet.
- **Fill on live:** 770+ Posts at 200 per admin page load; a handful of page loads. If it times out, it resumes on the next load.
- **Rollback:** switch the five templates back to **Post Publish Date** first, then restore the three snippets from `rollback/`. Event meta and `hs_final_date_label` stay in the database and do nothing without the code.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the three snippets.
- `wp eval-file work/weekly-articles-to-posts/tests/wp-tests.php`, adding:
  - labels for every row of the table above, including year boundary, end = start, and the en dash;
  - no event fields → label equals the publish date in `j F Y`; changing `post_date` and saving updates it;
  - ACF validation refuses end-before-start and end-without-start;
  - setting/clearing event meta directly (quick-edit path) recalculates the label;
  - the fill writes a label for every Post, changes no `post_date`/`post_modified`, sets its option, and does nothing on the next run;
  - panel rows: defaults (event start = issue date, Publish now ticked), sanitising, invalid ranges reported as missing; a created Post has the event meta and the right label;
  - Publish now: no date needed; Live → published with a date within a minute of creation; Draft → `post_date_gmt` is the floating `0000-00-00`, so it dates itself when published; unticked with no date → reported missing; an old saved row without the field keeps its date;
  - everything from the badge task still passes.
- `panel-check.mjs` (headless Chrome), adding:
  - group headings and order as above; one rack per row, no per-field choices;
  - Publish now ticked by default with the date picker hidden; unticking shows it; Create is allowed with Publish now and no date;
  - Website Section and Campus fields start on the same line, and Yellow badge text and Location text start on the same line (within 2px);
  - Featured active first; clicking a rack image fills Featured, then Portrait becomes active; F/P tags; clicking a slot changes the active slot; Media Library and Remove still work;
  - rack thumbnails are `thumbnail` size and lazy;
  - a created Live Post with a range shows the range in `hs_final_date_label`; no JS errors; weekly update unchanged.
- Front end (after I switch the local templates for testing; the user does the same on live): logged-out 200 for an event-range Post, a no-event new Post and an imported Post, each showing the expected date text; a weekly update → 200 and unchanged.
- `tools/hs drift`: only the three snippets differ, as expected.

### UAT checklist for the user (Gate 5)
- [ ] Tick an article: fields sit in the groups described (Post / Event date / Website Section / Campus / Images / Tags), with the badge text under the Section and the location text under Campus.
- [ ] **Publish now** is ticked and the date picker hidden; untick it and the picker appears. Event start defaults to the weekly update's date.
- [ ] Website Section lines up with Campus, and Yellow badge text lines up with Location text.
- [ ] A Live Post made with Publish now is dated today; a Draft made with Publish now takes its date when you publish it.
- [ ] Images: one rack of the article's photos. Click one: it becomes the Featured image, and Portrait is now the one being picked. Click another: it becomes the Portrait. Click Featured and pick a different photo. F and P tags show which is which. Media Library and Remove work.
- [ ] Create a Post with a range across two days, one with a single event date, and one with none.
- [ ] Switch the date module in the local `HSN Post STANDARD` and `HSC Posts STANDARD` templates (steps above).
- [ ] The three Posts show the range, the single date, and the publish date.
- [ ] An imported Post still shows its original date (also after switching an Archive template).
- [ ] On a normal Post edit screen, Event start / Event end are under the location override; end before start is refused.
- [ ] Weekly updates, sliders and search look as before.

## Deployment notes for the dev (Gate 7)
Install **after** the share-links package and the badge package `a2p-badge-override-2026-11-10`. Then:
1. **Replace** the code of **Posts--Register-Fields**, **Posts--Compute-Taxonomies-From-Custom-Fields** and **Weekly-Update--Articles-To-Posts** with the files in `files/php/`.
2. **Load wp-admin a few times** (any admin page) until the date-label fill finishes; it runs 200 Posts per load. Tell Jeremy when it's done (the option `hs_date_label_fill_v1` exists).
3. **Jeremy** then switches the Date module in the five Post templates and purges WP Super Cache and Cloudflare APO.
- No permalinks flush.
- Smoke: open any Post's edit screen → Event start / Event end appear under the location override. Open a weekly update → Articles → Posts shows the grouped layout and the image rack.
