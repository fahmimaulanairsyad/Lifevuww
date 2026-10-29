<?php
// index.php - Main entry point & Landing Page (Awwwards / High-Craft Product Showcase)
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
    <meta name="description" content="Ubah rutinitas dan disiplin harianmu menjadi petualangan RPG yang seru. Lacak kebiasaan, kalahkan Boss Quest, kumpulkan gold, dan tingkatkan rank karaktermu.">
    
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

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha384-3B6NwesSXE7YJlcLI9RpRqGf2p/EgVH8BgoKTaUrmKNDkHPStTQ3EyoYjCGXaOTS" crossorigin="anonymous">
    <link href="assets/css/style.css?v=20261029_05" rel="stylesheet">

    <!-- Motion Libraries: GSAP via CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
    <!-- Confetti library for Interactive Preview -->
    <script defer src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js" integrity="sha384-HAH79XdRvHr6axVGh4xQWVCp14kcd32bNk4Xu0sHDHtFQ42n6BAM8ykvB47dGz6D" crossorigin="anonymous"></script>

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

    <!-- Interactive Antigravity Canvas & Film Grain Noise Overlay -->
    <canvas id="antigravity-canvas" class="antigravity-canvas"></canvas>
    <div class="film-grain"></div>

    <!-- Custom Magnetic Cursor Follower (Desktop only) -->
    <div class="cursor-dot" id="cursorDot"></div>
    <div class="cursor-ring" id="cursorRing"></div>

    <!-- Top Vignette Blur Shield (prevents content collision when scrolling behind navbar) -->
    <div class="top-vignette-shield"></div>

    <!-- 1. Floating Pill Glass Navbar -->
    <nav class="lp-navbar-pill">
        <!-- Brand -->
        <a href="index.php" class="lp-nav-brand">
            <img src="assets/images/logo.svg" alt="Lifevuww Logo" width="24" height="24">
            <span>Lifevuww</span>
        </a>

        <!-- Center Nav Anchor Links (Hidden on < 768px) -->
        <ul class="lp-nav-links d-none d-md-flex">
            <li><a href="#lp-showcase" class="lp-nav-link">Simulasi Live</a></li>
            <li><a href="#lp-reel" class="lp-nav-link">Teaser 15s</a></li>
            <li><a href="#lp-bento" class="lp-nav-link">Fitur RPG</a></li>
            <li><a href="#lp-ranks" class="lp-nav-link">Sistem Rank</a></li>
        </ul>

        <!-- Right CTA Buttons -->
        <div class="d-flex align-items-center gap-2">
            <a href="login.php" class="btn-core btn-ghost" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">Masuk</a>
            <a href="register.php" class="lp-btn-capsule">
                <span>Mulai Gratis</span>
                <i class="fas fa-arrow-right" style="font-size: 0.7rem;"></i>
            </a>
        </div>
    </nav>

    <!-- 2. Hero Section with Atmospheric 3D Stage & Perspective Grid -->
    <section class="lp-hero">
        <div class="lp-hero-stage">
            <canvas id="hero-3d-canvas"></canvas>
        </div>

        <div class="lp-hero-content">
            <span class="badge-status accent font-mono mb-3" style="font-size: 0.74rem; padding: 0.32rem 0.8rem; border-radius: 999px;">
                <i class="fas fa-gamepad me-1"></i> GAMIFIED PRODUCTIVITY PLATFORM
            </span>

            <h1 class="lp-hero-title" style="line-height: 1.18; overflow: visible !important;">
                <span class="char-reveal" style="--char-i: 1; line-height: 1.18; padding-bottom: 0.16em; margin-bottom: -0.1em; overflow: visible;">Disiplin</span>
                <span class="char-reveal" style="--char-i: 2; line-height: 1.18; padding-bottom: 0.16em; margin-bottom: -0.1em; overflow: visible;">Nyata</span>
                <span class="char-reveal" style="--char-i: 3; line-height: 1.18; padding-bottom: 0.16em; margin-bottom: -0.1em; overflow: visible;">dalam</span>
                <span class="char-reveal" style="--char-i: 4; line-height: 1.18; padding-bottom: 0.16em; margin-bottom: -0.1em; overflow: visible;">Format</span>
                <span class="char-reveal" style="--char-i: 5; line-height: 1.18; padding-bottom: 0.16em; margin-bottom: -0.1em; overflow: visible;">Petualangan</span>
                <span class="char-reveal text-accent" style="--char-i: 6; line-height: 1.18; padding-bottom: 0.16em; margin-bottom: -0.1em; overflow: visible;">RPG</span>
            </h1>

            <p class="lp-hero-subtitle">
                Ubah rutinitas harianmu menjadi petualangan level-up. Kalahkan Boss Quest, jaga streak konsistensi, bangun radar 5 pilar karakter, dan tebus reward impianmu.
            </p>

            <div class="lp-cta-group">
                <a href="register.php" class="btn-core btn-primary" style="padding: 0.75rem 1.6rem; font-size: 0.9rem; border-radius: 999px;">
                    <i class="fas fa-bolt me-1"></i> Mulai Petualangan
                </a>
                <form action="login.php" method="POST" style="margin: 0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="login_demo" value="1">
                    <button type="submit" class="btn-core btn-outline" style="padding: 0.75rem 1.5rem; font-size: 0.9rem; border-radius: 999px;">
                        <i class="fas fa-play text-accent me-1"></i> Coba Akun Demo (Guest Mode)
                    </button>
                </form>
            </div>
        </div>

        <!-- 3. 3D Perspective App Window Showcase (Interactive 3-Tab Mockup) -->
        <div class="lp-mockup-window spotlight-card" id="lp-showcase">
            <!-- Window Topbar -->
            <div class="lp-window-topbar">
                <div class="lp-window-dots">
                    <span class="lp-dot red"></span>
                    <span class="lp-dot yellow"></span>
                    <span class="lp-dot green"></span>
                    <span class="text-muted ms-2 font-mono d-none d-sm-inline" style="font-size: 0.7rem;">lifevuww-os // v2.0</span>
                </div>

                <!-- 3 Interactive Tabs -->
                <div class="lp-window-tabs">
                    <button type="button" class="lp-tab-btn active" data-tab="tab-boss-live" onclick="switchMockTab('tab-boss-live', this)">
                        <i class="fas fa-dragon me-1 text-danger"></i> <span>Boss & Habit Live</span>
                    </button>
                    <button type="button" class="lp-tab-btn" data-tab="tab-radar-stats" onclick="switchMockTab('tab-radar-stats', this)">
                        <i class="fas fa-brain me-1 text-accent"></i> <span>Matriks 5 Atribut</span>
                    </button>
                    <button type="button" class="lp-tab-btn" data-tab="tab-badges-shop" onclick="switchMockTab('tab-badges-shop', this)">
                        <i class="fas fa-medal me-1 text-warning"></i> <span>Lencana & Toko</span>
                    </button>
                </div>

                <!-- Re-active Unified Status Capsule -->
                <div class="lp-mock-capsule font-mono">
                    <span class="text-danger" title="Poin Kesehatan"><i class="fas fa-heart me-1"></i><span id="mockHp">100</span></span>
                    <span class="text-muted">|</span>
                    <span class="text-accent" title="Level & EXP"><i class="fas fa-bolt me-1"></i>Lv.<span id="mockLevel">5</span> • <span id="mockExp">450</span>/500</span>
                    <span class="text-muted">|</span>
                    <span class="text-warning" title="Saldo Gold"><i class="fas fa-coins me-1"></i><span id="mockGold">125</span></span>
                </div>
            </div>

            <!-- Window Mock Body -->
            <div class="lp-mock-body">
                <!-- TAB 1: Boss & Habit Live Simulation -->
                <div class="lp-tab-pane active" id="tab-boss-live">
                    <div class="row g-3">
                        <!-- Left: Habit Card Simulation -->
                        <div class="col-md-5">
                            <div class="p-3 h-100" style="background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.07); border-radius: 14px;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted font-mono" style="font-size: 0.7rem; text-transform: uppercase;">Rutinitas Harian</span>
                                    <span class="badge-status warning font-mono" style="font-size: 0.65rem;"><i class="fas fa-fire me-1"></i>Streak 4 Hari (1.2x)</span>
                                </div>
                                <h4 style="font-size: 0.95rem; font-weight: 600; margin-bottom: 0.85rem;">Checklist Kebiasaan</h4>
                                
                                <div class="d-flex flex-column gap-2">
                                    <!-- Habit 1: Multi-Count Stepper (1/2) -->
                                    <div class="d-flex justify-content-between align-items-center p-2" id="mockStepperRow" style="background: rgba(255,255,255,0.03); border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); transition: all 0.15s ease;">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-circle text-muted" id="mockStepperIcon" style="font-size: 0.8rem;"></i>
                                            <div>
                                                <span id="mockStepperText" style="font-size: 0.82rem; color: var(--text-primary); font-weight: 500;">Hidrasi Air Minum (Botol)</span>
                                                <div class="text-muted font-mono" style="font-size: 0.65rem;">Multi-Count • +10 EXP</div>
                                            </div>
                                        </div>
                                        <div class="habit-stepper" style="padding: 2px;">
                                            <button type="button" class="btn-stepper btn-stepper-dec" id="mockStepDec" onclick="stepMockHabit(-1, this)" title="Kurangi progres">
                                                <i class="fas fa-minus" style="font-size: 0.7rem;"></i>
                                            </button>
                                            <div class="stepper-display font-mono" id="mockStepDisplay" style="min-width: 54px; padding: 1px 6px;">
                                                <span class="stepper-text" id="mockStepText" style="font-size: 0.76rem;">1/2</span>
                                                <div class="stepper-progress-track" style="height: 3px;">
                                                    <div class="stepper-progress-fill" id="mockStepFill" style="width: 50%;"></div>
                                                </div>
                                            </div>
                                            <button type="button" class="btn-stepper btn-stepper-inc" id="mockStepInc" onclick="stepMockHabit(1, this)" title="Tambah progres">
                                                <i class="fas fa-plus" style="font-size: 0.7rem;"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Habit 2: Single-Click Checklist -->
                                    <div class="d-flex justify-content-between align-items-center p-2" id="mockHabitRow" onclick="toggleMockHabit()" style="background: rgba(255,255,255,0.03); border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); cursor: pointer; transition: all 0.15s ease;">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="far fa-circle text-muted" id="mockHabitIcon" style="font-size: 0.88rem;"></i>
                                            <span id="mockHabitText" class="slash-animated" style="font-size: 0.82rem; color: var(--text-primary);">Workout & Olahraga 30 Menit</span>
                                        </div>
                                        <span class="badge-status font-mono" id="mockHabitBadge" style="font-size: 0.7rem;">+12 EXP</span>
                                    </div>
                                </div>
                                <div class="text-muted mt-3" style="font-size: 0.7rem;">
                                    <i class="fas fa-info-circle me-1"></i> Klik kebiasaan atau tombol stepper [ - ] [ + ] di atas untuk menguji penambahan EXP & Gold secara real-time.
                                </div>
                            </div>
                        </div>

                        <!-- Right: Boss Quest Card Simulation -->
                        <div class="col-md-7">
                            <div class="boss-card-container p-3 h-100" id="mockBossCard" style="border-radius: 14px;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge-status danger font-mono" style="font-size: 0.65rem; padding: 2px 7px;">
                                            <i class="fas fa-skull me-1"></i> BOSS QUEST
                                        </span>
                                        <span class="fw-bold" style="font-size: 0.9rem; color: var(--text-primary);">Naga Kemalasan & Distraksi</span>
                                    </div>
                                    <span class="font-mono text-danger" id="mockBossHpLabel" style="font-size: 0.74rem; font-weight: 600;">Sisa HP: 67%</span>
                                </div>

                                <div class="vitals-track boss-hp-track" style="height: 6px; background: rgba(255,255,255,0.06); margin: 6px 0 12px 0;">
                                    <div class="vitals-fill boss-hp-fill" id="mockBossHpBar" style="width: 67%; background-color: var(--status-danger); transition: width 0.3s ease;"></div>
                                </div>

                                <div class="d-flex flex-column gap-1 mb-3">
                                    <label class="subtask-row w-100 d-flex align-items-center gap-2 p-2 m-0" style="cursor: pointer; border-radius: 8px;">
                                        <input type="checkbox" checked disabled style="accent-color: var(--accent); width: 14px; height: 14px;">
                                        <span class="slash-animated slashed" style="font-size: 0.8rem; color: var(--text-muted); text-decoration: line-through;">Fase 1: Bangun Tepat Waktu Tanpa Snooze</span>
                                    </label>
                                    <label class="subtask-row w-100 d-flex align-items-center gap-2 p-2 m-0" style="cursor: pointer; border-radius: 8px;">
                                        <input type="checkbox" id="mockSubtask2" onchange="updateMockBoss(this)" style="accent-color: var(--accent); cursor: pointer; width: 14px; height: 14px;">
                                        <span id="mockSubText2" class="slash-animated" style="font-size: 0.8rem; color: var(--text-primary);">Fase 2: Blokir Medsos & Distraksi Ponsel</span>
                                    </label>
                                    <label class="subtask-row w-100 d-flex align-items-center gap-2 p-2 m-0" style="cursor: pointer; border-radius: 8px;">
                                        <input type="checkbox" id="mockSubtask3" onchange="updateMockBoss(this)" style="accent-color: var(--accent); cursor: pointer; width: 14px; height: 14px;">
                                        <span id="mockSubText3" class="slash-animated" style="font-size: 0.8rem; color: var(--text-primary);">Fase 3: Selesaikan 1 Target Penting Hari Ini</span>
                                    </label>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-2" style="border-top: 1px solid rgba(255,255,255,0.08);">
                                    <span class="font-mono text-accent" style="font-size: 0.82rem;">Reward: +45 EXP • +90 Gold (1.5x Boss)</span>
                                    <button type="button" id="mockBossClaimBtn" class="btn-core btn-outline font-mono text-danger" style="padding: 0.35rem 0.85rem; font-size: 0.78rem; opacity: 0.65; cursor: not-allowed;" disabled onclick="claimMockBoss()">
                                        <i class="fas fa-lock me-1"></i> Lawan Boss
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: Dynamic 5-Axis Radar Chart Matrix -->
                <div class="lp-tab-pane" id="tab-radar-stats">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6 text-center">
                            <div class="p-3 d-inline-block" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px;">
                                <svg id="lpRadarSvg" width="260" height="260" viewBox="0 0 260 260" style="overflow: visible;">
                                    <!-- Background Pentagon Rings (20%, 40%, 60%, 80%, 100%) -->
                                    <polygon points="130,35 220,100 185,210 75,210 40,100" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
                                    <polygon points="130,54 202,106 174,194 86,194 58,106" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
                                    <polygon points="130,73 184,112 163,178 97,178 76,112" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
                                    <polygon points="130,92 166,118 152,162 108,162 94,118" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>

                                    <!-- 5 Axis Spokes -->
                                    <line x1="130" y1="130" x2="130" y2="35" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
                                    <line x1="130" y1="130" x2="220" y2="100" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
                                    <line x1="130" y1="130" x2="185" y2="210" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
                                    <line x1="130" y1="130" x2="75" y2="210" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
                                    <line x1="130" y1="130" x2="40" y2="100" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>

                                    <!-- Dynamic Data Polygon -->
                                    <polygon id="lpRadarPolygon" points="130,68 190,110 165,185 90,180 65,115" fill="rgba(16, 185, 129, 0.22)" stroke="#10b981" stroke-width="2" style="transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);"/>

                                    <!-- Vertex Marker Dots -->
                                    <circle id="dot-int" cx="130" cy="68" r="4" fill="#10b981" style="transition: all 0.4s ease;"/>
                                    <circle id="dot-phy" cx="190" cy="110" r="4" fill="#10b981" style="transition: all 0.4s ease;"/>
                                    <circle id="dot-wlt" cx="165" cy="185" r="4" fill="#10b981" style="transition: all 0.4s ease;"/>
                                    <circle id="dot-hlt" cx="90" cy="180" r="4" fill="#10b981" style="transition: all 0.4s ease;"/>
                                    <circle id="dot-soc" cx="65" cy="115" r="4" fill="#10b981" style="transition: all 0.4s ease;"/>

                                    <!-- Axis Labels -->
                                    <text x="130" y="24" text-anchor="middle" fill="#fafafa" font-size="10" font-family="'Geist', sans-serif" font-weight="600">Intelligence</text>
                                    <text x="232" y="103" text-anchor="start" fill="#fafafa" font-size="10" font-family="'Geist', sans-serif" font-weight="600">Physical</text>
                                    <text x="195" y="226" text-anchor="start" fill="#fafafa" font-size="10" font-family="'Geist', sans-serif" font-weight="600">Wealth</text>
                                    <text x="65" y="226" text-anchor="end" fill="#fafafa" font-size="10" font-family="'Geist', sans-serif" font-weight="600">Health</text>
                                    <text x="28" y="103" text-anchor="end" fill="#fafafa" font-size="10" font-family="'Geist', sans-serif" font-weight="600">Social</text>
                                </svg>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <span class="badge-status accent font-mono mb-2" style="font-size: 0.68rem;">Pohon Atribut Karakter</span>
                            <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 0.5rem;">Latih Atribut & Perluas Radar</h3>
                            <p class="text-secondary mb-3" style="font-size: 0.8rem; line-height: 1.5;">
                                Klik tombol di bawah untuk menyimulasikan latihan. Setiap aksi menambah EXP dan memperlebar matriks radar kehidupanmu secara langsung:
                            </p>

                            <div class="d-flex flex-column gap-2">
                                <button type="button" class="btn-core btn-outline text-start d-flex justify-content-between align-items-center p-2" onclick="trainAttribute('int', 0)">
                                    <span style="font-size: 0.8rem;"><i class="fas fa-brain text-accent me-2"></i> Latih Intelligence (Membaca/Koding)</span>
                                    <span class="font-mono text-accent" style="font-size: 0.72rem;">+15 EXP</span>
                                </button>
                                <button type="button" class="btn-core btn-outline text-start d-flex justify-content-between align-items-center p-2" onclick="trainAttribute('phy', 1)">
                                    <span style="font-size: 0.8rem;"><i class="fas fa-dumbbell text-danger me-2"></i> Latih Physical (Workout/Lari)</span>
                                    <span class="font-mono text-accent" style="font-size: 0.72rem;">+15 EXP</span>
                                </button>
                                <button type="button" class="btn-core btn-outline text-start d-flex justify-content-between align-items-center p-2" onclick="trainAttribute('hlt', 3)">
                                    <span style="font-size: 0.8rem;"><i class="fas fa-heart text-danger me-2"></i> Latih Health (Tidur 8 Jam/Air)</span>
                                    <span class="font-mono text-accent" style="font-size: 0.72rem;">+15 EXP</span>
                                </button>
                                <button type="button" class="btn-core btn-outline text-start d-flex justify-content-between align-items-center p-2" onclick="trainAttribute('wlt', 2)">
                                    <span style="font-size: 0.8rem;"><i class="fas fa-coins text-warning me-2"></i> Latih Wealth (Catat Finansial/Investasi)</span>
                                    <span class="font-mono text-accent" style="font-size: 0.72rem;">+15 EXP</span>
                                </button>
                                <button type="button" class="btn-core btn-outline text-start d-flex justify-content-between align-items-center p-2" onclick="trainAttribute('soc', 4)">
                                    <span style="font-size: 0.8rem;"><i class="fas fa-users text-muted me-2"></i> Latih Social (Sapa Teman/Networking)</span>
                                    <span class="font-mono text-accent" style="font-size: 0.72rem;">+15 EXP</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: 3D Holographic Badges & Shop Simulation -->
                <div class="lp-tab-pane" id="tab-badges-shop">
                    <div class="row g-3">
                        <!-- Left: 4 3D Holographic Badges -->
                        <div class="col-md-7">
                            <span class="text-muted font-mono" style="font-size: 0.7rem; text-transform: uppercase;">Pencapaian RPG (3D Holo Tilt)</span>
                            <h4 style="font-size: 0.95rem; font-weight: 600; margin: 4px 0 12px 0;">Koleksi Lencana Karakter</h4>

                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="p-3 holo-card spotlight-card h-100" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(16,185,129,0.3); border-radius: 12px;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <i class="fas fa-shoe-prints text-accent"></i>
                                            <span class="badge-status accent font-mono" style="font-size: 0.6rem;">Terbuka</span>
                                        </div>
                                        <div style="font-weight: 600; font-size: 0.82rem;">Langkah Pertama</div>
                                        <div class="text-muted" style="font-size: 0.7rem;">Selesaikan 1 aktivitas</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 holo-card spotlight-card h-100" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(16,185,129,0.3); border-radius: 12px;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <i class="fas fa-fire text-warning"></i>
                                            <span class="badge-status accent font-mono" style="font-size: 0.6rem;">Terbuka</span>
                                        </div>
                                        <div style="font-weight: 600; font-size: 0.82rem;">Penjaga Api</div>
                                        <div class="text-muted" style="font-size: 0.7rem;">Streak 7 hari beruntun</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 holo-card spotlight-card h-100" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(16,185,129,0.3); border-radius: 12px;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <i class="fas fa-bolt text-accent"></i>
                                            <span class="badge-status accent font-mono" style="font-size: 0.6rem;">Terbuka</span>
                                        </div>
                                        <div style="font-weight: 600; font-size: 0.82rem;">Kebangkitan</div>
                                        <div class="text-muted" style="font-size: 0.7rem;">Mencapai Tingkat 5</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 holo-card spotlight-card h-100" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; opacity: 0.7;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <i class="fas fa-coins text-muted"></i>
                                            <span class="badge-status font-mono" style="font-size: 0.6rem;">125/200 G</span>
                                        </div>
                                        <div style="font-weight: 600; font-size: 0.82rem;">Saudagar Emas</div>
                                        <div class="text-muted" style="font-size: 0.7rem;">Kumpulkan 200 Gold</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Shop Consumables Simulation -->
                        <div class="col-md-5">
                            <span class="text-muted font-mono" style="font-size: 0.7rem; text-transform: uppercase;">Reward Shop (Belanja Gold)</span>
                            <h4 style="font-size: 0.95rem; font-weight: 600; margin: 4px 0 12px 0;">Item Pemulih & Perisai</h4>

                            <div class="d-flex flex-column gap-2">
                                <div class="p-3 holo-card spotlight-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 12px;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(244,63,94,0.12); color: var(--status-danger); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-prescription-bottle"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; font-size: 0.82rem;">Ramuan HP Minor</div>
                                                <div class="text-muted" style="font-size: 0.7rem;">+25 HP Seketika</div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-core btn-outline font-mono text-warning" style="font-size: 0.75rem; padding: 0.3rem 0.7rem;" onclick="buyMockShopItem('minor', 25)">
                                            25 Gold
                                        </button>
                                    </div>
                                </div>

                                <div class="p-3 holo-card spotlight-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 12px;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(6,182,212,0.12); color: var(--status-info); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-snowflake"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; font-size: 0.82rem;">Perisai Streak Freeze</div>
                                                <div class="text-muted" style="font-size: 0.7rem;">Lindungi Streak 1 Hari</div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-core btn-outline font-mono text-warning" style="font-size: 0.75rem; padding: 0.3rem 0.7rem;" onclick="buyMockShopItem('freeze', 50)">
                                            50 Gold
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Infinite Marquee Bar (Rank Progression) -->
    <div class="marquee-wrapper mb-5" id="lp-ranks">
        <div class="marquee-track">
            <span class="marquee-item"><i class="fas fa-crown text-muted"></i> F-Rank Novice</span>
            <span class="text-muted">✦</span>
            <span class="marquee-item"><i class="fas fa-crown text-muted"></i> E-Rank Rookie</span>
            <span class="text-muted">✦</span>
            <span class="marquee-item"><i class="fas fa-compass text-accent"></i> D-Rank Explorer</span>
            <span class="text-muted">✦</span>
            <span class="marquee-item"><i class="fas fa-shield-alt text-info"></i> C-Rank Vanguard</span>
            <span class="text-muted">✦</span>
            <span class="marquee-item"><i class="fas fa-award text-accent"></i> B-Rank Elite</span>
            <span class="text-muted">✦</span>
            <span class="marquee-item"><i class="fas fa-dragon text-warning"></i> A-Rank Master</span>
            <span class="text-muted">✦</span>
            <span class="marquee-item"><i class="fas fa-medal text-warning"></i> S-Rank Sovereign</span>
            <span class="text-muted">✦</span>
            <!-- Duplicate for seamless infinite loop -->
            <span class="marquee-item"><i class="fas fa-crown text-muted"></i> F-Rank Novice</span>
            <span class="text-muted">✦</span>
            <span class="marquee-item"><i class="fas fa-crown text-muted"></i> E-Rank Rookie</span>
            <span class="text-muted">✦</span>
            <span class="marquee-item"><i class="fas fa-compass text-accent"></i> D-Rank Explorer</span>
            <span class="text-muted">✦</span>
            <span class="marquee-item"><i class="fas fa-shield-alt text-info"></i> C-Rank Vanguard</span>
            <span class="text-muted">✦</span>
            <span class="marquee-item"><i class="fas fa-award text-accent"></i> B-Rank Elite</span>
            <span class="text-muted">✦</span>
            <span class="marquee-item"><i class="fas fa-dragon text-warning"></i> A-Rank Master</span>
            <span class="text-muted">✦</span>
            <span class="marquee-item"><i class="fas fa-medal text-warning"></i> S-Rank Sovereign</span>
            <span class="text-muted">✦</span>
        </div>
    </div>

    <!-- 3.5. 15-Second High-Octane Motion Reel Showcase -->
    <section class="lp-reel-section" id="lp-reel">
        <div class="text-center mb-4">
            <span class="badge-status accent font-mono mb-2" style="font-size: 0.72rem; padding: 0.28rem 0.75rem; border-radius: 999px;">
                <i class="fas fa-play me-1"></i> 15-SECOND HIGH-OCTANE REEL
            </span>
            <h2 class="landing-section-title" style="font-size: 2rem; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 0.4rem;">
                Aksi Gamifikasi Produktivitas dalam 15 Detik
            </h2>
            <p class="text-muted" style="font-size: 0.88rem; max-width: 600px; margin: 0 auto;">
                Dari rentetan streak habit, radar 5 pilar karakter, lorong misi 3D, hingga kenaikan gelar RPG.
            </p>
        </div>

        <div class="lp-reel-window spotlight-card">
            <!-- Window Topbar with Interactive Player Controls -->
            <div class="lp-window-topbar">
                <div class="lp-window-dots">
                    <span class="lp-dot red"></span>
                    <span class="lp-dot yellow"></span>
                    <span class="lp-dot green"></span>
                    <span class="text-muted ms-2 font-mono d-none d-sm-inline" style="font-size: 0.7rem;" id="lpReelTopbarLabel">lifevuww-reel-15s // 120-bpm</span>
                </div>
                <div class="lp-reel-controls">
                    <button type="button" class="lp-reel-btn lp-reel-duration-btn active" id="btnReel15" onclick="switchReelDuration('15s', this)" title="Versi 15 detik">
                        <i class="fas fa-bolt text-accent"></i> <span>15s Cut</span>
                    </button>
                    <button type="button" class="lp-reel-btn lp-reel-duration-btn" id="btnReel30" onclick="switchReelDuration('30s', this)" title="Versi 30 detik">
                        <i class="fas fa-film text-warning"></i> <span>30s Cut</span>
                    </button>
                    <button type="button" class="lp-reel-btn" id="btnReelReplay" onclick="replayReel()" title="Putar ulang animasi dari frame 0.00s">
                        <i class="fas fa-redo-alt"></i> <span>Replay</span>
                    </button>
                    <button type="button" class="lp-reel-btn" id="btnReelFullscreen" onclick="toggleReelFullscreen()" title="Layar Penuh (1920x1080)">
                        <i class="fas fa-expand"></i> <span>Fullscreen</span>
                    </button>
                </div>
            </div>

            <!-- Player Body (16:9) -->
            <div class="lp-reel-body" id="lpReelBody">
                <iframe id="lpReelIframe" src="assets/reel-15s.html" allow="autoplay; fullscreen" allowfullscreen="true" title="Lifevuww 15s High-Octane Motion Reel"></iframe>
            </div>
        </div>
    </section>

    <!-- 4. Re-Engineered Bento Grid with Living Micro-UI Illustrations -->
    <section class="landing-bento-grid" id="lp-bento">
        <!-- Bento Card 1: 7 Ranks (Span 7) -->
        <div class="landing-bento-item span-7 spotlight-card holo-card">
            <div>
                <!-- Micro-UI 1: Rank Cycle Badges -->
                <div class="lp-micro-ui-wrap">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted font-mono" style="font-size: 0.68rem; text-transform: uppercase;">Hierarki Gelar & Kenaikan Rank</span>
                        <span class="font-mono text-accent" id="bentoRankTitle" style="font-size: 0.72rem; font-weight: 600;">D-Rank Explorer</span>
                    </div>
                    <div class="lp-rank-badges-row mb-2">
                        <div class="lp-rank-badge-node font-mono" id="rb-0">F</div>
                        <div class="lp-rank-badge-node font-mono" id="rb-1">E</div>
                        <div class="lp-rank-badge-node font-mono active-sweep" id="rb-2">D</div>
                        <div class="lp-rank-badge-node font-mono" id="rb-3">C</div>
                        <div class="lp-rank-badge-node font-mono" id="rb-4">B</div>
                        <div class="lp-rank-badge-node font-mono" id="rb-5">A</div>
                        <div class="lp-rank-badge-node font-mono" id="rb-6">S</div>
                    </div>
                    <div class="vitals-track" style="height: 4px;">
                        <div class="vitals-fill" id="bentoExpFill" style="width: 55%; background-color: var(--accent); transition: width 0.4s ease;"></div>
                    </div>
                </div>

                <div class="landing-bento-icon text-accent">
                    <i class="fas fa-crown"></i>
                </div>
                <h3 class="landing-bento-title">7 Tingkatan Gelar & Level Up</h3>
                <p class="landing-bento-desc">
                    Mulai dari F-Rank Novice hingga S-Rank Sovereign. Setiap kebiasaan positif yang kamu selesaikan menambah akumulasi EXP, menaikkan tingkat karakter, dan membuka rank baru.
                </p>
            </div>
        </div>

        <!-- Bento Card 2: 5 Atribut Radar (Span 5) -->
        <div class="landing-bento-item span-5 spotlight-card holo-card">
            <div>
                <!-- Micro-UI 2: Pulsing Radar Chart -->
                <div class="lp-micro-ui-wrap text-center py-2">
                    <svg width="120" height="90" viewBox="0 0 120 90" class="lp-radar-pulse" style="overflow: visible;">
                        <polygon points="60,10 100,35 85,80 35,80 20,35" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/>
                        <polygon points="60,25 90,42 78,70 42,70 30,42" fill="rgba(16,185,129,0.25)" stroke="#10b981" stroke-width="1.5"/>
                    </svg>
                    <div class="d-flex justify-content-center gap-1 mt-2">
                        <span class="badge-status font-mono" style="font-size: 0.62rem;">INT +15</span>
                        <span class="badge-status font-mono" style="font-size: 0.62rem;">PHY +20</span>
                        <span class="badge-status font-mono" style="font-size: 0.62rem;">HLT +10</span>
                    </div>
                </div>

                <div class="landing-bento-icon text-accent">
                    <i class="fas fa-brain"></i>
                </div>
                <h3 class="landing-bento-title">5 Atribut Radar Kehidupan</h3>
                <p class="landing-bento-desc">
                    Kebiasaanmu dipetakan ke 5 pilar karakter: Intelligence, Physical, Wealth, Health, dan Social yang divisualisasikan dalam bentuk poligon radar taktis.
                </p>
            </div>
        </div>

        <!-- Bento Card 3: Konsekuensi HP (Span 5) -->
        <div class="landing-bento-item span-5 spotlight-card holo-card">
            <div>
                <!-- Micro-UI 3: HP Shield Deflection -->
                <div class="lp-micro-ui-wrap">
                    <div class="d-flex justify-content-between align-items-center font-mono text-danger mb-1" style="font-size: 0.7rem;">
                        <span><i class="fas fa-heart me-1"></i>HP Karakter: 100/100</span>
                        <span class="text-info lp-shield-anim px-2 py-0 rounded" style="background: rgba(6,182,212,0.12);"><i class="fas fa-shield-alt me-1"></i>Freeze Shield</span>
                    </div>
                    <div class="vitals-track mb-2" style="height: 5px;">
                        <div class="vitals-fill" style="width: 100%; background-color: var(--status-danger);"></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center font-mono text-muted" style="font-size: 0.68rem;">
                        <span>Penalti Absen: -15 HP</span>
                        <span class="text-accent">Diserap Perisai (0 Damage)</span>
                    </div>
                </div>

                <div class="landing-bento-icon text-danger">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h3 class="landing-bento-title">Konsekuensi Nyata & Sistem HP</h3>
                <p class="landing-bento-desc">
                    Melewatkan tugas harian akan mengurangi HP. Jika HP habis (Game Over), kamu kehilangan 50% EXP. Disiplin memiliki konsekuensi yang nyata.
                </p>
            </div>
        </div>

        <!-- Bento Card 4: Streak Multiplier (Span 7) -->
        <div class="landing-bento-item span-7 spotlight-card holo-card">
            <div>
                <!-- Micro-UI 4: Heatmap Sequential Light-up -->
                <div class="lp-micro-ui-wrap">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted font-mono" style="font-size: 0.68rem; text-transform: uppercase;">14 Hari Konsistensi Heatmap</span>
                        <span class="badge-status warning font-mono" style="font-size: 0.65rem;"><i class="fas fa-fire me-1"></i>7 Hari Streak • 1.5x Boost</span>
                    </div>
                    <div class="d-flex gap-1" id="miniHeatTrack">
                        <div class="lp-mini-heat-cell lit"></div>
                        <div class="lp-mini-heat-cell lit"></div>
                        <div class="lp-mini-heat-cell lit"></div>
                        <div class="lp-mini-heat-cell lit"></div>
                        <div class="lp-mini-heat-cell lit"></div>
                        <div class="lp-mini-heat-cell lit"></div>
                        <div class="lp-mini-heat-cell lit"></div>
                        <div class="lp-mini-heat-cell"></div>
                        <div class="lp-mini-heat-cell"></div>
                        <div class="lp-mini-heat-cell"></div>
                        <div class="lp-mini-heat-cell"></div>
                        <div class="lp-mini-heat-cell"></div>
                        <div class="lp-mini-heat-cell"></div>
                        <div class="lp-mini-heat-cell"></div>
                    </div>
                </div>

                <div class="landing-bento-icon text-warning">
                    <i class="fas fa-fire"></i>
                </div>
                <h3 class="landing-bento-title">Streak Multiplier & Toko Hadiah</h3>
                <p class="landing-bento-desc">
                    Jaga konsistensi harianmu untuk membuka multiplier hingga 1.5x EXP & Gold. Tukarkan Gold di Toko untuk ramuan pemulih HP, perisai Streak Freeze, atau hadiah impianmu.
                </p>
            </div>
        </div>
    </section>

    <!-- 5. Closing CTA Section -->
    <section class="lp-closing-cta spotlight-card holo-card" id="lp-cta">
        <span class="badge-status accent font-mono mb-3" style="font-size: 0.74rem;">MULAI PETUALANGAN</span>
        <h2 class="lp-closing-title">
            Siap Mengubah Hidupmu Menjadi Petualangan?
        </h2>
        <p class="lp-closing-desc">
            Bangun karakter terkuatmu mulai hari ini. Gratis, tanpa kartu kredit, dan langsung bisa dicoba dalam mode demo tamu.
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a href="register.php" class="btn-core btn-primary" style="padding: 0.75rem 1.6rem; font-size: 0.9rem; border-radius: 999px;">
                <i class="fas fa-user-plus me-1"></i> Buat Karakter Baru
            </a>
            <form action="login.php" method="POST" style="margin: 0;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="login_demo" value="1">
                <button type="submit" class="btn-core btn-outline" style="padding: 0.75rem 1.5rem; font-size: 0.9rem; border-radius: 999px;">
                    <i class="fas fa-play text-accent me-1"></i> Langsung Coba Akun Demo
                </button>
            </form>
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
            Lifevuww &copy; 2026. Gamified Habit Tracker & RPG Productivity Platform.
        </p>
    </footer>

    <!-- Interactive Landing Demo Simulator & Motion Script Engine -->
    <script>
        // State variables for interactive mockup
        let mockExp = 450;
        let mockGold = 125;
        let mockHp = 100;
        let mockLevel = 5;
        let habitToggled = false;

        function updateMockCapsule() {
            document.getElementById('mockExp').textContent = mockExp;
            document.getElementById('mockGold').textContent = mockGold;
            document.getElementById('mockHp').textContent = mockHp;
            document.getElementById('mockLevel').textContent = mockLevel;
        }

        function spawnCombatText(x, y, textHtml) {
            const el = document.createElement('div');
            el.className = 'floating-combat-text';
            el.innerHTML = textHtml;
            el.style.left = x + 'px';
            el.style.top = y + 'px';
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 900);
        }

        // 1. Tab Switching Function
        function switchMockTab(tabId, btn) {
            document.querySelectorAll('.lp-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.lp-tab-pane').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            const pane = document.getElementById(tabId);
            if (pane) pane.classList.add('active');
        }

        // 2. Tab 1: Multi-Count Stepper & Single-Click Habit Simulation
        let mockStepCur = 1;
        const mockStepTarget = 2;
        let mockStepCompleted = false;

        function stepMockHabit(delta, btn) {
            const next = Math.max(0, Math.min(mockStepTarget, mockStepCur + delta));
            if (next === mockStepCur) return;

            const wasCompleted = (mockStepCur === mockStepTarget);
            mockStepCur = next;

            const text = document.getElementById('mockStepText');
            const fill = document.getElementById('mockStepFill');
            const icon = document.getElementById('mockStepperIcon');
            const title = document.getElementById('mockStepperText');
            const display = document.getElementById('mockStepDisplay');
            const btnDec = document.getElementById('mockStepDec');
            const btnInc = document.getElementById('mockStepInc');

            if (fill) fill.style.width = Math.min(100, Math.round((mockStepCur / mockStepTarget) * 100)) + '%';
            if (btnDec) btnDec.disabled = (mockStepCur <= 0);
            if (btnInc) btnInc.disabled = (mockStepCur >= mockStepTarget);

            const rect = btn.getBoundingClientRect();
            const posX = rect.left + rect.width / 2;
            const posY = rect.top;

            if (mockStepCur === mockStepTarget) {
                mockStepCompleted = true;
                if (text) text.innerHTML = '<i class="fas fa-check text-accent me-1"></i>2/2';
                if (display) display.classList.add('completed');
                if (icon) icon.className = 'fas fa-check-circle text-accent';
                if (title) {
                    title.style.color = 'var(--text-muted)';
                    title.style.textDecoration = 'line-through';
                }
                mockExp += 10;
                mockGold += 10;
                spawnCombatText(posX, posY, '<span class="text-accent fw-bold"><i class="fas fa-star text-accent me-1"></i>+10 EXP • +10 Gold (Tuntas 2/2!)</span>');
                if (typeof confetti === 'function') {
                    try {
                        confetti({ particleCount: 25, spread: 45, origin: { x: posX / window.innerWidth, y: posY / window.innerHeight } });
                    } catch(e){}
                }
            } else {
                if (title) {
                    title.style.textDecoration = 'none';
                    title.style.color = 'var(--text-primary)';
                }
                if (icon) icon.className = 'fas fa-circle text-muted';
                if (display) display.classList.remove('completed');

                if (wasCompleted) {
                    mockStepCompleted = false;
                    mockExp -= 10;
                    mockGold -= 10;
                    spawnCombatText(posX, posY, `<span class="text-warning fw-bold"><i class="fas fa-undo-alt text-warning me-1"></i>↺ Progres ${mockStepCur}/${mockStepTarget} (-10 EXP)</span>`);
                } else if (delta > 0) {
                    spawnCombatText(posX, posY, `<span class="text-primary fw-bold">⚡ Progres ${mockStepCur}/${mockStepTarget}</span>`);
                } else {
                    spawnCombatText(posX, posY, `<span class="text-warning fw-bold">↺ Progres ${mockStepCur}/${mockStepTarget}</span>`);
                }
                if (text) text.textContent = `${mockStepCur}/${mockStepTarget}`;
            }
            updateMockCapsule();
        }

        function toggleMockHabit() {
            habitToggled = !habitToggled;
            const icon = document.getElementById('mockHabitIcon');
            const text = document.getElementById('mockHabitText');
            const badge = document.getElementById('mockHabitBadge');
            const row = document.getElementById('mockHabitRow');

            if (habitToggled) {
                icon.className = 'fas fa-check-circle text-accent';
                text.classList.add('slashed');
                text.style.color = 'var(--text-muted)';
                badge.innerHTML = '<i class="fas fa-check"></i> Tuntas';
                badge.className = 'badge-status accent font-mono';
                mockExp += 12;
                mockGold += 12;
                const rect = row.getBoundingClientRect();
                spawnCombatText(rect.left + rect.width / 2, rect.top, '<i class="fas fa-bolt text-accent"></i> +12 EXP • +12 Gold');
            } else {
                icon.className = 'far fa-circle text-muted';
                text.classList.remove('slashed');
                text.style.color = 'var(--text-primary)';
                badge.textContent = '+12 EXP';
                badge.className = 'badge-status font-mono';
                mockExp -= 12;
                mockGold -= 12;
                const rect = row.getBoundingClientRect();
                spawnCombatText(rect.left + rect.width / 2, rect.top, '<span class="text-warning fw-bold"><i class="fas fa-undo-alt text-warning me-1"></i>↺ -12 EXP • -12 Gold</span>');
            }
            updateMockCapsule();
        }

        // 3. Tab 1: Boss Quest Simulation
        function updateMockBoss(el) {
            const isChecked = el ? el.checked : true;
            const s2 = document.getElementById('mockSubtask2').checked;
            const s3 = document.getElementById('mockSubtask3').checked;
            const t2 = document.getElementById('mockSubText2');
            const t3 = document.getElementById('mockSubText3');
            const hpBar = document.getElementById('mockBossHpBar');
            const hpLabel = document.getElementById('mockBossHpLabel');
            const card = document.getElementById('mockBossCard');
            const claimBtn = document.getElementById('mockBossClaimBtn');

            if (s2) { t2.classList.add('slashed'); t2.style.color = 'var(--text-muted)'; }
            else { t2.classList.remove('slashed'); t2.style.color = 'var(--text-primary)'; }

            if (s3) { t3.classList.add('slashed'); t3.style.color = 'var(--text-muted)'; }
            else { t3.classList.remove('slashed'); t3.style.color = 'var(--text-primary)'; }

            let doneCount = 1 + (s2 ? 1 : 0) + (s3 ? 1 : 0);
            let pct = Math.max(0, Math.round(((3 - doneCount) / 3) * 100));

            hpBar.style.width = pct + '%';
            hpLabel.textContent = 'Sisa HP: ' + pct + '%';

            const rect = (el && el.closest('.subtask-row')) ? el.closest('.subtask-row').getBoundingClientRect() : card.getBoundingClientRect();
            const posX = rect.left + rect.width / 2;
            const posY = rect.top;

            if (isChecked) {
                // Attacking Boss
                card.classList.add('boss-hit-anim', 'boss-hit-shake');
                setTimeout(() => card.classList.remove('boss-hit-anim', 'boss-hit-shake'), 300);

                if (pct === 0) {
                    spawnCombatText(posX, posY, '<span class="text-danger fw-bold"><i class="fas fa-skull text-danger me-1"></i>💀 Pertahanan Boss Hancur!</span>');
                } else {
                    spawnCombatText(posX, posY, '<span class="text-danger fw-bold"><i class="fas fa-crosshairs text-danger me-1"></i>⚔️ -33% HP Boss</span>');
                }
            } else {
                // Unchecking / Healing Boss (amber/warning, without shake)
                spawnCombatText(posX, posY, '<span class="text-warning fw-bold"><i class="fas fa-undo-alt text-warning me-1"></i>↺ +33% HP Boss Pulih</span>');
            }

            if (pct === 0) {
                hpLabel.innerHTML = '<span class="text-accent fw-bold"><i class="fas fa-trophy"></i> HP 0% — BOSS RUNTUH!</span>';
                hpBar.style.backgroundColor = 'var(--accent)';
                claimBtn.disabled = false;
                claimBtn.className = 'btn-core btn-accent font-mono';
                claimBtn.innerHTML = '<i class="fas fa-crown me-1"></i> KLAIM HADIAH (+45 EXP)';
                claimBtn.style.opacity = '1';
                claimBtn.style.cursor = 'pointer';
                claimBtn.style.boxShadow = '0 0 18px rgba(16, 185, 129, 0.4)';
            } else {
                hpBar.style.backgroundColor = 'var(--status-danger)';
                claimBtn.disabled = true;
                claimBtn.className = 'btn-core btn-outline font-mono text-danger';
                claimBtn.innerHTML = '<i class="fas fa-lock me-1"></i> Lawan Boss';
                claimBtn.style.opacity = '0.65';
                claimBtn.style.cursor = 'not-allowed';
                claimBtn.style.boxShadow = 'none';
            }
        }

        function claimMockBoss() {
            const btn = document.getElementById('mockBossClaimBtn');
            if (btn.disabled) return;
            mockExp += 45;
            mockGold += 90;
            updateMockCapsule();
            if (typeof confetti === 'function') {
                confetti({ particleCount: 110, spread: 75, origin: { y: 0.6 } });
            }
            const rect = btn.getBoundingClientRect();
            spawnCombatText(rect.left + rect.width / 2, rect.top - 10, '<i class="fas fa-dragon text-warning"></i> +45 EXP • +90 GOLD (1.5x BOSS BONUS)');
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Hadiah Diklaim!';
            btn.disabled = true;
        }

        // 4. Tab 2: Dynamic Radar Simulation
        const radarValues = { int: 68, phy: 110, wlt: 185, hlt: 180, soc: 115 };
        // Baseline angles & distances
        const radarStats = {
            int: { val: 65, min: 110, max: 40, cx: 130, cy: 130, dx: 0, dy: -1 },
            phy: { val: 60, min: 110, max: 215, cx: 130, cy: 130, dx: 0.95, dy: -0.31 },
            wlt: { val: 55, min: 110, max: 185, cx: 130, cy: 130, dx: 0.59, dy: 0.81 },
            hlt: { val: 70, min: 110, max: 75, cx: 130, cy: 130, dx: -0.59, dy: 0.81 },
            soc: { val: 50, min: 110, max: 45, cx: 130, cy: 130, dx: -0.95, dy: -0.31 }
        };

        function trainAttribute(attr, idx) {
            const s = radarStats[attr];
            s.val = Math.min(100, s.val + 10);
            // Interpolate coordinate between radius 20 and radius 95
            const r = 25 + (s.val / 100) * 70;
            const x = Math.round(s.cx + s.dx * r);
            const y = Math.round(s.cy + s.dy * r);

            const dot = document.getElementById(`dot-${attr}`);
            if (dot) {
                dot.setAttribute('cx', x);
                dot.setAttribute('cy', y);
            }

            // Recalculate polygon points
            const pInt = `${document.getElementById('dot-int').getAttribute('cx')},${document.getElementById('dot-int').getAttribute('cy')}`;
            const pPhy = `${document.getElementById('dot-phy').getAttribute('cx')},${document.getElementById('dot-phy').getAttribute('cy')}`;
            const pWlt = `${document.getElementById('dot-wlt').getAttribute('cx')},${document.getElementById('dot-wlt').getAttribute('cy')}`;
            const pHlt = `${document.getElementById('dot-hlt').getAttribute('cx')},${document.getElementById('dot-hlt').getAttribute('cy')}`;
            const pSoc = `${document.getElementById('dot-soc').getAttribute('cx')},${document.getElementById('dot-soc').getAttribute('cy')}`;

            document.getElementById('lpRadarPolygon').setAttribute('points', `${pInt} ${pPhy} ${pWlt} ${pHlt} ${pSoc}`);

            mockExp += 15;
            mockGold += 5;
            updateMockCapsule();
            spawnCombatText(window.innerWidth / 2, window.innerHeight * 0.45, `<i class="fas fa-brain text-accent"></i> +15 EXP Atribut ${attr.toUpperCase()}!`);
        }

        // 5. Tab 3: Shop Simulation
        function buyMockShopItem(type, cost) {
            if (mockGold < cost) {
                spawnCombatText(window.innerWidth / 2, window.innerHeight * 0.45, '<i class="fas fa-exclamation-circle text-danger"></i> Gold Tidak Cukup!');
                return;
            }
            mockGold -= cost;
            if (type === 'minor') {
                mockHp = Math.min(100, mockHp + 25);
                spawnCombatText(window.innerWidth / 2, window.innerHeight * 0.45, '<i class="fas fa-heart text-danger"></i> -25G • +25 HP Pulih!');
            } else {
                spawnCombatText(window.innerWidth / 2, window.innerHeight * 0.45, '<i class="fas fa-snowflake text-info"></i> -50G • Perisai Freeze Bertambah!');
            }
            updateMockCapsule();
        }

        // 6. Bento Grid Living Micro-UI: Rank Cycle Automation
        (function initBentoRankSweep() {
            const ranks = [
                { name: 'F-Rank Novice', exp: 15 },
                { name: 'E-Rank Rookie', exp: 35 },
                { name: 'D-Rank Explorer', exp: 55 },
                { name: 'C-Rank Vanguard', exp: 70 },
                { name: 'B-Rank Elite', exp: 82 },
                { name: 'A-Rank Master', exp: 92 },
                { name: 'S-Rank Sovereign', exp: 100 }
            ];
            let cur = 2; // Start at D-Rank
            const titleEl = document.getElementById('bentoRankTitle');
            const expFill = document.getElementById('bentoExpFill');

            setInterval(() => {
                cur = (cur + 1) % ranks.length;
                document.querySelectorAll('.lp-rank-badge-node').forEach((node, i) => {
                    if (i === cur) node.classList.add('active-sweep');
                    else node.classList.remove('active-sweep');
                });
                if (titleEl) titleEl.textContent = ranks[cur].name;
                if (expFill) expFill.style.width = ranks[cur].exp + '%';
            }, 1400);
        })();

        // 7. Atmospheric 3D Canvas Stage Renderer behind Hero
        (function initHero3DStage() {
            const canvas = document.getElementById('hero-3d-canvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            let w = canvas.width = canvas.parentElement.offsetWidth || window.innerWidth;
            let h = canvas.height = canvas.parentElement.offsetHeight || 600;

            window.addEventListener('resize', () => {
                w = canvas.width = canvas.parentElement.offsetWidth || window.innerWidth;
                h = canvas.height = canvas.parentElement.offsetHeight || 600;
            }, { passive: true });

            // 3D Polyhedral Octahedron Geometry
            const vertices = [
                { x: 0, y: -90, z: 0 },
                { x: 75, y: 0, z: -75 },
                { x: -75, y: 0, z: -75 },
                { x: -75, y: 0, z: 75 },
                { x: 75, y: 0, z: 75 },
                { x: 0, y: 90, z: 0 }
            ];

            const faces = [
                [0, 1, 2], [0, 2, 3], [0, 3, 4], [0, 4, 1],
                [5, 2, 1], [5, 3, 2], [5, 4, 3], [5, 1, 4]
            ];

            let rotX = 0.2, rotY = 0.3;
            let targetRotX = 0.2, targetRotY = 0.3;

            window.addEventListener('mousemove', (e) => {
                const normX = (e.clientX / window.innerWidth) - 0.5;
                const normY = (e.clientY / window.innerHeight) - 0.5;
                targetRotY = normX * 1.4;
                targetRotX = normY * 0.9;
            }, { passive: true });

            function project(p, rx, ry) {
                const cosY = Math.cos(ry), sinY = Math.sin(ry);
                const x1 = p.x * cosY + p.z * sinY;
                const z1 = -p.x * sinY + p.z * cosY;

                const cosX = Math.cos(rx), sinX = Math.sin(rx);
                const y2 = p.y * cosX - z1 * sinX;
                const z2 = p.y * sinX + z1 * cosX;

                const fov = 380;
                const scale = fov / (fov + z2 + 180);
                return {
                    x: (w / 2) + x1 * scale,
                    y: (h * 0.42) + y2 * scale,
                    z: z2
                };
            }

            function draw() {
                ctx.clearRect(0, 0, w, h);
                rotY += 0.007; // Continuous subtle rotation
                rotX += (targetRotX - rotX) * 0.05;
                rotY += (targetRotY - rotY) * 0.03;

                const projected = vertices.map(v => project(v, rotX, rotY));

                const sortedFaces = faces.map(f => {
                    const avgZ = (projected[f[0]].z + projected[f[1]].z + projected[f[2]].z) / 3;
                    return { face: f, z: avgZ };
                }).sort((a, b) => b.z - a.z);

                sortedFaces.forEach(sf => {
                    const f = sf.face;
                    const p0 = projected[f[0]];
                    const p1 = projected[f[1]];
                    const p2 = projected[f[2]];

                    ctx.beginPath();
                    ctx.moveTo(p0.x, p0.y);
                    ctx.lineTo(p1.x, p1.y);
                    ctx.lineTo(p2.x, p2.y);
                    ctx.closePath();

                    ctx.fillStyle = sf.z > 0 ? 'rgba(16, 185, 129, 0.08)' : 'rgba(99, 102, 241, 0.16)';
                    ctx.fill();

                    ctx.strokeStyle = sf.z > 0 ? 'rgba(16, 185, 129, 0.45)' : 'rgba(99, 102, 241, 0.65)';
                    ctx.lineWidth = 1.6;
                    ctx.stroke();
                });

                requestAnimationFrame(draw);
            }
            requestAnimationFrame(draw);
        })();

        // 8. GSAP 3D Scroll Scrub on App Mockup (Native Scroll Friendly)
        (function initGsapScrub() {
            const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (!canHover || reduceMotion) return;

            if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
                gsap.registerPlugin(ScrollTrigger);
                gsap.fromTo("#lp-showcase",
                    { transform: "perspective(1400px) rotateX(12deg) scale(0.96)" },
                    {
                        transform: "perspective(1400px) rotateX(0deg) scale(1)",
                        ease: "power2.out",
                        scrollTrigger: {
                            trigger: "#lp-showcase",
                            start: "top 80%",
                            end: "top 45%",
                            scrub: 1
                        }
                    }
                );
            }
        })();

        // 8.5. Motion Reel Interactive Player Controls & Viewport Optimizer
        function switchReelDuration(duration, btn) {
            const iframe = document.getElementById('lpReelIframe');
            const label = document.getElementById('lpReelTopbarLabel');
            const navLink = document.querySelector('.lp-nav-link[href="#lp-reel"]');
            if (!iframe) return;

            document.querySelectorAll('.lp-reel-duration-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');

            const targetSrc = (duration === '30s') ? 'assets/reel-30s.html' : 'assets/reel-15s.html';
            if (iframe.getAttribute('src') !== targetSrc) {
                iframe.src = targetSrc;
            } else {
                replayReel();
            }

            if (label) {
                label.textContent = `lifevuww-reel-${duration} // 120-bpm`;
            }
            if (navLink) {
                navLink.textContent = `Teaser ${duration}`;
            }
        }

        function replayReel() {
            const iframe = document.getElementById('lpReelIframe');
            if (!iframe) return;
            try {
                if (iframe.contentWindow) {
                    iframe.contentWindow.postMessage({ type: 'REEL_RESTART' }, '*');
                }
            } catch (e) {
                iframe.src = iframe.src;
            }
        }

        function toggleReelFullscreen() {
            const container = document.getElementById('lpReelBody');
            const iframe = document.getElementById('lpReelIframe');
            const elem = container || iframe;
            if (!elem) return;

            if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.mozFullScreenElement && !document.msFullscreenElement) {
                if (elem.requestFullscreen) {
                    elem.requestFullscreen();
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen();
                } else if (elem.mozRequestFullScreen) {
                    elem.mozRequestFullScreen();
                } else if (elem.msRequestFullscreen) {
                    elem.msRequestFullscreen();
                } else if (iframe && iframe.requestFullscreen) {
                    iframe.requestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        }

        document.addEventListener('fullscreenchange', updateReelFullscreenState);
        document.addEventListener('webkitfullscreenchange', updateReelFullscreenState);
        function updateReelFullscreenState() {
            const btn = document.getElementById('btnReelFullscreen');
            if (!btn) return;
            const isFs = !!(document.fullscreenElement || document.webkitFullscreenElement);
            const icon = btn.querySelector('i');
            const span = btn.querySelector('span');
            if (icon) icon.className = isFs ? 'fas fa-compress' : 'fas fa-expand';
            if (span) span.textContent = isFs ? 'Exit' : 'Fullscreen';
        }

        (function initReelViewportObserver() {
            const reelSection = document.getElementById('lp-reel');
            const iframe = document.getElementById('lpReelIframe');
            if (!reelSection || !iframe) return;

            function sendReelMsg(action) {
                try {
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage({ type: action }, '*');
                    }
                } catch (e) {}
            }

            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            sendReelMsg('REEL_PLAY');
                        } else {
                            sendReelMsg('REEL_PAUSE');
                        }
                    });
                }, { threshold: 0.15 });
                observer.observe(reelSection);
            }
        })();

        // 9. Antigravity Stardust Canvas
        (function initAntigravity() {
            const canvas = document.getElementById('antigravity-canvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            let width = canvas.width = window.innerWidth;
            let height = canvas.height = window.innerHeight;

            window.addEventListener('resize', () => {
                width = canvas.width = window.innerWidth;
                height = canvas.height = window.innerHeight;
            }, { passive: true });

            const particles = Array.from({ length: 36 }, () => ({
                x: Math.random() * width,
                y: Math.random() * height,
                vx: (Math.random() - 0.5) * 0.3,
                vy: (Math.random() - 0.5) * 0.3,
                radius: Math.random() * 1.3 + 0.6,
                alpha: Math.random() * 0.2 + 0.08,
                color: Math.random() > 0.5 ? '245, 245, 247' : '161, 161, 170'
            }));

            let mouseX = -9999, mouseY = -9999;
            window.addEventListener('mousemove', e => { mouseX = e.clientX; mouseY = e.clientY; }, { passive: true });

            function loop() {
                ctx.clearRect(0, 0, width, height);
                particles.forEach(p => {
                    p.x += p.vx; p.y += p.vy;
                    if (p.x < 0) p.x = width; if (p.x > width) p.x = 0;
                    if (p.y < 0) p.y = height; if (p.y > height) p.y = 0;
                    const dx = p.x - mouseX, dy = p.y - mouseY, dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 130 && dist > 0) {
                        const f = (1 - dist / 130) * 3;
                        p.x += (dx / dist) * f;
                        p.y += (dy / dist) * f;
                    }
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(${p.color}, ${p.alpha})`;
                    ctx.fill();
                });
                requestAnimationFrame(loop);
            }
            requestAnimationFrame(loop);
        })();

        // 10. Magnetic Cursor Follower
        (function initMagneticCursor() {
            const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
            if (!canHover) return;
            const dot = document.getElementById('cursorDot');
            const ring = document.getElementById('cursorRing');
            if (!dot || !ring) return;

            let mouseX = window.innerWidth / 2, mouseY = window.innerHeight / 2;
            let ringX = mouseX, ringY = mouseY;

            window.addEventListener('mousemove', e => {
                mouseX = e.clientX;
                mouseY = e.clientY;
                dot.style.transform = `translate3d(${mouseX}px, ${mouseY}px, 0) translate(-50%, -50%)`;
            }, { passive: true });

            function anim() {
                ringX += (mouseX - ringX) * 0.18;
                ringY += (mouseY - ringY) * 0.18;
                ring.style.transform = `translate3d(${ringX}px, ${ringY}px, 0) translate(-50%, -50%)`;
                requestAnimationFrame(anim);
            }
            requestAnimationFrame(anim);

            document.addEventListener('pointerenter', e => {
                if (e.target.closest('.btn-core, a, button, input, .lp-tab-btn, .lp-btn-capsule, .spotlight-card')) {
                    ring.classList.add('magnetic');
                }
            }, true);

            document.addEventListener('pointerleave', e => {
                if (e.target.closest('.btn-core, a, button, input, .lp-tab-btn, .lp-btn-capsule, .spotlight-card')) {
                    ring.classList.remove('magnetic');
                }
            }, true);
        })();
    </script>
</body>
</html>
