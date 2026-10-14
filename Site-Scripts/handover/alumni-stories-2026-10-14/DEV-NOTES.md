# Alumni stories: IG story slider, blurb and story-card strip (Divi groups + JS/CSS) — dev notes

Package `alumni-stories-2026-10-14.zip` · 2026-10-14 · branch `feat/alumni-stories`

## Problem
New feature, not a fix. The alumni page gets an "alumni stories" block: our Instagram story slides (4:5) in a slider, a blurb panel and cards for the other stories. Content is built in Divi 5 as Groups, so the team can maintain it without code; one new script turns those Groups into the layout on the front end.

## Change
- **`alumni-stories.js` (new, front end only):** runs only where a `.hs-alumni-stories` Group exists, so it does nothing on every other page. It skips the Visual Builder.
  - For each story Group, it reads the Gallery (`.hs-story-slides`), the Image (`.hs-story-card`) and the Text (`.hs-story-text`), plus an optional Text `.hs-story-invite`.
  - It builds the widget and hides the original Groups.
  - Vanilla JS, no dependencies, no AJAX, no PHP.
- **`core.css`:** one block appended at the end ("ALUMNI STORIES", 472 lines). Everything is scoped to `.hs-as*` and `.hs-alumni-stor*` classes. Nothing above it changed; the diff is a pure append.
- **Unchanged:** all existing snippets, scripts and galleries. Story galleries don't carry `hs-gallery-container`, so `gallery-mosaic.js` ignores them.

## Files
| File in package | Change | Goes on live as | Previous version |
|---|---|---|---|
| files/css/core.css | modified | `Global stylesheet (core.css)` | rollback/css/core.css |
| files/js/alumni-stories.js | new | `wp-content/uploads/hs-head/3e3af9574c9de9aa6d43360464ec1638.js` | — new: deactivate/remove to roll back |

## Deploy
- Upload `files/js/alumni-stories.js` as `wp-content/uploads/hs-head/3e3af9574c9de9aa6d43360464ec1638.js` and add after the last script tag in Divi → Theme Options → Integration → head:
  `<script src="https://headstartphuket.com/wp-content/uploads/hs-head/3e3af9574c9de9aa6d43360464ec1638.js" defer></script>`
- CSS: Jeremy will add the block to the global stylesheet.

## Previous version
`rollback/` holds the live files as they were before this change, if you need to revert. Files marked *new* have no previous version: deactivate them instead.
