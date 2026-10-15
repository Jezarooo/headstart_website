# Alumni stories: sharper slides and Visual Builder preview (update) — dev notes

Package `alumni-stories-live-import-2026-10-15.zip` · 2026-10-15 · branch `feat/alumni-stories-live-import`

**This is an update to the two files you already deployed from `alumni-stories-2026-10-14`** (`alumni-stories.js` and `core.css`). Same files, small changes. No new files, no new script tag, no PHP.

## Problem
1. **Soft slides on normal screens.** The slider told the browser the slide slot is ~440px wide (`sizes`) and listed WordPress's intermediate sizes (`srcset`). On a 1x screen (e.g. 1920×1080) the browser picked WordPress's re-compressed `-480x600` copy instead of the full 1080×1350 file. Retina screens picked the full file, which is why it looked fine there.
2. **Visual Builder took too much space.** The script deliberately did nothing in the builder, so editors saw each story as a tall "story row" (slides | blurb | card). We want the builder canvas to show the same layout as the live page, with stories edited through the Layers panel.

## Change
- **`alumni-stories.js`**
  - *Slides:* the slide `<img>` now uses the full-size file (the gallery link) with no `srcset`/`sizes`. Slides after the second are still `loading="lazy"`. Cards keep the small copy (`sizes="260px"`).
  - *Builder:* in the Visual Builder the script now builds the same widget (`watchBuilder()`). The original Groups stay in the DOM but are hidden, so the Layers panel keeps working. A `MutationObserver` on `document.body` rebuilds the widget about 350ms after content changes (text, image `src`/`href`/`srcset`, added or removed Groups). It ignores changes inside the widget itself, keeps the current story and slide across rebuilds, re-hides the Groups if Divi re-renders them, and removes its window listeners before each rebuild. Any exception puts the plain Groups back (and logs one `console.warn`), so editing is never blocked.
  - *Front end:* behaviour unchanged apart from the slide images. The code that reads the Groups was moved into `readAll()` so both paths share it.
- **`core.css`** (alumni block only)
  - Removed the builder-only "story row" styling: the dashed outlines, the numbered labels and the "Card photo" caption (all `body.et-fb …` rules, 24 lines).
  - Added one small rule: in the builder, once the widget is built, the original Groups and the invite Text module are not drawn (`body.et-fb .hs-alumni-stories.hs-as-ready …`).
  - The no-JS fallback "story rows" stay as they were, so the plain Groups still display if the script fails.
- **Unchanged:** everything outside the alumni block in `core.css`, all other scripts and snippets.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/css/core.css | modified | `Global stylesheet (core.css)` | rollback/css/core.css |
| files/js/alumni-stories.js | modified | replaces the alumni-stories script you deployed on 14 Oct (keep your existing file name and Divi head tag) | rollback/js/alumni-stories.js |

## Quick check after deploy (optional)
On the alumni page, paste in the console:
```js
[...document.querySelectorAll('.hs-as-slide img')].map(i => [i.hasAttribute('srcset'), i.currentSrc.split('/').pop()])
```
- **Expected:** every row is `false` with a file name that has no `-480x600` in it.
- **Builder:** open the alumni page in the Visual Builder; the canvas shows the finished slider, and editing a story in the Layers panel updates it a moment later.

## Previous version
`rollback/` holds the live files as they were on 15 Oct, if you need to revert. Reverting both files returns to the 14 Oct behaviour.
