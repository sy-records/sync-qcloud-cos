<?php
//防止有人恶意访问此文件，所以在没有 WP_UNINSTALL_PLUGIN 常量的情况下结束程序
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();

$cos_options = get_option('cos_options', TRUE);
$upload_url_path = get_option('upload_url_path');
$cos_upload_url_path = esc_attr($cos_options['upload_url_path']);

//如果现在使用的是COS的URL，则恢复原状
if( $upload_url_path == $cos_upload_url_path ) {
	update_option('upload_url_path', "" );
}

//移除配置
delete_option('cos_options');
