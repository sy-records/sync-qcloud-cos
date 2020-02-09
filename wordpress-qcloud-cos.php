<?php
/*
Plugin Name: Sync QCloud COS
Plugin URI: https://qq52o.me/2518.html
Description: 使用腾讯云对象存储服务 COS 作为附件存储空间。（This is a plugin that uses Tencent Cloud Cloud Object Storage for attachments remote saving.）
Version: 1.6.1
Author: 沈唁
Author URI: https://qq52o.me
License: GPL v3
*/

require_once 'cos-sdk-v5/vendor/autoload.php';

use Qcloud\Cos\Client;

define('COS_VERSION', "1.6.1");
define('COS_BASEFOLDER', plugin_basename(dirname(__FILE__)));

// 初始化选项
register_activation_hook(__FILE__, 'cos_set_options');
// 初始化选项
function cos_set_options()
{
    $options = array(
        'bucket' => "",
        'regional' => "ap-beijing",
        'app_id' => "",
        'secret_id' => "",
        'secret_key' => "",
        'nothumb' => "false", // 是否上传缩略图
        'nolocalsaving' => "false", // 是否保留本地备份
        'upload_url_path' => "", // URL前缀
    );
    add_option('cos_options', $options, '', 'yes');
}

function cos_get_client()
{
    $cos_opt = get_option('cos_options', true);
    return new Client(array(
                    'region' => esc_attr($cos_opt['regional']),
                    'schema' =>  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http",
                    'credentials' => array(
                            "secretId" => esc_attr($cos_opt['secret_id']),
                            "secretKey" => esc_attr($cos_opt['secret_key'])
                    )));
}

function cos_get_bucket_name()
{
    $cos_options = get_option('cos_options', true);
    $cos_bucket = esc_attr($cos_options['bucket']);
    $cos_app_id = esc_attr($cos_options['app_id']);
    $needle = "-" . $cos_app_id;
    if (strpos($cos_bucket, $needle) !== false){
        return $cos_bucket;
    }
    return $cos_bucket . $needle;
}

function cos_check_bucket($cos_opt)
{
    $client = new Client(array(
                     'region' => esc_attr($cos_opt['regional']),
                     'schema' =>  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http",
                     'credentials' => array(
                         "secretId" => esc_attr($cos_opt['secret_id']),
                         "secretKey" => esc_attr($cos_opt['secret_key'])
                     )
                 ));
    try {
        $buckets_obj = $client->listBuckets();
        if (isset($buckets_obj['Buckets'][0]['Bucket'])) {
            $cos_bucket = esc_attr($cos_opt['bucket']);
            $cos_app_id = esc_attr($cos_opt['app_id']);
            $needle = "-".$cos_app_id;
            if (strpos($cos_bucket, $needle) !== false){
                $setting_bucket = $cos_bucket;
            } else {
                $setting_bucket = $cos_bucket . $needle;
            }

            $buckets_msg = "存储桶名称错误，你需要设置的存储桶名称可能在以下名称中： ";
            foreach ($buckets_obj['Buckets'][0]['Bucket'] as $bucket) {
                if ($setting_bucket == $bucket['Name']) {
                    return true;
                } else {
                    $buckets_msg .= "<code>{$bucket['Name']}</code> ";
                }
            }
            echo '<div class="error"><p><strong>'. $buckets_msg .'</strong></p></div>';
        }
    } catch (Qcloud\Cos\Exception\ServiceResponseException $e) {
        $errorMessage = $e->getMessage();
        $statusCode = $e->getStatusCode();
        echo '<div class="error"><p><strong>ErrorCode：'. $statusCode .'，ErrorMessage：'. $errorMessage .'</strong></p></div>';
    }
    return false;
}

/**
 * 上传函数
 *
 * @param  $object
 * @param  $file
 * @param  $opt
 * @return bool
 */
