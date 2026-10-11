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

if (!function_exists('calculateLevel')) {
    // 100 EXP per tingkat: pengguna aktif (~20 EXP/hari) naik ke Level 2
    // dalam ±5 hari, bukan berbulan-bulan.
    function calculateLevel($exp) {
        return floor($exp / 100) + 1;
    }
}

if (!function_exists('calculateRank')) {
    function calculateRank($level) {
        if ($level >= 30) return 'S-Rank Sovereign';
        if ($level >= 22) return 'A-Rank Master';
        if ($level >= 15) return 'B-Rank Elite';
        if ($level >= 10) return 'C-Rank Vanguard';
        if ($level >= 6) return 'D-Rank Explorer';
        if ($level >= 3) return 'E-Rank Rookie';
        return 'F-Rank Novice';
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
    // Single source of truth for EXP rewards. $default preserves each
    // caller's legacy behavior for unknown input.
    function exp_for_difficulty($difficulty, $default = 5) {
        return match ($difficulty) {
            'Easy' => 5,
            'Medium' => 10,
            'Hard' => 15,
            default => $default,
        };
    }
}

if (!function_exists('skill_id_for_category')) {
    // Single source of truth for the category -> skill mapping.
    // Returns [skill_id, skill_name, skill_type] or null.
    function skill_id_for_category($category) {
        return match ($category) {
            'Wealth' => [1, 'Wealth', 'Wealth'],
            'Intelligence' => [2, 'Intelligence', 'Intelligence'],
            'Physical' => [3, 'Physical', 'Physical'],
            'Health' => [4, 'Health', 'Health'],
            'Social' => [5, 'Social', 'Social'],
            default => null,
        };
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

if (!function_exists('secure_session_start')) {
    // Session bootstrap with hardened cookie flags. Must run BEFORE
    // session_start() — callers must require database.php first.
    function secure_session_start() {
        if (session_status() !== PHP_SESSION_NONE) return;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ]);
        session_start();
    }
}

if (!function_exists('enforce_login')) {
    // Starts a hardened session, redirects guests, and enforces idle timeout.
    function enforce_login($loginPath = '../login.php', $timeoutSeconds = 1800) {
        secure_session_start();
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . $loginPath);
            exit();
        }
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeoutSeconds)) {
            session_unset();
            session_destroy();
            header("Location: " . $loginPath . "?timeout=1");
            exit();
        }
        $_SESSION['last_activity'] = time();
    }
}

if (!function_exists('run_daily_rollover')) {
    // THE single daily engine: evaluates missed habits, consumes one streak
    // freeze per user when available, applies HP damage, expires overdue
    // quests, resets daily flags, and stamps last_daily_check. Transactional
    // per user and idempotent (no-op if already rolled today).
    // Returns ['rolled' => bool, 'damage' => int, 'frozen' => bool, 'failed_quests' => int].
    function run_daily_rollover($pdo, $userId) {
        $today = date('Y-m-d');
        $result = ['rolled' => false, 'damage' => 0, 'frozen' => false, 'failed_quests' => 0];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT hp, streak_freeze, last_daily_check FROM users WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $pdo->rollBack();
                return $result;
            }
            if ($user['last_daily_check'] === $today) {
                $pdo->rollBack();
                return $result;
            }
            $isFirstRun = empty($user['last_daily_check']);

            if (!$isFirstRun) {
                // 1. Missed habits from yesterday
                $stmtMissed = $pdo->prepare("
                    SELECT habit_id, difficulty FROM habits
                    WHERE user_id = ? AND completed_today = 0
                ");
                $stmtMissed->execute([$userId]);
                $missed = $stmtMissed->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($missed)) {
                    if (($user['streak_freeze'] ?? 0) > 0) {
                        $pdo->prepare("UPDATE users SET streak_freeze = streak_freeze - 1 WHERE user_id = ?")
                            ->execute([$userId]);
                        $result['frozen'] = true;
                    } else {
                        $damage = 0;
                        foreach ($missed as $h) {
                            $damage += match ($h['difficulty']) {
                                'Easy' => 5,
                                'Medium' => 10,
                                'Hard' => 15,
                                default => 5,
                            };
                        }
                        $pdo->prepare("UPDATE users SET hp = GREATEST(0, hp - ?) WHERE user_id = ?")
                            ->execute([$damage, $userId]);
                        $pdo->prepare("UPDATE habits SET streak = 0 WHERE user_id = ?")
                            ->execute([$userId]);
                        $result['damage'] = $damage;

                        $logFail = $pdo->prepare("
                            INSERT INTO habit_logs (habit_id, user_id, completion_date, status, exp_reward)
                            VALUES (?, ?, CURDATE(), 'Failed', 0)
                        ");
                        foreach ($missed as $h) {
                            $logFail->execute([$h['habit_id'], $userId]);
                        }
                    }
                }

                // 2. Expired quests
                $stmtExp = $pdo->prepare("
                    SELECT quest_id, difficulty FROM quests
                    WHERE user_id = ? AND status = 'In Progress'
                      AND DATE_ADD(start_date, INTERVAL duration_days DAY) < CURDATE()
                ");
                $stmtExp->execute([$userId]);
                $expired = $stmtExp->fetchAll(PDO::FETCH_ASSOC);
                foreach ($expired as $q) {
                    $qd = match ($q['difficulty']) {
                        'Easy' => 10,
                        'Medium' => 20,
                        'Hard' => 30,
                        default => 10,
                    };
                    $pdo->prepare("UPDATE users SET hp = GREATEST(0, hp - ?) WHERE user_id = ?")
                        ->execute([$qd, $userId]);
                    $pdo->prepare("UPDATE quests SET status = 'Failed' WHERE quest_id = ?")
                        ->execute([$q['quest_id']]);
                    $pdo->prepare("INSERT INTO quest_logs (quest_id, user_id, status, completed_at, exp_reward) VALUES (?, ?, 'Failed', NOW(), 0)")
                        ->execute([$q['quest_id'], $userId]);
                    $result['damage'] += $qd;
                    $result['failed_quests']++;
                }

                // 3. Reset daily flags for the new day
                $pdo->prepare("UPDATE habits SET completed_today = 0, last_reset = NOW() WHERE user_id = ?")
                    ->execute([$userId]);
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

