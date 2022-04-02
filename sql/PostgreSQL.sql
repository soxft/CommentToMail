CREATE TABLE IF NOT EXISTS typecho_mail (
  `id` int(11) unsigned SERIAL PRIMARY KEY,
  `content` text NOT NULL,
  `sent` int(1) DEFAULT '0',
);