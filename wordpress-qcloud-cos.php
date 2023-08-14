<?php
/*
Plugin Name: Sync QCloud COS
Plugin URI: https://qq52o.me/2518.html
Description: 使用腾讯云对象存储服务 COS 作为附件存储空间。（This is a plugin that uses Tencent Cloud Cloud Object Storage for attachments remote saving.）
Version: 2.2.0
Author: 沈唁
Author URI: https://qq52o.me
License: Apache 2.0
*/

if (!defined('ABSPATH')) {
    exit;
}

require_once 'cos-sdk-v5/vendor/autoload.php';

use Qcloud\Cos\Client;
use Qcloud\Cos\Exception\ServiceResponseException;

define('COS_VERSION', '2.2.0');
define('COS_BASEFOLDER', plugin_basename(dirname(__FILE__)));

if (!function_exists('get_home_path')) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
}

// 初始化选项
register_activation_hook(__FILE__, 'cos_set_options');
function cos_set_options()
{
    $options = array(
        'bucket' => '',
        'regional' => 'ap-beijing',
        'app_id' => '',
        'secret_id' => '',
        'secret_key' => '',
        'nothumb' => 'false', // 是否上传缩略图
        'nolocalsaving' => 'false', // 是否保留本地备份
        'delete_options' => 'false',
        'upload_url_path' => '', // URL前缀
        'ci_style' => '',
        'update_file_name' => 'false', // 是否重命名文件名
    );
    add_option('cos_options', $options, '', 'yes');
}

// stop plugin
function cos_stop_option()
{
    $option = get_option('cos_options');
    if (esc_attr($option['delete_options']) == 'true') {
        $upload_url_path = cos_get_option('upload_url_path');
        $cos_upload_url_path = esc_attr($option['upload_url_path']);

        if($upload_url_path == $cos_upload_url_path) {
            update_option('upload_url_path', '' );
        }
        delete_option('cos_options');
    }
}

register_deactivation_hook(__FILE__, 'cos_stop_option');

/**
 * @param array $cos_options
 * @return Client
 */
function cos_get_client($cos_options = null)
{
    if ($cos_options === null) $cos_options = get_option('cos_options', true);
    return new Client([
                          'region' => esc_attr($cos_options['regional']),
                          'schema' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http',
                          'credentials' => [
                              'secretId' => esc_attr($cos_options['secret_id']),
                              'secretKey' => esc_attr($cos_options['secret_key'])
                          ],
                          'userAgent' => 'WordPress v' . $GLOBALS['wp_version'] . '; SyncQCloudCOS v' . COS_VERSION . '; SDK v' . Client::VERSION,
                      ]);
}

function cos_get_bucket_name()
{
    $cos_options = get_option('cos_options', true);
    $cos_bucket = esc_attr($cos_options['bucket']);
    $cos_app_id = esc_attr($cos_options['app_id']);
    $needle = '-' . $cos_app_id;
    if (strpos($cos_bucket, $needle) !== false){
        return $cos_bucket;
    }
    return $cos_bucket . $needle;
}

function cos_check_bucket($cos_options)
{
    $client = cos_get_client($cos_options);
    try {
        $buckets_obj = $client->listBuckets();
        if (isset($buckets_obj['Buckets'][0]['Bucket'])) {
            $cos_bucket = esc_attr($cos_options['bucket']);
            $cos_app_id = esc_attr($cos_options['app_id']);
            $needle = "-{$cos_app_id}";
            if (strpos($cos_bucket, $needle) !== false){
                $setting_bucket = $cos_bucket;
            } else {
                $setting_bucket = $cos_bucket . $needle;
            }

            $buckets_msg = '存储桶名称或APPID错误，需要设置的存储桶名称或APPID可能在以下名称中： ';
            if (isset($buckets_obj['Buckets'][0]['Bucket'][0])) {
                foreach ($buckets_obj['Buckets'][0]['Bucket'] as $bucket) {
                    if ($setting_bucket == $bucket['Name']) {
                        return true;
                    } else {
                        $buckets_msg .= "<code>{$bucket['Name']}</code> ";
                    }
                }
            } else {
                if ($setting_bucket == $buckets_obj['Buckets'][0]['Bucket']['Name']) {
                    return true;
                } else {
                    $buckets_msg .= "<code>{$buckets_obj['Buckets'][0]['Bucket']['Name']}</code> ";
                }
            }
            echo '<div class="error"><p><strong>'. $buckets_msg .'</strong></p></div>';
        }
    } catch (ServiceResponseException $e) {
        echo "<div class='error'><p><strong>{$e}</strong></p></div>";
    }
    return false;
}

