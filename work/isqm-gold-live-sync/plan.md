# Plan: Sync ISQM Gold snippet with live version, then wording/layout edits

- Task: `fix/isqm-gold-live-sync` · opened 2026-10-29
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-28`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
The dev reviewed and put a version of the ISQM Gold snippet live (logo loaded from a file, pop-up opens after page load). Our working copy was behind it, so step one is to adopt that live version unchanged (Latest-Drop/isqm-check, dropped 2026-10-29). On top of it, small content and layout fixes so the pop-up, inline block and Quality Bar match the EDT report: the eight areas are listed in the report's own order (Standards 1 to 8), each slide shows the report's full standard title as a small yellow heading above its quote, the Quality Bar shows the same full titles, the report source line gets a thin yellow left border, the button reads "Explore our accreditations" (it links to /accreditations/, not a story), and the Quality Bar has no outer spacing and a #003057 background.

## Proposed changes
All in one file: `Site-Scripts/php/isqm-gold-announcement.code-snippets.php` (identical to the live version apart from the items below).
1. **Quote data (`hs_isqm_quotes()`)**: the eight rows are reordered to the report's Standard 1 to 8 (left column 1 to 4, right column 5 to 8). Labels: "Parents and the community" (was "Partnership with parents and the community") and "Leadership and management" (was "Leadership, management and governance"). Each row gains two keys: `title` (the report's full standard title) and `qb` (its old position). Quote text is untouched.
2. **Pop-up and inline block**: the slide heading (`h3.hs-isqm-area`) now prints `title` (falls back to `area`), restyled small, uppercase, brand yellow, with reserved height so slides do not shift. The report source line (`.src`) gets a 2px yellow left border and 5px padding. Mobile sizes adjusted.
3. **Quality Bar (`hs_qbar_slides()`, CSS)**: quotes are re-sorted by `qb` with one `usort` so the existing quote and stat-card pairing is unchanged. Its quote kicker shows the full title (smaller, tighter, class `qb-std`). `.hs-qbar-wrap` and `.hs-qbar` get margin 0, padding 0, border 0 (spacing is set in Divi). `--qb-slider` changes from #00294d to #003057.
4. **Button text**: "Read the full story" becomes "Explore our accreditations" in the pop-up, the inline shortcode, the settings page label and the shortcode help text.
No new files, hooks, rewrite rules or options. The logo file and the load delay from the dev's version are kept as they are.

## Risks & rollback
- Quality Bar is a fixed 172px (284px stage on phones) with overflow hidden. Standard 1's long title sits above the longest quote: check that slide on desktop and mobile for clipping.
- Anything else that reads `hs_isqm_quotes()` would now see the new order: nothing else in the repo does (checked). The Quality Bar order is kept by the `qb` sort.
- Rollback: put the previous live snippet back (copy included in the handover `rollback/` as the dev's current live version) and purge caches.

## Testing strategy
### Automated (Gate 4)
- `php -l` clean (run in the cloud container, receipt recorded in the repo).
- Local checks: `http://headstart.local/?hs_isqm=1` returns 200 with the pop-up; the Foundation test page with `[hs_quality_bar]` and `[hs_isqm_gold]` returns 200.

### UAT checklist for the user (Gate 5)
- [ ] Pop-up: the controls read 01 to 08 in report order, two columns of four, no column looks unbalanced.
- [ ] Pop-up: each slide shows the full standard title in small yellow capitals above its quote; the quote does not jump as slides change.
- [ ] Pop-up: the EDT source line has a thin yellow left border; the button reads "Explore our accreditations".
- [ ] Pop-up on a phone width: the title and quote fit, nothing is cut off, the button fits on one line.
- [ ] Quality Bar: each quote slide shows the full title; Standard 1's slide is not clipped (desktop and phone).
- [ ] Quality Bar: no extra space above or below it, and its background matches a #003057 section.
- [ ] Inline `[hs_isqm_gold]` block matches the pop-up.
- [ ] Settings page (Settings > ISQM Gold) still opens and saves.

## Deployment notes for the dev (Gate 7)
- Replace the content of the existing Code Snippet `isqm-gold-announcement` with the file in the package (same snippet, same "run everywhere" setting). Nothing else to activate or deactivate.
- No permalink flush. Purge WP Super Cache and Cloudflare APO afterwards.
- The logo file `uploads/hs-head/9a41e560505e7872cb5adbf43a124b29.webp` is already on live (it comes from the dev's version).
- Smoke check after deploy: `/?hs_isqm=1` (logged-in admin) pop-up, a page with `[hs_quality_bar]`, and Settings > ISQM Gold save.
