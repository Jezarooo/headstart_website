# Plan: Trim Watch out for in gallery-popup-portraits dev notes

- Task: `fix/gallery-notes-trim` · opened 2026-10-13
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-13`
- Follows: `fix/gallery-popup-portraits` (already merged; package not yet sent)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
The user wants the "Watch out for" section of the gallery-popup-portraits DEV-NOTES short and simple: the dev is very experienced. The package is already merged, so the change goes through its own task.

## Proposed changes
- `Site-Scripts/handover/gallery-popup-portraits-2026-10-13/DEV-NOTES.md`: rewrite "Watch out for" as three short bullets. Nothing else in the notes changes.
- Re-zip `gallery-popup-portraits-2026-10-13.zip` with the same contents plus the edited notes.
- No code changes.

## Risks & rollback
None for live; documentation only. The previous notes stay in git history (tag `handover/gallery-popup-portraits-2026-10-13`).

## Testing strategy
### Automated (Gate 4)
None — no code changed. Check the zip lists the same 9 entries and contains the new section.

### UAT checklist for the user (Gate 5)
- [ ] The user reads the new "Watch out for" text and approves it.

## Deployment notes for the dev (Gate 7)
None — nothing ships beyond the gallery-popup-portraits package. Closed with `handover --local-only`.
