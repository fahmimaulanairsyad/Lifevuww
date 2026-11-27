<?php
// hero-review.php - Interactive Review Showcase untuk Konsep 3D Creation Hero Lifevuww
$allowedOpts = ['a', 'b', 'c', 'd'];
$initOpt = in_array($_GET['opt'] ?? 'a', $allowedOpts, true) ? $_GET['opt'] : 'a';
$allowedStyles = ['hybrid', 'halftone', '3d'];
$initStyle = in_array($_GET['style'] ?? 'hybrid', $allowedStyles, true) ? $_GET['style'] : 'hybrid';
$initConverged = (($_GET['state'] ?? '') === 'converged');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Review Hero 3D - Lifevuww</title>
    <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha384-3B6NwesSXE7YJlcLI9RpRqGf2p/EgVH8BgoKTaUrmKNDkHPStTQ3EyoYjCGXaOTS" crossorigin="anonymous">
    <link href="assets/css/style.css?v=20261127_01" rel="stylesheet">
    <style>
        html, body {
            overflow-x: hidden;
            max-width: 100%;
        }
        /* Review Hero 3D Creation Composition (Self-contained until finalized into index.php) */
        .lp-creation-hero {
            position: relative;
            min-height: 100dvh;
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 86px 1.25rem 2.2rem 1.25rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            overflow: hidden;
            text-align: center;
        }

        .lp-creation-header {
            position: relative;
            z-index: 5;
            max-width: 720px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .lp-creation-title {
            font-family: var(--font-display, var(--font-sans));
            font-size: clamp(2.15rem, 4.7vw, 3.85rem);
            font-weight: 700;
            letter-spacing: -0.04em;
            line-height: 1.08;
            color: #fafafa;
            margin: 0 0 0.85rem 0;
        }

        .lp-creation-subtitle {
            max-width: 52ch;
            color: #a1a1aa;
            font-size: clamp(0.9rem, 1.35vw, 1.02rem);
            line-height: 1.55;
            margin: 0 0 1.35rem 0;
        }

        .lp-creation-cta-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .lp-btn-adventure {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 0.72rem 1.45rem;
            border-radius: 999px;
            background: #fafafa;
            color: #09090b;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45), 0 0 0 0 rgba(16, 185, 129, 0);
            transition: transform 0.18s cubic-bezier(0.22, 1, 0.36, 1),
                        background-color 0.2s ease,
                        color 0.2s ease,
                        border-color 0.2s ease,
                        box-shadow 0.25s ease;
            cursor: pointer;
            white-space: nowrap;
        }

        .lp-btn-adventure:hover,
        .lp-btn-adventure:focus-visible {
            background: #10b981;
            color: #032016;
            border-color: #34d399;
            transform: translateY(-2px);
            box-shadow: 0 14px 36px rgba(16, 185, 129, 0.28);
            outline: none;
        }

        .lp-btn-adventure:active {
            transform: translateY(0) scale(0.98);
        }

        .lp-btn-demo-ghost {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0.7rem 1.25rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.04);
            color: #e4e4e7;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.14);
            text-decoration: none;
            transition: transform 0.18s ease, border-color 0.2s ease, background-color 0.2s ease;
            white-space: nowrap;
        }

        .lp-btn-demo-ghost:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(16, 185, 129, 0.45);
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Interactive 3D Creation Stage */
        .lp-creation-stage {
            position: relative;
            width: 100%;
            max-width: 1140px;
            height: clamp(290px, 41vh, 420px);
            margin: 0.75rem auto 3.8rem auto;
            pointer-events: none;
            user-select: none;
            perspective: 1100px;
        }

        .lp-stage-aura {
            position: absolute;
            left: 50%;
            top: 50%;
            width: clamp(280px, 42vw, 520px);
            height: clamp(260px, 38vw, 460px);
            transform: translate(-50%, -50%);
            background: radial-gradient(circle, rgba(16, 185, 129, 0.22) 0%, rgba(16, 185, 129, 0.06) 44%, transparent 72%);
            transition: transform 0.65s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.45s ease;
            opacity: 0.82;
            z-index: 1;
        }

        #lp-relic-rays {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            pointer-events: none;
        }

        .lp-hand-wrap {
            position: absolute;
            width: clamp(235px, 40vw, 475px);
            z-index: 4;
            will-change: transform;
            transition: transform 0.72s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.35s ease;
        }

        .lp-hand-left-wrap {
            left: -4.5%;
            top: -66%;
            transform-origin: left center;
            transform: translate3d(0, 0, 0);
        }

        .lp-hand-right-wrap {
            right: -1%;
            top: 39%;
            bottom: auto;
            transform-origin: right bottom;
            transform: translate3d(0, 0, 0);
        }

        .lp-hand-img {
            display: block;
            width: 100%;
            height: auto;
            pointer-events: none;
            filter: drop-shadow(0 18px 40px rgba(0, 0, 0, 0.7));
        }

        .lp-center-relic-wrap {
            position: absolute;
            left: 50%;
            top: 49%;
            width: clamp(150px, 20vw, 245px);
            aspect-ratio: 1 / 1;
            transform: translate(-50%, -50%);
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: center;
            transform-style: preserve-3d;
        }

        .lp-center-relic-inner {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: translate3d(0, 0, 0) scale(1);
            transition: transform 0.65s cubic-bezier(0.22, 1, 0.36, 1);
            will-change: transform;
            animation: lpRelicFloat 4.8s ease-in-out infinite;
        }

        @keyframes lpRelicFloat {
            0%, 100% { transform: translate3d(0, -5px, 0) scale(1); }
            50% { transform: translate3d(0, 6px, 0) scale(1.015); }
        }

        .lp-center-relic-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 0 28px rgba(16, 185, 129, 0.38));
            transition: filter 0.45s ease, transform 0.45s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.25s ease;
            will-change: transform, opacity;
        }

        /* Shockwave Ring on Contact */
        .lp-contact-ring {
            position: absolute;
            left: 50%;
            top: 49%;
            width: clamp(140px, 18vw, 210px);
            aspect-ratio: 1 / 1;
            border-radius: 50%;
            border: 2px solid rgba(52, 211, 153, 0.85);
            box-shadow: 0 0 42px rgba(16, 185, 129, 0.55), inset 0 0 28px rgba(16, 185, 129, 0.35);
            transform: translate(-50%, -50%) scale(0.45);
            opacity: 0;
            z-index: 2;
            pointer-events: none;
        }

        .lp-creation-stage.is-primed .lp-hand-left-wrap {
            transform: translate3d(clamp(16px, 2.5vw, 34px), clamp(4px, 0.8vw, 12px), 0);
        }

        .lp-creation-stage.is-primed .lp-hand-right-wrap {
            transform: translate3d(calc(-1 * clamp(16px, 2.5vw, 34px)), calc(-1 * clamp(4px, 0.8vw, 12px)), 0);
        }

        .lp-creation-stage.is-primed .lp-center-relic-img {
            transform: scale(1.06);
            filter: drop-shadow(0 0 42px rgba(16, 185, 129, 0.65));
        }

        .lp-creation-stage.is-primed .lp-stage-aura {
            transform: translate(-50%, -50%) scale(1.12);
            opacity: 1;
        }

        /* Active Converging State (after clicking Mulai Petualangan) */
        .lp-creation-stage.is-converging .lp-hand-left-wrap {
            transform: translate3d(clamp(108px, 18.5vw, 224px), clamp(78px, 9vw, 114px), 0) scale(1.03);
        }

        .lp-creation-stage.is-converging .lp-hand-right-wrap {
            transform: translate3d(calc(-1 * clamp(72px, 12.8vw, 154px)), calc(-1 * clamp(22px, 3vw, 40px)), 0) scale(1.03);
        }

        .lp-creation-stage.is-converging .lp-center-relic-img {
            transform: scale(1.13);
            filter: drop-shadow(0 0 64px rgba(52, 211, 153, 0.92));
        }

        .lp-creation-stage.is-converging .lp-stage-aura {
            transform: translate(-50%, -50%) scale(1.38);
            opacity: 1;
        }

        .lp-creation-stage.is-converging .lp-contact-ring {
            animation: lpContactPulse 0.85s cubic-bezier(0.16, 1, 0.3, 1) 0.32s forwards;
        }

        @keyframes lpContactPulse {
            0% {
                transform: translate(-50%, -50%) scale(0.5);
                opacity: 0.95;
            }
            100% {
                transform: translate(-50%, -50%) scale(2.05);
                opacity: 0;
            }
        }

        /* Floating Review Control Dock (HANYA untuk halaman review ini) */
        .review-dock {
            position: fixed;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1200;
            background: rgba(17, 17, 20, 0.92);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 16px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            max-width: calc(100vw - 24px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.78);
        }

        .review-group {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
            justify-content: center;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 3px;
            max-width: 100%;
        }

        .review-pill {
            border: none;
            background: transparent;
            color: #a1a1aa;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 7px;
            cursor: pointer;
            transition: background-color 0.15s ease, color 0.15s ease;
            white-space: nowrap;
        }

        .review-pill:hover {
            color: #fafafa;
        }

        .review-pill.active {
            background: #10b981;
            color: #032016;
            font-weight: 600;
        }

        .review-status-pill {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            color: #34d399;
            padding: 0 6px;
            white-space: nowrap;
        }

        .review-reset-btn {
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.06);
            color: #fafafa;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 5px 11px;
            border-radius: 8px;
            cursor: pointer;
            white-space: nowrap;
        }

        .review-reset-btn:hover {
            border-color: #10b981;
            color: #34d399;
        }

        @media (max-width: 768px) {
            .lp-creation-hero {
                padding: 80px 0.9rem 7.5rem 0.9rem;
                justify-content: flex-start;
                gap: 0.75rem;
            }
            .lp-creation-header {
                width: 100%;
                max-width: 100%;
            }
            .lp-creation-title {
                font-size: clamp(1.75rem, 7.6vw, 2.3rem);
                line-height: 1.12;
            }
            .lp-creation-subtitle {
                font-size: 0.88rem;
                max-width: 100%;
            }
            .lp-creation-stage {
                height: 245px;
                margin-top: 0.5rem;
                margin-bottom: 1.5rem;
            }
            .lp-hand-wrap {
                width: 48vw;
                max-width: 215px;
            }
            .lp-hand-left-wrap {
                left: -5%;
                top: 8%;
            }
            .lp-hand-right-wrap {
                right: -5%;
                bottom: 4%;
            }
            .lp-center-relic-wrap {
                width: 122px;
                top: 50%;
            }
            .lp-creation-stage.is-converging .lp-hand-left-wrap {
                transform: translate3d(58px, 8px, 0) scale(1.03);
            }
            .lp-creation-stage.is-converging .lp-hand-right-wrap {
                transform: translate3d(-48px, -10px, 0) scale(1.03);
            }
            .lp-creation-cta-wrap {
                gap: 0.5rem;
                width: 100%;
            }
            .lp-btn-adventure,
            .lp-btn-demo-ghost {
                padding: 0.58rem 1.05rem;
                font-size: 0.82rem;
            }
            .review-dock {
                left: 10px;
                right: 10px;
                bottom: 10px;
                transform: none;
                width: auto;
                max-width: none;
                padding: 6px 8px;
                gap: 5px;
            }
            .review-pill {
                font-size: 0.68rem;
                padding: 4px 7px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .lp-center-relic-inner { animation: none; }
            .lp-hand-wrap,
            .lp-center-relic-img,
            .lp-stage-aura { transition: none; }
            .lp-creation-stage.is-converging .lp-contact-ring { animation: none; }
        }
    </style>
</head>
<body class="landing-body">

    <canvas id="antigravity-canvas" class="antigravity-canvas"></canvas>
    <div class="film-grain"></div>
    <div class="cursor-dot" id="cursorDot"></div>
    <div class="cursor-ring" id="cursorRing"></div>

    <!-- Floating Pill Glass Navbar (Persis index.php) -->
    <nav class="lp-navbar-pill">
        <a href="index.php" class="lp-nav-brand">
            <img src="assets/images/logo.svg" alt="Lifevuww Logo" width="24" height="24">
            <span>Lifevuww</span>
        </a>
        <ul class="lp-nav-links d-none d-md-flex">
            <li><a href="index.php#lp-showcase" class="lp-nav-link">Simulasi Live</a></li>
            <li><a href="index.php#lp-bento" class="lp-nav-link">Fitur RPG</a></li>
            <li><a href="index.php#lp-ranks" class="lp-nav-link">Sistem Rank</a></li>
        </ul>
        <div class="d-flex align-items-center gap-2">
            <a href="login.php" class="btn-core btn-ghost" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">Masuk</a>
            <a href="register.php" class="lp-btn-capsule">
                <span>Mulai Gratis</span>
                <i class="fas fa-arrow-right" style="font-size: 0.7rem;"></i>
            </a>
        </div>
    </nav>

    <?php
    $optCodeMap = [
        'a' => 'OPSI A • APEL KRISTAL',
        'b' => 'OPSI B • BINTANG POROS',
        'c' => 'OPSI C • JAM PASIR CHRONO',
        'd' => 'OPSI D • MAHKOTA HALO',
    ];
    ?>
    <!-- Main Hero Section -->
    <main class="lp-creation-hero" id="lpHeroRoot">
        <div class="lp-creation-header">
            <span class="badge-status accent font-mono mb-3" style="font-size: 0.72rem; padding: 0.28rem 0.8rem; border-radius: 999px;">
                PREVIEW HERO 3D • <span id="activeLabelCode"><?php echo htmlspecialchars($optCodeMap[$initOpt], ENT_QUOTES, 'UTF-8'); ?></span>
            </span>

            <h1 class="lp-creation-title">
                Disiplin Nyata dalam<br>Format Petualangan <span class="text-accent">RPG</span>
            </h1>

            <p class="lp-creation-subtitle" id="heroDynamicSub">
                Ubah rutinitas harianmu menjadi petualangan level-up. Raih kendali atas kebiasaanmu dan bangun karakter terbaikmu setiap hari.
            </p>

            <div class="lp-creation-cta-wrap">
                <a href="register.php" class="lp-btn-adventure" id="btnMulaiPetualangan">
                    <span>Mulai Petualangan</span>
                    <i class="fas fa-arrow-right" style="font-size: 0.8rem;"></i>
                </a>
                <button type="button" class="lp-btn-demo-ghost" id="btnTriggerDemoAnim">
                    <i class="fas fa-hand-sparkles text-accent" style="font-size: 0.8rem;"></i>
                    <span>Uji Sentuhan Tangan</span>
                </button>
            </div>
        </div>

        <!-- 3D Creation-of-Hands Interactive Stage -->
        <div class="lp-creation-stage <?php echo $initConverged ? 'is-converging' : ''; ?>" id="creationStage">
            <div class="lp-stage-aura"></div>
            <canvas id="lp-relic-rays"></canvas>
            <div class="lp-contact-ring" id="contactRing"></div>

            <!-- Left Hand -->
            <div class="lp-hand-wrap lp-hand-left-wrap" id="handLeftWrap">
                <img
                    src="assets/images/hero/<?php echo $initStyle === '3d' ? 'hand-left-3d.png' : 'hand-left-halftone.png'; ?>"
                    alt="Tangan Sistem Halftone"
                    class="lp-hand-img"
                    id="handLeftImg">
            </div>

            <!-- Glowing 3D Center Relic -->
            <div class="lp-center-relic-wrap" id="centerRelicWrap">
                <div class="lp-center-relic-inner" id="centerRelicInner">
                    <?php
                    $optFileMap = [
                        'a' => 'obj-a-apple',
                        'b' => 'obj-b-star',
                        'c' => 'obj-c-hourglass',
                        'd' => 'obj-d-crown',
                    ];
                    $centerBase = $optFileMap[$initOpt];
                    $centerSuffix = ($initStyle === 'halftone') ? '-halftone.png' : '-3d.png';
                    ?>
                    <img
                        src="assets/images/hero/<?php echo htmlspecialchars($centerBase . $centerSuffix, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="Objek Inti Lifevuww"
                        class="lp-center-relic-img"
                        id="centerRelicImg">
                </div>
            </div>

            <!-- Right Hand -->
            <div class="lp-hand-wrap lp-hand-right-wrap" id="handRightWrap">
                <img
                    src="assets/images/hero/<?php echo $initStyle === '3d' ? 'hand-right-3d.png' : 'hand-right-halftone.png'; ?>"
                    alt="Tangan Manusia Halftone"
                    class="lp-hand-img"
                    id="handRightImg">
            </div>
        </div>
    </main>

    <!-- Review Switcher Dock -->
    <aside class="review-dock" aria-label="Pemilih Review Hero">
        <div class="review-group" id="optSwitcher">
            <button type="button" class="review-pill <?php echo $initOpt === 'a' ? 'active' : ''; ?>" data-opt="a">A · Apel Kristal</button>
            <button type="button" class="review-pill <?php echo $initOpt === 'b' ? 'active' : ''; ?>" data-opt="b">B · Bintang Poros</button>
            <button type="button" class="review-pill <?php echo $initOpt === 'c' ? 'active' : ''; ?>" data-opt="c">C · Jam Pasir</button>
            <button type="button" class="review-pill <?php echo $initOpt === 'd' ? 'active' : ''; ?>" data-opt="d">D · Mahkota Halo</button>
        </div>
        <div class="review-group" id="styleSwitcher">
            <button type="button" class="review-pill <?php echo $initStyle === 'hybrid' ? 'active' : ''; ?>" data-style="hybrid">Hybrid (Referensi)</button>
            <button type="button" class="review-pill <?php echo $initStyle === 'halftone' ? 'active' : ''; ?>" data-style="halftone">Full Halftone</button>
            <button type="button" class="review-pill <?php echo $initStyle === '3d' ? 'active' : ''; ?>" data-style="3d">Full 3D Halus</button>
        </div>
        <button type="button" class="review-reset-btn" id="btnResetAnim">
            <i class="fas fa-rotate-left me-1"></i> Reset
        </button>
        <span class="review-status-pill" id="reviewStatusText">Siap diuji</span>
    </aside>

    <script>
        (function () {
            const OPTIONS = {
                a: { code: 'OPSI A • APEL KRISTAL', base: 'obj-a-apple', label: 'Apel Kristal Emerald' },
                b: { code: 'OPSI B • BINTANG POROS', base: 'obj-b-star', label: 'Relik Bintang Poros' },
                c: { code: 'OPSI C • JAM PASIR CHRONO', base: 'obj-c-hourglass', label: 'Inti Jam Pasir Chrono' },
                d: { code: 'OPSI D • MAHKOTA HALO', base: 'obj-d-crown', label: 'Mahkota Halo Sovereign' }
            };

            let currentOpt = <?php echo json_encode($initOpt); ?>;
            let currentStyle = <?php echo json_encode($initStyle); ?>;
            let isConverged = <?php echo $initConverged ? 'true' : 'false'; ?>;
            let burstStrength = isConverged ? 1.0 : 0.0;

            const stage = document.getElementById('creationStage');
            const handLeftImg = document.getElementById('handLeftImg');
            const handRightImg = document.getElementById('handRightImg');
            const centerRelicImg = document.getElementById('centerRelicImg');
            const centerRelicWrap = document.getElementById('centerRelicWrap');
            const activeLabelCode = document.getElementById('activeLabelCode');
            const reviewStatusText = document.getElementById('reviewStatusText');
            const btnAdventure = document.getElementById('btnMulaiPetualangan');
            const btnDemoAnim = document.getElementById('btnTriggerDemoAnim');
            const btnReset = document.getElementById('btnResetAnim');

            function updateAssets() {
                const opt = OPTIONS[currentOpt] || OPTIONS.a;
                activeLabelCode.textContent = opt.code;

                const handSuffix = (currentStyle === '3d') ? '3d.png' : 'halftone.png';
                const objSuffix = (currentStyle === 'halftone') ? '-halftone.png' : '-3d.png';

                handLeftImg.src = 'assets/images/hero/hand-left-' + handSuffix;
                handRightImg.src = 'assets/images/hero/hand-right-' + handSuffix;

                centerRelicImg.style.opacity = '0.25';
                centerRelicImg.src = 'assets/images/hero/' + opt.base + objSuffix;
                centerRelicImg.onload = function () {
                    centerRelicImg.style.opacity = '1';
                };
                if (centerRelicImg.complete) {
                    centerRelicImg.style.opacity = '1';
                }

                document.querySelectorAll('#optSwitcher .review-pill').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.opt === currentOpt);
                });
                document.querySelectorAll('#styleSwitcher .review-pill').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.style === currentStyle);
                });
            }

            function triggerConverge(navigateOnDone) {
                stage.classList.remove('is-primed');
                // Restart pulse animation cleanly
                stage.classList.remove('is-converging');
                void stage.offsetWidth;
                stage.classList.add('is-converging');
                isConverged = true;
                burstStrength = 1.0;
                reviewStatusText.textContent = navigateOnDone ? 'Tangan menyentuh! (Simulasi ke register)' : 'Sentuhan aktif!';

                if (navigateOnDone) {
                    // On review page, pause 1.4s so user can admire contact before optional reset or link
                    setTimeout(() => {
                        reviewStatusText.textContent = 'Kontak tercapai • Klik Reset';
                    }, 900);
                }
            }

            function resetAnim() {
                isConverged = false;
                burstStrength = 0.0;
                stage.classList.remove('is-converging', 'is-primed');
                reviewStatusText.textContent = 'Siap diuji';
            }

            btnAdventure.addEventListener('pointerenter', () => {
                if (!isConverged) stage.classList.add('is-primed');
            });
            btnAdventure.addEventListener('pointerleave', () => {
                stage.classList.remove('is-primed');
            });
            btnAdventure.addEventListener('click', (e) => {
                e.preventDefault();
                triggerConverge(true);
            });
            btnDemoAnim.addEventListener('click', () => {
                if (isConverged) {
                    resetAnim();
                    setTimeout(() => triggerConverge(false), 120);
                } else {
                    triggerConverge(false);
                }
            });
            btnReset.addEventListener('click', resetAnim);

            document.querySelectorAll('#optSwitcher .review-pill').forEach(btn => {
                btn.addEventListener('click', () => {
                    currentOpt = btn.dataset.opt;
                    updateAssets();
                });
            });
            document.querySelectorAll('#styleSwitcher .review-pill').forEach(btn => {
                btn.addEventListener('click', () => {
                    currentStyle = btn.dataset.style;
                    updateAssets();
                });
            });

            // Smooth 3D tilt parallax on mouse move
            const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            let targetTiltX = 0, targetTiltY = 0, curTiltX = 0, curTiltY = 0;
            if (!reduceMotion) {
                window.addEventListener('mousemove', (e) => {
                    const nx = (e.clientX / window.innerWidth) - 0.5;
                    const ny = (e.clientY / window.innerHeight) - 0.5;
                    targetTiltY = nx * 22;
                    targetTiltX = -ny * 18;
                }, { passive: true });
            }

            // Volumetric Emerald Starburst Rays Canvas behind the Center Relic
            const rayCanvas = document.getElementById('lp-relic-rays');
            const rayCtx = rayCanvas ? rayCanvas.getContext('2d') : null;
            let rw = 0, rh = 0;

            function resizeRayCanvas() {
                if (!rayCanvas) return;
                const dpr = Math.min(window.devicePixelRatio || 1, 2);
                rw = stage.clientWidth || 800;
                rh = stage.clientHeight || 360;
                rayCanvas.width = rw * dpr;
                rayCanvas.height = rh * dpr;
                rayCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
            }
            resizeRayCanvas();
            window.addEventListener('resize', resizeRayCanvas, { passive: true });

            const sparks = Array.from({ length: 24 }, (_, i) => ({
                ang: (i / 24) * Math.PI * 2,
                dist: 24 + (i % 5) * 26,
                speed: 0.16 + (i % 4) * 0.08,
                size: 1.0 + (i % 3) * 0.7
            }));

            let t = 0;
            function frameLoop() {
                t += 0.016;
                curTiltX += (targetTiltX - curTiltX) * 0.08;
                curTiltY += (targetTiltY - curTiltY) * 0.08;
                if (centerRelicWrap && !reduceMotion) {
                    centerRelicWrap.style.transform =
                        `translate(-50%, -50%) perspective(900px) rotateX(${curTiltX.toFixed(2)}deg) rotateY(${curTiltY.toFixed(2)}deg)`;
                }

                if (rayCtx) {
                    rayCtx.clearRect(0, 0, rw, rh);
                    const cx = rw * 0.5;
                    const cy = rh * 0.49;
                    burstStrength = isConverged
                        ? burstStrength + (0.65 - burstStrength) * 0.06
                        : burstStrength * 0.92;

                    const baseAlpha = 0.22 + burstStrength * 0.45;
                    const rayCount = 16;
                    for (let i = 0; i < rayCount; i++) {
                        const a = (i / rayCount) * Math.PI * 2 + (reduceMotion ? 0 : t * 0.12);
                        const len = Math.min(rw, rh) * (0.28 + (i % 2 === 0 ? 0.16 : 0.06) + burstStrength * 0.18);
                        const grad = rayCtx.createLinearGradient(cx, cy, cx + Math.cos(a) * len, cy + Math.sin(a) * len);
                        grad.addColorStop(0, `rgba(167, 243, 208, ${(baseAlpha * 0.9).toFixed(3)})`);
                        grad.addColorStop(0.35, `rgba(16, 185, 129, ${(baseAlpha * 0.42).toFixed(3)})`);
                        grad.addColorStop(1, 'rgba(16, 185, 129, 0)');
                        rayCtx.beginPath();
                        rayCtx.moveTo(cx, cy);
                        rayCtx.lineTo(cx + Math.cos(a) * len, cy + Math.sin(a) * len);
                        rayCtx.strokeStyle = grad;
                        rayCtx.lineWidth = (i % 2 === 0) ? 2.2 : 1.1;
                        rayCtx.stroke();
                    }

                    // Orbiting emerald stardust sparks
                    if (!reduceMotion) {
                        sparks.forEach(sp => {
                            sp.dist += sp.speed * (1 + burstStrength * 2.8);
                            if (sp.dist > Math.min(rw, rh) * 0.46) sp.dist = 18;
                            const px = cx + Math.cos(sp.ang + t * 0.2) * sp.dist;
                            const py = cy + Math.sin(sp.ang + t * 0.2) * sp.dist * 0.86;
                            const a = (1 - sp.dist / (Math.min(rw, rh) * 0.46)) * (0.45 + burstStrength * 0.45);
                            rayCtx.beginPath();
                            rayCtx.arc(px, py, sp.size, 0, Math.PI * 2);
                            rayCtx.fillStyle = `rgba(110, 231, 183, ${a.toFixed(3)})`;
                            rayCtx.fill();
                        });
                    }
                }
                requestAnimationFrame(frameLoop);
            }
            requestAnimationFrame(frameLoop);

            // Ambient background stardust matching index.php
            const bgCanvas = document.getElementById('antigravity-canvas');
            if (bgCanvas && !reduceMotion) {
                const ctx = bgCanvas.getContext('2d');
                let bw = bgCanvas.width = window.innerWidth;
                let bh = bgCanvas.height = window.innerHeight;
                window.addEventListener('resize', () => {
                    bw = bgCanvas.width = window.innerWidth;
                    bh = bgCanvas.height = window.innerHeight;
                }, { passive: true });
                const dots = Array.from({ length: 30 }, () => ({
                    x: Math.random() * bw,
                    y: Math.random() * bh,
                    vx: (Math.random() - 0.5) * 0.25,
                    vy: (Math.random() - 0.5) * 0.25,
                    r: Math.random() * 1.2 + 0.5,
                    a: Math.random() * 0.16 + 0.06
                }));
                function bgLoop() {
                    ctx.clearRect(0, 0, bw, bh);
                    dots.forEach(d => {
                        d.x += d.vx; d.y += d.vy;
                        if (d.x < 0) d.x = bw; if (d.x > bw) d.x = 0;
                        if (d.y < 0) d.y = bh; if (d.y > bh) d.y = 0;
                        ctx.beginPath();
                        ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
                        ctx.fillStyle = `rgba(228, 228, 231, ${d.a})`;
                        ctx.fill();
                    });
                    requestAnimationFrame(bgLoop);
                }
                requestAnimationFrame(bgLoop);
            }
        })();
    </script>
</body>
</html>
