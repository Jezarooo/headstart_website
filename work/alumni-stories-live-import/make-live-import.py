#!/usr/bin/env python3
"""Alumni stories: write the Divi import file with LIVE media IDs.
Input : media-live.json (live /wp-json/wp/v2/media export) + ../alumni-stories/divi-alumni-stories.template.json
Output: alumni-stories-live-import.json
IDs are matched by file name and slide number (live IDs run backwards), never by ID order."""
import json, re, sys, os
here = os.path.dirname(os.path.abspath(__file__))
media = json.load(open(os.path.join(here, "media-live.json")))
tpl = open(os.path.join(here, "..", "alumni-stories", "divi-alumni-stories.template.json")).read()
pat = re.compile(r"_Alumni-Stories_([A-Z]+)_(\d{3})\.webp$")
by = {}
for m in media:
    g = pat.search(m["source_url"])
    if g: by.setdefault(g.group(1), {})[int(g.group(2))] = m["id"]
expected = {"NICK": 10, "CAROL": 5, "PEA": 8, "REECE": 12}
for name, n in expected.items():
    slides = by.get(name, {})
    if sorted(slides) != list(range(1, n + 1)):
        sys.exit("%s: expected slides 1..%d, got %s" % (name, n, sorted(slides)))
    tpl = tpl.replace("__IDS_%s__" % name, ",".join(str(slides[i]) for i in range(1, n + 1)))
    print(name, ",".join(str(slides[i]) for i in range(1, n + 1)))
if "__IDS_" in tpl: sys.exit("placeholder left in output")
json.loads(tpl)  # must be valid JSON
open(os.path.join(here, "alumni-stories-live-import.json"), "w").write(tpl)
print("Wrote alumni-stories-live-import.json")
