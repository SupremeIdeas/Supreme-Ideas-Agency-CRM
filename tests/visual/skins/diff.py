import sys,glob,os
from PIL import Image, ImageChops, ImageFilter
thr=float(sys.argv[1]) if len(sys.argv)>1 else 0.02
def score(a,b):
    """fraction of pixels differing > 40/255 after a 1px blur, best over +/-2px shifts (kills anti-aliasing + sub-pixel offsets)"""
    a=a.filter(ImageFilter.GaussianBlur(.8)); b=b.filter(ImageFilter.GaussianBlur(.8))
    w,h=a.size; best=(2,None)
    for dx in range(-2,3):
        for dy in range(-2,3):
            bb=ImageChops.offset(b,dx,dy)
            r,g,b_=ImageChops.difference(a,bb).split(); d=ImageChops.lighter(ImageChops.lighter(r,g),b_)
            c=d.point(lambda v:255 if v>40 else 0)
            frac=sum(1 for v in c.getdata() if v)/(w*h) if not hasattr(c,'get_flattened_data') else sum(1 for v in c.get_flattened_data() if v)/(w*h)
            if frac<best[0]: best=(frac,c)
    return best
rows=[]
for wf in sorted(glob.glob('out/wf/*.png')):
    name=os.path.basename(wf); pr='out/prod/'+name
    if not os.path.exists(pr): rows.append((name,None,'missing prod')); continue
    a=Image.open(wf).convert('RGB'); b=Image.open(pr).convert('RGB')
    if abs(a.size[0]-b.size[0])>2 or abs(a.size[1]-b.size[1])>3:
        rows.append((name,1.0,f'size {a.size} vs {b.size}')); continue
    w=min(a.size[0],b.size[0]);h=min(a.size[1],b.size[1]); a=a.crop((0,0,w,h)); b=b.crop((0,0,w,h))
    frac,c=score(a,b); rows.append((name,frac,''))
    if frac>thr:
        s=Image.new('RGB',(w*3,h)); s.paste(a,(0,0)); s.paste(b,(w,0)); s.paste(c.convert('RGB'),(2*w,0)); s.save('out/side-'+name)
bad=sorted([r for r in rows if r[1] is None or r[1]>thr],key=lambda r:-(r[1] or 2))
for n,v,m in bad: print(f'{n:30} {"-" if v is None else round(v*100,2)}% {m}')
print(f'{len(rows)-len(bad)}/{len(rows)} within {thr*100}%')
