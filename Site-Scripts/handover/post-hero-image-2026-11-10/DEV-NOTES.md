# Post hero: 70vh featured image with a focal point and preview — dev notes

Package `post-hero-image-2026-11-10.zip` · 2026-11-10 · branch `feat/post-hero-image`

**Install order:** the share-links package, then `a2p-badge-override-2026-11-10.zip` (pack 1), `a2p-panel-event-date-2026-11-10.zip` (pack 2), `post-screen-layout-preview-2026-11-10.zip` (pack 3), then this one (pack 4). Every PHP file here builds on the pack 2/3 versions.

## Problem
- **Post template hero:** the featured Image module showed the featured image at its natural height. The page heroes are 70vh rows, so Post heroes didn't match them. There was also no way to choose which part of a photo stays in view when it's cropped.
- **Phones and tablets:** Posts had no way to use a different, better-framed image at small sizes.
- **Hints:** in Post Classification, the Website Section hint said it set the URL. In fact the URL comes from Campus or Location (`posts-routing-engine`), and the Section sets the feeds.
- **Renaming a term:** each Post stores its chip text (`hs_final_section_chip`, `hs_final_location_chip`). Renaming a Section, badge or Campus term (e.g. Thai → Thai Department) left every Post showing the old name until it was saved again.
- **Heading matching:** the weekly update Articles → Posts panel matched article headings to Sections by exact name only, so "Thai Department" wouldn't find the `thai` Section.

## Change
**Global stylesheet (core.css):** one new rule after the Theme Builder image rules. It applies only where the featured Image module has the class `hs-post-hero`:
```css
.hs-post-hero .et_pb_image_wrap,
.hs-post-hero img {
    width: 100% !important;
    height: 70vh !important;
    object-fit: cover;
    object-position: var(--hs-hero-focus, 50% 50%);
}
```
Jeremy adds this rule to live core.css himself, and adds `hs-post-hero` to the two STANDARD Post templates. Nick: no template or CSS work needed. `files/css/core.css` is the full file for reference only.

**Posts--Register-Fields:**
- **Three new fields** in `group_hs_post_classification`:
  - `hs_hero_focus` (text, "X Y" in percent)
  - `hs_hero_mobile` (image, returns an ID)
  - `hs_hero_mobile_focus` (text)

  All three are optional. They're registered in PHP like the rest of the group, so there's nothing to import. `hs_hero_focus_sanitize()` keeps both focus fields to "0–100 0–100" or empty.
- **On single Posts, `wp_head` prints:**
  - `<style id="hs-hero-focus">`, which sets `--hs-hero-focus` on `.hs-post-hero`;
  - when a phone image is set, `<style id="hs-hero-mobile">`: at ≤980px the image wrap gets that image as a cover background at its own focus, and the `<img>` is hidden.
- **On the Post edit screen:**
  - the three fields are hidden behind a hero preview. You choose Desktop, Laptop, Tablet or Phone, then drag the photo or use the arrow keys to set the focal point;
  - the Tablet and Phone views have a picker for the phone image;
  - the hints are corrected (Section: where the Post is shown; Campus / Location: the URL).

**Posts--Card-Preview:** adds `hsCardPreview.heroFocus()`, the shared hero preview. The device shapes in it were measured on the Post template. Admin only.

**Posts--Compute-Taxonomies-From-Custom-Fields:** new `edited_term` hook, `hs_refresh_chips_after_term_rename()`. When an `hs_department`, `hs_thematic_chip` or `hs_post_campus` term is edited, it re-runs `hs_compute_final_divi_chips()` for every Post in that term. It writes only the chip meta.

**Weekly-Update--Articles-To-Posts:**
- each article row gets the same hero preview and phone image, saved to the new meta on create;
- `hs_a2p_section_for_heading()` now matches by exact name, then slug, then a short alias list.

**Deliberately unchanged:**
- Posts without a focus are centred;
- Posts without a phone image use the featured image;
- templates without `hs-post-hero` look exactly as before;
- URLs, slider cards (they keep using the Portrait), feeds and the gatekeeper are unchanged.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/css/core.css | modified | Global stylesheet (core.css). Jeremy adds the one rule above. | pack 1's core.css |
| files/php/posts-card-preview.code-snippets.php | modified | Code Snippet **Posts--Card-Preview** (admin only) | pack 3's version (new in pack 3) |
| files/php/posts-compute-taxonomies-from-custom-fields.code-snippets.php | modified | Code Snippet **Posts--Compute-Taxonomies-From-Custom-Fields** | pack 2's version |
| files/php/posts-register-fields.code-snippets.php | modified | Code Snippet **Posts--Register-Fields** | pack 3's version |
| files/php/weekly-articles-to-posts.code-snippets.php | modified | Code Snippet **Weekly-Update--Articles-To-Posts** | pack 3's version |

## Deploy steps
1. Make sure packs 1–3 are in, in order.
2. **Replace** the code of these four Code Snippets with the files in `files/php/`, keeping their current run settings:
   - Posts--Card-Preview
   - Posts--Compute-Taxonomies-From-Custom-Fields
   - Posts--Register-Fields
   - Weekly-Update--Articles-To-Posts
3. Purge WP Super Cache and Cloudflare APO, because Post pages now print the hero style tags.

## Watch out for
- **Rollback is to pack 3 (and pack 2 for Compute-Taxonomies), not to `rollback/`.** `rollback/` holds the older live copies from before packs 1–3. Restoring them would also undo those packs.
- **No permalinks flush.** No URLs or routing change.
- **Nothing shows on the front end until Jeremy adds the CSS rule and the `hs-post-hero` class.** Until then the new style tags are harmless.
- **Cloudflare challenges plain `curl` (403).** Send a browser user agent when checking.

## Quick check after deploy (optional)
1. Open any Post's edit screen. Post Classification shows the hero preview with Desktop / Laptop / Tablet / Phone buttons.
2. Drag the photo, update the Post, and view its source. It contains `<style id="hs-hero-focus">` with the new position.

## Previous version
See **Watch out for**. To undo this pack only, put back pack 3's Posts--Card-Preview, Posts--Register-Fields and Weekly-Update--Articles-To-Posts, and pack 2's Posts--Compute-Taxonomies-From-Custom-Fields. The new meta can stay; nothing reads it without this pack.

## Live drift accepted
main holds packs 1-3, not live yet; this pack installs after them. Checked byte-for-byte: core.css = pack 1 (a2p-badge-override), posts-compute-taxonomies = pack 2 (a2p-panel-event-date), posts-register-fields = pack 3 (post-screen-layout-preview).
