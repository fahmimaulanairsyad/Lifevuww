<?php
// pages/skills.php
require_once __DIR__ . '/../config/database.php';
enforce_login();

// ============ POST handlers: Skill Tree & Astral Companion (sebelum output) ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $userId = (int) $_SESSION['user_id'];
    $skillTree = game_skill_tree();

    // Buka node bakat pasif: butuh SP cukup + semua tier sebelumnya di cabang sama sudah terbuka
    if (isset($_POST['unlock_skill_node'])) {
        $allNodes = [];
        foreach ($skillTree['branches'] as $branchKey => $branch) {
            foreach ($branch['nodes'] as $node) {
                $allNodes[$node['code']] = ['branch' => $branchKey, 'tier' => (int) $node['tier'], 'cost' => (int) $node['cost']];
            }
        }
        $code = $_POST['unlock_skill_node'] ?? '';
        $stmt = $pdo->prepare("SELECT unlocked_skills, spent_skill_points, level FROM users WHERE user_id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $unlocked = json_decode($row['unlocked_skills'] ?? '[]', true) ?: [];
        $spent = (int) $row['spent_skill_points'];
        $available = game_available_skill_points((int) $row['level'], $spent);

        // Validasi: node ada, belum terbuka, tier sebelumnya di cabang sama terbuka, SP cukup
        $tierOk = false;
        if (isset($allNodes[$code])) {
            $n = $allNodes[$code];
            $tierOk = true;
            foreach ($skillTree['branches'][$n['branch']]['nodes'] as $node) {
                if ((int) $node['tier'] < $n['tier'] && !in_array($node['code'], $unlocked, true)) {
                    $tierOk = false;
                    break;
                }
            }
        }
        $valid = isset($allNodes[$code]) && !in_array($code, $unlocked, true) && $tierOk && $allNodes[$code]['cost'] <= $available;
        if ($valid) {
            $unlocked[] = $code;
            $pdo->prepare("UPDATE users SET unlocked_skills = ?, spent_skill_points = spent_skill_points + ? WHERE user_id = ?")
                ->execute([json_encode(array_values($unlocked)), (int) $allNodes[$code]['cost'], $userId]);
            $nodeName = '';
            foreach ($skillTree['branches'] as $branch) {
                foreach ($branch['nodes'] as $node) {
                    if ($node['code'] === $code) $nodeName = $node['name'];
                }
            }
            $_SESSION['skill_toast'] = "Bakat \"$nodeName\" terbuka!";
        } else {
            $_SESSION['skill_toast'] = "Bakat tidak dapat dibuka (SP kurang atau tier sebelumnya belum terbuka).";
        }
        header("Location: skills.php");
        exit();
    }

    // Reset SP: 50 Gold, semua pasif dikembalikan, actives ikut terkunci
    if (isset($_POST['reset_skill_points'])) {
        $stmt = $pdo->prepare("UPDATE users SET gold = gold - 50, spent_skill_points = 0, unlocked_skills = '[]' WHERE user_id = ? AND gold >= 50");
        $stmt->execute([$userId]);
        $_SESSION['skill_toast'] = $stmt->rowCount() > 0 ? "Skill Points direset. 50 Gold dipotong." : "Gold tidak cukup untuk reset (butuh 50).";
        header("Location: skills.php");
        exit();
    }

    // Active Skill harian (1x per hari, harus terbuka via unlock)
    if (isset($_POST['cast_active_skill'])) {
        $code = $_POST['cast_active_skill'] ?? '';
        $actives = $skillTree['actives'];
        $stmt = $pdo->prepare("SELECT unlocked_skills, gold, hp, max_hp, exp, level, `rank`, last_skill_cast_date FROM users WHERE user_id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $unlocked = json_decode($row['unlocked_skills'] ?? '[]', true) ?: [];
        $today = date('Y-m-d');
        $msg = '';
        if (!isset($actives[$code])) {
            $msg = 'Active Skill tidak dikenal.';
        } elseif (!in_array($code, $unlocked, true)) {
            $msg = 'Buka dulu node Active Skill ini di pohon bakat.';
        } elseif ($row['last_skill_cast_date'] === $today) {
            $msg = 'Active Skill hanya bisa dipakai 1x per hari (sudah dipakai hari ini).';
        } elseif ($code === 'vital_transmutation' && (int) $row['gold'] < 25) {
            $msg = 'Butuh 25 Gold untuk Vital Transmutation.';
        } else {
            if ($code === 'overdrive_surge') {
                $pdo->prepare("UPDATE users SET overdrive_until = DATE_ADD(NOW(), INTERVAL 2 HOUR) WHERE user_id = ?")->execute([$userId]);
                $msg = 'OVERDRIVE AKTIF: +50% EXP selama 2 jam!';
            } elseif ($code === 'vital_transmutation') {
                $pdo->prepare("UPDATE users SET gold = gold - 25, hp = LEAST(max_hp, hp + 40) WHERE user_id = ?")->execute([$userId]);
                $msg = 'Vital Transmutation: -25 Gold, +40 HP!';
            } elseif ($code === 'tactical_inspiration') {
                // +15 EXP lalu hitung ulang level & rank dari total EXP (atomic dalam transaksi FOR UPDATE)
                $newExp = (int) $row['exp'] + 15;
                $newLvl = calculateLevel($newExp);
                $newRank = calculateRank($newLvl);
                $pdo->prepare("UPDATE users SET exp = ?, level = ?, `rank` = ? WHERE user_id = ?")
                    ->execute([$newExp, $newLvl, $newRank, $userId]);
                if (function_exists('deal_dungeon_damage')) {
                    deal_dungeon_damage($pdo, $userId, 100, 'Tactical Inspiration');
                }
                $msg = 'Tactical Inspiration: 100 True Damage ke Boss minggu ini + 15 EXP!';
            }
            $pdo->prepare("UPDATE users SET last_skill_cast_date = ? WHERE user_id = ?")->execute([$today, $userId]);
        }
        $_SESSION['skill_toast'] = $msg;
        header("Location: skills.php");
        exit();
    }

    // Ganti Familiar aktif
    if (isset($_POST['select_companion'])) {
        $key = $_POST['select_companion'] ?? '';
        if (isset(game_companions()[$key])) {
            $pdo->prepare("UPDATE users SET active_companion = ? WHERE user_id = ?")->execute([$key, $userId]);
            $_SESSION['skill_toast'] = 'Familiar aktif diganti!';
        }
        header("Location: skills.php");
        exit();
    }
}

$pageTitle = 'Keahlian';
require_once __DIR__ . '/../includes/header.php';

// Ambil data progres skill berdasarkan kategori (1 query tunggal)
$categories = ['Intelligence', 'Physical', 'Health', 'Wealth', 'Social'];
$stat_data = array_fill_keys($categories, 0);
$maxProgress = 0;

$stmt = $pdo->prepare("SELECT skill_id, progress FROM skills WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userSkills = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($categories as $category) {
    $skillId = skill_id_for_category($category)[0];
    $progress = (int) ($userSkills[$skillId] ?? 0);
    $stat_data[$category] = $progress;
    if ($progress > $maxProgress) {
        $maxProgress = $progress;
    }
}

// Urutkan tampilan stat dari level tertinggi ke terendah untuk list
$sorted_stats = $stat_data;
arsort($sorted_stats);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Matriks Keahlian & Atribut</h1>
        <p class="page-subtitle">Status atribut karakter dan alokasi Skill Point (SP).</p>
    </div>
</div>

<!-- Main Radar Console Panel -->
<div class="bento-panel tactical-frame mx-auto mb-4" style="max-width: 980px;">
    <div class="sys-micro-tag"><span class="text-accent">SYS.MATRIX // ATTRIBUTE_RADAR</span> &bull; 5-AXIS TELEMETRY</div>
    <div class="row align-items-center g-4">
        <div class="col-md-5">
            <div class="panel-header mb-3">
                <h3 class="panel-title"><i class="fas fa-layer-group text-accent"></i> Ringkasan Poligon Atribut</h3>
                <span class="badge-status accent font-mono" style="font-size: 0.68rem;">5 PILAR AKTIF</span>
            </div>
            
            <div class="data-list">
                <?php foreach ($sorted_stats as $category => $value): 
                    $catInfo = game_categories()[$category] ?? ['icon' => 'fa-circle', 'color' => 'text-muted', 'label' => $category];
                    $attrLevel = (int) floor($value / 5) + 1;
                ?>
                    <div class="data-row" style="padding: 0.65rem 0;">
                        <div class="data-main">
                            <div style="width: 30px; height: 30px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                                <i class="fas <?php echo $catInfo['icon'] . ' ' . $catInfo['color']; ?>"></i>
                            </div>
                            <div>
                                <div class="data-title" style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($category); ?></div>
                                <div class="text-muted font-mono" style="font-size: 0.7rem;"><?php echo $value; ?>x aktivitas selesai</div>
                            </div>
                        </div>
                        <div class="data-actions">
                            <span class="badge-status accent font-mono">
                                Tingkat <?php echo $attrLevel; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="col-md-7 d-flex justify-content-center">
            <div style="width: 100%; max-width: 380px; aspect-ratio: 1/1;">
                <canvas id="radarChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Section: 5 Breakdown Attribute Cards (Bento Glass Grid) -->
<div class="panel-header mt-4 mb-3" style="border-bottom: 1px solid var(--border-hairline); padding-bottom: 0.6rem;">
    <div>
        <div class="sys-micro-tag">SYS.ATTRIBUTES // TELEMETRY</div>
        <h3 class="panel-title" style="font-size: 0.95rem; font-weight: 600;">
            <i class="fas fa-th-large text-accent me-1"></i> Parameter Lima Atribut Karakter
        </h3>
    </div>
    <span class="text-muted font-mono" style="font-size: 0.75rem;">Status alokasi dan progres per tingkat</span>
</div>

<div class="row g-3 mb-4">
    <?php 
    $catDescriptions = [
        'Intelligence' => 'Belajar, membaca, eksplorasi keahlian, pemrograman, dan penajaman wawasan intelektual.',
        'Physical' => 'Olahraga, latihan fisik, jalan kaki, ketahanan stamina, dan kebugaran tubuh.',
        'Health' => 'Pola tidur teratur, konsumsi air 2+ liter, nutrisi sehat, dan pemulihan energi.',
        'Wealth' => 'Pencatatan keuangan harian, menabung, investasi, dan disiplin pengelolaan aset.',
        'Social' => 'Relasi keluarga, komunikasi dengan sahabat, jejaring profesional, dan interaksi bermakna.'
    ];

    foreach ($categories as $category):
        $progress = $stat_data[$category] ?? 0;
        $attrLevel = (int) floor($progress / 5) + 1;
        $inLevelExp = $progress % 5;
        $pct = ($inLevelExp / 5) * 100;
        $catInfo = game_categories()[$category] ?? ['icon' => 'fa-circle', 'color' => 'text-accent', 'label' => $category];
        $desc = $catDescriptions[$category] ?? '';
    ?>
        <div class="col-md-4 col-sm-6">
            <div class="bento-panel spotlight-card holo-card h-100 d-flex flex-column justify-content-between p-3" style="border-radius: 14px;">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div style="width: 36px; height: 36px; border-radius: var(--radius); background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                            <i class="fas <?php echo $catInfo['icon'] . ' ' . $catInfo['color']; ?>"></i>
                        </div>
                        <span class="badge-status accent font-mono" style="font-size: 0.72rem;">
                            Tingkat <?php echo $attrLevel; ?>
                        </span>
                    </div>

                    <h4 style="font-size: 0.95rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--text-primary);">
                        <?php echo htmlspecialchars($catInfo['label']); ?>
                    </h4>
                    <p class="text-secondary mb-3" style="font-size: 0.78rem; line-height: 1.5;">
                        <?php echo htmlspecialchars($desc); ?>
                    </p>
                </div>

                <div>
                    <div class="d-flex justify-content-between align-items-center font-mono text-muted mb-1" style="font-size: 0.7rem;">
                        <span>Progres Fase: <?php echo $inLevelExp; ?>/5</span>
                        <span class="text-accent"><?php echo $progress * 10; ?> EXP Total</span>
                    </div>
                    <div class="vitals-track mb-3" style="height: 5px;">
                        <div class="vitals-fill" style="width: <?php echo $pct; ?>%; background-color: var(--accent);"></div>
                    </div>

                    <a href="habits.php?new_cat=<?php echo urlencode($category); ?>" class="btn-core btn-outline w-100 font-mono" style="font-size: 0.75rem; padding: 0.4rem 0.6rem;">
                        <i class="fas fa-plus me-1 text-accent"></i> + Buat Kebiasaan
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
// ============ Sovereign Expansion: state Skill Tree & Companion ============
$stmtMe = $pdo->prepare("SELECT level, gold, hp, max_hp, unlocked_skills, spent_skill_points, active_companion, companion_exp, overdrive_until, last_skill_cast_date FROM users WHERE user_id = ?");
$stmtMe->execute([(int) $_SESSION['user_id']]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);
$skillTreeData = game_skill_tree();
$unlockedNodes = json_decode($me['unlocked_skills'] ?? '[]', true) ?: [];
$spentPoints = (int) $me['spent_skill_points'];
$availableSP = game_available_skill_points((int) $me['level'], $spentPoints);
$companionsCatalog = game_companions();
$activeCompanionKey = isset($companionsCatalog[$me['active_companion']]) ? $me['active_companion'] : 'obsidian_wolf';
$activeCompanion = $companionsCatalog[$activeCompanionKey];
$bond = game_companion_level((int) $me['companion_exp']);
$bondPct = (int) round(($bond['exp_into_level'] / $bond['exp_for_next']) * 100);
$overdriveActive = !empty($me['overdrive_until']) && strtotime((string) $me['overdrive_until']) > time();
$skillToast = isset($_SESSION['skill_toast']) ? $_SESSION['skill_toast'] : '';
unset($_SESSION['skill_toast']);
?>

<?php if ($skillToast !== ''): ?>
    <div class="bento-panel mb-4 p-2 text-center" style="border-color: var(--accent-border); font-size: 0.85rem;">
        <i class="fas fa-wand-magic-sparkles text-accent me-1"></i> <?php echo htmlspecialchars($skillToast); ?>
    </div>
<?php endif; ?>

<!-- Panel Pohon Bakat Karakter (Skill Tree) -->
<div class="bento-panel tactical-frame mx-auto mb-4" style="max-width: 980px;">
    <div class="sys-micro-tag"><span class="text-accent">SYS.SKILL_TREE // SPECIALIZATION_BRANCHES</span></div>
    <div class="panel-header mb-3">
        <h3 class="panel-title"><i class="fas fa-sitemap text-accent"></i> Pohon Bakat Karakter</h3>
        <span class="badge-status accent font-mono">SP tersedia: <?php echo $availableSP; ?> &nbsp;•&nbsp; Level <?php echo (int) $me['level']; ?></span>
    </div>
    <div class="row g-3">
        <?php foreach ($skillTreeData['branches'] as $branchKey => $branch): ?>
            <div class="col-md-4">
                <div class="p-3 h-100" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.07); border-radius: 14px;">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fas <?php echo $branch['icon']; ?> text-accent"></i>
                        <strong style="font-size: 0.9rem;"><?php echo $branch['label']; ?></strong>
                    </div>
                    <?php foreach ($branch['nodes'] as $node):
                        $isUnlocked = in_array($node['code'], $unlockedNodes, true);
                        $nodeStyle = $isUnlocked
                            ? 'border: 1px solid var(--accent-border); background: var(--accent-dim);'
                            : 'border: 1px solid var(--border-hairline); background: rgba(255,255,255,0.02); opacity: 0.85;';
                    ?>
                        <div class="p-2 mb-2" style="<?php echo $nodeStyle; ?> border-radius: 10px;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div style="font-size: 0.82rem; font-weight: 600;">
                                        <?php echo $isUnlocked ? '<i class="fas fa-check text-accent me-1"></i>' : '<i class="fas fa-lock text-muted me-1" style="font-size: 0.7rem;"></i>'; ?><?php echo htmlspecialchars($node['name']); ?>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($node['desc']); ?></div>
                                </div>
                                <?php if ($isUnlocked): ?>
                                    <span class="badge-status accent font-mono" style="font-size: 0.62rem;">Aktif</span>
                                <?php else: ?>
                                    <form method="POST" action="skills.php" style="margin: 0;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="unlock_skill_node" value="<?php echo htmlspecialchars($node['code']); ?>">
                                        <button type="submit" class="btn-core btn-outline font-mono" style="font-size: 0.7rem; padding: 0.3rem 0.6rem;" <?php echo $availableSP < (int) $node['cost'] ? 'disabled' : ''; ?>>
                                            Buka (<?php echo (int) $node['cost']; ?> SP)
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-3 pt-2" style="border-top: 1px solid var(--border-hairline);">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="text-muted" style="font-size: 0.74rem;">
                <i class="fas fa-circle-info me-1"></i> SP tersedia = Level - 1 - SP terpakai. Reset SP mengembalikan seluruh pohon bakat.
            </div>
            <form method="POST" action="skills.php" style="margin: 0;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="reset_skill_points" value="1">
                <button type="submit" class="btn-core btn-outline" style="font-size: 0.76rem;" data-confirm="Reset seluruh Skill Points? Semua node pasif & aktif akan dikunci kembali (butuh 50 Gold)." data-confirm-title="Reset SP" data-confirm-variant="warning" data-confirm-btn="Reset">
                    <i class="fas fa-rotate-left me-1"></i> Reset SP (50 Gold)
                </button>
            </form>
        </div>
    </div>

    <div class="mt-3 pt-2" style="border-top: 1px solid var(--border-hairline);">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <strong style="font-size: 0.88rem;"><i class="fas fa-bolt text-warning me-1"></i> Active Skill Harian (1x per hari)</strong>
            <span class="font-mono text-muted" style="font-size: 0.72rem;">Terakhir cast: <?php echo htmlspecialchars((string) ($me['last_skill_cast_date'] ?: '-')); ?></span>
        </div>
        <div class="row g-2">
            <?php foreach ($skillTreeData['actives'] as $code => $act):
                $actUnlocked = in_array($code, $unlockedNodes, true);
                $castedToday = $me['last_skill_cast_date'] === date('Y-m-d');
            ?>
                <div class="col-md-4">
                    <div class="p-2 h-100" style="border: 1px solid <?php echo $actUnlocked ? 'var(--accent-border)' : 'var(--border-hairline)'; ?>; border-radius: 10px; background: rgba(255,255,255,0.02);">
                        <div style="font-size: 0.8rem; font-weight: 600;"><i class="fas <?php echo $act['icon']; ?> text-warning me-1"></i> <?php echo htmlspecialchars($act['name']); ?> <span class="font-mono text-muted" style="font-size: 0.66rem;">(<?php echo (int) $act['cost']; ?> SP)</span></div>
                        <div class="text-muted" style="font-size: 0.68rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($act['desc']); ?></div>
                        <form method="POST" action="skills.php" style="margin: 0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="cast_active_skill" value="<?php echo htmlspecialchars($code); ?>">
                            <button type="submit" class="btn-core btn-accent w-100" style="font-size: 0.74rem;" <?php echo (!$actUnlocked || $castedToday) ? 'disabled' : ''; ?>>
                                <?php echo $actUnlocked ? ($castedToday ? 'Sudah Dipakai Hari Ini' : 'Jalankan Skill') : 'Terkunci (unlock di atas)'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($overdriveActive): ?>
            <div class="mt-2 badge-status warning font-mono"><i class="fas fa-bolt me-1"></i> OVERDRIVE aktif sampai <?php echo htmlspecialchars((string) $me['overdrive_until']); ?> (+50% EXP)</div>
        <?php endif; ?>
    </div>
