# Plan: Post hero: 70vh featured image with a focal point and preview

- Task: `feat/post-hero-image` · opened 2026-11-10
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-09`
- Roadmap: item `post-hero-image`
- Builds on: packs 1–3 (`a2p-badge-override`, `a2p-panel-event-date`, `post-screen-layout-preview`, merged 10 Nov, not yet live). This is pack 4 and installs after them.

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## In plain English
On desktop (981px and wider, Divi's desktop breakpoint), a Post's featured image at the top of its page will be exactly as tall as the hero banners on the rest of the site: 70% of the window height. It's cropped to fill, so tall photos no longer run off the screen. Each Post gets a **focal point**: the part of the photo that stays in view when it's cropped. You set it by clicking or dragging on the photo in a new hero preview that shows the desktop crop. Phones and tablets keep the featured image as it shows today; that is left for a separate task. This works both in the weekly update's Articles → Posts panel and on the Post edit screen. Slider cards don't change; you choose their photo with the Portrait image as now. You switch it on by adding one CSS class to the image module in the Post templates. The main risk is the class going on the wrong module in the template, which the steps guard against.

## Problem / goal
Page heroes are a row background image at `min-height: 70vh`, cropped to fill (`cover`) and centred. Measured: 756px on a 1080px window, 560px on 800px. On a Post, the featured image is a Divi **Image module** at the top of the Post template's body, shown at its natural height. A 3:2 photo comes out around 60% of the window, but a taller photo goes past the bottom and needs scrolling. There's also no way to choose which part of a photo stays in view.

Decisions from the user (10 Nov):
- Use the same viewport height as the rest of the site, as a **fixed** 70vh (not "at most").
- Allow the image to be nudged to its best position, with a preview, on both the weekly update panel and the Post screen.
- Slider cards are **not** changed: the Portrait image already chooses their photo.
- **70vh at every size, like the page heroes** (user, 10 Nov, revised again): measured, page heroes are 70vh on phones (371×591, portrait) and tablets (779×826) as well as desktop. The Post hero now does the same at all widths. An optional **Image for phones and tablets** replaces the Featured image below 981px, cropped the same way, with its own position.

## Proposed changes

### `Site-Scripts/css/core.css` (global stylesheet; Jeremy adds the rule on live, as with the padding fix)
After the existing Theme Builder image rules (~line 3140):
```css
/* Post hero: the featured image at the site's hero height (70vh, like the page heroes), cropped to fill,
   at the Post's focal point. Below 981px a Post can use its own Image for phones and tablets (printed per Post). */
