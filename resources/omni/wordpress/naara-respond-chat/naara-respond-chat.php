<?php
/**
 * Plugin Name:       Naara Respond Chat
 * Description:       Adds your Naara Respond website chat to every page of your WordPress site. Messages arrive in your Naara inbox next to WhatsApp, Instagram and email.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Naara
 * License:           GPL-2.0-or-later
 * Text Domain:       naara-respond-chat
 */

if (! defined('ABSPATH')) {
    exit;
}

define('NARA_RESPOND_CHAT_VERSION', '1.0.0');

// Defaults baked in when the plugin is downloaded from Business > Channels (your Naara address and this chat's public site key). Neither is a secret.
$naara_rc_defaults = file_exists(plugin_dir_path(__FILE__).'defaults.php') ? include plugin_dir_path(__FILE__).'defaults.php' : [];
if (! is_array($naara_rc_defaults)) {
    $naara_rc_defaults = [];
}

/** The saved settings merged over the baked-in defaults. */
function naara_rc_options() {
    global $naara_rc_defaults;
    $saved = get_option('naara_rc_options', []);
    $saved = is_array($saved) ? $saved : [];

    return [
        'enabled' => isset($saved['enabled']) ? (bool) $saved['enabled'] : true,
        'host'    => isset($saved['host']) && $saved['host'] !== '' ? $saved['host'] : (isset($naara_rc_defaults['host']) ? $naara_rc_defaults['host'] : ''),
        'site'    => isset($saved['site']) && $saved['site'] !== '' ? $saved['site'] : (isset($naara_rc_defaults['site']) ? $naara_rc_defaults['site'] : ''),
        'hide_for_admins' => ! empty($saved['hide_for_admins']),
    ];
}

/** A Naara address must be https (http only for localhost while testing), with no path. */
function naara_rc_clean_host($value) {
    $value = untrailingslashit(esc_url_raw(trim((string) $value)));
    $p = wp_parse_url($value);
    if (! is_array($p) || empty($p['host']) || isset($p['path']) || isset($p['query'])) {
        return '';
    }
    if (($p['scheme'] ?? '') === 'https' || (($p['scheme'] ?? '') === 'http' && in_array($p['host'], ['localhost', '127.0.0.1'], true))) {
        return $value;
    }

    return '';
}

function naara_rc_clean_site($value) {
    $value = strtolower(trim((string) $value));

    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value) ? $value : '';
}

function naara_rc_sanitize($input) {
    $input = is_array($input) ? $input : [];
    $out = [
        'enabled' => ! empty($input['enabled']),
        'host' => naara_rc_clean_host($input['host'] ?? ''),
        'site' => naara_rc_clean_site($input['site'] ?? ''),
        'hide_for_admins' => ! empty($input['hide_for_admins']),
    ];
    if (($input['host'] ?? '') !== '' && $out['host'] === '') {
        add_settings_error('naara_rc_options', 'naara_rc_host', __('The Naara address must start with https:// and have no page path, for example https://naara.app.', 'naara-respond-chat'));
    }
    if (($input['site'] ?? '') !== '' && $out['site'] === '') {
        add_settings_error('naara_rc_options', 'naara_rc_site', __('The site key looks like 8f14e45f-ceea-4672-9a0c-2f3b6e2d9c11. Copy it again from Business > Channels in Naara.', 'naara-respond-chat'));
    }

    return $out;
}

add_action('admin_init', function () {
    register_setting('naara_rc', 'naara_rc_options', ['type' => 'array', 'sanitize_callback' => 'naara_rc_sanitize', 'default' => []]);
});

add_action('admin_menu', function () {
    add_options_page(__('Naara Chat', 'naara-respond-chat'), __('Naara Chat', 'naara-respond-chat'), 'manage_options', 'naara-respond-chat', 'naara_rc_page');
});

add_filter('plugin_action_links_'.plugin_basename(__FILE__), function ($links) {
    array_unshift($links, '<a href="'.esc_url(admin_url('options-general.php?page=naara-respond-chat')).'">'.esc_html__('Settings', 'naara-respond-chat').'</a>');

    return $links;
});

