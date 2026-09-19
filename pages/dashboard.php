<?php   
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// pages/dashboard.php

require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

try {
    // Comprehensive user statistics query
    $stmt = $pdo->prepare("
SELECT 
    u.*,
    COUNT(DISTINCT h.habit_id) as total_habits,
    COUNT(DISTINCT hl.log_id) as completed_habits,
    COUNT(DISTINCT q.quest_id) as active_quests,
    COUNT(DISTINCT ql.quest_id) as completed_quests
FROM users u
LEFT JOIN habits h ON u.user_id = h.user_id
LEFT JOIN habit_logs hl ON h.habit_id = hl.habit_id
LEFT JOIN quest_logs ql ON u.user_id = ql.user_id AND ql.status = 'In Progress'
LEFT JOIN quests q ON ql.quest_id = q.quest_id
WHERE u.user_id = ?
GROUP BY u.user_id
");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Total EXP langsung dari kolom users.exp (sudah di-update saat complete habit/quest)
    $totalExp = $user['exp'] ?? 0;

    // Level & Rank calculation (using centralized functions)
    $level = calculateLevel($totalExp);
    $nextLevelExp = $level * 1000;
    $currentLevelExp = ($level - 1) * 1000;
    $expToNextLevel = $nextLevelExp - $totalExp;
    $rank = calculateRank($level);

    // Recent achievements (combined habits and quests)
    $stmt = $pdo->prepare("
    (SELECT 'habit' as type, h.habit_name as name, hl.exp_reward, hl.completion_date 
    FROM habit_logs hl
    JOIN habits h ON hl.habit_id = h.habit_id
    WHERE hl.user_id = ?)
    UNION
    (SELECT 'quest' as type, q.quest_name as name, ql.exp_reward, ql.completed_at as completion_date 
    FROM quest_logs ql
    JOIN quests q ON ql.quest_id = q.quest_id
    WHERE ql.user_id = ? AND ql.status = 'Completed')
    ORDER BY completion_date DESC
    LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $recent_achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    // Ambil data habit progress & highest streak
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_habits,
            SUM(CASE WHEN hl.status = 'Completed' THEN 1 ELSE 0 END) as completed_habits,
            COALESCE(MAX(h.streak), 0) as max_streak
        FROM habits h
        LEFT JOIN habit_logs hl ON h.habit_id = hl.habit_id
        WHERE h.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $habitStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalHabits = $habitStats['total_habits'] ?? 0;
    $completedHabits = $habitStats['completed_habits'] ?? 0;
    $maxStreak = $habitStats['max_streak'] ?? 0;

    // Query untuk mengambil data quest yang telah diselesaikan
    $stmt = $pdo->prepare("
        SELECT q.quest_name, ql.exp_reward, ql.completed_at 
        FROM quest_logs ql
        JOIN quests q ON ql.quest_id = q.quest_id
        WHERE ql.user_id = ? AND ql.status = 'Completed'
        ORDER BY ql.completed_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $completedQuestsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <h1 class="page-title">Dasbor Komando</h1>
        <p class="page-subtitle">Status real-time progres karakter, disiplin harian, dan statistik vital.</p>
    </div>
</div>

<!-- Primary Tactical Console -->
<div class="row g-3 mb-4">
    <!-- Character Core Status -->
    <div class="col-md-8">
        <div class="bento-panel h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="badge-status accent mb-2">
                            <?php echo htmlspecialchars($rank); ?>
                        </span>
                        <h2 style="font-size: 1.65rem; margin: 0; font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></h2>
                    </div>
                    <div class="text-end">
                        <div class="text-muted" style="font-size: 0.65rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;">Tingkat</div>
                        <div style="font-size: 2.2rem; font-weight: 600; line-height: 1; font-family: var(--font-mono); color: var(--text-primary);"><?php echo $level; ?></div>
                    </div>
                </div>

                <!-- Health Points (HP) -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                        <span class="text-muted"><i class="fas fa-heart text-danger me-1"></i> Poin Kesehatan (HP)</span>
                        <span class="font-mono text-danger"><?php echo $user['hp'] ?? 100; ?> / <?php echo $user['max_hp'] ?? 100; ?></span>
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
                    <span class="font-mono text-accent"><?php echo number_format($totalExp); ?> / <?php echo number_format($nextLevelExp); ?></span>
                </div>
                <div class="vitals-track">
                    <?php $expPercentage = min(100, max(0, (($totalExp - $currentLevelExp) / ($nextLevelExp - $currentLevelExp)) * 100)); ?>
                    <div class="vitals-fill" style="width: <?php echo $expPercentage; ?>%; background-color: var(--accent);"></div>
                </div>
                <div class="mt-1 d-flex justify-content-between" style="font-size: 0.7rem;">
                    <span class="text-muted">Progres ke Tingkat <?php echo $level + 1; ?></span>
                    <span class="font-mono text-muted">Sisa <?php echo number_format(max(0, $expToNextLevel)); ?> EXP</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Gold & Shop Quick Access -->
    <div class="col-md-4">
        <div class="bento-panel h-100 d-flex flex-column justify-content-between text-center py-4">
            <div>
                <div class="text-muted mb-2" style="font-size: 0.68rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;">Saldo Gold</div>
                <div style="font-family: var(--font-mono); font-size: 2.25rem; font-weight: 600; color: var(--status-warning); line-height: 1.1;">
                    <i class="fas fa-coins me-1" style="font-size: 1.6rem;"></i><?php echo number_format($user['gold'] ?? 0); ?>
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
        <div class="metric-label"><i class="fas fa-check-square text-accent"></i> Kebiasaan Tuntas</div>
        <div class="metric-value"><?php echo $completedHabits; ?> <span class="text-muted" style="font-size: 1rem; font-weight: 400;">/ <?php echo $totalHabits; ?></span></div>
        <div class="metric-sub">Capaian hari ini</div>
    </div>
    <div class="metric-cell">
        <div class="metric-label"><i class="fas fa-scroll text-accent"></i> Misi Tuntas</div>
        <div class="metric-value"><?php echo $completedQuests; ?> <span class="text-muted" style="font-size: 1rem; font-weight: 400;">/ <?php echo $totalQuests; ?></span></div>
        <div class="metric-sub">Total pencapaian</div>
    </div>
    <div class="metric-cell">
        <div class="metric-label"><i class="fas fa-fire text-warning"></i> Streak Maks.</div>
        <div class="metric-value text-warning"><?php echo $maxStreak; ?> <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">HARI</span></div>
        <div class="metric-sub">Momentum tak terputus</div>
    </div>
    <div class="metric-cell">
        <div class="metric-label"><i class="fas fa-snowflake" style="color: var(--status-info);"></i> Perisai Aktif</div>
        <div class="metric-value" style="color: var(--status-info);"><?php echo $user['streak_freeze'] ?? 0; ?> <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">SLOT</span></div>
        <div class="metric-sub">Hari terlindungi</div>
    </div>
</div>

<!-- 7-Day Activity Velocity Panel -->
<div class="bento-panel mb-4">
    <div class="panel-header">
        <div>
            <h3 class="panel-title"><i class="fas fa-chart-bar text-accent"></i> Velocity Performa 7 Hari</h3>
            <p class="text-muted mb-0" style="font-size: 0.78rem;">Akumulasi EXP dari rutinitas dan petualangan.</p>
        </div>
        <span class="badge-status accent font-mono">
            <?php echo number_format(array_sum($activityData)); ?> EXP TERKUMPUL
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
    </div>
    
    <div class="data-list">
        <?php if (!empty($completedQuestsList)): ?>
            <?php foreach ($completedQuestsList as $quest): ?>
                <div class="data-row">
                    <div class="data-main">
                        <div style="width: 28px; height: 28px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 0.75rem;">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <div class="data-title"><?php echo htmlspecialchars($quest['quest_name']); ?></div>
                            <div class="data-meta font-mono">
                                <span><?php echo indo_short_date($quest['completed_at'], true); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <span class="badge-status accent">
                            +<?php echo htmlspecialchars($quest['exp_reward']); ?> EXP
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
</script>

<?php require_once("../includes/footer.php"); ?>
