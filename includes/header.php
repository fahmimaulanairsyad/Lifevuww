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
    <link href="../assets/css/style.css?v=20261030_01" rel="stylesheet">
    <!-- Motion Libraries: GSAP via CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
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
    <!-- Aura Theme Engine Initializer (Zero FOUT) -->
    <script>
        (function() {
            var saved = localStorage.getItem('lv_aura_theme');
            var aura = saved || 'emerald';
            document.documentElement.setAttribute('data-aura', aura);
        })();
    </script>
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

            $stmt = $pdo->prepare("SELECT hp, max_hp, gold, exp, streak_freeze, last_daily_check, reminder_time, specialization FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $navUser = $stmt->fetch(PDO::FETCH_ASSOC);

            // Sync user specialization to session
            if (!empty($navUser['specialization'])) {
                $_SESSION['user_specialization'] = $navUser['specialization'];
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
            // Data level & EXP untuk HUD dan mobile header
            $navExp = (int) ($navUser['exp'] ?? 0);
            $navLevel = calculateLevel($navExp);
            $navNextExp = $navLevel * 100;
            $navCurBase = ($navLevel - 1) * 100;
            $navExpPct = min(100, max(0, (($navExp - $navCurBase) / max(1, ($navNextExp - $navCurBase))) * 100));
            $userRankTitle = calculateRank($navLevel);
        ?>
        <!-- Film Grain Noise Overlay (Cinematic physical depth) -->
        <div class="film-grain"></div>

        <!-- Custom Magnetic Cursor Follower (Desktop only) -->
        <div class="cursor-dot" id="cursorDot"></div>
        <div class="cursor-ring" id="cursorRing"></div>

        <!-- Top Vignette Blur Shield for HUD -->
        <div class="top-vignette-shield"></div>

        <!-- Top Telemetry Cockpit HUD -->
        <header class="telemetry-hud">
            <div class="hud-inner">
                <!-- Brand & Rank Title -->
                <a href="dashboard.php" class="hud-brand">
                    <img src="../assets/images/logo.svg" alt="Logo" class="hud-logo">
                    <span class="hud-title">Lifevuww</span>
                    <span class="hud-rank-pill font-mono d-none d-sm-inline-block">
                        <?php echo htmlspecialchars($userRankTitle); ?>
                    </span>
                </a>

                <!-- Telemetry Control Center (Right) -->
                <div class="hud-telemetry">
                    <!-- Unified Glass Status Capsule -->
                    <div class="hud-unified-capsule">
                        <div class="hud-cap-item text-danger" title="Poin Kesehatan (HP)">
                            <i class="fas fa-heart"></i>
                            <span class="font-mono" id="hudHpVal"><?php echo $navUser['hp'] ?? 100; ?></span>
                        </div>
                        <div class="hud-cap-divider"></div>
                        <div class="hud-cap-item text-accent" title="Tingkat <?php echo $navLevel; ?> (<?php echo $navExp; ?>/<?php echo $navNextExp; ?> EXP)">
                            <i class="fas fa-bolt"></i>
                            <span class="font-mono" id="hudLevelVal">Lv.<?php echo $navLevel; ?></span>
                            <span class="hud-cap-sub d-none d-md-inline font-mono"><?php echo $navExp; ?>/<?php echo $navNextExp; ?></span>
                        </div>
                        <div class="hud-cap-divider"></div>
                        <div class="hud-cap-item text-warning" title="Saldo Gold">
                            <i class="fas fa-coins"></i>
                            <span class="font-mono" id="hudGoldVal"><?php echo number_format($navUser['gold'] ?? 0); ?></span>
                        </div>
                    </div>

                    <!-- Focus Rift Timer Trigger -->
                    <button type="button" class="hud-cmd-trigger" onclick="window.openFocusRiftModal && window.openFocusRiftModal();" title="🌀 Focus Rift — Pomodoro Battle Timer">
                        <i class="fas fa-hourglass-half text-accent"></i>
                        <span class="font-mono d-none d-lg-inline ms-1" style="font-size: 0.72rem;">Rift</span>
                    </button>

                    <!-- Quick Command Trigger [Ctrl+K / ⌘K] -->
                    <button type="button" class="hud-cmd-trigger" onclick="window.openCommandPalette && window.openCommandPalette();" title="Menu Pintasan & Pencarian Cepat (Ctrl+K)">
                        <i class="fas fa-search"></i>
                        <kbd class="font-mono d-none d-md-inline ms-1 cmd-shortcut-kbd">Ctrl+K</kbd>
                    </button>

                    <!-- Sound Toggle -->
                    <button type="button" class="btn-sound-toggle hud-btn-icon" aria-label="Nyalakan atau matikan suara" title="Audio">
                        <i class="fas fa-volume-up"></i>
                    </button>

                    <!-- Profile Avatar (Desktop HUD) -->
                    <a href="profile.php" class="hud-profile-btn d-none d-md-inline-flex" title="Profil" aria-label="Profil Pengguna">
                        <i class="fas fa-user-circle"></i>
                    </a>

                    <!-- Logout Quick Action -->
                    <a href="../logout.php" class="hud-btn-icon text-muted" title="Keluar dari akun" aria-label="Keluar dari akun" data-confirm="Yakin ingin keluar dari akun?" data-confirm-title="Keluar Akun" data-confirm-variant="danger" data-confirm-btn="Keluar">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Floating Magnetic Glass Dock -->
        <nav class="floating-magnetic-dock" id="floatingDock">
            <div class="dock-container">
                <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
                <a href="dashboard.php" class="dock-item <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" data-tooltip="Dasbor">
                    <i class="fas fa-home"></i>
                    <span class="dock-label">Dasbor</span>
                </a>
                <a href="habits.php" class="dock-item <?php echo $currentPage == 'habits.php' ? 'active' : ''; ?>" data-tooltip="Kebiasaan">
                    <i class="fas fa-check-square"></i>
                    <span class="dock-label">Kebiasaan</span>
                </a>
                <a href="quests.php" class="dock-item <?php echo $currentPage == 'quests.php' ? 'active' : ''; ?>" data-tooltip="Misi">
                    <i class="fas fa-map-signs"></i>
                    <span class="dock-label">Misi</span>
                </a>
                <a href="skills.php" class="dock-item <?php echo $currentPage == 'skills.php' ? 'active' : ''; ?>" data-tooltip="Keahlian">
                    <i class="fas fa-book-open"></i>
                    <span class="dock-label">Keahlian</span>
                </a>
                <a href="shop.php" class="dock-item <?php echo $currentPage == 'shop.php' ? 'active' : ''; ?>" data-tooltip="Toko">
                    <i class="fas fa-store"></i>
                    <span class="dock-label">Toko</span>
                </a>
                <a href="history.php" class="dock-item <?php echo $currentPage == 'history.php' ? 'active' : ''; ?>" data-tooltip="Riwayat">
                    <i class="fas fa-history"></i>
                    <span class="dock-label">Riwayat</span>
                </a>
                <a href="profile.php" class="dock-item <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>" data-tooltip="Profil">
                    <i class="fas fa-user-circle"></i>
                    <span class="dock-label">Profil</span>
                </a>
            </div>
        </nav>

        <!-- Bottom Vignette Gradient Behind Floating Dock -->
        <div class="dock-backdrop-fade"></div>
        <?php endif; ?>

        <main class="app-main">
            <div class="main-container">
