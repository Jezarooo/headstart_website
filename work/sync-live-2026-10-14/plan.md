# Plan: Align working copies with live (14 Oct snapshot)

- Task: `fix/sync-live-2026-10-14` · opened 2026-10-14
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-14`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
The 14 Oct snapshot shows Nick changed live since our last copy. Bring those changes into the working copies so local matches live before the short-links work. Nothing ships to live.

## Proposed changes
- `Site-Scripts/php/staff-directory-csv-importer.code-snippets.php` ← live version (Nick, 13 Oct): "Download Full Directory (XLS)" export, column 'Lead Sort' replaces 'Reports To', automatic backup + Emergency Revert, wipe disabled until backups keep photos, image-sync button removed. Marked "OWNED BY Nick".
- `Site-Scripts/php/gallery-image-real-size.code-snippets.php` ← live version (Nick added a one-line description comment; code identical).
- `Site-Scripts/js/MANIFEST.tsv`: gallery-mosaic live hash a175107c… → 0ecd9c6cd7c2af8261644a9e84e666b3 (live file is byte-identical to our gallery-mosaic.js).

Deliberately left as they are (expected drift, already on the roadmap):
- `posts-compute-taxonomies-from-custom-fields`: our copy has the stray `?>` removed; live still has it (roadmap: posts-compute-close-tag).
- `posts-divi-gatekeeper`, `weekly-update-divi-gatekeeper`: local-only block-editor fix (user decision 13 Oct).
- `auto-assign-cross-campus-taxonomy`: local only, pending decision (roadmap: cross-campus-decision).

## Risks & rollback
Local only. The CSV importer on live is "disabled" per its header, but its admin page now works for exports; locally it behaves the same as live. Rollback: git revert of this task.

## Testing strategy
### Automated (Gate 4)
`tools/hs lint` on the two PHP files; `tools/hs drift` afterwards shows only the expected drift listed above.

### UAT checklist for the user (Gate 5)
- [ ] Local admin → Staff → Import/Export: "Download Full Directory (XLS)" downloads a spreadsheet of staff.
- [ ] A strip gallery pop-up on local still shows portraits.

## Deployment notes for the dev (Gate 7)
None — nothing ships; closed with `handover --local-only`.
