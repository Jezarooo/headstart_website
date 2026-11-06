# Plan: Site search: one instant, predictive search panel for the whole site

- Task: `feat/site-search` · opened 2026-11-06
- Live baseline (Gate 1): `Site-Scripts/live-site-backups-2026-11-05`
- Brief / review doc: Claude Doc "Site Search Plan" (https://claude.ai/artifact/PVJD3J6ohzWvBsyuMoABvx), agreed with the user 5 Nov 2026

> Approval locks this file. Any edit after approval blocks code commits until the user approves again.

## Problem / goal
Visitors can't find things. The only search today is the staff-directory box and the ECA table filter. WordPress's own search skips events and notices. Important documents (Google Docs, Drive folders, PDFs) are scattered across Divi tabs, buttons and code blocks on many pages and weekly updates.

We want one search panel that finds people, class teachers, pages, common questions (fees, enrolment, "how much"), weekly updates, events, news and documents. It must:
- be instant and predictive;
- filter by campus;
- handle combined searches like "science teacher cherngtalay" or "events november" through one simple box.

**Decisions agreed with the user (5 Nov):**
- **No new page.** Everything happens in a panel opened from the header. There is no `/search/` page and no results shortcode. "See all" expands a group inside the panel. WordPress's own `/?s=` stays only as the no-JavaScript fallback.
- **Don't touch the staff directory.** Make no changes to `staff-directory.js`, `staff-directory-*.php` or the staff edit screen. Staff results link to the directory page and show name, role and campus in the result itself.
- **"Classes" means class teachers.** Searching "Year 3", "3B" or "class teacher" finds the teacher(s).
- **Documents and downloads are in v1**, found by scanning content.
- **Search tracking is wanted.**
- **Panel design (mock-up reviewed 6 Nov, `scratch/site-search/mockup/index.html`):**
  - the search field sits **in the utility bar** beside the social icons, and the results **slide out under the bar**, anchored to the field. No modal, no backdrop; Esc clears then closes, and clicking elsewhere closes;
  - layouts **A (grouped list)** and **C (columns)**: A on phones, tablets and narrow desktops, C on wide desktops. B (category rail) is dropped;
  - **brand colours only** (`--hs-dark #003057`, `--hs-gold #f0b323`). The utility bar is already `--hs-dark`, so the panel continues it. The user picks one of three styles in the mock-up (brand blue / blue strip + white / white and gold); brand blue is preferred. Row hover is a solid lighter blue (no yellow tint); the keyboard-active row also gets a 3px gold left edge; rows are separated by subtle 1px lines;
  - the field and buttons follow the site's own components: 1px border, **4px radius** (as `.hs-search-input` and the utility-bar buttons), `--hs-font-body`; no pill shapes;
  - result icons: **staff photo** (the staff post's featured image, 150px thumbnail, the same image the directory uses), a clear icon tile for pages and weekly updates, a square gold date block for events, a file icon with a small type tag for documents;
  - **"News" is merged into "Weekly updates"**: there is no separate News group. Weekly-update issues and the articles in them are listed together. Articles are read from inside each issue (see "Weekly update articles"), so no tagging is needed;
  - the **Contact the school** quick answer is a tight two-column card, one column per campus, built from the real Contact page (`/contact-us/`):
    - **Chaofah City Campus:** 39/99 Moo 4, Vichitsongkram Rd., Soi Na Seau 1, T.Vichit, A.Muang, Phuket 83000; phone +66-76-612875 and +66-88-765-5878; email `info.city@headstartphuket.com`; Google Maps `https://maps.app.goo.gl/h1Bf1KAVkkpF7KuL6`.
    - **Cherngtalay Campus:** 9/2 Srisoonthorn Road, T.Cherngtalay, A.Thalang, Phuket 83110; phone +66-76-510623 and +66-81-678-6466; email `info.north@headstartphuket.com`; Google Maps `https://maps.app.goo.gl/JdCREJnwAFNRrVDq6`.
    - Phone numbers are **buttons** (`tel:` links) on desktop and mobile; Google Maps is an outlined button; the email is a `mailto:` link.
    - A bottom row holds the visit times and the single "Contact page" button. The Contact page only gives **school visit and tour times** (Mon-Fri 9:00-15:00, Saturday mornings by appointment), not general reception hours. **To confirm with the user:** use those, or supply general opening times.
    - The details are typed into the "Contact the school" topic (it is seeded as a draft), so they are editable and are not scraped from the page. The contact page also lists department emails (admissions, office, ECA, fees, portal support, recruiting, language coordinators); they are not in the card, the page link covers them.
  - font colours follow the site: headings and titles `--hs-dark #003057`, body text `#4a5568`, secondary text `#64748b`, borders `#e2e8f0`/`#cbd5e1`; on the blue panel titles are white and secondary text `#a9c2dc`;
  - the active row's gold edge is a straight 3px bar on the left, with the row's right corners rounded;
- **Removed from scope:** Google sitelinks search-box markup, voice input, and a shared engine inside the staff directory.
- **No AI answers.** Quick answers are written by the school.

## Architecture (decision)
A custom client-side index, not a search plugin. The data is small (hundreds to low thousands of items), so the whole index sits in the browser and every keystroke is searched in memory, with no server round trip.

- **Server (PHP snippets):** builds one JSON file, `wp-content/uploads/hs-search/index.json`, from WordPress data. It rebuilds 30 s after relevant edits, writes atomically, and has a "Rebuild now" button.
- **Browser (`site-search.js`):** fetches the index on first intent, builds an in-memory prefix index, parses the query into chips plus free text, scores, groups and renders the panel.
- **Why not Relevanssi, SearchWP, ElasticPress, Algolia or Typesense:** they add a server call per keystroke or a paid third party, and we would still need custom code for staff, events and documents.

## Proposed changes

### 1. `Site-Scripts/php/search-topics.code-snippets.php` (new): Quick answers + "Hide from search"
Follow the header style of existing snippets (see `weekly-update-short-links.code-snippets.php`): a `<?php` line, then a docblock saying what it does and whether a permalink flush is needed (none here).

- **CPT `hs_search_topic`**, label "Quick answers", with these settings:
  - `public => false`, `show_ui => true`, `publicly_queryable => false`, `exclude_from_search => true`, `rewrite => false`, `show_in_rest => false`, `supports => ['title','page-attributes']`.
  - Its own top-level admin menu "Site search" (`dashicons-search`), which also holds the status page (section 2).
- **ACF local field group** (`acf_add_local_field_group`, same pattern as `weekly-update-register-fields`). Keys are prefixed `field_hs_search_`:

  | Field | Type | Notes |
  |---|---|---|
  | `hs_topic_synonyms` | textarea | Comma- or line-separated words and phrases, e.g. `fees, fee, cost, costs, price, tuition, how much, payment` |
  | `hs_topic_link` | url | Default destination (both campuses) |
  | `hs_topic_link_chaofah` | url | Optional campus-specific destination |
  | `hs_topic_link_cherngtalay` | url | Optional campus-specific destination |
  | `hs_topic_answer` | text, maxlength 140 | Optional one-line answer shown on the card |
  | `hs_topic_pinned` | true_false | Shown in the empty state ("Popular") |

  Order comes from `menu_order`.
- **Seed once:** if the option `hs_search_topics_seeded` isn't set and no topics exist, insert 15 **draft** topics with labels and synonyms but no links. Drafts never go into the index, so nothing half-finished reaches visitors.

  | Topic | Synonyms (starter) |
  |---|---|
  | Admissions & enrolment | admissions, admission, enrol, enroll, enrolment, enrollment, apply, application, join, registration, register, places, waiting list |
  | Fees | fees, fee, cost, costs, price, prices, tuition, how much, payment, pay, invoice, discount, sibling discount |
  | Book a tour | tour, visit, open day, open morning, look around, book a visit |
  | Term dates | term dates, calendar, holidays, half term, school year, academic calendar, closures |
  | Uniform | uniform, school uniform, pe kit, shoes, uniform shop |
  | ECAs & clubs | eca, ecas, clubs, after school, activities, extracurricular, extra curricular |
  | Contact | contact, phone, email, address, office, reception, location, map, directions |
  | Careers | jobs, careers, vacancies, work here, recruitment, teaching jobs |
  | Transport | bus, buses, transport, school bus, pick up, drop off |
  | Lunch | lunch, food, canteen, menu, meals, snacks |
  | Scholarships | scholarship, scholarships, bursary, financial aid |
  | Curriculum | curriculum, british curriculum, igcse, a level, a-levels, eyfs, key stage, subjects |
  | Policies | policy, policies, safeguarding, child protection, complaints, privacy |
  | Alumni | alumni, former students, old students, graduates |
  | Shop | shop, yearbook, store, buy |

- **"Hide from search"** ACF true_false `hs_search_exclude` (label "Hide from site search"), shown in the sidebar on **page, post, weekly_update and events only**. Not on staff, per "don't touch the staff directory".

### 2. `Site-Scripts/php/search-index-builder.code-snippets.php` (new): builds the index
**Entry point:** `hs_search_build_index()` returns the array and writes the file.

**File handling**
- Write `wp_upload_dir()['basedir'] . '/hs-search/index.json'` via a `.tmp` file plus `rename()`, so the swap is atomic.
- Store `hs_search_index_meta` (built time, counts per type, bytes, ms taken, last error).
- The URL is fixed and has no version query. This keeps page HTML (cached by WP Super Cache and Cloudflare APO) independent of the index version.

**Index shape** (short keys keep the file small):
```json
{
  "v": 1, "built": "2026-11-05T10:00:00+07:00",
  "campuses": [{"k":"chaofah","l":"Chaofah City","a":["chaofah","chao fah","cfc"]},
               {"k":"cherngtalay","l":"Cherngtalay","a":["cherngtalay","cherng talay","ctl"]}],
  "facets": {"dept": ["Science","Maths", "..."], "ecat": ["Sports","Secondary", "..."]},
  "items": [
    {"id":"p123","t":"page","ti":"Admissions","u":"/admissions/","c":["chaofah","cherngtalay"],"lg":"en",
     "s":"Apply for a place at HeadStart…","x":"headings and tab titles text…","k":["admissions"]},
    {"id":"s45","t":"person","ti":"Jane Doe","s":"Year 3 Class Teacher","u":"/about/staff-directory/",
     "c":["cherngtalay"],"k":["Primary"],"cl":["year 3","3"],"img":"…-150x150.jpg"},
    {"id":"e88","t":"event","ti":"Sports Day","s":"Whole-school sports day…","u":"/events/?hs_event=sports-day&d=2026-11-20",
     "c":["chaofah"],"d":"2026-11-20","de":"2026-11-20","k":["Sports"],"loc":"Field"},
    {"id":"w301","t":"update","ti":"Weekly Update – Week 12","s":"blurb…","u":"/cherngtalay/weekly-update/2026/11/…/",
     "c":["cherngtalay"],"d":"2026-11-03","wk":12,"k":["Sports Day","Library"],"ar":0},
    {"id":"n77","t":"news","ti":"…","s":"…","u":"…","c":["chaofah"],"d":"2026-10-30","k":["Science"]},
    {"id":"d9","t":"doc","ti":"Chaofah City Campus - Foundation Parent Handbook","ft":"gdoc",
     "u":"https://docs.google.com/document/d/…","on":{"ti":"Foundation","u":"/chaofah/foundation/"},"c":["chaofah"],"k":["Downloads"]},
    {"id":"q3","t":"topic","ti":"Fees","syn":["fees","cost","how much"], "a":"Fees for 2026–27 by year group.",
     "u":"/admissions/fees/","uc":{"chaofah":"/chaofah/fees/","cherngtalay":"/cherngtalay/fees/"},"pin":1}
  ]
}
```
An item with no campus means both campuses.

**Campus normalisation (verify the real slugs on local before coding)**
- `hs_campus` term slugs are `chaofah`, `cherngtalay` and `both-campuses` (seen in `weekly-update-short-links`). `both-campuses` becomes `[]`.
- Events `target_campus` meta: `chaofah`, `cherngtalay`, `both`.
- Posts: `hs_post_campus` terms.
- Staff: `staff_campus` term names ("Both Campuses" becomes `[]`).
- Pages: the first path segment is `/chaofah/` or `/cherngtalay/`.

**Sources** (every source skips non-published items and items with `hs_search_exclude = 1`):

| Type | Query | Fields | Rules |
|---|---|---|---|
| page | `page`, publish, no password | `ti` = title; `s` = Yoast meta description (`_yoast_wpseo_metadesc`), else the first 200 chars of text; `x` = H1–H3 text + Divi tab titles, capped at 600 chars; `lg` = ACF `page_language` (default `en`) | Skip: Yoast noindex (`_yoast_wpseo_meta-robots-noindex` = `1`); the campus parent pages that `pages-campus-routing` redirects; the language placeholder parents (`get_page_by_path($lang)` for th/ru/zh); pages whose slug contains `thank-you` or `thanks` |
| person | `staff`, publish, `posts_per_page => -1` | name; `job_title`; `staff_department` and `staff_display_group` names → `k`; campuses; thumbnail (`thumbnail` size) → `img`; `cl` = class tokens (below) | **One item per person**: don't reuse the transient `hs_staff_directory_data`, which clones people per campus × department. No email and no `profile_blurb`: keep the index lean and only show fields the directory already shows on cards. `u` = permalink of the first published page whose content contains `[hs_staff_directory` (found at build time) |
| event (low priority in v1) | `events`, publish, `event_end_date` (or `event_start_date`) ≥ today − 60 days | title, `event_blurb`, `event_category` label → `k`, `event_location`, `event_start_date`/`event_end_date` → `d`/`de`, `target_campus`, times | `u` = the events-calendar page (content contains `[hs_events_calendar`). If several pages carry different `campus=` attributes, pick the one matching the event's campus. Add `?hs_event=<sanitize_title(title)>&d=<Y-m-d>` (see section 4, deep link). Events are `publicly_queryable => false`, so no single URL exists |
| update (issue) | `weekly_update`, publish | title (e.g. "Weekly Update #9"), ACF `blurb`, `in_this_issue` term names → `k` (these are the article topics of the issue), `hs_week_number` → `wk`, `hs_campus`, date, archive flag (`hs_weekly_archive_status` term) → `ar` | `u` = `get_permalink()` (the phantom-link injector already gives the pretty URL). Check what `hs_legacy_archive` does before deciding whether those items link elsewhere. Rank newest first when the query is about issues ("weekly update", "week 7") |
| update (article) | **Parsed from each issue's own content** (below), plus `post`, publish | article headline, section label, first ~160 chars of body, issue id → `p`, date | The articles are not tagged individually, but they are structured: see "Weekly update articles". A `post` with the same normalised headline as an issue article replaces that article's entry and gives the real page `u`. A `post` with no match is listed as its own article |
| doc | Scan the content of every indexed page, weekly_update and post (below) | link title, file type, the page it was found on, that page's campus | Dedupe by URL: keep the first page as `on`, and add a count if found on more |
| topic | `hs_search_topic`, **publish only** | title, synonyms (split, trimmed, lowercased, deduped), links, answer, pinned | Skip topics with no link at all |

**Reading Divi 5 content.** Content is block markup with JSON attributes. Example from `Latest-Drop/Foundation.json`: Code modules hold HTML such as `<a href="https://docs.google.com/…" class="hs-download-card">…<span class="hs-download-title">…</span>`, and buttons hold `linkUrl`.
- Use `parse_blocks( $post->post_content )` and walk every block's `attrs` recursively, collecting string values (they arrive JSON-decoded) plus `innerHTML`.
- Load each HTML-ish string into `DOMDocument` (wrapped as UTF-8, errors suppressed) to get text, headings and anchors.
- Divi tab titles: from `divi/tab` blocks (verify the block name and attribute path on a real page export; dump one with WP-CLI or a temporary debug endpoint on local, never on live).
- Plain text for `s`/`x`: strip tags, collapse whitespace, and drop shortcode tags (`strip_shortcodes`) and script/style content.

**Events.** The events listing is barely used today, and an update with full event blurbs is coming. Search indexes whatever exists, using `event_blurb` when present, and needs no change when the new events land (same post type and meta keys). If the events source has no upcoming items, the Events group simply doesn't appear. Don't build anything events-specific beyond what the table above already says.

**Weekly update articles.** In an issue, each article is a Divi Group whose class is `hs-weekly-article` (the same class `weekly-update-single.js` and `gallery-mosaic.js` already use). Inside it: the headline is the first `h3` (a text module with the admin label "Heading"), the body is the text modules after it, and the section is the nearest preceding `h2.hs-section` ("Upcoming events", "Upcoming meetings & presentations", a themed heading such as "Halloween Celebrations!") or the Divi section's admin label (Foundation, Primary, Secondary, Academy News). Checked on Weekly Update #9 (Cherngtalay): 16 articles, all with an `h3` headline. So no tagging is needed:
- Parse with `parse_blocks()`, walk to groups carrying the class `hs-weekly-article`, and take headline, section and a short body snippet. Skip an article with no `h3`.
- **Deep link:** opening the issue is enough for v1 (decision 6 Nov): the user will add anchor tags to articles later. Until then the articles have no anchors and we can't edit the weekly-update snippets. The result links to the issue (or the matching `post`) with `#a=<slug of the headline>`. `site-search.js` runs on every page: if it finds `#a=` it looks for the `.hs-weekly-article` whose first `h3` slugifies to the same value, scrolls to it and highlights it briefly. If none is found it just opens the page.
- **Size guard:** about 170 issues × 15 articles is roughly 2,500 article entries. Store only headline, section, a 160-character snippet and the ids. Measure the gzipped `index.json` in build step 2. If it is too big, keep article entries for the last 12 months and index older issues at issue level only.
- **Display:** an article row shows its headline, with "Weekly Update #9 · Section · date" underneath. An issue row shows "Weekly Update #9", the week and date, and "In this issue: …" from the `in_this_issue` terms. Both sit in one **Weekly updates** group. There is no News group.

**Document links.** A link counts as a document if its href matches any of these:
- `docs.google.com/(document|spreadsheets|presentation|forms)/`
- `drive.google.com/(drive/folders|file|open)`
- `/wp-content/uploads/…` with extension `pdf|docx?|xlsx?|pptx?|zip|csv`
- any URL ending in those extensions

`ft` is one of `gdoc|gsheet|gslides|gform|folder|pdf|word|excel|ppt|zip|file`.

**Documents carry no description of their own**, so the title and the "Found on" context come from the HTML around the link. First step of the index builder work: an **audit pass** on local that lists every document link with its surrounding markup and the title each rule would give, and reports how many fall through to the weak rules. Then choose the title in this order:
1. `.hs-download-title` inside the anchor (the site's own download-card pattern);
2. the anchor's `title` or `aria-label`, an `img` `alt` inside it, or a Divi button's text attribute, if it is at least 4 characters and isn't generic ("click here", "download", "view", "view document", "here", "link", "read more");
3. the anchor's own text, with the same generic test;
4. the **enclosing card or module text**: the closest ancestor that holds a heading, `strong` or title-class element (download card, Divi blurb, text or toggle module) and that heading/strong text;
5. the **nearest preceding heading** (`h2`-`h6`) inside the same Divi section/row, plus the **tab or accordion label** the link sits under (Divi tabs/toggles: map the panel to its tab title);
6. the file name, humanised (drop the extension, replace `-`/`_`, drop trailing date/version tokens).
If the title came from rules 4-6, append the file type label in brackets, e.g. "Foundation Parent Handbook (Google Doc)".

**"Found on" context** is the page title plus the tab or section label from rule 5, e.g. "Foundation › Downloads". Keep `on.ti` (page) and `on.sec` (tab/section) separately so the UI can show both.

**Class teacher tokens (`cl`).** The class is already stored on each staff member as their **display group** (taxonomy `staff_display_group`: "Year 3 (Blue)", "Year 3 (White)", "Reception (Yellow)", "Preschool (Blue)", "Early Years (Yellow)" and so on). The job title often repeats it ("Year 3 (Blue) Homeroom Teacher"), but not always. So the tokens come from **both** the display group term names and `job_title`. No staff field is added or changed.
- `/\b(?:year|yr|y)\s?(\d{1,2})\b/i` gives `year N` plus `N`;
- `/\b(\d{1,2})\s?([A-Z])\b/` gives `NX`, e.g. `3B`;
- the colour in brackets, e.g. `blue`, `white`, `yellow`, becomes a token too, so "year 3 blue" works;
- EYFS words: `pre-nursery`, `nursery`, `preschool`, `reception`, `foundation`, `kindergarten`, `early years`;
- if the title or the group contains "homeroom" or "class teacher", add the keyword `class teacher`. Local titles use **"Homeroom Teacher"**, not "Class Teacher", so "homeroom" must map to it.

Local check on 6 Nov: 115 of 342 staff sit in a Year / Reception / Preschool / Early Years display group, and 13 of those have a title without the class, so the display group is needed. Year 3 (Blue), for example, holds two Homeroom Teachers and two Deputy Teachers.

First task in Build: report class-teacher coverage again from the built index. Expected: every person in a class display group has a `cl` token. If anyone is missed, report it. No staff field is needed.

## 3. `Site-Scripts/php/search-shortcodes.code-snippets.php` (new): front-end hooks
- **`[hs_search_button]`** renders `<button type="button" class="hs-search-trigger" aria-label="Search the site" aria-haspopup="dialog"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>`. Font Awesome is already on the site; `staff-directory.js` uses `fa-search`. It has no outer margin or padding (user preference: spacing is set in Divi).
- **`[hs_search placeholder="Search HeadStart…"]`** renders an inline bar: a `<button>` styled like an input that opens the same panel. It also has no outer margin or padding.
- **Shortcode output is identical for every visitor**, so page caching stays safe.
- **Search-terms logging (no personal data)**, via `wp_ajax_hs_search_log` and `wp_ajax_nopriv_hs_search_log` (admin-ajax POST, so APO doesn't cache it):
  - Accept `q` (≤ 60 chars), `n` (result count, int) and `kind` (`commit` | `miss`).
  - Normalise to lowercase and collapse spaces.
  - **Reject** anything containing `@`, any run of ≥ 6 digits, or a URL.
  - Keep an option `hs_search_terms`: term → {commits, misses, last}. Cap it at 300 terms by dropping the oldest `last`. Rate-limit with a transient per hashed IP (`md5(ip + wp_salt())`, never stored raw), at most 30 per 10 min.
  - The status page shows "Top searches" and "Searches with no results" (top 50 each), plus a "Clear" button.
- **Index fallback** `wp_ajax(_nopriv)_hs_search_index`: if `index.json` is missing, build it (with the lock) and return the JSON. The front end calls this only after a 404 on the file.
- **`site-search.js` needs no localised data** (cache-safe). It uses the fixed URLs `/wp-content/uploads/hs-search/index.json` and `/wp-admin/admin-ajax.php`.

### 4. `Site-Scripts/js/site-search.js` (new): engine + UI
Header comment in the style of `noticeboard.js`. Plain ES2017, no libraries, one IIFE.

Split into two parts so the engine can be unit-tested in Node:
- `HSSearchEngine`: pure functions, no DOM.
- `if (typeof module !== 'undefined') module.exports = HSSearchEngine;`
- The UI part runs only when `typeof document !== 'undefined'`, and not in the Divi builder. Use the same check as `noticeboard.js`: `et_fb=1`, `et_pb_preview=true`, or framed.

**Loading**
- Do nothing until intent: the first `pointerenter`, `focus` or `click` on `.hs-search-trigger`, a press of `/`, Ctrl/Cmd+K, or a `?q=` in the URL.
- Then `fetch(INDEX_URL, {cache: 'no-cache'})`, which revalidates and gets a 304 when unchanged. On 404, fall back to the admin-ajax index.
- Build the in-memory index once per page view. Show a small spinner if the index isn't ready when the panel opens.

**Normalising and tokenising**
- Lowercase, NFKD, strip combining marks, and turn `&` into "and".
- Map spelling variants: enrol/enroll, enrolment/enrollment, programme/program, colour/color, centre/center, organise/organize.
- Stopwords, dropped for item matching but kept for topic phrase matching: how, much, what, is, the, a, an, do, does, i, my, for, of, to, in, at, and, are, when, where, can, you, me, about.
- Thai, Chinese and other runs without spaces: use `Intl.Segmenter(lang, {granularity: 'word'})` when available; otherwise use character bigrams for those runs.

**In-memory index**
- For each item and field (title, synonyms, subtitle and keywords, class tokens, text), tokenise and store token → [itemIdx, fieldWeight].
- Keep a sorted array of unique tokens for prefix lookup by binary search.

**Query parser (chips)**
- Scan the query for recognised words and phrases (longest match first). Each becomes a chip and is removed from the free text:
  - **Campus:** `campuses[].a`.
  - **Type:** people, staff, teacher(s), person → `person`; event(s), calendar, what's on → `event`; weekly, update(s), newsletter, bulletin → `update`; news, story, stories → `update`; doc(s), document(s), pdf, form(s), handbook → `doc`; page(s) → `page`.
  - "Teacher" is both a type hint and a text term, so it boosts people without filtering out "class teacher" topics.
- Departments: any `facets.dept` value; it filters people and news.
- Time, resolved against the visitor's local date:
  - today, tomorrow, this week, next week, this month, next month, last week;
  - month names (and 3-letter forms) with an optional year, taking the nearest such month that isn't more than 2 months in the past;
  - `week N` maps to `wk` for updates.
  - Time chips filter events (by date overlap) and updates (by publish date). They also imply type event or update if no type chip is set.
- Topic phrases: before stopwords are removed, test the normalised full query against every topic synonym. A substring or prefix match on whole words marks the topic as a candidate. This is how "how much is it" finds Fees.

**Matching and scoring**
- Each remaining free-text token must match each candidate item (AND), through exact token, prefix (last token only, or any token of 3+ chars), or a fuzzy match. Fuzzy means Damerau–Levenshtein ≤ 1, only for tokens of 5+ chars, only against index tokens with the same first letter and a length within ±1.
- Field weights:

  | Match | Weight |
  |---|---|
  | Title exact | 100 |
  | Title starts with query | 80 |
  | Title token | 50 |
  | Synonym | 50 |
  | Class token | 45 |
  | Keyword / department / category | 30 |
  | Subtitle | 20 |
  | Text | 10 |

  Exact beats prefix beats fuzzy, as ×1.0, ×0.7 and ×0.4 of the weight.
- Boosts:
  - topic synonym phrase hit: +60;
  - same language as `<html lang>`: +10; other language: −15;
  - events: upcoming or ongoing +15, past −25;
  - updates and news: −min(ageDays / 30, 20);
  - archived update: −10.
- Chips are hard filters. An item with no campus is in both campuses.
- If there are zero results with AND: retry with OR over the tokens, and show "No exact matches – showing close results". If still none: the empty-help state.

**Grouping:** a topic card first (best topic if score ≥ 60), then groups People, Pages, Documents, Events, Weekly updates, ordered by each group's best score. Show 3 items per group; "See all N" expands that group in place, with a "‹ All results" back control.

**Panel UI**
- Built once and appended to `<body>`. It's a `role="dialog" aria-modal="true" aria-label="Site search"`.
- Contents:
  - input (`role="combobox" aria-expanded aria-controls aria-activedescendant`) with ghost-text completion (Tab or → accepts) and a clear (×) button;
  - a chips row, where each chip has a remove ×;
  - scope pills: All · People · Pages · Documents · Events · Updates;
  - results (`role="listbox"`, each row `role="option"`);
  - an `aria-live="polite"` count ("12 results").
- Desktop: a centred panel 720 px wide, top 10vh, with a backdrop. At ≤ 980 px (Divi breakpoint): a full-screen sheet, input pinned top, safe-area insets.
- Keyboard: `/` (when not typing in a field) or Ctrl/Cmd+K opens; ↑ ↓ move; Enter opens the highlighted row, or the top result if none is highlighted; Esc clears chips, then closes. Focus is trapped while open and returns to the trigger on close. Body scroll is locked while open.
- Empty state: "Popular" (pinned topics) and "Recent" (the last 5 committed queries in `localStorage` under `hs_search_recent`, every access in try/catch), with "Clear recent".
- Result rows:
  - **person:** 40 px round photo, name, role, campus chip(s). Opens the staff directory page; the directory itself is unchanged.
  - **event:** date badge, title, time, location, campus chip, category.
  - **update:** week number + date, title, blurb snippet, tags.
  - **doc:** file-type icon, title, "Found on: page title" (as a link), opens in a new tab.
  - **page / news:** title, snippet, campus chip.
  - Matched terms are highlighted with `<mark>`. All text from the index is HTML-escaped before rendering.
- Campus chip colours follow the admin palette: Chaofah `#e5f0fa` / `#00438a`, Cherngtalay `#fdf2e8` / `#a65d14`, Both `#e5f0e9` / `#004d22`.
- No-results help: "Nothing found for '…'", top 4 pinned topics, and the Contact topic link. Offer "Search the whole site" pointing at `/?s=<q>` as a last resort.
- `?q=` on any URL (plus optional `&campus=` and `&type=`) opens the panel pre-filled on load. It doesn't rewrite the URL as the visitor types.

**Event deep link** (instead of editing `events-calendar.js`)
- On load, if the URL has `hs_event` and `d`: wait for `.hs-card-v1` cards, then find the one whose decoded `data-title` matches `hs_event` by slug and whose `data-iso-date` starts with `d`.
- Scroll to it and call `card.click()`. The existing handler in `events-calendar.js` (line ~144) opens the popup.
- Retry for up to 3 s (cards render after DOMContentLoaded), then give up silently.
- Verify the format of `data-iso-date` on local first.

**Analytics.** The site already pushes to Google Tag Manager's `window.dataLayer` (see `weekly-update-short-links`, `hs_gallery_open`), so use the same channel:
- `dataLayer.push({event: 'hs_search', search_term, hs_results: n, hs_scope})` on commit (Enter, or a result click after 1+ s idle);
- `{event: 'hs_search_click', search_term, hs_result_type, hs_result_position}` on result click;
- `{event: 'hs_search_no_results', search_term}` once per query after 1.2 s idle with 0 results.

Also POST the same `commit` and `miss` events to `hs_search_log` with `navigator.sendBeacon`, so the admin report works even without GA4. Apply the same privacy filter client-side (no `@`, no 6+ digit runs). The user needs to add GTM triggers or tags for these events in GA4; note this in DEV-NOTES.

**Debug:** `?hs_search_debug=1` logs index load ms, item count, and per-keystroke search+render ms to the console.

**Size:** under 25 KB minified-equivalent source. It loads with the other head scripts (`defer` if Nick's head setup allows).

### 5. `Site-Scripts/js/MANIFEST.tsv`
Add `site-search.js` after `global-utilities.js`, with purpose "Site search panel, index loader, chips, analytics". The live file name is assigned at handover.

### 6. `Site-Scripts/css/core.css` (live, shared with Nick)
A scoped `/* Site search */` block, appended at the end:
- `.hs-search-trigger`, `.hs-search-inline`, `.hs-search-backdrop`, `.hs-search-panel`, and the rows, chips, pills, ghost text and focus rings;
- brand tokens `--hs-dark` and `--hs-gold`, fonts `--hs-font-head` and `--hs-font-body`;
- z-index above the mega menu and mobile drawer: the highest existing is `9999 !important` at line ~942, so use `100000`;
- `prefers-reduced-motion`: no slide animation;
- visible focus states; text contrast at least 4.5:1;
- no outer margin or padding on the trigger and inline bar.

### 7. Header placement (Divi, done by the user, not code)
**Decided 6 Nov:** the search icon goes in the **top utility bar, next to the social links** (Facebook, Instagram, YouTube), added through the `[hs_search_button]` shortcode in a Divi Code module placed beside the socials.
- Style requirement (step 6): in the utility bar the trigger must look like a sibling of the social icons: white magnifier on the dark bar, roughly the same size (about 22-24px), the same hover as the social icons, no outer margin or padding (spacing is set in Divi), and a tap target of at least 44px on touch through padding inside the button only. The panel itself opens above everything (z-index 100000), so the utility bar's stacking context does not clip it.
- The utility bar is hidden on small screens in the Divi header, so the mobile drawer needs its own `[hs_search_button]` somewhere the user chooses (a Code module in the mobile bar or the drawer). Still to be decided; it does not block the build.
- Don't edit `mega-menu.js` or `mobile-navigation.js`. If the mobile drawer moves or clones nodes ("form teleport") and breaks the button, the trigger uses a delegated listener on `document` for `.hs-search-trigger`, so clones still work.

### Explicitly not changed
- `staff-directory.js` and all `staff-directory-*.php`
- `events-calendar.js` and `events-calendar-*.php`
- `mega-menu.js` and `mobile-navigation.js`
- the weekly update files
No rewrite rules, so no permalink flush.

## Risks & rollback
- **Index missing or stale:** the panel falls back to the admin-ajax build. If that fails, it shows "Search the whole site" (`/?s=`). "Rebuild now" fixes it. A daily rebuild catches missed hooks.
- **Caching:** page HTML never carries index data or versions, and the index URL is revalidated, so WP Super Cache and Cloudflare APO can't serve a mismatched page. Purge both after deploy anyway. Have the user check whether Cloudflare caches `.json` on live. If it does, add a Cache Rule to bypass `/wp-content/uploads/hs-search/*`.
- **Build load on live:** the build is debounced and locked, and runs in WP-Cron. Bulk edits (CSV import) cause one build, not hundreds. Watch the build ms on the status page.
- **Privacy:**
  - No staff emails or blurbs in the index; only data already shown publicly.
  - Search logging rejects emails, long numbers and URLs, and stores no IPs.
  - Document links are only those already public on published pages. Links on noindex, password-protected or hidden pages are not collected.
- **Divi content parsing:** Divi 5 attribute paths may differ from what we expect. A failure in one page is caught and logged in `last error`, and the build continues.
- **Thai and other languages:** `Intl.Segmenter` is missing on older browsers, which use the bigram fallback (slightly noisier results).
- **Shared file:** `core.css` is edited by Nick on live, so Gate 1 drift must be resolved first. The block is appended and scoped, so it doesn't interact with existing rules.
- **Rollback:**
  - Remove the `site-search.js` script tag from the Divi head and the button modules from the header. Search disappears and nothing else changes.
  - Then deactivate the three snippets and remove the CSS block.
  - Quick answers posts stay in the DB, harmless. `uploads/hs-search/` can be deleted.

## Testing strategy
### Automated (Gate 4)
- `tools/hs lint` on all changed PHP and JS. In the cloud: stage the PHP, run `php -l` in the container, then `tools/hs receipt`.
- **Engine unit tests:** `work/site-search/tests/run.js` (Node, no dependencies), run with `node work/site-search/tests/run.js`.
  - It loads `site-search.js` via `require` (engine export) and a fixture `work/site-search/tests/fixture-index.json`, copied from a local build with names replaced by fake ones before committing. Never commit real staff data.
  - It runs `cases.json`, about 30 cases, each of the form `{q, expectTopType, expectTopTitleIncludes | expectChips | expectZero}`:
    - "fees", "how much", "cost", "enrol", "enrollment", "admissions";
    - a person's name and a one-letter typo of it;
    - "year 3", "3b", "class teacher";
    - "cherngtalay events" (chips campus + type), "events november" (type + month), "science cherngtalay" (dept + campus);
    - "week 12", "foundation handbook" (doc), "parent handbook", "uniform", "term dates", "bus", "lunch menu", "chaofah";
    - a Thai query if Thai pages exist;
    - "zzzz" (zero);
    - "the", which must not dump everything.
  - It also checks that a 2,000-item synthetic index searches 50 random queries at under 15 ms each in Node.
- **Endpoint checks on `http://headstart.local`** (report status codes):
  - `GET /wp-content/uploads/hs-search/index.json` → 200, `application/json`, under the size budget (raw and gzip measured with `gzip -c | wc -c`);
  - `POST /wp-admin/admin-ajax.php action=hs_search_log` → 200; `action=hs_search_index` → 200 valid JSON;
  - `GET /?s=fees` → 200;
  - the home page, the events page and the staff directory page → 200, no PHP notices in `debug.log`.
- **Browser check** (built-in browser or Playwright on the Mac):
  - opening the panel, typing "fees", arrow and Enter navigation, and Esc;
  - no console errors;
  - `?hs_search_debug=1` shows p95 search+render under 50 ms at 4× CPU throttle;
  - an event deep link opens the right popup;
  - mobile 390 px full-screen sheet screenshots.

### UAT checklist for the user (Gate 5)
- [ ] The search icon opens the panel on desktop and mobile; Esc closes it; `/` and Ctrl/Cmd+K open it.
- [ ] Empty panel shows Popular topics and your recent searches.
- [ ] "fees", "how much" and "enrol" each show the right Quick answer card, and the campus-specific link when a campus chip is set.
- [ ] A staff name, and a typo of it, finds the person, showing photo, role and campus; clicking goes to the staff directory, which is unchanged.
- [ ] "Year 3" or "class teacher" finds the right teacher(s).
- [ ] "cherngtalay events" shows two chips and only Cherngtalay events; removing a chip widens the results.
- [ ] "events november" shows November events; clicking one opens its popup on the calendar.
- [ ] A weekly update is findable by title, by week number and by a tag.
- [ ] A Google Doc or PDF from a tabbed section (e.g. the Foundation Parent Handbook) is findable by name and shows "Found on: …".
- [ ] "See all" expands a group inside the panel; no new page opens.
- [ ] Ticking "Hide from site search" on a page removes it after a rebuild.
- [ ] Site search → Index status shows counts and Rebuild works; Quick answers can be edited and published.
- [ ] Feels instant on a mid-range phone.
- [ ] The rest of the site (mega menu, mobile drawer, staff directory, events calendar) behaves as before.

## Deployment notes for the dev (Gate 7)
- **Activate** the new Code Snippets "Search Topics", "Search Index Builder" and "Search Shortcodes" (run everywhere).
- **Upload** `site-search.js` as `uploads/hs-head/<md5>.js` and add its `<script>` tag to Divi → Theme Options → Integration → head, **after** `global-utilities`.
- **Replace** the live `core.css`. The new block is at the end, `/* Site search */`.
- **No permalink flush** (no rewrite rules).
- **After activation:**
  1. Site search → Index status → Rebuild now; check the counts.
  2. The school fills in the Quick answers links and publishes them.
  3. The user places `[hs_search_button]` in the Divi header (desktop + mobile).
- **Purge** WP Super Cache and Cloudflare APO.
- **Smoke checks:**
  - `index.json` returns 200;
  - searching "fees" shows the topic;
  - a staff name, an event and a document each return a result.
- **Optional:** in GTM, add GA4 event tags for `hs_search`, `hs_search_click` and `hs_search_no_results`.
