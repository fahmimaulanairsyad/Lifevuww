<?php
// index.php - Main entry point & Landing Page
require_once __DIR__ . '/config/database.php';
secure_session_start();

// Jika pengguna sudah login, langsung arahkan ke Dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Lifevuww - Gamified Habit Tracker & RPG Platform</title>
    <meta name="description" content="Ubah rutinitas dan disiplin harianmu menjadi petualangan RPG yang seru. Lacak kebiasaan, naikkan level dan rank, kumpulkan gold, dan tebus reward impianmu.">
    
    <!-- OpenGraph & Social Cards -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://lifevuww.42web.io/">
    <meta property="og:title" content="Lifevuww - Gamified Habit Tracker RPG">
    <meta property="og:description" content="Disiplin nyata dalam format petualangan RPG. Bangun kebiasaan positif dan capai potensi terbaikmu.">
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
    <link href="assets/css/style.css?v=20261025_02" rel="stylesheet">

    <script>
        (function() {
            var isCap = !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());
            var isMobile = isCap || window.innerWidth <= 768 || /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
            var root = document.documentElement;
            if (isMobile) { root.classList.add('is-mobile'); } else { root.classList.add('is-desktop'); }
            if (isCap) { root.classList.add('is-apk'); }
        })();

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('sw.js').catch(function(err) {
                    console.warn('SW registration failed:', err);
                });
            });
        }
    </script>
