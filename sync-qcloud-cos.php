<?php
/*
Plugin Name: Sync QCloud COS
Plugin URI: https://qq52o.me/2518.html
Description: 使用腾讯云对象存储服务 COS 作为附件存储空间。（This is a plugin that uses Tencent Cloud Cloud Object Storage for attachments remote saving.）
Version: 2.3.6
Author: 沈唁
Author URI: https://qq52o.me
License: Apache2.0
*/

if (!defined('ABSPATH')) {
    exit;
}

require_once 'cos-sdk-v5/vendor/autoload.php';

use Qcloud\Cos\Client;
use Qcloud\Cos\Exception\ServiceResponseException;
use SyncQcloudCos\CI\Audit;
use SyncQcloudCos\CI\FilePreview;
use SyncQcloudCos\CI\ImageSlim;
use SyncQcloudCos\CI\Service;
use SyncQcloudCos\ErrorCode;
use SyncQcloudCos\Monitor\Charts;
use SyncQcloudCos\Monitor\DataPoints;

define('COS_VERSION', '2.3.6');
define('COS_PLUGIN_SLUG', 'sync-qcloud-cos');
define('COS_PLUGIN_PAGE', plugin_basename(dirname(__FILE__)) . '%2F' . basename(__FILE__));

if (!function_exists('get_home_path')) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
}

// 初始化选项
register_activation_hook(__FILE__, 'cos_set_options');
function cos_set_options()
{
    $options = [
        'bucket' => '',
        'regional' => 'ap-beijing',
        'app_id' => '',
        'secret_id' => '',
        'secret_key' => '',
        'nothumb' => 'false', // 是否上传缩略图
        'nolocalsaving' => 'false', // 是否保留本地备份
        'delete_options' => 'true',
        'upload_url_path' => '', // URL前缀
        'update_file_name' => 'false', // 是否重命名文件名
        'ci_style' => '',
        'ci_image_slim' => 'off',
        'ci_image_slim_mode' => '',
        'ci_image_slim_suffix' => '',
        'attachment_preview' => 'off',
        'ci_text_comments' => 'off',
        'skip_comment_validation_on_login' => 'off',
        'ci_text_comments_strategy' => '',
        'ci_text_comments_check_roles' => ''
    ];
    add_option('cos_options', $options, '', 'yes');
}

/**
 * @param array $cos_options
 * @return Client
 */
function cos_get_client($cos_options = null)
{
    if ($cos_options === null) {
        $cos_options = get_option('cos_options', true);
    }
    return new Client([
                          'region' => esc_attr($cos_options['regional']),
                          'scheme' => cos_get_url_scheme(''),
                          'credentials' => [
                              'secretId' => esc_attr($cos_options['secret_id']),
                              'secretKey' => esc_attr($cos_options['secret_key'])
                          ],
                          'userAgent' => 'WordPress v' . $GLOBALS['wp_version'] . '; SyncQCloudCOS v' . COS_VERSION . '; SDK v' . Client::VERSION,
                      ]);
}

function cos_get_bucket_name($cos_options = null)
{
    if ($cos_options === null) {
        $cos_options = get_option('cos_options', true);
    }
    $cos_bucket = esc_attr($cos_options['bucket']);
    $cos_app_id = esc_attr($cos_options['app_id']);
    $needle = '-' . $cos_app_id;
    if (strpos($cos_bucket, $needle) !== false) {
        return $cos_bucket;
    }
    return $cos_bucket . $needle;
}

function cos_check_bucket($cos_options)
{
    try {
        $client = cos_get_client($cos_options);
        $bucket = cos_get_bucket_name($cos_options);
        $client->HeadBucket(['Bucket' => $bucket]);

        return true;
    } catch (ServiceResponseException $e) {
        $message = (string)$e;
        $errorCode = $e->getCosErrorCode();
        if ($errorCode == ErrorCode::NO_SUCH_BUCKET) {
            $message = '<code>Bucket</code> 不存在，请检查存储桶名称和 <code>APP ID</code> 参数！';
        } elseif ($errorCode == ErrorCode::ACCESS_DENIED) {
            $message = '<code>SecretID</code> 或 <code>SecretKey</code> 有误，请检查配置信息！';
        }
        echo "<div class='error'><p><strong>{$message}</strong></p></div>";
    }
    return false;
}

/**
 * @param Client $client
 * @param string $bucket
 * @return Client
 */
function cos_replace_client_region($client, $bucket)
{
    if ($client->getCosConfig('region') != 'accelerate') {
        return $client;
    }

    $list = $client->listBuckets();
    $buckets = $list['Buckets'][0]['Bucket'];
    $buckets = array_column($buckets, null, 'Name');

    if (isset($buckets[$bucket])) {
        $client->setCosConfig('region', $buckets[$bucket]['Location']);
    }

    return $client;
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
            $cosClient->upload($bucket, $object, $file);

            if (is_resource($file)) {
                fclose($file);
            }

            if ($no_local_file) {
                cos_delete_local_file($filename);
            }
        }
    } catch (ServiceResponseException $e) {
        if (WP_DEBUG) {
            echo json_encode(['errorMessage' => $e->getMessage(), 'statusCode' => $e->getStatusCode(), 'requestId' => $e->getRequestId()]);
            exit();
        }
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
    return esc_attr($cos_options['nolocalsaving']) == 'true';
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
    } catch (Exception $ex) {
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
    $cosClient->deleteObject(['Bucket' => $bucket, 'Key' => $file]);
}

/**
 * 批量删除cos中的文件
 * @param array $files_key
 */
