<?php
// pages/habits.php
require_once __DIR__ . '/../config/database.php';
enforce_login();

$userId = $_SESSION['user_id'];

// Fungsi untuk menambahkan habit
function addHabit($pdo, $userId, $habitName, $difficulty, $resetPeriod, $category, $targetCount = 1, $scheduleType = 'daily', $scheduleValue = '1') {
    $expReward = exp_for_difficulty($difficulty, 0);
    $targetCount = max(1, min(50, (int)$targetCount));

    $stmt = $pdo->prepare("
    INSERT INTO habits (user_id, habit_name, difficulty, exp_reward, reset_period, category, completed_today, last_reset, target_count, current_count, schedule_type, schedule_value)
    VALUES (:user_id, :habit_name, :difficulty, :exp_reward, :reset_period, :category, 0, NOW(), :target_count, 0, :schedule_type, :schedule_value)
    ");

    // Eksekusi query untuk memasukkan habit baru ke dalam database
    $stmt->execute([
        ':user_id' => $userId,
        ':habit_name' => $habitName,
        ':difficulty' => $difficulty,
        ':exp_reward' => $expReward,
        ':reset_period' => $resetPeriod,
        ':category' => $category,
        ':target_count' => $targetCount,
        ':schedule_type' => $scheduleType,
        ':schedule_value' => $scheduleValue
    ]);
}

// Fungsi untuk menyelesaikan habit
function completeHabit($pdo, $userId, $habitId) {
    try {
        // Ambil habit dari database berdasarkan habit_id dan user_id
        $stmt = $pdo->prepare("SELECT * FROM habits WHERE habit_id = ? AND user_id = ?");
        $stmt->execute([$habitId, $userId]);
        $habit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$habit) {
            return;
        }

        $pdo->beginTransaction();

        // Klaim atomik: hanya SATU request yang bisa mengubah completed_today 0 -> 1.
        // Klik ganda / request paralel / replay POST tidak akan memberi hadiah dua kali.
        $claim = $pdo->prepare("UPDATE habits SET completed_today = 1, current_count = target_count, streak = streak + 1, last_completed_at = NOW() WHERE habit_id = ? AND user_id = ? AND completed_today = 0");
        $claim->execute([$habitId, $userId]);
        if ($claim->rowCount() === 0) {
            $pdo->rollBack();
            return;
        }

        $stmtStreak = $pdo->prepare("SELECT streak FROM habits WHERE habit_id = ? AND user_id = ?");
        $stmtStreak->execute([$habitId, $userId]);
        $newStreak = (int) $stmtStreak->fetchColumn();

        // Hitung Multiplier berdasarkan Streak (sentral via game.php)
        $multiplier = game_streak_multiplier($newStreak);

        $finalExp = floor($habit['exp_reward'] * $multiplier);
        $earnedGold = $finalExp; // Gold yang didapat sama dengan EXP akhir

        // Ambil data user lama untuk deteksi Level Up & Rank Up
        $stmtUserOld = $pdo->prepare("SELECT exp, level FROM users WHERE user_id = ? FOR UPDATE");
        $stmtUserOld->execute([$userId]);
        $oldUserData = $stmtUserOld->fetch(PDO::FETCH_ASSOC);
        $oldExp = (int) ($oldUserData['exp'] ?? 0);
        $oldLvl = calculateLevel($oldExp);

        $newExpTotal = $oldExp + $finalExp;
        $newLvl = calculateLevel($newExpTotal);
        $oldRank = calculateRank($oldLvl);
        $newRank = calculateRank($newLvl);

        // Tambahkan EXP dan Gold ke pengguna serta update level dan rank
        $pdo->prepare("UPDATE users SET exp = exp + ?, gold = gold + ?, level = ?, rank = ? WHERE user_id = ?")
            ->execute([$finalExp, $earnedGold, $newLvl, $newRank, $userId]);

        // Cek jika naik tingkat / rank
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
            'type' => 'habit',
            'name' => $habit['habit_name'],
            'exp' => $finalExp,
            'gold' => $earnedGold,
            'streak' => $newStreak,
            'multiplier' => $multiplier
        ];

        // Tambahkan progres skill berdasarkan kategori habit
        bump_skill_progress($pdo, $userId, $habit['category']);

        // Catat ke habit_logs dengan kolom exp_reward (simpan EXP final setelah multiplier)
        $pdo->prepare("
            INSERT INTO habit_logs (habit_id, user_id, completion_date, status, exp_reward)
            VALUES (?, ?, NOW(), 'Completed', ?)
        ")->execute([$habitId, $userId, $finalExp]);

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("completeHabit error: " . $e->getMessage());
    }
}



