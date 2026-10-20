=== Naara Widgets ===
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later

Sell Naara eSIM data plans, verification numbers and gift cards on your WordPress site, with your own branding and your own margin. Customers can sign in to see and manage their own orders.

== Description ==
A thin loader. It adds Naara's widget script to your pages through a block or the [naara_widget] shortcode. [naara_account] gives customers a "My orders" page. All pricing, catalogue and checkout run on Naara; nothing sensitive is stored in WordPress. Only your PUBLIC widget key is used, and Naara only answers it on the domains you allow in your merchant dashboard.

== Installation ==
1. Download the plugin from your Naara merchant dashboard (Widgets tab) and upload it under Plugins, Add New, Upload.
2. Open Settings, Naara Widgets and paste your public widget key.
3. Add the Naara widget block, or [naara_widget type="esim"] (also "number" and "giftcard"; leave type out for everything).
4. Optional: add a page with [naara_account] and link it from your menu so customers can return to track their orders.

== Changelog ==
= 1.2.0 =
* Settings page opens with an illustration of how your site, the Naara widget and the Naara infrastructure fit together.
* Verification numbers: [naara_widget type="number"], with a live price and the SMS code appearing on the page.
* Clear tabs with a plain-language description of every product, so shoppers know what they are buying.
* [naara_account] and a block option for a customer "My orders" page: emailed-code sign-in (no password), copy and share details, download my data, ask to delete my data.
* New setting to switch customer sign-in off.
= 1.1.0 =
* Settings page rebuilt: set-up checklist, a one-click connection test that tells you exactly what is wrong, and copy-ready shortcodes.
* Widget reserves its space so the page does not jump while it loads.
* Block editor shows whether the widget is connected.
= 1.0.0 =
* First release.