function cos_delete_cos_files(array $files_key)
{
    $bucket = cos_get_bucket_name();
    $cosClient = cos_get_client();
    $cosClient->deleteObjects(['Bucket' => $bucket, 'Objects' => $files_key]);
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
    $image_mime_types = [
        $mime_types['jpg|jpeg|jpe'],
        $mime_types['gif'],
        $mime_types['png'],
        $mime_types['bmp'],
        $mime_types['tiff|tif'],
        $mime_types['webp'],
        $mime_types['ico'],
        $mime_types['heic'],
    ];
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
    if (empty($metadata['file'])) {
        return $metadata;
    }

    //获取上传路径
    $wp_uploads = wp_upload_dir();
    $basedir = $wp_uploads['basedir'];
    $upload_path = cos_get_option('upload_path');

    $cos_options = get_option('cos_options', true);
    $no_local_file = esc_attr($cos_options['nolocalsaving']) == 'true';
    $no_thumb = esc_attr($cos_options['nothumb']) == 'true';

    // Maybe there is a problem with the old version
    $file = $basedir . '/' . $metadata['file'];
    if ($upload_path != '.') {
        $path_array = explode($upload_path, $file);
        if (count($path_array) >= 2) {
            $object = '/' . $upload_path . end($path_array);
        }
    } else {
        $object = '/' . $metadata['file'];
        $file = str_replace('./', '', $file);
    }

    cos_file_upload($object, $file, $no_local_file);

    //得到本地文件夹和远端文件夹
    $dirname = dirname($metadata['file']);
    $file_path = $dirname != '.' ? "{$basedir}/{$dirname}/" : "{$basedir}/";
    $file_path = str_replace("\\", '/', $file_path);
    if ($upload_path == '.') {
        $file_path = str_replace('./', '', $file_path);
    }
    $object_path = str_replace(get_home_path(), '', $file_path);

    if (!empty($metadata['original_image'])) {
        cos_file_upload("/{$object_path}{$metadata['original_image']}", "{$file_path}{$metadata['original_image']}", $no_local_file);
    }

    //如果禁止上传缩略图，就不用继续执行了
    if ($no_thumb) {
        return $metadata;
    }

    //上传所有缩略图
    if (!empty($metadata['sizes'])) {
        //there may be duplicated filenames,so ....
        foreach ($metadata['sizes'] as $val) {
            //生成object在COS中的存储路径
            $object = '/' . $object_path . $val['file'];
            //生成本地存储路径
            $file = $file_path . $val['file'];

            cos_file_upload($object, $file, $no_local_file);
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
    $meta = wp_get_attachment_metadata($post_id);

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

        // 超大图原图
        if (!empty($meta['original_image'])) {
            $deleteObjects[] = ['Key' => str_replace("\\", '/', $dirname . $meta['original_image'])];
        }

        // 删除缩略图
        if (!empty($meta['sizes'])) {
            foreach ($meta['sizes'] as $val) {
                $size_file = $dirname . $val['file'];

                $deleteObjects[] = ['Key' => str_replace("\\", '/', $size_file)];
            }
        }

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
    return str_replace(['./', get_home_path()], '', $url);
}

if (cos_get_option('upload_path') == '.') {
    add_filter('wp_get_attachment_url', 'cos_modefiy_img_url', 30, 2);
}

function cos_sanitize_file_name($filename)
{
    $cos_options = get_option('cos_options');
    switch ($cos_options['update_file_name']) {
        case 'md5':
            return md5($filename) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        case 'time':
            return gmdate('YmdHis', current_time('timestamp')) . wp_rand(100, 999) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        default:
            return $filename;
    }
}

add_filter('sanitize_file_name', 'cos_sanitize_file_name', 10, 1);

/**
 * @param string $homePath
 * @param string $uploadPath
 * @return array
 */
function cos_read_dir_queue($homePath, $uploadPath)
{
    $dir = $homePath . $uploadPath;
    $dirsToProcess = new SplQueue();
    $dirsToProcess->enqueue([$dir, '']);
    $foundFiles = [];

    while (!$dirsToProcess->isEmpty()) {
        list($currentDir, $relativeDir) = $dirsToProcess->dequeue();

        foreach (new DirectoryIterator($currentDir) as $fileInfo) {
            if ($fileInfo->isDot()) continue;

            $filepath = $fileInfo->getRealPath();

            // Compute the relative path of the file/directory with respect to upload path
            $currentRelativeDir = "{$relativeDir}/{$fileInfo->getFilename()}";

            if ($fileInfo->isDir()) {
                $dirsToProcess->enqueue([$filepath, $currentRelativeDir]);
            } else {
                // Add file path and key to the result array
                $foundFiles[] = [
                    'filepath' => $filepath,
                    'key' => '/' . $uploadPath . $currentRelativeDir
                ];
            }
        }
    }

    return $foundFiles;
}

// 在插件列表页添加设置按钮
function cos_plugin_action_links($links, $file)
{
    if ($file == urldecode(COS_PLUGIN_PAGE)) {
        $page = COS_PLUGIN_SLUG;
        $links[] = "<a href='admin.php?page={$page}'>设置</a>";
    }
    return $links;
}

add_filter('plugin_action_links', 'cos_plugin_action_links', 10, 2);

add_filter('the_content', 'cos_setting_content_ci');
add_filter('post_thumbnail_html', 'cos_setting_post_thumbnail_ci', 10, 3);

function cos_setting_content_ci($content)
{
    $option = get_option('cos_options');
    $style = esc_attr($option['ci_style']);
    if (!empty($style)) {
        preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $content, $images);
        if (!empty($images) && isset($images[1])) {
            $images[1] = array_unique($images[1]);
            foreach ($images[1] as $item) {
                if (strpos($item, esc_attr($option['upload_url_path'])) !== false) {
                    $content = str_replace($item, $item . $style, $content);
                }
            }
        }
    }

    if (!empty($option['attachment_preview']) && $option['attachment_preview'] == 'on') {
        preg_match_all('/<a.*?href="(.*?)".*?\/a>/is', $content, $matches);
        if (!empty($matches)) {
            list($tags, $links) = $matches;
            $handledLinks = [];
            foreach ($links as $index => $link) {
                if (in_array($link, $handledLinks)) {
                    continue;
                }

                if (FilePreview::isFileExtensionSupported($link, $option['upload_url_path'])) {
                    $iframe = '<iframe src="' . $link . '?ci-process=doc-preview&dstType=html" width="100%" allowFullScreen="true" height="800"></iframe>';
                    $content = str_replace($tags[$index], $iframe, $content);
                    $handledLinks[] = $link;
                }
            }
        }
    }

    return $content;
}

function cos_setting_post_thumbnail_ci($html, $post_id, $post_image_id)
{
    $option = get_option('cos_options');
    $style = esc_attr($option['ci_style']);
    if (!empty($style) && has_post_thumbnail()) {
        preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $html, $images);
        if (!empty($images) && isset($images[1])) {
            $images[1] = array_unique($images[1]);
            foreach ($images[1] as $item) {
                if (strpos($item, esc_attr($option['upload_url_path'])) !== false) {
                    $html = str_replace($item, $item . $style, $html);
                }
            }
        }
    }
    return $html;
}

