# Release Notes

## [Unreleased](https://github.com/sy-records/sync-qcloud-cos/compare/v2.6.1...master)

## [v2.6.1](https://github.com/sy-records/sync-qcloud-cos/compare/v2.6.0...v2.6.1) - 2024-11-23

- Fix sub-site failure to delete remote images by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/74
- Fix `get_option` default value error

## [v2.6.0](https://github.com/sy-records/sync-qcloud-cos/compare/v2.5.8...v2.6.0) - 2024-08-17

* Support upload to subdirectories by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/72

## [v2.5.8](https://github.com/sy-records/sync-qcloud-cos/compare/v2.5.7...v2.5.8) - 2024-06-27

* Use wp_get_mime_types instead of get_allowed_mime_types by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/71

## [v2.5.7](https://github.com/sy-records/sync-qcloud-cos/compare/v2.5.6...v2.5.7) - 2024-06-22

- Support delete file using `wp-cli` command by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/69
- Fix upload heic format file error.

## [v2.5.6](https://github.com/sy-records/sync-qcloud-cos/compare/v2.5.5...v2.5.6) - 2024-05-29

* Support disable charts and wp-cli commands by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/68

## [v2.5.5](https://github.com/sy-records/sync-qcloud-cos/compare/v2.5.4...v2.5.5) - 2024-05-19

* Sync region by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/66

## [v2.5.4](https://github.com/sy-records/sync-qcloud-cos/compare/v2.5.3...v2.5.4) - 2024-03-10

* Optimize wpdb query by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/65

## [v2.5.3](https://github.com/sy-records/sync-qcloud-cos/compare/v2.5.2...v2.5.3) - 2024-03-04

* Fix get non-image file size error by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/64

## [v2.5.2](https://github.com/sy-records/sync-qcloud-cos/compare/v2.5.1...v2.5.2) - 2024-02-25

* Support gif for image slim by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/63

## [v2.5.1](https://github.com/sy-records/sync-qcloud-cos/compare/v2.5.0...v2.5.1) - 2024-02-09

* Fix CSRF error by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/62

## [v2.5.0](https://github.com/sy-records/sync-qcloud-cos/compare/v2.4.1...v2.5.0) - 2024-01-30

