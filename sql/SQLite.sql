CREATE TABLE IF NOT EXISTS `typecho_mail` (
  `id` INTEGER NOT NULL PRIMARY KEY,
  `content` text NOT NULL,
  `sent` int(1) default 0,
);