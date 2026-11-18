# Weekly Update template: tidy layers, CSS classes, layout library — dev notes

Package `weekly-template-tidy-2026-11-18.zip` · 2026-11-18 · branch `feat/weekly-template-tidy`

**Not for Nick.** Jeremy puts this live himself (one CSS snippet plus Divi imports). This record keeps the rollback information and what went live.

## Problem
The Divi 5 weekly update template (export "Weekly Update 12") had unclear layer names, an article nested inside another article (Success Outside of School, so the article-to-post tool saw one article), and stray modules. Each article's headline was a separate text module whose look (gold left border, 1.6rem, padding) lived on that module. Merging headline and body into one module lost that look; the first attempt carried it inside the JSON (per-module free-form CSS, and a `<style>` block in every reel code block), which broke when the preset didn't import on live.

## Change
- New stylesheet `weekly-article.css`, two rules, scoped to classes only the new template and layout library use:
  - `.hs-article-text h3:first-of-type`: the article headline (first h3 in the merged text module): 1.6rem, 6px gold left border, padding 10/0/10/10, 1rem bottom margin. `!important` beats the global `h3 { margin: 0 !important; padding-bottom: 0 !important }`. Selector doesn't depend on Divi's front-end markup, so it applies in the builder too.
  - `.hs-wu-reel` (+ `iframe`): YouTube Shorts box, 9:16, up to 360px, centred, 12px corners.
- Templates (in `work/weekly-template-tidy/templates/`, imported by Jeremy): HSC and HSN Weekly Update (differ only in the Google Calendar), and a Layout Library (15 image/reel layouts). No CSS inside any JSON. Article text modules carry class `hs-article-text`; reels use `hs-wu-reel`.
- Deliberately unchanged: `core.css`, all PHP/JS, existing weekly updates and posts (nothing on them has the new classes; old #8/#10 reels keep `hs-reel` with their inline styles).

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/css/weekly-article.css | new | Custom CSS & JS entry "Weekly Article CSS" (CSS, front end, header) | — new: deactivate/remove to roll back |

## Watch out for
- Order: add the CSS entry **before** using the new templates, or headlines show without the gold line and reels as 300×150 boxes.
- If the gold line shows on the front end but not in the Divi builder, the Custom CSS & JS entry isn't loading inside the builder; put the same CSS in Divi → Theme Options → Custom CSS instead.
- After adding the CSS and after importing: clear Divi static CSS (Theme Options → Builder → Advanced), purge WP Super Cache and Cloudflare APO.
- Delete the unused "Weekly Article Text (H3 + body)" text preset left by the first import attempt.
- Full live steps (imports, saving layouts to the Library, legacy library clean-up): `work/weekly-template-tidy/TEMPLATE-GUIDE.md`.

## Quick check after deploy (optional)
Open a weekly update built from the new template: each article headline has the gold left line at the same size as older issues, and a reel shows as a tall rounded box. In the browser console, `document.querySelectorAll('.hs-article-text, .hs-wu-reel').length` is more than 0 on new issues and 0 on older ones.

## Previous version
`rollback/` is empty: the CSS is a new entry with no previous version. To roll back, deactivate the "Weekly Article CSS" entry and re-import the old template (`work/weekly-template-tidy/source/Weekly-Update-12-live-export.json`) or restore old library items from the Divi Library export / Trash.