/**
 * @param array $options
 * @return array
 */
function cos_append_options($options)
{
    $cos_options = get_option('cos_options');

    $options['ci_image_slim'] = $cos_options['ci_image_slim'] ?? 'off';
    $options['ci_image_slim_mode'] = $cos_options['ci_image_slim_mode'] ?? '';
    $options['ci_image_slim_suffix'] = $cos_options['ci_image_slim_suffix'] ?? '';

    $options['attachment_preview'] = $cos_options['attachment_preview'] ?? 'off';

    $options['ci_text_comments'] = $cos_options['ci_text_comments'] ?? 'off';
    $options['skip_comment_validation_on_login'] = $cos_options['skip_comment_validation_on_login'] ?? 'off';
    $options['ci_text_comments_strategy'] = $cos_options['ci_text_comments_strategy'] ?? '';
    $options['ci_text_comments_check_roles'] = $cos_options['ci_text_comments_check_roles'] ?? '';

    return $options;
}

/**
 * @param array $parametersToUpdate
 * @param array|null $currentParameters
 * @return array
 */
function cos_update_config_parameters($parametersToUpdate, $currentOptions = null)
{
    $currentOptions = $currentOptions ?: get_option('cos_options');

    $options = array_merge($currentOptions, $parametersToUpdate);

    update_option('cos_options', $options);

    return $options;
}

/**
 * @param array $slimConfigData
 * @param array $currentOptions
 * @return array
 */
function cos_sync_image_slim_config($slimConfigData, $currentOptions)
{
    $sanitizedConfig = [
        'ci_image_slim' => sanitize_text_field($slimConfigData['Status']),
        'ci_image_slim_mode' => sanitize_text_field($slimConfigData['SlimMode']),
        'ci_image_slim_suffix' => implode(',', ($slimConfigData['Suffixs']['Suffix'] ?? []))
    ];

    return cos_update_config_parameters($sanitizedConfig, $currentOptions);
}

/**
 * @param string $url
 * @param array|null $options
 * @return string
 */
function cos_append_ci_style($url, $options = null)
{
    if (empty($options)) $options = get_option('cos_options');

    if (!empty($options['ci_style']) && !empty($options['upload_url_path']) && strpos($url, esc_attr($options['upload_url_path'])) !== false) {
        $url .= esc_attr($options['ci_style']);
    }

    return $url;
}

/**
 * @param string $url
 * @param array|null $options
 * @return string
 */
function cos_local2remote($url, $options = null)
{
    if (empty($options)) $options = get_option('cos_options');

    $upload_path = get_option('upload_path');

    if ($upload_path != '.' && !empty($options['upload_url_path']) && strpos($url, $upload_path) !== false) {
        return $options['upload_url_path'] . explode($upload_path, $url)[1];
    }

    return $url;
}

function cos_get_regional($regional)
{
    $options = [
        'ap-beijing-1' => ['tj', '北京一区（华北）'],
        'ap-beijing' => ['bj', '北京'],
        'ap-nanjing' => ['ap-nanjing', '南京'],
        'ap-shanghai' => ['sh', '上海（华东）'],
        'ap-guangzhou' => ['gz', '广州（华南）'],
        'ap-chengdu' => ['cd', '成都（西南）'],
        'ap-chongqing' => ['ap-chongqing', '重庆'],
        'ap-shenzhen-fsi' => ['ap-shenzhen-fsi', '深圳金融'],
        'ap-shanghai-fsi' => ['ap-shanghai-fsi', '上海金融'],
        'ap-beijing-fsi' => ['ap-beijing-fsi', '北京金融'],
        'ap-hongkong' => ['hk', '中国香港'],
        'ap-singapore' => ['sgp', '新加坡'],
        'na-toronto' => ['ca', '多伦多'],
        'eu-frankfurt' => ['ger', '法兰克福'],
        'ap-mumbai' => ['ap-mumbai', '孟买'],
        'ap-seoul' => ['ap-seoul', '首尔'],
        'na-siliconvalley' => ['na-siliconvalley', '硅谷'],
        'na-ashburn' => ['na-ashburn', '弗吉尼亚'],
        'ap-bangkok' => ['ap-bangkok', '曼谷'],
        'eu-moscow' => ['eu-moscow', '莫斯科'],
        'accelerate' => ['accelerate', '全球加速']
    ];

    foreach ($options as $value => $info) {
        $selected = ($regional == $info[0] || $regional == $value) ? ' selected="selected"' : '';
        echo '<option value="' . $value . '"' . $selected . '>' . $info[1] . '</option>';
    }
}

/**
 * Generate URL scheme
 *
 * Decides whether 'http' or 'https' should be used based on the server configuration.
 *
 * @param string $separator separator used between the schema and the rest of the URL.
 * @return string 'http' or 'https' followed by the separator.
 */
function cos_get_url_scheme($separator = '://')
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    $scheme = $isHttps ? 'https' : 'http';

    return $scheme . $separator;
}

function cos_sync_setting_form($cos_options)
{
    $protocol = cos_get_url_scheme();

    $upload_path = cos_get_option('upload_path');
    $upload_path = $upload_path == '.' ? '' : $upload_path;

    $old_url = "{$protocol}{$_SERVER['HTTP_HOST']}/{$upload_path}";
    $new_url = $cos_options['upload_url_path'];
    return <<<HTML
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>数据库内容替换</legend>
                    </th>
                    <td>
                        <input type="text" name="old_url" size="50" placeholder="请输入要替换的内容"/>
                        <p><b>可能会填入：<code>{$old_url}</code></b></p>
                        <p>例如：<code>https://qq52o.me</code></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend></legend>
                    </th>
                    <td>
                        <input type="text" name="new_url" size="50" placeholder="请输入要替换为的内容"/>
                        <p><b>可能会填入：<code>{$new_url}</code></b></p>
                        <p>例如：COS访问域名<code>https://bucket-appid.cos.ap-xxx.myqcloud.com</code>或自定义域名<code>https://resources.qq52o.me</code></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend></legend>
                    </th>
                    <input type="hidden" name="type" value="qcloud_cos_replace">
                    <td>
                        <input type="submit" class="button button-secondary" value="开始替换"/>
                        <p><b>注意：如果是首次替换，请注意备份！此功能会替换文章以及设置的特色图片（题图）等使用的资源链接，也可用于其他需要替换文章内容的场景。</b></p>
                    </td>
                </tr>
            </table>
        </form>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>同步历史附件</legend>
                    </th>
                    <input type="hidden" name="type" value="qcloud_cos_all">
                    <td>
                        <input type="submit" class="button button-secondary" value="开始同步"/>
                        <p><b>注意：如果是首次同步，执行时间将会非常长（根据你的历史附件数量），有可能会因为执行时间过长，导致页面显示超时或者报错。<br> 所以，建议附件数量过多的用户，直接使用官方的<a target="_blank" rel="nofollow" href="https://cloud.tencent.com/document/product/436/10976">同步工具</a>进行迁移，具体可参考<a target="_blank" rel="nofollow" href="https://qq52o.me/2809.html">使用 COSCLI 快速迁移本地数据到 COS</a></b></p>
                    </td>
                </tr>
            </table>
        </form>
HTML;
}