</div>

<!-- Panel Astral Companion Sanctum -->
<div class="bento-panel tactical-frame mx-auto mb-4" style="max-width: 980px;">
    <div class="sys-micro-tag"><span class="text-accent">ASTRAL.SANCTUM // FAMILIAR_BOND</span> &bull; ACTIVE LINK</div>
    <div class="panel-header mb-3">
        <h3 class="panel-title"><i class="fas fa-paw text-accent"></i> Astral Companion Sanctum</h3>
        <span class="badge-status font-mono"><?php echo count($companionsCatalog); ?> Familiar terdaftar</span>
    </div>
    <div class="row g-4 align-items-center">
        <div class="col-md-5 text-center">
            <?php
            $stageScale = ['Hatchling' => 90, 'Awakened' => 110, 'Sovereign' => 130];
            $svgSize = $stageScale[$bond['stage']] ?? 90;
            $aura = $bond['stage'] === 'Sovereign' ? 0.55 : ($bond['stage'] === 'Awakened' ? 0.35 : 0.18);
            $hasActiveImg = !empty($activeCompanion['image']) && file_exists(__DIR__ . '/../' . $activeCompanion['image']);
            ?>
            <?php if ($hasActiveImg): ?>
                <div class="mb-2 position-relative d-inline-block">
                    <img src="../<?php echo htmlspecialchars($activeCompanion['image']); ?>" alt="<?php echo htmlspecialchars($activeCompanion['name']); ?>" style="width: <?php echo $svgSize; ?>px; height: <?php echo $svgSize; ?>px; object-fit: contain; filter: drop-shadow(0 0 16px <?php echo $activeCompanion['accent']; ?>66);">
                </div>
            <?php else: ?>
                <svg width="<?php echo $svgSize; ?>" height="<?php echo $svgSize; ?>" viewBox="0 0 140 140" style="overflow: visible;">
                    <circle cx="70" cy="70" r="<?php echo $svgSize / 2 - 6; ?>" fill="none" stroke="<?php echo $activeCompanion['accent']; ?>" stroke-opacity="<?php echo $aura; ?>" stroke-width="3" stroke-dasharray="6 5">
                        <animateTransform attributeName="transform" type="rotate" from="0 70 70" to="360 70 70" dur="14s" repeatCount="indefinite"/>
                    </circle>
                    <circle cx="70" cy="74" r="34" fill="<?php echo $activeCompanion['accent']; ?>" fill-opacity="0.25" stroke="<?php echo $activeCompanion['accent']; ?>" stroke-width="2"/>
                    <circle cx="59" cy="66" r="5" fill="#fafafa"/>
                    <circle cx="81" cy="66" r="5" fill="#fafafa"/>
                    <path d="M 62 84 Q 70 92 78 84" stroke="#fafafa" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                    <?php if ($bond['stage'] !== 'Hatchling'): ?>
                        <path d="M 40 48 L 52 58 M 100 48 L 88 58" stroke="<?php echo $activeCompanion['accent']; ?>" stroke-width="3" stroke-linecap="round"/>
                    <?php endif; ?>
                    <?php if ($bond['stage'] === 'Sovereign'): ?>
                        <path d="M 54 34 L 62 26 L 70 34 L 78 26 L 86 34" fill="none" stroke="#f59e0b" stroke-width="3" stroke-linejoin="round"/>
                    <?php endif; ?>
                </svg>
            <?php endif; ?>
            <div class="mt-2" style="font-size: 0.92rem; font-weight: 600; color: <?php echo $activeCompanion['accent']; ?>;">
                <?php echo htmlspecialchars($activeCompanion['name']); ?>
            </div>
            <div class="font-mono text-muted" style="font-size: 0.72rem;">
                <?php echo $bond['stage']; ?> • Bond Lv.<?php echo $bond['bond_level']; ?> • Bonus x<?php echo number_format($bond['bonus_scale'], 2); ?>
            </div>
            <div class="vitals-track mt-2 mx-auto" style="max-width: 220px; height: 6px;">
                <div class="vitals-fill" style="width: <?php echo $bondPct; ?>%; background-color: <?php echo $activeCompanion['accent']; ?>;"></div>
            </div>
            <div class="font-mono text-muted mt-1" style="font-size: 0.66rem;">Bond EXP <?php echo (int) $me['companion_exp']; ?> (+<?php echo $bond['exp_into_level']; ?>/<?php echo $bond['exp_for_next']; ?> ke level berikutnya)</div>
            <div class="text-muted mt-2" style="font-size: 0.72rem;"><i class="fas fa-star me-1" style="color: <?php echo $activeCompanion['accent']; ?>;"></i> <?php echo htmlspecialchars($activeCompanion['bonus']); ?></div>
        </div>
        <div class="col-md-7">
            <div class="text-muted mb-2 font-mono" style="font-size: 0.74rem;">UNIT FAMILIAR // DAFTAR AKTIF:</div>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($companionsCatalog as $key => $comp): 
                    $isActive = $key === $activeCompanionKey; 
                    $hasCompImg = !empty($comp['image']) && file_exists(__DIR__ . '/../' . $comp['image']);
                ?>
                    <div class="p-2 d-flex justify-content-between align-items-center" style="border: 1px solid <?php echo $isActive ? $comp['accent'] : 'var(--border-hairline)'; ?>; border-radius: 8px; background: rgba(255,255,255,0.02);">
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($hasCompImg): ?>
                                <img src="../<?php echo htmlspecialchars($comp['image']); ?>" alt="" style="width: 24px; height: 24px; object-fit: contain;">
                            <?php else: ?>
                                <i class="fas <?php echo $comp['icon']; ?>" style="color: <?php echo $comp['accent']; ?>;"></i>
                            <?php endif; ?>
                            <div>
                                <div style="font-size: 0.82rem; font-weight: 600;"><?php echo htmlspecialchars($comp['name']); ?><?php echo $isActive ? ' <span class="badge-status accent font-mono" style="font-size: 0.6rem;">Aktif</span>' : ''; ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($comp['bonus']); ?></div>
                            </div>
                        </div>
                        <?php if (!$isActive): ?>
                            <form method="POST" action="skills.php" style="margin: 0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="select_companion" value="<?php echo htmlspecialchars($key); ?>">
                                <button type="submit" class="btn-core btn-outline" style="font-size: 0.72rem; padding: 0.3rem 0.7rem;">Panggil</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" integrity="sha384-vsrfeLOOY6KuIYKDlmVH5UiBmgIdB1oEf7p01YgWHuqmOHfZr374+odEv96n9tNC" crossorigin="anonymous"></script>
