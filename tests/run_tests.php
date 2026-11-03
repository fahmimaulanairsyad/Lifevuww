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

echo "\n9. Rollover multi-hari & payload jadwal:\n";
$H1 = ['habit_id'=>1,'reset_period'=>'daily','schedule_type'=>'daily','schedule_value'=>'1','last_completed_at'=>null];
$H2 = ['habit_id'=>2,'reset_period'=>'daily','schedule_type'=>'weekly_days','schedule_value'=>'1,3,5','last_completed_at'=>null];
$H3 = ['habit_id'=>3,'reset_period'=>'weekly','schedule_type'=>'daily','schedule_value'=>'1','last_completed_at'=>null];
$H4 = ['habit_id'=>4,'reset_period'=>'monthly','schedule_type'=>'interval_months','schedule_value'=>'1','last_completed_at'=>null];
$H5 = ['habit_id'=>5,'reset_period'=>'daily','schedule_type'=>'interval_days','schedule_value'=>'3','last_completed_at'=>'2026-10-28 09:00:00'];

assert_test("T1 [H1] 10-29 s/d 10-31, tanpa log", game_missed_habits_by_day([$H1], '2026-10-29', '2026-10-31', []) === ['2026-10-29'=>[1], '2026-10-30'=>[1], '2026-10-31'=>[1]]);
assert_test("T2 [H1] 10-29 s/d 10-31, log 1@10-29", game_missed_habits_by_day([$H1], '2026-10-29', '2026-10-31', [['habit_id'=>1, 'date'=>'2026-10-29']]) === ['2026-10-30'=>[1], '2026-10-31'=>[1]]);
assert_test("T3 [H2] 10-26 s/d 11-01, log 2@10-26", game_missed_habits_by_day([$H2], '2026-10-26', '2026-11-01', [['habit_id'=>2, 'date'=>'2026-10-26']]) === ['2026-10-28'=>[2], '2026-10-30'=>[2]]);
assert_test("T3b [H2] 10-26 s/d 11-01, log 2@10-26, 2@10-28, 2@10-30", game_missed_habits_by_day([$H2], '2026-10-26', '2026-11-01', [['habit_id'=>2, 'date'=>'2026-10-26'], ['habit_id'=>2, 'date'=>'2026-10-28'], ['habit_id'=>2, 'date'=>'2026-10-30']]) === []);
assert_test("T4 [H3] 10-26 s/d 11-01, tanpa log", game_missed_habits_by_day([$H3], '2026-10-26', '2026-11-01', []) === ['2026-11-01'=>[3]]);
assert_test("T4b [H3] 10-26 s/d 11-01, log 3@10-28", game_missed_habits_by_day([$H3], '2026-10-26', '2026-11-01', [['habit_id'=>3, 'date'=>'2026-10-28']]) === []);
assert_test("T4c [H3] 10-28 s/d 11-02, log 3@10-27", game_missed_habits_by_day([$H3], '2026-10-28', '2026-11-02', [['habit_id'=>3, 'date'=>'2026-10-27']]) === []);
assert_test("T4d [H3] 10-26 s/d 10-31, tanpa log", game_missed_habits_by_day([$H3], '2026-10-26', '2026-10-31', []) === []);
assert_test("T5 [H4] 10-29 s/d 11-01, tanpa log", game_missed_habits_by_day([$H4], '2026-10-29', '2026-11-01', []) === ['2026-10-31'=>[4]]);
assert_test("T5b [H4] 10-29 s/d 11-01, log 4@10-05", game_missed_habits_by_day([$H4], '2026-10-29', '2026-11-01', [['habit_id'=>4, 'date'=>'2026-10-05']]) === []);
assert_test("T6 [H5] 10-29 s/d 11-01, tanpa log", game_missed_habits_by_day([$H5], '2026-10-29', '2026-11-01', []) === ['2026-10-31'=>[5], '2026-11-01'=>[5]]);
assert_test("T7 [H1] from 11-02, to 11-01 (from > to)", game_missed_habits_by_day([$H1], '2026-11-02', '2026-11-01', []) === []);
assert_test("T8 [H1,H2] 10-26 s/d 10-28, tanpa log", game_missed_habits_by_day([$H1, $H2], '2026-10-26', '2026-10-28', []) === ['2026-10-26'=>[1,2], '2026-10-27'=>[1], '2026-10-28'=>[1,2]]);

