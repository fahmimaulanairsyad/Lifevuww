<?php
/**
 * migrate.php - Runner Migrasi Database CLI Lifevuww
 * 
 * Penggunaan:
 *   php migrate.php
 *   php migrate.php --status
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Hanya dapat dijalankan melalui CLI.\n");
}

require_once __DIR__ . '/config/database.php';

echo "=== Lifevuww Database Migration Runner ===\n";

try {
    // 1. Buat tabel schema_migrations jika belum ada
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `migration` varchar(255) NOT NULL,
            `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");

    // 2. Ambil daftar migrasi yang sudah pernah dieksekusi
    $executed = $pdo->query("SELECT migration FROM schema_migrations ORDER BY id ASC")
                    ->fetchAll(PDO::FETCH_COLUMN);

    // 3. Scan file SQL di direktori migrations/
    $migrationDir = __DIR__ . '/migrations';
    if (!is_dir($migrationDir)) {
        exit("Direktori migrations/ tidak ditemukan.\n");
    }

    $files = glob($migrationDir . '/*.sql');
    sort($files);

    if (empty($files)) {
        echo "Tidak ada berkas migrasi SQL yang ditemukan.\n";
        exit(0);
    }

    if (isset($argv[1]) && $argv[1] === '--status') {
        echo "Status Migrasi:\n";
        foreach ($files as $file) {
            $base = basename($file);
            $status = in_array($base, $executed, true) ? "[DONE]" : "[PENDING]";
            echo "  $status $base\n";
        }
        exit(0);
    }

    $count = 0;
    foreach ($files as $file) {
        $base = basename($file);
        if (in_array($base, $executed, true)) {
            continue;
        }

        echo "Menjalankan migrasi: $base ... ";
        $sql = file_get_contents($file);

        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $migrationErrors = [];
        foreach ($statements as $stmtSql) {
            if (empty($stmtSql)) continue;
            try {
                $pdo->exec($stmtSql);
            } catch (Exception $stmtEx) {
                // Abaikan error duplikasi jika kolom, key, atau tabel sudah ada sebelumnya (idempotent)
                $msg = $stmtEx->getMessage();
                if (stripos($msg, 'duplicate') !== false || stripos($msg, 'already exists') !== false) {
                    continue;
                }
                $migrationErrors[] = $msg;
            }
        }

        if (empty($migrationErrors)) {
            $stmt = $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (?)");
            $stmt->execute([$base]);
            echo "BERHASIL\n";
            $count++;
        } else {
            echo "GAGAL!\n";
            echo "Error: " . implode("; ", $migrationErrors) . "\n";
            exit(1);
        }
    }

    if ($count === 0) {
        echo "Database sudah up-to-date. Tidak ada migrasi baru.\n";
    } else {
        echo "Selesai! $count migrasi berhasil dieksekusi.\n";
    }

} catch (Exception $e) {
    echo "Terjadi kesalahan: " . $e->getMessage() . "\n";
    exit(1);
}
