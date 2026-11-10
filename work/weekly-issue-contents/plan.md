# Plan: Weekly updates: blurb in the admin list; In this issue from the article headings

- Task: `fix/weekly-issue-contents` · opened 2026-11-10
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-09`
- Roadmap: `weekly-issue-contents` (added with this plan)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## In plain English
The Blurb column in the Weekly Updates list will show the blurb you wrote on the edit screen, and its Edit button will save to the same place. "In this issue" stops being tags you pick. Instead, it becomes a list of the issue's article headings, found automatically every time you save, so no article is ever missed. Each heading has a box where you can type a shorter name. Promos are left out. On the Campus Hub page, the chips under each issue show that list. Older issues that have tags and no Divi 5 articles keep their tags, so nothing old changes.

## Problem / goal
- **Blurb column always says "No blurb".** The edit screen saves the blurb under `blurb`; 49 issues have one. The admin list column reads `hs_weekly_update_blurb`, which no issue has. Its inline Edit saves to that unused name too, through the shared `hs_inline_meta_update` handler. That handler also strips HTML from the blurb.
- **In this issue relies on picking tags by hand.** It's easy to miss articles, and the two newest drafts have none. Article headings are already in the issue, and the Articles → Posts tool already finds them.
- Goal: the admin column shows and edits the real blurb. In this issue is built from the headings, with editable short names, and the hub shows it.

## Proposed changes
- **`Site-Scripts/php/weekly-update-register-fields.code-snippets.php`** (Weekly-Update--Register-Fields; runs everywhere, so it also catches block-editor saves, which don't count as admin)
  - `hs_issue_headings( $content )`: finds every Divi group with the class `hs-weekly-article` and takes its first h3. This is the same rule the Articles → Posts tool uses, so promos (not marked as articles) are left out. Returns `[ key, heading ]` in page order, where the key is the heading's slug (repeats get `--2`).
  - **New meta `hs_issue_contents`**: a JSON list of `{ key, heading, label }`, where `label` is your short name and empty means use the heading. It's rebuilt from the content on every save of a weekly update (`save_post_weekly_update`). Short names are kept by key. New articles are added in page order, and articles that are gone are dropped.
  - `hs_issue_contents_names( $id )`: the names to show (label, otherwise heading). Empty for issues with no Divi 5 articles.
  - **"In this issue" box on the weekly update edit screen**, replacing the In This Issue (Tags) field on issues that have articles:
    - one row per article, showing the heading and a text box for the short name;
    - the short names save with the update;
    - after each save the list refreshes itself, so new articles appear without a reload.
  - Issues without articles (old and legacy) still show the tags field as now.
- **`Site-Scripts/php/weekly-update-shortcode-engine.code-snippets.php`** (Shortcode-Engine): the hub card's `tags` come from `hs_issue_contents_names()` when not empty, otherwise the In This Issue terms as now. The "+N more" chip behaviour is unchanged.
- **`Site-Scripts/php/weekly-update-admin-columns.code-snippets.php`** (Admin-Columns):
  - **Blurb column:** reads `blurb`.
  - **Its Edit:** saves through a new action, `hs_wu_save_blurb`. That action checks you can edit that issue, keeps safe HTML (`wp_kses_post`), and saves the ACF `blurb` field. The shared `hs_inline_meta_update` handler isn't touched.
  - **In This Issue column:** shows the article names when the issue has them, with Edit linking to the edit screen's box. Older issues keep the tag chips and tag editor.
- **One-off fill:** `hs_issue_contents` is filled for existing Divi 5 issues, 50 per admin page load, then a flag stops it. It writes only that field, so tags and dates are untouched.
- **No change:** search keeps indexing article headlines from the content (already does) plus any tags.

## Risks & rollback
- **Names come from headings.** An article without an h3, or not marked `hs-weekly-article`, won't be listed. The Articles → Posts tool has the same rule and reports skipped articles; the box will say how many were skipped too.
- **Hub chips change for Divi 5 issues.** Their chips switch from picked tags to every article name, which may be more chips. The "+N" chip already folds the extras.
- **Blurb HTML.** The column's editor shows raw HTML, as it does now. Saving keeps safe tags.
- **Caching.** The hub list is cached, so purge WP Super Cache and Cloudflare APO after deploy.
- **Rollback.** Put back the three snippets from `rollback/`. The new meta can stay; nothing reads it then. Tags were never removed.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the three PHP files.
- **New WordPress tests** (`work/weekly-issue-contents/tests/wp-tests.php`):
  - headings are found in order, promos and h3-less groups are skipped, and repeats get unique keys;
  - saving keeps short names by key, adds new articles and drops removed ones;
  - names fall back to the heading;
  - the hub card's `tags` use the names, and old issues keep their tags;
  - the blurb column prints the `blurb` field;
  - `hs_wu_save_blurb` refuses a user who can't edit that issue and keeps `<strong>`;
  - the fill writes only `hs_issue_contents`.
- **Endpoints on http://headstart.local** (expect 200 and no PHP notices):
  - `/wp-admin/edit.php?post_type=weekly_update`
  - a weekly update's edit screen
  - the Campus Hub page
  - `admin-ajax.php?action=hs_load_weekly_updates`
- **A headless browser check:**
  - the box lists the articles and a short name saves;
  - adding an article and saving shows it in the box without a reload;
  - the admin list shows week 9's blurb.

### UAT checklist for the user (Gate 5)
- [ ] Weekly Updates list: Weekly Update #9 shows its blurb. Edit, change a word, Save: the column and the edit screen both show the change.
- [ ] Open Weekly Update #10 (either campus): the "In this issue" box lists every article heading in order, with no promos.
- [ ] Type a shorter name for one long heading and Update. Reload: it's kept.
- [ ] Add an article group with an h3 in Divi, save: it appears in the box.
- [ ] The Weekly Updates list's In This Issue column shows the names for #10, and the old tag chips for an old issue.
- [ ] Campus Hub page: the #10 card's chips show the article names, including your short name. An older issue still shows its tags.

## Deployment notes for the dev (Gate 7)
- **Replace** Weekly-Update--Register-Fields, Weekly-Update--Admin-Columns and Weekly-Update--Shortcode-Engine.
- Load wp-admin a few times until the fill finishes (option `hs_issue_contents_fill_v1` exists).
- Purge WP Super Cache and Cloudflare APO.
- No permalinks flush. Independent of packs 1–5, but ship after them to keep the order simple.
