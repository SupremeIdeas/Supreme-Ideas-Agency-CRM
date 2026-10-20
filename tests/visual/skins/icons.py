import json,re,sys
from PIL import Image
meta=json.load(open('out/ic/meta.json'))
def lum(c):
    f=lambda v:(v/255)/12.92 if v/255<=.03928 else (((v/255)+.055)/1.055)**2.4
    return .2126*f(c[0])+.7152*f(c[1])+.0722*f(c[2])
def ratio(a,b):
    la,lb=lum(a),lum(b);return (max(la,lb)+.05)/(min(la,lb)+.05)
def parse(c):
    m=re.match(r'rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)',c)
    if m:return [float(m[1]),float(m[2]),float(m[3])],float(m[4]) if m[4] else 1.0
    m=re.match(r'color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)(?: / ([\d.]+))?\)',c)
    if m:return [float(m[1])*255,float(m[2])*255,float(m[3])*255],float(m[4]) if m[4] else 1.0
    return None,1.0
fails={};n=0
for m in meta:
    im=Image.open(m['file']).convert('RGB')
    for it in m['items']:
        col,al=parse(it['c'])
        if col is None:continue
        al*=it['o'];x0=max(0,int(it['x']));y0=max(0,int(it['y']));x1=min(im.width,int(it['x']+it['w']));y1=min(im.height,int(it['y']+it['h']))
        if x1<=x0 or y1<=y0:continue
        px=list(im.crop((x0,y0,x1,y1)).get_flattened_data());sample=px[::max(1,len(px)//300)]
        rs=sorted(ratio([col[i]*al+p[i]*(1-al) for i in range(3)],p) for p in sample);w=rs[len(rs)//4];n+=1
        if w<3.0:fails.setdefault((m['url'],m['skin'],m['mode']),[]).append((round(w,2),it['name'],it['par']))
print('checked',n,'icons')
for k,v in sorted(fails.items()):print(k,len(v),sorted(v)[:3])
print('combos with failures:',len(fails))
