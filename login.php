<?php
// login.php
require_once __DIR__ . '/config/database.php';
secure_session_start();

// Check if already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard.php");
    exit();
}

// Simple brute-force protection: max 5 gagal per IP per 15 menit.
function login_is_blocked($pdo, $ip) {
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->execute();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    return $stmt->fetchColumn() >= 5;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validate_csrf();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login_is_blocked($pdo, $ip)) {
        $error = "Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Catatan: percobaan gagal TIDAK dihapus saat login sukses. Kalau dihapus,
            // penyerang cukup login ke akun miliknya sendiri di sela tebakan untuk mereset limit.
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['level'] = $user['level'];
            $_SESSION['last_activity'] = time();
            header("Location: pages/dashboard.php");
            exit();
        } else {
            $pdo->prepare("INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())")->execute([$ip]);
            $error = "Nama pengguna atau kata sandi salah";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Masuk - Lifevuww</title>
    <!-- Favicon -->
    <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml">
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#09090b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link href="assets/css/style.css?v=6" rel="stylesheet">
    <script>
        (function() {
            var isCap = !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());
            var isMobile = isCap || window.innerWidth <= 768 || /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
            var root = document.documentElement;
            if (isMobile) { root.classList.add('is-mobile'); } else { root.classList.add('is-desktop'); }
            if (isCap) { root.classList.add('is-apk'); }
        })();
    </script>
</head>
<body style="background-color: var(--bg-base); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1.5rem;">
    
    <div style="width: 100%; max-width: 380px;">
        <div class="text-center mb-4">
            <img src="assets/images/logo.svg" alt="Lifevuww Logo" style="width: 44px; height: 44px; margin-bottom: 1rem;">
            <h1 style="font-size: 1.35rem; font-weight: 600; letter-spacing: -0.02em;">Selamat Datang di Lifevuww</h1>
            <p class="text-muted" style="font-size: 0.82rem;">Masuk untuk mengakses sistem progres hidupmu</p>
        </div>

        <div class="bento-panel p-4">
            <?php if (isset($error)): ?>
                <div class="p-2 mb-3 text-center" style="background-color: var(--status-danger-dim); border: 1px solid rgba(244, 63, 94, 0.3); color: var(--status-danger); border-radius: var(--radius); font-size: 0.8rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label for="login_username" class="form-label">Nama pengguna</label>
                    <input type="text" id="login_username" name="username" class="form-control" required autofocus placeholder="Masukkan nama penggunamu">
                </div>
                <div class="mb-4">
                    <label for="login_password" class="form-label">Kata sandi</label>
                    <input type="password" id="login_password" name="password" class="form-control" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn-core btn-primary w-100" style="padding: 0.65rem;">Masuk</button>
            </form>
        </div>

        <p class="text-center mt-3" style="color: var(--text-muted); font-size: 0.8rem;">
            Belum punya akun? <a href="register.php" style="color: var(--text-primary); font-weight: 500;">Daftar di sini</a>
        </p>
    </div>

</body>
</html>