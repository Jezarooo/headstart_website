#!/usr/bin/env bash
# Alumni stories: upload the slides and write a Divi import file with THIS site's media IDs.
# Usage (from this folder):   ./make-import.sh --path=/path/to/wordpress
#   needs WP-CLI (wp) on PATH; any extra args are passed to every wp command (e.g. --path, --url).
# Output: alumni-stories-import.json  -> Divi > Divi Library > Import & Export.
set -euo pipefail
cd "$(dirname "$0")"
out=alumni-stories-import.json
cp divi-alumni-stories.template.json "$out"
for name in CAROL NICK PEA REECE; do
  files=$(ls images/HeadStart-Phuket_Alumni-Network_Alumni-Stories_${name}_*.webp | sort)
  ids=$(wp media import $files --porcelain "$@" | paste -sd, -)
  [ -n "$ids" ] || { echo "No IDs returned for $name" >&2; exit 1; }
  sed -i.bak "s/__IDS_${name}__/${ids}/" "$out" && rm -f "$out.bak"
  echo "$name: $ids"
done
echo "Wrote $out"
