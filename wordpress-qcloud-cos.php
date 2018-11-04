<?php
/*
Plugin Name: 腾讯云对象存储服务COS
Plugin URI: https://qq52o.me/2130.html
Description: 使用腾讯云对象存储服务 COS 作为附件存储空间。（This is a plugin that uses QCloud Cloud Object Service for attachments remote saving.）
Version: 1.3
Author: sy-records
Author URI: https://qq52o.me
License: GPL v3
*/

require_once 'sdk/include.php';
use Qcloudcos\Cosapi;
if (!defined('WP_PLUGIN_URL')) {
	define('WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins');
}
//  plugin url

define('COS_BASENAME', plugin_basename(__FILE__));
define('COS_BASEFOLDER', plugin_basename(dirname(__FILE__)));

// 初始化选项
register_activation_hook(__FILE__, 'cos_set_options');

// 初始化选项
function cos_set_options() {
	$options = array(
		'bucket' => "",
		'regional' => 'gz',
		'app_id' => "",
		'secret_id' => "",
		'secret_key' => "",
		'nothumb' => "false", // 是否上传缩略图
		'nolocalsaving' => "false", // 是否保留本地备份
		'upload_url_path' => "", // URL前缀
	);
	add_option('cos_options', $options, '', 'yes');
}

// 设置COS所在的区域：
$cos_opt = get_option('cos_options', TRUE);
$regional = esc_attr($cos_opt['regional']);
Cosapi::setRegion($regional);
/**
 * 上传函数
 * @param $object
 * @param $file
 * @param $opt
 * @return bool
 */

function cos_file_upload($object, $file, $opt = array()) {
	//如果文件不存在，直接返回FALSE
	if (!@file_exists($file)) {
		return FALSE;
	}

	//获取WP配置信息
	$cos_options = get_option('cos_options', TRUE);
	$cos_bucket = esc_attr($cos_options['bucket']);
	if (@file_exists($file)) {
		$dirname = dirname($object);
		cos_create_folder($cos_bucket, $dirname);
		$ret = Cosapi::upload($cos_bucket, $file, $object);
	} else {
		return FALSE;
	}
	/*
		    echo $object.'<br>';
		    echo $file.'<br>';
		    echo $dirname.'<br>';
	*/
}

/**
 * 创建相应的目录
 * @param $cos_bucket
 * @param $dir
 */
function cos_create_folder($cos_bucket, $dir) {
	$data = Cosapi::statFolder($cos_bucket, $dir . '/');
	if ($data['code'] == -166) {
		$dir_array = explode('/', $dir);
		$dir_name = '';
		foreach ($dir_array as $dir) {
			$dir_name .= ($dir . '/');
			$result = Cosapi::statFolder($cos_bucket, $dir_name);
			if ($result['code'] == -166) {
				$ret = Cosapi::createFolder($cos_bucket, $dir_name);
			}
		}
	}
	//var_dump($data,$result,$ret);
}

/**
 * 是否需要删除本地文件
 * @return bool
 */
function cos_is_delete_local_file() {
	$cos_options = get_option('cos_options', TRUE);
	return (esc_attr($cos_options['nolocalsaving']) == 'true');
}

/**
 * 删除本地文件
 * @param $file 本地文件路径
 * @return bool
 */
function cos_delete_local_file($file) {
	try {
		//文件不存在
		if (!@file_exists($file)) {
			return TRUE;
		}

		//删除文件
		if (!@unlink($file)) {
			return FALSE;
		}

		return TRUE;
	} catch (Exception $ex) {
		return FALSE;
	}
}

/**
 * 上传附件（包括图片的原图）
 * @param $metadata
 * @return array()
 */
