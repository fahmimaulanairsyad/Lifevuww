<?php
// config/database.php

// Load environment variables from .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
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

