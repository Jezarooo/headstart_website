"""Build the final Weekly Update files from the agreed draft.

    python3 -I build.py <draft.json> <out_dir>

Input: templates/draft-HSC-Weekly-Update-Template-inline-css.json (the version
agreed on local on 18 Nov; made from source/Weekly-Update-12-live-export.json
by tidy.py -> pass2..pass4 -> reels.py plus label/gallery edits in the session).

Output (into out_dir):
  HSC-Weekly-Update-Template.json   Upcoming Events: 2 x single image; two copyable sections, 2 articles each (2 across + gallery); no reels
  HSN-Weekly-Update-Template.json   same, Cherngtalay calendar
  Weekly-Update-Layout-Library.json every image + reel layout, one per article

CSS is taken OUT of the JSON: article text modules get the class
hs-article-text and reels lose their <style>; the look comes from
Site-Scripts/css/weekly-article.css.
"""
import copy
import json
import os
import random
import re
import string
import sys

PAT = re.compile(r'<!-- (/?)wp:([\w/-]+)(?: (\{.*?\}))?\s*(/?)-->', re.S)
HSC_CAL = 'headstartphuket.com_keg0agicud8dmu7a8saltt5uto@group.calendar.google.com'
HSN_CAL = 'c_a6c3c4a648e80171a8aa723b6720a479867a99f4e30e04a64de10cb3582fcc7d@group.calendar.google.com'

# Order of the example articles in the draft's two "Section" rows.
DRAFT_LAYOUTS = [
    ['Single image', '2 across', '3 across', '2 × 2', '1 + 2'],
    ['1 + 3', '1 + 4', '2 then 3', '2 + 4', 'Gallery', '1 + gallery',
     'Reel – right column', 'Reels – 2 across', 'Reels – 3 across'],
]


def parse(s):
    root = {'name': 'ROOT', 'attrs': None, 'children': [], 'self': False}
    st = [root]
    for m in PAT.finditer(s):
        close, name, attrs, selfc = m.groups()
        if close:
            st.pop()
            continue
        node = {'name': name, 'attrs': json.loads(attrs) if attrs else None, 'children': [], 'self': bool(selfc)}
        st[-1]['children'].append(node)
        if not selfc:
            st.append(node)
    assert len(st) == 1, 'unbalanced blocks'
    return root


def enc(a):
    j = json.dumps(a, separators=(',', ':'), ensure_ascii=True)
    for x, y in (('--', '\\u002d\\u002d'), ('<', '\\u003c'), ('>', '\\u003e'), ('&', '\\u0026'), ('\\"', '\\u0022')):
        j = j.replace(x, y)
    return j


def ser(n):
    if n['name'] == 'ROOT':
        return ''.join(ser(c) for c in n['children'])
    a = (' ' + enc(n['attrs'])) if n['attrs'] is not None else ''
    if n['self']:
        return f'<!-- wp:{n["name"]}{a} /-->\n'
    return f'<!-- wp:{n["name"]}{a} -->\n' + ''.join(ser(c) for c in n['children']) + f'<!-- /wp:{n["name"]} -->\n'


def walk(n):
    yield n
    for c in n['children']:
        yield from walk(c)


def label(n):
    return ((n['attrs'] or {}).get('module', {}).get('meta', {}).get('adminLabel', {}).get('desktop', {}) or {}).get('value', '')


def setlabel(n, v):
    n['attrs'].setdefault('module', {}).setdefault('meta', {})['adminLabel'] = {'desktop': {'value': v}}


def text(n):
    return n['attrs'].get('content', {}).get('innerContent', {}).get('desktop', {}).get('value', '')


def map_text(n, f):
    for bp in n['attrs'].get('content', {}).get('innerContent', {}).values():
        if isinstance(bp.get('value'), str):
            bp['value'] = f(bp['value'])


def add_class(n, cls):
    rid = ''.join(random.Random(cls).choice(string.ascii_lowercase + string.digits) for _ in range(10))
    lst = (n['attrs'].setdefault('module', {}).setdefault('decoration', {})
           .setdefault('attributes', {}).setdefault('desktop', {}).setdefault('value', {})
           .setdefault('attributes', []))
    if not any(x.get('name') == 'class' and x.get('value') == cls for x in lst):
        lst.append({'id': rid, 'name': 'class', 'value': cls, 'adminLabel': cls, 'targetElement': 'main'})


def strip_css(root):
    """Article text: CSS class instead of per-module CSS. Reels: no <style>."""
    for n in walk(root):
        a = n['attrs'] or {}
        if n['name'] == 'divi/text' and 'css' in a:
            a.pop('css')
            dec = a.get('content', {}).get('decoration', {})
            dec.pop('headingFont', None)
            if not dec and 'content' in a:
                a['content'].pop('decoration', None)
            add_class(n, 'hs-article-text')
        if n['name'] == 'divi/code' and 'hs-reel' in text(n):
            map_text(n, lambda v: re.sub(r'\s*<style>.*?</style>\s*', '', v, flags=re.S).strip()
                     .replace('class="hs-reel"', 'class="hs-wu-reel"'))


