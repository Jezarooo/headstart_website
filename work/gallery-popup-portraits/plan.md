# Plan: Pop-up bento gallery: portraits treated as landscape

- Task: `fix/gallery-popup-portraits` · opened 2026-10-13
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-10-13`
- Roadmap: `local-gallery-portraits` (P1, live bug)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
The pop-up bento grid (opened from the fixed summary gallery or the strip gallery) no longer mixes portrait and landscape photos: every photo is laid out as a landscape tile. This happens on every page that uses the gallery module, on live and local.

`gallery-mosaic.js` → `preCompileGallery()` decides portrait/landscape for each hidden Divi gallery photo in this order:
1. `data-hs-w` / `data-hs-h` on the thumbnail, expected from a helper `hs-gallery-image-data.php`. That helper isn't in any live export and isn't on the site, so this never applies.
2. The thumbnail's `width` / `height` attributes. WordPress adds these to every content image (`wp_img_tag_add_width_and_height_attr`). For Divi gallery thumbnails they describe the **cropped 400×284 thumbnail**, so every photo reads as landscape. Confirmed in the browser: `width="400"` on every `.et_pb_gallery_item img`.
3. Download the full photo and measure it. Never reached for gallery photos.

The same check feeds the inline and paginated bento grids, so they are affected the same way. The summary and strip previews are meant to be landscape-only, and stay that way.

Goal: portraits are recognised again everywhere, without downloading the full-size photos.

## Proposed changes
1. **New PHP snippet `gallery-image-real-size.code-snippets.php`** (this is the missing `hs-gallery-image-data` helper).
   - Hooks `wp_content_img_tag`, the WordPress filter that already runs on every content image and passes the attachment ID.
   - For images with an attachment ID, it adds:
     - `data-hs-w` / `data-hs-h`: the original photo's size, from `wp_get_attachment_metadata()`;
     - `data-hs-srcset`: from `wp_get_attachment_image_srcset( $id, 'full' )`, so big pop-up tiles load a suitable size, not the 400px crop or the full original.
   - Front end only; it leaves an image alone if it already has `data-hs-w` or if the metadata has no size. No DB writes, no new options.
2. **`Site-Scripts/js/gallery-mosaic.js`** (live: `uploads/hs-head/a175107c2aea8fc25780095fc0f14383.js`). One condition in `preCompileGallery()`: the `width`/`height` shortcut (step 2) applies only to standalone images (`obj.type === 'standalone'`), never to gallery thumbnails. If the PHP data is ever missing (e.g. a gallery rendered outside the normal content filter), gallery photos fall back to measuring the original. That is slower, but correct, so this can't silently come back. No other changes to the packing or grid logic.

## Risks & rollback
- **New JS file name on live:** live JS files are named by the MD5 of their content, so the changed `gallery-mosaic.js` gets a new hash. The dev uploads it and updates the `<script>` link in Divi → Theme Options → Integration → head. DEV-NOTES will give the exact old and new names.
- **Layouts will change:** galleries that contain portraits will lay out differently from today, as intended. Galleries with only landscape photos keep their current fixed layouts.
- **Small HTML size increase:** each content image with an attachment ID gains a few data attributes.
- **Caching:** page output changes, so purge WP Super Cache and Cloudflare APO after deploy.
- **Rollback:** deactivate the new snippet and restore the previous JS file name in the Divi head (`rollback/` will hold the live original). Either part can be rolled back on its own: the JS change works without the PHP (just slower), and the PHP is harmless without the JS change.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint`: `php -l` on the new snippet (cloud container), JS syntax check on `gallery-mosaic.js`.
- Local fetch of `/cherngtalay/weekly-update/2026/10/weekly-update-7/` returns 200, and its HTML contains `data-hs-w=` on `.et_pb_gallery_item` images (curl / view-source).
- Console check on that page: `[...document.querySelectorAll('.et_pb_gallery_item img')].filter(i => +i.dataset.hsH > +i.dataset.hsW).length` is above 0 for a gallery that has portrait photos.

### UAT checklist for the user (Gate 5)
- [ ] Weekly update 7 (Cherngtalay): the summary gallery still shows landscape tiles only, unchanged.
- [ ] Click the summary gallery: the pop-up bento shows portraits as tall tiles mixed in with landscapes, with no gaps.
- [ ] Same for a strip gallery: click opens the pop-up with portraits mixed in.
- [ ] An inline bento grid page: portraits appear as tall tiles, no gaps.
- [ ] A paginated bento grid page: each page is flush, portraits present, paging still works.
- [ ] At least one gallery page outside weekly updates (your choice): pop-up opens and portraits show correctly.
- [ ] Clicking a tile in the pop-up still opens the large image viewer with title/caption, next/previous and close.
- [ ] Mobile width (narrow window): pop-up and inline grids still lay out cleanly.

## Deployment notes for the dev (Gate 7)
- **Add and activate a new Code Snippet:** `gallery-image-real-size` (run everywhere / front end).
- **Replace the gallery JS:** upload the new `gallery-mosaic.js` to `wp-content/uploads/hs-head/` under its new MD5 name, then update the matching `<script src>` in Divi → Theme Options → Integration → head (old: `a175107c2aea8fc25780095fc0f14383.js`). DEV-NOTES will give the exact new name.
- **No permalinks flush needed.**
- **Purge WP Super Cache and Cloudflare APO.**
