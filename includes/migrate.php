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
        'last_completed_at' => "ALTER TABLE `habits` ADD COLUMN `last_completed_at` DATETIME DEFAULT NULL",
        'is_pinned' => "ALTER TABLE `habits` ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0",
        'sort_order' => "ALTER TABLE `habits` ADD COLUMN `sort_order` INT(11) NOT NULL DEFAULT 0"
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

    
    // Explicit check & migration for quests pin & custom sort_order
    $questCols = $pdo->query("DESCRIBE quests")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $newQuestCols = [
        'is_pinned' => "ALTER TABLE `quests` ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0",
        'sort_order' => "ALTER TABLE `quests` ADD COLUMN `sort_order` INT(11) NOT NULL DEFAULT 0",
        'end_date' => "ALTER TABLE `quests` ADD COLUMN `end_date` DATE DEFAULT NULL"
    ];
    foreach ($newQuestCols as $col => $sql) {
        if (!in_array($col, $questCols, true)) {
            try {
                $pdo->exec($sql);
                $results[] = "Added column $col to quests";
            } catch (Exception $e) {}
        }
    }

    // Explicit check & migration for user specialization & relics
    $userCols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (!in_array('specialization', $userCols, true)) {
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `specialization` VARCHAR(50) NOT NULL DEFAULT 'Obsidian Vanguard'");
            $results[] = "Added column specialization to users";
        } catch (Exception $e) {}
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_relics` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `relic_key` varchar(50) NOT NULL,
            `acquired_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_user_relic` (`user_id`, `relic_key`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `fk_user_relics_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");

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
        $pdo->query("SELECT target_count, is_pinned, sort_order FROM habits LIMIT 1");
        $pdo->query("SELECT is_pinned, sort_order, end_date FROM quests LIMIT 1");
        $pdo->query("SELECT specialization FROM users LIMIT 1");
        $pdo->query("SELECT id FROM user_relics LIMIT 1");
    } catch (Exception $e) {
        run_lifevuww_migrations($pdo);
    }
}

// If executed directly from CLI or included with active PDO
if (isset($pdo) && $pdo instanceof PDO) {
    ensure_lifevuww_migrated($pdo);
}
