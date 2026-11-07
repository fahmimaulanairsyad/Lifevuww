<?php
// pages/dungeon.php: Dungeon Raid & Weekly Boss Rush (Sovereign Expansion v3.0)
require_once __DIR__ . '/../config/database.php';
enforce_login();

$userId = (int) $_SESSION['user_id'];

// ============ POST handlers (sebelum output) ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    // Klaim loot kemenangan: EXP + Gold (+ Streak Freeze) sesuai katalog boss
    if (isset($_POST['claim_raid_reward'])) {
        $weekKey = date('o-W');
        $bosses = game_dungeon_bosses();
        $stmt = $pdo->prepare("SELECT raid_id, boss_key, boss_hp, boss_max_hp, reward_claimed FROM dungeon_raids WHERE user_id = ? AND week_key = ? FOR UPDATE");
        $stmt->execute([$userId, $weekKey]);
        $raid = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($raid && (int) $raid['boss_hp'] <= 0 && (int) $raid['reward_claimed'] === 0 && isset($bosses[$raid['boss_key']])) {
            $b = $bosses[$raid['boss_key']];
            try {
                $pdo->beginTransaction();
                $stmtUser = $pdo->prepare("SELECT exp, gold FROM users WHERE user_id = ? FOR UPDATE");
                $stmtUser->execute([$userId]);
                $u = $stmtUser->fetch(PDO::FETCH_ASSOC);
                $newExp = (int) $u['exp'] + (int) $b['reward_exp'];
                $newGold = (int) $u['gold'] + (int) $b['reward_gold'];
                $newLvl = calculateLevel($newExp);
                $newRank = calculateRank($newLvl);
                $pdo->prepare("UPDATE users SET exp = ?, gold = gold + ?, level = ?, `rank` = ?, streak_freeze = streak_freeze + ? WHERE user_id = ?")
                    ->execute([$newExp, (int) $b['reward_gold'], $newLvl, $newRank, (int) $b['reward_freeze'], $userId]);
                $pdo->prepare("UPDATE dungeon_raids SET reward_claimed = 1 WHERE raid_id = ?")
                    ->execute([(int) $raid['raid_id']]);
                $_SESSION['reward_toast'] = [
                    'type' => 'boss',
                    'name' => "RAID CLEARED: {$b['name']} [ {$b['gate']} ]",
                    'exp' => (int) $b['reward_exp'],
                    'gold' => (int) $b['reward_gold'],
                    'is_boss' => true
                ];
                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Raid reward error: " . $e->getMessage());
            }
        }
        header("Location: dungeon.php");
        exit();
    }

    // Buka Gate tingkat berikutnya setelah Boss minggu ini dikalahkan & loot diklaim
    if (isset($_POST['next_gate'])) {
        $weekKey = date('o-W');
        $bosses = game_dungeon_bosses();
        $keys = array_keys($bosses);
        $stmt = $pdo->prepare("SELECT raid_id, boss_key, boss_hp, reward_claimed FROM dungeon_raids WHERE user_id = ? AND week_key = ? FOR UPDATE");
        $stmt->execute([$userId, $weekKey]);
        $raid = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($raid && (int) $raid['boss_hp'] <= 0 && (int) $raid['reward_claimed'] === 1) {
            $idx = array_search($raid['boss_key'], $keys, true);
            $nextIdx = ($idx === false) ? 0 : min(count($keys) - 1, $idx + 1);
            if ($nextIdx !== $idx) {
                $nb = $bosses[$keys[$nextIdx]];
                $pdo->prepare("UPDATE dungeon_raids SET boss_key = ?, boss_hp = ?, boss_max_hp = ?, is_defeated = 0, reward_claimed = 0, battle_log = CONCAT(COALESCE(battle_log, ''), ?) WHERE raid_id = ?")
                    ->execute([$keys[$nextIdx], (int) $nb['hp'], (int) $nb['hp'], json_encode([['t' => date('Y-m-d H:i:s'), 'a' => 'GATE OPENED: ' . $nb['gate'], 'd' => 0]], JSON_UNESCAPED_UNICODE), (int) $raid['raid_id']]);
            }
        }
        header("Location: dungeon.php");
        exit();
    }
}