</head>
<body class="landing-body">

    <!-- Top Navigation -->
    <header class="landing-nav">
        <a href="index.php" class="landing-brand">
            <img src="assets/images/logo.svg" alt="Lifevuww Logo" width="28" height="28">
            <span>Lifevuww</span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="login.php" class="btn-core btn-ghost" style="font-size: 0.82rem; padding: 0.4rem 0.8rem;">Masuk</a>
            <a href="register.php" class="btn-core btn-primary" style="font-size: 0.82rem; padding: 0.4rem 0.9rem;">Daftar Akun</a>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="landing-hero">
        <span class="badge-status accent font-mono mb-3" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">
            <i class="fas fa-gamepad me-1"></i> GAMIFIED HABIT TRACKER
        </span>
        <h1 class="landing-hero-title">
            Disiplin Nyata dalam Format Petualangan RPG
        </h1>
        <p class="landing-hero-subtitle">
            Bangun kebiasaan harian, tingkatkan level dan rank karaktermu, kumpulkan gold, dan tebus reward impianmu.
        </p>

        <div class="landing-cta-group">
            <a href="register.php" class="btn-core btn-primary" style="padding: 0.75rem 1.5rem; font-size: 0.9rem;">
                <i class="fas fa-bolt me-1"></i> Mulai Petualangan
            </a>
            <form action="login.php" method="POST" style="margin: 0;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="login_demo" value="1">
                <button type="submit" class="btn-core btn-outline" style="padding: 0.75rem 1.4rem; font-size: 0.9rem;">
                    <i class="fas fa-play text-accent me-1"></i> Coba Akun Demo
                </button>
            </form>
        </div>

        <!-- Live Vitals Preview Card -->
        <div class="landing-preview-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 34px; height: 34px; border-radius: 50%; background-color: var(--accent-dim); border: 1px solid var(--accent); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 0.9rem;">
                        <i class="fas fa-user-astronaut"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; font-size: 0.95rem; line-height: 1.2;">Demo Adventurer</div>
                        <span class="badge-status accent font-mono" style="font-size: 0.68rem;">D-Rank Explorer</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 font-mono" style="font-size: 0.85rem;">
                    <span class="text-danger"><i class="fas fa-heart me-1"></i>90 / 100 HP</span>
                    <span class="text-warning"><i class="fas fa-coins me-1"></i>125 Gold</span>
                </div>
            </div>

            <!-- EXP Bar -->
            <div class="mb-3">
                <div class="d-flex justify-content-between font-mono text-muted mb-1" style="font-size: 0.75rem;">
                    <span>Tingkat 5</span>
                    <span>450 / 500 EXP</span>
                </div>
                <div class="vitals-track" style="height: 6px;">
                    <div class="vitals-fill" style="width: 90%; background-color: var(--accent);"></div>
                </div>
            </div>

            <!-- Habit Rows Sample -->
            <div style="border-top: 1px solid var(--border-hairline); padding-top: 0.85rem;" class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between align-items-center p-2" style="background-color: var(--bg-surface-hover); border-radius: var(--radius); border: 1px solid var(--border-hairline);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-check-circle text-accent" style="font-size: 0.9rem;"></i>
                        <span style="font-size: 0.84rem; text-decoration: line-through; color: var(--text-muted);">Minum 2.5 Liter Air</span>
                    </div>
                    <span class="font-mono text-accent" style="font-size: 0.75rem;">+5 EXP</span>
                </div>
                <div class="d-flex justify-content-between align-items-center p-2" style="background-color: var(--bg-surface-hover); border-radius: var(--radius); border: 1px solid var(--border-hairline);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-check-circle text-accent" style="font-size: 0.9rem;"></i>
                        <span style="font-size: 0.84rem; text-decoration: line-through; color: var(--text-muted);">Membaca 20 Halaman Buku</span>
                    </div>
                    <span class="font-mono text-accent" style="font-size: 0.75rem;">+5 EXP</span>
                </div>
                <div class="d-flex justify-content-between align-items-center p-2" style="background-color: var(--bg-surface-hover); border-radius: var(--radius); border: 1px solid var(--border-hairline);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="far fa-circle text-muted" style="font-size: 0.9rem;"></i>
                        <span style="font-size: 0.84rem; color: var(--text-primary);">Workout 30 Menit</span>
                    </div>
                    <span class="badge-status font-mono" style="font-size: 0.72rem;">Sedang</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Bento Features Grid -->
    <section class="landing-bento-grid">
        <div class="landing-bento-item">
            <div>
                <div class="landing-bento-icon text-accent">
                    <i class="fas fa-crown"></i>
                </div>
                <h2 class="landing-bento-title">7 Tingkatan Rank & Level</h2>
                <p class="landing-bento-desc">
                    Mulai dari F-Rank Novice hingga S-Rank Sovereign. Setiap kebiasaan positif yang kamu selesaikan menambah EXP dan menaikkan level karaktermu di dunia nyata.
                </p>
            </div>
            <div class="mt-3 d-flex gap-1 flex-wrap font-mono" style="font-size: 0.7rem;">
                <span class="badge-status">F-Rank</span>
                <span class="badge-status">E-Rank</span>
                <span class="badge-status">D-Rank</span>
                <span class="badge-status">C-Rank</span>
                <span class="badge-status">B-Rank</span>
                <span class="badge-status">A-Rank</span>
                <span class="badge-status accent">S-Rank</span>
            </div>
        </div>

        <div class="landing-bento-item">
            <div>
                <div class="landing-bento-icon text-danger">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h2 class="landing-bento-title">Konsekuensi Nyata & Sistem HP</h2>
                <p class="landing-bento-desc">
                    Karaktermu dibekali 100 HP. Melewatkan rutinitas akan mengurangi HP. Jika HP habis, karakter mengalami Game Over dan kehilangan 50% EXP. Disiplin bukan lagi sekadar pilihan.
                </p>
            </div>
            <div class="mt-3 font-mono text-danger" style="font-size: 0.75rem;">
                <i class="fas fa-exclamation-triangle me-1"></i> Penalti HP sesuai tingkat kesulitan
            </div>
        </div>

        <div class="landing-bento-item">
            <div>
                <div class="landing-bento-icon text-warning">
                    <i class="fas fa-fire"></i>
                </div>
                <h2 class="landing-bento-title">Streak Multiplier & Toko Hadiah</h2>
                <p class="landing-bento-desc">
                    Jaga konsistensi harianmu untuk membuka multiplier hingga 1.5x EXP dan Gold. Belanjakan gold di Reward Shop untuk ramuan pemulih HP, perisai Streak Freeze, atau hadiah impianmu.
                </p>
            </div>
            <div class="mt-3 d-flex gap-2 font-mono text-warning" style="font-size: 0.75rem;">
                <span><i class="fas fa-bolt me-1"></i>1.2x (3+ hari)</span>
                <span><i class="fas fa-fire me-1"></i>1.5x (7+ hari)</span>
            </div>
        </div>

        <div class="landing-bento-item">
            <div>
                <div class="landing-bento-icon text-accent">
                    <i class="fas fa-brain"></i>
                </div>
                <h2 class="landing-bento-title">5 Atribut Radar Kehidupan</h2>
                <p class="landing-bento-desc">
                    Setiap kebiasaan memupuk salah satu dari 5 pilar karakter: Intelligence, Physical, Wealth, Health, dan Social yang dipetakan secara dinamis dalam grafik radar visual.
                </p>
            </div>
            <div class="mt-3 d-flex gap-1 flex-wrap" style="font-size: 0.72rem;">
                <span class="badge-status">Intelligence</span>
                <span class="badge-status">Physical</span>
                <span class="badge-status">Wealth</span>
                <span class="badge-status">Health</span>
                <span class="badge-status">Social</span>
            </div>
        </div>

        <!-- Full Width Call to Action Bento Item -->
        <div class="landing-bento-item full-width text-center py-4">
            <h2 style="font-size: 1.5rem; font-weight: 600; letter-spacing: -0.02em; margin-bottom: 0.5rem;">
                Siap Mengubah Hidupmu Hari Ini?
            </h2>
            <p style="color: var(--text-secondary); font-size: 0.88rem; max-width: 500px; margin: 0 auto 1.5rem auto;">
                Bergabung sekarang dan buktikan bahwa membangun kebiasaan baik bisa menjadi petualangan yang memuaskan.
            </p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a href="register.php" class="btn-core btn-primary" style="padding: 0.65rem 1.4rem;">
                    Daftar Akun Gratis
                </a>
                <form action="login.php" method="POST" style="margin: 0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="login_demo" value="1">
                    <button type="submit" class="btn-core btn-outline" style="padding: 0.65rem 1.4rem;">
                        Masuk Mode Demo
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="landing-footer-links">
            <a href="index.php">Beranda</a>
            <a href="login.php">Masuk</a>
            <a href="register.php">Daftar</a>
            <a href="https://github.com/fahmimaulanairsyad/Lifevuww" target="_blank" rel="noopener">GitHub</a>
        </div>
        <p class="mb-0">
            Lifevuww &copy; 2026. Gamified Habit Tracker & RPG Platform.
        </p>
    </footer>

</body>
</html>
