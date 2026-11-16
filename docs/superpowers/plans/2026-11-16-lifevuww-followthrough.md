# Lifevuww Follow-Through Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement task-by-task. Steps use checkbox syntax.

**Goal:** Complete the remaining approved Lifevuww reconcept items: make linked activities advance goal steps safely, create a humane return path after inactivity, show companion history and balanced dungeon progress, and align docs/deploy.
**Architecture:** Preserve existing PHP/PDO modules and data. Add small database tables through sequential migrations, expose pure decision rules for CLI tests, and keep activity rewards idempotent within their existing transactions.
**Tech Stack:** PHP 8.1+, MySQL/MariaDB, existing migration runner, PHP CLI test suite, PowerShell deployment script.
**Spec:** User-approved reconcept quoted in chat on 2026-11-16; prior design in `docs/superpowers/plans/2026-11-15-lifevuww-reconcept.md`.

## Global Constraints
- Preserve historical EXP, level, achievements, five attribute keys and IDs.
- Keep `Physical` optional; do not add a compulsory Mental stat.
- One completed activity grants its existing rewards exactly once, even if linked to multiple goals.
- No automatic retroactive completion or punishment when new rules are installed.
- A planned break only changes activities that overlap its dates; protect against overlapping break ranges.
- Do not deploy, push or overwrite user data; keep deploy preview as the default.
- All new visuals must be original, transparent pixel art and must not use Solo Leveling characters, marks or art.

## Review Focus
- Concurrent/retried activity completion advances goal links once, never multiple times.
- A habit linked to multiple goals updates each matching goal once.
- Failed/dismissed activity does not complete milestones or grant progress.
- Returning users with missed dates see a manageable recovery action and no retroactive destructive loss.
- Dungeon scaling is bounded and fixed for the entire weekly encounter.

## File Map
- `config/game.php`: pure goal-link matching, recovery recommendation and balance rules.
- `pages/habits.php`, `pages/quests.php`, their completion endpoints: transactional propagation of completed activity IDs to goal milestones.
- `pages/dashboard.php`, `includes/header.php`: return-to-routine prompt and clear navigation.
- `migrations/016_goal_activity_progress.sql`: unique mapping from a goal-linked habit/quest to the milestone it advances.
- `migrations/017_companion_chronicle.sql`: companion stage history and timestamped event records.
- `migrations/018_raid_capacity.sql`: capacity snapshot, nullable for existing encounters.
- `pages/skills.php`, `pages/profile.php`: companion chronicle view and distinct evolution-state feedback.
- `pages/dungeon.php`, `config/game.php`: explain adaptive target and progression/reward caps with tests.
- `README.md`, `docs/deployment.md`, `scripts/deploy.ps1`, relevant tests: documentation, deploy inventory and verification.
- `scripts/generate_pixel_assets.py`, `assets/images/companions/`: only add chronicle/quest illustration assets if the UI cannot be made clear with existing assets.

## Tasks

### Task 1: Linked activity advances goal milestone once
**Interfaces:** Existing `goal_activities(goal_id, kind, activity_id)`; add `goal_activity_progress(user_id,kind,activity_id,goal_id,milestone_id)` as a unique ownership-safe mapping.
- [ ] Add CLI tests for matching links, no match, and distinct goals; observe failure.
- [ ] Add migration 016 with unique `(user_id,kind,activity_id,goal_id)` and unique `(user_id,milestone_id)`.
- [ ] Add milestone selection to the Journey link form and validate milestone belongs to the supplied goal.
- [ ] In the existing completion transaction, advance linked milestone(s) with a conditional incomplete update and idempotency key; retain current reward behavior.
- [ ] Test repeated requests, multiple goals, unauthorized IDs, completed/reversed task policy; run suite and lint.
- [ ] Commit `feat(goals): advance linked milestones on activity completion`.

### Task 2: Return path after missed routines
- [ ] Add pure tests for no-miss, one-miss, multi-miss and planned-break cases; observe failure.
- [ ] Add helper producing a non-punitive recovery prompt without rewriting history or streaks.
- [ ] Show a dismissible dashboard prompt with one recommended small action and direct resume link; do not duplicate the existing emergency HP action.
- [ ] Record dismissal per local day, not permanent suppression; test WIB day rollover.
- [ ] Verify HP-zero recovery and legacy rollover behavior remain unchanged; run suite and lint.
- [ ] Commit `feat(recovery): guide users back with a small next action`.

