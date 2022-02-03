<?php

namespace TypechoPlugin\CommentToMail;

/**
 * CommentToMail
 * Typecho 异步评论邮件提醒插件
 * 
 * @copyright  Copyright (c) 2022 xcsoft
 * @license    GNU General Public License 3.0
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

/**
 * action
 * 
 * @package CommentToMail
 */
class Action extends Widget implements \Widget\ActionInterface
{
    /** 
     * 数据库对象 
     * 
     * @var Db  
     */
    private Db $_db;

    /** 
     * 表前缀  
     * 
     * @var string  
     */
    private string $_prefix;

    /** 
     * 插件目录 
     *
     *  @var string  
     */
    private string $_dir = __DIR__;

    /** 
     * 插件配置信息 
     * 
     * @var \Typecho\Config
     */
    private \Typecho\Config $_cfg;

    /** 
     * 系统配置信息 
     * 
     * @var \Widget\Options
     */
    private \Widget\Options $_options;

    /** 
     * 当前登录用户 
     * 
     * @var object 
     */
    private object $_user;

    /** 
     * 模板文件目录
     * 
     *  @var string 
     */
    private string $_template_dir = __DIR__ . '/template/';

    /**
     * 邮件对象
     *
     * @var Email
     */
    private Email $_email;

    /**
     * 入口方法
     *
     * @access public
     * @return void
     */
    public function action()
    {
        $this->init();

        $this->on($this->request->is('do=deliverMail'))->deliverMail($this->request->key);  //邮件队列

        if (!$this->_user->hasLogin()) $this->response->redirect($this->_options->loginUrl); //用户未登录
        $this->on($this->request->is('do=testMail'))->testMail();                           //测试邮件
        $this->on($this->request->is('do=editTheme'))->editTheme($this->request->edit);     //编辑主题
    }

    /**
     * 初始化
     *
     * @return void
     */
    public function init()
    {
        $this->_db = Db::get();
        $this->_prefix = $this->_db->getPrefix();

        $this->_user = $this->widget('\Widget\User');
        $this->_options = $this->widget('\Widget\Options');
        $this->_cfg = Helper::options()->plugin('CommentToMail');
        $this->_email = new Email();
    }

    /**
     * 处理发信队列
     * 
     * @return void
     */
    public function processQueue(): void
    {
        if (!isset($this->_cfg->verify) || !in_array('nonAuth', $this->_cfg->verify)) {
            $this->response->throwJson([
                'result' => 0,
                'msg' => 'Forbidden'
            ]);
        }
        $this->deliverMail($this->_cfg->key);
    }

    /**
     * 发送邮件
     * 
     * @param string $key
     * @return void
     */
    private function deliverMail(string $key): void
    {
        if ($key != $this->_cfg->key) {
            $this->response->throwJson([
                'result' => 0,
                'msg' => 'No permission'
            ]);
        }

        $mailQueue = $this->_db->fetchAll($this->_db->select('id', 'content')->from($this->_prefix . 'mail')->where('sent = ?', 0));
        $success_id = array();
        $fail_id = array();
        foreach ($mailQueue as &$mail) {
            $is_success = false;
            $this->_email_id = $mail['id'];
            $mailInfo = unserialize(base64_decode($mail['content']));

            /** 发送邮件 */
            if ($mailInfo) {
                if ($this->processMail($mailInfo)) {
                    $this->_db->query($this->_db->update($this->_prefix . 'mail')->rows(array('sent' => 1))->where('id = ?', $mail['id']));
                    $is_success = true;
                }
            } else {
                $is_success = false;
            }

            if ($is_success) {
                array_push($success_id, $mail['id']);
            } else {
                array_push($fail_id, $mail['id']);
            }

            /** 排队反垃圾 */
            if (in_array('force_wait', $this->_cfg->other)) {
                sleep($this->_cfg->force_waiting_time);
            }
        }
        //清除已发送的数据
        $this->_db->query(
            $this->_db->delete($this->_prefix . 'mail')->where('sent = ?', 1)
        );
        $this->response->throwJson(array(
            'result' => true,
            'amount' => count($mailQueue),
            'success' => array(
                'amount' => count($success_id),
                'id' => $success_id
            ),
            'fail' => array(
                'amount' => count($fail_id),
                'id' => $fail_id
            )
        ));
    }

