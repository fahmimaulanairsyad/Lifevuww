<?php
// pages/dungeon.php — Dungeon Raid & Weekly Boss Rush (Sovereign Expansion v3.0)
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
        <p class="page-subtitle">Boss mingguan berganti setiap minggu — serang lewat Habit, Misi, Focus Rift, dan Jurnal.</p>
    </div>
    <span class="badge-status font-mono">Minggu <?php echo htmlspecialchars($weekKey); ?> • <?php echo htmlspecialchars($boss['gate']); ?></span>
</div>

<div class="bento-panel spotlight-card holo-card mb-4">
    <div class="row g-4 align-items-center">
        <div class="col-md-5 text-center">
            <svg width="170" height="170" viewBox="0 0 170 170" style="overflow: visible;">
                <circle cx="85" cy="85" r="70" fill="none" stroke="<?php echo $boss['color']; ?>" stroke-opacity="<?php echo $defeated ? 0.15 : 0.5; ?>" stroke-width="4" stroke-dasharray="10 6">
                    <animateTransform attributeName="transform" type="rotate" from="0 85 85" to="360 85 85" dur="18s" repeatCount="indefinite"/>
                </circle>
                <circle cx="85" cy="85" r="52" fill="<?php echo $defeated ? 'rgba(16,185,129,0.10)' : $boss['color']; ?>" fill-opacity="<?php echo $defeated ? 0.4 : 0.18; ?>" stroke="<?php echo $defeated ? 'var(--accent)' : $boss['color']; ?>" stroke-width="2.5"/>
                <circle cx="70" cy="80" r="5" fill="#fafafa"/>
                <circle cx="100" cy="80" r="5" fill="#fafafa"/>
                <path d="M 74 100 Q 85 110 96 100" stroke="<?php echo $defeated ? '#10b981' : '#fafafa'; ?>" stroke-width="3" fill="none" stroke-linecap="round"/>
                <path d="M 62 58 L 74 66 M 108 58 L 96 66" stroke="<?php echo $boss['color']; ?>" stroke-width="3.5" stroke-linecap="round"/>
            </svg>
            <h2 style="font-size: 1.15rem; font-weight: 700; margin: 0.5rem 0 0.1rem;"><?php echo htmlspecialchars($boss['name']); ?></h2>
            <span class="badge-status danger font-mono mb-2"><i class="fas fa-skull me-1"></i> <?php echo htmlspecialchars($boss['gate']); ?> BOSS</span>
            <?php if ($defeated): ?>
                <div class="badge-status accent font-mono d-block mt-1"><i class="fas fa-trophy me-1"></i> TUMBANG!</div>
            <?php endif; ?>
        </div>
        <div class="col-md-7">
            <div class="d-flex justify-content-between font-mono mb-1" style="font-size: 0.8rem;">
                <span class="text-danger"><i class="fas fa-heart me-1"></i> BOSS HP</span>
                <span><?php echo number_format($bossHp); ?> / <?php echo number_format($bossMax); ?> (<?php echo $hpPct; ?>%)</span>
            </div>
            <div class="vitals-track" style="height: 16px; border-radius: 999px; background: rgba(255,255,255,0.06);">
                <div class="vitals-fill" style="width: <?php echo $hpPct; ?>%; background: linear-gradient(90deg, var(--status-danger), #fb7185); border-radius: 999px;"></div>
            </div>
            <div class="d-flex flex-wrap gap-3 mt-3 font-mono" style="font-size: 0.76rem;">
                <span class="text-accent"><i class="fas fa-gavel me-1"></i> Total damage: <?php echo number_format($totalDmg); ?></span>
                <span><i class="fas <?php echo $comp['icon']; ?> me-1" style="color: <?php echo $comp['accent']; ?>;"></i> <?php echo htmlspecialchars($comp['name']); ?> (<?php echo $bond['stage']; ?> Lv.<?php echo $bond['bond_level']; ?> ×<?php echo number_format($bond['bonus_scale'], 2); ?>)</span>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php if ($defeated && !$rewardClaimed): ?>
                    <form method="POST" action="dungeon.php" style="margin: 0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="claim_raid_reward" value="1">
                        <button type="submit" class="btn-core btn-accent" style="padding: 0.6rem 1.2rem;">
                            <i class="fas fa-box-open me-1"></i> Klaim Loot Kemenangan (+<?php echo (int) $boss['reward_exp']; ?> EXP, +<?php echo (int) $boss['reward_gold']; ?> Gold<?php echo $boss['reward_freeze'] > 0 ? ', +' . (int) $boss['reward_freeze'] . ' Streak Freeze' : ''; ?>)
                        </button>
                    </form>
                <?php elseif ($defeated && $rewardClaimed): ?>
                    <form method="POST" action="dungeon.php" style="margin: 0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="next_gate" value="1">
                        <button type="submit" class="btn-core btn-primary" style="padding: 0.6rem 1.2rem;">
                            <i class="fas fa-door-open me-1"></i> Buka Gate Tingkat Berikutnya (Overlord Challenge)
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-muted" style="font-size: 0.78rem;">
                        <i class="fas fa-circle-info me-1"></i> Serang Boss lewat menyelesaikan <strong>Habit</strong> (+20..35 DMG), <strong>Misi</strong> (+60..120 DMG), <strong>Focus Rift</strong> (+40..90 DMG), dan <strong>Jurnal</strong> (+25 DMG). Bonus <?php echo htmlspecialchars($comp['name']); ?> ikut dihitung.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="bento-panel mb-4">
    <div class="panel-header">
        <h3 class="panel-title"><i class="fas fa-scroll text-accent"></i> Battle Log (15 serangan terakhir)</h3>
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
