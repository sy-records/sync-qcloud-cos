=== Sync QCloud COS ===
Contributors: shenyanzhi
Donate link: https://qq52o.me/sponsor.html
Tags: COS, 腾讯云, 对象存储, Tencent, Qcloud
Requires at least: 4.2
Tested up to: 5.3.2
Requires PHP: 5.6.0
Stable tag: 1.6.4
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
7. 插件更多详细介绍和安装：[https://github.com/sy-records/wordpress-qcloud-cos](https://github.com/sy-records/wordpress-qcloud-cos)

## 作者博客

[沈唁志](https://qq52o.me "沈唁志")

接受定制开发 WordPress 插件，如有定制开发需求可以[联系QQ](ttp://wpa.qq.com/msgrd?v=3&uin=85464277&site=qq&menu=yes)。

== Installation ==

1. Upload the folder `wordpress-qcloud-cos` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's all

== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png

== Frequently Asked Questions ==

= 怎么替换文章中之前的旧资源地址链接 =

这个插件已经加上了替换数据库中之前的旧资源地址链接功能，只需要填好对应的链接即可

= 使用子账户报错`Cos Error Code: AccessDenied, Status Code: 403` =

可以使用子账户，但是 APPID 需要填写为存储桶创建者的ID，而不是子账户的ID。例如[配置指南](https://github.com/sy-records/wordpress-qcloud-cos#%E9%85%8D%E7%BD%AE%E6%8C%87%E5%8D%97)中的`1250000000`就是APPID

= 上传图片提示`图像后期处理失败，请将其缩小到2500像素并重新上传` =

配置的`存储桶名称`填写错误，正确的配置参照[配置指南](https://github.com/sy-records/wordpress-qcloud-cos#%E9%85%8D%E7%BD%AE%E6%8C%87%E5%8D%97)中`存储桶名称`

> `v1.6.1`增强了校验，填写错误会给予提示；同时兼容了桶名称附带`APPID`的情况

== Changelog ==

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

= 1.6.0 =

升级sdk版本至v5。