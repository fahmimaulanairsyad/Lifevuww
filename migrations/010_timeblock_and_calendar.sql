-- Migration 010: Time Blocking & Tactical Calendar (start_time and end_time)
-- Menambahkan kolom rentang waktu start_time dan end_time pada tabel habits dan quests.
-- Tanpa ADD COLUMN IF NOT EXISTS agar kompatibel MySQL 8; jalankan sekali lewat
-- schema_migrations (duplikasi aman: runner mengabaikan error duplicate column).

ALTER TABLE `habits`
  ADD COLUMN `start_time` TIME DEFAULT NULL,
  ADD COLUMN `end_time` TIME DEFAULT NULL;

ALTER TABLE `quests`
  ADD COLUMN `start_time` TIME DEFAULT NULL,
  ADD COLUMN `end_time` TIME DEFAULT NULL;