/**
 * @param array $content
 * @return bool
 */
function cos_ci_image_slim_setting($content)
{
    $cos_options = get_option('cos_options', true);

    if (!cos_validate_configuration($cos_options)) {
        return false;
    }

    $slim = !empty($content['ci_image_slim']) ? sanitize_text_field($content['ci_image_slim']) : 'off';
    $mode = !empty($content['ci_image_slim_mode']) ? implode(',', $content['ci_image_slim_mode']) : '';
    $suffix = !empty($content['ci_image_slim_suffix']) ? implode(',', $content['ci_image_slim_suffix']) : '';

    if ($slim == 'on') {
        if (empty($mode)) {
            echo '<div class="error"><p><strong>图片极智压缩模式不能为空！</strong></p></div>';
            return false;
        }
        if (strpos($mode, 'Auto') !== false && empty($suffix)) {
            echo '<div class="error"><p><strong>图片极智压缩使用模式包含自动时，图片格式不能为空！</strong></p></div>';
            return false;
        }
    }

    try {
        $client = cos_get_client($cos_options);
        $bucket = cos_get_bucket_name($cos_options);
        $client = cos_replace_client_region($client, $bucket);
        ImageSlim::checkStatus($client, $bucket);
        if ($slim == 'on') {
            ImageSlim::open($client, $bucket, $mode, $suffix);
        } else {
            ImageSlim::close($client, $bucket);
        }
    } catch (ServiceResponseException $e) {
        $msg = (string)$e;
        if ($e->getExceptionCode() === ErrorCode::NO_BIND_CI) {
            $msg = "存储桶 {$bucket} 未绑定数据万象，若要开启极智压缩，请先 <a href= 'https://console.cloud.tencent.com/ci' target='_blank'>绑定数据万象服务</a >";
        }
        if ($e->getExceptionCode() === ErrorCode::REGION_UNSUPPORTED) {
            $msg = "存储桶所在地域 {$cos_options['regional']} 暂不支持图片极智压缩";
        }
        echo "<div class='error'><p><strong>{$msg}</strong></p></div>";
        return false;
    }

    $cos_options['ci_image_slim'] = $slim;
    $cos_options['ci_image_slim_mode'] = $mode;
    $cos_options['ci_image_slim_suffix'] = $suffix;

    update_option('cos_options', $cos_options);

    echo '<div class="updated"><p><strong>图片极智压缩设置已保存！</strong></p></div>';
    return true;
}

function cos_ci_image_slim_page($options)
{
    cos_validate_configuration($options);

    $ci_image_slim = esc_attr($options['ci_image_slim']);
    $checked_ci_image_slim = $ci_image_slim == 'on' ? 'checked="checked"' : '';
    $ci_image_slim_mode = explode(',', esc_attr($options['ci_image_slim_mode']));
    $checked_mode_api = in_array('API', $ci_image_slim_mode) ? 'checked="checked"' : '';
    $checked_mode_auto = in_array('Auto', $ci_image_slim_mode) ? 'checked="checked"' : '';
    $ci_image_slim_suffix = explode(',', esc_attr($options['ci_image_slim_suffix']));
    $checked_suffix_jpg = in_array('jpg', $ci_image_slim_suffix) ? 'checked="checked"' : '';
    $checked_suffix_png = in_array('png', $ci_image_slim_suffix) ? 'checked="checked"' : '';

    $remoteStatus = '';
    if (!empty($options['bucket']) && !empty($options['app_id']) && !empty($options['secret_id']) && !empty($options['secret_key'])) {
        try {
            $bucket = cos_get_bucket_name($options);
            $client = cos_get_client($options);
            $client = cos_replace_client_region($client, $bucket);
            $result = ImageSlim::checkStatus($client, $bucket);
            cos_sync_image_slim_config($result, $options);
            $status = $result['Status'];

            $checked_ci_image_slim = $status == 'on' ? 'checked="checked"' : '';
            $remoteStatus = $status == 'on' ? '云端状态：<span class="open">已开启</span>' : '云端状态：<span class="close">已关闭</span>';

            $remoteMode = explode(',', $result['SlimMode']);
            $checked_mode_api = in_array('API', $remoteMode) ? 'checked="checked"' : '';
            $checked_mode_auto = in_array('Auto', $remoteMode) ? 'checked="checked"' : '';
        } catch (ServiceResponseException $e) {
            $msg = (string)$e;
            if ($e->getExceptionCode() === ErrorCode::NO_BIND_CI) {
                $msg = "存储桶 {$bucket} 未绑定数据万象，若要开启极智压缩，请先 <a href='https://console.cloud.tencent.com/ci' target='_blank'>绑定数据万象服务</a>";
            }
            if ($e->getExceptionCode() === ErrorCode::REGION_UNSUPPORTED) {
                $msg = "存储桶所在地域 '{$options['regional']}' 暂不支持图片极智压缩";
            }
            echo "<div class='error'><p><strong>{$msg}</strong></p></div>";
        }
    }

    return <<<EOF
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>简介</legend>
                    </th>
                    <td>
                        <p>图片极智压缩开启后，通过智能判断图片的主观质量进行自动调节，在不改变图片原格式的基础上，使图片体积相比原图有显著的降低，<br> 同时在视觉效果上可以最大程度贴近原图。更多详情请查看：<a href="https://cloud.tencent.com/document/product/460/86438" target="_blank">腾讯云文档</a></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>是否启用</legend>
                    </th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="ci_image_slim" {$checked_ci_image_slim} />
                            <span class="slider round"></span>
                        </label>
                        <p>{$remoteStatus}</p>
                        <p>极智压缩支持两种模式自动压缩和API模式，请按需选择。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>自动压缩</legend>
                    </th>
                    <td>
                        <label class="switch">
                          <input type="checkbox" name="ci_image_slim_mode[]" value="Auto" {$checked_mode_auto} />
                          <span class="slider round"></span>
                        </label>

                        <p>开通极智压缩的自动使用方式，开通后无需携带任何参数，存储桶内指定格式的图片将在访问时自动进行极智压缩。</p>
                        <p>需要选择自动进行压缩的图片格式：</p>
                        <input type="checkbox" name="ci_image_slim_suffix[]" value="jpg" {$checked_suffix_jpg} /> jpg（包含<code>jpg</code>、<code>jpeg</code>）<br>
                        <input type="checkbox" name="ci_image_slim_suffix[]" value="png" {$checked_suffix_png} /> png <br>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>API 模式</legend>
                    </th>
                    <td>
                        <label class="switch">
                          <input type="checkbox" name="ci_image_slim_mode[]" value="API" {$checked_mode_api} />
                          <span class="slider round"></span>
                        </label>
                        <p>开通极智压缩的 API 使用方式，开通后可在图片时通过极智压缩参数（需要配置配置图片处理样式<code>?imageSlim</code>）对图片进行压缩；</p>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <input type="hidden" name="type" value="qcloud_cos_ci_image_slim">
                    <td><input type="submit" class="button button-primary" value="保存"/></td>
                </tr>
            </table>
        </form>
EOF;
}