    /**
     * 处理发信
     *
     * @param mixed $mailInfo
     * @return boolean
     */
    private function processMail(mixed $mailInfo): bool
    {
        $this->_email = $mailInfo;

        //发件人邮箱
        $this->_email->from = $this->_cfg->user;
        //发件人名称
        $this->_email->fromName = $this->_cfg->fromName ? $this->_cfg->fromName : $this->_email->siteTitle;
        //向博主发邮件的标题格式
        $this->_email->titleForOwner = $this->_cfg->titleForOwner;

        //向访客发邮件的标题格式
        $this->_email->titleForGuest = $this->_cfg->titleForGuest;
        //验证博主是否接收自己的邮件
        $toMe = (in_array('to_me', $this->_cfg->other) && $this->_email->ownerId == $this->_email->authorId) ? true : false;

        //向博主发信
        if (0 == $this->_email->parent) {
            if (
                in_array($this->_email->status, $this->_cfg->status) && in_array('to_owner', $this->_cfg->other)
                && ($toMe || $this->_email->ownerId != $this->_email->authorId)
            ) {
                if (empty($this->_cfg->mail)) {
                    self::widget('Widget_Users_Author@temp' . $this->_email->cid, array('uid' => $this->_email->ownerId))->to($user);
                    $this->_email->to = $user->mail;
                } else {
                    $this->_email->to = $this->_cfg->mail;
                }
                $this->authorMail()->sendMail();
            }
        }

        /** 向访客发信 */
        if ($this->_email->parent !== 0) {
            if (
                'approved' == $this->_email->status
                && in_array('to_guest', $this->_cfg->other)
            ) {
                /**  如果联系我的邮件地址为空，则使用文章作者的邮件地址 */
                if (empty($this->_email->contactme)) {
                    if (!isset($user) || !$user) {
                        self::widget('Widget_Users_Author@temp' . $this->_email->cid, array('uid' => $this->_email->ownerId))->to($user);
                    }
                    $this->_email->contactme = $user->mail;
                } else {
                    $this->_email->contactme = $this->_cfg->contactme;
                }
                $original = $this->_db->fetchRow($this->_db->select('author', 'mail', 'text')
                    ->from('table.comments')
                    ->where('coid = ?', $this->_email->parent));
                if (
                    in_array('to_me', $this->_cfg->other)
                    || $this->_email->mail != $original['mail']
                ) {
                    $this->_email->to             = $original['mail'];
                    $this->_email->originalText   = $original['text'];
                    $this->_email->originalAuthor = $original['author'];
                    $this->guestMail()->sendMail();
                }
            }
        }
        return true;
    }

    /**
     * 作者邮件信息
     * @return $this
     */
    private function authorMail()
    {
        $this->_email->toName = $this->_email->siteTitle;
        $date = new \Typecho\Date($this->_email->created);
        $time = $date->format('Y-m-d H:i:s');
        $status = array(
            "approved" => '通过',
            "waiting"  => '待审',
            "spam"     => '垃圾'
        );
        $search  = array(
            '{siteTitle}',
            '{title}',
            '{author}',
            '{ip}',
            '{mail}',
            '{permalink}',
            '{manage}',
            '{text}',
            '{time}',
            '{status}'
        );
        $replace = array(
            $this->_email->siteTitle,
            $this->_email->title,
            $this->_email->author,
            $this->_email->ip,
            $this->_email->mail,
            $this->_email->permalink,
            $this->_email->manage,
            $this->_email->text,
            $time,
            $status[$this->_email->status]
        );

        $this->_email->msgHtml = str_replace($search, $replace, $this->getTemplate('owner'));
        $this->_email->subject = str_replace($search, $replace, $this->_email->titleForOwner);
        $this->_email->altBody = "作者:" . $this->_email->author . "\r\n链接:" . $this->_email->permalink . "\r\n评论:\r\n" . $this->_email->text;

        return $this;
    }

