-- Migration 005: Flexible Habits Scheduling & Multi-Count Stepper
-- Adds target_count, current_count, schedule_type, schedule_value, and last_completed_at to habits table

ALTER TABLE `habits`
  ADD COLUMN IF NOT EXISTS `target_count` int(11) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `current_count` int(11) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `schedule_type` varchar(30) NOT NULL DEFAULT 'daily',
  ADD COLUMN IF NOT EXISTS `schedule_value` varchar(100) NOT NULL DEFAULT '1',
  ADD COLUMN IF NOT EXISTS `last_completed_at` datetime DEFAULT NULL;
