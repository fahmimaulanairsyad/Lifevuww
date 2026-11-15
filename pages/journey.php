<?php
require_once __DIR__ . '/../config/database.php';
enforce_login();
$uid = (int) $_SESSION['user_id'];
$error = '';
$cats = game_categories();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    try {
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
            $owned = $pdo->prepare('SELECT goal_id FROM life_goals WHERE goal_id=? AND user_id=?'); $owned->execute([$goalId,$uid]);
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
            } elseif ($action === 'toggle') {
                $pdo->prepare('UPDATE goal_milestones SET completed=? WHERE milestone_id=? AND goal_id=?')->execute([isset($_POST['completed'])?1:0,(int)($_POST['milestone_id']??0),$goalId]);
            } elseif ($action === 'link') {
                $kind=$_POST['kind']??''; $aid=(int)($_POST['activity_id']??0);
                if (!in_array($kind,['habit','quest'],true)) throw new RuntimeException('Jenis aktivitas tidak valid.');
                $table=$kind==='habit'?'habits':'quests'; $key=$kind==='habit'?'habit_id':'quest_id';
                $ownedActivity=$pdo->prepare("SELECT $key FROM $table WHERE $key=? AND user_id=?");$ownedActivity->execute([$aid,$uid]);
                if (!$ownedActivity->fetchColumn()) throw new RuntimeException('Aktivitas tidak ditemukan.');
                $pdo->prepare('INSERT IGNORE INTO goal_activities (goal_id,kind,activity_id) VALUES (?,?,?)')->execute([$goalId,$kind,$aid]);
            }
        }
        header('Location: journey.php');exit;
    } catch (RuntimeException $e) { $error=$e->getMessage(); }
}
$goalsStmt=$pdo->prepare('SELECT * FROM life_goals WHERE user_id=? ORDER BY goal_id DESC');$goalsStmt->execute([$uid]);$goals=$goalsStmt->fetchAll();
$habitsStmt=$pdo->prepare('SELECT habit_id,habit_name FROM habits WHERE user_id=?');$habitsStmt->execute([$uid]);$habits=$habitsStmt->fetchAll();
$questsStmt=$pdo->prepare('SELECT quest_id,quest_name FROM quests WHERE user_id=?');$questsStmt->execute([$uid]);$quests=$questsStmt->fetchAll();
$breakStmt=$pdo->prepare('SELECT * FROM planned_breaks WHERE user_id=? AND end_date>=CURDATE() ORDER BY start_date');$breakStmt->execute([$uid]);$breaks=$breakStmt->fetchAll();
$pageTitle='Perjalanan';require_once __DIR__.'/../includes/header.php';
function journey_escape($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
?>
<div class="page-header"><h1 class="page-title">Perjalananmu</h1><p class="text-muted">Bangun tujuan dengan langkah kecil, kebiasaan, dan waktu fokus.</p></div>
<?php if($error): ?><p role="alert" class="text-danger"><?=journey_escape($error)?></p><?php endif; ?>
<section class="bento-panel mb-4"><h2 class="h5">Tujuan baru</h2>
<form method="post" class="d-flex flex-column gap-2"><?=csrf_field()?><input type="hidden" name="action" value="goal">
<label for="goal-title">Apa yang ingin kamu capai?</label><input class="form-control" id="goal-title" name="title" required maxlength="180">
<label for="goal-category">Atribut utama</label><select class="form-select" id="goal-category" name="category"><?php foreach($cats as $key=>$cat): ?><option value="<?=journey_escape($key)?>"><?=journey_escape($cat['label'])?></option><?php endforeach;?></select>
<button class="btn-core btn-primary align-self-start">Simpan Tujuan</button></form></section>
<?php if(!$goals): ?><p>Belum ada tujuan. Mulai dari satu hal yang ingin kamu selesaikan.</p><?php endif;?>
<?php foreach($goals as $goal):
$gid=(int)$goal['goal_id'];$ms=$pdo->prepare('SELECT * FROM goal_milestones WHERE goal_id=? ORDER BY milestone_id');$ms->execute([$gid]);$steps=$ms->fetchAll();
$done=count(array_filter($steps,fn($step)=>(int)$step['completed']===1));$pct=game_goal_progress($done,count($steps));
$links=$pdo->prepare("SELECT ga.kind,ga.activity_id,CASE WHEN ga.kind='habit' THEN h.habit_name ELSE q.quest_name END AS title FROM goal_activities ga LEFT JOIN habits h ON ga.kind='habit' AND h.habit_id=ga.activity_id AND h.user_id=? LEFT JOIN quests q ON ga.kind='quest' AND q.quest_id=ga.activity_id AND q.user_id=? WHERE ga.goal_id=?");$links->execute([$uid,$uid,$gid]);
$focus=$pdo->prepare('SELECT COALESCE(SUM(duration_seconds),0) FROM goal_focus_sessions WHERE goal_id=? AND user_id=?');$focus->execute([$gid,$uid]);?>
<section class="bento-panel mb-4"><h2 class="h5"><?=journey_escape($goal['title'])?></h2><p class="text-muted"><?=journey_escape($goal['category'])?> · <?=$done?>/<?=count($steps)?> langkah · <?=$pct?>% · <?=intdiv((int)$focus->fetchColumn(),60)?> menit fokus<?php if(!empty($goal['target_date'])):?> · Target <?=journey_escape($goal['target_date'])?><?php endif;?></p>
<form method="post" class="d-flex flex-wrap gap-2 mb-3"><?=csrf_field()?><input type="hidden" name="action" value="goal_date"><input type="hidden" name="goal_id" value="<?=$gid?>"><label>Tanggal target<input class="form-control" type="date" name="target_date" value="<?=journey_escape($goal['target_date']??'')?>"></label><button class="btn-core btn-outline align-self-end">Simpan Target</button></form>
<?php foreach($steps as $step):?><form method="post" class="d-flex gap-2 align-items-center mb-2"><?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="goal_id" value="<?=$gid?>"><input type="hidden" name="milestone_id" value="<?=(int)$step['milestone_id']?>"><label><input type="checkbox" name="completed" <?=$step['completed']?'checked':''?>> <?=journey_escape($step['title'])?></label><button class="btn-core btn-outline">Simpan</button></form><?php endforeach;?>
<form method="post" class="d-flex gap-2 flex-wrap my-3"><?=csrf_field()?><input type="hidden" name="action" value="milestone"><input type="hidden" name="goal_id" value="<?=$gid?>"><label class="flex-grow-1">Langkah berikutnya<input class="form-control" name="title" required maxlength="180"></label><button class="btn-core btn-outline align-self-end">Tambah Langkah</button></form>
<details><summary>Aktivitas pendukung</summary><p class="small text-muted mt-2">Tautan tidak memberi reward tambahan. Reward tetap mengikuti penyelesaian aktivitas asal.</p>
<?php foreach($links->fetchAll() as $link):if(!$link['title'])continue;?><p><a href="<?=$link['kind']==='habit'?'habits.php':'quests.php'?>"><?=journey_escape($link['title'])?></a></p><?php endforeach;?>
<?php foreach(['habit'=>$habits,'quest'=>$quests] as $kind=>$items):if(!$items)continue;?><form method="post" class="d-flex flex-wrap gap-2 my-2"><?=csrf_field()?><input type="hidden" name="action" value="link"><input type="hidden" name="goal_id" value="<?=$gid?>"><input type="hidden" name="kind" value="<?=$kind?>"><label><?=$kind==='habit'?'Kebiasaan':'Misi'?><select class="form-select" name="activity_id"><?php foreach($items as $item):?><option value="<?=(int)$item[$kind.'_id']?>"><?=journey_escape($item[$kind.'_name'])?></option><?php endforeach;?></select></label><button class="btn-core btn-outline align-self-end">Hubungkan</button></form><?php endforeach;?></details>
<a class="btn-core btn-primary mt-3" href="dashboard.php?rift=1&amp;goal=<?=$gid?>">Fokus ke Tujuan</a></section>
<?php endforeach;?>
<section class="bento-panel mb-4"><h2 class="h5">Jeda terencana</h2><p class="text-muted small">Jeda melindungi kebiasaan yang jatuh tempo dan memperpanjang tenggat misi sebanyak hari jeda yang beririsan dengannya. Berlaku hanya untuk jeda yang dicatat sebelum masa jeda dimulai. Mulai paling cepat tanggal <?=date('d-m-Y',strtotime('+1 day'))?>.</p>
<?php foreach($breaks as $break):?><p><?=journey_escape($break['start_date'])?> sampai <?=journey_escape($break['end_date'])?></p><?php if($break['start_date']>date('Y-m-d')):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="cancel_break"><input type="hidden" name="break_id" value="<?=(int)$break['break_id']?>"><button class="btn-core btn-outline mb-3">Batalkan Jeda</button></form><?php endif;endforeach;?>
<form method="post" class="d-flex flex-wrap gap-3"><?=csrf_field()?><input type="hidden" name="action" value="break"><label>Mulai<input class="form-control" type="date" name="start_date" min="<?=date('Y-m-d',strtotime('+1 day'))?>" required></label><label>Selesai<input class="form-control" type="date" name="end_date" required></label><button class="btn-core btn-outline align-self-end">Rencanakan Jeda</button></form></section>
<?php
$review=$pdo->prepare("SELECT completion_date AS d FROM habit_logs WHERE user_id=? AND status='Completed' AND completion_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 6 DAY) AND CURDATE() UNION ALL SELECT DATE(COALESCE(completed_at,completion_date,created_at)) AS d FROM quest_logs WHERE user_id=? AND status='Completed' AND DATE(COALESCE(completed_at,completion_date,created_at)) BETWEEN DATE_SUB(CURDATE(),INTERVAL 6 DAY) AND CURDATE()");$review->execute([$uid,$uid]);$reviewDays=$review->fetchAll(PDO::FETCH_COLUMN);
$moods=$pdo->prepare('SELECT AVG(mood_score) AS mood,AVG(energy_score) AS energy,COUNT(*) AS entries FROM journal_entries WHERE user_id=? AND entry_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 6 DAY) AND CURDATE()');$moods->execute([$uid]);$mood=$moods->fetch();
?>
<section class="bento-panel mb-4"><h2 class="h5">Evaluasi tujuh hari</h2><p><?=date('d-m-Y',strtotime('-6 days'))?> sampai <?=date('d-m-Y')?> · <?=count($reviewDays)?> aktivitas selesai · <?=count(array_unique($reviewDays))?>/7 hari aktif</p>
<?php if((int)$mood['entries']>0):?><p>Rata-rata mood <?=number_format((float)$mood['mood'],1)?>/5 dan energi <?=number_format((float)$mood['energy'],1)?>/5 dari <?=(int)$mood['entries']?> catatan.</p><?php else:?><p>Belum ada catatan mood dan energi pada periode ini.</p><?php endif;?>
<p class="text-muted">Angka ini merangkum catatanmu, bukan diagnosis atau bukti sebab-akibat. Apa yang terasa mudah? Target mana yang perlu diringankan?</p><a href="journal.php" class="btn-core btn-outline">Tulis Refleksi</a></section>
<section class="bento-panel"><h2 class="h5">Panduan atribut</h2><p>Intelligence: belajar dan pemecahan masalah. Physical: gerak dan kebugaran. Health: tidur, makan, dan pemulihan. Wealth: keuangan dan pekerjaan. Social: hubungan dan komunitas.</p><p class="text-muted">Pilih atribut yang mendukung tujuanmu. Semua atribut tidak harus tumbuh sama cepat.</p></section>
<?php require_once __DIR__.'/../includes/footer.php';?>
