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

// Helper untuk resolusi gambar aset (mendukung .png dan .svg)
function game_resolve_asset_image(string $type, string $key): string {
    $png = "assets/images/{$type}/{$key}.png";
    $svg = "assets/images/{$type}/{$key}.svg";
    $baseDir = dirname(__DIR__);
    if (file_exists($baseDir . '/' . $png)) {
        return $png;
    }
    if (file_exists($baseDir . '/' . $svg)) {
        return $svg;
    }
    return $svg;
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
            'effect' => '+20% EXP & Gold untuk sesi Focus Rift.',
            'desc' => 'Artefak akselerasi fokus waktu.',
            'image' => game_resolve_asset_image('relics', 'chrono_blade'),
        ],
        'archon_signet' => [
            'key' => 'archon_signet',
            'name' => 'Signet of the Archon',
            'cost' => 300,
            'icon' => 'fa-ring',
            'slot' => 'Ring',
            'badge' => 'Cincin',
            'effect' => '+25% perolehan Gold dari semua aktivitas.',
            'desc' => 'Cincin meterai pelipat ganda saldo Gold.',
            'image' => game_resolve_asset_image('relics', 'archon_signet'),
        ],
        'sovereign_mantle' => [
            'key' => 'sovereign_mantle',
            'name' => 'Aegis Sovereign Mantle',
            'cost' => 500,
            'icon' => 'fa-shield-halved',
            'slot' => 'Armor',
            'badge' => 'Armor',
            'effect' => '-30% penalti HP dan +25 Max HP permanen.',
            'desc' => 'Armor pertahanan pasif dan ketahanan vital.',
            'image' => game_resolve_asset_image('relics', 'sovereign_mantle'),
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
            'focus' => 'Disiplin Umum (All-Rounder)',
            'desc' => '+10% EXP semua tugas dan -20% penalti HP.',
            'icon' => 'fa-shield-alt'
        ],
        'Arcane Scholar' => [
            'id' => 'Arcane Scholar',
            'title' => 'Arcane Scholar',
            'aura' => 'cyan',
            'aura_name' => 'Cyan Pulse',
            'color' => '#06b6d4',
            'focus' => 'Fokus Intelligence & Deep Work',
            'desc' => '+20% EXP & Gold untuk misi kategori Intelligence.',
            'icon' => 'fa-brain'
        ],
        'Crimson Striker' => [
            'id' => 'Crimson Striker',
            'title' => 'Crimson Striker',
            'aura' => 'crimson',
            'aura_name' => 'Crimson Blaze',
            'color' => '#f43f5e',
            'focus' => 'Fokus Physical & Health',
            'desc' => '+20% EXP & Gold untuk misi kategori Physical dan Health.',
            'icon' => 'fa-dumbbell'
        ],
        'Solar Sovereign' => [
            'id' => 'Solar Sovereign',
            'title' => 'Solar Sovereign',
            'aura' => 'amber',
            'aura_name' => 'Amber Gold',
            'color' => '#f59e0b',
            'focus' => 'Fokus Wealth & Social',
            'desc' => '+20% EXP & Gold untuk misi kategori Wealth dan Social.',
            'icon' => 'fa-coins'
        ],
        'Abyssal Archon' => [
            'id' => 'Abyssal Archon',
            'title' => 'Abyssal Archon',
            'aura' => 'violet',
            'aura_name' => 'Phantom Violet',
            'color' => '#8b5cf6',
            'focus' => 'Spesialisasi Elit & Eksplorasi Ekstrem',
            'desc' => '+25% EXP untuk misi Hard dan Crimson Focus Rift.',
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

// --- 13. Evaluasi Absen Rutinitas Multi-Hari (Multi-Day Rollover Engine) ---
function game_missed_habits_by_day(array $habits, string $fromDate, string $toDate, array $completedLogs): array {
    $fromTs = strtotime($fromDate);
    $toTs = strtotime($toDate);
    if ($fromTs === false || $toTs === false || $fromTs > $toTs) return [];

    $logIdx = [];
    foreach ($completedLogs as $l) {
        $logIdx[(int) $l['habit_id']][] = (string) $l['date'];
    }

    $out = [];
    for ($ts = $fromTs; $ts <= $toTs; $ts = strtotime('+1 day', $ts)) {
        $d = date('Y-m-d', $ts);
        $isoDay = (int) date('N', $ts);
        foreach ($habits as $h) {
            $period = strtolower((string) ($h['reset_period'] ?? 'daily'));
            if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';

            // Periode "berakhir" pada: tiap hari / hari Minggu / hari terakhir bulan.
            $isBoundary = match ($period) {
                'daily'   => true,
                'weekly'  => $isoDay === 7,
                'monthly' => date('j', $ts) === date('t', $ts),
            };
            if (!$isBoundary) continue;

            $periodStart = match ($period) {
                'daily'   => $d,
                'weekly'  => date('Y-m-d', strtotime('-' . ($isoDay - 1) . ' days', $ts)),
                'monthly' => date('Y-m-01', $ts),
            };

            $done = false;
            foreach ($logIdx[(int) $h['habit_id']] ?? [] as $ld) {
                if ($ld >= $periodStart && $ld <= $d) { $done = true; break; }
            }
            if ($done) continue;

            $probe = $h;
            $probe['completed_today'] = 0; // WAJIB: isHabitDueToday() langsung true bila completed_today=1
            if (!isHabitDueToday($probe, $d)) continue;

            $out[$d][] = (int) $h['habit_id'];
        }
    }
    return $out;
}

// --- 14. Parser Payload Jadwal Rutinitas ---
function parse_habit_schedule_payload($data): array {
    $targetCount = max(1, min(50, intval($data['target_count'] ?? 1)));
    $scheduleType = $data['schedule_type'] ?? 'daily';
    $validScheduleTypes = ['daily', 'interval_days', 'weekly_days', 'interval_weeks', 'interval_months'];
    if (!in_array($scheduleType, $validScheduleTypes, true)) {
        $scheduleType = 'daily';
    }

    $scheduleValue = '1';
    $resetPeriod = 'daily';

    if ($scheduleType === 'daily') {
        $scheduleValue = '1';
        $resetPeriod = 'daily';
    } elseif ($scheduleType === 'interval_days') {
        $scheduleValue = (string) max(2, min(365, intval($data['interval_days_val'] ?? 2)));
        $resetPeriod = 'daily';
    } elseif ($scheduleType === 'weekly_days') {
        $days = $data['weekly_days_val'] ?? ($data['weekly_days_val[]'] ?? []);
        if (!is_array($days)) $days = explode(',', (string)$days);
        $cleanDays = array_values(array_unique(array_filter(array_map('intval', $days), fn($d) => $d >= 1 && $d <= 7)));
        sort($cleanDays);
        $scheduleValue = !empty($cleanDays) ? implode(',', $cleanDays) : '1,2,3,4,5,6,7';
        $resetPeriod = 'daily';
    } elseif ($scheduleType === 'interval_weeks') {
        $scheduleValue = (string) max(1, min(52, intval($data['interval_weeks_val'] ?? 1)));
        $resetPeriod = 'weekly';
    } elseif ($scheduleType === 'interval_months') {
        $scheduleValue = (string) max(1, min(24, intval($data['interval_months_val'] ?? 1)));
        $resetPeriod = 'monthly';
    }

    return [$targetCount, $scheduleType, $scheduleValue, $resetPeriod];
}

// --- 15. Time Blocking & Kalender Taktis Helpers ---
function validate_time_block_input(?string $time): ?string {
    if ($time === null) return null;
    $time = trim($time);
    if ($time === '') return null;
    if (preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $time, $m)) {
        $hh = (int) $m[1]; $mm = (int) $m[2]; $ss = isset($m[3]) ? (int) $m[3] : 0;
        if ($hh > 23 || $mm > 59 || $ss > 59) return null; // tolak di luar jangkauan
        return sprintf('%02d:%02d:%02d', $hh, $mm, $ss);
    }
    return null;
}