/**
 * @param $object
 * @param $filename
 * @param bool $no_local_file
 * @return false|void
 */
function cos_file_upload($object, $filename, $no_local_file = false)
{
    //如果文件不存在，直接返回false
    if (!@file_exists($filename)) {
        return false;
    }
    $bucket = cos_get_bucket_name();
    try {
        $file = fopen($filename, 'rb');
        if ($file) {
            $cosClient = cos_get_client();
            $cosClient->Upload($bucket, $object, $file);

            if ($no_local_file) {
                cos_delete_local_file($filename);
            }
        }
    } catch (ServiceResponseException $e) {
        WP_DEBUG && print_r(['errorMessage' => $e->getMessage(), 'statusCode' => $e->getStatusCode(), 'requestId' => $e->getRequestId()]);
    }
}

/**
 * 是否需要删除本地文件
 *
 * @return bool
 */
function cos_is_delete_local_file()
{
    $cos_options = get_option('cos_options', true);
    return (esc_attr($cos_options['nolocalsaving']) == 'true');
}

/**
 * 删除本地文件
 *
 * @param  $file
 * @return bool
 */
function cos_delete_local_file($file)
{
    try {
        //文件不存在
        if (!@file_exists($file)) {
            return true;
        }

        //删除文件
        if (!@unlink($file)) {
            return false;
        }

        return true;
    } catch (\Exception $ex) {
        return false;
    }
}

/**
 * 删除cos中的单个文件
 * @param $file
 */
function cos_delete_cos_file($file)
{
    $bucket = cos_get_bucket_name();
    $cosClient = cos_get_client();
    $cosClient->deleteObject(array('Bucket' => $bucket, 'Key' => $file));
}

/**
 * 批量删除cos中的文件
 * @param array $files_key
 */
function cos_delete_cos_files(array $files_key)
{
    $bucket = cos_get_bucket_name();
    $cosClient = cos_get_client();
    $cosClient->deleteObjects(array('Bucket' => $bucket, 'Objects' => $files_key));
}

function cos_get_option($key)
{
    return esc_attr(get_option($key));
}

/**
 * 上传附件（包括图片的原图）
 *
 * @param  $metadata
 * @return array
 */
function cos_upload_attachments($metadata)
{
    $mime_types = get_allowed_mime_types();
    $image_mime_types = array(
        $mime_types['jpg|jpeg|jpe'],
        $mime_types['gif'],
        $mime_types['png'],
        $mime_types['bmp'],
        $mime_types['tiff|tif'],
        $mime_types['ico'],
    );
    // 例如mp4等格式 上传后根据配置选择是否删除 删除后媒体库会显示默认图片 点开内容是正常的
    // 图片在缩略图处理
    if (!in_array($metadata['type'], $image_mime_types)) {
        //生成object在COS中的存储路径
        if (cos_get_option('upload_path') == '.') {
            $metadata['file'] = str_replace("./", '', $metadata['file']);
        }
        $object = str_replace("\\", '/', $metadata['file']);
        $home_path = get_home_path();
        $object = str_replace($home_path, '', $object);

        //在本地的存储路径
        $file = $home_path . $object; //向上兼容，较早的WordPress版本上$metadata['file']存放的是相对路径
        //执行上传操作
        cos_file_upload('/' . $object, $file, cos_is_delete_local_file());
    }

    return $metadata;
}

//避免上传插件/主题时出现同步到COS的情况
if (substr_count($_SERVER['REQUEST_URI'], '/update.php') <= 0) {
    add_filter('wp_handle_upload', 'cos_upload_attachments', 50);
    add_filter('wp_generate_attachment_metadata', 'cos_upload_thumbs', 100);
    add_filter('wp_save_image_editor_file', 'cos_save_image_editor_file', 101);
}

