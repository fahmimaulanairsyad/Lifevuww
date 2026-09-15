<?php
// register.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validate_csrf();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rawPassword = $_POST['password'] ?? '';

    // Validasi server-side
    if (strlen($username) < 3) {
        $error = "Username must be at least 3 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif (strlen($rawPassword) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existing_user = $stmt->fetch();

        if ($existing_user) {
            $error = "Username or email already exists.";
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Lifevuww</title>
    <!-- Favicon -->
    <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body style="background-color: var(--bg-base); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1.5rem;">
    
    <div style="width: 100%; max-width: 380px;">
        <div class="text-center mb-4">
            <img src="assets/images/logo.svg" alt="Lifevuww Logo" style="width: 44px; height: 44px; margin-bottom: 1rem;">
            <h1 style="font-size: 1.35rem; font-weight: 600; letter-spacing: -0.02em;">Initialize Character</h1>
            <p class="text-muted" style="font-size: 0.82rem;">Create your profile in the Lifevuww progression system</p>
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
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required autofocus placeholder="e.g., Mivuww">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="user@domain.com">
                </div>
                <div class="mb-4">
                    <label class="form-label">Password (min. 6 chars)</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn-core btn-primary w-100" style="padding: 0.65rem;">Create Character</button>
            </form>
        </div>

        <p class="text-center mt-3" style="color: var(--text-muted); font-size: 0.8rem;">
            Already registered? <a href="login.php" style="color: var(--text-primary); font-weight: 500;">Sign in here</a>
        </p>
    </div>

</body>
</html>