# Plan: Workflow tooling: fetch-js, clean JS names + manifest, CSS working copy, bulk-delete guard

- Task: `feat/workflow-tooling` · opened 2026-10-13
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-13`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
Finish the workflow so it runs without Antigravity and is readable:
- Live JS is 14 files in `uploads/hs-head/` with hash names; our working copies use the same unreadable names, and the local loader runs them in alphabetical-hash order, not the order live loads them.
- Downloading those files needs an agent today. Claude can't reach the live site; the user's Mac can.
- The live global CSS (`core.css`) has no working copy, and the loader looks for a file that no longer exists.
- Today a commit was nearly made that deleted almost every tracked file (index emptied while two agents were active). The hook caught it via the backup rule, but there is no general guard.
- CLAUDE.md doesn't yet say how work gets to GitHub, or that Antigravity is retired and another developer (Nick) edits live directly.
No live-site change. Nothing in this task is deployed.

## Proposed changes
1. `Site-Scripts/js/` — rename the 14 hash files to clean names (`mega-menu.js`, `staff-directory.js`, …) with `git mv` (history follows). Add `Site-Scripts/js/MANIFEST.tsv`: `clean name ⇥ live hash file ⇥ purpose`, rows in **live load order** (as in the Divi head code).
2. `app/public/wp-content/mu-plugins/headstart-local-loader.php` — load JS in MANIFEST order (then any unlisted `*.js` alphabetically); load every `Site-Scripts/css/*.css` instead of the missing `core-site.css`; derive paths from the repo location instead of hard-coding `/Users/...`; skip files ending `.off.php` / `.off.js` so a snippet can be disabled locally without deleting it. Keep the existing staff-hover CSS override.
3. `Site-Scripts/css/core.css` — working copy of live `core.css` from the 13 Oct backup.
4. `tools/hs`:
   - `fetch-js` (run in Terminal on the Mac): reads the Divi head export in `Latest-Drop/js/`, downloads every `uploads/hs-head/*.js` with its hash name, rejects HTML/Cloudflare responses, prints a count.
   - `handover` + `drift`: translate clean JS names ↔ live hash names via MANIFEST, so rollback copies are found and DEV-NOTES say "replace `uploads/hs-head/<hash>.js` with `files/js/<clean>.js`".
   - `publish`: after `finish`/`deployed`/`snapshot`, writes a git bundle of commits not yet on GitHub so a cloud session can push them (Mac sessions just `git push`).
5. `tools/git-hooks/pre-commit` — block any commit deleting more than 10 tracked files unless `HS_ALLOW_BULK_DELETE=1` (the Claude Code guard blocks agents from setting it). The rename in step 1 is a rename, not a delete, so it is unaffected.
6. `CLAUDE.md` (+ project copy) — Antigravity retired; one agent at a time; Gate 1 uses `tools/hs fetch-js`; Nick (firedev) edits live directly, so snapshot before every task; how to push (cloud: bundle → push; Mac: `git push`).

Out of scope (own tasks): the `?>` bug in posts-compute on live; syncing the local CSV importer with Nick's live version; deciding what to do with the undeployed cross-campus snippet.

## Risks & rollback
- Local only. Risk: a loader bug breaks the local site (white screen) or a script loads in the wrong order. Rollback: `git revert` the merge, or restore the loader file from `main`.
- Renaming JS changes nothing on live: live keeps hash names; MANIFEST maps them.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the loader (PHP) and all 14 JS files.
- Self-test of `hs`: run handover/drift in a throwaway repo copy with a renamed JS file and confirm rollback lookup via hash + DEV-NOTES live path.
- Pre-commit: staged deletion of 11 files is blocked; a rename of 14 files passes.
- `fetch-js` dry run against the existing head export (download step runs on the Mac).

### UAT checklist for the user (Gate 5)
- [ ] `http://headstart.local` home, a weekly update, the staff directory, the events calendar and a page with a gallery all load and behave as before (menus, tabs, sliders, lightbox).
- [ ] Browser console shows no new JS errors.
- [ ] Run `tools/hs fetch-js` in Terminal once; it reports 14 files.

## Deployment notes for the dev (Gate 7)
None — nothing in this task goes to the live site. `hs deployed` is not used for this task.