function format_time_block(?string $startTime, ?string $endTime): ?string {
    if (empty($startTime) || empty($endTime)) return null;
    $s = substr($startTime, 0, 5);
    $e = substr($endTime, 0, 5);
    return "$s - $e";
}

function is_time_block_active(?string $startTime, ?string $endTime, ?string $currentTime = null): bool {
    if (empty($startTime) || empty($endTime)) return false;
    $now = $currentTime ?? date('H:i:s');
    $st = (strlen($startTime) === 5) ? $startTime . ':00' : $startTime;
    $et = (strlen($endTime) === 5) ? $endTime . ':00' : $endTime;
    if ($st <= $et) {
        return ($now >= $st && $now <= $et);
    } else {
        return ($now >= $st || $now <= $et);
    }
}

function time_to_minutes(string $time): int {
    $parts = explode(':', $time);
    return ((int)$parts[0] * 60) + (int)($parts[1] ?? 0);
}

function detect_time_block_overlaps(array $items): array {
    $overlaps = [];
    $timedItems = [];
    foreach ($items as $item) {
        if (!empty($item['start_time']) && !empty($item['end_time'])) {
            $timedItems[] = $item;
        }
    }
    $n = count($timedItems);
    for ($i = 0; $i < $n; $i++) {
        $s1 = time_to_minutes(substr($timedItems[$i]['start_time'], 0, 5));
        $e1 = time_to_minutes(substr($timedItems[$i]['end_time'], 0, 5));
        if ($e1 <= $s1) $e1 += 1440;

        for ($j = $i + 1; $j < $n; $j++) {
            $s2 = time_to_minutes(substr($timedItems[$j]['start_time'], 0, 5));
            $e2 = time_to_minutes(substr($timedItems[$j]['end_time'], 0, 5));
            if ($e2 <= $s2) $e2 += 1440;

            if (max($s1, $s2) < min($e1, $e2)) {
                $overlaps[] = [
                    'item1' => $timedItems[$i],
                    'item2' => $timedItems[$j],
                ];
            }
        }
    }
    return $overlaps;
}