function cos_file_upload($object, $file)
{
    //如果文件不存在，直接返回false
    if (!@file_exists($file)) {
        return false;
    }

    $bucket = cos_get_bucket_name();
    $file = fopen($file, 'rb');
    if ($file) {
        $cosClient = cos_get_client();
        $cosClient->Upload($bucket, $object, $file);
    } else {
        return false;
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
 * @param  $file 本地文件路径
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
    } catch (Exception $ex) {
        return false;
    }
}

/**
 * 删除cos中的文件
 * @param $file
 * @return bool
 */
function cos_delete_cos_file($file)
{
    $bucket = cos_get_bucket_name();
    $cosClient = cos_get_client();
    $cosClient->deleteObject(array('Bucket' => $bucket, 'Key' => $file));
}

/**
 * 上传附件（包括图片的原图）
 *
 * @param  $metadata
 * @return array()
 */
function cos_upload_attachments($metadata)
{
    //生成object在OSS中的存储路径
    if (get_option('upload_path') == '.') {
        //如果含有“./”则去除之
        $metadata['file'] = str_replace("./", '', $metadata['file']);
    }
    $object = str_replace("\\", '/', $metadata['file']);
    $object = str_replace(get_home_path(), '', $object);

    //在本地的存储路径
    $file = get_home_path() . $object; //向上兼容，较早的WordPress版本上$metadata['file']存放的是相对路径

    //执行上传操作
    cos_file_upload('/' . $object, $file);

    //如果不在本地保存，则删除本地文件
    if (cos_is_delete_local_file()) {
        cos_delete_local_file($file);
    }
    return $metadata;
}

//避免上传插件/主题时出现同步到COS的情况
if (substr_count($_SERVER['REQUEST_URI'], '/update.php') <= 0) {
    add_filter('wp_handle_upload', 'cos_upload_attachments', 50);
}

/**
 * 上传图片的缩略图
 */
function cos_upload_thumbs($metadata)
{
    //上传所有缩略图
    if (isset($metadata['sizes']) && count($metadata['sizes']) > 0) {
        //获取COS插件的配置信息
        $cos_options = get_option('cos_options', true);
        //是否需要上传缩略图
        $nothumb = (esc_attr($cos_options['nothumb']) == 'true');
        //是否需要删除本地文件
        $is_delete_local_file = (esc_attr($cos_options['nolocalsaving']) == 'true');
        //如果禁止上传缩略图，就不用继续执行了
        if ($nothumb) {
            return $metadata;
        }
        //获取上传路径
        $wp_uploads = wp_upload_dir();
        $basedir = $wp_uploads['basedir'];
        $file_dir = $metadata['file'];
        //得到本地文件夹和远端文件夹
        $file_path = $basedir . '/' . dirname($file_dir) . '/';
        if (get_option('upload_path') == '.') {
            $file_path = str_replace("\\", '/', $file_path);
            $file_path = str_replace(get_home_path() . "./", '', $file_path);
        } else {
            $file_path = str_replace("\\", '/', $file_path);
        }

        $object_path = str_replace(get_home_path(), '', $file_path);

        //there may be duplicated filenames,so ....
        foreach ($metadata['sizes'] as $val) {
            //生成object在COS中的存储路径
            $object = '/' . $object_path . $val['file'];
            //生成本地存储路径
            $file = $file_path . $val['file'];

            //执行上传操作
            cos_file_upload($object, $file);

            //如果不在本地保存，则删除
            if ($is_delete_local_file) {
                cos_delete_local_file($file);
            }

        }
    }
    return $metadata;
}

//避免上传插件/主题时出现同步到COS的情况
if (substr_count($_SERVER['REQUEST_URI'], '/update.php') <= 0) {
    add_filter('wp_generate_attachment_metadata', 'cos_upload_thumbs', 100);
}

/**
 * 删除远端文件，删除文件时触发
 * @param $post_id
 */
