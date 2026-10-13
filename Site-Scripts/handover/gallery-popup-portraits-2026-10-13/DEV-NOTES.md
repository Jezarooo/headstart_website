# Pop-up bento gallery: portraits treated as landscape — dev notes

Package `gallery-popup-portraits-2026-10-13.zip` · 2026-10-13 · branch `fix/gallery-popup-portraits`

## Problem
On live, the pop-up bento grid opened from the **strip gallery** lays out every photo as a landscape tile, so portraits never get their tall `1x3` slot. The pop-up opened from the fixed summary gallery on weekly updates works as designed. The problem shows wherever the gallery thumbnails carry WordPress's thumbnail `width`/`height` (see step 2 below), and on live that appears to be the strip galleries.

The cause is in `gallery-mosaic.js` → `preCompileGallery()`. For each `.et_pb_gallery_item` it decides `isPortrait` in this order:
1. `data-hs-w` / `data-hs-h` on the thumbnail `<img>`. The code comments say these come from a `hs-gallery-image-data.php`, but nothing on the site outputs them, so this branch never runs.
2. The `<img>`'s `width` / `height` attributes. Core's `wp_filter_content_tags()` → `wp_img_tag_add_width_and_height_attr()` adds these to content images. For Divi gallery thumbnails they are the cropped thumbnail size (`width="400" height="284"`, confirmed in the browser), so every photo reads as landscape.
3. Load the original and read `naturalWidth` / `naturalHeight`. Never reached for gallery items.

Galleries whose thumbnails don't get those attributes fall through to step 3 and lay out correctly, which is why only some pop-ups were affected.

## Change
**New snippet `gallery-image-real-size`** (`hs_gallery_image_real_size()` on `wp_content_img_tag`, priority 20)
- This is the missing step-1 helper. For any content image WordPress matches to an attachment, it uses `WP_HTML_Tag_Processor` to add:
  - `data-hs-w` / `data-hs-h`: original size from `wp_get_attachment_metadata()`;
  - `data-hs-srcset`: `wp_get_attachment_image_srcset( $id, 'full' )`. `renderMosaicGrid()` already uses this, so large pop-up tiles get an appropriately sized file instead of the 400px crop or the full original.
- Skips admin, images with no attachment ID or no size metadata, and tags that already have `data-hs-w`. No DB writes, no options.

**`gallery-mosaic.js`**: one change in `preCompileGallery()`
- The step-2 `width`/`height` shortcut now applies only when `obj.type === 'standalone'` (standalone `.et_pb_image` images, where those attributes are the real size). Gallery items never use it.
- If the data attributes are ever missing, gallery items now fall back to step 3: slower, but correct.
- The diff is 4 lines (2 changed, 2 comment lines). The packing/layout engine, the summary and strip previews (deliberately landscape-only), the modal markup and the title/caption handling are all unchanged.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/js/gallery-mosaic.js | modified | the script currently served as `wp-content/uploads/hs-head/a175107c2aea8fc25780095fc0f14383.js` | rollback/js/gallery-mosaic.js |
| files/php/gallery-image-real-size.code-snippets.php | new | Code Snippets: `gallery-image-real-size` (front end) | — new: deactivate to roll back |

## Watch out for
- `gallery-mosaic.js` is the head script `uploads/hs-head/a175107c….js` (Divi → Integration → head). New content MD5: `0ecd9c6cd7c2af8261644a9e84e666b3`.
- The snippet and the JS change are independent; any order.
- Purge WP Super Cache + Cloudflare APO (page HTML changes).

## Quick check after deploy (optional)
On a weekly update with a gallery, run this in the browser console:
```js
[...document.querySelectorAll('.et_pb_gallery_item img')].map(i => (+i.dataset.hsH > +i.dataset.hsW) ? 'P' : (i.dataset.hsW ? 'L' : '?')).join('')
```
- **Expected:** a string of `L`/`P` with no `?`.
- **Then:** click the summary gallery; portraits should show as tall tiles in the pop-up.

## Previous version
`rollback/` holds the live files as they were before this change, if you need to revert. `rollback/js/gallery-mosaic.js` is byte-identical to the current `a175107c…js`. Files marked *new* have no previous version: deactivate them instead.
