# Pinned data for Business Links

`public_suffix_list.dat` is a pinned copy of the Public Suffix List (https://publicsuffix.org/list/public_suffix_list.dat), licensed MPL-2.0
(https://mozilla.org/MPL/2.0/). It is what lets us tell a registrable domain (`example.com.ng`) from its public suffix (`com.ng`), and a shared host's
customer label (`brand.blogspot.com`) from the host itself. The list is bundled, never fetched at request time (cPanel cannot run `composer update`).

Refresh: Admin > Business links > Data > "Refresh from source" (or replace this file). The version line at the top of the file and `config/brand_links.php`
(`psl.verified_on`) record what is in use.
