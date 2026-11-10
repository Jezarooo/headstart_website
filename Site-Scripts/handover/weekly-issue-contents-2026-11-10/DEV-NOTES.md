# Weekly updates: blurb in the admin list; In this issue from the article headings — dev notes

Package `weekly-issue-contents-2026-11-10.zip` · 2026-11-10 · branch `fix/weekly-issue-contents`

**Not to be sent on its own.** Jeremy will send one combined pack covering packs 1–5 and this change. This package is the record of this change. It doesn't depend on packs 1–5: none of its three snippets is touched by them.

## Problem
- **Blurb column always empty.** In the Weekly Updates list (Weekly-Update--Admin-Columns), the Blurb column read `get_field('hs_weekly_update_blurb')`. The edit screen's Blurb field saves as `blurb` (`field_weekly_update_blurb`); 49 issues have one, and none has `hs_weekly_update_blurb`. The column's inline Edit saved to that unused name too, through the shared `hs_inline_meta_update` handler. That handler also strips HTML with `sanitize_text_field`.
- **In this issue was hand-picked tags.** The `in_this_issue` taxonomy relies on editors ticking tags, so articles get missed, and the newest drafts had none.

## Change
**Weekly-Update--Register-Fields (runs everywhere):**
- **Finding the articles:** `hs_issue_headings( $content )` finds every Divi 5 group with the class `hs-weekly-article` and takes its first h3. This is the same rule as Weekly-Update--Articles-To-Posts, so promos are left out.
- **Storing the list:** new meta `hs_issue_contents`, a JSON list of `{ key, heading, label, show }`. It's rebuilt on `save_post_weekly_update`, which covers REST, Divi and the meta-box form post. Short names (`label`) and ticks (`show`) are kept per heading key; new articles are added ticked, and removed ones are dropped. The form post is checked with a nonce and `edit_post`.
- **The names:** `hs_issue_contents_names( $id )` returns the ticked articles' short names, or their headings.
- **Edit screen, "Weekly Update Details":**
    - laid out as a grid: Blurb | Campus over Week number | In this issue, all the same height;
    - the Blurb editor has a single toolbar row, and its hint is starter text inside the editor;
    - In this issue is a new ACF message field, `field_weekly_update_contents`, drawn by `hs_issue_contents_render_field()`. Each article has a tick box, its heading and a short-name box, with a counter that turns red past 30 characters (`HS_ISSUE_NAME_MAX`). An ⓘ tooltip explains the list. After a save the list refreshes through the `hs_issue_contents_get` AJAX action, which checks a nonce and `edit_post`.
- **What shows where:**
    - The In This Issue (Tags) field shows only on issues without articles (older ones).
    - Archive Post (`field_6a3772e6f9322`) shows only on issues already marked as imported. The meta itself is unchanged.
- **Labels:** Blurb, Week number and In this issue are renamed. Field keys, names and saving are unchanged.
- **One-off fill:** `hs_issue_contents_fill_v1()` on `admin_init` builds `hs_issue_contents` for existing issues, 50 per admin page load. It sets the option `hs_issue_contents_fill_v1` when done, then clears the Campus Hub cache. It writes only that meta: no re-save, no change to tags or dates.

**Weekly-Update--Shortcode-Engine:** the Campus Hub card's `tags` come from `hs_issue_contents_names()` when not empty, otherwise from the `in_this_issue` terms, as before.

**Weekly-Update--Admin-Columns:**
- **Blurb column:** reads `blurb` and shows the whole blurb, no longer cut to 3 lines.
- **Its Save:** uses a new action, `hs_wu_save_blurb`. That checks a nonce, the post type and `edit_post` on that issue, keeps safe HTML (`wp_kses_post`), saves `field_weekly_update_blurb`, and clears the hub cache.
- **In This Issue column:** shows the article names for issues that have them, with Edit linking to the edit screen. Older issues keep the tag chips and the tag editor.

**Deliberately unchanged:**
- the shared `hs_inline_meta_update` handler (Posts--Admin-Columns);
- the `in_this_issue` taxonomy and every existing tag;
- the search index, which already indexes article headlines;
- URLs.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/weekly-update-register-fields.code-snippets.php | modified | Code Snippet **Weekly-Update--Register-Fields** | rollback/php/weekly-update-register-fields.code-snippets.php |
| files/php/weekly-update-shortcode-engine.code-snippets.php | modified | Code Snippet **Weekly-Update--Shortcode-Engine** | rollback/php/weekly-update-shortcode-engine.code-snippets.php |
| files/php/weekly-update-admin-columns.code-snippets.php | modified | Code Snippet **Weekly-Update--Admin-Columns** | rollback/php/weekly-update-admin-columns.code-snippets.php |

## Deploy steps
1. **Replace** the code of the three snippets above with the files in `files/php/`, keeping their run settings (everywhere).
2. **Load any wp-admin page a few times** until the option `hs_issue_contents_fill_v1` exists, e.g. `wp option get hs_issue_contents_fill_v1`.
3. **Purge** WP Super Cache and Cloudflare APO: the Campus Hub list output changes.

## Watch out for
- **Campus Hub chips change.** Issues built with Divi articles will show every ticked article name instead of their picked tags, and the full headings until someone adds short names. The card's "+N" chip folds the extras.
- No permalinks flush.
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.

## Quick check after deploy (optional)
- **Weekly Updates list:** a recent issue's Blurb column shows its full blurb.
- **Edit screen:** open that issue. Weekly Update Details shows Blurb, Campus over Week number, and an In this issue list with tick boxes.
- **Campus Hub page:** that issue's chips are its article names.

## Previous version
`rollback/` holds the three live snippets as they were. Restoring them brings back the tags behaviour. `hs_issue_contents` can stay: nothing reads it then.
