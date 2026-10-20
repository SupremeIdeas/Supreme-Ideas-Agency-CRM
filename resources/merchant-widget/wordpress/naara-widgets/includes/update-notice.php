<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Manual updates (v1): once a day, ask YOUR Naara for the current plugin version and show a notice with a download link when a newer
 * one exists. Nothing is downloaded or installed automatically.
 */
add_action('admin_notices', function () {
    if (! current_user_can('update_plugins')) {
        return;
    }
    $host = naara_widgets_clean_host(naara_widgets_opt('host'));
    if ($host === '') {
        return;
    }
    $info = get_transient('naara_widgets_latest');
    if ($info === false) {
        $res = wp_remote_get($host.'/widget/plugin/version', ['timeout' => 4, 'redirection' => 0]);
        $json = is_wp_error($res) ? null : json_decode(wp_remote_retrieve_body($res), true);
        $info = (is_array($json) && isset($json['version'])) ? ['version' => preg_replace('/[^0-9.]/', '', (string) $json['version'])] : ['version' => ''];
        set_transient('naara_widgets_latest', $info, DAY_IN_SECONDS);
    }
    if ($info['version'] !== '' && version_compare($info['version'], NAARA_WIDGETS_VERSION, '>')) {
        echo '<div class="notice notice-info"><p>'.sprintf(
            /* translators: %s: version number */
            esc_html__('A new version of Naara Widgets (%s) is available. Download it from your Naara merchant dashboard.', 'naara-widgets'),
            esc_html($info['version'])
        ).'</p></div>';
    }
});
