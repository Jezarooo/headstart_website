# Plan: Alumni stories: live import, sharper slides, builder view

- Task: `feat/alumni-stories-live-import` · opened 2026-10-15
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-15` (taken 15 Oct; `core.css` and the live alumni script are byte-identical to the working copies)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
The alumni-stories slider is live (JS + CSS deployed by Nick). Three things remain:
1. **Content:** the Divi section with the four stories still has to be imported on live. The 35 slides are uploaded; the media IDs are exported (`media-live.json`).
2. **Sharpness:** on a normal-density screen (1920×1080, DPR 1) the slides look soft. Measured on live: the slider loads WordPress's re-compressed `-480x600` copies, because the `sizes` hint tells the browser the slot is ~440px. Retina screens fetch the full file, which is why they look fine. Loading the full 1080×1350 file for slides was tested live in the console and fixed it.
3. **Builder view:** in the Divi Visual Builder the stories show as tall "story rows" with dashed outlines, which takes too much space. Wanted: the builder canvas shows the same slider as the front end; stories are edited through the Layers panel.

## Proposed changes
1. **`Site-Scripts/js/alumni-stories.js`**
   - *Sharper slides:* slide `<img>` uses the full-size link (no `srcset`/`sizes`), so DPR 1 screens get the 1080px file. Cards keep the small copy (`sizes="260px"`).
   - *Builder:* build the widget in the Visual Builder too (today `init()` returns early when `inBuilder()`).
     - The original Groups stay in the DOM but are collapsed out of sight with CSS (not removed), so Divi and the Layers panel keep working.
     - The widget is inserted as a sibling after the Groups and rebuilt (debounced) when the Groups change, so edits show up.
     - Guards against loops (the observer ignores its own changes) and duplicates (one widget per wrapper, replaced on rebuild).
     - Everything is wrapped in try/catch: if the build throws, the plain Groups stay visible, as today.
2. **`Site-Scripts/css/core.css`** (alumni block only): remove the builder "story rows" styling (outlines, labels, "Card photo" captions, around lines 13797–13917); add the rule that collapses the original Groups in the builder once the widget exists. The front-end and no-JS fallback styles stay.
3. **`Site-Scripts/js/MANIFEST.tsv`:** correct the live file name of `alumni-stories.js` to `9ea1406fcfab969a5641b86346a34089.js` (the MD5 of the content, and what is on live). The old `3e3af957…` entry was wrong. The name changes again to the new content's MD5 at handover.
4. **`work/alumni-stories-live-import/`** (already committed): `media-live.json`, `make-live-import.py`, `alumni-stories-live-import.json`: the Divi import file with live media IDs (IDs matched by file name and slide number).
5. **`work/alumni-stories/EDITOR-GUIDE.md`:** update the builder section (stories are edited in the Layers panel; the canvas shows the live layout).

## Risks & rollback
- **Divi 5 builder:** Divi manages the canvas itself, so adding or hiding elements there can cause glitches (duplicates, a canvas error, the widget not refreshing). Tested on local first. If it misbehaves, fallback: ship without builder rendering and use a CSS-only compact row per story.
- **Heavier slide files:** about 160 KB per slide instead of ~40 KB, but slides beyond the second are lazy-loaded.
- **Two live files change:** the JS (new hash name, so the Divi head `<script src>` must be updated) and `core.css`. Rollback: the previous JS file and the live `core.css` from the baseline; both are put in `rollback/` at handover. The import file only adds two Divi Library items; delete them to roll back.
- Front-end caching: purge WP Super Cache and Cloudflare APO after deploy, or visitors keep the old script and CSS.
- Not in scope: the undeployed `*-divi-gatekeeper` editor patch and the other drift items (all unrelated).

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` (`node --check` on the JS); JSON validity of the import file (done: valid, 35 live IDs, 4 stories).
- Local: alumni page returns 200; no console errors; one widget per wrapper; slide `<img>` has no `srcset` and loads the full file.

### UAT checklist for the user (Gate 5)
- [ ] Local front end: slider, blurb, card grid and invitation tile as before; slides load the full-size file (console check).
- [ ] Local Visual Builder: the canvas shows the same slider as the front end, and the layout is compact.
- [ ] Builder: edit text, a photo and a story's order in the Layers panel; the canvas updates; no duplicates or errors.
- [ ] Builder: after deleting or duplicating a story the canvas stays correct.
- [ ] If the widget fails to build, the plain Groups still show and can be edited.
- [ ] On live (after deploy): import the Divi file, place the section, slides are sharp on a 1920×1080 screen.
- [ ] Rest of the alumni page (filmstrip, mosaic, quality bar slider) behaves as before.

## Deployment notes for the dev (Gate 7)
- Replace the live script `uploads/hs-head/9ea1406fcfab969a5641b86346a34089.js` with the new `alumni-stories.js` and update its `<script src>` in the Divi head to the new name (DEV-NOTES will give it).
- Replace `core.css` (the whole file as it is on live today, plus our alumni block change).
- No snippet changes; no permalink flush.
- Purge WP Super Cache and Cloudflare APO.
- The user imports `alumni-stories-live-import.json` into live Divi (no dev action).
