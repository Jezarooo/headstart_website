# Plan: Dev notes: technical problem/change format + rewrite permalinks notes

- Task: `fix/dev-notes-format` · opened 2026-10-13
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-13` (no live code touched)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
The user reviewed the first handover's DEV-NOTES: the problem wasn't described in developer terms, and generic deploy/rollback steps aren't needed for a professional dev (who may hand the notes to Claude). Rewrite those notes and make the format the default for every future handover.

## Proposed changes
1. `Site-Scripts/handover/weekly-update-permalinks-2026-10-13/DEV-NOTES.md`: rewrite as Problem (technical) / Change / Files / Watch out for / Quick check (optional) / Previous version. Then rebuild that folder's zip (files/ and rollback/ unchanged).
2. `tools/templates/DEV-NOTES.md`: same six sections as the default.
3. `tools/hs`: the JS hash-name note goes under "Watch out for" (the old "Always, after the steps above" anchor no longer exists).
No site code changes; nothing ships beyond the rebuilt zip's notes.

## Risks & rollback
Docs/tooling only. Rollback: `git revert` the merge.

## Testing strategy
### Automated (Gate 4)
- `bash -n tools/hs`; scaffold a throwaway handover with a JS change and confirm the template renders and the JS note lands under "Watch out for".
- Rebuilt zip lists DEV-NOTES.md, files/, rollback/ with the code files byte-identical to the merged handover.

### UAT checklist for the user (Gate 5)
- [ ] User reads the rewritten DEV-NOTES.md before it is zipped and confirms it.

## Deployment notes for the dev (Gate 7)
None — local only. The rebuilt permalinks zip is what goes to the dev.
