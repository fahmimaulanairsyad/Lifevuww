<?php
require_once __DIR__ . '/../config/database.php';
secure_session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - Lifevuww' : 'Lifevuww'; ?></title>
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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha384-3B6NwesSXE7YJlcLI9RpRqGf2p/EgVH8BgoKTaUrmKNDkHPStTQ3EyoYjCGXaOTS" crossorigin="anonymous">
    <!-- Unified Stylesheet (desktop + mobile + APK via media queries) -->
    <link href="../assets/css/style.css?v=20261025_02" rel="stylesheet">
    <style>
        /* Scoped resilience styles for filters & heatmap */
        .filter-pills-category { display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.4rem; margin-bottom: 1.25rem; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
        .filter-pills-category::-webkit-scrollbar { display: none; }
        .btn-filter-pill { appearance: none !important; -webkit-appearance: none !important; background-color: var(--bg-surface-hover) !important; border: 1px solid var(--border-hairline) !important; color: var(--text-secondary) !important; font-size: 0.78rem !important; font-family: var(--font-sans) !important; padding: 0.35rem 0.85rem !important; border-radius: 9999px !important; cursor: pointer; white-space: nowrap; transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 0.4rem; outline: none !important; box-shadow: none !important; line-height: 1.2; }
        .btn-filter-pill:hover { color: var(--text-primary) !important; border-color: var(--border-subtle) !important; background-color: var(--bg-surface-hover) !important; }
        .btn-filter-pill.active { background-color: var(--accent-dim) !important; color: var(--accent) !important; border-color: var(--accent) !important; font-weight: 600; }
        .heatmap-track { display: flex !important; flex-wrap: wrap !important; gap: 6px !important; align-items: center !important; max-width: 100% !important; }
        .heatmap-cell { display: inline-block !important; width: 16px !important; height: 16px !important; border-radius: 3px !important; flex-shrink: 0 !important; }
    </style>
    <!-- Platform Tagging (Desktop vs Mobile vs APK) -->
    <script>
        (function() {
            var isCap = !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());
            var isMobile = isCap || window.innerWidth <= 768 || /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
            var root = document.documentElement;
            if (isMobile) { root.classList.add('is-mobile'); } else { root.classList.add('is-desktop'); }
            if (isCap) { root.classList.add('is-apk'); }
        })();
    </script>
    <!-- Confetti library for Dopamine feedback -->
    <script defer src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js" integrity="sha384-HAH79XdRvHr6axVGh4xQWVCp14kcd32bNk4Xu0sHDHtFQ42n6BAM8ykvB47dGz6D" crossorigin="anonymous"></script>
