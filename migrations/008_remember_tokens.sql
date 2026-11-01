-- Migration 008: Remember Tokens (Login Persisten)
-- Menyimpan token remember-me untuk auto-login antar buka aplikasi
-- (APK WebView / browser) tanpa sesi PHP. Format cookie: selector:validator;
-- validator disimpan sebagai SHA-256 dan dirotasi pada tiap pemakaian.
-- Dipakai oleh config/database.php (lv_issue_remember_token, lv_consume_remember_token).

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `selector` varchar(64) NOT NULL,
  `validator_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_selector` (`selector`),
  KEY `idx_rt_user` (`user_id`),
  KEY `idx_rt_expires` (`expires_at`),
  CONSTRAINT `fk_remember_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
