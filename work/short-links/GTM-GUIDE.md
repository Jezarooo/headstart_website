# GTM: weekly update tracking — step by step

Import file: `work/short-links/gtm-weekly-update-tracking.json` (in your HeadStart folder).

What it adds to container **GTM-T24X62SD** (nothing existing is changed):

| Type | Name | What it does |
|---|---|---|
| Tag | GA4 - Weekly Update View | Sends `weekly_update_view` with campus, school_year, week_number |
| Tag | GA4 - Gallery Open | Sends `gallery_open` with gallery_type (summary / strip / other) |
| Trigger | Event - weekly_update_view | Fires when a weekly update page loads |
| Trigger | Event - hs_gallery_open | Fires when a gallery pop-up is opened |
| Variables | DLV - hs_campus, hs_school_year, hs_week, hs_gallery_type | Read the details the page provides |

Scroll depth is already tracked site-wide by your existing "GA4 - Scroll Depth" tag (`content_scroll` at 25/50/75/90%), so it isn't duplicated.

**When:** steps 1–3 can be done now. Steps 4–5 come after Nick has put the short-links snippet live, because Preview needs the live pages to send the new data.

---

## 1. Check what's already waiting in the workspace (2 min)
1. Open tagmanager.google.com → container **GTM-T24X62SD**.
2. In the top bar, look at **Workspace Changes**. If it's above 0, click it to see the list.
3. Anything there gets published together with our tags in step 5. If you don't recognise something, tell me what's listed before publishing.

## 2. Import (2 min)
1. **Admin** (top) → **Import Container**.
2. **Choose container file** → pick `gtm-weekly-update-tracking.json`.
3. **Choose workspace:** **Existing** → the workspace you checked in step 1 (usually "Default Workspace").
4. **Choose an import option:** **Merge** → **Rename conflicting tags, triggers, and variables**.
5. The preview should say **2 tags, 2 triggers, 4 variables added** and nothing modified or deleted. Click **Confirm**.

## 3. Tell GA4 about the new details (3 min)
GA4 only shows these in reports once they're registered.
1. analytics.google.com → **Admin** (gear, bottom left) → **Custom definitions** → **Create custom dimension**.
2. Create four, each with **Scope: Event**:

| Dimension name | Event parameter |
|---|---|
| Campus | `campus` |
| School year | `school_year` |
| Week number | `week_number` |
| Gallery type | `gallery_type` |

## 4. Test with Preview (after Nick deploys, 5 min)
1. In GTM click **Preview** (top right). Enter a live weekly update URL, e.g. `https://headstartphuket.com/cherngtalay/weekly-update/2026/10/weekly-update-7/`, then **Connect**.
2. The site opens in a new tab, with a Tag Assistant tab alongside it.
3. In Tag Assistant's left column, click **weekly_update_view**. Under **Tags Fired** you should see **GA4 - Weekly Update View**. Click it to check the values: campus `cherngtalay`, school_year `2026-27`, week_number `7`.
4. Back on the site tab, click a gallery so the pop-up opens. A **hs_gallery_open** event appears, with **GA4 - Gallery Open** fired.
5. Optional: open a short link in that tab (e.g. `https://headstartphuket.com/cherngtalay/2026-27/weekly-update-7`). The page address should end with `?utm_source=whatsapp&…`.

If a tag shows under **Tags Not Fired**, send me a screenshot of that panel.

## 5. Publish (1 min)
1. Close Preview → **Submit** (top right).
2. **Version name:** `Weekly update tracking` · **Description:** `GA4 events: weekly_update_view, gallery_open`.
3. **Publish**.
4. Check: GA4 → **Reports → Realtime**. Open a weekly update and you should see `weekly_update_view` within a minute.

## Undo
GTM → **Versions** → the version before "Weekly update tracking" → **⋯ → Publish**.

## Where to see results (from the next day)
- **Which channel brought readers:** GA4 → Reports → Acquisition → Traffic acquisition → filter *Session campaign* = `weekly-update`; the source shows `whatsapp` / `facebook`.
- **Which update, which campus:** Explore → Free form → dimensions *Campus*, *Week number*; metric *Event count* for `weekly_update_view`.
- **Total clicks per link, including people who decline cookies:** the **Share links** box on each weekly update in WordPress.
