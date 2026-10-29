<?php
// pages/quests.php
require_once __DIR__ . '/../config/database.php';
enforce_login();

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $validDiffs = array_keys(game_difficulties());
    $validCats = array_keys(game_categories());

    // Edit Quest
    if (isset($_POST['edit_quest'])) {
        $questId = intval($_POST['quest_id'] ?? 0);
        $quest_name = trim($_POST['quest_name'] ?? '');
        $difficulty = $_POST['difficulty'] ?? '';
        $start_date = trim($_POST['start_date'] ?? '');
        $category = $_POST['category'] ?? '';

        if ($questId <= 0 || mb_strlen($quest_name) < 1 || mb_strlen($quest_name) > 150 || !in_array($difficulty, $validDiffs, true) || !in_array($category, $validCats, true)) {
            header("Location: quests.php?error=validation");
            exit();
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            $start_date = date('Y-m-d');
        }

        $rawSubtasks = trim($_POST['subtasks'] ?? '');
        $subtasksJson = null;
        if (!empty($rawSubtasks)) {
            $stmtCurr = $pdo->prepare("SELECT subtasks FROM quests WHERE quest_id = ? AND user_id = ?");
            $stmtCurr->execute([$questId, $_SESSION['user_id']]);
            $currList = json_decode($stmtCurr->fetchColumn() ?: '[]', true) ?: [];
            $doneMap = [];
            foreach ($currList as $item) {
                if (isset($item['title'])) $doneMap[$item['title']] = !empty($item['done']);
            }

            $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawSubtasks)));
            if (!empty($lines)) {
                $formatted = array_map(fn($t) => ['title' => $t, 'done' => !empty($doneMap[$t])], $lines);
                $subtasksJson = json_encode(array_values($formatted), JSON_UNESCAPED_UNICODE);
            }
        }

        $expReward = exp_for_difficulty($difficulty);
        $diffs = game_difficulties();
        $durationDays = $diffs[$difficulty]['duration_days'] ?? 7;

        try {
            $stmt = $pdo->prepare("
                UPDATE quests 
                SET quest_name = ?, difficulty = ?, start_date = ?, exp_reward = ?, duration_days = ?, category = ?, subtasks = ? 
                WHERE quest_id = ? AND user_id = ?
            ");
            $stmt->execute([$quest_name, $difficulty, $start_date, $expReward, $durationDays, $category, $subtasksJson, $questId, $_SESSION['user_id']]);
            header("Location: quests.php");
            exit();
        } catch (PDOException $e) {
            error_log("Quest update error: " . $e->getMessage());
            die("Error updating quest.");
        }
    }

    // Toggle subtask checklist (Support both AJAX & form submit)
    if (isset($_POST['toggle_subtask'])) {
        $questId = intval($_POST['quest_id'] ?? 0);
        $subIndex = intval($_POST['subtask_index'] ?? -1);
        $isDone = false;
        $totalSub = 0;
        $doneSub = 0;

        if ($questId > 0 && $subIndex >= 0) {
            $stmt = $pdo->prepare("SELECT subtasks FROM quests WHERE quest_id = ? AND user_id = ?");
            $stmt->execute([$questId, $_SESSION['user_id']]);
            $raw = $stmt->fetchColumn();
            $list = json_decode($raw ?: '[]', true) ?: [];
            if (isset($list[$subIndex])) {
                $list[$subIndex]['done'] = empty($list[$subIndex]['done']);
                $isDone = (bool)$list[$subIndex]['done'];
                $newJson = json_encode(array_values($list), JSON_UNESCAPED_UNICODE);
                $pdo->prepare("UPDATE quests SET subtasks = ? WHERE quest_id = ? AND user_id = ?")
                    ->execute([$newJson, $questId, $_SESSION['user_id']]);
                $totalSub = count($list);
                $doneSub = count(array_filter($list, fn($s) => !empty($s['done'])));
            }
        }

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            $bossHpPct = $totalSub > 0 ? round((($totalSub - $doneSub) / $totalSub) * 100) : 100;
            echo json_encode([
                'success' => true,
                'is_done' => $isDone,
                'total_sub' => $totalSub,
                'done_sub' => $doneSub,
                'boss_hp_pct' => $bossHpPct
            ]);
            exit();
        }

        header("Location: quests.php");
        exit();
    }

    if (isset($_POST['complete_quest'])) {
        $questId = intval($_POST['quest_id'] ?? 0);

        if ($questId > 0) {
            try {
                $pdo->beginTransaction();

                // Ambil detail quest terlebih dahulu (FOR UPDATE)
                $stmt = $pdo->prepare("SELECT quest_name, exp_reward, category, difficulty, subtasks FROM quests WHERE quest_id = ? AND user_id = ? FOR UPDATE");
                $stmt->execute([$questId, $_SESSION['user_id']]);
                $quest = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$quest) {
                    $pdo->rollBack();
                    header("Location: quests.php");
                    exit();
                }

                // Cek apakah Boss Quest memiliki subtasks yang belum selesai
                $questSubs = !empty($quest['subtasks']) ? json_decode($quest['subtasks'], true) : [];
                if (is_array($questSubs) && count($questSubs) > 0) {
                    $hasUnfinished = false;
                    foreach ($questSubs as $st) {
                        if (empty($st['done'])) {
                            $hasUnfinished = true;
                            break;
                        }
                    }
                    if ($hasUnfinished) {
                        $pdo->rollBack();
                        $_SESSION['quest_error'] = "Semua fase Boss Quest wajib dituntaskan (HP Boss 0%) sebelum dapat menyelesaikan misi!";
                        header("Location: quests.php?error=boss_not_defeated");
                        exit();
                    }
                }

                // Klaim atomik: hanya misi 'In Progress' milik user ini yang bisa diselesaikan,
                // dan hanya SEKALI. Replay POST / misi 'Failed' / klik ganda tidak memberi hadiah.
                $claim = $pdo->prepare("UPDATE quests SET status = 'Completed' WHERE quest_id = ? AND user_id = ? AND status = 'In Progress'");
                $claim->execute([$questId, $_SESSION['user_id']]);
                if ($claim->rowCount() === 0) {
                    $pdo->rollBack();
                    header("Location: quests.php");
                    exit();
                }

                // Cek apakah Boss Quest (Hard atau memiliki subtasks)
                $isBoss = ($quest['difficulty'] === 'Hard' || (!empty($questSubs) && count($questSubs) > 0));
                $multiplier = $isBoss ? 1.5 : 1.0;

                // Hitung Passive EXP Mastery Bonus dari Boss yang sudah pernah ditaklukkan
                $bossMastery = getBossMasteryStats($pdo, $_SESSION['user_id']);
                $masteryMultiplier = $bossMastery['multiplier'] ?? 1.0;

                // Ambil data user lama untuk deteksi Level Up & Rank Up
                $stmtUserOld = $pdo->prepare("SELECT exp, level FROM users WHERE user_id = ? FOR UPDATE");
                $stmtUserOld->execute([$_SESSION['user_id']]);
                $oldUserData = $stmtUserOld->fetch(PDO::FETCH_ASSOC);
                $oldExp = (int) ($oldUserData['exp'] ?? 0);
                $oldLvl = calculateLevel($oldExp);

                // Tambahkan EXP dan Gold ke pengguna (dengan bonus boss 1.5x dan bonus passive mastery jika applicable)
                $expReward = (int) floor($quest['exp_reward'] * $multiplier * $masteryMultiplier);
                $goldReward = (int) ($expReward * 2); // Quest memberikan Gold lebih banyak (x2)

                $newExpTotal = $oldExp + $expReward;
                $newLvl = calculateLevel($newExpTotal);
                $oldRank = calculateRank($oldLvl);
                $newRank = calculateRank($newLvl);

                $stmt = $pdo->prepare("UPDATE users SET exp = exp + ?, gold = gold + ?, level = ?, rank = ? WHERE user_id = ?");
                $stmt->execute([$expReward, $goldReward, $newLvl, $newRank, $_SESSION['user_id']]);

                if ($newLvl > $oldLvl) {
                    $_SESSION['level_up'] = [
                        'old_level' => $oldLvl,
                        'new_level' => $newLvl,
                        'old_rank' => $oldRank,
                        'new_rank' => $newRank,
                        'is_rank_up' => ($oldRank !== $newRank)
                    ];
                }

                // Set Session untuk Dopamine Toast & Confetti
                $_SESSION['reward_toast'] = [
                    'type' => $isBoss ? 'boss' : 'quest',
                    'name' => $isBoss ? "⚔️ BOSS CONQUERED: {$quest['quest_name']} [ ⚡ +1 Mastery Trophy ]" : $quest['quest_name'],
                    'exp' => $expReward,
                    'gold' => $goldReward,
                    'is_boss' => $isBoss
                ];

                // Tambah progres skill + ambil skill_id untuk log
                bump_skill_progress($pdo, $_SESSION['user_id'], $quest['category']);
                $skillMap = skill_id_for_category($quest['category']);
                $skillId = $skillMap[0] ?? null;

                // Log quest yang selesai, termasuk exp_reward
                $stmt = $pdo->prepare("INSERT INTO quest_logs (quest_id, user_id, skill_id, status, completed_at, exp_reward)
                                       VALUES (?, ?, ?, 'Completed', NOW(), ?)");
                $stmt->execute([$questId, $_SESSION['user_id'], $skillId, $expReward]);

                $pdo->commit();

                // Redirect ke halaman quests.php
                header("Location: quests.php");
                exit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Quest completion error: " . $e->getMessage());
                die("Error completing quest. Please try again.");
            }
        }
    }

    // Undo penyelesaian quest
    if (isset($_POST['undo_quest'])) {
        $questId = intval($_POST['quest_id'] ?? 0);

        if ($questId > 0) {
            try {
                $pdo->beginTransaction();

                // Pastikan quest berstatus Completed dan milik user ini
                $stmt = $pdo->prepare("SELECT quest_name, exp_reward, category FROM quests WHERE quest_id = ? AND user_id = ? AND status = 'Completed' FOR UPDATE");
                $stmt->execute([$questId, $_SESSION['user_id']]);
                $quest = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$quest) {
                    $pdo->rollBack();
                    header("Location: quests.php");
                    exit();
                }

                // Ambil log quest penyelesaian terakhir
                $stmtLog = $pdo->prepare("
                    SELECT log_id, exp_reward 
                    FROM quest_logs 
                    WHERE quest_id = ? AND user_id = ? AND status = 'Completed'
                    ORDER BY log_id DESC LIMIT 1
                ");
                $stmtLog->execute([$questId, $_SESSION['user_id']]);
                $log = $stmtLog->fetch(PDO::FETCH_ASSOC);

                $revertExp = $log ? (int) $log['exp_reward'] : (int) $quest['exp_reward'];
                $revertGold = $revertExp * 2;

                // Cek apakah gold mencukupi (anti-exploit)
                $stmtUser = $pdo->prepare("SELECT exp, gold FROM users WHERE user_id = ? FOR UPDATE");
                $stmtUser->execute([$_SESSION['user_id']]);
                $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if (!$userData || (int)$userData['gold'] < $revertGold) {
                    $pdo->rollBack();
                    $_SESSION['quest_error'] = "Aksi tidak bisa dibatalkan karena Gold reward misi sudah terpakai.";
                    header("Location: quests.php?error=gold_used");
                    exit();
                }

                $curExp = (int) $userData['exp'];
                $newExp = max(0, $curExp - $revertExp);
                $newGold = (int)$userData['gold'] - $revertGold;
                $newLevel = calculateLevel($newExp);
                $newRank = calculateRank($newLevel);

                // Kembalikan status misi menjadi 'In Progress'
                $pdo->prepare("UPDATE quests SET status = 'In Progress' WHERE quest_id = ? AND user_id = ?")
                    ->execute([$questId, $_SESSION['user_id']]);

                // Kurangi EXP, Gold, dan perbarui level serta rank
                $pdo->prepare("UPDATE users SET exp = ?, gold = ?, level = ?, rank = ? WHERE user_id = ?")
                    ->execute([$newExp, $newGold, $newLevel, $newRank, $_SESSION['user_id']]);

                // Revert progress skill
                revert_skill_progress($pdo, $_SESSION['user_id'], $quest['category']);

                // Hapus log penyelesaian
                if ($log) {
                    $pdo->prepare("DELETE FROM quest_logs WHERE log_id = ?")->execute([$log['log_id']]);
                }

                $pdo->commit();
                header("Location: quests.php?success=undone");
                exit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Quest undo error: " . $e->getMessage());
                die("Error undoing quest.");
            }
        }
    }

    if (isset($_POST['delete_quest'])) {
        $questId = $_POST['quest_id'] ?? null;

        if ($questId) {
            try {
                $stmt = $pdo->prepare("DELETE FROM quests WHERE quest_id = ? AND user_id = ?");
                $stmt->execute([$questId, $_SESSION['user_id']]);
                header("Location: quests.php");
                exit();
            } catch (PDOException $e) {
                error_log("Quest delete error: " . $e->getMessage());
                die("Error deleting quest. Please try again.");
            }
        }
    }

    if (isset($_POST['add_quest']) || (isset($_POST['quest_name']) && !isset($_POST['edit_quest']))) {
        $quest_name = trim($_POST['quest_name'] ?? '');
        $difficulty = $_POST['difficulty'] ?? '';
        $category = $_POST['category'] ?? '';

        if (mb_strlen($quest_name) < 1 || mb_strlen($quest_name) > 150 || !in_array($difficulty, $validDiffs, true) || !in_array($category, $validCats, true)) {
            header("Location: quests.php?error=validation");
            exit();
        }

        $today = date('Y-m-d');
        $rawDate = trim($_POST['due_date'] ?? ($_POST['start_date'] ?? ''));
        $diffs = game_difficulties();
        $defaultDuration = $diffs[$difficulty]['duration_days'] ?? 7;

        if (!empty($rawDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
            // Jika due_date >= today, hitung durasi hari selisihnya
            if (strtotime($rawDate) >= strtotime($today)) {
                $start_date = $today;
                $durationDays = max(1, (int) round((strtotime($rawDate) - strtotime($today)) / 86400));
            } else {
                $start_date = $rawDate;
                $durationDays = $defaultDuration;
            }
        } else {
            $start_date = $today;
            $durationDays = $defaultDuration;
        }

        $rawSubtasks = trim($_POST['subtasks'] ?? '');
        $subtasksJson = null;
        if (!empty($rawSubtasks)) {
            $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawSubtasks)));
            if (!empty($lines)) {
                $formatted = array_map(fn($t) => ['title' => $t, 'done' => false], $lines);
                $subtasksJson = json_encode(array_values($formatted), JSON_UNESCAPED_UNICODE);
            }
        }

        $expReward = exp_for_difficulty($difficulty, 0);
        $diffs = game_difficulties();
        $durationDays = $diffs[$difficulty]['duration_days'] ?? 7;

        try {
            if ($expReward === 0) {
                throw new Exception("Invalid difficulty level, EXP reward cannot be zero.");
            }

            $stmt = $pdo->prepare("INSERT INTO quests (user_id, quest_name, difficulty, start_date, exp_reward, duration_days, status, category, subtasks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $quest_name, $difficulty, $start_date, $expReward, $durationDays, 'In Progress', $category, $subtasksJson]);

            header("Location: quests.php");
            exit();
        } catch (PDOException $e) {
            error_log("Quest add error: " . $e->getMessage());
            die("Error adding quest. Please try again.");
        }
    }
}
// Ambil misi 'In Progress' dan misi selesai hari ini (setelah semua handler POST, sebelum ada output)
try {
    $stmt = $pdo->prepare("SELECT * FROM quests WHERE user_id = ? AND status = 'In Progress'");
    $stmt->execute([$_SESSION['user_id']]);
    $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil misi yang baru diselesaikan hari ini untuk opsi Undo
    $stmtDone = $pdo->prepare("
        SELECT q.*, ql.completed_at, ql.log_id, ql.exp_reward as logged_exp 
        FROM quests q 
        JOIN quest_logs ql ON q.quest_id = ql.quest_id 
        WHERE q.user_id = ? AND q.status = 'Completed' AND DATE(ql.completed_at) = CURDATE()
        ORDER BY ql.completed_at DESC
    ");
    $stmtDone->execute([$_SESSION['user_id']]);
    $completedQuests = $stmtDone->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching quests: " . $e->getMessage());
    die("Error loading quests. Please try again later.");
}

// Header baru di-include SETELAH semua handler POST (redirect butuh belum ada output)
$pageTitle = 'Misi';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Modal Add Quest -->
<div class="modal fade" id="addQuestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addQuestForm" method="POST" action="quests.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="add_quest" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-size: 1rem; font-weight: 600;"><i class="fas fa-plus me-2 text-accent"></i> Buat Misi Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="questName" class="form-label">Tujuan misi</label>
                        <input type="text" class="form-control" id="questName" name="quest_name" placeholder="mis., Tamatkan satu buku, Lari 5K" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Kategori / Atribut</label>
                        <select class="form-select" id="category" name="category" required>
                            <?php echo render_category_options(); ?>
                        </select>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-6">
                            <label for="difficulty" class="form-label">Tingkat kesulitan</label>
                            <select class="form-select" id="difficulty" name="difficulty" required>
                                <?php echo render_difficulty_options(); ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="dueDate" class="form-label">Tenggat Waktu</label>
                            <input type="date" class="form-control font-mono" id="dueDate" name="due_date" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label for="subtasks" class="form-label">Sub-langkah / Target Kecil Boss (Opsional)</label>
                        <textarea class="form-control" id="subtasks" name="subtasks" rows="3" placeholder="Langkah 1: Riset materi&#10;Langkah 2: Kerjakan draft&#10;Langkah 3: Review & Submit" style="font-size: 0.8rem;"></textarea>
                        <div class="text-muted mt-1" style="font-size: 0.72rem;">Misi dengan sub-langkah otomatis menjadi <strong>Boss Quest</strong> (bonus 1.5x EXP & Gold). Pisahkan tiap langkah dengan baris baru.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-core btn-ghost" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_quest" value="1" class="btn-core btn-primary">Mulai Misi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="page-header">
    <div>
        <h1 class="page-title">Misi Aktif</h1>
        <p class="page-subtitle">Target jangka panjang, proyek, dan tantangan ambisius.</p>
    </div>
    <button type="button" class="btn-core btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestModal">
        <i class="fas fa-plus"></i> Misi Baru
    </button>
</div>

<?php if (isset($_GET['error'])): ?>
    <?php if ($_GET['error'] === 'validation'): ?>
        <div class="alert alert-danger mb-3 d-flex align-items-center" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div>Input misi tidak valid. Periksa kembali nama misi, tingkat kesulitan, dan kategori.</div>
        </div>
    <?php elseif ($_GET['error'] === 'gold_used'): ?>
        <div class="alert alert-danger mb-3 d-flex align-items-center" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <div><?php echo htmlspecialchars($_SESSION['quest_error'] ?? 'Aksi tidak bisa dibatalkan karena Gold reward misi sudah terpakai.'); unset($_SESSION['quest_error']); ?></div>
        </div>
    <?php elseif ($_GET['error'] === 'boss_not_defeated'): ?>
        <div class="alert alert-danger mb-3 d-flex align-items-center" role="alert">
            <i class="fas fa-skull-crossbones me-2"></i>
            <div><?php echo htmlspecialchars($_SESSION['quest_error'] ?? 'Semua fase Boss Quest wajib dituntaskan (HP Boss 0%) terlebih dahulu.'); unset($_SESSION['quest_error']); ?></div>
        </div>
    <?php endif; ?>
<?php elseif (isset($_GET['success']) && $_GET['success'] === 'undone'): ?>
    <div class="alert alert-info mb-3 d-flex align-items-center" role="status">
        <i class="fas fa-undo me-2"></i>
        <div>Penyelesaian misi berhasil dibatalkan dan status misi kembali berjalan.</div>
    </div>
<?php endif; ?>

<div class="bento-panel">
    <?php if (empty($quests)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-map-marked-alt fa-2x mb-3" style="opacity: 0.3;"></i>
            <p class="mb-0">Belum ada misi berjalan. Mulai petualangan baru!</p>
        </div>
    <?php else: ?>
        <div class="task-filter-bar mb-3">
            <div class="filter-pills-category">
                <button type="button" class="btn-core btn-filter-pill active" data-filter="all"><i class="fas fa-layer-group"></i> Semua</button>
                <button type="button" class="btn-core btn-filter-pill" data-filter="Intelligence"><i class="fas fa-brain"></i> Intelligence</button>
                <button type="button" class="btn-core btn-filter-pill" data-filter="Physical"><i class="fas fa-dumbbell"></i> Physical</button>
                <button type="button" class="btn-core btn-filter-pill" data-filter="Health"><i class="fas fa-heart"></i> Health</button>
                <button type="button" class="btn-core btn-filter-pill" data-filter="Wealth"><i class="fas fa-coins"></i> Wealth</button>
                <button type="button" class="btn-core btn-filter-pill" data-filter="Social"><i class="fas fa-users"></i> Social</button>
            </div>
            <div class="filter-sort-wrap">
                <label for="sortQuests" class="text-muted" style="font-size: 0.74rem; white-space: nowrap;"><i class="fas fa-sort me-1"></i>Urutkan:</label>
                <select id="sortQuests" class="form-select form-select-sm font-mono" style="font-size: 0.76rem; width: auto; padding-right: 1.75rem; background-color: var(--bg-surface-hover); border-color: var(--border-hairline);">
                    <option value="deadline">Deadline Terdekat</option>
                    <option value="exp-desc">EXP Terbanyak</option>
                    <option value="newest">Terbaru</option>
                </select>
            </div>
        </div>

        <div class="data-list">
            <?php 
            $bossMastery = getBossMasteryStats($pdo, $_SESSION['user_id']);
            $masteryMultiplier = $bossMastery['multiplier'] ?? 1.0;

            foreach ($quests as $quest): 
                $startDate = $quest['start_date'] ?? date('Y-m-d');
                $duration = (int) ($quest['duration_days'] ?? 7);
                $deadline = date('Y-m-d', strtotime("$startDate + $duration days"));
                $today = date('Y-m-d');
                $daysLeft = (int) ceil((strtotime($deadline) - strtotime($today)) / 86400);
                $hpPenalty = hp_penalty_for_quest($quest['difficulty']);

                $subtasks = !empty($quest['subtasks']) ? json_decode($quest['subtasks'], true) : [];
                $isBoss = ($quest['difficulty'] === 'Hard' || (!empty($subtasks) && count($subtasks) > 0));
                $totalSub = is_array($subtasks) ? count($subtasks) : 0;
                $doneSub = $totalSub > 0 ? count(array_filter($subtasks, fn($s) => !empty($s['done']))) : 0;
                $bossHpPct = $totalSub > 0 ? round((($totalSub - $doneSub) / $totalSub) * 100) : 100;
                $finalExp = $isBoss ? (int) floor($quest['exp_reward'] * 1.5 * $masteryMultiplier) : (int) floor($quest['exp_reward'] * $masteryMultiplier);
            ?>
                <div class="data-row" data-category="<?php echo htmlspecialchars($quest['category']); ?>" data-deadline="<?php echo $daysLeft; ?>" data-exp="<?php echo $finalExp; ?>" data-id="<?php echo (int) $quest['quest_id']; ?>" data-is-boss="<?php echo $isBoss ? '1' : '0'; ?>">
                    <div class="data-main">
                        <div style="width: 30px; height: 30px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; color: <?php echo $isBoss ? 'var(--status-danger)' : ($quest['status'] === 'Completed' ? 'var(--accent)' : 'var(--text-muted)'); ?>; font-size: 0.8rem;">
                            <i class="fas <?php echo $isBoss ? 'fa-dragon' : ($quest['status'] === 'Completed' ? 'fa-check' : 'fa-scroll'); ?>"></i>
                        </div>
                        <div style="flex: 1;">
                            <div class="data-title d-flex align-items-center flex-wrap gap-2">
                                <span><?php echo htmlspecialchars($quest['quest_name']); ?></span>
                                <?php if ($isBoss): ?>
                                    <span class="badge-status danger font-mono" style="font-size: 0.65rem; padding: 0.15rem 0.5rem;">
                                        <i class="fas fa-skull me-1"></i> BOSS QUEST
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="data-meta">
                                <span class="badge-status"><?php echo htmlspecialchars($quest['category']); ?></span>
                                <span class="badge-status font-mono"><?php echo htmlspecialchars($quest['difficulty']); ?></span>
                                <span class="badge-status font-mono <?php echo $daysLeft <= 1 ? 'danger' : ($daysLeft <= 3 ? 'warning' : ''); ?>">
                                    <i class="fas fa-hourglass-half"></i> <?php echo $daysLeft < 0 ? 'Telat' : ($daysLeft === 0 ? 'Hari Terakhir!' : "Sisa {$daysLeft} hari"); ?>
                                </span>
                                <span class="text-danger font-mono" title="Penalti jika gagal"><i class="fas fa-heart text-danger"></i> -<?php echo $hpPenalty; ?> HP</span>
                            </div>

                            <!-- Boss Quest HP Bar & Subtask Checklist -->
                            <?php if ($totalSub > 0): ?>
                                <div class="boss-card-container spotlight-card holo-card mt-2 p-3" style="max-width: 520px;">
                                    <div class="d-flex justify-content-between align-items-center font-mono text-danger mb-1" style="font-size: 0.72rem;">
                                        <span><i class="fas fa-shield-virus me-1"></i> Sisa HP Boss</span>
                                        <span class="boss-hp-label"><?php echo $bossHpPct; ?>% (<?php echo ($totalSub - $doneSub); ?>/<?php echo $totalSub; ?> Fase)</span>
                                    </div>
                                    <div class="vitals-track boss-hp-track" style="height: 6px; background-color: rgba(255,255,255,0.06); margin-bottom: 10px;">
                                        <div class="vitals-fill boss-hp-fill" style="width: <?php echo $bossHpPct; ?>%; background-color: var(--status-danger); transition: width 0.3s ease;"></div>
                                    </div>
                                    <div class="d-flex flex-column gap-1">
                                        <?php foreach ($subtasks as $idx => $st): ?>
                                            <form method="POST" action="quests.php" class="subtask-toggle-form d-flex align-items-center gap-2 m-0">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="toggle_subtask" value="1">
                                                <input type="hidden" name="quest_id" value="<?php echo $quest['quest_id']; ?>">
                                                <input type="hidden" name="subtask_index" value="<?php echo $idx; ?>">
                                                <label class="subtask-row w-100 d-flex align-items-center gap-2 p-2 m-0" style="cursor: pointer; border-radius: var(--radius);">
                                                    <input type="checkbox" class="subtask-checkbox" <?php echo !empty($st['done']) ? 'checked' : ''; ?> style="accent-color: var(--accent); cursor: pointer; width: 15px; height: 15px; flex-shrink: 0;">
                                                    <span class="subtask-text" style="font-size: 0.8rem; line-height: 1.35; <?php echo !empty($st['done']) ? 'text-decoration: line-through; color: var(--text-muted);' : 'color: var(--text-primary);'; ?>">
                                                        <?php echo htmlspecialchars($st['title'] ?? ''); ?>
                                                    </span>
                                                </label>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="data-actions">
                        <div class="font-mono text-accent me-2" style="font-size: 0.82rem; font-weight: 500;">
                            +<?php echo $finalExp; ?> EXP<?php echo $isBoss ? ' <span class="badge-status accent font-mono" style="font-size: 0.6rem;">1.5x</span>' : ''; ?>
                        </div>
                        
                        <?php if ($quest['status'] === 'Completed'): ?>
                            <span class="badge-status accent"><i class="fas fa-check"></i> Tuntas</span>
                        <?php else: ?>
                            <button type="button" class="btn-core btn-outline font-mono text-danger btn-action-primary boss-action-locked <?php echo ($totalSub > 0 && $doneSub < $totalSub) ? '' : 'd-none'; ?>" style="padding: 0.35rem 0.75rem; font-size: 0.78rem; opacity: 0.65; cursor: not-allowed;" title="Kalahkan semua fase Boss Quest terlebih dahulu">
                                <i class="fas fa-lock me-1"></i> Lawan Boss
                            </button>
                            <form method="POST" action="quests.php" style="margin: 0;" class="form-action-primary boss-action-form <?php echo ($totalSub > 0 && $doneSub < $totalSub) ? 'd-none' : ''; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="quest_id" value="<?php echo $quest['quest_id']; ?>">
                                <button type="submit" name="complete_quest" class="btn-core btn-accent btn-action-primary" style="padding: 0.35rem 0.75rem; font-size: 0.78rem;">
                                    Selesaikan
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Edit Button -->
                        <button type="button" class="btn-core btn-ghost btn-action-secondary" style="padding: 0.35rem 0.55rem;" title="Ubah" aria-label="Ubah misi <?php echo htmlspecialchars($quest['quest_name']); ?>" data-bs-toggle="modal" data-bs-target="#editQuestModal<?php echo $quest['quest_id']; ?>">
                            <i class="fas fa-pencil-alt"></i>
                        </button>

                        <form method="POST" action="quests.php" style="margin: 0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="quest_id" value="<?php echo $quest['quest_id']; ?>">
                            <button type="submit" name="delete_quest" class="btn-core btn-ghost btn-action-secondary" style="padding: 0.35rem 0.55rem;" title="Hapus" aria-label="Hapus misi <?php echo htmlspecialchars($quest['quest_name']); ?>" data-confirm="Hapus misi ini secara permanen?" data-confirm-title="Hapus Misi" data-confirm-variant="danger" data-confirm-btn="Hapus">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Modal Edit Quest -->
                <div class="modal fade" id="editQuestModal<?php echo $quest['quest_id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="quests.php">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="edit_quest" value="1">
                                <input type="hidden" name="quest_id" value="<?php echo $quest['quest_id']; ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title" style="font-size: 1rem; font-weight: 600;"><i class="fas fa-edit me-2 text-accent"></i> Ubah Misi</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="edit_qname_<?php echo $quest['quest_id']; ?>" class="form-label">Tujuan misi</label>
                                        <input type="text" class="form-control" id="edit_qname_<?php echo $quest['quest_id']; ?>" name="quest_name" value="<?php echo htmlspecialchars($quest['quest_name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_qcat_<?php echo $quest['quest_id']; ?>" class="form-label">Kategori / Atribut</label>
                                        <select class="form-select" id="edit_qcat_<?php echo $quest['quest_id']; ?>" name="category" required>
                                            <?php echo render_category_options($quest['category']); ?>
                                        </select>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label for="edit_qdiff_<?php echo $quest['quest_id']; ?>" class="form-label">Tingkat kesulitan</label>
                                            <select class="form-select" id="edit_qdiff_<?php echo $quest['quest_id']; ?>" name="difficulty" required>
                                                <?php echo render_difficulty_options($quest['difficulty']); ?>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label for="edit_qdate_<?php echo $quest['quest_id']; ?>" class="form-label">Tanggal mulai</label>
                                            <input type="date" class="form-control" id="edit_qdate_<?php echo $quest['quest_id']; ?>" name="start_date" value="<?php echo htmlspecialchars($quest['start_date']); ?>" required>
                                        </div>
                                    </div>
                                    <?php
                                    $editSubs = !empty($quest['subtasks']) ? json_decode($quest['subtasks'], true) : [];
                                    $editSubText = '';
                                    if (is_array($editSubs)) {
                                        $editSubText = implode("\n", array_map(fn($item) => $item['title'] ?? '', $editSubs));
                                    }
                                    ?>
                                    <div class="mb-3 mt-3">
                                        <label for="edit_subtasks_<?php echo $quest['quest_id']; ?>" class="form-label">Sub-langkah / Target Kecil Boss (Opsional)</label>
                                        <textarea class="form-control" id="edit_subtasks_<?php echo $quest['quest_id']; ?>" name="subtasks" rows="3" placeholder="Pisahkan tiap langkah dengan baris baru" style="font-size: 0.8rem;"><?php echo htmlspecialchars($editSubText); ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn-core btn-ghost" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" name="edit_quest" value="1" class="btn-core btn-primary">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="filter-empty-state text-center py-4 text-muted" style="display: none;">
            <i class="fas fa-filter fa-2x mb-2" style="opacity: 0.3;"></i>
            <p class="mb-0" style="font-size: 0.85rem;">Tidak ada misi berjalan di kategori <span class="filter-empty-cat-name text-accent font-mono"></span>.</p>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($completedQuests)): ?>
<div class="panel-header mt-4 mb-2" style="border-bottom: 1px solid var(--border-hairline); padding-bottom: 0.6rem;">
    <h3 class="panel-title" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted);">
        <i class="fas fa-check-double text-accent me-1"></i> Misi Diselesaikan Hari Ini
    </h3>
