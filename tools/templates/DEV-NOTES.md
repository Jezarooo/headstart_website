# {{TITLE}} — developer handover

Package `{{ZIP}}` · {{DATE}} · branch `{{BRANCH}}`

## Summary
TODO: 2–4 sentences — what changes on the live site and why.

## Files
{{FILE_TABLE}}

## Deploy steps
TODO: numbered steps naming the exact Code Snippets / Custom CSS & JS entries to replace, activate or deactivate.

Always, after the steps above:
1. If any routing or rewrite rule changed: Settings → Permalinks → Save Changes.
2. Purge WP Super Cache (Settings → WP Super Cache → Delete Cache).
3. Purge Cloudflare (APO): purge everything, or at least the affected URLs.
4. Run the smoke checks below.

## Smoke checks
TODO: URL → expected result (status code / what you should see), e.g. `curl -sI <url>`.

## Rollback
Paste each file in `rollback/` back over its snippet (these are the live versions from before this change). For files marked *new*, deactivate the snippet. Then purge the caches again.
