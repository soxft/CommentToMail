<?php
/**
 * CommentToMail
 * 同步发送邮件
 * 
 * 我不知道怎么在plugin.php中调用Action，所以只能自己写一个了。
 * 
 * By buzhangjiuzhou
 */
use \Utils\Helper;
use \Typecho\{Widget, Db};
use \TypechoPlugin\CommentToMail\lib\Email;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/Exception.php';

require_once 'Log.php';

function deliverMailSync($comment)
{
    $_config = Helper::options()->plugin('CommentToMail');
    $_optionSync = Widget::widget('Widget_Options');

    $_emailSync = new Email();
    //发件人邮箱
    $_emailSync->from = $_config->user;
    //发件人名称
    $_emailSync->fromName = $_config->fromName ? $_config->fromName : $_optionSync->title;
    //向博主发邮件的标题格式
    $_emailSync->titleForOwner = $_config->titleForOwner;
    //向访客发邮件的标题格式
    $_emailSync->titleForGuest = $_config->titleForGuest;

    //验证博主是否接收自己的邮件
    $toMe = (in_array('to_me', $_config->other) && $comment->ownerId == $comment->authorId) ? true : false;

    // 向博主发信
    // TODO $comment->parent === '0' // parent === ‘0’ 时 为根评论
    // 如果在此处判断 会导致 别人评论别人的评论时 不会发送邮件给博主 后续fix
    if (in_array($comment->status, $_config->status) && $comment->type !== '1' && in_array('to_owner', $_config->other) && ($toMe || $comment->ownerId != $comment->authorId)) {
        if (!$_config->mail) {
            Widget::widget('\Widget\Users\Author@temp' . $comment->cid, ['uid' => $comment->ownerId])->to($user);
            $_emailSync->reciver = $user->mail;
        } else {
            $_emailSync->reciver = $_config->mail;
        }
        if (!$_config->name) {
            Widget::widget('\Widget\Users\Author@temp' . $comment->cid, ['uid' => $comment->ownerId])->to($user);
            $_emailSync->reciverName = $user->name;
        } else {
            $_emailSync->reciverName = $_config->name;
        }

        // 设置邮件回复信息
        $_emailSync->replyTo = $comment->mail; //评论者的邮箱
        $_emailSync->replyToName = $comment->author;
        authorMailSync($comment, $_optionSync, $_emailSync, $_config);
    }

    /** 向访客发信 */
    if ($comment->parent !== '0' && $comment->status == 'approved' && in_array('to_guest', $_config->other)) {
        /**  如果联系我的邮件地址为空，则使用文章作者的邮件地址 */
        if (!$_config->contactme) {
            if (!isset($user) || !$user) {
                Widget::widget('\Widget\Users\Author@temp' . $comment->cid, array('uid' => $comment->ownerId))->to($user);
            }
            $comment->contactme = $user->mail;
        } else {
            $comment->contactme = $_config->contactme;
        }
        
        // 从数据库中读取父评论的信息，所以需要使用数据库？
        $_dbSync = Db::get();
        $original = $_dbSync->fetchRow($_dbSync->select('author', 'mail', 'text')->from('table.comments')->where('coid = ?', $comment->parent));
        // 被评论者
        if (in_array('to_me', $_config->other) || $comment->mail != $original['mail']) {
            $comment->originalText   = $original['text'];
            $comment->originalAuthor = $original['author'];

            $_emailSync->reciver = $original['mail'];
            $_emailSync->reciverName = $original['author'];
            $_emailSync->replyTo  = $comment->mail; //当前评论者的邮箱
            $_emailSync->replyToName = $comment->author ? $comment->author : $_optionSync->title;
            
            guestMailSync($comment, $_optionSync, $_emailSync, $_config);
        }
    }

    unset($comment); //销毁评论对象
    unset($_emailSync); //销毁对象
    return true;
}

