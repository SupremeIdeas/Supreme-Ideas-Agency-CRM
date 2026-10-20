# GIF and sticker generator

The GIFs and animated stickers shipped in `public/images/gifs/` are original artwork drawn by these two scripts (Pillow only; no third-party images, so they are free to ship).

    pip install pillow
    python3 tools/gif-generator/cards.py      # 48 text cards: funny, love, business, celebrate, thanks, greetings, reactions, motivation
    python3 tools/gif-generator/stickers.py   # 31 animated stickers (public/images/gifs/stickers)
    php artisan gifs:seed                     # register new files in the library (the migration does this on a fresh install)

Add or change a card by editing its entry in `LIB` (cards) or `FACES` / `OBJ` (stickers). Admins can add their own GIFs from Admin > GIF library without touching these scripts.
