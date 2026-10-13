# Staff Directory CSV Importer Updates

| File Modified / Deployed | Description |
|--------------------------|-------------|
| `staff-directory-csv-importer.code-snippets.php` | Implemented full XLS export, an emergency revert feature, and removed deprecated image sync logic. |

### What changed and why
- **UI & Text Updates**: Changed the WP admin page and menu title to "Import/Export". Updated the text for the "DANGER" database wipe checkbox to accurately describe restoring amended directories.
- **Removed Deprecated Code**: Stripped out the "Missing Images?" retroactive image sync feature entirely (PHP and JS).
- **Template Updates**: Added the `Photo Filename` column to the "Download Empty XLS Template" button so it maps completely.
- **Full Directory Export**: Built a "Download Full Directory (XLS)" button. This runs a custom AJAX endpoint to fetch all staff metadata and custom taxonomy terms, and uses `ExcelJS` to populate a ready-to-use XLS file that includes full Data Validation dropdowns.
- **Emergency Revert Feature**: Built a fail-safe backup feature. When a database wipe is initiated, it now automatically snapshots the entire directory into `wp_options` as JSON. A new "Emergency Revert" UI panel allows the user to click one button, which fetches the backup, securely wipes the broken directory, and perfectly re-imports the snapshot using the existing batched chunking system.
