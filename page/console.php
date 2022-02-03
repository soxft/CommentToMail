<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * CommentToMail
 * Typecho 异步评论邮件提醒插件
 * 
 * @copyright  Copyright (c) 2022 xcsoft
 * @license    GNU General Public License 3.0
 */

require_once 'header.php';
require_once 'menu.php';

use \Typecho\Widget;
use \TypechoPlugin\CommentToMail\Plugin;

$current = $request->get('act', 'index');
$theme = $request->get('file', 'owner.html');
$title = $current == 'index' ? $menu->title : '编辑邮件模板 ' . $theme;
?>
<div class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2><?= $title ?></h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <!-- MENU -->
            <div class="col-mb-12">
                <ul class="typecho-option-tabs fix-tabs clearfix">
                    <li <?= ($current == 'index' ? ' class="current"' : '') ?>>
                        <a href="<?php $options->adminUrl('extending.php?panel=' . Plugin::$_panel); ?>"><?php _e('邮件发送测试'); ?></a>
                    </li>
                    <li <?= ($current == 'theme' ? ' class="current"' : '') ?>>
                        <a href="<?php $options->adminUrl('extending.php?panel=' . Plugin::$_panel . '&act=theme'); ?>">
                            <?php _e('编辑邮件模板'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php $options->adminUrl('options-plugin.php?config=CommentToMail') ?>"><?php _e('插件设置'); ?></a>
                    </li>
                </ul>
            </div>
            <?php
            if ($current == 'index') :
            ?>
                <div class="typecho-edit-theme">
                    <div class="col-mb-12 col-tb-8 col-9 content">
                        <?php Widget::widget('TypechoPlugin\CommentToMail\Console')->testMailForm()->render(); ?>
                    </div>
                </div>
            <?php
            else :
                Widget::widget('TypechoPlugin\CommentToMail\Console')->to($files);
            ?>
                <div class="typecho-edit-theme">
                    <div class="col-mb-12 col-tb-8 col-9 content">
                        <form method="post" name="theme" id="theme" action="<?php $options->index('/action/' . Plugin::$_action); ?>">
                            <label for="content" class="sr-only"><?php _e('编辑源码'); ?></label>
                            <textarea name="content" id="content" class="w-100 mono" <?php if (!$files->currentIsWriteable()) echo 'readonly'; ?>>
                                <?php echo $files->currentContent(); ?>
                            </textarea>
                            <p class="submit">
                                <?php if ($files->currentIsWriteable()) : ?>
                                    <input type="hidden" name="do" value="editTheme" />
                                    <input type="hidden" name="edit" value="<?php echo $files->currentFile(); ?>" />
                                    <button type="submit" class="btn primary"><?php _e('保存文件'); ?></button>
                                <?php else : ?>
                                    <em><?php _e('文件无写入权限'); ?></em>
                                <?php endif; ?>
                            </p>
                        </form>
                    </div>
                    <ul class="col-mb-12 col-tb-4 col-3">
                        <li><strong>模板文件</strong></li>
                        <?php while ($files->next()) : ?>
                            <li <?php if ($files->current) echo "class='current'"; ?>>
                                <a href="<?php $options->adminUrl('extending.php?panel=' . Plugin::$_panel . '&act=theme' . '&file=' . $files->file); ?>">
                                    <?php $files->file(); ?>
                                </a>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once 'copyright.php';
require_once 'common-js.php';
require_once 'footer.php';
?>