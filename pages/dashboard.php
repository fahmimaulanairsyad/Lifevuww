<?php
// pages/dashboard.php
require_once __DIR__ . '/../config/database.php';
enforce_login();

$pageTitle = 'Dasbor';
require_once __DIR__ . '/../includes/header.php';

try {
    // Data pengguna (query lama menggabungkan 4 tabel dan hasil hitungannya tidak dipakai)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Total EXP langsung dari kolom users.exp (sudah di-update saat complete habit/quest)
    $totalExp = $user['exp'] ?? 0;

    // Level & Rank calculation (using centralized functions)
    $level = calculateLevel($totalExp);
    $nextLevelExp = $level * 100;
    $currentLevelExp = ($level - 1) * 100;
    $expToNextLevel = $nextLevelExp - $totalExp;
    $rank = calculateRank($level);

    // Ambil data quest progress
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_quests,
            SUM(CASE WHEN ql.status = 'Completed' THEN 1 ELSE 0 END) AS completed_quests
        FROM quest_logs ql
        WHERE ql.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalQuests = $progress['total_quests'] ?? 0;
    $completedQuests = $progress['completed_quests'] ?? 0;

    // Ambil data habit progress & highest streak.
    // completed_habits = yang selesai HARI INI (sebelumnya menghitung seluruh riwayat log).
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_habits,
            COALESCE(SUM(completed_today), 0) as completed_habits,
            COALESCE(MAX(streak), 0) as max_streak
        FROM habits
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $habitStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalHabits = $habitStats['total_habits'] ?? 0;
    $completedHabits = $habitStats['completed_habits'] ?? 0;
    $maxStreak = $habitStats['max_streak'] ?? 0;

    // Query gabungan riwayat penyelesaian kebiasaan dan misi terbaru
    $stmt = $pdo->prepare("
        SELECT item_name, exp_reward, completed_at, item_type
        FROM (
            SELECT h.habit_name AS item_name, hl.exp_reward, 
                   CONCAT(hl.completion_date, ' 23:59:59') AS completed_at, 
                   'habit' AS item_type
            FROM habit_logs hl
            JOIN habits h ON hl.habit_id = h.habit_id
            WHERE hl.user_id = ? AND hl.status = 'Completed'
            UNION ALL
            SELECT q.quest_name AS item_name, ql.exp_reward, 
                   COALESCE(ql.completed_at, ql.completion_date, ql.created_at) AS completed_at, 
                   'quest' AS item_type
            FROM quest_logs ql
            JOIN quests q ON ql.quest_id = q.quest_id
            WHERE ql.user_id = ? AND ql.status = 'Completed'
        ) combined
        ORDER BY completed_at DESC
        LIMIT 6
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $recentActivityList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk Grafik Aktivitas 7 Hari Terakhir
    $stmtActivity = $pdo->prepare("
        SELECT DATE(log_date) as day_date, SUM(exp_gained) as daily_exp
        FROM (
            SELECT completion_date as log_date, exp_reward as exp_gained 
            FROM habit_logs 
            WHERE user_id = ? AND status = 'Completed' AND completion_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            UNION ALL
            SELECT DATE(completed_at) as log_date, exp_reward as exp_gained 
            FROM quest_logs 
            WHERE user_id = ? AND status = 'Completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        ) activity
        GROUP BY DATE(log_date)
    ");
    $stmtActivity->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $rawActivity = $stmtActivity->fetchAll(PDO::FETCH_KEY_PAIR); // ['YYYY-MM-DD' => exp]

    // Buat array 7 hari lengkap (dari 6 hari lalu s.d. hari ini)
    $activityLabels = [];
    $activityData = [];
    for ($i = 6; $i >= 0; $i--) {
        $dateKey = date('Y-m-d', strtotime("-$i days"));
        $activityLabels[] = indo_short_date($dateKey);
        $activityData[] = (int) ($rawActivity[$dateKey] ?? 0);
    }

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    die("Error loading dashboard. Please try again later.");
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dasbor</h1>
        <p class="page-subtitle">Status real-time progres karakter, disiplin harian, dan statistik vital.</p>
    </div>
</div>

<?php if (isset($_SESSION['morning_report'])): 
    $mReport = $_SESSION['morning_report'];
    unset($_SESSION['morning_report']);
?>
    <div class="alert alert-dismissible fade show mb-3 d-flex align-items-center justify-content-between p-3" role="status" style="background-color: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius);">
        <div class="d-flex align-items-center gap-3">
            <?php if (!empty($mReport['damage']) && $mReport['damage'] > 0): ?>
                <div style="width: 32px; height: 32px; border-radius: 50%; background-color: var(--status-danger-dim); border: 1px solid rgba(244, 63, 94, 0.3); color: var(--status-danger); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fas fa-heart-broken"></i>
                </div>
                <div>
                    <strong style="font-size: 0.88rem; color: var(--text-primary); display: block;">Laporan Hari Baru</strong>
                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                        Ada rutinitas kemarin yang terlewat. Kamu menerima penalti <span class="text-danger font-mono">-<?php echo (int) $mReport['damage']; ?> HP</span>.
                    </div>
                </div>
            <?php elseif (!empty($mReport['frozen'])): ?>
                <div style="width: 32px; height: 32px; border-radius: 50%; background-color: var(--status-info-dim); border: 1px solid rgba(6, 182, 212, 0.3); color: var(--status-info); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fas fa-snowflake"></i>
                </div>
                <div>
                    <strong style="font-size: 0.88rem; color: var(--text-primary); display: block;">Laporan Hari Baru</strong>
                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                        Perisai Streak Freeze berhasil melindungi HP dan streak rutinitasmu dari hari kemarin!
                    </div>
                </div>
            <?php else: ?>
                <div style="width: 32px; height: 32px; border-radius: 50%; background-color: var(--accent-dim); border: 1px solid var(--accent); color: var(--accent); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fas fa-sun"></i>
                </div>
                <div>
                    <strong style="font-size: 0.88rem; color: var(--text-primary); display: block;">Laporan Hari Baru</strong>
                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                        Selamat datang kembali! Seluruh rutinitas kemarin berhasil kamu tuntaskan tanpa penalti.
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
<?php endif; ?>

<!-- Primary Tactical Console -->
<div class="row g-3 mb-4">
    <!-- Character Core Status -->
    <div class="col-md-8">
        <div class="bento-panel spotlight-card holo-card h-100 d-flex flex-column justify-content-between position-relative overflow-hidden" style="background: radial-gradient(circle at 100% 0%, rgba(16, 185, 129, 0.16) 0%, transparent 48%), radial-gradient(circle at 0% 100%, rgba(99, 102, 241, 0.06) 0%, transparent 40%), #111114; border-top: 1px solid rgba(255, 255, 255, 0.18);">
            <div>
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="badge-status accent font-mono mb-2" style="font-size: 0.74rem; padding: 0.25rem 0.7rem; border-color: rgba(16, 185, 129, 0.4); background: rgba(16, 185, 129, 0.14); box-shadow: 0 0 14px rgba(16, 185, 129, 0.18);">
                            <i class="fas fa-crown me-1 text-warning"></i> <?php echo htmlspecialchars($rank); ?>
                        </span>
                        <h2 style="font-size: 1.65rem; margin: 0; font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></h2>
                    </div>
                    <div class="text-end">
                        <div class="text-muted" style="font-size: 0.65rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;">Tingkat</div>
                        <div style="font-size: 2.2rem; font-weight: 600; line-height: 1; font-family: var(--font-mono); color: var(--text-primary);"><span class="count-up" data-target="<?php echo $level; ?>"><?php echo $level; ?></span></div>
                    </div>
                </div>

                <!-- Health Points (HP) -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                        <span class="text-muted"><i class="fas fa-heart text-danger me-1"></i> Poin Kesehatan (HP)</span>
                        <span class="font-mono text-danger"><span class="count-up" data-target="<?php echo (int)($user['hp'] ?? 100); ?>"><?php echo $user['hp'] ?? 100; ?></span> / <?php echo $user['max_hp'] ?? 100; ?></span>
                    </div>
                    <div class="vitals-track">
                        <?php 
                            $hp = $user['hp'] ?? 100;
                            $max_hp = $user['max_hp'] ?? 100;
                            $hpPercentage = min(100, max(0, ($hp / $max_hp) * 100)); 
                        ?>
                        <div class="vitals-fill" style="width: <?php echo $hpPercentage; ?>%; background-color: var(--status-danger);"></div>
                    </div>
                </div>
            </div>

            <!-- EXP Meter -->
            <div class="pt-2">
                <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                    <span class="text-muted"><i class="fas fa-bolt text-accent me-1"></i> Pengalaman (EXP)</span>
                    <span class="font-mono text-accent"><span class="count-up" data-target="<?php echo (int)$totalExp; ?>"><?php echo number_format($totalExp); ?></span> / <?php echo number_format($nextLevelExp); ?></span>
                </div>
                <div class="vitals-track">
                    <?php $expPercentage = min(100, max(0, (($totalExp - $currentLevelExp) / ($nextLevelExp - $currentLevelExp)) * 100)); ?>
                    <div class="vitals-fill" style="width: <?php echo $expPercentage; ?>%; background-color: var(--accent);"></div>
                </div>
                <div class="mt-2 d-flex justify-content-between align-items-center font-mono" style="font-size: 0.72rem;">
                    <span class="text-muted">Progres ke Tingkat <?php echo $level + 1; ?></span>
                    <span class="badge-status font-mono" style="font-size: 0.7rem; background: rgba(255, 255, 255, 0.05); color: #d4d4d8; border-color: rgba(255, 255, 255, 0.1);">
                        Butuh <strong class="text-accent ms-1"><?php echo number_format(max(0, $expToNextLevel)); ?> EXP</strong> lagi
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Gold & Shop Quick Access -->
    <div class="col-md-4">
        <div class="bento-panel spotlight-card h-100 d-flex flex-column justify-content-between text-center py-4">
            <div>
                <div class="text-muted mb-2" style="font-size: 0.68rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;">Saldo Gold</div>
                <div style="font-family: var(--font-mono); font-size: 2.25rem; font-weight: 600; color: var(--status-warning); line-height: 1.1;">
                    <i class="fas fa-coins me-1" style="font-size: 1.6rem;"></i><span class="count-up" data-target="<?php echo (int)($user['gold'] ?? 0); ?>"><?php echo number_format($user['gold'] ?? 0); ?></span>
                </div>
                <p class="text-muted mt-2 mb-0" style="font-size: 0.78rem;">Kumpulkan gold dengan menjaga kebiasaan dan menuntaskan misi.</p>
            </div>
            <div class="mt-3">
                <a href="shop.php" class="btn-core btn-outline w-100">
                    <i class="fas fa-store me-1"></i> Toko Hadiah
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Unified Metric Strip (Anti-slop connected grid) -->
<div class="metric-strip mb-4">
    <div class="metric-cell">
        <div class="metric-label"><i class="fas fa-check-square text-accent"></i> Kebiasaan</div>
        <div class="metric-value"><span class="count-up" data-target="<?php echo (int)$completedHabits; ?>"><?php echo $completedHabits; ?></span> <span class="text-muted" style="font-size: 1rem; font-weight: 400;">/ <?php echo $totalHabits; ?></span></div>
        <div class="metric-sub">Tuntas hari ini</div>
    </div>
    <div class="metric-cell">
        <div class="metric-label"><i class="fas fa-scroll text-accent"></i> Misi</div>
        <div class="metric-value"><span class="count-up" data-target="<?php echo (int)$completedQuests; ?>"><?php echo $completedQuests; ?></span> <span class="text-muted" style="font-size: 1rem; font-weight: 400;">/ <?php echo $totalQuests; ?></span></div>
        <div class="metric-sub">Total tuntas</div>
    </div>
    <div class="metric-cell">
        <div class="metric-label"><i class="fas fa-fire text-warning"></i> Streak</div>
        <div class="metric-value text-warning"><span class="count-up" data-target="<?php echo (int)$maxStreak; ?>"><?php echo $maxStreak; ?></span> <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">HARI</span></div>
        <div class="metric-sub">Rekor beruntun</div>
    </div>
    <div class="metric-cell">
        <div class="metric-label"><i class="fas fa-snowflake" style="color: var(--status-info);"></i> Perisai</div>
        <div class="metric-value" style="color: var(--status-info);"><span class="count-up" data-target="<?php echo (int)($user['streak_freeze'] ?? 0); ?>"><?php echo $user['streak_freeze'] ?? 0; ?></span> <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">SLOT</span></div>
        <div class="metric-sub">Hari terlindungi</div>
    </div>
</div>

<!-- 7-Day Activity Velocity Panel -->
<div class="bento-panel spotlight-card mb-4" style="margin-top: 1.5rem;">
    <div class="panel-header">
        <div>
            <h3 class="panel-title"><i class="fas fa-chart-bar text-accent"></i> Velocity Performa 7 Hari</h3>
            <p class="text-muted mb-0" style="font-size: 0.78rem;">Akumulasi EXP dari rutinitas dan petualangan.</p>
        </div>
        <span class="badge-status accent font-mono">
            <span class="count-up" data-target="<?php echo (int)array_sum($activityData); ?>"><?php echo number_format(array_sum($activityData)); ?></span> EXP TERKUMPUL
        </span>
    </div>
    <div style="height: 180px; width: 100%;">
        <canvas id="weeklyActivityChart"></canvas>
    </div>
</div>

<!-- Recent Completions Stream -->
<div class="bento-panel">
    <div class="panel-header">
        <h3 class="panel-title"><i class="fas fa-history text-muted"></i> Penyelesaian Terakhir</h3>
        <a href="history.php" style="font-size: 0.78rem; color: var(--text-secondary);">Lihat semua →</a>
    </div>
    
    <div class="data-list">
        <?php if (!empty($recentActivityList)): ?>
            <?php foreach ($recentActivityList as $act): ?>
                <div class="data-row">
                    <div class="data-main">
                        <div style="width: 28px; height: 28px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; color: <?php echo $act['item_type'] === 'quest' ? 'var(--accent)' : 'var(--text-muted)'; ?>; font-size: 0.75rem;">
                            <i class="fas <?php echo $act['item_type'] === 'quest' ? 'fa-scroll' : 'fa-check'; ?>"></i>
                        </div>
                        <div>
                            <div class="data-title"><?php echo htmlspecialchars($act['item_name']); ?></div>
                            <div class="data-meta font-mono">
                                <span><?php echo indo_short_date($act['completed_at'], true); ?></span>
                                <span class="badge-status font-mono"><?php echo $act['item_type'] === 'quest' ? 'Misi' : 'Kebiasaan'; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <span class="badge-status accent font-mono">
                            +<?php echo htmlspecialchars($act['exp_reward']); ?> EXP
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-4 text-muted" style="font-size: 0.85rem;">
                Belum ada aktivitas tercatat. Saatnya bergerak!
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" integrity="sha384-vsrfeLOOY6KuIYKDlmVH5UiBmgIdB1oEf7p01YgWHuqmOHfZr374+odEv96n9tNC" crossorigin="anonymous"></script>
<script>
    const ctxWeekly = document.getElementById('weeklyActivityChart').getContext('2d');
    new Chart(ctxWeekly, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($activityLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($activityData); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.18)',
                borderColor: '#10b981',
                borderWidth: 1,
                borderRadius: 4,
                hoverBackgroundColor: '#10b981'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#71717a', font: { family: "'Geist', sans-serif", size: 10 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#71717a', font: { family: "'Geist Mono', monospace", size: 10 }, stepSize: 10 }
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
                        label: function(context) { return '+' + context.parsed.y + ' EXP'; }
                    }
                }
            }
        }
    });

    // TernakKlip Count-Up Telemetry Animation
    (function() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const countEls = document.querySelectorAll('.count-up');
        countEls.forEach(el => {
            const target = parseInt(el.dataset.target, 10);
            if (isNaN(target) || target <= 0) return;
            const duration = 650;
            const start = performance.now();
            const animate = (now) => {
                const elapsed = now - start;
                const progress = Math.min(1, elapsed / duration);
                // easeOutQuad
                const ease = progress * (2 - progress);
                const current = Math.floor(ease * target);
                el.textContent = current.toLocaleString('id-ID');
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    el.textContent = target.toLocaleString('id-ID');
                }
            };
            requestAnimationFrame(animate);
        });
    })();
</script>

<?php require_once("../includes/footer.php"); ?>
