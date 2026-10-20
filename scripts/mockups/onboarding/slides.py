"""Builds the four onboarding slides (public/images/onboarding/*.svg): real Naara screens in a phone frame plus SVG callouts.
Steps: see scripts/mockups/README.md (throwaway sqlite app, capture.mjs with scripts/mockups/shots/onboarding.json), then:
  SHOTS=/tmp/mk/shots/ python3 scripts/mockups/onboarding/slides.py"""
import base64, io, os, re
from PIL import Image
import os; exec(open(os.path.join(os.path.dirname(__file__), 'art.py')).read().split('# ---------- 1: globe')[0])   # head(), sparkles(), colours
OUT='/home/user/NaaraSim/public/images/onboarding/'
SHOT=os.environ.get('SHOTS', '/tmp/mk/shots/')
FONT='system-ui,Segoe UI,Roboto,Arial,sans-serif'

def shot_uri(name):
    im=Image.open(SHOT+name+'.png').convert('RGB').resize((660,1428),Image.LANCZOS)
    b=io.BytesIO(); im.save(b,'WEBP',quality=80,method=6); return 'data:image/webp;base64,'+base64.b64encode(b.getvalue()).decode()

def phone(name, x=210, y=60, w=380):
    h=round(w*844/390)+44          # screen incl. our status strip; the real screen sits under it
    pad=14
    s=f'<g filter="url(#sh)"><rect x="{x-pad}" y="{y-pad}" width="{w+2*pad}" height="{h+2*pad}" rx="58" fill="#0b2236" stroke="{TEAL}" stroke-width="3.5"/></g>'
    s+=f'<clipPath id="scr"><rect x="{x}" y="{y}" width="{w}" height="{h}" rx="44"/></clipPath>'
    s+=f'<g clip-path="url(#scr)"><rect x="{x}" y="{y}" width="{w}" height="44" fill="#eef7f7"/>'
    s+=f'<image href="{shot_uri(name)}" x="{x}" y="{y+44}" width="{w}" height="{h-44}" preserveAspectRatio="xMidYMid slice"/></g>'
    s+=f'<rect x="{x+w/2-50}" y="{y+9}" width="100" height="27" rx="13.5" fill="#05101c"/>'
    s+=f'<text x="{x+30}" y="{y+29}" font-family="{FONT}" font-size="16" font-weight="700" fill="#0a172c">9:41</text>'
    s+=f'<g fill="#0a172c"><rect x="{x+w-84}" y="{y+22}" width="4" height="8" rx="1"/><rect x="{x+w-77}" y="{y+19}" width="4" height="11" rx="1"/><rect x="{x+w-70}" y="{y+16}" width="4" height="14" rx="1"/><rect x="{x+w-52}" y="{y+16}" width="28" height="14" rx="4.5" fill="none" stroke="#0a172c" stroke-width="1.7"/><rect x="{x+w-50}" y="{y+18}" width="20" height="10" rx="2.5"/></g>'
    return s

ICONS={
 'call':'M6.62,10.79c1.44,2.83 3.76,5.14 6.59,6.59l2.2,-2.2c0.27,-0.27 0.67,-0.36 1.02,-0.24 1.12,0.37 2.33,0.57 3.57,0.57 0.55,0 1,0.45 1,1V20c0,0.55 -0.45,1 -1,1 -9.39,0 -17,-7.61 -17,-17 0,-0.55 0.45,-1 1,-1h3.5c0.55,0 1,0.45 1,1 0,1.25 0.2,2.45 0.57,3.57 0.11,0.35 0.03,0.74 -0.25,1.02l-2.2,2.2z',
 'chat':'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z',
 'spark':'M12 2l2.4 6.6L21 11l-6.6 2.4L12 20l-2.4-6.6L3 11l6.6-2.4z',
 'users':'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z',
 'inbox':'M19 3H4.99C3.89 3 3 3.9 3 5l.01 14c0 1.1.89 2 1.99 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 12h-4c0 1.66-1.35 3-3 3s-3-1.34-3-3H4.99V5H19v10z',
 'lock':'M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM9 8V6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9z',
}
def pill(cx,cy,text,color,w=170,icon=None):
    s=f'<g transform="translate({cx} {cy})" filter="url(#sh)"><rect x="{-w/2}" y="-32" width="{w}" height="64" rx="32" fill="#0b2236" stroke="{color}" stroke-width="4"/>'
    s+=f'<circle cx="{-w/2+34}" cy="0" r="16" fill="{color}"/>'
    if icon=='bolt': s+=f'<path d="M{-w/2+36} -9L{-w/2+27} 2H{-w/2+34}L{-w/2+31} 10L{-w/2+41} -2H{-w/2+34}Z" fill="#0a172c"/>'
    if icon=='check': s+=f'<path d="M{-w/2+27} 0L{-w/2+33} 6L{-w/2+43} -6" fill="none" stroke="#0a172c" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>'
    if icon in ICONS: s+=f'<path d="{ICONS[icon]}" fill="#0a172c" transform="translate({-w/2+34-10} -10) scale(.83)"/>'
    s+=f'<text x="{-w/2+60}" y="9" font-family="{FONT}" font-size="25" font-weight="800" fill="#fff">{text}</text></g>'
    return s
