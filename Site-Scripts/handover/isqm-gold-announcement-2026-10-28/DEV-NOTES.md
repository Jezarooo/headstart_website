# ISQM Gold announcement: pop-up, inline block, Quality Bar and settings page — dev notes

Package `isqm-gold-announcement-2026-10-28.zip` · 2026-10-28 · branch `feat/isqm-gold-announcement`

## Problem
HeadStart was accredited ISQM Gold by Education Development Trust (report published 26 Oct 2026). We want (1) a celebratory pop-up with gold confetti for the length of the announcement (about 2 to 4 weeks), (2) the same content available inline on pages, and (3) a refreshed Quality Bar that carries the ISQM Gold badge and the report quotes. The marketing team wants to switch things on and off themselves, so the package has a settings page. Nothing existing needed fixing: this is one new, self-contained Code Snippet.

## Change
One new snippet, `isqm-gold-announcement.code-snippets.php`. It touches no other snippet, no `core.css` and no Custom CSS & JS file. It adds:

### 1. Sitewide pop-up (`wp_footer`, priority 99)
- Prints one `<style id="hs-isqm-css">` and one `<script id="hs-isqm-js">`, namespaced `#hs-isqm` / `.hs-isqm-*`. The JS runs in one function with no globals.
- Navy card, white text, brand yellow accents, gold confetti, ISQM logo (embedded, no media upload), explanation, eight numbered areas and one word-for-word EDT quote per area in a sideways slider, "Read the full story" button. Backdrop and gold close button follow the site's lightbox style; on desktop the Close button sits at the top right of the screen, on phones it stays inside the card. The card uses nearly the full window height so the button is not cut off on shorter laptop screens. Layout changes at 768, 640 and 370 px.
- Once per visitor (browser `localStorage` key `hs_isqm_gold_v{version}`), reminded again after N days. If storage is blocked it shows once per page view.
- Does not print in wp-admin, AJAX, REST, feeds, embeds, previews, the Customizer or the Divi Visual Builder / Theme Builder (`et_fb`, `et_tb`).
- Testing aid: `?hs_isqm=1` while logged in as an administrator shows it whatever the settings say; for visitors `?hs_isqm=1` only re-shows it when the pop-up is on and the page is allowed. `?hs_isqm=0` keeps it hidden.

### 2. Settings page (Settings > ISQM Gold, administrators only)
Stored in one option, `hs_isqm_settings`. The constants at the top of the file are the defaults, so the snippet works with nothing saved.
| Control | Default | Effect |
|---|---|---|
| Show the pop-up sitewide | on | Master switch. Off stops the pop-up and the confetti everywhere. Shortcodes keep working. |
| Days before it shows again | 3 | Reminder gap for returning visitors. 0 = every page load (testing). |
| Show between (start, end) | 2026-10-28 to 2026-11-30 | Site-time dates. Empty = no limit. |
| "Read the full story" link | /accreditations/ | Full address or path. |
| Show on weekly update pages | on | Off hides it on every page whose address contains `/weekly-update/`. |
| Pages to leave out | empty | One link per line (full URL or path; only the path is compared, a language prefix such as /en/ is ignored). A trailing `*` matches everything below. Example: `https://headstartphuket.com/chaofah/weekly-update/2026/10/weekly-update-8/`. Next week is the same with `-9`. |
| Show to logged-in administrators | on | Off stops it appearing while the team edits the site. |
| Show again to everyone | off | Tick and save to change the reset code, so people who already saw it see it again. |
Saving also clears WP Super Cache if present. The page also holds a shortcode reference and a live preview of both shortcodes.

### 3. Shortcode `[hs_isqm_gold]` (inline block, no pop-up or confetti)
The heading "Rated outstanding in all eight areas", the numbered areas, the quote panel with medal and source, the "Read the full story" button, and arrows and dots. Attributes: `show="heading,areas,quotes,button"` (any subset), `link="/news/"` (override the button link), `boxed="yes"` (puts it on its own navy card, for light sections; the default is for navy sections). Several can sit on one page. Server-rendered markup with a small script.

### 4. Shortcode `[hs_quality_bar]` (replaces the Divi "Quality Bar v4" section)
A 172px slider: yellow badge panel on the left (award icon, "Rated Outstanding in all eight areas", "by Education Development Trust"), slides on the right with the current top-right arrow buttons (copied from `core.css`) and a counter. The eight EDT quotes sit between the existing slides. The existing slides are unchanged (1600+ students, 100% IGCSE pass rate and 42% A*/A, accreditations with "ISQM Accredited" now "ISQM Gold", 47% A-Level, universities, environment, 60% BTEC); the stats are not from the EDT report and are kept as they were. The slide text lives in `hs_qbar_slides()` in the snippet. On phones the badge becomes a rounded stacked panel and slides are centred at one fixed height.

Shortcode CSS is printed beside the first shortcode of each request and the JS (delegated, so it works on markup inserted later) in the footer, only on pages that use a shortcode. In the Divi Visual Builder both also print so the bar previews as it will look (slides side by side, not stacked).

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/isqm-gold-announcement.code-snippets.php | new | `Code Snippets: isqm-gold-announcement` | — new: deactivate/remove to roll back |

## Watch out for
- New snippet, nothing to replace. Create it as a **PHP snippet that runs everywhere** ("Run snippet everywhere"), not front end only: the settings page needs it to load in wp-admin. The front-end output is already guarded inside the code.
- The settings and shortcodes were tested in a PHP/WordPress stub and in a browser. WordPress's own settings save (options.php) is only exercised on the local site, so check saving once after activating.
- No permalink flush is needed. The only DB change is the new option `hs_isqm_settings` (created on first save; removing the snippet leaves it behind, harmless).
- The pop-up is rendered by the browser from data in the page and the "seen" check runs in the browser, so full-page caching is fine. Exclusions, the weekly switch and the on/off switch are decided on the server, so purge WP Super Cache and Cloudflare APO after changing them (saving the settings clears WP Super Cache; Cloudflare APO still needs purging).
- z-index of the pop-up is 2147483000; the staff directory modal uses 2147483647. Glance at the header and mega menu with the pop-up open.
- Shortcodes: in Divi, put them in a Code or Text module. `[hs_quality_bar]` fills the width of its column, so give it a full-width row (the section currently using the Divi slider can be removed once the shortcode is in place).
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.
- To end the campaign: untick "Show the pop-up" (shortcodes stay), or deactivate the snippet to remove everything, then purge both caches.

## Quick check after deploy
1. Settings > ISQM Gold opens, shows the live preview of both shortcodes, and saving works.
2. Private window, `https://headstartphuket.com/en/our-school/?hs_isqm=1` while logged out: pop-up appears only if the pop-up is on; reloading without the parameter does not show it again.
3. Add `[hs_quality_bar]` and `[hs_isqm_gold]` to a test page and check desktop and phone.
4. Add a weekly update link to "Pages to leave out" and confirm the pop-up does not show on it.

## Previous version
This task has no previous version of anything: deactivate the new snippet instead. The Divi Quality Bar section is untouched until someone replaces it with `[hs_quality_bar]`.
