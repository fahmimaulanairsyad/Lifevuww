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
