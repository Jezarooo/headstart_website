import re,json,sys,copy
src,dst=sys.argv[1],sys.argv[2]
d=json.load(open(src))
pid=list(d['data'])[0]
s=d['data'][pid]
pat=re.compile(r'<!-- (/?)wp:([\w/-]+)(?: (\{.*?\}))?\s*(/?)-->',re.S)
# ---- parse
root={'name':'ROOT','attrs':None,'children':[],'self':False}
stack=[root]
for m in pat.finditer(s):
    close,name,attrs,selfc=m.groups()
    if close: stack.pop(); continue
    n={'name':name,'attrs':json.loads(attrs) if attrs else None,'children':[],'self':bool(selfc)}
    stack[-1]['children'].append(n)
    if not selfc: stack.append(n)
assert len(stack)==1
# ---- helpers
def label(n): return (((n['attrs'] or {}).get('module',{}).get('meta',{}).get('adminLabel',{}).get('desktop',{}) or {}).get('value',''))
def setlabel(n,v):
    a=n['attrs']=n['attrs'] or {}
    a.setdefault('module',{}).setdefault('meta',{})['adminLabel']={'desktop':{'value':v}}
def classes(n):
    try: return ' '.join(x['value'] for x in n['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] if x['name']=='class')
    except Exception: return ''
def is_article(n): return n['name']=='divi/group' and 'hs-weekly-article' in classes(n)
def inner(n): return ((n['attrs'] or {}).get('content',{}).get('innerContent',{}))
def map_text(n,f):
    for bp,v in inner(n).items():
        if isinstance(v.get('value'),str): v['value']=f(v['value'])
def text_of(n):
    v=inner(n).get('desktop',{}).get('value','')
    return v if isinstance(v,str) else ''
log=[]
section=root['children'][0]['children'][0]
assert section['name']=='divi/section'
setlabel(section,'Weekly Update – template')
NOUN={'Announcements':'Announcement','Upcoming Events':'Upcoming event','Upcoming Meetings & Presentations':'Upcoming meeting',
 'Meetings & Presentations':'Meeting','Meet the Team':'Team member','Alumni Network':'Alumni story','Foundation':'Foundation story',
 'Primary':'Primary story','Secondary':'Secondary story','Success Outside of School':'Student success','Promotions from around the Island':'Promotion'}
H2FIX={'ANNOUNCEMENTS':'Announcements','UPCOMING EVENTS':'Upcoming Events','foundation':'Foundation'}
def tidy_article(art,noun,num,keep_head=False):
    setlabel(art,f'Article – {noun} {num}')
    for g in art['children']:
        if g['name']!='divi/group': continue
        if label(g)=='Article Text':
            setlabel(g,'Text')
            for t in g['children']:
                L=label(t)
                if t['name']=='divi/code': setlabel(t,'Download button'); continue
                if L in('Heading','Main Heading'):
                    setlabel(t,'Headline (h3)')
                    if not keep_head: map_text(t,lambda v:re.sub(r'<h3[^>]*>.*?</h3>',f'<h3>{noun} {num} headline</h3>',v,flags=re.S))
                elif L=='Subheading': setlabel(t,'Subheading – name | job title')
                elif L=='Body': setlabel(t,'Body text')
        elif label(g)=='Images':
            setlabel(g,'Images')
            for c in g['children']:
                if c['name']=='divi/gallery': setlabel(c,'Gallery – add 12+ images'); continue
                L=label(c)
                if L=='Single Image': setlabel(c,'Image – full width'); imgs=c['children']
                else:
                    cols=c['attrs']['module']['decoration']['layout']['desktop']['value'].get('gridColumnCount','?')
                    setlabel(c,f'Image grid – {cols} across'); imgs=c['children']
                for i,im in enumerate(imgs,1): setlabel(im,f'Image {i}')
row_names={}
for row in section['children']:
    col=row['children'][0]; setlabel(col,'Column')
    if label(row)=='Google Calendar':
        setlabel(row,'Calendar')
        kept=[]
        for c in col['children']:
            if c['name']=='divi/text' and '[hs_weekly_blurb]' in text_of(c): log.append('removed phone-only [hs_weekly_blurb] text module'); continue
            if c['name']=='divi/code': setlabel(c,'Google Calendar embed')
            kept.append(c)
        col['children']=kept; continue
    head=col['children'][0]
    m=re.search(r'<h2[^>]*>(.*?)</h2>',text_of(head),re.S)
    h2=m.group(1).replace('&amp;','&')
    if h2 in H2FIX:
        new=H2FIX[h2]; log.append(f'heading {h2!r} -> {new!r}')
        map_text(head,lambda v,h2=h2,new=new:v.replace('>'+h2+'<','>'+new+'<')); h2=new
    noun=NOUN[h2]
    setlabel(row,f'Section – {h2}')
    setlabel(head,'Section heading (h2) – keep exact wording')
    # un-nest articles inside articles
    flat=[]
    for c in col['children'][1:]:
        stack=[c]
        while stack:
            a=stack.pop(0)
            nested=[x for x in a['children'] if is_article(x)]
            if nested:
                log.append(f'{h2}: un-nested an article from inside another article')
                a['children']=[x for x in a['children'] if not is_article(x)]
            flat.append(a)
            for x in nested:
                # a text group that only holds a form/download card becomes its own article
                txt=[g for g in x['children'] if g['name']=='divi/group' and label(g)=='Article Text']
                forms=[g for g in txt if any(t['name']=='divi/code' for t in g['children'])]
                if forms:
                    x['children']=[g for g in x['children'] if g not in forms]
                    stack.append(x)
                    for f in forms:
                        fa=copy.deepcopy(x); fa['children']=[f]; fa['_form']=True
                        stack.append(fa)
                else: stack.append(x)
                # keep order: x then form article
    n=0
    for a in flat:
        if a.get('_form'):
            a.pop('_form'); tidy_article(a,noun,'',keep_head=True); setlabel(a,'Article – Submission form (keep every week)'); [setlabel(t,'Form button') for g in a['children'] for t in g['children'] if t['name']=='divi/code']; continue
        n+=1; tidy_article(a,noun,n)
        if h2.startswith('Promotions'): setlabel(a,'Promotions – images only (not turned into a post)')
    col['children']=[head]+flat
# ---- strip stray inline font-size spans from h3s
def walk(n):
    yield n
    for c in n['children']: yield from walk(c)
for n in walk(root):
    if n['name']=='divi/text': map_text(n,lambda v:re.sub(r'<span style="font-size: 25\.6px;">(.*?)</span>',r'\1',v))
# ---- serialize like WordPress serialize_block_attributes
def enc(a):
    j=json.dumps(a,separators=(',',':'),ensure_ascii=True)
    for x,y in (('--','\\u002d\\u002d'),('<','\\u003c'),('>','\\u003e'),('&','\\u0026'),('\\"','\\u0022')): j=j.replace(x,y)
    return j
def ser(n):
    if n['name']=='ROOT': return ''.join(ser(c) for c in n['children'])
    a=(' '+enc(n['attrs'])) if n['attrs'] is not None else ''
    if n['self']: return f'<!-- wp:{n["name"]}{a} /-->\n'
    return f'<!-- wp:{n["name"]}{a} -->\n'+''.join(ser(c) for c in n['children'])+f'<!-- /wp:{n["name"]} -->\n'
out=ser(root)
d['data'][pid]=out
json.dump(d,open(dst,'w'),ensure_ascii=False)
print('\n'.join(log))
