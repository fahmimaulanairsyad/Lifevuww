<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

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

<div class="bento-panel">
    <form method="GET" action="history.php" class="d-flex flex-wrap gap-2 align-items-center mb-4">
        <div class="btn-group" role="group" aria-label="Rentang">
            <?php foreach ([7, 14, 30] as $r): ?>
                <a href="history.php?range=<?php echo $r; ?>&type=<?php echo $type; ?>"
                   class="btn-core <?php echo $range === $r ? 'btn-primary' : 'btn-outline'; ?>"
                   style="padding: 0.4rem 0.8rem; font-size: 0.78rem;"><?php echo $r; ?> hari</a>
            <?php endforeach; ?>
        </div>
        <div class="btn-group" role="group" aria-label="Tipe">
            <?php foreach (['all' => 'Semua', 'habit' => 'Kebiasaan', 'quest' => 'Misi'] as $k => $label): ?>
                <a href="history.php?range=<?php echo $range; ?>&type=<?php echo $k; ?>"
                   class="btn-core <?php echo $type === $k ? 'btn-primary' : 'btn-outline'; ?>"
                   style="padding: 0.4rem 0.8rem; font-size: 0.78rem;"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>
    </form>

    <?php if (empty($grouped)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-history fa-2x mb-3" style="opacity: 0.3;"></i>
            <p class="mb-0">Belum ada aktivitas dalam <?php echo $range; ?> hari terakhir.</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $day => $items):
            $dayExp = array_sum(array_column($items, 'exp_reward'));
        ?>
            <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                <h3 class="font-mono mb-0" style="font-size: 0.85rem; font-weight: 600;"><?php echo indo_short_date($day); ?></h3>
                <span class="badge-status accent font-mono">+<?php echo number_format($dayExp); ?> EXP</span>
            </div>
            <div class="data-list" style="border-top: 1px solid var(--border-hairline);">
                <?php foreach ($items as $it): ?>
                    <div class="data-row" style="padding: 0.7rem 0;">
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
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once("../includes/footer.php"); ?>
