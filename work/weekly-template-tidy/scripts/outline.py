import re,json,sys,html
s=open(sys.argv[1]).read()
pat=re.compile(r'<!-- (/?)wp:([\w/-]+)(?: (\{.*?\}))?\s*(/?)-->',re.S)
depth=0
for m in pat.finditer(s):
    close,name,attrs,selfc=m.groups()
    if close: depth-=1; continue
    a=json.loads(attrs) if attrs else {}
    mod=a.get('module',{})
    label=mod.get('meta',{}).get('adminLabel',{}).get('desktop',{}).get('value','')
    cls=[x.get('value') for x in mod.get('decoration',{}).get('attributes',{}).get('desktop',{}).get('value',{}).get('attributes',[]) if x.get('name') in('class','id')]
    css=mod.get('advanced',{}).get('htmlAttributes',{}).get('desktop',{}).get('value',{})
    cont=a.get('content',{})
    txt=''
    ic=cont.get('innerContent',{}).get('desktop',{}).get('value') if isinstance(cont,dict) else None
    if isinstance(ic,str): txt=re.sub(r'<[^>]+>',' ',html.unescape(ic)); txt=' '.join(txt.split())[:70]
    elif isinstance(ic,dict): txt=str(ic)[:90]
    title=cont.get('title',{}).get('innerContent',{}).get('desktop',{}).get('value') if isinstance(cont,dict) and isinstance(cont.get('title'),dict) else None
    extra=''
    if 'gallery' in name: extra=' GALLERY:'+json.dumps(a.get('module',{}).get('advanced',{}).get('galleryIds',a.get('module',{}).get('advanced',{})))[:200]
    if name=='divi/image': extra=' SRC:'+str(a.get('image',{}).get('innerContent',{}).get('desktop',{}).get('value',{}).get('src',''))[-60:]
    dis=mod.get('decoration',{}).get('disabledOn')
    print('  '*depth+f"{name} [{label}] {cls or ''} {css or ''} {('T:'+title) if title else ''} {('«'+txt+'»') if txt else ''}{extra}{' DISABLED:'+json.dumps(dis) if dis else ''}")
    if not selfc: depth+=1
