# Plan: Alumni stories: IG story slider, blurb and story-card strip (Divi groups + JS/CSS)

- Task: `feat/alumni-stories` · opened 2026-10-14
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-14`
- Design: canvas "Alumni Stories" → page "Alumni stories prototype" (approved direction, 14 Oct)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
Bring the alumni stories we post on Instagram (multi-slide 4:5 graphics, 1080×1350) onto the alumni page, as in the approved prototype:
- **Left:** the story's slides in a slider (arrows, dots, swipe; **Next story** on the last slide).
- **Joined to it:** a navy panel with that story's overview blurb.
- **Right:** a strip of story cards (plain photos, two rows) with its own ‹ › controls, a scroll bar and a peek of the next column, so it can grow as stories are added.
- **Mobile:** slider, then blurb, then a swipeable row of cards.

Built so the team maintains it in Divi 5 (no code blocks): each story is a Divi Group; adding a story = duplicating a Group.

## Proposed changes
1. **`Site-Scripts/js/alumni-stories.js`** (new). On the front end only (not in the Visual Builder), it finds each `.hs-alumni-stories` wrapper and reads its child Groups `.hs-alumni-story`:
   - **Slides:** from the Gallery module `.hs-story-slides`, using the full-size link of each item, in gallery order (and `data-hs-srcset` from the gallery-image-real-size snippet when present).
   - **Card photo:** from the Image module `.hs-story-card`; falls back to the first slide.
   - **Blurb:** from the Text module `.hs-story-text`. H3 = name; first paragraph = meta line (e.g. "Class of 2022 · …"); the rest = blurb; any link = "View on Instagram".

   It then builds the prototype's markup and behaviour:
   - real `<button>`s with aria labels;
   - `aria-live` on the story panel;
   - keyboard ← → on the focused slider;
   - pointer swipe;
   - the card strip follows the current story.

   The original Groups are hidden once the layout is built. Without JS they remain readable, stacked.

   It doesn't touch `gallery-mosaic.js` galleries: those carry `hs-gallery-container`, which story galleries won't. Divi's own lightbox on the story gallery links isn't used (the gallery is replaced).
2. **`Site-Scripts/css/core.css`** (live global stylesheet): a new, scoped `/* Alumni stories */` block for the layout, using the brand tokens (`--hs-dark`, `--hs-gold`, Montserrat / Open Sans).
   - **Desktop:** slider and panel as one card, plus the card strip.
   - **Below 981px:** stacked.
   - **In the Visual Builder:** a light outline and label on each story Group, so editors can see the structure.
3. **`work/alumni-stories/divi-alumni-stories.json`**: a Divi Library import file (format copied from a real Divi 5.14 export) with two items:
   - **"Alumni stories (section)":** Section › Row › wrapper Group, containing one complete story Group for Nick (text filled in from his Instagram caption).
   - **"Alumni story (template)":** a single story Group, for adding new stories.

   Gallery and card image are left empty: Media Library IDs differ between local and live, so the images are picked in the builder after import.
4. **`work/alumni-stories/EDITOR-GUIDE.md`**: how to add, edit, reorder and remove a story (for the team; can also become a shared doc).
5. **`Site-Scripts/js/MANIFEST.tsv`**: add `alumni-stories.js` (live file name assigned at handover).

## Risks & rollback
- **New live script:** `alumni-stories.js` ships as a new `uploads/hs-head/<md5>.js` linked in the Divi head (Nick). If it fails, the page shows the plain stacked Groups, so content is never lost.
- **Divi import format** is new in Divi 5. The file is tested by importing on local first. If import misbehaves on live, the guide covers building the same structure by hand (it's 1 section, 1 wrapper Group and 3 modules per story).
- **CSS** is scoped to `.hs-alumni-stories*` classes, so no effect elsewhere.
- **Performance:** slides lazy-load beyond the first two; cards use the media library's sized images.
- **Rollback:**
  - remove the script tag from the Divi head;
  - remove the CSS block;
  - delete the section from the page, or leave it, since without the script it's harmless stacked content.

## Testing strategy
### Automated (Gate 4)
- `node --check` (via `tools/hs lint`) on `alumni-stories.js`; JSON validity check on the import file.
- Local: import the JSON into the local Divi Library (by the user), place the section on the local alumni page, pick Nick's slides and photo. Then on `http://headstart.local/…/alumni-network/` check:
  - the page returns 200;
  - no console errors;
  - one `.hs-as` widget is built per wrapper.

