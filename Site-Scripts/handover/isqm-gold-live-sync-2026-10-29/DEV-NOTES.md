# Sync ISQM Gold snippet with live version, then wording/layout edits — dev notes

Package `isqm-gold-live-sync-2026-10-29.zip` · 2026-10-29 · branch `fix/isqm-gold-live-sync`

## Problem
Replace the content of the existing Code Snippet `isqm-gold-announcement` with the file in this package. It starts from the version you reviewed and put live (logo loaded from a file, pop-up opens after page load); what changes is wording, layout and CSS, plus five small code edits listed below. No new files, options or hooks, and no permalink flush. The aim: the eight areas and quote headings now match the EDT report's own standard names and order, the button names the page it links to, and the phone layout no longer leaves uneven gaps.

## Change

| Area | Change | Type |
|---|---|---|
| Area list | Re-ordered to the EDT report's Standards 1 to 8 (two columns of four). Labels: "Parents and the community", "Leadership and management" (the word "governance" is gone). | Content |
| Slide heading | Each slide shows the report's full standard title, small, uppercase, brand yellow, with reserved height so slides do not shift. | CSS plus code |
| Source line | The report source line under each quote has a 2px yellow left border. | CSS |
| Quality Bar | Quote slides show the full standard title; no outer margin or padding on the wrapper and bar (spacing is set in Divi); background #00294d becomes #003057; the line "by Education Development Trust" has more line height so it is not clipped. | CSS plus code |
| Phones (640px and below) | Pop-up and inline block: the small medal beside the title is hidden; the title is sized by length (short 14px, medium 10.5px, long 9px) in a fixed 42px block so every card is the same height; 10px gap from title to quote; source line pinned to the bottom of the card. | CSS plus code |
| Button | "Read the full story" becomes "Explore our accreditations" (pop-up, inline shortcode, settings label, help text). | Content |

### Please review: the code changes
Everything else is text and CSS. These five are the only logic edits:
1. `hs_isqm_quotes()`: each of the eight rows gains `'title'` (the full standard title) and `'qb'` (its old position). Quote text is untouched.
2. Pop-up JavaScript: the slide heading prints `(s.title || s.area)` instead of `s.area`.
3. Inline shortcode PHP: the heading prints `isset( $s['title'] ) ? $s['title'] : $s['area']`.
4. `hs_qbar_slides()`: one new line, `usort( $quotes, function ( $a, $b ) { return ( isset( $a['qb'] ) ? $a['qb'] : 99 ) - ( isset( $b['qb'] ) ? $b['qb'] : 99 ); } );`. The Quality Bar pairs each quote with a stat slide by position, so this keeps its original order while the pop-up uses the new order.
5. Title size band for phones: the slide title gets a class `hs-isqm-t-s`, `-m` or `-l` by length (up to 33 characters, up to 75, longer). PHP uses a new helper `hs_isqm_tsize()` (multibyte-safe); the pop-up JavaScript applies the same rule inline.

Nothing else calls `hs_isqm_quotes()`.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/isqm-gold-announcement.code-snippets.php | modified | `Code Snippets: isqm-gold-announcement` | `rollback/php/isqm-gold-announcement.code-snippets.php` (your live version, as exported on 29 Oct) |

## Watch out for
- Purge WP Super Cache and Cloudflare APO after deploying if URLs, redirects or page output changed.
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.

## Previous version
`rollback/` holds the live files as they were before this change, if you need to revert. Our 28 October live backup pre-dates this snippet, so the rollback copy is your version as exported from live on 29 October.
