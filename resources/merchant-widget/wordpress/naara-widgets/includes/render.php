<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * The ONLY thing this plugin does with Naara: print the mount point and enqueue Naara's own script with data-* attributes.
 * No pricing, catalogue, checkout or network call to Naara happens in PHP.
 */
function naara_widgets_render($type = '', $theme = '', $start = '')
{
    $key = naara_widgets_opt('key');
    $host = naara_widgets_clean_host(naara_widgets_opt('host'));
    if ($key === '' || $host === '') {
        return current_user_can('manage_options')
            ? '<p><em>'.esc_html__('Naara Widgets: add your widget key under Settings, Naara Widgets.', 'naara-widgets').'</em></p>'
            : '';
    }
    static $n = 0;
    $n++;
    $id = 'naara-widget-'.$n;
    $type = in_array($type, ['esim', 'number', 'giftcard'], true) ? $type : '';
    $theme = in_array($theme, ['light', 'dark', 'auto'], true) ? $theme : 'auto';

    wp_enqueue_script('naara-widgets-sdk-'.$n, $host.'/widgets/merchant-widget.js', [], null, true);
    $attrs = [
        'data-naara-key' => $key, 'data-target' => '#'.$id, 'data-theme' => $theme,
        'data-mode' => naara_widgets_opt('mode', 'modal') === 'redirect' ? 'redirect' : 'modal',
    ];
    if ($type !== '') {
        $attrs['data-product-type'] = $type;
    }
    if (naara_widgets_opt('account', 'on') !== 'on') {
        $attrs['data-account'] = 'off';
    }
    if ($start === 'account') {
        $attrs['data-start'] = 'account';   // a dedicated "My orders" page opens straight on sign-in
    }
    if (naara_widgets_opt('color') !== '') {
        $attrs['data-theme-color'] = naara_widgets_opt('color');
    }
    $GLOBALS['naara_widgets_attrs']['naara-widgets-sdk-'.$n] = $attrs;

    // min-height reserves the space so the page does not jump when the widget loads.
    return '<div id="'.esc_attr($id).'" class="naara-widget" style="min-height:140px"></div>';
}

add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if (empty($GLOBALS['naara_widgets_attrs'][$handle])) {
        return $tag;
    }
    $out = '<script src="'.esc_url($src).'" defer';
    foreach ($GLOBALS['naara_widgets_attrs'][$handle] as $name => $value) {
        $out .= ' '.esc_attr($name).'="'.esc_attr($value).'"';
    }

    return $out."></script>\n";
}, 10, 3);

add_shortcode('naara_widget', function ($atts) {
    $a = shortcode_atts(['type' => '', 'theme' => 'auto'], $atts, 'naara_widget');

    return naara_widgets_render(sanitize_key($a['type']), sanitize_key($a['theme']));
});

add_shortcode('naara_account', function ($atts) {
    $a = shortcode_atts(['theme' => 'auto'], $atts, 'naara_account');

    return naara_widgets_render('', sanitize_key($a['theme']), 'account');
});

add_action('init', function () {
    wp_register_script('naara-widgets-block', plugins_url('block/block.js', NAARA_WIDGETS_FILE), ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'], NAARA_WIDGETS_VERSION, true);
    wp_add_inline_script('naara-widgets-block', 'window.naaraWidgets = '.wp_json_encode([
        'configured' => naara_widgets_opt('key') !== '' && naara_widgets_clean_host(naara_widgets_opt('host')) !== '',
        'settingsUrl' => admin_url('options-general.php?page=naara-widgets'),
    ]).';', 'before');
    register_block_type('naara/widget', [
        'api_version' => 2,
        'title' => 'Naara widget',
        'editor_script' => 'naara-widgets-block',
        'attributes' => ['type' => ['type' => 'string', 'default' => 'esim'], 'theme' => ['type' => 'string', 'default' => 'auto']],
        'render_callback' => function ($attrs) {
            $type = isset($attrs['type']) ? sanitize_key($attrs['type']) : '';
            $theme = isset($attrs['theme']) ? sanitize_key($attrs['theme']) : 'auto';

            // "account" is a block-only choice: the customer's My orders page.
            return $type === 'account' ? naara_widgets_render('', $theme, 'account') : naara_widgets_render($type, $theme);
        },
    ]);
});
