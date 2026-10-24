-- Migration 002: RPG Gameplay Expansion
-- Add HP, Gold, Streaks, Daily Check, Reminders, Rewards Shop

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `hp` int(11) DEFAULT 100,
  ADD COLUMN IF NOT EXISTS `max_hp` int(11) DEFAULT 100,
  ADD COLUMN IF NOT EXISTS `gold` int(11) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `streak_freeze` int(11) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `last_daily_check` date DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `reminder_time` time DEFAULT '20:00:00';

ALTER TABLE `habits`
  ADD COLUMN IF NOT EXISTS `streak` int(11) DEFAULT 0;

CREATE TABLE IF NOT EXISTS `rewards` (
  `reward_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reward_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `cost` int(11) NOT NULL DEFAULT 0,
  `icon` varchar(50) DEFAULT 'fa-gift',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reward_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `rewards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `reward_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reward_id` int(11) DEFAULT NULL,
  `cost` int(11) NOT NULL,
  `claimed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `reward_id` (`reward_id`),
  CONSTRAINT `reward_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `reward_logs_ibfk_2` FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`reward_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