assert_test("Payload daily default", parse_habit_schedule_payload(['schedule_type'=>'daily']) === [1,'daily','1','daily']);
assert_test("Payload weekly_days 1,3,5", parse_habit_schedule_payload(['schedule_type'=>'weekly_days','weekly_days_val'=>['1','3','5']]) === [1,'weekly_days','1,3,5','daily']);
assert_test("Payload interval_days 3", parse_habit_schedule_payload(['schedule_type'=>'interval_days','interval_days_val'=>'3']) === [1,'interval_days','3','daily']);
assert_test("Payload interval_weeks 2", parse_habit_schedule_payload(['schedule_type'=>'interval_weeks','interval_weeks_val'=>'2']) === [1,'interval_weeks','2','weekly']);
assert_test("Payload interval_months 1", parse_habit_schedule_payload(['schedule_type'=>'interval_months','interval_months_val'=>'1']) === [1,'interval_months','1','monthly']);

echo "\n10. Time-Blocking & Kalender Taktis:\n";
assert_test("Validasi input jam '09:30' valid", validate_time_block_input('09:30') === '09:30:00');
assert_test("Validasi input jam '09:30:00' valid", validate_time_block_input('09:30:00') === '09:30:00');
assert_test("Validasi input jam kosong menghasilkan null", validate_time_block_input('') === null && validate_time_block_input(null) === null);
assert_test("Validasi input jam 'abc' menghasilkan null", validate_time_block_input('abc') === null);
assert_test("Format rentang jam '09:00:00' dan '10:30:00'", format_time_block('09:00:00', '10:30:00') === '09:00 - 10:30');
assert_test("Format rentang jam parsial/null menghasilkan null", format_time_block('09:00:00', null) === null);
assert_test("Time block aktif pada jam 09:30 (antara 09:00 dan 10:30)", is_time_block_active('09:00:00', '10:30:00', '09:30:00') === true);
assert_test("Time block tidak aktif pada jam 11:00 (antara 09:00 dan 10:30)", is_time_block_active('09:00:00', '10:30:00', '11:00:00') === false);
assert_test("Time block lewat tengah malam (23:00 - 02:00) aktif pada 23:30", is_time_block_active('23:00:00', '02:00:00', '23:30:00') === true);
assert_test("Time block lewat tengah malam (23:00 - 02:00) aktif pada 01:15", is_time_block_active('23:00:00', '02:00:00', '01:15:00') === true);
assert_test("Time block lewat tengah malam (23:00 - 02:00) tidak aktif pada 12:00", is_time_block_active('23:00:00', '02:00:00', '12:00:00') === false);

$overlapItems = [
    ['id' => 1, 'name' => 'Item 1', 'start_time' => '09:00', 'end_time' => '10:30'],
    ['id' => 2, 'name' => 'Item 2', 'start_time' => '10:00', 'end_time' => '11:00'],
    ['id' => 3, 'name' => 'Item 3', 'start_time' => '11:00', 'end_time' => '12:00'],
];
$overlaps = detect_time_block_overlaps($overlapItems);
assert_test("Deteksi tabrakan waktu: Item 1 dan Item 2 bertabrakan", count($overlaps) === 1 && $overlaps[0]['item1']['id'] === 1 && $overlaps[0]['item2']['id'] === 2);

echo "\n11. Efek Pasif Nyata (Real Passive Buffs):\n";
$bVanguard = game_calculate_passive_buffs('Obsidian Vanguard', []);
assert_test("Obsidian Vanguard: +10% EXP & -20% Penalti HP", $bVanguard['exp_multiplier'] === 1.1 && $bVanguard['hp_penalty_multiplier'] === 0.8 && $bVanguard['gold_multiplier'] === 1.0);

$bScholarMatch = game_calculate_passive_buffs('Arcane Scholar', [], 'Intelligence');
assert_test("Arcane Scholar (Intelligence): +20% EXP & +20% Gold", $bScholarMatch['exp_multiplier'] === 1.2 && $bScholarMatch['gold_multiplier'] === 1.2 && $bScholarMatch['hp_penalty_multiplier'] === 1.0);

$bScholarOther = game_calculate_passive_buffs('Arcane Scholar', [], 'Physical');
assert_test("Arcane Scholar (Physical): default 1.0", $bScholarOther['exp_multiplier'] === 1.0 && $bScholarOther['gold_multiplier'] === 1.0);

$bStriker = game_calculate_passive_buffs('Crimson Striker', [], 'Physical');
assert_test("Crimson Striker (Physical): +20% EXP & +20% Gold", $bStriker['exp_multiplier'] === 1.2 && $bStriker['gold_multiplier'] === 1.2);

$bSolar = game_calculate_passive_buffs('Solar Sovereign', [], 'Wealth');
assert_test("Solar Sovereign (Wealth): +20% EXP & +20% Gold", $bSolar['exp_multiplier'] === 1.2 && $bSolar['gold_multiplier'] === 1.2);

$bArchonHard = game_calculate_passive_buffs('Abyssal Archon', [], 'General', 'quest', 'Hard');
assert_test("Abyssal Archon (Hard): +25% EXP", $bArchonHard['exp_multiplier'] === 1.25);

