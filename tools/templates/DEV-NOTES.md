# {{TITLE}} — dev notes

Package `{{ZIP}}` · {{DATE}} · branch `{{BRANCH}}`

## Problem
TODO: technical description for a developer: what was wrong, where (snippet / function / hook / file), and why it happened.

## Change
TODO: what the code does now, function by function, and what is deliberately unchanged.

## Files
{{FILE_TABLE}}

## Watch out for
TODO: anything unusual about deploying this (a new snippet, activation order, rewrite flush, files in uploads/hs-head); delete this line if nothing.
- Purge WP Super Cache and Cloudflare APO after deploying if URLs, redirects or page output changed.
- Cloudflare challenges plain `curl` (403); send a browser user agent when checking.

## Quick check after deploy (optional)
TODO: one short command or click-path, with the expected result.

## Previous version
`rollback/` holds the live files as they were before this change, if you need to revert. Files marked *new* have no previous version: deactivate them instead.
