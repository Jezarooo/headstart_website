import re,json,sys,copy
src,dst=sys.argv[1],sys.argv[2]
d=json.load(open(src)); pid=list(d['data'])[0]; s=d['data'][pid]
pat=re.compile(r'<!-- (/?)wp:([\w/-]+)(?: (\{.*?\}))?\s*(/?)-->',re.S)
def enc(a):
    j=json.dumps(a,separators=(',',':'),ensure_ascii=True)
    for x,y in (('--','\\u002d\\u002d'),('<','\\u003c'),('>','\\u003e'),('&','\\u0026'),('\\"','\\u0022')): j=j.replace(x,y)
    return j
shadow=None
def fix(m):
    global shadow
    close,name,attrs,selfc=m.groups()
    if close or not attrs: return m.group(0)
    a=json.loads(attrs)
    meta=a.get('module',{}).get('meta',{}).get('adminLabel',{}).get('desktop',{})
    L=meta.get('value','')
    if name=='divi/section': meta['value']='HSC Weekly Update'
    if name=='divi/row':
        dec=a['module'].setdefault('decoration',{})
        if 'boxShadow' in dec and shadow is None: shadow=copy.deepcopy(dec['boxShadow'])
        if L.startswith('Section – '):
            meta['value']='Section'
            if 'boxShadow' not in dec: dec['boxShadow']=copy.deepcopy(shadow); print('added shadow to',L)
    if name=='divi/group' and L.startswith('Article – ') and 'Submission form' not in L: meta['value']='Article'
    if name=='divi/group' and L.startswith('Promotions'): meta['value']='Article – images only (no post)'
    return f'<!-- wp:{name} {enc(a)} {"/" if selfc else ""}-->'.replace(' -->',' -->')
out=pat.sub(fix,s)
d['data'][pid]=out
json.dump(d,open(dst,'w'),ensure_ascii=False)
