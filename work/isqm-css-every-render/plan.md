# Plan: ISQM shortcodes: print their CSS on every render (fixes unstyled block on weekly update pages)

- Task: `fix/isqm-css-every-render` · opened 2026-10-29
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-28`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
On the live weekly update pages the `[hs_isqm_gold]` block renders with no formatting. A console report from the page showed the block's HTML and the JavaScript present but the shortcode's `<style>` tag missing, and the raw page source (fetched without JavaScript) also has the block but no CSS. Cause: `hs_isqm_css_once()` prints the CSS only on the first render of the shortcode in a request and sets a flag. WordPress can render the shortcode early and discard the output (for example `get_the_excerpt()` is used by the weekly update code and runs the page content when there is no excerpt; SEO plugins do the same), which spends the flag, so the real render comes out unstyled. `[hs_quality_bar]` has the same flaw. This is in the dev's live version too, not something introduced by the previous task.

## Proposed changes
One file: `Site-Scripts/php/isqm-gold-announcement.code-snippets.php`, function `hs_isqm_css_once()` only: it now returns the `<style>` on every render instead of returning an empty string after the first. The flag is still set so the builder footer does not print a second copy. A comment explains why. No other change.

## Risks & rollback
- A page with two blocks gets the same CSS twice (a few KB, harmless; duplicate style id). No effect on layout.
- Rollback: put back the previous version of the snippet (the previous handover package, or the dev's live version) and purge caches.

## Testing strategy
### Automated (Gate 4)
- `php -l` clean (cloud container, receipt recorded).
- Reproduced in a PHP test: render both shortcodes once and discard, then render again. Before the fix the real output has no CSS; after, it has both the inline and Quality Bar CSS. Done.

### UAT checklist for the user (Gate 5)
- [ ] A weekly update page with `[hs_isqm_gold]` shows the block fully styled (needs the dev to deploy; locally, a weekly update with the shortcode if it can be made).
- [ ] Other pages with the shortcode and with `[hs_quality_bar]` look the same as before.

## Deployment notes for the dev (Gate 7)
- Replace the snippet content as in the previous package; this package contains everything from the previous one plus this fix, so deploy only this one. Purge WP Super Cache and Cloudflare APO.
- Smoke check: the weekly update page that showed the unstyled block, in a private window.