/**
 * 上传图片的缩略图
 */
function cos_upload_thumbs($metadata)
{
    //获取上传路径
    $wp_uploads = wp_upload_dir();
    $basedir = $wp_uploads['basedir'];
    if (!empty($metadata['file'])) {
        // Maybe there is a problem with the old version
        $file = $basedir . '/' . $metadata['file'];
        $upload_path = cos_get_option('upload_path');
        if ($upload_path != '.') {
            $path_array = explode($upload_path, $file);
            if (count($path_array) >= 2) {
                $object = '/' . $upload_path . end($path_array);
            }
        } else {
            $object = '/' . $metadata['file'];
            $file = str_replace('./', '', $file);
        }

        cos_file_upload($object, $file, cos_is_delete_local_file());
    }
    //上传所有缩略图
    if (!empty($metadata['sizes'])) {
        //获取COS插件的配置信息
        $cos_options = get_option('cos_options', true);
        //是否需要上传缩略图
        $nothumb = (esc_attr($cos_options['nothumb']) == 'true');
        //如果禁止上传缩略图，就不用继续执行了
        if ($nothumb) {
            return $metadata;
        }
        //得到本地文件夹和远端文件夹
        $dirname = dirname($metadata['file']);
        $file_path = $dirname != '.' ? "{$basedir}/{$dirname}/" : "{$basedir}/";
        $file_path = str_replace("\\", '/', $file_path);
        if ($upload_path == '.') {
            $file_path = str_replace('./', '', $file_path);
        }

        $object_path = str_replace(get_home_path(), '', $file_path);

        //there may be duplicated filenames,so ....
        foreach ($metadata['sizes'] as $val) {
            //生成object在COS中的存储路径
            $object = '/' . $object_path . $val['file'];
            //生成本地存储路径
            $file = $file_path . $val['file'];

            //执行上传操作
            cos_file_upload($object, $file, (esc_attr($cos_options['nolocalsaving']) == 'true'));
        }
    }
    return $metadata;
}

/**
* @param $override
* @return mixed
 */
function cos_save_image_editor_file($override)
{
    add_filter('wp_update_attachment_metadata', 'cos_image_editor_file_do');
    return $override;
}

/**
 * @param $metadata
 * @return mixed
 */
function cos_image_editor_file_do($metadata)
{
    return cos_upload_thumbs($metadata);
}

/**
 * 删除远端文件，删除文件时触发
 * @param $post_id
 */
function cos_delete_remote_attachment($post_id)
{
    // 获取图片类附件的meta信息
    $meta = wp_get_attachment_metadata( $post_id );

    if (!empty($meta['file'])) {

        $deleteObjects = [];

        // meta['file']的格式为 "2020/01/wp-bg.png"
        $upload_path = cos_get_option('upload_path');
        if ($upload_path == '') {
            $upload_path = 'wp-content/uploads';
        }
        $file_path = $upload_path . '/' . $meta['file'];

        $deleteObjects[] = ['Key' => str_replace("\\", '/', $file_path)];

        $dirname = dirname($file_path) . '/';

        // 删除时不管是否开启不上传缩略图，只要有就删除。
//        $cos_options = get_option('cos_options', true);
//        $is_nothumb = (esc_attr($cos_options['nothumb']) == 'false');
//        if ($is_nothumb) {
            // 删除缩略图
            if (!empty($meta['sizes'])) {
                foreach ($meta['sizes'] as $val) {
                    $size_file = $dirname . $val['file'];

                    $deleteObjects[] = ['Key' => str_replace("\\", '/', $size_file)];
                }
            }
//        }

        $backup_sizes = get_post_meta($post_id, '_wp_attachment_backup_sizes', true);
        if (is_array($backup_sizes)) {
            foreach ($backup_sizes as $size) {
                $deleteObjects[] = ['Key' => str_replace("\\", '/', $dirname . $size['file'])];
            }
        }

        cos_delete_cos_files($deleteObjects);
    } else {
        // 获取链接删除
        $link = wp_get_attachment_url($post_id);
        if ($link) {
            $upload_path = cos_get_option('upload_path');
            if ($upload_path != '.') {
                $file_info = explode($upload_path, $link);
                if (count($file_info) >= 2) {
                    cos_delete_cos_file($upload_path . end($file_info));
                }
            } else {
                $cos_options = get_option('cos_options', true);
                $cos_upload_url = esc_attr($cos_options['upload_url_path']);
                $file_info = explode($cos_upload_url, $link);
                if (count($file_info) >= 2) {
                    cos_delete_cos_file(end($file_info));
                }
            }
        }
    }
}
add_action('delete_attachment', 'cos_delete_remote_attachment');

