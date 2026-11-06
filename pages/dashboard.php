<?php
// pages/dashboard.php
require_once __DIR__ . '/../config/database.php';
enforce_login();

$userId = $_SESSION['user_id'];
$todayDate = date('Y-m-d');

// POST Handlers (Supply Crate & Emergency Recovery)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    // 1. Klaim Supply Crate Harian
    if (isset($_POST['claim_supply_crate'])) {
        $stmtHCount = $pdo->prepare("SELECT COUNT(*) FROM habit_logs WHERE user_id = ? AND status = 'Completed' AND completion_date = CURDATE()");
        $stmtHCount->execute([$userId]);
        $doneHabitsToday = (int)$stmtHCount->fetchColumn();

        $stmtQCount = $pdo->prepare("SELECT COUNT(*) FROM quest_logs WHERE user_id = ? AND status = 'Completed' AND DATE(COALESCE(completed_at, completion_date, created_at)) = CURDATE()");
        $stmtQCount->execute([$userId]);
        $doneQuestsToday = (int)$stmtQCount->fetchColumn();

        $stmtTbH = $pdo->prepare("SELECT COUNT(*) FROM habit_logs hl JOIN habits h ON hl.habit_id = h.habit_id WHERE hl.user_id = ? AND hl.status = 'Completed' AND hl.completion_date = CURDATE() AND h.start_time IS NOT NULL AND h.end_time IS NOT NULL");
        $stmtTbH->execute([$userId]);
        $doneTbHabits = (int)$stmtTbH->fetchColumn();

        $stmtTbQ = $pdo->prepare("SELECT COUNT(*) FROM quest_logs ql JOIN quests q ON ql.quest_id = q.quest_id WHERE ql.user_id = ? AND ql.status = 'Completed' AND DATE(COALESCE(ql.completed_at, ql.completion_date, ql.created_at)) = CURDATE() AND q.start_time IS NOT NULL AND q.end_time IS NOT NULL");
        $stmtTbQ->execute([$userId]);
        $doneTbQuests = (int)$stmtTbQ->fetchColumn();

        $target1Met = ($doneHabitsToday >= 2);
        $target2Met = ($doneQuestsToday >= 1 || ($doneTbHabits + $doneTbQuests) >= 1);

        if ($target1Met && $target2Met) {
            $pdo->beginTransaction();
            try {
                $stmtU = $pdo->prepare("SELECT exp, gold, level, last_crate_date FROM users WHERE user_id = ? FOR UPDATE");
                $stmtU->execute([$userId]);
                $uRow = $stmtU->fetch(PDO::FETCH_ASSOC);

                if ($uRow && ($uRow['last_crate_date'] ?? '') !== $todayDate) {
                    $newExp = (int)$uRow['exp'] + 20;
                    $newGold = (int)$uRow['gold'] + 25;
                    $newLvl = calculateLevel($newExp);
                    $newRank = calculateRank($newLvl);

                    $pdo->prepare("UPDATE users SET exp = ?, gold = ?, level = ?, `rank` = ?, last_crate_date = CURDATE() WHERE user_id = ?")
                        ->execute([$newExp, $newGold, $newLvl, $newRank, $userId]);
                    $pdo->commit();

                    $_SESSION['dashboard_alert'] = [
                        'type' => 'success',
                        'icon' => 'fa-box-open',
                        'title' => 'Supply Crate Harian Terbuka!',
                        'message' => '+20 EXP dan +25 Gold berhasil diklaim ke saldo karaktermu.'
                    ];
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $pdo->rollBack();
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
        }
        header("Location: dashboard.php");
        exit();
    }

    // 2. Pemulihan Darurat (+30 HP)
    if (isset($_POST['emergency_heal'])) {
        $stmtHCount = $pdo->prepare("SELECT COUNT(*) FROM habit_logs WHERE user_id = ? AND status = 'Completed' AND completion_date = CURDATE()");
        $stmtHCount->execute([$userId]);
        $doneH = (int)$stmtHCount->fetchColumn();

        $stmtQCount = $pdo->prepare("SELECT COUNT(*) FROM quest_logs WHERE user_id = ? AND status = 'Completed' AND DATE(COALESCE(completed_at, completion_date, created_at)) = CURDATE()");
        $stmtQCount->execute([$userId]);
        $doneQ = (int)$stmtQCount->fetchColumn();

        if (($doneH + $doneQ) >= 1) {
            $pdo->beginTransaction();
            try {
                $stmtU = $pdo->prepare("SELECT hp, max_hp, last_emergency_heal_date FROM users WHERE user_id = ? FOR UPDATE");
                $stmtU->execute([$userId]);
                $uRow = $stmtU->fetch(PDO::FETCH_ASSOC);

                if ($uRow && (int)$uRow['hp'] <= 25 && ($uRow['last_emergency_heal_date'] ?? '') !== $todayDate) {
                    $maxHp = (int)($uRow['max_hp'] ?? 100);
                    $curHp = (int)($uRow['hp'] ?? 0);
                    $newHp = min($maxHp, $curHp + 30);

                    $pdo->prepare("UPDATE users SET hp = ?, last_emergency_heal_date = CURDATE() WHERE user_id = ?")
                        ->execute([$newHp, $userId]);
                    $pdo->commit();

                    $_SESSION['dashboard_alert'] = [
                        'type' => 'danger',
                        'icon' => 'fa-heart',
                        'title' => 'Protokol Pemulihan Darurat Berhasil!',
                        'message' => '+30 HP berhasil dipulihkan secara gratis.'
                    ];
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $pdo->rollBack();
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
        }
        header("Location: dashboard.php");
        exit();
    }
}

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

    // Query Ringkasan Time-Block Hari Ini (Habit & Quest dengan jadwal aktif)
    $todayDate = date('Y-m-d');
    $stmtHabitsToday = $pdo->prepare("SELECT * FROM habits WHERE user_id = ?");
    $stmtHabitsToday->execute([$_SESSION['user_id']]);
    $allUserHabits = $stmtHabitsToday->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $todayTimeBlocks = [];
    foreach ($allUserHabits as $h) {
        if (isHabitDueToday($h, $todayDate)) {
            $isDone = (!empty($h['completed_today']) || (int)($h['current_count'] ?? 0) >= (int)($h['target_count'] ?? 1));
            $todayTimeBlocks[] = [
                'kind' => 'habit',
                'id' => $h['habit_id'],
                'title' => $h['habit_name'],
                'category' => $h['category'] ?? 'Health',
                'exp' => $h['exp_reward'] ?? 5,
                'start_time' => $h['start_time'],
                'end_time' => $h['end_time'],
                'is_done' => $isDone,
                'is_pinned' => !empty($h['is_pinned']),
                'sort_order' => (int)($h['sort_order'] ?? 0),
            ];
        }
    }

    $stmtQuestsToday = $pdo->prepare("SELECT * FROM quests WHERE user_id = ? AND status = 'In Progress' AND (start_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE()))");
    $stmtQuestsToday->execute([$_SESSION['user_id']]);
    $allUserQuests = $stmtQuestsToday->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($allUserQuests as $q) {
        $todayTimeBlocks[] = [
            'kind' => 'quest',
            'id' => $q['quest_id'],
            'title' => $q['quest_name'],
            'category' => $q['category'] ?? 'General',
            'exp' => $q['exp_reward'] ?? 10,
            'start_time' => $q['start_time'],
            'end_time' => $q['end_time'],
            'is_done' => false,
            'is_pinned' => !empty($q['is_pinned']),
            'sort_order' => (int)($q['sort_order'] ?? 0),
        ];
    }

    usort($todayTimeBlocks, function($a, $b) {
        $hasTimeA = (!empty($a['start_time']) && !empty($a['end_time'])) ? 1 : 0;
        $hasTimeB = (!empty($b['start_time']) && !empty($b['end_time'])) ? 1 : 0;
        if ($hasTimeA !== $hasTimeB) return $hasTimeB - $hasTimeA;
        if ($hasTimeA && $hasTimeB) {
            $cmp = strcmp($a['start_time'], $b['start_time']);
            if ($cmp !== 0) return $cmp;
        }
        $pinDiff = ($b['is_pinned'] ? 1 : 0) - ($a['is_pinned'] ? 1 : 0);
        if ($pinDiff !== 0) return $pinDiff;
        return $a['sort_order'] - $b['sort_order'];
    });

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

    // RPG Expansion v2: Boss Mastery, Specializations, Relics, and Radar Stats for PNG Card
    $bossMastery = getBossMasteryStats($pdo, $_SESSION['user_id']);
    $userRelics = getUserRelics($pdo, $_SESSION['user_id']);
    $relicsList = game_relics();
    $userSpec = $user['specialization'] ?? 'Obsidian Vanguard';

    $stmtS = $pdo->prepare("SELECT skill_id, progress FROM skills WHERE user_id = ?");
    $stmtS->execute([$_SESSION['user_id']]);
    $userSkillsMap = $stmtS->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    $radarAttrData = [];
    foreach (game_categories() as $catKey => $catMeta) {
        $sId = $catMeta['id'];
        $radarAttrData[$catKey] = (int)($userSkillsMap[$sId] ?? 0);
    }

    // Daily Directive & Emergency Recovery Status
    $stmtHCount = $pdo->prepare("SELECT COUNT(*) FROM habit_logs WHERE user_id = ? AND status = 'Completed' AND completion_date = CURDATE()");
    $stmtHCount->execute([$userId]);
    $doneHabitsToday = (int)$stmtHCount->fetchColumn();

    $stmtQCount = $pdo->prepare("SELECT COUNT(*) FROM quest_logs WHERE user_id = ? AND status = 'Completed' AND DATE(COALESCE(completed_at, completion_date, created_at)) = CURDATE()");
    $stmtQCount->execute([$userId]);
    $doneQuestsToday = (int)$stmtQCount->fetchColumn();

    $stmtTbH = $pdo->prepare("SELECT COUNT(*) FROM habit_logs hl JOIN habits h ON hl.habit_id = h.habit_id WHERE hl.user_id = ? AND hl.status = 'Completed' AND hl.completion_date = CURDATE() AND h.start_time IS NOT NULL AND h.end_time IS NOT NULL");
    $stmtTbH->execute([$userId]);
    $doneTbHabits = (int)$stmtTbH->fetchColumn();

    $stmtTbQ = $pdo->prepare("SELECT COUNT(*) FROM quest_logs ql JOIN quests q ON ql.quest_id = q.quest_id WHERE ql.user_id = ? AND ql.status = 'Completed' AND DATE(COALESCE(ql.completed_at, ql.completion_date, ql.created_at)) = CURDATE() AND q.start_time IS NOT NULL AND q.end_time IS NOT NULL");
    $stmtTbQ->execute([$userId]);
    $doneTbQuests = (int)$stmtTbQ->fetchColumn();

    $target1Met = ($doneHabitsToday >= 2);
    $target2Met = ($doneQuestsToday >= 1 || ($doneTbHabits + $doneTbQuests) >= 1);
    $isCrateClaimedToday = (($user['last_crate_date'] ?? '') === $todayDate);

    $isEmergency = ((int)($user['hp'] ?? 100) <= 25);
    $isHealedToday = (($user['last_emergency_heal_date'] ?? '') === $todayDate);
    $canEmergencyHeal = ($isEmergency && !$isHealedToday && ($doneHabitsToday + $doneQuestsToday >= 1));

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    die("Error loading dashboard. Please try again later.");
}
?>

<div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
        <h1 class="page-title">Dasbor</h1>
        <p class="page-subtitle">Telemetri vital karakter, status direktif harian, dan ringkasan raid.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn-core btn-outline font-mono" onclick="window.openFocusRiftModal && window.openFocusRiftModal();" title="Buka Focus Rift Pomodoro Battle Timer">
            <i class="fas fa-hourglass-half text-accent me-1"></i> Focus Rift
        </button>
        <a href="dungeon.php" class="btn-core btn-outline font-mono" title="Dungeon Raid Boss Mingguan">
            <i class="fas fa-dungeon text-danger me-1"></i> Dungeon
        </a>
        <button type="button" class="btn-core btn-outline font-mono" id="btnDownloadCardDashboard" title="Unduh Kartu Status PNG">
            <i class="fas fa-camera text-accent me-1"></i> Unduh Kartu (PNG)
        </button>
    </div>
</div>

<?php
// Sovereign Expansion: chip status Companion + Skill Tree + Overdrive
$dashCompanions = game_companions();
$dashCompKey = isset($dashCompanions[$user['active_companion'] ?? '']) ? $user['active_companion'] : 'obsidian_wolf';
$dashComp = $dashCompanions[$dashCompKey];
$dashBond = game_companion_level((int) ($user['companion_exp'] ?? 0));
$dashOverdrive = !empty($user['overdrive_until']) && strtotime((string) $user['overdrive_until']) > time();
?>
<div class="bento-panel tactical-frame mb-4 p-2 px-3 d-flex flex-wrap align-items-center gap-3" style="font-size: 0.76rem;">
    <span title="Astral Companion aktif"><i class="fas <?php echo $dashComp['icon']; ?> me-1" style="color: <?php echo $dashComp['accent']; ?>;"></i> <?php echo htmlspecialchars($dashComp['name']); ?> <span class="font-mono text-muted">(<?php echo $dashBond['stage']; ?> Lv.<?php echo $dashBond['bond_level']; ?>)</span></span>
    <span title="Skill Points tersedia" class="font-mono"><i class="fas fa-sitemap text-accent me-1"></i> SP: <?php echo game_available_skill_points((int) ($user['level'] ?? 1), (int) ($user['spent_skill_points'] ?? 0)); ?></span>
    <?php if ($dashOverdrive): ?>
        <span class="badge-status warning font-mono"><i class="fas fa-bolt me-1"></i> OVERDRIVE +50% EXP s/d <?php echo htmlspecialchars(date('H:i', strtotime((string) $user['overdrive_until']))); ?></span>
    <?php endif; ?>
    <a href="skills.php" class="ms-auto font-mono text-accent" style="font-size: 0.72rem;">Kelola Sanctum <i class="fas fa-arrow-right ms-1"></i></a>
</div>

<?php if (isset($_SESSION['dashboard_alert'])): 
    $dbAlert = $_SESSION['dashboard_alert'];
    unset($_SESSION['dashboard_alert']);
    $alertBg = ($dbAlert['type'] === 'danger') ? 'var(--status-danger-dim)' : 'var(--accent-dim)';
    $alertBorder = ($dbAlert['type'] === 'danger') ? 'rgba(244, 63, 94, 0.3)' : 'var(--accent-border)';
    $alertColor = ($dbAlert['type'] === 'danger') ? 'var(--status-danger)' : 'var(--accent)';
?>
    <div class="alert alert-dismissible fade show mb-3 d-flex align-items-center justify-content-between p-3" role="status" style="background-color: var(--bg-surface); border: 1px solid <?php echo $alertBorder; ?>; border-radius: var(--radius);">
        <div class="d-flex align-items-center gap-3">
            <div style="width: 32px; height: 32px; border-radius: 50%; background-color: <?php echo $alertBg; ?>; color: <?php echo $alertColor; ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fas <?php echo htmlspecialchars($dbAlert['icon']); ?>"></i>
            </div>
            <div>
                <strong style="font-size: 0.88rem; color: var(--text-primary); display: block;"><?php echo htmlspecialchars($dbAlert['title']); ?></strong>
                <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($dbAlert['message']); ?></div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
<?php endif; ?>

<?php if ($isEmergency && !$isHealedToday): ?>
    <!-- Emergency Recovery Zone (Zona Darurat) -->
    <div class="alert alert-dismissible fade show mb-3 d-flex align-items-center justify-content-between p-3 tactical-frame frame-danger" role="alert" style="background: rgba(244, 63, 94, 0.12); border: 1px solid rgba(244, 63, 94, 0.35); border-radius: var(--radius);">
        <div class="d-flex align-items-center gap-3">
            <div style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--status-danger-dim); border: 1px solid var(--status-danger); color: var(--status-danger); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                <i class="fas fa-heart-crack"></i>
            </div>
            <div>
                <div class="sys-micro-tag text-danger"><span class="text-danger">SYS.ALERT // CRITICAL_VITAL</span></div>
                <strong style="font-size: 0.9rem; color: #fca5a5; display: block;">PROTOKOL DARURAT: Vital Karakter Kritis!</strong>
                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                    Poin kesehatan tersisa <span class="text-danger font-mono fw-bold"><?php echo (int)($user['hp'] ?? 0); ?> HP</span>. Tuntaskan minimal 1 rutinitas atau misi hari ini untuk memicu pemulihan darurat (+30 HP).
                </div>
            </div>
        </div>
        <div class="ms-3 flex-shrink-0">
            <?php if ($canEmergencyHeal): ?>
                <form method="POST" action="dashboard.php" class="m-0">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="emergency_heal" value="1" class="btn-core btn-accent font-mono" style="background-color: var(--status-danger) !important; border-color: var(--status-danger) !important; color: #fff !important; font-size: 0.78rem;">
                        <i class="fas fa-heart me-1"></i> Pulihkan Darurat (+30 HP)
                    </button>
                </form>
            <?php else: ?>
                <button type="button" class="btn-core btn-outline font-mono text-muted" disabled style="opacity: 0.6; cursor: not-allowed; font-size: 0.76rem;" title="Selesaikan minimal 1 habit atau quest hari ini">
                    <i class="fas fa-lock me-1"></i> Terkunci (0/1 Tugas)
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

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
        <div class="bento-panel tactical-frame h-100 d-flex flex-column justify-content-between position-relative overflow-hidden" style="background: radial-gradient(circle at 100% 0%, rgba(16, 185, 129, 0.16) 0%, transparent 48%), radial-gradient(circle at 0% 100%, rgba(99, 102, 241, 0.06) 0%, transparent 40%), #111114; border-top: 1px solid rgba(255, 255, 255, 0.18);">
            <div>
                <div class="sys-micro-tag"><span class="text-accent">OPERATOR.STATUS // ACTIVE</span> &bull; CORE HUD</div>
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
            <div class="mt-3 d-flex flex-column gap-2">
                <button type="button" class="btn-core btn-accent w-100 font-mono" onclick="window.openFocusRiftModal && window.openFocusRiftModal();">
                    <i class="fas fa-hourglass-half me-1"></i> Mulai Focus Rift
                </button>
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

<!-- Daily System Directive (Supply Crate Harian) -->
<div class="bento-panel tactical-frame frame-warning mb-4" style="border-top: 1px solid rgba(245, 158, 11, 0.4); background: linear-gradient(180deg, rgba(245, 158, 11, 0.05) 0%, rgba(17, 17, 20, 0.95) 45%);">
    <div class="sys-micro-tag mb-2"><span class="text-warning">SYS.DIRECTIVE // 01</span> &bull; DAILY SUPPLY ALLOCATION</div>
    <div class="panel-header mb-3">
        <div>
            <h3 class="panel-title"><i class="fas fa-box text-warning"></i> Daily System Directive</h3>
            <p class="text-muted mb-0" style="font-size: 0.78rem;">Penuhi 2 parameter target harian untuk membuka alokasi perbekalan (+20 EXP, +25 Gold).</p>
        </div>
        <div>
            <?php if ($isCrateClaimedToday): ?>
                <span class="badge-status accent font-mono" style="font-size: 0.75rem;">
                    <i class="fas fa-check-double me-1"></i> Crate Terklaim
                </span>
            <?php elseif ($target1Met && $target2Met): ?>
                <span class="badge-status warning font-mono pulse-live" style="font-size: 0.75rem;">
                    <i class="fas fa-gift me-1"></i> Siap Diklaim!
                </span>
            <?php else: ?>
                <span class="badge-status font-mono" style="font-size: 0.75rem;">
                    Progres Arahan
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 align-items-center">
        <div class="col-md-8">
            <div class="d-flex flex-column gap-2">
                <!-- Directive 1 -->
                <div class="d-flex align-items-center justify-content-between p-2" style="background: var(--bg-surface-hover); border: 1px solid var(--border-hairline); border-radius: var(--radius);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas <?php echo $target1Met ? 'fa-check-circle text-accent' : 'fa-circle text-muted'; ?>" style="font-size: 0.85rem;"></i>
                        <span style="font-size: 0.82rem; color: var(--text-primary);">Direktif 1: Tuntaskan minimal 2 Rutinitas hari ini</span>
                    </div>
                    <span class="badge-status font-mono <?php echo $target1Met ? 'accent' : ''; ?>" style="font-size: 0.72rem;">
                        <?php echo min(2, $doneHabitsToday); ?> / 2
                    </span>
                </div>
                <!-- Directive 2 -->
                <div class="d-flex align-items-center justify-content-between p-2" style="background: var(--bg-surface-hover); border: 1px solid var(--border-hairline); border-radius: var(--radius);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas <?php echo $target2Met ? 'fa-check-circle text-accent' : 'fa-circle text-muted'; ?>" style="font-size: 0.85rem;"></i>
                        <span style="font-size: 0.82rem; color: var(--text-primary);">Direktif 2: Tuntaskan minimal 1 Misi atau 1 Agenda Time-Block</span>
                    </div>
                    <span class="badge-status font-mono <?php echo $target2Met ? 'accent' : ''; ?>" style="font-size: 0.72rem;">
                        <?php echo min(1, ($doneQuestsToday + $doneTbHabits + $doneTbQuests)); ?> / 1
                    </span>
                </div>
            </div>
        </div>

        <div class="col-md-4 text-center">
            <?php if ($isCrateClaimedToday): ?>
                <div class="p-3" style="background: rgba(16, 185, 129, 0.08); border: 1px solid var(--accent-border); border-radius: var(--radius);">
                    <i class="fas fa-box-open text-accent fa-2x mb-2"></i>
                    <div class="font-mono text-accent" style="font-size: 0.85rem; font-weight: 600;">SUPPLY CRATE // TUNTAS</div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;">Alokasi perbekalan harian telah diterima. Reset pukul 00:00 WIB.</div>
                </div>
            <?php elseif ($target1Met && $target2Met): ?>
                <form method="POST" action="dashboard.php" class="m-0">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="claim_supply_crate" value="1" class="btn-core btn-accent font-mono w-100 py-3" style="font-size: 0.84rem;">
                        <i class="fas fa-gift me-1"></i> Klaim Supply Crate<br>
                        <span style="font-size: 0.72rem; opacity: 0.9;">(+20 EXP &amp; +25 Gold)</span>
                    </button>
                </form>
            <?php else: ?>
                <div class="p-3" style="background: var(--bg-surface-hover); border: 1px dashed var(--border-hairline); border-radius: var(--radius);">
                    <i class="fas fa-lock text-muted fa-2x mb-2"></i>
                    <div class="font-mono text-muted" style="font-size: 0.82rem;">SUPPLY CRATE // TERKUNCI</div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;">Penuhi kuota 2 direktif di samping untuk membuka kunci transfer perbekalan.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Ringkasan Jadwal Time-Block Hari Ini -->
<div class="bento-panel spotlight-card mb-4" style="margin-top: 1.5rem;">
    <div class="panel-header">
        <div>
            <h3 class="panel-title"><i class="fas fa-clock text-accent"></i> Jadwal Time-Block Hari Ini</h3>
            <p class="text-muted mb-0" style="font-size: 0.78rem;">Agenda rutinitas dan misi aktif berdasarkan rentang jam fokus.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge-status font-mono" style="font-size: 0.72rem;"><?php echo count($todayTimeBlocks); ?> Agenda</span>
            <a href="calendar.php" class="btn-core btn-outline font-mono" style="font-size: 0.75rem;">
                <i class="fas fa-calendar-alt me-1"></i> Kalender Taktis &rarr;
            </a>
        </div>
    </div>
    
    <div class="data-list">
        <?php if (!empty($todayTimeBlocks)): ?>
            <?php foreach (array_slice($todayTimeBlocks, 0, 5) as $tb): 
                $hasTime = (!empty($tb['start_time']) && !empty($tb['end_time']));
                $isLive = $hasTime && is_time_block_active($tb['start_time'], $tb['end_time']);
            ?>
                <div class="data-row">
                    <div class="data-main">
                        <div style="width: 28px; height: 28px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; color: <?php echo $isLive ? 'var(--accent)' : 'var(--text-muted)'; ?>; font-size: 0.75rem;">
                            <i class="fas <?php echo $tb['kind'] === 'quest' ? 'fa-map-signs' : 'fa-check-square'; ?>"></i>
                        </div>
                        <div>
                            <div class="data-title" style="<?php echo $tb['is_done'] ? 'text-decoration: line-through; opacity: 0.6;' : ''; ?>">
                                <?php echo htmlspecialchars($tb['title']); ?>
                            </div>
                            <div class="data-meta font-mono">
                                <span class="badge-status font-mono"><?php echo $tb['kind'] === 'quest' ? 'Misi' : 'Habit'; ?></span>
                                <span class="badge-status"><?php echo htmlspecialchars($tb['category']); ?></span>
                                <?php if ($hasTime): ?>
                                    <span class="badge-status font-mono <?php echo $isLive ? 'accent' : ''; ?>">
                                        <i class="fas fa-clock me-1"></i><?php echo htmlspecialchars(format_time_block($tb['start_time'], $tb['end_time'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status font-mono text-muted">Sepanjang Hari</span>
                                <?php endif; ?>
                                <?php if ($isLive): ?>
                                    <span class="badge-status accent font-mono pulse-live"><i class="fas fa-circle me-1" style="font-size: 0.45rem;"></i>LIVE NOW</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <span class="badge-status accent font-mono">
                            +<?php echo htmlspecialchars($tb['exp']); ?> EXP
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($todayTimeBlocks) > 5): ?>
                <div class="text-center pt-2">
                    <a href="calendar.php" style="font-size: 0.75rem; color: var(--text-secondary);">Lihat semua <?php echo count($todayTimeBlocks); ?> agenda di Kalender &rarr;</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-4 text-muted" style="font-size: 0.85rem;">
                Belum ada jadwal khusus hari ini. Buka kalender untuk merencanakan time-blocking!
            </div>
        <?php endif; ?>
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

    // Download Character Card PNG trigger
    document.getElementById('btnDownloadCardDashboard')?.addEventListener('click', function() {
        if (typeof window.generateCharacterCard === 'function') {
            window.generateCharacterCard({
                username: <?php echo json_encode($user['username'] ?? 'Player'); ?>,
                rank: <?php echo json_encode($rank); ?>,
                specialization: <?php echo json_encode($userSpec); ?>,
                level: <?php echo (int)$level; ?>,
                hp: <?php echo (int)($user['hp'] ?? 100); ?>,
                max_hp: <?php echo (int)($user['max_hp'] ?? 100); ?>,
                exp: <?php echo (int)$totalExp; ?>,
                streak: <?php echo (int)$maxStreak; ?>,
                gold: <?php echo (int)($user['gold'] ?? 0); ?>,
                trophies_count: <?php echo (int)($bossMastery['count'] ?? 0); ?>,
                relics: <?php 
                    $ownedNames = [];
                    foreach ($userRelics as $rk => $dt) {
                        if (isset($relicsList[$rk])) $ownedNames[] = $relicsList[$rk]['name'];
                    }
                    echo json_encode($ownedNames);
                ?>,
                attributes: <?php echo json_encode($radarAttrData); ?>
            });
            if (typeof LVSound !== 'undefined') LVSound.coin();
        }
    });

    // Auto-open Focus Rift modal if query string contains rift=1
    if (window.location.search.includes('rift=1')) {
        setTimeout(() => {
            if (window.openFocusRiftModal) window.openFocusRiftModal();
        }, 300);
    }
</script>

<?php require_once("../includes/footer.php"); ?>