function cos_get_user_roles()
{
    $result = [];

    $editable_roles = array_reverse(get_editable_roles());
    foreach ($editable_roles as $role => $details) {
        $result[$role] = translate_user_role($details['name']);
    }

    return $result;
}

function cos_ci_text_page($options)
{
    cos_validate_configuration($options);

    $checked_skip_comment_validation_on_login = esc_attr($options['skip_comment_validation_on_login'] ?? 'off') !== 'off' ? 'checked="checked"' : '';
    $checked_text_comments = esc_attr($options['ci_text_comments'] ?? 'off') !== 'off' ? 'checked="checked"' : '';
    $ci_text_comments_strategy = esc_attr($options['ci_text_comments_strategy'] ?? '');

    $roles = cos_get_user_roles();
    $check_roles = explode(',', esc_attr($options['ci_text_comments_check_roles'] ?? ''));
    $select_roles = '';
    foreach ($roles as $role => $name) {
        $check = '';
        if (in_array($role, $check_roles)) {
            $check = 'checked="checked"';
        }
        $select_roles .= '<input type="checkbox" name="ci_text_comments_check_roles[]" value="' . $role . '" ' . $check . '>' . $name . '<br>';
    }

    return <<<EOF
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>评论审核</legend>
                    </th>
                    <td>
                        <label class="switch">
                          <input type="checkbox" name="ci_text_comments" {$checked_text_comments} />
                          <span class="slider round"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>评论审核策略</legend>
                    </th>
                    <td>
                        <input type="text" name="ci_text_comments_strategy" value="{$ci_text_comments_strategy}" size="50" placeholder="请填写对应的 Biztype 名称" />
                        <p>填写需要使用的文本审核策略 <code>Biztype</code> 名称，为空时使用默认策略，详情查看 <a href="https://cloud.tencent.com/document/product/436/55206" target="_blank">设置审核策略</a>。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>跳过登录态验证</legend>
                    </th>
                    <td>
                        <label class="switch">
                          <input type="checkbox" name="skip_comment_validation_on_login" {$checked_skip_comment_validation_on_login} />
                          <span class="slider round"></span>
                        </label>
                        <p>启用后如果是登录态则会跳过该用户评论内容，不去验证。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>需要验证的登录角色</legend>
                    </th>
                    <td>
                        {$select_roles}
                        <p>选择需要在登录态下验证的角色，选择后即使选择了<strong>跳过登录态验证</strong>，属于该角色的用户评论也会进行验证。</p>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <input type="hidden" name="type" value="qcloud_cos_ci_text">
                    <td><input type="submit" class="button button-primary" value="保存"/></td>
                </tr>
            </table>
        </form>
EOF;
}

function cos_ci_text_setting($content)
{
    $cos_options = get_option('cos_options', true);
    if (!cos_validate_configuration($cos_options)) {
        return false;
    }

    $client = cos_get_client($cos_options);
    $bucket = cos_get_bucket_name($cos_options);
    $ciService = Service::checkStatus($client, $bucket);
    if (!$ciService) {
        echo "<div class='error'><p><strong>存储桶 {$bucket} 未绑定数据万象，若要开启文本审核，请先 <a href= 'https://console.cloud.tencent.com/ci' target='_blank'>绑定数据万象服务</a ></strong></p></div>";
        return false;
    }

    $ci_text_comments = isset($content['ci_text_comments']) ? sanitize_text_field($content['ci_text_comments']) : 'off';
    $skip_comment_validation_on_login = isset($content['skip_comment_validation_on_login']) ? sanitize_text_field($content['skip_comment_validation_on_login']) : 'off';
    $ci_text_comments_strategy = isset($content['ci_text_comments_strategy']) ? sanitize_text_field($content['ci_text_comments_strategy']) : '';
    $ci_text_comments_check_roles = isset($content['ci_text_comments_check_roles']) ? implode(',', $content['ci_text_comments_check_roles']) : '';

    $cos_options['ci_text_comments'] = $ci_text_comments;
    $cos_options['skip_comment_validation_on_login'] = $skip_comment_validation_on_login;
    $cos_options['ci_text_comments_strategy'] = $ci_text_comments_strategy;
    $cos_options['ci_text_comments_check_roles'] = $ci_text_comments_check_roles;
    update_option('cos_options', $cos_options);

    echo '<div class="updated"><p><strong>文本审核设置已保存！</strong></p></div>';
    return true;
}

