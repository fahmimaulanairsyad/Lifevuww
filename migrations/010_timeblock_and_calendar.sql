-- Migration 010: Time Blocking & Tactical Calendar (start_time and end_time)
-- Menambahkan kolom rentang waktu start_time dan end_time pada tabel habits dan quests

ALTER TABLE `habits`
  ADD COLUMN IF NOT EXISTS `start_time` TIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `end_time` TIME DEFAULT NULL;

ALTER TABLE `quests`
  ADD COLUMN IF NOT EXISTS `start_time` TIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `end_time` TIME DEFAULT NULL;