$bArchonCrimson = game_calculate_passive_buffs('Abyssal Archon', [], 'General', 'focus_rift_crimson');
assert_test("Abyssal Archon (Crimson Rift): +25% EXP", $bArchonCrimson['exp_multiplier'] === 1.25);

$bChronoRift = game_calculate_passive_buffs('None', ['chrono_blade'], 'General', 'focus_rift_beta');
assert_test("Chrono Blade (Focus Rift): +20% EXP & +20% Gold", $bChronoRift['exp_multiplier'] === 1.2 && $bChronoRift['gold_multiplier'] === 1.2);

$bChronoHabit = game_calculate_passive_buffs('None', ['chrono_blade'], 'General', 'habit');
assert_test("Chrono Blade (Habit): no bonus", $bChronoHabit['exp_multiplier'] === 1.0 && $bChronoHabit['gold_multiplier'] === 1.0);

$bSignet = game_calculate_passive_buffs('None', ['archon_signet']);
assert_test("Archon Signet: +25% Gold", $bSignet['gold_multiplier'] === 1.25);

$bMantle = game_calculate_passive_buffs('None', ['sovereign_mantle']);
assert_test("Sovereign Mantle: -30% Penalti HP (0.7) & +25 Max HP", $bMantle['hp_penalty_multiplier'] === 0.7 && $bMantle['bonus_max_hp'] === 25);

$bCombo = game_calculate_passive_buffs('Obsidian Vanguard', ['sovereign_mantle', 'archon_signet']);
assert_test("Combo Vanguard + Mantle + Signet: 1.1 EXP, 1.25 Gold, 0.5 HP Penalti, +25 Max HP", $bCombo['exp_multiplier'] === 1.1 && $bCombo['gold_multiplier'] === 1.25 && $bCombo['hp_penalty_multiplier'] === 0.5 && $bCombo['bonus_max_hp'] === 25);

echo "\n10. Astral Companions (game_companions & game_companion_level):\n";
$comps = game_companions();
assert_test("Terdapat 5 Familiar orisinal", count($comps) === 5);
assert_test("Kunci katalog lengkap", isset($comps['obsidian_wolf'], $comps['chrono_owl'], $comps['crimson_drake'], $comps['auric_griffin'], $comps['aegis_tortoise']));
assert_test("Setiap familiar punya nama, ikon, aksen, dan bonus", count(array_filter($comps, fn($c) => isset($c['name'], $c['icon'], $c['accent'], $c['bonus']))) === 5);
assert_test("Shadow Fang Wolf memberi +5 flat DMG", ($comps['obsidian_wolf']['dmg_bonus'] ?? 0) === 5);
assert_test("Crimson Ember Drake multiplier DMG 15%", ($comps['crimson_drake']['dmg_multiplier'] ?? 0) === 0.15);
assert_test("Auric Griffin multiplier Gold 15%", ($comps['auric_griffin']['gold_multiplier'] ?? 0) === 0.15);
assert_test("Aegis Tortoise pengurangan penalti 15%", ($comps['aegis_tortoise']['penalty_reduction'] ?? 0) === 0.15);
assert_test("Chrono Owl bonus EXP timed 12%", ($comps['chrono_owl']['exp_bonus_timed'] ?? 0) === 0.12);

$lv0 = game_companion_level(0);
assert_test("EXP 0 = Bond Lv.1 Hatchling", $lv0['bond_level'] === 1 && $lv0['stage'] === 'Hatchling');
assert_test("Bonus skala Lv.1 = 1.0", $lv0['bonus_scale'] === 1.0);
assert_test("EXP 80 = Bond Lv.2", game_companion_level(80)['bond_level'] === 2);
assert_test("EXP 240 = Lv.4 Awakened (skala 1.15)", game_companion_level(240)['bond_level'] === 4 && game_companion_level(240)['stage'] === 'Awakened' && game_companion_level(240)['bonus_scale'] === 1.15);
assert_test("EXP 560 = Lv.8 Sovereign (skala 1.35)", game_companion_level(560)['bond_level'] === 8 && game_companion_level(560)['stage'] === 'Sovereign' && game_companion_level(560)['bonus_scale'] === 1.35);
assert_test("EXP 800 = Lv.10 maksimum", game_companion_level(800)['bond_level'] === 10);
assert_test("EXP 10000 di-cap di Lv.10", game_companion_level(10000)['bond_level'] === 10);
assert_test("Progres dalam level benar (83 EXP = 3/80)", game_companion_level(83)['exp_into_level'] === 3);

