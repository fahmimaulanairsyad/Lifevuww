<?php
// pages/history.php
require_once __DIR__ . '/../config/database.php';
enforce_login();

$pageTitle = 'Riwayat';
require_once __DIR__ . '/../includes/header.php';

$userId = $_SESSION['user_id'];

// Filter: rentang hari (7/14/30) & tipe (all/habit/quest)
$range = intval($_GET['range'] ?? 14);
if (!in_array($range, [7, 14, 30], true)) {
    $range = 14;
}
$type = $_GET['type'] ?? 'all';
if (!in_array($type, ['all', 'habit', 'quest'], true)) {
    $type = 'all';
}

$entries = [];

try {
    if ($type === 'all' || $type === 'habit') {
        $stmt = $pdo->prepare("
            SELECT h.habit_name AS name, hl.exp_reward, hl.completion_date AS done_at,
                   hl.status, 'habit' AS kind
            FROM habit_logs hl
            JOIN habits h ON hl.habit_id = h.habit_id
            WHERE hl.user_id = ? AND hl.completion_date >= DATE_SUB(CURDATE(), INTERVAL $range DAY)
        ");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['sort_key'] = $row['done_at'] . ' 23:59:59';
            $entries[] = $row;
        }
    }

    if ($type === 'all' || $type === 'quest') {
        $stmt = $pdo->prepare("
            SELECT q.quest_name AS name, ql.exp_reward,
                   COALESCE(ql.completed_at, ql.completion_date, ql.created_at) AS done_at,
                   ql.status, 'quest' AS kind
            FROM quest_logs ql
            JOIN quests q ON ql.quest_id = q.quest_id
            WHERE ql.user_id = ?
              AND DATE(COALESCE(ql.completed_at, ql.completion_date, ql.created_at)) >= DATE_SUB(CURDATE(), INTERVAL $range DAY)
        ");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['sort_key'] = $row['done_at'];
            $entries[] = $row;
        }
    }
} catch (PDOException $e) {
    error_log("History error: " . $e->getMessage());
    die("Gagal memuat riwayat. Coba lagi nanti.");
}

