# Post edit screen: tidy layout and labelled card preview — dev notes

Package `post-screen-layout-preview-2026-11-10.zip` · 2026-11-10 · branch `feat/post-screen-layout-preview`

**Install order:** the share-links package, then `a2p-badge-override-2026-11-10.zip` (pack 1), then `a2p-panel-event-date-2026-11-10.zip` (pack 2), then this one (pack 3). This package builds on pack 2's versions of two snippets.

## Problem
- On the Post edit screen, the seven **Post Classification** ACF fields (`group_hs_post_classification`) sat in one long row. Each had a paragraph of instructions, so the row was uneven, and related fields weren't paired.
- There was no preview of how a Post's choices look on its slider card or Post page.
- The featured image could only be set from the sidebar. The weekly update Articles → Posts panel already had a labelled preview and an image picker, but they weren't available on the Post screen.

## Change
- **Posts--Card-Preview** (new, admin only): one shared copy of the labelled preview and the image picker, used by both screens.
  - `hs_card_preview_screen()` limits it to edit screens of `post` and `weekly_update`. On those screens it prints `window.hsCardPreview` and its CSS in `admin_head`, and enqueues Google Fonts (Montserrat, Open Sans).
  - `hsCardPreview.create()` builds the slider card and Post-top preview with labelled lines. Its date rules match `hs_card_date_label()` and `hs_date_label_for_post()`.
  - `hsCardPreview.imagePicker()` provides Featured and Portrait slots plus a rack of photos.
  - `hs_card_preview_content_images( $content )` lists a Post's own Media Library photos: Divi 5 image modules, then gallery modules, then `<img class="wp-image-ID">`.
  - `hs_card_preview_unlisted_image_count( $content )` counts `<img>` tags that aren't Media Library items, such as imported archive Posts' `uploads/legacy-images/`.
- **Posts--Register-Fields:**
  - **Data unchanged:** the same field keys, names, types, required flags and term saving.
  - **Presentation only:**
    - shorter labels and hints;
    - a wrapper class on each field (`hs-pc-*`), with widths emptied;
    - on `post` edit screens, an `admin_head` CSS grid with named areas, and an `admin_footer` script.
  - **The script:**
    - adds group headings;
    - adds the preview, as a right-hand column when the box is 1400px or wider, otherwise behind **Show preview**;
    - adds the image picker.
  - **Featured slot:** this sets the real featured image through the block editor store (`core/editor` → `editPost({ featured_media })`), the same way the sidebar panel does.
  - **Portrait slot:** this sets the ACF Portrait field through `acf.getField('field_hs_portrait_image').render(...)`. ACF's own Portrait box is hidden by the script, and its input stays in the form.
  - **Gatekeeper:** the Posts gatekeeper finds fields by `data-name`, so it's unaffected.
- **Weekly-Update--Articles-To-Posts:** its own preview and image picker code is removed, and it calls the shared snippet instead. It looks and works as before. If the shared snippet is off, the panel shows plain Media Library buttons and no preview. The Website Section hint wording changed.

**Deliberately unchanged:** front-end output, URLs, the gatekeeper, the chip calculation and saved data. Nothing public changes.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/posts-card-preview.code-snippets.php | new | Code Snippet **Posts--Card-Preview** (Only run in administration area) | — new: deactivate to roll back |
| files/php/posts-register-fields.code-snippets.php | modified | Code Snippet **Posts--Register-Fields** | the version in pack 2 (`a2p-panel-event-date-2026-11-10.zip`). `rollback/` here is the older live copy from before pack 2. |
| files/php/weekly-articles-to-posts.code-snippets.php | modified | Code Snippet **Weekly-Update--Articles-To-Posts** | the version in pack 2 |

## Deploy steps
1. Make sure packs 1 and 2 are in, in that order, with pack 2's date-label fill done.
2. **Add** a new Code Snippet **Posts--Card-Preview** from `files/php/posts-card-preview.code-snippets.php`, set to **Only run in administration area**, and activate it.
3. **Replace** the code of **Posts--Register-Fields** and **Weekly-Update--Articles-To-Posts** with the files in `files/php/`.

## Watch out for
- **Rollback is to pack 2, not to `rollback/`:** restoring `rollback/php/posts-register-fields…` would also remove pack 2's Event start/end fields. To undo only this pack, put back pack 2's two files and deactivate Posts--Card-Preview.
- No permalinks flush, and no cache purge: admin only, nothing public changes.
- **Template note for Jeremy (from pack 2):** only the two STANDARD templates (HSN Post STANDARD, HSC Posts STANDARD) need their Date module switched to `hs_final_date_label`. The archive templates can stay on Post Publish Date.
- Archive Posts' body photos aren't Media Library items, so the image rack can't offer them. It says so on those Posts, and Media Library still works.

## Quick check after deploy (optional)
Open any Post's edit screen on a wide screen. Post Classification shows grouped fields with the labelled preview on the right, and an Images row with Featured and Portrait slots. Pick a photo from the rack for Featured: the sidebar's Featured image changes too. Then open a weekly update. Its Articles → Posts panel still shows its preview and image picker.

## Previous version
See **Watch out for**. To undo this pack only: deactivate **Posts--Card-Preview**, then put back pack 2's **Posts--Register-Fields** and **Weekly-Update--Articles-To-Posts**. No data to undo.

## Live drift accepted
posts-register-fields: main is pack 2's version (a2p-panel-event-date-2026-11-10, Event start/end fields), which isn't live yet; this pack installs after pack 2. Checked byte-for-byte against pack 2's packaged file.