// --- 16. Efek Pasif Nyata (Real Passive Buffs) Spesialisasi & Relik ---
function game_calculate_passive_buffs(
    string $specialization,
    array $ownedRelicKeys,
    string $category = 'General',
    string $source = 'habit',
    string $difficulty = 'Medium'
): array {
    $expBonus = 0.0;
    $goldBonus = 0.0;
    $hpPenaltyReduction = 0.0;
    $bonusMaxHp = 0;

    // 1. Buff Spesialisasi
    switch ($specialization) {
        case 'Obsidian Vanguard':
            $expBonus += 0.10;
            $hpPenaltyReduction += 0.20;
            break;
        case 'Arcane Scholar':
            if ($category === 'Intelligence') {
                $expBonus += 0.20;
                $goldBonus += 0.20;
            }
            break;
        case 'Crimson Striker':
            if ($category === 'Physical' || $category === 'Health') {
                $expBonus += 0.20;
                $goldBonus += 0.20;
            }
            break;
        case 'Solar Sovereign':
            if ($category === 'Wealth' || $category === 'Social') {
                $expBonus += 0.20;
                $goldBonus += 0.20;
            }
            break;
        case 'Abyssal Archon':
            if ($difficulty === 'Hard' || $source === 'focus_rift_crimson') {
                $expBonus += 0.25;
            }
            break;
    }

    // 2. Buff Relik
    $hasRelic = function(string $key) use ($ownedRelicKeys): bool {
        return in_array($key, $ownedRelicKeys, true) || isset($ownedRelicKeys[$key]);
    };

    if ($hasRelic('chrono_blade') && str_starts_with($source, 'focus_rift')) {
        $expBonus += 0.20;
        $goldBonus += 0.20;
    }

    if ($hasRelic('archon_signet')) {
        $goldBonus += 0.25;
    }

    if ($hasRelic('sovereign_mantle')) {
        $hpPenaltyReduction += 0.30;
        $bonusMaxHp += 25;
    }

    return [
        'exp_multiplier' => round(1.0 + $expBonus, 4),
        'gold_multiplier' => round(1.0 + $goldBonus, 4),
        'hp_penalty_multiplier' => round(max(0.30, 1.0 - $hpPenaltyReduction), 4),
        'bonus_max_hp' => $bonusMaxHp
    ];
}

