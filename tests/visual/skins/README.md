# Skin verification harness (Prompt 20 §28, §42)

Not part of phpunit (it needs a browser and a running app). Run it after any change to `resources/css/nx-*.css`, `scripts/nx_port.py` or the
wireframes, with a built app (`npm run build`) serving on `BASE` (default `http://127.0.0.1:8099`), a verified member (`NX_EMAIL`,
`NX_PASSWORD`; default `nx-diff@naara.test` / `password`) and every skin enabled in `appearance_presets`.

| Script | What it proves |
|---|---|
| `node shoot.cjs` then `python3 diff.py 0.02` | **Fidelity.** Renders each wireframe skin's preview card and the full preview panel in light and dark, and the same thing in production, with the same forced font. The diff blurs 0.8px, allows a 2px shift and compares colour, so a wrong colour fails and anti-aliasing does not. `ONLY=neo,glass` limits it. |
| `node contrast.cjs` then `python3 contrast.py all` | **Contrast.** 35 skins x 6 accents x 2 modes: hides the text, samples the real pixels behind every text node and compares with its real colour (AA, 3:1 for large text). `SKINS=` / `ACCENTS=` limit it. |

| `node contrast-pages.cjs` then `python3 contrast-pages.py all` | **Contrast on real pages.** Same pixel scan over the converted screens (`URLS=` to change the list). Run it again after converting each batch. |

Expected differences are listed in `docs/appearance/CONTRAST-DEVIATIONS.md` (five dark minis show the page's dark palette on purpose).
Output goes to `./out` (git-ignored).
