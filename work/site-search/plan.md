# Plan: Site search: one instant, predictive search panel for the whole site

- Task: `feat/site-search` · opened 2026-11-06
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-05`
- Brief / review doc: Claude Doc "Site Search Plan" (https://claude.ai/artifact/PVJD3J6ohzWvBsyuMoABvx), agreed with the user 5 Nov 2026
- Approved design: mock-up `scratch/site-search/mockup/index.html` (v3, reviewed with the user 6 Nov 2026; gitignored, holds real staff photos)

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
Visitors can't find things. The only search today is the staff-directory box and the ECA table filter. WordPress's own search skips events and notices. Important documents (Google Docs, Drive folders, PDFs) are scattered across Divi tabs, buttons and code blocks on many pages and weekly updates.

We want one search box in the utility bar that finds people, class teachers, pages, quick answers (fees, enrolment, contact, "how much"), weekly updates and the articles inside them, events and documents. It must be instant and predictive, filter by campus, and understand combined searches such as "science teacher chaofah" or "events november".

### Decisions agreed with the user (5-6 Nov)
- **No new page.** Results drop down from the search field. No `/search/` page, no results shortcode. WordPress `/?s=` stays only as the no-JavaScript fallback.
- **Don't touch** the staff directory, events calendar, mega menu, mobile navigation or the weekly-update snippets.
- **Class teachers** are found through the staff display group ("Year 3 (Blue)") plus the job title. No staff field is added.
- **Weekly updates replace "News".** Two groups (UAT decision 6 Nov): **Weekly updates** holds the issues ("Weekly Update #9"); **Articles** holds posts and the articles inside issues, laid out like pages: the headline (+ campus), then two lines that start with **"Weekly Update #9 · 30 Oct 2026"** and continue with a preview of the article's own text. A post opens its own page; an issue-only article opens the issue.
- **Documents** are found by scanning content; titles come from the HTML around each link.
- **Documents show the weekly update they were found in, with its date highlighted** ("Found on: Weekly Update #9 · **30 Oct 2026** › section"); documents on pages show no date. Links inside posts are not collected: posts are copies of weekly-update articles, so the same links are found in the issue (UAT 6 Nov).
- **Legacy Drive galleries are left out (UAT decision 6 Nov).** The school no longer shares Google Drive photo galleries. A Google Drive *folder* link (`drive.google.com/drive/folders/…`) found only in weekly updates or posts is treated as a legacy gallery and not indexed (local: all 153 folder links are of this kind; none are on pages). All other Google links stay: Docs, Sheets, Slides, Forms, Drive files, and PDFs. A folder linked from a regular page is still a document. Any folder can be brought back by Pinning it on the Documents screen.
- **Posts and events are forward-looking sources (6 Nov).** Posts are only partly used today (imports), and the school will soon move from Google Calendar to the site calendar. Both must be searchable as they grow:
  - **posts** are indexed in their own right (every published post, its own page link), not only as copies of weekly-update articles;
  - **events** follow the existing `events` post type and meta keys (no new structure expected); they appear automatically once a public calendar page exists.
- **Source switches** on the Index status screen let the school turn each source (pages, people, weekly updates, posts, events, documents) on or off without code.
- **Search tracking** is wanted (GA4 via the existing `dataLayer`, plus a small admin report).
- **No AI answers.** Quick answers are written by the school.
- **Documents are curated** in a back-end Documents manager: new links go in automatically; the school can exclude, pin or retitle any document, with optional rules (age limit for weekly-update documents, exclude by URL pattern). No exclude-by-type rule (user decision 6 Nov).
- **Removed from scope:** a `/search/` page, Google sitelinks markup, voice input, category rail (layout B), scope pills, ghost-text completion, event deep links, article anchors (the user adds anchors to articles later).

### Design (from the mock-up)
- **Placement:** a real search field in the **utility bar**, next to the social icons (`[hs_search]` in a Divi Code module). The results panel **slides down from under the bar**, anchored to the field. No modal, no backdrop, no scroll lock. Esc clears, a second Esc closes; clicking outside closes. `/` or Ctrl/Cmd+K focuses the field.
- **Mobile:** the utility bar is hidden on small screens, so `[hs_search_button]` (magnifier icon) goes in the mobile header. It opens the same panel as a full-width sheet with the field at the top. Exact spot chosen by the user in Divi.
- **Layouts (UAT decision 6 Nov):** on desktop (viewport ≥ 1100 px) **the same structure for every search**: a quick-answer card across the top when there is one, then, only when people match, a **People row** of cards (five across, "Show all N people" makes the panel taller), then **three equal-width fixed columns** in the same place every time, **Pages + Quick answers | Weekly updates + Events | Articles**, then a **Documents** row showing one line of four with a **"Show all N documents"** toggle that makes the whole panel taller (the People and Documents rows never scroll). An empty column shows a quiet "No … match" line instead of disappearing. The panel has a **fixed standard height** (about 30% shorter than the space below the bar) and **only the three columns scroll** (UAT 6 Nov).
- **One card shape for every result (UAT 6 Nov):** a 38 px icon or photo, a one-line title (cut with … if long, full title on hover) with the campus tag, then **two lines of text in the Documents row and three lines in the columns** (more of each page's and article's text shows), so cards in the same area have the same height. **People cards** are different (UAT 6 Nov): a 62 px square photo as tall as the text, then three single lines: full name, job title, class + campus chips (each line is cut with … only if it doesn't fit). Page descriptions keep up to 260 characters and article previews up to 230 in the index. The text differs by type: people = role + class; pages = section + description; articles = **"Weekly Update #9 · 30 Oct 2026"** + article text; weekly updates = **"Week 9 · 30 Oct 2026"** + "Includes: <matching article headline>" (or "In this issue: …"); documents (own row, taller cards, no "Found on" label) = title, then two lines: page › section, then the weekly update's date (gold; none for pages) · document type (PDF, Google Doc, …).
- **Previews start at the match:** if the search word is further into an article's or page's text than the card can show, the preview starts just before it ("…Rising star James (4 Yellow…"), so every result visibly shows why it matched (UAT 6 Nov).
- **People open in the staff directory at their card (UAT 6 Nov):** a person result links to `/staff-directory/?hs_staff=<full name>`. On that page `site-search.js` uses the directory's own search: it types the name into the directory's search box and picks the matching suggestion, so the directory itself switches the campus tab, opens the section, scrolls to the card and pulses it, exactly as when a visitor searches there. `staff-directory.js` is not changed. The parameter is then removed from the address bar.
- **Campus codes:** the internal codes **HSC / HSN are always shown as Chaofah / Cherngtalay** in every result text (titles, previews, excerpts; never changed inside links), and typing "hsc" or "hsn" works as a campus filter (UAT 6 Nov).
- **How tooltips open (option C, UAT 6 Nov):** never from hovering the card itself. Each card has a small **"ⓘ Info" tab under its icon** (pages, weekly updates, articles, documents) and staff photos have a small round **"i" on the photo's corner**. Hovering the tab shows the tooltip instantly; tapping or clicking it toggles the tooltip (touch screens, where the tab is always fully visible); pressing **→ at the end of the typed text** toggles it for the selected result; ↑ ↓ or Esc close it. The tab (and the photo "i") is **hidden until its card is hovered or selected**; on touch screens it is always shown.
- **Tooltips on every result (UAT 6 Nov), all in the document-tooltip style and speed:** people = full name, job title, department; pages = section, language, campus, "On this page" (first headings) and the full description; articles = from (issue or department), date, section, campus and the full preview; weekly updates = week, date, campus and "In this issue" (up to 6 headlines, matching ones first); events = date/time, location, campus, blurb. Search words are underlined in tooltips too. Tooltips open **below** the hovered card (above only when there is no room) and have a straight gold top edge with rounded bottom corners.
- **Scroll hint:** a column with more results than fit shows a fade and a small gold "More below ↓" tag at its foot, which disappears once scrolled to the end.
- **Document tooltips (UAT 6 Nov):** our own tooltip (white card with a gold top edge, appears after ~0.1 s, not the browser's slow title tooltip) shows the title, a small table (Found on, Date, Type, Campus) and an excerpt of up to 220 characters of the text around the link (the paragraph or card it sits in; if that is a grid of links or a whole section, the text just before the link). The excerpt is stored per document in the index (`cx`).
- **Page language switch (UAT 6 Nov):** pages exist in English and Thai. When the matching pages include both, a small **EN | TH** switch sits at the top right of the Pages column and filters it (default: **English**). Switching only changes the Pages column; the highlighted row moves to the first page, never to another column.
- **Typos:** when a word matched through typo tolerance, the highlighting and previews use the word that actually matched, and the panel says "Showing results for **international**" (UAT 6 Nov). A weekly-update card always has a summary line ("Includes: …", or its topics or first headlines).
- **Empty panel:** the six quick-answer cards are all the same height in both rows. Below 1100 px (tablets, phones): one grouped list in the **same order as desktop** (People, Quick answers, Pages, Weekly updates, Articles, Events, Documents), 3 per group with "See all"; on phones it is a full-screen sheet that starts below the WordPress admin bar for logged-in editors (UAT 6 Nov).
- **Panel position:** starts at the left edge of the utility bar's row container (aligned with the social icons) and below 1920 px spans the full row container (aligned left and right); at 1920 px and wider it stays at max 1320 px (UAT 6 Nov).
- **Style: brand blue.** Panel `--hs-dark #003057`, the same as the utility bar; gold `--hs-gold #f0b323` for group headings, the active-row edge and the quick-answer card.
  - Field: same height as the Safeguarding button (30 px), **square corners** (UAT decision 6 Nov), 1px border `#cbd5e1`, `--hs-font-body`; vertically centred with the social icons. Buttons in the panel: 4px radius, no pill shapes. Focus: a thin **solid** brand-gold border (`1px solid #f0b323`), no glow, gradient or transparency (UAT 6 Nov).
  - Rows: hover is a solid lighter blue (`#0a4377`); the keyboard-active row also gets a **straight** 3px gold bar on the left, right corners rounded 6px; subtle 1px lines between rows (hidden next to the hovered row).
  - Fonts: weights 500-600 only; on the blue panel titles are white, secondary text `#a9c2dc`.
  - **Keyword highlight: gold underline** (2px, offset 3px), no fill.
  - Tags: campus = **gold dot** + plain text in a thin outline; class ("Year 3 (Blue)") = dashed outline; "Understood as" chips are outlines. Title line and meta line are flex rows so tags align.
  - Leading visuals, all 38 px squares: **staff photo** (featured image, 150 px thumbnail, `object-fit: cover`, 6px radius, soft shadow, matching the directory cards; no hover effect on the photo); pages = **layout** icon on a white tile; weekly updates = **newspaper** icon on a gold tile; documents = file-download icon with a small type tag (PDF, DOC…); events = square gold date block.
  - Buttons: brand blue or gold only, never a colour per campus. Hover lifts 1px and brightens; outlined buttons fill softly; visible focus ring.
