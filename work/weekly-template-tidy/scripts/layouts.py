import re,json,sys,collections,os
pat=re.compile(r'<!-- (/?)wp:([\w/-]+)(?: (\{.*?\}))?\s*(/?)-->',re.S)
def parse(s):
    root={'name':'ROOT','a':{},'c':[]};st=[root]
    for m in pat.finditer(s):
        cl,n,a,sc=m.groups()
        if cl: st.pop(); continue
        node={'name':n,'a':json.loads(a) if a else {},'c':[]}
        st[-1]['c'].append(node)
        if not sc: st.append(node)
    return root
def cls(n):
    try: return ' '.join(x['value'] for x in n['a']['module']['decoration']['attributes']['desktop']['value']['attributes'] if x['name']=='class')
    except Exception: return ''
def lay(n):
    try: return n['a']['module']['decoration']['layout']['desktop']['value']
    except Exception: return {}
def label(n): return n['a'].get('module',{}).get('meta',{}).get('adminLabel',{}).get('desktop',{}).get('value','')
def walk(n):
    yield n
    for c in n['c']: yield from walk(c)
def imgs(n): return sum(1 for x in walk(n) if x['name']=='divi/image')
def gal(n):
    v=n['a'].get('image',{}).get('advanced',{}).get('galleryIds',{}).get('desktop',{}).get('value','')
    return len([x for x in str(v).split(',') if x.strip()])
def describe(art):
    parts=[]
    def visit(n):
        for c in n['c']:
            if c['name']=='divi/gallery': parts.append(f'gallery({gal(c)})'); continue
            if c['name']=='divi/image': parts.append('img'); continue
            if c['name']=='divi/group':
                direct=[x for x in c['c'] if x['name']=='divi/image']
                if direct and len(direct)==len(c['c']):
                    L=lay(c); cols=L.get('gridColumnCount') if L.get('display')=='grid' else None
                    parts.append(f'{len(direct)}' + (f'[g{cols}]' if cols and str(cols)!=str(len(direct)) else '') + ('' if L.get('display')=='grid' or len(direct)==1 else f'[{L.get("display","flex")}]'))
                else: visit(c)
    visit(art); return ' + '.join(parts) or '(no images)'
combos=collections.Counter(); per={}
outside=collections.Counter()
for f in sys.argv[1:]:
    r=parse(open(f).read())
    arts=[n for n in walk(r) if n['name']=='divi/group' and 'hs-weekly-article' in cls(n)]
    inarts=set(id(x) for a in arts for x in walk(a))
    outside[os.path.basename(f)]=sum(1 for x in walk(r) if x['name'] in('divi/image','divi/gallery') and id(x) not in inarts)
    for a in arts: combos[describe(a)]+=1
for k,v in combos.most_common(): print(f'{v:3}  {k}')
print('images/galleries outside any article:',{k:v for k,v in outside.items() if v})
