<p align="center">
    <img src="/wordpress-cos.png" alt="wordpress-qcloud-cos" align="center" />
</p>
<p align="center">使用腾讯云对象存储服务 COS 作为附件存储空间的 WordPress 插件</p>

## 前言

本插件核心功能使用了腾讯云 COS 官方 SDK

原作者射雕天龙，更新截止时间为2015-11-14

我使用的时候已经失效了，所以更新了一下

## Todo

* 禁用插件后恢复默认资源地址
* 媒体删除同时删除`COS`桶内资源

## 更新记录

欢迎使用 WordPress 系统写博客的同学提交 PR

2018-11-30 增加数据库链接替换功能

2018-11-04 添加北京、香港、法兰克福等完整地域使用

2018-10-30 修复变量未定义错误

2018-09-01 新增错误 Case

2018-05-22 接手更新，创建仓库

## 插件特色

* 使用腾讯云对象存储服务存储wordpress站点图片等多媒体文件
* 可配置是否上传缩略图和是否保留本地备份
* 本地删除可同步删除腾讯云上面的文件
* 支持腾讯云云存储服务绑定的个性域名
* 支持替换数据库中旧的资源链接地址

## 安装

### 直接下载源码

从 Github 下载源码，通过 WordPress 后台上传安装，或者直接将源码上传到 WordPress 插件目录`wp-content\plugins`，然后在后台启用

Github 项目地址:  [https://github.com/sy-records/wordpress-qcloud-cos](https://github.com/sy-records/wordpress-qcloud-cos)

## 修改配置
* 方法一：在 WordPress 插件管理页面有设置按钮，进行设置
* 方法二：在 WordPress 后台管理左侧导航栏`设置`下`腾讯云COS设置`，点击进入设置页面

## 特别说明
* 本插件仅支持`PHP 5.4+`版本
* 推荐使用腾讯云`cos v5`版本
* WordPress后台设置时，`Bucket设置`的input框只需填写桶名，无需带上`-你的APPID`

## 常见问题

1、怎么替换文章中之前的旧资源地址链接

这个插件已经加上了替换数据库中之前的旧资源地址链接功能，只需要填好对应的链接即可，如图所示

![update-wordpress-posts-photos.png](https://ws2.sinaimg.cn/large/0072Lfvtly1fxpxr5iy88j30qv07x74r.jpg)

2、控制报错跨域问题

见之前的文章[https://qq52o.me/2130.html](https://qq52o.me/2130.html)

## 错误 Case
很多人不理解上面这句话的意思？好多问我配置的问题，看下面换这个图吧

![错误case详解](https://raw.githubusercontent.com/sy-records/wordpress-qcloud-cos/master/screenshot-2.jpg)

## 插件截图
![设置页面](https://raw.githubusercontent.com/sy-records/wordpress-qcloud-cos/master/screenshot-1.png)
![同步历史记录&替换数据库链接](https://ws1.sinaimg.cn/large/0072Lfvtly1fy0n7dem0hj31180ciq4i.jpg)