- **Quick-answer cards:**
  - Standard card: title, one short paragraph, an optional note line (may hold one email link), **up to two buttons**.
  - **Contact the school** card: two columns, one per campus, each with address, phone numbers as `tel:` **buttons** (desktop and mobile), a Google Maps button and the campus email; a bottom row with the visit times and one "Contact page" button. Data (from `/contact-us/`):
    - Chaofah City Campus: 39/99 Moo 4, Vichitsongkram Rd., Soi Na Seau 1, T.Vichit, A.Muang, Phuket 83000 · +66-76-612875, +66-88-765-5878 · `info.city@headstartphuket.com` · https://maps.app.goo.gl/h1Bf1KAVkkpF7KuL6
    - Cherngtalay Campus: 9/2 Srisoonthorn Road, T.Cherngtalay, A.Thalang, Phuket 83110 · +66-76-510623, +66-81-678-6466 · `info.north@headstartphuket.com` · https://maps.app.goo.gl/JdCREJnwAFNRrVDq6
    - Visit times: "School visits & tours: Mon-Fri 9:00-15:00, or Saturday mornings by appointment" (the only hours on the Contact page; the user can change them).
  - Fees card: one page, `/resources-and-information/tuition-fees/`, plus `fees@headstartphuket.com` (the Contact page lists the same fees address for both campuses).
  - Enrol card: `/resources-and-information/enrolment-and-visa-support/` and `/booking/`. Term dates: `/resources-and-information/calendars-key-dates/`. Uniform: no page yet, left as a draft.

