#!/usr/bin/env bash
# Rebuild EVERY marketing mockup image from the live dashboard (master). One run after a dashboard change or a demo-data change.
#   DB_CONNECTION=sqlite DB_DATABASE=/tmp/mockdemo.sqlite php artisan migrate --force && php artisan db:seed --force
#   DB_CONNECTION=sqlite DB_DATABASE=/tmp/mockdemo.sqlite php scripts/mockups/seed-demo.php
#   DB_CONNECTION=sqlite DB_DATABASE=/tmp/mockdemo.sqlite CACHE_STORE=file SESSION_DRIVER=file php artisan serve --port=8766 &
#   BASE=http://127.0.0.1:8766 bash scripts/mockups/build-all.sh
set -euo pipefail
cd "$(dirname "$0")/../.."
S=${SHOTS:-/tmp/mk/shots}; O=public/images/marketing/mockups
mkdir -p "$S"
for f in home how-it-works products pricing blog; do node scripts/mockups/capture.mjs scripts/mockups/shots/$f.json "$S" >/dev/null; done
c() { python3 scripts/mockups/compose.py "$@" >/dev/null; }
# home steps + features desktop
c --mock 04 --shot $S/home-step-1.png --out $O/home/step-1-destination.webp --scale 1
c --mock 13 --shot $S/home-step-2.png --out $O/home/step-2-plan.webp --scale 1
c --mock 15 --shot $S/home-step-3.png --out $O/home/step-3-activate.webp --scale 1
c --mock 17 --shot $S/home-features-desktop.png --crop 0,70,720,600 --out $O/home/features-desktop.webp --scale 2 --q 80
# how it works (7 steps)
c --mock 15 --shot $S/hiw-1-device.png    --out $O/how-it-works/step-1-check-device.webp --scale 1
c --mock 04 --shot $S/hiw-2-plans.png     --out $O/how-it-works/step-2-choose-plan.webp --scale 1
c --mock 13 --shot $S/hiw-3-buy.png       --out $O/how-it-works/step-3-purchase.webp --scale 1
c --mock 15 --shot $S/hiw-4-install.png   --out $O/how-it-works/step-4-install.webp --scale 1
c --mock 04 --shot $S/hiw-5-active.png    --out $O/how-it-works/step-5-data-sim.webp --scale 1
c --mock 13 --shot $S/hiw-6-connected.png --out $O/how-it-works/step-6-connect.webp --scale 1
c --mock 15 --shot $S/hiw-7-topup.png     --out $O/how-it-works/step-7-top-up.webp --scale 1
# products, about, pricing
c --mock 04 --shot $S/prod-data.png   --out $O/products/naara-data.webp --scale 1
c --mock 13 --shot $S/prod-verify.png --out $O/products/naara-verify.webp --scale 1
c --mock 15 --shot $S/prod-rent.png   --out $O/products/naara-rent.webp --scale 1
c --mock 12 --shot $S/hiw-7-topup.png --shot $S/hiw-3-buy.png --shot $S/hiw-4-install.png --out $O/about/journey-trio.webp --scale 1.2 --q 78
c --mock 15 --shot $S/pricing-estimator.png --out $O/pricing/data-estimator.webp --scale 1
# blog
c --mock 15 --shot $S/blog-install-qr.png     --out $O/blog/how-to-install-esim-step-by-step-guide-1.webp --scale 1
c --mock 04 --shot $S/blog-install-active.png --out $O/blog/how-to-install-esim-step-by-step-guide-2.webp --scale 1
c --mock 13 --shot $S/blog-verify.png         --out $O/blog/sms-verification-numbers-explained-otp-vs-rental-1.webp --scale 1
c --mock 15 --shot $S/blog-rent.png           --out $O/blog/sms-verification-numbers-explained-otp-vs-rental-2.webp --scale 1
c --mock 04 --shot $S/blog-wallet.png         --out $O/blog/ngn-or-usd-dual-currency-wallet-explained-1.webp --scale 1
c --mock 13 --shot $S/blog-rewards.png        --out $O/blog/how-naaracredits-loyalty-rewards-work-1.webp --scale 1
c --mock 04 --shot $S/blog-compat.png         --out $O/blog/which-phones-support-esim-compatibility-list-1.webp --scale 1
c --mock 15 --shot $S/blog-shared.png         --out $O/blog/group-trip-connectivity-splitting-costs-wallet-1.webp --scale 1
echo "rebuilt all mockups into $O"
