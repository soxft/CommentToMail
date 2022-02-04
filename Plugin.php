<?php

namespace TypechoPlugin\CommentToMail;

use \Typecho\Plugin\PluginInterface;
use \Utils\Helper;
use \Typecho\{Widget, Db};
use \Typecho\Widget\Helper\Form\Element\{Password, Text, Radio, Checkbox};

/**
 * 异步评论邮件提醒插件
 *
 * @package CommentToMail
 * @author  xcsoft
 * @version 1.2.1
 * @link https://xsot.cn
 * @LastEditDate 20220130
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Plugin
 * 
 * @package CommentToMail
 */
class Plugin implements PluginInterface
{
	/** 
	 * action name
	 * 
	 * @var string 
	 */
	public static $_action = 'comment-to-mail';

	/** 
	 * @var string
	 */
	public static $_panel  = 'CommentToMail/page/console.php';

	/**
	 * 激活插件方法,如果激活失败,直接抛出异常
	 *
	 * @access public
	 * @return void
	 * @throws \Typecho\Plugin\Exception
	 */
	public static function activate()
	{
		$msg = self::dbInstall();
		\Typecho\Plugin::factory('\Widget\Feedback')->finishComment = ['TypechoPlugin\CommentToMail\Plugin', 'parseComment'];
		\Typecho\Plugin::factory('\Widget\Comments\Edit')->finishComment = ['TypechoPlugin\CommentToMail\Plugin', 'parseComment'];
		\Typecho\Plugin::factory('\Widget\Comments\Edit')->mark = ['TypechoPlugin\CommentToMail\Plugin', 'passComment'];

		Helper::addAction(self::$_action, 'TypechoPlugin\CommentToMail\Action');
		Helper::addPanel(1, self::$_panel, '评论邮件提醒', '评论邮件提醒控制台', 'administrator');
		return _t($msg);
	}

	/**
	 * 禁用插件
	 *
	 * @return void
	 * @throws \Typecho\Plugin\Exception
	 */
	public static function deactivate()
	{
		Helper::removeAction(self::$_action);
		Helper::removePanel(1, self::$_panel);
	}

	/**
	 * 获取插件配置面板
	 *
	 * @param \Typecho\Widget\Helper\Form $form 配置面板
	 * @return void
	 */
	public static function config(\Typecho\Widget\Helper\Form $form)
	{
		$options = Widget::widget('Widget_Options');

		$mode = new Radio(
			'mode',
			[
				'smtp' => 'smtp',
				'mail' => 'mail()',
				'sendmail' => 'sendmail()'
			],
			'smtp',
			'发信方式'
		);
		$form->addInput($mode);

		$host = new Text(
			'host',
			NULL,
			'',
			_t('SMTP地址'),
			_t('请填写 SMTP 服务器地址')
		);
		$form->addInput($host->addRule('required', _t('SMTP服务器地址不能为空')));

		$port = new Text(
			'port',
			NULL,
			'25',
			_t('SMTP端口'),
			_t('SMTP服务端口, 一般为25. SSL一般为465')
		);
		$port->input->setAttribute('class', 'mini');
		$form->addInput($port->addRule('required', _t('SMTP端口不能为空'))
			->addRule('isInteger', _t('端口号必须为数字')));

		$user = new Text(
			'user',
			NULL,
			NULL,
			_t('SMTP用户'),
			_t('SMTP服务验证用户名,一般为邮箱账户')
		);
		$form->addInput($user->addRule('required', _t('SMTP服务验证用户名不能为空')));

		$pass = new Password(
			'pass',
			NULL,
			NULL,
			_t('SMTP密码')
		);
		$form->addInput($pass->addRule('required', _t('SMTP服务验证密码不能为空')));

		$validate = new Checkbox(
			'validate',
			[
				'validate' => '服务器需要验证',
				'ssl' => 'ssl加密',
				'tls' => 'tls加密',
				'solve544' => '启用抄送以规避544错误'
			],
			['validate'],
			'SMTP验证'
		);
		$form->addInput($validate);

		$fromName = new Text(
			'fromName',
			NULL,
			NULL,
			_t('发件人名称'),
			_t('发件人名称, 留空则使用博客标题')
		);
		$form->addInput($fromName);

		$mail = new Text(
			'mail',
			NULL,
			NULL,
			_t('接收邮件的地址'),
			_t('接收邮件的地址,如为空则使用文章作者个人设置中的邮件地址!')
		);
		$form->addInput($mail->addRule('email', _t('请填写正确的邮件地址!')));

		$contactme = new Text(
			'contactme',
			NULL,
			NULL,
			_t('模板中“联系我”的邮件地址'),
			_t('联系我用的邮件地址,如为空则使用文章作者个人设置中的邮件地址!')
		);
		$form->addInput($contactme->addRule('email', _t('请填写正确的邮件地址!')));

		$titleForOwner = new Text(
			'titleForOwner',
			null,
			"[{{title}}] 一文有新的评论",
			_t('博主接收邮件标题')
		);
		$form->addInput($titleForOwner->addRule('required', _t('博主接收邮件标题 不能为空')));

		$titleForGuest = new Text(
			'titleForGuest',
			null,
			"您在 [{{title}}] 的评论有了回复",
			_t('访客接收邮件标题')
		);
		$form->addInput($titleForGuest->addRule('required', _t('访客接收邮件标题 不能为空')));

		$status = new Checkbox(
			'status',
			[
				'approved' => '提醒已通过评论',
				'waiting' => '提醒待审核评论',
				'spam' => '提醒垃圾评论'
			],
			['approved', 'waiting'],
			'提醒设置',
			_t('该选项仅针对博主, 访客只发送已通过的评论。')
		);
		$form->addInput($status);

		$other = new Checkbox(
			'other',
			[
				'to_owner' => '有评论及回复时, 发邮件通知博主.',
				'to_guest' => '评论被回复时, 发邮件通知评论者.',
				'to_me' => '自己回复自己的评论时, 发邮件通知. (同时针对博主和访客)',
			],
			['to_owner', 'to_guest'],
			'其他设置',
			_t('由于Typecho钩子限制, 开启审核后通过审核会重复通知.')
		);
		$form->addInput($other->multiMode());


		$entryUrl = ($options->rewrite) ? $options->siteUrl : $options->siteUrl . 'index.php'; // 博客网址

		$deliverMailUrl = rtrim($entryUrl, '/') . '/action/' . self::$_action . '?do=deliverMail&key={KEY}';
		$key = new Text(
			'key',
			null,
			\Typecho\Common::randString(16),
			_t('Key'),
			_t('执行发送任务地址为' . $deliverMailUrl)
		);
		$form->addInput($key->addRule('required', _t('key 不能为空.')));
	}