// Fungsi untuk membatalkan penyelesaian habit hari ini
function undoHabit($pdo, $userId, $habitId): bool {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT habit_name, category, exp_reward FROM habits WHERE habit_id = ? AND user_id = ? AND completed_today = 1 FOR UPDATE");
        $stmt->execute([$habitId, $userId]);
        $habit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$habit) {
            $pdo->rollBack();
            return false;
        }

        // Ambil log penyelesaian hari ini
        $stmtLog = $pdo->prepare("
            SELECT log_id, exp_reward 
            FROM habit_logs 
            WHERE habit_id = ? AND user_id = ? AND status = 'Completed' AND completion_date = CURDATE()
            ORDER BY log_id DESC LIMIT 1
        ");
        $stmtLog->execute([$habitId, $userId]);
        $log = $stmtLog->fetch(PDO::FETCH_ASSOC);

        $revertExp = $log ? (int) $log['exp_reward'] : (int) $habit['exp_reward'];
        $revertGold = $revertExp;

        // Ambil data user terkini (FOR UPDATE)
        $stmtUser = $pdo->prepare("SELECT exp, gold FROM users WHERE user_id = ? FOR UPDATE");
        $stmtUser->execute([$userId]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            $pdo->rollBack();
            return false;
        }

        $curGold = (int) ($userData['gold'] ?? 0);
        if ($curGold < $revertGold) {
            $pdo->rollBack();
            $_SESSION['habit_error'] = "Aksi tidak bisa dibatalkan karena Gold reward sudah terpakai.";
            return false;
        }

        $curExp = (int) ($userData['exp'] ?? 0);
        $newExp = max(0, $curExp - $revertExp);
        $newLevel = calculateLevel($newExp);
        $newRank = calculateRank($newLevel);
        $newGold = $curGold - $revertGold;

        // Revert status completed dan kurangi streak serta current_count
        $pdo->prepare("
            UPDATE habits 
            SET completed_today = 0, current_count = GREATEST(0, target_count - 1), streak = GREATEST(0, streak - 1) 
            WHERE habit_id = ? AND user_id = ?
        ")->execute([$habitId, $userId]);

        // Revert EXP dan Gold user serta level dan rank
        $pdo->prepare("UPDATE users SET exp = ?, gold = ?, level = ?, rank = ? WHERE user_id = ?")
            ->execute([$newExp, $newGold, $newLevel, $newRank, $userId]);

        // Revert progress skill
        revert_skill_progress($pdo, $userId, $habit['category']);

        // Hapus log penyelesaian
        if ($log) {
            $pdo->prepare("DELETE FROM habit_logs WHERE log_id = ?")->execute([$log['log_id']]);
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("undoHabit error: " . $e->getMessage());
        return false;
    }
}

// Fungsi untuk menghapus habit (dibatasi milik user sendiri)
function deleteHabit($pdo, $userId, $habitId) {
    $pdo->prepare("DELETE FROM habit_logs WHERE habit_id = ? AND user_id = ?")->execute([$habitId, $userId]);
    $pdo->prepare("DELETE FROM habits WHERE habit_id = ? AND user_id = ?")
        ->execute([$habitId, $userId]);
}

// Fungsi untuk update habit
function updateHabit($pdo, $userId, $habitId, $habitName, $difficulty, $resetPeriod, $category, $targetCount = 1, $scheduleType = 'daily', $scheduleValue = '1') {
    $expReward = exp_for_difficulty($difficulty);
    $targetCount = max(1, min(50, (int)$targetCount));
    $stmt = $pdo->prepare("
        UPDATE habits 
        SET habit_name = ?, difficulty = ?, reset_period = ?, category = ?, exp_reward = ?,
            target_count = ?, schedule_type = ?, schedule_value = ?
        WHERE habit_id = ? AND user_id = ?
    ");
    $stmt->execute([$habitName, $difficulty, $resetPeriod, $category, $expReward, $targetCount, $scheduleType, $scheduleValue, $habitId, $userId]);
}

function parse_habit_schedule_payload($data): array {
    $targetCount = max(1, min(50, intval($data['target_count'] ?? 1)));
    $scheduleType = $data['schedule_type'] ?? 'daily';
    $validScheduleTypes = ['daily', 'interval_days', 'weekly_days', 'interval_weeks', 'interval_months'];
    if (!in_array($scheduleType, $validScheduleTypes, true)) {
        $scheduleType = 'daily';
    }

    $scheduleValue = '1';
    $resetPeriod = 'daily';

    if ($scheduleType === 'daily') {
        $scheduleValue = '1';
        $resetPeriod = 'daily';
    } elseif ($scheduleType === 'interval_days') {
        $scheduleValue = (string) max(2, min(365, intval($data['interval_days_val'] ?? 2)));
        $resetPeriod = 'daily';
        } elseif ($scheduleType === 'weekly_days') {
            $days = $data['weekly_days_val'] ?? ($data['weekly_days_val[]'] ?? []);
            if (!is_array($days)) $days = explode(',', (string)$days);
            $cleanDays = array_values(array_unique(array_filter(array_map('intval', $days), fn($d) => $d >= 1 && $d <= 7)));
            sort($cleanDays);
            $scheduleValue = !empty($cleanDays) ? implode(',', $cleanDays) : '1,2,3,4,5,6,7';
            $resetPeriod = 'weekly';
    } elseif ($scheduleType === 'interval_weeks') {
        $scheduleValue = (string) max(1, min(52, intval($data['interval_weeks_val'] ?? 1)));
        $resetPeriod = 'weekly';
    } elseif ($scheduleType === 'interval_months') {
        $scheduleValue = (string) max(1, min(24, intval($data['interval_months_val'] ?? 1)));
        $resetPeriod = 'monthly';
    }

    return [$targetCount, $scheduleType, $scheduleValue, $resetPeriod];
}

// (Reset harian/mingguan/bulanan ditangani run_daily_rollover() di enforce_login())

// Proses permintaan POST (dengan validasi CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $validDiffs = array_keys(game_difficulties());
    $validCats = array_keys(game_categories());
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    // Stepper: Increment Habit
    if (isset($_POST['increment_habit']) && !empty($_POST['habit_id'])) {
        $habitId = intval($_POST['habit_id']);

        $stmt = $pdo->prepare("SELECT * FROM habits WHERE habit_id = ? AND user_id = ?");
        $stmt->execute([$habitId, $userId]);
        $habit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$habit) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Habit tidak ditemukan.']);
                exit();
            }
            header("Location: habits.php");
            exit();
        }

        $target = max(1, (int)($habit['target_count'] ?? 1));
        $current = (int)($habit['current_count'] ?? 0);

        if ($current < $target) {
            $newCount = $current + 1;
            if ($newCount < $target) {
                $pdo->prepare("UPDATE habits SET current_count = ? WHERE habit_id = ? AND user_id = ?")
                    ->execute([$newCount, $habitId, $userId]);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => true,
                        'status' => 'progress',
                        'habit_id' => $habitId,
                        'current_count' => $newCount,
                        'target_count' => $target,
                        'completed_today' => 0,
                        'floating_text' => "⚡ Progres {$newCount}/{$target}"
                    ]);
                    exit();
                }
            } else {
                // Reached target -> complete habit
                $pdo->prepare("UPDATE habits SET current_count = ?, last_completed_at = NOW() WHERE habit_id = ? AND user_id = ?")
                    ->execute([$target, $habitId, $userId]);
                completeHabit($pdo, $userId, $habitId);

                $uStmt = $pdo->prepare("SELECT exp, level, gold, hp FROM users WHERE user_id = ?");
                $uStmt->execute([$userId]);
                $uData = $uStmt->fetch(PDO::FETCH_ASSOC);

                $hStmt = $pdo->prepare("SELECT streak FROM habits WHERE habit_id = ? AND user_id = ?");
                $hStmt->execute([$habitId, $userId]);
                $curStreak = (int)$hStmt->fetchColumn();
                $multiplier = game_streak_multiplier($curStreak);
                $finalExp = floor($habit['exp_reward'] * $multiplier);

                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => true,
                        'status' => 'completed',
                        'habit_id' => $habitId,
                        'current_count' => $target,
                        'target_count' => $target,
                        'completed_today' => 1,
                        'streak' => $curStreak,
                        'multiplier' => $multiplier,
                        'exp_reward' => $finalExp,
                        'gold_reward' => $finalExp,
                        'user_exp' => $uData['exp'],
                        'user_level' => $uData['level'],
                        'user_gold' => $uData['gold'],
                        'floating_text' => "+{$finalExp} EXP • +{$finalExp} Gold (Tuntas {$target}/{$target}!)"
                    ]);
                    exit();
                }
            }
        }
        header("Location: habits.php");
        exit();
    }

    // Stepper: Decrement Habit
    if (isset($_POST['decrement_habit']) && !empty($_POST['habit_id'])) {
        $habitId = intval($_POST['habit_id']);

        $stmt = $pdo->prepare("SELECT * FROM habits WHERE habit_id = ? AND user_id = ?");
        $stmt->execute([$habitId, $userId]);
        $habit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$habit || (int)($habit['current_count'] ?? 0) <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Progres sudah 0.']);
                exit();
            }
            header("Location: habits.php");
            exit();
        }

        $target = max(1, (int)($habit['target_count'] ?? 1));
        $current = (int)($habit['current_count'] ?? 0);

        if (!empty($habit['completed_today']) || $current >= $target) {
            $undoSuccess = undoHabit($pdo, $userId, $habitId);
            if (!$undoSuccess) {
                $err = $_SESSION['habit_error'] ?? "Aksi tidak bisa dibatalkan karena Gold reward sudah terpakai.";
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $err]);
                    exit();
                }
                header("Location: habits.php?error=gold_used");
                exit();
            }
            $newCount = max(0, $target - 1);
            $pdo->prepare("UPDATE habits SET current_count = ? WHERE habit_id = ? AND user_id = ?")
                ->execute([$newCount, $habitId, $userId]);

            $uStmt = $pdo->prepare("SELECT exp, level, gold, hp FROM users WHERE user_id = ?");
            $uStmt->execute([$userId]);
            $uData = $uStmt->fetch(PDO::FETCH_ASSOC);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'status' => 'progress',
                    'habit_id' => $habitId,
                    'current_count' => $newCount,
                    'target_count' => $target,
                    'completed_today' => 0,
                    'user_exp' => $uData['exp'],
                    'user_level' => $uData['level'],
                    'user_gold' => $uData['gold'],
                    'floating_text' => "↺ Progres {$newCount}/{$target}"
                ]);
                exit();
            }
        } else {
            $newCount = max(0, $current - 1);
            $pdo->prepare("UPDATE habits SET current_count = ? WHERE habit_id = ? AND user_id = ?")
                ->execute([$newCount, $habitId, $userId]);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'status' => 'progress',
                    'habit_id' => $habitId,
                    'current_count' => $newCount,
                    'target_count' => $target,
                    'completed_today' => 0,
                    'floating_text' => "↺ Progres {$newCount}/{$target}"
                ]);
                exit();
            }
        }
        header("Location: habits.php");
        exit();
    }

    if (isset($_POST['edit_habit'])) {
        $habitId = intval($_POST['habit_id'] ?? 0);
        $name = trim($_POST['habit_name'] ?? '');
        $diff = $_POST['difficulty'] ?? '';
        $cat = $_POST['category'] ?? '';
        [$targetCount, $scheduleType, $scheduleValue, $resetPeriod] = parse_habit_schedule_payload($_POST);

        if ($habitId <= 0 || mb_strlen($name) < 1 || mb_strlen($name) > 100 || !in_array($diff, $validDiffs, true) || !in_array($cat, $validCats, true)) {
            header("Location: habits.php?error=validation");
            exit();
        }

        updateHabit($pdo, $userId, $habitId, $name, $diff, $resetPeriod, $cat, $targetCount, $scheduleType, $scheduleValue);
        header("Location: habits.php");
        exit();
    }

    if (isset($_POST['add_habit']) || (!empty($_POST['habit_name']) && !isset($_POST['edit_habit']))) {
        $name = trim($_POST['habit_name'] ?? '');
        $diff = $_POST['difficulty'] ?? '';
        $cat = $_POST['category'] ?? '';
        [$targetCount, $scheduleType, $scheduleValue, $resetPeriod] = parse_habit_schedule_payload($_POST);

        if (mb_strlen($name) < 1 || mb_strlen($name) > 100 || !in_array($diff, $validDiffs, true) || !in_array($cat, $validCats, true)) {
            header("Location: habits.php?error=validation");
            exit();
        }

        addHabit($pdo, $userId, $name, $diff, $resetPeriod, $cat, $targetCount, $scheduleType, $scheduleValue);
        header("Location: habits.php");
        exit();
    }

    if (isset($_POST['complete_habit']) && !empty($_POST['habit_id'])) {
        $habitId = intval($_POST['habit_id']);
        completeHabit($pdo, $userId, $habitId);
        $pdo->prepare("UPDATE habits SET current_count = target_count, last_completed_at = NOW() WHERE habit_id = ? AND user_id = ?")
            ->execute([$habitId, $userId]);
        header("Location: habits.php");
        exit();
    }

    if (isset($_POST['undo_habit']) && !empty($_POST['habit_id'])) {
        $habitId = intval($_POST['habit_id']);
        $res = undoHabit($pdo, $userId, $habitId);
        if (!$res && isset($_SESSION['habit_error'])) {
            header("Location: habits.php?error=gold_used");
            exit();
        }
        $pdo->prepare("UPDATE habits SET current_count = GREATEST(0, target_count - 1) WHERE habit_id = ? AND user_id = ?")
            ->execute([$habitId, $userId]);
        header("Location: habits.php");
        exit();
    }

    if (isset($_POST['install_starter_habits'])) {
        // Tolak jika pengguna sudah memiliki habit
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE user_id = ?");
        $stmtCount->execute([$userId]);
        $existingCount = (int) $stmtCount->fetchColumn();

        if ($existingCount > 0) {
            header("Location: habits.php?error=starter_exists");
            exit();
        }

        $starters = [
            ['Minum 2 Liter Air', 'Easy', 'daily', 'Health', 4, 'daily', '1'],
            ['Membaca Buku 15 Menit', 'Easy', 'daily', 'Intelligence', 1, 'daily', '1'],
            ['Olahraga / Jalan 20 Menit', 'Medium', 'daily', 'Physical', 1, 'daily', '1'],
            ['Tidur Sebelum Jam 23:00', 'Medium', 'daily', 'Health', 1, 'daily', '1'],
            ['Merapikan Meja Kerja', 'Easy', 'daily', 'Social', 1, 'daily', '1']
        ];
        foreach ($starters as $s) {
            addHabit($pdo, $userId, $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6]);
        }
        header("Location: habits.php");
        exit();
    }

    if (isset($_POST['delete_habit']) && !empty($_POST['habit_id'])) {
        deleteHabit($pdo, $userId, intval($_POST['habit_id']));
        header("Location: habits.php?success=deleted");
        exit();
    }
}

