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

// Ambil data user untuk balance Gold
$stmt = $pdo->prepare("SELECT gold FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$userGold = $stmt->fetchColumn() ?: 0;

// POST Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    // Beli Health Potion (Recovery)
    if (isset($_POST['buy_potion'])) {
        $potionType = $_POST['potion_type'];
        $cost = ($potionType === 'full') ? 75 : 25;
        $healAmount = ($potionType === 'full') ? 100 : 25;
        
        if ($userGold >= $cost) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET gold = gold - ?, hp = LEAST(max_hp, hp + ?) WHERE user_id = ?")
                    ->execute([$cost, $healAmount, $userId]);
                $pdo->commit();
                header("Location: shop.php?success=healed");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Transaksi gagal.";
            }
        } else {
            $error = "Gold tidak cukup untuk ramuan ini!";
        }
    }

    // Beli Streak Freeze
    if (isset($_POST['buy_freeze'])) {
        $cost = 50;
        if ($userGold >= $cost) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET gold = gold - ?, streak_freeze = streak_freeze + 1 WHERE user_id = ?")
                    ->execute([$cost, $userId]);
                $pdo->commit();
                header("Location: shop.php?success=frozen");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Transaksi gagal.";
            }
        } else {
            $error = "Gold tidak cukup untuk Perisai Streak Freeze!";
        }
    }

    // Tambah Reward
    if (isset($_POST['add_reward'])) {
        $name = $_POST['reward_name'];
        $cost = intval($_POST['cost']);
        $icon = $_POST['icon'];
        
        $stmt = $pdo->prepare("INSERT INTO rewards (user_id, reward_name, cost, icon) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $name, $cost, $icon]);
        header("Location: shop.php");
        exit();
    }
    
    // Beli Reward
    if (isset($_POST['buy_reward'])) {
        $rewardId = intval($_POST['reward_id']);
        
        $stmt = $pdo->prepare("SELECT cost FROM rewards WHERE reward_id = ? AND user_id = ?");
        $stmt->execute([$rewardId, $userId]);
        $reward = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reward && $userGold >= $reward['cost']) {
            $pdo->beginTransaction();
            try {
                // Kurangi gold
                $pdo->prepare("UPDATE users SET gold = gold - ? WHERE user_id = ?")->execute([$reward['cost'], $userId]);
                // Catat log pembelian
                $pdo->prepare("INSERT INTO reward_logs (user_id, reward_id, cost) VALUES (?, ?, ?)")->execute([$userId, $rewardId, $reward['cost']]);
                $pdo->commit();
                header("Location: shop.php?success=bought");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Transaksi gagal.";
            }
        } else {
            $error = "Gold tidak cukup!";
        }
    }
    
    // Hapus Reward
    if (isset($_POST['delete_reward'])) {
        $rewardId = intval($_POST['reward_id']);
        $pdo->prepare("DELETE FROM rewards WHERE reward_id = ? AND user_id = ?")->execute([$rewardId, $userId]);
        header("Location: shop.php");
        exit();
    }
}

// Ambil data rewards
$stmt = $pdo->prepare("SELECT * FROM rewards WHERE user_id = ? ORDER BY cost ASC");
$stmt->execute([$userId]);
$rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="page-header d-flex justify-content-between align-items-end">
    <div>
        <h1 class="page-title">Toko Hadiah</h1>
        <p class="page-subtitle">Tukarkan gold untuk pemulihan vital, perisai streak, dan hadiah pribadi.</p>
    </div>
    <div class="text-end">
        <div class="text-muted" style="font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;">Saldo Gold</div>
        <div style="font-size: 1.8rem; font-weight: 600; color: var(--status-warning); font-family: var(--font-mono); line-height: 1;">
            <i class="fas fa-coins me-1" style="font-size: 1.3rem;"></i><?php echo number_format($userGold); ?>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="p-3 mb-4 d-flex align-items-center gap-2" style="background-color: var(--status-danger-dim); border: 1px solid rgba(244, 63, 94, 0.3); color: var(--status-danger); border-radius: var(--radius); font-size: 0.85rem;">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>
<?php if (isset($_GET['success']) && $_GET['success'] == 'bought'): ?>
    <div class="p-3 mb-4 d-flex align-items-center gap-2" style="background-color: var(--accent-dim); border: 1px solid var(--accent-border); color: var(--accent); border-radius: var(--radius); font-size: 0.85rem;">
        <i class="fas fa-check-circle"></i> Hadiah berhasil ditebus! Selamat menikmati.
    </div>