$pageTitle = 'Dungeon';
require_once __DIR__ . '/../includes/header.php';

// ============ Data raid minggu berjalan ============
$weekKey = date('o-W');
$bosses = game_dungeon_bosses();
$stmt = $pdo->prepare("SELECT * FROM dungeon_raids WHERE user_id = ? AND week_key = ?");
$stmt->execute([$userId, $weekKey]);
$raid = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$raid) {
    $bossKey = game_dungeon_pick_boss_key($weekKey);
    $b0 = $bosses[$bossKey];
    $pdo->prepare("INSERT IGNORE INTO dungeon_raids (user_id, week_key, boss_key, boss_hp, boss_max_hp) VALUES (?, ?, ?, ?, ?)")
        ->execute([$userId, $weekKey, $bossKey, (int) $b0['hp'], (int) $b0['hp']]);
    $stmt->execute([$userId, $weekKey]);
    $raid = $stmt->fetch(PDO::FETCH_ASSOC);
}
$boss = $bosses[$raid['boss_key']] ?? reset($bosses);
$bossHp = max(0, (int) $raid['boss_hp']);
$bossMax = max(1, (int) $raid['boss_max_hp']);
$hpPct = (int) round(($bossHp / $bossMax) * 100);
$totalDmg = (int) $raid['total_damage_dealt'];
$defeated = $bossHp <= 0;
$rewardClaimed = (int) $raid['reward_claimed'] === 1;
$battleLog = json_decode((string) ($raid['battle_log'] ?? '[]'), true) ?: [];
$battleLog = array_reverse($battleLog);

// Familiar aktif untuk efek tampilan
$stmtMe = $pdo->prepare("SELECT active_companion, companion_exp FROM users WHERE user_id = ?");
$stmtMe->execute([$userId]);
$meRow = $stmtMe->fetch(PDO::FETCH_ASSOC);
$comps = game_companions();
$compKey = isset($comps[$meRow['active_companion'] ?? '']) ? $meRow['active_companion'] : 'obsidian_wolf';
$comp = $comps[$compKey];
$bond = game_companion_level((int) ($meRow['companion_exp'] ?? 0));
?>

<div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
        <h1 class="page-title"><i class="fas fa-dungeon text-danger me-1"></i> Dungeon Raid</h1>
        <p class="page-subtitle">Target mingguan berskala server. Serang target via penyelesaian Habit, Misi, Focus Rift, dan Jurnal.</p>
    </div>
    <span class="badge-status font-mono">Minggu <?php echo htmlspecialchars($weekKey); ?> • <?php echo htmlspecialchars($boss['gate']); ?></span>
</div>

