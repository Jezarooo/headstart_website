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
section=root['children'][0]['children'][0]
rows=section['children']
def heading(row):
    m=re.search(r'<h2[^>]*>(.*?)</h2>',txt(row['children'][0]['children'][0]),re.S); return m.group(1).replace('&amp;','&')
byname={heading(r):r for r in rows[1:]}
LOREM='<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>'
BULLETS='<ul><li>Lorem ipsum dolor sit amet, consectetur adipiscing elit</li><li>Sed do eiusmod tempor incididunt ut labore et dolore</li></ul>'
# --- generic placeholder sections from Primary and Secondary
generic=[]
for name in ('Primary','Secondary'):
    r=byname[name]; h=r['children'][0]['children'][0]
    settxt(h,re.sub(r'(<h2[^>]*>).*?(</h2>)',r'\1Section heading\2',txt(h)))
    setlabel(h,'Section heading (h2) – type the exact Website Section name')
    for i,a in enumerate([x for x in r['children'][0]['children'] if x['name']=='divi/group'],1):
        for t in walk(a):
            if t['name']=='divi/text' and '<h3' in txt(t): settxt(t,re.sub(r'<h3>.*?</h3>',f'<h3>Article headline</h3>',txt(t),flags=re.S))
    generic.append(r)
# --- Success Outside of School: lorem + bullets, form inside article 2 below images
succ=byname['Success Outside of School']; col=succ['children'][0]
arts=[x for x in col['children'] if x['name']=='divi/group']
form=[a for a in arts if 'Submission form' in label(a)][0]
stories=[a for a in arts if a is not form]
for a in stories:
    for t in walk(a):
        if t['name']=='divi/text' and label(t)=='Body text': settxt(t,LOREM+BULLETS)
formtext=form['children'][0]; setlabel(formtext,'Submission form – keep every week')
stories[-1]['children'].append(formtext)
col['children']=[col['children'][0]]+stories
# --- row order
keep=['Announcements','Upcoming Events','Upcoming Meetings & Presentations','Meetings & Presentations']
section['children']=[rows[0]]+[byname[k] for k in keep]+generic+[succ,byname['Promotions from around the Island']]
# --- merge headline + body into one text module with a new preset
NEWP='hswkarttx1'
for g in walk(root):
    if g['name']!='divi/group': continue
    kids=g['children']
    hi=[i for i,k in enumerate(kids) if k['name']=='divi/text' and label(k)=='Headline (h3)']
    if not hi: continue
    i=hi[0]; head=kids[i]; body=kids[i+1]; assert body['name']=='divi/text' and label(body)=='Body text',label(body)
    h3=re.search(r'<h3[^>]*>.*?</h3>',txt(head),re.S).group(0)
    settxt(body,h3+txt(body)); body['attrs']['modulePreset']=[NEWP]; setlabel(body,'Article text (h3 + body)')
    del kids[i]
    if label(g)=='Text': setlabel(g,'Text')
# --- new preset: Paragraph Default + h3 headline style
pp=d['presets']['module']['divi/text']['items']
base=copy.deepcopy(pp['e55j0z9xxs'])
CSS=("selector h3 {\n  border-left: 6px solid var(--gcid-secondary-color, #f0b323);\n"
     "  padding: 10px 0 10px 10px !important;\n  margin: 0 0 1rem 0 !important;\n}")
base.update(id=NEWP,name='Weekly Article Text (H3 + body)')
for k in ('attrs','styleAttrs'):
    base.setdefault(k,{}); base[k]['css']={'desktop':{'value':{'freeForm':CSS}}}
pp[NEWP]=base
# --- serialize
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
