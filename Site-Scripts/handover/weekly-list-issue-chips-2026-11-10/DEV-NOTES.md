# Weekly Updates list: compact In This Issue chips; tagged issues keep their tags — dev notes

Package `weekly-list-issue-chips-2026-11-10.zip` · 2026-11-10 · branch `fix/weekly-list-issue-chips`

**Not to be sent on its own.** Jeremy will send one combined pack covering packs 1–5, weekly-issue-contents and this change. This package is the record of this change. It builds on weekly-issue-contents: install that first, or simply use these two files, which already include it.

## Problem
- **Very tall rows in the Weekly Updates list.** Since weekly-issue-contents, the In This Issue column (Weekly-Update--Admin-Columns, `hs_tags_col`) lists every article name: 11–17 chips with inline styles (11px, 2px × 6px padding) in a column a quarter of the table wide. Weekly Update #10's row was 923px tall.
- **Existing tags were being replaced.** weekly-issue-contents preferred article names over In This Issue tags whenever an issue had Divi 5 articles. All 18 published Divi 5 issues already have hand-picked tags, which Jeremy wants to keep.

## Change
**Weekly-Update--Admin-Columns (list page only):**
- **Chips:** the inline styles become one class: `hs-wu-chip`, with `is-name` for article names and `is-tag` for tags. They're 10.5px text with 1px × 5px padding and a 15px line height, in a wrapping flex row with a 3px gap.
- **Edit link:** sits inline after the chips (`hs-wu-chips-edit`).
- **Column widths:** every list column gets a width, inside `@media (min-width:783px)`:

  | Column | Width |
  |---|---|
  | Title | 15% |
  | Blurb | 22% |
  | Campus | 140px, and its badge no longer wraps |
  | Week | 70px |
  | In This Issue | 33% |
  | Date | 104px |
  | Clicks | 84px |

  Setting only some widths collapses Title, which is why all of them are set.
- **Result:** #10's row went from 923px to 372px.

**Weekly-Update--Register-Fields: tags first.**
- **New `hs_issue_uses_tags( $id )`:** true when the issue has any `in_this_issue` terms.
- **`hs_issue_contents_names()`:** returns an empty array for tagged issues. The Campus Hub card (Shortcode-Engine) and the list column already fall back to the terms when it's empty, so neither needs another change.
- **Edit screen:**
    - The tags field (`field_weekly_update_tags`) shows only on tagged issues.
    - The In this issue box (`field_weekly_update_contents`) shows only on untagged issues that have articles.
- **Effect:** every published issue keeps its tags, and from week 10 on, the article list is used.

**Deliberately unchanged:**
- the tag editor;
- saving;
- the stored `hs_issue_contents` meta, which stays on tagged issues but is no longer read there;
- the Blurb column, the Clicks column and Shortcode-Engine.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/weekly-update-admin-columns.code-snippets.php | modified | Code Snippet **Weekly-Update--Admin-Columns** | rollback/php/weekly-update-admin-columns.code-snippets.php |
| files/php/weekly-update-register-fields.code-snippets.php | modified | Code Snippet **Weekly-Update--Register-Fields** | rollback/php/weekly-update-register-fields.code-snippets.php |

## Watch out for
- **Install order:** after weekly-issue-contents, replacing the code of the two snippets above and keeping their run settings (everywhere).
- **Cache:** purge WP Super Cache and Cloudflare APO once the combined pack is in, because the Campus Hub chips are in the cached hub output.
- No permalinks flush.
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.

## Quick check after deploy (optional)
- **wp-admin → Weekly Updates:** chips are small, In This Issue is the widest column, and Edit sits after the chips.
- **Weekly Update #9:** shows its tags, in the list and on the Campus Hub.
- **Weekly Update #10, once published:** shows its article names.

## Previous version
`rollback/` holds the live files from before weekly-issue-contents, so restoring them undoes both changes.

## Live drift accepted
Live still has the pre-weekly-issue-contents versions, identical to that pack's `rollback/` apart from the export's `<?php` line and blank lines. weekly-issue-contents isn't live yet; both go in the combined pack, this one after it.

## Live drift accepted
Live still has the pre-weekly-issue-contents versions (identical to that pack's rollback/, apart from the export's <?php line and blank lines). weekly-issue-contents isn't live yet; both go in the combined pack, this after it.
