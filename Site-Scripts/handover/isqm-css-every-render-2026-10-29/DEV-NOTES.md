# ISQM Gold snippet: wording and layout edits, plus two fixes for the inline shortcode — dev notes

Package `isqm-css-every-render-2026-10-29.zip` · 2026-10-29 · branch `fix/isqm-css-every-render`

## Problem
Replace the content of the existing Code Snippet `isqm-gold-announcement` with the file in this package. It starts from the version you reviewed and put live (logo loaded from a file, pop-up opens after page load). **This package contains everything**, so deploy only this one; it supersedes `isqm-gold-live-sync-2026-10-29` if you have not used that one yet (if you have, this replaces it). No new files, options or hooks, no permalink flush, and **no change to `core.css` is needed**.

Two bugs in the inline shortcode `[hs_isqm_gold]` on weekly update pages are fixed here, and both exist in your live version too:

1. **Unstyled block (CSS missing).** `hs_isqm_css_once()` printed the shortcode's `<style>` only on the first render in a request and set a flag. WordPress can render the shortcode early and throw the output away (the weekly update code calls `get_the_excerpt()`, which runs the content when there is no excerpt; SEO plugins do the same), which used up the flag, so the real render came out with no CSS.
2. **Divi overrides the block's text sizes.** Content shown through a Theme Builder Post Content module sits inside `.et-db #et-boc .et-l`, and Divi's customizer rules there (`h3 { font-size: 23px }`, a `p` rule, the `ul` 20px left padding and bottom margin) out-rank the block's selectors. The standard title rendered at 23px instead of 11px, the list heading at 16px instead of 13px, the numbered list sat 20px in from its heading, and the phone size bands for the titles did not apply either. The pop-up is not affected (it sits in the footer, outside those containers).

## Change
| Area | Change | Type |
|---|---|---|
| Area list | Re-ordered to the EDT report's Standards 1 to 8 (two columns of four). Labels: "Parents and the community", "Leadership and management" (the word "governance" is gone). | Content |
| Slide heading | Each slide shows the report's full standard title, small, uppercase, brand yellow, with reserved height so slides do not shift. | CSS plus code |
| Source line | The report source line under each quote has a 2px yellow left border. | CSS |
| Quality Bar | Quote slides show the full standard title; no outer margin or padding on the wrapper and bar (spacing is set in Divi); background #00294d becomes #003057; "by Education Development Trust" has more line height so it is not clipped. | CSS plus code |
| Phones (640px and below) | Pop-up and inline block: the small medal beside the title is hidden; the title is sized by length (short 14px, medium 10.5px, long 9px) in a fixed 42px block so every card is the same height; 10px gap from title to quote; source line pinned to the bottom of the card. | CSS plus code |
| Button | "Read the full story" becomes "Explore our accreditations" (pop-up, inline shortcode, settings label, help text). | Content |
| Inline block: CSS | The shortcode's CSS is printed on every render (bug 1 above). | Code |
| Inline block: text sizes | List heading, medal caption, standard title, quote and source line use `!important` on font, spacing and letter-spacing, desktop and phone rules, so Divi's heading and paragraph rules cannot override them. The values are unchanged. | CSS |
| Inline block: list | `margin: 0 -6px 12px !important; padding: 0 !important` on the numbered list, so "01" and "05" start under the "Rated outstanding in all eight areas" heading. | CSS |
| Inline block: divider | The grey divider line above that heading is removed from the inline block only (the `<hr>` and its rule). The pop-up keeps its line. | Markup plus CSS |

### Please review: the code changes
Everything else is text and CSS. These are the only logic and markup edits:
1. `hs_isqm_quotes()`: each of the eight rows gains `'title'` (the full standard title) and `'qb'` (its old position). Quote text is untouched.
2. Pop-up JavaScript: the slide heading prints `(s.title || s.area)` instead of `s.area`.
3. Inline shortcode PHP (`hs_isqm_sc_inline()`): the heading prints `isset( $s['title'] ) ? $s['title'] : $s['area']`; the `<hr class="hs-isqm-rule">` before the list heading is removed.
4. `hs_qbar_slides()`: one new line, `usort( $quotes, function ( $a, $b ) { return ( isset( $a['qb'] ) ? $a['qb'] : 99 ) - ( isset( $b['qb'] ) ? $b['qb'] : 99 ); } );`. The Quality Bar pairs each quote with a stat slide by position, so this keeps its original order while the pop-up uses the new order.
5. Title size band for phones: the slide title gets a class `hs-isqm-t-s`, `-m` or `-l` by length (up to 33 characters, up to 75, longer). PHP uses a new helper `hs_isqm_tsize()` (multibyte-safe); the pop-up JavaScript applies the same rule inline.
6. `hs_isqm_css_once( $key, $css )`: now always returns the `<style>` and still sets the flag (so the builder footer does not print a second copy). A page with two blocks gets the CSS twice (a few KB, harmless).

Nothing else calls `hs_isqm_quotes()`.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/isqm-gold-announcement.code-snippets.php | modified | `Code Snippets: isqm-gold-announcement` `rollback/php/isqm-gold-announcement.code-snippets.php` (your live version, as exported on 29 Oct) |

## Watch out for
- Nothing unusual: it is a replacement of the existing snippet's content. No `core.css` change is needed for it.
- The weekly update layout uses Divi column sizes; we separately updated the `core.css` padding/margin reset block for that. Unrelated to this package.
- Purge WP Super Cache and Cloudflare APO after deploying if URLs, redirects or page output changed.
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.

## Quick check after deploy (optional)
Open a weekly update that contains `[hs_isqm_gold]` in a private window. Expected: the block is fully styled; the yellow standard title is small (about 11px, uppercase); "01" and "05" start under the "Rated outstanding in all eight areas" heading; no grey line above that heading. Then check the pop-up and a page with `[hs_quality_bar]` look unchanged.

## Previous version
`rollback/` holds the live snippet as it was before this change, if you need to revert. Our 28 October live backup pre-dates this snippet, so the rollback copy is your version as exported from live on 29 October.
