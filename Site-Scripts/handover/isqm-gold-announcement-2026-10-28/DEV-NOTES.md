# ISQM Gold announcement: sitewide pop-up — dev notes

Package `isqm-gold-announcement-2026-10-28.zip` · 2026-10-28 · branch `feat/isqm-gold-announcement`

## Problem
HeadStart was accredited ISQM Gold by Education Development Trust (report published 26 Oct 2026). We want a short celebratory pop-up with gold confetti on every public page for the length of the announcement (about 2 to 4 weeks), that can be switched off in one step. Nothing existing needed fixing: this is one new, self-contained Code Snippet.

## Change
One new snippet, `isqm-gold-announcement.code-snippets.php`. It touches no other snippet, no `core.css` and no Custom CSS & JS file.

- **Hook:** `wp_footer` (priority 99) prints one `<style id="hs-isqm-css">` and one `<script id="hs-isqm-js">`. Everything is namespaced `#hs-isqm` / `.hs-isqm-*`, and the JS runs inside one function with no globals.
- **Where it runs:** every public front-end page, weekly updates included. It does not print in wp-admin, AJAX, REST, feeds, embeds, previews, the Customizer or the Divi Visual Builder / Theme Builder (`et_fb`, `et_tb`).
- **Date window:** settings at the top of the snippet: `HS_ISQM_START` (2026-10-28) and `HS_ISQM_END` (2026-11-30), compared with the site's date. Outside the window nothing is printed.
- **How often a visitor sees it:** the first time they load any page, then never while they browse. If they return 3 or more days later, it shows once more. This uses the browser's `localStorage` key `hs_isqm_gold_v1` (a timestamp, saved when the pop-up opens). `HS_ISQM_REPEAT_DAYS` (3) sets the gap. Raise `HS_ISQM_VERSION` to show it to everyone again. If storage is blocked it shows once per page view.
- **Content:** the ISQM logo is embedded in the snippet (no media library upload, no external requests), the explanation text, eight numbered areas, and one word-for-word quote from the EDT report for each area, in a sideways slider. The bold words are our emphasis. Shorter versions are used on phones. Please do not reword the quotes.
- **Link:** "Read the full story" goes to `/accreditations/` (`HS_ISQM_POST_URL`, relative to the site home).
- **Behaviour:** gold confetti on a full-screen canvas (skipped for visitors who prefer reduced motion); close with the Close button, Escape or a click outside the card; arrow keys, arrows, dots, swipe or the numbered areas change the quote; focus is trapped in the dialog and returned afterwards; page scroll is locked while it is open.
- **Look:** navy card, white text, brand yellow accents. The backdrop and gold close button follow the site's lightbox style. Selectors are prefixed with `#hs-isqm` and text colours carry `!important` so Divi and global styles cannot recolour it. Layout changes at 768, 640 and 370 px wide.
- **Testing aid:** add `?hs_isqm=1` to any URL to show it again regardless of the saved flag, `?hs_isqm=0` to keep it hidden. Both are ignored outside the date window.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/isqm-gold-announcement.code-snippets.php | new | `Code Snippets: isqm-gold-announcement` | — new: deactivate/remove to roll back |

## Watch out for
- It is a new snippet, so there is nothing to replace. Create it in Code Snippets as a **PHP snippet that runs on the front end only** ("Only run on site front-end"), not "Run everywhere". Activate it when the announcement should start (it is also gated by the date window).
- No permalink flush is needed. No rewrite rules, options, post meta or DB changes.
- The pop-up is rendered by the browser from data in the page, and the "seen" check runs in the browser, so full-page caching is fine. It does need the caches purged after activating and again after deactivating (see below).
- It sits at z-index 2147483000. The existing staff directory modal uses 2147483647, so it would sit above this pop-up if both were open at once. Please glance at the header and mega menu with the pop-up open, as only the pop-up itself was checked locally.
- Purge WP Super Cache and Cloudflare APO after deploying if URLs, redirects or page output changed. Here the page output changes, so purge both.
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.
- To end the campaign: deactivate the snippet (or let `HS_ISQM_END` pass), then purge both caches. The window is currently 28 Oct to 30 Nov 2026; adjust `HS_ISQM_END` if the campaign is shorter.

## Quick check after deploy (optional)
Open `https://headstartphuket.com/en/our-school/?hs_isqm=1` in a private window: the navy "HeadStart is Gold!" pop-up opens with gold confetti, "Read the full story" goes to `/accreditations/`, and reloading without `?hs_isqm=1` in the same window does not show it again.

## Previous version
`rollback/` holds the live files as they were before this change, if you need to revert. This task has no previous version of anything: deactivate the new snippet instead.