### UAT checklist for the user (Gate 5)
- [ ] Import works: Divi Library shows "Alumni stories (section)" and "Alumni story (template)".
- [ ] On the alumni page (local), Nick's story shows as in the prototype: 4:5 slider, joined navy blurb, card strip.
- [ ] Slider: arrows, dots, swipe/drag; Next story on the last slide.
- [ ] Card strip: ‹ › buttons, drag, scroll bar; picking a card switches the story; the current card is marked.
- [ ] Add a second story from the template in the builder: it appears as a new card without touching code.
- [ ] Mobile width: stacked slider, blurb, swipeable cards.
- [ ] In the Visual Builder the stories are easy to see and edit (outlined, labelled).
- [ ] The rest of the alumni page (filmstrip gallery, mosaic, quality bar slider) behaves as before.

## Amendment 1 (14 Oct): changes agreed on the prototype since approval
Design reference: canvas "Alumni stories prototype", version 27.
1. **Right-hand side when there are 4 tiles or fewer:**
   - shows a flush 2×2 grid of the *other* stories, plus a gold **"Your story next?"** invitation tile;
   - the tile has a large navy quote mark, and on desktop an arrow circle;
   - with 5 or more tiles, it stays the two-row sliding strip;
   - the current story is never shown as a card.
2. **Invitation tile content comes from Divi, not code.** An optional Text module with class `hs-story-invite` inside the wrapper Group:
   - H3 = heading;
   - first paragraph = line;
   - its link = where the tile goes.

   With no such module, no tile is shown.
3. **Instagram link:** shown as a full-width Instagram-gradient "View on Instagram" button, text only, with no logo (a logo can be added later).
4. **Blurb read-more hint:** fade plus a gently bobbing gold arrow, shown only when the text overflows and hidden once scrolled. Reduced-motion respected.
5. **Slide arrows:** see-through (faint navy, white outline and arrow) so they don't hide slide text. On mobile too: left arrow only after the first slide; "Next story" on the last slide.
6. **Mobile "More stories" row:** ‹ › buttons beside the heading, plus swipe. They reset when the story changes.
7. **Builder story rows:** each story Group shows as a row resembling the final design in the Divi 5 builder, with "Thumbnail Orientation: Portrait" in the guide.
8. **Content pipeline (for the JSON, step 3 above):**
   - Claude converts each story's slides to WebP, named `HeadStart-Phuket_Alumni-Network_Alumni-Stories_<NAME>_001.webp` (plus `_card`), into one flat `_upload/` folder (git-ignored);
   - the user bulk-uploads them to the Media Library;
   - the user saves the site's `/wp-json/wp/v2/media?search=Alumni-Stories&per_page=100&_fields=id,source_url` output;
   - Claude regenerates the JSON with all four stories (Carol, Nick, Pea, Reece; approved copy in `stories-copy.md`), real media IDs and the invitation tile.

   The same steps run again on live with live IDs.

### Added UAT items
- [ ] With 4 stories: 2×2 grid of the other 3 plus the invitation tile, on desktop; the row on mobile.
- [ ] Invitation tile text and link can be changed in Divi.
- [ ] Read-more arrow only on overflowing blurbs, gone after scrolling.
- [ ] Slide arrows visible but not hiding text; mobile arrows behave as described.
- [ ] Instagram button opens the story's post.

## Deployment notes for the dev (Gate 7)
- **New JS:** upload `alumni-stories.js` to `uploads/hs-head/` (MD5 name as usual) and add its `<script defer>` to Divi → Integration → head.
- **CSS:** append the "Alumni stories" block to the global stylesheet.
- **Content (Jeremy, not the dev):** import the JSON into the live Divi Library, place the section on the alumni page, and pick the images.
- **No permalinks flush.** Purge WP Super Cache + Cloudflare APO.
