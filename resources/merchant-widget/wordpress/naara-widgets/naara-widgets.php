<?php
/**
 * Plugin Name:       Naara Widgets
 * Description:       Sell Naara eSIM data plans, verification numbers and gift cards on your WordPress site, and give your customers a My orders page so they keep coming back. A thin loader: it adds Naara's own widget script to your pages and holds no pricing, catalogue or checkout logic.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Naara
 * License:           GPL-2.0-or-later
 * Text Domain:       naara-widgets
 */

if (! defined('ABSPATH')) {
    exit;
}

define('NAARA_WIDGETS_VERSION', '1.2.0');
define('NAARA_WIDGETS_FILE', __FILE__);
define('NAARA_WIDGETS_DIR', plugin_dir_path(__FILE__));

// Defaults baked in when the merchant downloads the plugin from their Naara dashboard (host, and their public key when they chose one).
$naara_defaults = file_exists(NAARA_WIDGETS_DIR.'defaults.php') ? include NAARA_WIDGETS_DIR.'defaults.php' : [];
define('NAARA_WIDGETS_DEFAULTS', is_array($naara_defaults) ? $naara_defaults : []);

require_once NAARA_WIDGETS_DIR.'includes/settings.php';
require_once NAARA_WIDGETS_DIR.'includes/render.php';
require_once NAARA_WIDGETS_DIR.'includes/update-notice.php';
