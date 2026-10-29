<?php
// config/game.php
// Sentralisasi aturan, konstanta, dan logika keseimbangan (balancing) game Lifevuww.

// --- 1. Kategori / Atribut Inti ---
function game_categories(): array {
    return [
        'Intelligence' => [
            'id' => 2,
            'name' => 'Intelligence',
            'label' => 'Kecerdasan (Intelligence)',
            'icon' => 'fa-brain',
            'color' => 'text-accent'
        ],
        'Physical' => [
            'id' => 3,
            'name' => 'Physical',
            'label' => 'Fisik & Stamina (Physical)',
            'icon' => 'fa-dumbbell',
            'color' => 'text-danger'
        ],
        'Wealth' => [
            'id' => 1,
            'name' => 'Wealth',
            'label' => 'Finansial (Wealth)',
            'icon' => 'fa-coins',
            'color' => 'text-warning'
        ],
        'Health' => [
            'id' => 4,
            'name' => 'Health',
            'label' => 'Kesehatan (Health)',
            'icon' => 'fa-heart',
            'color' => 'text-accent'
        ],
        'Social' => [
            'id' => 5,
            'name' => 'Social',
            'label' => 'Sosial & Relasi (Social)',
            'icon' => 'fa-users',
            'color' => 'text-muted'
        ],
    ];
}

// --- 2. Tingkat Kesulitan & Hadiah ---
function game_difficulties(): array {
    return [
        'Easy' => [
            'key' => 'Easy',
            'label' => 'Mudah (+5 EXP)',
            'exp' => 5,
            'duration_days' => 7,
            'hp_penalty_habit' => 5,
            'hp_penalty_quest' => 10,
        ],
        'Medium' => [
            'key' => 'Medium',
            'label' => 'Sedang (+10 EXP)',
            'exp' => 10,
            'duration_days' => 14,
            'hp_penalty_habit' => 10,
            'hp_penalty_quest' => 20,
        ],
        'Hard' => [
            'key' => 'Hard',
            'label' => 'Sulit (+15 EXP)',
            'exp' => 15,
            'duration_days' => 21,
            'hp_penalty_habit' => 15,
            'hp_penalty_quest' => 30,
        ],
    ];
}

// --- 3. Streak Multiplier ---
function hp_penalty_for_quest(string $difficulty): int {
    $diffs = game_difficulties();
    return $diffs[$difficulty]['hp_penalty_quest'] ?? 10;
}

function hp_penalty_for_habit(string $difficulty): int {
    $diffs = game_difficulties();
    return $diffs[$difficulty]['hp_penalty_habit'] ?? 10;
}

function game_streak_multiplier(int $streak): float {
    if ($streak >= 7) {
        return 1.5;
    }
    if ($streak >= 3) {
        return 1.2;
    }
    return 1.0;
}

// --- 4. Sistem Tingkat (Level) & Gelar (Rank) ---
function game_calculate_level(int $exp): int {
    return (int) floor($exp / 100) + 1;
}

function game_calculate_rank(int $level): string {
    if ($level >= 30) return 'S-Rank Sovereign';
    if ($level >= 22) return 'A-Rank Master';
    if ($level >= 15) return 'B-Rank Elite';
    if ($level >= 10) return 'C-Rank Vanguard';
    if ($level >= 6)  return 'D-Rank Explorer';
    if ($level >= 3)  return 'E-Rank Rookie';
    return 'F-Rank Novice';
}

// --- 5. Item Toko (Consumables) ---
function game_potions(): array {
    return [
        'minor' => [
            'name' => 'Ramuan HP Minor',
            'cost' => 25,
            'heal' => 25,
            'icon' => 'fa-prescription-bottle',
            'desc' => 'Memulihkan +25 HP seketika'
        ],
        'full' => [
            'name' => 'Elixir HP Penuh',
            'cost' => 75,
            'heal' => 100,
            'icon' => 'fa-flask',
            'desc' => 'Memulihkan 100% HP penuh'
        ],
        'freeze' => [
            'name' => 'Perisai Streak Freeze',
            'cost' => 50,
            'icon' => 'fa-snowflake',
            'desc' => 'Melindungi streak selama 1 hari'
        ],
    ];
}