// 当upload_path为根目录时，需要移除URL中出现的“绝对路径”
function cos_modefiy_img_url($url, $post_id)
{
    // 移除 ./ 和 项目根路径
    $url = str_replace(array('./', get_home_path()), array('', ''), $url);
    return $url;
}

if (cos_get_option('upload_path') == '.') {
    add_filter('wp_get_attachment_url', 'cos_modefiy_img_url', 30, 2);
}

function cos_sanitize_file_name($filename)
{
    $cos_options = get_option('cos_options');
    switch ($cos_options['update_file_name']) {
        case 'md5':
            return  md5($filename) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        case 'time':
            return date('YmdHis', current_time('timestamp'))  . mt_rand(100, 999) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        default:
            return $filename;
    }
}

add_filter( 'sanitize_file_name', 'cos_sanitize_file_name', 10, 1 );

function cos_function_each(&$array)
{
    $res = array();
    $key = key($array);
    if ($key !== null) {
        next($array);
        $res[1] = $res['value'] = $array[$key];
        $res[0] = $res['key'] = $key;
    } else {
        $res = false;
    }
    return $res;
}

/**
 * @param $dir
 * @return array
 */
function cos_read_dir_queue($dir)
{
    $dd = [];
    if (isset($dir)) {
        $files = array();
        $queue = array($dir);
        while ($data = cos_function_each($queue)) {
            $path = $data['value'];
            if (is_dir($path) && $handle = opendir($path)) {
                while ($file = readdir($handle)) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    }
                    $files[] = $real_path = $path . '/' . $file;
                    if (is_dir($real_path)) {
                        $queue[] = $real_path;
                    }
                    //echo explode(cos_get_option('upload_path'),$path)[1];
                }
            }
            closedir($handle);
        }
        $upload_path = cos_get_option('upload_path');
        foreach ($files as $v) {
            if (!is_dir($v)) {
                $dd[] = ['filepath' => $v, 'key' =>  '/' . $upload_path . explode($upload_path, $v)[1]];
            }
        }
    }

    return $dd;
}

// 在插件列表页添加设置按钮
function cos_plugin_action_links($links, $file)
{
    if ($file == plugin_basename(dirname(__FILE__) . '/wordpress-qcloud-cos.php')) {
        $links[] = '<a href="options-general.php?page=' . COS_BASEFOLDER . '/wordpress-qcloud-cos.php">设置</a>';
    }
    return $links;
}
add_filter('plugin_action_links', 'cos_plugin_action_links', 10, 2);

add_filter('the_content', 'cos_setting_content_ci');
function cos_setting_content_ci($content)
{
    $option = get_option('cos_options');
    if (!empty(esc_attr($option['ci_style']))) {
        preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $content, $images);
        if (!empty($images) && isset($images[1])) {
            $images[1] = array_unique($images[1]);
            foreach ($images[1] as $item) {
                if(strpos($item, esc_attr($option['upload_url_path'])) !== false){
                    $content = str_replace($item, $item . esc_attr($option['ci_style']), $content);
                }
            }
        }
    }
    return $content;
}

add_filter('post_thumbnail_html', 'cos_setting_post_thumbnail_ci', 10, 3);
function cos_setting_post_thumbnail_ci($html, $post_id, $post_image_id)
{
    $option = get_option('cos_options');
    if (!empty(esc_attr($option['ci_style'])) && has_post_thumbnail()) {
        preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $html, $images);
        if (!empty($images) && isset($images[1])) {
            $images[1] = array_unique($images[1]);
            foreach ($images[1] as $item) {
                if(strpos($item, esc_attr($option['upload_url_path'])) !== false){
                    $html = str_replace($item, $item . esc_attr($option['ci_style']), $html);
                }
            }
        }
    }
    return $html;
}

