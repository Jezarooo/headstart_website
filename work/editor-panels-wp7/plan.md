# Plan: Block editor crash: make gatekeeper panel removal safe on WordPress 7

- Task: `fix/editor-panels-wp7` · opened 2026-10-13
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-13` (both gatekeeper snippets identical to working copies)
- Roadmap: local-admin-blank

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
The block editor is blank locally (WP 7.1.3, Divi 5.14.0). Console: `Cannot read properties of null (reading 'removeEditorPanel')` from the inline script of the **Weekly-Update--Divi-Gatekeeper** snippet, then `Cannot unlock an undefined object` in edit-post and `initializeEditor` undefined.
Cause: the snippet (printed on `admin_head`) subscribes to `wp.data` and, as soon as `core/editor` exists, calls `wp.data.dispatch('core/edit-post').removeEditorPanel(...)`. During editor start-up `core/edit-post` isn't registered yet, so `dispatch()` returns null and the subscriber throws. The throw escapes into the editor package's own start-up, `wp.editor` never finishes, and edit-post crashes on top of it. Live (WP 7.1.1) currently survives on load timing — it is exposed to the same crash after any plugin/core/Divi update. **Posts-Divi-Gatekeeper** uses the same `core/edit-post` panel API (guarded by a select check, but still the deprecated store).
Goal: panel removal that can never crash the editor, on old and new WordPress.

## Proposed changes
1. `Site-Scripts/php/weekly-update-divi-gatekeeper.code-snippets.php`: replace the panel-removal block with a safe helper: prefer `core/editor`'s `removeEditorPanel` (WP ≥ 6.5), fall back to `core/edit-post` for older WP; null-check every store; wrap in try/catch; mark done only after a successful call and unsubscribe once done.
2. `Site-Scripts/php/posts-divi-gatekeeper.code-snippets.php`: same helper for its four taxonomy panels (`isEditorPanelEnabled` read from whichever store exists). Its other `core/edit-post` use (`isSavingMetaBoxes`, already null-safe) is unchanged.
3. No other behaviour changes: the same panels are hidden, the green/red field monitor and save interceptors are untouched.

## Risks & rollback
- If neither store exposes `removeEditorPanel`, the panels simply stay visible (cosmetic) instead of crashing the editor.
- Rollback: paste the `rollback/` versions back into both snippets.

## Testing strategy
### Automated (Gate 4)
- `php -l` both snippets; `node --check` on the extracted inline scripts.
- Node run of the helper against stubbed `wp.data`: core/editor present / only edit-post present / neither present / dispatch null during start-up then present → never throws, removes panels once.

### UAT checklist for the user (Gate 5)
- [ ] Local: open an existing weekly update in the block editor — it loads (no blank screen); "In This Issue" taxonomy panel is hidden; field highlights still work.
- [ ] Local: open an existing post — loads; category / tag / department / post-campus panels hidden.
- [ ] Local: Add New weekly update → editor loads (also lets us UAT the permalink fix in Gutenberg).
- [ ] Console on those screens: no red errors from the gatekeepers.

## Deployment notes for the dev (Gate 7)
Replace the code of Code Snippets **Weekly-Update--Divi-Gatekeeper** and **Posts--Divi-Gatekeeper** (titles as on live). Admin-only JS, no front-end impact, no cache purge needed.