function cos_delete_remote_attachment($post_id) {
    $meta = wp_get_attachment_metadata( $post_id );

    $cos_options = get_option('cos_options', true);

    if (isset($meta['file'])) {
        // meta['file']的格式为 "2020/01/wp-bg.png"
        $upload_path = get_option('upload_path');
        if ($upload_path == '') {
            $upload_path = 'wp-content/uploads';
        }
        $file_path = $upload_path . '/' . $meta['file'];
        cos_delete_cos_file(str_replace("\\", '/', $file_path));
        $is_nothumb = (esc_attr($cos_options['nothumb']) == 'false');
        if ($is_nothumb) {
            // 删除缩略图
            if (isset($meta['sizes']) && count($meta['sizes']) > 0) {
                foreach ($meta['sizes'] as $val) {
                    $size_file = dirname($file_path) . '/' . $val['file'];
                    cos_delete_cos_file(str_replace("\\", '/', $size_file));
                }
            }
        }
    }
}
add_action('delete_attachment', 'cos_delete_remote_attachment');

// 当upload_path为根目录时，需要移除URL中出现的“绝对路径”
function cos_modefiy_img_url($url, $post_id)
{
    $home_path = str_replace(array('/', '\\'), array('', ''), get_home_path());
    $url = str_replace($home_path, '', $url);
    return $url;
}

if (get_option('upload_path') == '.') {
    add_filter('wp_get_attachment_url', 'cos_modefiy_img_url', 30, 2);
}

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

function cos_read_dir_queue($dir)
{
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
                    //echo explode(get_option('upload_path'),$path)[1];
                }
            }
            closedir($handle);
        }
        $i = '';
        foreach ($files as $v) {
            $i++;
            if (!is_dir($v)) {
                $dd[$i]['j'] = $v;
                $dd[$i]['x'] = '/' . get_option('upload_path') . explode(get_option('upload_path'), $v)[1];
            }
        }
    } else {
        $dd = '';
    }
    return $dd;
}