function cos_contact_page()
{
    echo <<<EOF
    <table class="form-table">
      <tbody>
        <tr>
          <th>GitHub</th>
          <td><a href="https://github.com/sy-records" target="_blank">@sy-records</a></td>
        </tr>
        <tr>
          <th>QQ 群</th>
          <td>887595381 <a href="https://go.qq52o.me/qm/ccs" target="_blank">点击加入</a></td>
        </tr>
        <tr>
          <th>微信公众号</th>
          <td><img width="150px" src="https://open.weixin.qq.com/qr/code?username=sy-records" alt="鲁飞"></td>
        </tr>
        <tr>
          <th>打赏一杯咖啡或一杯香茗</th>
          <td><img height="290px" src="https://cdn.jsdelivr.net/gh/sy-records/staticfile@master/images/donate.png"></td>
        </tr>
      </tbody>
    </table>
EOF;
}

if (!function_exists('is_user_logged_in')) {
    require_once(ABSPATH . WPINC . '/pluggable.php');
}

function cos_process_comments($comment_data)
{
    $options = get_option('cos_options', true);

    // If CI text for comments is not enabled
    if (($options['ci_text_comments'] ?? 'off') !== 'on') {
        return $comment_data;
    }

    // If 'skip_comment_validation_on_login' option is not enabled
    if (($options['skip_comment_validation_on_login'] ?? 'off') !== 'on') {
        cos_request_txt_check($options, $comment_data['comment_content']);
        return $comment_data;
    }

    // If User is not logged in
    if (!is_user_logged_in()) {
        cos_request_txt_check($options, $comment_data['comment_content']);
        return $comment_data;
    }

    $roles = explode(',', $options['ci_text_comments_check_roles'] ?? '');
    global $current_user;
    // Check if one of the user roles is in the defined roles
    foreach ($roles as $role) {
        if (in_array($role, $current_user->roles)) {
            cos_request_txt_check($options, $comment_data['comment_content']);
            break;
        }
    }

    return $comment_data;
}

add_filter('preprocess_comment', 'cos_process_comments');

function cos_request_txt_check($options, $comment)
{
    $client = cos_get_client($options);
    $bucket = cos_get_bucket_name($options);
    $client = cos_replace_client_region($client, $bucket);
    $result = Audit::comment($client, $bucket, $comment, $options['ci_text_comments_strategy'] ?? '');
    if (!$result['state'] || $result['result'] === 2) {
        // 人工审核
        add_filter('pre_comment_approved', '__return_zero');
    }

    if ($result['state'] && $result['result'] === 1) {
        wp_die("评论内容{$result['message']}涉嫌违规，请重新评论", 409);
    }
}

/**
 * @param array $content
 * @return bool
 */
function cos_ci_attachment_preview_setting($content)
{
    $cos_options = get_option('cos_options', true);
    if (!cos_validate_configuration($cos_options)) {
        return false;
    }

    $attachment_preview = !empty($content['attachment_preview']) ? sanitize_text_field($content['attachment_preview']) : 'off';

    $cos_options['attachment_preview'] = $attachment_preview;
    update_option('cos_options', $cos_options);

    echo '<div class="updated"><p><strong>文档处理设置已保存！</strong></p></div>';
    return true;
}

/**
 * @param array $options
 * @return bool
 */
function cos_validate_configuration($options)
{
    if (empty($options['bucket']) || empty($options['app_id']) || empty($options['secret_id']) || empty($options['secret_key'])) {
        echo '<div class="error"><p><strong>请先保存存储桶名称、地域、APP ID、SecretID、SecretKey 参数！</strong></p></div>';
        return false;
    }

    return true;
}

function cos_document_page($options)
{
    cos_validate_configuration($options);

    $ci_attachment_preview = esc_attr($options['attachment_preview'] ?? 'off');
    $checked_attachment_preview = $ci_attachment_preview == 'on' ? 'checked="checked"' : '';
    $bucket = cos_get_bucket_name($options);

    $remoteStatus = '';
    $status = false;
    if (!empty($options['bucket']) && !empty($options['app_id']) && !empty($options['secret_id']) && !empty($options['secret_key'])) {
        try {
            $client = cos_get_client($options);
            $client = cos_replace_client_region($client, $bucket);
            $status = FilePreview::checkStatus($client, $bucket);

            if ($ci_attachment_preview == 'on' && !$status) {
                cos_update_config_parameters(['attachment_preview' => 'off'], $options);
                $checked_attachment_preview = '';
            }

            $remoteStatus = $status ? '云端状态：<span class="open">已开启</span>' : '云端状态：<span class="close">已关闭</span>';
        } catch (ServiceResponseException $e) {
            $msg = (string)$e;
            if ($e->getExceptionCode() === ErrorCode::NO_BIND_CI) {
                $msg = "存储桶 {$bucket} 未绑定数据万象，若要开启文档处理，请先 <a href='https://console.cloud.tencent.com/ci' target='_blank'>绑定数据万象服务</a>";
            }
            echo "<div class='error'><p><strong>{$msg}</strong></p></div>";
        }
    }

    $disableSubmit = !$status ? 'disabled=disabled' : '';
    $disableMessage = !$status ? "<p>如需使用请先访问 <a href='https://console.cloud.tencent.com/ci/bucket?bucket={$bucket}&region={$options['regional']}&type=document' target='_blank'>腾讯云控制台</a>开启。</p>" : '';

    return <<<EOF
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>文档预览</legend>
                    </th>
                    <td>
                        <label class="switch">
                          <input type="checkbox" name="attachment_preview" {$checked_attachment_preview} />
                          <span class="slider round"></span>
                        </label>
                        <p>{$remoteStatus}</p>
                        <p>文档预览支持对多种文件类型生成预览，可以解决文档内容的页面展示问题，满足 PC、App 等多个用户端的文档在线浏览需求。更多详情请查看：<a href="https://cloud.tencent.com/document/product/460/47495" target="_blank">腾讯云文档</a></p>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <input type="hidden" name="type" value="qcloud_cos_ci_attachment_preview">
                    <td>
                        <input type="submit" class="button button-primary" {$disableSubmit} value="保存"/>
                        {$disableMessage}
                    </td>
                </tr>
            </table>
        </form>
EOF;
}

/**
 * @return string[]
 */
function cos_setting_page_tabs()
{
    return [
        'config' => '配置',
        'sync' => '数据迁移',
        'slim' => '图片极智压缩',
        'document' => '文档处理',
        'text' => '文本审核',
        'metric' => '数据监控',
        'contact' => '联系作者'
    ];
}