def section_rows(root):
    sec = root['children'][0]['children'][0]
    return sec, [r for r in sec['children'] if label(r) == 'Section']


def articles(row):
    return [x for x in row['children'][0]['children'] if x['name'] == 'divi/group']


def layout_of(article):
    """The article's image/reel layout: everything after its Text group."""
    return [c for c in article['children'] if label(c) != 'Text']


def with_layout(article, nodes):
    a = copy.deepcopy(article)
    a['children'] = [c for c in a['children'] if label(c) == 'Text'] + copy.deepcopy(nodes)
    return a


def two_plus_gallery(by_name):
    """'2 across' grid, then the gallery, in a wrapper like '1 + gallery'."""
    wrap = copy.deepcopy(layout_of(by_name['1 + gallery'])[0])
    wrap['children'] = copy.deepcopy(layout_of(by_name['2 across']) + layout_of(by_name['Gallery']))
    setlabel(wrap, 'Images – 2 + gallery')
    return wrap


def build_template(draft):
    """Upcoming Events: 2 articles, single image each.
    Both 'Section' rows: 2 articles, each 2 across + gallery. No reels."""
    root = copy.deepcopy(draft)
    sec, generic = section_rows(root)
    by_name = {}
    for row, names in zip(generic, DRAFT_LAYOUTS):
        by_name.update(zip(names, articles(row)))
    single = layout_of(by_name['Single image'])
    two_gal = two_plus_gallery(by_name)
    for row in generic:
        col = row['children'][0]
        col['children'] = [col['children'][0]] + [with_layout(by_name['2 across'], [two_gal]) for _ in range(2)]
    events = next(r for r in sec['children'] if label(r) == 'Upcoming Events')
    col = events['children'][0]
    col['children'] = [col['children'][0]] + [with_layout(a, single) for a in articles(events)[:2]]
    return root


def build_library(draft):
    root = copy.deepcopy(draft)
    sec, generic = section_rows(root)
    examples = []
    for row, names in zip(generic, DRAFT_LAYOUTS):
        examples += list(zip(names, articles(row)))
    by_name = dict(examples)
    i = [n for n, _ in examples].index('1 + gallery') + 1
    examples.insert(i, ('2 + gallery', with_layout(by_name['2 across'], [two_plus_gallery(by_name)])))
    row = generic[0]
    col = row['children'][0]
    head = col['children'][0]
    map_text(head, lambda v: re.sub(r'(<h2[^>]*>).*?(</h2>)', r'\1Layout library\2', v, flags=re.S))
    setlabel(head, 'Section Heading')
    setlabel(row, 'Layout library')
    out = []
    for name, art in examples:
        a = copy.deepcopy(art)
        setlabel(a, f'Article – {name}')
        for n in walk(a):
            if n['name'] == 'divi/text' and '<h3' in text(n):
                map_text(n, lambda v, name=name: re.sub(
                    r'<h3>.*?</h3>.*', f'<h3>{name}</h3><p>Right-click the layout below and choose Save to Library. '
                    f'In a weekly update, add it inside the Article, below the Text.</p>', v, flags=re.S))
                break
        out.append(a)
    col['children'] = [head] + out
    sec['children'] = [row]
    setlabel(sec, 'Weekly Update – Layout Library')
    return root


def write(draft_json, root, path, section_label=None, calendar=None):
    d = copy.deepcopy(draft_json)
    k = list(d['data'])[0]
    if section_label:
        setlabel(root['children'][0]['children'][0], section_label)
    s = ser(root)
    if calendar:
        assert HSC_CAL in s
        s = s.replace(HSC_CAL, calendar)
    assert 'freeForm' not in s and '\\u003cstyle' not in s, 'CSS left in ' + path
    d['data'][k] = s
    json.dump(d, open(path, 'w'), ensure_ascii=False)
    print('wrote', path, len(s), 'chars,', s.count('wp:divi/'), 'block markers')


def main():
    src, out_dir = sys.argv[1], sys.argv[2]
    draft_json = json.load(open(src))
    draft = parse(list(draft_json['data'].values())[0])
    strip_css(draft)
    tpl = build_template(draft)
    write(draft_json, copy.deepcopy(tpl), os.path.join(out_dir, 'HSC-Weekly-Update-Template.json'), 'HSC Weekly Update')
    write(draft_json, copy.deepcopy(tpl), os.path.join(out_dir, 'HSN-Weekly-Update-Template.json'), 'HSN Weekly Update', HSN_CAL)
    lib = build_library(draft)
    write(draft_json, lib, os.path.join(out_dir, 'Weekly-Update-Layout-Library.json'))


if __name__ == '__main__':
    main()
