<?php
require_once __DIR__ . '/../config/game.php';
if (!function_exists('game_daily_priorities')) { fwrite(STDERR, "FAIL: missing daily priorities\n"); exit(1); }
$activities = [
    ['title' => 'done', 'is_done' => true],
    ['title' => 'first', 'is_done' => false],
    ['title' => 'second', 'is_done' => false],
    ['title' => 'third', 'is_done' => false],
    ['title' => 'fourth', 'is_done' => false],
];
if (array_column(game_daily_priorities($activities), 'title') !== ['first','second','third'] || game_daily_priorities([]) !== []) {
    fwrite(STDERR, "FAIL: pending only, preserve due-order, max three, empty state\n"); exit(1);
}
echo "PASS: daily priorities filter completions, preserve order and cap at three\n";
