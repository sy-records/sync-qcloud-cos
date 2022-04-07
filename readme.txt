=== Sync QCloud COS ===
Contributors: shenyanzhi
Donate link: https://qq52o.me/sponsor.html
Tags: COS, 腾讯云, 对象存储, Tencent, Qcloud
Requires at least: 4.2
Tested up to: 5.9
Requires PHP: 5.6.0
Stable tag: 2.0.2
License: Apache 2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0.html

使用腾讯云对象存储服务 COS 作为附件存储空间。（This is a plugin that uses Tencent Cloud Cloud Object Storage for attachments remote saving.）

== Description ==

使用腾讯云对象存储服务 COS 作为附件存储空间。（This is a plugin that uses Tencent Cloud Cloud Object Storage for attachments remote saving.）

* 依赖腾讯云COS服务：https://cloud.tencent.com/product/cos
* 使用说明：https://cloud.tencent.com/product/cos/details

## 插件特点

1. 可配置是否上传缩略图和是否保留本地备份
2. 本地删除可同步删除腾讯云对象存储 COS 中的文件
3. 支持腾讯云对象存储 COS 绑定的个性域名
4. 支持替换数据库中旧的资源链接地址
5. 支持北京、上海、广州、香港、法兰克福等完整地域使用
6. 支持同步历史附件到 COS
7. 支持验证桶名是否填写正确
8. 支持腾讯云数据万象 CI 图片处理
9. 支持上传文件自动重命名

