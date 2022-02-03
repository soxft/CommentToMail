## CommentToMail

> 一个Typecho异步邮件推送插件

适用版本: Typecho 1.2.0 beta / php 8.0 +

## 安装参考

1. clone或下载本项目
2. 重命名下载文件为 `CommentToMail`
3. 移动文件夹至 ~/usr/plugins/ 下
4. 后台启用插件, 配置SMTP等信息
5. 通过网址监控等服务 定时访问指定URL来发送邮件 (推荐使用uptimerobot)

## Copyright

CommentToMail 作为一款老牌Typecho 邮件推送插件, 具有多个分支. 但大都长时间为更新, 且无法支持 php8 与 Typecho 1.2.0. 

本项目部分参考原项目 且对其进行大量重构.

邮件服务采用[PHPMailer](https://github.com/PHPMailer/PHPMailer)

本项目采用 GNU GENERAL PUBLIC LICENSE 开源