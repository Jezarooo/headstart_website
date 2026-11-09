# Plan: Stop the weekly update editor crashing on the local site

- Task: `fix/local-editor-crash` · opened 2026-11-09
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-09`
- Roadmap: item `local-editor-crash`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## In plain English
Weekly updates open as a blank white page in wp-admin on the local site, but not on live. This adds one small file that exists only on the local site and stops the crash, so weekly updates open normally again. Nothing goes to Nick and live doesn't change. If the file doesn't work, the editor simply stays blank as it is now, and removing the file undoes it.

## Problem / goal
On `headstart.local`, opening a weekly update for editing shows a white page. The browser reports `Cannot read properties of null (reading 'removeEditorPanel')` from the inline script in `Weekly-Update--Divi-Gatekeeper`, because `wp.data.dispatch('core/edit-post')` is still empty when that script calls it. Posts open normally.

Live works and has been reviewed and tested by Nick, so live's code stays as it is. The local site runs WordPress 7.1.3; the October notes recorded live on 7.1.1.

The first fix (`editor-panels-wp7`, 13 Oct) edited the working copies of the snippets. It never went to live, so the 5 Nov sync correctly put them back to match live and the crash returned. This fix has to survive future syncs, so it can't edit those working copies.

## Proposed changes
### New file: `app/public/wp-content/mu-plugins/headstart-local-editor-guard.php`
The name matters: `headstart-*` mu-plugins are tracked by git, and `headstart-local-` ones are never packaged for live (`HS_LOCAL_ONLY_RE`).

On `admin_head` at priority 1, so before the gatekeeper snippet's script, it prints a small script on block editor screens that wraps `wp.data.dispatch`:
- When something asks for `core/edit-post` and that store is still empty, it returns a stand-in whose `removeEditorPanel` calls the same action on `core/editor`. WordPress has kept that action there since 6.5. This means the "In This Issue" panels are still hidden, rather than the error just being silenced.
- Every other call goes through unchanged, including `core/edit-post` once it is ready.
- If `wp.data` isn't there, it does nothing.

No changes to `Site-Scripts/`, the database, rewrite rules or the front end. `tools/hs drift` stays as it is now.

**If `wp.data.dispatch` can't be wrapped** (WordPress may protect it), the fallback is a stand-in store: the same file registers an empty `core/edit-post` holder only if the real one is late. If neither works, I stop and update this plan for re-approval.

### New file: `work/local-editor-crash/tests/editor-check.mjs` (local only)
The browser check used to reproduce this crash: it loads an edit screen in a logged-in headless Chrome and reports errors and whether the editor appeared. Its temporary login session is removed afterwards.

## Risks & rollback
- **Live:** no risk. The file isn't in any package (`tools/hs handover --local-only`).
- **Masking a real difference:** local could look fine while hiding a problem that only appears at this WordPress version. If live is updated to 7.1.3 or later and the crash appears there, the October fix is still in git (`16b73951`) to send to Nick.
- **Stand-in differs from the real store:** it only provides `removeEditorPanel`, the one call that crashes. Another early call to the empty store would still fail; the browser check would show that.
- **Task branches:** the file reaches other branches only after `tools/hs sync`. `feat/weekly-articles-to-posts` needs that before its panel can be tested.
- **Rollback:** move the file out of `mu-plugins/`. The blank page returns, and nothing else changes.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the new file.
- `work/local-editor-crash/tests/editor-check.mjs`, logged in, with the crash reproduced first without the file:
  - weekly update `post.php?post=991965&action=edit`: editor appears; no `removeEditorPanel` or `initializeEditor` errors;
  - "Add New" weekly update: same;
  - Post `post.php?post=992360&action=edit`: still loads, with no new errors;
  - in the weekly editor, `taxonomy-panel-in_this_issue` reports as not enabled.
- `curl` on `http://headstart.local/` → 200 (front end unaffected).
- `tools/hs drift` output unchanged from before the task.

### UAT checklist for the user (Gate 5)
- [ ] Open a weekly update in wp-admin: the editor loads with the WordPress controls, not a white page.
- [ ] The "In This Issue" panel isn't in the sidebar, and the required fields still turn green or red.
- [ ] Add New on weekly updates: the editor loads.
- [ ] Open a Post: it still loads as before.
- [ ] Open a weekly update in the Divi builder: it still loads.

## Deployment notes for the dev (Gate 7)
None. Nothing goes to Nick; this closes with `tools/hs handover --local-only "local-site editor guard; live is unaffected"`.
