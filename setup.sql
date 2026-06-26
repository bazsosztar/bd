-- datebalazs.com — MySQL setup
-- Run this once before starting the server

CREATE DATABASE IF NOT EXISTS datebala_datebalazs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE datebala_datebalazs;

-- Generic event log (every click, spin, quiz result)
CREATE TABLE IF NOT EXISTS events (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  type       VARCHAR(50)  NOT NULL,
  data       JSON,
  ts         DATETIME     NOT NULL,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Who said Yes and their contact info
CREATE TABLE IF NOT EXISTS contacts (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  instagram       VARCHAR(255),
  facebook        VARCHAR(255),
  whatsapp        VARCHAR(50),
  secure_chat     VARCHAR(50),
  bumble_profile  VARCHAR(255),
  lang            VARCHAR(5),
  ts              DATETIME     NOT NULL,
  created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Reviews / ratings
CREATE TABLE IF NOT EXISTS reviews (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(255),
  stars      INT,
  review     TEXT,
  lang       VARCHAR(5),
  ts         DATETIME     NOT NULL,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Useful views
CREATE OR REPLACE VIEW v_daily_stats AS
SELECT
  DATE(ts)   AS day,
  type,
  COUNT(*)   AS total
FROM events
GROUP BY DATE(ts), type
ORDER BY day DESC, total DESC;
