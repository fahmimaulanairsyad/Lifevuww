-- Migration 004: Boss Quests & Subtask Checklist
-- Adds subtasks JSON storage for multi-step quest progression

ALTER TABLE `quests`
  ADD COLUMN IF NOT EXISTS `subtasks` text DEFAULT NULL;