/**
 * @return string
 */
function cos_get_current_tab()
{
    if (isset($_GET['tab'])) {
        return $_GET['tab'];
    }

    global $pagenow;
    if ($pagenow == 'admin.php' && $_GET['page'] !== COS_PLUGIN_SLUG) {
        $parts = explode('-', $_GET['page']);
        return end($parts);
    }

    return 'config';
}

function cos_get_user_color_scheme()
{
    // Get the user data
    $user_info = get_userdata(get_current_user_id());

    // Get the admin color scheme name
    $color_scheme_name = $user_info->admin_color;

    // Get the admin color scheme object
    global $_wp_admin_css_colors;
    $color_scheme = $_wp_admin_css_colors[$color_scheme_name];

    // Return the color scheme
    return $color_scheme;
}

// 在导航栏“设置”中添加条目
function cos_add_setting_page()
{
    add_options_page('腾讯云 COS', '腾讯云 COS', 'manage_options', __FILE__, 'cos_setting_page');

    add_menu_page('腾讯云 COS', '腾讯云 COS', 'manage_options', COS_PLUGIN_SLUG, 'cos_setting_page', 'dashicons-cloud-upload');
    foreach (cos_setting_page_tabs() as $tab => $name) {
        add_submenu_page(COS_PLUGIN_SLUG, $name, $name, 'manage_options', COS_PLUGIN_SLUG . "-{$tab}", 'cos_setting_page');
    }
}

add_action('admin_menu', 'cos_add_setting_page');