function naara_rc_page() {
    if (! current_user_can('manage_options')) {
        return;
    }
    $o = naara_rc_options();
    $ready = $o['host'] !== '' && $o['site'] !== '';
    echo '<div class="wrap"><h1>'.esc_html__('Naara Chat', 'naara-respond-chat').'</h1>';
    settings_errors('naara_rc_options');
    echo '<img src="'.esc_url(plugins_url('assets/chat-flow.svg', __FILE__)).'" alt="'.esc_attr__('Your WordPress site connected to your Naara Respond inbox', 'naara-respond-chat').'" style="max-width:560px;width:100%;height:auto;border-radius:14px;margin:8px 0 12px">';
    echo '<p>'.esc_html__('Visitors chat from any page. Their messages land in your Naara Respond inbox.', 'naara-respond-chat').'</p>';
    echo $ready
        ? '<p style="color:#0a6e6e"><strong>'.esc_html__('Ready. The chat bubble shows on your site.', 'naara-respond-chat').'</strong></p>'
        : '<p style="color:#b32d2e"><strong>'.esc_html__('Add your Naara address and site key below to switch the chat on.', 'naara-respond-chat').'</strong></p>';
    echo '<form method="post" action="options.php">';
    settings_fields('naara_rc');
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row">'.esc_html__('Show the chat', 'naara-respond-chat').'</th><td><label><input type="checkbox" name="naara_rc_options[enabled]" value="1" '.checked($o['enabled'], true, false).'> '.esc_html__('Yes, show the chat bubble', 'naara-respond-chat').'</label></td></tr>';
    echo '<tr><th scope="row"><label for="naara_rc_host">'.esc_html__('Your Naara address', 'naara-respond-chat').'</label></th><td><input id="naara_rc_host" class="regular-text" type="url" name="naara_rc_options[host]" value="'.esc_attr($o['host']).'" placeholder="https://naara.app"></td></tr>';
    echo '<tr><th scope="row"><label for="naara_rc_site">'.esc_html__('Chat site key', 'naara-respond-chat').'</label></th><td><input id="naara_rc_site" class="regular-text code" type="text" name="naara_rc_options[site]" value="'.esc_attr($o['site']).'" placeholder="8f14e45f-ceea-4672-9a0c-2f3b6e2d9c11"><p class="description">'.esc_html__('In Naara: Business, Channels, Website chat. It is public: it only says which inbox the chat belongs to.', 'naara-respond-chat').'</p></td></tr>';
    echo '<tr><th scope="row">'.esc_html__('Staff', 'naara-respond-chat').'</th><td><label><input type="checkbox" name="naara_rc_options[hide_for_admins]" value="1" '.checked($o['hide_for_admins'], true, false).'> '.esc_html__('Hide the chat while I am logged in as an administrator', 'naara-respond-chat').'</label></td></tr>';
    echo '</table>';
    submit_button();
    echo '</form><p class="description">'.esc_html__('Also allow this site in Naara: Channels, Website chat, allowed websites must list this site address.', 'naara-respond-chat').'</p></div>';
}

add_action('wp_enqueue_scripts', function () {
    $o = naara_rc_options();
    if (! $o['enabled'] || $o['host'] === '' || $o['site'] === '' || is_admin()) {
        return;
    }
    if ($o['hide_for_admins'] && current_user_can('manage_options')) {
        return;
    }
    wp_enqueue_script('naara-respond-chat', $o['host'].'/js/omni-webchat.js', [], NARA_RESPOND_CHAT_VERSION, ['strategy' => 'async', 'in_footer' => true]);
});

// The widget reads its site key from its own script tag, so the tag needs the data attribute.
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if ($handle !== 'naara-respond-chat') {
        return $tag;
    }
    $o = naara_rc_options();

    return '<script async src="'.esc_url($src).'" data-site="'.esc_attr($o['site']).'" id="naara-respond-chat-js"></script>'."\n";
}, 10, 3);
