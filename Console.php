<?php

namespace TypechoPlugin\CommentToMail;

use \Typecho\{Widget};
use \Typecho\Widget\Helper\Form;
use \Typecho\Widget\Helper\Form\Element\{Text, Hidden, Submit, Textarea};

/**
 * CommentToMail
 * Typecho 异步评论邮件提醒插件
 * 
 * @copyright  Copyright (c) 2022 xcsoft
 * @license    GNU General Public License 3.0
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Console
 * 
 * @package CommentToMail
 */
class Console extends Widget
{
    /** 
     * 模板文件目录
     * 
     *  @var string 
     */
    private string $_template_dir = __DIR__ . '/template/';

    /** 
     * 当前文件
     * 
     * @var string  
     */
    private string $_currentFile;

    /**
     * 执行函数
     *
     * @return void
     * @throws \Typecho\Widget\Exception
     */
    public function execute()
    {
        $this->widget('Widget_User')->pass('administrator');
        $files = glob($this->_template_dir . '*.{html,HTML}', GLOB_BRACE);
        $this->_currentFile = $this->request->get('file', 'owner.html');

        if (preg_match("/^([_0-9a-z-\.\ ])+$/i", $this->_currentFile) && file_exists($this->_template_dir . $this->_currentFile)) {
            foreach ($files as $file) {
                if (!file_exists($file)) continue;
                $file = basename($file);
                $this->push(array(
                    'file'      =>  $file,
                    'current'   => ($file == $this->_currentFile)
                ));
            }
            return;
        }
        throw new \Typecho\Widget\Exception('模板文件不存在', 404);
    }

    /**
     * 获取菜单标题
     *
     * @return string
     */
    public function getMenuTitle(): string
    {
        return _t('编辑文件 %s', $this->_currentFile);
    }

    /**
     * 获取文件内容
     *
     * @return string
     */
    public function currentContent(): string
    {
        return htmlspecialchars(file_get_contents($this->_template_dir . $this->_currentFile));
    }

    /**
     * 获取文件是否可读
     *
     * @return string
     */
    public function currentIsWriteable(): string
    {
        return is_writeable($this->_template_dir . $this->_currentFile);
    }

    /**
     * 获取当前文件
     *
     * @return string
     */
    public function currentFile(): string
    {
        return $this->_currentFile;
    }

    /**
     * 邮件测试表单
     * 
     * @return Form
     */
    public function testMailForm(): Form
    {
        /** 构建表单 */
        $options = Widget::widget('Widget_Options');
        $form = new Form(
            \Typecho\Common::url('/action/' . Plugin::$_action, $options->index),
            Form::POST_METHOD
        );

        /** 收件人名称 */
        $toName = new Text('toName', NULL, NULL, _t('收件人名称'), _t('为空则使用博主昵称'));
        $form->addInput($toName);

        /** 收件人邮箱 */
        $to = new Text('to', NULL, NULL, _t('收件人邮箱'), _t('为空则使用博主邮箱'));
        $form->addInput($to);

        /** 邮件标题 */
        $title = new Text('title', NULL, NULL, _t('邮件标题 *'));
        $form->addInput($title);

        /** 邮件内容 */
        $content = new Textarea('content', NULL, NULL, _t('邮件内容 *'));
        $content->input->setAttribute('class', 'w-100 mono');
        $form->addInput($content);

        /** 动作 */
        $do = new Hidden('do');
        $form->addInput($do);

        /** 提交按钮 */
        $submit = new Submit();
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        /** 设置值 */
        $do->value('testMail');
        $submit->value('发送邮件');

        /** 添加规则 */
        $to->addRule('email', _t('非法的邮件地址'));
        $title->addRule('required', _t('邮件标题不能为空'));
        $content->addRule('required', _t('邮件内容不能为空'));

        return $form;
    }
}