## Audit of the structure this builds on (local, 6 Nov)
| Area | Finding | Consequence for the plan |
|---|---|---|
| Content volume | 51 pages, 342 staff, 174 weekly updates (~3,350 article groups inside them), 730 posts, 287 events (131 upcoming), ~270 items with document links | Index will be roughly 1 MB raw / 200-300 KB gzipped; loaded only on first intent. Size guard below |
| Weekly update articles | Each article is a Divi Group with class `hs-weekly-article`, headline in the first `h3`, sections marked by `h2.hs-section`. All 730 posts use the same class | Articles are parsed from issues; a post with the same headline replaces the issue copy and gives a real URL |
| Staff | 342 published, all with featured images; campus slugs `chaofah-city`, `cherngtalay`, `both-campuses`; class in `staff_display_group` | Map `chaofah-city` → `chaofah`; `both-campuses` → both |
| Campus taxonomies | `hs_campus` (weekly updates) and `hs_post_campus` (posts) use `chaofah` / `cherngtalay` | Direct mapping |
| Events | Date meta stored as `Ymd` (e.g. `20260810`). The calendar shortcode is not in any page's `post_content` (it sits in a Divi layout or code module) | Events link to the events page found at build time (search pages, layouts and `et_template` posts for `hs_events_calendar`; fall back to a URL set on the status page) |
| ACF | Free version (no Repeater) | Quick-answer buttons are fixed fields (button 1, button 2); the contact card uses fixed per-campus fields |
| Global CSS | On live and locally the global stylesheet is the Custom CSS & JS post "Core CSS" (id 20333). The local loader runs with `no-css`, so `Site-Scripts/css/*.css` is **not** loaded locally | Search styles go in their **own file** `Site-Scripts/css/site-search.css` (not into the shared `core.css`), and the local loader learns to skip only `core.css` under `no-css` |
| JS loading | Live: `uploads/hs-head/<hash>.js` with `defer` from the Divi head. Local: loader inlines `Site-Scripts/js/*.js` in the footer, MANIFEST order | `site-search.js` must not rely on load order beyond `global-utilities.js`; it uses its own builder check |
| PHP loading | Live: Code Snippets (eval). Local: loader `require_once`s `php/*.php` A-Z | Each snippet stands alone: hooks only, `function_exists` guards, no `?>`, no output at load |
| Caching | WP Super Cache + Cloudflare APO cache page HTML; `.json` is not in Cloudflare's default cached extensions | Page HTML carries no index data or version; index fetched with `cache: 'no-cache'` (ETag revalidation). User checks live Cloudflare rules after deploy |
| Local uploads | `uploads-by-proxy` streams missing uploads from live | Locally a missing `index.json` may be proxied to live's 404; the admin-ajax fallback covers it |
| Stacking | Utility bar section `z-index: 1002`, overflow visible; mega dropdowns `1001`; nav `999` | Panel lives inside the utility bar's search wrapper (`position: absolute`), so it sits above nav and mega menus without a global z-index fight. Opening a mega menu closes the search panel |