</head>
<body>
    <div class="app-layout">
        <?php 
        if(isset($_SESSION['user_id'])): 
            // Daily rollover sudah dijalankan di enforce_login() sebelum handler halaman.

            $stmt = $pdo->prepare("SELECT hp, max_hp, gold, exp, streak_freeze, last_daily_check, reminder_time FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $navUser = $stmt->fetch(PDO::FETCH_ASSOC);

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
            // Data level & EXP untuk sidebar dan mobile header
            $navExp = (int) ($navUser['exp'] ?? 0);
            $navLevel = calculateLevel($navExp);
            $navNextExp = $navLevel * 100;
            $navCurBase = ($navLevel - 1) * 100;
            $navExpPct = min(100, max(0, (($navExp - $navCurBase) / max(1, ($navNextExp - $navCurBase))) * 100));
        ?>
        <!-- Dedicated Mobile Top App Bar -->
        <header class="mobile-top-bar">
            <a href="dashboard.php" class="mobile-brand">
                <img src="../assets/images/logo.svg" alt="Logo" class="mobile-logo">
                <span class="mobile-brand-title">Lifevuww</span>
            </a>
            <div class="mobile-vitals">
                <button type="button" class="btn-core btn-ghost p-1 d-md-none text-muted" onclick="window.openCommandPalette && window.openCommandPalette();" aria-label="Buka Menu Cepat">
                    <i class="fas fa-search"></i>
                </button>
                <span class="mobile-chip font-mono text-accent" style="border-color: rgba(16, 185, 129, 0.25); background-color: var(--accent-dim);">
                    Lv.<?php echo $navLevel; ?>
                </span>
                <span class="mobile-chip hp">
                    <i class="fas fa-heart"></i> <?php echo $navUser['hp'] ?? 100; ?>
                </span>
                <span class="mobile-chip gold">
                    <i class="fas fa-coins"></i> <?php echo number_format($navUser['gold'] ?? 0); ?>
                </span>
                <a href="profile.php" class="mobile-profile-link" title="Profil" aria-label="Profil Pengguna">
                    <i class="fas fa-user-circle"></i>
                </a>
            </div>
        </header>

        <aside class="app-sidebar">
            <div class="sidebar-header">
                <img src="../assets/images/logo.svg" alt="Logo" style="width: 24px; height: 24px;">
                <span class="brand-name">Lifevuww</span>
            </div>
            
            <div class="sidebar-vitals">
                <!-- HP -->
                <div class="vitals-row">
                    <span class="vitals-label"><i class="fas fa-heart text-danger me-1"></i> HP</span>
                    <span class="vitals-value font-mono text-danger"><?php echo $navUser['hp'] ?? 100; ?> / <?php echo $navUser['max_hp'] ?? 100; ?></span>
                </div>
                <div class="vitals-track mb-2">
                    <div class="vitals-fill" style="width: <?php echo min(100, max(0, (($navUser['hp'] ?? 100) / ($navUser['max_hp'] ?? 100)) * 100)); ?>%; background-color: var(--status-danger);"></div>
                </div>

                <!-- EXP Progress Bar -->
                <div class="vitals-row">
                    <span class="vitals-label"><i class="fas fa-bolt text-accent me-1"></i> Tingkat <?php echo $navLevel; ?></span>
                    <span class="vitals-value font-mono text-accent" style="font-size: 0.72rem;"><?php echo $navExp; ?> / <?php echo $navNextExp; ?> EXP</span>
                </div>
                <div class="vitals-track mb-3">
                    <div class="vitals-fill" style="width: <?php echo $navExpPct; ?>%; background-color: var(--accent);"></div>
                </div>
                
                <!-- Gold -->
                <div class="vitals-row">
                    <span class="vitals-label"><i class="fas fa-coins text-warning me-1"></i> Gold</span>
                    <span class="vitals-value font-mono text-warning"><?php echo number_format($navUser['gold'] ?? 0); ?></span>
                </div>
            </div>

            <!-- Command Palette Trigger (Desktop) -->
            <div class="px-2 pt-1 pb-1 d-none d-md-block">
                <button type="button" class="btn-command-palette-trigger w-100 d-flex align-items-center justify-content-between" style="background: var(--bg-surface-hover); border: 1px solid var(--border-hairline); border-radius: var(--radius); padding: 0.4rem 0.65rem; color: var(--text-muted); font-size: 0.75rem; cursor: pointer;" onclick="window.openCommandPalette && window.openCommandPalette();">
                    <span><i class="fas fa-search me-1 text-muted"></i> Menu Cepat</span>
                    <kbd class="font-mono" style="background: var(--bg-base); border: 1px solid var(--border-subtle); color: var(--text-secondary); padding: 0.1rem 0.35rem; border-radius: 4px; font-size: 0.65rem;">Ctrl+K</kbd>
                </button>
            </div>

            <nav class="sidebar-nav">
                <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
                <a href="dashboard.php" class="nav-item <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" title="Dasbor" aria-label="Dasbor">
                    <i class="fas fa-home"></i> <span>Dasbor</span>
                </a>
                <a href="habits.php" class="nav-item <?php echo $currentPage == 'habits.php' ? 'active' : ''; ?>" title="Kebiasaan" aria-label="Kebiasaan">
                    <i class="fas fa-check-square"></i> <span>Kebiasaan</span>
                </a>
                <a href="quests.php" class="nav-item <?php echo $currentPage == 'quests.php' ? 'active' : ''; ?>" title="Misi" aria-label="Misi">
                    <i class="fas fa-map-signs"></i> <span>Misi</span>
                </a>
                <a href="skills.php" class="nav-item d-none d-md-flex <?php echo $currentPage == 'skills.php' ? 'active' : ''; ?>" title="Keahlian" aria-label="Keahlian">
                    <i class="fas fa-book-open"></i> <span>Keahlian</span>
                </a>
                <a href="shop.php" class="nav-item <?php echo $currentPage == 'shop.php' ? 'active' : ''; ?>" title="Toko" aria-label="Toko Hadiah">
                    <i class="fas fa-store"></i> <span>Toko</span>
                </a>
                <a href="history.php" class="nav-item d-none d-md-flex <?php echo $currentPage == 'history.php' ? 'active' : ''; ?>" title="Riwayat" aria-label="Riwayat Aktivitas">
                    <i class="fas fa-history"></i> <span>Riwayat</span>
                </a>
                <a href="profile.php" class="nav-item d-md-none <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>" title="Profil" aria-label="Profil Pengguna">
                    <i class="fas fa-user-circle"></i> <span>Profil</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="profile.php" class="nav-item <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>" aria-label="Profil Pengguna">
                    <i class="fas fa-user-circle"></i> <span>Profil</span>
                </a>
                <button type="button" id="btn-sound" class="nav-item btn-sound-toggle" aria-label="Nyalakan atau matikan efek suara" style="background: none; border: none; width: 100%; cursor: pointer; font-family: inherit; text-align: left;">
                    <i class="fas fa-volume-up"></i> <span>Suara nyala</span>
                </button>
                <a href="../logout.php" class="nav-item" aria-label="Keluar dari akun" style="color: var(--text-tertiary);">
                    <i class="fas fa-sign-out-alt"></i> <span>Keluar</span>
                </a>
            </div>
        </aside>
        <?php endif; ?>

        <main class="app-main">
            <div class="main-container">