插件更多详细介绍和安装：[https://github.com/sy-records/wordpress-qcloud-cos](https://github.com/sy-records/wordpress-qcloud-cos)

## 作者博客

[沈唁志](https://qq52o.me "沈唁志")

欢迎加入沈唁的WordPress云存储全家桶QQ交流群：887595381

== Installation ==

1. Upload the folder `wordpress-qcloud-cos` or `sync-qcloud-cos` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's all

== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png

== Frequently Asked Questions ==

= 怎么替换文章中之前的旧资源地址链接 =

这个插件已经加上了替换数据库中之前的旧资源地址链接功能，只需要填好对应的链接即可

= 使用子账户报错：Cos Error Code: AccessDenied, Status Code: 403 =

可以使用子账户，但是 APPID 需要填写为存储桶创建者的ID，而不是子账户的ID。例如[配置指南](https://github.com/sy-records/wordpress-qcloud-cos#%E9%85%8D%E7%BD%AE%E6%8C%87%E5%8D%97)中的`1250000000`就是APPID

= 上传图片提示：图像后期处理失败，请将其缩小到2500像素并重新上传 =

1）配置的`存储桶名称`填写错误，正确的配置参照[配置指南](https://github.com/sy-records/wordpress-qcloud-cos#%E9%85%8D%E7%BD%AE%E6%8C%87%E5%8D%97)中的`存储桶名称`，v1.6.1 版本增强了校验，填写错误会给予提示；同时兼容了桶名称附带`APPID`的情况
2）图片确实像素大于2500px，可以在`functions.php`中加入以下代码禁止大图片裁剪功能

`add_filter('big_image_size_threshold', '__return_false');`

= 在插件中应该如何使用腾讯云数据万象CI =

参考：[腾讯云对象存储COS + 数据万象CI = 完善的图片解决方案](https://cloud.tencent.com/developer/article/1606153) 或 [腾讯云文档 - 使用图片样式](https://cloud.tencent.com/document/product/436/42214#.E4.BD.BF.E7.94.A8.E5.9B.BE.E7.89.87.E6.A0.B7.E5.BC.8F)

= 保存配置时报错：您的站点遇到了致命错误，请查看您的站点的管理电子邮箱来获得指引 =

这个问题应该只出现在Windows的机器上，打开`WP_DEBUG`的话会报错：`Fatal error: Uncaught GuzzleHttp\Exception\RequestException: cURL error 60: SSL certificate problem: self signed certificate`，解决方法如下：

1）从 [https://curl.haxx.se/ca/cacert.pem](https://curl.haxx.se/ca/cacert.pem) 下载最新的cacert.pem  
2）将以下行添加到`php.ini`中，注意修改对应的路径

`curl.cainfo=/path/to/cacert.pem`

= 保存配置时提示：ErrorCode:xxx, ErrorMessage:xxxx。如：ErrorCode:403, ErrorMessage:Request has expired =

这种问题请直接前往腾讯云[对象存储文档](https://cloud.tencent.com/document/product/436)搜索对应的`ErrorMessage`信息即可，或者访问[对象存储错误码列表](https://cloud.tencent.com/document/product/436/7730#.E9.94.99.E8.AF.AF.E7.A0.81.E5.88.97.E8.A1.A8)

= 跟所有使用 Guzzle 组件的插件都可能发生冲突，发生报错 Call to undefined method GuzzleHttp... =

不可同时开启同类插件；类似于腾讯云的官方插件 `tencentcloud-*` 系列；

== Changelog ==

= 2.0.2 =

* 移除 esc_html
* 修复 path error

= 2.0.1 =

* 修复安全问题

= 2.0.0 =

* 修复 XSS

= 1.9.9 =

* 优化 isset 判断
* 优化访问权限
* 修复存在同名path时截取错误

= 1.9.8 =

* 支持 WordPress 5.9
* 修改 SecretKey 类型为 password

= 1.9.7 =

* 修复页面引用多次同一图片导致图片处理添加多次

= 1.9.6 =

* 升级 COS SDK

= 1.9.5 =

* 添加 get_home_path 方法判断
* 支持 WordPress 5.7 版本

= 1.9.4 =

* 优化配置校验逻辑
* 支持删除非图片类型文件

= 1.9.3 =

* 修复版本号

= 1.9.2 =

* 修复勾选不上传缩略图后不会删除云端缩略图

= 1.9.1 =

* 升级 COS SDK 版本
* 支持 WordPress 5.6 版本

= 1.9.0 =

* 修复多站点上传原图失败，缩略图正常问题
* 优化上传路径获取

= 1.8.5 =

* 优化同步上传路径获取

= 1.8.4 =

* 修改常见问题和相关链接

= 1.8.3 =

* 增加南京地域

= 1.8.2 =

* 增加替换题图数据库链接

= 1.8.1 =

* 支持上传文件自动重命名
* 支持特色图片使用图片处理

= 1.8.0 =

* 修复因svn提交错误导致打包文件缺失问题

= 1.7.1 =

* 增加金融云地域
* 升级SDK至[v2.0.8](https://github.com/tencentyun/cos-php-sdk-v5/releases/tag/v2.0.8)

= 1.7.0 =

* 修复勾选不在本地保存图片后媒体库显示默认图片问题
* 修复删除错误

= 1.6.8 =

* 修复勾选不在本地保存图片后媒体库显示默认图片问题
* 优化删除文件逻辑

= 1.6.7 =

* 增加腾讯云数据万象图片处理

= 1.6.6 =

* 优化deactivation_hook，禁用时可选删除配置和恢复URL前缀

= 1.6.5 =

* 增加插件禁用事件，可选择是否删除配置
* 增加上传文件try catch，提示文件上传错误时请打开控制台查看对应请求的Response输出信息
* 修复首尔地域错误，感谢`서대현`反馈

= 1.6.4 =

* 更新腾讯云SDK至[v2.0.7](https://github.com/tencentyun/cos-php-sdk-v5/releases/tag/v2.0.7)版本
* 修改上个版本插件文件末尾空白符号问题

= 1.6.3 =

* 更新腾讯云SDK至[v2.0.6](https://github.com/tencentyun/cos-php-sdk-v5/releases/tag/v2.0.6)版本

= 1.6.2 =

* 修复腾讯云cos返回数据格式不一致问题

= 1.6.1 =

* 增强存储桶配置验证
* cos client增加schema

= 1.6.0 =

* 升级sdk为v5版本
* 修复本地文件夹和URL前缀结尾 / 去除失败
* 优化URL前缀注意事项提示中的http和https

= 1.5.1 =

* 优化button按钮样式
* 优化sdk中的代码

= 1.5.0 =

* 修复第一次删除文件失败，报错 ERROR_PROXY_APPID_USERID_NOTMATCH，导致删除文件不完整
* 优化删除逻辑
* 移除时区设置
* 增加发布版本链接
* 修改 README 中的常见问题

= 1.4.3 =
* 修复地域选择上海地区跳转华中问题

= 1.4.2 =
* 修复导致评论时间戳差 8 小时问题

= 1.4 =
* 增加替换文章中资源链接地址功能

= 1.3 =
* 添加北京、香港、法兰克福等完整地域使用

= 1.0 =
* First version

== Upgrade Notice ==

= 1.6.7 =

更新后请点击一次插件设置中的保存更改按钮。

= 1.6.0 =

升级sdk版本至v5。
