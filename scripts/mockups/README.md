# Marketing mockup pipeline

Real dashboard screens, placed inside device mockups, used as the images on marketing pages and blog posts.

| Piece | What it is |
|---|---|
| `resources/mockups/library/*.jpg` | The owner's 20 device mockup photos (720px wide). Source only, never served. |
| `resources/mockups/library.json` | Per mockup: kind (phone/tablet/desktop), the screen's 4 corners in source pixels, corner radius, occluders (a phone or finger in front of the screen), blemishes to heal. `calibrated:false` = catalogued, screen not measured yet. |
| `scripts/mockups/grid.py` | Draws a labelled grid over a mockup so the screen corners can be read off. |
| `scripts/mockups/seed-demo.php` | Invents one member + a catalogue + one eSIM in a THROWAWAY sqlite database, so screens look lived-in. Never run against a real database. |
| `scripts/mockups/capture.mjs` | Playwright: logs in and snaps a screen at the mockup's exact aspect (390x844 phone, 16:10 desktop), hiding the wizard/journey bubbles and the splash. |
| `scripts/mockups/compose.py` | Warps the screenshot into the screen quad (true perspective), adds status bar / island / home bar for phones, puts back anything in front of the screen, writes WebP. |

Output lands in `public/images/marketing/mockups/<page>/` (committed, tens of KB, never hotlinked).

## Re-making an image

```bash
# 1. throwaway app
DB_CONNECTION=sqlite DB_DATABASE=/tmp/mockdemo.sqlite php artisan migrate --force && php artisan db:seed --force
DB_CONNECTION=sqlite DB_DATABASE=/tmp/mockdemo.sqlite php scripts/mockups/seed-demo.php
DB_CONNECTION=sqlite DB_DATABASE=/tmp/mockdemo.sqlite CACHE_STORE=file SESSION_DRIVER=file php artisan serve --port=8766 &
# 2. snap (see the header of capture.mjs for the shots.json shape)
BASE=http://127.0.0.1:8766 node scripts/mockups/capture.mjs shots.json /tmp/mk/shots
# 3. place it
python3 scripts/mockups/compose.py --mock 04 --shot /tmp/mk/shots/home-step-1.png --out public/images/marketing/mockups/home/step-1-destination.webp --scale 1
```

`shots.json` for every image that has shipped lives in `scripts/mockups/shots/<page>.json`, so a changed dashboard can be re-snapped in one go.

## Calibrating a new mockup
1. `python3 scripts/mockups/grid.py resources/mockups/library/NN-name.jpg /tmp/grid.png`, read the four screen corners (TL, TR, BR, BL).
2. Add the entry to `library.json` with `calibrated:true`, render a test shot, look at the edges, nudge by a pixel or two.
3. A phone or finger in front of the screen goes in `occlude` (a rounded rect or polygon): those pixels are restored from the original photo.

## Rules
- Master only unless the page exists in the forks too; the screens show Naara product UI only, never a master-only module (N2N, Widgets, NaaraBrain, Business Suite) on a page a fork can serve.
- Never show cost, a real person, or real data: the demo member, prices and QR code are invented.
- Dark/light: the photo carries its own backdrop, so it sits in a rounded panel rather than floating on the card.

## Onboarding slides (installed app, first run)
`scripts/mockups/shots/onboarding.json` + `scripts/mockups/onboarding/slides.py` build the four slides in `public/images/onboarding/` (real screens in a phone frame, SVG callouts around it). In the throwaway app, first set the brand logos to the current Naara wordmark (`brand.logo_family_light/dark` and `brand.logo_product_light/dark` = `/brand/naara-logo-light.webp` / `-dark.webp`), then capture and run `SHOTS=/tmp/mk/shots/ python3 scripts/mockups/onboarding/slides.py`.
