<?php
// config/database.php

// Load environment variables from .env file.
// Fail closed: never silently fall back to root/empty credentials.
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    error_log("Missing .env configuration file.");
    die("Missing application configuration (.env). Copy .env.example to .env and configure it.");
}
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    if (strpos($line, '=') === false) continue;
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'habit_tracker_rpg';
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

// --- Application Timezone (single source of truth) ---
// Server hosting berjalan di UTC sedangkan pengguna di WIB (UTC+7).
// Tanpa ini, aktivitas jam 00:00–06:59 WIB tercatat sebagai "kemarin"
// dan reset harian jatuh jam 07:00 WIB, bukan tengah malam.
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', $_ENV['APP_TIMEZONE'] ?? 'Asia/Jakarta');
}
date_default_timezone_set(APP_TIMEZONE);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Samakan zona waktu session MySQL dengan PHP agar CURDATE(), NOW(),
    // DATE(), dan perbandingan reset harian konsisten satu sama lain.
    $tzOffset = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P'); // contoh: +07:00
    if (preg_match('/^[+-]\d{2}:\d{2}$/', $tzOffset)) {
        $pdo->exec("SET time_zone = '$tzOffset'");
    }
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

require_once __DIR__ . '/game.php';
require_once __DIR__ . '/../includes/migrate.php';

if (isset($pdo) && function_exists('ensure_lifevuww_migrated')) {
    ensure_lifevuww_migrated($pdo);
}

if (!function_exists('calculateLevel')) {
    function calculateLevel($exp) {
        return game_calculate_level($exp);
    }
}

if (!function_exists('calculateRank')) {
    function calculateRank($level) {
        return game_calculate_rank($level);
    }
}

// --- CSRF Protection Helpers ---
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
    }
}

