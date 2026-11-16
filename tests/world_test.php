<?php
// Kontrak katalog display: salah eja atau pemetaan ganda tidak boleh kehilangan atribut.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Akses ditolak: Test suite hanya dapat dijalankan melalui CLI.\n");
}

$path = __DIR__ . '/../config/world.php';
if (!is_file($path)) {
    fwrite(STDERR, "FAIL: world config is missing\n");
    exit(1);
}

$world = require $path;
$fail = static function (string $message): void {
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
};
if (!is_array($world) || !is_string($world['title'] ?? null) || trim($world['title']) === '' || !is_string($world['intro'] ?? null) || trim($world['intro']) === '') {
    $fail('world must return an array with a title and intro');
}
if ($world !== require $path) {
    $fail('world config must be safe to require repeatedly');
}
echo "PASS: standalone array with title, intro and repeatable loading\n";

$regions = $world['regions'] ?? [];
if (!is_array($regions) || count($regions) !== 5) {
    $fail('world must define exactly five regions');
}
$attributes = [];
$labels = [];
foreach ($regions as $key => $region) {
    if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]*$/', $key) || !is_array($region)) {
        $fail('region keys must be stable lowercase identifiers');
    }
    foreach (['label', 'attribute', 'purpose'] as $field) {
        if (!is_string($region[$field] ?? null) || trim($region[$field]) === '') {
            $fail("region $key needs a non-empty $field");
        }
    }
    $attributes[] = $region['attribute'];
    $labels[] = $region['label'];
}
$expected = ['Health', 'Intelligence', 'Physical', 'Social', 'Wealth'];
sort($attributes);
if ($attributes !== $expected || count(array_unique($labels)) !== 5 || count(array_unique(array_keys($regions))) !== 5) {
    $fail('five unique regions must map each existing attribute exactly once');
}
echo "PASS: five unique regions map Intelligence/Physical/Health/Wealth/Social exactly once\n";

require_once __DIR__ . '/../config/game.php';
$catalogs = [
    'ranks' => ['F-Rank Novice', 'E-Rank Rookie', 'D-Rank Explorer', 'C-Rank Vanguard', 'B-Rank Elite', 'A-Rank Master', 'S-Rank Sovereign'],
    'specializations' => array_keys(game_specializations()),
    'companions' => array_keys(game_companions()),
    'companion_stages' => ['Hatchling', 'Awakened', 'Sovereign'],
    'bosses' => array_keys(game_dungeon_bosses()),
    'focus_rifts' => array_keys(game_focus_rifts()),
];
foreach ($catalogs as $catalog => $keys) {
    $aliases = $world[$catalog] ?? null;
    if (!is_array($aliases) || array_keys($aliases) !== $keys) {
        $fail("$catalog aliases must preserve all existing internal keys");
    }
    foreach ($aliases as $label) {
        if (!is_string($label) || trim($label) === '') {
            $fail("$catalog aliases must be non-empty display labels");
        }
    }
    if (count(array_unique(array_values($aliases))) !== count($aliases)) {
        $fail("$catalog display labels must be unique within their catalog");
    }
}
echo "PASS: rank, specialization, companion, stage, boss and focus aliases retain internal keys\n";

$terms = $world['terms'] ?? [];
foreach (['habit', 'quest', 'goal', 'milestone', 'focus_rift', 'dungeon', 'boss', 'companion', 'recovery', 'planned_break'] as $key) {
    if (!is_string($terms[$key] ?? null) || trim($terms[$key]) === '') {
        $fail("missing display term: $key");
    }
}
echo "PASS: core product terminology is available for display\n";
