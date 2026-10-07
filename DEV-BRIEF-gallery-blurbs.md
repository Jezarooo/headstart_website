# Dev Brief: Gallery Popup Title & Blurb
**Date:** 7 Oct 2026

## Goal
If an image has a **Title** and/or **Caption** in the Media Library, show it in the gallery **popup** (lightbox). If both are empty, show no text box. The thumbnail grid stays image-only.
Applies to all gallery variants: strip → bento popup, inline mosaic, paginated mosaic.

Two files to review and deploy. Each is the **live version with a minimal edit**.

---

## File 1: `Site-Scripts/js/gallery-mosaic.js`
**Replaces on live:** `/wp-content/uploads/hs-head/8bc22973688e0198e14ad97942bf5d15.js`
**Untouched live copy:** `Site-Scripts/js/live-site-backups-2026-10-07/8bc22973688e0198e14ad97942bf5d15.js`

**Change (lines 393–394 only):** `innerText` → `textContent`
```js
titleText = titleTag ? titleTag.textContent.trim() : '';
captionText = captionTag && captionTag !== titleTag ? captionTag.textContent.trim() : '';
```
**Why:** Site CSS hides Divi's gallery title/caption (`display:none`) to keep the grid clean. `innerText` returns `''` for hidden elements, so the popup never got any text. `textContent` reads it regardless. The existing popup code already renders text when present and adds `hs-empty-text` when absent.

**Verify:** `diff` against the live copy shows only these 2 lines.

---

## File 2: `Site-Scripts/php/images-meta-info-on-upload.code-snippets.php`
**Snippet:** "Strip Image Titles & Captions" (Code Snippets plugin)
**Untouched original:** `Site-Scripts/php/originals-2026-10-07/images-meta-info-on-upload.code-snippets.php`

**Change (auto-strip condition only):**
```php
if ( empty( $postarr['ID'] ) && $data['post_type'] === 'attachment' && strpos( $data['post_mime_type'], 'image/' ) === 0 ) {
```
**Why:** `wp_insert_attachment_data` fires on **updates as well as uploads**, so any Title/Caption typed into the Media Library was wiped on save. New uploads have no ID yet, so this limits stripping to new uploads only. The bulk "Strip Captions" tool is unaffected (it sets empty values explicitly).

---

## Before deploying, please check
1. **Caption test on live.** Add a Caption to an image, save, refresh. If it disappears, File 2 is the cause and is required.
2. **Divi Gallery module:** "Show Title and Caption" must be **on**, otherwise Divi outputs no text for the script to read.
3. **Fields used:** Media Library **Title** = popup heading, **Caption** = blurb. Description is not used.
4. **Image size data:** gallery `<img>` tags on live should carry `data-hs-w` / `data-hs-h` (from `hs-gallery-image-data.php`). This lets the script sort portrait/landscape without downloading full-size images. Please send us that snippet so local matches live (it's missing locally).
5. **Test** in all three variants: images with text show it in the popup only, images without show no empty box, portrait/landscape layout unchanged.

## Not in scope (separate task)
Alt text clean-up. Note: standalone (non-gallery) images use their **alt text** as the popup title.
