




<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Fungsi untuk reset habits berdasarkan reset_period
function resetHabits($pdo, $userId) {
    $stmt = $pdo->prepare("
        UPDATE habits
        SET completed_today = 0, 
            last_reset = NOW()
        WHERE user_id = :user_id
        AND (
            (reset_period = 'daily' AND DATE(last_reset) < CURDATE()) OR
            (reset_period = 'weekly' AND WEEK(last_reset, 1) < WEEK(CURDATE(), 1)) OR
            (reset_period = 'monthly' AND MONTH(last_reset) < MONTH(CURDATE()))
        )
    ");
    $stmt->execute([':user_id' => $userId]);
}

// Fungsi untuk menambahkan habit
function addHabit($pdo, $userId, $habitName, $difficulty, $resetPeriod, $category) {
    $expReward = match ($difficulty) {
        'Easy' => 5,
        'Medium' => 10,
        'Hard' => 15,
        default => 0,
    };

    $stmt = $pdo->prepare("
    INSERT INTO habits (user_id, habit_name, difficulty, exp_reward, reset_period, category, completed_today, last_reset)
    VALUES (:user_id, :habit_name, :difficulty, :exp_reward, :reset_period, :category, 0, NOW())
    ");

    // Eksekusi query untuk memasukkan habit baru ke dalam database
    $stmt->execute([
        ':user_id' => $userId,
        ':habit_name' => $habitName,
        ':difficulty' => $difficulty,
        ':exp_reward' => $expReward,
        ':reset_period' => $resetPeriod,
        ':category' => $category
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

        if (!$habit['completed_today']) {
            $pdo->beginTransaction();

            // Tambah Streak
            $newStreak = $habit['streak'] + 1;
            
            // Hitung Multiplier berdasarkan Streak
            $multiplier = 1.0;
            if ($newStreak >= 7) {
                $multiplier = 1.5;
            } elseif ($newStreak >= 3) {
                $multiplier = 1.2;
            }
            
            $finalExp = floor($habit['exp_reward'] * $multiplier);
            $earnedGold = $finalExp; // Gold yang didapat sama dengan EXP akhir

            // Tandai habit sebagai completed dan update streak
            $pdo->prepare("UPDATE habits SET completed_today = 1, streak = ? WHERE habit_id = ? AND user_id = ?")
                ->execute([$newStreak, $habitId, $userId]);

            // Tambahkan EXP dan Gold ke pengguna
            $pdo->prepare("UPDATE users SET exp = exp + ?, gold = gold + ? WHERE user_id = ?")
                ->execute([$finalExp, $earnedGold, $userId]);

            // Set Session untuk Dopamine Toast & Confetti
            $_SESSION['reward_toast'] = [
                'type' => 'habit',
                'name' => $habit['habit_name'],
                'exp' => $finalExp,
                'gold' => $earnedGold,
                'streak' => $newStreak,
                'multiplier' => $multiplier
            ];

            // Tentukan skill_id berdasarkan kategori habit
            $categoryToSkillId = [
                'Wealth' => 1,
                'Intelligence' => 2,
                'Physical' => 3,
                'Health' => 4,
                'Social' => 5
            ];
            $skillId = $categoryToSkillId[$habit['category']] ?? null;

            // Tambahkan progres skill jika kategori valid
            if ($skillId !== null) {
                $stmt = $pdo->prepare("
                    INSERT INTO skills (user_id, skill_id, progress) 
                    VALUES (?, ?, 1) 
                    ON DUPLICATE KEY UPDATE progress = progress + 1
                ");
                $stmt->execute([$userId, $skillId]);
            }

            // Catat ke habit_logs dengan kolom exp_reward (simpan EXP final setelah multiplier)
            $pdo->prepare("
                INSERT INTO habit_logs (habit_id, user_id, completion_date, status, exp_reward)
                VALUES (?, ?, NOW(), 'Completed', ?)
            ")->execute([$habitId, $userId, $finalExp]);

            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("completeHabit error: " . $e->getMessage());
    }
}



// Fungsi untuk menghapus habit
function deleteHabit($pdo, $userId, $habitId) {
    $pdo->prepare("DELETE FROM habit_logs WHERE habit_id = ?")->execute([$habitId]);
    $pdo->prepare("DELETE FROM habits WHERE habit_id = ? AND user_id = ?")
        ->execute([$habitId, $userId]);
}

// Fungsi untuk update habit
function updateHabit($pdo, $userId, $habitId, $habitName, $difficulty, $resetPeriod, $category) {
    $expReward = match ($difficulty) {
        'Easy' => 5,
        'Medium' => 10,
        'Hard' => 15,
        default => 5,
    };
    $stmt = $pdo->prepare("
        UPDATE habits 
        SET habit_name = ?, difficulty = ?, reset_period = ?, category = ?, exp_reward = ? 
        WHERE habit_id = ? AND user_id = ?
    ");
    $stmt->execute([$habitName, $difficulty, $resetPeriod, $category, $expReward, $habitId, $userId]);
}

// Reset habits
resetHabits($pdo, $userId);

// Proses permintaan POST (dengan validasi CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    if (isset($_POST['edit_habit'])) {
        $habitId = intval($_POST['habit_id']);
        updateHabit($pdo, $userId, $habitId, $_POST['habit_name'], $_POST['difficulty'], $_POST['reset_period'], $_POST['category']);
        header("Location: habits.php");
        exit();
    }

    if (isset($_POST['add_habit']) || (!empty($_POST['habit_name']) && !isset($_POST['edit_habit']))) {
        addHabit($pdo, $userId, $_POST['habit_name'], $_POST['difficulty'], $_POST['reset_period'], $_POST['category']);
        header("Location: habits.php");
        exit();
    }

    if (isset($_POST['complete_habit']) && !empty($_POST['habit_id'])) {
        completeHabit($pdo, $userId, intval($_POST['habit_id']));
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

// Muat header
require_once '../includes/header.php';
?>


<!-- Modal Add Habit -->
<div class="modal fade" id="addHabitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addHabitForm" method="POST" action="habits.php">
                <?php echo csrf_field(); ?>
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
                            <option value="Intelligence">Intelligence</option>
                            <option value="Physical">Physical</option>
                            <option value="Wealth">Wealth</option>
                            <option value="Health">Health</option>
                            <option value="Social">Social</option>
                        </select>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-6">
                            <label for="difficulty" class="form-label">Tingkat kesulitan</label>
                            <select class="form-select" id="difficulty" name="difficulty" required>
                                <option value="Easy">Mudah (+5 EXP)</option>
                                <option value="Medium">Sedang (+10 EXP)</option>
                                <option value="Hard">Sulit (+15 EXP)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="resetPeriod" class="form-label">Frekuensi reset</label>
                            <select class="form-select" id="resetPeriod" name="reset_period" required>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-core btn-ghost" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-core btn-primary">Buat Rutinitas</button>
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

<div class="bento-panel">
    <?php if (empty($habits)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-seedling fa-2x mb-3" style="opacity: 0.3;"></i>
            <p class="mb-0">Belum ada rutinitas aktif. Buat satu untuk mulai naik level.</p>
        </div>
    <?php else: ?>
        <div class="data-list">
            <?php foreach ($habits as $habit): ?>
                <div class="data-row">
                    <div class="data-main">
                        <div style="width: 30px; height: 30px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; color: <?php echo $habit['completed_today'] ? 'var(--accent)' : 'var(--text-muted)'; ?>; font-size: 0.8rem;">
                            <i class="fas <?php echo $habit['completed_today'] ? 'fa-check' : 'fa-circle'; ?>"></i>
                        </div>
                        <div>
                            <div class="data-title d-flex align-items-center flex-wrap gap-2">
                                <span><?php echo htmlspecialchars($habit['habit_name']); ?></span>
                                <?php 
                                    $streak = $habit['streak'] ?? 0;
                                    if ($streak > 0): 
                                        $streakClass = 'badge-status warning';
                                        $boostText = '';
                                        if ($streak >= 7) {
                                            $boostText = ' • 1.5x';
                                        } elseif ($streak >= 3) {
                                            $boostText = ' • 1.2x';
                                        }
                                ?>
                                    <span class="<?php echo $streakClass; ?>">
                                        <i class="fas fa-fire"></i> <?php echo $streak; ?>h<?php echo $boostText; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="data-meta">
                                <span class="badge-status"><?php echo htmlspecialchars($habit['category']); ?></span>
                                <span class="badge-status font-mono"><?php echo htmlspecialchars($habit['difficulty']); ?></span>
                                <span class="font-mono"><?php echo htmlspecialchars(ucfirst($habit['reset_period'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="data-actions">
                        <div class="font-mono text-accent me-2" style="font-size: 0.82rem; font-weight: 500;">
                            +<?php echo $habit['exp_reward']; ?> EXP
                        </div>
                        
                        <?php if ($habit['completed_today']): ?>
                            <span class="badge-status accent"><i class="fas fa-check"></i> Tuntas</span>
                        <?php else: ?>
                            <form method="POST" style="margin: 0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="habit_id" value="<?php echo $habit['habit_id']; ?>">
                                <button type="submit" name="complete_habit" class="btn-core btn-accent" style="padding: 0.35rem 0.75rem; font-size: 0.78rem;">
                                    Selesaikan
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Edit Button -->
                        <button type="button" class="btn-core btn-ghost" style="padding: 0.35rem 0.55rem;" title="Ubah" data-bs-toggle="modal" data-bs-target="#editHabitModal<?php echo $habit['habit_id']; ?>">
                            <i class="fas fa-pencil-alt"></i>
                        </button>

                        <form method="POST" style="margin: 0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="habit_id" value="<?php echo $habit['habit_id']; ?>">
                            <button type="submit" name="delete_habit" class="btn-core btn-ghost" style="padding: 0.35rem 0.55rem;" title="Hapus">
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
                                        <label class="form-label">Nama kebiasaan</label>
                                        <input type="text" class="form-control" name="habit_name" value="<?php echo htmlspecialchars($habit['habit_name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Kategori / Atribut</label>
                                        <select class="form-select" name="category" required>
                                            <option value="Intelligence" <?php echo $habit['category'] == 'Intelligence' ? 'selected' : ''; ?>>Intelligence</option>
                                            <option value="Physical" <?php echo $habit['category'] == 'Physical' ? 'selected' : ''; ?>>Physical</option>
                                            <option value="Wealth" <?php echo $habit['category'] == 'Wealth' ? 'selected' : ''; ?>>Wealth</option>
                                            <option value="Health" <?php echo $habit['category'] == 'Health' ? 'selected' : ''; ?>>Health</option>
                                            <option value="Social" <?php echo $habit['category'] == 'Social' ? 'selected' : ''; ?>>Social</option>
                                        </select>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label">Tingkat kesulitan</label>
                                            <select class="form-select" name="difficulty" required>
                                                <option value="Easy" <?php echo $habit['difficulty'] == 'Easy' ? 'selected' : ''; ?>>Mudah (+5 EXP)</option>
                                                <option value="Medium" <?php echo $habit['difficulty'] == 'Medium' ? 'selected' : ''; ?>>Sedang (+10 EXP)</option>
                                                <option value="Hard" <?php echo $habit['difficulty'] == 'Hard' ? 'selected' : ''; ?>>Sulit (+15 EXP)</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Frekuensi reset</label>
                                            <select class="form-select" name="reset_period" required>
                                                <option value="daily" <?php echo strtolower($habit['reset_period']) == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                                <option value="weekly" <?php echo strtolower($habit['reset_period']) == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                                <option value="monthly" <?php echo strtolower($habit['reset_period']) == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn-core btn-ghost" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn-core btn-primary">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once("../includes/footer.php"); ?>