<?php
// pages/shop.php
require_once __DIR__ . '/../config/database.php';
enforce_login();

$userId = $_SESSION['user_id'];

// POST Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    function shop_json_response($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit();
    }

    // Beli Health Potion (Recovery) — atomic: guard gold di WHERE + cek rowCount
    if (isset($_POST['buy_potion'])) {
        $potionType = $_POST['potion_type'] ?? '';
        $potList = game_potions();
        if (!isset($potList[$potionType]) || !isset($potList[$potionType]['heal'])) {
            $error = "Jenis ramuan tidak valid!";
            if ($isAjax) shop_json_response(['success' => false, 'message' => $error], 400);
        } else {
            $cost = $potList[$potionType]['cost'];
            $healAmount = $potList[$potionType]['heal'];

            // Cek apakah HP sudah penuh
            $curHp = $pdo->prepare("SELECT hp, max_hp, gold, streak_freeze FROM users WHERE user_id = ?");
            $curHp->execute([$userId]);
            $userHpRow = $curHp->fetch(PDO::FETCH_ASSOC);

            if ($userHpRow && ($userHpRow['hp'] >= $userHpRow['max_hp'])) {
                $error = "HP kamu sudah penuh, ramuan tidak diperlukan!";
                if ($isAjax) shop_json_response([
                    'success' => false,
                    'message' => $error,
                    'hp' => (int)$userHpRow['hp'],
                    'max_hp' => (int)$userHpRow['max_hp'],
                    'gold' => (int)$userHpRow['gold'],
                    'streak_freeze' => (int)$userHpRow['streak_freeze']
                ], 400);
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE users SET gold = gold - ?, hp = LEAST(max_hp, hp + ?) WHERE user_id = ? AND gold >= ?");
                    $stmt->execute([$cost, $healAmount, $userId, $cost]);
                    if ($stmt->rowCount() === 0) {
                        $pdo->rollBack();
                        $error = "Gold tidak cukup untuk ramuan ini!";
                        if ($isAjax) shop_json_response(['success' => false, 'message' => $error], 400);
                    } else {
                        $pdo->commit();
                        if ($isAjax) {
                            $st = $pdo->prepare("SELECT hp, max_hp, gold, streak_freeze FROM users WHERE user_id = ?");
                            $st->execute([$userId]);
                            $updated = $st->fetch(PDO::FETCH_ASSOC);
                            $healedGain = max(0, (int)$updated['hp'] - (int)$userHpRow['hp']);
                            shop_json_response([
                                'success' => true,
                                'action' => 'buy_potion',
                                'potion_type' => $potionType,
                                'cost' => $cost,
                                'heal' => $healedGain,
                                'floating_text' => "<i class='fas fa-heart text-danger'></i> -{$cost} Gold • +{$healedGain} HP",
                                'message' => "HP berhasil dipulihkan (+{$healedGain} HP)! Karaktermu kembali bugar.",
                                'gold' => (int)$updated['gold'],
                                'hp' => (int)$updated['hp'],
                                'max_hp' => (int)$updated['max_hp'],
                                'streak_freeze' => (int)$updated['streak_freeze']
                            ]);
                        }
                        header("Location: shop.php?success=healed");
                        exit();
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Transaksi gagal.";
                    if ($isAjax) shop_json_response(['success' => false, 'message' => $error], 500);
                }
            }
        }
    }

    // Beli Streak Freeze — atomic
    if (isset($_POST['buy_freeze'])) {
        $potList = game_potions();
        $cost = $potList['freeze']['cost'] ?? 50;
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET gold = gold - ?, streak_freeze = streak_freeze + 1 WHERE user_id = ? AND gold >= ?");
            $stmt->execute([$cost, $userId, $cost]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                $error = "Gold tidak cukup untuk Perisai Streak Freeze!";
                if ($isAjax) shop_json_response(['success' => false, 'message' => $error], 400);
            } else {
                $pdo->commit();
                if ($isAjax) {
                    $st = $pdo->prepare("SELECT hp, max_hp, gold, streak_freeze FROM users WHERE user_id = ?");
                    $st->execute([$userId]);
                    $updated = $st->fetch(PDO::FETCH_ASSOC);
                    shop_json_response([
                        'success' => true,
                        'action' => 'buy_freeze',
                        'cost' => $cost,
                        'floating_text' => "<i class='fas fa-snowflake text-info'></i> -{$cost} Gold • +1 🛡 Shield",
                        'message' => "Perisai Streak Freeze didapat! Streak-mu aman 1 hari.",
                        'gold' => (int)$updated['gold'],
                        'hp' => (int)$updated['hp'],
                        'max_hp' => (int)$updated['max_hp'],
                        'streak_freeze' => (int)$updated['streak_freeze']
                    ]);
                }
                header("Location: shop.php?success=frozen");
                exit();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Transaksi gagal.";
            if ($isAjax) shop_json_response(['success' => false, 'message' => $error], 500);
        }
    }

    // Tambah Reward — harga minimal 1 Gold, maksimal 99.999 Gold
    if (isset($_POST['add_reward'])) {
        $name = trim($_POST['reward_name'] ?? '');
        $cost = min(99999, max(1, intval($_POST['cost'] ?? 0)));
        $icon = $_POST['icon'] ?? 'fa-gift';

        if ($name === '') {
            $error = "Nama hadiah tidak boleh kosong.";
            if ($isAjax) shop_json_response(['success' => false, 'message' => $error], 400);
        } else {
            $stmt = $pdo->prepare("INSERT INTO rewards (user_id, reward_name, cost, icon) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $cost, $icon]);
            header("Location: shop.php");
            exit();
        }
    }

    // Beli Reward — atomic
    if (isset($_POST['buy_reward'])) {
        $rewardId = intval($_POST['reward_id']);

        $stmt = $pdo->prepare("SELECT reward_name, cost, icon FROM rewards WHERE reward_id = ? AND user_id = ?");
        $stmt->execute([$rewardId, $userId]);
        $reward = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reward || $reward['cost'] < 1) {
            $error = "Hadiah tidak valid.";
            if ($isAjax) shop_json_response(['success' => false, 'message' => $error], 400);
        } else {
            $pdo->beginTransaction();
            try {
                // Kurangi gold hanya jika saldo cukup (cegah race)
                $stmt = $pdo->prepare("UPDATE users SET gold = gold - ? WHERE user_id = ? AND gold >= ?");
                $stmt->execute([$reward['cost'], $userId, $reward['cost']]);
                if ($stmt->rowCount() === 0) {
                    $pdo->rollBack();
                    $error = "Gold tidak cukup!";
                    if ($isAjax) shop_json_response(['success' => false, 'message' => $error], 400);
                } else {
                    // Catat log pembelian
                    $pdo->prepare("INSERT INTO reward_logs (user_id, reward_id, cost) VALUES (?, ?, ?)")->execute([$userId, $rewardId, $reward['cost']]);
                    $pdo->commit();
                    if ($isAjax) {
                        $st = $pdo->prepare("SELECT hp, max_hp, gold, streak_freeze FROM users WHERE user_id = ?");
                        $st->execute([$userId]);
                        $updated = $st->fetch(PDO::FETCH_ASSOC);
                        shop_json_response([
                            'success' => true,
                            'action' => 'buy_reward',
                            'reward_id' => $rewardId,
                            'reward_name' => $reward['reward_name'],
                            'cost' => (int)$reward['cost'],
                            'icon' => $reward['icon'],
                            'claimed_at' => indo_short_date(date('Y-m-d H:i:s'), true),
                            'floating_text' => "<i class='fas fa-gift text-warning'></i> -{$reward['cost']} Gold • Hadiah Ditebus!",
                            'message' => "Hadiah '{$reward['reward_name']}' berhasil ditebus! (-{$reward['cost']} Gold)",
                            'gold' => (int)$updated['gold'],
                            'hp' => (int)$updated['hp'],
                            'max_hp' => (int)$updated['max_hp'],
                            'streak_freeze' => (int)$updated['streak_freeze']
                        ]);
                    }
                    header("Location: shop.php?success=bought");
                    exit();
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Transaksi gagal.";
                if ($isAjax) shop_json_response(['success' => false, 'message' => $error], 500);
            }
        }
    }
    
    // Hapus Reward
    if (isset($_POST['delete_reward'])) {
        $rewardId = intval($_POST['reward_id']);
        $pdo->prepare("DELETE FROM rewards WHERE reward_id = ? AND user_id = ?")->execute([$rewardId, $userId]);
        if ($isAjax) {
            shop_json_response([
                'success' => true,
                'action' => 'delete_reward',
                'reward_id' => $rewardId,
                'message' => "Hadiah berhasil dihapus."
            ]);
        }
        header("Location: shop.php");
        exit();
    }
}

