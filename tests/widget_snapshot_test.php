<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../config/game.php';
require_once __DIR__ . '/../pages/widget_snapshot.php';

$user = [
    'username' => 'Fahmi',
    'exp' => 450,
    'active_companion' => 'chrono_owl',
    'companion_exp' => 240,
];
$habits = [
    [
        'habit_id' => 1,
        'habit_name' => 'Minum',
        'category' => 'Health',
        'exp_reward' => 5,
        'target_count' => 3,
        'current_count' => 1,
        'completed_today' => 0,
        'streak' => 12,
        'schedule_type' => 'daily',
        'schedule_value' => '1',
        'last_completed_at' => null,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'is_pinned' => 1,
        'sort_order' => 0,
    ],
    [
        'habit_id' => 2,
        'habit_name' => 'Baca',
        'category' => 'Intelligence',
        'exp_reward' => 5,
        'target_count' => 1,
        'current_count' => 1,
        'completed_today' => 1,
        'streak' => 4,
        'schedule_type' => 'daily',
        'schedule_value' => '1',
        'last_completed_at' => '2026-11-23 07:00:00',
        'start_time' => null,
        'end_time' => null,
        'is_pinned' => 0,
        'sort_order' => 1,
    ],
];
$activeQuests = [
    [
        'quest_id' => 10,
        'quest_name' => 'Lari 5K',
        'category' => 'Physical',
        'exp_reward' => 15,
        'difficulty' => 'Hard',
        'start_date' => '2026-11-18',
        'end_date' => '2026-11-26',
        'start_time' => '19:00:00',
        'end_time' => '20:00:00',
        'is_pinned' => 1,
        'subtasks' => '[{"title":"A","done":true},{"title":"B","done":false}]',
        'sort_order' => 0,
    ],
];
$doneQuests = [
    [
        'quest_id' => 11,
        'quest_name' => 'Kuis JS',
        'category' => 'Intelligence',
        'exp_reward' => 10,
        'difficulty' => 'Easy',
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-23',
        'start_time' => null,
        'end_time' => null,
        'is_pinned' => 0,
        'subtasks' => null,
        'sort_order' => 1,
    ],
];

$payload = build_widget_snapshot_payload($user, $habits, $activeQuests, $doneQuests, ['stage' => 'Awakened', 'bond_level' => 4], '2026-11-23');
if ($payload['schema_version'] !== 1) exit("FAIL: schema_version\n");
if ($payload['today_summary']['done_count'] !== 1 || $payload['today_summary']['total_count'] !== 2) exit("FAIL: today_summary\n");
if ($payload['user']['streak_max'] !== 12) exit("FAIL: streak_max\n");
if ($payload['user']['companion_sprite'] !== 'chrono_owl_awakened') exit("FAIL: companion_sprite\n");
if (count($payload['quests']) !== 2) exit("FAIL: quests count\n");
if ($payload['quests'][0]['completed_today'] !== false || $payload['quests'][1]['completed_today'] !== true) exit("FAIL: quest completed_today\n");
echo "PASS: widget_snapshot_test\n";
