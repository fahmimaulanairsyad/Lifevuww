-- Migration 003: Security Hardening & Performance Indexes
-- Brute-force tracking, Unique constraints, Status updates

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip_time` (`ip`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Enforce Unique Credentials to prevent race conditions on registration
ALTER TABLE `users`
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

-- Quests status enum update to include 'Failed'
ALTER TABLE `quests`
  MODIFY COLUMN `status` enum('In Progress','Completed','Failed') NOT NULL DEFAULT 'In Progress';

-- Skills columns nullable to support atomic upsert
ALTER TABLE `skills`
  MODIFY COLUMN `skill_name` varchar(100) NULL,
  MODIFY COLUMN `skill_type` enum('Wealth','Intelligence','Physical','Health','Social') NULL;

-- Performance index on habit_logs for 60-day heatmap & velocity charts
ALTER TABLE `habit_logs`
  ADD KEY IF NOT EXISTS `user_date` (`user_id`, `completion_date`);

-- Ensure skills cascades properly on user deletion
ALTER TABLE `skills`
  DROP FOREIGN KEY `skills_ibfk_1`;
ALTER TABLE `skills`
  ADD CONSTRAINT `skills_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- Sync legacy users level column with calculated exp level
UPDATE `users`
  SET `level` = FLOOR(`exp` / 100) + 1
  WHERE `level` != (FLOOR(`exp` / 100) + 1);