if (!function_exists('get_client_ip')) {
    // Deteksi IP klien aman. Hanya mempercayai header reverse proxy/Cloudflare jika TRUST_PROXY=true
    function get_client_ip(): string {
        $trustProxy = filter_var($_ENV['TRUST_PROXY'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($trustProxy) {
            $headers = [
                'HTTP_CF_CONNECTING_IP',
                'HTTP_X_REAL_IP',
                'HTTP_X_FORWARDED_FOR'
            ];
            foreach ($headers as $h) {
                if (!empty($_SERVER[$h])) {
                    $raw = $_SERVER[$h];
                    if (str_contains($raw, ',')) {
                        $parts = explode(',', $raw);
                        $raw = trim($parts[0]);
                    }
                    if (filter_var($raw, FILTER_VALIDATE_IP)) {
                        return $raw;
                    }
                }
            }
        }

        $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '127.0.0.1';
    }
}

if (!function_exists('ensure_demo_user')) {
    // Memastikan akun demo tersedia dengan data sampel habit & quest
    function ensure_demo_user(PDO $pdo): int {
        $demoUsername = 'DemoPlayer';
        $demoEmail = 'demo_player@example.com';

        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$demoUsername]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $demoId = (int) $existingId;
            // Cek apakah quest atau habit sudah habis/dihapus pengunjung sebelumnya
            $stmtHabitCount = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE user_id = ?");
            $stmtHabitCount->execute([$demoId]);
            $habitCount = (int) $stmtHabitCount->fetchColumn();

            $stmtQuestCount = $pdo->prepare("SELECT COUNT(*) FROM quests WHERE user_id = ? AND status = 'In Progress'");
            $stmtQuestCount->execute([$demoId]);
            $questCount = (int) $stmtQuestCount->fetchColumn();

            $hasMultiCount = 0;
            try {
                $stmtCheckMulti = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE user_id = ? AND target_count > 1 AND current_count >= 1");
                $stmtCheckMulti->execute([$demoId]);
                $hasMultiCount = (int) $stmtCheckMulti->fetchColumn();
            } catch (Exception $e) {}

            // Jika kebiasaan < 3, quest aktif < 1, atau belum ada habit multi-count aktif, re-seed otomatis
            if ($habitCount < 3 || $questCount < 1 || $hasMultiCount === 0) {
                // Pulihkan status vitals & kunci last_daily_check hari ini agar tidak langsung direset oleh rollover
                $pdo->prepare("UPDATE users SET hp = 90, max_hp = 100, gold = GREATEST(125, gold), streak_freeze = GREATEST(1, streak_freeze), last_daily_check = CURDATE() WHERE user_id = ?")
                    ->execute([$demoId]);

                if ($habitCount < 3 || $hasMultiCount === 0) {
                    $pdo->prepare("DELETE FROM habit_logs WHERE user_id = ?")->execute([$demoId]);
                    $pdo->prepare("DELETE FROM habits WHERE user_id = ?")->execute([$demoId]);
                    $starters = [
                        ['Membaca 20 Halaman Buku', 'Easy', 'daily', 'Intelligence', 4, 1, 1, 1, 'daily', '1'],
                        ['Hidrasi 3 Botol Air', 'Easy', 'daily', 'Health', 12, 0, 3, 1, 'daily', '1'],
                        ['Workout / Latihan Fisik', 'Medium', 'weekly', 'Physical', 6, 0, 1, 0, 'weekly_days', '1,3,5'],
                        ['Review Pengeluaran Harian', 'Easy', 'daily', 'Wealth', 3, 0, 1, 0, 'daily', '1'],
                        ['Hubungi Teman / Keluarga', 'Easy', 'weekly', 'Social', 1, 0, 1, 0, 'interval_days', '3']
                    ];
                    $stmtH = $pdo->prepare("
                        INSERT INTO habits (user_id, habit_name, difficulty, exp_reward, reset_period, category, streak, completed_today, target_count, current_count, schedule_type, schedule_value, last_reset)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    foreach ($starters as $s) {
                        $exp = exp_for_difficulty($s[1]);
                        $stmtH->execute([$demoId, $s[0], $s[1], $exp, $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], $s[8], $s[9]]);
                        $habitId = (int) $pdo->lastInsertId();
                        if ($s[5] === 1) {
                            $pdo->prepare("INSERT INTO habit_logs (habit_id, user_id, completion_date, status, exp_reward) VALUES (?, ?, CURDATE(), 'Completed', ?)")
                                ->execute([$habitId, $demoId, $exp]);
                            bump_skill_progress($pdo, $demoId, $s[3]);
                        }
                    }
                }

                if ($questCount < 1) {
                    $demoSubtasks = json_encode([
                        ['title' => 'Pemanasan & Jogging 1 KM', 'done' => true],
                        ['title' => 'Lari Konstan 3 KM', 'done' => false],
                        ['title' => 'Sprint 1 KM & Pendinginan', 'done' => false]
                    ], JSON_UNESCAPED_UNICODE);

                    $stmtQ = $pdo->prepare("
                        INSERT INTO quests (user_id, quest_name, difficulty, start_date, exp_reward, duration_days, status, category, subtasks)
                        VALUES (?, ?, ?, CURDATE(), ?, ?, 'In Progress', ?, ?)
                    ");
                    $stmtQ->execute([$demoId, 'Menyelesaikan Modul Belajar Javascript', 'Medium', 10, 14, 'Intelligence', null]);
                    $stmtQ->execute([$demoId, 'Lari 5K Tanpa Henti (Boss Quest)', 'Hard', 15, 21, 'Physical', $demoSubtasks]);

                    // Tambahkan 1 Boss Quest yang sudah ditaklukkan agar trofi langsung tampil di profil demo
                    $stmtQ->execute([$demoId, 'Tuntaskan Proyek Web RPG (Boss Quest)', 'Hard', 15, 7, 'Intelligence', '[]']);
                    $conqueredBossId = (int) $pdo->lastInsertId();
                    $pdo->prepare("UPDATE quests SET status = 'Completed' WHERE quest_id = ?")->execute([$conqueredBossId]);
                    $pdo->prepare("INSERT INTO quest_logs (quest_id, user_id, status, completed_at, exp_reward) VALUES (?, ?, 'Completed', DATE_SUB(NOW(), INTERVAL 2 DAY), 23)")
                        ->execute([$conqueredBossId, $demoId]);
                }

                // Berikan relik awal untuk showcase di toko & profil demo
                try {
                    $pdo->prepare("INSERT IGNORE INTO user_relics (user_id, relic_key) VALUES (?, 'chrono_blade')")
                        ->execute([$demoId]);
                    $pdo->prepare("UPDATE habits SET is_pinned = 1 WHERE user_id = ? AND habit_name LIKE 'Hidrasi%'")
                        ->execute([$demoId]);
                    $pdo->prepare("UPDATE quests SET is_pinned = 1 WHERE user_id = ? AND status = 'In Progress' AND difficulty = 'Hard'")
                        ->execute([$demoId]);
                } catch (Exception $e) {}
            }

            // Pastikan selalu ada minimal 1 Boss Quest yang sudah ditaklukkan agar etalase trofi langsung aktif
            $stmtBossCheck = $pdo->prepare("SELECT COUNT(*) FROM quests WHERE user_id = ? AND status = 'Completed' AND difficulty = 'Hard'");
            $stmtBossCheck->execute([$demoId]);
            if ((int)$stmtBossCheck->fetchColumn() === 0) {
                $stmtQ = $pdo->prepare("INSERT INTO quests (user_id, quest_name, difficulty, start_date, exp_reward, duration_days, status, category, subtasks) VALUES (?, ?, 'Hard', CURDATE(), 15, 7, 'Completed', 'Intelligence', '[]')");
                $stmtQ->execute([$demoId, 'Tuntaskan Proyek Web RPG (Boss Quest)']);
                $conqueredBossId = (int) $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO quest_logs (quest_id, user_id, status, completed_at, exp_reward) VALUES (?, ?, 'Completed', DATE_SUB(NOW(), INTERVAL 2 DAY), 23)")
                    ->execute([$conqueredBossId, $demoId]);
            }

            return $demoId;
        }

        // Gunakan ID tetap / eksplisit untuk menghindari lock auto-increment
        $stmtMax = $pdo->query("SELECT COALESCE(MAX(user_id), 0) + 1 FROM users");
        $targetId = max(999, (int) $stmtMax->fetchColumn());

        // Buat akun demo baru dengan saldo awal & level 5
        $demoPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (user_id, username, password, email, `rank`, level, exp, hp, max_hp, gold, streak_freeze, last_daily_check, specialization)
            VALUES (?, ?, ?, ?, 'D-Rank Explorer', 5, 450, 90, 100, 125, 1, CURDATE(), 'Obsidian Vanguard')
        ");
        $stmt->execute([$targetId, $demoUsername, $demoPass, $demoEmail]);
        $userId = $targetId;

        // Tambahkan kebiasaan demo (lengkap dengan Multi-Count Stepper & Jadwal Fleksibel)
        $starters = [
            ['Membaca 20 Halaman Buku', 'Easy', 'daily', 'Intelligence', 4, 1, 1, 1, 'daily', '1'],
            ['Hidrasi 3 Botol Air', 'Easy', 'daily', 'Health', 12, 0, 3, 1, 'daily', '1'],
            ['Workout / Latihan Fisik', 'Medium', 'weekly', 'Physical', 6, 0, 1, 0, 'weekly_days', '1,3,5'],
            ['Review Pengeluaran Harian', 'Easy', 'daily', 'Wealth', 3, 0, 1, 0, 'daily', '1'],
            ['Hubungi Teman / Keluarga', 'Easy', 'weekly', 'Social', 1, 0, 1, 0, 'interval_days', '3']
        ];

        $stmtH = $pdo->prepare("
            INSERT INTO habits (user_id, habit_name, difficulty, exp_reward, reset_period, category, streak, completed_today, target_count, current_count, schedule_type, schedule_value, last_reset)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($starters as $s) {
            $exp = exp_for_difficulty($s[1]);
            $stmtH->execute([$userId, $s[0], $s[1], $exp, $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], $s[8], $s[9]]);
            $habitId = (int) $pdo->lastInsertId();

            if ($s[5] === 1) {
                // Catat log untuk habit yang sudah selesai
                $pdo->prepare("INSERT INTO habit_logs (habit_id, user_id, completion_date, status, exp_reward) VALUES (?, ?, CURDATE(), 'Completed', ?)")
                    ->execute([$habitId, $userId, $exp]);
                bump_skill_progress($pdo, $userId, $s[3]);
            }
        }

        // Tambahkan quest demo
        $demoSubtasks = json_encode([
            ['title' => 'Pemanasan & Jogging 1 KM', 'done' => true],
            ['title' => 'Lari Konstan 3 KM', 'done' => false],
            ['title' => 'Sprint 1 KM & Pendinginan', 'done' => false]
        ], JSON_UNESCAPED_UNICODE);

        $stmtQ = $pdo->prepare("
            INSERT INTO quests (user_id, quest_name, difficulty, start_date, exp_reward, duration_days, status, category, subtasks)
            VALUES (?, ?, ?, CURDATE(), ?, ?, 'In Progress', ?, ?)
        ");
        $stmtQ->execute([$userId, 'Menyelesaikan Modul Belajar Javascript', 'Medium', 10, 14, 'Intelligence', null]);
        $stmtQ->execute([$userId, 'Lari 5K Tanpa Henti (Boss Quest)', 'Hard', 15, 21, 'Physical', $demoSubtasks]);

        // Tambahkan 1 Boss Quest yang sudah ditaklukkan
        $stmtQ->execute([$userId, 'Tuntaskan Proyek Web RPG (Boss Quest)', 'Hard', 15, 7, 'Intelligence', '[]']);
        $conqueredBossId = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE quests SET status = 'Completed' WHERE quest_id = ?")->execute([$conqueredBossId]);
        $pdo->prepare("INSERT INTO quest_logs (quest_id, user_id, status, completed_at, exp_reward) VALUES (?, ?, 'Completed', DATE_SUB(NOW(), INTERVAL 2 DAY), 23)")
            ->execute([$conqueredBossId, $userId]);

        // Berikan relik awal untuk showcase
        try {
            $pdo->prepare("INSERT IGNORE INTO user_relics (user_id, relic_key) VALUES (?, 'chrono_blade')")
                ->execute([$userId]);
        } catch (Exception $e) {}

        return $userId;
    }
}

if (!function_exists('indo_short_date')) {
    // Format tanggal ringkas Bahasa Indonesia: "Sab, 19 Sep" / "19 Sep 2026 • 11:08"
    function indo_short_date($dateKey, $withYearTime = false) {
        $ts = strtotime($dateKey);
        if ($ts === false) return $dateKey;
        $days = ['Sun' => 'Min', 'Mon' => 'Sen', 'Tue' => 'Sel', 'Wed' => 'Rab', 'Thu' => 'Kam', 'Fri' => 'Jum', 'Sat' => 'Sab'];
        $months = ['Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr', 'May' => 'Mei', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Agu', 'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des'];
        $out = $days[date('D', $ts)] . ', ' . date('j', $ts) . ' ' . $months[date('M', $ts)];
        if ($withYearTime) {
            $out .= ' ' . date('Y', $ts) . ' • ' . date('H:i', $ts);
        }
        return $out;
    }
}

if (!function_exists('exp_for_difficulty')) {
    function exp_for_difficulty($difficulty, $default = 5) {
        $diffs = game_difficulties();
        return $diffs[$difficulty]['exp'] ?? $default;
    }
}

if (!function_exists('skill_id_for_category')) {
    function skill_id_for_category($category) {
        $cats = game_categories();
        if (!isset($cats[$category])) return null;
        return [$cats[$category]['id'], $category, $category];
    }
}

if (!function_exists('bump_skill_progress')) {
    // Atomic insert-or-increment of a user's skill progress.
    function bump_skill_progress($pdo, $userId, $category) {
        $map = skill_id_for_category($category);
        if ($map === null) return;
        [$skillId, $skillName, $skillType] = $map;
        $stmt = $pdo->prepare("
            INSERT INTO skills (user_id, skill_id, skill_name, skill_type, progress)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE progress = progress + 1
        ");
        $stmt->execute([$userId, $skillId, $skillName, $skillType]);
    }
}

if (!function_exists('revert_skill_progress')) {
    // Atomic decrement of a user's skill progress on undo.
    function revert_skill_progress($pdo, $userId, $category) {
        $map = skill_id_for_category($category);
        if ($map === null) return;
        [$skillId] = $map;
        $stmt = $pdo->prepare("
            UPDATE skills 
            SET progress = GREATEST(0, progress - 1) 
            WHERE user_id = ? AND skill_id = ?
        ");
        $stmt->execute([$userId, $skillId]);
    }
}

if (!function_exists('lv_session_lifetime')) {
    // Masa berlaku login tersimpan (cookie sesi & token remember-me).
    // Dikonfigurasi lewat .env: SESSION_LIFETIME_DAYS (default 30 hari).
    function lv_session_lifetime(): int {
        $days = (int) ($_ENV['SESSION_LIFETIME_DAYS'] ?? getenv('SESSION_LIFETIME_DAYS') ?: 30);
        return max(1, $days) * 86400;
    }
}

if (!function_exists('secure_session_start')) {
    // Session bootstrap with hardened cookie flags. Must run BEFORE
    // session_start() - callers must require database.php first.
    // Cookie persisten (bukan session-cookie) agar login di APK WebView
    // dan browser tidak hilang setiap kali aplikasi ditutup.
    function secure_session_start() {
        if (session_status() !== PHP_SESSION_NONE) return;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $lifetime = lv_session_lifetime();
        // Naikkan masa hidup file sesi di server agar GC PHP tidak
        // menghapus sesi yang masih dipakai cookie jangka panjang.
        @ini_set('session.gc_maxlifetime', (string) $lifetime);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ]);
        session_start();
    }
}

if (!function_exists('lv_remember_cookie_clear')) {
    function lv_remember_cookie_clear(): void {
        if (!headers_sent()) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('lv_remember', '', [
                'expires' => time() - 42000,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $secure,
            ]);
        }
        unset($_COOKIE['lv_remember']);
    }
}

if (!function_exists('lv_issue_remember_token')) {
    // Terbitkan token remember-me saat login sukses (login manual & demo).
    // Format cookie: selector:validator — validator disimpan sebagai SHA-256,
    // sehingga bocornya database tidak langsung membocorkan cookie.
    function lv_issue_remember_token(PDO $pdo, int $userId): void {
        try {
            $selector = bin2hex(random_bytes(9));
            $validator = bin2hex(random_bytes(32));
            $lifetime = lv_session_lifetime();
            $stmt = $pdo->prepare("
                INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', time() + $lifetime)]);
            // Buang token user ini yang sudah kedaluwarsa (hemat baris tabel)
            $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND expires_at < NOW()")
                ->execute([$userId]);
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('lv_remember', $selector . ':' . $validator, [
                'expires' => time() + $lifetime,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $secure,
            ]);
        } catch (Exception $e) {
            error_log("Remember token issue failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('lv_consume_remember_token')) {
    // Auto-login dari cookie lv_remember saat sesi PHP sudah hilang.
    // Validator dirotasi pada tiap pemakaian (mencegah replay cookie lama)
    // dan ketidakcocokan validator mencabut seluruh token user terkait.
    function lv_consume_remember_token(PDO $pdo): bool {
        $raw = $_COOKIE['lv_remember'] ?? '';
        if (!preg_match('/^([a-f0-9]{16,64}):([a-f0-9]{32,128})$/', $raw, $m)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("
                SELECT rt.id, rt.user_id, rt.validator_hash, rt.expires_at, u.username, u.level
                FROM remember_tokens rt
                JOIN users u ON u.user_id = rt.user_id
                WHERE rt.selector = ?
                LIMIT 1
            ");
            $stmt->execute([$m[1]]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                lv_remember_cookie_clear();
                return false;
            }
            if (!hash_equals((string) $row['validator_hash'], hash('sha256', $m[2]))) {
                // Cookie dengan selector valid tapi validator salah: indikasi
                // cookie dicuri/direplay — cabut semua token user ini.
                $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")
                    ->execute([(int) $row['user_id']]);
                lv_remember_cookie_clear();
                return false;
            }
            if (strtotime((string) $row['expires_at']) < time()) {
                $pdo->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([(int) $row['id']]);
                lv_remember_cookie_clear();
                return false;
            }

            $lifetime = lv_session_lifetime();
            // Slide masa berlaku token + rotasi validator
            $newValidator = bin2hex(random_bytes(32));
            $stmtUp = $pdo->prepare("UPDATE remember_tokens SET validator_hash = ?, expires_at = ? WHERE id = ?");
            $stmtUp->execute([hash('sha256', $newValidator), date('Y-m-d H:i:s', time() + $lifetime), (int) $row['id']]);
            $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND expires_at < NOW()")
                ->execute([(int) $row['user_id']]);
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('lv_remember', $m[1] . ':' . $newValidator, [
                'expires' => time() + $lifetime,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $secure,
            ]);

            // Bangun ulang sesi seperti hasil login (id sesi baru anti-fixation)
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $row['user_id'];
            $_SESSION['username'] = (string) $row['username'];
            $_SESSION['level'] = (int) $row['level'];
            $_SESSION['last_activity'] = time();
            return true;
        } catch (Exception $e) {
            error_log("Remember token consume failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('lv_revoke_remember_token')) {
    // Cabut token remember-me milik cookie ini (dipakai logout).
    function lv_revoke_remember_token(PDO $pdo): void {
        $raw = $_COOKIE['lv_remember'] ?? '';
        if (preg_match('/^([a-f0-9]{16,64}):/', $raw, $m)) {
            try {
                $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?")->execute([$m[1]]);
            } catch (Exception $e) {
                error_log("Remember token revoke failed: " . $e->getMessage());
            }
        }
        lv_remember_cookie_clear();
    }
}

if (!function_exists('enforce_login')) {
    // Starts a hardened session, redirects guests, enforces optional idle
    // timeout, and runs the daily rollover BEFORE any page handler (so no
    // handler ever sees yesterday's flags on a new day).
    // Login dipertahankan antar buka-aplikasi lewat remember token; idle
    // timeout lama (30 menit) memaksa re-login terus di APK sehingga
    // diganti SESSION_IDLE_TIMEOUT (.env, 0 = nonaktif).
    function enforce_login($loginPath = '../login.php', $timeoutSeconds = null) {
        global $pdo;
        secure_session_start();
        if (!isset($_SESSION['user_id'])) {
            // Sesi PHP hilang (server restart / GC hosting): pulihkan dari cookie remember-me
            if (!(isset($pdo) && lv_consume_remember_token($pdo))) {
                header("Location: " . $loginPath);
                exit();
            }
        }
        $idleLimit = $timeoutSeconds ?? (int) ($_ENV['SESSION_IDLE_TIMEOUT'] ?? getenv('SESSION_IDLE_TIMEOUT') ?: 0);
        if ($idleLimit > 0 && isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $idleLimit)) {
            session_unset();
            session_destroy();
            lv_remember_cookie_clear();
            header("Location: " . $loginPath . "?timeout=1");
            exit();
        }
        $_SESSION['last_activity'] = time();

        if (isset($pdo)) {
            try {
                $rolloverRes = run_daily_rollover($pdo, (int) $_SESSION['user_id']);
                if (!empty($rolloverRes['rolled'])) {
                    $_SESSION['morning_report'] = $rolloverRes;
                }
            } catch (Exception $e) {
                error_log("Rollover failed for user " . (int) $_SESSION['user_id'] . ": " . $e->getMessage());
            }
        }
    }
}

if (!function_exists('run_daily_rollover')) {
    // THE single daily engine (called from enforce_login() and cron/daily_reset.php).
    // Transactional per user and idempotent (no-op if already rolled today).
    //  - daily habits are evaluated every day; weekly habits only when the ISO week
    //    changed since the last check; monthly habits only when the month changed.
    //  - only the habits that were actually missed lose their streak.
    //  - a missed habit is logged as 'Failed' on the day it was missed (yesterday).
    //  - one streak freeze (if owned) absorbs the damage instead of HP loss.
    // Returns ['rolled' => bool, 'damage' => int, 'frozen' => bool, 'failed_quests' => int].
    function run_daily_rollover($pdo, $userId) {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $result = ['rolled' => false, 'damage' => 0, 'frozen' => false, 'failed_quests' => 0];

        // Fast path without a transaction/lock: almost every request ends here.
        $quick = $pdo->prepare("SELECT last_daily_check FROM users WHERE user_id = ?");
        $quick->execute([$userId]);
        if (substr((string) $quick->fetchColumn(), 0, 10) === $today) {
            return $result;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT hp, streak_freeze, last_daily_check FROM users WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $pdo->rollBack();
                return $result;
            }
            $lastDate = substr((string) ($user['last_daily_check'] ?? ''), 0, 10);
            if ($lastDate === $today) {
                $pdo->rollBack();
                return $result;
            }
            $isFirstRun = ($lastDate === '');

            if (!$isFirstRun) {
                // Which reset periods ended between the last check and today?
                // (internal constants only - never user input)
                $periods = ["'daily'"];
                $lastTs = strtotime($lastDate);
                if (date('o-W', $lastTs) !== date('o-W')) $periods[] = "'weekly'";
                if (date('Y-m', $lastTs) !== date('Y-m')) $periods[] = "'monthly'";
                $periodSql = implode(',', $periods);
                $periodCond = "COALESCE(LOWER(reset_period), 'daily') IN ($periodSql)";

                // 1. Missed habits (Evaluasi multi-hari dengan game_missed_habits_by_day)
                $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
                $from = ($lastDate < $thirtyDaysAgo) ? $thirtyDaysAgo : $lastDate;
                $to = $yesterday;

                $stmtHabits = $pdo->prepare("
                    SELECT habit_id, difficulty, reset_period, schedule_type, schedule_value, last_completed_at
                    FROM habits
                    WHERE user_id = ?
                ");
                $stmtHabits->execute([$userId]);
                $allHabits = $stmtHabits->fetchAll(PDO::FETCH_ASSOC);

                $logsFrom = date('Y-m-d', strtotime($from . ' -31 days'));
                $stmtLogs = $pdo->prepare("
                    SELECT habit_id, completion_date AS date
                    FROM habit_logs
                    WHERE user_id = ? AND status = 'Completed' AND completion_date >= ?
                ");
                $stmtLogs->execute([$userId, $logsFrom]);
                $completedLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

                $missedByDay = game_missed_habits_by_day($allHabits, $from, $to, $completedLogs);
                ksort($missedByDay);

                $freezeLeft = (int) ($user['streak_freeze'] ?? 0);
                $freezeUsed = 0;
                $habitDamage = 0;
                $missedStreakIds = [];
                $diffRules = game_difficulties();

                $stmtLogFail = $pdo->prepare("
                    INSERT INTO habit_logs (habit_id, user_id, completion_date, status, exp_reward)
                    VALUES (?, ?, ?, 'Failed', 0)
                ");

                $habitMap = [];
                foreach ($allHabits as $h) {
                    $habitMap[(int) $h['habit_id']] = $h;
                }

                foreach ($missedByDay as $date => $hIds) {
                    if (empty($hIds)) continue;

                    if ($freezeLeft > 0) {
                        $freezeLeft--;
                        $freezeUsed++;
                        // Dilindungi streak freeze: tanpa damage, reset streak, atau log Failed
                    } else {
                        foreach ($hIds as $hid) {
                            $h = $habitMap[$hid] ?? null;
                            $diff = $h['difficulty'] ?? 'Easy';
                            $habitDamage += $diffRules[$diff]['hp_penalty_habit'] ?? 5;
                            $missedStreakIds[$hid] = true;
                            $stmtLogFail->execute([$hid, $userId, $date]);
                        }
                    }
                }

                if ($habitDamage > 0) {
                    $pdo->prepare("UPDATE users SET hp = GREATEST(0, hp - ?) WHERE user_id = ?")
                        ->execute([$habitDamage, $userId]);
                }

                if ($freezeUsed > 0) {
                    $pdo->prepare("UPDATE users SET streak_freeze = streak_freeze - ? WHERE user_id = ?")
                        ->execute([$freezeUsed, $userId]);
                }

                if (!empty($missedStreakIds)) {
                    $resetIds = array_keys($missedStreakIds);
                    $in = implode(',', array_fill(0, count($resetIds), '?'));
                    $pdo->prepare("UPDATE habits SET streak = 0 WHERE user_id = ? AND habit_id IN ($in)")
                        ->execute(array_merge([$userId], $resetIds));
                }

                $result['damage'] = $habitDamage;
                $result['frozen'] = ($freezeUsed > 0);

                // 2. Expired quests
                $stmtExp = $pdo->prepare("
                    SELECT quest_id, difficulty FROM quests
                    WHERE user_id = ? AND status = 'In Progress'
                      AND DATE_ADD(start_date, INTERVAL duration_days DAY) < CURDATE()
                ");
                $stmtExp->execute([$userId]);
                $expired = $stmtExp->fetchAll(PDO::FETCH_ASSOC);
                $diffRules = game_difficulties();
                foreach ($expired as $q) {
                    $qd = $diffRules[$q['difficulty']]['hp_penalty_quest'] ?? 10;
                    $pdo->prepare("UPDATE users SET hp = GREATEST(0, hp - ?) WHERE user_id = ?")
                        ->execute([$qd, $userId]);
                    $pdo->prepare("UPDATE quests SET status = 'Failed' WHERE quest_id = ?")
                        ->execute([$q['quest_id']]);
                    $pdo->prepare("INSERT INTO quest_logs (quest_id, user_id, status, completed_at, exp_reward) VALUES (?, ?, 'Failed', NOW(), 0)")
                        ->execute([$q['quest_id'], $userId]);
                    $result['damage'] += $qd;
                    $result['failed_quests']++;
                }

                // 3. Reset completion flags and current counter - only for periods that just ended
                try {
                    $pdo->prepare("UPDATE habits SET completed_today = 0, current_count = 0, last_reset = NOW() WHERE user_id = ? AND $periodCond")
                        ->execute([$userId]);
                } catch (Exception $eUpd) {
                    $pdo->prepare("UPDATE habits SET completed_today = 0, last_reset = NOW() WHERE user_id = ? AND $periodCond")
                        ->execute([$userId]);
                }
            }

            $pdo->prepare("UPDATE users SET last_daily_check = ? WHERE user_id = ?")
                ->execute([$today, $userId]);
            $pdo->commit();
            $result['rolled'] = true;
            return $result;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Daily rollover error for user $userId: " . $e->getMessage());
            throw $e;
        }
    }
}

if (!function_exists('validate_csrf')) {
    function validate_csrf() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $token = $_POST['csrf_token'] ?? '';
            if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
                error_log("CSRF token validation failed.");
                die("Security verification failed (Invalid CSRF Token). Please refresh the page and try again.");
            }
        }
    }
}

