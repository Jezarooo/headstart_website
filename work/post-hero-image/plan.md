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
- **Desktop only** (user, 10 Nov): phones and tablets keep the current featured image; mobile handling is a separate, later task.

## Proposed changes

### `Site-Scripts/css/core.css` (global stylesheet; Jeremy adds the rule on live, as with the padding fix)
After the existing Theme Builder image rules (~line 3140):
```css
/* Post hero (desktop): the featured image at the site's hero height, cropped to fill, focal point per Post.
   Phones and tablets keep the image as it was. */
@media (min-width: 981px) {
    .hs-post-hero .et_pb_image_wrap,
    .hs-post-hero img {
        width: 100% !important;
        height: 70vh !important;
        object-fit: cover;
        object-position: var(--hs-hero-focus, 50% 50%);
    }
}
```
It does nothing until the class `hs-post-hero` is on the module (see Templates), so it's safe to add first.

### Templates (database, done by Jeremy)
In **HSN Post STANDARD** and **HSC Posts STANDARD** (body layout titled "HSC Posts Archive Body Layout"): open the featured-image Image module (the first module of the body; its source is the dynamic "Featured Image") → Advanced → CSS ID & Classes → add `hs-post-hero`. Recommended, but your call: add it to the three Archive templates too, so imported Posts' heroes are the same height (their focal point is the centre unless set).

### `Site-Scripts/php/posts-register-fields.code-snippets.php` (Posts--Register-Fields)
1. A new ACF field in Post Classification: **Hero focal point**. Key `field_hs_hero_focus`, name `hs_hero_focus`, type text, wrapper class `hs-pc-hero-focus`. It stores two whole percentages, `"50 30"`. Empty means the centre. On save it's validated to two numbers between 0 and 100; anything else is cleared. The field is hidden by the Post screen script, which sets it from the preview; its input stays in the form so it saves. If the script fails, the field shows and can be typed into.
2. On the front end of a single Post, a `wp_head` hook prints `<style>.hs-post-hero{--hs-hero-focus:X% Y%}</style>` when `hs_hero_focus` is set. Nothing is printed when it's empty.
3. The Post screen's script adds the hero preview with the focal point control (below).

### New in `Site-Scripts/php/posts-card-preview.code-snippets.php` (Posts--Card-Preview)
`hsCardPreview.heroFocus({ image, get, set })`, a third labelled preview, **"Preview · Hero (top of the Post page)"**:
- **One image with a desktop frame** *(user, 10 Nov)*: the whole Featured image, with a frame on top showing exactly what the desktop hero shows. The frame is the hero's shape at a 1920×1080 window: the 973px content column × 70vh, about 1.29:1. Outside it is dimmed. Drag the frame to move it, use the arrow keys (1% per press, 10% with Shift), or click **Centre**. It slides only along the axis that gets cropped: left/right for photos wider than the hero (most landscape photos), up/down for taller ones. Its position is the `object-position` the site uses, so what's in the frame is what the page shows.
- No separate crop box and no dot: the frame is the crop. No phone frame (phones are unchanged).
- Label lines as in the other previews: "Hero: the Featured image at 70% of the window height", "Focal point: stays in view when cropped".
- With no Featured image it says to pick one first.

### `Site-Scripts/php/weekly-articles-to-posts.code-snippets.php` (Weekly-Update--Articles-To-Posts)
- A new row field `heroFocus` (`"X Y"`, sanitised as above). Its default is empty (centre), and it resets to the centre when the Featured image changes.
- The hero preview is shown under the card preview, fed from the row's Featured image.
- On **Create Posts**, `hs_hero_focus` and its ACF key are saved on the Post when set.

Not changed: URLs, chips, dates, the gatekeeper, page heroes, weekly updates, slider cards (Posts--Slider-Shortcode isn't touched).

## Risks & rollback
- **Different window shapes on desktop:** the hero box is the content column's width × 70vh, so its shape varies a little between screens (wider on a short laptop window, squarer on a tall one). The preview uses a 1920×1080 window; the focal point keeps the chosen part in view on other desktop sizes too.
- **The class must be on the right module:** if it were added to another image module, that image would become 70vh. The template steps name the module. Checked on the local templates.
- **CSS before the class:** harmless, because the rule only matches `.hs-post-hero`.
- **Cache:** the hero's look is page output. Purge WP Super Cache and Cloudflare APO after adding the CSS and the class. A focal point change on one Post is picked up when that Post is saved, as now.
- **Rollback:** remove the class from the templates (the heroes go back to natural height), or remove the CSS rule. Restore the three snippets from pack 3 as listed in the dev notes. `hs_hero_focus` meta stays and does nothing.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on the three snippets; the inline JS is extracted and `node --check`ed.
- `wp eval-file work/weekly-articles-to-posts/tests/wp-tests.php` (all 188 still pass), plus:
  - `hs_hero_focus` sanitising: `"50 30"` kept; `"120 x"`, `"-5 10"` and junk cleared; empty is fine;
  - the front-end style tag is printed only for a single Post with a focus, with the right values;
  - slider card HTML is unchanged by a focal point;
  - panel rows: `heroFocus` sanitised; a created Post stores it; the new ACF field keeps its key, name and type.
- `panel-check.mjs` (all 65 still pass), plus: the hero preview appears with the frame; dragging the frame changes the row's focus along the cropped axis only; a created Post has `hs_hero_focus`.
- `post-screen-check.mjs` (all 35 still pass), plus:
  - the hero preview follows the Featured image;
  - the frame matches the desktop hero's shape over the image; dragging it and the arrow keys move the focus along the cropped axis only;
  - Centre resets it;
  - the hidden field holds the value, and saving stores it;
  - changing the Featured image resets it.
- Front end, after I add the class to the local STANDARD templates for the test (then put them back, so you do it yourself in UAT):
  - the hero is 70vh tall at 1920×1080, 1920×800 and 1280×720; at 390×844 (phone) and 800×1024 (tablet) it's unchanged from today;
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
- [ ] Narrow the browser below 981px (or use your phone on the local site): the featured image looks as it does today.
- [ ] Page heroes, weekly updates and archive Posts look as before.

## Deployment notes for the dev (Gate 7)
Install **after** packs 1, 2 and 3.
1. **Replace** the code of **Posts--Register-Fields**, **Posts--Card-Preview** and **Weekly-Update--Articles-To-Posts** with the files in `files/php/`.
2. **Jeremy** adds the CSS rule to the live global stylesheet, then adds the class `hs-post-hero` to the featured image module in the two STANDARD templates (and the Archive ones if wanted), then purges WP Super Cache and Cloudflare APO.
- No permalinks flush.
- Smoke: open any Post → the hero is 70% of the window tall. Open a Post's edit screen → the hero preview with the desktop frame is under the card preview.
