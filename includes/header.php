<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lifevuww</title>
    <!-- Favicon -->
    <link rel="icon" href="../assets/images/logo.svg" type="image/svg+xml">
    <!-- PWA -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#09090b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Lifevuww">
    <link rel="apple-touch-icon" href="../assets/icons/apple-touch-icon.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <!-- Confetti library for Dopamine feedback -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
</head>
<body>
    <div class="app-layout">
        <?php 
        if(isset($_SESSION['user_id'])): 
            $stmt = $pdo->prepare("SELECT hp, max_hp, gold, exp, streak_freeze, last_daily_check, reminder_time FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $navUser = $stmt->fetch(PDO::FETCH_ASSOC);

            // ==========================================
            // AUTO DAILY-CHECK ENGINE (UNTUK LOCALHOST)
            // ==========================================
            $today = date('Y-m-d');
            if ($navUser && $navUser['last_daily_check'] !== $today) {
                // Hari baru terdeteksi! Jalankan evaluasi habit hari kemarin
                if (!empty($navUser['last_daily_check'])) {
                    // Cek habit yang tidak selesai
                    $stmtMissed = $pdo->prepare("
                        SELECT habit_id, difficulty, streak 
                        FROM habits 
                        WHERE user_id = ? AND completed_today = 0
                    ");
                    $stmtMissed->execute([$_SESSION['user_id']]);
                    $missedHabits = $stmtMissed->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($missedHabits)) {
                        // Cek apakah punya Streak Freeze
                        if (($navUser['streak_freeze'] ?? 0) > 0) {
                            // Pakai 1 freeze
                            $pdo->prepare("UPDATE users SET streak_freeze = streak_freeze - 1 WHERE user_id = ?")
                                ->execute([$_SESSION['user_id']]);
                            $navUser['streak_freeze']--;
                        } else {
                            // Hitung damage HP
                            $totalDamage = 0;
                            foreach ($missedHabits as $h) {
                                $damage = match ($h['difficulty']) {
                                    'Easy' => 5,
                                    'Medium' => 10,
                                    'Hard' => 15,
                                    default => 5,
                                };
                                $totalDamage += $damage;
                            }
                            // Kurangi HP & putuskan streak
                            $pdo->prepare("UPDATE users SET hp = GREATEST(0, hp - ?) WHERE user_id = ?")
                                ->execute([$totalDamage, $_SESSION['user_id']]);
                            $pdo->prepare("UPDATE habits SET streak = 0 WHERE user_id = ?")
                                ->execute([$_SESSION['user_id']]);
                            $navUser['hp'] = max(0, ($navUser['hp'] ?? 100) - $totalDamage);
                        }
                    }

                    // Reset status completed_today = 0 untuk semua habit agar siap dikerjakan hari ini
                    $pdo->prepare("UPDATE habits SET completed_today = 0, last_reset = NOW() WHERE user_id = ?")
                        ->execute([$_SESSION['user_id']]);
                }

                // Update tanggal pengecekan terakhir ke hari ini
                $pdo->prepare("UPDATE users SET last_daily_check = ? WHERE user_id = ?")
                    ->execute([$today, $_SESSION['user_id']]);
                $navUser['last_daily_check'] = $today;
            }

            // ==========================================
            // LOGIKA GAME OVER / DEATH PENALTY (HP <= 0)
            // ==========================================
            if (($navUser['hp'] ?? 100) <= 0) {
                // Penalti: Kehilangan 50% current EXP
                $lostExp = floor(($navUser['exp'] ?? 0) * 0.5);
                $newExp = max(0, ($navUser['exp'] ?? 0) - $lostExp);
                $newLevel = calculateLevel($newExp);

                // Pulihkan HP ke 100, potong EXP
                $pdo->prepare("UPDATE users SET hp = max_hp, exp = ?, level = ? WHERE user_id = ?")
                    ->execute([$newExp, $newLevel, $_SESSION['user_id']]);

                // Reset semua streak habit ke 0
                $pdo->prepare("UPDATE habits SET streak = 0 WHERE user_id = ?")
                    ->execute([$_SESSION['user_id']]);

                // Set alert session untuk ditampilkan
                $_SESSION['game_over'] = [
                    'lost_exp' => $lostExp,
                    'current_exp' => $newExp
                ];

                // Update data untuk render sidebar
                $navUser['hp'] = $navUser['max_hp'] ?? 100;
                $navUser['exp'] = $newExp;
            }

            // ==========================================
            // STREAK NAG ENGINE (Duolingo-style reminder)
            // Dihitung sekali per request. Dirender sebagai banner
            // + browser Notification di footer.php.
            // ==========================================
            $nagIncomplete = 0;
            $nagMaxStreak = 0;
            try {
                $stmtNag = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE user_id = ? AND completed_today = 0");
                $stmtNag->execute([$_SESSION['user_id']]);
                $nagIncomplete = (int) $stmtNag->fetchColumn();

                $stmtStreak = $pdo->prepare("SELECT COALESCE(MAX(streak), 0) FROM habits WHERE user_id = ?");
                $stmtStreak->execute([$_SESSION['user_id']]);
                $nagMaxStreak = (int) $stmtStreak->fetchColumn();
            } catch (Exception $e) {
                error_log("Nag engine error: " . $e->getMessage());
            }

            $reminderTime = $navUser['reminder_time'] ?? '20:00:00';
            $reminderShort = substr($reminderTime, 0, 5); // HH:MM
            $nowShort = date('H:i');
            // Tampilkan nag hanya jika jam pengingat sudah lewat DAN masih ada habit belum selesai
            if ($nagIncomplete > 0 && $nowShort >= $reminderShort) {
                $_SESSION['streak_nag'] = [
                    'incomplete' => $nagIncomplete,
                    'max_streak' => $nagMaxStreak,
                    'reminder' => $reminderShort
                ];
            }
        ?>
        <aside class="app-sidebar">
            <div class="sidebar-header">
                <img src="../assets/images/logo.svg" alt="Logo" style="width: 24px; height: 24px;">
                <span class="brand-name">Lifevuww</span>
            </div>
            
            <div class="sidebar-vitals">
                <div class="vitals-row">
                    <span class="vitals-label"><i class="fas fa-heart text-danger me-1"></i> HP</span>
                    <span class="vitals-value font-mono text-danger"><?php echo $navUser['hp'] ?? 100; ?> / <?php echo $navUser['max_hp'] ?? 100; ?></span>
                </div>
                <div class="vitals-track mb-3">
                    <div class="vitals-fill" style="width: <?php echo min(100, max(0, (($navUser['hp'] ?? 100) / ($navUser['max_hp'] ?? 100)) * 100)); ?>%; background-color: var(--status-danger);"></div>
                </div>
                
                <div class="vitals-row">
                    <span class="vitals-label"><i class="fas fa-coins text-warning me-1"></i> Gold</span>
                    <span class="vitals-value font-mono text-warning"><?php echo number_format($navUser['gold'] ?? 0); ?></span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
                <a href="dashboard.php" class="nav-item <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> <span>Dasbor</span>
                </a>
                <a href="habits.php" class="nav-item <?php echo $currentPage == 'habits.php' ? 'active' : ''; ?>">
                    <i class="fas fa-check-square"></i> <span>Kebiasaan</span>
                </a>
                <a href="quests.php" class="nav-item <?php echo $currentPage == 'quests.php' ? 'active' : ''; ?>">
                    <i class="fas fa-map-signs"></i> <span>Misi</span>
                </a>
                <a href="skills.php" class="nav-item <?php echo $currentPage == 'skills.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book-open"></i> <span>Keahlian</span>
                </a>
                <a href="shop.php" class="nav-item <?php echo $currentPage == 'shop.php' ? 'active' : ''; ?>">
                    <i class="fas fa-store"></i> <span>Toko</span>
                </a>
                <!-- Mobile only profile link -->
                <a href="profile.php" class="nav-item d-md-none <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i> <span>Profil</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="profile.php" class="nav-item <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i> <span>Profil</span>
                </a>
                <button type="button" id="btn-sound" class="nav-item" style="background: none; border: none; width: 100%; cursor: pointer; font-family: inherit; text-align: left;">
                    <i class="fas fa-volume-up"></i> <span>Suara nyala</span>
                </button>
                <a href="../logout.php" class="nav-item" style="color: var(--text-tertiary);">
                    <i class="fas fa-sign-out-alt"></i> <span>Keluar</span>
                </a>
            </div>
        </aside>
        <?php endif; ?>

        <main class="app-main">
            <div class="main-container">
