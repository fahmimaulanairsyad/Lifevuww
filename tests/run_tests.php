<?php
/**
 * tests/run_tests.php
 * Test runner mandiri berbasis CLI untuk rumus game & logika inti Lifevuww.
 * Tidak membutuhkan dependensi eksternal (PHPUnit/Pest), berjalan di lingkungan PHP apa pun.
 */

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

echo "\n----------------------------------------\n";
echo "Hasil Pengujian: $passed / $total lulus";
if ($failed > 0) {
    echo " ($failed GAGAL!)\n";
    exit(1);
} else {
    echo " (100% SUKSES)\n";
    exit(0);
}
