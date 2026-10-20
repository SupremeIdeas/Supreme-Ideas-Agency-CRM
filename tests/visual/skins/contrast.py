import json,sys
from PIL import Image
import statistics
meta=json.load(open('out/c/meta.json'))
def lum(c):
    def f(v):
        v/=255
        return v/12.92 if v<=.03928 else ((v+.055)/1.055)**2.4
    return .2126*f(c[0])+.7152*f(c[1])+.0722*f(c[2])
def ratio(a,b):
    la,lb=lum(a),lum(b); hi,lo=max(la,lb),min(la,lb); return (hi+.05)/(lo+.05)
import re
def parse(c):
    m=re.match(r'rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)',c)
    if m: return [float(m[1]),float(m[2]),float(m[3])],float(m[4]) if m[4] else 1.0
    m=re.match(r'color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)(?: / ([\d.]+))?\)',c)
    if m: return [float(m[1])*255,float(m[2])*255,float(m[3])*255],float(m[4]) if m[4] else 1.0
    m=re.match(r'oklab|oklch|color',c)
    return None,1.0
fails={}; total=0; unparsed=set()
for m in meta:
    im=Image.open(m['file']).convert('RGB')
    for it in m['items']:
        col,al=parse(it['c'])
        if col is None: unparsed.add(it['c'][:30]); continue
        x0=int(it['x']-m['ox']);y0=int(it['y']-m['oy']);x1=int(x0+it['w']);y1=int(y0+it['h'])
        x0=max(0,x0);y0=max(0,y0);x1=min(im.width,x1);y1=min(im.height,y1)
        if x1<=x0 or y1<=y0: continue
        px=list(im.crop((x0,y0,x1,y1)).get_flattened_data())
        # the WORST background in the text box: contrast against the pixel that is closest to the text colour
        al*=it['o']
        sample=px[::max(1,len(px)//400)]
        rs=[]
        for p in sample:
            fg=[col[i]*al+p[i]*(1-al) for i in range(3)]
            rs.append(ratio(fg,p))
        rs.sort(); worst=rs[len(rs)//4]    # 25th percentile: a gradient/pattern edge may dip, the body of the text must pass
        large=it['fs']>=24 or (it['fs']>=18.66 and it['fw']>=700)
        need=3.0 if large else 4.5
        total+=1
        if worst<need:
            key=(m['skin'],m['mode'])
            fails.setdefault(key,[]).append((round(worst,2),need,it['t'],it['cls'],m['acc']))
print('checked',total,'text nodes; unparsed colours:',unparsed)
by={}
for (s,mode),v in fails.items(): by.setdefault(s,{})[mode]=v
for s in sorted(by):
    for mode,v in by[s].items():
        worst=sorted(v)[:3]
        print(f'{s:11}{mode:6} {len(v):3} fails  worst: '+'; '.join(f'{w[0]}:{w[1]} "{w[2]}" [{w[3][:22]}] {w[4]}' for w in worst))
print('skins with failures:',len(by))
uniq={}
for (sk,mode),v in fails.items():
    for w in v:
        k=(sk,mode,w[3][:30],w[2]); 
        if k not in uniq or w[0]<uniq[k][0]: uniq[k]=(w[0],w[1],w[4])
import sys
if len(sys.argv)>1:
    for k,v in sorted(uniq.items()):
        if sys.argv[1]=='all' or sys.argv[1] in k[0]:
            print(k,v)
