CREATE TABLE IF NOT EXISTS `typecho_mail` (
  `id` INTEGER NOT NULL PRIMARY KEY,
  `content` text NOT NULL,
  `sent` tinyint(1) default '0',
  `log` text
);