// 在插件列表页添加设置按钮
function cos_plugin_action_links($links, $file)
{
    if ($file == plugin_basename(dirname(__FILE__) . '/wordpress-qcloud-cos.php')) {
        $links[] = '<a href="options-general.php?page=' . COS_BASEFOLDER . '/wordpress-qcloud-cos.php">设置</a>';
        $links[] = '<a href="https://qq52o.me/sponsor.html" target="_blank">赞赏</a>';
        $links[] = '<a href="https://github.com/sy-records/wordpress-qcloud-cos" target="_blank">Star支持</a>';
    }
    return $links;
}
add_filter('plugin_action_links', 'cos_plugin_action_links', 10, 2);

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
        //仅用于插件卸载时比较使用
        $options['upload_url_path'] = isset($_POST['upload_url_path']) ? sanitize_text_field(stripslashes($_POST['upload_url_path'])) : '';
    }

    if (!empty($_POST) and $_POST['type'] == 'qcloud_cos_all') {
        $synv = cos_read_dir_queue(get_home_path() . get_option('upload_path'));
        $i = 0;
        foreach ($synv as $k) {
            $i++;
            cos_file_upload($k['x'], $k['j']);
        }
        echo '<div class="updated"><p><strong>本次操作成功同步' . $i . '个文件</strong></p></div>';
    }

    // 替换数据库链接
    if(!empty($_POST) and $_POST['type'] == 'qcloud_cos_replace') {
        global $wpdb;
        $table_name = $wpdb->prefix .'posts';
        $oldurl = esc_url_raw($_POST['old_url']);
        $newurl = esc_url_raw($_POST['new_url']);
        $result = $wpdb->query("UPDATE $table_name SET post_content = REPLACE( post_content, '$oldurl', '$newurl') ");

        echo '<div class="updated"><p><strong>替换成功！共批量执行'.$result.'条！</strong></p></div>';
    }

    // 若$options不为空数组，则更新数据
    if ($options !== array()) {

        $check_status = cos_check_bucket($options);

        if ($check_status){
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
    $upload_path = get_option('upload_path');
    $upload_url_path = get_option('upload_url_path');

    $cos_bucket = esc_attr($cos_options['bucket']);
    $cos_regional = esc_attr($cos_options['regional']);
    $cos_app_id = esc_attr($cos_options['app_id']);
    $cos_secret_id = esc_attr($cos_options['secret_id']);
    $cos_secret_key = esc_attr($cos_options['secret_key']);

    $cos_nothumb = esc_attr($cos_options['nothumb']);
    $cos_nothumb = ($cos_nothumb == 'true');

    $cos_nolocalsaving = esc_attr($cos_options['nolocalsaving']);
    $cos_nolocalsaving = ($cos_nolocalsaving == 'true');
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    ?>
    <div class="wrap" style="margin: 10px;">
        <h1>腾讯云 COS 设置 <span style="font-size: 13px;">当前版本：<?php echo COS_VERSION; ?></span></h1>
        <p>插件网站： <a href="https://qq52o.me/" target="_blank">沈唁志</a> / <a href="https://qq52o.me/2518.html" target="_blank">Sync QCloud COS发布页面</a> / <a href="https://qq52o.me/2722.html" target="_blank">详细使用教程</a>；</p>
        <p>优惠促销： <a href="https://qq52o.me/welfare.html#qcloud" target="_blank">腾讯云优惠</a> / <a href="https://qq52o.me/go/qcloud-cos" target="_blank">腾讯云COS资源包优惠</a>；</p>
        <p>如果觉得此插件对你有所帮助，不妨到 <a href="https://github.com/sy-records/wordpress-qcloud-cos" target="_blank">Github</a> 上点个<code>Star</code>，<code>Watch</code>关注更新；</p>
        <hr/>
        <form name="form1" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . COS_BASEFOLDER . '/wordpress-qcloud-cos.php'); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>存储桶名称</legend>
                    </th>
                    <td>
                        <input type="text" name="bucket" value="<?php echo $cos_bucket; ?>" size="50" placeholder="请填写存储桶名称"/>

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
                            <option value="ap-shanghai" <?php if ($cos_regional == 'sh' || $cos_regional == 'ap-shanghai') {echo ' selected="selected"';}?>>上海（华东）</option>
                            <option value="ap-guangzhou" <?php if ($cos_regional == 'gz' || $cos_regional == 'ap-guangzhou') {echo ' selected="selected"';}?>>广州（华南）</option>
                            <option value="ap-chengdu" <?php if ($cos_regional == 'cd' || $cos_regional == 'ap-chengdu') {echo ' selected="selected"';}?>>成都（西南）</option>
                            <option value="ap-chongqing" <?php if ($cos_regional == 'ap-chongqing') {echo ' selected="selected"';}?>>重庆</option>
                            <option value="ap-hongkong" <?php if ($cos_regional == 'hk' || $cos_regional == 'ap-hongkong') {echo ' selected="selected"';}?>>中国香港</option>
                            <option value="ap-singapore" <?php if ($cos_regional == 'sgp' || $cos_regional == '	ap-singapore') {echo ' selected="selected"';}?>>新加坡</option>
                            <option value="na-toronto" <?php if ($cos_regional == 'ca' || $cos_regional == 'na-toronto') {echo ' selected="selected"';}?>>多伦多</option>
                            <option value="eu-frankfurt" <?php if ($cos_regional == 'ger' || $cos_regional == 'eu-frankfurt') {echo ' selected="selected"';}?>>法兰克福</option>
                            <option value="ap-mumbai" <?php if ($cos_regional == 'ap-mumbai') {echo ' selected="selected"';}?>>孟买</option>
                            <option value="ap-mumbai" <?php if ($cos_regional == 'ap-mumbai') {echo ' selected="selected"';}?>>首尔</option>
                            <option value="na-siliconvalley" <?php if ($cos_regional == 'na-siliconvalley') {echo ' selected="selected"';}?>>硅谷</option>
                            <option value="na-ashburn" <?php if ($cos_regional == 'na-ashburn') {echo ' selected="selected"';}?>>弗吉尼亚</option>
                            <option value="ap-bangkok" <?php if ($cos_regional == 'ap-bangkok') {echo ' selected="selected"';}?>>曼谷</option>
                            <option value="eu-moscow" <?php if ($cos_regional == 'eu-moscow') {echo ' selected="selected"';}?>>莫斯科</option>

                        </select>
                        <p>请选择您创建的<code>存储桶</code>所在地域</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>APP ID</legend>
                    </th>
                    <td>
                        <input type="text" name="app_id" value="<?php echo $cos_app_id; ?>" size="50" placeholder="APP ID"/>

                        <p>请先访问 <a href="https://console.cloud.tencent.com/cos5/key" target="_blank">腾讯云控制台</a> 获取 <code>APP ID、SecretID、SecretKey</code></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>SecretID</legend>
                    </th>
                    <td><input type="text" name="secret_id" value="<?php echo $cos_secret_id; ?>" size="50" placeholder="secretID"/></td>
                </tr>
                <tr>
                    <th>
                        <legend>SecretKey</legend>
                    </th>
                    <td>
                        <input type="text" name="secret_key" value="<?php echo $cos_secret_key; ?>" size="50" placeholder="secretKey"/>
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
                        <input type="checkbox"
                               name="nolocalsaving" <?php if ($cos_nolocalsaving) { echo 'checked="checked"'; } ?> />

                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>本地文件夹</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_path" value="<?php echo $upload_path; ?>" size="50" placeholder="请输入上传文件夹"/>

                        <p>附件在服务器上的存储位置，例如： <code>wp-content/uploads</code> （注意不要以“/”开头和结尾），根目录请输入<code>.</code>。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>URL前缀</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_url_path" value="<?php echo $upload_url_path; ?>" size="50" placeholder="请输入URL前缀"/>

                        <p><b>注意：</b></p>

                        <p>1）URL前缀的格式为 <code><?php echo $protocol;?>{cos域名}/{本地文件夹}</code> ，“本地文件夹”务必与上面保持一致（结尾无 <code>/</code> ），或者“本地文件夹”为 <code>.</code> 时 <code><?php echo $protocol;?>{cos域名}</code> 。</p>

                        <p>2）COS中的存放路径（即“文件夹”）与上述 <code>本地文件夹</code> 中定义的路径是相同的（出于方便切换考虑）。</p>

                        <p>3）如果需要使用 <code>独立域名</code> ，直接将 <code>{cos域名}</code> 替换为 <code>独立域名</code> 即可。</p>
                    </td>
                </tr>
                <tr>
                    <th><legend>保存/更新选项</legend></th>
                    <td><input type="submit" name="submit" class="button button-primary" value="保存更改"/></td>
                </tr>
            </table>
            <input type="hidden" name="type" value="cos_set">
        </form>
        <form name="form2" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . COS_BASEFOLDER . '/wordpress-qcloud-cos.php'); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>同步历史附件</legend>
                    </th>
                    <input type="hidden" name="type" value="qcloud_cos_all">
                    <td>
                        <input type="submit" name="submit" class="button button-secondary" value="开始同步"/>
                        <p><b>注意：如果是首次同步，执行时间将会十分十分长（根据你的历史附件数量），有可能会因执行时间过长，页面显示超时或者报错。<br> 所以，建议那些几千上万附件的大神们，考虑官方的 <a target="_blank" rel="nofollow" href="https://www.qcloud.com/document/product/436/7133">同步工具</a></b></p>
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
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend></legend>
                    </th>
                    <td>
                        <input type="text" name="new_url" size="50" placeholder="请输入要替换的新域名"/>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend></legend>
                    </th>
                    <input type="hidden" name="type" value="qcloud_cos_replace">
                    <td>
                        <input type="submit" name="submit"  class="button button-secondary" value="开始替换"/>
                        <p><b>注意：如果是首次替换，请注意备份！此功能只限于替换文章中使用的资源链接</b></p>
                    </td>
                </tr>
            </table>
        </form>
    </div>
<?php
}
?>
