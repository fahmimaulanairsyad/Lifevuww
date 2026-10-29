-- Migration 006: RPG Expansion v2 (Specializations, Boss Mastery, Relics)

CREATE TABLE IF NOT EXISTS `user_relics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `relic_key` varchar(50) NOT NULL,
  `acquired_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_relic` (`user_id`, `relic_key`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_user_relics_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `specialization` varchar(50) NOT NULL DEFAULT 'Obsidian Vanguard';
