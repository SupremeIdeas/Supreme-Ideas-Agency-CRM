<?php
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
delete_option('naara_widgets');
delete_transient('naara_widgets_latest');