// Ambil data habits
$stmt = $pdo->prepare("SELECT * FROM habits WHERE user_id = ?");
$stmt->execute([$userId]);
$habits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Kebiasaan';
require_once '../includes/header.php';
?>


<!-- Modal Add Habit -->
<div class="modal fade" id="addHabitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addHabitForm" method="POST" action="habits.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="add_habit" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-size: 1rem; font-weight: 600;"><i class="fas fa-plus me-2 text-accent"></i> Buat Rutinitas Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="habitName" class="form-label">Nama kebiasaan</label>
                        <input type="text" class="form-control" id="habitName" name="habit_name" placeholder="mis., Baca 15 halaman, 20 pushup" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Kategori / Area atribut</label>
                        <select class="form-select" id="category" name="category" required>
                            <?php echo render_category_options(); ?>
                        </select>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="difficulty" class="form-label">Tingkat kesulitan</label>
                            <select class="form-select" id="difficulty" name="difficulty" required>
                                <?php echo render_difficulty_options(); ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="targetCount" class="form-label">Target per siklus</label>
                            <input type="number" class="form-control font-mono" id="targetCount" name="target_count" min="1" max="50" value="1" required>
                        </div>
                        <div class="col-12">
                            <div class="text-muted" style="font-size: 0.72rem;">Isi 1 untuk tombol centang biasa, atau &gt;1 (misal: 2 untuk Sikat Gigi 2x, 3 untuk Minum 3 Botol) agar muncul tombol [ - ] dan [ + ].</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="scheduleType" class="form-label">Pola Jadwal Rutinitas</label>
                        <select class="form-select" id="scheduleType" name="schedule_type" onchange="toggleScheduleOptions(this)">
                            <option value="daily" selected>Setiap Hari</option>
                            <option value="interval_days">Tiap N Hari Sekali</option>
                            <option value="weekly_days">Hari Tertentu dalam Seminggu</option>
                            <option value="interval_weeks">Tiap N Minggu Sekali</option>
                            <option value="interval_months">Tiap N Bulan Sekali</option>
                        </select>
                    </div>

                    <!-- Dynamic Schedule Option Containers -->
                    <div class="schedule-option-container sched-opt-interval_days mb-3 d-none">
                        <label class="form-label">Setiap berapa hari sekali?</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-surface text-muted">Setiap</span>
                            <input type="number" class="form-control font-mono" name="interval_days_val" min="2" max="365" value="2">
                            <span class="input-group-text bg-surface text-muted">Hari Sekali</span>
                        </div>
                    </div>

                    <div class="schedule-option-container sched-opt-weekly_days mb-3 d-none">
                        <label class="form-label mb-2">Pilih Hari Aktif:</label>
                        <div class="d-flex flex-wrap gap-1">
                            <?php 
                            $dayNames = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
                            foreach ($dayNames as $dNum => $dName): ?>
                                <label class="day-checkbox-pill">
                                    <input type="checkbox" name="weekly_days_val[]" value="<?php echo $dNum; ?>" <?php echo in_array($dNum, [1,2,3,4,5]) ? 'checked' : ''; ?>>
                                    <span><?php echo $dName; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="schedule-option-container sched-opt-interval_weeks mb-3 d-none">
                        <label class="form-label">Setiap berapa minggu sekali?</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-surface text-muted">Setiap</span>
                            <input type="number" class="form-control font-mono" name="interval_weeks_val" min="1" max="52" value="1">
                            <span class="input-group-text bg-surface text-muted">Minggu Sekali</span>
                        </div>
                    </div>

                    <div class="schedule-option-container sched-opt-interval_months mb-3 d-none">
                        <label class="form-label">Setiap berapa bulan sekali?</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-surface text-muted">Setiap</span>
                            <input type="number" class="form-control font-mono" name="interval_months_val" min="1" max="24" value="1">
                            <span class="input-group-text bg-surface text-muted">Bulan Sekali</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-core btn-ghost" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_habit" value="1" class="btn-core btn-primary">Buat Rutinitas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="page-header">
    <div>
        <h1 class="page-title">Rutinitas Harian</h1>
        <p class="page-subtitle">Lacak, jaga, dan lipatgandakan disiplin harianmu.</p>
    </div>
    <button class="btn-core btn-primary" data-bs-toggle="modal" data-bs-target="#addHabitModal">
        <i class="fas fa-plus"></i> Kebiasaan Baru
    </button>
