<?php

namespace TypechoPlugin\CommentToMail\lib;

/**
 * CommentToMail
 * Typecho 异步评论邮件提醒插件
 * 
 * @copyright  Copyright (c) 2022 xcsoft
 * @license    GNU General Public License 3.0
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Email
 * 
 * @package CommentToMail
 */
class Email
{
    /**
     * 寄件人
     *
     * @var string
     */
    public string $from;

    /**
     * 寄件人姓名
     *
     * @var string
     */
    public string $fromName;

    /**
     * reply地址
     *
     * @var string
     */
    public string $replyTo;

    /**
     * reply姓名
     *
     * @var string
     */
    public string $replyToName;

    /**
     * 收件人地址
     *
     * @var string
     */
    public string $reciver;

    /**
     * 收件人姓名
     * @var string
     */

    public string $reciverName;

    /**
     * 邮件主题
     *
     * @var string
     */
    public string $subject;

    /**
     * 邮件内容
     *
     * @var string
     */
    public string $altBody;

    /**
     * 邮件内容
     *
     * @var string
     */
    public string $msgHtml;


    /**
     * 向博主发邮件的标题
     *
     * @var string
     */
    public string $titleForOwner;

    /**
     * 向访客发邮件的标题
     *
     * @var string
     */
    public string $titleForGuest;
}
