# Plan: ISQM Gold announcement: site-wide pop-up and weekly update feature

- Task: `feat/isqm-gold-announcement` · opened 2026-10-28
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-28`
- Mock-up (approved by the user in review): https://claude.ai/artifact/HCTHbFd4io188ZpSt3jHEC

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
HeadStart has been accredited ISQM Gold by Education Development Trust (report published 26 Oct 2026). We want a celebratory announcement on the website:
A once-per-visitor pop-up on every page of the site, weekly updates included ("Proud to announce: HeadStart is Gold!"), with gold confetti, the ISQM logo, a short explanation of the award, a swipeable set of eight real quotes from the report (one per area, numbered 01 to 08) and a "Read the full story" button to the news post.
Scope change (user, 28 Oct, at approval): the pop-up runs sitewide as one package, with no weekly-update-only treatment (no feature block, no confetti-only mode). A weekly-update variant may come later, to be discussed first. The whole announcement is one Code Snippet, so it can be switched off in one step when the campaign ends.

All wording is checked against the EDT report (`Copywriting/ISQM-Report-2026-HeadStart-full-text.md`). Quotes are word for word; shortened mobile versions end with an ellipsis or a full sentence.

## Proposed changes
One new file, nothing else touched, so it cannot conflict with Nick's live edits to `core.css` and other snippets (the 28 Oct snapshot shows drift in core.css, alumni-stories.js and four PHP snippets; none are touched here).

- **New:** `Site-Scripts/php/isqm-gold-announcement.code-snippets.php`, a Code Snippet that runs on the front end only (not in admin, feeds, REST or AJAX).
  - Settings at the top: `HS_ISQM_START` and `HS_ISQM_END` (date window, default start 2026-10-28, end 2026-11-30 for confirmation), `HS_ISQM_POST_URL` (the "Read the full story" link, placeholder until the news post exists), `HS_ISQM_VERSION` (bump to show the pop-up again to everyone).
  - `wp_footer` output: the pop-up template (hidden `<template>`), its CSS (all rules namespaced `.hs-isqm-*`, using the site tokens `--hs-font-head` and `--hs-dark`) and its JS (confetti canvas, slider, focus trap, Escape and backdrop close, swipe, reduced-motion respected).
  - Pop-up on every front-end page; shows once per visitor using `localStorage` key `hs_isqm_gold_v{VERSION}` (wrapped in try/catch; if storage is blocked it shows once per page view).
  - Confetti plays when the pop-up opens.
  - Logo images are referenced from the media library (ISQM.png already in uploads/2026/08); the HeadStart logo uses the existing site logo URL.
  - Styling matches the site lightbox (80% black backdrop with blur, 90% width, gold pill close button, 36px circle on mobile).
- **Mock-up kept for reference** (its weekly update tab shows a variant that is not being built now): `work/isqm-gold-announcement/mockup/` (the approved HTML and the report-quote check).

No rewrite rules, no DB or post meta changes, no new options.

## Risks & rollback
- **Caching:** WP Super Cache and Cloudflare APO serve full pages; the pop-up is rendered client side from a template that is the same for everyone, and the once-per-visitor check runs in the browser, so cached pages are fine. Purge both caches after activating so the footer output appears.
- **Divi and Theme Builder pages:** the pop-up is printed in the footer, so it does not depend on any template. Checked on a standard page, the home page and a weekly update in UAT.
- **Other scripts:** all selectors and the localStorage key are prefixed `hs-isqm` / `hs_isqm`; the script runs inside one function with no globals; z-index is checked against the mega menu and the staff and gallery modals during UAT.
- **Accessibility:** dialog role, labelled title, focus trap, Escape to close, visible focus, `prefers-reduced-motion` turns off confetti and the slide animation.
- **Rollback:** deactivate the snippet in Code Snippets (instant), then purge both caches. Nothing else changes.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the new PHP and its embedded JS (external `php -l` receipt, as the device shell has no PHP).
- `http://headstart.local/`, a standard page and a single weekly update each return 200 and contain `hs-isqm-modal`; `wp-admin` and `wp-json` responses do not contain `hs-isqm`.
- With the date window set in the past, the home page contains no `hs-isqm` output.

### UAT checklist for the user (Gate 5)
- [ ] Home page: confetti and the pop-up appear; close with the X, Escape and a click outside.
- [ ] Reload the page: the pop-up does not appear again. Clear site data: it appears again.
- [ ] The eight numbered areas select the matching quote; arrows, dots, swipe and the arrow keys slide left and right.
- [ ] "Read the full story" goes to the news post link.
- [ ] A single weekly update: the same pop-up and confetti appear (once per visitor across the whole site).
- [ ] Phone (390 wide) and small phone (360 wide): the card fits without scrolling, with a margin around it.
- [ ] Tablet and desktop widths look right. The pop-up is light only; the mock-up dark theme was just for preview.
- [ ] Quotes match the EDT report wording (spot-check three).

## Deployment notes for the dev (Gate 7)
- Add the new snippet `isqm-gold-announcement` in Code Snippets, "Run snippet everywhere" off, front end only. Activate it on the chosen date (or leave it inactive and activate to go live).
- Set `HS_ISQM_POST_URL` to the live news post, and confirm the date window, before activating.
- No permalink flush needed.
- After activating: purge WP Super Cache and Cloudflare APO; smoke check the home page (pop-up once), a weekly update (same pop-up, not shown again after it was closed) and a phone.
- To end the campaign: deactivate the snippet, or let the end date pass; to re-show the pop-up, bump `HS_ISQM_VERSION`.