</div>

<?php if (isset($_GET['error'])): ?>
    <?php if ($_GET['error'] === 'gold_used'): ?>
        <div class="alert alert-danger mb-3 d-flex align-items-center" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <div><?php echo htmlspecialchars($_SESSION['habit_error'] ?? 'Aksi tidak bisa dibatalkan karena Gold reward sudah terpakai.'); unset($_SESSION['habit_error']); ?></div>
        </div>
    <?php elseif ($_GET['error'] === 'starter_exists'): ?>
        <div class="alert alert-warning mb-3 d-flex align-items-center" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <div>Paket starter hanya bisa dipasang jika Anda belum memiliki rutinitas aktif.</div>
        </div>
    <?php elseif ($_GET['error'] === 'validation'): ?>
        <div class="alert alert-danger mb-3 d-flex align-items-center" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div>Input kebiasaan tidak valid. Periksa kembali nama, tingkat kesulitan, dan kategori.</div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="bento-panel">
    <?php if (empty($habits)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-seedling fa-2x mb-3 text-accent" style="opacity: 0.6;"></i>
            <h3 style="font-size: 1.1rem; color: var(--text-primary); margin-bottom: 0.5rem;">Belum ada rutinitas aktif</h3>
            <p class="mb-4" style="max-width: 450px; margin: 0 auto 1.5rem auto; font-size: 0.85rem;">Bangun fondasi karaktermu sekarang. Buat rutinitas pertamamu atau pasang paket starter langsung.</p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <button class="btn-core btn-primary" data-bs-toggle="modal" data-bs-target="#addHabitModal">
                    <i class="fas fa-plus"></i> Buat Sendiri
                </button>
                <form method="POST" action="habits.php" style="margin: 0;">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="install_starter_habits" value="1" class="btn-core btn-outline">
                        <i class="fas fa-magic text-accent"></i> Pasang 5 Kebiasaan Starter
                    </button>
                </form>
            </div>
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
                <label for="sortHabits" class="text-muted" style="font-size: 0.74rem; white-space: nowrap;"><i class="fas fa-sort me-1"></i>Urutkan:</label>
                <select id="sortHabits" class="form-select form-select-sm font-mono" style="font-size: 0.76rem; width: auto; padding-right: 1.75rem; background-color: var(--bg-surface-hover); border-color: var(--border-hairline);">
                    <option value="default">Bawaan</option>
                    <option value="streak-desc">Streak Tertinggi</option>
                    <option value="exp-desc">EXP Terbanyak</option>
                    <option value="newest">Terbaru</option>
                </select>
            </div>
        </div>

        <div class="data-list">
            <?php foreach ($habits as $habit): 
                $hTarget = max(1, (int)($habit['target_count'] ?? 1));
                $hCur = min($hTarget, max(0, (int)($habit['current_count'] ?? 0)));
                $isHabitDone = (!empty($habit['completed_today']) || $hCur >= $hTarget);
                $schedBadge = formatHabitScheduleLabel($habit);
                $isDue = isHabitDueToday($habit);
            ?>
                <div class="data-row" data-category="<?php echo htmlspecialchars($habit['category']); ?>" data-streak="<?php echo (int) ($habit['streak'] ?? 0); ?>" data-exp="<?php echo (int) $habit['exp_reward']; ?>" data-id="<?php echo (int) $habit['habit_id']; ?>">
                    <div class="data-main">
                        <div class="habit-icon-status" style="width: 30px; height: 30px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; color: <?php echo $isHabitDone ? 'var(--accent)' : 'var(--text-muted)'; ?>; font-size: 0.8rem;">
                            <i class="fas <?php echo $isHabitDone ? 'fa-check' : 'fa-circle'; ?>"></i>
                        </div>
                        <div>
                            <div class="data-title d-flex align-items-center flex-wrap gap-2">
                                <span><?php echo htmlspecialchars($habit['habit_name']); ?></span>
                                <?php 
                                    $streak = $habit['streak'] ?? 0;
                                    if ($streak > 0): 
                                        $boostText = '';
                                        if ($streak >= 7) {
                                            $boostText = ' • 1.5x';
                                        } elseif ($streak >= 3) {
                                            $boostText = ' • 1.2x';
                                        }
                                ?>
                                    <span class="badge-status warning font-mono" style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.22); color: #f59e0b;">
                                        <i class="fas fa-fire"></i> <?php echo $streak; ?> hari<?php echo $boostText; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="data-meta">
                                <span class="badge-status"><?php echo htmlspecialchars($habit['category']); ?></span>
                                <span class="badge-status font-mono"><?php echo htmlspecialchars($habit['difficulty']); ?></span>
                                <span class="badge-status font-mono" title="Pola jadwal rutinitas"><i class="fas fa-calendar-alt me-1"></i><?php echo htmlspecialchars($schedBadge); ?></span>
                                <?php if (!$isDue): ?>
                                    <span class="badge-status warning font-mono" style="opacity: 0.85;" title="Tidak wajib hari ini"><i class="fas fa-hourglass-half me-1"></i>Jadwal Opsional Hari Ini</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="data-actions">
                        <div class="font-mono text-accent me-2" style="font-size: 0.82rem; font-weight: 500;">
                            +<?php echo $habit['exp_reward']; ?> EXP
                        </div>
                        
                        <?php if ($hTarget > 1): ?>
                            <!-- Stepper Control -->
                            <div class="habit-stepper" data-habit-id="<?php echo $habit['habit_id']; ?>" data-target="<?php echo $hTarget; ?>" data-current="<?php echo $hCur; ?>" data-exp="<?php echo $habit['exp_reward']; ?>">
                                <button type="button" class="btn-stepper btn-stepper-dec" <?php echo ($hCur <= 0) ? 'disabled' : ''; ?> title="Kurangi progres">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <div class="stepper-display font-mono <?php echo $isHabitDone ? 'completed' : ''; ?>">
                                    <span class="stepper-text">
                                        <?php if ($isHabitDone): ?>
                                            <i class="fas fa-check text-accent me-1"></i><?php echo "{$hCur}/{$hTarget}"; ?>
                                        <?php else: ?>
                                            <?php echo "{$hCur}/{$hTarget}"; ?>
                                        <?php endif; ?>
                                    </span>
                                    <div class="stepper-progress-track">
                                        <div class="stepper-progress-fill" style="width: <?php echo min(100, round(($hCur / $hTarget) * 100)); ?>%;"></div>
                                    </div>
                                </div>
                                <button type="button" class="btn-stepper btn-stepper-inc" <?php echo ($isHabitDone) ? 'disabled' : ''; ?> title="Tambah progres">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Single Action Button (Target = 1) -->
                            <?php if ($isHabitDone): ?>
                                <span class="badge-status accent"><i class="fas fa-check"></i> Tuntas</span>
                                <form method="POST" action="habits.php" style="margin: 0; display: inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="undo_habit" value="1">
                                    <input type="hidden" name="habit_id" value="<?php echo $habit['habit_id']; ?>">
                                    <button type="submit" class="btn-core btn-ghost text-muted" style="padding: 0.35rem 0.55rem;" title="Batalkan penyelesaian hari ini" aria-label="Batalkan penyelesaian <?php echo htmlspecialchars($habit['habit_name']); ?>" data-confirm="Batalkan penyelesaian kebiasaan ini? EXP dan Gold akan ditarik kembali." data-confirm-title="Batalkan Kebiasaan" data-confirm-variant="warning" data-confirm-btn="Ya, Batalkan">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="margin: 0;" class="form-action-primary">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="habit_id" value="<?php echo $habit['habit_id']; ?>">
                                    <button type="submit" name="complete_habit" class="btn-core btn-accent btn-action-primary" style="padding: 0.35rem 0.75rem; font-size: 0.78rem;">
                                        Selesaikan
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Edit Button -->
                        <button type="button" class="btn-core btn-ghost btn-action-secondary" style="padding: 0.35rem 0.55rem;" title="Ubah" aria-label="Ubah kebiasaan <?php echo htmlspecialchars($habit['habit_name']); ?>" data-bs-toggle="modal" data-bs-target="#editHabitModal<?php echo $habit['habit_id']; ?>">
                            <i class="fas fa-pencil-alt"></i>
                        </button>

                        <form method="POST" style="margin: 0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="habit_id" value="<?php echo $habit['habit_id']; ?>">
                            <button type="submit" name="delete_habit" class="btn-core btn-ghost btn-action-secondary" style="padding: 0.35rem 0.55rem;" title="Hapus" aria-label="Hapus kebiasaan <?php echo htmlspecialchars($habit['habit_name']); ?>" data-confirm="Hapus kebiasaan ini secara permanen?" data-confirm-title="Hapus Kebiasaan" data-confirm-variant="danger" data-confirm-btn="Hapus">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Modal Edit Habit -->
                <div class="modal fade" id="editHabitModal<?php echo $habit['habit_id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="habits.php">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="edit_habit" value="1">
                                <input type="hidden" name="habit_id" value="<?php echo $habit['habit_id']; ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title" style="font-size: 1rem; font-weight: 600;"><i class="fas fa-edit me-2 text-accent"></i> Ubah Rutinitas</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="edit_hname_<?php echo $habit['habit_id']; ?>" class="form-label">Nama kebiasaan</label>
                                        <input type="text" class="form-control" id="edit_hname_<?php echo $habit['habit_id']; ?>" name="habit_name" value="<?php echo htmlspecialchars($habit['habit_name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_hcat_<?php echo $habit['habit_id']; ?>" class="form-label">Kategori / Atribut</label>
                                        <select class="form-select" id="edit_hcat_<?php echo $habit['habit_id']; ?>" name="category" required>
                                            <?php echo render_category_options($habit['category']); ?>
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label for="edit_hdiff_<?php echo $habit['habit_id']; ?>" class="form-label">Tingkat kesulitan</label>
                                            <select class="form-select" id="edit_hdiff_<?php echo $habit['habit_id']; ?>" name="difficulty" required>
                                                <?php echo render_difficulty_options($habit['difficulty']); ?>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label for="edit_htarget_<?php echo $habit['habit_id']; ?>" class="form-label">Target per siklus</label>
                                            <input type="number" class="form-control font-mono" id="edit_htarget_<?php echo $habit['habit_id']; ?>" name="target_count" min="1" max="50" value="<?php echo max(1, (int)($habit['target_count'] ?? 1)); ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <div class="text-muted" style="font-size: 0.72rem;">Isi 1 untuk tombol biasa, atau &gt;1 (misal 2x atau 3x) agar muncul kontrol [ - ] dan [ + ].</div>
                                        </div>
                                    </div>

                                    <?php 
                                        $hType = $habit['schedule_type'] ?? 'daily';
                                        $hVal = (string)($habit['schedule_value'] ?? '1');
                                        $hWeeklyDays = ($hType === 'weekly_days') ? array_filter(array_map('intval', explode(',', $hVal))) : [1,2,3,4,5];
                                        $dayNames = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
                                    ?>
                                    <div class="mb-3">
                                        <label for="edit_hsched_<?php echo $habit['habit_id']; ?>" class="form-label">Pola Jadwal Rutinitas</label>
                                        <select class="form-select" id="edit_hsched_<?php echo $habit['habit_id']; ?>" name="schedule_type" onchange="toggleScheduleOptions(this)">
                                            <option value="daily" <?php echo $hType === 'daily' ? 'selected' : ''; ?>>Setiap Hari</option>
                                            <option value="interval_days" <?php echo $hType === 'interval_days' ? 'selected' : ''; ?>>Tiap N Hari Sekali</option>
                                            <option value="weekly_days" <?php echo $hType === 'weekly_days' ? 'selected' : ''; ?>>Hari Tertentu dalam Seminggu</option>
                                            <option value="interval_weeks" <?php echo $hType === 'interval_weeks' ? 'selected' : ''; ?>>Tiap N Minggu Sekali</option>
                                            <option value="interval_months" <?php echo $hType === 'interval_months' ? 'selected' : ''; ?>>Tiap N Bulan Sekali</option>
                                        </select>
                                    </div>

                                    <!-- Option: Tiap N Hari Sekali -->
                                    <div class="schedule-option-container sched-opt-interval_days mb-3 <?php echo $hType === 'interval_days' ? '' : 'd-none'; ?>">
                                        <label class="form-label">Setiap berapa hari sekali?</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-surface text-muted">Setiap</span>
                                            <input type="number" class="form-control font-mono" name="interval_days_val" min="2" max="365" value="<?php echo $hType === 'interval_days' ? max(2, (int)$hVal) : 2; ?>">
                                            <span class="input-group-text bg-surface text-muted">Hari Sekali</span>
                                        </div>
                                    </div>

                                    <!-- Option: Hari Tertentu dalam Seminggu -->
                                    <div class="schedule-option-container sched-opt-weekly_days mb-3 <?php echo $hType === 'weekly_days' ? '' : 'd-none'; ?>">
                                        <label class="form-label mb-2">Pilih Hari Aktif:</label>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($dayNames as $dNum => $dName): ?>
                                                <label class="day-checkbox-pill">
                                                    <input type="checkbox" name="weekly_days_val[]" value="<?php echo $dNum; ?>" <?php echo in_array($dNum, $hWeeklyDays) ? 'checked' : ''; ?>>
                                                    <span><?php echo $dName; ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Option: Tiap N Minggu Sekali -->
                                    <div class="schedule-option-container sched-opt-interval_weeks mb-3 <?php echo $hType === 'interval_weeks' ? '' : 'd-none'; ?>">
                                        <label class="form-label">Setiap berapa minggu sekali?</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-surface text-muted">Setiap</span>
                                            <input type="number" class="form-control font-mono" name="interval_weeks_val" min="1" max="52" value="<?php echo $hType === 'interval_weeks' ? max(1, (int)$hVal) : 1; ?>">
                                            <span class="input-group-text bg-surface text-muted">Minggu Sekali</span>
                                        </div>
                                    </div>

                                    <!-- Option: Tiap N Bulan Sekali -->
                                    <div class="schedule-option-container sched-opt-interval_months mb-3 <?php echo $hType === 'interval_months' ? '' : 'd-none'; ?>">
                                        <label class="form-label">Setiap berapa bulan sekali?</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-surface text-muted">Setiap</span>
                                            <input type="number" class="form-control font-mono" name="interval_months_val" min="1" max="24" value="<?php echo $hType === 'interval_months' ? max(1, (int)$hVal) : 1; ?>">
                                            <span class="input-group-text bg-surface text-muted">Bulan Sekali</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn-core btn-ghost" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" name="edit_habit" value="1" class="btn-core btn-primary">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="filter-empty-state text-center py-4 text-muted" style="display: none;">
            <i class="fas fa-filter fa-2x mb-2" style="opacity: 0.3;"></i>
            <p class="mb-0" style="font-size: 0.85rem;">Tidak ada kebiasaan di kategori <span class="filter-empty-cat-name text-accent font-mono"></span>.</p>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleScheduleOptions(selectEl) {
    const val = selectEl.value;
    const form = selectEl.closest('form');
    if (!form) return;
    form.querySelectorAll('.schedule-option-container').forEach(el => el.classList.add('d-none'));
    const target = form.querySelector('.sched-opt-' + val);
    if (target) {
        target.classList.remove('d-none');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // 1. Check URL new_cat or open_modal parameter for auto-open
    const params = new URLSearchParams(window.location.search);
    const newCat = params.get('new_cat');
    const openModal = params.get('open_modal');
    const validCats = ['Intelligence', 'Physical', 'Health', 'Wealth', 'Social'];
    if (openModal === '1' || (newCat && validCats.includes(newCat))) {
        const catSelect = document.getElementById('category');
        if (catSelect && newCat && validCats.includes(newCat)) {
            catSelect.value = newCat;
        }
        const modalEl = document.getElementById('addHabitModal');
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

    // 2. Stepper Button AJAX Handlers ([ - ] and [ + ])
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.btn-stepper');
        if (!btn || btn.disabled) return;
        const stepper = btn.closest('.habit-stepper');
        if (!stepper) return;

        e.preventDefault();
        e.stopPropagation();

        const isInc = btn.classList.contains('btn-stepper-inc');
        const habitId = stepper.dataset.habitId;
        const action = isInc ? 'increment_habit' : 'decrement_habit';
        const row = stepper.closest('.data-row');

        btn.disabled = true;

        const formData = new FormData();
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        if (csrfInput) {
            formData.append('csrf_token', csrfInput.value);
        }
        formData.append(action, '1');
        formData.append('habit_id', habitId);

        try {
            const res = await fetch('habits.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                if (window.showToast) {
                    window.showToast('Perhatian', data.message || 'Gagal mengubah progres.', 'fa-exclamation-triangle', 'warning');
                }
                btn.disabled = false;
                return;
            }

            // Update stepper attributes & display
            stepper.dataset.current = data.current_count;
            const display = stepper.querySelector('.stepper-display');
            const textSpan = stepper.querySelector('.stepper-text');
            const fillBar = stepper.querySelector('.stepper-progress-fill');
            const btnDec = stepper.querySelector('.btn-stepper-dec');
            const btnInc = stepper.querySelector('.btn-stepper-inc');
            const iconStatus = row ? row.querySelector('.habit-icon-status i') : null;

            const cur = parseInt(data.current_count) || 0;
            const tgt = parseInt(data.target_count) || 1;
            const isDone = (data.completed_today == 1 || cur >= tgt);

            if (textSpan) {
                if (isDone) {
                    textSpan.innerHTML = `<i class="fas fa-check text-accent me-1"></i>${cur}/${tgt}`;
                } else {
                    textSpan.textContent = `${cur}/${tgt}`;
                }
            }
            if (display) {
                if (isDone) {
                    display.classList.add('completed');
                } else {
                    display.classList.remove('completed');
                }
            }
            if (fillBar) {
                fillBar.style.width = Math.min(100, Math.round((cur / tgt) * 100)) + '%';
            }
            if (iconStatus) {
                iconStatus.className = isDone ? 'fas fa-check' : 'fas fa-circle';
                if (iconStatus.parentElement) {
                    iconStatus.parentElement.style.color = isDone ? 'var(--accent)' : 'var(--text-muted)';
                }
            }

            btnDec.disabled = (cur <= 0);
            btnInc.disabled = isDone;

            // Floating feedback & sounds
            const rect = btn.getBoundingClientRect();
            const posX = rect.left + rect.width / 2;
            const posY = rect.top;

            if (window.spawnFloatingReward) {
                window.spawnFloatingReward(posX, posY, `<span class="${isDone ? 'text-accent' : (isInc ? 'text-primary' : 'text-warning')} fw-bold">${data.floating_text}</span>`);
            }

            if (typeof LVSound !== 'undefined') {
                if (isDone) {
                    LVSound.fanfare();
                } else if (isInc) {
                    LVSound.coin();
                } else {
                    LVSound.tick();
                }
            }

            // Update HUD vitals
            if (typeof data.user_gold !== 'undefined') {
                const hGold = document.getElementById('hudGoldVal');
                if (hGold) hGold.textContent = Number(data.user_gold).toLocaleString('id-ID');
            }
            if (typeof data.user_level !== 'undefined') {
                const hLvl = document.getElementById('hudLevelVal');
                if (hLvl) hLvl.textContent = 'Lv.' + data.user_level;
            }

        } catch (err) {
            console.error('Stepper error:', err);
            btn.disabled = false;
        }
    });
});
</script>

<?php require_once("../includes/footer.php"); ?>