</div>

<div class="bento-panel">
    <div class="data-list">
        <?php foreach ($completedQuests as $done): ?>
            <div class="data-row">
                <div class="data-main">
                    <div style="width: 30px; height: 30px; border-radius: var(--radius); background-color: var(--accent-dim); border: 1px solid var(--accent-border); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 0.8rem;">
                        <i class="fas fa-check"></i>
                    </div>
                    <div>
                        <div class="data-title" style="text-decoration: line-through; color: var(--text-muted);"><?php echo htmlspecialchars($done['quest_name']); ?></div>
                        <div class="data-meta">
                            <span class="badge-status"><?php echo htmlspecialchars($done['category']); ?></span>
                            <span class="badge-status accent font-mono"><i class="fas fa-check"></i> Tuntas Hari Ini</span>
                        </div>
                    </div>
                </div>
                <div class="data-actions">
                    <div class="font-mono text-accent me-2" style="font-size: 0.82rem;">
                        +<?php echo $done['logged_exp'] ?? $done['exp_reward']; ?> EXP
                    </div>
                    <form method="POST" action="quests.php" style="margin: 0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="quest_id" value="<?php echo $done['quest_id']; ?>">
                        <button type="submit" name="undo_quest" value="1" class="btn-core btn-ghost text-muted" style="padding: 0.35rem 0.65rem; font-size: 0.78rem;" title="Batalkan penyelesaian misi" data-confirm="Batalkan penyelesaian misi ini? EXP dan Gold reward akan ditarik kembali." data-confirm-title="Batalkan Misi" data-confirm-variant="warning" data-confirm-btn="Ya, Batalkan">
                            <i class="fas fa-undo me-1"></i> Batalkan
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.subtask-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function(e) {
            const isChecked = checkbox.checked;
            const form = checkbox.closest('form');
            const bossCard = checkbox.closest('.boss-card-container');
            const allCheckboxes = bossCard ? Array.from(bossCard.querySelectorAll('.subtask-checkbox')) : [checkbox];
            const totalSteps = allCheckboxes.length || 1;
            const stepPct = Math.round(100 / totalSteps);
            const doneCount = allCheckboxes.filter(c => c.checked).length;
            const remainingSteps = totalSteps - doneCount;
            const remainingPct = Math.max(0, Math.round((remainingSteps / totalSteps) * 100));

            // 1. Text strike-through style
            const labelSpan = checkbox.closest('label').querySelector('.subtask-text');
            if (labelSpan) {
                if (isChecked) {
                    labelSpan.style.textDecoration = 'line-through';
                    labelSpan.style.color = 'var(--text-muted)';
                } else {
                    labelSpan.style.textDecoration = 'none';
                    labelSpan.style.color = 'var(--text-primary)';
                }
            }

            // 2. Update HP Bar & Label
            if (bossCard) {
                const hpBar = bossCard.querySelector('.boss-hp-fill');
                const hpLabel = bossCard.querySelector('.boss-hp-label');
                if (hpBar) {
                    hpBar.style.width = remainingPct + '%';
                    if (remainingPct === 0) {
                        hpBar.style.backgroundColor = 'var(--accent)';
                    } else {
                        hpBar.style.backgroundColor = 'var(--status-danger)';
                    }
                }
                if (hpLabel) {
                    if (remainingPct === 0) {
                        hpLabel.innerHTML = '<span class="text-accent fw-bold"><i class="fas fa-trophy"></i> 0% (Fase Tuntas)</span>';
                    } else {
                        hpLabel.textContent = `${remainingPct}% (${remainingSteps}/${totalSteps} Fase)`;
                    }
                }
            }

            // 3. Update quest card action button (Lawan Boss / Selesaikan)
            const questRow = checkbox.closest('.data-row');
            if (questRow) {
                const lockedBtn = questRow.querySelector('.boss-action-locked');
                const formAction = questRow.querySelector('.boss-action-form');
                if (remainingPct === 0) {
                    if (lockedBtn) lockedBtn.classList.add('d-none');
                    if (formAction) formAction.classList.remove('d-none');
                } else {
                    if (lockedBtn) lockedBtn.classList.remove('d-none');
                    if (formAction) formAction.classList.add('d-none');
                }
            }

            // 4. Floating combat text & Animation
            const rect = checkbox.getBoundingClientRect();
            const x = rect.left + rect.width / 2;
            const y = rect.top;

            if (isChecked) {
                // Attacking Boss
                const textHtml = (remainingPct === 0)
                    ? '<span class="text-danger fw-bold"><i class="fas fa-skull text-danger me-1"></i>💀 Pertahanan Boss Hancur!</span>'
                    : `<span class="text-danger fw-bold"><i class="fas fa-crosshairs text-danger me-1"></i>⚔️ -${stepPct}% HP Boss</span>`;
                
                if (window.spawnFloatingReward) {
                    window.spawnFloatingReward(x, y, textHtml);
                }

                if (bossCard) {
                    bossCard.classList.add('boss-hit-shake', 'boss-hit-anim');
                    setTimeout(() => bossCard.classList.remove('boss-hit-shake', 'boss-hit-anim'), 350);
                }
                if (typeof LVSound !== 'undefined') LVSound.coin();
            } else {
                // Reverting / Healing Boss (amber/warning, no shake)
                const textHtml = `<span class="text-warning fw-bold"><i class="fas fa-undo-alt text-warning me-1"></i>↺ +${stepPct}% HP Boss Pulih</span>`;
                
                if (window.spawnFloatingReward) {
                    window.spawnFloatingReward(x, y, textHtml);
                }

                // JANGAN memicu animasi getar serangan saat centang dibatalkan
                if (typeof LVSound !== 'undefined') LVSound.tick();
            }

            // 5. AJAX POST submission to preserve state without full page flash
            if (form) {
                const formData = new FormData(form);
                fetch('quests.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).then(res => {
                    if (!res.ok) {
                        form.submit();
                    }
                }).catch(() => {
                    form.submit();
                });
            }
        });
    });

    // Check URL open_modal parameter for auto-open
    const params = new URLSearchParams(window.location.search);
    if (params.get('open_modal') === '1') {
        const modalEl = document.getElementById('addQuestModal');
        if (modalEl) {
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
            if (typeof bootstrap !== 'undefined') {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        }
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>

<?php require_once("../includes/footer.php"); ?>