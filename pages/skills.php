<?php
// pages/skills.php
require_once __DIR__ . '/../config/database.php';
enforce_login();

require_once __DIR__ . '/../includes/header.php';

// Ambil data progres skill berdasarkan kategori
$categories = ['Wealth', 'Intelligence', 'Physical', 'Health', 'Social'];

// Inisialisasi data kategori dengan nilai default 0
$stat_data = array_fill_keys($categories, 0);

// Variabel untuk menyimpan progres tertinggi
$maxProgress = 0;

// Ambil progres skill untuk setiap kategori berdasarkan user_id
foreach ($categories as $category) {
    $skillId = skill_id_for_category($category)[0];
    // Ambil progres skill berdasarkan skill_id dan user_id
    $stmt = $pdo->prepare("SELECT progress FROM skills WHERE user_id = ? AND skill_id = ?");
    $stmt->execute([$_SESSION['user_id'], $skillId]);
    $skill = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika ada data progres untuk skill, masukkan ke dalam stat_data dan cek nilai tertinggi
    if ($skill) {
        $progress = (int) $skill['progress'];
        $stat_data[$category] = $progress;
        if ($progress > $maxProgress) {
            $maxProgress = $progress;  // Update progres tertinggi
        }
    }
}

// Urutkan tampilan stat dari level yang tertinggi (higher) ke terendah
arsort($stat_data);

?>

<div class="page-header">
    <div>
        <h1 class="page-title">Matriks Atribut</h1>
        <p class="page-subtitle">Perkembangan multidimensi lima pilar kehidupan.</p>
    </div>
</div>

<div class="bento-panel mx-auto" style="max-width: 920px;">
    <div class="row align-items-center g-4">
        <div class="col-md-5">
            <div class="panel-header">
                <h3 class="panel-title"><i class="fas fa-layer-group text-accent"></i> Atribut Inti</h3>
            </div>
            
            <div class="data-list">
                <?php foreach ($stat_data as $category => $value): ?>
                    <div class="data-row" style="padding: 0.65rem 0;">
                        <div class="data-main">
                            <?php
                            $icon = match($category) {
                                'Wealth' => 'fa-coins text-warning',
                                'Intelligence' => 'fa-brain text-accent',
                                'Physical' => 'fa-dumbbell text-danger',
                                'Health' => 'fa-heart text-accent',
                                'Social' => 'fa-users text-muted',
                                default => 'fa-circle text-muted'
                            };
                            ?>
                            <div style="width: 28px; height: 28px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="data-title" style="font-size: 0.85rem;"><?php echo htmlspecialchars($category); ?></div>
                        </div>
                        <div class="data-actions">
                            <span class="badge-status accent font-mono">
                                Lv <?php echo $value; ?>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" integrity="sha384-vsrfeLOOY6KuIYKDlmVH5UiBmgIdB1oEf7p01YgWHuqmOHfZr374+odEv96n9tNC" crossorigin="anonymous"></script>
<script>
    const ctxRadar = document.getElementById('radarChart').getContext('2d');
    const maxProgress = <?php echo $maxProgress > 0 ? $maxProgress : 1; ?>; 

    Chart.defaults.color = '#71717a';
    Chart.defaults.font.family = "'Geist', sans-serif";

    new Chart(ctxRadar, {
        type: 'radar',
        data: {
            labels: <?php echo json_encode(array_keys($stat_data)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($stat_data)); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.12)',
                borderColor: '#10b981',
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#09090b',
                pointHoverBackgroundColor: '#fafafa',
                pointHoverBorderColor: '#10b981',
                borderWidth: 1.5,
                pointRadius: 3,
                pointHoverRadius: 5
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
                        font: { size: 11, weight: '500', family: "'Geist', sans-serif" },
                        color: '#d4d4d8'
                    },
                    ticks: {
                        stepSize: Math.ceil(maxProgress / 4),
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
                        label: function(context) { return 'Tingkat ' + context.parsed.r; }
                    }
                }
            }
        }
    });
</script>

<?php require_once("../includes/footer.php"); ?>
