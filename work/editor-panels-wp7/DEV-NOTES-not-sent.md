# Block editor crash: gatekeeper panel removal — dev notes

Package `editor-panels-wp7-2026-10-13.zip` · 2026-10-13 · branch `fix/editor-panels-wp7`

## Problem
- Both gatekeeper snippets print an inline script on `admin_head` that hides some native sidebar panels in the block editor via `wp.data.dispatch('core/edit-post').removeEditorPanel(...)`.
- **Weekly-Update--Divi-Gatekeeper** subscribes to `wp.data` and, as soon as `core/editor` is registered (`isCleanNewPost() !== null` is always true), calls `dispatch('core/edit-post')`. During editor start-up `core/edit-post` isn't registered yet, so `dispatch()` returns `null` and the subscriber throws `Cannot read properties of null (reading 'removeEditorPanel')`. The throw escapes into the editor package's own initialisation, `wp.editor` never finishes, and edit-post then fails with `Cannot unlock an undefined object` / `initializeEditor` undefined → blank editor.
- Reproduced locally on WordPress 7.1.3 + Divi 5.14.0 with the live database of 13 Oct. Live (7.1.1) does not show the crash; we have not confirmed whether 7.1.3 itself or another local difference (e.g. plugin set) is the trigger. The fix is defensive either way.
- **Posts--Divi-Gatekeeper** guards with `select('core/edit-post')` so it doesn't crash, but uses the same store; `removeEditorPanel` has lived on `core/editor` since WP 6.5.

## Change (two snippets; same helper in each)
- New `hsRemoveEditorPanels(panels)`: tries `dispatch('core/editor').removeEditorPanel`, falls back to `core/edit-post` (older WP); null-checks every store; wrapped in try/catch (logs a `console.warn` instead of throwing).
- New `hsWhenEditorReady(panels)`: subscribes once, waits until `select('core/editor').getCurrentPostId()` is set, then runs the removal in a `setTimeout(…, 0)` (outside the editor's dispatch cycle), unsubscribes, and retries once after 1s if no store was ready.
- Weekly: hides `taxonomy-panel-in_this_issue` and `taxonomy-panel-in-this-issue` (unchanged list). Posts: hides category / post_tag / hs_department / hs_post_campus taxonomy panels (unchanged list; the `isEditorPanelEnabled` pre-check was dropped — removing a panel that isn't there is harmless).
- Unchanged: the green/red field monitor, save interceptors, the `isSavingMetaBoxes` check, all PHP.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/weekly-update-divi-gatekeeper.code-snippets.php | modified | Code Snippet **Weekly-Update--Divi-Gatekeeper** (replace its code) | rollback/php/weekly-update-divi-gatekeeper.code-snippets.php |
| files/php/posts-divi-gatekeeper.code-snippets.php | modified | Code Snippet **Posts--Divi-Gatekeeper** (replace its code) | rollback/php/posts-divi-gatekeeper.code-snippets.php |

## Watch out for
- Admin-only JS; no front-end change, no cache purge needed.
- If you deploy it, do so before updating WordPress core to 7.1.3+ on live (WordPress applies minor core updates automatically unless disabled).
- Tested: PHP 8.3 lint; `node --check` on the inline scripts; helper run against a stubbed `wp.data` in 5 start-up scenarios (core/editor has the method / only edit-post / edit-post registered late / neither / no core/editor) — never throws; then checked in the block editor locally (WP 7.1.3, Divi 5.14.0, live DB of 13 Oct): weekly updates and posts open and work.

## Quick check after deploy (optional)
Open any weekly update and any post in the block editor: it loads, the listed taxonomy panels are hidden, and the console has no red errors from the gatekeepers.

## Previous version
`rollback/php/` holds both snippets as they were on live on 13 Oct, if you need to revert.
