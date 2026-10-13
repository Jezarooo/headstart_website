# Implementation Plan: Automatic Backup & Revert Feature

1. **PHP: Refactor Export Logic into a Helper**
   - Create `hs_get_all_staff_data()` so both the backup and export features can fetch the full array of staff safely.
   - Update `hs_ajax_export_staff` to simply return `hs_get_all_staff_data()`.

2. **PHP: Modify Wipe Logic to Create Backups**
   - In `hs_ajax_wipe_staff`, optionally skip backup if a specific flag is set.
   - Otherwise, fetch `hs_get_all_staff_data()` and store it in the database using `update_option('hs_staff_last_backup')` with a timestamp before proceeding with deletion.

3. **PHP: Add Backup Retrieval Endpoint**
   - Create `hs_ajax_get_backup` which retrieves `hs_staff_last_backup` from the DB and sends it to the frontend.

4. **UI: Add "Emergency Revert" Panel**
   - Add a UI section at the bottom of the page showing the timestamp and staff count of the last backup.
   - Add a "Revert to this Backup" button.

5. **JS: Implement Revert Logic**
   - Move `startBatchImport` out of the form submit handler so it can be reused.
   - Add click handler for the Revert button that:
     1. Asks for user confirmation.
     2. Fetches the backup data via AJAX.
     3. Calls `hs_wipe_staff` with a `skip_backup=1` flag (to avoid overwriting the backup being restored).
     4. Feeds the backup array into `startBatchImport(headers, rows)` to perfectly restore the exact state in chunked batches.