## Proposed changes

### 1. `Site-Scripts/php/search-topics.code-snippets.php` (new): Quick answers + "Hide from search"
Header in the style of `weekly-update-short-links.code-snippets.php`. No permalink flush.
- **CPT `hs_search_topic`**, label "Quick answers": `public => false`, `show_ui => true`, `publicly_queryable => false`, `exclude_from_search => true`, `rewrite => false`, `show_in_rest => false`, `supports => ['title','page-attributes']`. Top-level admin menu "Site search" (`dashicons-search`), which also holds the status page.
- **ACF local field group** (`acf_add_local_field_group`, keys `field_hs_search_*`):

  | Field | Type | Notes |
  |---|---|---|
  | `hs_topic_card` | select `standard` / `contact` | Card layout |
  | `hs_topic_synonyms` | textarea | Comma- or line-separated words and phrases |
  | `hs_topic_answer` | textarea, max 300 | Short paragraph |
  | `hs_topic_note` | text | Optional line; an email address in it becomes a `mailto:` link |
  | `hs_topic_btn1_label` / `hs_topic_btn1_url` | text / url | First button (required to publish a standard card) |
  | `hs_topic_btn2_label` / `hs_topic_btn2_url` | text / url | Optional second button |
  | `hs_topic_pinned` | true_false | Shown in the empty panel |
  | Contact fields (conditional on `contact`) | text / url / email | For each campus: name, address, phone 1, phone 2, email, map URL; plus visit-times label and text, contact page URL |

  Order comes from `menu_order`.
- **Seed once** (option `hs_search_topics_seeded`, only if no topics exist): **draft** topics with synonyms, and the links already known: Contact the school (full contact data above), School fees, How to enrol, Calendar (term dates and key dates), Staff directory, Safeguarding, Book a tour, Uniform (no link), ECAs & clubs, Careers (`/recruitment/`), Lunch menu, Policies (`/key-policies/`), Alumni (`/alumni-network/`), Transport, Curriculum, Scholarships, Shop. Drafts never reach the index.
- **"Hide from site search"** ACF true_false `hs_search_exclude` in the sidebar of page, post, weekly_update and events (not staff).

### 2. `Site-Scripts/php/search-index-builder.code-snippets.php` (new): builds the index
- **Entry point** `hs_search_build_index()`: builds the array, writes `uploads/hs-search/index.json` through a `.tmp` file + `rename()`, stores `hs_search_index_meta` (built time, counts per type, raw and gzip bytes, ms, last error).
- **Triggers:** `save_post` / `delete_post` / `transition_post_status` for page, post, weekly_update, events, staff, hs_search_topic, plus term edits on the campus, department and display-group taxonomies. Each schedules one single WP-Cron build 30 s later (debounced; a transient lock stops overlapping builds). A daily rebuild catches anything missed. "Rebuild now" on the status page.
- **Admin screens** under Site search: **Index status** (source switches for pages, people, weekly updates, posts, events and documents; counts per type, size raw/gzip, build ms, last error, Rebuild now, class-teacher coverage, top searches and no-result searches with "Clear"), **Documents** (section 2c, which also shows the audit: markup, chosen title and rule used) and **Quick answers** (the CPT list).