<?php endif; ?>
<?php if (isset($_GET['success']) && $_GET['success'] == 'healed'): ?>
    <div class="p-3 mb-4 d-flex align-items-center gap-2" style="background-color: var(--status-danger-dim); border: 1px solid rgba(244, 63, 94, 0.3); color: var(--status-danger); border-radius: var(--radius); font-size: 0.85rem;">
        <i class="fas fa-heart"></i> HP berhasil dipulihkan! Karaktermu kembali bugar.
    </div>
<?php endif; ?>
<?php if (isset($_GET['success']) && $_GET['success'] == 'frozen'): ?>
    <div class="p-3 mb-4 d-flex align-items-center gap-2" style="background-color: var(--status-info-dim); border: 1px solid rgba(6, 182, 212, 0.3); color: var(--status-info); border-radius: var(--radius); font-size: 0.85rem;">
        <i class="fas fa-snowflake"></i> Perisai Streak Freeze didapat! Streak-mu aman 1 hari.
    </div>
<?php endif; ?>

<!-- Section: Consumables & Recovery -->
<div class="mb-5">
    <div class="panel-header" style="border-bottom: 1px solid var(--border-hairline); padding-bottom: 0.6rem; margin-bottom: 1rem;">
        <h3 class="panel-title" style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted);">
            <i class="fas fa-prescription-bottle text-danger"></i> Item Sistem
        </h3>
    </div>
    
    <div class="row g-3">
        <!-- Minor Potion -->
        <div class="col-md-4">
            <div class="bento-panel d-flex flex-column justify-content-between p-3 h-100">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div style="width: 36px; height: 36px; border-radius: var(--radius); background-color: var(--status-danger-dim); border: 1px solid rgba(244, 63, 94, 0.2); color: var(--status-danger); display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                        <i class="fas fa-prescription-bottle"></i>
                    </div>
                    <div>
                        <div style="font-weight: 500; font-size: 0.88rem; color: var(--text-primary);">Ramuan HP Minor</div>
                        <div class="text-muted" style="font-size: 0.75rem;">Memulihkan +25 HP seketika</div>
                    </div>
                </div>
                <form method="POST" action="shop.php" class="m-0">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="potion_type" value="minor">
                    <button type="submit" name="buy_potion" class="btn-core btn-outline w-100 font-mono" <?php echo $userGold < 25 ? 'disabled' : ''; ?>>
                        <i class="fas fa-coins text-warning me-1"></i> 25 Gold
                    </button>
                </form>
            </div>
        </div>

        <!-- Full Elixir -->
        <div class="col-md-4">
            <div class="bento-panel d-flex flex-column justify-content-between p-3 h-100">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div style="width: 36px; height: 36px; border-radius: var(--radius); background-color: var(--status-danger-dim); border: 1px solid rgba(244, 63, 94, 0.2); color: var(--status-danger); display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                        <i class="fas fa-flask"></i>
                    </div>
                    <div>
                        <div style="font-weight: 500; font-size: 0.88rem; color: var(--text-primary);">Elixir HP Penuh</div>
                        <div class="text-muted" style="font-size: 0.75rem;">Memulihkan 100% HP penuh</div>
                    </div>
                </div>
                <form method="POST" action="shop.php" class="m-0">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="potion_type" value="full">
                    <button type="submit" name="buy_potion" class="btn-core btn-outline w-100 font-mono" <?php echo $userGold < 75 ? 'disabled' : ''; ?>>
                        <i class="fas fa-coins text-warning me-1"></i> 75 Gold
                    </button>
                </form>
            </div>
        </div>

        <!-- Streak Freeze -->
        <div class="col-md-4">
            <div class="bento-panel d-flex flex-column justify-content-between p-3 h-100">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div style="width: 36px; height: 36px; border-radius: var(--radius); background-color: var(--status-info-dim); border: 1px solid rgba(6, 182, 212, 0.2); color: var(--status-info); display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                        <i class="fas fa-snowflake"></i>
                    </div>
                    <div>
                        <div style="font-weight: 500; font-size: 0.88rem; color: var(--text-primary);">Perisai Streak Freeze</div>
                        <div class="text-muted" style="font-size: 0.75rem;">Melindungi streak selama 1 hari</div>
                    </div>
                </div>
                <form method="POST" action="shop.php" class="m-0">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="buy_freeze" class="btn-core btn-outline w-100 font-mono" <?php echo $userGold < 50 ? 'disabled' : ''; ?>>
                        <i class="fas fa-coins text-warning me-1"></i> 50 Gold
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Section: Custom Rewards -->
<div>
    <div class="panel-header" style="border-bottom: 1px solid var(--border-hairline); padding-bottom: 0.6rem; margin-bottom: 1rem;">
        <h3 class="panel-title" style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted);">
            <i class="fas fa-gift text-warning"></i> Hadiah Dunia Nyata & Insentif
        </h3>
    </div>

    <div class="row g-3">
        <?php foreach ($rewards as $reward): ?>
            <div class="col-md-4">
                <div class="bento-panel text-center h-100 position-relative p-4 d-flex flex-column justify-content-between" style="<?php echo $userGold < $reward['cost'] ? 'opacity: 0.55;' : ''; ?>">
                    <form method="POST" action="shop.php" style="position: absolute; top: 0.75rem; right: 0.75rem;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="reward_id" value="<?php echo $reward['reward_id']; ?>">
                        <button type="submit" name="delete_reward" class="btn-core btn-ghost p-1" title="Hapus hadiah">
                            <i class="fas fa-times" style="font-size: 0.75rem;"></i>
                        </button>
                    </form>
                    
                    <div>
                        <div style="width: 44px; height: 44px; border-radius: var(--radius); background-color: var(--status-warning-dim); border: 1px solid rgba(245, 158, 11, 0.2); color: var(--status-warning); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin: 0.5rem auto 1rem auto;">
                            <i class="fas <?php echo htmlspecialchars($reward['icon']); ?>"></i>
                        </div>
                        <h3 style="font-size: 0.95rem; font-weight: 500; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($reward['reward_name']); ?></h3>
                    </div>
                    
                    <div class="mt-4">
                        <form method="POST" action="shop.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="reward_id" value="<?php echo $reward['reward_id']; ?>">
                            <button type="submit" name="buy_reward" class="btn-core btn-outline w-100 font-mono" <?php echo $userGold < $reward['cost'] ? 'disabled' : ''; ?>>
                                <i class="fas fa-coins text-warning mx-1"></i> <?php echo number_format($reward['cost']); ?> Gold
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Create New Reward Slot -->
        <div class="col-md-4">
            <div class="bento-panel h-100 d-flex flex-column align-items-center justify-content-center p-4 text-center" style="border-style: dashed; cursor: pointer; min-height: 180px;" data-bs-toggle="modal" data-bs-target="#addRewardModal">
                <div style="width: 36px; height: 36px; border-radius: var(--radius); background-color: var(--bg-surface-hover); color: var(--text-muted); display: flex; align-items: center; justify-content: center; font-size: 1rem; margin-bottom: 0.75rem;">
                    <i class="fas fa-plus"></i>
                </div>
                <div style="font-size: 0.85rem; font-weight: 500; color: var(--text-secondary);">Buat Hadiah</div>
                <div class="text-muted" style="font-size: 0.72rem;">Tentukan hadiah dunia nyatamu sendiri</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Reward -->
<div class="modal fade" id="addRewardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="shop.php">
                <?php echo csrf_field(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" style="font-size: 1rem; font-weight: 600;"><i class="fas fa-gift me-2 text-warning"></i> Buat Hadiah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama hadiah</label>
                        <input type="text" class="form-control" name="reward_name" placeholder="mis., Beli kopi, Main game 2 jam" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Harga (Gold)</label>
                            <input type="number" class="form-control font-mono" name="cost" min="1" placeholder="100" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Ikon</label>
                            <select class="form-select" name="icon">
                                <option value="fa-gift">Kado</option>
                                <option value="fa-coffee">Kopi</option>
                                <option value="fa-gamepad">Game</option>
                                <option value="fa-pizza-slice">Makanan</option>
                                <option value="fa-ticket-alt">Hiburan</option>
                                <option value="fa-shopping-cart">Belanja</option>
                                <option value="fa-bed">Istirahat</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-core btn-ghost" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_reward" class="btn-core btn-primary">Buat Hadiah</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once("../includes/footer.php"); ?>