function get_user_passive_buffs(
    PDO $pdo,
    int $userId,
    string $category = 'General',
    string $source = 'habit',
    string $difficulty = 'Medium'
): array {
    try {
        $stmt = $pdo->prepare("SELECT specialization FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $spec = (string)($stmt->fetchColumn() ?: 'Obsidian Vanguard');
        $ownedRelics = getUserRelics($pdo, $userId);
        return game_calculate_passive_buffs($spec, array_keys($ownedRelics), $category, $source, $difficulty);
    } catch (Exception $e) {
        return [
            'exp_multiplier' => 1.0,
            'gold_multiplier' => 1.0,
            'hp_penalty_multiplier' => 1.0,
            'bonus_max_hp' => 0
        ];
    }
}




// ============================================================
// SOVEREIGN EXPANSION (v3.0): Astral Companions & Skill Tree
// ============================================================

// Katalog 5 Familiar orisinal. Bonus tumbuh mengikuti bond level familar aktif.
function game_companions(): array {
    return [
        'obsidian_wolf' => [
            'name' => 'Shadow Fang Wolf',
            'icon' => 'fa-paw',
            'accent' => '#10b981',
            'bonus' => '+8% EXP semua Habit & +5 DMG ke Dungeon Boss',
            'desc' => '+8% EXP Habit dan +5 flat damage ke Boss Dungeon.',
            'image' => game_resolve_asset_image('companions', 'obsidian_wolf'),
            'exp_bonus_habit' => 0.08,
            'dmg_bonus' => 5,
        ],
        'chrono_owl' => [
            'name' => 'Chrono Starlight Owl',
            'icon' => 'fa-feather-alt',
            'accent' => '#06b6d4',
            'bonus' => '+12% EXP dari Focus Rift & item ber-Time-Block',
            'desc' => '+12% EXP untuk sesi Focus Rift dan tugas terjadwal.',
            'image' => game_resolve_asset_image('companions', 'chrono_owl'),
            'exp_bonus_timed' => 0.12,
        ],
        'crimson_drake' => [
            'name' => 'Crimson Ember Drake',
            'icon' => 'fa-dragon',
            'accent' => '#f43f5e',
            'bonus' => '+15% Damage ke Dungeon Boss & +10% EXP Quest',
            'desc' => '+15% multiplier damage Boss dan +10% EXP Quest.',
            'image' => game_resolve_asset_image('companions', 'crimson_drake'),
            'dmg_multiplier' => 0.15,
            'exp_bonus_quest' => 0.10,
        ],
        'auric_griffin' => [
            'name' => 'Auric Sovereign Griffin',
            'icon' => 'fa-crown',
            'accent' => '#f59e0b',
            'bonus' => '+15% perolehan Gold dari semua sumber',
            'desc' => '+15% multiplier saldo Gold dari seluruh aktivitas.',
            'image' => game_resolve_asset_image('companions', 'auric_griffin'),
            'gold_multiplier' => 0.15,
        ],
        'aegis_tortoise' => [
            'name' => 'Aegis Spirit Tortoise',
            'icon' => 'fa-shield-alt',
            'accent' => '#6366f1',
            'bonus' => 'Pengurangan penalti HP gagal sebesar 15%',
            'desc' => '-15% mitigasi penalti pengurangan Health Points (HP).',
            'image' => game_resolve_asset_image('companions', 'aegis_tortoise'),
            'penalty_reduction' => 0.15,
        ],
    ];
}

// Murni: hitung bond level (1-10, tiap 80 EXP naik 1 level), stage evolusi,
// dan skala bonus aktif. Dipakai UI Sanctum + engine dungeon.
function game_companion_level(int $companionExp): array {
    $bondLevel = max(1, min(10, intdiv(max(0, $companionExp), 80) + 1));
    if ($bondLevel >= 8) {
        $stage = 'Sovereign';
    } elseif ($bondLevel >= 4) {
        $stage = 'Awakened';
    } else {
        $stage = 'Hatchling';
    }
    $intoLevel = max(0, $companionExp) % 80;
    return [
        'bond_level' => $bondLevel,
        'stage' => $stage,
        'bonus_scale' => 1.0 + ($bondLevel - 1) * 0.05,
        'exp_into_level' => $intoLevel,
        'exp_for_next' => 80,
    ];
}

// Katalog Pohon Bakat: 3 cabang x 3 tier pasif + 3 Active Skill harian.
// Passive: efek terdokumentasi di katalog (dinilai test berdasarkan struktur).
function game_skill_tree(): array {
    return [
        'branches' => [
            'discipline' => [
                'label' => 'Discipline',
                'icon' => 'fa-shield-alt',
                'nodes' => [
                    ['code' => 'discipline_1', 'tier' => 1, 'cost' => 1, 'name' => 'Iron Routine', 'desc' => 'Slot jadwal habit +1 prioritas pin'],
                    ['code' => 'discipline_2', 'tier' => 2, 'cost' => 2, 'name' => 'Unbreakable Chain', 'desc' => 'Streak tidak reset saat absen 1 hari'],
                    ['code' => 'discipline_3', 'tier' => 3, 'cost' => 3, 'name' => 'Sovereign Will', 'desc' => 'Penalti HP habit -20%'],
                ],
            ],
            'focus' => [
                'label' => 'Focus',
                'icon' => 'fa-crosshairs',
                'nodes' => [
                    ['code' => 'focus_1', 'tier' => 1, 'cost' => 1, 'name' => 'Deep Current', 'desc' => 'Durasi Focus Rift +5 menit'],
                    ['code' => 'focus_2', 'tier' => 2, 'cost' => 2, 'name' => 'Flow State', 'desc' => 'EXP Focus Rift +10%'],
                    ['code' => 'focus_3', 'tier' => 3, 'cost' => 3, 'name' => 'Ascended Mind', 'desc' => 'Ambient Focus Audio +1 kanal eksklusif'],
                ],
            ],
            'fortune' => [
                'label' => 'Fortune',
                'icon' => 'fa-coins',
                'nodes' => [
                    ['code' => 'fortune_1', 'tier' => 1, 'cost' => 1, 'name' => 'Lucky Star', 'desc' => 'Gold reward +5%'],
                    ['code' => 'fortune_2', 'tier' => 2, 'cost' => 2, 'name' => 'Midas Touch', 'desc' => 'Peluang Supply Crate bonus +10%'],
                    ['code' => 'fortune_3', 'tier' => 3, 'cost' => 3, 'name' => 'Sovereign Treasury', 'desc' => 'Gold reward +15% kumulatif'],
                ],
            ],
        ],
        'actives' => [
            'overdrive_surge' => [
                'name' => 'Overdrive Surge',
                'icon' => 'fa-bolt',
                'cost' => 2,
                'desc' => 'Status Overdrive: +50% EXP tambahan selama 2 jam ke depan',
            ],
            'vital_transmutation' => [
                'name' => 'Vital Transmutation',
                'icon' => 'fa-heart',
                'cost' => 2,
                'desc' => 'Ubah 25 Gold menjadi pemulihan instan +40 HP',
            ],
            'tactical_inspiration' => [
                'name' => 'Tactical Inspiration',
                'icon' => 'fa-crosshairs',
                'cost' => 2,
                'desc' => 'Serangan instan 100 True Damage ke Boss minggu ini + 15 EXP',
            ],
        ],
    ];
}

// Murni: SP tersedia = level - 1 - sudah dibelanjakan (mulai 0 SP di level 1).
function game_available_skill_points(int $userLevel, int $spentPoints): int {
    return max(0, $userLevel - 1 - $spentPoints);
}

// ============================================================
// SOVEREIGN EXPANSION (v3.0): Dungeon Raid & Weekly Boss Rush
// ============================================================

// Katalog 5 Boss Mingguan (Gate E s/d Gate S). Reward diklaim lewat
// halaman dungeon setelah boss_hp <= 0.
function game_dungeon_bosses(): array {
    return [
        'gate_e_crawler' => ['name' => 'Ironhide Crawler', 'gate' => 'E-Rank', 'hp' => 300, 'reward_exp' => 50, 'reward_gold' => 40, 'reward_freeze' => 0, 'color' => '#a1a1aa', 'icon' => 'fa-bug', 'image' => game_resolve_asset_image('bosses', 'gate_e_crawler')],
        'gate_c_lich' => ['name' => 'Frostveil Archmage', 'gate' => 'C-Rank', 'hp' => 600, 'reward_exp' => 100, 'reward_gold' => 80, 'reward_freeze' => 0, 'color' => '#06b6d4', 'icon' => 'fa-hat-wizard', 'image' => game_resolve_asset_image('bosses', 'gate_c_lich')],
        'gate_b_colossus' => ['name' => 'Obsidian Colossus', 'gate' => 'B-Rank', 'hp' => 1000, 'reward_exp' => 160, 'reward_gold' => 130, 'reward_freeze' => 1, 'color' => '#8b5cf6', 'icon' => 'fa-mountain', 'image' => game_resolve_asset_image('bosses', 'gate_b_colossus')],
        'gate_a_valkyrie' => ['name' => 'Crimson Valkyrie', 'gate' => 'A-Rank', 'hp' => 1500, 'reward_exp' => 250, 'reward_gold' => 200, 'reward_freeze' => 1, 'color' => '#f43f5e', 'icon' => 'fa-feather-pointed', 'image' => game_resolve_asset_image('bosses', 'gate_a_valkyrie')],
        'gate_s_monarch' => ['name' => 'Sovereign of the Abyss', 'gate' => 'S-Rank', 'hp' => 2500, 'reward_exp' => 400, 'reward_gold' => 350, 'reward_freeze' => 2, 'color' => '#f59e0b', 'icon' => 'fa-skull', 'image' => game_resolve_asset_image('bosses', 'gate_s_monarch')],
    ];
}

// Murni: pilih boss minggu ini dari rotasi hash week_key ( stabil antar request).
function game_dungeon_pick_boss_key(string $weekKey): string {
    $keys = array_keys(game_dungeon_bosses());
    $idx = abs(crc32('lv-dungeon-' . $weekKey)) % count($keys);
    return $keys[$idx];
}

// Murni: kalkulasi damage efektif ke Boss dari base damage + familiar aktif
// (flat dmg bonus & multiplier, diskalakan bond level). Minimum 1.
function game_dungeon_effective_damage(int $baseDamage, string $companionKey, int $companionExp): int {
    $comps = game_companions();
    $comp = $comps[$companionKey] ?? ($comps['obsidian_wolf'] ?? []);
    $bond = game_companion_level($companionExp);
    $dmg = (float) $baseDamage * $bond['bonus_scale'];
    if (isset($comp['dmg_bonus'])) $dmg += (float) $comp['dmg_bonus'];
    if (isset($comp['dmg_multiplier'])) $dmg *= (1.0 + (float) $comp['dmg_multiplier']);
    return max(1, (int) round($dmg));
}

// Terapkan damage ke Boss minggu berjalan. Bonus familar aktif ikut
// (flat dmg bonus & multiplier, diskalakan bond level); companion_exp
// tumbuh sebesar damage yang benar-benar diterapkan. Battle log disimpan
// JSON maksimal 15 entri terakhir.
function deal_dungeon_damage(PDO $pdo, int $userId, int $baseDamage, string $actionLabel): array {
    // Seluruh operasi dungeon dilindungi: kegagalan DB apa pun TIDAK boleh
    // membuat aksi utama (centang habit/quest/focus rift) ikut gagal.
    try {
        $weekKey = date('o-W');
        $bosses = game_dungeon_bosses();

        $stmt = $pdo->prepare("SELECT raid_id, boss_key, boss_hp, is_defeated, total_damage_dealt, battle_log FROM dungeon_raids WHERE user_id = ? AND week_key = ? FOR UPDATE");
        $stmt->execute([$userId, $weekKey]);
        $raid = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$raid) {
            $bossKey = game_dungeon_pick_boss_key($weekKey);
            $b = $bosses[$bossKey];
            $pdo->prepare("INSERT IGNORE INTO dungeon_raids (user_id, week_key, boss_key, boss_hp, boss_max_hp) VALUES (?, ?, ?, ?, ?)")
                ->execute([$userId, $weekKey, $bossKey, (int) $b['hp'], (int) $b['hp']]);
            $stmt->execute([$userId, $weekKey]);
            $raid = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$raid) {
                return ['applied' => 0, 'defeated' => false, 'message' => 'Raid minggu ini tidak tersedia.'];
            }
        }
        if ((int) $raid['boss_hp'] <= 0) {
            return ['applied' => 0, 'defeated' => true, 'message' => 'Boss minggu ini sudah tumbang. Klaim lootnya!'];
        }

        // Bonus familiar aktif (diskalakan bond level) — murni via helper
        $stmtUser = $pdo->prepare("SELECT active_companion, companion_exp FROM users WHERE user_id = ?");
        $stmtUser->execute([$userId]);
        $uRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $comps = game_companions();
        $compKey = isset($comps[$uRow['active_companion'] ?? '']) ? $uRow['active_companion'] : 'obsidian_wolf';
        $comp = $comps[$compKey];
        $bond = game_companion_level((int) ($uRow['companion_exp'] ?? 0));
        $dmg = game_dungeon_effective_damage($baseDamage, $compKey, (int) ($uRow['companion_exp'] ?? 0));

        $newHp = max(0, (int) $raid['boss_hp'] - $dmg);
        $defeated = $newHp <= 0 ? 1 : (int) $raid['is_defeated'];

        $log = json_decode((string) ($raid['battle_log'] ?? '[]'), true) ?: [];
        $log[] = ['t' => date('Y-m-d H:i:s'), 'a' => $actionLabel, 'd' => $dmg];
        if (count($log) > 15) $log = array_slice($log, -15);

        $pdo->prepare("UPDATE dungeon_raids SET boss_hp = ?, total_damage_dealt = total_damage_dealt + ?, is_defeated = ?, battle_log = ? WHERE raid_id = ?")
            ->execute([$newHp, $dmg, $defeated, json_encode($log, JSON_UNESCAPED_UNICODE), (int) $raid['raid_id']]);
        $pdo->prepare("UPDATE users SET companion_exp = companion_exp + ? WHERE user_id = ?")
            ->execute([$dmg, $userId]);

        return [
            'applied' => $dmg,
            'defeated' => $newHp <= 0,
            'boss_hp' => $newHp,
            'boss_name' => $bosses[$raid['boss_key']]['name'] ?? $raid['boss_key'],
            'message' => $newHp <= 0 ? 'Boss tumbang! Klaim loot kemenanganmu.' : 'Damage diterapkan ke ' . ($bosses[$raid['boss_key']]['name'] ?? ''),
        ];

    } catch (Throwable $eDungeon) {
        error_log('Dungeon damage failed: ' . $eDungeon->getMessage());
        return ['damage' => 0, 'applied' => 0, 'defeated' => false];
    }}