.hs-post-hero .et_pb_image_wrap,
.hs-post-hero img {
    width: 100% !important;
    height: 70vh !important;
    object-fit: cover;
    object-position: var(--hs-hero-focus, 50% 50%);
}
```
It does nothing until the class `hs-post-hero` is on the module (see Templates), so it's safe to add first.

### Templates (database, done by Jeremy)
In **HSN Post STANDARD** and **HSC Posts STANDARD** (body layout titled "HSC Posts Archive Body Layout"): open the featured-image Image module (the first module of the body; its source is the dynamic "Featured Image") → Advanced → CSS ID & Classes → add `hs-post-hero`. Recommended, but your call: add it to the three Archive templates too, so imported Posts' heroes are the same height (their focal point is the centre unless set).

### `Site-Scripts/php/posts-register-fields.code-snippets.php` (Posts--Register-Fields)
1. A new ACF field in Post Classification: **Hero focal point**. Key `field_hs_hero_focus`, name `hs_hero_focus`, type text, wrapper class `hs-pc-hero-focus`. It stores two whole percentages, `"50 30"`. Empty means the centre. On save it's validated to two numbers between 0 and 100; anything else is cleared. The field is hidden by the Post screen script, which sets it from the preview; its input stays in the form so it saves. If the script fails, the field shows and can be typed into.
1b. Two new ACF fields: **Image for phones and tablets** (key `field_hs_hero_mobile`, name `hs_hero_mobile`, image, return ID, class `hs-pc-hero-mobile`) and its position (key `field_hs_hero_mobile_focus`, name `hs_hero_mobile_focus`, text "X Y", same sanitiser, class `hs-pc-hero-mobile-focus`). Both are hidden by the Post screen script and set from the preview; if the script fails they show as normal fields.
2. On the front end of a single Post, a `wp_head` hook prints `<style>.hs-post-hero{--hs-hero-focus:X% Y%}</style>` when `hs_hero_focus` is set (nothing when empty, so the hero is centred). When `hs_hero_mobile` is set to an image, it also prints, inside `@media (max-width: 980px)`, that image as the hero wrapper's background (`cover`, positioned at `hs_hero_mobile_focus`, centre if empty) with the `<img>` made invisible, so it keeps the 70vh box. Nothing is printed for phones when it's empty.
3. The Post screen's script adds the hero preview with the focal point control (below).

### New in `Site-Scripts/php/posts-card-preview.code-snippets.php` (Posts--Card-Preview)
`hsCardPreview.heroFocus({ image, get, set })`, a third labelled preview, **"Preview · Hero (top of the Post page)"**:
- **Device preview** *(revised)*: toggles **Desktop · Laptop · Tablet · Phone** (no dimensions or model names on them). Each shows a to-scale screen down to the fold: the header band, the hero at its measured size and position, and a **fold line** at the bottom of the window. No placeholder strip below the hero.
- **Measured hero sizes** (local site, Post template, 10 Nov; an earlier measurement was wrongly taken in a frame squeezed to 1024px):
  | Window | Header | Hero (w × h) | Shape |
  |---|---|---|---|
  | 1920×1080 | 256px | 1632 × 756 (70vh) | 2.16 : 1 |
  | 1470×830 | 157px | 1380 × 581 (70vh) | 2.38 : 1 |
  | Tablet 820×1180 | 100px | 779 × 826 (70vh) | 0.94 : 1 |
  | Phone 390×844 | 76px | 371 × 591 (70vh) | 0.63 : 1 (portrait) |
  These numbers are built into the preview; if the Post template's header or margins change, they'll need re-measuring (noted in the dev notes).
- **Setting the focal point:** one focal point per Post, a point on the photo (`hs_hero_focus`, "X Y"). The wide Desktop/Laptop crops use its up/down part; the portrait Tablet/Phone crops use its left/right part. Dragging the photo in a view (or the arrow keys) sets the part that view crops; **Centre** resets. So a typical 3:2 photo needs a drag in Desktop (up/down) and one in Phone (left/right).
- **Tablet and Phone views:** show the Featured image cropped to the 70vh portrait box, or the **Image for phones and tablets** when one is chosen. That image is picked below the view (from this Post's/article's images or Media Library, with padding and a compact layout) and has its **own position** (`hs_hero_mobile_focus`, "X Y"), set by dragging in the Tablet/Phone views. The photo rack keeps its scroll position while you use it (it's updated in place, not rebuilt).
- The previous single-image frame (wrong 1.29 shape) is replaced by this device preview.
- Label lines as in the other previews: "Hero: the Featured image at 70% of the window height", "Focal point: stays in view when cropped".
- With no Featured image it says to pick one first.

### `Site-Scripts/php/weekly-articles-to-posts.code-snippets.php` (Weekly-Update--Articles-To-Posts)
- New row fields `heroFocus` and `heroMobile` (image ID, with its URL for the preview). `heroFocus` (`"X Y"`, sanitised as above). Its default is empty (centre), and it resets to the centre when the Featured image changes.
- The hero preview is shown under the card preview, fed from the row's Featured image.
- On **Create Posts**, `hs_hero_focus` and `hs_hero_mobile` (with their ACF keys) are saved on the Post when set.

### Also in this pack (user, 10 Nov)
1. **Correct the hints about the URL.** A Post's URL is `/{campus}/school-life/YYYY/MM/post-name/`, built from the **Campus**, or from the **Location override** when set (`posts-routing-engine`); the Website Section isn't in it. Earlier hints said the Section sets the URL. New wording, on the Post screen (**Posts--Register-Fields**) and in the panel (**Weekly-Update--Articles-To-Posts**):
   - Website Section: "Dictates where on the site the Post is shown (its feeds). Also the default yellow badge."
   - Campus: "Tick both for Both Campuses. Sets the campus in the URL."
   - Location override / Location text: "Optional. Replaces the campus name in the chip and the URL, e.g. Bangkok Trip. Still tick a Campus."
2. **Renaming a Website Section, Yellow badge or Campus refreshes its Posts** (**Posts--Compute-Taxonomies-From-Custom-Fields**). Each Post stores its chip text (`hs_final_section_chip`, `hs_final_location_chip`), so renaming a term (e.g. "Thai" → "Thai Department", slug kept) used to leave Posts showing the old name until each was saved. A new `edited_term` hook for `hs_department`, `hs_thematic_chip` and `hs_post_campus` recalculates the chips for every Post in that term (via `hs_compute_final_divi_chips()`, which writes only those meta keys). Thai has 93 Posts; the largest Section has a few hundred, so it runs in the same request.
3. **The panel matches headings by slug too** (**Weekly-Update--Articles-To-Posts**): `hs_a2p_section_for_heading()` tries the exact name, then the slug (so a heading "Thai" still finds the Section after it's renamed "Thai Department"), then the aliases. The alias `thai department → Thai` becomes `thai department → thai` by slug, so it works before and after the rename.

Not changed: URLs, chips, dates, the gatekeeper, page heroes, weekly updates, slider cards (Posts--Slider-Shortcode isn't touched).

## Risks & rollback
- **Different window shapes on desktop:** the hero is the page width (minus margins) × 70vh, so its shape changes with the window: 2.16:1 at 1920×1080, about 2.4:1 on a MacBook Air or 1280×720. The Desktop and MacBook buttons show both; one focal point serves both.
- **Image for phones and tablets:** used below 981px in place of the Featured image, cropped to 70vh at its own position. Removing it brings back the Featured image (with the Post's focal point). Slider cards and anything else are untouched.
- **The class must be on the right module:** if it were added to another image module, that image would become 70vh. The template steps name the module. Checked on the local templates.
- **CSS before the class:** harmless, because the rule only matches `.hs-post-hero`.
- **Cache:** the hero's look is page output. Purge WP Super Cache and Cloudflare APO after adding the CSS and the class. A focal point change on one Post is picked up when that Post is saved, as now.
- **Rollback:** remove the class from the templates (the heroes go back to natural height), or remove the CSS rule. Restore the three snippets from pack 3 as listed in the dev notes. `hs_hero_focus` meta stays and does nothing.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the three snippets; the inline JS is extracted and `node --check`ed.
- `wp eval-file work/weekly-articles-to-posts/tests/wp-tests.php` (all 188 still pass), plus:
  - `hs_hero_focus` sanitising: `"50 30"` kept; `"120 x"`, `"-5 10"` and junk cleared; empty is fine;
  - the front-end style tag is printed only for a single Post with a focus, with the right values; with an Image for phones and tablets, the `max-width: 980px` rule names that image and its shape; without one, nothing for phones;
  - slider card HTML is unchanged by a focal point;
  - renaming a test Website Section and a test Campus refreshes `hs_final_section_chip` / `hs_final_location_chip` on their Posts (and a badge override still wins); Posts in other terms are untouched;
  - heading matching: exact name, then slug, then alias; "Thai" and "Thai Department" both find the Thai Section, before and after a rename;
  - the three hints read as above on both screens;
  - panel rows: `heroFocus` sanitised; a created Post stores it; the new ACF field keeps its key, name and type.
- `panel-check.mjs` (all 65 still pass), plus: the hero preview appears with the frame; dragging the frame changes the row's focus along the cropped axis only; a created Post has `hs_hero_focus`.
- `post-screen-check.mjs` (all 35 still pass), plus:
  - the hero preview follows the Featured image;
  - the frame matches the desktop hero's shape over the image; dragging it and the arrow keys move the focus along the cropped axis only;
  - Centre resets it;
  - the hidden field holds the value, and saving stores it;
  - changing the Featured image resets it.
- Front end, after I add the class to the local STANDARD templates for the test (then put them back, so you do it yourself in UAT):
  - the hero is 70vh tall at 1920×1080, 1470×830, 820×1180 and 390×844, with the widths in the table above, cropped at the focal point; with a phone image, below 981px the wrapper shows it at its own position and the box is still 70vh;
  - its `object-position` matches the Post's focus;
  - a Post without a focus is centred;
  - an imported Post on an Archive template is unchanged unless the class is added there;
  - the pages return 200 logged out.

### UAT checklist for the user (Gate 5)
- [ ] Add `hs-post-hero` to the featured image module in your local **HSN Post STANDARD** and **HSC Posts STANDARD** templates.
- [ ] Open a Post on your 1920 screen: the hero is the same height as a page hero (70% of the window), with no scrolling needed to see all of it.
- [ ] On the Post edit screen, the hero preview shows the Featured image with the desktop frame. Drag the frame to the best part. Save, then view the Post on desktop: the hero shows what was in the frame.
- [ ] The same in a weekly update's Articles → Posts panel: set a focal point, create a Draft, view it.
- [ ] Slider cards look as before.
- [ ] In the preview, switch between **Desktop**, **Laptop**, **Tablet** and **Phone**: each shows the header, the hero at its real size and the fold line (no empty space below). Drag the photo up/down in Desktop and left/right in Phone; compare with your screen, laptop and phone.
- [ ] Set an **Image for phones and tablets** on one Post (the picker has proper padding, and the photo rack scrolls smoothly). Drag it in the Phone view. On your phone (or a window under 981px) the hero shows it, 70% of the screen tall, at that position. Remove it: the Featured image is back.
- [ ] Without a phone image, the phone hero is the Featured image, 70% of the screen tall, cropped at the Post's left/right position.
- [ ] Page heroes, weekly updates and archive Posts look as before.
- [ ] The hints for Website Section, Campus and Location override read as above (no "URL" under Website Section).
- [ ] Rename the Website Section **Thai** to **Thai Department** (Posts → Website Sections; keep the slug `thai`). Open a Thai Post: its Section heading and slider badge read "THAI DEPARTMENT" without re-saving it. A weekly-update heading "Thai" or "Thai Department" still pre-selects it in the panel.

## Deployment notes for the dev (Gate 7)
Install **after** packs 1, 2 and 3.
1. **Replace** the code of **Posts--Register-Fields**, **Posts--Card-Preview**, **Weekly-Update--Articles-To-Posts** and **Posts--Compute-Taxonomies-From-Custom-Fields** with the files in `files/php/`.
2. **Jeremy** adds the CSS rule to the live global stylesheet, then adds the class `hs-post-hero` to the featured image module in the two STANDARD templates (and the Archive ones if wanted), then purges WP Super Cache and Cloudflare APO.
- No permalinks flush.
- Smoke: open any Post → the hero is 70% of the window tall. Open a Post's edit screen → the hero preview with the desktop frame is under the card preview.
