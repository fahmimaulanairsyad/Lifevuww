<?php
require_once __DIR__ . '/../config/database.php';
enforce_login();
$uid = (int) $_SESSION['user_id'];
$error = '';
$cats = game_categories();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    try {
        $pdo->beginTransaction();
        $action = $_POST['action'] ?? '';
        if ($action === 'goal') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $category = (string) ($_POST['category'] ?? 'Intelligence');
            if ($title === '' || mb_strlen($title) > 180 || !isset($cats[$category])) throw new RuntimeException('Isi tujuan maksimal 180 karakter dan pilih atribut.');
            $pdo->prepare('INSERT INTO life_goals (user_id,title,category) VALUES (?,?,?)')->execute([$uid,$title,$category]);
        } elseif ($action === 'break') {
            $start = (string) ($_POST['start_date'] ?? ''); $end = (string) ($_POST['end_date'] ?? '');
            foreach ([$start,$end] as $date) {
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new RuntimeException('Tanggal jeda tidak valid.');
            }
            if ($start < date('Y-m-d',strtotime('+1 day')) || $end < $start || $end > date('Y-m-d',strtotime($start.' +30 days'))) throw new RuntimeException('Jeda dimulai paling cepat esok hari, maksimal 31 hari.');
            $overlap=$pdo->prepare('SELECT COUNT(*) FROM planned_breaks WHERE user_id=? AND start_date<=? AND end_date>=?');$overlap->execute([$uid,$end,$start]);
            if((int)$overlap->fetchColumn()>0)throw new RuntimeException('Gabungkan tanggal dengan jeda yang sudah ada agar tidak terjadi jadwal tumpang tindih.');
            $pdo->prepare('INSERT INTO planned_breaks (user_id,start_date,end_date) VALUES (?,?,?)')->execute([$uid,$start,$end]);
        } elseif ($action === 'cancel_break') {
            $pdo->prepare('DELETE FROM planned_breaks WHERE break_id=? AND user_id=? AND start_date>CURDATE()')->execute([(int)($_POST['break_id']??0),$uid]);
        } else {
            $goalId = (int) ($_POST['goal_id'] ?? 0);
            $owned = $pdo->prepare('SELECT goal_id FROM life_goals WHERE goal_id=? AND user_id=? FOR UPDATE'); $owned->execute([$goalId,$uid]);
            if (!$owned->fetchColumn()) throw new RuntimeException('Tujuan tidak ditemukan.');
            if ($action === 'goal_date') {
                $target=trim((string)($_POST['target_date']??''));
                $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$target);
                if($target!==''&&(!$parsed||$parsed->format('Y-m-d')!==$target))throw new RuntimeException('Tanggal tujuan tidak valid.');
                $pdo->prepare('UPDATE life_goals SET target_date=? WHERE goal_id=? AND user_id=?')->execute([$target?:null,$goalId,$uid]);
            } elseif ($action === 'milestone') {
                $title=trim((string)($_POST['title']??''));
                if ($title==='' || mb_strlen($title)>180) throw new RuntimeException('Isi langkah maksimal 180 karakter.');
                $pdo->prepare('INSERT INTO goal_milestones (goal_id,title) VALUES (?,?)')->execute([$goalId,$title]);
            } elseif ($action === 'delete_link') {
                $kind=(string)($_POST['kind']??''); $aid=(int)($_POST['activity_id']??0);
                if (!in_array($kind,['habit','quest'],true)) throw new RuntimeException('Jenis aktivitas tidak valid.');
                $lockLink=$pdo->prepare('SELECT advanced_at FROM goal_activity_progress WHERE user_id=? AND kind=? AND activity_id=? AND goal_id=? FOR UPDATE');$lockLink->execute([$uid,$kind,$aid,$goalId]);$linkState=$lockLink->fetch(PDO::FETCH_ASSOC);
                if($linkState && $linkState['advanced_at']!==null)throw new RuntimeException('Tautan yang sudah tercapai tetap disimpan sebagai jejak progres.');
                $pdo->prepare('DELETE FROM goal_activity_progress WHERE user_id=? AND kind=? AND activity_id=? AND goal_id=? AND advanced_at IS NULL')->execute([$uid,$kind,$aid,$goalId]);
                $pdo->prepare('DELETE FROM goal_activities WHERE goal_id=? AND kind=? AND activity_id=?')->execute([$goalId,$kind,$aid]);
            } elseif ($action === 'toggle') {
                $pdo->prepare('UPDATE goal_milestones SET completed=? WHERE milestone_id=? AND goal_id=?')->execute([(int)($_POST['completed']??0) === 1 ? 1 : 0,(int)($_POST['milestone_id']??0),$goalId]);
            } elseif ($action === 'link') {
                $kind=$_POST['kind']??''; $aid=(int)($_POST['activity_id']??0);
                if (!in_array($kind,['habit','quest'],true)) throw new RuntimeException('Jenis aktivitas tidak valid.');
                $table=$kind==='habit'?'habits':'quests'; $key=$kind==='habit'?'habit_id':'quest_id';
                $ownedActivity=$pdo->prepare("SELECT $key FROM $table WHERE $key=? AND user_id=?");$ownedActivity->execute([$aid,$uid]);
                if (!$ownedActivity->fetchColumn()) throw new RuntimeException('Aktivitas tidak ditemukan.');
                $milestoneId=(int)($_POST['milestone_id']??0);
                $ownedMilestone=$pdo->prepare('SELECT milestone_id FROM goal_milestones WHERE milestone_id=? AND goal_id=? AND completed=0');
                $ownedMilestone->execute([$milestoneId,$goalId]);
                if (!$ownedMilestone->fetchColumn()) throw new RuntimeException('Pilih langkah yang belum selesai dan milik tujuan ini.');
                $milestoneOwner=$pdo->prepare('SELECT kind,activity_id FROM goal_activity_progress WHERE user_id=? AND goal_id=? AND milestone_id=?');
                $milestoneOwner->execute([$uid,$goalId,$milestoneId]);
                $existingOwner=$milestoneOwner->fetch(PDO::FETCH_ASSOC);
                if ($existingOwner && ($existingOwner['kind'] !== $kind || (int)$existingOwner['activity_id'] !== $aid)) throw new RuntimeException('Setiap langkah hanya dapat ditautkan ke satu aktivitas.');
                $existingLink=$pdo->prepare('SELECT advanced_at FROM goal_activity_progress WHERE user_id=? AND kind=? AND activity_id=? AND goal_id=?');
                $existingLink->execute([$uid,$kind,$aid,$goalId]);
                $oldLink=$existingLink->fetch(PDO::FETCH_ASSOC);
                if ($oldLink && $oldLink['advanced_at'] !== null) throw new RuntimeException('Tautan ini sudah mencapai langkahnya. Buat langkah baru untuk aktivitas lain.');
                $pdo->prepare('INSERT IGNORE INTO goal_activities (goal_id,kind,activity_id) VALUES (?,?,?)')->execute([$goalId,$kind,$aid]);
                $pdo->prepare('INSERT INTO goal_activity_progress (user_id,kind,activity_id,goal_id,milestone_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE milestone_id=IF(advanced_at IS NULL,VALUES(milestone_id),milestone_id)')->execute([$uid,$kind,$aid,$goalId,$milestoneId]);
            }
        }
        $pdo->commit();
        header('Location: journey.php');exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Journey save failed: '.$e->getMessage());
        $error='Perubahan belum tersimpan. Periksa apakah langkah sudah ditautkan ke aktivitas lain, lalu coba lagi.';
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error=$e->getMessage();
    }
}
$goalsStmt=$pdo->prepare('SELECT * FROM life_goals WHERE user_id=? ORDER BY goal_id DESC');$goalsStmt->execute([$uid]);$goals=$goalsStmt->fetchAll();
$habitsStmt=$pdo->prepare('SELECT habit_id,habit_name FROM habits WHERE user_id=?');$habitsStmt->execute([$uid]);$habits=$habitsStmt->fetchAll();
$questsStmt=$pdo->prepare('SELECT quest_id,quest_name FROM quests WHERE user_id=?');$questsStmt->execute([$uid]);$quests=$questsStmt->fetchAll();
$breakStmt=$pdo->prepare('SELECT * FROM planned_breaks WHERE user_id=? AND end_date>=CURDATE() ORDER BY start_date');$breakStmt->execute([$uid]);$breaks=$breakStmt->fetchAll();
$regionStmt=$pdo->prepare('SELECT skill_type,progress FROM skills WHERE user_id=?');$regionStmt->execute([$uid]);$regionProgress=$regionStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$worldRegions=game_world()['regions'];
$selectedRegionKey=(string)($_GET['region']??array_key_first($worldRegions));
if(!isset($worldRegions[$selectedRegionKey]))$selectedRegionKey=array_key_first($worldRegions);
$worldStateLabels=['quiet'=>'Tenang','awakening'=>'Terbangun','flourishing'=>'Berkembang'];
$pageTitle='Perjalanan';require_once __DIR__.'/../includes/header.php';
function journey_escape($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
?>
<div class="page-header journey-heading">
    <div><p class="dashboard-date font-mono">RENCANA &amp; PROGRES</p><h1 class="page-title">Perjalananmu</h1><p class="page-subtitle">Ubah tujuan besar menjadi langkah yang bisa dilakukan.</p></div>
    <a class="btn-core btn-primary" href="#new-goal"><i class="fas fa-plus me-2" aria-hidden="true"></i>Tujuan Baru</a>
</div>
<section class="world-atlas" aria-labelledby="world-atlas-title">
    <div class="world-atlas-heading"><div><p class="dashboard-date font-mono">PETA NAWASELA</p><h2 id="world-atlas-title">Duniamu tumbuh bersamamu</h2><p>Pilih wilayah untuk melihat hubungan antara aktivitas dan perubahan duniamu.</p></div><span class="world-atlas-key">0 aktivitas: Tenang<br>1-4: Terbangun · 5+: Berkembang</span></div>
    <div class="world-atlas-map">
        <img src="../assets/images/world/nawasela_sea.png" alt="" width="1920" height="1024" class="world-atlas-sea">
        <?php foreach($worldRegions as $regionKey=>$region): $regionCount=max(0,(int)($regionProgress[$region['attribute']]??0));$regionState=game_world_visual_state($regionCount); ?>
            <a href="journey.php?region=<?php echo urlencode($regionKey); ?>#world-detail" class="world-region world-region-<?php echo htmlspecialchars($regionKey); ?> state-<?php echo $regionState; ?><?php echo $selectedRegionKey===$regionKey?' is-selected':''; ?>" <?php echo $selectedRegionKey===$regionKey?'aria-current="true"':''; ?> aria-label="<?php echo htmlspecialchars($region['label'].', '.$region['attribute'].', '.$worldStateLabels[$regionState].', '.$regionCount.' aktivitas'); ?>">
                <img src="../assets/images/world/<?php echo htmlspecialchars($regionKey.'_'.$regionState); ?>.png" alt="" width="768" height="576">
                <span class="world-region-label"><strong><?php echo htmlspecialchars($region['label']); ?></strong><span><?php echo htmlspecialchars($region['attribute']); ?> · <?php echo $regionCount; ?> aktivitas</span></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php $selectedRegion=$worldRegions[$selectedRegionKey];$selectedCount=max(0,(int)($regionProgress[$selectedRegion['attribute']]??0));$selectedState=game_world_visual_state($selectedCount); ?>
    <div class="world-region-detail" id="world-detail">
        <div class="world-region-detail-art"><img src="../assets/images/world/<?php echo htmlspecialchars($selectedRegionKey.'_'.$selectedState); ?>.png" width="768" height="576" alt="<?php echo htmlspecialchars($selectedRegion['label'].': '.$worldStateLabels[$selectedState]); ?>"></div>
        <div class="world-region-detail-copy"><p class="world-state-label"><?php echo htmlspecialchars($selectedRegion['attribute'].' / '.$worldStateLabels[$selectedState]); ?></p><h3><?php echo htmlspecialchars($selectedRegion['label']); ?></h3><p><?php echo htmlspecialchars($selectedRegion['purpose']); ?></p><p class="world-real-progress"><strong><?php echo $selectedCount; ?></strong> aktivitas tercatat · Tingkat <?php echo intdiv($selectedCount,5)+1; ?></p><p class="world-progress-explanation"><?php echo $selectedState==='quiet'?'Wilayah masih tenang. Satu aktivitas akan menyalakan tanda kehidupan pertamanya.':($selectedState==='awakening'?'Wilayah mulai terbangun. Capai lima aktivitas agar bangunan dan jalurnya berkembang.':'Wilayah sudah berkembang. Tingkat atribut terus bertambah setiap lima aktivitas, meskipun ilustrasi telah mencapai tahap akhirnya.'); ?> Tidak perlu menaikkan semua atribut sama cepat.</p><a href="habits.php?new_cat=<?php echo urlencode($selectedRegion['attribute']); ?>" class="btn-core btn-outline">Rawat <?php echo htmlspecialchars($selectedRegion['attribute']); ?> <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i></a></div>
    </div>
    <details class="world-story"><summary>Cerita Lima Poros &amp; cara membaca peta</summary><p><?php echo htmlspecialchars(game_world()['intro']); ?></p><p>Peta memakai jumlah progres atribut yang sudah tersimpan, bukan persentase pemulihan yang dibuat-buat. Visual hanya memiliki tiga tahap. Physical tetap opsional; wilayah tidak terkunci dan tidak rusak ketika kamu mengambil jeda.</p></details>
</section>
<?php if($error): ?><p role="alert" class="alert alert-danger"><?=journey_escape($error)?></p><?php endif; ?>
<section class="journey-goal-create" id="new-goal">
    <details <?php echo !$goals ? 'open' : ''; ?>>
        <summary><span class="journey-create-mark"><i class="fas fa-plus" aria-hidden="true"></i></span><span><strong>Buat tujuan baru</strong><small>Tentukan arah sebelum menambahkan langkah.</small></span><i class="fas fa-chevron-down journey-create-chevron" aria-hidden="true"></i></summary>
        <form method="post" class="journey-create-form"><?=csrf_field()?><input type="hidden" name="action" value="goal">
            <label for="goal-title">Apa yang ingin kamu capai?</label><input class="form-control" id="goal-title" name="title" required maxlength="180" placeholder="Contoh: Selesaikan proyek akhir">
            <label for="goal-category">Atribut utama</label><select class="form-select" id="goal-category" name="category"><?php foreach($cats as $key=>$cat): ?><option value="<?=journey_escape($key)?>"><?=journey_escape($cat['label'])?></option><?php endforeach;?></select>
            <button class="btn-core btn-primary" type="submit">Simpan Tujuan</button>
        </form>
    </details>
</section>
<?php if(!$goals): ?><section class="journey-empty"><span><i class="fas fa-route" aria-hidden="true"></i></span><h2>Mulai dari satu tujuan</h2><p>Pilih sesuatu yang penting bagimu. Setelah itu, pecah menjadi langkah kecil yang realistis.</p><a class="btn-core btn-primary" href="#new-goal">Buat Tujuan Pertama</a></section><?php endif;?>
<?php foreach($goals as $goal):
$gid=(int)$goal['goal_id'];$ms=$pdo->prepare('SELECT * FROM goal_milestones WHERE goal_id=? ORDER BY milestone_id');$ms->execute([$gid]);$steps=$ms->fetchAll();
$done=count(array_filter($steps,fn($step)=>(int)$step['completed']===1));$pct=game_goal_progress($done,count($steps));
$links=$pdo->prepare("SELECT ga.kind,ga.activity_id,gap.milestone_id,gap.advanced_at,gm.title AS milestone_title,CASE WHEN ga.kind='habit' THEN h.habit_name ELSE q.quest_name END AS title FROM goal_activities ga LEFT JOIN goal_activity_progress gap ON gap.user_id=? AND gap.goal_id=ga.goal_id AND gap.kind=ga.kind AND gap.activity_id=ga.activity_id LEFT JOIN goal_milestones gm ON gm.milestone_id=gap.milestone_id LEFT JOIN habits h ON ga.kind='habit' AND h.habit_id=ga.activity_id AND h.user_id=? LEFT JOIN quests q ON ga.kind='quest' AND q.quest_id=ga.activity_id AND q.user_id=? WHERE ga.goal_id=?");$links->execute([$uid,$uid,$uid,$gid]);
$focus=$pdo->prepare('SELECT COALESCE(SUM(duration_seconds),0) FROM goal_focus_sessions WHERE goal_id=? AND user_id=?');$focus->execute([$gid,$uid]);$focusMinutes=intdiv((int)$focus->fetchColumn(),60);
$linkedActivities=$links->fetchAll();?>
<section class="journey-goal">
    <header class="journey-goal-header"><div><p class="journey-goal-category"><?=journey_escape($goal['category'])?></p><h2><?=journey_escape($goal['title'])?></h2><?php if(!empty($goal['target_date'])):?><p class="journey-goal-deadline"><i class="fas fa-calendar-day me-1" aria-hidden="true"></i>Target <?=journey_escape($goal['target_date'])?></p><?php endif;?></div><a class="btn-core btn-primary" href="dashboard.php?rift=1&amp;goal=<?=$gid?>"><i class="fas fa-hourglass-half me-2" aria-hidden="true"></i>Fokus</a></header>
    <div class="journey-goal-stats"><div><strong><?=$done?>/<?=(int)count($steps)?></strong><span>langkah selesai</span></div><div><strong><?=$focusMinutes?></strong><span>menit fokus</span></div><div><strong><?=$pct?>%</strong><span>progres</span></div></div>
    <div class="journey-progress-track" role="progressbar" aria-label="Progres tujuan <?=journey_escape($goal['title'])?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?=$pct?>"><span style="width: <?=$pct?>%"></span></div>
    <form method="post" class="journey-deadline-form"><?=csrf_field()?><input type="hidden" name="action" value="goal_date"><input type="hidden" name="goal_id" value="<?=$gid?>"><label for="target-<?=$gid?>">Tanggal target</label><input class="form-control" id="target-<?=$gid?>" type="date" name="target_date" value="<?=journey_escape($goal['target_date']??'')?>"><button class="btn-core btn-quiet" type="submit">Simpan Tanggal</button></form>
    <div class="journey-section-heading"><h3>Langkah tujuan</h3><span><?=count($steps)?> total</span></div>
    <?php if($steps):?><ol class="journey-milestones"><?php foreach($steps as $step):?><li class="<?=$step['completed']?'is-complete':''?>"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="goal_id" value="<?=$gid?>"><input type="hidden" name="milestone_id" value="<?=(int)$step['milestone_id']?>"><input type="hidden" name="completed" value="<?=$step['completed']?'0':'1'?>"><button type="submit" class="journey-milestone-toggle" aria-label="<?=$step['completed']?'Tandai belum selesai':'Tandai selesai'?>: <?=journey_escape($step['title'])?>"><i class="fas <?=$step['completed']?'fa-check':'fa-circle'?>" aria-hidden="true"></i></button><span><?=journey_escape($step['title'])?></span></form></li><?php endforeach;?></ol><?php else:?><p class="journey-section-empty">Tambahkan langkah pertama yang paling dekat untuk dikerjakan.</p><?php endif;?>
    <form method="post" class="journey-add-milestone"><?=csrf_field()?><input type="hidden" name="action" value="milestone"><input type="hidden" name="goal_id" value="<?=$gid?>"><label class="visually-hidden" for="milestone-<?=$gid?>">Nama langkah baru</label><input class="form-control" id="milestone-<?=$gid?>" name="title" required maxlength="180" placeholder="Tambahkan langkah berikutnya"><button class="btn-core btn-outline" type="submit"><i class="fas fa-plus me-1" aria-hidden="true"></i>Tambah Langkah</button></form>
    <details class="journey-linked">
        <summary>Aktivitas pendukung <span><?=count($linkedActivities)?></span></summary>
        <div class="journey-linked-body">
            <p>Menautkan aktivitas tidak memberikan reward tambahan.</p>
            <?php foreach ($linkedActivities as $link): ?>
                <?php if (!$link['title']) $link['title']='Aktivitas sudah dihapus'; ?>
                <div class="journey-linked-item"><a href="<?=$link['kind']==='habit'?'habits.php':'quests.php'?>"><i class="fas <?=$link['kind']==='habit'?'fa-check-square':'fa-scroll'?>" aria-hidden="true"></i><span><?=journey_escape($link['title'])?><small>Langkah: <?=journey_escape($link['milestone_title']??'Tidak ditentukan')?></small></span></a><?php if(empty($link['advanced_at'])): ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="delete_link"><input type="hidden" name="goal_id" value="<?=$gid?>"><input type="hidden" name="kind" value="<?=journey_escape($link['kind'])?>"><input type="hidden" name="activity_id" value="<?=(int)$link['activity_id']?>"><button class="journey-link-remove" type="submit" aria-label="Lepaskan tautan aktivitas"><i class="fas fa-unlink" aria-hidden="true"></i></button></form><?php endif; ?></div>
            <?php endforeach; ?>
            <?php foreach (['habit' => $habits, 'quest' => $quests] as $kind => $items): ?>
                <?php if (!$items) continue; ?>
                <form method="post" class="journey-link-form">
                    <?=csrf_field()?><input type="hidden" name="action" value="link"><input type="hidden" name="goal_id" value="<?=$gid?>"><input type="hidden" name="kind" value="<?=$kind?>">
                    <label><?=$kind === 'habit' ? 'Kebiasaan' : 'Misi'?><select class="form-select" name="activity_id" required><?php foreach ($items as $item): ?><option value="<?=(int)$item[$kind.'_id']?>"><?=journey_escape($item[$kind.'_name'])?></option><?php endforeach; ?></select></label>
                    <?php if ($steps): ?><label>Langkah tujuan<select class="form-select" name="milestone_id" required><?php foreach ($steps as $step): if ((int)$step['completed'] === 1) continue; ?><option value="<?=(int)$step['milestone_id']?>"><?=journey_escape($step['title'])?></option><?php endforeach; ?></select></label><?php else: ?><span class="text-muted small">Tambahkan langkah tujuan sebelum menautkan aktivitas.</span><?php endif; ?>
                    <button class="btn-core btn-quiet" type="submit" <?php echo !$steps ? 'disabled' : ''; ?>>Hubungkan</button>
                </form>
            <?php endforeach; ?>
        </div>
    </details>
</section>
<?php endforeach;?>
<section class="journey-secondary">
    <details class="journey-secondary-panel">
        <summary><span><i class="fas fa-calendar-minus me-2" aria-hidden="true"></i>Jeda terencana</span><small><?=count($breaks)?> aktif</small></summary>
        <div class="journey-secondary-body">
            <p>Jeda melindungi kebiasaan yang jatuh tempo dan memperpanjang tenggat misi sesuai hari yang beririsan. Catat sebelum jeda dimulai.</p>
            <?php foreach ($breaks as $break): ?>
                <div class="journey-break-row"><span><?=journey_escape($break['start_date'])?> <i class="fas fa-arrow-right" aria-hidden="true"></i> <?=journey_escape($break['end_date'])?></span>
                    <?php if ($break['start_date'] > date('Y-m-d')): ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="cancel_break"><input type="hidden" name="break_id" value="<?=(int)$break['break_id']?>"><button class="btn-core btn-quiet" type="submit">Batalkan</button></form><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <form method="post" class="journey-break-form"><?=csrf_field()?><input type="hidden" name="action" value="break"><label>Mulai<input class="form-control" type="date" name="start_date" min="<?=date('Y-m-d',strtotime('+1 day'))?>" required></label><label>Selesai<input class="form-control" type="date" name="end_date" required></label><button class="btn-core btn-outline" type="submit">Rencanakan Jeda</button></form>
        </div>
    </details>
<?php
$review = $pdo->prepare("SELECT completion_date AS d FROM habit_logs WHERE user_id=? AND status='Completed' AND completion_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 6 DAY) AND CURDATE() UNION ALL SELECT DATE(COALESCE(completed_at,completion_date,created_at)) AS d FROM quest_logs WHERE user_id=? AND status='Completed' AND DATE(COALESCE(completed_at,completion_date,created_at)) BETWEEN DATE_SUB(CURDATE(),INTERVAL 6 DAY) AND CURDATE()");
$review->execute([$uid, $uid]);
$reviewDays = $review->fetchAll(PDO::FETCH_COLUMN);
$moods = $pdo->prepare('SELECT AVG(mood_score) AS mood, AVG(energy_score) AS energy, COUNT(*) AS entries FROM journal_entries WHERE user_id=? AND entry_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 6 DAY) AND CURDATE()');
$moods->execute([$uid]);
$mood = $moods->fetch();
$hasMood = (int) ($mood['entries'] ?? 0) > 0;
?>
<details class="journey-secondary-panel journey-review">
    <summary><span><i class="fas fa-chart-line me-2" aria-hidden="true"></i>Evaluasi tujuh hari</span><small><?=count(array_unique($reviewDays))?>/7 hari aktif</small></summary>
    <div class="journey-secondary-body">
        <div class="journey-review-stats">
            <div><strong><?=count($reviewDays)?></strong><span>aktivitas selesai</span></div>
            <div><strong><?=count(array_unique($reviewDays))?>/7</strong><span>hari aktif</span></div>
            <div><strong><?=$hasMood ? number_format((float)$mood['mood'], 1) . '/5' : '-'?></strong><span>rata-rata mood</span></div>
            <div><strong><?=$hasMood ? number_format((float)$mood['energy'], 1) . '/5' : '-'?></strong><span>rata-rata energi</span></div>
        </div>
        <p>Ringkasan catatan, bukan diagnosis atau bukti sebab-akibat. Apa yang terasa mudah? Target mana yang perlu diringankan?</p>
        <a href="journal.php" class="journey-linked-item"><i class="fas fa-feather-pointed" aria-hidden="true"></i>Tulis Refleksi</a>
    </div>
</details>
<details class="journey-secondary-panel">
    <summary><span><i class="fas fa-compass me-2" aria-hidden="true"></i>Panduan atribut</span><small>5 atribut</small></summary>
    <div class="journey-attribute-guide"><div><strong>Intelligence</strong><span>Belajar dan pemecahan masalah</span></div><div><strong>Physical</strong><span>Gerak dan kebugaran</span></div><div><strong>Health</strong><span>Tidur, nutrisi, dan pemulihan</span></div><div><strong>Wealth</strong><span>Keuangan dan pekerjaan</span></div><div><strong>Social</strong><span>Hubungan dan komunitas</span></div></div>
</details>
</section>
<?php require_once __DIR__.'/../includes/footer.php';?>
<script>
document.querySelectorAll('a[href="#new-goal"]').forEach(function(link) {
    link.addEventListener('click', function() {
        var details=document.querySelector('#new-goal details');
        if(details) details.open=true;
        var input=document.getElementById('goal-title');
        if(input) input.focus({preventScroll:true});
    });
});
</script>