def badge(cx,cy,t,color):
    return f'<g transform="translate({cx} {cy})" filter="url(#sh)"><circle r="34" fill="{color}" stroke="#0a172c" stroke-width="4"/><text y="9" text-anchor="middle" font-family="{FONT}" font-size="24" font-weight="800" fill="#0a172c">{t}</text></g>'
def callout(x,y,w,h,lines,fill='url(#card)',ink='#0a172c',sub='#35566a',tail='l'):
    s=f'<g transform="translate({x} {y})" filter="url(#sh)"><rect width="{w}" height="{h}" rx="26" fill="{fill}"/>'
    ty=h/2-(len(lines)-1)*16+9
    for i,(t,size,wt,col) in enumerate(lines):
        s+=f'<text x="{w/2}" y="{ty+i*34}" text-anchor="middle" font-family="{FONT}" font-size="{size}" font-weight="{wt}" fill="{col or ink}">{t}</text>'
    return s+'</g>'
def bars(x,y,n=4,on=4,color=GOLD):
    s=''
    for i in range(n):
        hh=14+i*12; s+=f'<rect x="{x+i*20}" y="{y+(50-hh)}" width="13" height="{hh}" rx="4" fill="{color if i<on else "#ffffff"}" opacity="{1 if i<on else .3}"/>'
    return s
def scene1():
    s=phone('onb-1-destination')
    s+=pill(570,170,'190+ countries',GOLD,w=290,icon='bolt')
    s+=badge(130,320,'FR',TEAL)+badge(104,430,'JP',ORANGE)+badge(150,540,'ZA',GOLD)
    s+=f'<path d="M170 322Q200 322 226 352M144 430Q180 430 226 416M188 540Q206 520 226 476" fill="none" stroke="{GOLD}" stroke-width="4" stroke-linecap="round" stroke-dasharray="2 10"/>'
    s+=f'<g transform="translate(640 640) rotate(-20)" filter="url(#sh)"><path d="M-34 0L34 -4L12 -22L20 -22L54 -3L20 16L12 16L34 5L-34 9Z" fill="#fff" stroke="#0a172c" stroke-width="3" stroke-linejoin="round"/></g>'
    return s
def scene2():
    s=phone('onb-2-plan')
    s+=f'<g transform="translate(505 190)" filter="url(#sh)"><rect width="215" height="150" rx="26" fill="url(#card)"/><text x="24" y="78" font-family="{FONT}" font-size="58" font-weight="800" fill="#0a172c">5<tspan font-size="30" dx="6">GB</tspan></text><text x="24" y="118" font-family="{FONT}" font-size="26" font-weight="700" fill="#35566a">30 days</text></g>'
    s+=bars(641,222)
    s+=pill(615,420,'Instant',GOLD,w=200,icon='bolt')
    s+=pill(205,300,'No SIM swap',TEAL,w=240,icon='check')
    s+=f'<g transform="translate(140 620)" filter="url(#sh)"><rect x="-56" y="-44" width="112" height="88" rx="16" fill="url(#brand)"/><rect x="-30" y="-24" width="60" height="48" rx="8" fill="#0a172c" opacity=".85"/><path d="M-30 -8H30M-30 8H30M-10 -24V24M10 -24V24" stroke="{GOLD}" stroke-width="3"/></g>'
    return s
def scene3():
    s=phone('onb-3-activate')
    # scan frame placed on the QR code of the real screenshot
    cx=210+0.46*380; cy=60+44+0.41*(round(380*844/390)); half=0.39*380/2+14
    for (sx,sy) in [(-1,-1),(1,-1),(-1,1),(1,1)]:
        px,py=cx+sx*half,cy+sy*half
        s+=f'<path d="M{px} {py-30*sy}V{py}H{px-30*sx}" fill="none" stroke="{GOLD}" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>'
    s+=pill(565,170,'Scan to install',TEAL,w=300,icon='check')
    s+=f'<g transform="translate(636 640)" filter="url(#sh)"><circle r="64" fill="#1E9E5A"/><path d="M-27 2L-8 21L29 -19" fill="none" stroke="#fff" stroke-width="13" stroke-linecap="round" stroke-linejoin="round"/></g>'
    s+=f'<g transform="translate(150 500) rotate(-14)" filter="url(#sh)"><rect x="-52" y="-64" width="104" height="128" rx="14" fill="url(#brand)"/><rect x="-28" y="-30" width="56" height="52" rx="8" fill="#0a172c" opacity=".85"/><path d="M-28 -14H28M-28 4H28M-9 -30V22M9 -30V22" stroke="{GOLD}" stroke-width="3"/></g>'
    return s