// 在导航栏“设置”中添加条目
function cos_add_setting_page()
{
    add_options_page('腾讯云COS设置', '腾讯云COS设置', 'manage_options', __FILE__, 'cos_setting_page');
}

add_action('admin_menu', 'cos_add_setting_page');

// 插件设置页面
function cos_setting_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient privileges!');
    }
    $options = array();
    if (!empty($_POST) and $_POST['type'] == 'cos_set') {
        $options['bucket'] = isset($_POST['bucket']) ? sanitize_text_field($_POST['bucket']) : '';
        $options['regional'] = isset($_POST['regional']) ? sanitize_text_field($_POST['regional']) : '';
        $options['app_id'] = isset($_POST['app_id']) ? sanitize_text_field($_POST['app_id']) : '';
        $options['secret_id'] = isset($_POST['secret_id']) ? sanitize_text_field($_POST['secret_id']) : '';
        $options['secret_key'] = isset($_POST['secret_key']) ? sanitize_text_field($_POST['secret_key']) : '';
        $options['nothumb'] = isset($_POST['nothumb']) ? 'true' : 'false';
        $options['nolocalsaving'] = isset($_POST['nolocalsaving']) ? 'true' : 'false';
        $options['delete_options'] = isset($_POST['delete_options']) ? 'true' : 'false';

        //仅用于插件卸载时比较使用
        $options['upload_url_path'] = isset($_POST['upload_url_path']) ? sanitize_text_field(stripslashes($_POST['upload_url_path'])) : '';

        $options['ci_style'] = isset($_POST['ci_style']) ? sanitize_text_field($_POST['ci_style']) : '';
        $options['update_file_name'] = isset($_POST['update_file_name']) ? sanitize_text_field($_POST['update_file_name']) : 'false';
    }

    if (!empty($_POST) and $_POST['type'] == 'qcloud_cos_all') {
        $sync = cos_read_dir_queue(get_home_path() . cos_get_option('upload_path'));
        foreach ($sync as $k) {
            cos_file_upload($k['key'], $k['filepath']);
        }
        echo '<div class="updated"><p><strong>本次操作成功同步' . count($sync) . '个文件</strong></p></div>';
    }

    // 替换数据库链接
    if(!empty($_POST) and $_POST['type'] == 'qcloud_cos_replace') {
        $old_url = esc_url_raw($_POST['old_url']);
        $new_url = esc_url_raw($_POST['new_url']);

        global $wpdb;
        $posts_name = $wpdb->prefix .'posts';
        // 文章内容
        $posts_result = $wpdb->query("UPDATE $posts_name SET post_content = REPLACE( post_content, '$old_url', '$new_url') ");

        // 修改题图之类的
        $postmeta_name = $wpdb->prefix .'postmeta';
        $postmeta_result = $wpdb->query("UPDATE $postmeta_name SET meta_value = REPLACE( meta_value, '$old_url', '$new_url') ");

        echo '<div class="updated"><p><strong>替换成功！共替换文章内链'.$posts_result.'条、题图链接'.$postmeta_result.'条！</strong></p></div>';
    }

    // 若$options不为空数组，则更新数据
    if ($options !== array()) {

        $check_status = true;
        if (!empty($options['bucket']) && !empty($options['app_id']) && !empty($options['secret_id']) && !empty($options['secret_key'])) {
            $check_status = cos_check_bucket($options);
        }

        if ($check_status) {
            //更新数据库
            update_option('cos_options', $options);

            $upload_path = sanitize_text_field(trim(stripslashes($_POST['upload_path']), '/'));
            $upload_path = ($upload_path == '') ? ('wp-content/uploads') : ($upload_path);
            update_option('upload_path', $upload_path);
            $upload_url_path = sanitize_text_field(trim(stripslashes($_POST['upload_url_path']), '/'));
            update_option('upload_url_path', $upload_url_path);
            echo '<div class="updated"><p><strong>设置已保存！</strong></p></div>';
        }
    }

    $cos_options = get_option('cos_options', true);
    $cos_regional = esc_attr($cos_options['regional']);

    $cos_nothumb = esc_attr($cos_options['nothumb']);
    $cos_nothumb = ($cos_nothumb == 'true');

    $cos_nolocalsaving = esc_attr($cos_options['nolocalsaving']);
    $cos_nolocalsaving = ($cos_nolocalsaving == 'true');

    $cos_delete_options = esc_attr($cos_options['delete_options']);
    $cos_delete_options = ($cos_delete_options == 'true');

    $cos_update_file_name = esc_attr($cos_options['update_file_name']);

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    ?>
    <div class="wrap" style="margin: 10px;">
        <h1>腾讯云 COS 设置 <span style="font-size: 13px;">当前版本：<?php echo COS_VERSION; ?></span></h1>
        <p>插件网站：<a href="https://qq52o.me/" target="_blank">沈唁志</a> / <a href="https://qq52o.me/2518.html" target="_blank">Sync QCloud COS发布页面</a> / <a href="https://qq52o.me/2722.html" target="_blank">详细使用教程</a>；</p>
        <p>如果觉得此插件对你有所帮助，不妨到 <a href="https://github.com/sy-records/wordpress-qcloud-cos" target="_blank">GitHub</a> 上点个<code>Star</code>，<code>Watch</code>关注更新；</p>
        <hr/>
        <form name="form1" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . COS_BASEFOLDER . '/wordpress-qcloud-cos.php'); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>存储桶名称</legend>
                    </th>
                    <td>
                        <input type="text" name="bucket" value="<?php echo esc_attr($cos_options['bucket']); ?>" size="50" placeholder="请填写存储桶名称"/>

                        <p>请先访问 <a href="https://console.cloud.tencent.com/cos5/bucket" target="_blank">腾讯云控制台</a> 创建<code>存储桶</code>，再填写以上内容。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>存储桶地域</legend>
                    </th>
                    <td><select name="regional">
                            <option value="ap-beijing-1" <?php if ($cos_regional == 'tj' || $cos_regional == 'ap-beijing-1') {echo ' selected="selected"';}?>>北京一区（华北）</option>
                            <option value="ap-beijing" <?php if ($cos_regional == 'bj' || $cos_regional == 'ap-beijing') {echo ' selected="selected"';}?>>北京</option>
                            <option value="ap-nanjing" <?php if ($cos_regional == 'ap-nanjing') {echo ' selected="selected"';}?>>南京</option>
                            <option value="ap-shanghai" <?php if ($cos_regional == 'sh' || $cos_regional == 'ap-shanghai') {echo ' selected="selected"';}?>>上海（华东）</option>
                            <option value="ap-guangzhou" <?php if ($cos_regional == 'gz' || $cos_regional == 'ap-guangzhou') {echo ' selected="selected"';}?>>广州（华南）</option>
                            <option value="ap-chengdu" <?php if ($cos_regional == 'cd' || $cos_regional == 'ap-chengdu') {echo ' selected="selected"';}?>>成都（西南）</option>
                            <option value="ap-chongqing" <?php if ($cos_regional == 'ap-chongqing') {echo ' selected="selected"';}?>>重庆</option>
                            <option value="ap-shenzhen-fsi" <?php if ($cos_regional == 'ap-shenzhen-fsi') {echo ' selected="selected"';}?>>深圳金融</option>
                            <option value="ap-shanghai-fsi" <?php if ($cos_regional == 'ap-shanghai-fsi') {echo ' selected="selected"';}?>>上海金融</option>
                            <option value="ap-beijing-fsi" <?php if ($cos_regional == 'ap-beijing-fsi') {echo ' selected="selected"';}?>>北京金融</option>
                            <option value="ap-hongkong" <?php if ($cos_regional == 'hk' || $cos_regional == 'ap-hongkong') {echo ' selected="selected"';}?>>中国香港</option>
                            <option value="ap-singapore" <?php if ($cos_regional == 'sgp' || $cos_regional == '	ap-singapore') {echo ' selected="selected"';}?>>新加坡</option>
                            <option value="na-toronto" <?php if ($cos_regional == 'ca' || $cos_regional == 'na-toronto') {echo ' selected="selected"';}?>>多伦多</option>
                            <option value="eu-frankfurt" <?php if ($cos_regional == 'ger' || $cos_regional == 'eu-frankfurt') {echo ' selected="selected"';}?>>法兰克福</option>
                            <option value="ap-mumbai" <?php if ($cos_regional == 'ap-mumbai') {echo ' selected="selected"';}?>>孟买</option>
                            <option value="ap-seoul" <?php if ($cos_regional == 'ap-seoul') {echo ' selected="selected"';}?>>首尔</option>
                            <option value="na-siliconvalley" <?php if ($cos_regional == 'na-siliconvalley') {echo ' selected="selected"';}?>>硅谷</option>
                            <option value="na-ashburn" <?php if ($cos_regional == 'na-ashburn') {echo ' selected="selected"';}?>>弗吉尼亚</option>
                            <option value="ap-bangkok" <?php if ($cos_regional == 'ap-bangkok') {echo ' selected="selected"';}?>>曼谷</option>
                            <option value="eu-moscow" <?php if ($cos_regional == 'eu-moscow') {echo ' selected="selected"';}?>>莫斯科</option>
                            <option value="accelerate" <?php if ($cos_regional == 'accelerate') {echo ' selected="selected"';}?>>全球加速</option>
                        </select>
                        <p>请选择您创建的<code>存储桶</code>所在地域</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>APP ID</legend>
                    </th>
                    <td>
                        <input type="text" name="app_id" value="<?php echo esc_attr($cos_options['app_id']); ?>" size="50" placeholder="APP ID"/>

                        <p>请先访问 <a href="https://console.cloud.tencent.com/cos5/key" target="_blank">腾讯云控制台</a> 获取 <code>APP ID、SecretID、SecretKey</code></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>SecretID</legend>
                    </th>
                    <td><input type="text" name="secret_id" value="<?php echo esc_attr($cos_options['secret_id']); ?>" size="50" placeholder="SecretID"/></td>
                </tr>
                <tr>
                    <th>
                        <legend>SecretKey</legend>
                    </th>
                    <td>
                        <input type="password" name="secret_key" value="<?php echo esc_attr($cos_options['secret_key']); ?>" size="50" placeholder="SecretKey"/>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>不上传缩略图</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="nothumb" <?php if ($cos_nothumb) { echo 'checked="checked"'; } ?> />

                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>不在本地保留备份</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="nolocalsaving" <?php if ($cos_nolocalsaving) { echo 'checked="checked"'; } ?> />

                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>是否删除配置信息</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="delete_options" <?php if ($cos_delete_options) { echo 'checked="checked"'; } ?> />

                        <p>建议不勾选。勾选后禁用插件时会删除保存的配置信息和恢复默认URL前缀。不勾选卸载插件时也会进行删除和恢复。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>自动重命名文件</legend>
                    </th>
                    <td>
                        <select name="update_file_name">
                            <option <?php if ($cos_update_file_name == 'false') {echo 'selected="selected"';} ?> value="false">不处理</option>
                            <option <?php if ($cos_update_file_name == 'md5') {echo 'selected="selected"';} ?> value="md5">MD5</option>
                            <option <?php if ($cos_update_file_name == 'time') {echo 'selected="selected"';} ?> value="time">时间戳+随机数</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>本地文件夹</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_path" value="<?php echo cos_get_option('upload_path'); ?>" size="50" placeholder="请输入上传文件夹"/>

                        <p>附件在服务器上的存储位置，例如： <code>wp-content/uploads</code> （注意不要以“/”开头和结尾），根目录请输入<code>.</code>。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>URL前缀</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_url_path" value="<?php echo cos_get_option('upload_url_path'); ?>" size="50" placeholder="请输入URL前缀"/>

                        <p><b>注意：</b></p>

                        <p>1）URL前缀的格式为 <code><?php echo $protocol;?>{cos域名}/{本地文件夹}</code> ，“本地文件夹”务必与上面保持一致（结尾无 <code>/</code> ），或者“本地文件夹”为 <code>.</code> 时 <code><?php echo $protocol;?>{cos域名}</code> 。</p>

                        <p>2）COS中的存放路径（即“文件夹”）与上述 <code>本地文件夹</code> 中定义的路径是相同的（出于方便切换考虑）。</p>

                        <p>3）如果需要使用 <code>独立域名</code> ，直接将 <code>{cos域名}</code> 替换为 <code>独立域名</code> 即可。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>图片处理样式</legend>
                    </th>
                    <td>
                        <input type="text" name="ci_style" value="<?php echo esc_attr($cos_options['ci_style']); ?>" size="50" placeholder="请输入图片处理样式，留空表示不处理"/>

                        <p><b>获取样式：</b></p>

                        <p>1）在 <a href="https://console.cloud.tencent.com/cos5/bucket" target="_blank">存储桶列表</a> 中对应桶的 <code>图片处理</code> 处添加。具体样式设置参考<a href="https://cloud.tencent.com/document/product/460/6936" target="_blank">腾讯云文档</a>。</p>

                        <p>2）填写时需要将<code>分隔符</code>和对应的<code>名称</code>或 <code>描述</code>进行拼接，例如：</p>

                        <p><code>分隔符</code>为<code>!</code>(感叹号)，<code>名称</code>为<code>blog</code>，<code>描述</code>为 <code>	imageMogr2/format/webp/interlace/0/quality/100</code></p>
                        <p>则填写为 <code>!blog</code> 或 <code>?imageMogr2/format/webp/interlace/0/quality/100</code></p>
                    </td>
                </tr>
                <tr>
                    <th><legend>保存/更新选项</legend></th>
                    <td><input type="submit" name="submit" class="button button-primary" value="保存更改"/></td>
                </tr>
            </table>
            <input type="hidden" name="type" value="cos_set">
        </form>
        <hr>
        <form name="form2" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . COS_BASEFOLDER . '/wordpress-qcloud-cos.php'); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>同步历史附件</legend>
                    </th>
                    <input type="hidden" name="type" value="qcloud_cos_all">
                    <td>
                        <input type="submit" name="submit" class="button button-secondary" value="开始同步"/>
                        <p><b>注意：如果是首次同步，执行时间将会非常长（根据你的历史附件数量），有可能会因为执行时间过长，导致页面显示超时或者报错。<br> 所以，建议附件数量过多的用户，直接使用官方的<a target="_blank" rel="nofollow" href="https://cloud.tencent.com/document/product/436/10976">同步工具</a>进行迁移，具体可参考<a target="_blank" rel="nofollow" href="https://qq52o.me/2809.html">使用 COSCLI 快速迁移本地数据到 COS</a></b></p>
                    </td>
                </tr>
            </table>
        </form>
        <hr>
        <form name="form3" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . COS_BASEFOLDER . '/wordpress-qcloud-cos.php'); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>数据库原链接替换</legend>
                    </th>
                    <td>
                        <input type="text" name="old_url" size="50" placeholder="请输入要替换的旧域名"/>
                        <p>如：<code>https://qq52o.me</code></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend></legend>
                    </th>
                    <td>
                        <input type="text" name="new_url" size="50" placeholder="请输入要替换的新域名"/>
                        <p>如：COS访问域名<code>https://bucket-appid.cos.ap-xxx.myqcloud.com</code>或自定义域名<code>https://resources.qq52o.me</code></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend></legend>
                    </th>
                    <input type="hidden" name="type" value="qcloud_cos_replace">
                    <td>
                        <input type="submit" name="submit"  class="button button-secondary" value="开始替换"/>
                        <p><b>注意：如果是首次替换，请注意备份！此功能会替换文章以及设置的特色图片（题图）等使用的资源链接</b></p>
                    </td>
                </tr>
            </table>
        </form>
        <hr/>
        <p>优惠活动：<a href="https://qq52o.me/welfare.html#qcloud" target="_blank">腾讯云优惠</a> / <a href="https://go.qq52o.me/a/cos" target="_blank">腾讯云COS资源包优惠</a>；</p>
        <p>限时推广：<a href="https://cloud.tencent.com/developer/support-plan?invite_code=cqidlih5bagj" target="_blank">技术博客可以加入腾讯云云+社区定制周边礼品等你来拿</a> / <a href="https://go.qq52o.me/qm/ccs" target="_blank">欢迎加入云存储插件交流群，QQ群号：887595381</a>；</p>
    </div>
<?php
}
?>