**Index shape** (short keys):
```json
{ "v": 1, "built": "…",
  "campuses": [{"k":"chaofah","l":"Chaofah City","a":["chaofah","chao fah","city"]},
               {"k":"cherngtalay","l":"Cherngtalay","a":["cherngtalay","cherng talay","north"]}],
  "items": [
    {"id":"s45","t":"person","ti":"Name","s":"Year 3 (Blue) Deputy Teacher","c":["cherngtalay"],"cl":["year 3","3","blue","3b"],"g":"Year 3 (Blue)","k":["Primary"],"img":"…-150x150.jpg","u":"/staff-directory/"},
    {"id":"p12","t":"page","ti":"Tuition & Fees","s":"…","x":"headings and tab titles…","c":[],"lg":"en","u":"/resources-and-information/tuition-fees/"},
    {"id":"w301","t":"issue","ti":"Weekly Update #9","wk":9,"d":"2026-10-30","k":["Chess Tournament","Friday Market"],"c":["cherngtalay"],"u":"…"},
    {"id":"a9_3","t":"article","ti":"Friday Market: Call for Vendors!","sn":"Our next Friday Market is on…","sec":"Upcoming events","p":"w301","d":"2026-10-30","c":["cherngtalay"],"u":"…"},
    {"id":"e88","t":"event","ti":"…","s":"blurb…","d":"2026-11-20","de":"2026-11-20","c":["chaofah"],"u":"<events page>"},
    {"id":"d9","t":"doc","ti":"Foundation Parent Handbook","ft":"gdoc","u":"https://docs.google.com/…","on":{"ti":"Foundation","sec":"Downloads","u":"/…/"},"c":["chaofah"]},
    {"id":"q3","t":"topic","card":"standard","ti":"School fees","syn":["fees","how much"],"a":"…","note":"…","b":[["Tuition & Fees","/resources-and-information/tuition-fees/"]],"pin":1}
  ] }
```
No campus (`c: []`) means both campuses.

