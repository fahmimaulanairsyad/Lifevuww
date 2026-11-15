# Lifevuww reconcept implementation plan

**Goal:** Make Lifevuww an original personal-development RPG with clear daily actions and durable progress.
**Architecture:** Keep the existing PHP/PDO application, category identifiers and user records. Introduce improvements in independently verified commits rather than replacing the application or discarding saved progress.
**Spec:** User approved all six recommendations in conversation on 2026-11-15: original identity, action-first dashboard, connected goals, recovery, meaningful RPG progression, deployment and verification.

## Global constraints
- Preserve all five category IDs and historical attribute data. Physical remains available; no compulsory balanced radar score.
- Preserve EXP, level and earned achievements when HP reaches zero.
- New art is original transparent pixel art, reproducibly generated.
- Keep existing CSRF, authorization, transactional reward handling and recurrence rules.
- Deploy patches without overwriting unrelated destination changes or environment files.

## Review focus
- Zero HP from concurrent requests must recover once without reducing progress.
- Tasks not due must never enter daily priorities.
- Focus/task linking must not issue duplicate completion rewards.
- Users with no activities need an actionable empty state.
- Existing companion keys and category IDs must remain valid.

## Work packages
- [x] Original pixel collection committed and pushed: e21f5e7.
- [ ] Recovery: remove total-EXP and global-streak penalties, explain recovery consistently, add DB-independent transition tests.
- [ ] Attribute guidance: distinguish Intelligence, Physical, Health, Wealth and Social with concrete examples, retaining IDs.
- [ ] Action-first dashboard: show up to three due activities and direct links before secondary RPG panels.
- [ ] Goals: add owned goals and milestones, optional quest/habit links, progress and focus association with idempotent rewards.
- [ ] Planned breaks and weekly consistency: persist date ranges and apply to rollover without retroactive changes.
- [ ] Companion evolution: distinct stage artwork, valid fallbacks and consistent sprites on all pages.
- [ ] Adaptive dungeon: capacity-aware target snapshotted at entry, bounded difficulty and stable rewards.
- [ ] Weekly review: summarize completion, mood and energy without implying causal relationships.
- [ ] Release pipeline and documentation: safe deployment manifest, hash validation, update feature/version documentation and browser smoke checks.

## Execution record
2026-11-15: Begin with recovery and clear attribute guidance because both affect the rest of the redesign. Default decision: retain Physical rather than silently remapping historical activities to Mental. No new mental-health scoring.
