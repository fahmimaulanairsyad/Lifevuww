-- Migration 011: Daily System Directives & Emergency Recovery Zone
-- Menambahkan kolom last_crate_date dan last_emergency_heal_date pada tabel users

ALTER TABLE `users` ADD COLUMN `last_crate_date` DATE DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `last_emergency_heal_date` DATE DEFAULT NULL;