    /**
     * 访客邮件信息
     */
    public function guestMail()
    {
        $this->_email->toName = $this->_email->originalAuthor ? $this->_email->originalAuthor : $this->_email->siteTitle;
        $date    = new \Typecho\Date($this->_email->created);
        $time    = $date->format('Y-m-d H:i:s');
        $search  = array(
            '{siteTitle}',
            '{title}',
            '{author_p}',
            '{author}',
            '{permalink}',
            '{text}',
            '{contactme}',
            '{text_p}',
            '{time}'
        );
        $replace = array(
            $this->_email->siteTitle,
            $this->_email->title,
            $this->_email->originalAuthor,
            $this->_email->author,
            $this->_email->permalink,
            $this->_email->text,
            $this->_email->contactme,
            $this->_email->originalText,
            $time
        );

        $this->_email->msgHtml = str_replace($search, $replace, $this->getTemplate('guest'));
        $this->_email->subject = str_replace($search, $replace, $this->_email->titleForGuest);
        $this->_email->altBody = "作者:" . $this->_email->author . "\r\n链接:" . $this->_email->permalink . "\r\n评论:\r\n" . $this->_email->text;

        return $this;
    }

    /**
     * 发送邮件
     * 
     * @return bool|string|null
     */
    public function sendMail(): bool|string|NULL
    {
        /** 载入邮件组件 */
        $mailer = new PHPMailer();
        $mailer->CharSet = 'UTF-8';
        $mailer->Encoding = 'base64';

        /** 选择发信模式 */
        switch ($this->_cfg->mode) {
            case 'mail':
                break;
            case 'sendmail':
                $mailer->IsSendmail();
                break;
            case 'smtp':
                $mailer->IsSMTP();
                if (in_array('validate', $this->_cfg->validate)) $mailer->SMTPAuth = true;

                if (in_array('ssl', $this->_cfg->validate)) {
                    $mailer->SMTPSecure = "ssl";
                } else if (in_array('tls', $this->_cfg->validate)) {
                    $mailer->SMTPSecure = "tls";
                }

                $mailer->Host     = $this->_cfg->host;
                $mailer->Port     = $this->_cfg->port;
                $mailer->Username = $this->_cfg->user;
                $mailer->Password = $this->_cfg->pass;
                break;
        }

        $mailer->SetFrom($this->_email->from, $this->_email->fromName);
        $mailer->AddReplyTo($this->_email->to, $this->_email->toName);
        $mailer->Subject = $this->_email->subject;
        $mailer->AltBody = $this->_email->altBody;
        if (in_array('solve544', $this->_cfg->validate)) $mailer->AddCC($this->_email->from); // 躲避审查造成的 544 错误 

        $mailer->MsgHTML($this->_email->msgHtml);
        $mailer->AddAddress($this->_email->to, $this->_email->toName);
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
    public function getTemplate(string $template = 'owner'): string
    {
        $filename = $this->_template_dir  . $template . '.html';

        if (!file_exists($filename)) {
            throw new \Typecho\Widget\Exception('模板文件' . $template . '不存在', 404);
        }

        return file_get_contents($this->_dir . '/' . $template);
    }

    /**
     * 邮件发送测试
     */
    public function testMail()
    {
        if (self::widget('TypechoPlugin\CommentToMail\Console')->testMailForm()->validate()) {
            $this->response->goBack();
        }

        $email = $this->request->from('toName', 'to', 'title', 'content');

        $this->_email->from = $this->_cfg->user;
        $this->_email->fromName = $this->_cfg->fromName ? $this->_cfg->fromName : $this->_options->title;
        $this->_email->to = $email['to'] ? $email['to'] : $this->_user->mail;
        $this->_email->toName = $email['toName'] ? $email['toName'] : $this->_user->screenName;
        $this->_email->subject = $email['title'];
        $this->_email->altBody = $email['content'];
        $this->_email->msgHtml = $email['content'];

        $result = $this->sendMail();

        /** 提示信息 */
        $this->widget('Widget_Notice')->set(
            $result ? _t('邮件发送成功') : _t('邮件发送失败: ' . $result),
            $result ? 'success' : 'notice'
        );

        /** 转向原页 */
        $this->response->goBack();
    }

    /**
     * 编辑模板文件
     * @param $file
     * @throws \Typecho\Widget\Exception
     */
    public function editTheme($file)
    {
        $path = $this->_template_dir . $file;

        if (file_exists($path) && is_writeable($path)) {
            $handle = fopen($path, 'wb');
            if ($handle && fwrite($handle, $this->request->content)) {
                fclose($handle);
                $this->widget('Widget_Notice')->set(_t("文件 %s 的更改已经保存", $file), 'success');
            } else {
                $this->widget('Widget_Notice')->set(_t("文件 %s 无法被写入", $file), 'error');
            }
            $this->response->goBack();
        } else {
            throw new \Typecho\Widget\Exception(_t('您编辑的模板文件不存在'));
        }
    }
}
