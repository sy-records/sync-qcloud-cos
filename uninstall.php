<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

$cos_options = get_option('cos_options', true);
$upload_url_path = get_option('upload_url_path');
$cos_upload_url_path = esc_attr($cos_options['upload_url_path']);

if ($upload_url_path == $cos_upload_url_path) {
    update_option('upload_url_path', '');
}

delete_option('cos_options');