<script>
    const ctxRadar = document.getElementById('radarChart').getContext('2d');
    const maxProgress = <?php echo $maxProgress > 0 ? $maxProgress : 5; ?>; 

    Chart.defaults.color = '#71717a';
    Chart.defaults.font.family = "'Geist', sans-serif";

    new Chart(ctxRadar, {
        type: 'radar',
        data: {
            labels: <?php echo json_encode(array_keys($stat_data)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($stat_data)); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.14)',
                borderColor: '#10b981',
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#09090b',
                pointHoverBackgroundColor: '#fafafa',
                pointHoverBorderColor: '#10b981',
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                r: {
                    min: 0,
                    max: maxProgress,
                    angleLines: { color: 'rgba(255, 255, 255, 0.08)' },
                    grid: { color: 'rgba(255, 255, 255, 0.08)', circular: true },
                    pointLabels: {
                        font: { size: 11, weight: '600', family: "'Geist', sans-serif" },
                        color: '#d4d4d8'
                    },
                    ticks: {
                        stepSize: Math.max(1, Math.ceil(maxProgress / 4)),
                        display: false 
                    }
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
                        label: function(context) { return 'Penyelesaian: ' + context.parsed.r + 'x'; }
                    }
                }
            }
        }
    });
</script>

<?php require_once("../includes/footer.php"); ?>
