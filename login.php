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
function login_is_blocked($pdo, $ip, $username = '') {
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->execute();
    
    // Periksa total percobaan gagal dari IP ini
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    if ($stmt->fetchColumn() >= 5) {
        return true;
    }

    return false;
}

$username = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validate_csrf();
    $ip = get_client_ip();

    // 1-Click Guest/Demo Login handler
    if (isset($_POST['login_demo'])) {
        try {
            $demoId = ensure_demo_user($pdo);
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$demoId]);
            $demoUser = $stmt->fetch();

            if ($demoUser) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $demoUser['user_id'];
                $_SESSION['username'] = $demoUser['username'];
                $_SESSION['level'] = $demoUser['level'];
                $_SESSION['last_activity'] = time();
                header("Location: pages/dashboard.php");
                exit();
            }
        } catch (Exception $e) {
            error_log("Demo login error: " . $e->getMessage());
            $error = "Gagal memuat akun demo. Silakan coba lagi.";
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (login_is_blocked($pdo, $ip, $username)) {
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
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Masuk - Lifevuww</title>
    <meta name="description" content="Lifevuww adalah platform gamified habit tracker berbasis RPG. Bangun kebiasaan positif, tingkatkan level karaktermu, dan capai potensi terbaikmu.">
    
    <!-- OpenGraph & Social Cards -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://lifevuww.42web.io/login.php">
    <meta property="og:title" content="Lifevuww - Gamified Habit Tracker RPG">
    <meta property="og:description" content="Ubah rutinitas harianmu menjadi petualangan RPG yang seru dan terukur.">
    <meta property="og:image" content="assets/images/logo.svg">
    <meta name="twitter:card" content="summary">

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha384-3B6NwesSXE7YJlcLI9RpRqGf2p/EgVH8BgoKTaUrmKNDkHPStTQ3EyoYjCGXaOTS" crossorigin="anonymous">
    <link href="assets/css/style.css?v=20261026_01" rel="stylesheet">
    <style>
        .password-field-wrapper { position: relative; display: flex; align-items: center; width: 100%; }
        .password-field-wrapper .form-control { padding-right: 2.5rem !important; }
        .btn-toggle-password { position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); background: none !important; background-color: transparent !important; border: none !important; color: var(--text-muted, #71717a) !important; font-size: 0.85rem; cursor: pointer; padding: 0.35rem; line-height: 1; display: flex; align-items: center; justify-content: center; z-index: 2; outline: none !important; box-shadow: none !important; appearance: none; -webkit-appearance: none; }
        .btn-toggle-password:hover { color: var(--text-primary, #fafafa) !important; }
    </style>
    <script>
        (function() {
            var isCap = !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());
            var isMobile = isCap || window.innerWidth <= 768 || /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
            var root = document.documentElement;
            if (isMobile) { root.classList.add('is-mobile'); } else { root.classList.add('is-desktop'); }
            if (isCap) { root.classList.add('is-apk'); }
        })();

        // Register Service Worker from public page for seamless install
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('sw.js').catch(function(err) {
                    console.warn('SW registration failed:', err);
                });
            });
        }
    </script>
</head>
<body style="background-color: var(--bg-base); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1.5rem;">
    
    <div style="width: 100%; max-width: 380px;">
        <div class="mb-3">
            <a href="index.php" class="d-inline-flex align-items-center gap-2" style="color: var(--text-muted); font-size: 0.8rem; text-decoration: none; transition: color 0.15s ease;" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">
                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
            </a>
        </div>

        <div class="text-center mb-4">
            <a href="index.php" title="Kembali ke Beranda">
                <img src="assets/images/logo.svg" alt="Lifevuww Logo" style="width: 44px; height: 44px; margin-bottom: 1rem;">
            </a>
            <h1 style="font-size: 1.35rem; font-weight: 600; letter-spacing: -0.02em;">Selamat Datang di Lifevuww</h1>
            <p style="color: var(--text-secondary); font-size: 0.85rem;">Masuk untuk mengakses sistem progres hidupmu</p>
        </div>

        <div class="bento-panel p-4">
            <?php if (isset($error)): ?>
                <div class="p-2 mb-3 text-center" role="alert" aria-live="assertive" style="background-color: var(--status-danger-dim); border: 1px solid rgba(244, 63, 94, 0.3); color: var(--status-danger); border-radius: var(--radius); font-size: 0.8rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label for="login_username" class="form-label">Nama pengguna</label>
                    <input type="text" id="login_username" name="username" class="form-control" required autofocus placeholder="Masukkan nama penggunamu" value="<?php echo htmlspecialchars($username); ?>">
                </div>
                <div class="mb-4">
                    <label for="login_password" class="form-label">Kata sandi</label>
                    <div class="password-field-wrapper" style="position: relative; display: flex; align-items: center; width: 100%;">
                        <input type="password" id="login_password" name="password" class="form-control" required placeholder="••••••••" style="padding-right: 2.5rem !important;">
                        <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('login_password', this)" aria-label="Tampilkan atau sembunyikan kata sandi" style="position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); background: none !important; border: none !important; color: var(--text-muted, #71717a); cursor: pointer; padding: 0.35rem; display: flex; align-items: center; justify-content: center; z-index: 2;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" id="btnSubmitLogin" class="btn-core btn-primary w-100" style="padding: 0.65rem;">
                    <span class="btn-text">Masuk</span>
                </button>
            </form>

            <div class="d-flex align-items-center my-3" style="gap: 0.75rem;">
                <hr style="flex: 1; border-color: var(--border-hairline); margin: 0;">
                <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">atau</span>
                <hr style="flex: 1; border-color: var(--border-hairline); margin: 0;">
            </div>

            <!-- 1-Click Demo Login -->
            <form method="POST" id="demoLoginForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="login_demo" value="1">
                <button type="submit" class="btn-core btn-outline w-100" style="padding: 0.6rem; font-size: 0.82rem;">
                    <i class="fas fa-play text-accent me-1"></i> Coba Akun Demo (Guest Mode)
                </button>
            </form>
        </div>

        <p class="text-center mt-3" style="color: var(--text-secondary); font-size: 0.82rem;">
            Belum punya akun? <a href="register.php" style="color: var(--text-primary); font-weight: 500;">Daftar di sini</a>
        </p>
    </div>

    <script>
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmitLogin');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Memproses...';
        });
    </script>
</body>
</html>