// 插件设置页面
function cos_setting_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient privileges!');
    }
    $options = [];
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

        $options = cos_append_options($options);
    }

    if (!empty($_POST) and $_POST['type'] == 'qcloud_cos_all') {
        if (cos_validate_configuration(get_option('cos_options', true))) {
            $files = cos_read_dir_queue(get_home_path(), cos_get_option('upload_path'));
            foreach ($files as $file) {
                cos_file_upload($file['key'], $file['filepath']);
            }
            echo '<div class="updated"><p><strong>本次操作成功同步' . count($files) . '个文件</strong></p></div>';
        }
    }

    // 替换数据库链接
    if (!empty($_POST) and $_POST['type'] == 'qcloud_cos_replace') {
        $old_url = esc_url_raw($_POST['old_url']);
        $new_url = esc_url_raw($_POST['new_url']);

        global $wpdb;
        $posts_name = $wpdb->prefix . 'posts';
        // 文章内容
        $posts_result = $wpdb->query(
            "UPDATE $posts_name SET post_content = REPLACE(post_content, '$old_url', '$new_url')"
        );

        // 修改题图之类的
        $postmeta_name = $wpdb->prefix . 'postmeta';
        $postmeta_result = $wpdb->query(
            "UPDATE $postmeta_name SET meta_value = REPLACE(meta_value, '$old_url', '$new_url')"
        );

        echo '<div class="updated"><p><strong>替换成功！共替换文章内链' . $posts_result . '条、题图链接' . $postmeta_result . '条！</strong></p></div>';
    }

    if (!empty($_POST) and $_POST['type'] == 'qcloud_cos_ci_image_slim') {
        cos_ci_image_slim_setting($_POST);
    }

    if (!empty($_POST) and $_POST['type'] == 'qcloud_cos_ci_text') {
        cos_ci_text_setting($_POST);
    }

    if (!empty($_POST) and $_POST['type'] == 'qcloud_cos_ci_attachment_preview') {
        cos_ci_attachment_preview_setting($_POST);
    }

    // 若$options不为空数组，则更新数据
    if ($options !== []) {
        $check_status = true;
        if (!empty($options['bucket']) && !empty($options['app_id']) && !empty($options['secret_id']) && !empty($options['secret_key'])) {
            $check_status = cos_check_bucket($options);
        }

        if ($check_status) {
            //更新数据库
            update_option('cos_options', $options);

            $upload_path = sanitize_text_field(trim(stripslashes($_POST['upload_path']), '/'));
            $upload_path = $upload_path == '' ? 'wp-content/uploads' : $upload_path;
            update_option('upload_path', $upload_path);
            $upload_url_path = sanitize_text_field(trim(stripslashes($_POST['upload_url_path']), '/'));
            update_option('upload_url_path', $upload_url_path);
            echo '<div class="updated"><p><strong>设置已保存！</strong></p></div>';
        }
    }

    $cos_options = get_option('cos_options', true);
    $cos_regional = esc_attr($cos_options['regional']);

    $cos_nothumb = esc_attr($cos_options['nothumb']);
    $check_nothumb = $cos_nothumb == 'true' ? 'checked="checked"' : '';

    $cos_nolocalsaving = esc_attr($cos_options['nolocalsaving']);
    $check_nolocalsaving = $cos_nolocalsaving == 'true' ? 'checked="checked"' : '';

    $cos_delete_options = esc_attr($cos_options['delete_options']);
    $check_delete_options = $cos_delete_options == 'true' ? 'checked="checked"' : '';

    $cos_update_file_name = esc_attr($cos_options['update_file_name']);

    $protocol = cos_get_url_scheme();

    $current_tab = cos_get_current_tab();

    $color_scheme = cos_get_user_color_scheme();
    ?>
    <style>
      .new-tab{margin-left: 5px;padding: 3px;border-radius: 10px;font-size: 10px;}
      .open{color: #007017;}
      .close{color: #b32d2e;}
      .charts-container{display: flex;flex-wrap: wrap;margin-top: 10px;}
      .cos-chart{flex-basis: calc(50% - 20px);}
      @media(max-width:600px){.cos-chart{flex-basis:100%}}
      .switch{position:relative;display:inline-block;width:60px;height:30px}
      .switch input{opacity:0;width:0;height:0}
      .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:#ccc;transition:.4s}
      .slider:before{position:absolute;content:"";height:25px;width:25px;left:4px;bottom:2.5px;background-color:white;transition:.4s}
      input:checked+.slider{background-color: <?php echo $color_scheme->colors[2]; ?>;}
      input:checked+.slider:before{transform:translateX(25px)}
      .slider.round{border-radius:30px}
      .slider.round:before{border-radius:50%}
    </style>
    <div class="wrap" style="margin: 10px;">
        <h1>腾讯云 COS <span style="font-size: 13px;">当前版本：<?php echo COS_VERSION; ?></span></h1>
        <p>插件网站：<a href="https://qq52o.me/" target="_blank">沈唁志</a> / <a href="https://qq52o.me/2518.html" target="_blank">Sync QCloud COS发布页面</a> / <a href="https://qq52o.me/2722.html" target="_blank">详细使用教程</a>；</p>
        <p>如果觉得此插件对你有所帮助，不妨到 <a href="https://github.com/sy-records/sync-qcloud-cos" target="_blank">GitHub</a> 上点个<code>Star</code>，<code>Watch</code>关注更新；<a href="?page=sync-qcloud-cos-contact">打赏一杯咖啡或一杯香茗</a></p>
        <h3 class="nav-tab-wrapper">
            <?php global $pagenow; ?>
            <?php foreach (cos_setting_page_tabs() as $tab => $label): ?>
              <?php $href = $pagenow === 'admin.php' ? COS_PLUGIN_SLUG . '-' . $tab : COS_PLUGIN_PAGE . '&tab=' . $tab; ?>
              <?php $label = $tab == 'contact' ? $label . '<span class="wp-ui-notification new-tab">NEW</span>' : $label; ?>
              <a class="nav-tab <?php echo $current_tab == $tab ? 'nav-tab-active' : '' ?>" href="?page=<?php echo $href;?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </h3>
        <?php if ($current_tab == 'config'): ?>
        <form method="post">
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
                    <td>
                        <select name="regional"><?php cos_get_regional($cos_regional); ?></select>
                        <p>请选择<code>存储桶</code>对应的所在地域。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>APP ID</legend>
                    </th>
                    <td>
                        <input type="text" name="app_id" value="<?php echo esc_attr($cos_options['app_id']); ?>" size="50" placeholder="APP ID"/>

                        <p>请先访问 <a href="https://console.cloud.tencent.com/cos5/key" target="_blank">腾讯云控制台</a> 获取 <code>APP ID、SecretID、SecretKey</code>。</p>
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
                        <label class="switch">
                          <input type="checkbox" name="nothumb" <?php echo $check_nothumb; ?> />
                          <span class="slider round"></span>
                        </label>

                        <p>建议不启用。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>不在本地保留备份</legend>
                    </th>
                    <td>
                        <label class="switch">
                          <input type="checkbox" name="nolocalsaving" <?php echo $check_nolocalsaving; ?> />
                          <span class="slider round"></span>
                        </label>

                        <p>建议不启用。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>删除配置信息</legend>
                    </th>
                    <td>
                        <label class="switch">
                          <input type="checkbox" name="delete_options" <?php echo $check_delete_options; ?> />
                          <span class="slider round"></span>
                        </label>

                        <p>默认启用，删除插件时会删除当前配置信息。</p>
                        <p>如果不启用，删除插件时只会重置URL前缀为空，保留当前配置信息。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>自动重命名文件</legend>
                    </th>
                    <td>
                        <select name="update_file_name">
                            <option <?php echo $cos_update_file_name == 'false' ? 'selected="selected"' : '';?> value="false">不处理</option>
                            <option <?php echo $cos_update_file_name == 'md5' ? 'selected="selected"' : '';?> value="md5">MD5</option>
                            <option <?php echo $cos_update_file_name == 'time' ? 'selected="selected"' : '';?> value="time">时间戳+随机数</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>本地文件夹</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_path" value="<?php echo cos_get_option('upload_path'); ?>" size="50" placeholder="请输入上传文件夹"/>

                        <p>附件在服务器上的存储位置，例如：<code>wp-content/uploads</code>（注意不要以“/”开头和结尾），根目录请输入<code>.</code>。</p>
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
                    <th></th>
                    <td><input type="submit" class="button button-primary" value="保存更改"/></td>
                </tr>
            </table>
            <input type="hidden" name="type" value="cos_set">
        </form>
        <?php elseif ($current_tab == 'sync'): ?>
            <?php echo cos_sync_setting_form($cos_options); ?>
        <?php elseif ($current_tab == 'slim'): ?>
            <?php echo cos_ci_image_slim_page($cos_options); ?>
        <?php elseif ($current_tab == 'document'): ?>
            <?php echo cos_document_page($cos_options); ?>
        <?php elseif ($current_tab == 'text'): ?>
            <?php echo cos_ci_text_page($cos_options); ?>
        <?php elseif ($current_tab == 'metric'): ?>
        <script src="//cdnjs.cloudflare.com/ajax/libs/apexcharts/3.41.1/apexcharts.min.js"></script>
        <?php $monitor = new DataPoints(cos_get_bucket_name($cos_options), $cos_options); ?>
        <div class="charts-container">
        <?php
            Charts::setColors($color_scheme->colors);
            echo Charts::storage($monitor->getStorage());
            echo Charts::objectNumber($monitor->getObjectNumber());
            echo Charts::requests($monitor->getRequests());
            echo Charts::traffic($monitor->getTraffic());

            if (!empty($cos_options['ci_style'])) {
                echo Charts::ciStyle($monitor->getImageBasicsRequests());
                echo Charts::ciTraffic($monitor->getCITraffic());
            }

            if (!empty($cos_options['attachment_preview']) && $cos_options['attachment_preview'] == 'on') {
                echo Charts::ciDocumentHtml($monitor->getDocumentHtmlRequests());
            }

            if (!empty($cos_options['ci_text_comments']) && $cos_options['ci_text_comments'] == 'on') {
                echo Charts::ciTextAuditing($monitor->getTextAuditing());
            }
        ?>
        </div>
        <?php elseif ($current_tab == 'contact'): ?>
            <?php echo cos_contact_page(); ?>
        <?php endif; ?>
        <hr>
        <p>优惠活动：<a href="https://qq52o.me/welfare.html#qcloud" target="_blank">腾讯云优惠</a> / <a href="https://go.qq52o.me/a/cos" target="_blank">腾讯云COS资源包优惠</a>；</p>
        <p>限时推广：<a href="https://cloud.tencent.com/developer/support-plan?invite_code=cqidlih5bagj" target="_blank">技术博客可以加入腾讯云云+社区定制周边礼品等你来拿</a>；</p>
    </div>
<?php
}
?>