// --- 6. Helper Render Pilihan HTML ---
function render_category_options(?string $selected = null): string {
    $html = '';
    foreach (game_categories() as $key => $info) {
        $isSelected = ($selected === $key) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($key) . '"' . $isSelected . '>' . htmlspecialchars($info['label']) . '</option>';
    }
    return $html;
}

function render_difficulty_options(?string $selected = null): string {
    $html = '';
    foreach (game_difficulties() as $key => $info) {
        $isSelected = ($selected === $key) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($key) . '"' . $isSelected . '>' . htmlspecialchars($info['label']) . '</option>';
    }
    return $html;
}

// --- 7. Sistem Lencana & Prestasi Karakter (Zero-Migration Achievements) ---
function game_achievements(array $stats): array {
    $level = (int) ($stats['level'] ?? 1);
    $gold = (int) ($stats['gold'] ?? 0);
    $hp = (int) ($stats['hp'] ?? 100);
    $freeze = (int) ($stats['streak_freeze'] ?? 0);
    $maxStreak = (int) ($stats['max_streak'] ?? 0);
    $habitsDone = (int) ($stats['completed_habits'] ?? 0);
    $questsDone = (int) ($stats['completed_quests'] ?? 0);
    $totalTasks = (int) ($stats['total_tasks'] ?? ($habitsDone + $questsDone));

    $badges = [
        [
            'id' => 'first_blood',
            'title' => 'Langkah Pertama',
            'desc' => 'Menuntaskan minimal 1 rutinitas atau misi petualangan.',
            'icon' => 'fa-shoe-prints',
            'target' => 1,
            'current' => min(1, $totalTasks),
            'color' => 'text-accent'
        ],
        [
            'id' => 'flame_keeper',
            'title' => 'Penjaga Api',
            'desc' => 'Membangun streak konsistensi 7 hari berturut-turut.',
            'icon' => 'fa-fire',
            'target' => 7,
            'current' => min(7, $maxStreak),
            'color' => 'text-warning'
        ],
        [
            'id' => 'centurion',
            'title' => 'Disiplin Baja',
            'desc' => 'Membangun rekor streak spektakuler 30 hari.',
            'icon' => 'fa-shield-alt',
            'target' => 30,
            'current' => min(30, $maxStreak),
            'color' => 'text-accent'
        ],
        [
            'id' => 'treasury_master',
            'title' => 'Saudagar Emas',
            'desc' => 'Mengumpulkan kekayaan saldo 200 Gold.',
            'icon' => 'fa-coins',
            'target' => 200,
            'current' => min(200, $gold),
            'color' => 'text-warning'
        ],
        [
            'id' => 'awakened_hunter',
            'title' => 'Kebangkitan Karakter',
            'desc' => 'Mencapai Tingkat 5 atau lebih tinggi.',
            'icon' => 'fa-bolt',
            'target' => 5,
            'current' => min(5, $level),
            'color' => 'text-accent'
        ],
        [
            'id' => 'aegis_bearer',
            'title' => 'Perisai Abadi',
            'desc' => 'Memiliki HP penuh (100 HP) dan minimal 1 Perisai Streak Freeze.',
            'icon' => 'fa-snowflake',
            'target' => 1,
            'current' => ($hp >= 100 && $freeze >= 1) ? 1 : 0,
            'color' => 'text-info'
        ],
        [
            'id' => 'quest_master',
            'title' => 'Penakluk Misi',
            'desc' => 'Menyelesaikan minimal 5 misi petualangan.',
            'icon' => 'fa-scroll',
            'target' => 5,
            'current' => min(5, $questsDone),
            'color' => 'text-accent'
        ],
        [
            'id' => 'sovereign_champion',
            'title' => 'Kedaulatan Disiplin',
            'desc' => 'Menuntaskan akumulasi 50 aktivitas produktif seumur hidup.',
            'icon' => 'fa-trophy',
            'target' => 50,
            'current' => min(50, $totalTasks),
            'color' => 'text-warning'
        ]
    ];

    foreach ($badges as &$b) {
        $b['unlocked'] = ($b['current'] >= $b['target']);
        $b['progress_pct'] = (int) min(100, round(($b['current'] / max(1, $b['target'])) * 100));
    }
    unset($b);

    return $badges;
}

