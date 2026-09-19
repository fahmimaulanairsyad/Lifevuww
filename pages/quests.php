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

// Fetch quests with 'In Progress' status
try {
    $stmt = $pdo->prepare("SELECT * FROM quests WHERE user_id = ? AND status = 'In Progress'");
    $stmt->execute([$_SESSION['user_id']]);
    $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching quests: " . $e->getMessage());
    die("Error loading quests. Please try again later.");
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    // Edit Quest
    if (isset($_POST['edit_quest'])) {
        $questId = intval($_POST['quest_id']);
        $quest_name = $_POST['quest_name'];
        $difficulty = $_POST['difficulty'];
        $start_date = $_POST['start_date'];
        $category = $_POST['category'];

        $expReward = match ($difficulty) {
            'Easy' => 5,
            'Medium' => 10,
            'Hard' => 15,
            default => 5,
        };

        $durationDays = match ($difficulty) {
            'Easy' => 7,
            'Medium' => 14,
            'Hard' => 21,
            default => 7,
        };

        try {
            $stmt = $pdo->prepare("
                UPDATE quests 
                SET quest_name = ?, difficulty = ?, start_date = ?, exp_reward = ?, duration_days = ?, category = ? 
                WHERE quest_id = ? AND user_id = ?
            ");
            $stmt->execute([$quest_name, $difficulty, $start_date, $expReward, $durationDays, $category, $questId, $_SESSION['user_id']]);
            header("Location: quests.php");
            exit();
        } catch (PDOException $e) {
            error_log("Quest update error: " . $e->getMessage());
            die("Error updating quest.");
        }
    }

    if (isset($_POST['complete_quest'])) {
        $questId = $_POST['quest_id'] ?? null;
    
        if ($questId) {
            try {
                // Ambil detail quest
                $stmt = $pdo->prepare("SELECT exp_reward, category FROM quests WHERE quest_id = ? AND user_id = ?");
                $stmt->execute([$questId, $_SESSION['user_id']]);
                $quest = $stmt->fetch(PDO::FETCH_ASSOC);
    
                if (!$quest) {
                    throw new Exception("Quest tidak ditemukan atau tidak milik pengguna.");
                }
    
                $pdo->beginTransaction();

                // Update status quest menjadi 'Completed'
                $stmt = $pdo->prepare("UPDATE quests SET status = 'Completed' WHERE quest_id = ? AND user_id = ?");
                $stmt->execute([$questId, $_SESSION['user_id']]);
    
                // Tambahkan EXP dan Gold ke pengguna
                $expReward = $quest['exp_reward'];
                $goldReward = $expReward * 2; // Quest memberikan Gold lebih banyak (x2)
                $stmt = $pdo->prepare("UPDATE users SET exp = exp + ?, gold = gold + ? WHERE user_id = ?");
                $stmt->execute([$expReward, $goldReward, $_SESSION['user_id']]);

                // Set Session untuk Dopamine Toast & Confetti
                $_SESSION['reward_toast'] = [
                    'type' => 'quest',
                    'name' => $quest['quest_name'],
                    'exp' => $expReward,
                    'gold' => $goldReward
                ];
    
                // Ambil skill_id berdasarkan kategori
                $categoryToSkillId = [
                    'Wealth' => 1,
                    'Intelligence' => 2,
                    'Physical' => 3,
                    'Health' => 4,
                    'Social' => 5
                ];
                $skillId = $categoryToSkillId[$quest['category']] ?? null;
    
                if ($skillId) {
                    // Update progres skill (insert jika belum ada, update jika sudah ada)
                    $stmt = $pdo->prepare("
                        INSERT INTO skills (user_id, skill_id, progress) 
                        VALUES (?, ?, 1) 
                        ON DUPLICATE KEY UPDATE progress = progress + 1
                    ");
                    $stmt->execute([$_SESSION['user_id'], $skillId]);
                }
    
                // Log quest yang selesai, termasuk exp_reward
                $stmt = $pdo->prepare("INSERT INTO quest_logs (quest_id, user_id, skill_id, status, completed_at, exp_reward) 
                                       VALUES (?, ?, ?, 'Completed', NOW(), ?)");
                $stmt->execute([$questId, $_SESSION['user_id'], $skillId, $expReward]);
    
                $pdo->commit();

                // Redirect ke halaman quests.php
                header("Location: quests.php");
                exit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Quest completion error: " . $e->getMessage());
                die("Error completing quest. Please try again.");
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                die(htmlspecialchars($e->getMessage()));
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

    if (isset($_POST['quest_name'])) {
        $quest_name = $_POST['quest_name'];
        $difficulty = $_POST['difficulty'];
        $start_date = $_POST['start_date'];
        $category = $_POST['category'];

        $expReward = match ($difficulty) {
            'Easy' => 5,
            'Medium' => 10,
            'Hard' => 15,
            default => 0,
        };

        $durationDays = match ($difficulty) {
            'Easy' => 7,
            'Medium' => 14,
            'Hard' => 21,
            default => 7,
        };

        try {
            if ($expReward === 0) {
                throw new Exception("Invalid difficulty level, EXP reward cannot be zero.");
            }

            $stmt = $pdo->prepare("INSERT INTO quests (user_id, quest_name, difficulty, start_date, exp_reward, duration_days, status, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $quest_name, $difficulty, $start_date, $expReward, $durationDays, 'In Progress', $category]);

            header("Location: quests.php");
            exit();
        } catch (PDOException $e) {
            error_log("Quest add error: " . $e->getMessage());
            die("Error adding quest. Please try again.");
        }
    }
}
?>



<!-- Modal Add Quest -->
<div class="modal fade" id="addQuestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addQuestForm" method="POST" action="quests.php">
                <?php echo csrf_field(); ?>
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
                            <label for="startDate" class="form-label">Tanggal mulai</label>
                            <input type="date" class="form-control" id="startDate" name="start_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-core btn-ghost" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-core btn-primary">Mulai Misi</button>
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

<div class="bento-panel">
    <?php if (empty($quests)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-map-marked-alt fa-2x mb-3" style="opacity: 0.3;"></i>
            <p class="mb-0">Belum ada misi berjalan. Mulai petualangan baru!</p>
        </div>
    <?php else: ?>
        <div class="data-list">
            <?php foreach ($quests as $quest): ?>
                <div class="data-row">
                    <div class="data-main">
                        <div style="width: 30px; height: 30px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; color: <?php echo $quest['status'] === 'Completed' ? 'var(--accent)' : 'var(--text-muted)'; ?>; font-size: 0.8rem;">
                            <i class="fas <?php echo $quest['status'] === 'Completed' ? 'fa-check' : 'fa-scroll'; ?>"></i>
                        </div>
                        <div>
                            <div class="data-title"><?php echo htmlspecialchars($quest['quest_name']); ?></div>
                            <div class="data-meta">
                                <span class="badge-status"><?php echo htmlspecialchars($quest['category']); ?></span>
                                <span class="badge-status font-mono"><?php echo htmlspecialchars($quest['difficulty']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="data-actions">
                        <div class="font-mono text-accent me-2" style="font-size: 0.82rem; font-weight: 500;">
                            +<?php echo $quest['exp_reward']; ?> EXP
                        </div>
                        
                        <?php if ($quest['status'] === 'Completed'): ?>
                            <span class="badge-status accent"><i class="fas fa-check"></i> Tuntas</span>
                        <?php else: ?>
                            <form method="POST" action="quests.php" style="margin: 0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="quest_id" value="<?php echo $quest['quest_id']; ?>">
                                <button type="submit" name="complete_quest" class="btn-core btn-accent" style="padding: 0.35rem 0.75rem; font-size: 0.78rem;">
                                    Selesaikan
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Edit Button -->
                        <button type="button" class="btn-core btn-ghost" style="padding: 0.35rem 0.55rem;" title="Ubah" data-bs-toggle="modal" data-bs-target="#editQuestModal<?php echo $quest['quest_id']; ?>">
                            <i class="fas fa-pencil-alt"></i>
                        </button>

                        <form method="POST" action="quests.php" style="margin: 0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="quest_id" value="<?php echo $quest['quest_id']; ?>">
                            <button type="submit" name="delete_quest" class="btn-core btn-ghost" style="padding: 0.35rem 0.55rem;" title="Hapus">
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
                                        <label class="form-label">Tujuan misi</label>
                                        <input type="text" class="form-control" name="quest_name" value="<?php echo htmlspecialchars($quest['quest_name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Kategori / Atribut</label>
                                        <select class="form-select" name="category" required>
                                            <option value="Intelligence" <?php echo $quest['category'] == 'Intelligence' ? 'selected' : ''; ?>>Intelligence</option>
                                            <option value="Physical" <?php echo $quest['category'] == 'Physical' ? 'selected' : ''; ?>>Physical</option>
                                            <option value="Wealth" <?php echo $quest['category'] == 'Wealth' ? 'selected' : ''; ?>>Wealth</option>
                                            <option value="Health" <?php echo $quest['category'] == 'Health' ? 'selected' : ''; ?>>Health</option>
                                            <option value="Social" <?php echo $quest['category'] == 'Social' ? 'selected' : ''; ?>>Social</option>
                                        </select>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label">Tingkat kesulitan</label>
                                            <select class="form-select" name="difficulty" required>
                                                <option value="Easy" <?php echo $quest['difficulty'] == 'Easy' ? 'selected' : ''; ?>>Mudah (+5 EXP)</option>
                                                <option value="Medium" <?php echo $quest['difficulty'] == 'Medium' ? 'selected' : ''; ?>>Sedang (+10 EXP)</option>
                                                <option value="Hard" <?php echo $quest['difficulty'] == 'Hard' ? 'selected' : ''; ?>>Sulit (+15 EXP)</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Tanggal mulai</label>
                                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($quest['start_date']); ?>" required>
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