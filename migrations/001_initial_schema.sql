-- Migration 001: Initial Schema (Lifevuww Foundation)
-- Tables: users, habits, quests, skills, habit_logs, quest_logs, habit_reset_periods

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `rank` varchar(50) DEFAULT 'F-Rank Novice',
  `level` int(11) DEFAULT 1,
  `exp` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `habit_reset_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `habits` (
  `habit_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `habit_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `difficulty` enum('Easy','Medium','Hard') DEFAULT 'Easy',
  `frequency` enum('Daily','Weekly','Monthly') DEFAULT 'Daily',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_period_id` int(11) DEFAULT NULL,
  `exp_reward` int(11) DEFAULT 5,
  `skill_id` int(11) DEFAULT NULL,
  `category` enum('Wealth','Intelligence','Physical','Health','Social') DEFAULT 'Intelligence',
  `last_reset` datetime DEFAULT NULL,
  `completed_today` tinyint(4) DEFAULT 0,
  `reset_period` enum('Daily','Weekly','Monthly') DEFAULT 'Daily',
  PRIMARY KEY (`habit_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `habits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `quests` (
  `quest_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `quest_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `difficulty` enum('Easy','Medium','Hard') NOT NULL DEFAULT 'Easy',
  `exp_reward` int(11) DEFAULT 5,
  `min_level` int(11) DEFAULT 1,
  `duration_days` int(11) DEFAULT 7,
  `status` enum('In Progress','Completed','Failed') NOT NULL DEFAULT 'In Progress',
  `skill_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`quest_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `quests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `skill_id` int(11) NOT NULL,
  `skill_name` varchar(100) DEFAULT NULL,
  `skill_type` enum('Wealth','Intelligence','Physical','Health','Social') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `min_level` int(11) DEFAULT 1,
  `category` enum('1','2','3','4','5') DEFAULT '1',
  `progress` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_skill` (`user_id`,`skill_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `skills_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `habit_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `habit_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `completion_date` date DEFAULT NULL,
  `status` enum('Completed','Failed','Skipped') NOT NULL,
  `skill_id` int(11) DEFAULT NULL,
  `exp_reward` int(11) DEFAULT 0,
  PRIMARY KEY (`log_id`),
  KEY `habit_id` (`habit_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `habit_logs_ibfk_1` FOREIGN KEY (`habit_id`) REFERENCES `habits` (`habit_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `habit_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `quest_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `quest_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `completion_date` datetime DEFAULT NULL,
  `status` enum('Pending','Completed','Failed') NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `skill_id` int(11) DEFAULT NULL,
  `exp_reward` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `quest_id` (`quest_id`),
  CONSTRAINT `quest_logs_ibfk_1` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`quest_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `quest_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
