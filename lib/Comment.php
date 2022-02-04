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
 * Comment
 * 
 * @package CommentToMail
 */
class Comment
{
	/**
	 * 文章ID
	 *
	 * @var int
	 */
	public int $cid;

	/**
	 * 评论ID
	 *
	 * @var int
	 */
	public int $coid;

	/**
	 * 评论创建时间
	 *
	 * @var integer
	 */
	public int $created;

	/**
	 * 评论作者
	 *
	 * @var string
	 */
	public string $author;

	/**
	 * 作者ID
	 *
	 * @var int
	 */
	public int $authorId;

	/**
	 * 不知道什么玩意儿
	 *
	 * @var integer
	 */
	public int $ownerId;

	/**
	 * 邮箱
	 *
	 * @var string
	 */
	public string $mail;

	/**
	 * ip
	 *
	 * @var string
	 */
	public string $ip;

	/**
	 * 文章名称
	 *
	 * @var string
	 */
	public string $title;

	/**
	 * 邮件内容
	 *
	 * @var string
	 */
	public string $text;

	/**
	 * 评论地址
	 *
	 * @var string
	 */
	public string $permalink;

	/**
	 * 状态
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * 被评论者
	 *
	 * @var string
	 */
	public string $parent;

	/**
	 * 对于访客时 访客的原始文字
	 *
	 * @var string
	 */
	public string $originalText;

	/**
	 * 对于访客时 访客的名称
	 *
	 * @var string
	 */
	public string $originalAuthor;

	/**
	 * 对于访客时 访客的邮件地址
	 *
	 * @var string
	 */
	public string $originalMail;

	/**
	 * 模版中联系我的邮箱
	 *
	 * @var string
	 */
	public string $contactme;

	/**
	 * 评论类型 1 > 仅向访客发送邮件 2 > 向博主和访客发送邮件
	 *
	 * @var string
	 */
	public string $type;
}
