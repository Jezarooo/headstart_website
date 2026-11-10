# Plan: Weekly Updates list: compact In This Issue chips in a wider column

- Task: `fix/weekly-list-issue-chips` · opened 2026-11-10
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-09`
- Roadmap: `weekly-list-issue-chips` (added with this plan)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## In plain English
In the Weekly Updates list in wp-admin, the In This Issue chips now hold full article names, so each row becomes very tall. This change makes the chips smaller (smaller text, less padding) and gives the In This Issue column more of the table's width, taken from the narrow Campus and Week Number columns, so the chips wrap onto fewer lines. Admin only; nothing on the public site changes.

## Problem / goal
Since In this issue lists the article names (task weekly-issue-contents), a row can carry 11–17 long chips at 11px with 2px × 6px padding in a quarter-width column. Each row ends up several times taller than its blurb. Goal: rows about as tall as the blurb, with the names still readable.

## Proposed changes
- **`Site-Scripts/php/weekly-update-admin-columns.code-snippets.php`** (Weekly-Update--Admin-Columns)
  - **Chip styles:** the inline chip styles become one class, `hs-wu-chip` (`is-name` for article names, `is-tag` for older issues' tags). The chips are 10.5px text with 1px × 5px padding and 15px line height, and sit closer together.
  - **Column widths:** the list page's `<style>` (already printed only on the Weekly Updates list) sets them:
    - In This Issue 32%
    - Blurb 28%
    - Campus 120px
    - Week Number 80px
    - Title and Date keep the rest
  - **Edit button:** In This Issue's Edit link sits right after the chips, not on its own line.
  - **No change** to what's listed, the tag editor, saving, or any other column.

## Risks & rollback
- **Admin only:** no cache purge or permalinks flush.
- **Narrow screens:** WordPress already stacks list columns below 782px; the widths apply only above that.
- **Rollback:** put back the weekly-issue-contents version of the snippet.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint`.
- **Headless check on `http://headstart.local/wp-admin/edit.php?post_type=weekly_update`** at 1440px:
  - the In This Issue column is the widest of the four custom columns;
  - chips are ≤ 11px text;
  - for Weekly Update #10 (17 articles), the In This Issue cell is no taller than 1.5× its Blurb cell, and the row is at least 40% shorter than before;
  - an old issue still shows its tags and tag editor.
- Before and after screenshots.
- The weekly-issue-contents WordPress tests still pass.

### UAT checklist for the user (Gate 5)
- [ ] Weekly Updates list: the In This Issue chips are smaller and the column is wider, and rows are about as tall as their blurbs.
- [ ] The names are still easy to read.
- [ ] Edit next to the chips still opens the update.
- [ ] An older issue's tags and tag editor still work.

## Deployment notes for the dev (Gate 7)
- Replace **Weekly-Update--Admin-Columns**. It goes in the combined pack with packs 1–5 and weekly-issue-contents, after weekly-issue-contents.
- Admin only: no permalinks flush, no cache purge.
