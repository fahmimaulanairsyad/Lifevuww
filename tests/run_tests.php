<?php
/**
 * tests/run_tests.php
 * Test runner mandiri berbasis CLI untuk rumus game & logika inti Lifevuww.
 * Tidak membutuhkan dependensi eksternal (PHPUnit/Pest), berjalan di lingkungan PHP apa pun.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Akses ditolak: Test suite hanya dapat dijalankan melalui CLI.\n");
}

require_once __DIR__ . '/../config/game.php';

$total = 0;
$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $details = '') {
    global $total, $passed, $failed;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] $name\n";
    } else {
        $failed++;
        echo "  [FAIL] $name" . ($details ? " ($details)" : "") . "\n";
    }
}

echo "=== Lifevuww Test Suite ===\n\n";

echo "1. Pengujian Rumus Level (game_calculate_level):\n";
assert_test("0 EXP menghasilkan Level 1", game_calculate_level(0) === 1);
assert_test("99 EXP menghasilkan Level 1", game_calculate_level(99) === 1);
assert_test("100 EXP menghasilkan Level 2", game_calculate_level(100) === 2);
assert_test("250 EXP menghasilkan Level 3", game_calculate_level(250) === 3);
assert_test("2999 EXP menghasilkan Level 30", game_calculate_level(2999) === 30);
assert_test("3000 EXP menghasilkan Level 31", game_calculate_level(3000) === 31);

echo "\n2. Pengujian Peringkat / Rank Tiers (game_calculate_rank):\n";
assert_test("Level 1 adalah F-Rank Novice", game_calculate_rank(1) === 'F-Rank Novice');
assert_test("Level 2 adalah F-Rank Novice", game_calculate_rank(2) === 'F-Rank Novice');
assert_test("Level 3 adalah E-Rank Rookie", game_calculate_rank(3) === 'E-Rank Rookie');
assert_test("Level 6 adalah D-Rank Explorer", game_calculate_rank(6) === 'D-Rank Explorer');
assert_test("Level 10 adalah C-Rank Vanguard", game_calculate_rank(10) === 'C-Rank Vanguard');
assert_test("Level 15 adalah B-Rank Elite", game_calculate_rank(15) === 'B-Rank Elite');
assert_test("Level 22 adalah A-Rank Master", game_calculate_rank(22) === 'A-Rank Master');
assert_test("Level 30 adalah S-Rank Sovereign", game_calculate_rank(30) === 'S-Rank Sovereign');
assert_test("Level 50 tetap S-Rank Sovereign", game_calculate_rank(50) === 'S-Rank Sovereign');

echo "\n3. Pengujian Multiplier Streak (game_streak_multiplier):\n";
assert_test("Streak 0 bernilai multiplier 1.0", game_streak_multiplier(0) === 1.0);
assert_test("Streak 2 bernilai multiplier 1.0", game_streak_multiplier(2) === 1.0);
assert_test("Streak 3 bernilai multiplier 1.2", game_streak_multiplier(3) === 1.2);
assert_test("Streak 6 bernilai multiplier 1.2", game_streak_multiplier(6) === 1.2);
assert_test("Streak 7 bernilai multiplier 1.5", game_streak_multiplier(7) === 1.5);
assert_test("Streak 30 bernilai multiplier 1.5", game_streak_multiplier(30) === 1.5);

echo "\n4. Pengujian Penalti HP (hp_penalty_for_quest & habit):\n";
assert_test("Penalti quest Easy adalah 10 HP", hp_penalty_for_quest('Easy') === 10);
assert_test("Penalti quest Medium adalah 20 HP", hp_penalty_for_quest('Medium') === 20);
assert_test("Penalti quest Hard adalah 30 HP", hp_penalty_for_quest('Hard') === 30);
assert_test("Penalti habit Easy adalah 5 HP", hp_penalty_for_habit('Easy') === 5);
assert_test("Penalti habit Medium adalah 10 HP", hp_penalty_for_habit('Medium') === 10);
assert_test("Penalti habit Hard adalah 15 HP", hp_penalty_for_habit('Hard') === 15);
assert_test("Penalti quest tidak valid fallback ke 10 HP", hp_penalty_for_quest('Unknown') === 10);

echo "\n5. Pengujian Kategori & Atribut Inti:\n";
$cats = game_categories();
assert_test("Ada 5 kategori/atribut inti", count($cats) === 5);
assert_test("Kategori Intelligence terdaftar", isset($cats['Intelligence']));
assert_test("Kategori Physical terdaftar", isset($cats['Physical']));
assert_test("Kategori Wealth terdaftar", isset($cats['Wealth']));
assert_test("Kategori Health terdaftar", isset($cats['Health']));
assert_test("Kategori Social terdaftar", isset($cats['Social']));

echo "\n6. Pengujian Item Ramuan Toko:\n";
$potions = game_potions();
assert_test("Ada 3 item konsumsi toko", count($potions) === 3);
assert_test("Ramuan minor memulihkan 25 HP", ($potions['minor']['heal'] ?? 0) === 25);
assert_test("Elixir memulihkan 100 HP", ($potions['full']['heal'] ?? 0) === 100);
assert_test("Perisai streak freeze terdaftar", isset($potions['freeze']));

echo "\n7. Pengujian Sistem Lencana & Pencapaian (game_achievements):\n";
$mockStats = [
    'level' => 5,
    'exp' => 450,
    'gold' => 250,
    'hp' => 100,
    'streak_freeze' => 1,
    'max_streak' => 8,
    'completed_habits' => 10,
    'completed_quests' => 6,
    'total_tasks' => 16
];
$ach = game_achievements($mockStats);
assert_test("Total ada 8 lencana pencapaian", count($ach) === 8);
$unlockedMap = [];
foreach ($ach as $item) {
    $unlockedMap[$item['id']] = $item['unlocked'];
}
assert_test("Lencana 'Langkah Pertama' terbuka untuk 16 tugas", $unlockedMap['first_blood'] === true);
assert_test("Lencana 'Penjaga Api' terbuka untuk streak 8 hari", $unlockedMap['flame_keeper'] === true);
assert_test("Lencana 'Disiplin Baja' terkunci untuk streak 8 hari (target 30)", $unlockedMap['centurion'] === false);
assert_test("Lencana 'Saudagar Emas' terbuka untuk 250 gold", $unlockedMap['treasury_master'] === true);
assert_test("Lencana 'Kebangkitan Karakter' terbuka untuk level 5", $unlockedMap['awakened_hunter'] === true);
assert_test("Lencana 'Perisai Abadi' terbuka untuk 100 HP + 1 perisai", $unlockedMap['aegis_bearer'] === true);
assert_test("Lencana 'Penakluk Misi' terbuka untuk 6 misi", $unlockedMap['quest_master'] === true);
assert_test("Lencana 'Kedaulatan Disiplin' terkunci untuk 16 tugas (target 50)", $unlockedMap['sovereign_champion'] === false);

echo "\n8. Pengujian Jadwal Rutinitas Fleksibel (formatHabitScheduleLabel & isHabitDueToday):\n";
assert_test("Label jadwal daily adalah 'Setiap Hari'", formatHabitScheduleLabel(['schedule_type' => 'daily']) === 'Setiap Hari');
assert_test("Label jadwal interval 2 hari adalah 'Tiap 2 Hari'", formatHabitScheduleLabel(['schedule_type' => 'interval_days', 'schedule_value' => '2']) === 'Tiap 2 Hari');
assert_test("Label jadwal 1 hari sabtu adalah 'Tiap Sabtu'", formatHabitScheduleLabel(['schedule_type' => 'weekly_days', 'schedule_value' => '6']) === 'Tiap Sabtu');
assert_test("Label jadwal senin rabu jumat adalah 'Sen, Rab, Jum'", formatHabitScheduleLabel(['schedule_type' => 'weekly_days', 'schedule_value' => '1,3,5']) === 'Sen, Rab, Jum');
assert_test("Label jadwal interval 3 minggu adalah 'Tiap 3 Minggu'", formatHabitScheduleLabel(['schedule_type' => 'interval_weeks', 'schedule_value' => '3']) === 'Tiap 3 Minggu');
assert_test("Label jadwal interval 1 bulan adalah 'Tiap Bulan'", formatHabitScheduleLabel(['schedule_type' => 'interval_months', 'schedule_value' => '1']) === 'Tiap Bulan');

// Test isHabitDueToday with fixed dates: 2026-10-28 is Wednesday (ISO day 3)
$testDateWed = '2026-10-28';
assert_test("Daily selalu jatuh tempo", isHabitDueToday(['schedule_type' => 'daily'], $testDateWed) === true);
assert_test("Weekly days (1,3,5) jatuh tempo di hari Rabu", isHabitDueToday(['schedule_type' => 'weekly_days', 'schedule_value' => '1,3,5'], $testDateWed) === true);
assert_test("Weekly days (2,6) tidak jatuh tempo di hari Rabu", isHabitDueToday(['schedule_type' => 'weekly_days', 'schedule_value' => '2,6'], $testDateWed) === false);
assert_test("Interval 2 hari jatuh tempo jika belum pernah selesai", isHabitDueToday(['schedule_type' => 'interval_days', 'schedule_value' => '2', 'last_completed_at' => null], $testDateWed) === true);
assert_test("Interval 2 hari tidak jatuh tempo jika baru selesai kemarin", isHabitDueToday(['schedule_type' => 'interval_days', 'schedule_value' => '2', 'last_completed_at' => '2026-10-27 12:00:00'], $testDateWed) === false);
assert_test("Interval 2 hari jatuh tempo jika selesai 2 hari lalu", isHabitDueToday(['schedule_type' => 'interval_days', 'schedule_value' => '2', 'last_completed_at' => '2026-10-26 12:00:00'], $testDateWed) === true);
assert_test("Interval 2 minggu belum jatuh tempo jika selesai 5 hari lalu", isHabitDueToday(['schedule_type' => 'interval_weeks', 'schedule_value' => '2', 'last_completed_at' => '2026-10-23 10:00:00'], $testDateWed) === false);
assert_test("Interval 2 minggu jatuh tempo jika selesai 14 hari lalu", isHabitDueToday(['schedule_type' => 'interval_weeks', 'schedule_value' => '2', 'last_completed_at' => '2026-10-14 10:00:00'], $testDateWed) === true);
assert_test("Interval 1 bulan belum jatuh tempo jika selesai 10 hari lalu", isHabitDueToday(['schedule_type' => 'interval_months', 'schedule_value' => '1', 'last_completed_at' => '2026-10-18 10:00:00'], $testDateWed) === false);
assert_test("Interval 1 bulan jatuh tempo jika selesai 30 hari lalu", isHabitDueToday(['schedule_type' => 'interval_months', 'schedule_value' => '1', 'last_completed_at' => '2026-09-28 10:00:00'], $testDateWed) === true);
assert_test("Habit yang sudah selesai hari ini selalu due", isHabitDueToday(['schedule_type' => 'interval_days', 'schedule_value' => '5', 'completed_today' => 1, 'last_completed_at' => '2026-10-28 10:00:00'], $testDateWed) === true);

echo "\n----------------------------------------\n";
echo "Hasil Pengujian: $passed / $total lulus";
if ($failed > 0) {
    echo " ($failed GAGAL!)\n";
    exit(1);
} else {
    echo " (100% SUKSES)\n";
    exit(0);
}
