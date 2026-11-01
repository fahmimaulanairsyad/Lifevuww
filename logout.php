<?php
// logout.php
require_once __DIR__ . '/config/database.php';
secure_session_start();

// Cabut token remember-me agar perangkat ini tidak auto-login kembali,
// lalu hapus cookie sesi persisten (session_destroy tidak menghapus cookie klien).
if (isset($pdo) && function_exists('lv_revoke_remember_token')) {
    lv_revoke_remember_token($pdo);
}
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $p['path'],
            'domain' => $p['domain'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

// Hapus cache untuk memastikan halaman sebelumnya tidak dapat diakses
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect ke halaman login
header("Location: login.php");
exit();
?>
