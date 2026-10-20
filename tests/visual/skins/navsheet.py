import json,sys
from PIL import Image,ImageDraw
skins=json.load(open('out/nav/skins.json'))
for mode in ['light','dark']:
    cols=3;rows=(len(skins)+cols-1)//cols
    sheet=Image.new('RGB',(cols*430,rows*130),(128,128,128));d=ImageDraw.Draw(sheet)
    for i,k in enumerate(skins):
        im=Image.open(f'out/nav/{k}-{mode}.png');x,y=(i%cols)*430,(i//cols)*130
        sheet.paste(im,(x,y+20));d.text((x+6,y+4),k,fill=(255,255,0))
    sheet.save(f'out/nav/sheet-{mode}.png')
