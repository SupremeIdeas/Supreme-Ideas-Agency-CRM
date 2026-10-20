import json,collections
d=json.load(open('out/matrix.json')); res=d['res']; errs=d['errs']
rows=[r for r in res if r['skin'] not in ('*',) and not r['skin'].startswith('*')]
print('checks:',len(rows),'| redirects:',[r for r in res if r.get('error')][:3])
print('JS/console errors per URL:',{k.split('8099')[-1]:len(v) for k,v in errs.items()}); 
for k,v in list(errs.items())[:6]: print('  ',k.split('8099')[-1],set(v))
ov=[r for r in rows if r['overflowX']]
print('page-level horizontal overflow:',len(ov)); 
c=collections.Counter((r['url'],r['w']) for r in ov); print('  by page/width:',dict(c))
cs=collections.Counter(r['skin'] for r in ov); print('  by skin:',dict(cs))
pk=collections.defaultdict(list)
for r in rows:
    if r['poking']>0: pk[(r['url'],r['w'])].append(r['skin'])
for k,v in pk.items(): print('poking',k,len(v),'skins e.g.',v[:4])
for r in res:
    if r['skin']=='*distinct-canvases': print('canvases',r['mode'],r['w'],r['url'],r['n'])
