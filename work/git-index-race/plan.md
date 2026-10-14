# Plan: Make hs commits race-proof (private index, no auto-maintenance, post-commit check)

- Task: `fix/git-index-race` · opened 2026-10-14
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-14`
- Roadmap: `git-index-race` (P1)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
Three times in two days (13 Oct 12:05 and 15:59; 14 Oct 09:46 and 10:19), git's index was found empty in the middle of an `hs` commit when run from the cloud session's shell on the Mac's shared folder.
- Twice a commit recorded every file as deleted. It was undone before anything reached GitHub.
- Once the pre-commit hook blocked it.

Evidence:
- `.git/objects/maintenance.lock` appeared in the same instant as a bad commit, so git's automatic background maintenance runs alongside the commit.
- The shared folder briefly serves a stale `.git/index` straight after a write: status showed 228 changes, then 0 three seconds later.

Committing with git's index in a private file outside the shared folder, with automatic maintenance off, has worked every time since. Goal: build that into `tools/hs` so no one has to remember it, and catch any bad commit automatically.

## Proposed changes
- `tools/lib.sh`:
  - `hs_git_safe_begin`: sets `GIT_CONFIG_COUNT/KEY/VALUE` so this process runs with `maintenance.auto=false` and `gc.auto=0`; creates a private index under `$TMPDIR` (outside the repo) with `git read-tree HEAD`; exports `GIT_INDEX_FILE`. An exit trap unsets it and runs `git reset -q`, so the real index matches HEAD again. The working tree is never touched.
  - `hs_verify_commit`: after each commit or merge, compares HEAD with its first parent. If the tree is empty, or more than 10 files were deleted without `HS_ALLOW_BULK_DELETE=1`, it undoes the commit with `git reset --soft HEAD~1` (files stay as they are) and stops with a clear message. Snapshot's own additions are unaffected (it only adds files).
- `tools/hs`:
  - Every command that commits or merges calls `hs_git_safe_begin` first and `hs_verify_commit` after each commit. That covers start, approve, save, uat, handover, finish, deployed and snapshot.
  - `hs setup` also writes `maintenance.auto=false` and `gc.auto=0` to the repo config, so the user's own git commands don't start background maintenance on this repo either.
- `CLAUDE.md` + project doc `claude/WORKFLOW.md`: replace the manual "private GIT_INDEX_FILE" instructions with "hs handles it", and keep a short note on what to do if the check fires.
- No changes to Site-Scripts or anything that ships to live.

## Risks & rollback
- `git reset -q` at the end of each hs command unstages anything staged by hand outside hs. hs already stages what it commits, so nothing is lost; the working tree is untouched.
- Turning off `gc.auto` means git won't tidy the repo on its own. The repo is small; the user can run `git gc` on the Mac occasionally (noted in the docs).
- bash 3.2 compatible (macOS), as the rest of `tools/hs`.
- Rollback: revert this task's commits; hs goes back to committing on the shared index.

## Testing strategy
### Automated (Gate 4)
- `bash -n tools/hs tools/lib.sh` (syntax).
- In a throwaway copy of the repo (under /tmp, never the real one):
  1. a normal `hs save` commits and leaves `git status` clean;
  2. an artificially emptied index during commit is caught: the commit is undone, the message is shown, and the files are intact;
  3. a merge through `hs finish` passes the check.
- Then use it for this task's own commits on the real repo and check that the tree count is stable after each.

### UAT checklist for the user (Gate 5)
None — internal tooling. Confirm the description and that commits keep working as you see them in the following tasks.

## Deployment notes for the dev (Gate 7)
None — nothing ships; closed with `handover --local-only`.
