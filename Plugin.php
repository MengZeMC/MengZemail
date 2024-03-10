<?php

/**
 * Typecho评论邮件提醒插件
 *
 * @package MengZemail
 * @author MengZe2
 * @version 1.0.1
 * @link https://www.mengze2.cn
 */

require dirname(__FILE__) . '/PHPMailer/PHPMailer.php';
require dirname(__FILE__) . '/PHPMailer/SMTP.php';
require dirname(__FILE__) . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MengZemail_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return string
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = [__CLASS__, 'finishComment']; // 前台提交评论完成接口
        Typecho_Plugin::factory('Widget_Comments_Edit')->finishComment = [__CLASS__, 'finishComment']; // 后台操作评论完成接口
        Typecho_Plugin::factory('Widget_Comments_Edit')->mark = [__CLASS__, 'mark']; // 后台标记评论状态完成接口
        return _t('请配置邮箱SMTP选项!');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     */
    public static function deactivate()
    {
        
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 记录log
        $log = new Typecho_Widget_Helper_Form_Element_Checkbox('log', ['log' => _t('记录日志')], 'log', _t('记录日志'), _t('启用后将当前目录生成一个log.txt 注:目录需有写入权限'));
        $form->addInput($log);

        $layout = new Typecho_Widget_Helper_Layout();
        $layout->html(_t('<h3>邮件服务配置:</h3>'));
        $form->addItem($layout);

        // SMTP服务地址
        $STMPHost = new Typecho_Widget_Helper_Form_Element_Text('STMPHost', NULL, 'smtp.qq.com', _t('SMTP服务器地址'), _t('如:smtp.163.com,smtp.gmail.com,smtp.exmail.qq.com,smtp.sohu.com,smtp.sina.com'));
        $form->addInput($STMPHost->addRule('required', _t('SMTP服务器地址必填!')));

        // SMTP用户名
        $smtpUserName = new Typecho_Widget_Helper_Form_Element_Text('smtpUserName', NULL, NULL, _t('SMTP登录用户'), _t('SMTP登录用户名，一般为邮箱地址'));
        $form->addInput($smtpUserName->addRule('required', _t('SMTP登录用户必填!')));

        // SMTP密码
        $smtpPassword = new Typecho_Widget_Helper_Form_Element_Text('smtpPassword', NULL, NULL, _t('SMTP登录密码'), _t('为QQ邮箱以例: <a href="https://service.mail.qq.com/cgi-bin/help?subtype=1&&no=1001256&&id=28" target="_blank">查看</a>'));
        $form->addInput($smtpPassword->addRule('required', _t('SMTP登录密码必填!')));

        // 服务器安全模式
        $SMTPSecure = new Typecho_Widget_Helper_Form_Element_Radio('SMTPSecure', array('' => _t('无安全加密'), 'ssl' => _t('SSL加密'), 'tls' => _t('TLS加密')), 'none', _t('SMTP加密模式'));
        $form->addInput($SMTPSecure);

        // SMTP server port
        $smtpPort = new Typecho_Widget_Helper_Form_Element_Text('smtpPort', NULL, '25', _t('SMTP服务端口'), _t('默认25 SSL为465 TLS为587'));
        $form->addInput($smtpPort);

        $layout = new Typecho_Widget_Helper_Layout();
        $layout->html(_t('<h3>邮件信息配置:</h3>'));
        $form->addItem($layout);

        // 发件邮箱
        $from = new Typecho_Widget_Helper_Form_Element_Text('from', NULL, NULL, _t('发件邮箱'), _t('用于发送邮件的邮箱'));
        $form->addInput($from->addRule('required', _t('发件邮箱必填!')));
        // 发件人姓名
        $fromName = new Typecho_Widget_Helper_Form_Element_Text('fromName', NULL, NULL, _t('发件人姓名'), _t('发件人姓名'));
        $form->addInput($fromName->addRule('required', _t('发件人姓名必填!')));
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 插件实现方法
     *
     * @access public
     * @return void
     */
    public static function render()
    {
    }

    /**
     * @param $comment
     * @param Widget_Comments_Edit $edit
     * @param $status
     * @throws Typecho_Db_Exception
     * @throws Typecho_Plugin_Exception
     * 在后台标记评论状态时的回调
     */
    public static function mark($comment, $edit, $status)
    {
        $status == 'approved' && self::beforeSendMail($edit);
    }


    /**
     * @param Widget_Comments_Edit|Widget_Feedback $comment
     * @throws Typecho_Db_Exception
     * @throws Typecho_Plugin_Exception
     * 评论/回复时的回调
     */
    public static function finishComment($comment)
    {
        $comment->status == 'approved' && self::beforeSendMail($comment);
    }

    /**
     * @param $comment
     * @throws Typecho_Db_Exception
     * @throws Typecho_Plugin_Exception
     * 发邮件前的一些操作
     */
    private static function beforeSendMail($comment)
    {
        $recipients = [];
        if (0 < $comment->parent) {
            $db = Typecho_Db::get();
            $widget = new Widget_Abstract_Comments(new Typecho_Request(), new Typecho_Response(), NULL);
            // 查询
            $select = $widget->select()->where('coid' . ' = ?', $comment->parent)->limit(1);
            $parent = $db->fetchRow($select, [$widget, 'push']); // 获取上级评论对象
            if ($parent && $parent['mail']) {
                $parentAuthor = [
                    'name' => $parent['author'],
                    'mail' => $parent['mail'],
                ];
                // 给上级评论人发邮件
                $recipients[] = $parentAuthor;
            }
        }
        self::sendMail($comment, $recipients);
    }

    /**
     * @param Widget_Comments_Edit|Widget_Feedback $comment
     * @param array $recipients
     * @throws Typecho_Plugin_Exception
     */
    private static function sendMail($comment, $recipients)
    {
        try {
            $mail = new PHPMailer(true);
            // 获取系统配置选项
            $options = Helper::options();
            // 获取插件配置
            $MengZemail = $options->plugin('MengZemail');
            //Server settings
            $mail->isSMTP();
            $mail->Host = $MengZemail->STMPHost; // SMTP 服务地址
            $mail->SMTPAuth = true; // 开启认证
            $mail->Username = $MengZemail->smtpUserName; // SMTP 用户名
            $mail->Password = $MengZemail->smtpPassword; // SMTP 密码
            $mail->SMTPSecure = $MengZemail->SMTPSecure; // SMTP 加密类型 'ssl' or 'tls'.
            $mail->Port = $MengZemail->smtpPort; // SMTP 端口

            $from = $MengZemail->from; // 发件邮箱
            $fromName = $MengZemail->fromName; // 发件人
            $mail->setFrom($from, $fromName);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';


            // 给博主发信
            $recipients[] = [
                'name' => $fromName,
                'mail' => $from,
            ];
            foreach ($recipients as $recipient) {
                $mail->addAddress($recipient['mail'], $recipient['name']); // 发件人
            }
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->Subject = '来自[' . $options->title . ']站点的新消息';

            $mail->isHTML(); // 邮件为HTML格式
            // 邮件内容
            $content = self::mailBody($comment);
            $mail->Body = $content;
            $mail->send();


            // 记录日志
            if ($MengZemail->log) {
                if ($mail->isError()) {
                    $data = $mail->ErrorInfo; // 记录发信失败的日志
                } else { // 记录发信成功的日志
                    $recipientNames = $recipientMails = '';
                    foreach ($recipients as $recipient) {
                        $recipientNames .= $recipient['name'] . ', ';
                        $recipientMails .= $recipient['mail'] . ', ';
                    }
                    $at    = date('Y-m-d H:i:s');
                    $data  = PHP_EOL . $at .' 发送成功! ';
                    $data .= ' 发件人:'   . $fromName;
                    $data .= ' 发件邮箱:' . $from;
                    $data .= ' 接收人:'   . $recipientNames;
                    $data .= ' 接收邮箱:' . $recipientMails . PHP_EOL;
                }
                $fileName = dirname(__FILE__) . '/log.txt';
                file_put_contents($fileName, $data, FILE_APPEND);
            }

        } catch (Exception $e) {
            $fileName = dirname(__FILE__) . '/log.txt';
            file_put_contents($fileName, $e, FILE_APPEND);
        }
    }

   /**
 * @param $comment
 * @return string
 * 很朴素的邮件风格
 */
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

}
