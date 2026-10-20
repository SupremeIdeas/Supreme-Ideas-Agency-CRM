import math, random
OUT='/home/user/NaaraSim/public/images/onboarding/'
W,H=800,900
NAVY1,NAVY2='#12385a','#0a172c'
TEAL,TEAL2,GOLD,ORANGE,WHITE='#13A39B','#0A6E6E','#F5B82E','#F0561D','#FFFFFF'

def head(title):
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" role="img" aria-label="{title}">
<defs>
<radialGradient id="bg" cx="50%" cy="42%" r="75%"><stop offset="0" stop-color="{NAVY1}"/><stop offset="1" stop-color="{NAVY2}"/></radialGradient>
<radialGradient id="glow" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="{GOLD}" stop-opacity=".28"/><stop offset=".55" stop-color="{TEAL}" stop-opacity=".16"/><stop offset="1" stop-color="{TEAL}" stop-opacity="0"/></radialGradient>
<linearGradient id="brand" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0aa6b5"/><stop offset=".55" stop-color="#f7d117"/><stop offset="1" stop-color="{ORANGE}"/></linearGradient>
<linearGradient id="card" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#dbe9ee"/></linearGradient>
<linearGradient id="teal" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{TEAL}"/><stop offset="1" stop-color="{TEAL2}"/></linearGradient>
<filter id="sh" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="14" stdDeviation="16" flood-color="#000" flood-opacity=".35"/></filter>
</defs>
<rect width="{W}" height="{H}" fill="url(#bg)"/>
<circle cx="400" cy="400" r="380" fill="url(#glow)"/>
'''
def sparkles(seed):
    r=random.Random(seed); s=''
    for _ in range(26):
        x=r.randint(30,W-30); y=r.randint(40,H-260); rad=r.choice([1.5,2,2.5,3])
        s+=f'<circle cx="{x}" cy="{y}" r="{rad}" fill="{GOLD if r.random()<.5 else TEAL}" opacity="{r.choice([.35,.5,.7])}"/>'
    return s

# ---------- 1: globe
def pip(pt,poly):
    x,y=pt; inside=False; n=len(poly)
    for i in range(n):
        x1,y1=poly[i]; x2,y2=poly[(i+1)%n]
        if (y1>y)!=(y2>y) and x < (x2-x1)*(y-y1)/(y2-y1)+x1: inside=not inside
    return inside
LAND={
'africa':[(-17,21),(-10,30),(-6,36),(10,37),(11,33),(20,32),(32,31),(34,28),(38,18),(43,12),(51,12),(48,5),(40,-3),(40,-15),(35,-24),(32,-29),(27,-34),(20,-35),(17,-30),(12,-17),(13,-6),(9,-1),(9,4),(5,5),(-8,4),(-13,8),(-17,14)],
'europe':[(-10,36),(-9,43),(-1,46),(-5,48),(2,51),(8,54),(10,58),(5,62),(15,69),(30,70),(40,66),(30,60),(28,52),(40,47),(30,45),(25,41),(28,37),(20,40),(15,38),(12,44),(3,43)],
'asia':[(35,31),(36,36),(40,41),(50,42),(60,56),(80,70),(105,72),(115,55),(105,30),(90,22),(80,10),(72,20),(60,25),(57,24),(52,16),(44,12),(38,18),(34,28)],
'samerica':[(-35,-5),(-50,0),(-60,10),(-70,10),(-78,-5),(-72,-30),(-62,-42),(-50,-30),(-40,-22)],
}
CLAT,CLON=8,18
def proj(lat,lon,R,cx,cy):
    la,lo=math.radians(lat),math.radians(lon); la0,lo0=math.radians(CLAT),math.radians(CLON)
    cosc=math.sin(la0)*math.sin(la)+math.cos(la0)*math.cos(la)*math.cos(lo-lo0)
    if cosc<0: return None
    x=R*math.cos(la)*math.sin(lo-lo0)
    y=R*(math.cos(la0)*math.sin(la)-math.sin(la0)*math.cos(la)*math.cos(lo-lo0))
    return cx+x, cy-y
def globe():
    cx,cy,R=400,400,292
    s=f'<g filter="url(#sh)"><circle cx="{cx}" cy="{cy}" r="{R}" fill="#0b3550"/></g><circle cx="{cx}" cy="{cy}" r="{R}" fill="none" stroke="{TEAL}" stroke-opacity=".5" stroke-width="3"/>'
    for lat in range(-75,90,4):
        for lon in range(-75,125,4):
            p=proj(lat,lon,R-6,cx,cy)
            if not p: continue
            land=any(pip((lon,lat),poly) for poly in LAND.values())
            if land: s+=f'<circle cx="{p[0]:.1f}" cy="{p[1]:.1f}" r="4.4" fill="{TEAL}"/>'
            else: s+=f'<circle cx="{p[0]:.1f}" cy="{p[1]:.1f}" r="1.8" fill="#ffffff" opacity=".16"/>'
    # routes from Lagos
    def pt(lat,lon): return proj(lat,lon,R-6,cx,cy)
    home=pt(6.5,3.4)
    dests=[(51.5,-0.1),(25.2,55.3),(-26.2,28.0),(40.7,-74.0)]
    for d in dests:
        q=pt(*d)
        if not q: continue
        mx,my=(home[0]+q[0])/2,(home[1]+q[1])/2-70
        s+=f'<path d="M{home[0]:.1f} {home[1]:.1f} Q{mx:.1f} {my:.1f} {q[0]:.1f} {q[1]:.1f}" fill="none" stroke="{GOLD}" stroke-width="4" stroke-linecap="round" stroke-dasharray="2 12"/>'
    def pin(p,c=GOLD):
        x,y=p
        return f'<g transform="translate({x:.1f} {y:.1f})"><path d="M0 0C-26 -34 -22 -62 0 -62C22 -62 26 -34 0 0Z" fill="{c}" stroke="#0a172c" stroke-width="3"/><circle cx="0" cy="-42" r="9" fill="#0a172c"/></g>'
    for d in dests[:3]:
        q=pt(*d)
        if q: s+=pin(q)
    s+=pin(home,ORANGE)
    # plane on first arc
    q=pt(*dests[0]); mx,my=(home[0]+q[0])/2,(home[1]+q[1])/2-70
    px,py=0.25*home[0]+0.5*mx+0.25*q[0], 0.25*home[1]+0.5*my+0.25*q[1]
    s+=f'<g transform="translate({px:.1f} {py:.1f}) rotate(-35)"><path d="M-26 0L26 -3L8 -16L14 -16L40 -2L14 12L8 12L26 3L-26 6Z" fill="{WHITE}" stroke="#0a172c" stroke-width="2.5" stroke-linejoin="round"/></g>'
    # orbit ring
    s+=f'<ellipse cx="{cx}" cy="{cy}" rx="385" ry="110" transform="rotate(-18 {cx} {cy})" fill="none" stroke="{GOLD}" stroke-opacity=".55" stroke-width="3" stroke-dasharray="3 14" stroke-linecap="round"/>'
    return s
# ---------- 2: plans
def card(x,y,rot,gb,days,fill,ink,sub,bars):
    s=f'<g transform="translate({x} {y}) rotate({rot})" filter="url(#sh)"><rect x="-210" y="-125" width="420" height="250" rx="30" fill="{fill}"/>'
    s+=f'<text x="-170" y="-62" font-family="system-ui,Segoe UI,Roboto,Arial,sans-serif" font-size="30" font-weight="700" fill="{sub}">{days}</text>'
    s+=f'<text x="-170" y="30" font-family="system-ui,Segoe UI,Roboto,Arial,sans-serif" font-size="92" font-weight="800" fill="{ink}">{gb}<tspan font-size="40" dx="8">GB</tspan></text>'
    for i in range(4):
        h=14+i*12
        s+=f'<rect x="{110+i*22}" y="{-52+ (48-h)}" width="14" height="{h}" rx="4" fill="{GOLD if i<bars else ink}" opacity="{1 if i<bars else .25}"/>'
    s+=f'<rect x="-170" y="70" width="120" height="14" rx="7" fill="{ink}" opacity=".18"/><rect x="-170" y="70" width="86" height="14" rx="7" fill="url(#brand)"/>'
    s+='</g>'
    return s
def plans():
    s=card(400,232,-9,1,'7 DAYS','#0d4a63',WHITE,'#9fd4dc',2)
    s+=card(400,420,6,10,'30 DAYS','#0A6E6E',WHITE,'#bfeee9',3)
    s+=card(400,590,-2,5,'30 DAYS','url(#card)','#0a172c','#35566a',4)
    # chip + sparkle
    s+=f'<g transform="translate(640 720)" filter="url(#sh)"><rect x="-56" y="-44" width="112" height="88" rx="16" fill="url(#brand)"/><rect x="-30" y="-24" width="60" height="48" rx="8" fill="#0a172c" opacity=".85"/><path d="M-30 -8H30M-30 8H30M-10 -24V24M10 -24V24" stroke="{GOLD}" stroke-width="3"/></g>'
    s+=f'<g transform="translate(170 720)"><circle r="46" fill="{GOLD}"/><path d="M-18 4L-4 18L20 -14" fill="none" stroke="#0a172c" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/></g>'
    return s
# ---------- 3: activate (QR)
def qr(x,y,n,cell,seed):
    r=random.Random(seed); s=''
    def finder(ox,oy):
        return f'<rect x="{x+ox*cell}" y="{y+oy*cell}" width="{7*cell}" height="{7*cell}" fill="#0a172c"/><rect x="{x+(ox+1)*cell}" y="{y+(oy+1)*cell}" width="{5*cell}" height="{5*cell}" fill="#fff"/><rect x="{x+(ox+2)*cell}" y="{y+(oy+2)*cell}" width="{3*cell}" height="{3*cell}" fill="#0a172c"/>'
    for i in range(n):
        for j in range(n):
            if (i<8 and j<8) or (i>=n-8 and j<8) or (i<8 and j>=n-8): continue
            if r.random()<.5: s+=f'<rect x="{x+i*cell}" y="{y+j*cell}" width="{cell}" height="{cell}" fill="#0a172c"/>'
    return s+finder(0,0)+finder(n-7,0)+finder(0,n-7)
def activate():
    s=f'<g filter="url(#sh)"><rect x="235" y="120" width="330" height="640" rx="54" fill="#0b2236" stroke="{TEAL}" stroke-width="4"/><rect x="251" y="140" width="298" height="600" rx="40" fill="#f4fbfb"/><rect x="345" y="152" width="110" height="26" rx="13" fill="#0b2236"/></g>'
    s+=f'<text x="400" y="232" text-anchor="middle" font-family="system-ui,Segoe UI,Roboto,Arial,sans-serif" font-size="26" font-weight="700" fill="#0a172c">Scan to install</text>'
    s+=qr(288,262,21,10,7)
    # scan frame corners
    for (cx,cy,sx,sy) in [(278,252,1,1),(522,252,-1,1),(278,486,1,-1),(522,486,-1,-1)]:
        s+=f'<path d="M{cx} {cy+34*sy}V{cy}H{cx+34*sx}" fill="none" stroke="{GOLD}" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>'
    s+=f'<rect x="270" y="372" width="260" height="5" rx="3" fill="{ORANGE}" opacity=".9"/>'
    s+=f'<rect x="283" y="540" width="234" height="62" rx="31" fill="url(#teal)"/><text x="400" y="581" text-anchor="middle" font-family="system-ui,Segoe UI,Roboto,Arial,sans-serif" font-size="26" font-weight="700" fill="#fff">Install eSIM</text>'
    s+=f'<rect x="283" y="628" width="234" height="12" rx="6" fill="#0a172c" opacity=".12"/><rect x="283" y="652" width="160" height="12" rx="6" fill="#0a172c" opacity=".12"/>'
    # signal waves
    for i,r in enumerate((60,96,132)):
        s+=f'<path d="M{610-r*0.0} {260} " />'
    for i,r in enumerate((46,82,118)):
        s+=f'<path d="M{590} {300-r} A{r} {r} 0 0 1 {590+r} {300}" fill="none" stroke="{GOLD}" stroke-width="9" stroke-linecap="round" opacity="{1-i*.22}" transform="rotate(8 590 300)"/>'
    s+=f'<circle cx="590" cy="300" r="12" fill="{GOLD}"/>'
    # check badge
    s+=f'<g transform="translate(600 640)" filter="url(#sh)"><circle r="62" fill="#1E9E5A"/><path d="M-26 2L-8 20L28 -18" fill="none" stroke="#fff" stroke-width="13" stroke-linecap="round" stroke-linejoin="round"/></g>'
    # sim chip floating left
    s+=f'<g transform="translate(150 520) rotate(-14)" filter="url(#sh)"><rect x="-52" y="-64" width="104" height="128" rx="14" fill="url(#brand)"/><rect x="-28" y="-30" width="56" height="52" rx="8" fill="#0a172c" opacity=".85"/><path d="M-28 -14H28M-28 4H28M-9 -30V22M9 -30V22" stroke="{GOLD}" stroke-width="3"/></g>'
    return s
# ---------- 4: numbers & calls
def numbers():
    s=f'<g filter="url(#sh)"><rect x="255" y="130" width="290" height="600" rx="50" fill="#0b2236" stroke="{TEAL}" stroke-width="4"/><rect x="270" y="148" width="260" height="564" rx="38" fill="#f4fbfb"/><rect x="350" y="158" width="100" height="24" rx="12" fill="#0b2236"/></g>'
    s+=f'<rect x="292" y="215" width="216" height="130" rx="22" fill="url(#teal)"/><text x="400" y="255" text-anchor="middle" font-family="system-ui,Segoe UI,Roboto,Arial,sans-serif" font-size="19" font-weight="700" letter-spacing="3" fill="#bfeee9">YOUR CODE</text>'
    s+=f'<text x="400" y="320" text-anchor="middle" font-family="ui-monospace,Menlo,Consolas,monospace" font-size="52" font-weight="800" letter-spacing="6" fill="#fff">482917</text>'
    for i in range(3):
        for j in range(3):
            s+=f'<circle cx="{340+i*60}" cy="{420+j*62}" r="19" fill="#0a172c" opacity=".12"/>'
    s+=f'<circle cx="400" cy="640" r="44" fill="#1E9E5A"/><path d="M6.62,10.79c1.44,2.83 3.76,5.14 6.59,6.59l2.2,-2.2c0.27,-0.27 0.67,-0.36 1.02,-0.24 1.12,0.37 2.33,0.57 3.57,0.57 0.55,0 1,0.45 1,1V20c0,0.55 -0.45,1 -1,1 -9.39,0 -17,-7.61 -17,-17 0,-0.55 0.45,-1 1,-1h3.5c0.55,0 1,0.45 1,1 0,1.25 0.2,2.45 0.57,3.57 0.11,0.35 0.03,0.74 -0.25,1.02l-2.2,2.2z" fill="#fff" transform="translate(374 614) scale(2.17)"/>'
    # chat bubble left
    s+=f'<g transform="translate(120 470)" filter="url(#sh)"><path d="M0 0h170a30 30 0 0 1 30 30v50a30 30 0 0 1-30 30H60L24 142l6-32H0a30 30 0 0 1-30-30V30A30 30 0 0 1 0 0Z" fill="url(#card)"/><rect x="-6" y="26" width="120" height="14" rx="7" fill="#0a172c" opacity=".7"/><rect x="-6" y="56" width="170" height="12" rx="6" fill="#0a172c" opacity=".18"/><rect x="-6" y="80" width="110" height="12" rx="6" fill="#0a172c" opacity=".18"/></g>'
    # country-code pills right
    for i,(t,c) in enumerate([('+234',GOLD),('+1',TEAL),('+44',ORANGE)]):
        y=250+i*92
        s+=f'<g transform="translate(600 {y})" filter="url(#sh)"><rect x="-60" y="-30" width="130" height="60" rx="30" fill="#0b2236" stroke="{c}" stroke-width="4"/><circle cx="-30" cy="0" r="12" fill="{c}"/><text x="-8" y="9" font-family="system-ui,Segoe UI,Roboto,Arial,sans-serif" font-size="26" font-weight="800" fill="#fff">{t}</text></g>'
    # shield
    s+=f'<g transform="translate(610 600)" filter="url(#sh)"><path d="M0 -70L56 -48V6C56 40 30 62 0 76C-30 62 -56 40 -56 6V-48Z" fill="url(#teal)" stroke="#bfeee9" stroke-width="3"/><path d="M-22 2L-6 18L24 -16" fill="none" stroke="#fff" stroke-width="11" stroke-linecap="round" stroke-linejoin="round"/></g>'
    return s

slides=[('slide-1-destination','Choose where you are going',globe,11),('slide-2-plan','Pick a data plan',plans,12),('slide-3-activate','Activate in seconds',activate,13),('slide-4-numbers','Numbers and calls',numbers,14)]
for name,title,fn,seed in slides:
    svg=head(title)+sparkles(seed)+'<g transform="translate(40 -8) scale(.9)">'+fn()+'</g></svg>'
    open(OUT+name+'.svg','w').write(svg); print(name,len(svg)//1024,'KB')
