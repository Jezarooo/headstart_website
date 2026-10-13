# DEV BRIEF: Posts Gatekeeper Fix

**Date:** October 7, 2026 · **Status:** Running on local (`headstart.local`). Owner has confirmed the core behaviour → dev review → live deployment

## Problem
Editors could open the Divi Builder (and publish from inside Divi) on Posts with required fields empty.

**Root cause:** the editor gatekeeper recognised Divi's button by its **label**. Divi's button (`#et-switch-to-divi`) reads "Use Divi Builder" on a never-saved post, but changes to **"Edit With The Divi Builder"** once a draft has been saved (`isNew: isCleanNewPostCached()` in Divi's `gutenberg.js`). The new label wasn't in the list, so the click went through. Two of the class selectors it checked (`.et_pb_gutenberg_button`, `.et-gb-toggle-builder-button`) don't exist in Divi at all. Nothing was checked on the server, and Divi's own Publish button wasn't checked at all.

## The rules (agreed with owner)
1. Post Title
2. Website Section (`hs_department`) **OR** Yellow Badge Override (`hs_thematic_chip`)
3. Campus (`hs_post_campus`) **OR** Display Location Override (`hs_location_override`)
4. **Featured Image** (required). It should be **landscape** because it's used as the **hero**.
   - A portrait or square Featured Image shows an **amber warning only**. It does not block (there may be valid uses).
   - The Portrait Featured Image (`hs_portrait_image`) is **optional and slider-only**. It does **not** count towards rule 4.

Posts that pass on overrides only will appear in "all posts" sliders, not filtered feeds. The owner accepts this.
**Drafts are never blocked** (owner confirmed: save a draft with a title first; Divi stays locked until the core fields are done). Publishing and opening Divi are blocked.

## Files changed
| File | Change |
|---|---|
| `Site-Scripts/php/posts-divi-gatekeeper.code-snippets.php` | Rewritten (see layers below) |
| `Site-Scripts/php/posts-admin-columns.code-snippets.php` | List-view check updated (rules + `et_fb_activation_nonce` links + delegated binding). New `post_class` filter `hs_posts_list_image_row_class` adds `hs-has-image` / `hs-no-image` to rows (Featured Image only). |
| `Site-Scripts/php/posts-register-fields.code-snippets.php` | Wording only: the Portrait field instructions now say it's optional and slider-only, and that the landscape Featured Image is the hero. |

Originals: `Site-Scripts/php/originals-2026-10-07/` (rollback = paste these back).

## How it works
| Layer | Where | What |
|---|---|---|
| **Shared rules** | `hs_post_gate_missing_fields( $post_id, $overrides )` | Returns the list of missing labels. Filterable via `hs_post_gate_missing_fields`. |
| **A. Browser alerts** | `hs_posts_gatekeeper()` (admin_head, post editor) | Detects Divi by `#et-switch-to-divi`, `.editor-post-switch-to-divi`, `et_fb=1` / `et_fb_activation_nonce` links (label text only as fallback). Also listens inside `iframe[name="editor-canvas"]`. If everything passes, **saves the post and waits for ACF meta boxes to finish saving** before letting Divi open. Live highlights: red = missing, green = OK, **amber = Featured Image is portrait/square** (orientation read from `core` `getMedia()` → `media_details`; nothing is shown until the image data has loaded). The featured-image hint text changes to match. |
| **B. Divi entry guard** | `template_redirect` @1 (before Divi's `et_fb_auto_activate_builder` @10) | Any front-end `?et_fb=1` / `?et_fb_activation_nonce` request for an incomplete post (by a user who can edit it) is redirected to the post editor, with a notice. |
| **C1. Divi Publish** | `rest_request_before_callbacks` on `POST /divi/v1/sync-to-server` | Changes the requested status `publish`/`future` to `draft` when incomplete. Takes into account `pageSettings.postTitle` / `postImage` sent in the same request. **Layout content is still saved.** |
| **C2. Block editor fallback** | `transition_post_status` + `wp_after_insert_post` @99 | The REST publish happens before ACF's separate meta-box save request, so the check is postponed (2-min transient `hs_post_gate_pending_{ID}`) to the meta-box request. There, an incomplete post is reverted to draft. |
| **C3. Quick/Bulk Edit etc.** | Same hooks, non-REST | Newly published incomplete posts are reverted to draft. |
| **Notices** | Per-user transient `hs_post_gate_notice_{user}` | Shown as a block-editor notice (`core/notices`) on that post, or an `admin_notices` error elsewhere. |

**Already-live posts are never unpublished by the server.** C only acts on *new* publishes (old status ≠ publish/future). Scheduled posts going live via cron are skipped.

## Verified locally
- `php -l` passes on all 3 files. The JS in both gatekeeper files passes `node --check`.
- No function-name collisions across `Site-Scripts/php`.
- The local front end and login return HTTP 200, with no PHP errors logged.
- Owner confirmed in the browser: drafts save; the Divi popup blocks when core fields are missing; red/green highlights display correctly.
- **Still to test:** the amber portrait warning, plus checklist steps 3–7 below.

## Points for dev review
1. **Divi 5 beta UI:** when C1 changes a publish to a draft, Divi's reply reports `post_status: draft`, but its interface may not explain why. The reason is shown in WP admin afterwards.
2. **C2 timing:** if Layer A were somehow bypassed in the block editor, the post is briefly live (about 1s) until the meta-box request reverts it. Other REST clients (`/wp/v2/` with no meta-box request) aren't reverted.
3. **Live but incomplete posts:** Layer A blocks the block editor **Update** button (same as before), and Layer B blocks opening Divi. Old live posts with no Featured Image (including ones that only have a Portrait image) will need a landscape Featured Image added before they can be edited in Divi. That's intended, but worth knowing.
4. **Orientation warning is browser-only.** Neither the server nor the list view checks orientation, by design (it's a warning, not a rule).
5. Requires WP ≥ 5.6 (`wp_after_insert_post`). The site runs 7.1.2.

## Test checklist
1. New post, nothing filled in → Divi button and Publish blocked, with an alert listing 4 items. ✅ *owner confirmed*
2. Save Draft with fields empty → saves. Reload → **"Edit With The Divi Builder" blocked** (the original bug). ✅ *owner confirmed*
3. Only the overrides (Badge + Location) plus a landscape Featured Image → Divi and Publish allowed. Portrait image only (no Featured Image) → **blocked**.
4. Set a **portrait or square** Featured Image → panel turns **amber** with a warning, but Divi and Publish are still allowed. Swap to landscape → green.
5. Tick the boxes **without saving**, then click Divi → saves, then opens Divi (no bounce back).
6. Incomplete draft → Posts list "Edit With Divi" blocked. Front-end admin bar "Enable Visual Builder" sends you back to the editor with a notice.
7. Inside Divi, remove the featured image (if Divi allows) and click Publish → the post stays a Draft. The notice appears in WP admin.
8. Quick Edit an incomplete draft to Published → it's reverted to Draft, with a notice on reload.

## Live deployment
Paste the three updated files into their matching **Code Snippets** entries ("Posts--Divi-Gatekeeper", "Posts--Admin-Columns", "Posts--Register-Fields"). Keep the gatekeeper set to run everywhere (admin + front end, because Layer B runs on the front end).