// Query aktivitas 60 hari terakhir untuk Heatmap Kalender Konsistensi (GitHub Style)
$stmtHeat = $pdo->prepare("
    SELECT log_date, COUNT(*) AS total_count, SUM(exp_reward) AS total_exp
    FROM (
        SELECT completion_date AS log_date, exp_reward 
        FROM habit_logs 
        WHERE user_id = ? AND status = 'Completed' AND completion_date >= DATE_SUB(CURDATE(), INTERVAL 59 DAY)
        UNION ALL
        SELECT DATE(COALESCE(completed_at, completion_date, created_at)) AS log_date, exp_reward 
        FROM quest_logs 
        WHERE user_id = ? AND status = 'Completed' AND DATE(COALESCE(completed_at, completion_date, created_at)) >= DATE_SUB(CURDATE(), INTERVAL 59 DAY)
    ) act
    GROUP BY log_date
");
$stmtHeat->execute([$userId, $userId]);
$heatMapData = [];
$totalHeatTasks = 0;
$activeHeatDays = 0;
foreach ($stmtHeat->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $c = (int) $row['total_count'];
    $heatMapData[$row['log_date']] = [
        'count' => $c,
        'exp' => (int) $row['total_exp']
    ];
    $totalHeatTasks += $c;
    if ($c > 0) $activeHeatDays++;
}

// Susun array 60 hari berurutan
$heatDays = [];
for ($i = 59; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $heatDays[] = [
        'date' => $d,
        'count' => $heatMapData[$d]['count'] ?? 0,
        'exp' => $heatMapData[$d]['exp'] ?? 0
    ];
}

// Ambil rekor streak terbaik untuk ringkasan metrik
$stmtStreak = $pdo->prepare("SELECT COALESCE(MAX(streak), 0) FROM habits WHERE user_id = ?");
$stmtStreak->execute([$userId]);
$historyBestStreak = (int) $stmtStreak->fetchColumn();
$historyAvgDaily = round($totalHeatTasks / 60, 1);

// Urutkan terbaru dulu, lalu kelompokkan per tanggal
usort($entries, fn($a, $b) => strcmp($b['sort_key'], $a['sort_key']));
$grouped = [];
foreach ($entries as $e) {
    $day = substr($e['sort_key'], 0, 10);
    $grouped[$day][] = $e;
}

function status_badge($status) {
    return match ($status) {
        'Completed' => '<span class="badge-status accent"><i class="fas fa-check"></i> Tuntas</span>',
        'Failed' => '<span class="badge-status danger"><i class="fas fa-times"></i> Gagal</span>',
        default => '<span class="badge-status"><i class="fas fa-minus"></i> ' . htmlspecialchars($status) . '</span>',
    };
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Riwayat Aktivitas</h1>
        <p class="page-subtitle">Jejak penyelesaian habit dan misi per tanggal.</p>
    </div>
</div>

<!-- Heatmap Konsistensi 60 Hari (GitHub Style) -->
<div class="bento-panel spotlight-card holo-card mb-4 p-4">
    <div class="panel-header flex-wrap gap-2">
        <div>
            <h3 class="panel-title"><i class="fas fa-th text-accent"></i> Heatmap Konsistensi (60 Hari Terakhir)</h3>
            <p class="text-muted mb-0" style="font-size: 0.78rem;">Peta visual keteraturan disiplin dan produktivitas harianmu.</p>
        </div>
        <!-- 3 Mini Summary Metrics -->
        <div class="d-flex flex-wrap gap-2 font-mono" style="font-size: 0.72rem;">
            <span class="badge-status accent font-mono" title="Total hari aktif dalam 60 hari">
                <i class="fas fa-calendar-check me-1"></i><strong><?php echo $activeHeatDays; ?></strong> / 60 Hari Aktif
            </span>
            <span class="badge-status warning font-mono" title="Rekor streak tertinggi">
                <i class="fas fa-fire me-1"></i><strong><?php echo $historyBestStreak; ?></strong> Hari Rekor Streak
            </span>
            <span class="badge-status font-mono" title="Rata-rata aktivitas harian">
                <i class="fas fa-chart-line me-1"></i><strong><?php echo $historyAvgDaily; ?></strong> Aktivitas/Hari
            </span>
        </div>
    </div>

    <!-- Floating Tooltip Container -->
    <div id="heatmapFloatingTooltip" class="heatmap-floating-tooltip"></div>

    <!-- Heatmap Grid -->
    <div class="heatmap-wrapper" id="heatmapWrapper" style="overflow-x: auto; padding: 0.25rem 0;">
        <div class="heatmap-track" style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center; max-width: 100%;">
            <?php foreach ($heatDays as $hd): 
                $cnt = $hd['count'];
                $bg = 'var(--bg-surface-hover)';
                $border = 'var(--border-hairline)';
                if ($cnt >= 5) {
                    $bg = '#10b981';
                    $border = '#10b981';
                } elseif ($cnt >= 3) {
                    $bg = 'rgba(16, 185, 129, 0.65)';
                    $border = 'rgba(16, 185, 129, 0.75)';
                } elseif ($cnt >= 1) {
                    $bg = 'rgba(16, 185, 129, 0.28)';
                    $border = 'rgba(16, 185, 129, 0.4)';
                }
                $tooltip = indo_short_date($hd['date']) . ' • ' . ($cnt > 0 ? "{$cnt} Aktivitas Selesai (+{$hd['exp']} EXP)" : "Tidak ada aktivitas");
            ?>
                <div class="heatmap-cell" 
                     data-date="<?php echo $hd['date']; ?>"
                     data-tooltip="<?php echo htmlspecialchars($tooltip); ?>" 
                     style="display: inline-block; width: 16px; height: 16px; border-radius: 3px; background-color: <?php echo $bg; ?>; border: 1px solid <?php echo $border; ?>; cursor: pointer; flex-shrink: 0;">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
        (function() {
            // Horizontal auto-scroll
            const hw = document.getElementById('heatmapWrapper');
            if (hw && hw.scrollWidth > hw.clientWidth) {
                hw.scrollLeft = hw.scrollWidth;
            }

            // Interactive Custom Floating Tooltip & Click-to-filter
            const tooltip = document.getElementById('heatmapFloatingTooltip');
            const banner = document.getElementById('historyDateFilterBanner');
            const filterLabel = document.getElementById('filterDateLabel');
            const clearBtn = document.getElementById('btnClearDateFilter');
            const emptyEl = document.getElementById('historyDateEmptyState');
            const dayGroups = document.querySelectorAll('.history-day-group');
            const cells = document.querySelectorAll('.heatmap-cell[data-tooltip]');

            let selectedDate = null;

            function positionTooltip(cell) {
                if (!tooltip) return;
                tooltip.textContent = cell.getAttribute('data-tooltip');
                tooltip.classList.add('show');
                const rect = cell.getBoundingClientRect();
                tooltip.style.left = (rect.left + rect.width / 2) + 'px';
                tooltip.style.top = rect.top + 'px';
            }

            function clearDateFilter() {
                selectedDate = null;
                cells.forEach(c => c.classList.remove('is-selected'));
                if (tooltip) tooltip.classList.remove('show');
                if (banner) {
                    banner.classList.remove('d-flex');
                    banner.classList.add('d-none');
                }
                if (emptyEl) emptyEl.classList.add('d-none');
                dayGroups.forEach(g => g.style.display = '');
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', clearDateFilter);
            }

            cells.forEach(cell => {
                const date = cell.getAttribute('data-date');
                
                cell.addEventListener('mouseenter', () => {
                    if (!selectedDate) positionTooltip(cell);
                });
                cell.addEventListener('mousemove', () => {
                    if (!selectedDate) positionTooltip(cell);
                });
                cell.addEventListener('mouseleave', () => {
                    if (!selectedDate && tooltip) tooltip.classList.remove('show');
                });

                // Click / Tap Handler
                cell.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (selectedDate === date) {
                        clearDateFilter();
                        return;
                    }

                    selectedDate = date;
                    cells.forEach(c => c.classList.remove('is-selected'));
                    cell.classList.add('is-selected');
                    positionTooltip(cell);

                    // Filter history groups
                    let visibleCount = 0;
                    dayGroups.forEach(g => {
                        if (g.getAttribute('data-date') === date) {
                            g.style.display = '';
                            visibleCount++;
                        } else {
                            g.style.display = 'none';
                        }
                    });

                    if (banner && filterLabel) {
                        const rawTooltip = cell.getAttribute('data-tooltip') || '';
                        const datePart = rawTooltip.split('•')[0].trim() || date;
                        filterLabel.textContent = datePart;
                        banner.classList.remove('d-none');
                        banner.classList.add('d-flex');
                    }

                    if (emptyEl) {
                        if (visibleCount === 0) {
                            emptyEl.classList.remove('d-none');
                        } else {
                            emptyEl.classList.add('d-none');
                        }
                    }

                    // Smooth scroll down to log list on mobile
                    if (window.innerWidth <= 768 && banner) {
                        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });
            });

            // Close tooltip and selection when clicking outside heatmap
            document.addEventListener('click', (e) => {
                if (!e.target.closest('#heatmapWrapper') && !e.target.closest('#historyDateFilterBanner')) {
                    if (!selectedDate && tooltip) tooltip.classList.remove('show');
                }
            });
        })();
    </script>

    <!-- Legend -->
    <div class="d-flex justify-content-between align-items-center mt-3 pt-2" style="border-top: 1px solid var(--border-hairline); font-size: 0.72rem; color: var(--text-muted);">
        <span>Setiap kotak mewakili 1 hari</span>
        <div class="d-flex align-items-center gap-1">
            <span>Sedikit</span>
            <div style="width: 10px; height: 10px; border-radius: 2px; background: var(--bg-surface-hover); border: 1px solid var(--border-hairline);"></div>
            <div style="width: 10px; height: 10px; border-radius: 2px; background: rgba(16, 185, 129, 0.28); border: 1px solid rgba(16, 185, 129, 0.4);"></div>
            <div style="width: 10px; height: 10px; border-radius: 2px; background: rgba(16, 185, 129, 0.65); border: 1px solid rgba(16, 185, 129, 0.75);"></div>
            <div style="width: 10px; height: 10px; border-radius: 2px; background: #10b981; border: 1px solid #10b981;"></div>
            <span>Banyak</span>
        </div>
    </div>
</div>

<div class="bento-panel">
    <form method="GET" action="history.php" class="task-filter-bar mb-4">
        <div class="filter-pills-category">
            <div class="btn-group" role="group" aria-label="Rentang">
                <?php foreach ([7, 14, 30] as $r): ?>
                    <a href="history.php?range=<?php echo $r; ?>&type=<?php echo $type; ?>"
                       class="btn-core <?php echo $range === $r ? 'btn-primary' : 'btn-outline'; ?>"
                       style="padding: 0.4rem 0.8rem; font-size: 0.78rem; white-space: nowrap;"><?php echo $r; ?> hari</a>
                <?php endforeach; ?>
            </div>
            <div class="btn-group" role="group" aria-label="Tipe">
                <?php foreach (['all' => 'Semua', 'habit' => 'Kebiasaan', 'quest' => 'Misi'] as $k => $label): ?>
                    <a href="history.php?range=<?php echo $range; ?>&type=<?php echo $k; ?>"
                       class="btn-core <?php echo $type === $k ? 'btn-primary' : 'btn-outline'; ?>"
                       style="padding: 0.4rem 0.8rem; font-size: 0.78rem; white-space: nowrap;"><?php echo $label; ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </form>

    <!-- Active Date Filter Indicator Banner -->
    <div id="historyDateFilterBanner" class="alert alert-dismissible fade show d-none align-items-center justify-content-between p-2 px-3 mb-3" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: var(--radius); font-size: 0.78rem;">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-filter text-accent"></i>
            <span>Menampilkan aktivitas tanggal <strong id="filterDateLabel" class="text-accent font-mono"></strong></span>
        </div>
        <button type="button" id="btnClearDateFilter" class="btn-core btn-ghost text-muted p-1" style="font-size: 0.75rem;" title="Tampilkan Semua Tanggal">
            Tampilkan Semua Tanggal &times;
        </button>
    </div>

    <div id="historyDateEmptyState" class="text-center py-4 text-muted d-none">
        <i class="fas fa-calendar-times fa-2x mb-2" style="opacity: 0.3;"></i>
        <p class="mb-0 font-mono" style="font-size: 0.82rem;">Tidak ada riwayat aktivitas tercatat pada tanggal ini.</p>
    </div>

    <div id="historyLogContainer">
    <?php if (empty($grouped)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-history fa-2x mb-3" style="opacity: 0.3;"></i>
            <p class="mb-0">Belum ada aktivitas dalam <?php echo $range; ?> hari terakhir.</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $day => $items):
            $dayExp = array_sum(array_column($items, 'exp_reward'));
        ?>
            <div class="history-day-group" data-date="<?php echo $day; ?>">
                <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                    <h3 class="font-mono mb-0" style="font-size: 0.85rem; font-weight: 600;"><?php echo indo_short_date($day); ?></h3>
                    <span class="badge-status accent font-mono">+<?php echo number_format($dayExp); ?> EXP</span>
                </div>
                <div class="data-list" style="border-top: 1px solid var(--border-hairline);">
                    <?php foreach ($items as $it): ?>
                        <div class="data-row" data-date="<?php echo $day; ?>" style="padding: 0.7rem 0;">
                            <div class="data-main">
                                <div style="width: 28px; height: 28px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; font-size: 0.72rem; color: <?php echo $it['kind'] === 'quest' ? 'var(--accent)' : 'var(--text-muted)'; ?>;">
                                    <i class="fas <?php echo $it['kind'] === 'quest' ? 'fa-scroll' : 'fa-check-square'; ?>"></i>
                                </div>
                                <div>
                                    <div class="data-title" style="font-size: 0.85rem;"><?php echo htmlspecialchars($it['name']); ?></div>
                                    <div class="data-meta font-mono">
                                        <span><?php echo htmlspecialchars($it['kind'] === 'quest' ? date('H:i', strtotime($it['done_at'])) : indo_short_date($it['done_at'])); ?></span>
                                        <span><?php echo $it['kind'] === 'quest' ? 'Misi' : 'Kebiasaan'; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="data-actions">
                                <span class="font-mono text-accent me-2" style="font-size: 0.78rem;">+<?php echo $it['exp_reward']; ?></span>
                                <?php echo status_badge($it['status']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<?php require_once("../includes/footer.php"); ?>
