<?php
// login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';

// Check if already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validate_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['level'] = $user['level'];
        header("Location: pages/dashboard.php");
        exit();
    } else {
        $error = "Nama pengguna atau kata sandi salah";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
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
                    <label class="form-label">Nama pengguna</label>
                    <input type="text" name="username" class="form-control" required autofocus placeholder="Masukkan nama penggunamu">
                </div>
                <div class="mb-4">
                    <label class="form-label">Kata sandi</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
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