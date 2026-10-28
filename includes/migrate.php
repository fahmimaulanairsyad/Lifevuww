<?php
/**
 * includes/migrate.php
 * Idempotent Schema Migration Runner for Lifevuww
 */

function run_lifevuww_migrations(PDO $pdo): array {
    $results = [];

    // Ensure schema_migrations table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `migration` varchar(255) NOT NULL,
            `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");

    $executed = $pdo->query("SELECT migration FROM schema_migrations ORDER BY id ASC")
                    ->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // Explicit check & migration for habits flexible scheduling and counters
    $cols = $pdo->query("DESCRIBE habits")->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $newCols = [
        'target_count' => "ALTER TABLE `habits` ADD COLUMN `target_count` INT NOT NULL DEFAULT 1",
        'current_count' => "ALTER TABLE `habits` ADD COLUMN `current_count` INT NOT NULL DEFAULT 0",
        'schedule_type' => "ALTER TABLE `habits` ADD COLUMN `schedule_type` VARCHAR(30) NOT NULL DEFAULT 'daily'",
        'schedule_value' => "ALTER TABLE `habits` ADD COLUMN `schedule_value` VARCHAR(100) NOT NULL DEFAULT '1'",
        'last_completed_at' => "ALTER TABLE `habits` ADD COLUMN `last_completed_at` DATETIME DEFAULT NULL"
    ];

    foreach ($newCols as $col => $sql) {
        if (!in_array($col, $cols, true)) {
            try {
                $pdo->exec($sql);
                $results[] = "Added column $col to habits";
            } catch (Exception $e) {
                // Ignore if duplicate or already added
            }
        }
    }

    // Also run any pending .sql files in migrations/
    $migrationDir = dirname(__DIR__) . '/migrations';
    if (is_dir($migrationDir)) {
        $files = glob($migrationDir . '/*.sql');
        sort($files);
        foreach ($files as $file) {
            $base = basename($file);
            if (in_array($base, $executed, true)) continue;

            $sql = file_get_contents($file);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $err = false;
            foreach ($statements as $stmtSql) {
                if (empty($stmtSql)) continue;
                try {
                    $pdo->exec($stmtSql);
                } catch (Exception $ex) {
                    $msg = $ex->getMessage();
                    if (stripos($msg, 'duplicate') === false && stripos($msg, 'already exists') === false) {
                        $err = true;
                        error_log("Migration error in $base: " . $msg);
                    }
                }
            }
            if (!$err) {
                $stmt = $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (?)");
                $stmt->execute([$base]);
                $results[] = "Executed migration $base";
            }
        }
    }

    return $results;
}

function ensure_lifevuww_migrated(PDO $pdo): void {
    static $alreadyChecked = false;
    if ($alreadyChecked) return;
    $alreadyChecked = true;

    try {
        $pdo->query("SELECT target_count FROM habits LIMIT 1");
    } catch (Exception $e) {
        run_lifevuww_migrations($pdo);
    }
}

// If executed directly from CLI or included with active PDO
if (isset($pdo) && $pdo instanceof PDO) {
    ensure_lifevuww_migrated($pdo);
}
