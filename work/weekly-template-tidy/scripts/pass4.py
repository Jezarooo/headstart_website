import re,json,sys,copy
src,dst=sys.argv[1],sys.argv[2]
d=json.load(open(src)); pid=list(d['data'])[0]; s=d['data'][pid]
pat=re.compile(r'<!-- (/?)wp:([\w/-]+)(?: (\{.*?\}))?\s*(/?)-->',re.S)
root={'name':'ROOT','attrs':None,'children':[],'self':False}; st=[root]
for m in pat.finditer(s):
    c,n,a,sc=m.groups()
    if c: st.pop(); continue
    node={'name':n,'attrs':json.loads(a) if a else None,'children':[],'self':bool(sc)}
    st[-1]['children'].append(node)
    if not sc: st.append(node)
def label(n): return ((n['attrs'] or {}).get('module',{}).get('meta',{}).get('adminLabel',{}).get('desktop',{}) or {}).get('value','')
def setlabel(n,v): n['attrs'].setdefault('module',{}).setdefault('meta',{})['adminLabel']={'desktop':{'value':v}}
def ic(n): return n['attrs'].get('content',{}).get('innerContent',{})
def txt(n): return ic(n).get('desktop',{}).get('value','')
def settxt(n,v):
    for bp in ic(n).values(): bp['value']=v
def walk(n):
    yield n
    for c in n['children']: yield from walk(c)
# ---- 1. article text: Paragraph Default + module-level h3 style (no new preset)
CSS=("selector h3 {\n  border-left: 6px solid var(--gcid-secondary-color, #f0b323);\n"
     "  padding: 10px 0 10px 10px !important;\n  margin: 0 0 1rem 0 !important;\n}")
for n in walk(root):
    if n['name']=='divi/text' and n['attrs'].get('modulePreset')==['hswkarttx1']:
        n['attrs']['modulePreset']=['e55j0z9xxs']
        n['attrs'].setdefault('content',{}).setdefault('decoration',{})['headingFont']={'h3':{'font':{'desktop':{'value':{'size':'1.6rem'}}}}}
        n['attrs']['css']={'desktop':{'value':{'freeForm':CSS}}}
        setlabel(n,'Article text (h3 headline + body)')
d['presets']['module']['divi/text']['items'].pop('hswkarttx1',None)
# ---- 2. unwrap single-image groups
for n in walk(root):
    new=[]
    for c in n['children']:
        if c['name']=='divi/group' and label(c)=='Image – full width': new+=c['children']
        else: new.append(c)
    n['children']=new
# ---- 3. row labels
section=root['children'][0]['children'][0]; rows=section['children']
def heading(row):
    m=re.search(r'<h2[^>]*>(.*?)</h2>',txt(row['children'][0]['children'][0]),re.S); return m.group(1).replace('&amp;','&')
generic=[]
for r in rows[1:]:
    h=heading(r)
    if h=='Section heading': setlabel(r,'Section (copy this row)'); generic.append(r)
    else: setlabel(r,'Promotions' if h.startswith('Promotions') else h)
# ---- 4. image layout examples in the two generic sections
proto_art=[x for x in generic[0]['children'][0]['children'] if x['name']=='divi/group'][0]
proto_img=next(n for n in walk(proto_art) if n['name']=='divi/image')
proto_grid=next(n for n in walk(root) if n['name']=='divi/group' and label(n).startswith('Image grid'))
proto_gal=next(n for n in walk(proto_art) if n['name']=='divi/gallery')
def img(span=None,phone_span=None):
    i=copy.deepcopy(proto_img); i['attrs']['module'].pop('meta',None)
    sz={}
    if span: sz['desktop']={'value':{'gridColumnSpan':str(span)}}
    if phone_span: sz['phone']={'value':{'gridColumnSpan':str(phone_span)}}
    if sz: i['attrs']['module'].setdefault('decoration',{})['sizing']=sz
    return i
def grid(name,cols,imgs,phone_cols=None):
    g=copy.deepcopy(proto_grid); g['children']=imgs
    lay=g['attrs']['module']['decoration']['layout']; lay['desktop']['value']['gridColumnCount']=str(cols)
    if phone_cols: lay['phone']={'value':{'gridColumnCount':str(phone_cols)}}
    setlabel(g,name); return g
def gal():
    g=copy.deepcopy(proto_gal); setlabel(g,'Gallery – 12+ images'); return g