function authorMailSync($comment, $_optionSync, $_emailSync, $_config)
{
    $date = new \Typecho\Date($comment->created);
    $status = [
        "approved" => '通过',
        "waiting"  => '待审',
        "spam"     => '垃圾'
    ];
    $search  = array(
        '{{siteTitle}}',
        '{{title}}',
        '{{author}}',
        '{{ip}}',
        '{{mail}}',
        '{{permalink}}',
        '{{manage}}',
        '{{text}}',
        '{{time}}',
        '{{status}}'
    );
    $replace = [
        $_optionSync->title,
        $comment->title,
        $comment->author,
        $comment->ip,
        $comment->mail,
        $comment->permalink,
        $_optionSync->siteUrl . __TYPECHO_ADMIN_DIR__ . "manage-comments.php",
        $comment->text,
        $date->format('Y-m-d H:i:s'),
        $status[$comment->status]
    ];

    $_emailSync->msgHtml = str_replace($search, $replace, getTemplate('owner'));
    $_emailSync->subject = str_replace($search, $replace, $_emailSync->titleForOwner);
    $_emailSync->altBody = "作者:" . $comment->author . "\r\n链接:" . $comment->permalink . "\r\n评论:\r\n" . $comment->text;

    sendMailSync($_config, $_emailSync);
}

/**
 * 访客邮件信息
 */
function guestMailSync($comment, $_optionSync, $_emailSync, $_config)
{
    $date = new \Typecho\Date($comment->created);
    $search = [
        '{{siteTitle}}',
        '{{title}}',
        '{{author_p}}',
        '{{author}}',
        '{{permalink}}',
        '{{text}}',
        '{{text_p}}',
        '{{contactme}}',
        '{{time}}'
    ];
    $replace = [
        $_optionSync->title,
        $comment->title,
        $comment->originalAuthor,
        $comment->author,
        $comment->permalink,
        $comment->text,
        $comment->originalText,
        $comment->contactme,
        $date->format('Y-m-d H:i:s'),
    ];

    $_emailSync->msgHtml = str_replace($search, $replace, getTemplate('guest'));
    $_emailSync->subject = str_replace($search, $replace, $_emailSync->titleForGuest);
    $_emailSync->altBody = "作者:" . $comment->author . "\r\n链接:" . $comment->permalink . "\r\n评论:\r\n" . $comment->text;

    sendMailSync($_config, $_emailSync);
}

/**
 * 发送邮件
 * 
 * @return bool|string|null
 */
function sendMailSync($_config, $_emailSync): bool|string|NULL
{
    /** 载入邮件组件 */
    $mailer = new PHPMailer();
    $mailer->CharSet = 'UTF-8';
    $mailer->Encoding = 'base64';

    /** 选择发信模式 */
    switch ($_config->mode) {
        case 'mail':
            break;
        case 'sendmail':
            $mailer->IsSendmail();
            break;
        case 'smtp':
            $mailer->IsSMTP();
            if (in_array('validate', $_config->validate)) $mailer->SMTPAuth = true;

            if (in_array('ssl', $_config->validate)) {
                $mailer->SMTPSecure = "ssl";
            } else if (in_array('tls', $_config->validate)) {
                $mailer->SMTPSecure = "tls";
            }

            $mailer->Host     = $_config->host;
            $mailer->Port     = $_config->port;
            $mailer->Username = $_config->user;
            $mailer->Password = $_config->pass;
            break;
    }

    $mailer->SetFrom($_emailSync->from, $_emailSync->fromName);
    if (isset($_emailSync->replyTo) && isset($_emailSync->replyToName)) $mailer->AddReplyTo($_emailSync->replyTo, $_emailSync->replyToName);
    $mailer->Subject = $_emailSync->subject;
    $mailer->AltBody = $_emailSync->altBody;
    if (in_array('solve544', $_config->validate)) $mailer->AddCC($_emailSync->from); // 躲避审查造成的 544 错误 

    $mailer->MsgHTML($_emailSync->msgHtml);
    $mailer->AddAddress($_emailSync->reciver, $_emailSync->reciverName);
    $mailer->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));

    $result = $mailer->Send();
    if (!$result) $result = $mailer->ErrorInfo;

    $mailer->ClearAddresses();
    $mailer->ClearReplyTos();

    return $result;
}

/**
 * 获取邮件模板
 * 
 * @param string $type
 * @return string
 */
function getTemplate(string $template = 'owner'): string
{
    $_template_dir = __DIR__ . '/template/';
    $filename = $_template_dir  . $template . '.html';

    if (!file_exists($filename)) {
        throw new \Typecho\Widget\Exception('模板文件' . $template . '不存在', 404);
    }

    return file_get_contents($filename);
}
