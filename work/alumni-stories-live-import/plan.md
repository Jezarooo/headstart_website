# Plan: Alumni stories: live Divi import file (live media IDs)

- Task: `feat/alumni-stories-live-import` · opened 2026-10-15
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-14`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
The alumni-stories code (JS + CSS) is built, tested on local and handed over (`handover/alumni-stories-2026-10-14`); the dev has deployed it. The page content is still missing on live: the Divi section with the four stories. The Divi import file from the handover has placeholders for Media Library IDs, because IDs differ per site. The user has now uploaded the 35 slides to the live Media Library and exported the ID list (`/wp-json/wp/v2/media?search=Alumni-Stories&per_page=100&_fields=id,source_url`, 15 Oct). This task turns that list into a ready-to-import file for live. Content only; no code change.

## Proposed changes
All new files in `work/alumni-stories-live-import/` (no change to `Site-Scripts/`, the snippets, the JS or the CSS):
1. `media-live.json`: the live media export (35 items: Nick 10, Carol 5, Pea 8, Reece 12), as supplied by the user.
2. `make-live-import.py`: fills the four `__IDS_<NAME>__` placeholders in `../alumni-stories/divi-alumni-stories.template.json`. IDs are matched by file name and slide number, because live IDs run backwards (e.g. NICK_001 = 25484). It stops if any story is missing a slide, and checks the output is valid JSON with no placeholders left.
3. `alumni-stories-live-import.json`: the output, for Divi → Divi Library → Import & Export. It holds two Library items: "Alumni stories (section)" (all four stories and the invitation tile) and "Alumni story (template)" (one empty story Group for adding more).

## Risks & rollback
- Wrong order: if IDs were matched by ID order the slides would play backwards. Mitigated by matching on slide number; the script prints each story's ID list for checking.
- Import only adds two Divi Library items. Nothing on the live page changes until the user places the section on the page. Rollback: delete the two Library items (and the section from the page, if placed).
- Card photos fall back to each story's first slide, so no separate card images are needed.
- After the page is edited: purge WP Super Cache and Cloudflare APO, or visitors will keep the old page.

## Testing strategy
### Automated (Gate 4)
- Script run: 35 IDs read, 4 stories mapped (10/5/8/12 slides), no placeholder left, output parses as JSON; output differs from the template only where the placeholders were.
- No PHP or JS changed, so nothing to lint. No local endpoint checks apply: the live IDs don't exist in the local Media Library.

### UAT checklist for the user (Gate 5)
- [ ] Live Divi Library shows "Alumni stories (section)" and "Alumni story (template)" after import.
- [ ] Place the section on the alumni page (draft or preview first): all four stories show with their slides, in the right order.
- [ ] Slider, blurb, card grid, invitation tile and Instagram button behave as on local.
- [ ] Rest of the alumni page unchanged.

## Deployment notes for the dev (Gate 7)
No dev action: no snippet, script or CSS changes. The user imports the file into live Divi and places the section. Afterwards purge WP Super Cache and Cloudflare APO. Handover will use `tools/hs handover --local-only "content import file, no code"`.