// --- 8. Jadwal Fleksibel & Helper Kebiasaan ---
function formatHabitScheduleLabel(array $habit): string {
    $type = $habit['schedule_type'] ?? '';
    $val = trim((string)($habit['schedule_value'] ?? '1'));

    if (empty($type)) {
        $period = strtolower($habit['reset_period'] ?? 'daily');
        return match ($period) {
            'weekly' => 'Tiap Minggu',
            'monthly' => 'Tiap Bulan',
            default => 'Setiap Hari'
        };
    }

    $dayShort = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
    $dayFull = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    switch ($type) {
        case 'daily':
            return 'Setiap Hari';

        case 'interval_days':
            $num = max(1, (int)$val);
            return $num === 1 ? 'Setiap Hari' : 'Tiap ' . $num . ' Hari';

        case 'weekly_days':
            $rawDays = array_filter(array_map('intval', explode(',', $val)));
            sort($rawDays);
            if (empty($rawDays)) return 'Setiap Hari';
            if (count($rawDays) === 7) return 'Setiap Hari';
            if (count($rawDays) === 1) {
                $d = reset($rawDays);
                return 'Tiap ' . ($dayFull[$d] ?? 'Hari');
            }
            $names = array_map(fn($d) => $dayShort[$d] ?? (string)$d, $rawDays);
            return implode(', ', $names);

        case 'interval_weeks':
            $num = max(1, (int)$val);
            return $num === 1 ? 'Tiap Minggu' : 'Tiap ' . $num . ' Minggu';

        case 'interval_months':
            $num = max(1, (int)$val);
            return $num === 1 ? 'Tiap Bulan' : 'Tiap ' . $num . ' Bulan';

        default:
            return 'Setiap Hari';
    }
}

function isHabitDueToday(array $habit, ?string $todayDate = null): bool {
    $today = $todayDate ?: date('Y-m-d');
    $type = $habit['schedule_type'] ?? '';
    $val = trim((string)($habit['schedule_value'] ?? '1'));

    if (empty($type)) {
        $period = strtolower($habit['reset_period'] ?? 'daily');
        if ($period === 'weekly') {
            return (int)date('N', strtotime($today)) === 1; // default senin
        }
        if ($period === 'monthly') {
            return (int)date('j', strtotime($today)) === 1; // tgl 1
        }
        return true;
    }

    switch ($type) {
        case 'daily':
            return true;

        case 'weekly_days':
            $isoDay = (int)date('N', strtotime($today));
            $rawDays = array_filter(array_map('intval', explode(',', $val)));
            return in_array($isoDay, $rawDays, true);

        case 'interval_days':
            if (!empty($habit['completed_today'])) {
                return true;
            }
            if (empty($habit['last_completed_at'])) {
                return true;
            }
            $lastDate = date('Y-m-d', strtotime($habit['last_completed_at']));
            $diffDays = (int) floor((strtotime($today) - strtotime($lastDate)) / 86400);
            $interval = max(1, (int)$val);
            return $diffDays >= $interval;

        case 'interval_weeks':
            if (!empty($habit['completed_today'])) {
                return true;
            }
            if (empty($habit['last_completed_at'])) {
                return true;
            }
            $lastDate = date('Y-m-d', strtotime($habit['last_completed_at']));
            $diffDays = (int) floor((strtotime($today) - strtotime($lastDate)) / 86400);
            $intervalWeeks = max(1, (int)$val);
            return $diffDays >= ($intervalWeeks * 7);

        case 'interval_months':
            if (!empty($habit['completed_today'])) {
                return true;
            }
            if (empty($habit['last_completed_at'])) {
                return true;
            }
            $lastDate = date('Y-m-d', strtotime($habit['last_completed_at']));
            $diffDays = (int) floor((strtotime($today) - strtotime($lastDate)) / 86400);
            $intervalMonths = max(1, (int)$val);
            return $diffDays >= ($intervalMonths * 30);

        default:
            return true;
    }
}

