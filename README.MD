## MengZemail-Typecho新评论邮箱提醒插件

我自己做的一个插件，我看着typecho的评论邮箱提醒插件不多，而且大部分主题不自带这个功能，就做了这个插件，瞎写的，还请凑活。
该插件使用PHPMailer来发送邮件，目前只有评论提醒博主功能，没有回复提醒，后面会改变。
如果想自定义邮件样式，请修改以下代码：

```php
private static function mailBody($comment)
{
    $content .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    $content  = '<div style="background-color: #9A9BA4DB; padding: 10px; border-radius: 5px;">';
    $content .= '<h3 style="color: #000;">评论人: ' . $comment->author . '</h3>';
    $content .= '<p style="color: #000;">评论内容: ' . $comment->text . '</p>';
    $content .= '<p style="color: #000;">评论地址: ' . $comment->permalink . '</p>';
    $commentAt = new Typecho_Date($comment->created);
    $commentAt = $commentAt->format('Y-m-d H:i:s');
    $content .= '<p style="color: #000;">评论时间: ' . $commentAt . '</p>';
    $content .= '</div>';
$content .= '<a style="color: #000;" href="' . $comment->permalink . '">点我查看</a>';
    return $content;
}
```


## 安装教程

导入到Typecho程序 usr/plugins/ 目录中，并解压

解压后文件夹名称必须为MengZemail

## 更新日志

**1.0.0版本**
 - 插件制作完成，正式发布