import json
from PIL import Image
def lin(c):
    c/=255;return c/12.92 if c<=.03928 else ((c+.055)/1.055)**2.4
def L(rgb):return .2126*lin(rgb[0])+.7152*lin(rgb[1])+.0722*lin(rgb[2])
def cr(a,b):
    la,lb=L(a),L(b);hi,lo=max(la,lb),min(la,lb);return (hi+.05)/(lo+.05)
import re
meta=json.load(open('out/navc/meta.json'));bad=[]
for e in meta:
    im=Image.open(e['file']).convert('RGB')
    for it in e['items']:
        m=re.findall(r'[\d.]+',it['color']);fg=[float(x) for x in m[:3]]
        x0,y0,x1,y1=int(it['x']),int(it['y']),int(it['x']+it['w']),int(it['y']+it['h'])
        px=[im.getpixel((x,y)) for x in range(max(x0,0),min(x1,430),2) for y in range(max(y0,0),min(y1,900),2)]
        if not px:continue
        # worst case: the background pixel with lowest contrast vs fg (median to avoid anti-alias noise)
        cs=sorted(cr(fg,p) for p in px);c=cs[len(cs)//2] if it['kind']=='icon' else cs[len(cs)//4]
        need=4.5 if it['kind']=='label' else 3.0
        if c<need:bad.append((int(it['x']),e['skin'],e['mode'],e['acc'],it['kind'],'on' if it['on'] else 'off',round(c,2)))
print('checked',len(meta),'combos; failures',len(bad))
for b in bad[:80]:print(b)