<div class="bento-panel mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 pb-2" style="border-bottom: 1px solid var(--border-hairline);">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <?php 
            $hasBossImg = !empty($boss['image']) && file_exists(__DIR__ . '/../' . $boss['image']);
            if ($hasBossImg): ?>
                <img src="../<?php echo htmlspecialchars($boss['image']); ?>" alt="<?php echo htmlspecialchars($boss['name']); ?>" class="boss-img-display" style="width: 140px; height: 140px; max-width: 140px; max-height: 140px; object-fit: contain; border-radius: 8px; border: 1px solid var(--border-hairline); background: var(--bg-surface-hover); flex-shrink: 0;">
            <?php else: ?>
                <div style="width: 140px; height: 140px; max-width: 140px; max-height: 140px; border-radius: var(--radius); background: var(--status-danger-dim); border: 1px solid rgba(220, 38, 38, 0.25); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--status-danger); flex-shrink: 0;">
                    <i class="fas <?php echo htmlspecialchars($boss['icon']); ?>"></i>
                </div>
            <?php endif; ?>
            <div style="flex: 1; min-width: 200px;">
                <div class="font-mono text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em; text-transform: uppercase;">
                    RAID TARGET &bull; <?php echo htmlspecialchars($boss['gate']); ?>
                </div>
                <h2 style="font-size: 1.35rem; font-weight: 700; margin: 4px 0 0 0;"><?php echo htmlspecialchars($boss['name']); ?></h2>
            </div>
        </div>
        <div>
            <?php if ($defeated): ?>
                <span class="badge-status accent font-mono"><i class="fas fa-check-circle me-1"></i> TUMBANG</span>
            <?php else: ?>
                <span class="badge-status danger font-mono"><i class="fas fa-crosshairs me-1"></i> TARGET AKTIF</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Big Typographic HP & Progress -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-baseline mb-1">
            <span class="text-muted font-mono" style="font-size: 0.72rem; text-transform: uppercase;">Integritas Vital Boss</span>
            <div class="font-mono text-end">
                <span style="font-size: 1.75rem; font-weight: 700; color: <?php echo $defeated ? 'var(--accent)' : 'var(--status-danger)'; ?>;">
                    <?php echo number_format($bossHp); ?>
                </span>
                <span class="text-muted" style="font-size: 0.95rem;"> / <?php echo number_format($bossMax); ?> HP</span>
                <span class="text-muted ms-2" style="font-size: 0.85rem;">(<?php echo $hpPct; ?>%)</span>
            </div>
        </div>
        <div class="vitals-track" style="height: 8px; border-radius: 4px; background: var(--bg-surface-hover);">
            <div class="vitals-fill" style="width: <?php echo $hpPct; ?>%; background-color: <?php echo $defeated ? 'var(--accent)' : 'var(--status-danger)'; ?>; border-radius: 4px;"></div>
        </div>
    </div>

    <!-- System Status Table (4 Telemetry Cells) -->
    <div class="row g-2 mb-3">
        <div class="col-sm-3 col-6">
            <div class="p-2" style="background: var(--bg-surface-hover); border: 1px solid var(--border-hairline); border-radius: var(--radius);">
                <div class="text-muted font-mono" style="font-size: 0.68rem; text-transform: uppercase;">Total Kerusakan</div>
                <div class="font-mono" style="font-size: 0.95rem; font-weight: 600; color: var(--accent);"><?php echo number_format($totalDmg); ?> DMG</div>
            </div>
        </div>
        <div class="col-sm-3 col-6">
            <div class="p-2" style="background: var(--bg-surface-hover); border: 1px solid var(--border-hairline); border-radius: var(--radius);">
                <div class="text-muted font-mono" style="font-size: 0.68rem; text-transform: uppercase;">Familiar Aktif</div>
                <div class="font-mono text-truncate d-flex align-items-center gap-1" style="font-size: 0.85rem; font-weight: 600;" title="<?php echo htmlspecialchars($comp['name']); ?>">
                    <?php 
                    $hasCompImg = !empty($comp['image']) && file_exists(__DIR__ . '/../' . $comp['image']);
                    if ($hasCompImg): ?>
                        <img src="../<?php echo htmlspecialchars($comp['image']); ?>" alt="" style="width: 18px; height: 18px; object-fit: contain; border-radius: 4px; flex-shrink: 0;">
                    <?php else: ?>
                        <i class="fas <?php echo $comp['icon']; ?> text-accent"></i>
                    <?php endif; ?>
                    <span class="text-truncate"><?php echo htmlspecialchars($comp['name']); ?></span>
                </div>
            </div>
        </div>
        <div class="col-sm-3 col-6">
            <div class="p-2" style="background: var(--bg-surface-hover); border: 1px solid var(--border-hairline); border-radius: var(--radius);">
                <div class="text-muted font-mono" style="font-size: 0.68rem; text-transform: uppercase;">Efektivitas Serangan</div>
                <div class="font-mono" style="font-size: 0.95rem; font-weight: 600;">&times;<?php echo number_format($bond['bonus_scale'], 2); ?></div>
            </div>
        </div>
        <div class="col-sm-3 col-6">
            <div class="p-2" style="background: var(--bg-surface-hover); border: 1px solid var(--border-hairline); border-radius: var(--radius);">
                <div class="text-muted font-mono" style="font-size: 0.68rem; text-transform: uppercase;">Hadiah Kemenangan</div>
                <div class="font-mono text-warning" style="font-size: 0.85rem; font-weight: 600;">+<?php echo (int) $boss['reward_exp']; ?> EXP &bull; +<?php echo (int) $boss['reward_gold']; ?> G</div>
            </div>
        </div>
    </div>

    <!-- Actions / Instructions -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2" style="border-top: 1px solid var(--border-hairline);">
        <?php if ($defeated && !$rewardClaimed): ?>
            <form method="POST" action="dungeon.php" style="margin: 0;" class="w-100">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="claim_raid_reward" value="1">
                <button type="submit" class="btn-core btn-accent w-100 font-mono" style="padding: 0.55rem 1rem;">
                    <i class="fas fa-gift me-1"></i> Klaim Loot Kemenangan (+<?php echo (int) $boss['reward_exp']; ?> EXP, +<?php echo (int) $boss['reward_gold']; ?> Gold<?php echo $boss['reward_freeze'] > 0 ? ', +' . (int) $boss['reward_freeze'] . ' Streak Freeze' : ''; ?>)
                </button>
            </form>
        <?php elseif ($defeated && $rewardClaimed): ?>
            <form method="POST" action="dungeon.php" style="margin: 0;" class="w-100">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="next_gate" value="1">
                <button type="submit" class="btn-core btn-outline w-100 font-mono" style="padding: 0.55rem 1rem;">
                    <i class="fas fa-door-open me-1"></i> Buka Gate Tingkat Berikutnya (Overlord Challenge)
                </button>
            </form>
        <?php else: ?>
            <div class="text-muted font-sans" style="font-size: 0.76rem;">
                <i class="fas fa-info-circle me-1 text-accent"></i> Serang Boss via penyelesaian Habit (+20..35 DMG), Misi (+60..120 DMG), Focus Rift (+40..90 DMG), dan Jurnal (+25 DMG).
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="bento-panel mb-4">
    <div class="panel-header">
        <div>
            <div class="sys-micro-tag">SYS.LOG // ENCOUNTER_HISTORY</div>
            <h3 class="panel-title"><i class="fas fa-scroll text-accent"></i> Battle Log (15 serangan terakhir)</h3>
        </div>
    </div>
    <?php if (empty($battleLog)): ?>
        <div class="text-center py-4 text-muted">
            <i class="fas fa-shield-halved fa-2x mb-3" style="opacity: 0.3;"></i>
            <p class="mb-0">Belum ada serangan minggu ini. Selesaikan habit/misi untuk menyerang!</p>
        </div>
    <?php else: ?>
        <div class="data-list">
            <?php foreach ($battleLog as $entry): ?>
                <div class="data-row" style="padding: 0.6rem 0;">
                    <div class="data-main">
                        <div style="width: 28px; height: 28px; border-radius: var(--radius); background: var(--status-danger-dim); border: 1px solid rgba(244,63,94,0.25); color: var(--status-danger); display: flex; align-items: center; justify-content: center; font-size: 0.72rem; flex-shrink: 0;">
                            <i class="fas fa-khanda"></i>
                        </div>
                        <div>
                            <div class="data-title" style="font-size: 0.84rem;"><?php echo htmlspecialchars((string) ($entry['a'] ?? 'Serangan')); ?></div>
                            <div class="data-meta font-mono" style="font-size: 0.7rem;"><?php echo htmlspecialchars((string) ($entry['t'] ?? '')); ?></div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <?php $d = (int) ($entry['d'] ?? 0); ?>
                        <span class="font-mono <?php echo $d > 0 ? 'text-danger' : 'text-muted'; ?>" style="font-size: 0.8rem; font-weight: 600;">
                            <?php echo $d > 0 ? '-' . number_format($d) . ' HP' : 'GATE'; ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once("../includes/footer.php"); ?>
