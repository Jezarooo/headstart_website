# Plan: ISQM shortcodes: print their CSS on every render (fixes unstyled block on weekly update pages)

- Task: `fix/isqm-css-every-render` · opened 2026-10-29
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-28`

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
On the live weekly update pages the `[hs_isqm_gold]` block renders with no formatting. A console report from the page showed the block's HTML and the JavaScript present but the shortcode's `<style>` tag missing, and the raw page source (fetched without JavaScript) also has the block but no CSS. Cause: `hs_isqm_css_once()` prints the CSS only on the first render of the shortcode in a request and sets a flag. WordPress can render the shortcode early and discard the output (for example `get_the_excerpt()` is used by the weekly update code and runs the page content when there is no excerpt; SEO plugins do the same), which spends the flag, so the real render comes out unstyled. `[hs_quality_bar]` has the same flaw. This is in the dev's live version too, not something introduced by the previous task.

## Added 2026-10-29 (second fix in this task): theme overrides the inline block's text sizes
A console report from the local weekly update preview showed the yellow standard title at 23px instead of 11px, and the "Rated outstanding in all eight areas" heading at 16px instead of 13px. Cause: Divi's customizer sets `.et-db #et-boc .et-l h3 { font-size: 23px }` (and a similar rule for `p`), which is more specific than the block's selectors, so the theme wins everywhere inside Divi content. The pop-up is not affected (it sits in the footer, outside those containers). The phone size bands for the titles were being overridden the same way. Expected to affect the live site too.

## Proposed changes
One file: `Site-Scripts/php/isqm-gold-announcement.code-snippets.php`, function `hs_isqm_css_once()` only: it now returns the `<style>` on every render instead of returning an empty string after the first. The flag is still set so the builder footer does not print a second copy. A comment explains why. No other change to that function.

Second change, same file, inside `hs_isqm_inline_css()` only (the pop-up CSS and the Quality Bar CSS are untouched): add `!important` to the typography and spacing of the inline block's list heading, medal caption (`.hs-isqm-mini`), standard title (`h3.hs-isqm-area`), quote and source line, including the phone-size rules, so Divi's heading and paragraph rules cannot override them. No sizes, colours or layout values change; the values are the ones already approved.

Third change (found while testing the inline block on a weekly update page, same file, `hs_isqm_inline_css()` and `hs_isqm_sc_inline()` only):
- The numbered area list is lined up with the "Rated outstanding in all eight areas" heading: Divi gives every `ul` a 20px left padding and a bottom margin; the list now sets `margin: 0 -6px 12px !important; padding: 0 !important` so "01"/"05" start under the heading and the selected outline extends 6px outside it. The 12px is the existing gap from the list to the quote panel.
- The grey divider line above the heading is removed from the inline version only (the `<hr>` and its CSS rule); the pop-up keeps its line.

## Risks & rollback
- A page with two blocks gets the same CSS twice (a few KB, harmless; duplicate style id). No effect on layout.
- Rollback: put back the previous version of the snippet (the previous handover package, or the dev's live version) and purge caches.

## Testing strategy
### Automated (Gate 4)
- `php -l` clean (cloud container, receipt recorded).
- Reproduced in a PHP test: render both shortcodes once and discard, then render again. Before the fix the real output has no CSS; after, it has both the inline and Quality Bar CSS. Done.

### UAT checklist for the user (Gate 5)
- [ ] A weekly update page with `[hs_isqm_gold]` shows the block fully styled (needs the dev to deploy; locally, a weekly update with the shortcode if it can be made).
- [ ] On a weekly update page (local or live), the yellow standard title is small (about 11px, uppercase, same look as the pop-up) and the list heading is about 13px.
- [ ] On a phone width, the titles use the three size bands and the cards stay the same height.
- [ ] Inline block: "01"/"05" line up with the heading, no grey line above it, gap to the quote panel unchanged.
- [ ] Other pages with the shortcode and with `[hs_quality_bar]` look the same as before.

## Deployment notes for the dev (Gate 7)
- Replace the snippet content as in the previous package; this package contains everything from the previous one plus this fix, so deploy only this one. Purge WP Super Cache and Cloudflare APO.
- Smoke check: the weekly update page that showed the unstyled block, in a private window.
