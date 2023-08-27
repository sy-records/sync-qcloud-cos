=== Sync QCloud COS ===
Contributors: shenyanzhi
Donate link: https://qq52o.me/sponsor.html
Tags: COS, 腾讯云, 对象存储, Tencent, Qcloud
Requires at least: 4.2
Tested up to: 6.3
Requires PHP: 7.0.0
Stable tag: 2.2.2
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
10. 支持媒体库编辑
11. 支持腾讯云数据万象图片极智压缩
12. 支持文件预览

插件更多详细介绍和安装：[https://github.com/sy-records/sync-qcloud-cos](https://github.com/sy-records/sync-qcloud-cos)

## 作者博客

[沈唁志](https://qq52o.me "沈唁志")

欢迎加入沈唁的WordPress云存储全家桶QQ交流群：887595381

== Installation ==

1. Upload the folder `sync-qcloud-cos` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's all

== Screenshots ==

1. 设置页面
2. 数据库链接替换
3. 图片极智压缩
4. 数据监控
5. 文档处理

== Frequently Asked Questions ==

= 怎么替换文章中之前的旧资源地址链接 =

这个插件已经加上了替换数据库中之前的旧资源地址链接功能，只需要填好对应的链接即可

= 使用子账户报错：Cos Error Code: AccessDenied, Status Code: 403 =

可以使用子账户，但是 APPID 需要填写为存储桶创建者的ID，而不是子账户的ID。例如[配置指南](https://github.com/sy-records/sync-qcloud-cos#%E9%85%8D%E7%BD%AE%E6%8C%87%E5%8D%97)中的`1250000000`就是APPID

= 上传图片提示：图像后期处理失败，请将其缩小到2500像素并重新上传 =

1）配置的`存储桶名称`填写错误，正确的配置参照[配置指南](https://github.com/sy-records/sync-qcloud-cos#%E9%85%8D%E7%BD%AE%E6%8C%87%E5%8D%97)中的`存储桶名称`，v1.6.1 版本增强了校验，填写错误会给予提示；同时兼容了桶名称附带`APPID`的情况
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

= Stable =
* 支持文件预览
* 增加数据监控：存储用量、对象数量、请求数、流量等
* 使用远程配置覆盖本地配置

= Other =

see [CHANGELOG.md](https://github.com/sy-records/sync-qcloud-cos/blob/master/CHANGELOG.md).