* Add [Live Preview](https://wordpress.org/plugins/sync-qcloud-cos/?preview=1) by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/61
* Support origin protect by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/60

## [v2.4.1](https://github.com/sy-records/sync-qcloud-cos/compare/v2.4.0...v2.4.1) - 2023-12-15

- Fix duplicate append image style param by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/58

## [v2.4.0](https://github.com/sy-records/sync-qcloud-cos/compare/v2.3.7...v2.4.0) - 2023-12-07

- No longer supported below PHP `7.2`
- Optimize catch exception
- Optimize image style process

## [v2.3.7](https://github.com/sy-records/sync-qcloud-cos/compare/v2.3.6...v2.3.7) - 2023-12-05

* Fix deletion failure when upload_url_path is `.` by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/56

## [v2.3.6](https://github.com/sy-records/sync-qcloud-cos/compare/v2.3.5...v2.3.6) - 2023-11-24

- Fix CI domain error of use accelerate region by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/53

## [v2.3.5](https://github.com/sy-records/sync-qcloud-cos/compare/v2.3.4...v2.3.5) - 2023-11-18

- Add cos_append_ci_style method by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/48
- Add cos_local2remote method by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/50

## [v2.3.4](https://github.com/sy-records/sync-qcloud-cos/compare/v2.3.3...v2.3.4) - 2023-11-10

- Optimize delete options by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/46

## [v2.3.3](https://github.com/sy-records/sync-qcloud-cos/compare/v2.3.2...v2.3.3) - 2023-10-20

- Optimize get scheme code by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/38
- Refactor file preview by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/39
- Optimize code by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/40
- Optimize sync code by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/41
- Apply fixes from Plugin Check by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/42
- Fix missing upload original image for big image by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/44
- Fix upload and file preview error by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/45

## [v2.3.2](https://github.com/sy-records/sync-qcloud-cos/compare/v2.3.1...v2.3.2) - 2023-09-06

- Optimize cos_check_bucket code by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/37

## [v2.3.1](https://github.com/sy-records/sync-qcloud-cos/compare/v2.3.0...v2.3.1) - 2023-09-02

- Fix missing check CiService by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/36

## [v2.3.0](https://github.com/sy-records/sync-qcloud-cos/compare/v2.2.3...v2.3.0) - 2023-09-01

- Support audit comments by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/34

## [v2.2.3](https://github.com/sy-records/sync-qcloud-cos/compare/v2.2.2...v2.2.3) - 2023-08-27

- Optimize assets by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/31
- Rename wordpress-qcloud-cos to sync-qcloud-cos by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/32
- Add primary navigation by [@sy-records](https://github.com/sy-records) in https://github.com/sy-records/sync-qcloud-cos/pull/33

## [v2.2.2](https://github.com/sy-records/sync-qcloud-cos/compare/v2.2.1...v2.2.2) - 2023-08-24

- Add cos metric by @sy-records in https://github.com/sy-records/sync-qcloud-cos/pull/29
- Support FilePreview by @sy-records in https://github.com/sy-records/sync-qcloud-cos/pull/30

## [v2.2.1](https://github.com/sy-records/sync-qcloud-cos/compare/v2.2.0...v2.2.1) - 2023-08-17

- Fix missing setting button for plugins page by @sy-records in 9aec3322db04e1f4f09b902f50e45b816185d3c8

## [v2.2.0](https://github.com/sy-records/sync-qcloud-cos/compare/v2.1.0...v2.2.0) - 2023-08-17

- Update deploy CI by @sy-records in https://github.com/sy-records/sync-qcloud-cos/pull/26
- Support Image Slim by @sy-records in https://github.com/sy-records/sync-qcloud-cos/pull/28

## [v2.1.0](https://github.com/sy-records/sync-qcloud-cos/compare/v2.0.4...v2.1.0) - 2023-07-24

- Add deploy ci by @sy-records in https://github.com/sy-records/sync-qcloud-cos/pull/25

## [v2.0.4](https://github.com/sy-records/sync-qcloud-cos/compare/v2.0.3...v2.0.4) - 2022-07-12

- Add UseAgent. (1f5f7aad378bb1fce23279909a97902a865a70ae)

## [v2.0.3](https://github.com/sy-records/sync-qcloud-cos/compare/v2.0.2...v2.0.3) - 2022-04-27

- Support media library editing

## [v2.0.2](https://github.com/sy-records/sync-qcloud-cos/compare/v2.0.1...v2.0.2) - 2022-04-04

## [v2.0.1](https://github.com/sy-records/sync-qcloud-cos/compare/v1.9.9...v2.0.1) - 2022-02-17

- Fixed security issues

## [v1.9.9](https://github.com/sy-records/sync-qcloud-cos/compare/v1.9.8...v1.9.9) - 2022-02-10

- Optimize isset
- Optimize access rights
- Fix the interception error when there is a path with the same name

## [v1.9.8](https://github.com/sy-records/sync-qcloud-cos/compare/v1.9.7...v1.9.8) - 2022-01-29

- Support WordPress 5.9.

## [v1.9.7](https://github.com/sy-records/sync-qcloud-cos/compare/v1.9.6...v1.9.7) - 2021-08-21

- 修复版本错误
- 修复页面引用多次同一图片导致图片处理添加多次

## [v1.9.6](https://github.com/sy-records/sync-qcloud-cos/compare/v1.9.5...v1.9.6) - 2021-07-21

- 升级 COS SDK

## [v1.9.5](https://github.com/sy-records/sync-qcloud-cos/compare/v1.9.4...v1.9.5) - 2021-03-17

- 添加 get_home_path 方法判断
- 支持 WordPress 5.7 版本

## [v1.9.4](https://github.com/sy-records/sync-qcloud-cos/compare/v1.9.3...v1.9.4) - 2021-02-18

- 优化配置校验逻辑
- 支持删除非图片类型文件

## [v1.9.3](https://github.com/sy-records/sync-qcloud-cos/compare/v1.9.2...v1.9.3) - 2020-12-13

- 修复版本号

## [v1.9.2](https://github.com/sy-records/sync-qcloud-cos/compare/v1.9.1...v1.9.2) - 2020-12-13

- 修复勾选不上传缩略图后不会删除云端缩略图

## [v1.9.1](https://github.com/sy-records/sync-qcloud-cos/compare/v1.9.0...v1.9.1) - 2020-12-13

- 升级 COS SDK 版本
- 支持 WordPress 5.6 版本

## [v1.9.0](https://github.com/sy-records/sync-qcloud-cos/compare/v1.8.5...v1.9.0) - 2020-08-11

- 修复多站点上传原图失败，缩略图正常问题
- 优化上传路径获取

## [v1.8.5](https://github.com/sy-records/sync-qcloud-cos/compare/v1.8.4...v1.8.5) - 2020-07-24

- 优化同步上传路径获取

## [v1.8.4](https://github.com/sy-records/sync-qcloud-cos/compare/v1.8.3...v1.8.4) - 2020-06-16

- 修改常见问题和相关链接

## [v1.8.3](https://github.com/sy-records/sync-qcloud-cos/compare/v1.8.2...v1.8.3) - 2020-05-22

- 增加南京地域

## [v1.8.2](https://github.com/sy-records/sync-qcloud-cos/compare/v1.8.1...v1.8.2) - 2020-05-15

- 增加替换题图数据库链接

## [v1.8.1](https://github.com/sy-records/sync-qcloud-cos/compare/v1.8.0...v1.8.1) - 2020-05-04

- 支持上传文件自动重命名
- 支持特色图片使用图片处理

## [v1.8.0](https://github.com/sy-records/sync-qcloud-cos/compare/v1.7.1...v1.8.0) - 2020-04-22

- 修复因svn提交错误导致打包文件缺失问题

## [v1.7.1](https://github.com/sy-records/sync-qcloud-cos/compare/v1.7.0...v1.7.1) - 2020-04-22

- 增加金融云地域 8856ff2a
- 升级SDK至[v2.0.8](https://github.com/tencentyun/cos-php-sdk-v5/releases/tag/v2.0.8)

## [v1.7.0](https://github.com/sy-records/sync-qcloud-cos/compare/v1.6.8...v1.7.0) - 2020-04-10

- 修复勾选不在本地保存图片后媒体库显示默认图片问题
- 修复删除错误

## [v1.6.8](https://github.com/sy-records/sync-qcloud-cos/compare/v1.6.7...v1.6.8) - 2020-04-02

- 修复勾选不在本地保存图片后媒体库显示默认图片问题
- 优化删除文件逻辑

## [v1.6.7](https://github.com/sy-records/sync-qcloud-cos/compare/v1.6.6...v1.6.7) - 2020-04-01

- 增加腾讯云数据万象图片处理

## [v1.6.6](https://github.com/sy-records/sync-qcloud-cos/compare/v1.6.5...v1.6.6) - 2020-03-29

- 优化deactivation_hook，禁用时可选删除配置和恢复URL前缀

## [v1.6.5](https://github.com/sy-records/sync-qcloud-cos/compare/v1.6.4...v1.6.5) - 2020-03-27

- 增加插件禁用事件，可选择是否删除配置
- 增加上传文件try catch，提示文件上传错误时请打开控制台查看对应请求的Response输出信息
- 修复首尔地域错误，感谢`서대현`反馈

## [v1.6.4](https://github.com/sy-records/sync-qcloud-cos/compare/v1.6.3...v1.6.4) - 2020-03-11

- 更新腾讯云SDK至[v2.0.7](https://github.com/tencentyun/cos-php-sdk-v5/releases/tag/v2.0.7)版本
- 修改上个版本插件文件末尾空白符号问题

## [v1.6.3](https://github.com/sy-records/sync-qcloud-cos/compare/v1.6.2...v1.6.3) - 2020-02-16

- 更新腾讯云SDK至[v2.0.6](https://github.com/tencentyun/cos-php-sdk-v5/releases/tag/v2.0.6)版本

## [v1.6.2](https://github.com/sy-records/sync-qcloud-cos/compare/v1.6.1...v1.6.2) - 2020-02-09

- 修复腾讯云cos返回数据格式不一致问题

> 账户中有一个存储桶和多个存储桶的返回值居然不一样😓

## [v1.6.1](https://github.com/sy-records/sync-qcloud-cos/compare/v1.6.0...v1.6.1) - 2020-02-09

- 增强存储桶配置验证
- cos client增加schema

## [v1.6.0](https://github.com/sy-records/sync-qcloud-cos/compare/v1.5.1...v1.6.0) - 2020-01-15

- 升级sdk为`v5`版本
- 修复本地文件夹和URL前缀结尾`/`去除失败
- 优化URL前缀注意事项提示中的`http`和`https`

## [v1.5.1](https://github.com/sy-records/sync-qcloud-cos/compare/v1.5.0...v1.5.1) - 2020-01-14

- 优化button按钮样式
- 优化sdk中的代码

> v4 sdk的最后一个版本，下个版本将支持从WordPress后台下载更新，并切换为v5 sdk

## [v1.5.0](https://github.com/sy-records/sync-qcloud-cos/compare/v1.4.3...v1.5.0) - 2020-01-09

- 修复第一次删除文件失败，报错`ERROR_PROXY_APPID_USERID_NOTMATCH`，导致删除文件不完整
- 优化删除逻辑
- 移除时区设置
- 增加发布版本链接
- 修改`README`中的常见问题

## [v1.4.3](https://github.com/sy-records/sync-qcloud-cos/compare/v1.4.2...v1.4.3) - 2019-11-23

- 修复地域选择上海地区跳转华中问题

## [v1.4.2](https://github.com/sy-records/sync-qcloud-cos/compare/v1.4.1...v1.4.2) - 2019-11-13

- 修复导致评论时间戳差8小时问题

## [v1.4.1](https://github.com/sy-records/sync-qcloud-cos/compare/v1.4...v1.4.1) - 2018-12-01

- 修改一下版本号

## [v1.4](https://github.com/sy-records/sync-qcloud-cos/compare/v1.3...v1.4) - 2018-11-30

- 增加替换文章中资源链接地址功能

## v1.3 - 2018-11-04

- 添加北京、香港、法兰克福等完整地域使用
- 打算继续增加完善功能
- 1.3以前的版本就不打包了
