<?php
// pages/journal.php: Jurnal Taktis & Mood/Energy Tracker (Sovereign Expansion v3.0)
require_once __DIR__ . '/../config/database.php';
enforce_login();

$userId = (int) $_SESSION['user_id'];

// ============ POST handlers (sebelum output) ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    // Simpan / update entri jurnal
    if (isset($_POST['save_entry'])) {
        $entryId = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $entryDate = $_POST['entry_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) $entryDate = date('Y-m-d');
        $mood = max(1, min(5, (int) ($_POST['mood_score'] ?? 3)));
        $energy = max(1, min(5, (int) ($_POST['energy_score'] ?? 3)));
        $tags = trim($_POST['tags'] ?? '');
        if (mb_strlen($tags) > 120) $tags = mb_substr($tags, 0, 120);
        if (mb_strlen($title) > 150) $title = mb_substr($title, 0, 150);

        if ($title === '' || $content === '') {
            $_SESSION['journal_toast'] = 'Judul dan isi catatan wajib diisi.';
            header("Location: journal.php" . ($entryId > 0 ? '?edit=' . $entryId : ''));
            exit();
        }

        if ($entryId > 0) {
            // Edit: hanya milik sendiri; Wisdom Bonus tidak dihitung ulang
            $pdo->prepare("UPDATE journal_entries SET title = ?, content = ?, entry_date = ?, mood_score = ?, energy_score = ?, tags = ? WHERE entry_id = ? AND user_id = ?")
                ->execute([$title, $content, $entryDate, $mood, $energy, $tags, $entryId, $userId]);
            $_SESSION['journal_toast'] = 'Catatan diperbarui.';
        } else {
            // Entri baru: Wisdom Bonus +10 EXP & +5 Gold untuk entri PERTAMA di hari tsb
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM journal_entries WHERE user_id = ? AND entry_date = ?");
            $stmtCheck->execute([$userId, $entryDate]);
            $firstToday = ((int) $stmtCheck->fetchColumn()) === 0;
            $expAwarded = $firstToday ? 1 : 0;

            $pdo->prepare("INSERT INTO journal_entries (user_id, entry_date, title, content, mood_score, energy_score, tags, exp_awarded) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$userId, $entryDate, $title, $content, $mood, $energy, $tags !== '' ? $tags : null, $expAwarded]);

            if ($firstToday) {
                $stmtUser = $pdo->prepare("SELECT exp, gold, level, `rank` FROM users WHERE user_id = ? FOR UPDATE");
                $stmtUser->execute([$userId]);
                $u = $stmtUser->fetch(PDO::FETCH_ASSOC);
                $newExp = (int) $u['exp'] + 10;
                $newGold = (int) $u['gold'] + 5;
                $newLvl = calculateLevel($newExp);
                $newRank = calculateRank($newLvl);
                $pdo->prepare("UPDATE users SET exp = ?, gold = ?, level = ?, `rank` = ? WHERE user_id = ?")
                    ->execute([$newExp, $newGold, $newLvl, $newRank, $userId]);
                $_SESSION['journal_toast'] = 'Wisdom Bonus: +10 EXP & +5 Gold untuk refleksi pertama hari ini!';
            } else {
                $_SESSION['journal_toast'] = 'Catatan tersimpan.';
            }
            // Serang Dungeon Boss mingguan (+25 DMG)
            if (function_exists('deal_dungeon_damage')) {
                deal_dungeon_damage($pdo, $userId, 25, 'Jurnal: ' . $title);
            }
        }
        header("Location: journal.php");
        exit();
    }

    // Hapus entri (milik sendiri)
    if (isset($_POST['delete_entry'])) {
        $entryId = (int) ($_POST['entry_id'] ?? 0);
        if ($entryId > 0) {
            $pdo->prepare("DELETE FROM journal_entries WHERE entry_id = ? AND user_id = ?")->execute([$entryId, $userId]);
            $_SESSION['journal_toast'] = 'Catatan dihapus.';
        }
        header("Location: journal.php");
        exit();
    }
}

$pageTitle = 'Jurnal';
require_once __DIR__ . '/../includes/header.php';

// ============ Data ============
$editEntry = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM journal_entries WHERE entry_id = ? AND user_id = ?");
    $stmt->execute([(int) $_GET['edit'], $userId]);
    $editEntry = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Filter kata kunci (judul/isi) & tag
$keyword = trim($_GET['q'] ?? '');
$tagFilter = trim($_GET['tag'] ?? '');
$sql = "SELECT * FROM journal_entries WHERE user_id = ?";
$params = [$userId];
if ($keyword !== '') {
    $sql .= " AND (title LIKE ? OR content LIKE ?)";
    $params[] = '%' . $keyword . '%';
    $params[] = '%' . $keyword . '%';
}
if ($tagFilter !== '') {
    $sql .= " AND FIND_IN_SET(?, REPLACE(tags, ', ', ','))";
    $params[] = $tagFilter;
}
$sql .= " ORDER BY entry_date DESC, entry_id DESC LIMIT 60";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Analitik 14 hari terakhir: rata-rata mood & energi + korelasi dengan habit/quest selesai
$stmtA = $pdo->prepare("
    SELECT entry_date, AVG(mood_score) AS avg_mood, AVG(energy_score) AS avg_energy, COUNT(*) AS entries
    FROM journal_entries
    WHERE user_id = ? AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY entry_date
");
$stmtA->execute([$userId]);
$byDay = [];
foreach ($stmtA->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $byDay[$r['entry_date']] = $r;
}
$stmtC = $pdo->prepare("
    SELECT completion_date AS d, COUNT(*) AS c FROM habit_logs
    WHERE user_id = ? AND status = 'Completed' AND completion_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY completion_date
");
$stmtC->execute([$userId]);
$habitByDay = [];
foreach ($stmtC->fetchAll(PDO::FETCH_KEY_PAIR) as $d => $c) $habitByDay[$d] = (int) $c;
$stmtQ = $pdo->prepare("
    SELECT DATE(COALESCE(completed_at, completion_date, created_at)) AS d, COUNT(*) AS c FROM quest_logs
    WHERE user_id = ? AND status = 'Completed' AND DATE(COALESCE(completed_at, completion_date, created_at)) >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY d
");
$stmtQ->execute([$userId]);
foreach ($stmtQ->fetchAll(PDO::FETCH_KEY_PAIR) as $d => $c) {
    $habitByDay[$d] = ($habitByDay[$d] ?? 0) + (int) $c;
}
$days14 = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days14[] = [
        'date' => $d,
        'mood' => $byDay[$d]['avg_mood'] ? (float) $byDay[$d]['avg_mood'] : null,
        'energy' => $byDay[$d]['avg_energy'] ? (float) $byDay[$d]['avg_energy'] : null,
        'done' => $habitByDay[$d] ?? 0,
    ];
}
$moodVals = array_filter(array_column($days14, 'mood'), fn($v) => $v !== null);
$energyVals = array_filter(array_column($days14, 'energy'), fn($v) => $v !== null);
$avgMood = !empty($moodVals) ? round(array_sum($moodVals) / count($moodVals), 1) : null;
$avgEnergy = !empty($energyVals) ? round(array_sum($energyVals) / count($energyVals), 1) : null;
// Korelasi Pearson sederhana antara rata-rata mood+energi harian dan jumlah selesaian
$pairs = [];
foreach ($days14 as $row) {
    if ($row['mood'] !== null && $row['energy'] !== null) {
        $pairs[] = [($row['mood'] + $row['energy']) / 2, $row['done']];
    }
}
$correlation = null;
if (count($pairs) >= 3) {
    $n = count($pairs);
    $sx = array_sum(array_column($pairs, 0)); $sy = array_sum(array_column($pairs, 1));
    $sxx = 0; $syy = 0; $sxy = 0;
    foreach ($pairs as $p) { $sxx += $p[0] * $p[0]; $syy += $p[1] * $p[1]; $sxy += $p[0] * $p[1]; }
    $den = sqrt(($n * $sxx - $sx * $sx) * ($n * $syy - $sy * $sy));
    if ($den > 0) $correlation = round(($n * $sxy - $sx * $sy) / $den, 2);
}
$journalToast = isset($_SESSION['journal_toast']) ? $_SESSION['journal_toast'] : '';
unset($_SESSION['journal_toast']);
?>

<div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
        <h1 class="page-title"><i class="fas fa-book text-accent me-1"></i> Jurnal Taktis</h1>
        <p class="page-subtitle">Refleksi harian mood dan energi. Entri pertama tiap hari memberi Wisdom Bonus +10 EXP &amp; +5 Gold.</p>
    </div>
    <a href="dungeon.php" class="btn-core btn-outline font-mono" style="font-size: 0.76rem;"><i class="fas fa-dungeon text-danger me-1"></i> Boss Mingguan</a>
</div>

<?php if ($journalToast !== ''): ?>
    <div class="bento-panel mb-4 p-2 text-center" style="border-color: var(--accent-border); font-size: 0.85rem;">
        <i class="fas fa-feather text-accent me-1"></i> <?php echo htmlspecialchars($journalToast); ?>
    </div>
<?php endif; ?>

<!-- Analitik 14 Hari -->
<div class="bento-panel tactical-frame mb-4">
    <div class="sys-micro-tag">SYS.ANALYTICS // MOOD_ENERGY_CORRELATION</div>
    <div class="panel-header">
        <h3 class="panel-title"><i class="fas fa-chart-line text-accent"></i> Analitik 14 Hari Terakhir</h3>
        <div class="d-flex flex-wrap gap-2 font-mono" style="font-size: 0.72rem;">
            <span class="badge-status accent">Mood rata-rata: <?php echo $avgMood ?? '-'; ?>/5</span>
            <span class="badge-status warning">Energi rata-rata: <?php echo $avgEnergy ?? '-'; ?>/5</span>
            <span class="badge-status">Korelasi mood↔produksi: <?php echo $correlation !== null ? number_format($correlation, 2) : '-'; ?></span>
        </div>
    </div>
    <div class="d-flex gap-1 flex-wrap" style="padding: 0.25rem 0;">
        <?php foreach ($days14 as $row):
            $m = $row['mood'];
            $e = $row['energy'];
            $moodColor = $m === null ? 'var(--bg-surface-hover)' : ($m >= 4 ? 'rgba(16,185,129,0.75)' : ($m >= 3 ? 'rgba(16,185,129,0.4)' : 'rgba(244,63,94,0.5)'));
            $enColor = $e === null ? 'transparent' : ($e >= 4 ? '#f59e0b' : ($e >= 3 ? 'rgba(245,158,11,0.6)' : 'rgba(245,158,11,0.3)'));
            $tip = $row['date'] . ' • ' . ($m === null ? 'Tanpa jurnal' : 'Mood ' . round($m, 1) . '/5, Energi ' . round($e, 1) . '/5') . ' • ' . $row['done'] . ' selesai';
        ?>
            <div style="flex: 1; min-width: 18px; text-align: center;" title="<?php echo htmlspecialchars($tip); ?>">
                <div style="height: 22px; border-radius: 4px; background: <?php echo $moodColor; ?>; border: 1px solid var(--border-hairline);"></div>
                <div style="height: 8px; border-radius: 3px; background: <?php echo $enColor; ?>; margin-top: 2px;"></div>
                <div class="font-mono text-muted" style="font-size: 0.56rem; margin-top: 2px;"><?php echo date('d', strtotime($row['date'])); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-muted mt-2" style="font-size: 0.7rem;">Bar atas = Mood (hijau baik, merah rendah), bar bawah = Energi (kuning). Angka = jumlah Habit+Misi selesai hari itu.</div>
</div>

<!-- Form tulis / edit -->
<div class="bento-panel mb-4">
    <div class="panel-header">
        <h3 class="panel-title"><i class="fas fa-pen-fancy text-accent"></i> <?php echo $editEntry ? 'Edit Catatan' : 'Tulis Refleksi'; ?></h3>
        <?php if ($editEntry): ?><a href="journal.php" class="btn-core btn-ghost" style="font-size: 0.72rem;">Batal edit</a><?php endif; ?>
    </div>
    <form method="POST" action="journal.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="entry_id" value="<?php echo (int) ($editEntry['entry_id'] ?? 0); ?>">
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="jr_title">Judul</label>
                <input type="text" class="form-control" id="jr_title" name="title" maxlength="150" required
                       value="<?php echo htmlspecialchars($editEntry['title'] ?? ''); ?>" placeholder="mis., Hari produktif setelah seminggu jalan lambat">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="jr_date">Tanggal</label>
                <input type="date" class="form-control font-mono" id="jr_date" name="entry_date" required
                       value="<?php echo htmlspecialchars($editEntry['entry_date'] ?? date('Y-m-d')); ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="jr_tags">Tag (pisah koma)</label>
                <input type="text" class="form-control font-mono" id="jr_tags" name="tags" maxlength="120"
                       value="<?php echo htmlspecialchars($editEntry['tags'] ?? ''); ?>" placeholder="kerja, kesehatan">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="jr_content">Isi refleksi</label>
            <textarea class="form-control" id="jr_content" name="content" rows="5" required placeholder="Apa yang terjadi hari ini? Apa yang terasa berat? Apa satu hal yang ingin dipertahankan besok?"><?php echo htmlspecialchars($editEntry['content'] ?? ''); ?></textarea>
        </div>
        <div class="row g-3 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label" style="font-size: 0.78rem;">Mood (1-5)</label>
                <div class="d-flex gap-1">
                    <?php for ($i = 1; $i <= 5; $i++):
                        $checked = (int) ($editEntry['mood_score'] ?? 3) === $i; ?>
                        <label class="btn-core <?php echo $checked ? 'btn-accent' : 'btn-outline'; ?>" style="min-width: 42px; cursor: pointer;">
                            <input type="radio" name="mood_score" value="<?php echo $i; ?>" <?php echo $checked ? 'checked' : ''; ?> style="display: none;" onchange="this.closest('.d-flex').querySelectorAll('label').forEach(l=>l.className=l.className.replace('btn-accent','btn-outline')); this.closest('label').className=this.closest('label').className.replace('btn-outline','btn-accent');">
                            <?php echo $i; ?>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label" style="font-size: 0.78rem;">Energi (1-5)</label>
                <div class="d-flex gap-1">
                    <?php for ($i = 1; $i <= 5; $i++):
                        $checked = (int) ($editEntry['energy_score'] ?? 3) === $i; ?>
                        <label class="btn-core <?php echo $checked ? 'btn-accent' : 'btn-outline'; ?>" style="min-width: 42px; cursor: pointer;">
                            <input type="radio" name="energy_score" value="<?php echo $i; ?>" <?php echo $checked ? 'checked' : ''; ?> style="display: none;" onchange="this.closest('.d-flex').querySelectorAll('label').forEach(l=>l.className=l.className.replace('btn-accent','btn-outline')); this.closest('label').className=this.closest('label').className.replace('btn-outline','btn-accent');">
                            <?php echo $i; ?>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                <button type="submit" name="save_entry" value="1" class="btn-core btn-primary" style="padding: 0.55rem 1.3rem;">
                    <i class="fas fa-feather-pointed me-1"></i> <?php echo $editEntry ? 'Simpan Perubahan' : 'Simpan Refleksi'; ?>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Riwayat + filter -->
<div class="bento-panel mb-4">
    <div class="panel-header flex-wrap gap-2">
        <h3 class="panel-title"><i class="fas fa-clock-rotate-left text-accent"></i> Riwayat Catatan</h3>
        <form method="GET" action="journal.php" class="d-flex gap-2" style="font-size: 0.78rem;">
            <input type="text" name="q" class="form-control form-control-sm font-mono" placeholder="Cari judul/isi..." style="min-width: 160px;" value="<?php echo htmlspecialchars($keyword); ?>">
            <input type="text" name="tag" class="form-control form-control-sm font-mono" placeholder="tag..." style="min-width: 90px;" value="<?php echo htmlspecialchars($tagFilter); ?>">
            <button type="submit" class="btn-core btn-outline" style="font-size: 0.74rem;"><i class="fas fa-search"></i></button>
            <?php if ($keyword !== '' || $tagFilter !== ''): ?><a href="journal.php" class="btn-core btn-ghost" style="font-size: 0.74rem;">Reset</a><?php endif; ?>
        </form>
    </div>
    <?php if (empty($entries)): ?>
        <div class="text-center py-4 text-muted">
            <i class="fas fa-book-open fa-2x mb-3" style="opacity: 0.3;"></i>
            <p class="mb-0">Belum ada catatan<?php echo ($keyword !== '' || $tagFilter !== '') ? ' yang cocok dengan filter' : ''; ?>. Mulai refleksi pertamamu!</p>
        </div>
    <?php else: ?>
        <div class="data-list">
            <?php foreach ($entries as $en): ?>
                <div class="data-row" style="padding: 0.8rem 0;">
                    <div class="data-main" style="align-items: flex-start;">
                        <div style="width: 34px; height: 34px; border-radius: var(--radius); background: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0;">
                            <?php echo (int) $en['mood_score'] >= 4 ? '😄' : ((int) $en['mood_score'] == 3 ? '🙂' : '😞'); ?>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div class="data-title d-flex align-items-center flex-wrap gap-2">
                                <?php echo htmlspecialchars($en['title']); ?>
                                <?php if ((int) $en['exp_awarded'] === 1): ?>
                                    <span class="badge-status accent font-mono" style="font-size: 0.6rem;">+10 EXP Wisdom</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size: 0.76rem; white-space: pre-wrap; word-break: break-word; margin-top: 2px;"><?php echo htmlspecialchars(mb_substr($en['content'], 0, 220)) . (mb_strlen($en['content']) > 220 ? '…' : ''); ?></div>
                            <div class="data-meta font-mono" style="font-size: 0.68rem;">
                                <span><?php echo indo_short_date($en['entry_date']); ?></span>
                                <span>Mood <?php echo (int) $en['mood_score']; ?>/5</span>
                                <span>Energi <?php echo (int) $en['energy_score']; ?>/5</span>
                                <?php if (!empty($en['tags'])):
                                    foreach (array_filter(array_map('trim', explode(',', (string) $en['tags']))) as $tg): ?>
                                        <a href="journal.php?tag=<?php echo urlencode($tg); ?>" class="badge-status font-mono" style="font-size: 0.62rem; text-decoration: none;"><?php echo htmlspecialchars($tg); ?></a>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="data-actions">
                        <a href="journal.php?edit=<?php echo (int) $en['entry_id']; ?>" class="btn-core btn-ghost btn-action-secondary" style="padding: 0.35rem 0.55rem;" title="Edit" aria-label="Edit catatan"><i class="fas fa-pencil-alt"></i></a>
                        <form method="POST" action="journal.php" style="margin: 0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="entry_id" value="<?php echo (int) $en['entry_id']; ?>">
                            <button type="submit" name="delete_entry" value="1" class="btn-core btn-ghost btn-action-secondary text-danger" style="padding: 0.35rem 0.55rem;" title="Hapus" aria-label="Hapus catatan" data-confirm="Hapus catatan ini secara permanen?" data-confirm-title="Hapus Catatan" data-confirm-variant="danger" data-confirm-btn="Hapus"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once("../includes/footer.php"); ?>
