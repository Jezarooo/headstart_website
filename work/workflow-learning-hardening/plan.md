# Plan: Workflow: learning snippets, quick plan review and hardening

- Task: `feat/workflow-learning-hardening` · opened 2026-11-09
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-05`
- Roadmap: not linked (the link in `CLAUDE.md` doesn't open, and no roadmap is among the 67 artifacts this account can list)
- Ships nothing to live: tooling and docs only, so it finishes with `tools/hs handover --local-only`.

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
Jeremy wants to learn git, devops, programming and architecture as the work happens, with the work itself explained in plain language, and wants to open the latest plan quickly before approving it (user, 9 Nov). The audit for this task also found that parts of the workflow don't do what `CLAUDE.md` says they do:

| Found | Effect |
|---|---|
| `.claude/settings.json` doesn't exist | No Claude Code hook has been running on this Mac: approval recording, edit and shell guards, the uncommitted-work check. Git hooks still run. |
| Live PHP snippets arrive as one JSON file, which `drift` and `handover` can't read | PHP drift can't be seen: 38 false "LOCAL ONLY" lines in the 5 Nov snapshot. Handovers changing a live snippet get no `rollback/` copy. |
| A new snapshot lands on `main` only | An open task branch never sees it, so the "fresh export" step doesn't reach the task. No command brings it across. |
| `handover` doesn't look at the backup's age, or at whether live has changed | A package can overwrite Nick's newer live edit. |
| Local-only mu-plugins get packaged | `headstart-local-loader.php` is in the site-search zip. DEV-NOTES says "Do not deploy", but only a human is stopping it. |
| 7 of 9 handovers have no deploy record, and nothing reports this | `DEPLOY-LOG.md` can't answer "what's live?". |
| Approval only matches capital `APPROVED`, and it re-records if the plan is already approved | Plain `approved` is missed. |
| `CLAUDE.md` and the hook disagree on who may run `hs approve` | Once hooks are on, the documented cloud route would be blocked. |
| The roadmap link doesn't open | Tasks can't be linked as `CLAUDE.md` requires. |

Checked using a one-off comparison for this plan: all 36 live snippets match our files, and 34 are identical. The other 2 differ cosmetically (one comment, one closing `?>`), so no live edit of Nick's has been missed as of 5 Nov.

## Proposed changes
### 1. Learning snippets
**How often:** snippets are tied to events, with a cooldown. A real event (a commit, new branch, push, merge, editing PHP, a failed lint) can trigger one, but not within 30 minutes of the last one. That gives several a day and never one per prompt, and a quiet stretch gives none. `HS_LEARN_COOLDOWN_MIN` in `tools/lib.sh` is the one number to change.

- **New `tools/learning/snippets.tsv`:** about 40 short snippets across git, devops, programming and architecture, each tied to an event and ordered basic to deeper.
- **New `tools/hs hook learn`** (a `PostToolUse` hook on Bash, Edit and Write):
  - It works out the event, checks the cooldown, and picks the next snippet for that event you haven't seen. Once they've all been seen it moves on to a related event, and only repeats when everything has been shown.
  - It asks Claude to give the snippet in the next reply, using the real work as the example.
- **New `tools/hs learned`:** prints your learning log, every snippet you've had, with its date.
- **State and log** live in `~/.claude/learning/headstart-*`, outside the repo, so they survive branch switches and never dirty a commit.
- **Pause:** the existing `~/.claude/learning/PAUSE` file stops these too. Saying "skip" stops them for the day.
- The daily lesson hook carries on unchanged.

### 2. Explaining the work
A new "Explain as you go" section in `CLAUDE.md`: after meaningful steps, Claude says what it did, why, and the concept behind it (commit, push, branch, merge and so on), in plain language. This is a written rule, not a hook, because it takes judgement.

### 3. Quick plan review
- **New `tools/hs plan [slug]`:** opens the plan in your default app for `.md` files and prints a clickable link. With no slug it opens this branch's plan, or on `main` the most recently changed plan from any open task. Plans on other branches open as a temporary read-only copy.
- **New `tools/hs plan --diff`:** shows only what changed since you last approved.
- **Stop hook:** Claude can't finish a reply while a finished plan is awaiting approval unless that reply contains its link. This applies once per version of the plan, so it doesn't nag.
- **Status output** includes the plan link whenever approval is pending.
- **Plan template** gets a Roadmap line, and "In plain English" as its first section, so the summary comes before the detail.

### 4. Health check
`tools/hs status` warns about, and the session-start hook shows:
- Claude Code hooks missing or out of date;
- the local site unreachable, naming whatever holds port 80 or 443;
- a live backup over 7 days old;
- handovers with no deploy record, by name;
- open tasks with their gate and age, such as `feat/gallery-inline-bento` (idle since 20 Oct).

### 5. Installing hooks properly
`tools/hs setup` copies the hooks into `.claude/settings.json` itself and lists that file in `.git/info/exclude`. It isn't committed, so the hooks stay on when switching to older branches that lack the file, and it can't get swept into a commit. `CLAUDE.md` gets corrected to match.

### 6. Approvals
- Accept `approved` in any capitalisation, but only as the whole message, or capital `APPROVED` at the start as now. "Not approved yet" can't trigger it. `UAT PASSED` is unchanged.
- If the plan is already approved, say so and record nothing.
- The hook keeps blocking Claude from running `hs approve` or `hs uat`, so Claude can't approve its own work. `CLAUDE.md` is updated to match:
  - A message sent while Claude is mid-task may not reach the hook, so Claude asks you to send it again once the turn has ended.
  - Cloud sessions, which don't run these hooks, keep the documented route.

### 7. Reading live PHP snippets
- **New helper in `tools/lib.sh`:** finds a snippet inside the live JSON export by name and extracts its code without changing the backup. It ignores formatting-only differences (our header comment, the opening `<?php`, a closing `?>`, line endings, trailing spaces).
- **`tools/hs drift`** uses it, so false "LOCAL ONLY" lines go and real differences show.
- **`tools/hs handover`** uses it to fill `rollback/` for changed snippets.
- Older backups with separate PHP files keep working.

### 8. Handover safety
For each file a task modifies, `handover` compares the newest backup's version with `main`'s version and stops if they differ, naming the file, because that means live has moved on. It also stops if the backup is over 7 days old and the task modifies existing live files. In both cases the fix is a fresh export, `tools/hs snapshot`, then `tools/hs sync`. There's an override, `--accept-drift "why"`, which is written into DEV-NOTES, for the case where a difference is understood and intended.

Local-only mu-plugins (`headstart-local-*`, `hs-local-*`) are left out of packages automatically.

### 9. Keeping task branches current
**New `tools/hs sync`:** merges `main` into the current task branch, bringing across new snapshots and the latest workflow tools. It requires a clean branch, stops cleanly on a conflict, and doesn't touch `plan.md`, so approval stays valid. `hs_verify_commit` and all git hooks still apply.

### 10. Roadmap and `CLAUDE.md`
`CLAUDE.md` gets the commands above and accurate wording for items 5 and 6. The roadmap rule becomes: if the link can't be opened, say so and carry on, rather than treating it as a required step. It's updated properly once you've decided what the roadmap should be.

### 11. Tests
**New `tools/tests/hs-tests.sh`:** runs against a throwaway copy of the repo in a temp folder, so this repo's history isn't touched. Coverage is listed below.

**Not changing:** any code in `Site-Scripts/`, past backups, existing deploy records, or the cloud push route.

## Risks & rollback
- **New blocks could get in the way mid-handover:** a false "live has changed" would stop a package. Mitigated by ignoring formatting-only differences, naming the file, and the override. Item 8 only affects handovers. Based on the one-off comparison, it should flag only the 2 snippets listed above, and only if a task changes them, unless a fresh export shows more.
- **Hooks going live for the first time:** guards never run on this Mac may wrongly block normal commands, for example a commit message containing `rm -rf`. The tests run each guard, and any issue found is fixed or raised before you install them.
- **The Stop hook could trap a reply:** it stands down on Claude Code's built-in repeat flag (`stop_hook_active`), so it can only ask once per reply.
- **Hook speed:** the local site check runs at session start and in `status` only, with a 2-second limit. The learn hook makes no network calls and returns straight away when in cooldown.
- **Older branches:** they keep their older `tools/hs` until synced. Unknown hook names exit without error there, so they don't break. This branch will sync `feat/share-links-all-pages` when that work resumes.
- **Hooks need a new session:** Claude Code reads hooks at startup, so they apply after installing and restarting.
- **Rollback:** nothing touches live. Delete `.claude/settings.json` to turn the Claude Code hooks off, and revert the merge commit on `main` to remove the rest.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` (no code in the lint paths changes, so this should report nothing to lint), plus `bash -n` on `tools/hs`, `tools/lib.sh` and the git hooks, under macOS bash 3.2.
- `tools/tests/hs-tests.sh`, in a throwaway clone:
  - **Guards:** each block fires on a sample command, and normal commands pass, including `tools/hs save` with awkward commit messages.
  - **Approval:** `approved`, `Approved.` and `APPROVED` record; `not approved yet` and `approved, but change X` don't; already approved records nothing.
  - **Learn:** an event gives a snippet; a second event inside the cooldown gives nothing; after the cooldown the next unseen snippet appears; no repeats; `PAUSE` silences it; the repo stays clean.
  - **Stop:** a plan awaiting approval blocks a reply without the link and passes one with it; it doesn't fire a second time for the same plan version, or while the plan still has unfilled sections.
  - **Plan:** `plan` and `plan --diff` from the task branch, from `main`, and for a plan on another branch.
  - **Snippets:** extraction matches the real 5 Nov backup with 36 of 36 found and the 2 known differences reported; older per-file backups still work; backups aren't modified.
  - **Handover:** a modified snippet gets a `rollback/` copy; a changed live version stops it; a stale backup stops it; `--accept-drift` passes and is written into DEV-NOTES; local-only mu-plugins are left out.
  - **Sync:** brings in a new snapshot, keeps approval valid, and stops cleanly on a conflict.
  - **Setup:** installs hooks, leaves the repo clean, and survives a switch to an old branch.

### UAT checklist for the user (Gate 5)
- [ ] Run `! tools/hs setup`, then restart Claude Code. The session-start message shows the health check with no hook warning.
- [ ] Run `! tools/hs plan`: the plan opens straight away.
- [ ] Run `! tools/hs status`: it lists open tasks with their gates, and the handovers with no deploy record.
- [ ] Work for a while, or ask Claude to make a few commits: snippets appear on events, feel useful, and aren't too frequent. Tell Claude if 30 minutes feels wrong.
- [ ] Run `! tools/hs learned`: the snippets you saw are listed.
- [ ] Type `approved` on its own at an approval prompt: it records automatically.

## Deployment notes for the dev (Gate 7)
None. Nothing ships to live, and Nick has nothing to do. Jeremy runs `tools/hs setup` once on the Mac.
