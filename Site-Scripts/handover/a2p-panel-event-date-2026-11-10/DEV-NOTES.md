# Articles to Posts panel layout, image picker and Post event dates — dev notes

Package `a2p-panel-event-date-2026-11-10.zip` · 2026-11-10 · branch `feat/a2p-panel-event-date`

**Install order:** the share-links package first, then `a2p-badge-override-2026-11-10.zip`, then this one. This package builds on the badge version of the Articles to Posts snippet.

## Problem
- Posts only had their publish date, so a Post scheduled or written up after an event showed the wrong date to readers.
- The Articles to Posts panel (`Weekly-Update--Articles-To-Posts`) laid every field out in one grid, showed the article's photos twice (once per image field), and had no way to set an event date or publish immediately without picking a date.

## Change
- **Posts--Register-Fields:** two optional ACF date pickers in the existing Post Classification group, after Display Location Override: `hs_event_start` (`field_hs_event_start`) and `hs_event_end` (`field_hs_event_end`), stored as `Ymd`. `hs_validate_event_end()` on `acf/validate_value/key=field_hs_event_end` refuses an end without a start, or an end before the start.
- **Posts--Compute-Taxonomies-From-Custom-Fields:**
  - `hs_compute_final_divi_chips()` also writes `hs_final_date_label`, the date readers see on the Post. It's the event date or range when set (`12 November 2026`, `12–14 November 2026`, `30 October – 2 November 2026`, `30 December 2026 – 2 January 2027`), otherwise the publish date as `j F Y`. The work is done in `hs_date_label_for_post()`.
  - `hs_card_date_label()` gives the slider card's short form: the publish date as `jS F Y` (unchanged) when there's no event, otherwise `12th November 2026`, `12–14 Nov 2026`, `30 Oct – 2 Nov 2026` or `30 Dec 2026 – 2 Jan 2027`. It's worked out when the card is drawn, so nothing is stored.
  - The meta catch also runs on `added_post_meta` and `deleted_post_meta`, and on the two event keys, so quick edits recalculate.
  - **One-off fill:** `hs_fill_date_labels_v1()` on `admin_init` writes `hs_final_date_label` for every Post that lacks it, 200 per admin page load. When it finds none left, it sets the option `hs_date_label_fill_v1`. It writes only that meta key: no re-save, and no change to any date.
- **Posts--Slider-Shortcode:** one line in `hs_get_post_slider_card_html()`. The card date is `hs_card_date_label()` when that function exists, else `get_the_date( 'jS F Y' )` as before. Ordering and the recent-post filter are unchanged.
- **Weekly-Update--Articles-To-Posts:**
  - **Layout:** grouped fields. Post: Title, then Publish date with a **Publish now** checkbox, Status, Event start and Event end in one aligned row. Website Section with the Yellow badge text under it. Campus with the Location text under it.
  - **Images:** one rack of the article's photos (150px thumbnails, lazy) feeding Featured and Portrait slots by click.
  - **Event dates:** event start and end are saved on the Post. **Publish now** dates a Live Post at creation and leaves a Draft's date floating.
  - **Labelled live preview:** of the slider card and the top of the Post page, beside the form on wide screens. It saves nothing, and its styles are copied and scoped to `#hs-a2p`.
  - **Fonts:** Montserrat and Open Sans are loaded from Google Fonts on weekly update edit screens only.
  - **Re-copy removed:** the `hs_a2p_recopy` action and function no longer exist.

**Deliberately unchanged:** URLs (`/school-life/YYYY/MM/` still uses the publish date), slider ordering, search results, RSS and Yoast dates.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/posts-register-fields.code-snippets.php | modified | Code Snippet **Posts--Register-Fields** | rollback/php/posts-register-fields.code-snippets.php |
| files/php/posts-compute-taxonomies-from-custom-fields.code-snippets.php | modified | Code Snippet **Posts--Compute-Taxonomies-From-Custom-Fields** | rollback/php/posts-compute-taxonomies-from-custom-fields.code-snippets.php |
| files/php/posts-slider-shortcode.code-snippets.php | modified | Code Snippet **Posts--Slider-Shortcode** | rollback/php/posts-slider-shortcode.code-snippets.php |
| files/php/weekly-articles-to-posts.code-snippets.php | modified (since the badge package) | Code Snippet **Weekly-Update--Articles-To-Posts** (admin only) | the version in `a2p-badge-override-2026-11-10.zip` |

## Deploy steps
1. Make sure the share-links and badge packages are in.
2. **Replace** the code of the four snippets above with the files in `files/php/`.
3. **Load any wp-admin page a few times** until the date-label fill finishes, at 200 Posts per load. It's done when the option `hs_date_label_fill_v1` exists, e.g. `wp option get hs_date_label_fill_v1`. Tell Jeremy when it is.
4. **Jeremy** then switches the Date module in the five Post templates (HSN/HSC STANDARD and the three Archive ones) to the custom field `hs_final_date_label`, and purges WP Super Cache and Cloudflare APO.

## Watch out for
- **Order matters:** if the templates are switched before step 3 finishes, Posts without a label show an empty date chip.
- **Preview** of an already-published Post shows blank chips. That's existing WordPress autosave behaviour, not this change.
- **Card output changes:** the slider card date changes only for Posts that have event dates; every existing card stays the same. Purge caches after deploy anyway, because card HTML is cached.
- No permalinks flush.
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.

## Quick check after deploy (optional)
Open any Post's edit screen: **Event start** and **Event end** are in Post Classification. Open a weekly update: **Articles → Posts** shows the grouped layout and the image rack, with the preview to the right on a wide screen. `wp eval 'echo hs_card_date_label( <any post ID> );'` prints that Post's card date.

## Previous version
`rollback/` holds the live files as they were before this change. To roll back, **switch the five templates back to Post Publish Date first**, then restore the three Posts snippets from `rollback/`, and put back the Articles to Posts snippet from the badge package. Event meta and `hs_final_date_label` stay in the database and do nothing without the code. The slider falls back to the publish date by itself.

## Live drift accepted
posts-compute-taxonomies: live differs from main only in the header (no opening <?php or name comment, and a stray ?> after the header comment); the logic is identical. The packaged file drops the stray ?>.
