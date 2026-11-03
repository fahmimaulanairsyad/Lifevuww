-- Migration 012: Sovereign Expansion (Lifevuww v3.0)
-- Kolom users untuk Companion/Skill Tree/Overdrive + tabel Dungeon Raid & Jurnal Taktis.
-- Tanpa ADD COLUMN IF NOT EXISTS agar kompatibel MySQL 8 (kolom juga ditambahkan
-- via pola DESCRIBE + ALTER di includes/migrate.php).

ALTER TABLE `users`
  ADD COLUMN `active_companion` VARCHAR(40) NOT NULL DEFAULT 'obsidian_wolf',
  ADD COLUMN `companion_exp` INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN `spent_skill_points` INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN `unlocked_skills` TEXT DEFAULT NULL,
  ADD COLUMN `overdrive_until` DATETIME DEFAULT NULL,
  ADD COLUMN `last_skill_cast_date` DATE DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `dungeon_raids` (
  `raid_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `week_key` VARCHAR(10) NOT NULL,
  `boss_key` VARCHAR(40) NOT NULL,
  `boss_hp` INT(11) NOT NULL,
  `boss_max_hp` INT(11) NOT NULL,
  `total_damage_dealt` INT(11) NOT NULL DEFAULT 0,
  `is_defeated` TINYINT(1) NOT NULL DEFAULT 0,
  `reward_claimed` TINYINT(1) NOT NULL DEFAULT 0,
  `battle_log` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`raid_id`),
  UNIQUE KEY `uniq_user_week` (`user_id`, `week_key`),
  CONSTRAINT `fk_dungeon_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `journal_entries` (
  `entry_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `entry_date` DATE NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `content` TEXT NOT NULL,
  `mood_score` TINYINT(1) NOT NULL DEFAULT 3,
  `energy_score` TINYINT(1) NOT NULL DEFAULT 3,
  `tags` VARCHAR(120) DEFAULT NULL,
  `exp_awarded` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`entry_id`),
  KEY `idx_journal_user_date` (`user_id`, `entry_date`),
  CONSTRAINT `fk_journal_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
