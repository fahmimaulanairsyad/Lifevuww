<?php
require_once __DIR__ . '/../config/game.php';
$cases = [
    [['hp' => 0, 'max_hp' => 125, 'exp' => 12000, 'level' => 121], true, 125],
    [['hp' => -10, 'max_hp' => 100, 'exp' => 50, 'level' => 1], true, 100],
    [['hp' => 1, 'max_hp' => 100, 'exp' => 50, 'level' => 1], false, 1],
];
if (!function_exists('game_recovery_state')) {
    fwrite(STDERR, "FAIL: recovery transition is not implemented\n");
    exit(1);
}
foreach ($cases as [$user, $expected, $hp]) {
    $state = game_recovery_state($user);
    if ($state['recovered'] !== $expected || $state['user']['hp'] !== $hp || $state['user']['exp'] !== $user['exp'] || $state['user']['level'] !== $user['level']) {
        fwrite(STDERR, "FAIL: recovery must preserve EXP and level\n");
        exit(1);
    }
    if (game_recovery_state($state['user'])['recovered']) {
        fwrite(STDERR, "FAIL: recovery must be idempotent\n");
        exit(1);
    }
}
echo "PASS: zero/negative/positive HP, preserved progression and idempotent recovery\n";