echo "\n11. Pohon Bakat (game_skill_tree & game_available_skill_points):\n";
$tree = game_skill_tree();
assert_test("3 cabang: Discipline, Focus, Fortune", array_keys($tree['branches']) === ['discipline', 'focus', 'fortune']);
$totalNodes = 0; $costSets = [];
foreach ($tree['branches'] as $bk => $br) {
    $totalNodes += count($br['nodes']);
    $costSets[$bk] = array_map(fn($n) => (int) $n['cost'], $br['nodes']);
}
assert_test("Total 9 node pasif", $totalNodes === 9);
assert_test("Biaya tier per cabang 1, 2, 3", $costSets['discipline'] === [1, 2, 3] && $costSets['focus'] === [1, 2, 3] && $costSets['fortune'] === [1, 2, 3]);
assert_test("Terdapat 3 Active Skill", count($tree['actives']) === 3);
assert_test("Active skills terkenal", isset($tree['actives']['overdrive_surge'], $tree['actives']['vital_transmutation'], $tree['actives']['tactical_inspiration']));
assert_test("Semua active skill berbiaya 2 SP", count(array_filter($tree['actives'], fn($a) => (int) $a['cost'] === 2)) === 3);
assert_test("SP level 1 = 0", game_available_skill_points(1, 0) === 0);
assert_test("SP level 5 dengan 2 terpakai = 2", game_available_skill_points(5, 2) === 2);
assert_test("SP level 10 tanpa belanja = 9", game_available_skill_points(10, 0) === 9);
assert_test("SP tidak pernah negatif", game_available_skill_points(1, 5) === 0);
assert_test("SP terpakai negatif diabaikan", game_available_skill_points(5, -3) === 7);

echo "\n12. Dungeon Raid (game_dungeon_bosses & kalkulasi damage):\n";
$bosses = game_dungeon_bosses();
assert_test("Terdapat 5 Boss Gate", count($bosses) === 5);
assert_test("Urutan Gate E,C,B,A,S", array_keys($bosses) === ['gate_e_crawler', 'gate_c_lich', 'gate_b_colossus', 'gate_a_valkyrie', 'gate_s_monarch']);
assert_test("HP boss 300/600/1000/1500/2500", array_values(array_map(fn($b) => (int) $b['hp'], $bosses)) === [300, 600, 1000, 1500, 2500]);
assert_test("Reward EXP 50/100/160/250/400", array_values(array_map(fn($b) => (int) $b['reward_exp'], $bosses)) === [50, 100, 160, 250, 400]);
assert_test("Reward Gold 40/80/130/200/350", array_values(array_map(fn($b) => (int) $b['reward_gold'], $bosses)) === [40, 80, 130, 200, 350]);
assert_test("Streak Freeze reward 0/0/1/1/2", array_values(array_map(fn($b) => (int) $b['reward_freeze'], $bosses)) === [0, 0, 1, 1, 2]);
$pk = game_dungeon_pick_boss_key('2026-W45');
assert_test("Rotasi boss menghasilkan kunci valid", isset($bosses[$pk]));
assert_test("Rotasi boss stabil untuk week_key sama", game_dungeon_pick_boss_key('2026-W45') === $pk && game_dungeon_pick_boss_key('2026-W45') === $pk);
assert_test("Wolf Lv.1: 100 base + 5 flat = 105", game_dungeon_effective_damage(100, 'obsidian_wolf', 0) === 105);
assert_test("Wolf Lv.10: 100 x 1.45 + 5 = 150", game_dungeon_effective_damage(100, 'obsidian_wolf', 800) === 150);
assert_test("Drake Lv.1: 100 x 1.15 = 115", game_dungeon_effective_damage(100, 'crimson_drake', 0) === 115);
assert_test("Tortoise Lv.6: 100 x skala 1.25 = 125", game_dungeon_effective_damage(100, 'aegis_tortoise', 400) === 125);
assert_test("Damage minimum dibatasi 1", game_dungeon_effective_damage(0, 'aegis_tortoise', 400) === 1);

echo "\n13. Time Block (validate_time_block_input):\n";
assert_test("String kosong -> null (Sepanjang Hari)", validate_time_block_input('') === null);
assert_test("null -> null", validate_time_block_input(null) === null);
assert_test("HH:MM valid dinormalisasi ke HH:MM:SS", validate_time_block_input('09:30') === '09:30:00');
assert_test("HH:MM:SS valid diterima apa adanya", validate_time_block_input('09:30:15') === '09:30:15');
assert_test("Format tidak valid ditolak", validate_time_block_input('9:5') === null);
assert_test("Nilai di luar jam ditolak", validate_time_block_input('25:00') === null);

echo "\n----------------------------------------\n";
echo "Hasil Pengujian: $passed / $total lulus";
if ($failed > 0) {
    echo " ($failed GAGAL!)\n";
    exit(1);
} else {
    echo " (100% SUKSES)\n";
    exit(0);
}
