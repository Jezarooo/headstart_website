# Plan: Image rack: portrait / landscape chip on each photo

- Task: `feat/rack-orientation-chip` · opened 2026-11-10
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-09`
- Roadmap: `rack-orientation-chip` (added with this plan)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## In plain English
Every photo in the image rack gets a small chip in its bottom-left corner saying **Portrait**, **Landscape** or **Square**. That way you can tell a photo's real shape even though the thumbnails are all cropped to squares. The chip appears on the weekly update's Articles → Posts panel and on the Post edit screen, including the phone-image picker in the hero preview. It's admin only: nothing on the public site changes. If a photo's size isn't recorded in WordPress, it simply gets no chip.

## Problem / goal
The rack shows WordPress's 150×150 thumbnails, which are square crops. A tall photo and a wide photo look the same there. That matters when choosing the slider card's Portrait image (it should be tall) and the hero (wide on desktop, tall for the phone image). Goal: show each photo's real shape on its thumbnail.

## Proposed changes
- **`Site-Scripts/php/posts-card-preview.code-snippets.php`** (Posts--Card-Preview)
  - New `hs_card_preview_image_shape( $id )`. It reads the attachment's stored `width`/`height` (`wp_get_attachment_metadata`) and returns `portrait`, `landscape` or `square` (within 5% counts as square), or `''` if unknown. No new meta: WordPress already stores these.
  - `hs_card_preview_content_images()` adds `'shape'`, `'w'` and `'h'` to each image it returns.
  - The picker JS (`imagePicker`) adds a chip `<span class="hs-cip-shape is-portrait|is-landscape|is-square">` in each rack item's bottom-left corner. The chip has a tiny outline of the shape plus the word, and a tooltip with the real pixel size (e.g. "Portrait · 1080 × 1440"). The same chip shows on the Featured / Portrait / phone-image slot thumbnails, so you can see the shape of what's already chosen.
  - CSS for the chip: dark translucent background, white 9px text, so it reads on any photo. It's kept away from the existing F (top-left) and P (top-right) tags.
- **`Site-Scripts/php/weekly-articles-to-posts.code-snippets.php`** (Weekly-Update--Articles-To-Posts)
  - The panel's image list adds `'shape'` (and width/height for the tooltip) using the same helper, guarded with `function_exists` so the panel still works if Posts--Card-Preview is off.

## Risks & rollback
- **Admin only.** No front-end output, URLs or saved data change. No cache purge or permalinks flush.
- **Older uploads may lack size metadata.** They show no chip rather than a wrong one.
- **Naming clash:** the purple "P" tag means "chosen as the Portrait image", while the new chip describes the photo's shape. The chip is visually different (bottom-left, dark, with a shape outline) and has a tooltip.
- **Rollback:** put back the pack 4 versions of the two snippets.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on both PHP files.
- `wp-tests.php`: `hs_card_preview_image_shape()` returns portrait / landscape / square / '' for a tall, wide, square and size-less attachment. Both image lists carry `shape`.
- `panel-check.mjs` and `post-screen-check.mjs`: every rack item with a known size has exactly one chip, matching its real shape. The chip sits bottom-left and doesn't overlap the F/P tags. The slot thumbnails show their chip.
- `http://headstart.local/wp-admin/post.php?post=<test post>&action=edit` and the weekly update edit screen return 200 with no PHP notices.

### UAT checklist for the user (Gate 5)
- [ ] Open a weekly update's Articles → Posts panel: every photo in the rack shows Portrait, Landscape or Square bottom-left, and it matches the real photo (open it in the Media Library to compare).
- [ ] Hovering the chip shows the pixel size.
- [ ] The F and P tags are still readable and not covered.
- [ ] Featured and Portrait slots show the chip for the chosen photo.
- [ ] On a Post's edit screen: same in the Images rack, and in the Tablet / Phone view's phone-image picker.

## Deployment notes for the dev (Gate 7)
- Replace the code of **Posts--Card-Preview** and **Weekly-Update--Articles-To-Posts**. Installs after pack 4 (post-hero-image).
- No new snippet, no permalinks flush, no cache purge (admin only).
