# HeadStart Post Sliders: Developer Handover Notes

**Package:** `handover/slider-fixes-2026-10-08.zip`

| File | Change |
|---|---|
| `php/posts-slider-shortcode.code-snippets.php` | Main changes (rendering, AJAX, Swiper setup, ordering, fixing autoplay logic) |
| `js/posts-slider.js` | Arrow click handler, cache-proof reload, restoring accidentally deleted functions |

---

## Action Required: CSS Tweak
Please manually delete the following conflicting rule from `core-site.css`:
```css
.hs-shortcode-slider-wrapper .swiper-button-disabled {
    opacity: 1 !important;
    pointer-events: auto !important;
    cursor: pointer !important;
}
```
*Why: This rule forced the arrows to stay fully solid and clickable even when there were no more cards, preventing Swiper from natively fading them out.*

---

## 1. What changed and why

### A. UI Arrow Fixes & Centering
- **Arrows:** Corrected the vertical centering of the mobile arrows so they sit perfectly aligned within the slider. Additionally, corrected an inline CSS selector inside the PHP file to properly hide those mobile arrows when viewed on desktop layouts.
- **Centering:** Added `centeredSlides: true` for mobile viewports so the cards sit correctly on small screens, ensuring the slider always starts with a card peeking in on the left edge.

### B. Stopping the Slide Jumps (Bidirectional Lazy Loading)
- **Initial render:** The slider now outputs the last 3 cards and first 12 cards of the list on page load, meaning a card always physically exists to the left of card 1 (preventing the hard stop/jump when scrolling left).
- **Lazy loading:** When the active card is within **4** of either edge, JS fetches **12** cards by `offset`/`limit` and prepends/appends them seamlessly into the DOM.
- **Loader timing:** The skeleton loader waits until the slider is completely still before inserting cards, ensuring card insertion never interrupts an ongoing animation.

### C. Autoplay & Arrow Disruption
- **Arrow Queueing:** A press made while a card is still sliding (e.g. during an autoplay step) is now queued and executes the moment the slide finishes. Previously, Swiper would just silently drop the click.
- **Autoplay Control:** Autoplay is set to 3.5s per card. It explicitly passes an `internal` flag when doing background tasks (like switching to loop mode) so that it doesn't accidentally cancel itself. However, it will cleanly stop the moment a user manually interacts with an arrow.

### D. Infinite Looping
- **Root cause:** Looping originally failed because Swiper requires all cards to be in the DOM to loop properly, and the original code gated the loop on an inaccurate total post count.
- **Fix:** The loaded ranges (lazy loading left and right) eventually meet. Once all cards for a department are loaded into the DOM, the JS automatically upgrades the slider into a true, seamless Swiper infinite loop.

### E. Restored Original Functions
- When the original `posts-slider.js` file was downloaded, it inexplicably contained unrelated code at the very bottom for the Events Calendar campus toggle and the Parent Fitness modal. 
- This bottom section was missing from the work-in-progress file, but has been safely restored to prevent breaking those elements on the Campus Hub page. 
- **Note for Dev:** Please quickly double-check that these restored functions don't conflict with any other recent work before deploying.

### F. Ordering & The Hybrid Layout Switch
- A new configuration constant `HS_ENABLE_LATEST_STRATEGY` has been added at the very top of `php/posts-slider-shortcode.code-snippets.php`. 
- **Currently turned ON (`true`):** This switches the default slider algorithm to a new `hybrid` strategy. 
- **The Hybrid Strategy:** It strictly preserves the date descending order for the absolute latest posts, but weaves in large gallery posts (8+ images) from the older imported archive. The pattern is `[Latest Post, Latest Post, Random Large Gallery Post]`.
- This approach was chosen because we will soon be publishing lots of new, rich content (that hasn't been imported), so we want the absolute latest posts to surface first, while still mixing in the best visual galleries from the archive.
- If turned OFF (`false`), it reverts to the old `random` strategy (fully randomized buckets).

---

## 2. Tunable values

| Setting | Where | Value |
|---|---|---|
| Hybrid Layout Toggle | `HS_ENABLE_LATEST_STRATEGY` (PHP) | `true` |
| Cards per lazy request | `HS_BATCH` (inline JS) | 12 |
| Trigger distance from edge | `HS_EDGE` (inline JS) | 4 |
| Cards rendered before card 1 | `$hs_tail_count` (PHP) | 3 |
| First batch | `$posts_per_page` (slider) | 12 |
| Autoplay delay | `autoplay.delay` | 3500ms |
| HTML cache key | `$html_transient_key` | `hs_slhtml54_` |
