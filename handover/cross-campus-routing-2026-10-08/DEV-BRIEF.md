# Cross-Campus Template Routing Automation

| File Modified / Deployed | Description |
|--------------------------|-------------|
| `auto-assign-cross-campus-taxonomy.code-snippets.php` | New snippet to automatically tag posts with a "Cross Campus" term when both Chaofah and Cherngtalay are selected. |

### What changed and why
- **Automated Routing for Divi**: Divi Theme Builder cannot apply an "AND" condition for templates when two taxonomy terms are checked (e.g. Chaofah and Cherngtalay). To solve this without breaking the UI, a script was added.
- **The Hook**: When a post is updated, the script (`hs_auto_assign_cross_campus_term`) checks the `hs_post_campus` taxonomy. If it detects *both* the `chaofah` and `cherngtalay` slugs, it automatically appends a third term (`cross-campus`) to the post.
- **Slider Compatibility**: The existing `posts-slider-shortcode.code-snippets.php` logic natively supports this because it outputs "Both Campuses" on the slider tags automatically if a post has `count($campus_terms) > 1`. 

### Action Required (Live Developer / Site Admin)
1. Import `auto-assign-cross-campus-taxonomy.code-snippets.php` into your Code Snippets plugin or `functions.php`.
2. In WordPress Admin, navigate to the **Campus** taxonomy editor and manually add a new term called **"Cross Campus"** with the slug **`cross-campus`**.
3. In Divi > Theme Builder, set the template assignments as follows:
   - **Cross Campus Template:** `Include: Cross Campus`
   - **Chaofah Template:** `Include: Chaofah`, **`Exclude: Cross Campus`**
   - **Cherngtalay Template:** `Include: Cherngtalay`, **`Exclude: Cross Campus`**
