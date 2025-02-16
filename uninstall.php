<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

update_option('upload_url_path', '');

$cos_options = get_option('cos_options', ['delete_options' => 'true']);
if (esc_attr($cos_options['delete_options']) == 'true') {
    delete_option('cos_options');
}
