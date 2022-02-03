CREATE TABLE IF NOT EXISTS `typecho_mail` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `content` text NOT NULL,
  `sent` tinyint(1) DEFAULT '0',
  `log` text,
  PRIMARY KEY (`id`)
) ENGINE=MYISAM DEFAULT CHARSET=%charset%;