	/**
	 * 个人用户的配置面板
	 *
	 * @param \Typecho\Widget\Helper\Form $form
	 * @return void
	 */
	public static function personalConfig(\Typecho\Widget\Helper\Form $form)
	{
	}

	/**
	 * 建立 邮件队列 数据表
	 */
	public static function dbInstall()
	{
		$installDb = Db::get();
		$type = array_pop(explode('_', $installDb->getAdapterName())); //数据库类型 mysql/sqlite
		$prefix = $installDb->getPrefix(); //表前缀

		$scripts = file_get_contents(__DIR__ . '/sql/' . $type . '.sql');
		$scripts = str_replace('typecho_', $prefix, $scripts);
		$scripts = str_replace('%charset%', 'utf8', $scripts);
		$scripts = explode(';', $scripts);
		try {
			foreach ($scripts as $script) {
				$script = trim($script);
				if ($script) $installDb->query($script, Db::WRITE);
			}
			return '建立邮件队列数据表成功, 请继续设置SMTP信息';
		} catch (\Typecho\Db\Exception $e) {
			$code = $e->getCode();
			if (($type === 'Mysql' && $code === 1050) || ($type === 'SQLite' && ($code === 'HY000' || $code === 1))) {
				try {
					$script = "SELECT `id`, `content`, `sent` FROM `{$prefix}mail`";
					$installDb->query($script, Db::READ);
					return '检测到邮件队列数据表存在, 请继续设置SMTP信息';
				} catch (\Typecho\Db\Exception $e) {
					throw new \Typecho\Plugin\Exception('数据表检测失败, 插件启用失败。错误代码:' . $code);
				}
			} else {
				throw new \Typecho\Plugin\Exception('数据表建立失败, 插件启用失败。错误代码:' . $code);
			}
		}
	}

	/**
	 * 获取邮件内容(拦截评论)
	 *
	 * @param $comment 调用参数
	 * @return void
	 */
	public static function parseComment($comment)
	{
		$commentClass = new \TypechoPlugin\CommentToMail\lib\Comment;

		$commentClass->cid = $comment->cid;
		$commentClass->coid = $comment->coid;
		$commentClass->created = $comment->created;
		$commentClass->ip = $comment->ip;
		$commentClass->author = $comment->author;
		$commentClass->mail = $comment->mail;
		$commentClass->authorId = $comment->authorId;
		$commentClass->ownerId = $comment->ownerId;
		$commentClass->title = $comment->title;
		$commentClass->text = $comment->text;
		$commentClass->permalink = $comment->permalink;
		$commentClass->status = $comment->status;
		$commentClass->parent = $comment->parent;
		$commentClass->type = $comment->type ?? '2';

		// 添加至队列
		$db = Db::get();
		$db->query(
			$db->insert($db->getPrefix() . 'mail')->rows([
				'content' => base64_encode(serialize((object)$commentClass)),
				'sent' => '0'
			])
		);
	}

	/**
	 * 通过邮件 博主 通过邮件后 回调函数
	 *
	 * @param $comment,$edit,$status 调用参数
	 * @return void
	 */
	public static function passComment($comment, $edit, $status)
	{
		// 邮件 状态未通过时 > 访客不会收到通知, 只有访客被评论 的评论状态 为 approved时 才会 notify
		if ($status !== 'approved') return;
		$edit->status = 'approved';
		$edit->type = '1'; //标记 approved后的邮件 仅发送给访客 避免重复发送给博主
		self::parseComment($edit);
	}
}
