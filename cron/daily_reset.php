<?php
// cron/daily_reset.php
// Thin wrapper around the shared run_daily_rollover() engine.
// Access control: CLI always allowed; over HTTP requires ?token=CRON_TOKEN
// (set CRON_TOKEN in .env). This prevents the public from triggering
// HP damage / streak resets for every user.

require_once __DIR__ . '/../config/database.php';

$token = $_ENV['CRON_TOKEN'] ?? '';
$isCli = (php_sapi_name() === 'cli');
$given = $_GET['token'] ?? '';

if (!$isCli && ($token === '' || !hash_equals($token, $given))) {
    http_response_code(403);
    exit('Forbidden');
}

try {
    $ids = $pdo->query("SELECT user_id FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $results = [];
    foreach ($ids as $id) {
        try {
            $results[(int) $id] = run_daily_rollover($pdo, (int) $id);
        } catch (Exception $e) {
            $results[(int) $id] = ['rolled' => false, 'error' => $e->getMessage()];
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'users' => $results]);
} catch (Exception $e) {
    error_log("Cron Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Daily reset failed.']);
}