L=[
 ('Single image',      lambda:[img()]),
 ('2 across',          lambda:[grid('Images – 2 across',2,[img(),img()])]),
 ('3 across',          lambda:[grid('Images – 3 across',3,[img(),img(),img()])]),
 ('2 × 2',             lambda:[grid('Images – 2 × 2',2,[img() for _ in range(4)])]),
 ('1 + 2',             lambda:[grid('Images – 1 + 2',2,[img(2)]+[img(),img()])]),
 ('1 + 3',             lambda:[grid('Images – 1 + 3',3,[img(3)]+[img() for _ in range(3)])]),
 ('1 + 4',             lambda:[grid('Images – 1 + 4',4,[img(4,2)]+[img() for _ in range(4)],phone_cols=2)]),
 ('2 then 3',          lambda:[grid('Images – 2 then 3',6,[img(3),img(3)]+[img(2) for _ in range(3)])]),
 ('2 + 4',             lambda:[grid('Images – 2 + 4',4,[img(2,1),img(2,1)]+[img() for _ in range(4)],phone_cols=2)]),
 ('Gallery',           lambda:[gal()]),
 ('1 + gallery',       lambda:[img(),gal()]),
]
def article(name,images):
    a=copy.deepcopy(proto_art)
    setlabel(a,f'Article – {name}')
    for g in a['children']:
        if label(g)=='Images': g['children']=images
    for t in walk(a):
        if t['name']=='divi/text' and '<h3' in txt(t): settxt(t,re.sub(r'<h3>.*?</h3>',f'<h3>Article headline ({name.lower()} layout)</h3>',txt(t),flags=re.S))
    return a
halves=[L[:5],L[5:]]
for r,part in zip(generic,halves):
    col=r['children'][0]
    col['children']=[col['children'][0]]+[article(n,f()) for n,f in part]
# ---- 5. fixed rows: stacks -> one-group layouts; then drop the Images wrapper
def is_grid(n,cols=None):
    if n['name']!='divi/group': return False
    v=n['attrs'].get('module',{}).get('decoration',{}).get('layout',{}).get('desktop',{}).get('value',{})
    return v.get('display')=='grid' and (cols is None or str(v.get('gridColumnCount'))==str(cols)) and all(c['name']=='divi/image' for c in n['children'])
for n in list(walk(root)):
    if n['name']!='divi/group' or label(n)!='Images': continue
    k=n['children']
    sig=['img' if c['name']=='divi/image' else ('g%s'%len(c['children']) if is_grid(c) else c['name']) for c in k]
    if sig==['img','g2']: n['children']=[grid('Images – 1 + 2',2,[img(2),img(),img()])]
    elif sig==['img','g3']: n['children']=[grid('Images – 1 + 3',3,[img(3)]+[img() for _ in range(3)])]
    elif sig==['g2','g3']: n['children']=[grid('Images – 2 then 3',6,[img(3),img(3)]+[img(2) for _ in range(3)])]
    elif sig==['img','divi/gallery']:
        w=copy.deepcopy(proto_grid); w['children']=n['children']; w['attrs']['module']['decoration']['layout']={'desktop':{'value':{'rowGap':'0.5rem'}}}; setlabel(w,'Images – 1 + gallery'); n['children']=[w]
for n in walk(root):
    if label(n).startswith('Image grid – '): setlabel(n,'Images – '+label(n)[len('Image grid – '):])
for n in list(walk(root)):
    new=[]
    for c in n['children']:
        if c['name']=='divi/group' and label(c)=='Images': new+=c['children']
        else: new.append(c)
    n['children']=new
def enc(a):
    j=json.dumps(a,separators=(',',':'),ensure_ascii=True)
    for x,y in (('--','\\u002d\\u002d'),('<','\\u003c'),('>','\\u003e'),('&','\\u0026'),('\\"','\\u0022')): j=j.replace(x,y)
    return j
def ser(n):
    if n['name']=='ROOT': return ''.join(ser(c) for c in n['children'])
    a=(' '+enc(n['attrs'])) if n['attrs'] is not None else ''
    if n['self']: return f'<!-- wp:{n["name"]}{a} /-->\n'
    return f'<!-- wp:{n["name"]}{a} -->\n'+''.join(ser(c) for c in n['children'])+f'<!-- /wp:{n["name"]} -->\n'
d['data'][pid]=ser(root)
json.dump(d,open(dst,'w'),ensure_ascii=False)
