-- Migration 009: Audit Fixes
-- Perbaiki data lama: habit bertipe weekly_days wajib memiliki reset_period 'daily'
UPDATE habits SET reset_period = 'daily' WHERE schedule_type = 'weekly_days';
