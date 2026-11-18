# Plan: Weekly Update template: tidy layers, CSS classes, layout library

- Task: `feat/weekly-template-tidy` · opened 2026-11-18
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-13`
- Roadmap: `weekly-template-tidy`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## In plain English
The weekly update template gets tidied: clear layer names, one text block per article (the headline sits inside it, styled exactly as before), and ready-made image and reel layouts to copy. The styling for headlines and reels moves out of the template and into one small site stylesheet, so the template carries no CSS of its own. You'll notice cleaner layers and faster weekly assembly; readers should see no change. The main risk is order: the new stylesheet must be live **before** the new template is used, or headlines lose their yellow line and reels show as small boxes.

## Problem / goal
- The current template (Weekly Update 12 export) has unclear layer names, a nested article in Success Outside of School, and stray modules.
- Headline and body are separate text modules; merging them simplifies editing but the headline's look (yellow left border, size, spacing) lived on the separate module.
- The first version put that styling inside the JSON (per-module free-form CSS, and a `<style>` block in every reel code block). Jeremy wants no CSS in the JSON: styling should live in one place on the site.
- Image layouts are inconsistent (single images in groups, "4xGrid" that is really 2 columns, stacked groups). A clean set of layouts, plus reel layouts, should be saved to the Divi Library.

Agreed in the session on 18 Nov (layer names, sections kept/placeholder, 11 image layouts, 3 reel layouts, phones stack 3 reels).

## Proposed changes
**1. New file `Site-Scripts/css/weekly-article.css`** (new Custom CSS & JS entry on live, like Mobile Menu CSS; `core.css` is not touched)
- `.hs-weekly-article .hs-article-text h3` — the article headline inside the merged text module: 1.6rem, 6px gold left border, padding `10px 0 10px 10px`, `margin: 0 0 1rem`. Values copied from the old headline module (preset "H2 Default (Left Border)" + per-module H3 size), checked against Weekly Update #9 on local.
- `.hs-reel` and `.hs-reel iframe` — YouTube Shorts embed box: 9:16, full width up to 360px, centred, 12px corners. Same rules that are currently pasted inside each reel code block in #8 and #10.
- Scoped to these two classes only, so existing weekly updates, posts and other pages are unaffected (old reels keep their inline style; the rules match, so no visual change).

**2. Weekly Update template JSON** — tracked at `work/weekly-template-tidy/Weekly-Update-Template.json` (Jeremy imports it on live; not a Site-Scripts file)
- Article text modules: Paragraph Default preset + CSS class `hs-article-text`; remove the per-module free-form CSS and H3 size (now in the stylesheet).
- Reel code blocks: just the `<div class="hs-reel"><iframe …></iframe></div>` embed, no `<style>`.
- Everything else as agreed: HSC Weekly Update section; named fixed rows (Announcements, Upcoming Events, Upcoming Meetings & Presentations, Meetings & Presentations, Success Outside of School as one article with CTA, Promotions); two copyable "Section" rows holding every image and reel layout; headlines with proper names; galleries pre-filled with the 12 live placeholders (IDs 993210–993221); no Images wrappers.

**3. Layout library JSON** — `work/weekly-template-tidy/Weekly-Update-Layout-Library.json`
- One row with each layout as its own labelled group, ready to right-click → Save to Library on live: Single image, 2 across, 3 across, 2 × 2, 1 + 2, 1 + 3, 1 + 4, 2 then 3, 2 + 4, Gallery (12 placeholders), 1 + gallery, Reel – right column, Reels – 2 across, Reels – 3 across.
- Grids use Divi 5 column spans (one group per layout); 4-column layouts drop to 2 on phones; 3 reels stack on phones.
- Replaces the old library items (Weekly Update – Images – Single Image / 2xGrid / 3 Cols / 4xGrid / Gallery). Jeremy renames the old ones "OLD –" first and deletes them once happy.

**4. Builder scripts** — `work/weekly-template-tidy/scripts/` (the Python used to tidy the export and build the layouts, so the template can be rebuilt and later reused by the bulk import). Local-only, never shipped.

No PHP, JS, hooks, rewrite rules or DB keys change.

## Risks & rollback
- **Order on live:** the stylesheet entry must be active before the template is imported/used. If the template is used first: headlines show without the gold line at a smaller size, reels show as 300×150 boxes. Fix: activate the CSS entry.
- **Divi static CSS / caches:** after import, stale CSS files can show old styling (seen on local). Clear Divi static CSS (Theme Options → Builder → Advanced), purge WP Super Cache and Cloudflare APO.
- **Specificity:** the global stylesheet forces `h3 { margin: 0 !important; padding-bottom: 0 !important }`; the new rule uses `!important` with a more specific selector to win. Checked on local; re-checked at UAT.
- **Posts:** the article-to-post tool copies the article's block into the post and removes the first h3; the class travels with the module, so any other h3 in the post body gets the same headline look (as it would on the weekly page). Expected, no change needed.
- **Left-over preset:** the failed first import on live created a "Weekly Article Text (H3 + body)" preset; Jeremy deletes it (unused).
- **Rollback:** deactivate the Weekly Article CSS entry; re-import the previous template (Weekly Update 12 export is in `Latest-Drop`, and the old library items stay until Jeremy deletes them).

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` (nothing to lint in PHP/JS; CSS file checked to parse).
- Local test weekly update (draft-free test post `zz-template-test`, ID 993466) rebuilt from the class-based template, Divi cache cleared, then in the browser:
  - all 21 article headlines: 25.6px, 6px gold left border, padding 10/0/10/10, 16px gap to body — desktop and phone; compared with #9 Chaofah (`/chaofah/weekly-update/2026/10/weekly-update-9/`);
  - every image layout and reel layout at desktop and 375px phone width (sizes as recorded in the session);
  - the template JSON contains no `freeForm` and no `<style>` (grep).
- `http://headstart.local/` and the test post return 200; #9 Chaofah still renders headlines as before (old reels unchanged).

### UAT checklist for the user (Gate 5)
- [ ] Local test post: headlines look exactly like a normal weekly update (gold line, size, spacing) on desktop and phone.
- [ ] Each image layout looks right on desktop and phone (2 + 4 and 1 + 4 drop to 2 columns; single and full-width images full width).
- [ ] Reel – right column: text left, reel right; on phone the reel goes under the text.
- [ ] Reels – 3 across stacks one per row on phone.
- [ ] Layer names in the Divi builder read as agreed.
- [ ] Import the layout library JSON on local, save one layout to the Library, insert it into an article below the text.

## Deployment notes for the dev (Gate 7)
- **Nick:** create a new Custom CSS & JS entry "Weekly Article CSS" (CSS, front end, header) with the contents of `weekly-article.css`, published. Ships in the combined pack with the other features (not on its own).
- **Jeremy, after the CSS is live:** import the template JSON (Divi → Library / Portability), delete the stray "Weekly Article Text (H3 + body)" preset, import the layout library JSON, save each layout to the Library, rename old image library items "OLD –".
- No permalinks flush. Purge Divi static CSS, WP Super Cache and Cloudflare APO after the CSS entry goes live and after the template import.
