CREATE TABLE IF NOT EXISTS typecho_mail (
  id SERIAL PRIMARY KEY,
  content text NOT NULL,
  sent int DEFAULT 0
);