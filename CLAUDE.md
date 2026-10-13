# HeadStart Phuket website — how work is done here

Read this first, every session. The rules below are enforced by git hooks (`tools/git-hooks/`) and, in Claude Code, by `.claude/settings.json`. If a hook blocks you, fix the cause — never bypass it.

## What this repo is
- `headstartphuket.com` — WordPress + Divi, edited on live through **Code Snippets** (PHP), **Custom CSS & JS** (JS) and `headstart-global.css`.
- Local copy: Local by Flywheel at `http://headstart.local` (WordPress root `app/public`). The mu-plugin `headstart-local-loader.php` loads every file in `Site-Scripts/php/*.php` and `Site-Scripts/js/*.js` straight into the local site — edit a file, reload, see it.
- The repo tracks **only bespoke code + workflow**: `Site-Scripts/`, our mu-plugins, `tools/`, `work/`, `docs/`, this file. WordPress core, plugins, Divi, uploads, caches and the DB are ignored on purpose.

| Path | What it is |
|---|---|
| `Site-Scripts/php/`, `js/`, `css/` | Working copies of live snippets. The only place code is edited. |
| `Site-Scripts/live-site-backups-YYYY-MM-DD/` | Read-only snapshots of live exports (Gate 1). Never edited. |
| `Site-Scripts/Latest-Drop/` | Where the user drops new live exports. Not tracked. |
| `Site-Scripts/handover/<slug>-<date>/` | Packages sent to the live developer. |
| `work/<slug>/` | Per-task plan, approval, UAT and handover records. |
| `DEPLOY-LOG.md` | What went live, when. Written by `tools/hs deployed` only. |
| `tools/hs` | The workflow CLI. Every key action goes through it. |
| `_archive/` | Retired material (old Antigravity setup, raw drops). Not tracked; don't touch. |

## Where you are running
- **Claude Code on the Mac**: repo is the project dir; run `tools/hs …` directly. PHP lint uses Local's PHP automatically.
- **Claude (cloud) linked to the Mac**: use the device shell; repo is at `$HOME/mnt/HeadStart` → `cd "$HOME/mnt/HeadStart" && tools/hs status`. That shell has **no PHP and no network**, so for lint: stage the changed PHP files into the cloud container, run `php -l` there, and only if they pass record it in the device shell with `HS_EXTERNAL_LINT="php <version> (cloud container)" tools/hs receipt <files>`. To check pages on `http://headstart.local`, use the built-in browser on the Mac (or ask the user); the device shell can't reach it.
- Start every session with `tools/hs status`.

## The gates

**Gate 1 — Live baseline.** Before planning a task that touches live code, make sure the newest `live-site-backups-*` is current (`tools/hs start` warns when it is over 7 days old). If not, ask the user to export from live — Code Snippets export, Custom CSS & JS, `headstart-global.css` — into `Site-Scripts/Latest-Drop/`, then on `main` run `tools/hs snapshot`. It unpacks to a dated read-only backup, commits it, and prints drift (DIFFERS / LIVE ONLY / LOCAL ONLY) against the working copies. Resolve drift on the task branch by copying from the backup into `Site-Scripts/php|js|css`.

**Gate 2 — Plan & approval.** `tools/hs start <feat|fix> <slug> "Title"` creates the branch and `work/<slug>/plan.md`. Fill every `TODO:` (problem, file-by-file changes, risks & rollback, automated tests, UAT checklist, deploy notes), commit it with `tools/hs save`, then show the user a short summary and ask for approval (in the cloud, use AskUserQuestion with an "APPROVED" option; in Claude Code the user types `APPROVED`, which the hook records automatically).
- Only after the user explicitly approves, record it: `tools/hs approve --via "<quote the user's words>"`. **Never run `hs approve` or `hs uat` on your own judgement** — they record the user's decision.
- Until approved, code commits are blocked. If `plan.md` changes after approval, code commits are blocked again until the user re-approves.

**Gate 3 — Build.** Edit only working copies on the task branch. Commit after each meaningful step with `tools/hs save -m "<type>(<scope>): <message>"` (types: feat fix chore docs refactor test style perf plan handover snapshot deploy). Small, frequent commits. Never `git add`/`git commit` by hand on `main`.

**Gate 4 — Automated checks.** `tools/hs lint` (PHP + JS on everything changed in the task); the pre-commit hook also lints every staged file. Then run the plan's endpoint checks against `http://headstart.local` and report status codes.

**Gate 5 — UAT.** Give the user the UAT checklist from the plan. When they confirm it passed (or agree none is needed, and why), record it: `tools/hs uat --via "<their words>"`.

**Gate 6 — Handover.** `tools/hs handover` builds `Site-Scripts/handover/<slug>-<date>/` with `files/` (exactly what changed), `rollback/` (the matching live versions from the newest backup) and a `DEV-NOTES.md` scaffold. Complete every `TODO:` in DEV-NOTES (summary, exact snippets to replace/activate, smoke checks), run `tools/hs handover` again to zip and commit, then `tools/hs finish` to merge into `main` and tag it. Tell the user the zip path; they send it to the developer.

**Gate 7 — Deployed.** When the user confirms the dev has put it live: `tools/hs deployed <slug> --note "..."` logs it in `DEPLOY-LOG.md` and tags it. Remind them of the post-deploy steps: permalinks flush if routing changed, purge WP Super Cache, purge Cloudflare APO, smoke checks.

## Never
- Edit anything in `live-site-backups-*` or `_archive/`.
- Use `--no-verify`, change `core.hooksPath`, set `HS_ALLOW_MAIN`, or otherwise get around a hook.
- Commit or merge on `main` except through `tools/hs` (snapshot, finish, deployed).
- Force-push, `git reset --hard`, `git clean -f`, or `rm -rf` — move unwanted things to `_archive/` and tell the user.
- Approve your own plan or UAT, or write `work/*/APPROVAL`, `work/*/UAT` or `DEPLOY-LOG.md` by hand.
- Commit secrets, DB dumps, or anything from `app/` outside our mu-plugins.

## Live-site facts worth remembering
- Caching: WP Super Cache on the server, Cloudflare APO at the edge. Any change to URLs, redirects or page output needs both purged after deploy, or visitors keep seeing the old result.
- Routing / rewrite-rule changes need Settings → Permalinks → Save on live.
- Social previews come from Yoast SEO Premium Open Graph tags; WhatsApp and Facebook crawlers get through Cloudflare.
