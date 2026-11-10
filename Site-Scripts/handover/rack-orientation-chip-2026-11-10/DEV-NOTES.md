# Image rack: portrait / landscape chip on each photo — dev notes

Package `rack-orientation-chip-2026-11-10.zip` · 2026-11-10 · branch `feat/rack-orientation-chip`

**Install order:** after `post-hero-image-2026-11-10.zip` (pack 4). This is pack 5. Both files build on pack 4's versions.

## Problem
The image picker's rack (Posts--Card-Preview, `hsCardPreview.imagePicker()`) shows WordPress's 150×150 `thumbnail` size, which is a square crop. So a tall photo and a wide photo looked the same when choosing the slider card Portrait, the hero or the phone image.

## Change
**Posts--Card-Preview:**
- New `hs_card_preview_image_size( $id )`. It reads the stored `width`/`height` from `wp_get_attachment_metadata()` and returns `[ 'shape' => portrait|landscape|square|'', 'w', 'h' ]`. Within 5% counts as square, and the shape is `''` when no size is recorded. There are also small wrappers: `hs_card_preview_image_shape( $id )` and `hs_card_preview_shape_of( $w, $h )`.
- `hs_card_preview_content_images()` adds `shape`, `w` and `h` to each image.
- **Picker JS:**
  - Each rack item gets a chip `<span class="hs-cip-shape is-…">` in its bottom-left corner, reading Portrait, Landscape or Square. Hovering it shows the pixel size.
  - The Featured / Portrait / phone-image slots show the chip for the chosen photo. That works when the photo is in the rack or was picked from the Media Library during this page load.
- **CSS:** `.hs-cip-shape` and `.hs-cip-thumbwrap`, admin only.

**Weekly-Update--Articles-To-Posts:** the panel's image list adds the same `shape` / `w` / `h`. This goes through `function_exists( 'hs_card_preview_image_size' )`, so the panel still works without the chips if Posts--Card-Preview is off.

**Deliberately unchanged:** saved data (no new meta: WordPress already stores the sizes), the front end, URLs.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/php/posts-card-preview.code-snippets.php | modified | Code Snippet **Posts--Card-Preview** (admin only) | pack 4's version |
| files/php/weekly-articles-to-posts.code-snippets.php | modified | Code Snippet **Weekly-Update--Articles-To-Posts** | pack 4's version |

## Deploy steps
1. Make sure pack 4 is in.
2. **Replace** the code of Posts--Card-Preview and Weekly-Update--Articles-To-Posts with the files in `files/php/`, keeping their run settings.

## Watch out for
- **Admin only:** no permalinks flush, no cache purge.
- **Older uploads without size metadata** simply show no chip.

## Quick check after deploy (optional)
Open a weekly update's edit screen. In the Articles → Posts panel, each rack photo shows Portrait, Landscape or Square in its bottom-left corner.

## Previous version
To undo, put back pack 4's two files. No data to undo.