function cos_upload_attachments($metadata) {
	$wp_uploads = wp_upload_dir();
	//生成object在OSS中的存储路径
	if (get_option('upload_path') == '.') {
		//如果含有“./”则去除之
		$metadata['file'] = str_replace("./", '', $metadata['file']);
	}
	$object = str_replace("\\", '/', $metadata['file']);
	$object = str_replace(get_home_path(), '', $object);

	//在本地的存储路径
	$file = get_home_path() . $object; //向上兼容，较早的WordPress版本上$metadata['file']存放的是相对路径

	//设置可选参数
	$opt = array('Content-Type' => $metadata['type']);

	//执行上传操作
	cos_file_upload('/' . $object, $file, $opt);

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
function cos_upload_thumbs($metadata) {
	//上传所有缩略图
	if (isset($metadata['sizes']) && count($metadata['sizes']) > 0) {
		//获取COS插件的配置信息
		$cos_options = get_option('cos_options', TRUE);
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
			//设置可选参数
			$opt = array('Content-Type' => $val['mime-type']);

			//执行上传操作
			cos_file_upload($object, $file, $opt);

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
 * 删除远程服务器上的单个文件
 */
function cos_delete_remote_file($file) {
	//获取WP配置信息
	$cos_options = get_option('cos_options', TRUE);
	$cos_bucket = esc_attr($cos_options['bucket']);

	//得到远程路径
	$file = str_replace("\\", '/', $file);
	$del_file_path = str_replace(get_home_path(), '/', $file);
	try {
		//删除文件
		Cosapi::delFile($cos_bucket, $del_file_path);
	} catch (Exception $ex) {

	}
	return $file;
}
add_action('wp_delete_file', 'cos_delete_remote_file', 100);

// 当upload_path为根目录时，需要移除URL中出现的“绝对路径”
function modefiy_img_url($url, $post_id) {
	$home_path = str_replace(array('/', '\\'), array('', ''), get_home_path());
	$url = str_replace($home_path, '', $url);
	return $url;
}

if (get_option('upload_path') == '.') {
	add_filter('wp_get_attachment_url', 'modefiy_img_url', 30, 2);
}

function cos_read_dir_queue($dir) {
	if (isset($dir)) {
		$files = array();
		$queue = array($dir);
		while ($data = each($queue)) {
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
function cos_plugin_action_links($links, $file) {
	if ($file == plugin_basename(dirname(__FILE__) . '/wordpress-qcloud-cos.php')) {
		$links[] = '<a href="options-general.php?page=' . COS_BASEFOLDER . '/wordpress-qcloud-cos.php">设置</a>';
	}
	return $links;
}
add_filter('plugin_action_links', 'cos_plugin_action_links', 10, 2);

// 在导航栏“设置”中添加条目
function cos_add_setting_page() {
	add_options_page('腾讯云COS设置', '腾讯云COS设置', 'manage_options', __FILE__, 'cos_setting_page');
}

add_action('admin_menu', 'cos_add_setting_page');

// 插件设置页面
function cos_setting_page() {
	if (!current_user_can('manage_options')) {
		wp_die('Insufficient privileges!');
	}
	$options = array();
	if (!empty($_POST) and $_POST['type'] == 'cos_set') {
		$options['bucket'] = (isset($_POST['bucket'])) ? trim(stripslashes($_POST['bucket'])) : '';
		$options['regional'] = (isset($_POST['regional'])) ? trim(stripslashes($_POST['regional'])) : '';
		$options['app_id'] = (isset($_POST['app_id'])) ? trim(stripslashes($_POST['app_id'])) : '';
		$options['secret_id'] = (isset($_POST['secret_id'])) ? trim(stripslashes($_POST['secret_id'])) : '';
		$options['secret_key'] = (isset($_POST['secret_key'])) ? trim(stripslashes($_POST['secret_key'])) : '';
		$options['nothumb'] = (isset($_POST['nothumb'])) ? 'true' : 'false';
		$options['nolocalsaving'] = (isset($_POST['nolocalsaving'])) ? 'true' : 'false';
		//仅用于插件卸载时比较使用
		$options['upload_url_path'] = (isset($_POST['upload_url_path'])) ? trim(stripslashes($_POST['upload_url_path'])) : '';
	}

	if (!empty($_POST) and $_POST['type'] == 'cos_sync_all') {
		$cos_options = get_option('cos_options', TRUE);
		$cos_bucket = esc_attr($cos_options['bucket']);

		$synv = cos_read_dir_queue(get_home_path() . get_option('upload_path'));
		$i = 0;
		foreach ($synv as $k) {
			$ret = Cosapi::stat($cos_bucket, $k['x']);
			if ($ret['code'] != 0) {
				$i++;
				cos_file_upload($k['x'], $k['j']);
			}
		}
		echo '<div class="updated"><p><strong>本次操作成功同步' . $i . '个文件</strong></p></div>';
	}

	// 若$options不为空数组，则更新数据
	if ($options !== array()) {
		//更新数据库
		update_option('cos_options', $options);

		$upload_path = trim(trim(stripslashes($_POST['upload_path'])), '/');
		$upload_path = ($upload_path == '') ? ('wp-content/uploads') : ($upload_path);
		update_option('upload_path', $upload_path);

		$upload_url_path = trim(trim(stripslashes($_POST['upload_url_path'])), '/');
		update_option('upload_url_path', $upload_url_path);

		?>
        <div class="updated"><p><strong>设置已保存！</strong></p></div>
    <?php
}

	$cos_options = get_option('cos_options', TRUE);
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
	?>
    <div class="wrap" style="margin: 10px;">
        <h2>腾讯云 COS 附件设置</h2>

        <form name="form1" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . COS_BASEFOLDER . '/wordpress-qcloud-cos.php'); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>Bucket 设置</legend>
                    </th>
                    <td>
                        <input type="text" name="bucket" value="<?php echo $cos_bucket; ?>" size="50"
                               placeholder="BUCKET"/>

                        <p>请先访问 <a href="http://console.qcloud.com/cos" target="_blank">腾讯云控制台</a> 创建
                            <code>bucket</code> ，再填写以上内容。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>Bucket 地域</legend>
                    </th>
                    <td><select name="regional">
							<option value="tj" <?php if ($cos_regional == 'tj') {echo ' selected="selected"';}?>>北京一区（华北）</option>
							<option value="bj" <?php if ($cos_regional == 'bj') {echo ' selected="selected"';}?>>北京</option>
							<option value="sh" <?php if ($cos_regional == 'sh') {echo ' selected="selected"';}?>>上海（华东）</option>
                            <option value="gz" <?php if ($cos_regional == 'gz') {echo ' selected="selected"';}?>>广州（华南）</option>
							<option value="cd" <?php if ($cos_regional == 'cd') {echo ' selected="selected"';}?>>成都（西南）</option>
                            <option value="sh" <?php if ($cos_regional == 'sh') {echo ' selected="selected"';}?>>华中</option>
							<option value="sgp" <?php if ($cos_regional == 'sgp') {echo ' selected="selected"';}?>>新加坡</option>
							<option value="hk" <?php if ($cos_regional == 'hk') {echo ' selected="selected"';}?>>香港</option>
							<option value="ca" <?php if ($cos_regional == 'ca') {echo ' selected="selected"';}?>>多伦多</option>
							<option value="ger" <?php if ($cos_regional == 'ger') {echo ' selected="selected"';}?>>法兰克福</option>

                        </select>
                        <p>请选择您创建的 <code>bucket</code> 所在地域</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>APP ID 设置</legend>
                    </th>
                    <td>
                        <input type="text" name="app_id" value="<?php echo $cos_app_id; ?>" size="50"
                               placeholder="APP ID"/>

                        <p>请先访问 <a href="http://console.qcloud.com/cos" target="_blank">腾讯云控制台</a>
                            点击<code>获取secretKey</code>获取 <code>APP ID、secretID、secretKey</code></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>secretID</legend>
                    </th>
                    <td><input type="text" name="secret_id" value="<?php echo $cos_secret_id; ?>" size="50" placeholder="secretID"/></td>
                </tr>
                <tr>
                    <th>
                        <legend>secretKey</legend>
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
                        <input type="checkbox" name="nothumb" <?php if ($cos_nothumb) {
		echo 'checked="TRUE"';
	}
	?> />

                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>不在本地保留备份</legend>
                    </th>
                    <td>
                        <input type="checkbox"
                               name="nolocalsaving" <?php if ($cos_nolocalsaving) {
		echo 'checked="TRUE"';
	}
	?> />

                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>本地文件夹：</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_path" value="<?php echo $upload_path; ?>" size="50"
                               placeholder="请输入上传文件夹"/>

                        <p>附件在服务器上的存储位置，例如： <code>wp-content/uploads</code> （注意不要以“/”开头和结尾），根目录请输入<code>.</code>。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>URL前缀：</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_url_path" value="<?php echo $upload_url_path; ?>" size="50"
                               placeholder="请输入URL前缀"/>

                        <p><b>注意：</b></p>

                        <p>1）URL前缀的格式为 <code>http://{cos域名}</code> （“本地文件夹”为 <code>.</code> 时），或者 <code>http://{cos域名}/{本地文件夹}</code>
                            ，“本地文件夹”务必与上面保持一致（结尾无 <code>/</code> ）。</p>

                        <p>2）cos中的存放路径（即“文件夹”）与上述 <code>本地文件夹</code> 中定义的路径是相同的（出于方便切换考虑）。</p>

                        <p>3）如果需要使用 <code>独立域名</code> ，直接将 <code>{cos域名}</code> 替换为 <code>独立域名</code> 即可。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>更新选项</legend>
                    </th>
                    <td><input type="submit" name="submit" value="更新"/></td>
                </tr>
            </table>
            <input type="hidden" name="type" value="cos_set">
        </form>
        <form name="form1" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . COS_BASEFOLDER . '/wordpress-qcloud-cos.php'); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>同步历史附件</legend>
                    </th>
                    <input type="hidden" name="type" value="cos_sync_all">
                    <td>
                        <input type="submit" name="submit" value="开始同步"/>
                        <p><b>注意：如果是首次同步，执行时间将会十分十分长（根据你的历史附件数量），有可能会因执行时间过长，页面显示超时或者报错。<br>
                                所以，建议那些几千上万附件的大神们，考虑官方的 <a href="https://www.qcloud.com/document/product/436/7133">同步工具</a></b></p>
                    </td>
                </tr>
            </table>
        </form>
    </div>
<?php
}
?>
