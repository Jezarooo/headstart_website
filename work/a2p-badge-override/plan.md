# Plan: Articles to Posts: yellow badge defaults to the Website Section

- Task: `fix/a2p-badge-override` · opened 2026-11-09
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-09`
- Roadmap: item `a2p-badge-override`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## In plain English
In the Articles → Posts panel, the yellow badge will show the Website Section unless you type something else, the same as hand-made Posts. The list of 14 theme badges (Spotlight, Academics and so on) is removed from the panel, and the badge is no longer required. This package replaces the 9 Nov zip, which shouldn't go to Nick. The main risk is a typo in a typed badge, which would show on the Post and stay in the site's badge list until someone fixes it.

## Problem / goal
The panel makes editors choose a Yellow Badge from a list, and that badge replaces the Section name. That is wrong: on the site, the yellow badge is the Website Section. Every one of the 730 published Posts shows its Section there, and none has a badge. The Post screen says the same: Website Section "sets the default text for the yellow badge", and the badge field is an optional override.

The list comes from `hs_seed_default_thematic_chips()` in the live snippet `Posts--Register-Taxonomies`, which creates 14 terms in `hs_thematic_chip`. They feed the Post screen's override dropdown. The previous plan wrongly treated that list as the core badges.

Decisions from the user (9 Nov): the badge defaults to the Section, with the option to write your own, and the current options shouldn't be offered in the panel.

## Proposed changes
### `Site-Scripts/php/weekly-articles-to-posts.code-snippets.php`
1. **Field:** the Yellow Badge dropdown and its Add box are replaced by **Yellow Badge Override (Optional)**, one text box. The hint says: "Leave empty to show the Website Section. Type your own to replace it."
2. **Required fields:** the badge is removed from `hs_a2p_missing_fields()` and the panel's own check. It never shows red or green. Tags stay required.
3. **Saved rows:** the row's `badge` term ID becomes `badgeText`, a string. No Post has been made with the tool on live, so nothing needs migrating.
4. **Creating a Post:**
   - empty: no `hs_thematic_chip` term is set, and the existing chip logic shows the Section;
   - typed: `term_exists()` reuses a matching badge, ignoring capitals, so "spotlight" reuses Spotlight. Otherwise a new term is created when the Post is made, not while typing, so abandoned text leaves nothing behind. Creating one needs the taxonomy's `manage_terms` capability; without it the row is skipped and named.
5. **Removed code:** `panel.badges`, `canBadge` and the badge route through `hs_a2p_add_term()`, which now handles tags only and is kept for the tag Add box.

Nothing else changes: no front-end output, no rewrite rules, no change to any existing snippet.

### Not in this task
The 14-term list and the Post screen's dropdown stay as they are, and they are harmless with no Post using them. Removing them means changing the live snippet `Posts--Register-Taxonomies` and deleting the terms, so it would be its own task if the user wants it.

### Tests
`work/weekly-articles-to-posts/tests/wp-tests.php` and `panel-check.mjs` are updated to this behaviour.

## Risks & rollback
- **Typed badge typos** show on the Post and stay in the badge list until fixed under Posts → Thematic Chips. This is the same as the Post screen's add option.
- **Hand-made Posts** are untouched.
- **Rollback:** deactivate the snippet "Weekly-Update--Articles-To-Posts". Posts already created stay as ordinary Posts.
- **Two packages:** `tools/hs status` will also list the 9 Nov package as not confirmed live until it is logged. It is superseded by this one, and its deploy record should say so.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the snippet.
- `wp eval-file work/weekly-articles-to-posts/tests/wp-tests.php`, adding:
  - empty badge: no `hs_thematic_chip` term; `hs_final_section_chip` equals the Section; the row isn't missing anything;
  - typed new badge: term created, chip shows it; typed existing name in different capitals: reused, no duplicate;
  - Author typing a new badge: row skipped and named, no term created; Author typing an existing badge: allowed;
  - the panel data no longer holds a badge list; `hs_a2p_add_term()` refuses `hs_thematic_chip`.
- `work/weekly-articles-to-posts/tests/panel-check.mjs`: no badge dropdown; the override box has no colour and doesn't hold back Create Posts; a Post made with it empty is logged-out 200 with the Section shown in its chip.
- `tools/hs drift` unchanged, apart from the snippet still being local only.

### UAT checklist for the user (Gate 5)
- [ ] Tick an article: there is no badge list, only the optional override box, and it has no colour.
- [ ] Fill in the rest, leaving the override empty: **Create Posts** is available. The Post and slider show the Website Section.
- [ ] Change the Section on another row: the Post shows that Section.
- [ ] Type an override on another row: the Post and slider show your text.
- [ ] Type "spotlight" on another row: it uses the existing Spotlight badge, with no duplicate under Posts → Thematic Chips.
- [ ] Open a hand-made Post: its Yellow Badge Override field works as before.

## Deployment notes for the dev (Gate 7)
- **Add** a new Code Snippet "Weekly-Update--Articles-To-Posts" from `files/php/weekly-articles-to-posts.code-snippets.php`, set to "Only run in administration area", and activate it. No existing snippet changes.
- This replaces the 9 Nov package `weekly-articles-to-posts-2026-11-09.zip`. Don't install that one. If it was already installed, replace the snippet's code with this version.
- No permalinks flush. No cache purge needed to install it.
- Smoke: open a weekly update and create one **Draft** with the override empty, check its chip shows the Section, then trash it.
