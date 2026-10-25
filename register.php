<?php
// register.php
require_once __DIR__ . '/config/database.php';
secure_session_start();

$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validate_csrf();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rawPassword = $_POST['password'] ?? '';

    // Validasi server-side
    if (strlen($username) < 3) {
        $error = "Nama pengguna minimal 3 karakter.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Masukkan alamat email yang valid.";
    } elseif (strlen($rawPassword) < 8) {
        $error = "Kata sandi minimal 8 karakter.";
    } elseif (strlen($rawPassword) > 72) {
        $error = "Kata sandi maksimal 72 karakter.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existing_user = $stmt->fetch();

        if ($existing_user) {
            $error = "Nama pengguna atau email sudah dipakai.";
        } else {
            $password = password_hash($rawPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
            $stmt->execute([$username, $password, $email]);
            header("Location: login.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Daftar - Lifevuww</title>
    <meta name="description" content="Daftarkan akun Lifevuww baru dan mulai petualangan produktivitas RPG-mu sekarang.">
    
    <!-- OpenGraph & Social Cards -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://lifevuww.42web.io/register.php">
    <meta property="og:title" content="Daftar Lifevuww - Gamified Habit Tracker RPG">
    <meta property="og:description" content="Bangun kebiasaan positif dan naikkan level karaktermu di dunia nyata.">
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
    <link href="assets/css/style.css?v=20261025_03" rel="stylesheet">
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
            <h1 style="font-size: 1.35rem; font-weight: 600; letter-spacing: -0.02em;">Buat Karakter Baru</h1>
            <p style="color: var(--text-secondary); font-size: 0.85rem;">Daftarkan profilmu di sistem progres Lifevuww</p>
        </div>

        <div class="bento-panel p-4">
            <?php if (isset($_GET['account_deleted'])): ?>
                <div class="p-2 mb-3 text-center" role="status" style="background-color: var(--accent-dim); border: 1px solid var(--accent-border); color: var(--accent); border-radius: var(--radius); font-size: 0.8rem;">
                    <i class="fas fa-check-circle me-1"></i> Akun dan seluruh datamu telah dihapus secara permanen.
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="p-2 mb-3 text-center" role="alert" aria-live="assertive" style="background-color: var(--status-danger-dim); border: 1px solid rgba(244, 63, 94, 0.3); color: var(--status-danger); border-radius: var(--radius); font-size: 0.8rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="regForm">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label for="reg_username" class="form-label">Nama pengguna</label>
                    <input type="text" id="reg_username" name="username" class="form-control" required autofocus placeholder="mis., Mivuww" value="<?php echo htmlspecialchars($username); ?>">
                </div>
                <div class="mb-3">
                    <label for="reg_email" class="form-label">Alamat email</label>
                    <input type="email" id="reg_email" name="email" class="form-control" required placeholder="nama@domain.com" value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <div class="mb-3">
                    <label for="reg_password" class="form-label">Kata sandi</label>
                    <div class="password-field-wrapper" style="position: relative; display: flex; align-items: center; width: 100%;">
                        <input type="password" id="reg_password" name="password" class="form-control" required placeholder="••••••••" style="padding-right: 2.5rem !important;">
                        <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('reg_password', this)" aria-label="Tampilkan atau sembunyikan kata sandi" style="position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); background: none !important; border: none !important; color: var(--text-muted, #71717a); cursor: pointer; padding: 0.35rem; display: flex; align-items: center; justify-content: center; z-index: 2;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="passHint" style="font-size: 0.73rem; color: var(--text-muted); margin-top: 0.35rem; transition: color 0.15s ease;">
                        <i class="fas fa-circle-notch me-1"></i> Minimal 8 karakter
                    </div>
                </div>
                <button type="submit" id="btnSubmitReg" class="btn-core btn-primary w-100 mt-2" style="padding: 0.65rem;">
                    <span class="btn-text">Buat Akun</span>
                </button>
            </form>
        </div>

        <p class="text-center mt-3" style="color: var(--text-secondary); font-size: 0.82rem;">
            Sudah terdaftar? <a href="login.php" style="color: var(--text-primary); font-weight: 500;">Masuk di sini</a>
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

        const passInput = document.getElementById('reg_password');
        const passHint = document.getElementById('passHint');
        passInput.addEventListener('input', function() {
            if (this.value.length >= 8) {
                passHint.style.color = 'var(--accent)';
                passHint.innerHTML = '<i class="fas fa-check-circle me-1"></i> Panjang sandi memenuhi syarat (min. 8 karakter)';
            } else {
                passHint.style.color = 'var(--text-muted)';
                passHint.innerHTML = '<i class="fas fa-circle-notch me-1"></i> Minimal 8 karakter (' + this.value.length + '/8)';
            }
        });

        document.getElementById('regForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmitReg');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Memproses...';
        });
    </script>
</body>
</html>