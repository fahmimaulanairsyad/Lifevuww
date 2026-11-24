<?php
/**
 * pages/widget_snapshot.php
 * Authenticated JSON snapshot endpoint for the Android Capacitor widget bridge.
 */

if (!function_exists('build_widget_snapshot_payload')) {
    function build_widget_snapshot_payload(
        array $user,
        array $habits,
        array $activeQuests,
        array $completedTodayQuests,
        array $companionMeta,
        string $todayDate
    ): array {
        $maxStreak = 0;
        $doneCount = 0;
        $totalCount = 0;
        $habitItems = [];

        foreach ($habits as $h) {
            $streak = (int)($h['streak'] ?? 0);
            if ($streak > $maxStreak) {
                $maxStreak = $streak;
            }
            $target = max(1, (int)($h['target_count'] ?? 1));
            $current = min($target, max(0, (int)($h['current_count'] ?? 0)));
            $isDone = (!empty($h['completed_today']) || $current >= $target);
            $isDue = function_exists('isHabitDueToday') ? isHabitDueToday($h, $todayDate) : true;

            if ($isDue) {
                $totalCount++;
                if ($isDone) {
                    $doneCount++;
                }
            }

            $habitItems[] = [
                'id' => (int)($h['habit_id'] ?? 0),
                'name' => (string)($h['habit_name'] ?? ''),
                'category' => (string)($h['category'] ?? 'Health'),
                'exp' => (int)($h['exp_reward'] ?? 5),
                'target_count' => $target,
                'current_count' => $current,
                'completed_today' => $isDone,
                'due_today' => $isDue,
                'schedule_type' => (string)($h['schedule_type'] ?? 'daily'),
                'schedule_value' => (string)($h['schedule_value'] ?? '1'),
                'last_completed_at' => $h['last_completed_at'] ?? null,
                'start_time' => !empty($h['start_time']) ? substr((string)$h['start_time'], 0, 5) : null,
                'end_time' => !empty($h['end_time']) ? substr((string)$h['end_time'], 0, 5) : null,
                'is_pinned' => !empty($h['is_pinned']),
                'sort_order' => (int)($h['sort_order'] ?? 0),
            ];
        }

        $formatQuest = static function (array $q, bool $completedToday) use ($todayDate): array {
            $subs = json_decode((string)($q['subtasks'] ?? '[]'), true);
            if (!is_array($subs)) {
                $subs = [];
            }
            $subDone = 0;
            foreach ($subs as $s) {
                if (!empty($s['done'])) {
                    $subDone++;
                }
            }
            $startDate = (string)($q['start_date'] ?? $todayDate);
            $endDate = !empty($q['end_date'])
                ? (string)$q['end_date']
                : date('Y-m-d', strtotime($startDate . ' +' . max(1, (int)($q['duration_days'] ?? 7)) . ' days'));

            return [
                'id' => (int)($q['quest_id'] ?? 0),
                'name' => (string)($q['quest_name'] ?? ''),
                'category' => (string)($q['category'] ?? 'General'),
                'exp' => (int)($q['exp_reward'] ?? 10),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => !empty($q['start_time']) ? substr((string)$q['start_time'], 0, 5) : null,
                'end_time' => !empty($q['end_time']) ? substr((string)$q['end_time'], 0, 5) : null,
                'is_pinned' => !empty($q['is_pinned']),
                'is_boss' => (($q['difficulty'] ?? '') === 'Hard'),
                'completed_today' => $completedToday,
                'subtasks_done' => $subDone,
                'subtasks_total' => count($subs),
                'sort_order' => (int)($q['sort_order'] ?? 0),
            ];
        };

        $questItems = [];
        $seenIds = [];
        foreach ($activeQuests as $q) {
            $qid = (int)($q['quest_id'] ?? 0);
            $seenIds[$qid] = true;
            $questItems[] = $formatQuest($q, false);
        }
        foreach ($completedTodayQuests as $q) {
            $qid = (int)($q['quest_id'] ?? 0);
            if (isset($seenIds[$qid])) {
                continue;
            }
            $seenIds[$qid] = true;
            $questItems[] = $formatQuest($q, true);
        }

        $compKey = (string)($user['active_companion'] ?? 'obsidian_wolf');
        if (function_exists('game_companions')) {
            $catalog = game_companions();
            if (!isset($catalog[$compKey])) {
                $compKey = 'obsidian_wolf';
            }
        }
        $stageRaw = strtolower(trim((string)($companionMeta['stage'] ?? 'hatchling')));
        $stageKey = in_array($stageRaw, ['hatchling', 'awakened', 'sovereign'], true) ? $stageRaw : 'hatchling';
        $sprite = $compKey . '_' . $stageKey;

        $totalExp = (int)($user['exp'] ?? 0);
        $level = function_exists('game_calculate_level') ? game_calculate_level($totalExp) : 1;

        return [
            'schema_version' => 1,
            'synced_at' => date('c'),
            'synced_date' => $todayDate,
            'logged_in' => true,
            'user' => [
                'username' => (string)($user['username'] ?? 'Hunter'),
                'level' => $level,
                'streak_max' => $maxStreak,
                'active_companion' => $compKey,
                'companion_stage_key' => $stageKey,
                'companion_sprite' => $sprite,
                'companion_bond_level' => (int)($companionMeta['bond_level'] ?? 1),
            ],
            'today_summary' => [
                'done_count' => $doneCount,
                'total_count' => $totalCount,
            ],
            'habits' => $habitItems,
            'quests' => $questItems,
        ];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once __DIR__ . '/../config/database.php';
    enforce_login();

    $userId = (int)$_SESSION['user_id'];
    $todayDate = date('Y-m-d');

    $stmtU = $pdo->prepare("SELECT username, exp, active_companion, companion_exp FROM users WHERE user_id = ? LIMIT 1");
    $stmtU->execute([$userId]);
    $user = $stmtU->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtH = $pdo->prepare("SELECT * FROM habits WHERE user_id = ? ORDER BY is_pinned DESC, sort_order ASC, habit_id ASC");
    $stmtH->execute([$userId]);
    $habits = $stmtH->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmtQ = $pdo->prepare("SELECT * FROM quests WHERE user_id = ? AND status = 'In Progress' ORDER BY is_pinned DESC, sort_order ASC, quest_id DESC");
    $stmtQ->execute([$userId]);
    $activeQuests = $stmtQ->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmtQDone = $pdo->prepare("
        SELECT q.*
        FROM quests q
        JOIN quest_logs ql ON ql.quest_id = q.quest_id AND ql.user_id = q.user_id
        WHERE q.user_id = ?
          AND ql.status = 'Completed'
          AND DATE(COALESCE(ql.completed_at, ql.completion_date, ql.created_at)) = CURDATE()
        GROUP BY q.quest_id
        ORDER BY q.is_pinned DESC, q.sort_order ASC, q.quest_id DESC
    ");
    $stmtQDone->execute([$userId]);
    $completedTodayQuests = $stmtQDone->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $companionMeta = game_companion_level((int)($user['companion_exp'] ?? 0));
    $payload = build_widget_snapshot_payload($user, $habits, $activeQuests, $completedTodayQuests, $companionMeta, $todayDate);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}