### Task 3: Companion chronicle and RPG feedback
- [ ] Add tests for stage transition boundaries, event ordering and empty history; observe failure.
- [ ] Add migration 017 for append-only companion events keyed by user, companion and event type/date.
- [ ] Record bond XP and stage transitions transactionally with existing boss-damage updates; prevent duplicate stage events.
- [ ] Render an accessible chronicle on Skills/Profile with stage names, dates and current original sprite.
- [ ] Ensure sprite alt text is meaningful and fallbacks work when an evolution PNG is absent.
- [ ] Run asset validation, application suite, PHP lint, browser render; commit `feat(companion): show evolution chronicle`.

### Task 4: Dungeon balance and product explanation
- [ ] Add boundary tests for minimum, midpoint, maximum, no history, high history, and weekly reset.
- [ ] Display how this week's target was set using actual activity count and selected tier, without fake precision.
- [ ] Snapshot target once per raid; ensure opening a later gate is bounded and does not reset the already-open weekly boss unexpectedly.
- [ ] Audit completion damage/reward arithmetic against existing boss and companion buffs.
- [ ] Run full suite and browser tests; commit `feat(dungeon): explain adaptive weekly challenge`.

### Task 5: Documentation and deploy alignment
- [ ] Update README title/version/feature matrix, recovery policy and category definitions; remove obsolete penalty text.
- [ ] Update deployment manifest/documentation to cover migrations 013-017, images, tests excluded from runtime, `.env` never copied, preview default and rollback path.
- [ ] Run deploy preview, apply only after comparing planned file list with intended source; verify SHA-256 and preserve `.env`/uploads.
- [ ] Run tests, all PHP lints, asset checks, browser smoke test and review clean Git status.
- [ ] Commit `docs: align Lifevuww product and deployment guides`.

## Execution Notes
- Start from `aaaa8d7` plus current `main`; no push.
- Existing UI/badge snapshot committed as `aaaa8d7`. New implementation remains uncommitted for chat approval; do not push.
- Resolve reviewer/UI feedback locally before creating the final review commit.

## Progress ledger
- Current UI/badges committed: aaaa8d7. No push.
- Goal activity mapping: pure matching tests and transactional database tests pass. HTTP completion/replay/undo preserves milestones, with no extra reward for links.
- Recovery: recent failed logs or an active planned break yield a small next action. Dismissal is session-scoped per WIB date. No history is rewritten.
- Companion: baseline and evolution events recorded after real damage; no invented past dates. Bond EXP remains character-wide and is explicitly described that way.
- Dungeon: transaction/savepoint protects all damage writes; overkill capped; weekly capacity is fixed for later encounters. Existing raids are unchanged.
- Identity: original Nawasela/Lima Poros config and guide integrated in Journey, expedition and chronicle display. Internal rank/companion keys preserved.
- Verification: PHP suite 152/152; DB fixture rollback; HTTP fixture creation/link/completion/replay/undo/focus/11 pages green.
- Review fixes: orphan activity links remain visible/detachable; rollover failures through the current date trigger recovery; deadlocks propagate as full transaction failures without masking the original exception; timer completion moved to shared scope and token survives until claim.
- Verification of review fixes: HTTP regression suite and actual JS timer test pass. All fixture records removed.
- Ruling: earned milestones survive activity undo, but may be manually marked incomplete. Remapping an already advanced link is disallowed. Cost: users wanting to repeat the same step must make a new step/activity mapping.
- Ruling: companion bond remains character-wide, matching the existing schema. The UI says this explicitly; no fabricated per-companion history. Cost: selecting another companion uses the shared bond level.
- Ruling: new work remains uncommitted for chat review despite task commit steps above; only aaaa8d7 was explicitly approved as the current snapshot.
- Local synchronization complete: 125 selected runtime/support files, 61 additions/replacements, SHA-256 verified. Destination .env unchanged. Backup: `C:/Users/Pongo/AppData/Local/Temp/opencode/lifevuww-deploy-backups/20261116-105344-e7d4b4f021f547fcb1f312bac46d7c67`.
- Final verification: core suite 152/152 in source and deploy; pure tests, timer JS tests, database rollback tests, HTTP tests (11 pages and real completion/focus/replay/undo/orphan/recovery flows) green; fixture user count zero; PHP lint clean.
- Visual: desktop dashboard and Journey inspected. Fixed goal-form grid alignment discovered visually. Narrow viewport request was inconsistent due to browser zoom; physical-phone verification remains a limitation, not a passing claim.
- No further commit or push. Await user review/commit approval in chat.