def scene4():
    s=phone('onb-4-numbers')
    s+=f'<g transform="translate(480 200)" filter="url(#sh)"><rect width="240" height="132" rx="26" fill="url(#teal)"/><text x="120" y="44" text-anchor="middle" font-family="{FONT}" font-size="19" font-weight="700" letter-spacing="3" fill="#bfeee9">YOUR CODE</text><text x="120" y="104" text-anchor="middle" font-family="ui-monospace,Menlo,Consolas,monospace" font-size="50" font-weight="800" letter-spacing="4" fill="#fff">482917</text></g>'
    for i,(t,c) in enumerate([('+234',GOLD),('+1',TEAL),('+44',ORANGE)]):
        s+=pill(630,420+i*86,t,c,w=170)
    s+=f'<g transform="translate(142 520)" filter="url(#sh)"><path d="M0 -70L56 -48V6C56 40 30 62 0 76C-30 62 -56 40 -56 6V-48Z" fill="url(#teal)" stroke="#bfeee9" stroke-width="3"/><path d="M-22 2L-6 18L24 -16" fill="none" stroke="#fff" stroke-width="11" stroke-linecap="round" stroke-linejoin="round"/></g>'
    return s
def scene5():
    s=phone('onb-5-chat')
    s+=pill(570,170,'Your Naara ID',TEAL,w=290,icon='users')
    s+=f'<g transform="translate(95 330)" filter="url(#sh)"><path d="M0 0h210a28 28 0 0 1 28 28v34a28 28 0 0 1-28 28H70L34 122l6-32H0a28 28 0 0 1-28-28V28A28 28 0 0 1 0 0Z" fill="url(#card)"/><text x="105" y="56" text-anchor="middle" font-family="{FONT}" font-size="27" font-weight="700" fill="#0a172c">See you at 5?</text><path d="M168 76l8 8 14-16M180 76l8 8 14-16" fill="none" stroke="{TEAL}" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></g>'
    s+=pill(565,470,'Voice and video',GOLD,w=300,icon='call')
    s+=pill(185,640,'Private',ORANGE,w=200,icon='lock')
    return s
ICONS='/home/user/NaaraSim/node_modules/simple-icons/icons/'
def brand_logo(name,color,cx,cy,r=19):
    """Brand disc with the real glyph (simple-icons path), white on brand colour; SMS is a drawn bubble."""
    if name=='Instagram':
        disc=f'<defs><linearGradient id="igg" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#FEDA75"/><stop offset=".35" stop-color="#FA7E1E"/><stop offset=".6" stop-color="#D62976"/><stop offset="1" stop-color="#4F5BD5"/></linearGradient></defs><circle cx="{cx}" cy="{cy}" r="{r}" fill="url(#igg)"/>'
    else:
        disc=f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{color}"/>'
    if name=='SMS':
        return disc+f'<path transform="translate({cx-10} {cy-10})" d="M3 2h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H8l-4 3.5V15H3a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" fill="#fff"/><circle cx="6" cy="8.5" r="1.3" fill="{color}"/><circle cx="10" cy="8.5" r="1.3" fill="{color}"/><circle cx="14" cy="8.5" r="1.3" fill="{color}"/>'
    d=re.search(r' d="([^"]+)"',open(ICONS+name.lower()+'.svg').read()).group(1)
    k=(r*1.05)/12
    return disc+f'<path transform="translate({cx-12*k:.2f} {cy-12*k:.2f}) scale({k:.3f})" d="{d}" fill="#fff"/>'
def scene6():
    s=phone('onb-6-business')
    s+=pill(570,170,'One shared inbox',TEAL,w=290,icon='inbox')
    for i,(t,c) in enumerate([('WhatsApp','#25D366'),('Instagram','#E1306C'),('Telegram','#2AABEE'),('SMS',GOLD)]):
        s+=f'<g transform="translate(615 {330+i*82})" filter="url(#sh)"><rect x="-95" y="-30" width="190" height="60" rx="30" fill="#0b2236" stroke="{c}" stroke-width="4"/>{brand_logo(t,c,-62,0)}<text x="-36" y="9" font-family="{FONT}" font-size="23" font-weight="800" fill="#fff">{t}</text></g>'
    s+=pill(192,420,'Your team',GOLD,w=215,icon='users')
    s+=pill(208,560,'AI assistant',TEAL,w=246,icon='spark')
    s+=pill(195,700,'Call flows',ORANGE,w=220,icon='call')
    return s
slides=[('slide-1-destination','Choose where you are going',scene1,21),('slide-2-plan','Pick a data plan',scene2,22),('slide-3-activate','Activate in seconds',scene3,23),('slide-4-numbers','Numbers and calls',scene4,24),('slide-5-n2n-chat','Chat and call on Naara',scene5,25),('slide-6-business-suite','One inbox for your business',scene6,26)]
for name,title,fn,seed in slides:
    svg=head(title)+sparkles(seed)+fn()+'</svg>'
    svg=svg.replace('id="scr"','id="scr'+name[-1]+'"') if False else svg
    open(OUT+name+'.svg','w').write(svg); print(name,len(svg)//1024,'KB')