// --- 9. Boss Mastery & Conquered Trophies ---
function getBossMasteryStats(PDO $pdo, int $userId): array {
    try {
        $stmt = $pdo->prepare("
            SELECT q.quest_id, q.quest_name, q.category, q.difficulty, q.subtasks,
                   COALESCE(ql.completed_at, ql.completion_date, q.end_date) AS completed_at
            FROM quests q
            LEFT JOIN quest_logs ql ON q.quest_id = ql.quest_id AND ql.user_id = q.user_id AND ql.status = 'Completed'
            WHERE q.user_id = ? AND q.status = 'Completed'
            ORDER BY completed_at DESC, q.quest_id DESC
        ");
        $stmt->execute([$userId]);
        $completed = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $trophies = [];
        $seen = [];
        foreach ($completed as $q) {
            if (isset($seen[$q['quest_id']])) continue;
            $subs = !empty($q['subtasks']) ? json_decode($q['subtasks'], true) : [];
            $isBoss = ($q['difficulty'] === 'Hard' || (!empty($subs) && count($subs) > 0));
            if ($isBoss) {
                $seen[$q['quest_id']] = true;
                $trophies[] = [
                    'quest_id' => (int)$q['quest_id'],
                    'quest_name' => $q['quest_name'],
                    'category' => $q['category'] ?? 'General',
                    'difficulty' => $q['difficulty'],
                    'completed_at' => $q['completed_at'] ?? date('Y-m-d')
                ];
            }
        }

        $count = count($trophies);
        $bonusPct = min(20, $count * 2);
        $multiplier = 1.0 + ($bonusPct / 100);

        return [
            'count' => $count,
            'bonus_pct' => $bonusPct,
            'multiplier' => $multiplier,
            'trophies' => $trophies
        ];
    } catch (Exception $e) {
        return [
            'count' => 0,
            'bonus_pct' => 0,
            'multiplier' => 1.0,
            'trophies' => []
        ];
    }
}

// --- 10. Relik & Equipment Permanen Toko ---
function game_relics(): array {
    return [
        'chrono_blade' => [
            'key' => 'chrono_blade',
            'name' => 'Obsidian Chrono Blade',
            'cost' => 150,
            'icon' => 'fa-khanda',
            'slot' => 'Weapon',
            'badge' => 'Senjata',
            'effect' => 'Ketajaman fokus eksekusi tugas & disiplin waktu',
            'desc' => 'Pedang obsidian legendaris yang ditempa dari kristal waktu murni.'
        ],
        'archon_signet' => [
            'key' => 'archon_signet',
            'name' => 'Signet of the Archon',
            'cost' => 300,
            'icon' => 'fa-ring',
            'slot' => 'Ring',
            'badge' => 'Cincin',
            'effect' => 'Penguat stabilitas finansial & kejernihan intelektual',
            'desc' => 'Cincin meterai kuno para arsitek disiplin.'
        ],
        'sovereign_mantle' => [
            'key' => 'sovereign_mantle',
            'name' => 'Aegis Sovereign Mantle',
            'cost' => 500,
            'icon' => 'fa-shield-halved',
            'slot' => 'Armor',
            'badge' => 'Armor',
            'effect' => 'Jubah pelindung kasta tertinggi penakluk konsistensi',
            'desc' => 'Mantle pelindung yang memancarkan aura wibawa bagi mereka yang berdisiplin tanpa henti.'
        ],
    ];
}

function getUserRelics(PDO $pdo, int $userId): array {
    try {
        $stmt = $pdo->prepare("SELECT relic_key, acquired_at FROM user_relics WHERE user_id = ?");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $owned = [];
        foreach ($rows as $r) {
            $owned[$r['relic_key']] = $r['acquired_at'];
        }
        return $owned;
    } catch (Exception $e) {
        return [];
    }
}

// --- 11. Spesialisasi Karakter (Specialization Classes) & Aura Themes ---
function game_specializations(): array {
    return [
        'Obsidian Vanguard' => [
            'id' => 'Obsidian Vanguard',
            'title' => 'Obsidian Vanguard',
            'aura' => 'emerald',
            'aura_name' => 'Emerald Core',
            'color' => '#10b981',
            'focus' => 'Disiplin Seimbang (All-Rounder)',
            'desc' => 'Garda terdepan yang teguh menjaga keseimbangan seluruh aspek produktivitas tanpa kompromi.',
            'icon' => 'fa-shield-alt'
        ],
        'Arcane Scholar' => [
            'id' => 'Arcane Scholar',
            'title' => 'Arcane Scholar',
            'aura' => 'cyan',
            'aura_name' => 'Cyan Pulse',
            'color' => '#06b6d4',
            'focus' => 'Fokus Intelligence & Deep Work',
            'desc' => 'Cendekiawan taktis pemecah masalah rumit yang mendedikasikan waktu untuk eksplorasi pengetahuan murni.',
            'icon' => 'fa-brain'
        ],
        'Crimson Striker' => [
            'id' => 'Crimson Striker',
            'title' => 'Crimson Striker',
            'aura' => 'crimson',
            'aura_name' => 'Crimson Blaze',
            'color' => '#f43f5e',
            'focus' => 'Fokus Physical & Health',
            'desc' => 'Petarung gigih berkekuatan fisik prima yang mengubah keringat dan vitalitas menjadi kemenangan disiplin.',
            'icon' => 'fa-dumbbell'
        ],
        'Solar Sovereign' => [
            'id' => 'Solar Sovereign',
            'title' => 'Solar Sovereign',
            'aura' => 'amber',
            'aura_name' => 'Amber Gold',
            'color' => '#f59e0b',
            'focus' => 'Fokus Wealth & Social',
            'desc' => 'Pemimpin visioner penguasa kemakmuran finansial dan jejaring relasi sosial yang berpengaruh luas.',
            'icon' => 'fa-coins'
        ],
        'Abyssal Archon' => [
            'id' => 'Abyssal Archon',
            'title' => 'Abyssal Archon',
            'aura' => 'violet',
            'aura_name' => 'Phantom Violet',
            'color' => '#8b5cf6',
            'focus' => 'Spesialisasi Elit & Eksplorasi Ekstrem',
            'desc' => 'Arsitek misterius penjelajah zona terdalam produktivitas yang mampu menaklukkan Boss tersulit dengan tenang.',
            'icon' => 'fa-wand-magic-sparkles'
        ]
    ];
}

// --- 12. Focus Rift (Pomodoro RPG Deep Work Raid) Configurations ---
function game_focus_rifts(): array {
    return [
        'alpha' => [
            'key' => 'alpha',
            'title' => 'Alpha Rift',
            'badge' => 'Fokus Cepat',
            'color' => '#10b981',
            'duration_minutes' => 15,
            'duration_seconds' => 900,
            'exp_reward' => 10,
            'gold_reward' => 8,
            'desc' => '15 Menit Fokus Cepat untuk sprint tugas kilat.'
        ],
        'beta' => [
            'key' => 'beta',
            'title' => 'Beta Rift',
            'badge' => 'Deep Work',
            'color' => '#06b6d4',
            'duration_minutes' => 25,
            'duration_seconds' => 1500,
            'exp_reward' => 20,
            'gold_reward' => 15,
            'desc' => '25 Menit Deep Work standar Pomodoro taktis.'
        ],
        'crimson' => [
            'key' => 'crimson',
            'title' => 'Crimson Overdrive Rift',
            'badge' => 'Fokus Intensif',
            'color' => '#f43f5e',
            'duration_minutes' => 45,
            'duration_seconds' => 2700,
            'exp_reward' => 40,
            'gold_reward' => 30,
            'desc' => '45 Menit Fokus Intensif. Tetap fokus hingga waktu habis untuk membuka segel hadiah penuh!'
        ],
    ];
}
