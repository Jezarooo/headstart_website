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
section=root['children'][0]['children'][0]
generic=[r for r in section['children'] if label(r)=='Section']
col=generic[-1]['children'][0]
proto=[x for x in col['children'] if x['name']=='divi/group'][0]
REEL='''<div class="hs-reel">
  <iframe
    src="https://www.youtube-nocookie.com/embed/{id}?rel=0&playsinline=1"
    title="HeadStart reel"
    loading="lazy"
    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
    allowfullscreen></iframe>
</div>

<style>
.hs-reel {{
  position: relative;
  width: 100%;
  max-width: 360px;
  aspect-ratio: 9 / 16;
  margin: 0 auto;
  border-radius: 12px;
  overflow: hidden;
}}
.hs-reel iframe {{
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  border: 0;
}}
</style>'''
IDS=['WnWsFXTz5cQ','KwDfQ6hH-qo','mi8Sfg_mIw8']
def reel(i,sizing=None):
    a={'module':{'meta':{'adminLabel':{'desktop':{'value':'Reel – paste YouTube ID'}}}},'content':{'innerContent':{'desktop':{'value':REEL.format(id=IDS[i%3])}}},'builderVersion':'5.13'}
    if sizing: a['module']['decoration']={'sizing':sizing}
    return {'name':'divi/code','attrs':a,'children':[],'self':True}
def group(name,layout,kids,sizing=None):
    a={'module':{'meta':{'adminLabel':{'desktop':{'value':name}}},'decoration':{'layout':layout}},'builderVersion':'5.13'}
    if sizing: a['module']['decoration']['sizing']=sizing
    return {'name':'divi/group','attrs':a,'children':kids,'self':False}
def article(body):
    a=copy.deepcopy(proto); text=[g for g in a['children'] if label(g)=='Text'][0]
    a['children']=body(text); return a
full={'desktop':{'value':{'flexType':'24_24'}},'tablet':{'value':{'flexType':'24_24'}},'phone':{'value':{'flexType':'24_24'}}}
def right_col(text):
    t=copy.deepcopy(text); t['attrs']['module'].setdefault('decoration',{})['sizing']={'desktop':{'value':{'flexType':'16_24'}},'tablet':{'value':{'flexType':'24_24'}},'phone':{'value':{'flexType':'24_24'}}}
    r=reel(0,{'desktop':{'value':{'flexType':'8_24'}},'tablet':{'value':{'flexType':'24_24'}},'phone':{'value':{'flexType':'24_24'}}})
    lay={'desktop':{'value':{'flexDirection':'row','columnGap':'1.5rem','rowGap':'1rem','alignItems':'flex-start'}},'tablet':{'value':{'flexDirection':'column'}},'phone':{'value':{'flexDirection':'column'}}}
    return [group('Reel – right column',lay,[t,r])]
def below(n):
    def f(text):
        lay={'desktop':{'value':{'display':'grid','gridAutoColumns':'0px','gridColumnCount':str(n),'columnGap':'0.5rem','rowGap':'0.5rem'}}}
        return [text, group(f'Reels – {n} across',lay,[reel(i) for i in range(n)])]
    return f
new=[article(right_col),article(below(2)),article(below(3))]
col['children']+=new
def enc(a):
    j=json.dumps(a,separators=(',',':'),ensure_ascii=True)
    for x,y in (('--','\\u002d\\u002d'),('<','\\u003c'),('>','\\u003e'),('&','\\u0026'),('\\"','\\u0022')): j=j.replace(x,y)
    return j
def ser(n):
    if n['name']=='ROOT': return ''.join(ser(c) for c in n['children'])
    a=(' '+enc(n['attrs'])) if n['attrs'] is not None else ''
    if n['self']: return f'<!-- wp:{n["name"]}{a} /-->\n'
    return f'<!-- wp:{n["name"]}{a} -->\n'+''.join(ser(c) for c in n['children'])+f'<!-- /wp:{n["name"]} -->\n'
d['data'][pid]=ser(root); json.dump(d,open(dst,'w'),ensure_ascii=False)
open(dst.replace('.json','.txt'),'w').write(d['data'][pid])