// Ambil saldo Gold terbaru (setelah handler POST)
$stmt = $pdo->prepare("SELECT gold FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$userGold = $stmt->fetchColumn() ?: 0;

// Ambil data rewards
$stmt = $pdo->prepare("SELECT * FROM rewards WHERE user_id = ? ORDER BY cost ASC");
$stmt->execute([$userId]);
$rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil riwayat penukaran hadiah terakhir
$stmtLogs = $pdo->prepare("
    SELECT rl.cost, rl.claimed_at, COALESCE(r.reward_name, 'Hadiah Kustom') AS reward_name, COALESCE(r.icon, 'fa-gift') AS icon
    FROM reward_logs rl
    LEFT JOIN rewards r ON rl.reward_id = r.reward_id
    WHERE rl.user_id = ?
    ORDER BY rl.claimed_at DESC
    LIMIT 5
");
$stmtLogs->execute([$userId]);
$claimedLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

// Header baru di-include SETELAH semua handler POST (redirect butuh belum ada output)
$pageTitle = 'Toko Hadiah';
require_once __DIR__ . '/../includes/header.php';

$isHpFull = (($navUser['hp'] ?? 100) >= ($navUser['max_hp'] ?? 100));
?>

<div class="page-header d-flex justify-content-between align-items-end">
    <div>
        <h1 class="page-title">Toko Hadiah</h1>
        <p class="page-subtitle">Tukarkan gold untuk pemulihan vital, perisai streak, dan hadiah pribadi.</p>
    </div>
    <div class="text-end">
        <div class="text-muted" style="font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;">Saldo Gold</div>
        <div style="font-size: 1.8rem; font-weight: 600; color: var(--status-warning); font-family: var(--font-mono); line-height: 1;">
            <i class="fas fa-coins me-1" style="font-size: 1.3rem;"></i><span id="shopGoldVal"><?php echo number_format($userGold); ?></span>
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
            <div class="bento-panel spotlight-card holo-card d-flex flex-column justify-content-between p-3 h-100">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div style="width: 36px; height: 36px; border-radius: var(--radius); background-color: var(--status-danger-dim); border: 1px solid rgba(244, 63, 94, 0.2); color: var(--status-danger); display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                        <i class="fas fa-prescription-bottle"></i>
                    </div>
                    <div>
                        <div style="font-weight: 500; font-size: 0.88rem; color: var(--text-primary);">Ramuan HP Minor</div>
                        <div class="text-muted" style="font-size: 0.75rem;">Memulihkan +25 HP seketika</div>
                    </div>
                </div>
                <form method="POST" action="shop.php" class="ajax-shop-form m-0">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="potion_type" value="minor">
                    <button type="submit" name="buy_potion" id="btnBuyPotionMinor" data-potion="minor" class="btn-core btn-outline w-100 font-mono shop-buy-btn" <?php echo ($userGold < 25 || $isHpFull) ? 'disabled' : ''; ?> data-confirm="Beli Ramuan HP Minor seharga 25 Gold?" data-confirm-title="Beli Ramuan">
                        <?php if ($isHpFull): ?>
                            <i class="fas fa-check text-accent me-1"></i> HP Penuh
                        <?php else: ?>
                            <i class="fas fa-coins text-warning me-1"></i> 25 Gold
                        <?php endif; ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Full Elixir -->
        <div class="col-md-4">
            <div class="bento-panel spotlight-card holo-card d-flex flex-column justify-content-between p-3 h-100">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div style="width: 36px; height: 36px; border-radius: var(--radius); background-color: var(--status-danger-dim); border: 1px solid rgba(244, 63, 94, 0.2); color: var(--status-danger); display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                        <i class="fas fa-flask"></i>
                    </div>
                    <div>
                        <div style="font-weight: 500; font-size: 0.88rem; color: var(--text-primary);">Elixir HP Penuh</div>
                        <div class="text-muted" style="font-size: 0.75rem;">Memulihkan 100% HP penuh</div>
                    </div>
                </div>
                <form method="POST" action="shop.php" class="ajax-shop-form m-0">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="potion_type" value="full">
                    <button type="submit" name="buy_potion" id="btnBuyPotionFull" data-potion="full" class="btn-core btn-outline w-100 font-mono shop-buy-btn" <?php echo ($userGold < 75 || $isHpFull) ? 'disabled' : ''; ?> data-confirm="Beli Elixir HP Penuh seharga 75 Gold?" data-confirm-title="Beli Ramuan">
                        <?php if ($isHpFull): ?>
                            <i class="fas fa-check text-accent me-1"></i> HP Penuh
                        <?php else: ?>
                            <i class="fas fa-coins text-warning me-1"></i> 75 Gold
                        <?php endif; ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Streak Freeze -->
        <div class="col-md-4">
            <div class="bento-panel spotlight-card holo-card d-flex flex-column justify-content-between p-3 h-100">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div style="width: 36px; height: 36px; border-radius: var(--radius); background-color: var(--status-info-dim); border: 1px solid rgba(6, 182, 212, 0.2); color: var(--status-info); display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                        <i class="fas fa-snowflake"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between">
                            <span style="font-weight: 500; font-size: 0.88rem; color: var(--text-primary);">Perisai Streak Freeze</span>
                            <span class="badge-status info font-mono ms-auto" id="freezeCountBadge" style="font-size: 0.7rem;">
                                <i class="fas fa-shield-alt me-1"></i><span id="freezeCountVal"><?php echo (int)($navUser['streak_freeze'] ?? 0); ?></span> Dimiliki
                            </span>
                        </div>
                        <div class="text-muted" style="font-size: 0.75rem;">Melindungi streak selama 1 hari</div>
                    </div>
                </div>
                <form method="POST" action="shop.php" class="ajax-shop-form m-0">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="buy_freeze" id="btnBuyFreeze" class="btn-core btn-outline w-100 font-mono shop-buy-btn" <?php echo $userGold < 50 ? 'disabled' : ''; ?> data-confirm="Beli Perisai Streak Freeze seharga 50 Gold?" data-confirm-title="Beli Perisai">
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

    <div class="row g-3" id="rewardsRow">
        <?php foreach ($rewards as $reward): ?>
            <div class="col-md-4 reward-card-col" id="rewardCard<?php echo $reward['reward_id']; ?>" data-cost="<?php echo (int)$reward['cost']; ?>">
                <div class="bento-panel spotlight-card holo-card text-center h-100 position-relative p-4 d-flex flex-column justify-content-between" style="<?php echo $userGold < $reward['cost'] ? 'opacity: 0.55;' : ''; ?>">
                    <form method="POST" action="shop.php" class="ajax-shop-form" style="position: absolute; top: 0.75rem; right: 0.75rem;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="reward_id" value="<?php echo $reward['reward_id']; ?>">
                        <button type="submit" name="delete_reward" class="btn-core btn-ghost p-1 delete-reward-btn" title="Hapus hadiah" aria-label="Hapus hadiah <?php echo htmlspecialchars($reward['reward_name']); ?>" data-confirm="Hapus hadiah '<?php echo htmlspecialchars($reward['reward_name']); ?>'?" data-confirm-title="Hapus Hadiah" data-confirm-variant="danger" data-confirm-btn="Hapus">
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
                        <form method="POST" action="shop.php" class="ajax-shop-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="reward_id" value="<?php echo $reward['reward_id']; ?>">
                            <button type="submit" name="buy_reward" class="btn-core btn-outline w-100 font-mono buy-reward-btn shop-buy-btn" <?php echo $userGold < $reward['cost'] ? 'disabled' : ''; ?> data-confirm="Tukar <?php echo number_format($reward['cost']); ?> Gold untuk hadiah '<?php echo htmlspecialchars($reward['reward_name']); ?>'?" data-confirm-title="Tukar Hadiah">
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

<!-- Section: Riwayat Penukaran Hadiah -->
<div class="mt-5" id="claimedLogsSection" style="<?php echo empty($claimedLogs) ? 'display: none;' : ''; ?>">
    <div class="panel-header" style="border-bottom: 1px solid var(--border-hairline); padding-bottom: 0.6rem; margin-bottom: 1rem;">
        <h3 class="panel-title" style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted);">
            <i class="fas fa-history text-accent"></i> Riwayat Klaim Hadiah
        </h3>
    </div>
    <div class="bento-panel p-3">
        <div class="data-list" id="claimedLogsList">
            <?php foreach ($claimedLogs as $log): ?>
                <div class="data-row" style="padding: 0.65rem 0;">
                    <div class="data-main">
                        <div style="width: 28px; height: 28px; border-radius: var(--radius); background-color: var(--status-warning-dim); border: 1px solid rgba(245, 158, 11, 0.2); color: var(--status-warning); display: flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                            <i class="fas <?php echo htmlspecialchars($log['icon']); ?>"></i>
                        </div>
                        <div>
                            <div class="data-title" style="font-size: 0.85rem;"><?php echo htmlspecialchars($log['reward_name']); ?></div>
                            <div class="data-meta font-mono" style="font-size: 0.72rem;">
                                <span><?php echo indo_short_date($log['claimed_at'], true); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <span class="badge-status warning font-mono">
                            <i class="fas fa-coins me-1"></i>-<?php echo number_format($log['cost']); ?> Gold
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal Add Reward -->
<div class="modal fade" id="addRewardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="shop.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="add_reward" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-size: 1rem; font-weight: 600;"><i class="fas fa-gift me-2 text-warning"></i> Buat Hadiah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_rew_name" class="form-label">Nama hadiah</label>
                        <input type="text" class="form-control" id="add_rew_name" name="reward_name" placeholder="mis., Beli kopi, Main game 2 jam" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label for="add_rew_cost" class="form-label">Harga (Gold)</label>
                            <input type="number" class="form-control font-mono" id="add_rew_cost" name="cost" min="1" max="99999" placeholder="100" required>
                        </div>
                        <div class="col-6">
                            <label for="add_rew_icon" class="form-label">Ikon</label>
                            <select class="form-select" id="add_rew_icon" name="icon">
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
                    <button type="submit" name="add_reward" value="1" class="btn-core btn-primary">Buat Hadiah</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function updateShopUI(data) {
        // 1. Update Gold in HUD & Shop Header
        const hudGold = document.getElementById('hudGoldVal');
        if (hudGold) hudGold.textContent = Number(data.gold).toLocaleString('id-ID');
        const shopGold = document.getElementById('shopGoldVal');
        if (shopGold) shopGold.textContent = Number(data.gold).toLocaleString('id-ID');

        // 2. Update HP in HUD
        const hudHp = document.getElementById('hudHpVal');
        if (hudHp) hudHp.textContent = data.hp;

        // 3. Update Streak Freeze Count
        const freezeVal = document.getElementById('freezeCountVal');
        if (freezeVal && typeof data.streak_freeze !== 'undefined') {
            freezeVal.textContent = data.streak_freeze;
        }

        // 4. Update Potion Buttons state
        const isHpFull = (data.hp >= data.max_hp);
        const btnMinor = document.getElementById('btnBuyPotionMinor');
        if (btnMinor) {
            if (isHpFull) {
                btnMinor.innerHTML = '<i class="fas fa-check text-accent me-1"></i> HP Penuh';
                btnMinor.disabled = true;
            } else {
                btnMinor.innerHTML = '<i class="fas fa-coins text-warning me-1"></i> 25 Gold';
                btnMinor.disabled = (data.gold < 25);
            }
        }

        const btnFull = document.getElementById('btnBuyPotionFull');
        if (btnFull) {
            if (isHpFull) {
                btnFull.innerHTML = '<i class="fas fa-check text-accent me-1"></i> HP Penuh';
                btnFull.disabled = true;
            } else {
                btnFull.innerHTML = '<i class="fas fa-coins text-warning me-1"></i> 75 Gold';
                btnFull.disabled = (data.gold < 75);
            }
        }

        // 5. Update Freeze Button state
        const btnFreeze = document.getElementById('btnBuyFreeze');
        if (btnFreeze) {
            btnFreeze.disabled = (data.gold < 50);
        }

        // 6. Update Custom Reward Cards
        document.querySelectorAll('.reward-card-col').forEach(col => {
            const cost = parseInt(col.dataset.cost) || 0;
            const panel = col.querySelector('.bento-panel');
            const btn = col.querySelector('.buy-reward-btn');
            if (data.gold < cost) {
                if (panel) panel.style.opacity = '0.55';
                if (btn) btn.disabled = true;
            } else {
                if (panel) panel.style.opacity = '1';
                if (btn) btn.disabled = false;
            }
        });
    }

    async function submitShopAjax(form, btn) {
        if (!form) return;
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
        }

        const formData = new FormData(form);
        if (btn && btn.name && !formData.has(btn.name)) {
            formData.append(btn.name, btn.value || '1');
        }

        try {
            const res = await fetch(form.action || 'shop.php', {
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
                    window.showToast('Gagal', data.message || 'Transaksi gagal.', 'fa-exclamation-triangle', 'danger');
                } else {
                    alert(data.message || 'Transaksi gagal.');
                }
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    delete btn.dataset.confirmed;
                }
                return;
            }

            // Success feedback
            if (btn) {
                delete btn.dataset.confirmed;
                const rect = btn.getBoundingClientRect();
                const x = rect.left + rect.width / 2;
                const y = rect.top;
                if (window.spawnFloatingReward) {
                    window.spawnFloatingReward(x, y, data.floating_text || '<i class="fas fa-check text-accent"></i> Sukses!');
                }
            }

            if (typeof LVSound !== 'undefined') {
                if (data.action === 'buy_reward') {
                    LVSound.fanfare();
                } else {
                    LVSound.coin();
                }
            }

            const iconMap = {
                'buy_potion': 'fa-heart',
                'buy_freeze': 'fa-snowflake',
                'buy_reward': 'fa-gift',
                'delete_reward': 'fa-trash'
            };
            const typeMap = {
                'buy_potion': 'accent',
                'buy_freeze': 'info',
                'buy_reward': 'warning',
                'delete_reward': 'danger'
            };

            if (window.showToast) {
                window.showToast('Toko Hadiah', data.message, iconMap[data.action] || 'fa-check', typeMap[data.action] || 'accent');
            }

            // Update UI State
            if (typeof data.gold !== 'undefined') {
                updateShopUI(data);
            }

            // Handle card deletion
            if (data.action === 'delete_reward' && data.reward_id) {
                const card = document.getElementById('rewardCard' + data.reward_id);
                if (card) {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';
                    setTimeout(() => card.remove(), 300);
                }
            }

            // Handle claim logs prepend
            if (data.action === 'buy_reward') {
                let logsSection = document.getElementById('claimedLogsSection');
                let logsList = document.getElementById('claimedLogsList');
                if (logsSection) {
                    logsSection.style.display = '';
                }
                if (logsList) {
                    const newRow = document.createElement('div');
                    newRow.className = 'data-row';
                    newRow.style.padding = '0.65rem 0';
                    newRow.innerHTML = `
                        <div class="data-main">
                            <div style="width: 28px; height: 28px; border-radius: var(--radius); background-color: var(--status-warning-dim); border: 1px solid rgba(245, 158, 11, 0.2); color: var(--status-warning); display: flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                                <i class="fas ${data.icon || 'fa-gift'}"></i>
                            </div>
                            <div>
                                <div class="data-title" style="font-size: 0.85rem;">${data.reward_name}</div>
                                <div class="data-meta font-mono" style="font-size: 0.72rem;">
                                    <span>${data.claimed_at}</span>
                                </div>
                            </div>
                        </div>
                        <div class="data-actions">
                            <span class="badge-status warning font-mono">
                                <i class="fas fa-coins me-1"></i>-${Number(data.cost).toLocaleString('id-ID')} Gold
                            </span>
                        </div>
                    `;
                    logsList.insertBefore(newRow, logsList.firstChild);
                }
            }

        } catch (err) {
            console.error('Shop AJAX error:', err);
            if (window.showToast) {
                window.showToast('Gagal', 'Terjadi kesalahan jaringan.', 'fa-exclamation-triangle', 'danger');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                delete btn.dataset.confirmed;
            }
        }
    }

    // Intercept only shop item forms (never modal forms)
    document.querySelectorAll('form.ajax-shop-form').forEach(form => {
        if (form.closest('.modal')) return;
        form.addEventListener('shop:ajax-submit', function(e) {
            submitShopAjax(form, e.detail && e.detail.triggerButton);
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitter = e.submitter || form.querySelector('button[type="submit"]');
            submitShopAjax(form, submitter);
        });
    });
});
</script>

<?php require_once("../includes/footer.php"); ?>