# Alumni stories — how to add and edit stories (Divi 5)

The Alumni stories block on the alumni page is built from ordinary Divi Groups. You never touch code: the site turns the Groups into the slider, blurb and story cards automatically.

## How it is put together

```
Section  "Alumni Stories"
 └ Group "Alumni stories"                ← the wrapper (class hs-alumni-stories)
    ├ Group "Story — Nick"               ← one Group per story (class hs-alumni-story)
    │   ├ Gallery   "Slides"             the Instagram graphics, in order (4:5, 1080×1350)
    │   ├ Image     "Card photo"         a plain photo of the person, no graphics
    │   └ Text      "Story text"         H3 name · meta line · blurb · Instagram link
    ├ Group "Story — …"
```

- **The order of the story Groups is the order of the cards.** The first story shows when the page loads.
- In the builder the canvas shows the **finished layout**, the same slider, blurb and cards as the live page (it refreshes a moment after each edit, and stays on the story you are working on). The story Groups themselves are tucked away: **edit them in the Layers panel**. Select a Group, Gallery, Image or Text module there and change it in the settings panel. Switching between stories works in the canvas too. If the preview ever can't be built, the plain story Groups appear instead and can be edited directly.

## Add a new story (about 3 minutes)

1. **Prepare the images.** Upload the story's Instagram slides (4:5, e.g. 1080×1350) and one plain photo of the person to the Media Library.
2. **Add a story Group.** Open the alumni page in the Visual Builder. In the "Alumni stories" Group, either:
   - right-click an existing story Group → **Duplicate**; or
   - click **+** inside the wrapper → **Add From Library** → **"Alumni story (template)"**.
3. **Rename it.** Rename the Group in the Layers panel to `Story — [Name]`, so it's easy to find.
4. **Slides.** Open the **Gallery** → **Images** → select the slides **in order** (the order you select is the order shown). Leave the gallery's "Order" setting on default (not Random).
5. **Card photo.** Open the **Image** → choose the plain photo.
6. **Text.** Open the **Text** module and keep this shape:
   - **Heading 3:** the name, e.g. `Nick`
   - **First paragraph:** the meta line, e.g. `Class of 2022 · History & International Relations, Lancaster University`. The card shows the part before the `·`.
   - **Next paragraphs:** the overview blurb, usually the first one or two paragraphs of the Instagram caption.
   - **Last paragraph:** a link to the Instagram post (optional). Its text is replaced with "View the original post on Instagram".
7. **Save, and check the live page.** The new card appears in the strip; the strip grows by itself as stories are added.

## Edit, reorder or remove

- **Edit:** change the Gallery, Image or Text inside that story's Group.
- **Reorder:** drag story Groups up or down in the Layers panel.
- **Remove:** delete the story Group. No other changes needed.

## Rules that keep it working

- Keep the story modules **inside** their story Group, and story Groups **inside** the wrapper Group.
- Don't remove the CSS classes in each module's **Advanced → Attributes** (`hs-alumni-stories`, `hs-alumni-story`, `hs-story-slides`, `hs-story-card`, `hs-story-text`). Duplicating or adding from the library keeps them.
- Slides should all be the same 4:5 shape. Other shapes are cropped to 4:5.
- Don't add the gallery classes used elsewhere on the site (`hs-gallery-container` …) to story galleries.

## First-time setup (once per site)

1. Divi → **Divi Library** → **Import & Export** → **Import** → `divi-alumni-stories.json`. This adds "Alumni stories (section)" and "Alumni story (template)".
2. On the alumni page: **+ Add section → Add From Library → "Alumni stories (section)"**, placed where you want it.
3. In Nick's story: pick his 10 slides in the Gallery and his photo in the Image (Media IDs differ per site, so images are never inside the import file).
4. After going live: purge WP Super Cache and Cloudflare.

## The "Your story next?" tile
- It's the Text module labelled **"Your story next?" tile** at the end of the wrapper Group (after the last story).
- **H3** = the tile heading; **first paragraph** = the line under it; **the link** = where the tile goes (the link text itself isn't shown).
- On phones only the first sentence of the line is shown, so keep the first sentence short.
- Delete the module to remove the tile.

## Card photos
- The Image module in each story is optional. Leave it blank and the card uses slide 1; add a plain photo to use that instead.
- With 4 stories or fewer, the cards show as a 2×2 grid of the other stories plus the tile; with more, they become a sliding strip automatically.
