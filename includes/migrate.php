<?php
/**
 * includes/migrate.php
 * Idempotent Schema Migration Runner for Lifevuww
 *
 * Hotfix deadlock migrasi di hosting: DDL (ALTER TABLE / CREATE TABLE) memicu
 * implicit commit di MySQL/MariaDB sehingga transaksi DILARANG di runner ini.
 * Statement file migrasi yang bentrok duplicate (1060 kolom / 1061 key /
 * 1050 tabel) diabaikan PER-STATEMENT dan eksekusi statement berikutnya dalam
 * file yang sama tetap berjalan; file tetap dicatat selesai di
 * schema_migrations agar tidak terjadi loop gagal + fatal "Table doesn't exist".
 */

// Naikkan setiap menambah file migrasi di migrations/ — probe ensure_lifevuww_migrated()
// memakai nilai ini sebagai penanda skema sudah terkini (1 query per request).
if (!defined('LV_SCHEMA_VERSION')) {
    define('LV_SCHEMA_VERSION', '012_sovereign_expansion.sql');
}

function run_lifevuww_migrations(PDO $pdo): array {
    $results = [];

    // Ensure schema_migrations table exists
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `migration` varchar(255) NOT NULL,
                `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
    } catch (Throwable $e) {
        error_log('Migrate CREATE schema_migrations failed: ' . $e->getMessage());
        return $results; // tanpa tabel ini tidak ada yang bisa dilanjutkan
    }

    try {
        $executed = $pdo->query("SELECT migration FROM schema_migrations ORDER BY id ASC")
                        ->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        error_log('Migrate read schema_migrations failed: ' . $e->getMessage());
        return $results;
    }

    // Explicit check & migration for habits flexible scheduling and counters
    try {
        $cols = $pdo->query("DESCRIBE habits")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $eDescHabits) {
        $cols = []; // tabel belum ada / DB bermasalah: biarkan file migrasi yang menambahkan
        error_log('Migrate DESCRIBE habits failed: ' . $eDescHabits->getMessage());
    }

    $newCols = [
        'target_count' => "ALTER TABLE `habits` ADD COLUMN `target_count` INT NOT NULL DEFAULT 1",
        'current_count' => "ALTER TABLE `habits` ADD COLUMN `current_count` INT NOT NULL DEFAULT 0",
        'schedule_type' => "ALTER TABLE `habits` ADD COLUMN `schedule_type` VARCHAR(30) NOT NULL DEFAULT 'daily'",
        'schedule_value' => "ALTER TABLE `habits` ADD COLUMN `schedule_value` VARCHAR(100) NOT NULL DEFAULT '1'",
        'last_completed_at' => "ALTER TABLE `habits` ADD COLUMN `last_completed_at` DATETIME DEFAULT NULL",
        'is_pinned' => "ALTER TABLE `habits` ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0",
        'sort_order' => "ALTER TABLE `habits` ADD COLUMN `sort_order` INT(11) NOT NULL DEFAULT 0",
        'start_time' => "ALTER TABLE `habits` ADD COLUMN `start_time` TIME DEFAULT NULL",
        'end_time' => "ALTER TABLE `habits` ADD COLUMN `end_time` TIME DEFAULT NULL"
    ];

    foreach ($newCols as $col => $sql) {
        if (!in_array($col, $cols, true)) {
            try {
                $pdo->exec($sql);
                $results[] = "Added column $col to habits";
            } catch (Throwable $e) {
                // Ignore if duplicate or already added
            }
        }
    }

    // Explicit check & migration for quests pin & custom sort_order
    try {
        $questCols = $pdo->query("DESCRIBE quests")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $eDescQuests) {
        $questCols = [];
        error_log('Migrate DESCRIBE quests failed: ' . $eDescQuests->getMessage());
    }
    $newQuestCols = [
        'is_pinned' => "ALTER TABLE `quests` ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0",
        'sort_order' => "ALTER TABLE `quests` ADD COLUMN `sort_order` INT(11) NOT NULL DEFAULT 0",
        'end_date' => "ALTER TABLE `quests` ADD COLUMN `end_date` DATE DEFAULT NULL",
        'start_time' => "ALTER TABLE `quests` ADD COLUMN `start_time` TIME DEFAULT NULL",
        'end_time' => "ALTER TABLE `quests` ADD COLUMN `end_time` TIME DEFAULT NULL"
    ];
    foreach ($newQuestCols as $col => $sql) {
        if (!in_array($col, $questCols, true)) {
            try {
                $pdo->exec($sql);
                $results[] = "Added column $col to quests";
            } catch (Throwable $e) {}
        }
    }

    // Explicit check & migration for user columns — mencakup kolom migrasi 011
    // (last_crate_date, last_emergency_heal_date) dan migrasi 012 (active_companion,
    // companion_exp, spent_skill_points, unlocked_skills, overdrive_until,
    // last_skill_cast_date)
    try {
        $userCols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $eDescUsers) {
        $userCols = [];
        error_log('Migrate DESCRIBE users failed: ' . $eDescUsers->getMessage());
    }
    $newUserCols = [
        'specialization' => "ALTER TABLE `users` ADD COLUMN `specialization` VARCHAR(50) NOT NULL DEFAULT 'Obsidian Vanguard'",
        'last_crate_date' => "ALTER TABLE `users` ADD COLUMN `last_crate_date` DATE DEFAULT NULL",
        'last_emergency_heal_date' => "ALTER TABLE `users` ADD COLUMN `last_emergency_heal_date` DATE DEFAULT NULL",
        'active_companion' => "ALTER TABLE `users` ADD COLUMN `active_companion` VARCHAR(40) NOT NULL DEFAULT 'obsidian_wolf'",
        'companion_exp' => "ALTER TABLE `users` ADD COLUMN `companion_exp` INT(11) NOT NULL DEFAULT 0",
        'spent_skill_points' => "ALTER TABLE `users` ADD COLUMN `spent_skill_points` INT(11) NOT NULL DEFAULT 0",
        'unlocked_skills' => "ALTER TABLE `users` ADD COLUMN `unlocked_skills` TEXT DEFAULT NULL",
        'overdrive_until' => "ALTER TABLE `users` ADD COLUMN `overdrive_until` DATETIME DEFAULT NULL",
        'last_skill_cast_date' => "ALTER TABLE `users` ADD COLUMN `last_skill_cast_date` DATE DEFAULT NULL",
    ];
    foreach ($newUserCols as $col => $sql) {
        if (!in_array($col, $userCols, true)) {
            try {
                $pdo->exec($sql);
                $results[] = "Added column $col to users";
            } catch (Throwable $e) {}
        }
    }

    // Tabel Dungeon Raid Mingguan (gate boss per minggu, uniq per user+minggu)
    // Dibuat eksplisit di sini — bukan hanya lewat file migrasi — agar tabel
    // selalu ada sebelum halaman apa pun jalan, sekalipun file migrasi tersangkut.
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `dungeon_raids` (
                `raid_id` INT(11) NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) NOT NULL,
                `week_key` VARCHAR(10) NOT NULL,
                `boss_key` VARCHAR(40) NOT NULL,
                `boss_hp` INT(11) NOT NULL,
                `boss_max_hp` INT(11) NOT NULL,
                `total_damage_dealt` INT(11) NOT NULL DEFAULT 0,
                `is_defeated` TINYINT(1) NOT NULL DEFAULT 0,
                `reward_claimed` TINYINT(1) NOT NULL DEFAULT 0,
                `battle_log` TEXT DEFAULT NULL,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`raid_id`),
                UNIQUE KEY `uniq_user_week` (`user_id`, `week_key`),
                CONSTRAINT `fk_dungeon_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
    } catch (Throwable $e) {
        error_log('Migrate CREATE dungeon_raids failed: ' . $e->getMessage());
    }

    // Tabel Jurnal Taktis (mood/energy harian + Wisdom Bonus)
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `journal_entries` (
                `entry_id` INT(11) NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) NOT NULL,
                `entry_date` DATE NOT NULL,
                `title` VARCHAR(150) NOT NULL,
                `content` TEXT NOT NULL,
                `mood_score` TINYINT(1) NOT NULL DEFAULT 3,
                `energy_score` TINYINT(1) NOT NULL DEFAULT 3,
                `tags` VARCHAR(120) DEFAULT NULL,
                `exp_awarded` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`entry_id`),
                KEY `idx_journal_user_date` (`user_id`, `entry_date`),
                CONSTRAINT `fk_journal_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
    } catch (Throwable $e) {
        error_log('Migrate CREATE journal_entries failed: ' . $e->getMessage());
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `remember_tokens` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `selector` varchar(64) NOT NULL,
                `validator_hash` char(64) NOT NULL,
                `prev_validator_hash` char(64) DEFAULT NULL,
                `rotated_at` datetime DEFAULT NULL,
                `expires_at` datetime NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_selector` (`selector`),
                KEY `idx_rt_user` (`user_id`),
                KEY `idx_rt_expires` (`expires_at`),
                CONSTRAINT `fk_remember_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
    } catch (Throwable $e) {
        error_log('Migrate CREATE remember_tokens failed: ' . $e->getMessage());
    }

    // Explicit check & migration for quest_logs.created_at
    try {
        $qlCols = $pdo->query("DESCRIBE quest_logs")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (!in_array('created_at', $qlCols, true)) {
            $pdo->exec("ALTER TABLE `quest_logs` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
            $results[] = "Added column created_at to quest_logs";
        }
    } catch (Throwable $e) {
        error_log('Migrate quest_logs.created_at check failed: ' . $e->getMessage());
    }

    // Explicit check & migration for remember_tokens.prev_validator_hash + rotated_at
    try {
        $rtCols = $pdo->query("DESCRIBE remember_tokens")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (!in_array('prev_validator_hash', $rtCols, true)) {
            $pdo->exec("ALTER TABLE `remember_tokens` ADD COLUMN `prev_validator_hash` CHAR(64) NULL DEFAULT NULL");
            $results[] = "Added column prev_validator_hash to remember_tokens";
        }
        if (!in_array('rotated_at', $rtCols, true)) {
            $pdo->exec("ALTER TABLE `remember_tokens` ADD COLUMN `rotated_at` DATETIME NULL DEFAULT NULL");
            $results[] = "Added column rotated_at to remember_tokens";
        }
    } catch (Throwable $e) {
        error_log('Migrate remember_tokens columns check failed: ' . $e->getMessage());
    }

    try {
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
    } catch (Throwable $e) {
        error_log('Migrate CREATE user_relics failed: ' . $e->getMessage());
    }

    // Also run any pending .sql files in migrations/
    // PENTING: tanpa beginTransaction/commit/rollBack — DDL memicu implicit
    // commit sehingga transaksi bisa melempar "There is no active transaction".
    // Setiap statement dijalankan satu per satu; error duplicate (kode 1060/1061/
    // 1050 atau pesan duplicate/already exists) DIABAIKAN dan statement
    // berikutnya dalam file yang sama tetap dieksekusi.
    $migrationDir = dirname(__DIR__) . '/migrations';
    if (is_dir($migrationDir)) {
        $files = glob($migrationDir . '/*.sql');
        sort($files);
        foreach ($files as $file) {
            $base = basename($file);
            if (in_array($base, $executed, true)) continue;

            $sql = file_get_contents($file);
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            foreach ($statements as $stmtSql) {
                if ($stmtSql === '' || $stmtSql === false) continue;
                try {
                    $pdo->exec($stmtSql);
                } catch (PDOException $eStmt) {
                    $info = $eStmt->errorInfo;
                    $driverCode = (int) ($info[1] ?? 0);
                    $msg = $eStmt->getMessage();
                    if (
                        $driverCode === 1060 || $driverCode === 1061 || $driverCode === 1050
                        || stripos($msg, 'duplicate column') !== false
                        || stripos($msg, 'duplicate key name') !== false
                        || stripos($msg, 'already exists') !== false
                    ) {
                        // Duplicate column/key/table: aman untuk dilewati,
                        // lanjut ke statement berikutnya dalam file yang sama.
                        continue;
                    }
                    error_log("Migration error in $base: " . $msg);
                } catch (Throwable $eStmtAny) {
                    error_log("Migration error in $base: " . $eStmtAny->getMessage());
                }
            }

            // File resmi tercatat selesai setelah SEMUA statement diproses,
            // sehingga tidak dijalankan ulang di request berikutnya.
            $stmt = $pdo->prepare("INSERT IGNORE INTO schema_migrations (migration) VALUES (?)");
            $stmt->execute([$base]);
            $results[] = "Executed migration $base";
        }
    }

    return $results;
}

function ensure_lifevuww_migrated(PDO $pdo): void {
    static $alreadyChecked = false;
    if ($alreadyChecked) return;
    $alreadyChecked = true;

    try {
        $needsMigration = false;
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM schema_migrations WHERE migration = ? LIMIT 1");
            $stmt->execute([LV_SCHEMA_VERSION]);
            if (!$stmt->fetchColumn()) {
                $needsMigration = true;
            }
        } catch (Throwable $probeEx) {
            $needsMigration = true;
        }

        if ($needsMigration) {
            run_lifevuww_migrations($pdo);
        }
    } catch (Throwable $e) {
        // Migrasi tidak boleh membuat halaman putih; catat dan biarkan halaman jalan
        error_log("Migration error: " . $e->getMessage());
    }
}

// If executed directly from CLI or included with active PDO
if (isset($pdo) && $pdo instanceof PDO) {
    ensure_lifevuww_migrated($pdo);
}
