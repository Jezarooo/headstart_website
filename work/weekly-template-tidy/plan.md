# Plan: Weekly Update template: tidy layers, CSS classes, layout library

- Task: `feat/weekly-template-tidy` · opened 2026-11-18
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-13`
- Roadmap: `weekly-template-tidy`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## In plain English
Three things, all put live by Jeremy himself (nothing for Nick): a tidied weekly update template, a library of ready-made image and reel layouts, and a short guide to using them, plus one small CSS snippet that gives the headline its gold line and the reels their shape. The template itself carries no CSS. Readers should see no change. The only risk is order: add the CSS snippet **before** using the new template, or headlines lose their gold line and reels show as small boxes.

## Problem / goal
- The current template (Weekly Update 12 export) has unclear layer names, a nested article in Success Outside of School, and stray modules.
- Headline and body are separate text modules; merging them simplifies editing, but the headline's look (gold left border, size, spacing) lived on the separate module.
- The first version put that styling inside the JSON (per-module free-form CSS, and a `<style>` block in every reel code block). Styling should live in one place on the site instead.
- Image layouts are inconsistent (single images in groups, "4xGrid" that is really 2 columns, stacked groups). A clean set of image and reel layouts should be saved to the Divi Library.

Agreed in the session on 18 Nov (layer names, sections kept/placeholder, 11 image layouts, 3 reel layouts, phones stack 3 reels, CSS added by Jeremy as a snippet, no package for Nick).

## Proposed changes
**1. CSS snippet: `Site-Scripts/css/weekly-article.css`** (working copy of what Jeremy pastes into live; `core.css` is not touched)
- `.hs-weekly-article .hs-article-text h3`: the headline inside the merged text module. 1.6rem, 6px gold left border, padding `10px 0 10px 10px`, `margin: 0 0 1rem`. Values copied from the old headline module (preset "H2 Default (Left Border)" + per-module H3 size), checked against Weekly Update #9 on local.
- `.hs-reel` and `.hs-reel iframe`: the YouTube Shorts box. 9:16, full width up to 360px, centred, 12px corners. Same rules currently pasted inside each reel in #8 and #10.
- Only these two classes, so existing weekly updates, posts and other pages are unaffected. Old reels keep their inline style; the rules match, so they look the same.
- Kept in `Site-Scripts/css/` so the local site loads it and `hs drift` can compare it with live after Jeremy adds it.

**2. Template JSON: `work/weekly-template-tidy/Weekly-Update-Template.json`**
- Article text modules: Paragraph Default preset + CSS class `hs-article-text`. No per-module CSS or H3 size.
- Reel code blocks: just `<div class="hs-reel"><iframe …></iframe></div>`, no `<style>`.
- As agreed: HSC Weekly Update section; named fixed rows (Announcements, Upcoming Events, Upcoming Meetings & Presentations, Meetings & Presentations, Success Outside of School as one article with CTA, Promotions); proper headlines; no Images wrappers.
- Two copyable "Section" rows, **2 articles each**, with varied image layouts and **no reels** (all layouts, reels included, live in the layout library): Section 1 = "1 + gallery" and "2 across"; Section 2 = "1 + 3" and "2 then 3". The gallery is pre-filled with the 12 live placeholders (IDs 993210–993221).

**3. Layout library JSON: `work/weekly-template-tidy/Weekly-Update-Layout-Library.json`**
- One row, each layout its own labelled group, ready to Save to Library: Single image, 2 across, 3 across, 2 × 2, 1 + 2, 1 + 3, 1 + 4, 2 then 3, 2 + 4, Gallery (12 placeholders), 1 + gallery, Reel – right column, Reels – 2 across, Reels – 3 across.
- Replaces the old library items (Weekly Update – Images – Single Image / 2xGrid / 3 Cols / 4xGrid / Gallery).

**4. Template guide: `work/weekly-template-tidy/TEMPLATE-GUIDE.md`**
Short how-to for whoever builds the weekly update: the layer structure (Section → Article → Text + one layout), the one rule (images and reels go **inside** the Article, below the Text), which sections are fixed and which are copied, typing the exact Website Section name in a copied heading, how to add a layout from the Library, swapping a reel's YouTube ID, gallery minimum of 12, DNP and watermark reminder, and the cache purge after changes.

**5. Builder scripts: `work/weekly-template-tidy/scripts/`**
The Python used to tidy the export and build the layouts, so the files can be rebuilt and later reused by the bulk import. Local only.

No PHP, JS, hooks, rewrite rules or DB keys change.

## Risks & rollback
- **Order on live:** add the CSS snippet before using the template. If the template comes first: headlines show without the gold line at a smaller size, reels show as 300×150 boxes. Fix: add the snippet.
- **Divi static CSS / caches:** after changes, stale CSS files can show old styling (seen on local). Clear Divi static CSS (Theme Options → Builder → Advanced), purge WP Super Cache and Cloudflare APO.
- **Specificity:** the global stylesheet forces `h3 { margin: 0 !important; padding-bottom: 0 !important }`; the new rule uses `!important` with a more specific selector to win. Checked on local, re-checked at UAT.
- **Posts:** the article-to-post tool copies the article into the post and removes the first h3; the class travels with the module, so any other h3 in the post body gets the headline look, the same as on the weekly page. Expected.
- **Left-over preset:** the first import on live created an unused "Weekly Article Text (H3 + body)" preset; Jeremy deletes it.
- **Rollback:** remove the CSS snippet; re-import the previous template (Weekly Update 12 export is in `Latest-Drop`; old library items stay until Jeremy deletes them).

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` (no PHP/JS changes; the CSS file is checked to parse).
- Local test weekly update `zz-template-test` (ID 993466) rebuilt from the class-based template, Divi cache cleared, then in the browser:
  - all article headlines: 25.6px, 6px gold left border, padding 10/0/10/10, 16px gap to body, at desktop and phone width; compared with #9 Chaofah (`/chaofah/weekly-update/2026/10/weekly-update-9/`);
  - every image and reel layout at desktop and 375px phone width (sizes as recorded in the session);
  - both JSON files contain no `freeForm` and no `<style>` (grep).
- `http://headstart.local/` and the test post return 200; #9 Chaofah renders as before.

### UAT checklist for the user (Gate 5)
- [ ] Local test post: headlines look exactly like a normal weekly update (gold line, size, spacing) on desktop and phone.
- [ ] Each image layout looks right on desktop and phone (1 + 4 and 2 + 4 drop to 2 columns on phone).
- [ ] Reel – right column: text left, reel right; on phone the reel goes under the text. Reels – 3 across stacks on phone.
- [ ] Layer names in the Divi builder read as agreed.
- [ ] Layout library imported on local; one layout saved to the Library and inserted into an article below the text.
- [ ] The template guide is clear enough to follow without asking.

## Deployment notes for the dev (Gate 7)
- **No package for Nick.** Handover runs only to record what went live and keep the rollback copy.
- **Jeremy, on live, in this order:**
  1. Add the CSS snippet (`weekly-article.css`) as a new CSS entry (Custom CSS & JS, front end, header), published.
  2. Clear Divi static CSS; purge WP Super Cache and Cloudflare APO.
  3. Import the template JSON; delete the stray "Weekly Article Text (H3 + body)" preset.
  4. Import the layout library JSON; save each layout to the Library; rename the old image library items "OLD –".
- No permalinks flush.
