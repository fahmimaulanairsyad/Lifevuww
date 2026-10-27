<?php
// pages/skills.php
require_once __DIR__ . '/../config/database.php';
enforce_login();

$pageTitle = 'Keahlian';
require_once __DIR__ . '/../includes/header.php';

// Ambil data progres skill berdasarkan kategori (1 query tunggal)
$categories = ['Intelligence', 'Physical', 'Health', 'Wealth', 'Social'];
$stat_data = array_fill_keys($categories, 0);
$maxProgress = 0;

$stmt = $pdo->prepare("SELECT skill_id, progress FROM skills WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userSkills = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($categories as $category) {
    $skillId = skill_id_for_category($category)[0];
    $progress = (int) ($userSkills[$skillId] ?? 0);
    $stat_data[$category] = $progress;
    if ($progress > $maxProgress) {
        $maxProgress = $progress;
    }
}

// Urutkan tampilan stat dari level tertinggi ke terendah untuk list
$sorted_stats = $stat_data;
arsort($sorted_stats);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Matriks Keahlian & Atribut</h1>
        <p class="page-subtitle">Visualisasi multidimensi perkembangan lima pilar karakter hidupmu.</p>
    </div>
</div>

<!-- Main Radar Console Panel -->
<div class="bento-panel spotlight-card holo-card mx-auto mb-4" style="max-width: 980px;">
    <div class="row align-items-center g-4">
        <div class="col-md-5">
            <div class="panel-header mb-3">
                <h3 class="panel-title"><i class="fas fa-layer-group text-accent"></i> Ringkasan Poligon Atribut</h3>
                <span class="badge-status accent font-mono" style="font-size: 0.68rem;">5 PILAR AKTIF</span>
            </div>
            
            <div class="data-list">
                <?php foreach ($sorted_stats as $category => $value): 
                    $catInfo = game_categories()[$category] ?? ['icon' => 'fa-circle', 'color' => 'text-muted', 'label' => $category];
                    $attrLevel = (int) floor($value / 5) + 1;
                ?>
                    <div class="data-row" style="padding: 0.65rem 0;">
                        <div class="data-main">
                            <div style="width: 30px; height: 30px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                                <i class="fas <?php echo $catInfo['icon'] . ' ' . $catInfo['color']; ?>"></i>
                            </div>
                            <div>
                                <div class="data-title" style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($category); ?></div>
                                <div class="text-muted font-mono" style="font-size: 0.7rem;"><?php echo $value; ?>x aktivitas selesai</div>
                            </div>
                        </div>
                        <div class="data-actions">
                            <span class="badge-status accent font-mono">
                                Tingkat <?php echo $attrLevel; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="col-md-7 d-flex justify-content-center">
            <div style="width: 100%; max-width: 380px; aspect-ratio: 1/1;">
                <canvas id="radarChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Section: 5 Breakdown Attribute Cards (Bento Glass Grid) -->
<div class="panel-header mt-4 mb-3" style="border-bottom: 1px solid var(--border-hairline); padding-bottom: 0.6rem;">
    <h3 class="panel-title" style="font-size: 0.95rem; font-weight: 600;">
        <i class="fas fa-th-large text-accent me-1"></i> Rincian Lima Pilar Kehidupan
    </h3>
    <span class="text-muted font-mono" style="font-size: 0.75rem;">Progres menuju kenaikan tingkat atribut</span>
</div>

