<?php
if (! defined('ABSPATH')) {
    exit;
}

/** Read one option, falling back to the value baked into this download, then to the default. */
function naara_widgets_opt($name, $fallback = '')
{
    $opts = get_option('naara_widgets', []);
    if (is_array($opts) && isset($opts[$name]) && $opts[$name] !== '') {
        return $opts[$name];
    }
    if (isset(NAARA_WIDGETS_DEFAULTS[$name]) && NAARA_WIDGETS_DEFAULTS[$name] !== '') {
        return NAARA_WIDGETS_DEFAULTS[$name];
    }

    return $fallback;
}

/** The Naara host must be a plain https origin: it decides which script runs on every page of the site. */
function naara_widgets_clean_host($value)
{
    $value = esc_url_raw(trim((string) $value), ['https', 'http']);
    $parts = wp_parse_url($value);
    if (empty($parts['host'])) {
        return '';
    }
    $scheme = (isset($parts['scheme']) && $parts['scheme'] === 'http' && $parts['host'] === 'localhost') ? 'http' : 'https';

    return $scheme.'://'.$parts['host'].(isset($parts['port']) ? ':'.(int) $parts['port'] : '');
}

function naara_widgets_sanitize($in)
{
    $in = is_array($in) ? $in : [];
    $key = isset($in['key']) ? trim((string) $in['key']) : '';

    return [
        'key' => preg_match('/^wk_[A-Za-z0-9_]+$/', $key) ? $key : '',
        'host' => isset($in['host']) ? naara_widgets_clean_host($in['host']) : '',
        'mode' => (isset($in['mode']) && $in['mode'] === 'redirect') ? 'redirect' : 'modal',
        'color' => (isset($in['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $in['color'])) ? $in['color'] : '',
        // A checkbox is absent when unticked, so "off" has to be stored explicitly or it would fall back to the default (on).
        'account' => (isset($in['account']) && $in['account'] === 'on') ? 'on' : 'off',
    ];
}

add_action('admin_init', function () {
    register_setting('naara_widgets', 'naara_widgets', ['sanitize_callback' => 'naara_widgets_sanitize']);
});

add_action('admin_menu', function () {
    add_options_page(__('Naara Widgets', 'naara-widgets'), __('Naara Widgets', 'naara-widgets'), 'manage_options', 'naara-widgets', 'naara_widgets_settings_page');
});

/** Clay-styled admin page (scoped under .nw so it never touches the rest of wp-admin). */
add_action('admin_head', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (! $screen || $screen->id !== 'settings_page_naara-widgets') {
        return;
    }
    echo '<style>
.nw{max-width:760px;--brand:#0A6E6E;--fg:#0d1b2a;--muted:#43566a;--field:#eef3f7;--ok:#067647;--bad:#b42318;--card:linear-gradient(145deg,#fff,#eef3f7);
--raise:10px 12px 24px -10px rgba(10,36,49,.28),-8px -8px 18px #fff,inset 2px 2px 4px #fff,inset -3px -3px 6px rgba(10,36,49,.06);--press:inset 3px 4px 8px rgba(10,36,49,.14),inset -3px -3px 7px #fff;color:var(--fg)}
.nw *{box-sizing:border-box}.nw h1{font-size:1.6rem;font-weight:800;margin:.6em 0 .2em}.nw .lead{color:var(--muted);font-size:14px;margin:0 0 18px}
.nw .illus{margin:0 0 20px;border-radius:28px;overflow:hidden;box-shadow:var(--raise)}.nw .illus svg{display:block;width:100%;height:auto}
.nw .card{background:var(--card);border-radius:28px;padding:20px 22px;margin:0 0 20px;box-shadow:var(--raise)}.nw .card h2{margin:0 0 4px;font-size:1.05rem;font-weight:800}.nw .card p{color:var(--muted);margin:4px 0 12px}
.nw .st{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.nw .badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;font-weight:700;font-size:12px;background:var(--field);box-shadow:var(--press)}
.nw .badge.ok{color:var(--ok)}.nw .badge.bad{color:var(--bad)}.nw .badge.idle{color:var(--muted)}
.nw ol.steps{margin:0;padding:0;list-style:none;display:grid;gap:12px;counter-reset:s}.nw ol.steps li{display:flex;gap:12px;align-items:flex-start}
.nw ol.steps li:before{counter-increment:s;content:counter(s);display:grid;place-items:center;width:28px;height:28px;flex:none;border-radius:50%;background:var(--field);box-shadow:var(--press);font-weight:800;font-size:13px}
.nw ol.steps li.done:before{content:"\2713";background:var(--ok);color:#fff;box-shadow:none}
.nw label.f{display:block;font-weight:700;margin:14px 0 6px}.nw input[type=text],.nw select{width:100%;max-width:100%;min-height:44px;padding:10px 16px;border:0;border-radius:18px;background:var(--field);box-shadow:var(--press);color:var(--fg);font-size:14px}
.nw .hint{color:var(--muted);font-size:12.5px;margin:6px 4px 0}
.nw .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:999px;padding:12px 24px;font-weight:800;font-size:14px;cursor:pointer;color:#fff;background:linear-gradient(180deg,#27a1a1,var(--brand) 55%,#075a5a);box-shadow:inset 0 4px 8px rgba(255,255,255,.28),inset 0 -5px 10px rgba(0,0,0,.22),0 10px 18px -8px rgba(10,110,110,.6)}
.nw .btn.ghost{color:var(--fg);background:var(--card);box-shadow:6px 8px 16px -8px rgba(10,36,49,.22),-5px -5px 12px #fff}.nw .btn[disabled]{opacity:.65;cursor:wait}
.nw code.copy{display:inline-block;padding:6px 14px;border-radius:14px;background:var(--field);box-shadow:var(--press);font-size:13px;margin:4px 8px 4px 0;cursor:copy}
.nw :focus-visible{outline:3px solid var(--brand);outline-offset:2px}
</style>';
});

function naara_widgets_settings_page()
{
    if (! current_user_can('manage_options')) {
        return;
    }
    $key = naara_widgets_opt('key');
    $host = naara_widgets_clean_host(naara_widgets_opt('host'));
    $site = wp_parse_url(home_url(), PHP_URL_HOST);
    $port = wp_parse_url(home_url(), PHP_URL_PORT);
    $site = $site.($port ? ':'.$port : '');
    $ready = $key !== '' && $host !== '';
    ?>
    <div class="wrap nw">
        <h1><?php esc_html_e('Naara Widgets', 'naara-widgets'); ?></h1>
        <p class="lead"><?php esc_html_e('Sell eSIM data plans and gift cards on this site. Naara handles pricing, payment, delivery and support. You earn your margin on every sale.', 'naara-widgets'); ?></p>

        <?php
        // The how-it-works illustration (a static SVG shipped inside this plugin, never user input).
        $naara_illus = NAARA_WIDGETS_DIR.'assets/infrastructure.svg';
        if (is_readable($naara_illus)) {
            echo '<div class="illus" aria-hidden="false">'.file_get_contents($naara_illus).'</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>

        <div class="card">
            <div class="st">
                <h2 style="margin:0"><?php esc_html_e('Connection', 'naara-widgets'); ?></h2>
                <span class="badge <?php echo $ready ? 'idle' : 'bad'; ?>" id="nw-badge"><?php echo $ready ? esc_html__('Not tested yet', 'naara-widgets') : esc_html__('Not set up', 'naara-widgets'); ?></span>
            </div>
            <p id="nw-msg" role="status" aria-live="polite"><?php echo $ready ? esc_html__('Press Test connection to check your key and this site\'s address with Naara.', 'naara-widgets') : esc_html__('Add your widget key below, then test the connection.', 'naara-widgets'); ?></p>
            <button type="button" class="btn ghost" id="nw-test" <?php disabled(! $ready); ?>><?php esc_html_e('Test connection', 'naara-widgets'); ?></button>
        </div>

        <div class="card">
            <h2><?php esc_html_e('Set up in three steps', 'naara-widgets'); ?></h2>
            <ol class="steps">
                <li class="<?php echo $key !== '' ? 'done' : ''; ?>"><span><b><?php esc_html_e('Paste your public widget key', 'naara-widgets'); ?></b><br><span class="hint"><?php esc_html_e('Find it in your Naara merchant dashboard, Widgets tab.', 'naara-widgets'); ?></span></span></li>
                <li><span><b><?php esc_html_e('Allow this website on your key', 'naara-widgets'); ?></b><br><span class="hint"><?php esc_html_e('In the Widgets tab, add exactly this address to the key\'s allowed domains:', 'naara-widgets'); ?> <code class="copy" tabindex="0" title="<?php esc_attr_e('Click to copy', 'naara-widgets'); ?>"><?php echo esc_html($site); ?></code></span></span></li>
                <li><span><b><?php esc_html_e('Put it on a page', 'naara-widgets'); ?></b><br><span class="hint"><?php esc_html_e('Add the "Naara widget" block, or paste a shortcode.', 'naara-widgets'); ?></span></span></li>
            </ol>
        </div>

        <form method="post" action="options.php" class="card">
            <?php settings_fields('naara_widgets'); ?>
            <h2><?php esc_html_e('Settings', 'naara-widgets'); ?></h2>
            <label class="f" for="nw-key"><?php esc_html_e('Public widget key', 'naara-widgets'); ?></label>
            <input id="nw-key" type="text" class="code" name="naara_widgets[key]" value="<?php echo esc_attr($key); ?>" placeholder="wk_live_..." autocomplete="off" spellcheck="false">
            <p class="hint"><?php esc_html_e('This only identifies your widget. Naara checks that the site is on your allowed list. Never paste a secret here.', 'naara-widgets'); ?></p>
            <label class="f" for="nw-host"><?php esc_html_e('Naara address', 'naara-widgets'); ?></label>
            <input id="nw-host" type="text" name="naara_widgets[host]" value="<?php echo esc_attr(naara_widgets_opt('host')); ?>" placeholder="https://..." autocomplete="off" spellcheck="false">
            <label class="f" for="nw-mode"><?php esc_html_e('When a visitor taps Buy', 'naara-widgets'); ?></label>
            <select id="nw-mode" name="naara_widgets[mode]">
                <option value="modal" <?php selected(naara_widgets_opt('mode', 'modal'), 'modal'); ?>><?php esc_html_e('Open checkout in a pop-up on this page (recommended)', 'naara-widgets'); ?></option>
                <option value="redirect" <?php selected(naara_widgets_opt('mode', 'modal'), 'redirect'); ?>><?php esc_html_e('Open checkout as a full page', 'naara-widgets'); ?></option>
            </select>
            <label class="f" for="nw-color"><?php esc_html_e('Accent colour (optional)', 'naara-widgets'); ?></label>
            <input id="nw-color" type="text" name="naara_widgets[color]" value="<?php echo esc_attr(naara_widgets_opt('color')); ?>" placeholder="#0A6E6E" maxlength="7" style="max-width:180px">
            <p class="hint"><?php esc_html_e('Buttons and highlights use this colour. Leave empty to use the colour set in your Naara dashboard.', 'naara-widgets'); ?></p>
            <label class="f" style="display:flex;gap:10px;align-items:flex-start;font-weight:600"><input type="checkbox" name="naara_widgets[account]" value="on" <?php checked(naara_widgets_opt('account', 'on'), 'on'); ?> style="margin-top:3px"><span><?php esc_html_e('Let customers sign in to see their orders', 'naara-widgets'); ?><br><span class="hint" style="font-weight:400"><?php esc_html_e('Adds a "Find my order" button. Customers get an emailed code (no password), then see their eSIMs and numbers, copy codes, download their data or ask to delete it. Orders stay tied to your site, so customers come back to you to manage them.', 'naara-widgets'); ?></span></span></label>
            <p style="margin:18px 0 0"><button type="submit" class="btn"><?php esc_html_e('Save settings', 'naara-widgets'); ?></button></p>
        </form>

        <div class="card">
            <h2><?php esc_html_e('Shortcodes', 'naara-widgets'); ?></h2>
            <p><?php esc_html_e('Click one to copy it, then paste it into any page or post.', 'naara-widgets'); ?></p>
            <code class="copy" tabindex="0">[naara_widget type="esim"]</code>
            <code class="copy" tabindex="0">[naara_widget type="number"]</code>
            <code class="copy" tabindex="0">[naara_widget type="giftcard"]</code>
            <code class="copy" tabindex="0">[naara_widget]</code>
            <code class="copy" tabindex="0">[naara_account]</code>
            <p class="hint"><?php esc_html_e('Use the empty one to show everything with clear tabs (eSIM data, verification numbers, gift cards). Put [naara_account] on its own page, for example "My orders", and link it from your menu so customers can come back to track their purchases. Verification numbers appear only when Naara has them switched on for your key.', 'naara-widgets'); ?></p>
            <p class="hint"><?php esc_html_e('Add theme="dark" or theme="light" to force a look; by default it follows the visitor\'s device.', 'naara-widgets'); ?></p>
        </div>
    </div>
    <script>
    (function () {
        var host = <?php echo wp_json_encode($host); ?>, key = <?php echo wp_json_encode($key); ?>;
        document.querySelectorAll('.nw code.copy').forEach(function (c) {
            function copy() { if (navigator.clipboard) { navigator.clipboard.writeText(c.textContent.trim()); var o = c.textContent; c.textContent = <?php echo wp_json_encode(__('Copied', 'naara-widgets')); ?>; setTimeout(function () { c.textContent = o; }, 1100); } }
            c.addEventListener('click', copy); c.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); copy(); } });
        });
        var btn = document.getElementById('nw-test'), badge = document.getElementById('nw-badge'), msg = document.getElementById('nw-msg');
        function show(cls, b, m) { badge.className = 'badge ' + cls; badge.textContent = b; msg.textContent = m; }
        btn.addEventListener('click', function () {
            btn.disabled = true; show('idle', <?php echo wp_json_encode(__('Testing…', 'naara-widgets')); ?>, <?php echo wp_json_encode(__('Contacting Naara…', 'naara-widgets')); ?>);
            // Runs in YOUR browser, from this site, exactly like a visitor's page would: so a pass here means the live widget will work.
            fetch(host + '/api/v1/merchant-widget/catalogue', { headers: { 'X-Naara-Widget-Key': key, 'Accept': 'application/json' }, credentials: 'omit' })
              .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { s: r.status, j: j }; }); })
              .then(function (x) {
                  if (x.s === 200) { var d = x.j.data || {}, n = (d.esim || []).length, g = (d.giftcard || []).length, v = (d.number || []).length; show('ok', <?php echo wp_json_encode(__('Connected', 'naara-widgets')); ?>, <?php echo wp_json_encode(__('Your widget can see', 'naara-widgets')); ?> + ' ' + n + ' eSIM plan(s), ' + v + ' verification service(s) and ' + g + ' gift card brand(s).' + (x.j.account ? ' Customer sign-in is available.' : '')); }
                  else if (x.s === 401 || x.s === 403) { show('bad', <?php echo wp_json_encode(__('Not allowed', 'naara-widgets')); ?>, <?php echo wp_json_encode(__('Naara refused the key. Check it is correct and active, and that this site\'s address is on its allowed domains:', 'naara-widgets')); ?> + ' <?php echo esc_js($site); ?>'); }
                  else if (x.s === 429) { show('bad', <?php echo wp_json_encode(__('Slow down', 'naara-widgets')); ?>, <?php echo wp_json_encode(__('Too many requests just now. Wait a minute and test again.', 'naara-widgets')); ?>); }
                  else { show('bad', <?php echo wp_json_encode(__('Problem', 'naara-widgets')); ?>, (x.j && x.j.message) || <?php echo wp_json_encode(__('Naara answered with an error. Widgets may be switched off by the platform right now.', 'naara-widgets')); ?>); }
              })
              .catch(function () { show('bad', <?php echo wp_json_encode(__('Cannot reach Naara', 'naara-widgets')); ?>, <?php echo wp_json_encode(__('Check the Naara address above. If it is right, this site\'s address may not be on the key\'s allowed domains.', 'naara-widgets')); ?>); })
              .finally(function () { btn.disabled = false; });
        });
    })();
    </script>
    <?php
}

add_filter('plugin_action_links_'.plugin_basename(NAARA_WIDGETS_FILE), function ($links) {
    array_unshift($links, '<a href="'.esc_url(admin_url('options-general.php?page=naara-widgets')).'">'.esc_html__('Settings', 'naara-widgets').'</a>');

    return $links;
});
