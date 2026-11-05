# Plan: Sync working copies to live snapshot 2026-11-05

- Task: `fix/sync-live-2026-11-05` · opened 2026-11-05
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-05`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
Local must behave exactly like live. A fresh live export (snippets, core.css, Divi head JS, Duplicator DB, all 5 Nov 2026) shows the working copies have drifted. Goal: bring `Site-Scripts/` in line with the snapshot, then restore the live DB into Local.

## Proposed changes
- `Site-Scripts/css/core.css`: replace with live `core.css` (live is ahead: `hs-keep-spacing`, full-width rule, photo-testimonial cards, alumni fallback styles).
- `Site-Scripts/js/MANIFEST.tsv`: `alumni-stories.js` now maps to live `d965673632f8299a221a98e5c0a09b03.js` (was a placeholder hash). The JS content is already identical to live. All other 14 live JS files are identical.
- `Site-Scripts/php/`: the snippets export is JSON, converted to PHP. 33 of 35 files already match live. Differences found:
  - Comment-only (header notes, trailing `?>`): `gallery-image-real-size`, `staff-directory-csv-importer`, `weekly-update-url-routing`, `posts-compute-taxonomies-from-custom-fields`. Take live.
  - Behaviour: `posts-divi-gatekeeper` and `weekly-update-divi-gatekeeper` locally have the 2026-10-13 editor-panel fix that is not on live. **User decision (5 Nov): match live exactly, local does not matter.** Both files are replaced with live's code; the local fix stays in git history if it is wanted again.
- The drift tool lists every PHP file as "LOCAL ONLY" only because the snippets export is JSON, not PHP files. Nothing is actually local-only.
- No DB/meta/rewrite changes in the repo. The DB restore is a separate local step (Duplicator import of `20261105_...`), done outside git.

## Risks & rollback
Local only; nothing ships to live. Rollback: `git revert` the task commits. The Duplicator import overwrites the local DB; the pre-import local DB is exported first to `~/Desktop/Websites/HeadStart-duplicator/local-db-before-import.sql`.

## Testing strategy
### Automated (Gate 4)
`tools/hs lint`. Then `http://headstart.local/` and a handful of key pages (home, a weekly update, staff directory, alumni, events, noticeboard) return 200.

### UAT checklist for the user (Gate 5)
- [ ] Local home page, menus and footer look the same as live.
- [ ] A weekly update page, the staff directory, events calendar, noticeboard and alumni page look the same as live.
- [ ] wp-admin loads; Snippets list matches live.

## Deployment notes for the dev (Gate 7)
Nothing to deploy. Use `tools/hs handover --local-only "sync of working copies to live snapshot"`.