<div class="row g-3 mb-4">
    <?php 
    $catDescriptions = [
        'Intelligence' => 'Belajar, membaca, eksplorasi keahlian, pemrograman, dan penajaman wawasan intelektual.',
        'Physical' => 'Olahraga, latihan fisik, jalan kaki, ketahanan stamina, dan kebugaran tubuh.',
        'Health' => 'Pola tidur teratur, konsumsi air 2+ liter, nutrisi sehat, dan pemulihan energi.',
        'Wealth' => 'Pencatatan keuangan harian, menabung, investasi, dan disiplin pengelolaan aset.',
        'Social' => 'Relasi keluarga, komunikasi dengan sahabat, jejaring profesional, dan interaksi bermakna.'
    ];

    foreach ($categories as $category):
        $progress = $stat_data[$category] ?? 0;
        $attrLevel = (int) floor($progress / 5) + 1;
        $inLevelExp = $progress % 5;
        $pct = ($inLevelExp / 5) * 100;
        $catInfo = game_categories()[$category] ?? ['icon' => 'fa-circle', 'color' => 'text-accent', 'label' => $category];
        $desc = $catDescriptions[$category] ?? '';
    ?>
        <div class="col-md-4 col-sm-6">
            <div class="bento-panel spotlight-card holo-card h-100 d-flex flex-column justify-content-between p-3" style="border-radius: 14px;">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div style="width: 36px; height: 36px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                            <i class="fas <?php echo $catInfo['icon'] . ' ' . $catInfo['color']; ?>"></i>
                        </div>
                        <span class="badge-status accent font-mono" style="font-size: 0.72rem;">
                            Tingkat <?php echo $attrLevel; ?>
                        </span>
                    </div>

                    <h4 style="font-size: 0.95rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--text-primary);">
                        <?php echo htmlspecialchars($catInfo['label']); ?>
                    </h4>
                    <p class="text-secondary mb-3" style="font-size: 0.78rem; line-height: 1.5;">
                        <?php echo htmlspecialchars($desc); ?>
                    </p>
                </div>

                <div>
                    <div class="d-flex justify-content-between align-items-center font-mono text-muted mb-1" style="font-size: 0.7rem;">
                        <span>Progres Fase: <?php echo $inLevelExp; ?>/5</span>
                        <span class="text-accent"><?php echo $progress * 10; ?> EXP Total</span>
                    </div>
                    <div class="vitals-track mb-3" style="height: 5px;">
                        <div class="vitals-fill" style="width: <?php echo $pct; ?>%; background-color: var(--accent);"></div>
                    </div>

                    <a href="habits.php?new_cat=<?php echo urlencode($category); ?>" class="btn-core btn-outline w-100 font-mono" style="font-size: 0.75rem; padding: 0.4rem 0.6rem;">
                        <i class="fas fa-plus me-1 text-accent"></i> + Buat Kebiasaan
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" integrity="sha384-vsrfeLOOY6KuIYKDlmVH5UiBmgIdB1oEf7p01YgWHuqmOHfZr374+odEv96n9tNC" crossorigin="anonymous"></script>
<script>
    const ctxRadar = document.getElementById('radarChart').getContext('2d');
    const maxProgress = <?php echo $maxProgress > 0 ? $maxProgress : 5; ?>; 

    Chart.defaults.color = '#71717a';
    Chart.defaults.font.family = "'Geist', sans-serif";

    new Chart(ctxRadar, {
        type: 'radar',
        data: {
            labels: <?php echo json_encode(array_keys($stat_data)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($stat_data)); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.14)',
                borderColor: '#10b981',
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#09090b',
                pointHoverBackgroundColor: '#fafafa',
                pointHoverBorderColor: '#10b981',
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                r: {
                    min: 0,
                    max: maxProgress,
                    angleLines: { color: 'rgba(255, 255, 255, 0.08)' },
                    grid: { color: 'rgba(255, 255, 255, 0.08)', circular: true },
                    pointLabels: {
                        font: { size: 11, weight: '600', family: "'Geist', sans-serif" },
                        color: '#d4d4d8'
                    },
                    ticks: {
                        stepSize: Math.max(1, Math.ceil(maxProgress / 4)),
                        display: false 
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#18181c',
                    titleColor: '#fafafa',
                    bodyColor: '#10b981',
                    borderColor: 'rgba(255, 255, 255, 0.12)',
                    borderWidth: 1,
                    padding: 8,
                    titleFont: { family: "'Geist', sans-serif", size: 11 },
                    bodyFont: { family: "'Geist Mono', monospace", size: 11 },
                    displayColors: false,
                    callbacks: {
                        label: function(context) { return 'Penyelesaian: ' + context.parsed.r + 'x'; }
                    }
                }
            }
        }
    });
</script>

<?php require_once("../includes/footer.php"); ?>