**Sources** (all skip non-published items and `hs_search_exclude = 1`):
| Type | Source | Kept | Rules |
|---|---|---|---|
| person | `staff` | name, `job_title`, display group → `g` and class tokens `cl`, department → `k`, campus, 150 px thumbnail | One item per person. No email, no blurb. `u` = the page holding `[hs_staff_directory` (local: `/staff-directory/`) |
| page | `page`, no password | title, Yoast description or first 200 chars, H1-H3 + Divi tab titles (≤ 600 chars) → `x`, `page_language` → `lg` | Skip Yoast noindex, campus parent pages redirected by `pages-campus-routing`, language placeholder parents, thank-you pages |
| issue | `weekly_update` | title, `hs_week_number`, date, `in_this_issue` terms → `k`, the headlines of the articles inside → `x` (so a search for a word in an article also finds its issue), `hs_campus` | `u` = permalink. Newest first for issue queries ("weekly update", "week 7") |
| article | `.hs-weekly-article` groups inside each issue | headline (first `h3`), section (nearest `h2.hs-section`, else the Divi section admin label), 160-char body snippet `sn`, parent issue, date, campus | Links to the issue. If an article group later carries an `id`, the link adds `#id` (for the anchors the user will add). Dropped when a post has the same normalised headline (the post wins) |
| post | `post`, publish (all of them, not only matches) | title, 160-char snippet (excerpt, else first text; the first `.hs-weekly-article` body if present), `hs_department` → `k`, `hs_post_campus`, date, the issue it also appears in (if any) → `p` | Own permalink. Shown in the Weekly updates group; the meta line reads "Weekly Update #9 · section · date" when it matches an issue article, otherwise "department · date". Works whether or not the post uses `hs-weekly-article`, so new-style posts need no change |
| event | `events`, end (or start) date ≥ today − 60 days | title, `event_blurb`, category, location, dates (`Ymd` → ISO), times, `target_campus` | Uses the existing post type and meta keys, so the new calendar content is picked up without code changes. **Only included once a public calendar page exists:** the builder looks for a published page or Divi layout containing `hs_events_calendar`, or a URL set on the status page; with neither, events are skipped (the 287 imported events never link to a page that isn't live). `u` = that page; a deep link to the event popup can be added when the new calendar is built |
| doc | links found in pages, issues and posts | title (rules below or override), file type, found-on page + section, campus, pinned | Dedupe by URL; respects the Documents manager states and rules (2c); Drive folders found only in weekly updates/posts are skipped as legacy galleries unless Pinned (shown as "Legacy gallery, left out" on the Documents screen) |
| topic | `hs_search_topic`, publish | as the index shape above | Skip a standard card with no button |

**2b. Documents: titles from the surrounding HTML.** A link is a document if it points to `docs.google.com/(document|spreadsheets|presentation|forms)`, `drive.google.com/(drive/folders|file|open)`, or ends in `pdf|docx?|xlsx?|pptx?|zip|csv`. Title, first match wins:
1. `.hs-download-title` inside the link;
2. the link's `title`/`aria-label`, an `img alt` inside it, or a Divi button's text, if ≥ 4 chars and not generic ("click here", "download", "view", "view document", "here", "link", "read more");
3. the link text (same test);
4. the heading or `strong` text of the enclosing card/module (download card, blurb, text, toggle);
5. the nearest preceding `h2`-`h6` in the same section/row, plus the tab or toggle label it sits under;
6. the humanised file name.
Rules 4-6 append the type in brackets, e.g. "Foundation Parent Handbook (Google Doc)". "Found on" = page title › tab/section. **First build step:** the audit columns on the Documents screen (markup around the link, title chosen, rule used), so weak titles can be fixed with a title override or in content before go-live.

**2c. Documents manager (curating which links are searchable).** Weekly updates share many one-off links (sign-up forms, flyers, old notices), so the school needs control. Site search → **Documents** (admin screen, capability `edit_others_posts`):
- Lists every discovered document link: title (as chosen above), type, URL, found on (page or issue › section), date first seen, date last seen, how many places it appears, and its state.
- **States:** *Included* (default for anything new, as the user asked), *Excluded* (never indexed, survives rebuilds), *Pinned* (always included and ranked higher, e.g. handbooks, menus, fee schedules).
- **Title override:** an editable title per document, used instead of the automatic one (fixes weak titles found by the audit).
- **Filters and bulk actions:** filter by source (pages / weekly updates / posts), type, campus, date range and state; bulk Include / Exclude / Pin; a search box.
- **Storage:** a non-autoloaded option `hs_search_docs` keyed by a hash of the normalised URL: `{state, title, first_seen, last_seen}`. The builder merges it in on every build: unknown URLs are added as Included, Excluded ones are dropped from the index, Pinned ones get +40 in ranking.
- **Rules to keep noise down (settings on the same screen, all optional, user decides the defaults at UAT):**
  1. **Age limit for weekly-update documents:** documents found *only* in weekly updates and last seen more than N months ago (setting, default off; suggested 12) are left out unless Pinned. Documents on pages are never aged out.
  2. **Exclude by URL pattern:** a list of patterns (one per line) never indexed, e.g. a sign-up domain.
  3. **Ranking:** documents found on pages rank above documents found only in weekly updates; weekly-update documents fade with age like articles.
- Changes take effect on the next build (the screen triggers one on save).

**Class tokens** from display group and job title: `year N` + `N`; `NX` (e.g. `3b`, from the colour initial or a letter); the colour word; EYFS words (preschool, nursery, reception, foundation, early years); "homeroom" or "class teacher" adds `class teacher`. Coverage check: every person in a class display group must get a token (115 people on local).

**Reading Divi 5 content:** `parse_blocks()`, walk every block's attrs recursively (JSON-decoded strings) and `innerHTML`, load HTML-ish strings into `DOMDocument` (UTF-8, errors suppressed). A failure on one item is caught, logged in `last error`, and the build continues.

**Size guard:** measure raw and gzip size in the first build. Budget 350 KB gzipped. If over: drop issue articles that duplicate a post, then keep article snippets only for the last 12 months (older articles keep the headline only).

### 3. `Site-Scripts/php/search-shortcodes.code-snippets.php` (new): front-end hooks
- **`[hs_search]`**: the utility-bar field. Renders a wrapper `.hs-search` holding a magnifier icon, `<input type="search" class="hs-search-input-field" role="combobox" aria-expanded="false" aria-controls="hs-search-panel" placeholder="Search the site…">`, a clear button and a `/` hint. Without JavaScript it is a normal `<form action="/" method="get">` with `name="s"`. No outer margin or padding.
- **`[hs_search_button]`**: magnifier button for the mobile header; opens the panel as a sheet.
- Output is identical for every visitor (cache-safe), no localised data.
- **Logging endpoint** `wp_ajax(_nopriv)_hs_search_log`: `q` ≤ 60 chars, `n`, `kind` (`commit` | `miss`); lowercase, reject `@`, 6+ digit runs and URLs; store term → {commits, misses, last} in option `hs_search_terms`, capped at 300; rate-limit 30 per 10 min per hashed IP (never stored raw).
- **Index fallback** `wp_ajax(_nopriv)_hs_search_index`: only if `index.json` is missing; builds under the lock, at most once per 5 minutes, and returns the JSON.

### 4. `Site-Scripts/js/site-search.js` (new): engine + UI
Plain ES2017, one IIFE, header in the style of `noticeboard.js`. Two parts:
- **`HSSearchEngine`** (no DOM; `module.exports` under Node for tests): normalise (lowercase, NFKD, strip marks, `&` → and, enrol/enroll and similar variants), tokenise (`Intl.Segmenter` for Thai/Chinese where available, bigram fallback), prefix index (sorted tokens + binary search), query parser (campus words → campus chip; type words → boost: staff/teacher/people → people (but **"staff" or "people" on their own are searched as text**, not "list all staff": they show the Staff directory quick answer and the Staff Directory page, and the People row only for people whose first or last name contains the word, never their job title or profile; UAT 6 Nov), weekly/update/news/newsletter → weekly updates, event(s) → events, pdf/doc/form/handbook → documents; month names, "this week", "next month" etc. → date filter for events and issues; `3b`-style → class), topic phrase matching before stopwords ("how much" → fees), scoring (title exact 100, title prefix 80, title token 50, synonym 50, class 45, keyword 30, subtitle/section 20, snippet/text 10; exact × 1, prefix × 0.7, fuzzy (Damerau-Levenshtein ≤ 1, words of 5+ letters) × 0.4; topic phrase +60; upcoming event +15, past −25; issues/articles −min(age days / 30, 20); same page language +10, other −15). All free words must match; if nothing does, retry with any word and say "No exact matches, showing close results".
- **UI:** finds `.hs-search` (and `.hs-search-trigger` via a delegated listener), builds one panel inside the wrapper, loads the index on first focus/hover/keypress (`fetch(…, {cache: 'no-cache'})`, admin-ajax fallback on 404, small loading line while it loads).
  - Panel: campus switch (Both campuses / Chaofah / Cherngtalay) and the "Understood as" chips (removable); results; footer key hints.
  - Results: quick-answer card first (score ≥ 60), then groups People, Pages, Quick answers, Weekly updates, Events, Documents. Layout A: best 3 per group with "See all N"; layout C: columns as in the design.
  - Row content: person (photo, name, role with class and campus tags; opens the directory); page (title, breadcrumb/description, campus tag); issue (title, week · date · "In this issue: …"); article (headline, article snippet around the first match, meta "Weekly Update #9 · Section · date" + campus tag); event (date block, title, time/location); document (icon + type, title, "Found on: page › section", opens in a new tab).
  - Keyboard: ↑ ↓ move, Enter opens the active row (or the first), Esc clears then closes; `role="listbox"` / `role="option"`, `aria-activedescendant`, `aria-live` count.
  - Empty state: the visitor's last 5 searches (`localStorage` `hs_search_recent`, every access in try/catch) and **six pinned quick-answer cards filling the grid** (3 × 2 on desktop): Contact the school, School fees, How to enrol, **Calendar** (renamed from Term dates), **Staff directory** (`/staff-directory/`) and **Safeguarding** (`/resources-and-information/safeguarding-guidelines/`). No "Try" row (UAT 6 Nov).
  - No results: suggestions, the Contact card, and "Search the whole site" (`/?s=`).
  - `?q=` on any URL (optional `&campus=`) opens the panel pre-filled.
  - Not in the Divi builder (same check as `noticeboard.js`).
- **Analytics:** `dataLayer.push` `hs_search` (term, results, campus) on commit, `hs_search_click` (term, type, position), `hs_search_no_results` after 1.2 s idle; the same commit/miss sent to `hs_search_log` with `navigator.sendBeacon`, same privacy filter client-side.
- `?hs_search_debug=1` logs index load and per-keystroke timings.

### 5. `Site-Scripts/css/site-search.css` (new) and the local loader
- All panel, field, row, tag, card and button styles from the design, scoped under `.hs-search` / `.hs-search-panel` / `.hs-search-trigger`; brand tokens; `prefers-reduced-motion` (no slide); focus states; contrast ≥ 4.5:1; mobile sheet at ≤ 980 px (Divi breakpoint).
- **On live** it becomes a new Custom CSS & JS entry "Site Search" (front end, header), so the shared "Core CSS" that Nick edits is not touched.
- **Local loader** (`app/public/wp-content/mu-plugins/headstart-local-loader.php`, tracked, local only): under the `no-css` option skip only `core.css`, so other `Site-Scripts/css/*.css` files load. One-line change; nothing else in the loader changes.

### 6. `Site-Scripts/js/MANIFEST.tsv`
Add `site-search.js` after `global-utilities.js`, purpose "Site search field, panel, index loader, analytics". Live file name assigned at handover.

### 7. Header placement (Divi, done by the user)
`[hs_search]` in a Code module beside the social icons in the utility bar; `[hs_search_button]` in the mobile header. Done on local for testing, then on live after deploy.

### Explicitly not changed
`staff-directory*`, `events-calendar*`, `mega-menu.js`, `mobile-navigation.js`, the weekly-update snippets, `core.css`. No rewrite rules, so no permalink flush.

### Build order (Gate 3), one commit each via `tools/hs save`
1. `chore(loader): load non-core CSS under no-css` + empty `site-search.css`
2. `feat(search): quick answers CPT, fields, seed, hide-from-search`
3. `feat(search): index builder, status page` → build on local, report counts, size and class coverage
4. `feat(search): documents manager (states, title override, rules, audit)` → report the document audit
5. `feat(search): shortcodes, logging, index fallback`
6. `feat(search): engine + node tests`
7. `feat(search): panel UI, keyboard, layouts A/C` + MANIFEST row
8. `style(search): panel styles from the mock-up`
9. `feat(search): analytics events + admin terms report`

## Risks & rollback
- **Index missing or stale:** admin-ajax fallback, then "Search the whole site". "Rebuild now" and the daily rebuild fix it.
- **Caching:** no index data or version in page HTML; index revalidated on each first load. Purge WP Super Cache and Cloudflare APO after deploy; the user checks Cloudflare doesn't cache `/wp-content/uploads/hs-search/*` (add a bypass rule if it does).
- **Build load on live:** debounced, locked, in WP-Cron; bulk edits trigger one build. Build ms shown on the status page.
- **Index size:** budget and fallbacks above; loaded only on first intent, so pages that never use search pay nothing.
- **Fallback endpoint abuse:** builds only when the file is missing, at most once per 5 minutes.
- **Privacy:** only data already public on the site; no staff emails or blurbs; search logs reject emails, long numbers and URLs and store no IPs; documents only from published, indexable pages.
- **Divi parsing:** per-item try/catch; failures logged, build continues.
- **Header interplay:** opening a mega menu closes the panel; the panel stays inside the utility bar's stacking context.
- **Rollback:** remove the two shortcodes from the Divi header and the `site-search.js` head tag (search disappears, nothing else changes); then deactivate the three snippets and the "Site Search" CSS entry. Quick-answer posts can stay; `uploads/hs-search/` can be deleted.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on all changed PHP and JS.
- **Engine tests** `node work/site-search/tests/run.js`: fixture `work/site-search/tests/fixture-index.json` built from a local index with **all names replaced by fake ones** (no real staff data, emails or blurbs committed); ~30 cases in `cases.json`: fees, how much, enrol/enrollment, contact, a staff name and a one-letter typo, year 3, 3b, class teacher, homeroom, science teacher chaofah (chip + people), events november, weekly update 7, week 9, an article headline word, foundation handbook (doc), chaofah alone, zzzz (zero), "the" (must not return everything); plus a speed check: 50 queries on the real-size fixture under 15 ms each.
- **Endpoint checks on `http://headstart.local`:** `index.json` 200 `application/json` with raw and gzip size reported; `admin-ajax.php` `hs_search_log` 200 and `hs_search_index` 200 valid JSON; `/?s=fees` 200; home, staff directory, an issue and the events page 200 with no new PHP notices.
- **Browser check** (headless Chrome on the Mac, as used for the mock-up): field in the utility bar, typing "fees", "science", "year 3 blue", "contact"; arrow/Enter/Esc; no console errors; screenshots at 1400 px (layout C), 1000 px (layout A) and 390 px (mobile sheet); compare against the mock-up.

### UAT checklist for the user (Gate 5)
- [ ] The search field sits in the utility bar next to the socials and looks like the mock-up (brand blue, 4px corners).
- [ ] Typing opens the panel under the bar; Esc clears then closes; clicking elsewhere closes; `/` and Ctrl/Cmd+K focus the field.
- [ ] Mobile: the magnifier in the mobile header opens the full-width sheet.
- [ ] "fees", "how much", "enrol" and "contact" show the right quick-answer card; phone buttons dial on a phone; Google Maps opens the right campus.
- [ ] A staff name (and a typo of it) finds the person with their square photo, role, class and campus; it opens the staff directory, which is unchanged.
- [ ] "year 3", "3b" and "class teacher" find the class teachers.
- [ ] "science teacher chaofah" shows the Chaofah chip and the right people.
- [ ] "weekly update" lists the newest issues first; a word from inside an article finds that article in the **Articles** group with a preview of its text; posts open their own page.
- [ ] Desktop panel always shows the same three columns and the Documents row, starts at the left edge of the social icons and is not full width.
- [ ] Old Drive photo-gallery folders no longer appear; Google Docs, Sheets, Slides, Forms and PDFs still do.
- [ ] A post (including one not in any weekly update) is findable by its title and opens its own page.
- [ ] Events stay out of search while no calendar page exists; setting the calendar page URL (or publishing one) brings them in after a rebuild. Source switches turn a source off and on.
- [ ] A Google Doc or PDF from a tabbed section is findable by name and shows "Found on: page › section".
- [ ] Ticking "Hide from site search" on a page removes it after a rebuild.
- [ ] Documents screen: a newly linked document appears as Included; Excluding it removes it from search; Pinning moves it up; a title override shows in results; the age limit and URL-pattern rules behave as set.
- [ ] Site search → Index status shows counts, size and the document audit; Rebuild works; quick answers can be edited and published.
- [ ] Feels instant on a mid-range phone.
- [ ] Mega menu, mobile drawer, staff directory, events calendar and weekly updates behave as before.

## Deployment notes for the dev (Gate 7)
- **Code Snippets:** add and activate "Search Topics", "Search Index Builder", "Search Shortcodes" (run everywhere).
- **Custom CSS & JS:** add a new CSS entry "Site Search" (front end, header) with `site-search.css`. Don't touch "Core CSS".
- **JS:** upload `site-search.js` as `uploads/hs-head/<md5>.js` and add its `<script defer>` tag to Divi → Theme Options → Integration → head, after `global-utilities`.
- **No permalink flush.**
- **After activation:** Site search → Index status → Rebuild now and check counts; the school reviews and publishes the quick answers; the user places `[hs_search]` in the utility bar and `[hs_search_button]` in the mobile header.
- **Purge** WP Super Cache and Cloudflare APO; check Cloudflare does not cache `/wp-content/uploads/hs-search/*`.
- **Smoke checks:** `index.json` 200; "fees" shows the card; a staff name, an article word and a document each return a result.
- **Optional:** GA4 event tags in GTM for `hs_search`, `hs_search_click`, `hs_search_no_results`.
