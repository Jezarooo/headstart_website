# Weekly update permalinks: clean editor link + 301s to the dated URL — developer handover

Package `weekly-update-permalinks-2026-10-13.zip` · 2026-10-13 · branch `fix/weekly-update-permalinks`

## Summary
Each weekly update keeps its one preferred URL, e.g. `/chaofah/weekly-update/2026/08/weekly-update-1/` (unchanged). This update makes the block editor show and link that URL instead of the raw database address, and adds a **301** from the two duplicate forms to it: the raw `/weekly-update/{db-slug}/` (e.g. `/weekly-update/weekly-update-1-chaofah-2026-08/`) and the older no-date `/{campus}/weekly-update/{slug}/`. Query strings (UTM tags) are kept. Rewrite rules, the unique-slug logic and the legacy-slug fallback are unchanged.

## Files
| File in package | Change | Goes on live as | Rollback copy |
|---|---|---|---|
| files/php/weekly-update-url-routing.code-snippets.php | modified | `Code Snippets: weekly-update-url-routing` | rollback/php/weekly-update-url-routing.code-snippets.php |

## Deploy steps
1. WordPress admin → Snippets → open **Weekly-Update--URL-Routing**.
2. Replace its entire code with `files/php/weekly-update-url-routing.code-snippets.php` (paste everything after the opening `<?php` line if the editor adds its own), keep it active, Save.
3. Load any weekly update on the front end to confirm no error before purging.

No other snippet changes. No new rewrite rules (a Permalinks save below is harmless but optional).

Always, after the steps above:
1. If any routing or rewrite rule changed: Settings → Permalinks → Save Changes.
2. Purge WP Super Cache (Settings → WP Super Cache → Delete Cache).
3. Purge Cloudflare (APO): purge everything, or at least the affected URLs.
4. Run the smoke checks below.

## Smoke checks
Cloudflare blocks plain `curl`, so send a browser user agent:

```
UA="Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 Chrome/128 Safari/537.36"
for u in /chaofah/weekly-update/2026/08/weekly-update-1/ \
         /weekly-update/weekly-update-1-chaofah-2026-08/ \
         "/weekly-update/weekly-update-1-chaofah-2026-08/?utm_source=test" \
         /chaofah/weekly-update/weekly-update-1/ ; do
  printf '%-60s ' "$u"; curl -s -A "$UA" -o /dev/null -w '%{http_code} %{redirect_url}\n' "https://headstartphuket.com$u"; done
```

Expected, in order:
- **200** (preferred URL, no redirect)
- **301** → `https://headstartphuket.com/chaofah/weekly-update/2026/08/weekly-update-1/`
- **301** → same URL with `?utm_source=test`
- **301** → same URL

If a duplicate still answers 200, the cache wasn't purged. In the block editor, the permalink shown for a published weekly update should be the preferred URL with the short slug (`weekly-update-1`).

## Rollback
Paste each file in `rollback/` back over its snippet (these are the live versions from before this change). For files marked *new*, deactivate the snippet. Then purge the caches again.
