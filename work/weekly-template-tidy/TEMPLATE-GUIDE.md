# Weekly Update template guide

For whoever builds the HSC or HSN weekly update in Divi.

## One-time setup (Jeremy, on live, in this order)
1. **Add the CSS.** Custom CSS & JS → Add CSS code → title "Weekly Article CSS", paste the contents of `weekly-article.css` (in this folder; the same file as `Site-Scripts/css/weekly-article.css`), set it to load on the front end in the header, publish. Without it, headlines lose their gold line and reels show as small boxes.
2. **Clear caches.** Divi → Theme Options → Builder → Advanced → Static CSS File Generation → Clear. Then purge WP Super Cache and Cloudflare APO.
3. **Import the templates.** Divi Library → Import: `templates/HSC-Weekly-Update-Template.json` and `templates/HSN-Weekly-Update-Template.json`. Delete the stray "Weekly Article Text (H3 + body)" preset from the first attempt (it's unused).
4. **Import the layout library.** Import `templates/Weekly-Update-Layout-Library.json` onto a scratch page. For each layout, right-click the layout group (the layer named "Images – …", "Reel – …" or "Reels – …"; for Single image and Gallery, the module itself) → **Save to Library**, and name it "Weekly – Images – 2 across", "Weekly – Reels – 3 across" and so on. Delete the scratch page afterwards.
5. **Clear out the old items.** Divi Library → Import & Export → Export everything first (keep the file as a backup). Then move to Trash: Weekly Update – Images (Single Image, 2xGrid, 3 Cols, 4xGrid, Gallery), Weekly Update – Section Template, HSC Weekly Update Placeholders, HSN Weekly Update Placeholder, Reel (Right), Week 9, Week 9 HSN, Y3-HSN-Literacy-Tree-Gallery. Leave every **global** item alone. Trash keeps them for 30 days.

## How the template is built
```
HSC (or HSN) Weekly Update            ← the section
├── Calendar                          ← fixed, campus calendar
├── Announcements                     ← fixed rows, real headings
├── Upcoming Events
├── Upcoming Meetings & Presentations
├── Meetings & Presentations
├── Section                           ← copy these for any other section
│   ├── Section Heading
│   ├── Article
│   │   ├── Text → Article text (headline + body in one module)
│   │   └── one image layout
│   └── Article
├── Section
├── Success Outside of School         ← one article: text, images, submission form
└── Promotions                        ← images only, never becomes a post
```

## The one rule
**Images, galleries and reels go inside the Article, below the Text.** If they land outside the Article (directly in the column), they won't appear in the post, the lightbox won't open them and the gallery won't lay out. If something looks wrong, check this first.

## Each week
1. **Pick the rows you need.** Delete the fixed rows with nothing this week. For other sections, copy a "Section" row.
2. **Section headings:** in a copied row, type the **exact Website Section name** (for example "Primary", "Secondary", "Foundation", "Alumni Network"). The article-to-post tool reads it to choose the post's section; a typo means no section.
3. **Articles:** copy an Article to add one. In the Article text module the first line is the headline. Keep it as **Heading 3**; it gets the gold line automatically. Then the body as normal paragraphs.
4. **Images:** delete the example layout and add one from the Library (inside the Article, below the Text). Swap the placeholder images for the real ones.
5. **Galleries:** need **at least 12 images** to fill the layout. Remove the numbered placeholders when you add real photos.
6. **Reels (YouTube Shorts):** in each reel code block, change only the video ID in the address: `…/embed/`**`WnWsFXTz5cQ`**`?rel=0&playsinline=1`. The ID is the code at the end of the Short's link.
7. **Buttons (download / form cards):** edit the link and the two text lines in the code block; leave the rest.

## Layouts in the Library
| Layout | Use for |
|---|---|
| Single image | One photo or poster |
| 2 across / 3 across | A pair or three of a kind |
| 2 × 2 | Four small photos |
| 1 + 2 / 1 + 3 / 1 + 4 | One feature photo with smaller ones below (1 + 4 drops to 2 columns on phones) |
| 2 then 3 / 2 + 4 | Two larger photos, then a row of smaller ones |
| Gallery / 1 + gallery | 5 or more photos (12+ fills the gallery) |
| Reel – right column | Text on the left, one reel on the right (goes under the text on phones) |
| Reels – 2 across / 3 across | Reels in a row under the text (3 across stacks on phones) |

## Never
- Upload any photo or video whose file name starts with **DNP** (do not photograph). This includes posting a video to YouTube.
- Use watermarked images.

## After changing the template or CSS on live
Clear Divi's static CSS, purge WP Super Cache and purge Cloudflare APO. Otherwise you may see old styling.
