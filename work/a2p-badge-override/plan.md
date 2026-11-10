# Plan: Articles to Posts: yellow badge defaults to the Website Section

- Task: `fix/a2p-badge-override` · opened 2026-11-09
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-09`
- Roadmap: item `a2p-badge-override`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## In plain English
In the Articles → Posts panel, the yellow badge will show the Website Section unless you type something else, the same as hand-made Posts. The badge is no longer required. The list of theme badges (Spotlight, Academics and so on) is removed from the whole site, so the Post screen's override dropdown starts empty and fills only with badges people write themselves. This package replaces the 9 Nov zip, which shouldn't go to Nick. The main risks are a typo in a typed badge, which would show on the Post and stay in the list until someone fixes it, and Nick needing to do the removal steps in the right order.

## Problem / goal
The panel makes editors choose a Yellow Badge from a list, and that badge replaces the Section name. That is wrong: on the site, the yellow badge is the Website Section. Every one of the 730 published Posts shows its Section there, and none has a badge. The Post screen says the same: Website Section "sets the default text for the yellow badge", and the badge field is an optional override.

The list comes from `hs_seed_default_thematic_chips()` in the live snippet `Posts--Register-Taxonomies`, which creates 14 terms in `hs_thematic_chip`. They feed the Post screen's override dropdown. The previous plan wrongly treated that list as the core badges.

Decisions from the user (9 Nov): the badge defaults to the Section, with the option to write your own, and the list is removed everywhere, including the Post screen.

## Proposed changes
### `Site-Scripts/php/weekly-articles-to-posts.code-snippets.php`
1. **Field:** the Yellow Badge dropdown and its Add box are replaced by **Yellow Badge Override (Optional)**, one text box. The hint says: "Leave empty to show the Website Section. Type your own to replace it."
2. **Required fields:** the badge is removed from `hs_a2p_missing_fields()` and the panel's own check. It never shows red or green. Tags stay required.
3. **Saved rows:** the row's `badge` term ID becomes `badgeText`, a string. No Post has been made with the tool on live, so nothing needs migrating.
4. **Creating a Post:**
   - empty: no `hs_thematic_chip` term is set, and the existing chip logic shows the Section;
   - typed: `term_exists()` reuses a matching badge, ignoring capitals, so "spotlight" reuses Spotlight. Otherwise a new term is created when the Post is made, not while typing, so abandoned text leaves nothing behind. Creating one needs the taxonomy's `manage_terms` capability; without it the row is skipped and named.
5. **Removed code:** `panel.badges`, `canBadge` and the badge route through `hs_a2p_add_term()`, which now handles tags only and is kept for the tag Add box.

### `Site-Scripts/php/posts-register-taxonomies.code-snippets.php` (existing live snippet "Posts--Register-Taxonomies")
Section 5, `hs_seed_default_thematic_chips()` and its `init` hook, is removed. It runs on every page load and recreates any missing term, so it has to go before the terms can be deleted. The `hs_thematic_chip` taxonomy itself stays: the override field, chip logic, slider, gatekeeper and admin columns all read whatever terms exist, and none relies on these names. Written badges still work.

### Removing the terms (database, not code)
The 14 seeded terms plus `Test` are deleted, only after the code above is in place:
- **Local:** I delete them with WP-CLI once the code is in. The only one in use is Spotlight, on demo Post D in the Trash, which simply loses it.
- **Live:** Nick deletes them under Posts → Thematic Chips (steps in the deployment notes). Any term showing a count above 0 is left alone and reported to us, because a Post on live is using it.

The Post screen's override field stays as it is, including its "e.g. Spotlight" hint, which is still a fair example. Its dropdown starts empty, and typing a new badge still works.

No rewrite rules and no new front-end output. No published Post changes, because none has a badge.

### `Site-Scripts/css/core.css` (global stylesheet): card bottom padding on tool-made Posts
Added 10 Nov. On a Post made by the tool, the article card has its top padding but none at the bottom, so the last module (often the gallery) touches the card's edge. The cause is two rules in core.css. The Post card rule (`.single-post .hs-post-content-sandbox .hs-weekly-article`, ~line 3082) gives `padding: 2rem 2.5rem` (1.5rem on phones). The older weekly-update separator rule (`.hs-post-content-sandbox .et_pb_group.hs-weekly-article`, ~line 2680) sets `padding-bottom: 0 !important` on every Divi article group, and its `!important` wins. Imported Posts aren't affected because their articles are plain-HTML `<article>` tags, which that rule doesn't match.

Fix: one Posts-only rule, placed straight after the Post card rules and their `@media` block:
```css
.single-post .hs-post-content-sandbox .et_pb_group.hs-weekly-article { padding-bottom: 2rem !important; }
@media (max-width: 768px) { .single-post .hs-post-content-sandbox .et_pb_group.hs-weekly-article { padding-bottom: 1.5rem !important; } }
```
Reach (checked on the local DB, 5 Nov data): weekly update pages have `single-weekly_update`, not `single-post`, so they never match. Of 772 Posts with an article, the 770 imported ones are plain HTML and don't match; only the 2 Posts made by the tool are Divi groups. Tested by injecting the rule on the ONESQA Post: card padding became 24px top and 24px bottom.

**Live:** the user adds this CSS on live himself (as a separate snippet added to core.css). It is not one of Nick's steps.

### Tests
`work/weekly-articles-to-posts/tests/wp-tests.php` and `panel-check.mjs` are updated to this behaviour.

## Risks & rollback
- **Typed badge typos** show on the Post and stay in the badge list until fixed under Posts → Thematic Chips. This is the same as the Post screen's add option.
- **Hand-made Posts:** their fields work as before, but the override dropdown has no ready-made options until someone writes a badge.
- **Order on live:** if the terms are deleted before the snippet is updated, the code recreates them on the next page load. The deployment notes put the steps in order, and checking the list afterwards shows whether it worked.
- **A live draft or Post using a badge:** the local data is from 5 Nov. The published Posts had none then, but this may have changed, or a draft may use one. That's why Nick leaves any term with a count above 0 and tells us; the count only covers published Posts, so a draft could lose its badge and fall back to its Section.
- **Rollback:** deactivate the snippet "Weekly-Update--Articles-To-Posts", and restore `Posts--Register-Taxonomies` from `rollback/`. The restored code recreates the 14 terms by itself on the next page load (not `Test`). Posts already created stay as ordinary Posts.
- **CSS reach:** if someone on live has built a Post by hand in Divi 5 with an `hs-weekly-article` group, it gets the same bottom padding, which is the intended look. Rollback for the CSS: remove the added rule.
- **CSS added on live by hand:** until the next snapshot, live and `main` differ for core.css. The next `tools/hs snapshot` should show core.css matching this branch; if the live copy differs in wording, the repo copy is aligned to live then.
- **Two packages:** `tools/hs status` will also list the 9 Nov package as not confirmed live until it is logged. It is superseded by this one, and its deploy record should say so.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the snippet.
- `wp eval-file work/weekly-articles-to-posts/tests/wp-tests.php`, adding:
  - empty badge: no `hs_thematic_chip` term; `hs_final_section_chip` equals the Section; the row isn't missing anything;
  - typed new badge: term created, chip shows it; typed existing name in different capitals: reused, no duplicate;
  - after the seed code is gone: deleted terms don't come back after `do_action( 'init' )`, and `hs_seed_default_thematic_chips` no longer exists;
  - Author typing a new badge: row skipped and named, no term created; Author typing an existing badge: allowed;
  - the panel data no longer holds a badge list; `hs_a2p_add_term()` refuses `hs_thematic_chip`.
- `work/weekly-articles-to-posts/tests/panel-check.mjs`: no badge dropdown; the override box has no colour and doesn't hold back Create Posts; a Post made with it empty is logged-out 200 with the Section shown in its chip.
- `tools/hs drift`: the only new difference is `posts-register-taxonomies`, and the new snippet is still local only.
- Logged out: a published Post, the homepage and a weekly update → 200. A logged-in Post edit screen loads with no errors.
- CSS, measured in the browser: on the ONESQA Post the card's bottom padding equals its top padding (2rem desktop, 1.5rem at ≤768px); on Weekly Update 9 the article groups still have `padding-bottom: 0`; on one imported Post the card padding is unchanged.

### UAT checklist for the user (Gate 5)
- [ ] Tick an article: there is no badge list, only the optional override box, and it has no colour.
- [ ] Fill in the rest, leaving the override empty: **Create Posts** is available. The Post and slider show the Website Section.
- [ ] Change the Section on another row: the Post shows that Section.
- [ ] Type an override on another row: the Post and slider show your text.
- [ ] Type that same text in different capitals on another row: it reuses the badge, with no duplicate under Posts → Thematic Chips.
- [ ] Posts → Thematic Chips no longer lists Spotlight, Academics, `Test` and the others; only badges you wrote are there. Reload it: they don't come back.
- [ ] Open a Post: the Yellow Badge Override dropdown has no theme badges, and typing a new one works and shows on the Post.
- [ ] Existing Posts and the sliders still show their Sections.
- [ ] On a Post made by the tool (e.g. the ONESQA one), the card has space below its last item, on desktop and phone width.
- [ ] A weekly update's articles and separator lines look as before, and an imported Post looks as before.

## Deployment notes for the dev (Gate 7)
In this order:
1. **Replace** the code of the Code Snippet **Posts--Register-Taxonomies** with `files/php/posts-register-taxonomies.code-snippets.php`.
2. **Then delete the badges** under Posts → Thematic Chips: Spotlight, School Life, Community, Academics, Student Success, Campus News, Event, Co-Curricular, Creativity, Innovation, Well-being, Leadership, Perspectives, Competition, and `Test` if it's there. Leave any with a count above 0 and tell us. Reload the page: they should not come back.
3. **Add** a new Code Snippet "Weekly-Update--Articles-To-Posts" from `files/php/weekly-articles-to-posts.code-snippets.php`, set to "Only run in administration area", and activate it.

- This replaces the 9 Nov package `weekly-articles-to-posts-2026-11-09.zip`. Don't install that one. If it was already installed, replace the snippet's code with this version.
- The core.css change (card bottom padding) is added on live by Jeremy, not Nick; `files/css/core.css` in the package is for reference only. After it is added: purge WP Super Cache and Cloudflare APO so visitors get the new CSS.
- No permalinks flush. No cache purge needed for the snippets.
- Smoke: open a weekly update and create one **Draft** with the override empty, check its chip shows the Section, then trash it.
